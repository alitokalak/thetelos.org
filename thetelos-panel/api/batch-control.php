<?php
/**
 * batch-control.php — Batch durumunu değiştir (pause | resume | cancel).
 * POST: batch_id, action
 * Drain worker'lar her kitap arasında bu durumu kontrol eder.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_POST['batch_id'] ?? ''));
$action   = trim($_POST['action'] ?? '');
if (!$batch_id) { echo json_encode(['ok'=>false,'error'=>'batch_id gerekli']); exit; }

$map = ['pause'=>'paused', 'resume'=>'running', 'cancel'=>'cancelled'];
if (!isset($map[$action])) { echo json_encode(['ok'=>false,'error'=>'Geçersiz action']); exit; }

$batch_file = dirname(__DIR__) . '/jobs/' . $batch_id . '.json';
if (!file_exists($batch_file)) { echo json_encode(['ok'=>false,'error'=>'Batch bulunamadı']); exit; }

$fp = fopen($batch_file, 'r+');
if (!$fp || !flock($fp, LOCK_EX)) { echo json_encode(['ok'=>false,'error'=>'Dosya kilitli']); exit; }
fseek($fp, 0);
$raw = ''; while (!feof($fp)) $raw .= fread($fp, 65536);
$batch = json_decode($raw, true);
if (!$batch) { flock($fp, LOCK_UN); fclose($fp); echo json_encode(['ok'=>false,'error'=>'Batch okunamadı']); exit; }

$batch['status'] = $map[$action];
fseek($fp, 0); ftruncate($fp, 0);
fwrite($fp, json_encode($batch, JSON_UNESCAPED_UNICODE));
fflush($fp);
flock($fp, LOCK_UN); fclose($fp);

echo json_encode(['ok'=>true, 'status'=>$batch['status']]);
