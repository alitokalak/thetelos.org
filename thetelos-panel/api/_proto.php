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
function proto_gutenberg($book, $author, $beat = null) {
    if (is_callable($beat)) $beat();
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
        $try = function ($u) use (&$best, &$best_url, &$debug, $is_book, $beat) {
            if (!$is_book($u)) return;
            if (is_callable($beat)) $beat();   // indirme uzun sürebilir → worker ölü sanılmasın
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
function proto_archive($book, $author, $beat = null) {
    if (is_callable($beat)) $beat();
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
        if (is_callable($beat)) $beat();
        $u = 'https://archive.org/download/' . rawurlencode($id) . '/' . rawurlencode($txt);
        $inf = null; $t = proto_fetch_text($u, 2, $inf);
        $debug['tried'][] = ['id' => $id, 'file' => $txt, 'code' => $inf['code'] ?? 0, 'bytes' => $inf['bytes'] ?? 0];
        if (mb_strlen($t) > 5000) return ['found' => true, 'url' => $u, 'title' => ($tt ?: $book), 'source' => 'Internet Archive', 'text' => $t, 'raw_len' => mb_strlen($t), 'debug' => $debug];
    }
    return ['found' => false, 'debug' => $debug];
}

/* ── Wikisource tam metin (tek sayfalık kamu-malı eserler) ──────────────────
   Gutenberg'de olmayan ama Wikisource'ta düz metin olarak bulunan eserler için.
   Çok-alt-sayfalı eserlerde yalnız içindekiler döner (kısa) → eşik geçilmez,
   Internet Archive'e düşülür. Kısmi/yanlış metin gelse bile chunk aşamasındaki
   ERKEN DURMA korur (ilk parçalardan not çıkmazsa iş anında durur). */
/* Wikisource sayfasının GERÇEK gövdesini al. prop=extracts, Wikisource'un
   <pages> transclusion'lı sayfalarında çoğu zaman NEREDEYSE BOŞ dönüyor (asıl
   metin başka sayfalardan aktarılıyor) → eser "bulundu ama kısa" diye eleniyordu.
   REST HTML uç noktası transclusion'ları RENDER eder → gerçek tam metni verir.
   HTML'i düz metne indirger, kaynak/dipnot/nav artıklarını kırpar. */
function proto_ws_fetch_text($title, $beat = null) {
    if (is_callable($beat)) $beat();
    $t = str_replace(' ', '_', trim($title));
    $html = proto_fetch_text('https://en.wikisource.org/api/rest_v1/page/html/' . rawurlencode($t), 2);
    if (mb_strlen($html) < 2000) return '';
    // <style>/<script>/<table> (gezinme, lisans) at; <sup> dipnot işaretlerini at.
    $html = preg_replace('#<(script|style|table|figure)[^>]*>.*?</\1>#is', ' ', $html);
    $html = preg_replace('#<sup[^>]*>.*?</sup>#is', '', $html);
    $txt  = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt  = preg_replace('/[ \t]+/', ' ', $txt);
    $txt  = preg_replace('/\n{3,}/', "\n\n", $txt);
    return trim($txt);
}
function proto_wikisource($book, $author, $beat = null) {
    if (is_callable($beat)) $beat();
    $debug = ['ws_results' => 0, 'tried' => []];
    // Parantezli orijinal başlığı at: "Of the Geometrical Spirit (De l'ésprit…)" → sade.
    $btitle = trim(preg_replace('/\s*\([^()]*\)\s*$/', '', $book));
    if ($btitle === '') $btitle = $book;

    // PRATİK ZEKA: sadece aramaya güvenme; Wikisource'un yaygın kanonik yollarını
    // DOĞRUDAN dene — özellikle "Yazar/Başlık" alt-sayfa yapısı (asıl kaçırılan buydu).
    $cands = [];
    if ($author !== '') { $cands[] = "$author/$btitle"; }
    $cands[] = $btitle;
    // Arama sonuçları da aday havuzuna (WS'nin döndürdüğü tam sayfa adları).
    $s = tls_fetch_json('https://en.wikisource.org/w/api.php?action=query&list=search&srnamespace=0&srlimit=6&format=json&srsearch='
        . rawurlencode(trim($btitle . ' ' . $author)), 'thetelos.org/1.0', 20, 3);
    $hits = $s['query']['search'] ?? [];
    $debug['ws_results'] = count($hits);
    foreach ($hits as $h) { $t = (string) ($h['title'] ?? ''); if ($t !== '') $cands[] = $t; }
    $cands = array_values(array_unique(array_filter($cands)));

    foreach ($cands as $title) {
        if (is_callable($beat)) $beat();
        // 1) Gerçek gövde (transclusion render): REST HTML.
        $txt = proto_ws_fetch_text($title, $beat);
        // 2) Yedek: düz-metin extract (bazı sayfalarda yeterli olur).
        if (mb_strlen($txt) < 6000) {
            $p = tls_fetch_json('https://en.wikisource.org/w/api.php?action=query&prop=extracts&explaintext=1&exlimit=1&redirects=1&format=json&titles='
                . rawurlencode($title), 'thetelos.org/1.0', 25, 2);
            foreach (($p['query']['pages'] ?? []) as $pg) { $ex = (string) ($pg['extract'] ?? ''); if (mb_strlen($ex) > mb_strlen($txt)) $txt = $ex; break; }
        }
        $len = mb_strlen(trim($txt));
        $debug['tried'][] = ['title' => $title, 'chars' => $len];
        // Eşik 15k→5k: felsefe denemeleri (bu Pascal metni gibi) kısa olabilir; ama
        // stub/içindekiler değil GERÇEK düzyazı olsun diye asgari cümle yoğunluğu iste.
        if ($len >= 5000 && substr_count($txt, '. ') >= 20) {
            return ['found' => true, 'title' => $title, 'source' => 'Wikisource', 'text' => $txt, 'raw_len' => $len,
                'url' => 'https://en.wikisource.org/wiki/' . rawurlencode(str_replace(' ', '_', $title)), 'debug' => $debug];
        }
    }
    return ['found' => false, 'debug' => $debug];
}

/* ── Sıralı edinim: Gutenberg → Wikisource → Internet Archive ───────────── */
function proto_acquire($book, $author, $beat = null) {
    $g = proto_gutenberg($book, $author, $beat);
    if (!empty($g['found'])) { $g['debug'] = ['gutenberg' => $g['debug']]; return $g; }
    $w = proto_wikisource($book, $author, $beat);
    if (!empty($w['found'])) { $w['debug'] = ['gutenberg' => $g['debug'] ?? null, 'wikisource' => $w['debug'] ?? null]; return $w; }
    $a = proto_archive($book, $author, $beat);
    $a['debug'] = ['gutenberg' => $g['debug'] ?? null, 'wikisource' => $w['debug'] ?? null, 'archive' => $a['debug'] ?? null];
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
    return "The following is an excerpt (part {$k} of {$n}) from a REAL public-domain edition of the book \"{$book}\""
        . ($author ? " by {$author}" : '') . ". It is genuinely from that edition — it may be the main text, OR the edition's introduction, the translator's/editor's analysis, a preface, footnotes, or appendices.\n"
        . "TASK: Write {$note_words} words of dense, faithful notes IN ENGLISH on the ideas, arguments, reasoning, concepts, events, and points ACTUALLY MADE in THIS passage. Use ONLY what this passage says — add no outside knowledge.\n"
        . "PRECISION: Preserve exact character/person names and place names as they appear in the text. Keep events in the ORDER they occur in this passage. Attribute each action to the correct character; never merge two people or two events into one.\n"
        . "DO NOT judge whether the passage 'belongs' to the book — it does. DO NOT refuse. Summarize whatever readable prose is present, whether it is the work itself or editorial discussion OF the work (when it is editorial context rather than the work's own words, just note that).\n"
        . "The ONLY case where you may skip: the passage contains NO readable sentences at all — i.e. it is purely a title page, a copyright/license/Project Gutenberg notice, a bare table of contents, or a page-number index. In that single case reply EXACTLY: (no substantive content). In EVERY other case you MUST produce notes.\n\n=== EXCERPT ===\n" . mb_substr($excerpt, 0, 48000);
}
function proto_reduce_prompt($book, $author, $notes, $target = 'a thorough summary', $chapters = []) {
    $chap_block = '';
    if (!empty($chapters)) {
        $chap_block = "\n=== REAL CHAPTER/SECTION STRUCTURE (detected from the actual text) ===\n"
            . implode("\n", array_slice($chapters, 0, 60)) . "\n=== END STRUCTURE ===\n"
            . "Use these REAL divisions instead of inventing generic 'Part 1 / Part 2' labels. "
            . "Mention each chapter heading AT MOST ONCE (as a ### subheading inside the Section-by-Section Summary); in 'Structure of the Book' describe the arrangement in prose. NEVER output the same chapter heading twice.\n";
    }
    return "You are writing a faithful, source-based book summary for a books website, in English.\n"
        . "Below are ORDERED notes taken directly from the ACTUAL TEXT of \"{$book}\"" . ($author ? " by {$author}" : '') . ", part by part.\n"
        . $chap_block
        . "Using ONLY these notes (from the real text), write {$target} with these ## sections, omitting any you lack material for:\n"
        . "## About the Work\n## Context\n## Structure of the Book\n## Detailed Section-by-Section Summary\n## Main Arguments\n## Key Concepts\n## Themes\n## The Author's Conclusions\n## Significance\n\n"
        . "RULES:\n"
        . "1. Base EVERY statement only on the notes (the real text). Do NOT invent or infer chapter titles, quotations, examples, dates, or names not in the notes.\n"
        . "2. COVER THE WHOLE BOOK. The Section-by-Section Summary MUST run from the FIRST part through the LAST part in the notes — beginning to end. Budget your length so EVERY part is represented; do NOT lavish detail on the early parts and then run out of room, leaving the later parts thin or missing. Reaching the book's actual ending is more important than fully detailing the opening. If space is tight, shorten every part evenly rather than dropping the final parts.\n"
        . "3. SYNTHESIZE — do NOT retell. Compress events and descriptions to their essence; give the substance of each part in a few sentences. Do NOT quote long passages or reproduce the text's descriptive catalogues (e.g. long lists of cloud shapes or scenery). Convey what happens and why it matters, not a paragraph-by-paragraph re-narration.\n"
        . "4. CHRONOLOGY: follow the ACTUAL order of the book as reflected in the ordered notes (Part 1 → last part). Do not reorder or jump around.\n"
        . "5. NO CONFLATION: keep characters and events distinct — attribute each action to the correct person; never merge two characters or two events into one. If the notes are unclear about who did what, stay general rather than guessing.\n"
        . "6. INTERPRETATION PLACEMENT: keep 'About the Work' neutral and descriptive (what the book is, its form, when/where). Put literary-critical labels and readings (e.g. 'spiritual autobiography', 'crisis of modernity', 'Romantic nature mysticism') in 'Themes', framed as interpretation grounded in specific text — not as fact, and not in the opening.\n"
        . "7. Separate the book's content from outside/biographical context. Clear, engaged prose, third person, in English. Do NOT pad or repeat. Do NOT restate the title as an H1; start with the ## sections. END on a complete sentence.\n\n"
        . "=== NOTES FROM THE REAL TEXT ===\n" . mb_substr($notes, 0, 60000) . "\n=== END NOTES ===";
}
function proto_systemA_prompt($book, $author) {
    return "Write a comprehensive summary of the book \"{$book}\"" . ($author ? " by {$author}" : '') . " in English, "
        . "covering its structure, main arguments, key concepts, themes, and conclusions. Aim for depth.";
}

/* ── SEGMENT bölüm-bölüm istemi: kitabın ARDIŞIK bir dilimini kapsar ─────────
   Tek reduce 15 parçayı kapsayamıyordu (son parçalar düşüyordu). Bu yüzden
   section-by-section'ı parça-gruplarına bölüp her grubu AYRI çağrıda üretiyoruz;
   her grup kendi bütçesini aldığı için tüm kitap garanti kapsanır. */
function proto_sbs_prompt($book, $author, $notes, $g, $G, $seg_words = 0) {
    $budget = $seg_words > 0
        ? "LENGTH BUDGET: write about {$seg_words} words for THIS segment (a hard ceiling — do not exceed it). Spend the words evenly across the whole segment so you reach its final event.\n"
        : '';
    return "You are writing the DETAILED SECTION-BY-SECTION narrative of a faithful, source-based summary of \"{$book}\"" . ($author ? " by {$author}" : '') . ", in English.\n"
        . "Below are ORDERED notes from the ACTUAL TEXT covering ONE consecutive segment of the book (segment {$g} of {$G}).\n"
        . "The notes are labelled [Part 1], [Part 2]… — these are OUR internal processing segments, NOT the book's real divisions. NEVER mention 'Part N' or reproduce those labels, and do NOT claim the book 'is divided into N parts'. Follow the book's OWN natural flow.\n"
        . $budget
        . "Write a SYNTHESIZED narrative of THIS segment as a sequence of ### subsections that follow the story's real progression. SYNTHESIZE HARD — do NOT retell or quote; compress descriptive detail (physical descriptions, catalogues of objects/scenery) to its essence; a few sentences per beat, conveying what happens and what it means for the protagonist's development. Use ONLY these notes; invent nothing. Title each ### by what actually happens — a real chapter name if the notes give one, otherwise a short descriptive phrase (e.g. '### Assisi and Annunziata Nardini').\n"
        . "COMPLETENESS OVER DETAIL: you MUST cover EVERY event in these notes THROUGH THE VERY LAST ONE. Do NOT run long on the early beats and get cut off before the end of this segment — if space is tight, shorten every beat evenly so you still reach the final event in these notes.\n"
        . ($g >= $G ? "THIS IS THE FINAL SEGMENT: you MUST narrate the book's actual ENDING — its last events and how the story truly concludes (do not stop before the real final scene).\n" : "")
        . "Output ONLY the ### subsections for this segment — no About/Context/Themes/Conclusions, no overall intro. Start directly with the first ###. END on a complete sentence.\n\n"
        . "=== NOTES (this segment, ordered) ===\n" . mb_substr($notes, 0, 50000) . "\n=== END NOTES ===";
}

/* ── ÇERÇEVE istemi: section-by-section HARİÇ tüm bölümler (tüm nottan) ─────── */
function proto_frame_prompt($book, $author, $notes, $target = 'a thorough summary', $chapters = []) {
    $chap_block = '';
    if (!empty($chapters)) {
        $chap_block = "\n=== REAL CHAPTER/SECTION STRUCTURE (detected from the actual text) ===\n"
            . implode("\n", array_slice($chapters, 0, 60)) . "\n=== END STRUCTURE ===\nUse these REAL divisions when describing structure; do NOT invent generic 'Part 1/2' labels.\n";
    }
    return "You are writing the FRAMING sections of a faithful, source-based book summary for a books website, in English.\n"
        . "Below are ORDERED notes from the ACTUAL TEXT of \"{$book}\"" . ($author ? " by {$author}" : '') . ".\n"
        . $chap_block
        . "Using ONLY these notes, write {$target}, but ONLY these ## sections (the blow-by-blow narrative is produced separately — do NOT write a section-by-section retelling here), omitting any you lack material for:\n"
        . "## About the Work\n## Context\n## Structure of the Book\n## Main Arguments\n## Key Concepts\n## Themes\n## The Author's Conclusions\n## Significance\n\n"
        . "RULES:\n"
        . "1. Base every statement only on the notes. Invent nothing (no quotes, dates, names, chapter titles not in the notes).\n"
        . "2. Keep 'About the Work' NEUTRAL and descriptive (what the book is, its form, when/where). Put literary-critical labels/readings (e.g. 'spiritual autobiography', 'crisis of modernity') in 'Themes', framed as interpretation grounded in the text — not as fact.\n"
        . "2b. BIBLIOGRAPHY: do NOT state a precise first-publication year, edition number, or publisher taken from the source's own title/imprint page. An imprint like 'fifty-eighth edition ... 1911' is a LATER REPRINT, not the first edition — presenting it as the first publication is an error. If you are not certain of the true first-publication facts, describe the period generally (e.g. 'an early-twentieth-century novel') rather than a precise but possibly wrong date/edition.\n"
        . "3. In 'Structure of the Book', describe the book's REAL structure (its chapters/narrative arc). The notes are labelled [Part N] — those are OUR internal processing segments, NOT the book's divisions; NEVER say the book 'is divided into N parts' based on them.\n"
        . "4. ENDING: read the LAST notes carefully and state the book's TRUE ending (its final events and how/where it actually concludes). Do NOT mistake a mid-book episode or location for the ending. If 'Structure' or 'The Author's Conclusions' describe how the book closes, they MUST match the real final scene in the last notes.\n"
        . "5. DISTINCT SECTIONS: keep 'Main Arguments', 'Key Concepts', and 'Themes' genuinely different — do NOT repeat the same points (e.g. nature, homesickness, love) across all three. Arguments = what the work claims/shows; Key Concepts = specific named ideas/motifs; Themes = the deeper recurring meanings. If a section would just repeat another, omit it.\n"
        . "6. Draw on the WHOLE book (first to last note), not just the opening. Synthesize; do not pad. Clear prose, third person, in English. Start with the ## sections. END on a complete sentence.\n\n"
        . "=== NOTES FROM THE REAL TEXT ===\n" . mb_substr($notes, 0, 60000) . "\n=== END NOTES ===";
}

/* ── Section-by-section'ı parça-gruplarında üret (tüm kitap garanti kapsanır) ─
   Notlar 5'erli gruplara bölünür; her grup AYRI (paralel) çağrıda kendi
   ### bölümlerini üretir; sonuçlar sırayla birleştirilir. Tek grup varsa null
   döner (çağıran eski tek-reduce yolunu kullanır). */
function proto_build_sbs($notes, $book, $author, $prov, $beat, $stage, $sbs_words = 0) {
    $groups = array_chunk($notes, 4);   // küçük grup = her segment bütçesine sığar, sonu kesmez
    $G = count($groups);
    if ($G <= 1) return null;
    // Segment başına kelime bütçesi → uzunluk seçici gerçekten çıktıyı belirlesin
    // (eskiden her segment 6000 token yazıp 60 dk'lık özet çıkıyordu). max_tokens'ı
    // da bütçeye göre kıs → hem uzunluk kontrolü hem TOKEN MALİYETİ düşer.
    $seg_words = $sbs_words > 0 ? max(180, (int) round($sbs_words / $G)) : 0;
    $seg_max   = $seg_words > 0 ? max(900, min(6000, (int) round($seg_words * 2.2))) : 6000;
    $stage("bölüm-bölüm özet {$G} segmentte üretiliyor" . ($seg_words ? " (~{$seg_words} kelime/segment)" : '') . "…");
    $prompts = [];
    foreach ($groups as $i => $gn) $prompts[$i] = proto_sbs_prompt($book, $author, implode("\n\n", $gn), $i + 1, $G, $seg_words);
    $out = ($prov !== 'gemini') ? proto_deepseek_multi($prompts, $seg_max, $beat, 6) : [];
    $parts = [];
    for ($i = 0; $i < $G; $i++) {
        $t = $out[$i] ?? '';
        if ($t === '') { $dg = ''; $t = proto_ds($prompts[$i], $seg_max, $beat, 280, $dg, $prov); }
        $t = proto_trim_incomplete(trim($t));
        if ($t !== '') $parts[] = $t;
    }
    return $parts ? implode("\n\n", $parts) : null;
}

/* ── Çerçeve + section-by-section'ı doğru yapısal sıraya diz ─────────────────
   Section-by-section, 'Main Arguments'tan ÖNCE (Structure'dan sonra) yerleşir. */
function proto_assemble($frame, $sbs) {
    if (trim((string) $sbs) === '') return $frame;
    $block = "## Detailed Section-by-Section Summary\n\n" . trim($sbs) . "\n\n";
    // NOT: PREG_OFFSET_CAPTURE ofseti BAYT cinsindendir → substr (bayt) kullan.
    // mb_substr (karakter) kullanınca em-dash gibi çok-baytlı karakterlerde
    // ofset kayıp "Main Arguments" başlığını "Ma"+"in Arguments" diye bölüyordu.
    if (preg_match('/^##[ \t]+Main Arguments/mi', $frame, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1];
        return rtrim(substr($frame, 0, $pos)) . "\n\n" . $block . substr($frame, $pos);
    }
    return rtrim($frame) . "\n\n" . $block;
}

/* ── Devam (expand) istemi — YALNIZ notlardan daha fazla derinlik ──────────
   Tek reduce çağrısı 6000 kelime yazmıyor; hedefe ulaşmak için notlardan
   ek bölüm/detay ekletiriz. Uydurma yok (notlar = gerçek metin), tekrar yok. */
function proto_continue_prompt($book, $author, $md, $notes) {
    return "You are EXTENDING a source-based summary of the book \"{$book}\"" . ($author ? " by {$author}" : '') . ".\n"
        . "Below is the SUMMARY SO FAR, then the ORDERED NOTES taken from the book's ACTUAL TEXT.\n"
        . "Continue the summary with MORE depth and detail DERIVED ONLY FROM THE NOTES — go deeper into the section-by-section coverage, the main arguments, and the key concepts with specifics that are in the text but not yet written.\n"
        . "HARD RULES: Use ONLY the notes (the real text). Do NOT repeat or lightly rephrase anything already written. Do NOT invent quotations, chapter titles, examples, dates, or claims not in the notes. Do NOT write a concluding restatement.\n"
        . "STRUCTURE: Do NOT reuse any of the ## section headings that already appear in the summary so far (e.g. do NOT write another '## Main Arguments' or '## Key Concepts'). Instead add depth under NEW, SPECIFIC ### subsection titles (e.g. '### Book IV — The Tripartite Soul', '### The Argument from Function') or as developed paragraphs. Output ONLY the NEW continuation.\n"
        . "If the notes contain nothing substantial left to add, output EXACTLY: DONE\n\n"
        . "=== SUMMARY SO FAR ===\n" . mb_substr($md, 0, 30000) . "\n\n=== NOTES FROM THE REAL TEXT ===\n" . mb_substr($notes, 0, 60000) . "\n=== END NOTES ===";
}
function proto_expand($md, $notes, $book, $author, $target_words, $prov, $beat, $max_pass = 4, $stage = null) {
    for ($i = 0; $i < $max_pass; $i++) {
        $w = str_word_count(strip_tags(bw_md2html($md)));
        if ($w >= $target_words * 0.85) break;   // hedefe ulaşıldı
        if (is_callable($stage)) $stage("özet genişletiliyor ({$w}/" . number_format($target_words) . " kelime)…");
        $beat();
        $d = '';
        $cont = trim(proto_ds(proto_continue_prompt($book, $author, $md, $notes), 8000, $beat, 300, $d, $prov));
        if ($cont === '' || preg_match('/^\s*DONE\b/i', $cont)) break;     // eklenecek şey yok
        $cont = trim(preg_replace('/\n*\bDONE\b\s*$/i', '', $cont));
        if (str_word_count(strip_tags(bw_md2html($cont))) < 50) break;
        $md = rtrim($md) . "\n\n" . $cont;
    }
    return $md;
}

/* ── Çıktı token sınırına (8000) dayanıp cümle ortasında kesilirse, son TAM
   cümleye geri kır. Kapının 'cümle ortasında kesilmiş' kontrolü böylece geçer;
   birkaç kelimelik yarım kuyruk atılır, içerik kaybı ihmal edilebilir. */
function proto_trim_incomplete($md) {
    $s = rtrim((string) $md);
    if ($s === '') return $s;
    if (preg_match('/[.!?…"”’)\]]\s*$/u', $s)) return $s;            // zaten düzgün bitiyor
    if (preg_match('/^(.*[.!?…"”’)\]])[^.!?…]*$/su', $s, $m) && trim($m[1]) !== '') return rtrim($m[1]);
    return $s;
}

/* ── TEK ÇAĞRIDA kaynak-temelli özet üretimi (batch + içerik düzeltme kullanır)
   Uzunluk: 'kisa' | 'standart' | 'kapsamli'. on_beat heartbeat.
   Dönüş: found/insufficient/md/source/url/book_words/chunks/chapters/model/trace */
function proto_generate($book, $author, $opts = []) {
    $len = in_array($opts['length'] ?? 'standart', ['kisa', 'standart', 'kapsamli'], true) ? $opts['length'] : 'standart';
    $beat = is_callable($opts['on_beat'] ?? null) ? $opts['on_beat'] : function () {};
    // Canlı aşama bildirimi (panelde görünür — nerede olduğumuzu göster).
    $stage = is_callable($opts['on_stage'] ?? null) ? $opts['on_stage'] : function ($m) {};

    // HEDEF KELİME: panelden serbest seçilebilir (kaynaksız kısımdaki gibi bir kaydırıcı).
    // Sayısal 'words' verilirse onu kullan; yoksa eski ön-ayar (kisa/standart/kapsamli).
    $preset_words = ['kisa' => 1500, 'standart' => 3000, 'kapsamli' => 5200][$len];
    $W = isset($opts['words']) && (int) $opts['words'] > 0
        ? max(1000, min(8000, (int) $opts['words']))
        : $preset_words;

    // ctarget: parça başına hedef karakter (küçük = daha çok/granüler parça).
    // OKUMA DERİNLİĞİ (kaç parça okunur) hedef uzunlukla orantılı ölçeklenir —
    // böylece kısa hedefte gereksiz parça okumayız (TOKEN MALİYETİ düşer), uzun
    // hedefte tüm kitabı granüler okuruz. 'words' = özet hedef kelime.
    if     ($W <= 2000) { $max = 8;  $ctarget = 50000; $notes = '140-220'; }
    elseif ($W <= 3500) { $max = 12; $ctarget = 38000; $notes = '200-320'; }
    elseif ($W <= 5500) { $max = 18; $ctarget = 26000; $notes = '280-420'; }
    else                { $max = 24; $ctarget = 20000; $notes = '340-500'; }
    $lo = (int) round($W * 0.9); $hi = (int) round($W * 1.1);
    $rtar = "a thorough, SYNTHESIZED summary of about {$lo}-{$hi} words that covers the ENTIRE book from the first part to the LAST part (do not stop halfway), does not pad or repeat, and finishes on a complete sentence";
    // SBS (bölüm-bölüm anlatı) toplam bütçenin ~%72'si; çerçeve (About/Themes…) kalanı.
    $sbs_words = (int) round($W * 0.72);
    $cfg = ['max' => $max, 'ctarget' => $ctarget, 'notes' => $notes, 'rtar' => $rtar, 'words' => $W];

    // Sağlayıcı: DeepSeek erişilebiliyorsa onu (ucuz), yoksa Gemini. İş başına bir kez.
    $prov = ($opts['provider'] ?? 'auto');
    if ($prov === 'auto') $prov = (proto_openrouter_key() !== '' || proto_deepseek_reachable()) ? 'auto' : 'gemini';
    $model_label = ($prov === 'gemini') ? 'gemini' : 'deepseek';

    $stage("kaynak metni indiriliyor (Gutenberg → Wikisource → Internet Archive)…");
    $src = proto_acquire($book, $author, $beat);
    $beat();
    if (empty($src['found'])) {
        $g = $src['debug']['gutenberg']['gutendex_results'] ?? '?';
        return ['found' => false, 'insufficient' => true, 'debug' => $src['debug'] ?? null,
                'trace' => "tam metin YOK (gutendex sonuç=$g) → Bilgi Metni'ne düşülecek"];
    }

    $text   = proto_clean($src['source'], $src['text']);
    $bw     = str_word_count(strip_tags($text));
    $chapters = proto_detect_chapters($text);   // GERÇEK bölüm başlıkları → reduce'a verilir (uydurma Part 1/2 yerine)
    $chunks = proto_chunks($text, $cfg['max'], $cfg['ctarget']);
    $ncnt   = count($chunks);
    $notes = []; $lastdg = ''; $empty = 0; $nosub = 0;
    // Parça istemlerini hazırla ve HEPSİNİ PARALEL gönder (curl_multi). 22 parça
    // sırayla ~15-25 dk sürüp host süreç limitinde ölüyordu; paralel ~birkaç dk.
    $stage("kaynak bulundu: {$src['source']} · " . number_format($bw) . " kelime · {$ncnt} parça — parçalar paralel okunuyor…");
    $prompts = [];
    foreach ($chunks as $i => $ch) $prompts[$i] = proto_chunk_prompt($book, $author, $i + 1, $ncnt, $ch, $cfg['notes']);
    $texts = ($prov !== 'gemini') ? proto_deepseek_multi($prompts, 1200, $beat, 8) : [];
    foreach ($chunks as $i => $ch) {
        $t = $texts[$i] ?? '';
        if ($t === '') {   // paralelde boş/başarısız → sıralı telafi (Gemini yedeği dahil)
            $dg = ''; $t = proto_ds($prompts[$i], 1200, $beat, 280, $dg, $prov); $lastdg = $dg;
        }
        if ($t !== '' && !preg_match('/^\W{0,4}no substantive content/i', ltrim($t))) $notes[] = '[Part ' . ($i + 1) . "]\n" . $t;
        else { if ($t === '') $empty++; else $nosub++; }
    }
    if (!$notes) return ['found' => true, 'insufficient' => true, 'source' => $src['source'], 'model' => $model_label,
        'trace' => $src['source'] . " {$bw}w, {$ncnt} parça (boş={$empty}, içeriksiz={$nosub}), 0 not"
                 . ($empty > 0 ? " · son sağlayıcı diag: {$lastdg}" : '') . " → Bilgi Metni'ne düşülecek"];

    $stage(count($notes) . " parça notu birleştiriliyor…");
    $beat();
    $dg2 = '';
    // HİYERARŞİK: section-by-section'ı parça-gruplarında üret (tüm kitap garanti
    // kapsanır — tek reduce son parçaları düşürüyordu), çerçeve bölümlerini ayrı
    // yaz, birleştir. Tek grup (kısa kitap) → eski tek-reduce yolu.
    $sbs = proto_build_sbs($notes, $book, $author, $prov, $beat, $stage, $sbs_words);
    if ($sbs === null) {
        // Tek grup (kısa kitap): tüm özeti tek reduce yazar → hedefe göre token kıs.
        $one_max = max(1500, min(8000, (int) round($W * 2.2)));
        $md = proto_ds(proto_reduce_prompt($book, $author, implode("\n\n", $notes), $cfg['rtar'], $chapters), $one_max, $beat, 300, $dg2, $prov);
    } else {
        $stage("çerçeve bölümleri (About/Context/Themes…) yazılıyor…");
        // Çerçeve yalnız About/Themes… yazar (anlatı ayrı) → bütçenin kalanı kadar token.
        $frame_max = max(1500, min(8000, (int) round(($W - $sbs_words) * 2.6) + 1200));
        $frame = proto_ds(proto_frame_prompt($book, $author, implode("\n\n", $notes), $cfg['rtar'], $chapters), $frame_max, $beat, 300, $dg2, $prov);
        $md = proto_assemble($frame, $sbs);
    }
    if (trim($md) === '') return ['found' => true, 'insufficient' => true, 'source' => $src['source'], 'model' => $model_label, 'error' => $dg2,
        'trace' => $src['source'] . " {$bw}w, reduce BOŞ ({$dg2}) → Bilgi Metni'ne düşülecek"];

    $md = proto_trim_incomplete($md);   // token sınırında yarım kalan son cümleyi at → kapı geçsin
    $fw = str_word_count(strip_tags(bw_md2html($md)));

    return ['found' => true, 'insufficient' => false, 'md' => $md, 'source' => $src['source'], 'url' => $src['url'],
            'book_words' => $bw, 'chunks' => count($chunks), 'chapters' => $chapters, 'model' => $model_label,
            'trace' => $src['source'] . " {$bw}w → " . count($chunks) . ' parça/' . count($notes) . " not → özet {$fw}w ({$model_label})"];
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

/* ── PARALEL DeepSeek — çok sayıda parçayı AYNI ANDA gönder (curl_multi) ────
   Takılma/yavaşlığın asıl çözümü: 22 parça sırayla ~11-22 dk yerine, aynı
   anda (en çok $conc tanesi uçuşta) ~birkaç dk'da biter; host süreç limitine
   takılmaz. Yalnız doğrudan DeepSeek için. Dönüş: [index => text]. Boş/başarısız
   kalanları çağıran taraf sıralı proto_ds (Gemini yedeği) ile telafi eder.
   $beat düzenli çağrılır → worker "ölü" sanılmaz. */
function proto_deepseek_multi($prompts, $max_tokens, $beat = null, $conc = 8) {
    if (!defined('DEEPSEEK_KEY') || !DEEPSEEK_KEY || !$prompts) return [];
    $model = (defined('DEEPSEEK_MODEL') && !in_array(DEEPSEEK_MODEL, ['deepseek-chat', 'deepseek-reasoner'], true))
           ? DEEPSEEK_MODEL : 'deepseek-v4-flash';
    $mkbody = function ($prompt) use ($model, $max_tokens) {
        return json_encode([
            'model' => $model, 'max_tokens' => max(300, min(8000, (int) $max_tokens)), 'temperature' => 0.3,
            'thinking' => ['type' => 'disabled'],
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ], JSON_UNESCAPED_UNICODE);
    };
    $hid = fn($ch) => is_object($ch) ? spl_object_id($ch) : (int) $ch;
    $keys = array_keys($prompts); $n = count($keys); $pos = 0;
    $out = []; $map = [];
    $mh = curl_multi_init();
    $add = function ($k) use ($mh, $mkbody, $prompts, &$map, $hid) {
        $ch = curl_init(DEEPSEEK_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 12, CURLOPT_TIMEOUT => 280,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
            CURLOPT_POSTFIELDS => $mkbody($prompts[$k]),
        ]);
        curl_multi_add_handle($mh, $ch); $map[$hid($ch)] = $k;
    };
    while ($pos < $n && count($map) < $conc) $add($keys[$pos++]);
    $deadline = time() + 200;   // GÜVENLİK: bütün paralel faz en çok ~200 sn; asla sonsuza takılma
    do {
        if (time() > $deadline) break;   // süre doldu → elde olanı dön, kalanı sıralı telafi eder
        curl_multi_exec($mh, $running);
        if (is_callable($beat)) $beat();
        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle']; $k = $map[$hid($ch)] ?? null;
            $body = curl_multi_getcontent($ch);
            $j = json_decode((string) $body, true);
            $txt = trim((string) ($j['choices'][0]['message']['content'] ?? ''));
            if ($txt === '') $txt = trim((string) ($j['choices'][0]['message']['reasoning_content'] ?? ''));
            if ($k !== null) $out[$k] = $txt;
            curl_multi_remove_handle($mh, $ch); curl_close($ch); unset($map[$hid($ch)]);
            if ($pos < $n) $add($keys[$pos++]);
        }
        if ($running && curl_multi_select($mh, 1.0) === -1) usleep(100000);
    } while ($running || $map || $pos < $n);
    curl_multi_close($mh);
    return $out;
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
