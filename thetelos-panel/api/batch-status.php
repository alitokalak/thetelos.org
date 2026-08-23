<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();   // session kilidini hemen bırak — uzun JSON okuma sırasında diğer istekler bloke olmasın
header('Content-Type: application/json');

$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_GET['batch_id'] ?? ''));
if (!$batch_id) { echo json_encode(['ok'=>false,'error'=>'batch_id gerekli']); exit; }

$batch_file = dirname(__DIR__) . '/jobs/' . $batch_id . '.json';
if (!file_exists($batch_file)) { echo json_encode(['ok'=>false,'error'=>'Batch bulunamadı']); exit; }

$batch = json_decode(file_get_contents($batch_file), true);
if (!$batch) { echo json_encode(['ok'=>false,'error'=>'Batch okunamadı']); exit; }

// Sadece UI için gereken alanları döndür — tüm içeriği yükleme.
// NOT: Bayat processing kitapları "error" yazarak dosyayı burada DEĞİŞTİRMİYORUZ —
// sıfırlama (pending'e çekme) bw_claim_next içinde kilit altında yapılıyor.
$light = $batch;
unset($light['books']);
$base = preg_replace('/\.json$/', '', $batch_file);
$now  = time();
$light['books'] = [];
foreach ($batch['books'] as $i => $b) {
    // Canlılık: ucuz heartbeat dosyasının yaşı (sn). null = hb yok.
    $hb = "$base.hb.$i";
    $hb_age = file_exists($hb) ? ($now - filemtime($hb)) : null;
    $stgf = "$base.stage.$i";
    $stage = file_exists($stgf) ? (string) @file_get_contents($stgf) : '';
    $light['books'][] = [
        'stage'            => $stage,
        'book_title'       => $b['book_title'],
        'author_name'      => $b['author_name'],
        'status'           => $b['status'],
        'post_id'          => $b['post_id'],
        'post_url'         => $b['post_url'],
        'edit_url'         => $b['edit_url'],
        'cover_set'        => $b['cover_set'],
        'placeholder'      => !empty($b['placeholder']) ? 1 : 0,
        'error'            => $b['error'],
        'processing_since' => (int)($b['processing_since'] ?? 0),
        'hb_age'           => $hb_age,
    ];
}

echo json_encode(['ok'=>true, 'batch'=>$light]);
