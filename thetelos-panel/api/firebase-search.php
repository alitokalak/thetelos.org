<?php
/**
 * firebase-search.php — Firebase Realtime Database'den yazar/eser ara
 * POST: author (yazar adı) veya subject (konu)
 * Dönüş: { ok, works:[{title, author}], source:"firebase" }
 *
 * Firebase'de .indexOn:["yazar_adi"] tanımlı olmalı!
 * Rules: { "kitaplar": { ".indexOn": ["yazar_adi"] } }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
@ini_set('display_errors', 0);
set_time_limit(30);

define('FIREBASE_URL', 'https://thetelos-db-default-rtdb.europe-west1.firebasedatabase.app');

$author  = trim($_POST['author']  ?? '');
$limit   = max(10, min(500, (int)($_POST['limit'] ?? 200)));

if ($author === '') {
    echo json_encode(['ok'=>false,'error'=>'Yazar adı zorunlu.']);
    exit;
}

// Firebase key normalize: Python scriptindeki norm_yazar() ile aynı mantık
function fb_norm($name) {
    $name = mb_strtolower(trim($name));
    return strtr($name, ['.'=>'_','#'=>'_','$'=>'_','['=>'_',']'=>'_','/'=>'_']);
}

// /yazarlar/{norm_adi}.json — direkt path lookup, index gerektirmez
function firebase_get_author_works($author_name, $limit) {
    $norm = fb_norm($author_name);
    $url  = FIREBASE_URL . '/yazarlar/' . rawurlencode($norm) . '.json'
          . '?limitToFirst=' . $limit;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || !$raw) return [null, 'Firebase bağlantı hatası: ' . $err];
    if ($code === 404 || $raw === 'null') return [[], ''];   // yazar yok
    if ($code !== 200) return [null, "Firebase HTTP $code — " . mb_substr($raw,0,100)];

    $data = json_decode($raw, true);
    if (!is_array($data)) return [[], ''];
    return [$data, ''];
}

[$data, $err] = firebase_get_author_works($author, $limit);

if ($err) {
    echo json_encode(['ok'=>false,'error'=>$err]);
    exit;
}

$works = [];
foreach ((array)$data as $key => $title) {
    $title = trim((string)$title);
    if ($title === '') continue;
    $works[] = [
        'title'  => $title,
        'author' => $author,
        'year'   => '',
        'cover'  => '',
    ];
}

echo json_encode([
    'ok'     => true,
    'author' => $author,
    'count'  => count($works),
    'works'  => $works,
    'source' => 'firebase',
], JSON_UNESCAPED_UNICODE);
