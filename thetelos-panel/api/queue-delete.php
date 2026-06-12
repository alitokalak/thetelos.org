<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');
$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_POST['batch_id'] ?? ''));
if (!$batch_id || strpos($batch_id, 'queue_') !== 0) { echo json_encode(['ok'=>false,'error'=>'Geçersiz ID']); exit; }
$file = dirname(__DIR__) . '/jobs/' . $batch_id . '.json';
if (file_exists($file)) unlink($file);
echo json_encode(['ok'=>true]);
