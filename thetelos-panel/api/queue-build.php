<?php
/**
 * queue-build.php — Batch dosyasındaki yazarların eserlerini adım adım ekle
 * POST: batch_id, chunk (kaç yazarı işle, varsayılan 5)
 * Her çağrıda 5 yazarın eserlerini Firebase→OL→LLM ile çeker, batch'e ekler
 * Dönüş: { ok, done:bool, authors_built, authors_total, books_added }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
set_time_limit(150);

$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_POST['batch_id'] ?? ''));
$chunk    = max(1, min(10, (int)($_POST['chunk'] ?? 3)));
if (!$batch_id) { echo json_encode(['ok'=>false,'error'=>'batch_id gerekli']); exit; }

$jobs_dir   = dirname(__DIR__) . '/jobs';
$batch_file = "$jobs_dir/{$batch_id}.json";
if (!file_exists($batch_file)) { echo json_encode(['ok'=>false,'error'=>'Kuyruk bulunamadı']); exit; }

// Dosyayı kilitle
$fp = fopen($batch_file, 'r+');
if (!flock($fp, LOCK_EX)) { fclose($fp); echo json_encode(['ok'=>false,'error'=>'Kilit alınamadı']); exit; }
fseek($fp, 0); $raw=''; while(!feof($fp)) $raw .= fread($fp, 65536);
$batch = json_decode($raw, true);
flock($fp, LOCK_UN); fclose($fp);

if (!$batch) { echo json_encode(['ok'=>false,'error'=>'Batch okunamadı']); exit; }

$authors      = $batch['authors']      ?? [];
$authors_built = (int)($batch['authors_built'] ?? 0);
$authors_total = count($authors);

if ($authors_built >= $authors_total) {
    $batch['status']    = 'running';
    $batch['build_msg'] = 'Kuyruk hazır.';
    file_put_contents($batch_file, json_encode($batch, JSON_UNESCAPED_UNICODE));
    echo json_encode(['ok'=>true,'done'=>true,'authors_built'=>$authors_built,'authors_total'=>$authors_total,'books_added'=>0]);
    exit;
}

define('FIREBASE_URL','https://thetelos-db-default-rtdb.europe-west1.firebasedatabase.app');

function qb_fb_norm($n){ return strtr(mb_strtolower(trim($n)),['.'=>'_','#'=>'_','$'=>'_','['=>'_',']'=>'_','/'=>'_']); }

function qb_firebase($author){
    $url = FIREBASE_URL.'/yazarlar/'.rawurlencode(qb_fb_norm($author)).'.json?limitToFirst=100';
    $ch  = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8]);
    $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($code!==200||!$r||$r==='null') return [];
    $d=json_decode($r,true); if(!is_array($d)) return [];
    $out=[];
    foreach($d as $t){ $t=trim($t); if($t) $out[]=['title'=>$t,'cover'=>'','year'=>'']; }
    return $out;
}

function qb_http_get($url){
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>18,CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_HTTPHEADER=>['Accept: application/json','User-Agent: thetelos.org/1.0']]);
    $r=curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return ($c===200&&$r)?json_decode($r,true):null;
}

function qb_tok($s){
    $stop=array_flip(explode(' ','the a an of on to and by with in for from about into that this his her their complete works collected selected early late major minor new revised critical introduction guide study reader companion handbook anthology essays'));
    $s=mb_strtolower($s,'UTF-8');
    $t=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s); if($t!==false&&$t!=='') $s=$t;
    $s=preg_replace('/[^a-z0-9]+',' ',$s); $out=[];
    foreach(explode(' ',trim($s)) as $w){ if(strlen($w)>=4&&!isset($stop[$w])) $out[]=$w; }
    return $out;
}
function qb_tok_match($a,$b){ $n=min(strlen($a),strlen($b)); if($n<4) return $a===$b; $k=0; while($k<$n&&$a[$k]===$b[$k]) $k++; return $k>=5; }
function qb_titles_same($ta,$tb){ if(!$ta||!$tb) return false; $small=count($ta)<=count($tb)?$ta:$tb; $big=count($ta)<=count($tb)?$tb:$ta; $m=0; foreach($small as $x){ foreach($big as $y){ if(qb_tok_match($x,$y)){$m++;break;} } } $min=count($small); return $m>=2||($m>=1&&$m===$min); }

/**
 * Python scriptindeki gibi: yazar için 1 GB isteği → title→cover_url map döner.
 * Sonra her kitap için qb_cover_from_map() ile eşleştir (N istek → 1 istek).
 */
function qb_gb_covers_for_author($author) {
    $url = 'https://www.googleapis.com/books/v1/volumes?' . http_build_query([
        'q'          => 'inauthor:"' . $author . '"',
        'maxResults' => 40,
        'langRestrict'=> 'en',
        'printType'  => 'books',
        'fields'     => 'items(volumeInfo(title,imageLinks))',
        'key'        => GOOGLE_BOOKS_KEY,
    ]);
    $data = qb_http_get($url);
    $map  = [];
    foreach ($data['items'] ?? [] as $item) {
        $t = mb_strtolower(trim($item['volumeInfo']['title'] ?? ''));
        if (!$t) continue;
        $lnk = $item['volumeInfo']['imageLinks'] ?? [];
        $c   = $lnk['thumbnail'] ?? ($lnk['smallThumbnail'] ?? '');
        if (!$c) continue;
        $c = str_replace(['http://','&edge=curl'], ['https://',''], $c);
        $c = preg_replace('/zoom=\d/', 'zoom=3', $c);
        $map[$t] = $c;
    }
    return $map;
}

function qb_cover_from_map($title, $orig, $map) {
    if (empty($map)) return '';
    $try = [mb_strtolower($title)];
    if ($orig) $try[] = mb_strtolower($orig);
    // Exact match
    foreach ($try as $t) {
        if (isset($map[$t])) return $map[$t];
    }
    // Contains match — catches "Aristotle's Nicomachean Ethics" for "Nicomachean Ethics"
    foreach ($try as $t) {
        if (strlen($t) < 5) continue;
        foreach ($map as $gt => $url) {
            if (str_contains($gt, $t) || str_contains($t, $gt)) return $url;
        }
    }
    return '';
}

function qb_openlibrary($author){
    // ── Adım 1: Wikidata'dan yazar entity bul ─────────────────────
    $wd_search = qb_http_get('https://www.wikidata.org/w/api.php?'.http_build_query(['action'=>'wbsearchentities','search'=>$author,'type'=>'item','language'=>'en','limit'=>5,'format'=>'json']));
    $entity_id = null;
    foreach($wd_search['search']??[] as $r){
        $desc=mb_strtolower($r['description']??'');
        if(preg_match('/philosopher|writer|author|poet|novelist|playwright|historian|theologian|mathematician|scientist|jurist|economist|psychologist|sociologist|scholar|thinker/',$desc)){
            $entity_id=$r['id']; break;
        }
    }
    if(!$entity_id&&!empty($wd_search['search'][0]['id'])) $entity_id=$wd_search['search'][0]['id'];

    if($entity_id){
        // ── Adım 2: Yazar doğum/ölüm yılları ─────────────────────
        $date_q='SELECT ?born ?died WHERE { OPTIONAL{wd:'.$entity_id.' wdt:P569 ?born.} OPTIONAL{wd:'.$entity_id.' wdt:P570 ?died.} } LIMIT 1';
        $dd=qb_http_get('https://query.wikidata.org/sparql?'.http_build_query(['query'=>$date_q,'format'=>'json']));
        $row=$dd['results']['bindings'][0]??[];
        $born_y=''; $died_y='';
        if(!empty($row['born']['value'])) { preg_match('/(\d{4})/',$row['born']['value'],$m); $born_y=$m[1]??''; }
        if(!empty($row['died']['value'])) { preg_match('/(\d{4})/',$row['died']['value'],$m); $died_y=$m[1]??''; }

        // ── Adım 3: Eserleri çek (P50 = yazar) ───────────────────
        // Şiir (Q482994), tekil mektup (Q133492), bölüm (Q1931185) hariç tut
        $sparql='SELECT DISTINCT ?work ?workLabel ?origLabel ?date WHERE {'
            .'?work wdt:P50 wd:'.$entity_id.'.'
            .'?work wdt:P31 ?type.'
            .'FILTER(?type IN (wd:Q571,wd:Q7725634,wd:Q49848,wd:Q8261,wd:Q162606,wd:Q36279,wd:Q25379,wd:Q11826511,wd:Q17537576))'
            .'FILTER NOT EXISTS { ?work wdt:P31 wd:Q482994. }'
            .'FILTER NOT EXISTS { ?work wdt:P31 wd:Q5185279. }'
            .'FILTER NOT EXISTS { ?work wdt:P31 wd:Q1931185. }'
            .'OPTIONAL{?work wdt:P577 ?date.}'
            .'OPTIONAL{?work wdt:P1476 ?origLabel.}'
            .'SERVICE wikibase:label{bd:serviceParam wikibase:language "en,fr,de,it,es,la".}'
            .'} ORDER BY ?date LIMIT 100';
        $wd=qb_http_get('https://query.wikidata.org/sparql?'.http_build_query(['query'=>$sparql,'format'=>'json']));
        $bindings=$wd['results']['bindings']??[];

        if(count($bindings)>=3){
            $author_last=preg_replace('/^.+\s/','',$author);
            // Google Books'tan yazar için 1 toplu istek → cover map
            $cover_map = qb_gb_covers_for_author($author);
            $out=[]; $seen_tok=[];
            foreach($bindings as $b){
                $title=trim($b['workLabel']['value']??'');
                if(!$title||preg_match('/^Q\d+$/',$title)) continue;
                $year=''; if(!empty($b['date']['value'])){preg_match('/(\d{4})/',$b['date']['value'],$m);$year=$m[1]??'';}
                $orig=trim($b['origLabel']['value']??'');
                if(mb_strtolower($orig)===mb_strtolower($title)) $orig='';

                // Tarih validasyonu
                if($year&&$born_y&&(int)$year<((int)$born_y-5)) continue;
                if($year&&$died_y&&(int)$year>((int)$died_y+40)) continue;

                // İkincil literatür / derleme filtresi
                if(strlen($author_last)>=4&&preg_match('/\b(of|on|to|about|after|against|beyond|from|with)\s+'.preg_quote($author_last,'/').'\b/i',$title)) continue;
                if(preg_match('/\b(portable|reader|anthology|selected works|selected writings|compendium|handbook|encyclopedia|introduction to|readings in|letters of|letters to)\b/i',$title)) continue;

                // Dedup — başlık ve orijinal başlık her ikisini de kontrol et
                $tok=qb_tok($title);
                $tok_orig=$orig?qb_tok($orig):[];
                $dup=false;
                foreach($seen_tok as $st){
                    if(qb_titles_same($tok,$st)||($tok_orig&&qb_titles_same($tok_orig,$st))){$dup=true;break;}
                }
                if($dup) continue;
                $seen_tok[]=$tok;
                if($tok_orig) $seen_tok[]=$tok_orig;

                // Kapak: Google Books cover map'ten eşleştir
                $cover = qb_cover_from_map($title, $orig, $cover_map);
                $out[]=['title'=>$title,'original'=>$orig,'cover'=>$cover,'year'=>$year];
            }
            if(count($out)>=3) return $out;
        }
    }

    // ── FALLBACK: OpenLibrary two-step ────────────────────────────
    $ad=qb_http_get('https://openlibrary.org/search/authors.json?q='.urlencode($author).'&limit=5');
    $author_key=null; $q=mb_strtolower($author);
    foreach($ad['docs']??[] as $a){
        $name=mb_strtolower(trim($a['name']??''));
        if($name===$q||str_contains($name,$q)||str_contains($q,$name)){$author_key=$a['key']??null;break;}
    }
    if(!$author_key&&!empty($ad['docs'][0]['key'])) $author_key=$ad['docs'][0]['key'];
    if(!$author_key) return [];

    $wd2=qb_http_get('https://openlibrary.org'.$author_key.'/works.json?limit=50');
    if(!$wd2) return [];

    $author_parts=array_filter(explode(' ',mb_strtolower($author)));
    $author_last2=end($author_parts);
    $out2=[]; $seen2=[];
    foreach($wd2['entries']??[] as $entry){
        $t=trim($entry['title']??''); if(!$t) continue;
        $k=mb_strtolower($t); if(isset($seen2[$k])) continue; $seen2[$k]=true;
        $was=$entry['authors']??[];
        if(!empty($was)){ $ok=false; foreach($was as $wa){ if(($wa['author']['key']??($wa['key']??''))===$author_key){$ok=true;break;} } if(!$ok) continue; }
        if(strlen($author_last2)>=4){ if(preg_match('/\b(of|on|to|about|and|by)\s+'.preg_quote($author_last2,'/').'\b/i',$t)) continue; if(trim(mb_strtolower($t))===$author_last2) continue; }
        $cover=''; if(!empty($entry['covers'][0])&&$entry['covers'][0]>0) $cover='https://covers.openlibrary.org/b/id/'.$entry['covers'][0].'-M.jpg';
        $year=''; if(!empty($entry['first_publish_date'])){preg_match('/\d{4}/',$entry['first_publish_date'],$m);$year=$m[0]??'';}
        $out2[]=['title'=>$t,'original'=>'','cover'=>$cover,'year'=>$year];
    }
    return $out2;
}

function qb_llm($author){
    $ch=curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>30,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>DEEPSEEK_MODEL,'max_tokens'=>1500,
            'messages'=>[['role'=>'user','content'=>"List the most important works by {$author}. Return ONLY JSON: [{\"title\":\"...\",\"year\":\"...\"}]"]]])]);
    $r=curl_exec($ch); curl_close($ch);
    $d=json_decode($r,true); $txt=$d['choices'][0]['message']['content']??'';
    $txt=preg_replace('/```json|```/i','',$txt);
    $s=strpos($txt,'['); $e=strrpos($txt,']');
    $arr=($s!==false&&$e>$s)?json_decode(substr($txt,$s,$e-$s+1),true):[];
    if(!is_array($arr)) return [];
    $out=[];
    foreach($arr as $it){ $t=trim($it['title']??''); if($t) $out[]=['title'=>$t,'cover'=>'','year'=>(string)($it['year']??'')]; }
    return $out;
}

/**
 * Returns true if the title looks non-English and needs "English (Original)" formatting.
 */
function qb_needs_normalization($title){
    // Already has parentheses → already formatted or ambiguous, skip
    if(preg_match('/\([^)]+\)/',$title)) return false;
    // Non-ASCII characters (Greek, Arabic, Cyrillic, Hebrew, CJK, etc.)
    if(preg_match('/[^\x00-\x7F]/',$title)) return true;
    // Latin / Greek / medieval prefixes common in classical works
    if(preg_match('/^(De |In |Ad |Contra |Super |Pro |Summa |Quaestio|Epistola|Tractatus|Commentar)/i',$title)) return true;
    // French / Italian / Spanish / German articles or words at start
    if(preg_match('/^(La |Le |Les |Il |Gli |Lo |Los |Las |El |Ein |Die |Das |Der |Une |Un |Sur |Del |Les |Über )/i',$title)) return true;
    return false;
}

/**
 * Batch-normalize non-English titles to "English Title (Original Title)" via DeepSeek.
 * $pairs = [['title'=>..., 'author'=>...], ...]  (only titles needing normalization)
 * Returns map: original_title => normalized_title
 */
function qb_normalize_batch($pairs){
    if(empty($pairs)) return [];
    $lines='';
    foreach($pairs as $i=>$p) $lines.=($i+1).". \"{$p['title']}\" — by {$p['author']}\n";
    $prompt = "The following book titles may be in Latin, Greek, Arabic, French, or another non-English language.\n"
            . "For each: if the title is NOT in English, rewrite it as \"English Title (Original Title)\" where English Title is the well-known English name and Original Title is the original-language title.\n"
            . "If the title is already in English (or has no well-known English equivalent), return it unchanged.\n"
            . "Return ONLY a JSON array with objects {\"n\":1,\"title\":\"...\"}. Same order, same count.\n\n"
            . $lines;
    $ch=curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>40,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>DEEPSEEK_MODEL,'max_tokens'=>2000,
            'messages'=>[['role'=>'user','content'=>$prompt]]])]);
    $r=curl_exec($ch); curl_close($ch);
    $d=json_decode($r,true); $txt=$d['choices'][0]['message']['content']??'';
    $txt=preg_replace('/```json|```/i','',$txt);
    $s=strpos($txt,'['); $e=strrpos($txt,']');
    $arr=($s!==false&&$e>$s)?json_decode(substr($txt,$s,$e-$s+1),true):[];
    if(!is_array($arr)) return [];
    $map=[];
    foreach($arr as $idx=>$item){
        $n=(int)($item['n']??($idx+1))-1; // 0-based
        if(isset($pairs[$n])) $map[$pairs[$n]['title']]=trim($item['title']??$pairs[$n]['title']);
    }
    return $map;
}

// Sonraki chunk kadar yazarı işle
$new_books   = [];
$end         = min($authors_built + $chunk, $authors_total);

for ($i = $authors_built; $i < $end; $i++) {
    $author_name = trim($authors[$i]['author'] ?? '');
    if (!$author_name) continue;

    $works = qb_firebase($author_name);
    if (empty($works)) $works = qb_openlibrary($author_name);
    if (empty($works)) $works = qb_llm($author_name);

    foreach ($works as $w) {
        $t    = trim($w['title']   ?? ''); if (!$t) continue;
        $orig = trim($w['original'] ?? '');

        // Sahte başlıkları filtrele (Socrates vs.)
        if (preg_match('/^(none|no\s+\w+\s+works|no\s+known|no\s+extant|not\s+applicable)/i', $t)) continue;

        // "English Title (Original Title)" formatı — Wikidata origLabel varsa direkt kullan
        if ($orig && mb_strtolower($orig) !== mb_strtolower($t) && !preg_match('/\([^)]+\)/', $t)) {
            $t = "$t ($orig)";
        }

        $new_books[] = [
            'book_title'  => $t,
            'author_name' => $author_name,
            'cover_url'   => $w['cover']  ?? '',
            'year'        => $w['year']   ?? '',
            'status'      => 'pending',
            'post_id'=>null,'post_url'=>null,'edit_url'=>null,'cover_set'=>false,'error'=>'',
        ];
    }
}

// Normalize non-English titles to "English Title (Original Title)"
$to_normalize = [];
foreach($new_books as $nb){
    if(qb_needs_normalization($nb['book_title'])){
        $to_normalize[]=['title'=>$nb['book_title'],'author'=>$nb['author_name']];
    }
}
if(!empty($to_normalize)){
    $norm_map = qb_normalize_batch($to_normalize);
    foreach($new_books as &$nb){
        if(isset($norm_map[$nb['book_title']])) $nb['book_title']=$norm_map[$nb['book_title']];
    }
    unset($nb);
}

// Batch dosyasını güncelle (kilitle)
$fp2 = fopen($batch_file, 'r+');
flock($fp2, LOCK_EX);
fseek($fp2,0); $raw2=''; while(!feof($fp2)) $raw2 .= fread($fp2,65536);
$b2 = json_decode($raw2,true) ?: $batch;

$b2['books']         = array_merge($b2['books']??[], $new_books);
$b2['total']         = count($b2['books']);
$b2['authors_built'] = $end;
$b2['build_msg']     = "{$end}/{$authors_total} yazar işlendi, " . count($b2['books']) . ' eser hazır';
if ($end >= $authors_total) {
    $b2['status']    = 'running';
    $b2['build_msg'] = 'Kuyruk hazır — ' . count($b2['books']) . ' eser.';
}

fseek($fp2,0); ftruncate($fp2,0);
fwrite($fp2, json_encode($b2, JSON_UNESCAPED_UNICODE));
fflush($fp2); flock($fp2,LOCK_UN); fclose($fp2);

echo json_encode([
    'ok'            => true,
    'done'          => ($end >= $authors_total),
    'authors_built' => $end,
    'authors_total' => $authors_total,
    'books_added'   => count($new_books),
    'total_books'   => count($b2['books']),
    'build_msg'     => $b2['build_msg'],
], JSON_UNESCAPED_UNICODE);
