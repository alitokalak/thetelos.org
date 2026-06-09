<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$job_id   = preg_replace('/[^a-z0-9_.]/', '', $_GET['job_id'] ?? '');
$job_file = dirname(__DIR__) . '/jobs/' . $job_id . '.json';

if (!$job_id || !file_exists($job_file)) {
    echo json_encode(['ok'=>false,'error'=>'Job bulunamadı.']); exit;
}

$job = json_decode(file_get_contents($job_file), true);

// Done ise job dosyasını sil (temizlik)
if (($job['status']??'') === 'done' || ($job['status']??'') === 'error') {
    // 5 dakika sonra sil
    if (time() - ($job['created_at']??0) > 300) {
        @unlink($job_file);
    }
}

echo json_encode(['ok'=>true,'job'=>$job]);
