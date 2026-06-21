<?php
/**
 * server-status.php — Arka planda çalışan batch'leri listele
 * GET: -
 * Dönüş: { ok, active: [{id, category, done, total, ok, failed, status, books_processing}] }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');

$jobs_dir = dirname(__DIR__) . '/jobs';
$active   = [];

if (is_dir($jobs_dir)) {
    foreach (glob("$jobs_dir/*.json") as $file) {
        $b = json_decode(file_get_contents($file), true);
        if (!$b) continue;
        $st = $b['status'] ?? '';
        if ($st === 'cancelled' || $st === 'paused') continue;
        // Aktif sayılanlar: bekleyen veya işlenen kitabı olan her batch
        $processing = 0; $pending = 0;
        foreach ($b['books'] ?? [] as $bk) {
            $bs = $bk['status'] ?? '';
            if ($bs === 'processing') $processing++;
            elseif ($bs === 'pending') $pending++;
        }
        if ($processing > 0 || $pending > 0) {
            $active[] = [
                'id'               => $b['id'] ?? basename($file, '.json'),
                'category'         => $b['category'] ?? '',
                'status'           => $st,
                'total'            => (int)($b['total'] ?? 0),
                'done'             => (int)($b['done'] ?? 0),
                'ok'               => (int)($b['ok'] ?? 0),
                'failed'           => (int)($b['failed'] ?? 0),
                'books_processing' => $processing,
                'books_pending'    => $pending,
            ];
        }
    }
}

echo json_encode(['ok' => true, 'active' => $active], JSON_UNESCAPED_UNICODE);
