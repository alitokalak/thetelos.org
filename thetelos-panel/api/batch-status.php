<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_GET['batch_id'] ?? ''));
if (!$batch_id) { echo json_encode(['ok'=>false,'error'=>'batch_id gerekli']); exit; }

$batch_file = dirname(__DIR__) . '/jobs/' . $batch_id . '.json';
if (!file_exists($batch_file)) { echo json_encode(['ok'=>false,'error'=>'Batch bulunamadı']); exit; }

$batch = json_decode(file_get_contents($batch_file), true);
if (!$batch) { echo json_encode(['ok'=>false,'error'=>'Batch okunamadı']); exit; }

// Sadece UI için gereken alanları döndür — tüm içeriği yükleme
$light = $batch;
unset($light['books']); // kitapları ayrı olarak gönder (sadece durum bilgisi)
$light['books'] = array_map(fn($b) => [
    'book_title'  => $b['book_title'],
    'author_name' => $b['author_name'],
    'status'      => $b['status'],
    'post_id'     => $b['post_id'],
    'post_url'    => $b['post_url'],
    'edit_url'    => $b['edit_url'],
    'cover_set'   => $b['cover_set'],
    'error'       => $b['error'],
], $batch['books']);

echo json_encode(['ok'=>true, 'batch'=>$light]);
