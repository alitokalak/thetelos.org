<?php
/**
 * proto-run.php — Prototip arka plan WORKER'ı (resumable state machine).
 *
 * Tarayıcı BEKLEMEZ: proto-start.php bunu fire-and-forget tetikler; ignore_user_abort
 * ile Cloudflare bağlantıyı kesse bile arka planda sürer. Her çağrıda ~45 sn'lik
 * bütçe kadar iş yapıp durum dosyasına yazar, iş bitmediyse halefini çağırır.
 * Böylece Cloudflare 524 (uzun tek istek) sorunu ortadan kalkar. Faz: acquire →
 * chunk → reduce → systemA → done. Tarayıcı proto-status.php ile yoklar.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/_proto.php';

$is_internal = isset($_REQUEST['_itok']) && hash_equals(proto_token(), (string) $_REQUEST['_itok']);
if (empty($_SESSION['tls_auth']) && !$is_internal) { http_response_code(401); exit; }
session_write_close();
ignore_user_abort(true); @ini_set('max_execution_time', '0'); @set_time_limit(0);
header('Content-Type: application/json');

$file = proto_job_file($_REQUEST['id'] ?? '');
if ($file === '' || !file_exists($file)) { echo json_encode(['status' => 'no_job']); exit; }

$budget = time() + 45;
while (time() < $budget) {
    $j = proto_job_read($file);
    if (!$j || ($j['status'] ?? '') === 'done') break;
    $phase = $j['phase'] ?? 'acquire';

    if ($phase === 'acquire') {
        proto_job_log($j, 'Kaynak (yasal tam metin) aranıyor: Gutenberg → Internet Archive…');
        proto_job_write($file, $j);
        $src = proto_acquire($j['book'], $j['author']);
        $j = proto_job_read($file) ?: $j;
        $j['debug'] = $src['debug'] ?? null;
        if (empty($src['found'])) {
            $j['resultB'] = ['state' => 'SOURCE_NOT_FOUND', 'msg' => 'Yasal/erişilebilir tam metin bulunamadı (Gutenberg + Internet Archive). Uydurma yapılmadı.'];
            proto_job_log($j, 'SOURCE_NOT_FOUND — tam metin yok');
            $j['phase'] = ($j['sys'] === 'B') ? 'done' : 'systemA';
            proto_job_write($file, $j); continue;
        }
        $text   = proto_clean($src['source'], $src['text']);
        $chunks = proto_chunks($text);
        $j['source'] = $src['source']; $j['url'] = $src['url'];
        $j['book_words'] = str_word_count(strip_tags($text));
        $j['chapters'] = proto_detect_chapters($text);
        $j['chunks'] = $chunks; $j['ci'] = 0; $j['notes'] = [];
        proto_job_log($j, 'Tam metin: ' . number_format($j['book_words']) . ' kelime · ' . $src['source'] . ' · ' . count($chunks) . ' parça');
        $j['phase'] = 'chunk';
        proto_job_write($file, $j); continue;
    }

    if ($phase === 'chunk') {
        $ci = (int) ($j['ci'] ?? 0); $tot = count($j['chunks'] ?? []);
        if ($ci < $tot) {
            proto_job_log($j, 'Parça ' . ($ci + 1) . '/' . $tot . ' analiz ediliyor (gerçek metinden)…');
            proto_job_write($file, $j);
            $r = tv_ask(proto_chunk_prompt($j['book'], $j['author'], $ci + 1, $tot, $j['chunks'][$ci]), 900, 220, 'deepseek');
            $t = !empty($r['ok']) ? trim($r['text']) : '';
            $j = proto_job_read($file) ?: $j;
            if ($t !== '' && stripos($t, 'no substantive content') === false) $j['notes'][] = '[Part ' . ($ci + 1) . "]\n" . $t;
            $j['ci'] = $ci + 1;
            proto_job_write($file, $j); continue;
        }
        $j['phase'] = 'reduce'; proto_job_write($file, $j); continue;
    }

    if ($phase === 'reduce') {
        if (empty($j['notes'])) {
            $j['resultB'] = ['state' => 'SOURCE_NOT_FOUND', 'msg' => 'Tam metin alındı ama analiz edilebilir içerik çıkarılamadı.'];
        } else {
            proto_job_log($j, 'Parça özetleri kapsamlı özete birleştiriliyor…');
            proto_job_write($file, $j);
            $r = tv_ask(proto_reduce_prompt($j['book'], $j['author'], implode("\n\n", $j['notes'])), 8000, 280, 'deepseek');
            $sum = !empty($r['ok']) ? trim($r['text']) : '';
            $html = $sum !== '' ? bw_md2html($sum) : '';
            $j = proto_job_read($file) ?: $j;
            $j['resultB'] = [
                'state' => $sum !== '' ? 'OK' : 'ERROR', 'source' => $j['source'] ?? '', 'url' => $j['url'] ?? '',
                'book_words' => $j['book_words'] ?? 0, 'chapters' => $j['chapters'] ?? [], 'chunks' => count($j['chunks'] ?? []),
                'summary_html' => $html, 'summary_words' => str_word_count(strip_tags($html)),
            ];
        }
        $j['phase'] = ($j['sys'] === 'both' || $j['sys'] === 'A') ? 'systemA' : 'done';
        proto_job_write($file, $j); continue;
    }

    if ($phase === 'systemA') {
        proto_job_log($j, 'Sistem A: başlık → DeepSeek (kaynaksız)…');
        proto_job_write($file, $j);
        $r = tv_ask(proto_systemA_prompt($j['book'], $j['author']), 6000, 240, 'deepseek');
        $as = !empty($r['ok']) ? trim($r['text']) : '';
        $ah = $as !== '' ? bw_md2html($as) : '';
        $j = proto_job_read($file) ?: $j;
        $j['resultA'] = ['state' => $as !== '' ? 'OK' : 'ERROR', 'summary_html' => $ah, 'summary_words' => str_word_count(strip_tags($ah))];
        $j['phase'] = 'done';
        proto_job_write($file, $j); continue;
    }

    // done
    $j['status'] = 'done'; proto_job_write($file, $j); break;
}

// Bütçe bitti ama iş sürüyorsa halefi çağır (30 sn içinde bir kez).
$j = proto_job_read($file);
if ($j && ($j['status'] ?? '') !== 'done') {
    if (time() - (int) ($j['spawned'] ?? 0) > 25) { $j['spawned'] = time(); proto_job_write($file, $j); proto_spawn($_REQUEST['id']); }
}
echo json_encode(['ok' => true]);
