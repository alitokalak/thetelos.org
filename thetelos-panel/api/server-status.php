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
        // Aktif sayılanlar: running veya içinde "processing" durumunda kitap olanlar
        $processing = 0;
        foreach ($b['books'] ?? [] as $bk) {
            if (($bk['status'] ?? '') === 'processing') $processing++;
        }
        if ($st === 'running' || $processing > 0) {
            $active[] = [
                'id'               => $b['id'] ?? basename($file, '.json'),
                'category'         => $b['category'] ?? '',
                'status'           => $st,
                'total'            => (int)($b['total'] ?? 0),
                'done'             => (int)($b['done'] ?? 0),
                'ok'               => (int)($b['ok'] ?? 0),
                'failed'           => (int)($b['failed'] ?? 0),
                'books_processing' => $processing,
            ];
        }
    }
}

echo json_encode(['ok' => true, 'active' => $active], JSON_UNESCAPED_UNICODE);
