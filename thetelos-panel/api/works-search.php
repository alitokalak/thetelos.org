<?php
/**
 * works-search.php — Yazar eserlerini Wikidata SPARQL ile çek, kapakları OpenLibrary'den al.
 * Wikidata'da yazar bulunamazsa OpenLibrary two-step yöntemine (author key) fallback yap.
 *
 * POST: author (yazar adı), limit (varsayılan 60)
 * Dönüş: { ok, works:[{title,year,cover,original}], source:"wikidata"|"openlibrary" }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
@ini_set('display_errors', 0);
set_time_limit(45);

$author = trim($_POST['author'] ?? '');
$limit  = max(10, min(200, (int)($_POST['limit'] ?? 60)));
if ($author === '') { echo json_encode(['ok'=>false,'error'=>'Yazar adı zorunlu.']); exit; }

/* ── HTTP GET yardımcısı ──────────────────────────────────── */
function ws_get($url, $headers = []) {
    $ch = curl_init($url);
    $default_headers = ['Accept: application/json', 'User-Agent: thetelos.org/1.0 (works-search)'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => array_merge($default_headers, $headers),
    ]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($c === 200 && $r) ? json_decode($r, true) : null;
}

/* ── Başlık token eşleştirme (dedup için) ────────────────── */
function ws_tokens($s) {
    static $stop = null;
    if ($stop === null) {
        $stop = array_flip(explode(' ',
            'the a an of on to and by with in for from about into that this his her their'
          . ' complete works collected selected early late major minor new revised critical'
          . ' introduction guide study reader companion handbook anthology essays'
        ));
    }
    $s = mb_strtolower($s, 'UTF-8');
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false && $t !== '') $s = $t;
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    $out = [];
    foreach (explode(' ', trim($s)) as $w) {
        if (strlen($w) >= 4 && !isset($stop[$w])) $out[] = $w;
    }
    return $out;
}
function ws_tok_match($a, $b) {
    $n = min(strlen($a), strlen($b));
    if ($n < 4) return $a === $b;
    $k = 0; while ($k < $n && $a[$k] === $b[$k]) $k++;
    return $k >= 5;
}
function ws_titles_same($ta, $tb) {
    if (!$ta || !$tb) return false;
    $small = count($ta) <= count($tb) ? $ta : $tb;
    $big   = count($ta) <= count($tb) ? $tb : $ta;
    $m = 0;
    foreach ($small as $x) { foreach ($big as $y) { if (ws_tok_match($x, $y)) { $m++; break; } } }
    $min = count($small);
    return $m >= 2 || ($m >= 1 && $m === $min);
}

/* ── Yıl çıkar ────────────────────────────────────────────── */
function ws_year($val) {
    if (!$val) return '';
    preg_match('/(\d{4})/', (string)$val, $m);
    return $m[1] ?? '';
}

/* ════════════════════════════════════════════════════════════
 * 1. WIKIDATA: yazar entity'sini bul
 * ════════════════════════════════════════════════════════════ */
function ws_wikidata_find_author($name) {
    $url = 'https://www.wikidata.org/w/api.php?' . http_build_query([
        'action'   => 'wbsearchentities',
        'search'   => $name,
        'type'     => 'item',
        'language' => 'en',
        'limit'    => 5,
        'format'   => 'json',
    ]);
    $data = ws_get($url);
    if (!$data) return null;
    foreach ($data['search'] ?? [] as $r) {
        $desc = mb_strtolower($r['description'] ?? '');
        // İnsan / yazar / filozoflar için açıklama genellikle "philosopher", "writer", "author", "poet", "novelist" içerir
        if (preg_match('/philosopher|writer|author|poet|novelist|playwright|historian|theologian|mathematician|scientist|jurist|economist|psychologist|sociologist|scholar|thinker/', $desc)) {
            return $r['id']; // örn. "Q9312"
        }
    }
    // Açıklamada eşleşme yoksa ilk sonucu dene
    return $data['search'][0]['id'] ?? null;
}

/* ════════════════════════════════════════════════════════════
 * 2. WIKIDATA: yazarın eserlerini + doğum/ölüm tarihlerini çek
 * ════════════════════════════════════════════════════════════ */
function ws_wikidata_works($entity_id, $limit) {
    // SPARQL: P50 = yazar, P31 = tür (kitap/eser), P577 = yayın tarihi, P1476 = orijinal başlık
    $sparql = <<<SPARQL
SELECT DISTINCT ?work ?workLabel ?origLabel ?date WHERE {
  ?work wdt:P50 wd:{$entity_id}.
  ?work wdt:P31 ?type.
  FILTER(?type IN (
    wd:Q571, wd:Q7725634, wd:Q49848, wd:Q8261,
    wd:Q162606, wd:Q36279, wd:Q25379, wd:Q11826511, wd:Q17537576
  ))
  FILTER NOT EXISTS { ?work wdt:P31 wd:Q482994. }
  FILTER NOT EXISTS { ?work wdt:P31 wd:Q5185279. }
  FILTER NOT EXISTS { ?work wdt:P31 wd:Q1931185. }
  OPTIONAL { ?work wdt:P577 ?date. }
  OPTIONAL { ?work wdt:P1476 ?origLabel. }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en,fr,de,it,es,la". }
}
ORDER BY ?date
LIMIT {$limit}
SPARQL;

    $url = 'https://query.wikidata.org/sparql?' . http_build_query(['query' => $sparql, 'format' => 'json']);
    $data = ws_get($url, ['Accept: application/sparql-results+json']);
    return $data['results']['bindings'] ?? [];
}

function ws_wikidata_author_dates($entity_id) {
    $sparql = <<<SPARQL
SELECT ?born ?died WHERE {
  OPTIONAL { wd:{$entity_id} wdt:P569 ?born. }
  OPTIONAL { wd:{$entity_id} wdt:P570 ?died. }
}
LIMIT 1
SPARQL;
    $url  = 'https://query.wikidata.org/sparql?' . http_build_query(['query' => $sparql, 'format' => 'json']);
    $data = ws_get($url, ['Accept: application/sparql-results+json']);
    $row  = $data['results']['bindings'][0] ?? [];
    return [
        ws_year($row['born']['value'] ?? ''),
        ws_year($row['died']['value'] ?? ''),
    ];
}

/* ════════════════════════════════════════════════════════════
 * 3. Google Books: yazar başına 1 toplu istek → cover map
 * ════════════════════════════════════════════════════════════ */
function ws_gb_covers_for_author($author) {
    $url  = 'https://www.googleapis.com/books/v1/volumes?' . http_build_query([
        'q'           => 'inauthor:"' . $author . '"',
        'maxResults'  => 40,
        'langRestrict'=> 'en',
        'printType'   => 'books',
        'fields'      => 'items(volumeInfo(title,imageLinks))',
        'key'         => GOOGLE_BOOKS_KEY,
    ]);
    $data = ws_get($url);
    $map  = [];
    foreach ($data['items'] ?? [] as $item) {
        $t = mb_strtolower(trim($item['volumeInfo']['title'] ?? ''));
        if (!$t) continue;
        $lnk = $item['volumeInfo']['imageLinks'] ?? [];
        $c   = $lnk['thumbnail'] ?? ($lnk['smallThumbnail'] ?? '');
        if (!$c) continue;
        $c = str_replace(['http://', '&edge=curl'], ['https://', ''], $c);
        $c = preg_replace('/zoom=\d/', 'zoom=3', $c);
        $map[$t] = $c;
    }
    return $map;
}

function ws_cover_from_map($title, $orig, $map) {
    if (empty($map)) return '';
    $try = [mb_strtolower($title)];
    if ($orig) $try[] = mb_strtolower($orig);
    foreach ($try as $t) {
        if (isset($map[$t])) return $map[$t];
    }
    foreach ($try as $t) {
        if (strlen($t) < 5) continue;
        foreach ($map as $gt => $url) {
            if (str_contains($gt, $t) || str_contains($t, $gt)) return $url;
        }
    }
    return '';
}

/* ════════════════════════════════════════════════════════════
 * 4. OpenLibrary FALLBACK: two-step (author key → works)
 * ════════════════════════════════════════════════════════════ */
function ws_openlibrary_fallback($author, $limit) {
    // Adım 1: yazar key'i bul
    $data = ws_get('https://openlibrary.org/search/authors.json?q=' . urlencode($author) . '&limit=5');
    $author_key = null;
    $q = mb_strtolower($author);
    foreach ($data['docs'] ?? [] as $a) {
        $name = mb_strtolower(trim($a['name'] ?? ''));
        if ($name === $q || str_contains($name, $q) || str_contains($q, $name)) {
            $author_key = $a['key'] ?? null; break;
        }
    }
    if (!$author_key && !empty($data['docs'][0]['key'])) $author_key = $data['docs'][0]['key'];
    if (!$author_key) return null;

    // Adım 2: o yazara ait eserler
    $works_data = ws_get('https://openlibrary.org' . $author_key . '/works.json?limit=' . $limit);
    if (!$works_data) return null;

    $author_parts = array_filter(explode(' ', mb_strtolower($author)));
    $author_last  = end($author_parts);

    $works = []; $seen = [];
    foreach ($works_data['entries'] ?? [] as $entry) {
        $title = trim($entry['title'] ?? '');
        if (!$title) continue;
        $key = mb_strtolower($title);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        // Yazar doğrulaması
        $work_authors = $entry['authors'] ?? [];
        if (!empty($work_authors)) {
            $matched = false;
            foreach ($work_authors as $wa) {
                $wa_key = $wa['author']['key'] ?? ($wa['key'] ?? '');
                if ($wa_key === $author_key) { $matched = true; break; }
            }
            if (!$matched) continue;
        }

        // "X of/on Kant" pattern veya başlık = soyadı → at
        if (strlen($author_last) >= 4) {
            if (preg_match('/\b(of|on|to|about|and|by)\s+' . preg_quote($author_last, '/') . '\b/i', $title)) continue;
            if (trim(mb_strtolower($title)) === $author_last) continue;
        }

        $cover = '';
        if (!empty($entry['covers'][0]) && $entry['covers'][0] > 0) {
            $cover = 'https://covers.openlibrary.org/b/id/' . $entry['covers'][0] . '-M.jpg';
        }
        $year = '';
        if (!empty($entry['first_publish_date'])) {
            preg_match('/\d{4}/', $entry['first_publish_date'], $m);
            $year = $m[0] ?? '';
        }
        $works[] = ['title' => $title, 'year' => $year, 'cover' => $cover, 'original' => ''];
    }
    return $works ?: null;
}

/* ════════════════════════════════════════════════════════════
 * ANA AKIŞ
 * ════════════════════════════════════════════════════════════ */

// 1) Wikidata'da yazar ara
$entity_id = ws_wikidata_find_author($author);

if ($entity_id) {
    // 2) Yazar doğum/ölüm yılları (validasyon için)
    [$born, $died] = ws_wikidata_author_dates($entity_id);

    // 3) Eserleri çek
    $bindings = ws_wikidata_works($entity_id, $limit);

    // 4) Google Books'tan yazar için tek seferde cover map al
    $cover_map = ws_gb_covers_for_author($author);

    $works = []; $seen_tokens = [];
    foreach ($bindings as $b) {
        $title = trim($b['workLabel']['value'] ?? '');
        // Wikidata entity URL ise (label bulunamadı) atla
        if (!$title || preg_match('/^Q\d+$/', $title)) continue;

        $year    = ws_year($b['date']['value'] ?? '');
        $orig    = trim($b['origLabel']['value'] ?? '');
        if (mb_strtolower($orig) === mb_strtolower($title)) $orig = '';

        // ── Doğrulama: yayın tarihi mantıklı mı? ─────────────────
        if ($year && $born) {
            // Yazar doğmadan önce yayınlandıysa at (çeviri baskıları için +5 tolerans)
            if ((int)$year < ((int)$born - 5)) continue;
        }
        if ($year && $died) {
            // Yazarın ölümünden 40 yıldan fazla sonra "ilk yayın" ise şüpheli — at
            if ((int)$year > ((int)$died + 40)) continue;
        }

        // ── İkincil literatür / derleme başlık filtresi ──────────
        if (preg_match('/\b(portable|reader|anthology|selected works|selected writings|compendium|handbook|encyclopedia|introduction to|readings in|letters of|letters to)\b/i', $title)) continue;
        $author_last_wd = preg_replace('/^.+\s/', '', $author);
        if (strlen($author_last_wd) >= 4) {
            if (preg_match('/\b(of|on|to|about|after|against|between|beyond|from|with)\s+' . preg_quote($author_last_wd, '/') . '\b/i', $title)) continue;
        }

        // ── Dedup (token bazlı) ────────────────────────────────────
        $tok = ws_tokens($title);
        $dup = false;
        foreach ($seen_tokens as $st) {
            if (ws_titles_same($tok, $st)) { $dup = true; break; }
        }
        if ($dup) continue;
        $seen_tokens[] = $tok;

        // ── Kapak: Google Books cover map'ten eşleştir ────────────
        $cover = ws_cover_from_map($title, $orig, $cover_map);

        // ── "English Title (Original Title)" formatı ──────────────
        $display = $title;
        if ($orig && mb_strtolower($orig) !== mb_strtolower($title) && !preg_match('/\([^)]+\)/', $title)) {
            $display = "$title ($orig)";
        }

        $works[] = [
            'title'    => $display,
            'original' => $orig,
            'year'     => $year,
            'cover'    => $cover,
        ];
    }

    if (count($works) >= 3) {
        echo json_encode([
            'ok'     => true,
            'author' => $author,
            'count'  => count($works),
            'works'  => $works,
            'source' => 'wikidata',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Wikidata'dan yeterli sonuç gelmediyse fallback'e düş
}

// 4) FALLBACK: OpenLibrary two-step
$ol_works = ws_openlibrary_fallback($author, $limit);
if ($ol_works !== null) {
    echo json_encode([
        'ok'     => true,
        'author' => $author,
        'count'  => count($ol_works),
        'works'  => $ol_works,
        'source' => 'openlibrary',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok'=>false,'error'=>'Yazar eserleri bulunamadı.'], JSON_UNESCAPED_UNICODE);
