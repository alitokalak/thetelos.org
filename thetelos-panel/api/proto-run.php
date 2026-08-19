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

/* TEK WORKER: aynı işe paralel worker'lar çakışıp adımları tekrarlıyordu.
   Özel (exclusive) non-blocking kilit — alamayan worker hemen çıkar. */
$lockf = fopen($file . '.lock', 'c');
if (!$lockf || !flock($lockf, LOCK_EX | LOCK_NB)) { echo json_encode(['status' => 'busy']); exit; }

$ping = function () use ($file) { $j = proto_job_read($file); if ($j) proto_job_write($file, $j); };
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
        // Parçaları SIRAYLA işle (içerik seyrek/erken olabilir). ÇÖP EŞLEŞME
        // koruması: ilk 6 parça tamamen boşsa dur (409k Mill gibi alakasız metin).
        $nc = count($chunks);
        $j['chunks'] = $chunks; $j['order'] = range(0, max(0, $nc - 1)); $j['probe_n'] = min($nc, 6); $j['pi'] = 0; $j['notes'] = [];
        proto_job_log($j, 'Tam metin: ' . number_format($j['book_words']) . ' kelime · ' . $src['source'] . ' · ' . count($chunks) . ' parça');
        // Model: DeepSeek'e bağlanabiliyorsak onu kullan (ucuz), yoksa Gemini.
        // İş başına bir kez ölçülür; engel kalkınca sonraki çalıştırmada otomatik döner.
        if (proto_openrouter_key() !== '') { $j['prov'] = 'auto'; proto_job_log($j, 'Model: DeepSeek (OpenRouter üzerinden)'); }
        elseif (proto_deepseek_reachable()) { $j['prov'] = 'auto'; proto_job_log($j, 'Model: DeepSeek (doğrudan) ✓ erişilebilir'); }
        else { $j['prov'] = 'gemini'; proto_job_log($j, 'Model: DeepSeek şu an erişilemiyor → Gemini kullanılıyor (engel kalkınca otomatik döner)'); }
        $j['phase'] = 'chunk';
        proto_job_write($file, $j); continue;
    }

    if ($phase === 'chunk') {
        $order = $j['order'] ?? array_keys($j['chunks'] ?? []); $tot = count($order);
        $pi = (int) ($j['pi'] ?? 0);
        if ($pi < $tot) {
            $idx = (int) $order[$pi];
            proto_job_log($j, 'Parça ' . ($idx + 1) . '/' . $tot . ' analiz ediliyor (gerçek metinden)…');
            proto_job_write($file, $j);
            $diag = '';
            $t = proto_ds(proto_chunk_prompt($j['book'], $j['author'], $idx + 1, $tot, $j['chunks'][$idx]), 900, $ping, 280, $diag, $j['prov'] ?? 'auto');
            $j = proto_job_read($file) ?: $j;
            if ($t !== '' && !preg_match('/^\W{0,4}no substantive content/i', ltrim($t))) {
                $j['notes'][(string) $idx] = '[Part ' . ($idx + 1) . "]\n" . $t;   // gerçek not
            } elseif ($t === '') {
                proto_job_log($j, '  ⚠ parça ' . ($idx + 1) . ' boş döndü — sağlayıcı ' . $diag);
            } else {
                proto_job_log($j, '  — parça ' . ($idx + 1) . ' içerik yok (metin bu kitaba ait olmayabilir)');
            }
            $j['pi'] = $pi + 1;
            // ÇÖP EŞLEŞME koruması: ilk 6 parça tamamen boşsa dur.
            if ($j['pi'] >= (int) ($j['probe_n'] ?? $tot) && empty($j['notes'])) {
                proto_job_log($j, '⏹ İlk ' . $j['pi'] . ' parçadan analiz edilebilir not çıkmadı → durduruldu (alakasız/çöp eşleşme).');
                $j['phase'] = 'reduce';
            }
            proto_job_write($file, $j); continue;
        }
        $j['phase'] = 'reduce'; proto_job_write($file, $j); continue;
    }

    if ($phase === 'reduce') {
        if (empty($j['notes'])) {
            $j['resultB'] = ['state' => 'SOURCE_NOT_FOUND',
                'msg' => ($j['source'] ?? 'Kaynak') . ' metni alındı ama bu kitaba ait analiz edilebilir içerik çıkarılamadı '
                       . '(muhtemelen yanlış eşleşme). Uydurma yapılmadı → Bilgi Metni gerekiyor.'];
        } else {
            proto_job_log($j, 'Parça özetleri kapsamlı özete birleştiriliyor…');
            proto_job_write($file, $j);
            $ordered = $j['notes']; ksort($ordered, SORT_NUMERIC);   // parça sırasına diz
            $sum = proto_ds(proto_reduce_prompt($j['book'], $j['author'], implode("\n\n", $ordered)), 8000, $ping, 300, $dd, $j['prov'] ?? 'auto');
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
        proto_job_log($j, 'Sistem A: başlık → Gemini (kaynaksız)…');
        proto_job_write($file, $j);
        $as = proto_ds(proto_systemA_prompt($j['book'], $j['author']), 6000, $ping, 280, $da, $j['prov'] ?? 'auto');
        $ah = $as !== '' ? bw_md2html($as) : '';
        $j = proto_job_read($file) ?: $j;
        $j['resultA'] = ['state' => $as !== '' ? 'OK' : 'ERROR', 'summary_html' => $ah, 'summary_words' => str_word_count(strip_tags($ah))];
        $j['phase'] = 'done';
        proto_job_write($file, $j); continue;
    }

    // done
    $j['status'] = 'done'; proto_job_write($file, $j); break;
}

// Bütçe bitti ama iş sürüyorsa: ÖNCE kilidi bırak, SONRA halefi çağır
// (yoksa halef kilidi alamaz). İş bittiyse sadece kilidi bırak.
$j = proto_job_read($file);
$need_successor = ($j && ($j['status'] ?? '') !== 'done');
if ($need_successor) { $j['spawned'] = time(); proto_job_write($file, $j); }
flock($lockf, LOCK_UN); fclose($lockf);
if ($need_successor) proto_spawn($_REQUEST['id']);
echo json_encode(['ok' => true]);
