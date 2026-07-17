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

/* ── Tara: sıradaki kitaplar için ASIN önerisi üret ── */
if ($action === 'scan') {
    $exclude = array_values(array_filter(array_map('intval', explode(',', (string)($_POST['exclude'] ?? '')))));
    $chunk   = 6;

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

    $rows = [];
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

        foreach (($ol['docs'] ?? []) as $doc) {
            $isbn10 = am_pick_isbn10($doc['isbn'] ?? []);
            if ($isbn10 === '') continue;
            $row['asin']     = $isbn10;
            $row['ol_title'] = (string) ($doc['title'] ?? '');
            $row['ol_year']  = (string) ($doc['first_publish_year'] ?? '');
            $row['cover']    = !empty($doc['cover_i'])
                ? 'https://covers.openlibrary.org/b/id/' . (int) $doc['cover_i'] . '-S.jpg' : '';
            break;
        }
        $rows[] = $row;
    }

    echo json_encode([
        'ok'    => true,
        'rows'  => $rows,
        'done'  => count($q->posts) === 0,
        'stats' => am_stats(),
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
