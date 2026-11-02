<?php
/**
 * _clean2.php — Yazar listesi temizleme (2. nesil).
 *
 * NEDEN YENİDEN: Eski motor yapay zekâya "bu listeyi grupla" diyordu; model aynı
 * eserin Almanca/İngilizce/Portekizce baskılarını ayrı eser sanabiliyor, çeviri ve
 * yanlış atıfları geçirebiliyordu. Wikidata eşleştirmesi de gevşekti ("Critique of
 * Pure Reason" ile "Critique of Practical Reason"ı aynı sayabiliyordu).
 *
 * YENİ AKIŞ — karar kodda, yapay zekâ yalnız "hangisi" sorusunu cevaplar:
 *   1) KURAL ELEMESİ (bedava): yazar adı, alıntı derlemesi, külliyat, yazar HAKKINDA kitap.
 *   2) KATALOG (Wikidata, bedava): yazarın eserleri + her eserin TÜM dillerdeki adları ve
 *      takma adları + eserin dili. Yazarın yazdığı diller eserlerin dilinden çıkarılır.
 *   3) SIKI EŞLEŞTİRME (kod): başlık, bir eserin herhangi bir dildeki adıyla birebir
 *      (tanımlık/alt başlık/noktalama farkı hariç) aynıysa o esere bağlanır.
 *   4) YAPAY ZEKÂ (DeepSeek, düşünerek, tek çağrı): eşleşmeyen başlıkların her biri için
 *      SEÇMELİ karar — katalogdaki hangi eser? / katalogda olmayan gerçek eser mi? /
 *      yazara ait değil mi? Model liste ÜRETMEZ, yalnız verilen başlıkları sınıflar.
 *   5) KOD KURALLARI (yapay zekâdan bağımsız, zorunlu):
 *      - Aynı esere bağlanan her baskı/çeviri TEK satır olur.
 *      - Katalogda olmayan bir başlık, yazarın yazdığı diller ve İngilizce dışında
 *        bir dildeyse ÇEVİRİDİR → elenir (asıl eser listede yoksa da).
 *      - Aynı eserin yazım farkları (tanımlık, alt başlık, boşluk) birleşir.
 *
 * Dönüş biçimi _clean-lib.php ile aynı:
 *   ['ok','groups'=>[['en','orig','display','year','members'=>int[]]],'not_by_author'=>[['n','reason']],'wrote_in','error','engine']
 */

require_once __DIR__ . '/_wikidata.php';
require_once __DIR__ . '/_work-rules.php';

if (!function_exists('cl2_clean_author')) {

function array_is_list_compat($a) { if (!is_array($a)) return false; $i = 0; foreach ($a as $k => $_) { if ($k !== $i++) return false; } return true; }

/* Eşleştirme anahtarı: küçük harf, aksansız, parantez ve alt başlık yok, baştaki
   tanımlık (birçok dilde) yok, yalnız harf/rakam, boşluksuz. */
function cl2_key($s) {
    $s = mb_strtolower(trim((string) $s), 'UTF-8');
    $s = preg_replace('/\s*[\(\[\（][^\)\]\）]*[\)\]\）]\s*/u', ' ', $s);
    $s = preg_replace('/\s*(?::|;|\s[–—-]\s).*$/u', '', $s);
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false && trim($t) !== '' && preg_match('/\p{Latin}/u', $s)) $s = $t;
    $s = preg_replace("/^(the|a|an|le|la|les|l'|der|die|das|den|dem|des|ein|eine|el|los|las|il|lo|gli|un|una|uno|une|de|het|een|o|os|as)\s+/u", '', trim($s));
    $s = preg_replace('/[^\p{L}\p{N}]+/u', '', $s);
    return (string) $s;
}

/* Wikidata: yazarın eserleri, tüm dillerdeki ad + takma adlar, eser dili. */
function cl2_wd_works($qid) {
    if (!preg_match('/^Q\d+$/', (string) $qid)) return null;
    $sparql = <<<SPARQL
SELECT ?work (SAMPLE(?enx) AS ?en) (SAMPLE(?origx) AS ?orig) (MIN(?datex) AS ?date)
       (GROUP_CONCAT(DISTINCT ?anyl; separator=" || ") AS ?labels)
       (GROUP_CONCAT(DISTINCT ?altl; separator=" || ") AS ?alts)
       (GROUP_CONCAT(DISTINCT ?langl; separator="|") AS ?langs) WHERE {
  { ?work wdt:P50 wd:{$qid} . } UNION { wd:{$qid} wdt:P800 ?work . }
  FILTER NOT EXISTS { ?work wdt:P31 wd:Q13442814. }
  FILTER NOT EXISTS { ?work wdt:P31 wd:Q191067. }
  FILTER NOT EXISTS { ?work wdt:P31 wd:Q482994. }
  FILTER NOT EXISTS { ?work wdt:P31 wd:Q3331189. }
  OPTIONAL { ?work rdfs:label ?enx . FILTER(LANG(?enx) = "en") }
  OPTIONAL { ?work wdt:P1476 ?origx . }
  OPTIONAL { ?work wdt:P577 ?datex . }
  OPTIONAL { ?work rdfs:label ?anyl . }
  OPTIONAL { ?work skos:altLabel ?altl . }
  OPTIONAL { ?work wdt:P407 ?lang . ?lang rdfs:label ?langl . FILTER(LANG(?langl) = "en") }
}
GROUP BY ?work
LIMIT 600
SPARQL;
    $data = wd_get('https://query.wikidata.org/sparql?' . http_build_query(['query' => $sparql, 'format' => 'json']),
                   ['Accept: application/sparql-results+json']);
    if (!$data) return null;
    $out = [];
    foreach ($data['results']['bindings'] ?? [] as $b) {
        $en   = trim($b['en']['value'] ?? '');
        $orig = trim($b['orig']['value'] ?? '');
        if ($en === '' && $orig === '') continue;
        if (preg_match('/^Q\d+$/', $en)) continue;   // etiketsiz öğe
        $names = array_filter(array_merge([$en, $orig],
            explode(' || ', $b['labels']['value'] ?? ''), explode(' || ', $b['alts']['value'] ?? '')), 'strlen');
        $keys = [];
        foreach ($names as $n) { $k = cl2_key($n); if (mb_strlen($k) >= 3) $keys[$k] = true; }
        if (!$keys) continue;
        $out[] = [
            'en' => $en !== '' ? $en : $orig, 'orig' => $orig, 'year' => wd_year($b['date']['value'] ?? ''),
            'keys' => array_keys($keys), 'names' => array_values(array_unique($names)),
            'langs' => array_values(array_filter(explode('|', $b['langs']['value'] ?? ''))),
        ];
    }
    return $out;
}

/* Yazarın yazdığı diller (Wikidata): önce eserlerin dili (P407), yoksa "yazdığı dil"
   (P6886), o da yoksa "konuştuğu/yazdığı diller" (P1412). */
function cl2_wd_author_langs($qid, $works) {
    $cnt = [];
    foreach ($works as $w) foreach ($w['langs'] as $l) $cnt[$l] = ($cnt[$l] ?? 0) + 1;
    arsort($cnt);
    // Eserlerin dilleri: en az 2 eserde geçen ya da en sık dil.
    $from_works = array_keys(array_filter($cnt, fn($c) => $c >= 2));
    if (!$from_works && $cnt) $from_works = [array_key_first($cnt)];
    if (count($from_works) >= 1 && array_sum($cnt) >= 3) return $from_works;
    $q = "SELECT ?p ?l WHERE { VALUES ?p { wdt:P6886 wdt:P1412 } wd:{$qid} ?p ?x . ?x rdfs:label ?l . FILTER(LANG(?l)=\"en\") }";
    $d = wd_get('https://query.wikidata.org/sparql?' . http_build_query(['query' => $q, 'format' => 'json']),
                ['Accept: application/sparql-results+json']);
    $p6886 = []; $p1412 = [];
    foreach ($d['results']['bindings'] ?? [] as $b) {
        $l = $b['l']['value'] ?? ''; if ($l === '') continue;
        if (strpos($b['p']['value'] ?? '', 'P6886') !== false) $p6886[] = $l; else $p1412[] = $l;
    }
    if ($p6886) return array_values(array_unique($p6886));
    if ($p1412 && count($p1412) <= 3) return array_values(array_unique($p1412));   // çok dilli liste güvenilmez
    return $from_works;
}

/* Bedava kural elemesi. */
function cl2_rule_drop($t, $author) {
    $tl = mb_strtolower(trim((string) $t), 'UTF-8');
    $an = mb_strtolower(trim((string) $author), 'UTF-8');
    if ($tl === '') return 'boş başlık';
    $ak = cl2_key($author);
    if ($an !== '' && preg_replace('/[^\p{L}\p{N}]+/u', '', $tl) === $ak) return 'başlık = yazar adı';
    if ($an !== '' && $ak !== '' && cl2_key($t) === $ak) return 'yazar hakkında ikincil kitap';   // Ad Soyad: alt başlık
    if (preg_match('/\b(quotes?|quotations?|sayings|aphorisms of|words of wisdom|wisdom of)\b/u', $tl)) return 'alıntı/özlü söz derlemesi';
    if (preg_match('/\b(complete|collected|selected)\s+(works|writings|papers|essays|poems)\b|\bomnibus\b|\bgesammelte werke\b|\bsämtliche werke\b|\bobras completas\b|\boeuvres complètes\b|\bopere complete\b/u', $tl)) return 'yayıncı külliyatı (tekil eser değil)';
    $parts = preg_split('/\s+/', $an); $sur = (string) end($parts);
    if (mb_strlen($sur) >= 4 && preg_match('/\b(biography|a life|his life|life of|the philosophy of|the thought of|introduction to|a companion to|companion to|guide to|reading|understanding|critique of|studies on|study of|essays on)\b.*\b' . preg_quote($sur, '/') . '\b/u', $tl)) {
        return 'yazar hakkında ikincil kitap';
    }
    if (mb_strlen($sur) >= 4 && preg_match('/\b' . preg_quote($sur, '/') . '\b.*\b(a biography|biography|a life|his life and|her life and|a critical study|a study|an introduction)\b/u', $tl)) {
        return 'yazar hakkında ikincil kitap';
    }
    return '';
}

/* DeepSeek (düşünerek) — eşleşmeyen başlıkları SINIFLA. */
function cl2_ai_classify($author, array $wrote_in, array $works, array $unmapped, &$err = '', $think = true) {
    if (!$unmapped) return [];
    $cat = '';
    foreach ($works as $wi => $w) {
        $cat .= 'W' . ($wi + 1) . ' | ' . mb_substr($w['en'], 0, 120)
              . ($w['orig'] !== '' && $w['orig'] !== $w['en'] ? ' | original: ' . mb_substr($w['orig'], 0, 120) : '')
              . ($w['year'] !== '' ? ' | ' . $w['year'] : '') . "\n";
        if ($wi >= 400) break;
    }
    $lst = '';
    foreach ($unmapped as $n => $t) $lst .= ($n + 1) . '. ' . mb_substr($t, 0, 180) . "\n";
    $prompt = "You are a meticulous bibliographer. AUTHOR: {$author}\n"
        . "The author wrote their works in: " . ($wrote_in ? implode(', ', $wrote_in) : 'unknown — infer it') . ".\n\n"
        . ($cat !== '' ? "CATALOG of the author's real works (from Wikidata):\n{$cat}\n" : "No catalog is available for this author.\n\n")
        . "LISTED TITLES (from a messy bookstore list; many are translations, other editions, duplicates or wrong attributions):\n{$lst}\n"
        . "For EVERY listed title decide exactly ONE of:\n"
        . "  A) It is an edition or TRANSLATION (in ANY language) of a catalog work → {\"n\":1,\"work\":\"W3\"}\n"
        . "  B) It is a genuine work BY THIS AUTHOR that is not in the catalog → {\"n\":2,\"new\":\"established English title (or faithful translation)\",\"orig\":\"title in the language the author wrote it, or empty if written in English\",\"lang\":\"language of the LISTED title\",\"group\":\"g1\"}. Give the same group id to listed titles that are the same work.\n"
        . "  C) Drop → {\"n\":3,\"drop\":\"not_by_author|about_author|anthology|not_a_book|unidentified\",\"why\":\"short reason\",\"real_author\":\"for not_by_author: the actual author's full name if you are sure, else empty\"}\n"
        . "RULES: Think about each title's language and meaning. A title in Portuguese/Spanish/Turkish/etc. of a book the author wrote in German is a TRANSLATION — map it to the catalog work (A) if it is one; never treat a translation as a new work. Use 'not_by_author' when the book is really by someone else (misattribution) — be strict. Use 'unidentified' if you cannot tell what work it is. Do NOT invent works.\n"
        . "Return ONLY JSON: {\"items\":[...]}";
    if (!defined('DEEPSEEK_KEY') || !DEEPSEEK_KEY) { $err = 'DeepSeek anahtarı yok'; return null; }
    $model = (defined('DEEPSEEK_MODEL') && DEEPSEEK_MODEL && !in_array(DEEPSEEK_MODEL, ['deepseek-chat', 'deepseek-reasoner'], true)) ? DEEPSEEK_MODEL : 'deepseek-flash';
    $call = function ($think) use ($model, $prompt, &$code) {
        $body = ['model' => $model, 'max_tokens' => 24000, 'response_format' => ['type' => 'json_object'],
                 'thinking' => ['type' => $think ? 'enabled' : 'disabled'],   // düşünerek: yavaş ama dikkatli
                 'messages' => [['role' => 'user', 'content' => $prompt]]];
        $ch = curl_init(DEEPSEEK_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 300, CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        ]);
        $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return $r;
    };
    $code = 0;
    $r = $call($think);
    if ($code === 400 && $think) $r = $call(false);   // düşünme + JSON birlikte reddedilirse
    if ($code !== 200 || !$r) { $err = "DeepSeek HTTP $code"; return null; }
    $d = json_decode($r, true);
    $txt = (string) ($d['choices'][0]['message']['content'] ?? '');
    $txt = preg_replace('/```json|```/i', '', $txt);
    $s = strpos($txt, '{'); $e = strrpos($txt, '}');
    $j = ($s !== false && $e > $s) ? json_decode(substr($txt, $s, $e - $s + 1), true) : null;
    if (!is_array($j)) { $err = 'DeepSeek JSON çözümlenemedi'; return null; }
    // Model farklı biçimde dönebiliyor: {"items":[...]}, {"items":{"1":{...}}}, [...] ya da {"1":{...}}.
    $list = $j['items'] ?? $j['results'] ?? $j['titles'] ?? $j;
    $out = [];
    foreach ((array) $list as $k => $it) {
        if (!is_array($it)) continue;
        $num = $it['n'] ?? $it['id'] ?? $it['index'] ?? $it['number'] ?? (is_numeric($k) && !array_is_list_compat($list) ? $k : null);
        if ($num === null && array_is_list_compat($list)) $num = $k + 1;   // sırayla verilmişse
        $n = (int) preg_replace('/\D/', '', (string) $num) - 1;
        if ($n >= 0 && $n < count($unmapped) && !isset($out[$n])) $out[$n] = $it;
    }
    return $out;
}

/**
 * @param string   $author
 * @param string[] $titles 0-tabanlı
 */
function cl2_clean_author($author, array $titles) {
    $author = trim((string) $author);
    $titles = array_values(array_map('strval', $titles));
    $n = count($titles);
    $res = ['ok' => true, 'groups' => [], 'not_by_author' => [], 'wrote_in' => [], 'error' => '', 'engine' => 'clean2'];
    if ($n === 0) return $res;

    // Önbellek (yazar + liste).
    $nt = array_map('cl2_key', $titles); sort($nt);
    $cdir = __DIR__ . '/cache/clean';
    $cfile = $cdir . '/c2_' . md5('c2v2|' . mb_strtolower($author) . '|' . implode("\n", $nt)) . '.json';
    if (is_file($cfile)) { $h = json_decode((string) file_get_contents($cfile), true); if (is_array($h) && !empty($h['ok'])) return $h; }

    $flags = [];      // ix => reason
    $assign = [];     // ix => work index (katalog)
    // 1) Kural elemesi
    foreach ($titles as $i => $t) { $why = cl2_rule_drop($t, $author); if ($why !== '') $flags[$i] = $why; }

    // 2) Katalog
    $qid = $author !== '' ? wd_find_entity($author) : null;
    $works = $qid ? (cl2_wd_works($qid) ?: []) : [];
    $wrote_in = ($qid && $works) ? cl2_wd_author_langs($qid, $works) : [];

    // 3) Sıkı eşleştirme
    $key2w = [];
    foreach ($works as $wi => $w) foreach ($w['keys'] as $k) $key2w[$k][$wi] = true;
    $unmapped = []; $umap = [];   // AI'a gidecekler: sıra → ix
    foreach ($titles as $i => $t) {
        if (isset($flags[$i])) continue;
        $k = cl2_key($t);
        $hits = ($k !== '' && isset($key2w[$k])) ? array_keys($key2w[$k]) : [];
        if (!$hits) {   // alt başlık-öneki kuralı ("Sleepwalkers" ~ "Sleepwalkers: A History…")
            foreach ($works as $wi => $w) { foreach ($w['names'] as $nm) { if (wr_same_work($nm, $t)) { $hits[] = $wi; break; } } }
            $hits = array_values(array_unique($hits));
        }
        if (count($hits) === 1) { $assign[$i] = $hits[0]; continue; }
        $umap[] = $i; $unmapped[] = $t;
    }

    // 4) Yapay zekâ — yalnız eşleşmeyenler
    // Uzun listeler 100'lük parçalar hâlinde (düşünme + JSON çıktısı bütçeye sığsın).
    $ai_err = ''; $ai = [];
    foreach (array_chunk($unmapped, 40, true) as $ci => $chunk) {
        $part = cl2_ai_classify($author, $wrote_in, $works, array_values($chunk), $ai_err);
        if ($part === null) return ['ok' => false, 'groups' => [], 'not_by_author' => [], 'wrote_in' => $wrote_in, 'error' => $ai_err, 'engine' => 'clean2'];
        $keys = array_keys($chunk);
        foreach ($part as $k => $it) {
            if (isset($it['group'])) $it['group'] = 'c' . $ci . '_' . $it['group'];   // parçalar arası çakışmasın
            $ai[$keys[$k]] = $it;
        }
        // Model bazı başlıkları atladıysa: yalnız onlar için bir kez daha sor (düşünmesiz, hızlı).
        $miss = array_values(array_filter($keys, fn($p) => !isset($ai[$p])));
        if ($miss) {
            $sub = []; foreach ($miss as $p) $sub[] = $unmapped[$p];
            $e2 = '';
            $part2 = cl2_ai_classify($author, $wrote_in, $works, $sub, $e2, false);
            foreach ((array) $part2 as $k => $it) {
                if (isset($it['group'])) $it['group'] = 'r' . $ci . '_' . $it['group'];
                $ai[$miss[$k]] = $it;
            }
        }
    }

    if (!$wrote_in) {   // katalog dili yoksa: AI'nin "orig" dillerinden + yazarın dil tahmini
        $wrote_in = wr_author_langs($author);
    }

    // 5) Kod kuralları
    $newg = [];   // group id => ['en','orig','members','langs']
    foreach ($umap as $pos => $i) {
        $it = $ai[$pos] ?? null;
        if (!$it) {   // model yine karar vermediyse ELEME — başlığı koru (yalnız açık çeviriyse ele)
            if (wr_translation_lang($titles[$i], $wrote_in) !== '') { $flags[$i] = 'çeviri baskısı (karar verilemedi, yazarın dilinde değil)'; continue; }
            $it = ['new' => $titles[$i], 'orig' => '', 'lang' => '', 'group' => 'k' . $i];
        }
        if (!empty($it['work']) && preg_match('/^W(\d+)$/', (string) $it['work'], $m) && isset($works[(int) $m[1] - 1])) {
            $assign[$i] = (int) $m[1] - 1; continue;
        }
        if (!empty($it['drop'])) {
            if ($it['drop'] === 'unidentified' && wr_translation_lang($titles[$i], $wrote_in) === '') {
                // Tanınamadı diye eleme yok (fazla siliniyordu) — yazarın dilinde/İngilizce ise koru.
                $it = ['new' => $titles[$i], 'orig' => '', 'lang' => '', 'group' => 'u' . $i];
                goto cl2_new;
            }
            $map = ['not_by_author' => 'yazara ait değil (yanlış atıf)', 'about_author' => 'yazar hakkında ikincil kitap',
                    'anthology' => 'antoloji/derleme', 'not_a_book' => 'kitap değil', 'unidentified' => 'tanımlanamadı'];
            $real = trim((string) ($it['real_author'] ?? ''));
            $flags[$i] = ($map[$it['drop']] ?? 'elendi')
                . ($real !== '' && $it['drop'] === 'not_by_author' ? ' — gerçek yazar: ' . mb_substr($real, 0, 80) : '')
                . (!empty($it['why']) ? ' — ' . mb_substr((string) $it['why'], 0, 120) : '');
            continue;
        }
        cl2_new:
        $en = trim((string) ($it['new'] ?? ''));
        if ($en === '') { $flags[$i] = 'tanımlanamadı'; continue; }
        // KURAL: listelenen başlık yazarın dilleri + İngilizce dışında → ÇEVİRİ.
        $lang = strtolower(trim((string) ($it['lang'] ?? '')));
        $tr = wr_translation_lang($titles[$i], $wrote_in);
        $lang_ok = ($lang === '' || $lang === 'english');
        foreach ($wrote_in as $w) if ($lang !== '' && (strpos(strtolower($w), $lang) !== false || strpos($lang, strtolower($w)) !== false)) $lang_ok = true;
        $is_translation = ($tr !== '') || (!$lang_ok && $wrote_in);
        $g = (string) ($it['group'] ?? ('i' . $i));
        if (!isset($newg[$g])) $newg[$g] = ['en' => $en, 'orig' => trim((string) ($it['orig'] ?? '')), 'members' => [], 'trans' => [], 'year' => ''];
        if ($is_translation) $newg[$g]['trans'][] = $i; else $newg[$g]['members'][] = $i;
    }

    // Katalog grupları
    $groups = [];
    $byw = [];
    foreach ($assign as $i => $wi) $byw[$wi][] = $i;
    foreach ($byw as $wi => $mem) {
        $w = $works[$wi];
        $groups[] = ['en' => $w['en'], 'orig' => $w['orig'], 'year' => $w['year'], 'members' => $mem];
    }
    // Yeni (katalog dışı) gruplar: yalnız çevirilerden oluşan grup → elenir.
    foreach ($newg as $g) {
        if (!$g['members']) {
            foreach ($g['trans'] as $i) $flags[$i] = 'çeviri baskısı — yazar ' . ($wrote_in ? implode('/', $wrote_in) : '?') . ' yazdı, asıl eser tanımlanamadı';
            continue;
        }
        $groups[] = ['en' => $g['en'], 'orig' => $g['orig'], 'year' => '', 'members' => array_merge($g['members'], $g['trans'])];
    }

    // Görünen ad + orig dil denetimi + son tekrar birleştirmesi (wr_enforce_groups).
    foreach ($groups as &$g) {
        if ($g['orig'] !== '' && wr_translation_lang($g['orig'], $wrote_in) !== '') $g['orig'] = '';
        $g['display'] = ($g['orig'] !== '' && cl2_key($g['orig']) !== cl2_key($g['en'])) ? "{$g['en']} ({$g['orig']})" : $g['en'];
    }
    unset($g);
    $groups = wr_enforce_groups($groups, $flags, $wrote_in ?: ['English'], $author);

    $res['groups'] = $groups;
    foreach ($flags as $i => $why) $res['not_by_author'][] = ['n' => $i, 'reason' => $why];
    $res['wrote_in'] = $wrote_in;
    $res['catalog'] = count($works);
    if (!is_dir($cdir)) @mkdir($cdir, 0775, true);
    @file_put_contents($cfile, json_encode($res, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $res;
}

}
