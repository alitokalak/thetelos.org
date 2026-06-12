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
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $batch_id . '.csv"');
echo "\xEF\xBB\xBF"; // UTF-8 BOM
echo "Kitap Adı,Yazar Adı,Yıl,Kapak\n";
foreach ($books as $b) {
    $title  = str_replace('"', '""', $b['book_title']  ?? '');
    $author = str_replace('"', '""', $b['author_name'] ?? '');
    $year   = $b['year']      ?? '';
    $cover  = $b['cover_url'] ?? '';
    echo "\"$title\",\"$author\",\"$year\",\"$cover\"\n";
}
