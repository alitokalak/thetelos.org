<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$type         = trim($_POST['type']         ?? 'summary');
$post_status  = trim($_POST['post_status']  ?? 'draft');
$max_tokens   = max(500, min(8000, (int)($_POST['max_tokens'] ?? 3000)));
$api_provider = trim($_POST['api_provider'] ?? 'deepseek');
$parts        = max(1, min(4, (int)($_POST['parts'] ?? 2)));
$books_json   = trim($_POST['books']        ?? '[]');

$books = json_decode($books_json, true);
if (!$books || !is_array($books) || count($books) === 0) {
    echo json_encode(['ok'=>false,'error'=>'Kitap listesi boş.']);
    exit;
}

// Boş kitapları filtrele
$books = array_values(array_filter($books, fn($b) => !empty(trim($b['book_title'] ?? ''))));
if (count($books) === 0) {
    echo json_encode(['ok'=>false,'error'=>'Geçerli kitap bulunamadı.']);
    exit;
}

$batch_id   = 'batch_' . uniqid('', true);
$jobs_dir   = dirname(__DIR__) . '/jobs';
if (!is_dir($jobs_dir)) mkdir($jobs_dir, 0755, true);
$batch_file = "$jobs_dir/{$batch_id}.json";

$batch = [
    'id'           => $batch_id,
    'status'       => 'running',
    'created_at'   => time(),
    'type'         => $type,
    'post_status'  => $post_status,
    'max_tokens'   => $max_tokens,
    'api_provider' => $api_provider,
    'parts'        => $parts,
    'total'        => count($books),
    'done'         => 0,
    'ok'           => 0,
    'failed'       => 0,
    'books'        => array_map(fn($b) => [
        'book_title'  => trim($b['book_title']  ?? ''),
        'author_name' => trim($b['author_name'] ?? ''),
        'status'      => 'pending',
        'post_id'     => null,
        'post_url'    => null,
        'edit_url'    => null,
        'cover_set'   => false,
        'error'       => '',
    ], $books),
];

file_put_contents($batch_file, json_encode($batch, JSON_UNESCAPED_UNICODE));

echo json_encode(['ok'=>true, 'batch_id'=>$batch_id, 'total'=>count($books)]);
