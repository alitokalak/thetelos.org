<?php
/**
 * clean-list.php — TEK YAZARIN eser listesini temizle.
 *
 * Katman 1 (kural, ücretsiz): normalize tekrarları birleştir.
 * Katman 2 (AI hakem, DeepSeek): aynı eserin farklı dil/çeviri baskılarını tek
 *   kanonik girişte birleştir ("İngilizce ad (Orijinal ad)"), yazara ait
 *   olmayanları gerekçesiyle ele. AI liste ÜRETMEZ — yalnız verilen başlıkları
 *   yargılar; listede olmayan hiçbir eser eklenmez.
 *
 * POST: author, works (JSON [{title,year,cover}]), use_ai (0/1)
 * Dönüş: { ok, works:[{title,author,year,cover,merged}], removed:[{title,reason}], ai_used, error? }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
@ini_set('display_errors', 0);
set_time_limit(150);

$author = trim($_POST['author'] ?? '');
$works  = json_decode($_POST['works'] ?? '', true);
$use_ai = !empty($_POST['use_ai']);
if ($author === '' || !is_array($works) || empty($works)) {
    echo json_encode(['ok'=>false,'error'=>'author ve works gerekli']); exit;
}

/* ── Normalizasyon (kural katmanı) ── */
function cl_norm($s) {
    $s = mb_strtolower(trim((string)$s));
    $s = preg_replace('/\s*[\(\（].*?[\)\）]\s*/u', ' ', $s);       // parantez içlerini at
    $s = preg_replace('/^(the|a|an|le|la|les|der|die|das|el|il)\s+/u', '', $s);
    $s = preg_replace('/\s*[:;].*$/u', '', $s);                     // alt başlık
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s); if ($t !== false && $t !== '') $s = $t;
    $s = preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s));
    return trim(preg_replace('/\s+/', ' ', $s));
}

/* Kural katmanı: normalize aynı olanları birleştir (en bilgili satırı tut) */
$groups = [];   // norm → ['rows'=>[...], 'title'=>best]
$order  = [];
foreach ($works as $w) {
    $t = trim((string)($w['title'] ?? '')); if ($t === '') continue;
    $n = cl_norm($t); if ($n === '') $n = mb_strtolower($t);
    if (!isset($groups[$n])) { $groups[$n] = ['rows'=>[], 'title'=>$t]; $order[] = $n; }
    $groups[$n]['rows'][] = [
        'title' => $t,
        'year'  => trim((string)($w['year']  ?? '')),
        'cover' => trim((string)($w['cover'] ?? '')),
    ];
    // Tercih: Latin alfabeli ve daha kısa ana başlığı temsilci yap
    $cur = $groups[$n]['title'];
    $t_latin  = (bool)preg_match('/^[\x20-\x7E\p{Latin}\p{P}\p{Zs}\d]+$/u', $t);
    $c_latin  = (bool)preg_match('/^[\x20-\x7E\p{Latin}\p{P}\p{Zs}\d]+$/u', $cur);
    if (($t_latin && !$c_latin) || ($t_latin === $c_latin && mb_strlen($t) < mb_strlen($cur))) {
        $groups[$n]['title'] = $t;
    }
}

/* Grubun en iyi yıl/kapak değeri (ilk dolu; yıl için en küçük 4 haneli = ilk basım) */
function cl_best($rows) {
    $year = ''; $cover = '';
    foreach ($rows as $r) {
        if ($r['cover'] !== '' && $cover === '') $cover = $r['cover'];
        if (preg_match('/^\d{3,4}$/', $r['year'])) {
            if ($year === '' || (int)$r['year'] < (int)$year) $year = $r['year'];
        }
    }
    return [$year, $cover];
}

$items = [];   // kural sonrası aday liste
foreach ($order as $n) {
    [$year, $cover] = cl_best($groups[$n]['rows']);
    $items[] = [
        'title'  => $groups[$n]['title'],
        'year'   => $year,
        'cover'  => $cover,
        'merged' => count($groups[$n]['rows']),
        'all'    => array_map(fn($r) => $r['title'], $groups[$n]['rows']),
    ];
}

$removed = [];
$ai_used = false;
$ai_err  = '';

/* ── Dil→alfabe eşlemesi (yapısal garanti için) ── */
function cl_scripts_for_langs($langs) {
    $map = [
        'german'=>'Latin','english'=>'Latin','french'=>'Latin','italian'=>'Latin','spanish'=>'Latin',
        'portuguese'=>'Latin','dutch'=>'Latin','latin'=>'Latin','turkish'=>'Latin','danish'=>'Latin',
        'norwegian'=>'Latin','swedish'=>'Latin','polish'=>'Latin','czech'=>'Latin','hungarian'=>'Latin',
        'romanian'=>'Latin','finnish'=>'Latin','vietnamese'=>'Latin','indonesian'=>'Latin',
        'russian'=>'Cyrillic','ukrainian'=>'Cyrillic','bulgarian'=>'Cyrillic','serbian'=>'Cyrillic',
        'greek'=>'Greek','ancient greek'=>'Greek','koine greek'=>'Greek',
        'hebrew'=>'Hebrew','aramaic'=>'Hebrew','yiddish'=>'Hebrew',
        'arabic'=>'Arabic','persian'=>'Arabic','farsi'=>'Arabic','urdu'=>'Arabic','ottoman turkish'=>'Arabic',
        'chinese'=>'Han','classical chinese'=>'Han','mandarin'=>'Han',
        'japanese'=>'Japanese','korean'=>'Hangul',
        'sanskrit'=>'Devanagari','hindi'=>'Devanagari','pali'=>'Devanagari','bengali'=>'Bengali','tamil'=>'Tamil',
    ];
    $out = [];
    foreach ((array)$langs as $l) {
        $k = strtolower(trim((string)$l));
        $out[$map[$k] ?? 'Latin'] = true;
        if (($map[$k] ?? '') === 'Japanese') $out['Han'] = true;   // Japonca kanji kullanır
    }
    return $out;
}
function cl_script_of($s) {
    if (preg_match('/[\x{3040}-\x{30FF}]/u', $s)) return 'Japanese';   // kana
    if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $s)) return 'Han';
    if (preg_match('/[\x{0590}-\x{05FF}]/u', $s)) return 'Hebrew';
    if (preg_match('/[\x{0600}-\x{06FF}]/u', $s)) return 'Arabic';
    if (preg_match('/[\x{0400}-\x{04FF}]/u', $s)) return 'Cyrillic';
    if (preg_match('/[\x{0370}-\x{03FF}]/u', $s)) return 'Greek';
    if (preg_match('/[\x{AC00}-\x{D7AF}]/u', $s)) return 'Hangul';
    if (preg_match('/[\x{0900}-\x{097F}]/u', $s)) return 'Devanagari';
    if (preg_match('/[\x{0980}-\x{09FF}]/u', $s)) return 'Bengali';
    if (preg_match('/[\x{0B80}-\x{0BFF}]/u', $s)) return 'Tamil';
    return 'Latin';
}

/* ── AI hakem katmanı ── */
if ($use_ai && count($items) >= 2 && defined('DEEPSEEK_KEY') && DEEPSEEK_KEY !== '') {
    $cap = 120;                                    // token güvenliği (her giriş çıktıda yer alacak)
    $slice = array_slice($items, 0, $cap);
    $lines = '';
    foreach ($slice as $i => $it) {
        $lines .= ($i+1) . '. ' . mb_substr($it['title'], 0, 160) . "\n";
    }
    $prompt = "You are a strict bibliographic judge. Below is a numbered list of titles catalogued under the author \"{$author}\".\n"
        . "Your job: produce the author's clean canonical bibliography from these entries.\n"
        . "STEP 0 — first determine which language(s) {$author} actually WROTE their works in, and return them in \"wrote_in\" "
        . "(e.g. Einstein → [\"German\",\"English\"]; Laozi → [\"Classical Chinese\"]). Every 'orig' you output must be in one of these languages.\n"
        . "OUTPUT CONTRACT — every entry number MUST appear in exactly one place: either in some group's members, or in not_by_author.\n"
        . "1) GROUP entries that are the SAME WORK (translations, different-language/script editions, transliterations, spelling variants) into ONE group. "
        . "A single-member group is normal for works appearing once.\n"
        . "2) For EVERY group give:\n"
        . "   en   = the work's standard title as used in ENGLISH literature (e.g. \"Tao Te Ching\", \"Critique of Pure Reason\", \"The Evolution of Physics\")\n"
        . "   orig = the title in the language the work was ORIGINALLY WRITTEN in by {$author} (e.g. \"道德经\" for Laozi, \"Kritik der reinen Vernunft\" for Kant).\n"
        . "   CRITICAL: think about which language(s) {$author} actually wrote in. A TRANSLATION'S title is NEVER orig — "
        . "e.g. a Japanese or Hebrew edition title of an Einstein work is NOT the original (Einstein wrote in German/English). "
        . "orig must be empty if the work was originally written in English, and ALSO empty if you don't know the true original title. "
        . "Never copy a listed foreign edition title into orig unless it IS the language the author wrote in.\n"
        . "3) not_by_author: entries that are NOT a single book written by {$author} — books ABOUT the author, secondary literature, "
        . "quote/aphorism collections (\"Quotes\", \"Words of Wisdom\"), publisher compilations (\"Collected/Complete Works\", \"Selected Writings\", omnibus editions), "
        . "anthologies/views/studies titled \"<Something> of/about {$author}\" (a memoir the author wrote about themselves is fine), "
        . "titles that are just the author's name or a slogan, or entries you cannot identify at all. Short reason each.\n"
        . "4) ERA CHECK — consider when {$author} lived and what they could have written. Entries chronologically or thematically "
        . "IMPOSSIBLE for this author (e.g. a modern travel book under an 8th-century poet — likely a different person with the same name) MUST be flagged with reason \"implausible for this author\".\n"
        . "5) For each group also give year = the work's ORIGINAL first-publication/composition year as an integer if you know it "
        . "(e.g. 868 for the Diamond Sutra printing, 1687 for Principia) — NOT a modern reprint year; empty string if unknown.\n"
        . "Rules: judge ONLY the given entries; do NOT invent extra works; when unsure about a plausible entry, keep it as its own group.\n"
        . "Return ONLY JSON:\n"
        . "{\"wrote_in\":[\"German\",\"English\"],\"groups\":[{\"en\":\"English title\",\"orig\":\"Original title or empty\",\"year\":\"1687\",\"members\":[1,4]}],\"not_by_author\":[{\"n\":3,\"reason\":\"short reason\"}]}\n\n"
        . $lines;

    $ch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 90,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => DEEPSEEK_MODEL, 'max_tokens' => 6000, 'temperature' => 0,
            'messages' => [['role'=>'user','content'=>$prompt]],
        ]),
    ]);
    $r = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);

    $parsed = null;
    if ($http === 200 && $r) {
        $d   = json_decode($r, true);
        $txt = $d['choices'][0]['message']['content'] ?? '';
        $txt = preg_replace('/```json|```/i', '', $txt);
        $s = strpos($txt, '{'); $e = strrpos($txt, '}');
        if ($s !== false && $e > $s) $parsed = json_decode(substr($txt, $s, $e - $s + 1), true);
    } else {
        $ai_err = "AI HTTP $http";
    }

    if (is_array($parsed)) {
        $ai_used  = true;
        // Yazarın yazdığı dillerin alfabeleri — orig bu alfabelerde değilse GEÇERSİZ.
        $wrote_scripts = cl_scripts_for_langs($parsed['wrote_in'] ?? []);
        $flagged  = [];   // index(0-based) → reason
        foreach ($parsed['not_by_author'] ?? [] as $f) {
            $ix = (int)($f['n'] ?? 0) - 1;
            if ($ix >= 0 && $ix < count($slice)) $flagged[$ix] = trim((string)($f['reason'] ?? 'yazara ait değil'));
        }

        $used = [];      // gruba giren indexler
        $out  = [];
        foreach ($parsed['groups'] ?? [] as $g) {
            $members = array_values(array_filter(array_map(fn($m) => (int)$m - 1, (array)($g['members'] ?? [])),
                        fn($ix) => $ix >= 0 && $ix < count($slice) && !isset($flagged[$ix]) && !isset($used[$ix])));
            if (empty($members)) continue;
            foreach ($members as $ix) $used[$ix] = true;

            $en   = trim((string)($g['en']   ?? ''));
            $orig = trim((string)($g['orig'] ?? ''));
            if ($en === '') $en = $slice[$members[0]]['title'];
            // YAPISAL GARANTİ: orig'in alfabesi, yazarın yazdığı dillerin alfabesinde
            // değilse (ör. Einstein + Japonca) orig bir ÇEVİRİ başlığıdır → at.
            if ($orig !== '' && !empty($wrote_scripts) && !isset($wrote_scripts[cl_script_of($orig)])) {
                $orig = '';
            }
            $final = $en;
            if ($orig !== '' && mb_strtolower($orig) !== mb_strtolower($en)) $final = "$en ($orig)";

            // Yıl: AI'nın verdiği İLK YAYIN yılı öncelikli (modern baskı yılı değil);
            // yoksa üyelerin en küçük yılı. Kapak: ilk dolu.
            $ai_year = trim((string)($g['year'] ?? ''));
            $year = preg_match('/^\d{1,4}$/', $ai_year) ? $ai_year : '';
            $cover = ''; $mergedN = 0;
            foreach ($members as $ix) {
                $it = $slice[$ix]; $mergedN += $it['merged'];
                if ($cover === '' && $it['cover'] !== '') $cover = $it['cover'];
                if ($year === '' && preg_match('/^\d{3,4}$/', $it['year'])) $year = $it['year'];
                elseif ($ai_year === '' && preg_match('/^\d{3,4}$/', $it['year']) && $year !== '' && (int)$it['year'] < (int)$year) $year = $it['year'];
            }
            $out[] = ['title'=>$final, 'author'=>$author, 'year'=>$year, 'cover'=>$cover, 'merged'=>$mergedN];
        }
        // Gruba girmeyen + işaretlenmemiş girişler aynen kalır (AI kararsızsa dokunma)
        foreach ($slice as $ix => $it) {
            if (isset($used[$ix])) continue;
            if (isset($flagged[$ix])) {
                $removed[] = ['title'=>$it['title'], 'author'=>$author, 'year'=>$it['year'], 'cover'=>$it['cover'], 'reason'=>$flagged[$ix]];
                continue;
            }
            $out[] = ['title'=>$it['title'], 'author'=>$author, 'year'=>$it['year'], 'cover'=>$it['cover'], 'merged'=>$it['merged']];
        }
        // Cap dışında kalanlar (>150) kural sonucuyla eklenir
        foreach (array_slice($items, $cap) as $it) {
            $out[] = ['title'=>$it['title'], 'author'=>$author, 'year'=>$it['year'], 'cover'=>$it['cover'], 'merged'=>$it['merged']];
        }
        $items_final = $out;
    } else {
        if ($ai_err === '') $ai_err = 'AI yanıtı çözümlenemedi';
    }
}

if (!isset($items_final)) {
    // AI yok/başarısız → kural sonucu
    $items_final = array_map(fn($it) => [
        'title'=>$it['title'], 'author'=>$author, 'year'=>$it['year'], 'cover'=>$it['cover'], 'merged'=>$it['merged'],
    ], $items);
}

/* ── SERT GÜVENLİK KATMANI (AI'dan bağımsız, çıkışta ZORUNLU) ──
   Format kuralı: her başlığın ANA kısmı İngilizce literatür adı olmalı; orijinal
   ad parantezdedir ("Tao Te Ching (道德经)" GEÇERLİDİR). Şunlar temiz listeye
   giremez (Elenenler'e düşer, geri alınabilir):
   1) Ana kısmı hâlâ Latin harfsiz kalan başlık = AI eseri İngilizce adıyla çözememiş
   2) Başlık == yazar adı ("Albert Einstein")
   3) Alıntı/özlü söz derlemeleri ("Quotes", "Words of Wisdom") */
$guarded = [];
$guard_seen = [];   // norm ana başlık → guarded index (yazar içi tekrar garantisi)
$auth_norm = cl_norm($author);
foreach ($items_final as $it) {
    // SAHTE PARANTEZ: "(Hebrew translation)", "(Latin edition)" gibi AÇIKLAMA
    // parantezleri orijinal ad değildir → paranteze at, ana başlık kalsın.
    if (preg_match('/^(.*?)\s*[\(\（]([^\)）]*)[\)）]\s*$/u', $it['title'], $pm)
        && preg_match('/\b(translation|edition|version|reprint|commentar\w*|abridged|selection|excerpt|subtitle|alternative|volume|part\s+\d)\b/i', $pm[2])) {
        $it['title'] = trim($pm[1]);
    }
    $main = trim(preg_replace('/\s*[\(\（].*$/u', '', $it['title']));   // parantez öncesi ana başlık
    if (!preg_match('/\p{Latin}/u', $main)) {
        $removed[] = ['title'=>$it['title'], 'author'=>$author, 'year'=>$it['year'], 'cover'=>$it['cover'],
                      'reason'=>'İngilizce literatür adı çözülemedi — geri alıp elle "İngilizce Ad ('.$it['title'].')" yazabilirsin'];
        continue;
    }
    if ($auth_norm !== '' && cl_norm($main) === $auth_norm) {
        $removed[] = ['title'=>$it['title'], 'author'=>$author, 'year'=>$it['year'], 'cover'=>$it['cover'],
                      'reason'=>'başlık = yazar adı'];
        continue;
    }
    if (preg_match('/\b(quotes?|quotations?|words of wisdom|sayings|aphorisms?)\b/i', $main)) {
        $removed[] = ['title'=>$it['title'], 'author'=>$author, 'year'=>$it['year'], 'cover'=>$it['cover'],
                      'reason'=>'alıntı/özlü söz derlemesi'];
        continue;
    }
    if (preg_match('/\b(collected|complete|selected)\s+(works|writings|papers|essays)\b|\bomnibus\b/i', $main)) {
        $removed[] = ['title'=>$it['title'], 'author'=>$author, 'year'=>$it['year'], 'cover'=>$it['cover'],
                      'reason'=>'yayıncı külliyatı/derlemesi — tekil eser değil'];
        continue;
    }
    // TEKRAR GARANTİSİ: aynı yazarda aynı ana başlık (normalize) tek satır olur.
    // (AI aynı eseri farklı parantez varyantlarıyla ayrı gruplara koyabiliyor.)
    $nk = cl_norm($main);
    if ($nk !== '' && isset($guard_seen[$nk])) {
        $j = $guard_seen[$nk];   // ilk görüleni zenginleştir
        if ($guarded[$j]['year']  === '' && $it['year']  !== '') $guarded[$j]['year']  = $it['year'];
        if ($guarded[$j]['cover'] === '' && $it['cover'] !== '') $guarded[$j]['cover'] = $it['cover'];
        // Parantezli (orijinalli) başlık, parantezsize tercih edilir
        if (!preg_match('/\(/', $guarded[$j]['title']) && preg_match('/\(/', $it['title'])) $guarded[$j]['title'] = $it['title'];
        $guarded[$j]['merged'] += $it['merged'];
        continue;
    }
    if ($nk !== '') $guard_seen[$nk] = count($guarded);
    $guarded[] = $it;
}
$items_final = $guarded;

/* ── KAPAK/YIL TAMAMLAMA (temizlik SONRASI, kanonik İngilizce adla) ──
   Ham varyant adla bulunamayan kapak, temiz İngilizce adla çoğu kez bulunur.
   Yazar başına 1 OpenLibrary search + 1 Google Books isteği; yalnız eksikler dolar. */
function cl_http_get($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_CONNECTTIMEOUT=>8,
        CURLOPT_FOLLOWLOCATION=>true, CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,
        CURLOPT_HTTPHEADER=>['Accept: application/json',
            'User-Agent: ThetelosBot/1.0 (https://thetelos.org; mailto:alitokalak@gmail.com)']]);
    $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ($c === 200 && $r) ? json_decode($r, true) : null;
}
function cl_meta_from_map($title, $map) {
    $empty = ['cover'=>'', 'year'=>''];
    if (empty($map)) return $empty;
    $q = cl_norm($title);
    if ($q === '') return $empty;
    if (isset($map[$q])) return $map[$q];
    if (strlen($q) >= 5) {
        foreach ($map as $k => $m) {
            if (str_contains($k, $q) || str_contains($q, $k)) return $m;
        }
    }
    return $empty;
}

$missing = 0;
foreach ($items_final as $it) if ($it['cover'] === '' || $it['year'] === '') $missing++;
if ($missing > 0) {
    $maps = [];
    // OpenLibrary search — cover_i + first_publish_year bol
    $ol = cl_http_get('https://openlibrary.org/search.json?' . http_build_query([
        'author'=>$author, 'limit'=>100, 'fields'=>'title,cover_i,first_publish_year']));
    $m = [];
    foreach ($ol['docs'] ?? [] as $d) {
        $k = cl_norm($d['title'] ?? ''); if ($k === '') continue;
        $c = !empty($d['cover_i']) ? ('https://covers.openlibrary.org/b/id/'.(int)$d['cover_i'].'-M.jpg') : '';
        $y = !empty($d['first_publish_year']) ? (string)(int)$d['first_publish_year'] : '';
        if (!isset($m[$k])) $m[$k] = ['cover'=>$c,'year'=>$y];
        else { if ($c && !$m[$k]['cover']) $m[$k]['cover']=$c; if ($y && !$m[$k]['year']) $m[$k]['year']=$y; }
    }
    if ($m) $maps[] = $m;
    // Google Books
    if (defined('GOOGLE_BOOKS_KEY') && GOOGLE_BOOKS_KEY !== '') {
        $gb = cl_http_get('https://www.googleapis.com/books/v1/volumes?' . http_build_query([
            'q'=>'inauthor:"'.$author.'"', 'maxResults'=>40, 'printType'=>'books',
            'fields'=>'items(volumeInfo(title,imageLinks,publishedDate))', 'key'=>GOOGLE_BOOKS_KEY]));
        $m2 = [];
        foreach ($gb['items'] ?? [] as $item) {
            $vi = $item['volumeInfo'] ?? [];
            $k = cl_norm($vi['title'] ?? ''); if ($k === '') continue;
            $lnk = $vi['imageLinks'] ?? [];
            $c = $lnk['thumbnail'] ?? ($lnk['smallThumbnail'] ?? '');
            if ($c) { $c = str_replace(['http://','&edge=curl'], ['https://',''], $c); $c = preg_replace('/zoom=\d/', 'zoom=3', $c); }
            $y = (!empty($vi['publishedDate']) && preg_match('/\d{4}/', $vi['publishedDate'], $ym)) ? $ym[0] : '';
            if (!isset($m2[$k])) $m2[$k] = ['cover'=>$c,'year'=>$y];
        }
        if ($m2) $maps[] = $m2;
    }
    foreach ($items_final as &$it) {
        if ($it['cover'] !== '' && $it['year'] !== '') continue;
        $main = trim(preg_replace('/\s*[\(\（].*$/u', '', $it['title']));
        foreach ($maps as $map) {
            if ($it['cover'] !== '' && $it['year'] !== '') break;
            $mm = cl_meta_from_map($main, $map);
            if ($it['cover'] === '' && $mm['cover'] !== '') $it['cover'] = $mm['cover'];
            if ($it['year']  === '' && $mm['year']  !== '') $it['year']  = $mm['year'];
        }
    }
    unset($it);
}

echo json_encode([
    'ok'      => true,
    'author'  => $author,
    'works'   => $items_final,
    'removed' => $removed,
    'ai_used' => $ai_used,
    'ai_err'  => $ai_err,
    'in'      => count($works),
    'out'     => count($items_final),
], JSON_UNESCAPED_UNICODE);
