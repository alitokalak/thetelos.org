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

// ATOMİK: .lock ile dışla, geçici dosya + rename ile yaz → yarıda kesilse
// bile canlı dosya asla 0 byte/bozuk kalmaz.
$lock = fopen($file . '.lock', 'c');
if ($lock) flock($lock, LOCK_EX);
$raw = @file_get_contents($file);
$b   = json_decode((string)$raw, true);
if (is_array($b)) {
    $b['status'] = 'cancelled';
    // processing + duplicate → pending olarak sıfırla ki sonraki başlatmada yeniden işlensin
    foreach ($b['books'] ?? [] as $i => $bk) {
        $st = $bk['status'] ?? '';
        if ($st === 'processing' || $st === 'duplicate') $b['books'][$i]['status'] = 'pending';
    }
    $json = json_encode($b, JSON_UNESCAPED_UNICODE);
    if ($json !== false && $json !== '') {
        $tmp = $file . '.tmp.' . getmypid() . '.' . mt_rand();
        if (@file_put_contents($tmp, $json) === strlen($json)) { @rename($tmp, $file); }
        else { @unlink($tmp); }
    }
}
if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
echo json_encode(['ok' => true]);
