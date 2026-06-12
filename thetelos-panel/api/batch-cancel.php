<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');

$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_POST['batch_id'] ?? ''));
if (!$batch_id) { echo json_encode(['ok'=>false,'error'=>'batch_id gerekli']); exit; }

$file = dirname(__DIR__) . '/jobs/' . $batch_id . '.json';
if (!file_exists($file)) { echo json_encode(['ok'=>false,'error'=>'Bulunamadı']); exit; }

$fp = fopen($file, 'r+');
if (!flock($fp, LOCK_EX)) { fclose($fp); echo json_encode(['ok'=>false,'error'=>'Kilit alınamadı']); exit; }
fseek($fp, 0); $raw = ''; while (!feof($fp)) $raw .= fread($fp, 65536);
$b = json_decode($raw, true);
if ($b) {
    $b['status'] = 'cancelled';
    // processing → pending olarak sıfırla ki sonraki başlatmada kaldığı yerden devam etsin
    foreach ($b['books'] as $i => $bk) {
        if (($bk['status'] ?? '') === 'processing') $b['books'][$i]['status'] = 'pending';
    }
    fseek($fp, 0); ftruncate($fp, 0);
    fwrite($fp, json_encode($b, JSON_UNESCAPED_UNICODE));
    fflush($fp);
}
flock($fp, LOCK_UN); fclose($fp);
echo json_encode(['ok' => true]);
