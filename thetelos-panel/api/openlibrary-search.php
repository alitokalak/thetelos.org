<?php
/**
 * openlibrary-search.php — OpenLibrary public API ile yazar eserlerini çek
 * POST: author, limit (varsayılan 50)
 * Dönüş: { ok, works:[{title,author,year,cover}], source:"openlibrary" }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
@ini_set('display_errors', 0);
set_time_limit(30);

$author = trim($_POST['author'] ?? '');
$limit  = max(10, min(200, (int)($_POST['limit'] ?? 50)));

if ($author === '') { echo json_encode(['ok'=>false,'error'=>'Yazar adı zorunlu.']); exit; }

$url = 'https://openlibrary.org/search.json?'
     . 'author=' . urlencode($author)
     . '&limit=' . $limit
     . '&fields=title,author_name,first_publish_year,cover_i'
     . '&lang=eng';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_USERAGENT      => 'thetelos.org/1.0 (content panel)',
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);
$raw  = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err || !$raw) { echo json_encode(['ok'=>false,'error'=>'OpenLibrary bağlantı hatası: '.$err]); exit; }
if ($code !== 200) { echo json_encode(['ok'=>false,'error'=>"OpenLibrary HTTP $code"]); exit; }

$data = json_decode($raw, true);
if (!isset($data['docs'])) { echo json_encode(['ok'=>false,'error'=>'OpenLibrary yanıt ayrıştırılamadı']); exit; }

$works = [];
$seen  = [];
foreach ($data['docs'] as $doc) {
    $title = trim($doc['title'] ?? '');
    if ($title === '') continue;
    $key = mb_strtolower($title);
    if (isset($seen[$key])) continue;
    $seen[$key] = true;

    $cover = '';
    if (!empty($doc['cover_i'])) {
        $cover = 'https://covers.openlibrary.org/b/id/' . $doc['cover_i'] . '-M.jpg';
    }

    $works[] = [
        'title'  => $title,
        'author' => trim(($doc['author_name'][0] ?? $author)),
        'year'   => (string)($doc['first_publish_year'] ?? ''),
        'cover'  => $cover,
    ];
}

echo json_encode([
    'ok'     => true,
    'author' => $author,
    'count'  => count($works),
    'works'  => $works,
    'source' => 'openlibrary',
], JSON_UNESCAPED_UNICODE);
