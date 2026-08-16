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
function proto_chunk_prompt($book, $author, $k, $n, $excerpt) {
    return "Below is an excerpt (part {$k} of {$n}) from the ACTUAL TEXT of the book \"{$book}\""
        . ($author ? " by {$author}" : '') . ".\n"
        . "Summarize the key content, ideas, arguments, reasoning, key concepts, and concrete examples ACTUALLY PRESENT in THIS excerpt. "
        . "Use ONLY what this text says — do NOT add outside knowledge, do NOT infer beyond the text. "
        . "If it is front-matter/index/notes with no substance, reply exactly: (no substantive content).\n"
        . "Write 150-280 words of dense, faithful notes.\n\n=== EXCERPT ===\n" . mb_substr($excerpt, 0, 48000);
}
function proto_reduce_prompt($book, $author, $notes) {
    return "You are writing a COMPREHENSIVE, faithful book summary for a books website, in English.\n"
        . "Below are ORDERED notes taken directly from the ACTUAL TEXT of \"{$book}\"" . ($author ? " by {$author}" : '') . ", part by part.\n"
        . "Using ONLY these notes (from the real text), write a thorough summary with these ## sections, omitting any you lack material for:\n"
        . "## About the Work\n## Context\n## Structure of the Book\n## Detailed Section-by-Section Summary\n## Main Arguments\n## Key Concepts\n## Themes\n## The Author's Conclusions\n## Significance\n\n"
        . "RULES: Base every statement on the notes (the real text). Do NOT invent chapter titles, quotations, examples, or claims not in the notes. Separate the book's actual content from outside/biographical context. Be comprehensive but do NOT pad or repeat to inflate length. Clear, engaged prose, third person.\n\n"
        . "=== NOTES FROM THE REAL TEXT ===\n" . mb_substr($notes, 0, 40000) . "\n=== END NOTES ===";
}
function proto_systemA_prompt($book, $author) {
    return "Write a comprehensive summary of the book \"{$book}\"" . ($author ? " by {$author}" : '') . " in English, "
        . "covering its structure, main arguments, key concepts, themes, and conclusions. Aim for depth.";
}

/* ── DeepSeek STREAMING çağrı — bloklu tv_ask bu bağlamda boş dönüyordu;
   streaming güvenilir çalışıyor. $ping (varsa) canlılık için periyodik çağrılır. */
function proto_ds($prompt, $max_tokens, $ping = null, $timeout = 280, &$diag = null) {
    if (!defined('DEEPSEEK_KEY') || !DEEPSEEK_KEY) { $diag = 'DEEPSEEK_KEY yok'; return ''; }
    $model = (defined('DEEPSEEK_MODEL') && !in_array(DEEPSEEK_MODEL, ['deepseek-chat', 'deepseek-reasoner'], true))
           ? DEEPSEEK_MODEL : 'deepseek-v4-flash';
    $full = ''; $buf = ''; $last = time();
    $cb = function ($ch, $chunk) use (&$full, &$buf, &$last, $ping) {
        $buf .= $chunk;
        while (($p = strpos($buf, "\n")) !== false) {
            $line = trim(substr($buf, 0, $p)); $buf = substr($buf, $p + 1);
            if (strpos($line, 'data:') !== 0) continue;
            $d = trim(substr($line, 5));
            if ($d === '' || $d === '[DONE]') continue;
            $j = json_decode($d, true);
            $t = $j['choices'][0]['delta']['content'] ?? '';
            if ($t === '') $t = $j['choices'][0]['delta']['reasoning_content'] ?? '';
            if ($t !== '') $full .= $t;
        }
        if ($ping && time() - $last >= 5) { $ping(); $last = time(); }
        return strlen($chunk);
    };
    // NOT: 'thinking:disabled' parametresi streaming uç noktasında boş yanıta
    // yol açıyordu; çalışan örnekler (generate.php, batch-worker) onu HİÇ
    // göndermiyor. reasoning_content yedeği zaten parser'da var.
    $payload = ['model' => $model, 'max_tokens' => $max_tokens, 'temperature' => 0.3, 'stream' => true,
                'messages' => [['role' => 'user', 'content' => $prompt]]];
    $ch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 30,
        // IPv4'e zorla: sunucunun kırık IPv6 rotası DeepSeek'e bağlanmayı 15 sn
        // askıda bırakıp zaman aşımına düşürüyordu (Gutenberg IPv4 olduğundan
        // sorunsuzdu). Gerçek örneklerde de güvenli.
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_WRITEFUNCTION => $cb,
    ]);
    $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $err = curl_error($ch);
    curl_close($ch);
    $diag = 'code=' . $code . ($err ? ' err=' . $err : '') . ' chars=' . mb_strlen($full);
    return trim($full);
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
