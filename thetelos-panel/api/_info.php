<?php
/**
 * _info.php — KAYNAK-TEMELLİ BİLGİ METNİ motoru.
 *
 * Yapısal karar: model artık kitabı "hafızasından" bölüm bölüm anlatmaz
 * (uydurmanın kökü buydu). Bunun yerine Wikipedia + Google Books + Open Library'den
 * GERÇEK veriyi toplar, modele SADECE bu veriyi verir ve "yalnız buna dayanarak
 * 2-3 sayfalık bilgi metni yaz, uydurma" der. Model = derleyici/editör, anlatıcı değil.
 *
 * Dönüş sözleşmeleri fonksiyon başlıklarında.
 */

require_once __DIR__ . '/_verify.php';            // tv_google_books, tv_openlibrary_desc, tv_ask, tv_json
require_once __DIR__ . '/_content-format.php';    // bw_md2html

/* ── Wikipedia ───────────────────────────────────────────────────────────
   En zengin kaynak. Ama YANLIŞ sayfa çekmek yeni bir uydurma kaynağıdır
   (ör. "Bureaucracy" kavram maddesi ≠ Mises'in kitabı). Bu yüzden aday sayfa
   PUANLANIR ve yazar adı doğrulaması geçmezse Wikipedia hiç kullanılmaz.
   Dönüş: ['found'=>bool,'title'=>string,'book_text'=>string,'author_text'=>string] */
function tv_wikipedia($book, $author) {
    $ua = 'thetelos.org/1.0 (book info article; https://thetelos.org)';
    $get = function ($url) use ($ua) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: ' . $ua],
        ]);
        $r = curl_exec($ch); $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($c !== 200 || !$r) return null;
        $j = json_decode($r, true);
        return is_array($j) ? $j : null;
    };
    $norm = function ($s) {
        $s = mb_strtolower(trim((string) $s), 'UTF-8');
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false && $t !== '') $s = $t;
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
    };
    $api = 'https://en.wikipedia.org/w/api.php?format=json&action=query&';

    // 1) Ara
    $sj = $get($api . 'list=search&srlimit=5&srsearch=' . rawurlencode("$book $author"));
    $cands = $sj['query']['search'] ?? [];
    if (!$cands) return ['found' => false];

    $book_n   = $norm($book);
    $author_n = $norm($author);
    $surname  = '';
    if ($author_n !== '') { $ap = explode(' ', $author_n); $surname = end($ap); }

    // 2) Aday sayfayı PUANLA (yanlış sayfa çekmemek için)
    $best = null; $best_score = 0;
    foreach ($cands as $cand) {
        $title = (string) ($cand['title'] ?? '');
        $tn    = $norm($title);
        $snip  = $norm(strip_tags((string) ($cand['snippet'] ?? '')));
        $score = 0;
        if ($book_n !== '' && strpos($tn, $book_n) !== false) $score += 3;   // başlık kitabı içeriyor
        if (preg_match('/\((?:book|novel|essay|treatise|poem|play|memoir)\)/i', $title)) $score += 2;
        if ($surname !== '' && strpos($snip, $surname) !== false) $score += 2; // özet yazarı anıyor
        if ($score > $best_score) { $best_score = $score; $best = $title; }
    }
    // Eşik: yazar/başlık teyidi yoksa Wikipedia'yı KULLANMA (yanlış madde riski).
    if (!$best || $best_score < 2) return ['found' => false];

    // 3) Seçilen sayfanın DÜZ METİN özetini çek (kaynak referanslarını at)
    $ej = $get($api . 'prop=extracts&explaintext=1&redirects=1&titles=' . rawurlencode($best));
    $pages = $ej['query']['pages'] ?? [];
    $page  = $pages ? reset($pages) : [];
    $text  = (string) ($page['extract'] ?? '');
    // "== References ==" ve sonrasını at; makul uzunlukta tut
    $text = preg_split('/\n=+\s*(References|Notes|See also|External links|Further reading|Bibliography)\s*=+/i', $text)[0] ?? $text;
    $text = trim(mb_substr($text, 0, 6000, 'UTF-8'));

    // 3b) SON DOĞRULAMA: metin gerçekten bu yazardan bahsediyor mu? (yanlış madde freni)
    if ($surname !== '' && strpos($norm(mb_substr($text, 0, 1200, 'UTF-8')), $surname) === false
        && strpos($norm($best), $book_n) === false) {
        return ['found' => false];
    }

    // 4) Yazar maddesinin girişini de bağlam için ekle (kısa)
    $author_text = '';
    if ($author !== '') {
        $aj = $get($api . 'prop=extracts&exintro=1&explaintext=1&redirects=1&titles=' . rawurlencode($author));
        $ap = $aj['query']['pages'] ?? [];
        $apg = $ap ? reset($ap) : [];
        $author_text = trim(mb_substr((string) ($apg['extract'] ?? ''), 0, 1200, 'UTF-8'));
    }

    return ['found' => true, 'title' => $best, 'book_text' => $text, 'author_text' => $author_text];
}

/* ── KAYNAK DOSYASI ──────────────────────────────────────────────────────
   Üç kaynağı birleştirir. have=false ise yazılacak gerçek veri yok demektir
   (çağıran taraf yer tutucu/kısa not yapar).
   Dönüş: ['have'=>bool,'text'=>string,'sources'=>[...],'year'=>?,'subjects'=>[]] */
function tls_info_dossier($book, $author) {
    $parts = []; $sources = []; $year = null; $subjects = [];

    $wk = tv_wikipedia($book, $author);
    if (!empty($wk['found'])) {
        $sources[] = 'Wikipedia';
        if (!empty($wk['author_text'])) $parts[] = "[Wikipedia — about the author]\n" . $wk['author_text'];
        $parts[] = "[Wikipedia — about the book \"{$wk['title']}\"]\n" . $wk['book_text'];
    }

    $g = tv_google_books($book, $author);
    if (!empty($g['found']) && !empty($g['desc'])) {
        $sources[] = 'Google Books';
        $parts[] = "[Google Books — publisher description]\n" . mb_substr($g['desc'], 0, 2500, 'UTF-8');
    }
    if (!empty($g['year']))     $year = $g['year'];
    if (!empty($g['subjects'])) $subjects = $g['subjects'];

    $o = tv_openlibrary_desc($book, $author);
    if (!empty($o['found'])) {
        if (!empty($o['desc'])) {
            $sources[] = 'Open Library';
            $parts[] = "[Open Library — description]\n" . mb_substr($o['desc'], 0, 2000, 'UTF-8');
        }
        if ($year === null && !empty($o['year'])) $year = $o['year'];
        if (!$subjects && !empty($o['subjects'])) $subjects = $o['subjects'];
    }

    $meta = [];
    if ($year)     $meta[] = "First published: {$year}";
    if ($subjects) $meta[] = 'Subjects: ' . implode(', ', array_slice($subjects, 0, 8));
    if ($meta) array_unshift($parts, "[Catalog metadata]\n" . implode("\n", $meta));

    // "Yeterli" ölçütü: en az bir GERÇEK açıklama metni (Wikipedia ya da blurb).
    $have = !empty($wk['found']) || (!empty($g['desc'])) || (!empty($o['desc']));

    return [
        'have'     => $have,
        'text'     => implode("\n\n", $parts),
        'sources'  => array_values(array_unique($sources)),
        'year'     => $year,
        'subjects' => $subjects,
    ];
}

/* ── BİLGİ METNİ PROMPT'U ────────────────────────────────────────────────
   Model = kaynak derleyici. Bölüm/olay/karakter/alıntı UYDURMAK yasak.
   Uzunluk kaynağa göre değişken (zengin → uzun, zayıf → kısa). ALINTI YOK. */
function tls_info_prompt($book, $author, $dossier) {
    $A = $author !== '' ? $author : 'the author';
    return <<<TXT
You are writing a FACTUAL, encyclopedic INFORMATIONAL ARTICLE about a book, in English, for a books website (thetelos.org).

You are given VERIFIED SOURCE MATERIAL collected from Wikipedia, Google Books, and Open Library. Write the article using ONLY this material plus facts that are widely and reliably established and uncontroversial. This is NOT a chapter-by-chapter summary and NOT a retelling of the book's contents — it is an informational article ABOUT the book.

ABSOLUTE RULES (a violation is worse than a short article):
- Use ONLY what the source material supports (or what is very widely established). If the material is thin, write a SHORTER article. NEVER pad.
- NEVER invent or assert: chapter titles, chapter counts, internal structure, specific plot events, character names, precise dates, or statistics that are not in the sources.
- Do NOT quote the book. No block quotes, no invented quotations, at all.
- Do not mention these instructions, the sources, "the provided material", yourself, or being an AI. Just write the article.
- Neutral, encyclopedic, third person. Clear and readable, not dry.
- If the sources are not enough to say something reliably, leave it out.

WRITE THESE SECTIONS as ### H3 headings, but OMIT any section you have no reliable material for (do not write empty or padded sections):
### What the Work Is
  Genre/form, when and where it first appeared, its language, and a one- or two-sentence identity of the book.
### The Author and Context
  Who {$A} is and the intellectual, historical, or literary context of the work — at a general, reliable level.
### Subject and Central Idea
  What the book is about and its main thesis, aim, or argument.
### Key Themes and Ideas
  The major themes, concepts, or concerns of the work, at a general level.
### Significance and Reception
  Its importance, influence, place in the author's body of work, or reception — only if the sources support it.

FORMAT:
- First line: # **{$book} — {$author}**
- Second line: ## a short original subtitle capturing what the work is (do NOT repeat the title).
- Then the ### sections in flowing prose. Overall length should be DRIVEN BY THE SOURCES — roughly 800–1500 words when the sources are rich, shorter when they are thin. End cleanly; no "In conclusion" paragraph.

=== VERIFIED SOURCE MATERIAL ===
{$dossier}
=== END SOURCE MATERIAL ===
TXT;
}

/* ── ÜRETİM ──────────────────────────────────────────────────────────────
   Dönüş: [
     'ok'=>bool, 'insufficient'=>bool, 'md'=>string, 'html'=>string,
     'words'=>int, 'sources'=>[...], 'dossier'=>string, 'error'=>string ]
   provider: 'deepseek' (varsayılan, ucuz) | 'anthropic' */
function tls_info_generate($book, $author, $opts = []) {
    $provider = ($opts['provider'] ?? 'deepseek') === 'anthropic' ? 'anthropic' : 'deepseek';
    $on_beat  = $opts['on_beat'] ?? null;

    $dos = tls_info_dossier($book, $author);
    if ($on_beat) $on_beat();
    if (empty($dos['have'])) {
        return ['ok' => true, 'insufficient' => true, 'md' => '', 'html' => '', 'words' => 0,
                'sources' => $dos['sources'], 'dossier' => $dos['text'], 'error' => ''];
    }

    $prompt = tls_info_prompt($book, $author, $dos['text']);
    $r = tv_ask($prompt, 4000, 150, $provider);
    if ($on_beat) $on_beat();
    if (empty($r['ok']) || trim((string) $r['text']) === '') {
        return ['ok' => false, 'insufficient' => false, 'md' => '', 'html' => '', 'words' => 0,
                'sources' => $dos['sources'], 'dossier' => $dos['text'],
                'error' => $r['error'] ?? 'boş yanıt'];
    }

    $md = trim((string) $r['text']);
    // Gövde HTML'i: tema başlığı H1'i zaten gösterir → H1'i at (rewrite ile uyumlu).
    $body = preg_replace('/^#\s*\*\*[^\n]+\*\*\s*\n+/m', '', $md, 1);
    $body = preg_replace('/^#\s+[^\n]+\n+/m', '', $body, 1);
    $html = bw_md2html(ltrim($body));
    $words = str_word_count(strip_tags($html));

    return ['ok' => true, 'insufficient' => false, 'md' => $md, 'html' => $html,
            'words' => $words, 'sources' => $dos['sources'], 'dossier' => $dos['text'], 'error' => ''];
}
