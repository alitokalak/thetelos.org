<?php
/**
 * _wikidata.php — Yazarın KANONİK eser listesini Wikidata'dan çeker.
 *
 * Amaç: liste temizlemede "gerçekleri" LLM'in hafızasına sordurmayı bırakmak.
 * Wikidata otoriter, ücretsiz ve DETERMİNİSTİK bir kaynak:
 *   - ?work wdt:P50 wd:<yazar>  → eser gerçekten o yazara ait (ikincil literatür değil)
 *   - İngilizce etiket (rdfs:label @en) → İngilizce ad
 *   - P1476 → orijinal ad
 *   - P577 → ilk yayın yılı
 *   - DISTINCT ?work → her eser TEK kez (çeviriler ayrı gelmez → birleştirme hazır)
 *
 * Kullanım:
 *   require_once __DIR__ . '/_wikidata.php';
 *   $res = wd_clean_author($author, $items);   // $items: [['title','year','cover','merged','all'=>[...]]]
 *   // $res = ['ok'=>bool, 'items'=>[...], 'removed'=>[...], 'source'=>'wikidata', 'wd_count'=>int, 'matched'=>int]
 */

if (!function_exists('wd_get')) {
    function wd_get($url, $headers = []) {
        $def = ['Accept: application/json', 'User-Agent: thetelos.org/1.0 (list-clean; contact: info@thetelos.org)'];
        // Geçici hata (429/5xx/ağ) → kısa bekleyip bir kez daha dene.
        for ($try = 0; $try < 2; $try++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 25,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER     => array_merge($def, $headers),
            ]);
            $r = curl_exec($ch);
            $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($c === 200 && $r) return json_decode($r, true);
            if ($c !== 429 && $c < 500 && $c !== 0) return null;   // kalıcı hata → tekrar etme
            if ($try === 0) usleep(1200000);                        // 1.2 sn bekle, tekrar dene
        }
        return null;
    }
}

/* ISO tarihten 4 haneli yıl (negatif/eski tarihler dahil). */
function wd_year($iso) {
    if (!is_string($iso) || $iso === '') return '';
    if (preg_match('/(-?\d{1,4})-\d{2}-\d{2}/', $iso, $m)) {
        $y = (int)$m[1];
        return ($y > 0 && $y <= (int)date('Y') + 1) ? (string)$y : '';
    }
    return '';
}

/* Başlık → anlamlı token'lar (dedup/eşleştirme için). Aksan sadeleştirilir,
   ortak yayın/derleme kelimeleri (stopword) atılır. */
function wd_tokens($s) {
    static $stop = null;
    if ($stop === null) {
        $stop = array_flip(explode(' ',
            'the a an of on to and or by with in for from about into that this his her their les des una uno'
          . ' de la le el il lo un une du der die das zur zum von vom und e y a o'
          . ' complete works collected selected early late major minor new revised critical edition editions'
          . ' introduction guide study reader companion handbook anthology essays volume volumes book books'
        ));
    }
    $s = mb_strtolower((string)$s, 'UTF-8');
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false && $t !== '') $s = $t;
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    $out = [];
    foreach (explode(' ', trim($s)) as $w) {
        if (strlen($w) >= 4 && !isset($stop[$w])) $out[] = $w;
    }
    return $out;
}

/* Tam başlığı normalize et (aksan sadeleş, küçük harf, alfanümerik). Kısa/
   stopword-ağırlıklı başlıklar (ör. "Ich und Du") token üretmez → bunları
   normalize tam-metin karşılaştırmasıyla yakalarız. */
function wd_norm($s) {
    $s = mb_strtolower((string)$s, 'UTF-8');
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false && $t !== '') $s = $t;
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

/* İki token dizisi aynı eseri mi gösteriyor? (öneke dayalı gevşek eşleşme) */
function wd_tok_match($a, $b) {
    $n = min(strlen($a), strlen($b));
    if ($n < 5) return $a === $b;
    $k = 0; while ($k < $n && $a[$k] === $b[$k]) $k++;
    return $k >= 5;
}
function wd_titles_same($ta, $tb) {
    if (!$ta || !$tb) return false;
    $small = count($ta) <= count($tb) ? $ta : $tb;
    $big   = count($ta) <= count($tb) ? $tb : $ta;
    $m = 0;
    foreach ($small as $x) { foreach ($big as $y) { if (wd_tok_match($x, $y)) { $m++; break; } } }
    $min = count($small);
    return $m >= 2 || ($m >= 1 && $m === $min);
}

/* Yazar adından Wikidata entity (Q-id) bul. İnsan/yazar açıklamasını tercih et. */
function wd_find_entity($author) {
    $url = 'https://www.wikidata.org/w/api.php?' . http_build_query([
        'action'   => 'wbsearchentities',
        'search'   => $author,
        'language' => 'en',
        'type'     => 'item',
        'limit'    => 8,
        'format'   => 'json',
    ]);
    $data = wd_get($url);
    if (!$data) return null;
    $first = null;
    foreach ($data['search'] ?? [] as $r) {
        if ($first === null) $first = $r['id'] ?? null;
        $desc = mb_strtolower($r['description'] ?? '');
        if (preg_match('/philosopher|writer|author|poet|novelist|playwright|historian|theologian|mathematician|scientist|jurist|economist|psychologist|sociologist|scholar|thinker|essayist|monk|mystic|priest|rabbi/', $desc)) {
            return $r['id'] ?? null;
        }
    }
    return $first;
}

/* Yazarın kanonik eserleri: [['en'=>..,'orig'=>..,'year'=>..,'tokens'=>[..]]] */
function wd_author_works($qid, $limit = 300) {
    if (!preg_match('/^Q\d+$/', (string)$qid)) return [];
    // Eser türleri: kitap, edebi eser, yazılı eser, roman, deneme, oyun, şiir,
    // risale, kısa öykü... P800 (notable works) da eklenir. Makale/bölüm hariç.
    // GROUP_CONCAT ile eserin TÜM dillerdeki etiketlerini topla → çeviri
    // başlıkları (ör. Türkçe "Ben ve Sen") kanonik esere eşleşebilsin. Gösterimde
    // yine yalnız İngilizce (en) + orijinal (P1476) kullanılır.
    $sparql = <<<SPARQL
SELECT ?work (SAMPLE(?enx) AS ?en) (SAMPLE(?origx) AS ?orig) (MIN(?datex) AS ?date)
       (GROUP_CONCAT(DISTINCT ?anyl; separator=" || ") AS ?labels) WHERE {
  {
    ?work wdt:P50 wd:{$qid} .
    ?work wdt:P31 ?type .
    FILTER(?type IN (
      wd:Q571, wd:Q7725634, wd:Q47461344, wd:Q8261, wd:Q49848,
      wd:Q162606, wd:Q36279, wd:Q25379, wd:Q11826511, wd:Q17537576,
      wd:Q386724, wd:Q1004, wd:Q5185279, wd:Q1279564
    ))
  } UNION {
    wd:{$qid} wdt:P800 ?work .
  }
  FILTER NOT EXISTS { ?work wdt:P31 wd:Q13442814. }
  FILTER NOT EXISTS { ?work wdt:P31 wd:Q191067. }
  FILTER NOT EXISTS { ?work wdt:P31 wd:Q482994. }
  OPTIONAL { ?work rdfs:label ?enx . FILTER(LANG(?enx) = "en") }
  OPTIONAL { ?work wdt:P1476 ?origx . }
  OPTIONAL { ?work wdt:P577 ?datex . }
  OPTIONAL { ?work rdfs:label ?anyl . }
}
GROUP BY ?work
LIMIT {$limit}
SPARQL;
    $url  = 'https://query.wikidata.org/sparql?' . http_build_query(['query' => $sparql, 'format' => 'json']);
    $data = wd_get($url, ['Accept: application/sparql-results+json']);
    if (!$data) return null;   // null = sorgu başarısız (fallback gerekir)

    $out = [];
    foreach ($data['results']['bindings'] ?? [] as $b) {
        $wk = $b['work']['value'] ?? ''; if ($wk === '') continue;
        $en     = trim($b['en']['value']     ?? '');
        $orig   = trim($b['orig']['value']   ?? '');
        $year   = wd_year($b['date']['value'] ?? '');
        $labels = array_filter(array_map('trim', explode(' || ', $b['labels']['value'] ?? '')));

        // Eşleştirme havuzu = en + orig + tüm dil etiketleri.
        $pool = array_merge([$en, $orig], $labels);
        $tokens = []; $norms = [];
        foreach ($pool as $p) {
            if ($p === '') continue;
            foreach (wd_tokens($p) as $tk) $tokens[$tk] = true;
            $nn = wd_norm($p); if ($nn !== '') $norms[$nn] = true;
        }
        if (empty($tokens) && empty($norms)) continue;   // eşleştirilemez
        $out[] = [
            'en'     => $en,
            'orig'   => $orig,
            'year'   => $year,
            'tokens' => array_keys($tokens),
            'norms'  => array_keys($norms),
        ];
    }
    return $out;
}

/* Kanonik eserin görünen adını kur: "İngilizce (Orijinal)". İngilizce yoksa
   orijinali göster (nadir). Orijinal İngilizceyle aynıysa parantez yok. */
function wd_display_title($w) {
    $en = trim((string)($w['en'] ?? ''));
    $orig = trim((string)($w['orig'] ?? ''));
    if ($en === '') return $orig;   // İngilizce etiket yok → en azından orijinal
    if ($orig === '' || mb_strtolower($orig) === mb_strtolower($en)) return $en;
    return "$en ($orig)";
}

/**
 * Yazarın kirli listesini Wikidata kanonik listesiyle temizle.
 * $items: kural katmanı sonrası aday liste (title, year, cover, merged, all[]).
 * Dönüş: ['ok','items','removed','source','wd_count','matched','error'].
 *   - Eşleşen kirli girişler kanonik "İngilizce (Orijinal)" satırında BİRLEŞİR.
 *   - Wikidata'da olmayan girişler ELENMEZ; olduğu gibi korunur (kitap kaybetme).
 *   - Wikidata liste ÜRETMEZ; yalnız senin listendeki başlıkları kanonikleştirir.
 */
function wd_clean_author($author, $items) {
    $qid = wd_find_entity($author);
    if (!$qid) return ['ok'=>false, 'error'=>'Wikidata: yazar bulunamadı', 'source'=>''];

    $works = wd_author_works($qid);
    if ($works === null) return ['ok'=>false, 'error'=>'Wikidata: sorgu başarısız', 'source'=>''];
    if (empty($works)) return ['ok'=>false, 'error'=>'Wikidata: bu yazara ait eser yok', 'source'=>'', 'wd_count'=>0];

    // Her kanonik esere düşen kirli girişleri topla.
    $bucket   = array_fill(0, count($works), null);   // wi => ['year','cover','merged']
    $leftover = [];                                    // eşleşmeyen kirli girişler
    $matched  = 0;

    foreach ($items as $it) {
        // Kirli girişin TÜM varyant başlıklarını dene (daha çok eşleşme).
        $variants = array_merge([$it['title']], (array)($it['all'] ?? []));
        $best = -1;
        foreach ($variants as $vt) {
            $vtok = wd_tokens($vt);
            $vn   = wd_norm($vt);
            foreach ($works as $wi => $w) {
                // (a) içerik-token eşleşmesi
                if (!empty($vtok) && wd_titles_same($vtok, $w['tokens'])) { $best = $wi; break 2; }
                // (b) normalize tam-metin: eşit ya da (>=6) biri diğerini içeriyor
                if ($vn !== '') foreach ($w['norms'] as $wn) {
                    if ($wn === $vn) { $best = $wi; break 3; }
                    if (strlen($vn) >= 6 && (str_contains($wn, $vn) || str_contains($vn, $wn))) { $best = $wi; break 3; }
                }
            }
        }
        if ($best < 0) { $leftover[] = $it; continue; }
        $matched++;
        if ($bucket[$best] === null) $bucket[$best] = ['year'=>'', 'cover'=>'', 'merged'=>0];
        $bucket[$best]['merged'] += (int)($it['merged'] ?? 1);
        if ($bucket[$best]['cover'] === '' && ($it['cover'] ?? '') !== '') $bucket[$best]['cover'] = $it['cover'];
        $iy = (string)($it['year'] ?? '');
        if (preg_match('/^\d{3,4}$/', $iy)) {
            if ($bucket[$best]['year'] === '' || (int)$iy < (int)$bucket[$best]['year']) $bucket[$best]['year'] = $iy;
        }
    }

    // Eşleşen kanonik eserleri çıktıya yaz.
    $out = [];
    foreach ($works as $wi => $w) {
        if ($bucket[$wi] === null) continue;   // katalogda bu eser yok → EKLEME (liste üretme)
        $b = $bucket[$wi];
        $year = ($w['year'] !== '') ? $w['year'] : $b['year'];   // Wikidata yılı öncelikli
        $out[] = [
            'title'  => wd_display_title($w),
            'author' => $author,
            'year'   => $year,
            'cover'  => $b['cover'],
            'merged' => max(1, (int)$b['merged']),
        ];
    }

    // Eşleşmeyenler: kaybetme. İkincil literatür şüphesi olanı Elenenler'e,
    // gerisini olduğu gibi koru.
    $removed = [];
    $alast = '';
    $ap = array_filter(explode(' ', mb_strtolower($author)));
    if ($ap) $alast = end($ap);
    foreach ($leftover as $it) {
        $t = (string)$it['title'];
        $is_secondary = ($alast !== '' && strlen($alast) >= 4
            && preg_match('/\b(of|on|to|about|and|by|life|thought|philosophy|companion|introduction|guide|study|reader)\b.*\b' . preg_quote($alast, '/') . '\b/iu', $t));
        if ($is_secondary) {
            $removed[] = ['title'=>$t, 'author'=>$author, 'year'=>$it['year'] ?? '', 'cover'=>$it['cover'] ?? '',
                          'reason'=>'Wikidata\'da yazarın eseri olarak bulunamadı; ikincil literatür olabilir'];
        } else {
            $out[] = ['title'=>$t, 'author'=>$author, 'year'=>$it['year'] ?? '', 'cover'=>$it['cover'] ?? '', 'merged'=>(int)($it['merged'] ?? 1)];
        }
    }

    return [
        'ok'       => true,
        'items'    => $out,
        'removed'  => $removed,
        'source'   => 'wikidata',
        'qid'      => $qid,
        'wd_count' => count($works),
        'matched'  => $matched,
    ];
}
