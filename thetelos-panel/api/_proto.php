<?php
/**
 * _proto.php — Kaynak-odaklı özet prototipinin ORTAK yardımcıları.
 *
 * Tam metni (yasal) bul → temizle → parçala; parça/birleştirme istemleri.
 * Kaynaklar: Project Gutenberg (temiz kamu malı) → Internet Archive (OCR).
 * Worker (proto-run.php) bunları kullanır. DeepSeek çağrıları BLOKLU tv_ask ile
 * yapılır (worker arka planda, tarayıcı beklemiyor → Cloudflare sorunu yok).
 */
require_once __DIR__ . '/_verify.php';          // tv_ask, tls_fetch_json
require_once __DIR__ . '/_content-format.php';  // bw_md2html

/* ── Ham metin indir (JSON değil); $info'ya code/bytes/err yazar ────────── */
function proto_fetch_text($url, $tries = 3, &$info = null) {
    for ($i = 1; $i <= $tries; $i++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0 (compatible; thetelos-research/1.0)', 'Accept: text/plain,*/*'],
        ]);
        $r = curl_exec($ch); $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch);
        curl_close($ch);
        $info = ['code' => $c, 'bytes' => strlen((string) $r), 'err' => $err];
        if ($c === 200 && $r !== false && $r !== '') return (string) $r;
        if ($i < $tries && ($c === 429 || $c >= 500 || $c === 0 || $err !== '')) { usleep(500000 * $i); continue; }
        return '';
    }
    return '';
}

/* ── Project Gutenberg tam metin (gutendex) ────────────────────────────── */
function proto_gutenberg($book, $author) {
    $surname = '';
    if ($author !== '') { $ap = preg_split('/\s+/', trim($author)); $surname = mb_strtolower(end($ap), 'UTF-8'); }
    $j = tls_fetch_json('https://gutendex.com/books/?search=' . rawurlencode(trim($book . ' ' . $author)), 'thetelos.org/1.0', 20, 3);
    $results = $j['results'] ?? [];
    $debug = ['gutendex_results' => count($results), 'tried' => []];
    foreach ($results as $r) {
        $ok_auth = ($surname === '');
        foreach (($r['authors'] ?? []) as $a) {
            if ($surname !== '' && mb_stripos((string) ($a['name'] ?? ''), $surname) !== false) { $ok_auth = true; break; }
        }
        if (!$ok_auth) continue;
        $id = (int) ($r['id'] ?? 0);
        $is_book = fn($u) => stripos((string) $u, 'readme') === false && stripos((string) $u, '.zip') === false;
        $cands = [];
        foreach (($r['formats'] ?? []) as $mime => $u) if (stripos($mime, 'text/plain') !== false && $is_book($u)) $cands[] = (string) $u;
        if ($id) {
            $cands[] = "https://www.gutenberg.org/cache/epub/{$id}/pg{$id}.txt";
            foreach (['-0.txt', '-8.txt', '.txt'] as $suf) $cands[] = "https://www.gutenberg.org/files/{$id}/{$id}{$suf}";
            $cands[] = "https://www.gutenberg.org/ebooks/{$id}.txt.utf-8";
        }
        $cands = array_values(array_unique(array_filter($cands, $is_book)));
        $debug['match'] = ['id' => $id, 'title' => (string) ($r['title'] ?? '')];
        $best = ''; $best_url = '';
        $try = function ($u) use (&$best, &$best_url, &$debug, $is_book) {
            if (!$is_book($u)) return;
            $inf = null; $t = proto_fetch_text($u, 2, $inf);
            $debug['tried'][] = ['url' => $u, 'code' => $inf['code'] ?? 0, 'bytes' => $inf['bytes'] ?? 0];
            if (mb_strlen($t) > mb_strlen($best)) { $best = $t; $best_url = $u; }
        };
        foreach ($cands as $u) { $try($u); if (mb_strlen($best) > 150000) break; }
        if (mb_strlen($best) < 20000 && $id) {   // dizin listesi yedeği (standart-dışı adlar)
            $listing = proto_fetch_text("https://www.gutenberg.org/files/{$id}/", 2);
            if ($listing !== '' && preg_match_all('/href="([^"?]+\.txt)"/i', $listing, $mm)) {
                $seen = [];
                foreach ($mm[1] as $href) { $fn = basename($href); if (!$is_book($fn) || isset($seen[$fn])) continue; $seen[$fn] = 1; $try("https://www.gutenberg.org/files/{$id}/{$fn}"); if (mb_strlen($best) > 150000) break; }
            }
        }
        $debug['sample'] = mb_substr(trim($best), 0, 200);
        if (mb_strlen($best) < 5000) { $debug['note'] = 'gerçek tam metin bulunamadı'; continue; }
        return ['found' => true, 'url' => $best_url, 'title' => (string) ($r['title'] ?? $book), 'source' => 'Project Gutenberg', 'text' => $best, 'raw_len' => mb_strlen($best), 'debug' => $debug];
    }
    return ['found' => false, 'debug' => $debug];
}

/* ── Internet Archive tam metin (taranmış/OCR — _djvu.txt) ──────────────── */
function proto_archive($book, $author) {
    $surname = '';
    if ($author !== '') { $ap = preg_split('/\s+/', trim($author)); $surname = mb_strtolower(end($ap), 'UTF-8'); }
    $qp = ['mediatype:texts', 'title:(' . $book . ')'];
    if ($author !== '') $qp[] = 'creator:(' . $author . ')';
    $url = 'https://archive.org/advancedsearch.php?q=' . rawurlencode(implode(' AND ', $qp)) . '&fl[]=identifier&fl[]=title&fl[]=creator&rows=6&output=json&sort[]=downloads+desc';
    $j = tls_fetch_json($url, 'thetelos.org/1.0', 20, 3);
    $docs = $j['response']['docs'] ?? [];
    $debug = ['ia_results' => count($docs), 'tried' => []];
    foreach ($docs as $d) {
        $id = (string) ($d['identifier'] ?? ''); if ($id === '') continue;
        $cr = is_array($d['creator'] ?? '') ? implode(' ', $d['creator']) : (string) ($d['creator'] ?? '');
        $tt = (string) ($d['title'] ?? '');
        if ($surname !== '' && mb_stripos($cr, $surname) === false && mb_stripos($tt, $book) === false) continue;
        $meta = tls_fetch_json('https://archive.org/metadata/' . rawurlencode($id), 'thetelos.org/1.0', 15, 2);
        $txt = '';
        foreach (($meta['files'] ?? []) as $f) if (($f['format'] ?? '') === 'DjVuTXT' || preg_match('/_djvu\.txt$/i', (string) ($f['name'] ?? ''))) { $txt = $f['name']; break; }
        if ($txt === '') foreach (($meta['files'] ?? []) as $f) { $nm = (string) ($f['name'] ?? ''); if (preg_match('/\.txt$/i', $nm) && stripos($nm, 'meta') === false && stripos($nm, 'readme') === false) { $txt = $nm; break; } }
        if ($txt === '') { $debug['tried'][] = ['id' => $id, 'note' => 'txt yok']; continue; }
        $u = 'https://archive.org/download/' . rawurlencode($id) . '/' . rawurlencode($txt);
        $inf = null; $t = proto_fetch_text($u, 2, $inf);
        $debug['tried'][] = ['id' => $id, 'file' => $txt, 'code' => $inf['code'] ?? 0, 'bytes' => $inf['bytes'] ?? 0];
        if (mb_strlen($t) > 5000) return ['found' => true, 'url' => $u, 'title' => ($tt ?: $book), 'source' => 'Internet Archive', 'text' => $t, 'raw_len' => mb_strlen($t), 'debug' => $debug];
    }
    return ['found' => false, 'debug' => $debug];
}

/* ── Sıralı edinim: Gutenberg → Internet Archive ───────────────────────── */
function proto_acquire($book, $author) {
    $g = proto_gutenberg($book, $author);
    if (!empty($g['found'])) { $g['debug'] = ['gutenberg' => $g['debug']]; return $g; }
    $a = proto_archive($book, $author);
    $a['debug'] = ['gutenberg' => $g['debug'] ?? null, 'archive' => $a['debug'] ?? null];
    return $a;
}

function proto_clean_gutenberg($t) {
    $t = str_replace(["\r\n", "\r"], "\n", $t);
    if (preg_match('/\*\*\*\s*START OF (?:THE|THIS)? ?PROJECT GUTENBERG.*?\*\*\*/is', $t, $m, PREG_OFFSET_CAPTURE)) $t = substr($t, $m[0][1] + strlen($m[0][0]));
    if (preg_match('/\*\*\*\s*END OF (?:THE|THIS)? ?PROJECT GUTENBERG/is', $t, $m, PREG_OFFSET_CAPTURE)) $t = substr($t, 0, $m[0][1]);
    $t = preg_replace('/^\s*Produced by[^\n]*\n/im', '', $t);
    return trim($t);
}
function proto_clean_ocr($t) {
    $t = str_replace(["\r\n", "\r", "\x0c"], ["\n", "\n", "\n"], $t);
    $t = preg_replace('/\n[ \t]*\d{1,4}[ \t]*\n/', "\n", $t);
    $t = preg_replace('/[ \t]+/', ' ', $t);
    return trim(preg_replace('/\n{3,}/', "\n\n", $t));
}
function proto_clean($src, $text) { return $src === 'Internet Archive' ? proto_clean_ocr($text) : proto_clean_gutenberg($text); }

function proto_detect_chapters($t) {
    $heads = [];
    if (preg_match_all('/^\s*(BOOK|CHAPTER|SECTION|PART|Book|Chapter|Section|Part)\s+([IVXLCDM0-9]+)[.\s:—-]*([^\n]{0,80})/m', $t, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) { $heads[] = mb_substr(trim($x[1] . ' ' . $x[2] . (trim($x[3]) !== '' ? ' — ' . trim($x[3]) : '')), 0, 90); if (count($heads) >= 60) break; }
    }
    return $heads;
}
function proto_chunks($t, $max_chunks = 14, $target = 45000) {
    $len = mb_strlen($t);
    $n = max(3, min($max_chunks, (int) ceil($len / $target)));
    $size = (int) ceil($len / $n);
    $chunks = []; $pos = 0;
    for ($i = 0; $i < $n && $pos < $len; $i++) {
        $end = min($len, $pos + $size);
        if ($end < $len) { $nl = mb_strrpos(mb_substr($t, $pos, $end - $pos), "\n\n"); if ($nl !== false && $nl > $size * 0.5) $end = $pos + $nl; }
        $chunks[] = trim(mb_substr($t, $pos, $end - $pos)); $pos = $end;
    }
    return array_values(array_filter($chunks, fn($c) => mb_strlen($c) > 200));
}

/* ── İstemler ──────────────────────────────────────────────────────────── */
function proto_chunk_prompt($book, $author, $k, $n, $excerpt, $note_words = '150-280') {
    return "Below is an excerpt (part {$k} of {$n}) from the ACTUAL TEXT of the book \"{$book}\""
        . ($author ? " by {$author}" : '') . ".\n"
        . "Summarize the key content, ideas, arguments, reasoning, key concepts, and concrete examples ACTUALLY PRESENT in THIS excerpt. "
        . "Use ONLY what this text says — do NOT add outside knowledge, do NOT infer beyond the text. "
        . "If it is front-matter/index/notes with no substance, reply exactly: (no substantive content).\n"
        . "Write {$note_words} words of dense, faithful notes.\n\n=== EXCERPT ===\n" . mb_substr($excerpt, 0, 48000);
}
function proto_reduce_prompt($book, $author, $notes, $target = 'a thorough summary') {
    return "You are writing a faithful, source-based book summary for a books website, in English.\n"
        . "Below are ORDERED notes taken directly from the ACTUAL TEXT of \"{$book}\"" . ($author ? " by {$author}" : '') . ", part by part.\n"
        . "Using ONLY these notes (from the real text), write {$target} with these ## sections, omitting any you lack material for:\n"
        . "## About the Work\n## Context\n## Structure of the Book\n## Detailed Section-by-Section Summary\n## Main Arguments\n## Key Concepts\n## Themes\n## The Author's Conclusions\n## Significance\n\n"
        . "RULES: Base every statement on the notes (the real text). Do NOT invent chapter titles, quotations, examples, or claims not in the notes. Separate the book's actual content from outside/biographical context. Be comprehensive but do NOT pad or repeat to inflate length. Clear, engaged prose, third person. Do NOT restate the title as an H1; start with the ## sections.\n\n"
        . "=== NOTES FROM THE REAL TEXT ===\n" . mb_substr($notes, 0, 60000) . "\n=== END NOTES ===";
}
function proto_systemA_prompt($book, $author) {
    return "Write a comprehensive summary of the book \"{$book}\"" . ($author ? " by {$author}" : '') . " in English, "
        . "covering its structure, main arguments, key concepts, themes, and conclusions. Aim for depth.";
}

/* ── TEK ÇAĞRIDA kaynak-temelli özet üretimi (batch + içerik düzeltme kullanır)
   Uzunluk: 'kisa' | 'standart' | 'kapsamli'. on_beat heartbeat.
   Dönüş: ['found'=>bool, 'insufficient'=>bool, 'md'=>string, 'source'=>..,
           'url'=>.., 'book_words'=>int, 'chunks'=>int, 'chapters'=>[..],
           'model'=>'deepseek'|'gemini', 'debug'=>..] */
function proto_generate($book, $author, $opts = []) {
    $len = in_array($opts['length'] ?? 'standart', ['kisa', 'standart', 'kapsamli'], true) ? $opts['length'] : 'standart';
    $beat = is_callable($opts['on_beat'] ?? null) ? $opts['on_beat'] : function () {};
    $cfg = [
        'kisa'     => ['max' => 6,  'notes' => '110-170', 'target' => 'a focused summary of about 1200-1800 words'],
        'standart' => ['max' => 12, 'notes' => '150-260', 'target' => 'a thorough summary of about 2500-3800 words'],
        'kapsamli' => ['max' => 18, 'notes' => '220-340', 'target' => 'as comprehensive a summary as the material supports (5000+ words for a long book), fully developing every section'],
    ][$len];

    // Sağlayıcı: DeepSeek erişilebiliyorsa onu (ucuz), yoksa Gemini. İş başına bir kez.
    $prov = ($opts['provider'] ?? 'auto');
    if ($prov === 'auto') $prov = (proto_openrouter_key() !== '' || proto_deepseek_reachable()) ? 'auto' : 'gemini';
    $model_label = ($prov === 'gemini') ? 'gemini' : 'deepseek';

    $src = proto_acquire($book, $author);
    $beat();
    if (empty($src['found'])) return ['found' => false, 'insufficient' => true, 'debug' => $src['debug'] ?? null];

    $text   = proto_clean($src['source'], $src['text']);
    $chunks = proto_chunks($text, $cfg['max']);
    $notes  = [];
    foreach ($chunks as $i => $ch) {
        $beat();
        $dg = '';
        $t = proto_ds(proto_chunk_prompt($book, $author, $i + 1, count($chunks), $ch, $cfg['notes']), 1200, $beat, 280, $dg, $prov);
        if ($t !== '' && stripos($t, 'no substantive content') === false) $notes[] = '[Part ' . ($i + 1) . "]\n" . $t;
    }
    if (!$notes) return ['found' => true, 'insufficient' => true, 'source' => $src['source'], 'model' => $model_label];

    $beat();
    $dg2 = '';
    $md = proto_ds(proto_reduce_prompt($book, $author, implode("\n\n", $notes), $cfg['target']), 8000, $beat, 300, $dg2, $prov);
    if (trim($md) === '') return ['found' => true, 'insufficient' => true, 'source' => $src['source'], 'model' => $model_label, 'error' => $dg2];

    return ['found' => true, 'insufficient' => false, 'md' => $md, 'source' => $src['source'], 'url' => $src['url'],
            'book_words' => str_word_count(strip_tags($text)), 'chunks' => count($chunks),
            'chapters' => proto_detect_chapters($text), 'model' => $model_label];
}

/* ── OpenRouter anahtarı (DeepSeek'i erişilebilir kapıdan kullanmak için) ── */
function proto_openrouter_key() {
    foreach (['OPENROUTER_KEY', 'OPENROUTER_API_KEY', 'OPENROUTER'] as $c) {
        if (defined($c) && constant($c)) return (string) constant($c);
    }
    return '';
}

/* ── OpenRouter üzerinden DeepSeek (bloklu, OpenAI-uyumlu) ──────────────────
   Sunucu api.deepseek.com'a (AWS IP) bağlanamıyor; OpenRouter (Cloudflare,
   erişilebilir) DeepSeek'e bizim yerimize gidip cevabı getirir. Model varsayılan
   deepseek/deepseek-chat — DeepSeek'in ucuz V3'ü. */
function proto_openrouter($prompt, $max_tokens, &$diag = null) {
    $key = proto_openrouter_key();
    $model = (defined('OPENROUTER_MODEL') && OPENROUTER_MODEL) ? OPENROUTER_MODEL : 'deepseek/deepseek-chat';
    $body = json_encode(['model' => $model, 'max_tokens' => max(300, min(8000, (int) $max_tokens)),
        'temperature' => 0.3, 'messages' => [['role' => 'user', 'content' => $prompt]]], JSON_UNESCAPED_UNICODE);
    for ($try = 1; $try <= 3; $try++) {
        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_TIMEOUT => 280,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key,
                'HTTP-Referer: https://thetelos.org', 'X-Title: The Telos'],
            CURLOPT_POSTFIELDS => $body,
        ]);
        $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $err = curl_error($ch);
        curl_close($ch);
        $j = json_decode((string) $r, true);
        $txt = trim((string) ($j['choices'][0]['message']['content'] ?? ''));
        if ($txt !== '') { $diag = 'openrouter[' . $model . '] http=' . $code . ' chars=' . mb_strlen($txt); return $txt; }
        if ($try < 3 && ($code === 429 || $code >= 500 || $code === 0 || $err !== '')) { sleep(2 * $try); continue; }
        $msg = $j['error']['message'] ?? ($err ?: 'boş');
        $diag = 'openrouter[' . $model . '] http=' . $code . ' err=' . mb_substr((string) $msg, 0, 160);
        return '';
    }
    return '';
}

/* ── DeepSeek'e TCP bağlanabiliyor muyuz? (iş başına BİR kez ölçülür) ──────
   Engelliyken her parçada 10 sn boşa beklememek için. Bağlantı kurulursa true. */
function proto_deepseek_reachable() {
    if (!defined('DEEPSEEK_KEY') || !DEEPSEEK_KEY) return false;
    $ch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 10]);
    curl_exec($ch);
    $ct = (float) curl_getinfo($ch, CURLINFO_CONNECT_TIME);
    $err = curl_error($ch);
    curl_close($ch);
    return ($ct > 0 && $err === '');   // TCP el sıkışması gerçekleşti
}

/* ── Doğrudan DeepSeek (ucuz; engel kalkınca OTOMATİK kullanılır) ─────────── */
function proto_deepseek_direct($prompt, $max_tokens, &$diag = null, $ping = null) {
    $model = (defined('DEEPSEEK_MODEL') && !in_array(DEEPSEEK_MODEL, ['deepseek-chat', 'deepseek-reasoner'], true))
           ? DEEPSEEK_MODEL : 'deepseek-v4-flash';
    // thinking:disabled → yanıt doğrudan content'e (temiz); v4-flash aksi halde
    // reasoning_content'e koyuyor. Model reddederse (400) parametresiz tekrar.
    $mk = fn($think) => json_encode(array_filter([
        'model' => $model, 'max_tokens' => max(300, min(8000, (int) $max_tokens)), 'temperature' => 0.3,
        'thinking' => $think ? ['type' => 'disabled'] : null,
        'messages' => [['role' => 'user', 'content' => $prompt]],
    ], fn($v) => $v !== null), JSON_UNESCAPED_UNICODE);
    $last = time();
    $do = function ($body) use (&$code, &$err, $ping, &$last) {
        $ch = curl_init(DEEPSEEK_API_URL);
        $o = [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 280, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
            CURLOPT_POSTFIELDS => $body];
        // Bloklu çağrı boyunca heartbeat: yoksa uzun reduce'ta kitap "ölü" sanılıp
        // başka worker'a geçebiliyordu (180 sn eşik). XFERINFOFUNCTION düzenli çağrılır.
        if (is_callable($ping)) {
            $o[CURLOPT_NOPROGRESS] = false;
            $o[CURLOPT_XFERINFOFUNCTION] = function () use ($ping, &$last) { if (time() - $last >= 5) { $ping(); $last = time(); } return 0; };
        }
        curl_setopt_array($ch, $o);
        $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $err = curl_error($ch);
        curl_close($ch);
        return $r;
    };
    $r = $do($mk(true));
    if ((int) $code === 400) $r = $do($mk(false));   // thinking desteklenmiyorsa
    $j = json_decode((string) $r, true);
    $txt = trim((string) ($j['choices'][0]['message']['content'] ?? ''));
    if ($txt === '') $txt = trim((string) ($j['choices'][0]['message']['reasoning_content'] ?? ''));
    $diag = 'deepseek http=' . (int) $code . ($err ? ' err=' . $err : '') . ' chars=' . mb_strlen($txt);
    return $txt;
}

/* ── Metin üretimi — SAĞLAYICI SEÇİMİ ──────────────────────────────────────
   Öncelik: DeepSeek (ucuz, senin istediğin). Sunucu ↔ DeepSeek şu an engelli;
   engel kalkınca hiçbir ayar değişmeden OTOMATİK DeepSeek'e döner. Erişilemezse
   Gemini yedeği devreye girer, iş HİÇ durmaz.
   $provider: 'gemini' → DeepSeek denemeyi atla (iş başına tek ölçümle karar
   verilir, boşuna beklememek için). OPENROUTER_KEY tanımlıysa DeepSeek'i onun
   üzerinden dener (opsiyonel, gerekmez). */
function proto_ds($prompt, $max_tokens, $ping = null, $timeout = 280, &$diag = null, $provider = 'auto') {
    if (is_callable($ping)) $ping();
    if ($provider !== 'gemini') {
        // 1) OpenRouter (yalnız anahtar eklenmişse)
        if (proto_openrouter_key() !== '') { $t = proto_openrouter($prompt, $max_tokens, $diag); if ($t !== '') return $t; }
        // 2) Doğrudan DeepSeek (çağrı boyunca heartbeat için $ping geçilir)
        $t = proto_deepseek_direct($prompt, $max_tokens, $diag, $ping); if ($t !== '') return $t;
    }
    // 3) Gemini yedeği
    require_once __DIR__ . '/_gemini.php';
    if (!tls_gemini_ready()) { if (empty($diag)) $diag = 'Gemini anahtarı yok'; return ''; }
    $r = tls_gemini('', $prompt, [
        'max_tokens'  => max(500, min(24000, (int) $max_tokens * 2)),
        'temperature' => 0.3, 'timeout' => $timeout, 'retries' => 2,
        'on_beat'     => is_callable($ping) ? $ping : null,
    ]);
    $g = 'gemini http=' . ($r['http'] ?? '?') . ' ' . (!empty($r['ok']) ? ('chars=' . mb_strlen((string) $r['text'])) : ('err=' . ($r['error'] ?? '?')));
    $diag = ($diag ? $diag . ' → ' : '') . $g;
    return !empty($r['ok']) ? trim((string) $r['text']) : '';
}

/* ── Worker'ı fire-and-forget tetikle (batch deseniyle aynı) ────────────── */
function proto_token() { return hash('sha256', WP_APP_PASS . '|tls-proto'); }
function proto_spawn($id) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = dirname(strtok($_SERVER['REQUEST_URI'] ?? '/thetelos-panel/api/x.php', '?'));
    $url    = $scheme . '://' . $host . rtrim($base, '/') . '/proto-run.php';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['id' => $id, '_itok' => proto_token()]),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 5,
        CURLOPT_NOSIGNAL => 1, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    curl_exec($ch); curl_close($ch);
}

function proto_job_file($id) {
    $id = preg_replace('/[^a-z0-9_]/', '', (string) $id);
    return $id === '' ? '' : dirname(__DIR__) . '/jobs/proto_' . $id . '.json';
}
function proto_job_read($file) { $j = @file_get_contents($file); $j = $j ? json_decode($j, true) : null; return is_array($j) ? $j : null; }
function proto_job_write($file, $j) { $j['updated'] = time(); @file_put_contents($file, json_encode($j, JSON_UNESCAPED_UNICODE)); }
function proto_job_log(&$j, $m) { $j['log'][] = $m; if (count($j['log']) > 80) array_shift($j['log']); }
