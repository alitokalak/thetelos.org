<?php
/**
 * proto.php — KAYNAK-ODAKLI ÖZET PROTOTİPİ (Sistem B) + karşılaştırma (Sistem A).
 *
 * AMAÇ: "Kitap adı → AI → özet" (halüsinasyon kaynağı) yerine:
 *   Kitabı bul → GERÇEK tam metni al (Project Gutenberg, kamu malı, yasal) →
 *   temizle → parçalara ayır → her parçayı YALNIZ o metinden özetle (DeepSeek) →
 *   parça özetlerini kapsamlı, yapılandırılmış bir özete birleştir.
 * Tam metin yoksa SOURCE_NOT_FOUND — uydurma YOK.
 *
 * Bu SSE uç noktası ilerlemeyi canlı yayınlar. Hiçbir şey WordPress'e gitmez;
 * yalnız test/karşılaştırma. Model katmanı DeepSeek (ucuz) — sonra değiştirilebilir.
 *
 * KULLANIM: prototype.php formundan çağrılır.
 *   ?book=&author=&lang=&year=&isbn=&sys=both|A|B
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); echo 'auth'; exit; }

@set_time_limit(0);
@ignore_user_abort(true);
while (ob_get_level() > 0) ob_end_flush();
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

require_once __DIR__ . '/_verify.php';         // tv_ask (DeepSeek), tls_fetch_json
require_once __DIR__ . '/_content-format.php'; // bw_md2html

function sse($event, $data) {
    echo "event: $event\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    @ob_flush(); @flush();
}
$beat = function () { echo ": ping\n\n"; @ob_flush(); @flush(); };

$book   = trim($_GET['book']   ?? '');
$author = trim($_GET['author'] ?? '');
$sys    = $_GET['sys'] ?? 'both';
if ($book === '') { sse('error', ['error' => 'Kitap adı gerekli']); exit; }

/* ── Ham metin indir (JSON değil) — retry'li; $info'ya code/bytes/err yazar ─ */
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

/* ── Project Gutenberg'de tam metin bul (gutendex API) ──────────────────── */
function proto_gutenberg($book, $author) {
    $surname = '';
    if ($author !== '') { $ap = preg_split('/\s+/', trim($author)); $surname = mb_strtolower(end($ap), 'UTF-8'); }
    $q = trim($book . ' ' . $author);
    $j = tls_fetch_json('https://gutendex.com/books/?search=' . rawurlencode($q), 'thetelos.org/1.0', 20, 3);
    $results = $j['results'] ?? [];
    $debug = ['gutendex_results' => count($results), 'tried' => []];
    foreach ($results as $r) {
        // Yazar teyidi (yanlış kitabı almamak için).
        $ok_auth = ($surname === '');
        foreach (($r['authors'] ?? []) as $a) {
            if ($surname !== '' && mb_stripos((string) ($a['name'] ?? ''), $surname) !== false) { $ok_auth = true; break; }
        }
        if (!$ok_auth) continue;

        // TAM METİN için aday URL'ler: önce KANONİK cache URL (en güvenilir,
        // tüm kitap), sonra ebook .txt, sonra gutendex'in verdiği text/plain'ler.
        // Hepsini dener, EN BÜYÜK metni seçer (kısa/robot sayfası eleme).
        $id = (int) ($r['id'] ?? 0);
        $cands = [];
        if ($id) {
            $cands[] = "https://www.gutenberg.org/cache/epub/{$id}/pg{$id}.txt";
            $cands[] = "https://www.gutenberg.org/files/{$id}/{$id}-0.txt";
            $cands[] = "https://www.gutenberg.org/ebooks/{$id}.txt.utf-8";
        }
        foreach (($r['formats'] ?? []) as $mime => $u) {
            if (stripos($mime, 'text/plain') !== false && stripos((string) $u, '.zip') === false) $cands[] = (string) $u;
        }
        $cands = array_values(array_unique($cands));

        $debug['match'] = ['id' => $id, 'title' => (string) ($r['title'] ?? '')];
        $best = ''; $best_url = '';
        foreach ($cands as $u) {
            $inf = null;
            $t = proto_fetch_text($u, 3, $inf);
            $debug['tried'][] = ['url' => $u, 'code' => $inf['code'] ?? 0, 'bytes' => $inf['bytes'] ?? 0, 'err' => $inf['err'] ?? ''];
            if (mb_strlen($t) > mb_strlen($best)) { $best = $t; $best_url = $u; }
            if (mb_strlen($best) > 150000) break;   // yeterince büyük → dur
        }
        $debug['sample'] = mb_substr(trim($best), 0, 240);
        if (mb_strlen($best) < 5000) { $debug['note'] = 'en büyük aday < 5000 karakter'; continue; }
        return ['found' => true, 'url' => $best_url, 'title' => (string) ($r['title'] ?? $book),
                'source' => 'Project Gutenberg', 'text' => $best, 'raw_len' => mb_strlen($best), 'debug' => $debug];
    }
    return ['found' => false, 'debug' => $debug];
}

/* ── Gutenberg başlık/altbilgisini temizle ─────────────────────────────── */
function proto_clean_gutenberg($t) {
    $t = str_replace(["\r\n", "\r"], "\n", $t);
    if (preg_match('/\*\*\*\s*START OF (?:THE|THIS)? ?PROJECT GUTENBERG.*?\*\*\*/is', $t, $m, PREG_OFFSET_CAPTURE)) {
        $t = substr($t, $m[0][1] + strlen($m[0][0]));
    }
    if (preg_match('/\*\*\*\s*END OF (?:THE|THIS)? ?PROJECT GUTENBERG/is', $t, $m, PREG_OFFSET_CAPTURE)) {
        $t = substr($t, 0, $m[0][1]);
    }
    // Yeniden yayın/transcriber notlarını kabaca kırp (ilk "produced by" bloğu).
    $t = preg_replace('/^\s*Produced by[^\n]*\n/im', '', $t);
    return trim($t);
}

/* ── Bölüm başlıklarını tespit et (gösterim için) ──────────────────────── */
function proto_detect_chapters($t) {
    $heads = [];
    if (preg_match_all('/^\s*(BOOK|CHAPTER|SECTION|PART|Book|Chapter|Section|Part)\s+([IVXLCDM0-9]+)[.\s:—-]*([^\n]{0,80})/m', $t, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            $h = trim($x[1] . ' ' . $x[2] . (trim($x[3]) !== '' ? ' — ' . trim($x[3]) : ''));
            $heads[] = mb_substr($h, 0, 90);
            if (count($heads) >= 60) break;
        }
    }
    return $heads;
}

/* ── Metni ≤N büyük parçaya böl (bölüm sınırına yakın) ──────────────────── */
function proto_chunks($t, $max_chunks = 14, $target = 45000) {
    $len = mb_strlen($t);
    $n = max(3, min($max_chunks, (int) ceil($len / $target)));
    $size = (int) ceil($len / $n);
    $chunks = []; $pos = 0;
    for ($i = 0; $i < $n && $pos < $len; $i++) {
        $end = min($len, $pos + $size);
        // Paragraf sınırına kaydır (son \n\n).
        if ($end < $len) {
            $nl = mb_strrpos(mb_substr($t, $pos, $end - $pos), "\n\n");
            if ($nl !== false && $nl > $size * 0.5) $end = $pos + $nl;
        }
        $chunks[] = trim(mb_substr($t, $pos, $end - $pos));
        $pos = $end;
    }
    return array_values(array_filter($chunks, fn($c) => mb_strlen($c) > 200));
}

/* ══ SİSTEM B: kaynak-odaklı ═══════════════════════════════════════════ */
$B = null;
if ($sys === 'both' || $sys === 'B') {
    $tB0 = microtime(true);
    sse('status', ['sys' => 'B', 'msg' => 'Gerçek tam metin aranıyor (Project Gutenberg)…']);
    $src = proto_gutenberg($book, $author);
    $beat();
    if (!empty($src['debug'])) sse('debug', $src['debug']);

    if (empty($src['found'])) {
        sse('resultB', [
            'state' => 'SOURCE_NOT_FOUND',
            'msg'   => 'Bu eserin yasal/erişilebilir tam metni bulunamadı (Project Gutenberg). Uydurma yapılmadı.',
            'time'  => round(microtime(true) - $tB0, 1),
        ]);
    } else {
        $text = proto_clean_gutenberg($src['text']);
        $words = str_word_count(strip_tags($text));
        $chapters = proto_detect_chapters($text);
        $rawkb = round(($src['raw_len'] ?? mb_strlen($src['text'])) / 1024);
        sse('status', ['sys' => 'B', 'msg' => 'Tam metin alındı: ' . number_format($words) . ' kelime (' . $rawkb . ' KB ham) · kaynak: ' . $src['source'] . ' · ' . count($chapters) . ' bölüm/başlık']);

        $chunks = proto_chunks($text);
        $notes = [];
        foreach ($chunks as $i => $ch) {
            $k = $i + 1; $n = count($chunks);
            sse('status', ['sys' => 'B', 'msg' => "Bölüm/parça $k/$n analiz ediliyor (gerçek metinden)…"]);
            $cp = "Below is an excerpt (part {$k} of {$n}) from the ACTUAL TEXT of the book \"{$book}\""
                . ($author ? " by {$author}" : '') . ".\n"
                . "Summarize the key content, ideas, arguments, reasoning, key concepts, and concrete examples that are ACTUALLY PRESENT in THIS excerpt. "
                . "Use ONLY what this text says — do NOT add outside knowledge, do NOT infer beyond the text. "
                . "If the excerpt is front-matter/index/notes with no substance, reply exactly: (no substantive content).\n"
                . "Write 150-280 words of dense, faithful notes.\n\n=== EXCERPT ===\n"
                . mb_substr($ch, 0, 48000);
            $r = tv_ask($cp, 900, 180, 'deepseek');
            $beat();
            $t = !empty($r['ok']) ? trim($r['text']) : '';
            if ($t !== '' && stripos($t, 'no substantive content') === false) $notes[] = "[Part {$k}]\n" . $t;
        }

        if (!$notes) {
            sse('resultB', ['state' => 'SOURCE_NOT_FOUND',
                'msg' => 'Metin alındı ama analiz edilebilir içerik çıkarılamadı.',
                'time' => round(microtime(true) - $tB0, 1)]);
        } else {
            sse('status', ['sys' => 'B', 'msg' => 'Parça özetleri kapsamlı özete birleştiriliyor…']);
            $joined = mb_substr(implode("\n\n", $notes), 0, 40000);
            $rp = "You are writing a COMPREHENSIVE, faithful book summary for a books website, in English.\n"
                . "Below are ORDERED notes taken directly from the ACTUAL TEXT of \"{$book}\"" . ($author ? " by {$author}" : '') . ", part by part.\n"
                . "Using ONLY these notes (which come from the real text), write a thorough summary with these sections as ## headings, omitting any you lack material for:\n"
                . "## About the Work\n## Context\n## Structure of the Book\n## Detailed Section-by-Section Summary\n## Main Arguments\n## Key Concepts\n## Themes\n## The Author's Conclusions\n## Significance\n\n"
                . "RULES: Base every statement on the notes (i.e. on the real text). Do NOT invent chapter titles, quotations, examples, or claims not in the notes. Separate the book's actual content from outside/biographical context. Be comprehensive but do NOT pad or repeat to inflate length. Write in clear, engaged prose, third person.\n\n"
                . "=== NOTES FROM THE REAL TEXT ===\n" . $joined . "\n=== END NOTES ===";
            $fr = tv_ask($rp, 8000, 300, 'deepseek');
            $beat();
            $summary = !empty($fr['ok']) ? trim($fr['text']) : '';
            $html = $summary !== '' ? bw_md2html($summary) : '';
            $B = [
                'state'    => $summary !== '' ? 'OK' : 'ERROR',
                'source'   => $src['source'],
                'url'      => $src['url'],
                'src_title'=> $src['title'],
                'book_words' => $words,
                'chapters' => $chapters,
                'chunks'   => count($chunks),
                'summary_html' => $html,
                'summary_words' => str_word_count(strip_tags($html)),
                'time'     => round(microtime(true) - $tB0, 1),
            ];
            sse('resultB', $B);
        }
    }
}

/* ══ SİSTEM A: klasik "başlık → DeepSeek" (karşılaştırma için) ═════════ */
if ($sys === 'both' || $sys === 'A') {
    $tA0 = microtime(true);
    sse('status', ['sys' => 'A', 'msg' => 'Sistem A: başlık → DeepSeek (kaynaksız)…']);
    $ap = "Write a comprehensive summary of the book \"{$book}\"" . ($author ? " by {$author}" : '') . " in English, "
        . "covering its structure, main arguments, key concepts, themes, and conclusions. Aim for depth.";
    $ar = tv_ask($ap, 6000, 240, 'deepseek');
    $beat();
    $asum = !empty($ar['ok']) ? trim($ar['text']) : '';
    $ahtml = $asum !== '' ? bw_md2html($asum) : '';
    sse('resultA', [
        'state' => $asum !== '' ? 'OK' : 'ERROR',
        'summary_html' => $ahtml,
        'summary_words' => str_word_count(strip_tags($ahtml)),
        'time' => round(microtime(true) - $tA0, 1),
    ]);
}

sse('done', ['ok' => true]);
