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
    $now = time();
    foreach (glob("$jobs_dir/*.json") as $file) {
        $b = json_decode(file_get_contents($file), true);
        if (!$b) continue;
        $st = $b['status'] ?? '';
        if ($st === 'cancelled' || $st === 'paused') continue;
        // Canlılık: iki katmanlı heartbeat (batch-worker ile aynı eşik, 180sn).
        // Üretim streaming olduğundan worker her birkaç saniyede heartbeat tazeler.
        // $processing = canlı .wk.* dosyası sayısı (per-worker, zincirleme boşluğu yok).
        // $stale      = processing durumda olan ama per-kitap hb dosyası bayatlamış kitap sayısı.
        $base      = preg_replace('/\.json$/', '', $file);
        $stale_thr = 180;
        $processing = 0; $pending = 0; $stale = 0;

        // Per-worker canlılık: her worker kendi .wk.{id} dosyasını tutar
        foreach (glob("$base.wk.*") ?: [] as $wk) {
            if (($now - @filemtime($wk)) <= $stale_thr) $processing++;
        }

        foreach ($b['books'] ?? [] as $i => $bk) {
            $bs = $bk['status'] ?? '';
            if ($bs === 'processing') {
                if (!empty($bk['post_id'])) continue;  // esasen tamamlanmış
                $hb = "$base.hb.$i";
                $alive = file_exists($hb) && ($now - filemtime($hb)) <= $stale_thr;
                if (!$alive) { $stale++; }   // per-kitap hb bayatlamış = worker öldü
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
                'workers'          => max(1, min(5, (int)($b['workers'] ?? 1))),
            ];
        }
    }
}

echo json_encode(['ok' => true, 'active' => $active], JSON_UNESCAPED_UNICODE);
