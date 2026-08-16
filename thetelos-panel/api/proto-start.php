<?php
/**
 * proto-start.php — Prototip işini kuyruğa alır ve worker'ı tetikler.
 * Uzun işi tek istekte YAPMAZ (Cloudflare 524 sebebi buydu); job dosyası açıp
 * proto-run.php'yi fire-and-forget başlatır, hemen job id döner. UI durumu
 * proto-status.php ile yoklar.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); echo json_encode(['error' => 'auth']); exit; }
require_once __DIR__ . '/_proto.php';
header('Content-Type: application/json');

$book   = trim($_POST['book']   ?? '');
$author = trim($_POST['author'] ?? '');
$sys    = in_array($_POST['sys'] ?? 'both', ['both', 'A', 'B'], true) ? $_POST['sys'] : 'both';
if ($book === '') { echo json_encode(['error' => 'Kitap adı gerekli']); exit; }

$jobs = dirname(__DIR__) . '/jobs';
if (!is_dir($jobs)) @mkdir($jobs, 0755, true);
$id   = bin2hex(random_bytes(6));
$file = proto_job_file($id);

$j = [
    'id' => $id, 'book' => $book, 'author' => $author, 'sys' => $sys,
    'status' => 'running', 'phase' => ($sys === 'A') ? 'systemA' : 'acquire',
    'log' => ['kuyruğa alındı'], 'notes' => [], 'ci' => 0,
    'created' => time(), 'updated' => time(), 'spawned' => time(),
];
proto_job_write($file, $j);
proto_spawn($id);

echo json_encode(['ok' => true, 'id' => $id]);
