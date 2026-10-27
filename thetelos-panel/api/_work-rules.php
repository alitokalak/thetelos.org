<?php
/**
 * _work-rules.php — Liste temizlemenin AI'dan BAĞIMSIZ, kodla zorlanan kuralları.
 *
 * KURAL 1 (dil): Yazar hangi dil(ler)de yazdıysa eserlerinin orijinali o
 *   dildedir. Başlık İngilizce değilse ve yazarın yazdığı dillerden biri de
 *   değilse = ÇEVİRİ baskıdır (ör. Koestler Macarca/Almanca/İngilizce yazdı →
 *   Hırvatça "Kazari – 13. židovsko pleme?" bir çeviridir).
 * KURAL 2 (tekrar): Aynı eser farklı yazımla iki kez listelenemez
 *   ("The sleep walkers" = "Sleepwalkers: A History of Man's Changing Vision").
 */

if (!function_exists('wr_norm')) {
    /** Karşılaştırma anahtarı: küçük harf, ASCII, baştaki tanımlık yok, parantez/alt başlık yok. */
    function wr_norm($s) {
        $s = mb_strtolower(trim((string) $s), 'UTF-8');
        $s = preg_replace('/\s*[\(\（].*?[\)\）]\s*/u', ' ', $s);
        $s = preg_replace('/\s*[:;]\s.*$|\s*[:;]$|\s+[–—-]\s.*$/u', '', $s);
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false && $t !== '') $s = $t;
        $s = preg_replace('/[^a-z0-9]+/', ' ', strtolower($s));
        $s = preg_replace('/^(the|a|an|le|la|les|der|die|das|el|il)\s+/', '', trim($s));
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}

/** Aynı eser mi? (normalize edilmiş iki başlık) */
function wr_same_work($a, $b) {
    $a = wr_norm($a); $b = wr_norm($b);
    if ($a === '' || $b === '') return false;
    $ca = str_replace(' ', '', $a); $cb = str_replace(' ', '', $b);
    if ($ca === $cb) return true;                         // "sleep walkers" = "sleepwalkers"
    if (strlen($ca) > strlen($cb)) { [$a, $b, $ca, $cb] = [$b, $a, $cb, $ca]; }
    if (strlen($ca) < 6 || strpos($cb, $ca) !== 0) return false;
    // Kısa başlık uzunun başı: kalan kısım bir ALT BAŞLIK ise aynı eser.
    // ("Sleepwalkers" + "a history of…" evet; "The Ghost" + "in the machine" hayır.)
    $rest = '';
    $need = strlen($ca); $got = 0;
    for ($i = 0, $L = strlen($b); $i < $L; $i++) {
        if ($b[$i] !== ' ') $got++;
        if ($got === $need) { $rest = trim(substr($b, $i + 1)); break; }
    }
    return (bool) preg_match('/^(a|an|or|being|the (story|history|life|tale|true story) of)\s/', $rest . ' ');
}

/**
 * Latin harfli başlığın dil adayları (küçük harf dil adları). İngilizce
 * görünüyorsa ya da işaret yoksa boş dizi döner (= karar verilemez → dokunma).
 */
function wr_guess_langs($title) {
    $s = mb_strtolower((string) $title, 'UTF-8');
    $words = preg_split('/[^\p{L}\']+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
    if (!$words) return [];
    // İngilizce işlev kelimesi varsa İngilizce say ("Gödel, Escher, Bach: an Eternal…").
    $en = ['the','of','and','in','to','with','for','from','on','an','is','my','his','her','its','at','by','into','about','how','what','why'];
    $en_hits = count(array_intersect($words, $en));

    $marks = [
        '/[řůě]/u'        => ['czech'],
        '/[łąęśźżń]/u'    => ['polish'],
        '/[őű]/u'         => ['hungarian'],
        '/[ğşı]/u'        => ['turkish'],
        '/[ãõ]/u'         => ['portuguese'],
        '/ñ/u'            => ['spanish'],
        '/ß/u'            => ['german'],
        '/[øæ]/u'         => ['danish', 'norwegian'],
        '/å/u'            => ['swedish', 'danish', 'norwegian'],
        '/đ/u'            => ['croatian', 'serbian', 'bosnian'],
        '/[žšč]/u'        => ['croatian', 'serbian', 'bosnian', 'slovene', 'slovenian', 'czech', 'slovak', 'latvian', 'lithuanian'],
        '/ć/u'            => ['croatian', 'serbian', 'bosnian', 'polish'],
        '/[êèàçëîïûœâ]/u' => ['french'],
        '/[ìò]/u'         => ['italian'],
        '/[äöü]/u'        => ['german', 'swedish', 'finnish', 'hungarian', 'turkish', 'estonian'],
        '/[áíóúé]/u'      => ['spanish', 'portuguese', 'hungarian', 'czech', 'slovak', 'italian', 'french', 'irish', 'icelandic'],
    ];
    // 'de', 'et', 'est', 'dei', 'die', 'as', 'do' gibi Latince/İngilizceyle çakışan kelimeler bilerek yok
    // ("De Profundis", "De Civitate Dei" çeviri sanılmasın).
    $stops = [
        'french'     => ['le','la','les','des','du','une','pour','sur','dans','aux','au','ou'],
        'german'     => ['der','das','und','von','vom','zum','zur','ein','eine','einer','über','mit','des'],
        'spanish'    => ['el','los','las','del','por','para','una','con'],
        'italian'    => ['il','lo','gli','della','delle','degli','di','nel','nella'],
        'portuguese' => ['dos','das','para','uma','em'],
        'dutch'      => ['het','een','voor'],
        'turkish'    => ['ve','bir','için','ile'],
        'croatian'   => ['i','u','na','za','od','je','iz'],
        'serbian'    => ['i','u','na','za','od','je','iz'],
        'polish'     => ['i','w','na','z','do','się'],
        'hungarian'  => ['az','és','egy'],
        'swedish'    => ['och','ett','av','på','för'],
    ];

    $by_mark = [];
    foreach ($marks as $re => $langs) if (preg_match($re, $s)) $by_mark = array_merge($by_mark, $langs);
    $by_mark = array_values(array_unique($by_mark));

    $by_stop = []; $stop_hits = 0;
    foreach ($stops as $lang => $list) {
        $h = count(array_intersect($words, $list));
        if ($h > 0) { $by_stop[$lang] = $h; $stop_hits = max($stop_hits, $h); }
    }

    if ($en_hits > 0 && $en_hits >= $stop_hits) return [];          // İngilizce başlık
    if ($by_mark) {
        // En dar özel işareti tercih et; durak kelimesi daraltabiliyorsa daralt.
        $narrow = array_values(array_intersect($by_mark, array_keys($by_stop)));
        return $narrow ?: $by_mark;
    }
    // Yalnız durak kelimesi: tek harfli/çok yaygın tek eşleşme ("e", "i") yetmez.
    $strong = array_keys(array_filter($by_stop, fn($h) => $h >= 1));
    $strong = array_values(array_filter($strong, function ($lang) use ($words, $stops) {
        foreach (array_intersect($words, $stops[$lang]) as $w) if (mb_strlen($w) >= 2) return true;
        return false;
    }));
    return $strong;
}

/** Alfabe → olası diller (Latin dışı başlıklar için). */
function wr_script_langs($title) {
    $s = (string) $title;
    if (preg_match('/[\x{3040}-\x{30FF}]/u', $s)) return ['japanese'];
    if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $s)) return ['chinese', 'classical chinese', 'mandarin', 'japanese'];
    if (preg_match('/[\x{0590}-\x{05FF}]/u', $s)) return ['hebrew', 'yiddish', 'aramaic'];
    if (preg_match('/[\x{0600}-\x{06FF}]/u', $s)) return ['arabic', 'persian', 'farsi', 'urdu', 'ottoman turkish'];
    if (preg_match('/[\x{0400}-\x{04FF}]/u', $s)) return ['russian', 'ukrainian', 'bulgarian', 'serbian', 'belarusian', 'macedonian'];
    if (preg_match('/[\x{0370}-\x{03FF}]/u', $s)) return ['greek', 'ancient greek', 'koine greek'];
    if (preg_match('/[\x{AC00}-\x{D7AF}]/u', $s)) return ['korean'];
    if (preg_match('/[\x{0900}-\x{097F}]/u', $s)) return ['sanskrit', 'hindi', 'pali', 'marathi', 'nepali'];
    if (preg_match('/[\x{0980}-\x{09FF}]/u', $s)) return ['bengali'];
    if (preg_match('/[\x{0B80}-\x{0BFF}]/u', $s)) return ['tamil'];
    return null;   // Latin
}

/**
 * KURAL 1: başlık, yazarın yazdığı diller dışında mı? (= çeviri baskısı)
 * Döner: '' (sorun yok / karar verilemez) ya da tespit edilen dil(ler) metni.
 */
function wr_translation_lang($title, array $wrote_in) {
    $wrote = array_map(fn($l) => strtolower(trim((string) $l)), $wrote_in);
    $wrote = array_values(array_filter($wrote));
    if (!$wrote) return '';
    $wrote[] = 'english';
    $cand = wr_script_langs($title);
    if ($cand === null) $cand = wr_guess_langs($title);
    if (!$cand) {
        // İşaret yoksa: wr_prime_langs ile AI'dan alınmış dil etiketi (yalnız dil tanıma).
        $ai = $GLOBALS['wr_lang_map'][wr_lang_key($title)] ?? '';
        if ($ai === '' || in_array($ai, ['english', 'latin', 'unknown'], true)) return '';   // Latince başlık çoğu kez orijinaldir
        $cand = [$ai];
    }
    foreach ($cand as $l) {
        foreach ($wrote as $w) if ($w === $l || strpos($w, $l) !== false || strpos($l, $w) !== false) return '';
    }
    return implode('/', array_slice($cand, 0, 3));
}

/**
 * Yazarın eser yazdığı diller (wrote_in boş geldiğinde). Tek kısa DeepSeek
 * çağrısı, yazar başına dosyada önbelleklenir.
 */
function wr_author_langs($author) {
    $author = trim((string) $author);
    if ($author === '') return [];
    $dir  = __DIR__ . '/cache/clean';
    $file = $dir . '/lang_' . md5('wl1|' . mb_strtolower($author, 'UTF-8')) . '.json';
    if (is_file($file)) {
        $hit = json_decode((string) file_get_contents($file), true);
        if (is_array($hit) && !empty($hit['langs'])) return $hit['langs'];
    }
    if (!defined('DEEPSEEK_API_URL') || !defined('DEEPSEEK_KEY') || !DEEPSEEK_KEY) return [];
    $ch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 40,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => (defined('DEEPSEEK_MODEL') && DEEPSEEK_MODEL && DEEPSEEK_MODEL !== 'deepseek-v4-flash') ? DEEPSEEK_MODEL : 'deepseek-chat',
            'max_tokens' => 80, 'temperature' => 0,
            'response_format' => ['type' => 'json_object'],
            'messages' => [['role' => 'user', 'content' =>
                "In which language(s) did the author \"$author\" originally WRITE their books? Only languages they themselves wrote in, not translations. "
                . "Return ONLY JSON: {\"wrote_in\":[\"Hungarian\",\"German\",\"English\"]}. If you do not know this author, return {\"wrote_in\":[]}."]],
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200 || !$r) return [];
    $d = json_decode($r, true);
    $j = json_decode((string) ($d['choices'][0]['message']['content'] ?? ''), true);
    $langs = array_values(array_filter(array_map('strval', (array) ($j['wrote_in'] ?? []))));
    if ($langs) {
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($file, json_encode(['langs' => $langs], JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    return $langs;
}

/**
 * Yabancı başlığın İngilizce adı (yerleşik ad ya da düz çeviri). DeepSeek,
 * clean-list.php'nin cl_resolve_en önbelleğini paylaşır.
 */
function wr_resolve_en($title, $author, array $known = []) {
    $dir  = __DIR__ . '/cache/clean';
    $file = $dir . '/en_' . md5(($known ? 'en2|' : 'en1|') . mb_strtolower(trim((string) $title)) . '|' . mb_strtolower(trim((string) $author))) . '.json';
    if (is_file($file)) {
        $hit = json_decode((string) file_get_contents($file), true);
        if (is_array($hit) && !empty($hit['en'])) return (string) $hit['en'];
    }
    $known = array_slice(array_values(array_filter(array_map('trim', $known))), 0, 80);
    if (!defined('DEEPSEEK_API_URL') || !defined('DEEPSEEK_KEY') || !DEEPSEEK_KEY) return '';
    $sys = 'You output ONLY the ENGLISH title of the given book — nothing else. If the work has an established English-literature title, output that. Otherwise output a faithful, natural English translation of the title. Output ONLY the title text: no quotes, no author name, no explanation, no original-language text.';
    $ch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 40,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => (defined('DEEPSEEK_MODEL') && DEEPSEEK_MODEL && DEEPSEEK_MODEL !== 'deepseek-v4-flash') ? DEEPSEEK_MODEL : 'deepseek-chat',
            'max_tokens' => 80, 'temperature' => 0,
            'messages' => [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => "Book title: {$title}\nAuthor: {$author}"
                . ($known ? "\nThis title is probably a translated edition. If it is the same work as one of the author's works below, output THAT title exactly:\n- " . implode("\n- ", $known) : '')]],
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200 || !$r) return '';
    $d = json_decode($r, true);
    $out = trim(preg_replace('/\s+/u', ' ', (string) ($d['choices'][0]['message']['content'] ?? '')));
    $out = trim($out, " \t\"“”'’");
    if (mb_strlen($out) < 2 || mb_strlen($out) > 200) return '';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($file, json_encode(['en' => $out], JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $out;
}

/**
 * KURAL 1 + 2'yi grup listesine uygula (_clean-lib.php biçimi:
 * ['en','orig','display','year','members']). Çeviri olup İngilizce adı
 * çözülemeyen grubun üyeleri $flags'e (n => reason) taşınır; aynı eserin
 * grupları tek grupta birleşir.
 */
function wr_enforce_groups(array $groups, array &$flags, array $wrote_in, $author) {
    $out = [];
    foreach ($groups as $g) {
        $en = trim((string) $g['en']); $orig = trim((string) ($g['orig'] ?? ''));
        if ($orig !== '' && wr_translation_lang($orig, $wrote_in) !== '') $orig = '';
        $tr = wr_translation_lang($en, $wrote_in);
        $own_foreign = ($tr === '') && (!preg_match('/\p{Latin}/u', $en) || wr_guess_langs($en));   // yazarın kendi dilinde
        if ($tr !== '' || $own_foreign) {
            $known = [];
            if ($tr !== '') foreach ($groups as $o) if ($o['en'] !== $en && wr_translation_lang($o['en'], $wrote_in) === '' && !wr_guess_langs($o['en'])) $known[] = $o['en'];
            $en2 = wr_resolve_en($en, $author, $known);
            if ($en2 !== '' && preg_match('/\p{Latin}/u', $en2) && !wr_guess_langs($en2)) {
                if ($own_foreign && $orig === '') $orig = $en;     // orijinal ad korunur
                $en = $en2;
            } elseif ($tr !== '') {
                foreach ($g['members'] as $m) $flags[$m] = 'çeviri baskısı (' . $tr . ') — yazar ' . implode('/', $wrote_in) . ' yazdı';
                continue;
            }
        }
        $g['en'] = $en; $g['orig'] = $orig;
        $g['display'] = ($orig !== '' && mb_strtolower($orig) !== mb_strtolower($en)) ? "$en ($orig)" : $en;
        $j = null;
        foreach ($out as $oi => $o) if (wr_same_work($o['en'], $en)) { $j = $oi; break; }
        if ($j === null) { $out[] = $g; continue; }
        $out[$j]['members'] = array_values(array_unique(array_merge($out[$j]['members'], $g['members'])));
        if ($out[$j]['orig'] === '' && $orig !== '') { $out[$j]['orig'] = $orig; $out[$j]['display'] = $g['display']; }
        if ($out[$j]['year'] === '' && ($g['year'] ?? '') !== '') $out[$j]['year'] = $g['year'];
    }
    return $out;
}

function wr_lang_key($t) { return md5(mb_strtolower(trim((string) $t), 'UTF-8')); }

/**
 * Kuralla dili belirlenemeyen başlıklar için TEK DeepSeek çağrısı: "her başlık
 * hangi dilde?" (bibliyografik yorum yok, yalnız dil tanıma). Başlık başına
 * önbelleklenir; sonuç wr_translation_lang tarafından kullanılır.
 */
function wr_prime_langs(array $titles) {
    if (!isset($GLOBALS['wr_lang_map'])) $GLOBALS['wr_lang_map'] = [];
    $map = &$GLOBALS['wr_lang_map'];
    $dir = __DIR__ . '/cache/clean';
    $file = $dir . '/langid.json';
    static $disk = null;
    if ($disk === null) { $disk = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : []; }
    $ask = [];
    foreach ($titles as $t) {
        $t = trim((string) $t); if ($t === '') continue;
        $k = wr_lang_key($t);
        if (isset($map[$k])) continue;
        if (isset($disk[$k])) { $map[$k] = $disk[$k]; continue; }
        if (wr_script_langs($t) !== null || wr_guess_langs($t)) continue;   // kural zaten biliyor
        $w = preg_split('/[^\p{L}\']+/u', mb_strtolower($t, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
        if (array_intersect($w, ['the','of','and','in','to','with','for','from','on','an','a','is','at','by','my','how','what'])) { $map[$k] = 'english'; continue; }
        $ask[$k] = $t;
    }
    if (!$ask || !defined('DEEPSEEK_API_URL') || !defined('DEEPSEEK_KEY') || !DEEPSEEK_KEY) return;
    $ask = array_slice($ask, 0, 150, true);
    $lines = ''; $keys = array_keys($ask);
    foreach (array_values($ask) as $i => $t) $lines .= ($i + 1) . '. ' . mb_substr($t, 0, 160) . "\n";
    $ch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => (defined('DEEPSEEK_MODEL') && DEEPSEEK_MODEL && DEEPSEEK_MODEL !== 'deepseek-v4-flash') ? DEEPSEEK_MODEL : 'deepseek-chat',
            'max_tokens' => 3000, 'temperature' => 0,
            'response_format' => ['type' => 'json_object'],
            'messages' => [['role' => 'user', 'content' =>
                "For each numbered book title, name the LANGUAGE the title text is written in (e.g. English, Spanish, Turkish, Croatian, German, Latin). "
                . "Judge only the words, not the book. A title that is just a proper name, or that reads as normal English, is English. If you cannot tell, say Unknown.\n"
                . "Return ONLY JSON: {\"1\":\"Spanish\",\"2\":\"English\"}\n\n" . $lines]],
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200 || !$r) return;
    $d = json_decode($r, true);
    $j = json_decode((string) ($d['choices'][0]['message']['content'] ?? ''), true);
    if (!is_array($j)) return;
    foreach ($keys as $i => $k) {
        $l = strtolower(trim((string) ($j[(string) ($i + 1)] ?? '')));
        if ($l === '') continue;
        $map[$k] = $l; $disk[$k] = $l;
    }
    if (count($disk) > 50000) $disk = array_slice($disk, -40000, null, true);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($file, json_encode($disk, JSON_UNESCAPED_UNICODE), LOCK_EX);
}
