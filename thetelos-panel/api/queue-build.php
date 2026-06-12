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
set_time_limit(90);

$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_POST['batch_id'] ?? ''));
$chunk    = max(1, min(10, (int)($_POST['chunk'] ?? 5)));
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

function qb_openlibrary($author){
    $url='https://openlibrary.org/search.json?author='.urlencode($author).'&limit=50&fields=title,first_publish_year,cover_i&lang=eng';
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_USERAGENT=>'thetelos.org/1.0']);
    $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($code!==200||!$r) return [];
    $data=json_decode($r,true); $out=[]; $seen=[];
    foreach($data['docs']??[] as $doc){
        $t=trim($doc['title']??''); if(!$t) continue;
        $k=mb_strtolower($t); if(isset($seen[$k])) continue; $seen[$k]=true;
        $cover=!empty($doc['cover_i'])?'https://covers.openlibrary.org/b/id/'.$doc['cover_i'].'-M.jpg':'';
        $out[]=['title'=>$t,'cover'=>$cover,'year'=>(string)($doc['first_publish_year']??'')];
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
        $t = trim($w['title']??''); if(!$t) continue;
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
