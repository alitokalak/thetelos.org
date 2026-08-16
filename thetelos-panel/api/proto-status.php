<?php
/**
 * proto-status.php — Prototip işinin ilerleme + sonucunu döner (UI yoklar).
 * İş takılırsa (worker ölmüş, 60 sn'dir güncellenmemiş) bir halef daha tetikler.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); echo json_encode(['error' => 'auth']); exit; }
require_once __DIR__ . '/_proto.php';
header('Content-Type: application/json');

$file = proto_job_file($_GET['id'] ?? '');
if ($file === '' || !file_exists($file)) { echo json_encode(['error' => 'no_job']); exit; }
$j = proto_job_read($file);
if (!$j) { echo json_encode(['error' => 'read']); exit; }

// Takılmış işi canlandır: running ama 60 sn'dir güncellenmemişse halef tetikle.
if (($j['status'] ?? '') !== 'done' && (time() - (int) ($j['updated'] ?? 0)) > 60
    && (time() - (int) ($j['spawned'] ?? 0)) > 60) {
    $j['spawned'] = time(); proto_job_write($file, $j); proto_spawn($j['id']);
}

echo json_encode([
    'ok'      => true,
    'status'  => $j['status'] ?? 'running',
    'phase'   => $j['phase'] ?? '',
    'log'     => $j['log'] ?? [],
    'debug'   => $j['debug'] ?? null,
    'resultA' => $j['resultA'] ?? null,
    'resultB' => $j['resultB'] ?? null,
], JSON_UNESCAPED_UNICODE);
