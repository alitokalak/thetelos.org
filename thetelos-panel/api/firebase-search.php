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

// Firebase REST: orderBy + equalTo (index gerektirir)
function firebase_search_by_author($author_name, $limit) {
    $url = FIREBASE_URL . '/kitaplar.json'
         . '?orderBy=' . urlencode('"yazar_adi"')
         . '&equalTo=' . urlencode('"' . $author_name . '"')
         . '&limitToFirst=' . $limit;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || !$raw) return [null, 'Firebase bağlantı hatası: ' . $err];
    if ($code !== 200) return [null, "Firebase HTTP $code"];

    $data = json_decode($raw, true);
    if (!is_array($data)) return [null, 'Firebase yanıt ayrıştırılamadı'];
    return [$data, ''];
}

// 1. Tam eşleşme dene
[$data, $err] = firebase_search_by_author($author, $limit);

// 2. Sonuç yok ve nokta/virgül farkı olabilir — kısaltılmış adla dene
if (!$err && (is_null($data) || count($data) === 0)) {
    // "John Stuart Mill" → adı parçala, "J. S. Mill" formatını dene
    // Basit: sadece tam isme bak, çeviri farklarında LLM fallback kullanılır
    $data = [];
}

if ($err) {
    echo json_encode(['ok'=>false,'error'=>$err]);
    exit;
}

$works = [];
foreach ((array)$data as $key => $row) {
    $title = trim($row['eser_adi'] ?? '');
    if ($title === '') continue;
    $works[] = [
        'title'  => $title,
        'author' => trim($row['yazar_adi'] ?? $author),
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
