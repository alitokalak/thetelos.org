<?php
/**
 * amazon-match.php — Kitapları Amazon ürün sayfasına (ASIN) eşleştirir.
 *
 * NASIL: Amazon'un ürün API'si (PA-API) ilk satışlar gelmeden erişim
 * vermediğinden, eşleştirme OpenLibrary üzerinden yapılır: kitap adı+yazar
 * ile OpenLibrary'de aranır, edisyon ISBN'lerinden bir ISBN-10 seçilir.
 * ISBN-10, Amazon'da doğrudan ürün sayfasıdır (amazon.com/dp/{ISBN10}) ve
 * ASIN olarak kullanılabilir. Admin önizleyip onaylar; onaylananlar
 * _tls_amazon_asin meta'sına yazılır → sitedeki "Buy on Amazon" butonu
 * aramaya değil doğrudan ürüne gider.
 *
 * POST action=scan   → ASIN'i olmayan sıradaki kitaplar için öneri üretir
 *                      (exclude: bu oturumda zaten gösterilen post id'leri).
 * POST action=save   → onaylanan {post_id: asin} çiftlerini kaydeder.
 * POST action=skip   → bulunamayanları '-' ile işaretler (bir daha sorulmaz;
 *                      buton arama linkine düşmeye devam eder).
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-LiteSpeed-Cache-Control: no-cache');
@set_time_limit(120);

ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();

/* ── Yardımcılar ── */

function am_http_get($url, $timeout = 12) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['User-Agent: TheTelos/1.0 (thetelos.org; contact@thetelos.org)'],
    ]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($c >= 200 && $c < 300) ? $r : null;
}

/* ISBN-13 (978…) → ISBN-10 (Amazon ASIN olarak çalışır). */
function am_isbn13_to_10($isbn13) {
    $isbn13 = preg_replace('/[^0-9]/', '', (string) $isbn13);
    if (strlen($isbn13) !== 13 || strpos($isbn13, '978') !== 0) return '';
    $core = substr($isbn13, 3, 9);
    $sum  = 0;
    for ($i = 0; $i < 9; $i++) { $sum += (10 - $i) * (int) $core[$i]; }
    $chk = (11 - ($sum % 11)) % 11;
    return $core . ($chk === 10 ? 'X' : (string) $chk);
}

/* OL edisyon ISBN listesinden kullanılabilir ISBN-10 seç. */
function am_pick_isbn10($isbns) {
    if (!is_array($isbns)) return '';
    foreach ($isbns as $i) {                      // önce hazır ISBN-10
        $i = strtoupper(preg_replace('/[^0-9Xx]/', '', (string) $i));
        if (preg_match('/^[0-9]{9}[0-9X]$/', $i)) return $i;
    }
    foreach ($isbns as $i) {                      // yoksa 978'li ISBN-13'ten çevir
        $ten = am_isbn13_to_10($i);
        if ($ten !== '') return $ten;
    }
    return '';
}

/* Post başlığından temiz kitap adı + yazar çıkar. */
function am_parse_book($post_id) {
    $title   = trim(html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8'));
    $authors = get_the_terms($post_id, 'authors');
    $author  = (!empty($authors) && !is_wp_error($authors)) ? $authors[0]->name : '';
    $book    = $title;
    if ($author !== '') {
        $book = preg_replace('/\s*[-–—]\s*' . preg_quote($author, '/') . '\s*[-–—]?\s*$/u', '', $book);
    }
    $book = trim(preg_replace('/\s*\([^()]*\)\s*$/u', '', $book));
    if ($book === '') $book = $title;
    return [$book, $author];
}

/* Başlık/yazar karşılaştırması için normalize: küçük harf, aksan/işaret temizliği. */
function am_norm($s) {
    $s = html_entity_decode((string) $s, ENT_QUOTES, 'UTF-8');
    $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    if (function_exists('transliterator_transliterate')) {
        $t = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $s);
        if (is_string($t)) $s = $t;
    } else {
        $map = ['á'=>'a','à'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a','ā'=>'a',
                'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','ē'=>'e',
                'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ī'=>'i',
                'ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o','ø'=>'o','ō'=>'o',
                'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ū'=>'u',
                'ç'=>'c','ñ'=>'n','ß'=>'ss','ş'=>'s','ğ'=>'g','ı'=>'i'];
        $s = strtr($s, $map);
    }
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', (string) $s));
}

/* İki başlık güçlü eşleşiyor mu? (tam eşit / biri diğerini kapsıyor / %90+ benzer) */
function am_title_match($our, $ol) {
    $a = am_norm($our); $b = am_norm($ol);
    if ($a === '' || $b === '') return false;
    if ($a === $b) return true;
    if (strlen($a) >= 6 && strpos($b, $a) !== false) return true;
    if (strlen($b) >= 6 && strpos($a, $b) !== false) return true;
    $pct = 0.0; similar_text($a, $b, $pct);
    return $pct >= 90;
}

/* Yazar eşleşiyor mu? null = bizde yazar yok (bilinmiyor). */
function am_author_match($our, $ol_authors) {
    $a = am_norm($our);
    if ($a === '') return null;
    $parts = explode(' ', $a); $sur = end($parts);
    foreach ((array) $ol_authors as $ol) {
        $b = am_norm($ol);
        if ($b === '') continue;
        if ($a === $b) return true;
        if ($sur !== '' && strlen($sur) >= 3 && strpos($b, $sur) !== false) return true;
        $pct = 0.0; similar_text($a, $b, $pct);
        if ($pct >= 85) return true;
    }
    return false;
}

function am_stats() {
    global $wpdb;
    $total   = (int) wp_count_posts('post')->publish;
    $matched = (int) $wpdb->get_var("SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_status='publish' AND p.post_type='post'
        WHERE pm.meta_key='_tls_amazon_asin' AND pm.meta_value NOT IN ('','-')");
    $skipped = (int) $wpdb->get_var("SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_status='publish' AND p.post_type='post'
        WHERE pm.meta_key='_tls_amazon_asin' AND pm.meta_value='-'");
    return ['total' => $total, 'matched' => $matched, 'skipped' => $skipped,
            'remaining' => max(0, $total - $matched - $skipped)];
}

$action = $_POST['action'] ?? '';

/* ── Tara: sıradaki kitaplar için ASIN önerisi üret ──
   auto=1 ise: başlık+yazar güçlü eşleşen (kesin emin olunan) kitaplar
   DOĞRUDAN kaydedilir; yalnızca şüpheli/bulunamayanlar elle onaya döner. */
if ($action === 'scan') {
    $exclude = array_values(array_filter(array_map('intval', explode(',', (string)($_POST['exclude'] ?? '')))));
    $auto    = !empty($_POST['auto']);
    $chunk   = $auto ? 10 : 6;

    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $chunk,
        'post__not_in'   => $exclude,
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [[ 'key' => '_tls_amazon_asin', 'compare' => 'NOT EXISTS' ]],
    ]);

    $rows       = [];
    $auto_saved = 0;
    foreach ($q->posts as $pid) {
        [$book, $author] = am_parse_book($pid);
        $row = [
            'post_id'  => $pid,
            'book'     => $book,
            'author'   => $author,
            'edit_url' => get_edit_post_link($pid, 'raw'),
            'asin'     => '', 'ol_title' => '', 'ol_year' => '', 'cover' => '',
        ];

        $ol = json_decode((string) am_http_get(
            'https://openlibrary.org/search.json?title=' . urlencode($book)
            . ($author !== '' ? '&author=' . urlencode($author) : '')
            . '&limit=5&fields=title,author_name,first_publish_year,isbn,cover_i'
        ), true);

        $fallback  = null;   // ISBN'li ilk sonuç (elle öneri)
        $confident = null;   // başlık+yazar kesin eşleşen sonuç
        foreach (($ol['docs'] ?? []) as $doc) {
            $isbn10 = am_pick_isbn10($doc['isbn'] ?? []);
            if ($isbn10 === '') continue;
            $cand = [
                'asin'  => $isbn10,
                'title' => (string) ($doc['title'] ?? ''),
                'year'  => (string) ($doc['first_publish_year'] ?? ''),
                'cover' => !empty($doc['cover_i'])
                    ? 'https://covers.openlibrary.org/b/id/' . (int) $doc['cover_i'] . '-S.jpg' : '',
            ];
            if ($fallback === null) $fallback = $cand;

            $tmatch = am_title_match($book, $cand['title']);
            $amatch = am_author_match($author, $doc['author_name'] ?? []);
            // Kesin emin: başlık güçlü eşleşir VE (yazar da eşleşir YA DA
            // yazar bilinmiyorsa başlık birebir aynıdır).
            $sure = $tmatch && ($amatch === true
                     || ($amatch === null && am_norm($book) === am_norm($cand['title'])));
            if ($sure) { $confident = $cand; break; }
        }

        if ($auto && $confident !== null) {
            update_post_meta($pid, '_tls_amazon_asin', $confident['asin']);
            $auto_saved++;
            continue;   // kaydedildi, elle onaya gönderme
        }

        $pick = $confident ?: $fallback;
        if ($pick) {
            $row['asin']     = $pick['asin'];
            $row['ol_title'] = $pick['title'];
            $row['ol_year']  = $pick['year'];
            $row['cover']    = $pick['cover'];
        }
        $rows[] = $row;
    }

    echo json_encode([
        'ok'         => true,
        'rows'       => $rows,
        'auto_saved' => $auto_saved,
        'done'       => count($q->posts) === 0,
        'stats'      => am_stats(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Kaydet: onaylanan eşleşmeleri yaz ── */
if ($action === 'save') {
    $pairs = json_decode((string) ($_POST['pairs'] ?? '[]'), true);
    $saved = 0;
    if (is_array($pairs)) {
        foreach ($pairs as $p) {
            $pid  = (int) ($p['post_id'] ?? 0);
            $asin = strtoupper(trim((string) ($p['asin'] ?? '')));
            if (!$pid || !preg_match('/^[0-9A-Z]{10}$/', $asin)) continue;
            if (get_post_status($pid) === false) continue;
            update_post_meta($pid, '_tls_amazon_asin', $asin);
            $saved++;
        }
    }
    echo json_encode(['ok' => true, 'saved' => $saved, 'stats' => am_stats()]);
    exit;
}

/* ── Atla: bulunamayanları '-' ile işaretle (tekrar sorulmaz) ── */
if ($action === 'skip') {
    $ids  = array_values(array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? '')))));
    $done = 0;
    foreach ($ids as $pid) {
        if (get_post_status($pid) === false) continue;
        if (trim((string) get_post_meta($pid, '_tls_amazon_asin', true)) !== '') continue;
        update_post_meta($pid, '_tls_amazon_asin', '-');
        $done++;
    }
    echo json_encode(['ok' => true, 'skipped' => $done, 'stats' => am_stats()]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'bad action']);
