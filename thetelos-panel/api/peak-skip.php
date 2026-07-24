<?php
/**
 * peak-skip.php — "Yoğun saatlerde tasarruf et" global anahtarı.
 * GET  → mevcut durumu döndürür.
 * POST on=1|0 → durumu jobs/.peak-skip dosyasına yazar.
 * Bayrak yoksa varsayılan AÇIK (cron-tick de böyle okur).
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$jobs = dirname(__DIR__) . '/jobs';
if (!is_dir($jobs)) @mkdir($jobs, 0755, true);
$flag = $jobs . '/.peak-skip';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $on = (($_POST['on'] ?? '1') === '1');
    @file_put_contents($flag, $on ? '1' : '0');
    echo json_encode(['ok' => true, 'on' => $on]);
    exit;
}

$on = !file_exists($flag) || trim((string) @file_get_contents($flag)) !== '0';
echo json_encode(['ok' => true, 'on' => $on]);
