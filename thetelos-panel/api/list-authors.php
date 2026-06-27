<?php
/**
 * list-authors.php — Bir kategorinin en önemli yazarlarını WIKIDATA'dan üret.
 *
 * NEDEN WIKIDATA: Eskiden liste LLM ile üretiliyordu; sıralama her çağrıda
 * değiştiği için 0-50 ile 50-100 ÖRTÜŞÜYOR, adlar varyant geliyordu
 * (Avicenna / İbn-i Sina …) → yazar terimleri parçalanıyordu. Wikidata
 * deterministiktir: aynı sorgu hep aynı sırayı verir, ad kanoniktir.
 *
 * Sıralama ölçütü: sitelink sayısı (kişinin kaç dilde Wikipedia maddesi var)
 * = ünlülük/önem için iyi bir vekil. ORDER BY DESC(sitelinks) + LIMIT/OFFSET
 * → aralıklar asla örtüşmez.
 *
 * POST: category, count (vars. 40, max 50), offset
 * Dönüş: { ok, category, offset, count, authors:[{author, era, note}], source }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
@ini_set('display_errors', 0);
set_time_limit(120);

$category = trim($_POST['category'] ?? '');
$count    = max(5, min(50, (int)($_POST['count'] ?? 40)));
$offset   = max(0, (int)($_POST['offset'] ?? 0));
if ($category === '') { echo json_encode(['ok'=>false,'error'=>'Kategori zorunlu.']); exit; }

/* UA zorunlu: Wikidata/WDQS, User-Agent'sız istekleri 403'lüyor. */
function la_http($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: ThetelosBot/1.0 (https://thetelos.org; content builder)',
            'Accept: application/sparql-results+json, application/json',
        ],
    ]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($c >= 200 && $c < 300) ? (string)$r : '';
}

/* ISO tarihten yıl (MÖ negatif): "1788-..." → "1788", "-0384-..." → "384 BC" */
function la_year($iso) {
    if (preg_match('/^(-?)0*(\d{1,4})-/', (string)$iso, $m)) {
        return $m[1] === '-' ? ($m[2] . ' BC') : $m[2];
    }
    return '';
}

/* Kategori → Wikidata [property, QID].
   P106 = occupation (uğraş), P101 = field of work (çalışma alanı). */
function la_map_category($cat) {
    $c = strtolower(trim(preg_replace('/[\s_]+/', ' ', $cat)));
    $map = [
        'philosophy'             => ['P106','Q4964182'], // philosopher
        'history of philosophy'  => ['P106','Q4964182'],
        'ethics'                 => ['P101','Q9465'],
        'metaphysics'            => ['P101','Q9035'],
        'epistemology'           => ['P101','Q9471'],
        'logic'                  => ['P106','Q19350898'], // logician
        'aesthetics'             => ['P101','Q35986'],
        'political philosophy'   => ['P101','Q1064441'],
        'philosophy of religion' => ['P101','Q1417149'],
        'theology'               => ['P106','Q1234713'],  // theologian
        'religion'               => ['P106','Q1234713'],
        'literature'             => ['P106','Q36180'],     // writer
        'classic literature'     => ['P106','Q36180'],
        'world literature'       => ['P106','Q36180'],
        'fiction'                => ['P106','Q36180'],
        'novel'                  => ['P106','Q6625963'],   // novelist
        'poetry'                 => ['P106','Q49757'],     // poet
        'drama'                  => ['P106','Q214917'],    // playwright
        'psychology'             => ['P106','Q212980'],    // psychologist
        'sociology'              => ['P106','Q2306091'],   // sociologist
        'anthropology'           => ['P106','Q4773904'],   // anthropologist
        'economics'              => ['P106','Q188094'],    // economist
        'political science'      => ['P106','Q3400985'],   // political scientist
        'politics'               => ['P106','Q82955'],     // politician
        'history'                => ['P106','Q201788'],    // historian
        'world history'          => ['P106','Q201788'],
        'ancient history'        => ['P106','Q201788'],
        'medieval history'       => ['P106','Q201788'],
        'modern history'         => ['P106','Q201788'],
        'science'                => ['P106','Q901'],       // scientist
        'physics'                => ['P106','Q169470'],    // physicist
        'mathematics'            => ['P106','Q170790'],    // mathematician
        'biology'                => ['P106','Q864503'],    // biologist
        'chemistry'              => ['P106','Q593644'],    // chemist
        'astronomy'              => ['P106','Q11063'],     // astronomer
        'medicine'               => ['P106','Q39631'],     // physician
    ];
    return $map[$c] ?? null;
}

[$prop, $qid] = la_map_category($category) ?? [null, null];

/* Haritada yoksa: Wikidata'da ara → çalışma alanı (P101) olarak dene. */
if (!$qid) {
    $sr  = json_decode(la_http(
        'https://www.wikidata.org/w/api.php?action=wbsearchentities&format=json&language=en&type=item&limit=1&search='
        . urlencode($category)
    ), true);
    $qid  = $sr['search'][0]['id'] ?? null;
    $prop = 'P101';
    if (!$qid) { echo json_encode(['ok'=>false,'error'=>"Kategori Wikidata'da eşlenemedi: $category"]); exit; }
}

/* Deterministik: sitelink (önem) sırasına göre, sabit sayfalama. */
$sparql = "SELECT ?person ?personLabel ?personDescription ?sitelinks ?birth ?death WHERE {
  { SELECT ?person ?sitelinks WHERE {
      ?person wdt:P31 wd:Q5 .
      ?person wdt:$prop wd:$qid .
      ?person wikibase:sitelinks ?sitelinks .
      FILTER(?sitelinks >= 2)
    } ORDER BY DESC(?sitelinks) ?person LIMIT $count OFFSET $offset }
  OPTIONAL { ?person wdt:P569 ?birth. }
  OPTIONAL { ?person wdt:P570 ?death. }
  SERVICE wikibase:label { bd:serviceParam wikibase:language \"en,mul\". }
} ORDER BY DESC(?sitelinks) ?person";

$resp = la_http('https://query.wikidata.org/sparql?format=json&query=' . urlencode($sparql));
$data = json_decode($resp, true);
$rows = $data['results']['bindings'] ?? null;
if ($rows === null) {
    echo json_encode(['ok'=>false,'error'=>'Wikidata sorgusu başarısız (zaman aşımı/limit). Tekrar dene.']);
    exit;
}

$authors = []; $seen = [];
foreach ($rows as $r) {
    $uri = $r['person']['value'] ?? '';
    if ($uri === '' || isset($seen[$uri])) continue;   // birden çok doğum tarihi → tekrar satır
    $seen[$uri] = true;

    $name = trim($r['personLabel']['value'] ?? '');
    if ($name === '' || preg_match('/^Q\d+$/', $name)) continue;  // etiketsiz QID'leri at

    $by  = isset($r['birth']['value']) ? la_year($r['birth']['value']) : '';
    $dy  = isset($r['death']['value']) ? la_year($r['death']['value']) : '';
    $era = $by !== '' ? ($by . '–' . $dy) : ($dy !== '' ? ('–' . $dy) : '');

    $authors[] = [
        'author' => $name,
        'era'    => $era,
        'note'   => trim($r['personDescription']['value'] ?? ''),
    ];
}

echo json_encode([
    'ok'       => true,
    'category' => $category,
    'offset'   => $offset,
    'count'    => count($authors),
    'authors'  => $authors,
    'source'   => 'wikidata',
], JSON_UNESCAPED_UNICODE);
