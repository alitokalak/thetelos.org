<?php
/**
 * error-reasons.php — Bir batch'teki HATA MESAJLARINI gruplayıp sayar.
 * Neden hata alındığını tek bakışta gösterir (ör. "Insufficient Balance × 400").
 * GET ?batch_id=...
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('unauthorized'); }
session_write_close();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$bid  = preg_replace('/[^A-Za-z0-9._-]/', '', (string)($_GET['batch_id'] ?? ''));
$file = dirname(__DIR__) . '/jobs/' . $bid . '.json';
if ($bid === '' || !is_file($file)) { http_response_code(404); exit('batch not found'); }

$batch = json_decode((string) @file_get_contents($file), true);

$counts = [];   // ham hata metni => adet
$sample = [];   // hata metni => örnek kitap
$total_err = 0;
foreach (($batch['books'] ?? []) as $b) {
    if (($b['status'] ?? '') !== 'error') continue;
    $e = trim((string)($b['error'] ?? '')) ?: '(boş hata metni)';
    if ($e === 'duplicate_skipped') continue;
    $total_err++;
    $counts[$e] = ($counts[$e] ?? 0) + 1;
    if (!isset($sample[$e])) $sample[$e] = trim(($b['book_title'] ?? '') . ' — ' . ($b['author_name'] ?? ''), ' —');
}
arsort($counts);

echo '<div style="font-family:system-ui,sans-serif;max-width:900px;margin:40px auto;line-height:1.6;color:#222">';
echo '<h2>Hata Sebepleri — ' . htmlspecialchars($bid) . '</h2>';
echo '<p><b>' . $total_err . '</b> hatalı kitap · <b>' . count($counts) . '</b> farklı hata tipi</p>';
echo '<table style="width:100%;border-collapse:collapse;font-size:14px">';
echo '<tr style="text-align:left;border-bottom:2px solid #ccc"><th style="padding:8px">Adet</th><th style="padding:8px">Hata mesajı</th><th style="padding:8px">Örnek kitap</th></tr>';
foreach ($counts as $msg => $n) {
    echo '<tr style="border-bottom:1px solid #eee">'
       . '<td style="padding:8px;font-weight:700;color:#b32d2e">' . $n . '</td>'
       . '<td style="padding:8px;font-family:monospace">' . htmlspecialchars($msg) . '</td>'
       . '<td style="padding:8px;color:#666">' . htmlspecialchars($sample[$msg] ?? '') . '</td>'
       . '</tr>';
}
echo '</table></div>';
