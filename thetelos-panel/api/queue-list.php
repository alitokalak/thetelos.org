<?php
/**
 * queue-list.php — Tüm kuyrukları listele (queue_ prefix'li batch dosyaları)
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');

$jobs_dir = dirname(__DIR__) . '/jobs';
$queues   = [];

if (is_dir($jobs_dir)) {
    foreach (glob("$jobs_dir/queue_*.json") as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data) continue;
        $queues[] = [
            'id'         => $data['id']         ?? '',
            'category'   => $data['category']   ?? '',
            'status'     => $data['status']      ?? '',
            'total'      => $data['total']       ?? 0,
            'done'       => $data['done']        ?? 0,
            'ok'         => $data['ok']          ?? 0,
            'failed'     => $data['failed']      ?? 0,
            'build_msg'  => $data['build_msg']   ?? '',
            'created_at' => $data['created_at']  ?? 0,
        ];
    }
}

// En yeni önce
usort($queues, fn($a,$b) => $b['created_at'] - $a['created_at']);

echo json_encode(['ok'=>true,'queues'=>$queues], JSON_UNESCAPED_UNICODE);
