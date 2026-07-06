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

$batch_file = dirname(__DIR__) . '/jobs/' . $batch_id . '.json';

/* delete: batch dosyasını tamamen sil (eski yarım kalan denemeleri temizlemek için) */
if ($action === 'delete') {
    if (file_exists($batch_file)) @unlink($batch_file);
    echo json_encode(['ok'=>true, 'deleted'=>true]);
    exit;
}

$map = ['pause'=>'paused', 'resume'=>'running', 'cancel'=>'cancelled', 'retry_errors'=>'running'];
if (!isset($map[$action])) { echo json_encode(['ok'=>false,'error'=>'Geçersiz action']); exit; }

if (!file_exists($batch_file)) { echo json_encode(['ok'=>false,'error'=>'Batch bulunamadı']); exit; }

// ATOMİK desen: kilit ayrı .lock dosyasında; yazma tmp+rename ile (bozulma imkânsız).
$lk = fopen($batch_file . '.lock', 'c');
if (!$lk || !flock($lk, LOCK_EX)) { if ($lk) fclose($lk); echo json_encode(['ok'=>false,'error'=>'Dosya kilitli']); exit; }
$batch = json_decode((string)@file_get_contents($batch_file), true);
if (!$batch) { flock($lk, LOCK_UN); fclose($lk); echo json_encode(['ok'=>false,'error'=>'Batch okunamadı']); exit; }

$batch['status'] = $map[$action];

/* retry_errors: hata alan kitapları pending'e çek, yeniden işlensin. */
if ($action === 'retry_errors') {
    $retried = 0;
    foreach ($batch['books'] as $i => $b) {
        if (($b['status'] ?? '') === 'error' && empty($b['post_id'])) {
            $batch['books'][$i]['status'] = 'pending';
            unset($batch['books'][$i]['error'], $batch['books'][$i]['processing_since']);
            $retried++;
        }
    }
    // done/ok/failed sayaçlarını yeniden hesapla
    $done = $ok = $failed = 0;
    foreach ($batch['books'] as $b) {
        if (in_array($b['status'], ['done','error'])) $done++;
        if ($b['status'] === 'done')  $ok++;
        if ($b['status'] === 'error') $failed++;
    }
    $batch['done']   = $done;
    $batch['ok']     = $ok;
    $batch['failed'] = $failed;
}

/* Resume sırasında orphan "processing" kitapları pending'e sıfırla.
   Durdur→Devam Et yapıldığında önceki worker ölmüş demektir; 15 dk timeout
   beklemeye gerek yok, hemen kurtarabiliriz. post_id'si olanlar WP'ye
   zaten yazıldığından onlara dokunma. */
if ($action === 'resume') {
    foreach ($batch['books'] as $i => $b) {
        if (($b['status'] ?? '') === 'processing' && empty($b['post_id'])) {
            $batch['books'][$i]['status'] = 'pending';
            unset($batch['books'][$i]['processing_since']);
        }
    }
    // done/ok/failed sayaçlarını yeniden hesapla
    $done = $ok = $failed = 0;
    foreach ($batch['books'] as $b) {
        if (in_array($b['status'], ['done','error'])) $done++;
        if ($b['status'] === 'done')  $ok++;
        if ($b['status'] === 'error') $failed++;
    }
    $batch['done']   = $done;
    $batch['ok']     = $ok;
    $batch['failed'] = $failed;
}
$json = json_encode($batch, JSON_UNESCAPED_UNICODE);
if ($json !== false && $json !== '') {
    $tmp = $batch_file . '.tmp.' . getmypid() . '.' . mt_rand();
    if (@file_put_contents($tmp, $json) === strlen($json)) { @rename($tmp, $batch_file); }
    else { @unlink($tmp); }
}
flock($lk, LOCK_UN); fclose($lk);

echo json_encode(['ok'=>true, 'status'=>$batch['status']]);
