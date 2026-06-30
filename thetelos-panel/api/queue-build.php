<?php
/**
 * queue-build.php — Batch dosyasındaki yazarların eserlerini adım adım ekle
 * POST: batch_id, chunk (kaç yazarı işle, varsayılan 5)
 * Her çağrıda 5 yazarın eserlerini Firebase→OL→LLM ile çeker, batch'e ekler
 * Dönüş: { ok, done:bool, authors_built, authors_total, books_added }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
// Yetki: panel session'ı, ya da cron'un kullandığı dahili token / CLI.
$qb_itok     = hash('sha256', WP_APP_PASS . '|tls-batch-worker');
$qb_internal = isset($_POST['_itok']) && hash_equals($qb_itok, (string)$_POST['_itok']);
$qb_is_cli   = (PHP_SAPI === 'cli');
if (empty($_SESSION['tls_auth']) && !$qb_internal && !$qb_is_cli) { http_response_code(401); exit; }
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
    $batch['status']    = !empty($batch['list_only']) ? 'list_ready' : 'running';
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
function qb_tok_match($a,$b){ $n=min(strlen($a),strlen($b)); if($n<5) return $a===$b; $k=0; while($k<$n&&$a[$k]===$b[$k]) $k++; return $k>=5; }
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
    // OpenLibrary iki adım: yazar key → eserler
    $ad = qb_http_get('https://openlibrary.org/search/authors.json?q='.urlencode($author).'&limit=5');
    $author_key = null;
    $q = mb_strtolower($author);
    foreach($ad['docs']??[] as $a){
        $name = mb_strtolower(trim($a['name']??''));
        if($name===$q||str_contains($name,$q)||str_contains($q,$name)){$author_key=$a['key']??null;break;}
    }
    if(!$author_key&&!empty($ad['docs'][0]['key'])) $author_key=$ad['docs'][0]['key'];
    if(!$author_key) return [];

    $data = qb_http_get('https://openlibrary.org'.$author_key.'/works.json?limit=50');
    if(!$data) return [];

    $author_parts = array_filter(explode(' ', mb_strtolower($author)));
    $author_last  = end($author_parts);
    $out = []; $seen = [];
    foreach($data['entries']??[] as $entry){
        $t = trim($entry['title']??''); if(!$t) continue;
        $k = mb_strtolower($t); if(isset($seen[$k])) continue; $seen[$k]=true;

        // Sadece bu yazara ait eserler
        $was = $entry['authors']??[];
        if(!empty($was)){
            $ok = false;
            foreach($was as $wa){
                if(($wa['author']['key']??($wa['key']??''))===$author_key){$ok=true;break;}
            }
            if(!$ok) continue;
        }

        // "X of/on Kant" gibi ikincil literatürü at
        if(strlen($author_last)>=4){
            if(preg_match('/\b(of|on|to|about|and|by)\s+'.preg_quote($author_last,'/').'\\b/i',$t)) continue;
            if(trim(mb_strtolower($t))===$author_last) continue;
        }

        $cover = '';
        if(!empty($entry['covers'][0])&&$entry['covers'][0]>0)
            $cover = 'https://covers.openlibrary.org/b/id/'.$entry['covers'][0].'-M.jpg';
        $year = '';
        if(!empty($entry['first_publish_date'])){preg_match('/\d{4}/',$entry['first_publish_date'],$m);$year=$m[0]??'';}

        $out[] = ['title'=>$t,'original'=>'','cover'=>$cover,'year'=>$year];
    }
    return $out;
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


// Sonraki chunk kadar yazarı işle
$new_books   = [];
$end         = min($authors_built + $chunk, $authors_total);
$dbg         = ['ol'=>0,'fb'=>0,'llm'=>0,'none'=>0];  // son chunk kaynak teşhisi

for ($i = $authors_built; $i < $end; $i++) {
    $author_name = trim($authors[$i]['author'] ?? '');
    if (!$author_name) continue;

    $works = qb_openlibrary($author_name);              $src = $works ? 'ol' : '';
    if (empty($works)) { $works = qb_firebase($author_name); if ($works) $src = 'fb'; }
    if (empty($works)) { $works = qb_llm($author_name);      if ($works) $src = 'llm'; }
    $dbg[$src ?: 'none']++;

    $author_last_main = preg_replace('/^.+\s/', '', $author_name);
    foreach ($works as $w) {
        $t    = trim($w['title']   ?? ''); if (!$t) continue;
        $orig = trim($w['original'] ?? '');

        // Sahte başlıkları filtrele (Socrates vs.)
        if (preg_match('/^(none|no\s+(\w+\s+)?works|no\s+known|no\s+extant|not\s+applicable)/i', $t)) continue;

        // İkincil literatür / derleme filtresi (Firebase ve LLM sonuçları için de geçerli)
        if (preg_match('/\b(portable|reader|anthology|selected works|selected writings|compendium|handbook|encyclopedia|introduction to|readings in|letters of|letters to|essential texts|primary texts|key texts|complete texts)\b/i', $t)) continue;
        if (strlen($author_last_main) >= 4) {
            if (preg_match('/\b(of|on|to|about|after|against|beyond|from|with)\s+'.preg_quote($author_last_main,'/').'\\b/i', $t)) continue;
            if (trim(mb_strtolower($t)) === mb_strtolower($author_last_main)) continue;
        }

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

// Batch dosyasını güncelle (kilitle)
$fp2 = fopen($batch_file, 'r+');
flock($fp2, LOCK_EX);
fseek($fp2,0); $raw2=''; while(!feof($fp2)) $raw2 .= fread($fp2,65536);
$b2 = json_decode($raw2,true) ?: $batch;

$b2['books']         = array_merge($b2['books']??[], $new_books);
$b2['total']         = count($b2['books']);
$b2['authors_built'] = $end;
$b2['build_msg']     = "{$end}/{$authors_total} yazar işlendi, " . count($b2['books']) . ' eser hazır'
    . " · son {$chunk}: OL {$dbg['ol']}·FB {$dbg['fb']}·LLM {$dbg['llm']}·boş {$dbg['none']}";
if ($end >= $authors_total) {
    $b2['status']    = !empty($b2['list_only']) ? 'list_ready' : 'running';
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
