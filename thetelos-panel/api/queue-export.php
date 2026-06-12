<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_GET['batch_id'] ?? ''));
if (!$batch_id) { http_response_code(400); exit; }
$file = dirname(__DIR__) . '/jobs/' . $batch_id . '.json';
if (!file_exists($file)) { http_response_code(404); exit; }
$batch = json_decode(file_get_contents($file), true);
$books = $batch['books'] ?? [];
$cat_raw   = $batch['category']      ?? 'export';
$offset    = (int)($batch['author_offset'] ?? 0);
$aut_total = (int)($batch['authors_total'] ?? 50);
$cat_slug  = ucfirst(preg_replace('/\s+/', '_', trim($cat_raw)));
$range_end = $offset + $aut_total;
$filename  = "{$cat_slug}_{$offset}-{$range_end}.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF"; // UTF-8 BOM
echo "Kitap Adı,Yazar Adı,Yıl,Kapak\n";
foreach ($books as $b) {
    $raw   = trim($b['book_title'] ?? '');
    // "Title (Original)" formatını koru; sadece ASCII-dışı başlıklara "(orijinal)" etiketi ekle
    $title = $raw;
    if ($raw !== '' && !preg_match('/\([^)]+\)/', $raw)) {
        // OpenLibrary bazen tam Latince/Yunanca başlık döner; olduğu gibi bırak
        // (Kullanıcı gerekirse düzenler)
        $title = $raw;
    }
    $author = trim($b['author_name'] ?? '');
    $year   = trim($b['year']        ?? '');
    $cover  = trim($b['cover_url']   ?? '');
    $title  = str_replace('"', '""', $title);
    $author = str_replace('"', '""', $author);
    echo "\"$title\",\"$author\",\"$year\",\"$cover\"\n";
}
