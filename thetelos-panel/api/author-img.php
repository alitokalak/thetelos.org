<?php
/**
 * author-img.php — Yazar portresini SUNUCU üzerinden proxy'ler.
 * Tarayıcı bunu aynı-köken bir görsel gibi kullanır → CORS/redirect derdi yok,
 * canvas kirlenmez (toDataURL/indir/tweet çalışır).
 *
 * GET name=<yazar adı>  → görsel bytes (image/*), yoksa 404
 * GET name=<...>&debug=1 → JSON teşhis {ok,url,qid,file,fetch_code}
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();

ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();
require_once __DIR__ . '/_wikidata-authors.php';   // tls_wd_http
require_once __DIR__ . '/_social-lib.php';          // sg_author_image

$name  = trim((string) ($_GET['name'] ?? ''));
$debug = !empty($_GET['debug']);

if ($name === '') { if ($debug){header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>'name yok']);} else http_response_code(400); exit; }

$info = sg_author_image($name, true);   // [url,qid,file]
$url  = $info['url'];

if ($url === '') {
    if ($debug) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'reason'=>'portre yok','qid'=>$info['qid'],'file'=>$info['file']], JSON_UNESCAPED_UNICODE); }
    else http_response_code(404);
    exit;
}

// Görseli sunucuda çek
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 25,
    CURLOPT_HTTPHEADER => ['User-Agent: ThetelosBot/1.0 (https://thetelos.org; content builder)'],
]);
$data = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ct   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$err  = curl_error($ch);
curl_close($ch);

if ($debug) {
    header('Content-Type: application/json');
    echo json_encode(['ok'=>($code===200 && $data!==false && $data!==''), 'url'=>$url, 'qid'=>$info['qid'],
        'file'=>$info['file'], 'fetch_code'=>$code, 'content_type'=>$ct, 'bytes'=>is_string($data)?strlen($data):0,
        'curl_err'=>$err], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($code !== 200 || $data === false || $data === '') { http_response_code(404); exit; }

header('Content-Type: ' . ($ct ?: 'image/jpeg'));
header('Cache-Control: public, max-age=604800');
echo $data;
