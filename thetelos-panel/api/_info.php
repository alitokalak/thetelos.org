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
    $book_n  = $norm($book);
    $surname = '';
    if (($an = $norm($author)) !== '') { $ap = explode(' ', $an); $surname = end($ap); }

    // Bir DİLDEKİ Wikipedia'dan kitabı bul + düz metin özetini getir. Aday sayfa
    // puanlanır ve yazar/başlık teyidi geçmezse null döner (yanlış madde freni).
    $fetch_book = function ($lang) use ($get, $norm, $book, $author, $book_n, $surname) {
        $api = "https://{$lang}.wikipedia.org/w/api.php?format=json&action=query&";
        $sj = $get($api . 'list=search&srlimit=5&srsearch=' . rawurlencode("$book $author"));
        $cands = $sj['query']['search'] ?? [];
        if (!$cands) return null;
        $best = null; $bs = 0;
        foreach ($cands as $cand) {
            $title = (string) ($cand['title'] ?? '');
            $tn    = $norm($title);
            $snip  = $norm(strip_tags((string) ($cand['snippet'] ?? '')));
            $sc = 0;
            if ($book_n !== '' && strpos($tn, $book_n) !== false) $sc += 3;
            if (preg_match('/\((?:book|novel|essay|treatise|poem|play|memoir|roman|livre|libro|buch)\)/iu', $title)) $sc += 2;
            if ($surname !== '' && strpos($snip, $surname) !== false) $sc += 2;
            if ($sc > $bs) { $bs = $sc; $best = $title; }
        }
        if (!$best || $bs < 2) return null;
        $ej = $get($api . 'prop=extracts&explaintext=1&redirects=1&titles=' . rawurlencode($best));
        $pages = $ej['query']['pages'] ?? [];
        $page  = $pages ? reset($pages) : [];
        $text  = (string) ($page['extract'] ?? '');
        $text  = preg_split('/\n=+\s*(References|Notes|See also|External links|Further reading|Bibliography|Sources|Weblinks|Literatur|Einzelnachweise|Notes et références|Bibliographie|Véase también|Bibliografía|Note|Bibliografia|Kaynakça|Dış bağlantılar)\s*=+/iu', $text)[0] ?? $text;
        $text  = trim(mb_substr($text, 0, 28000, 'UTF-8'));
        if (mb_strlen($text, 'UTF-8') < 200) return null;
        // Yazar teyidi (yanlış madde çekmeyi engelle).
        if ($surname !== '' && strpos($norm(mb_substr($text, 0, 1500, 'UTF-8')), $surname) === false
            && strpos($norm($best), $book_n) === false) return null;
        return ['title' => $best, 'text' => $text, 'lang' => $lang];
    };

    // ÖNCE İngilizce. Zayıf (<1500 krk) ya da yoksa DİĞER DİLLER → en zengini seç.
    // Böylece İngilizce Wikipedia'sı ince eserlerde bile gerçek kaynak bulunur;
    // model dili ne olursa olsun sentezleyip İngilizce yazar.
    $chosen = $fetch_book('en');
    if (!$chosen || mb_strlen($chosen['text'], 'UTF-8') < 1500) {
        foreach (['de', 'fr', 'it', 'es', 'tr', 'ru'] as $lang) {
            $r = $fetch_book($lang);
            if ($r && (!$chosen || mb_strlen($r['text'], 'UTF-8') > mb_strlen((string) ($chosen['text'] ?? ''), 'UTF-8'))) {
                $chosen = $r;
            }
            if ($chosen && mb_strlen($chosen['text'], 'UTF-8') >= 3000) break; // yeterince zengin
        }
    }
    if (!$chosen) return ['found' => false];

    // Yazar maddesinin yalnız KISA girişi (odak KİTAP; biyografi yazar sayfasında).
    $author_text = '';
    if ($author !== '') {
        foreach (array_values(array_unique([$chosen['lang'], 'en'])) as $lang) {
            $aj = $get("https://{$lang}.wikipedia.org/w/api.php?format=json&action=query&prop=extracts&exintro=1&explaintext=1&redirects=1&titles=" . rawurlencode($author));
            $ap = $aj['query']['pages'] ?? [];
            $apg = $ap ? reset($ap) : [];
            $t = trim((string) ($apg['extract'] ?? ''));
            if ($t !== '') { $author_text = trim(mb_substr($t, 0, 700, 'UTF-8')); break; }
        }
    }

    return ['found' => true, 'title' => $chosen['title'], 'book_text' => $chosen['text'],
            'author_text' => $author_text, 'lang' => $chosen['lang']];
}

/* ── Wikidata: YAPISAL OLGULAR ───────────────────────────────────────────
   Wikipedia'dan FARKLI bir kaynak: tür, ana konu, akım, karakterler, dönem,
   etkilendikleri gibi yapısal gerçekler. Yanlış varlık çekmemek için YAZAR
   TEYİDİ şart (P50 yazar ya da açıklama yazarı içermeli). Dönüş: kısa olgu
   metni (bulunamazsa ''). */
function tv_wikidata($book, $author) {
    $ua = 'thetelos.org/1.0 (book info; https://thetelos.org)';
    $get = function ($url) use ($ua) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: ' . $ua]]);
        $r = curl_exec($ch); $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($c !== 200 || !$r) return null;
        $j = json_decode($r, true); return is_array($j) ? $j : null;
    };
    $surname = '';
    $an = mb_strtolower(trim((string) $author), 'UTF-8');
    if ($an !== '') { $p = explode(' ', $an); $surname = end($p); }

    $empty = ['facts' => '', 'enwiki' => '', 'label' => ''];

    // 1) Varlığı ara (İngilizce). Dil sınırlaması olmadan da dene: bölgesel/çeviri
    //    başlıklar (ör. Tamilce "Satya Sothanai") İngilizce label'da olmayabilir.
    $cands = [];
    foreach (['en', ''] as $lng) {
        $u = 'https://www.wikidata.org/w/api.php?action=wbsearchentities&format=json&type=item&limit=6&search=' . rawurlencode($book)
           . ($lng ? "&language={$lng}&uselang={$lng}" : '&language=en&uselang=en&strictlanguage=0');
        $sj = $get($u);
        if (!empty($sj['search'])) { $cands = $sj['search']; break; }
    }
    if (!$cands) return $empty;
    $qid = '';
    foreach ($cands as $c) {
        $desc = mb_strtolower((string) ($c['description'] ?? ''), 'UTF-8');
        if (preg_match('/\b(book|novel|essay|treatise|work|poem|play|non-fiction|memoir|autobiograph|written)\b/', $desc)
            || ($surname !== '' && strpos($desc, $surname) !== false)) {
            $qid = (string) ($c['id'] ?? ''); break;
        }
    }
    if ($qid === '') $qid = (string) ($cands[0]['id'] ?? '');
    if ($qid === '') return $empty;

    // 2) Claim + İngilizce Wikipedia bağlantısı (sitelink) + etiket çek
    $ej = $get("https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&props=claims|descriptions|labels|sitelinks&languages=en&sitefilter=enwiki&ids={$qid}");
    $ent = $ej['entities'][$qid] ?? null;
    if (!$ent) return $empty;
    $claims  = $ent['claims'] ?? [];
    $enwiki  = (string) ($ent['sitelinks']['enwiki']['title'] ?? '');   // kanonik İngilizce madde adı
    $enlabel = (string) ($ent['labels']['en']['value'] ?? '');

    $props = [
        'P50' => 'Author', 'P136' => 'Genre', 'P921' => 'Main subject', 'P135' => 'Movement',
        'P7937' => 'Form', 'P674' => 'Characters', 'P840' => 'Narrative location',
        'P941' => 'Inspired by', 'P144' => 'Based on', 'P495' => 'Country of origin',
        'P407' => 'Language', 'P155' => 'Follows', 'P156' => 'Followed by',
    ];
    $need = [];           // çözülecek Q-id'ler
    $vals = [];           // prop => [Q-id...]
    $pubyear = '';
    foreach ($props as $pid => $_) {
        foreach ($claims[$pid] ?? [] as $cl) {
            $dv = $cl['mainsnak']['datavalue']['value'] ?? null;
            if (is_array($dv) && isset($dv['id'])) { $vals[$pid][] = $dv['id']; $need[$dv['id']] = true; }
        }
    }
    foreach ($claims['P577'] ?? [] as $cl) {   // yayın tarihi
        $t = $cl['mainsnak']['datavalue']['value']['time'] ?? '';
        if (preg_match('/(\d{4})/', $t, $m)) { $pubyear = $m[1]; break; }
    }
    // Olgu yoksa bile enwiki bağlantısı değerli (köprü) → onu döndür.
    if (!$vals && $pubyear === '') return ['facts' => '', 'enwiki' => $enwiki, 'label' => $enlabel];

    // 3) Yazar TEYİDİ: P50 yazar etiketi soyadı içermeli (ya da açıklama).
    $entdesc = mb_strtolower((string) ($ent['descriptions']['en']['value'] ?? ''), 'UTF-8');

    // 4) Q-id etiketlerini tek çağrıda çöz
    $labels = [];
    if ($need) {
        $ids = implode('|', array_slice(array_keys($need), 0, 50));
        $lj = $get("https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&props=labels&languages=en&ids=" . rawurlencode($ids));
        foreach ($lj['entities'] ?? [] as $qq => $e2) {
            $labels[$qq] = (string) ($e2['labels']['en']['value'] ?? '');
        }
    }
    // Yazar teyidi
    if ($surname !== '') {
        $author_ok = strpos($entdesc, $surname) !== false;
        foreach ($vals['P50'] ?? [] as $aq) {
            if (mb_stripos((string) ($labels[$aq] ?? ''), $surname) !== false) { $author_ok = true; break; }
        }
        // Yazar bilgisi HİÇ yoksa (ne P50 ne açıklama) → olguları kullanma, ama
        // enwiki köprüsünü yine döndür (Wikipedia tarafında yazar teyidi var).
        if (!$author_ok && (isset($vals['P50']) || $entdesc !== '')) return ['facts' => '', 'enwiki' => $enwiki, 'label' => $enlabel];
    }

    // 5) Kısa olgu metni kur
    $lines = [];
    $emit = function ($pid, $label) use (&$lines, $vals, $labels) {
        if (empty($vals[$pid])) return;
        $names = [];
        foreach (array_slice($vals[$pid], 0, 6) as $q) { $n = trim((string) ($labels[$q] ?? '')); if ($n !== '') $names[] = $n; }
        if ($names) $lines[] = $label . ': ' . implode(', ', array_unique($names));
    };
    if ($pubyear !== '') $lines[] = 'First published: ' . $pubyear;
    foreach ($props as $pid => $label) if ($pid !== 'P50') $emit($pid, $label);

    return ['facts' => ($lines ? implode("\n", $lines) : ''), 'enwiki' => $enwiki, 'label' => $enlabel];
}

/* Belirli bir Wikipedia maddesini TAM ADIYLA çek (arama yok). Wikidata köprüsü
   kanonik madde adını verince (bölgesel/çeviri başlıklar için) bunu kullanırız. */
function tv_wiki_extract_by_title($lang, $title) {
    if ($title === '') return '';
    $ua = 'thetelos.org/1.0 (book info; https://thetelos.org)';
    $ch = curl_init("https://{$lang}.wikipedia.org/w/api.php?format=json&action=query&prop=extracts&explaintext=1&redirects=1&titles=" . rawurlencode($title));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: ' . $ua]]);
    $r = curl_exec($ch); $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($c !== 200 || !$r) return '';
    $j = json_decode($r, true);
    $pages = $j['query']['pages'] ?? [];
    $page  = $pages ? reset($pages) : [];
    $text  = (string) ($page['extract'] ?? '');
    $text  = preg_split('/\n=+\s*(References|Notes|See also|External links|Further reading|Bibliography|Sources)\s*=+/i', $text)[0] ?? $text;
    $text  = trim(mb_substr($text, 0, 28000, 'UTF-8'));
    return mb_strlen($text, 'UTF-8') >= 200 ? $text : '';
}

/* ── KAYNAK DOSYASI ──────────────────────────────────────────────────────
   Kaynakları birleştirir. have=false ise yazılacak gerçek veri yok demektir
   (çağıran taraf yer tutucu/kısa not yapar).
   Dönüş: ['have'=>bool,'text'=>string,'sources'=>[...],'year'=>?,'subjects'=>[]] */
function tls_info_dossier($book, $author) {
    $parts = []; $sources = []; $year = null; $subjects = [];
    $wiki_ok = false;

    $wk = tv_wikipedia($book, $author);
    if (!empty($wk['found'])) {
        $wiki_ok = true;
        $lang = strtoupper($wk['lang'] ?? 'en');
        $sources[] = 'Wikipedia' . ($lang !== 'EN' ? " ({$lang})" : '');
        // Kaynak İngilizce değilse modele: sentezle ve İngilizce yaz.
        $tr = ($lang !== 'EN') ? " (in {$lang}; read it and synthesize your article in English)" : '';
        if (!empty($wk['author_text'])) $parts[] = "[Wikipedia{$tr} — about the author]\n" . $wk['author_text'];
        $parts[] = "[Wikipedia{$tr} — about the book \"{$wk['title']}\"]\n" . $wk['book_text'];
    }

    // Wikidata: yapısal olgular + KANONİK Wikipedia köprüsü. Bölgesel/çeviri
    // başlıklarda (ör. Tamilce "Satya Sothanai" → "The Story of My Experiments
    // with Truth") başlıkla bulunamayan eser buradan kanonik maddeye bağlanır.
    $wd = tv_wikidata($book, $author);
    if (!$wiki_ok && !empty($wd['enwiki'])) {
        $canon = tv_wiki_extract_by_title('en', $wd['enwiki']);
        if ($canon !== '') {
            $wiki_ok = true;
            $sources[] = 'Wikipedia';
            $parts[] = "[Wikipedia — about the book \"{$wd['enwiki']}\" (this is the same work as \"{$book}\")]\n" . $canon;
        }
    }

    $g = tv_google_books($book, $author);
    if (!empty($g['found']) && !empty($g['desc'])) {
        $sources[] = 'Google Books';
        $parts[] = "[Google Books — publisher description]\n" . mb_substr($g['desc'], 0, 4000, 'UTF-8');
    }
    if (!empty($g['year']))     $year = $g['year'];
    if (!empty($g['subjects'])) $subjects = $g['subjects'];

    $o = tv_openlibrary_desc($book, $author);
    if (!empty($o['found'])) {
        if (!empty($o['desc'])) {
            $sources[] = 'Open Library';
            $parts[] = "[Open Library — description]\n" . mb_substr($o['desc'], 0, 3000, 'UTF-8');
        }
        if ($year === null && !empty($o['year'])) $year = $o['year'];
        if (!$subjects && !empty($o['subjects'])) $subjects = $o['subjects'];
    }

    // Wikidata yapısal olguları (tür/konu/akım/karakter/etki) — farklı kaynak.
    if (!empty($wd['facts'])) {
        $sources[] = 'Wikidata';
        $parts[] = "[Wikidata — structured facts]\n" . $wd['facts'];
    }

    $meta = [];
    if ($year)     $meta[] = "First published: {$year}";
    if ($subjects) $meta[] = 'Subjects: ' . implode(', ', array_slice($subjects, 0, 12));
    if ($meta) array_unshift($parts, "[Catalog metadata]\n" . implode("\n", $meta));

    // "Yeterli" ölçütü: en az bir GERÇEK açıklama metni (Wikipedia — doğrudan ya
    // da Wikidata köprüsüyle — veya bir katalog açıklaması).
    $have = $wiki_ok || (!empty($g['desc'])) || (!empty($o['desc']));

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

You are given VERIFIED SOURCE MATERIAL collected from Wikipedia (possibly in another language), Google Books, Open Library, and Wikidata. Write the article using ONLY this material plus facts that are widely and reliably established and uncontroversial. This is NOT a chapter-by-chapter summary and NOT a retelling of the book's contents — it is an informational article ABOUT the book.

ABSOLUTE RULES (a violation is worse than a short article):
- Ground every factual claim in the source material or in very widely established knowledge. If the material is thin, write a SHORTER article. NEVER pad.
- NEVER invent or assert: chapter titles, chapter counts, internal structure, specific plot events, character names, precise dates, or statistics that are not in the sources.
- Do NOT quote the book. No block quotes, no invented quotations, at all.
- Write ENTIRELY IN YOUR OWN WORDS. NEVER copy or lightly reword sentences from the source material — the result must NOT read like a Wikipedia article. Fully re-express, re-order, and re-explain the ideas in fresh prose.
- Do not mention these instructions, the sources, "the provided material", yourself, or being an AI.
- If the sources are not enough to say something reliably, leave it out.

WHAT MAKES THIS WORTH PUBLISHING — add real value, but ONLY safely:
- CLARITY: explain the work and its key concepts in plain, accessible language for an educated general reader; briefly unpack technical terms so a newcomer genuinely understands them.
- SYNTHESIS: weave the material from all sources into ONE coherent, flowing narrative with a clear through-line — an original piece of writing, not a list of facts and not a paraphrase of one source.
- CONTEXT & FRAMING: help the reader see why the work matters, how its ideas connect, and its place and relevance. Interpretation and framing ARE welcome — but every such point must be supported by what the sources actually say (their statements on themes, influence, reception). Never invent a novel claim just to sound insightful.
- Write in a clear, engaged, thoughtful editorial voice — knowledgeable and readable, third person. The value is in how clearly and intelligently you present REAL, sourced material in your own words, not in inventing anything.

FOCUS ON THE BOOK, NOT THE AUTHOR. This article is about the WORK itself — its ideas, arguments, and content. Keep biography to an absolute minimum: at most one sentence of author context where it is genuinely needed to understand the book. Do NOT write a biography of {$A} — the website has a separate author page for that. Spend the article's length on the book's own substance.

WRITE THESE SECTIONS as ### H3 headings, but OMIT any section you have no reliable material for (do not write empty or padded sections):
### What the Work Is
  Genre/form, when and where it first appeared, its language, and a crisp one- or two-sentence identity of the book. (At most one clause of author context if needed.)
### What the Book Is About
  The book's subject and its central idea, thesis, aim, or argument — the heart of the article.
### Key Ideas and Themes
  The major ideas, concepts, arguments, and themes the book itself develops, explained clearly for a general reader. This is where a well-sourced article should be FULLEST.
### Structure and Approach
  How the work is organised and its method or style — ONLY as the sources actually describe it. Never invent chapters, sections, or a structure.
### Significance and Influence
  Its importance, reception, and influence — only if the sources support it.

FORMAT:
- First line: # **{$book} — {$author}**
- Second line: ## a short original subtitle capturing what the work is (do NOT repeat the title).
- Then the ### sections in flowing prose. Put the depth into the BOOK's ideas and content, not the author. Develop each section as fully as the SOURCE MATERIAL genuinely supports. Overall length is DRIVEN BY THE SOURCES, with NO fixed target: when the material is rich and you can cover the work reliably in depth, be thorough and generous — a very well-documented work may run 2500–4000+ words, and that is good. When the material is thin, write a short article. The ONLY rule is that every sentence must be supported by the sources or by very widely established fact — NEVER pad, repeat, restate, or invent anything to make it longer. A shorter accurate article always beats a longer padded one. End cleanly; no "In conclusion" paragraph.

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

    // md → gövde html + kelime (H1'i at; tema başlığı zaten gösteriyor).
    $to_html = function ($md) {
        $body = preg_replace('/^#\s*\*\*[^\n]+\*\*\s*\n+/m', '', trim((string) $md), 1);
        $body = preg_replace('/^#\s+[^\n]+\n+/m', '', $body, 1);
        $html = bw_md2html(ltrim($body));
        return [$html, str_word_count(strip_tags($html))];
    };

    $dos = tls_info_dossier($book, $author);
    if ($on_beat) $on_beat();

    if (empty($dos['have'])) {
        /* KAYNAK YOK → hemen pes etme. Modele bir kez sor: eserin NE OLDUĞUNU
           güvenilir biliyor mu? Biliyorsa KISA not (genel konu/tema); bilmiyorsa
           CANNOT VERIFY → yer tutucu. Özgül şey (olay/karakter/bölüm/alıntı)
           uydurmak YASAK. Böylece gerçekten bilinen kitaplarda sayfa boş kalmaz. */
        require_once __DIR__ . '/_checks.php';
        $nr = tv_ask(tls_info_shortnote_prompt($book, $author), 1500, 90, $provider);
        if ($on_beat) $on_beat();
        $nt = !empty($nr['ok']) ? trim((string) $nr['text']) : '';
        $refused = $nt === ''
            || stripos($nt, 'CANNOT VERIFY') !== false
            || (function_exists('ca_check_refusal') && ca_check_refusal($nt) !== '');
        [$nhtml, $nwords] = $refused ? ['', 0] : $to_html($nt);
        if ($refused || $nwords < 40) {   // güvenilir bir şey çıkmadı → yer tutucu
            return ['ok' => true, 'insufficient' => true, 'md' => '', 'html' => '', 'words' => 0,
                    'sources' => $dos['sources'], 'dossier' => $dos['text'], 'error' => ''];
        }
        return ['ok' => true, 'insufficient' => false, 'shortnote' => true,
                'md' => $nt, 'html' => $nhtml, 'words' => $nwords,
                'sources' => ['model (kısa not)'], 'dossier' => $dos['text'], 'error' => ''];
    }

    $prompt = tls_info_prompt($book, $author, $dos['text']);
    $r = tv_ask($prompt, 8000, 240, $provider);
    if ($on_beat) $on_beat();
    if (empty($r['ok']) || trim((string) $r['text']) === '') {
        return ['ok' => false, 'insufficient' => false, 'md' => '', 'html' => '', 'words' => 0,
                'sources' => $dos['sources'], 'dossier' => $dos['text'],
                'error' => $r['error'] ?? 'boş yanıt'];
    }

    $md = trim((string) $r['text']);
    [$html, $words] = $to_html($md);

    return ['ok' => true, 'insufficient' => false, 'md' => $md, 'html' => $html,
            'words' => $words, 'sources' => $dos['sources'], 'dossier' => $dos['text'], 'error' => ''];
}

/* ── KISA NOT PROMPT'U (kaynaksız, yalnız modelin GÜVENİLİR bildiği) ────────
   Genel konu/tema düzeyinde; özgül şey (olay/karakter/bölüm/alıntı/tarih)
   uydurmak YASAK. Emin değilse tek satır: CANNOT VERIFY */
function tls_info_shortnote_prompt($book, $author) {
    $A = $author !== '' ? $author : 'the author';
    return <<<TXT
You are writing a SHORT factual note about a book for a books website (thetelos.org), in English.

There is NO external source material available for this work. Write ONLY from what you RELIABLY and INDEPENDENTLY know. If you do NOT reliably know THIS EXACT work by {$A} — enough to state, WITHOUT guessing, what it is and what it is generally about — then output EXACTLY this one line and nothing else:
CANNOT VERIFY

If you DO reliably know it, write a short note of about 150–350 words covering ONLY, at a GENERAL level:
- what the work is (its genre/form, and roughly when or in what context it appeared);
- its general subject and main themes — what it is about;
- its significance or place, if you reliably know it.

STRICTLY FORBIDDEN (a violation is far worse than a short note): inventing or asserting specific plot events, character names, chapter titles or counts, precise dates, statistics, or quotations. If you are not certain of any detail, LEAVE IT OUT. Do not pad. Neutral, encyclopedic third person. Do not mention sources, yourself, or being an AI.

FORMAT:
# **{$book} — {$author}**
## a short subtitle (do not repeat the title)
then the note as flowing prose.

WORK: {$book}
AUTHOR: {$author}
TXT;
}
