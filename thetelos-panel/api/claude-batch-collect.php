<?php
/**
 * claude-batch-collect.php — Biten bir Claude Batch'in sonuçlarını TASLAK yaz.
 *
 * POST: batch_id
 * → durum yoklanır; bitmediyse pending döner. Bittiyse her başarılı sonuç,
 *   UNKNOWN olmayanlar, YENİ TASLAK yazı olarak WP'ye eklenir (post_status=draft),
 *   'authors' terimi atanır. Taslak = güvenli: sen inceleyip yayınlarsın.
 * İdempotent: bir kez yazılınca job dosyasına written=1 işaretlenir.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
require_once __DIR__ . '/_anthropic.php';
require_once __DIR__ . '/_content-format.php';   // bw_md2html

$batch_id = trim($_POST['batch_id'] ?? '');
if ($batch_id === '') { echo json_encode(['ok' => false, 'error' => 'batch_id boş']); exit; }

$jobs_dir = dirname(__DIR__) . '/jobs';
$job_file = "$jobs_dir/cbatch_" . preg_replace('/[^A-Za-z0-9_]/', '', $batch_id) . ".json";
$job = is_file($job_file) ? json_decode((string) @file_get_contents($job_file), true) : null;
$map = (is_array($job) && !empty($job['map'])) ? $job['map'] : [];

$st = tls_claude_batch_status($batch_id);
if (empty($st['ok'])) { echo json_encode(['ok' => false, 'error' => $st['error'] ?? 'durum alınamadı']); exit; }
if (empty($st['ended'])) {
    echo json_encode(['ok' => true, 'pending' => true, 'status' => $st['status'], 'counts' => $st['counts'] ?? []]);
    exit;
}
// Yalnız durum sorgusu (yazma yok).
if (!empty($_POST['check_only'])) {
    echo json_encode(['ok' => true, 'ended' => true, 'status' => 'ended', 'counts' => $st['counts'] ?? []]);
    exit;
}
if (is_array($job) && !empty($job['written'])) {
    echo json_encode(['ok' => true, 'already' => true, 'status' => 'ended', 'message' => 'Bu batch zaten taslağa yazılmıştı.']);
    exit;
}

$rr = tls_claude_batch_results($batch_id);
if (empty($rr['ok'])) { echo json_encode(['ok' => false, 'error' => $rr['error'] ?? 'sonuç alınamadı']); exit; }

// WordPress'i yükle (taslak yazmak için).
ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();

$written = []; $skipped = []; $errors = [];
foreach ($rr['results'] as $cid => $r) {
    $meta   = $map[$cid] ?? ['book_title' => $cid, 'author_name' => ''];
    $title  = trim((string) $meta['book_title']);
    $author = trim((string) $meta['author_name']);
    if (empty($r['ok'])) { $errors[] = ['book' => $title, 'error' => $r['error'] ?? 'hata']; continue; }
    $md = trim((string) $r['text']);
    // UNKNOWN / çok kısa → atla (uydurma koruması).
    $probe = mb_strtoupper(preg_replace('/[^A-Za-z]/', '', mb_substr($md, 0, 40)));
    if ($md === '' || strpos($probe, 'UNKNOWN') === 0 || str_word_count(strip_tags($md)) < 60) {
        $skipped[] = $title; continue;
    }
    $html = bw_md2html($md);
    $pid  = wp_insert_post([
        'post_title'   => $title,
        'post_content' => $html,
        'post_status'  => 'draft',
        'post_type'    => 'post',
    ], true);
    if (is_wp_error($pid) || !$pid) { $errors[] = ['book' => $title, 'error' => is_wp_error($pid) ? $pid->get_error_message() : 'insert 0']; continue; }
    if ($author !== '' && taxonomy_exists('authors')) {
        wp_set_object_terms((int) $pid, [$author], 'authors', false);
    }
    $written[] = ['book' => $title, 'post_id' => (int) $pid, 'edit_url' => admin_url('post.php?post=' . (int) $pid . '&action=edit')];
}

// İdempotent işaretle.
if (is_array($job)) {
    $job['written'] = 1; $job['written_at'] = time();
    $job['written_count'] = count($written);
    @file_put_contents($job_file, json_encode($job, JSON_UNESCAPED_UNICODE));
}

echo json_encode(['ok' => true, 'ended' => true,
    'written' => $written, 'written_count' => count($written),
    'skipped' => $skipped, 'skipped_count' => count($skipped),
    'errors' => $errors, 'error_count' => count($errors)]);
