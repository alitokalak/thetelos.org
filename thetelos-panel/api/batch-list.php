<?php
/**
 * batch-list.php — Sunucudaki tamamlanmamış toplu batch dosyalarını listele.
 * "batch_" prefix'li JSON dosyaları; iptal edilenler hariç.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$jobs_dir = dirname(__DIR__) . '/jobs';
$batches  = [];

if (is_dir($jobs_dir)) {
    foreach (glob("$jobs_dir/batch_*.json") as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data) continue;
        $status = $data['status'] ?? '';
        if ($status === 'cancelled') continue;
        $batches[] = [
            'id'         => $data['id']         ?? basename($file, '.json'),
            'status'     => $status,
            'total'      => (int)($data['total']  ?? 0),
            'done'       => (int)($data['done']   ?? 0),
            'ok'         => (int)($data['ok']     ?? 0),
            'failed'     => (int)($data['failed'] ?? 0),
            'type'       => $data['type']         ?? '',
            'created_at' => (int)($data['created_at'] ?? filemtime($file)),
        ];
    }
}

usort($batches, fn($a, $b) => $b['created_at'] - $a['created_at']);
echo json_encode(['ok' => true, 'batches' => $batches], JSON_UNESCAPED_UNICODE);
