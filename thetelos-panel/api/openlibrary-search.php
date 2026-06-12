<?php
/**
 * openlibrary-search.php — OpenLibrary ile yazar eserlerini çek (author ID bazlı)
 * Adım 1: /search/authors.json → yazar key'ini bul
 * Adım 2: /authors/{key}/works.json → sadece o yazara ait eserler
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

function ol_get($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'thetelos.org/1.0', CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ($c === 200 && $r) ? json_decode($r, true) : null;
}

// Adım 1: Yazar adından OpenLibrary author key'ini bul
$author_data = ol_get('https://openlibrary.org/search/authors.json?q=' . urlencode($author) . '&limit=5');
$author_key  = null;
foreach ($author_data['docs'] ?? [] as $a) {
    // En iyi eşleşme: adı tam ya da yakın olan ilk sonuç
    $name = mb_strtolower(trim($a['name'] ?? ''));
    $q    = mb_strtolower($author);
    if ($name === $q || str_contains($name, $q) || str_contains($q, $name)) {
        $author_key = $a['key'] ?? null; // "/authors/OL123A"
        break;
    }
}
// Bulunamazsa ilk sonucu dene
if (!$author_key && !empty($author_data['docs'][0]['key'])) {
    $author_key = $author_data['docs'][0]['key'];
}

if (!$author_key) {
    echo json_encode(['ok'=>false,'error'=>'Yazar OpenLibrary\'de bulunamadı.']); exit;
}

// Adım 2: O yazara ait eserleri çek
$works_data = ol_get('https://openlibrary.org' . $author_key . '/works.json?limit=' . $limit);
if (!$works_data) {
    echo json_encode(['ok'=>false,'error'=>'OpenLibrary eser listesi alınamadı.']); exit;
}

$author_parts = array_filter(explode(' ', mb_strtolower($author)));
$author_last  = end($author_parts);

$works = [];
$seen  = [];
foreach ($works_data['entries'] ?? [] as $entry) {
    $title = trim($entry['title'] ?? '');
    if (!$title) continue;
    $key = mb_strtolower($title);
    if (isset($seen[$key])) continue;
    $seen[$key] = true;

    // Yazar doğrulaması: eserin yazarları arasında hedef yazar olmalı
    $work_authors = $entry['authors'] ?? [];
    if (!empty($work_authors)) {
        $matched = false;
        foreach ($work_authors as $wa) {
            $wa_key = $wa['author']['key'] ?? ($wa['key'] ?? '');
            if ($wa_key === $author_key) { $matched = true; break; }
        }
        if (!$matched) continue;
    }

    // Başlık sadece yazar soyadıysa veya "X of/on Kant" kalıbındaysa → atla
    if (strlen($author_last) >= 4) {
        if (preg_match('/\b(of|on|to|about|and|by)\s+' . preg_quote($author_last, '/') . '\b/i', $title)) continue;
        if (trim(mb_strtolower($title)) === $author_last) continue;
    }

    $cover = '';
    if (!empty($entry['covers'][0]) && $entry['covers'][0] > 0) {
        $cover = 'https://covers.openlibrary.org/b/id/' . $entry['covers'][0] . '-M.jpg';
    }

    $year = '';
    if (!empty($entry['first_publish_date'])) {
        preg_match('/\d{4}/', $entry['first_publish_date'], $m);
        $year = $m[0] ?? '';
    }

    $works[] = ['title' => $title, 'author' => $author, 'year' => $year, 'cover' => $cover];
}

echo json_encode([
    'ok' => true, 'author' => $author,
    'count' => count($works), 'works' => $works, 'source' => 'openlibrary',
], JSON_UNESCAPED_UNICODE);
