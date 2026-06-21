<?php
/**
 * server-status.php — Arka planda çalışan batch'leri listele.
 * books_stale: 5 dk+ takılı kalmış (worker ölmüş) processing kitap sayısı.
 * Nöbetçi (app.js) bunu okuyarak bayat kitaplar varsa da worker ateşler.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');

$jobs_dir = dirname(__DIR__) . '/jobs';
$active   = [];

if (is_dir($jobs_dir)) {
    $stale_thr = 5 * 60; // bw_claim_next ile aynı eşik (5 dk)
    $now = time();
    foreach (glob("$jobs_dir/*.json") as $file) {
        $b = json_decode(file_get_contents($file), true);
        if (!$b) continue;
        $st = $b['status'] ?? '';
        if ($st === 'cancelled' || $st === 'paused') continue;
        $processing = 0; $pending = 0; $stale = 0;
        foreach ($b['books'] ?? [] as $bk) {
            $bs = $bk['status'] ?? '';
            if ($bs === 'processing') {
                $since = (int)($bk['processing_since'] ?? 0);
                // post_id varsa WP'ye yazılmış sayılır; yoksa ve 5dk+ geçtiyse "bayat"
                if (empty($bk['post_id']) && $since > 0 && ($now - $since) > $stale_thr) {
                    $stale++;
                } else {
                    $processing++;
                }
            } elseif ($bs === 'pending') {
                $pending++;
            }
        }
        if ($processing > 0 || $pending > 0 || $stale > 0) {
            $active[] = [
                'id'               => $b['id'] ?? basename($file, '.json'),
                'category'         => $b['category'] ?? '',
                'status'           => $st,
                'total'            => (int)($b['total'] ?? 0),
                'done'             => (int)($b['done'] ?? 0),
                'ok'               => (int)($b['ok'] ?? 0),
                'failed'           => (int)($b['failed'] ?? 0),
                'books_processing' => $processing,   // canlı worker'ların elindeki kitap
                'books_stale'      => $stale,        // 5dk+ takılı = bayat, kurtarılması gerekiyor
                'books_pending'    => $pending,
            ];
        }
    }
}

echo json_encode(['ok' => true, 'active' => $active], JSON_UNESCAPED_UNICODE);
