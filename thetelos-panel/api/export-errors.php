<?php
/**
 * export-errors.php — Bir batch'teki HATALI kitapları CSV olarak indirir.
 * Yeni (temiz) liste yapmak için: Kitap Adı, Yazar Adı, Yıl, Kapak sütunları —
 * panelin yüklediği formatın aynısı, doğrudan tekrar yüklenebilir.
 *
 * GET ?batch_id=...  (&status=error|duplicate|all  — vars. error)
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('unauthorized'); }
session_write_close();

$bid  = preg_replace('/[^A-Za-z0-9._-]/', '', (string)($_GET['batch_id'] ?? ''));
$want = (string)($_GET['status'] ?? 'error');
$file = dirname(__DIR__) . '/jobs/' . $bid . '.json';
if ($bid === '' || !is_file($file)) { http_response_code(404); exit('batch not found'); }

$batch = json_decode((string) @file_get_contents($file), true);
$rows  = [];
foreach (($batch['books'] ?? []) as $b) {
    $st = $b['status'] ?? '';
    if ($want !== 'all' && $st !== $want) continue;
    // duplicate_skipped 'done' + error='duplicate_skipped' olarak tutulur
    if ($want === 'error' && ($b['error'] ?? '') === 'duplicate_skipped') continue;
    $rows[] = [
        $b['book_title']  ?? '',
        $b['author_name'] ?? '',
        $b['pub_year']    ?? '',
        $b['cover_url']   ?? '',
    ];
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="hatali-kitaplar-' . $bid . '.csv"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM → Excel Türkçe karakterleri doğru gösterir
fputcsv($out, ['Kitap Adı', 'Yazar Adı', 'Yıl', 'Kapak']);
foreach ($rows as $r) fputcsv($out, $r);
fclose($out);
