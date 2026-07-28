<?php
/**
 * clean-progress.php — Liste temizleme işinin SUNUCUDA saklanan durumu.
 *
 * NEDEN: Temizleme yazar-yazar ilerliyordu ama tüm durum tarayıcı belleğindeydi;
 * sekme kapanınca / oturum düşünce baştan başlamak gerekiyordu. Artık her yazar
 * bitince sonuç sunucuya eklenir → sayfa yeniden açıldığında kaldığı yerden devam eder.
 *
 * POST action=start  → yeni iş: {file_name, use_ai, authors[], by_author{}}
 * POST action=append → bir yazarın sonucu: {author, works[], removed[]}
 * POST action=load   → kayıtlı işi döndür (yoksa none:true)
 * POST action=clear  → işi sil
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-LiteSpeed-Cache-Control: no-cache');
@ini_set('display_errors', 0);
@set_time_limit(120);

$jobs = dirname(__DIR__) . '/jobs';
if (!is_dir($jobs)) @mkdir($jobs, 0755, true);
$file = $jobs . '/.cleanjob.json';

/* Eşzamanlı append'lerde bozulmasın: kilitle + atomik yaz. */
function cp_read($file) {
    if (!is_file($file)) return null;
    $j = json_decode((string) @file_get_contents($file), true);
    return is_array($j) ? $j : null;
}
function cp_write($file, $job) {
    $json = json_encode($job, JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    $tmp = $file . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, $json) !== strlen($json)) { @unlink($tmp); return false; }
    return @rename($tmp, $file);
}

$action = $_POST['action'] ?? '';

if ($action === 'start') {
    $job = [
        'created_at' => time(),
        'updated_at' => time(),
        'file_name'  => trim((string)($_POST['file_name'] ?? 'liste.csv')),
        'use_ai'     => !empty($_POST['use_ai']) ? 1 : 0,
        'total_in'   => (int)($_POST['total_in'] ?? 0),
        'authors'    => json_decode((string)($_POST['authors'] ?? '[]'), true) ?: [],
        'by_author'  => json_decode((string)($_POST['by_author'] ?? '{}'), true) ?: [],
        'done'       => [],     // tamamlanan yazar adları
        'works'      => [],     // birikmiş temiz eserler
        'removed'    => [],     // birikmiş elenenler
        'ai_fails'   => 0,
    ];
    // Onsite elemesi gibi başlangıçta hazır elenenler varsa onları da al
    $pre = json_decode((string)($_POST['removed'] ?? '[]'), true);
    if (is_array($pre)) $job['removed'] = $pre;

    cp_write($file, $job);
    echo json_encode(['ok' => true, 'total' => count($job['authors'])]);
    exit;
}

if ($action === 'append') {
    $lk = @fopen($file . '.lock', 'c');
    if ($lk) flock($lk, LOCK_EX);
    $job = cp_read($file);
    if (!$job) { if ($lk) { flock($lk, LOCK_UN); fclose($lk); } echo json_encode(['ok'=>false,'error'=>'iş yok']); exit; }

    $author  = trim((string)($_POST['author'] ?? ''));
    $works   = json_decode((string)($_POST['works']   ?? '[]'), true) ?: [];
    $removed = json_decode((string)($_POST['removed'] ?? '[]'), true) ?: [];

    if ($author !== '' && !in_array($author, $job['done'], true)) {
        $job['done'][] = $author;
        if ($works)   $job['works']   = array_merge($job['works'], $works);
        if ($removed) $job['removed'] = array_merge($job['removed'], $removed);
        if (!empty($_POST['ai_fail'])) $job['ai_fails'] = (int)$job['ai_fails'] + 1;
        $job['updated_at'] = time();
        cp_write($file, $job);
    }
    if ($lk) { flock($lk, LOCK_UN); fclose($lk); }

    echo json_encode(['ok'=>true, 'done'=>count($job['done']), 'total'=>count($job['authors'])]);
    exit;
}

if ($action === 'load') {
    $job = cp_read($file);
    if (!$job) { echo json_encode(['ok'=>true, 'none'=>true]); exit; }
    $done_set = array_flip($job['done'] ?? []);
    $pending  = array_values(array_filter($job['authors'] ?? [], fn($a) => !isset($done_set[$a])));
    echo json_encode([
        'ok'         => true,
        'none'       => false,
        'file_name'  => $job['file_name'] ?? '',
        'use_ai'     => (int)($job['use_ai'] ?? 0),
        'total_in'   => (int)($job['total_in'] ?? 0),
        'total'      => count($job['authors'] ?? []),
        'done'       => count($job['done'] ?? []),
        'pending'    => $pending,
        'by_author'  => $job['by_author'] ?? [],
        'works'      => $job['works'] ?? [],
        'removed'    => $job['removed'] ?? [],
        'ai_fails'   => (int)($job['ai_fails'] ?? 0),
        'updated_at' => (int)($job['updated_at'] ?? 0),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'clear') {
    @unlink($file);
    @unlink($file . '.lock');
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'bad action']);
