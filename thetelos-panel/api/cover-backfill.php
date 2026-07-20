<?php
/**
 * cover-backfill.php — Kapağı (featured image) olmayan yayınlanmış kitaplara
 * sonradan kapak bulur ve atar. Üretim sırasında kapak bulunamayan postlar
 * sitede tipografik "thetelos" placeholder ile görünüyor; bu araç onları
 * OpenLibrary / Google Books / Amazon (ASIN) üzerinden tarar.
 *
 * Kaynak önceliği (kaliteye göre):
 *   1) OpenLibrary  → covers.openlibrary.org/…-L.jpg (büyük, güvenilir)
 *   2) Google Books → volumeInfo.imageLinks (geniş kapsam)
 *   3) Amazon       → images-na…/images/P/{ASIN}.jpg (ASIN meta'sı ya da OL ISBN'i varsa)
 *
 * POST action=scan   → kapağı olmayan sıradaki kitaplar için aday kapak(lar) önerir
 *                      (yazmaz; exclude: bu oturumda gösterilen post id'leri).
 * POST action=apply  → [{post_id, candidates:[url…]}] için ilk çalışan kapağı
 *                      indirip WP medyasına yükler, featured image yapar.
 * POST action=skip   → kapak bulunamayanları _tls_cover_skip=1 ile işaretler
 *                      (tekrar taranmaz).
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-LiteSpeed-Cache-Control: no-cache');
@set_time_limit(180);

ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();

/* ── HTTP GET (ham gövde döndürür) ── */
function cb_http_get($url, $timeout = 15) {
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

/* ISBN-13 (978…) → ISBN-10 (Amazon görsel yolu için de kullanılır). */
function cb_isbn13_to_10($isbn13) {
    $isbn13 = preg_replace('/[^0-9]/', '', (string) $isbn13);
    if (strlen($isbn13) !== 13 || strpos($isbn13, '978') !== 0) return '';
    $core = substr($isbn13, 3, 9);
    $sum  = 0;
    for ($i = 0; $i < 9; $i++) { $sum += (10 - $i) * (int) $core[$i]; }
    $chk = (11 - ($sum % 11)) % 11;
    return $core . ($chk === 10 ? 'X' : (string) $chk);
}
function cb_pick_isbn10($isbns) {
    if (!is_array($isbns)) return '';
    foreach ($isbns as $i) {
        $i = strtoupper(preg_replace('/[^0-9Xx]/', '', (string) $i));
        if (preg_match('/^[0-9]{9}[0-9X]$/', $i)) return $i;
    }
    foreach ($isbns as $i) {
        $ten = cb_isbn13_to_10($i);
        if ($ten !== '') return $ten;
    }
    return '';
}

/* Post başlığından temiz kitap adı + yazar çıkar. */
function cb_parse_book($post_id) {
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

/* Bir post için sıralı aday kapak URL'leri üret. */
function cb_find_covers($post_id) {
    [$book, $author] = cb_parse_book($post_id);
    $cands  = [];
    $olisbn = [];

    // 1) OpenLibrary
    $ol = json_decode((string) cb_http_get(
        'https://openlibrary.org/search.json?title=' . urlencode($book)
        . ($author !== '' ? '&author=' . urlencode($author) : '')
        . '&limit=3&fields=title,cover_i,isbn'
    ), true);
    foreach (($ol['docs'] ?? []) as $doc) {
        if (!empty($doc['isbn']) && is_array($doc['isbn'])) {
            $olisbn = array_merge($olisbn, array_slice($doc['isbn'], 0, 4));
        }
        if (!empty($doc['cover_i']) && (int) $doc['cover_i'] > 0) {
            $cands[] = ['url' => 'https://covers.openlibrary.org/b/id/' . (int) $doc['cover_i'] . '-L.jpg', 'src' => 'openlibrary'];
            break;
        }
    }

    // 2) Google Books
    if (defined('GOOGLE_BOOKS_KEY') && GOOGLE_BOOKS_KEY !== '') {
        $q  = 'intitle:' . $book . ($author !== '' ? ' inauthor:' . $author : '');
        $gb = json_decode((string) cb_http_get('https://www.googleapis.com/books/v1/volumes?' . http_build_query([
            'q'          => $q,
            'maxResults' => 3,
            'printType'  => 'books',
            'fields'     => 'items(volumeInfo(title,imageLinks))',
            'key'        => GOOGLE_BOOKS_KEY,
        ])), true);
        foreach (($gb['items'] ?? []) as $it) {
            $lnk = $it['volumeInfo']['imageLinks'] ?? [];
            $c   = $lnk['thumbnail'] ?? ($lnk['smallThumbnail'] ?? '');
            if ($c) {
                $c = str_replace(['http://', '&edge=curl'], ['https://', ''], $c);
                $c = preg_replace('/zoom=\d/', 'zoom=1', $c);
                $cands[] = ['url' => $c, 'src' => 'google'];
                break;
            }
        }
    }

    // 3) Amazon (ASIN meta'sı ya da OL ISBN'i)
    $asin = trim((string) get_post_meta($post_id, '_tls_amazon_asin', true));
    if ($asin === '' || $asin === '-') $asin = cb_pick_isbn10($olisbn);
    if ($asin !== '' && $asin !== '-' && preg_match('/^[0-9A-Z]{10}$/', $asin)) {
        $cands[] = ['url' => 'https://images-na.ssl-images-amazon.com/images/P/' . $asin . '.01._SCLZZZZZZZ_.jpg', 'src' => 'amazon'];
    }

    return [$book, $author, $cands];
}

/* Aday URL'yi indir, doğrula, WP medyasına yükle, featured image yap. */
function cb_set_cover($post_id, $url, $title) {
    $bytes = cb_http_get($url, 25);
    if (!$bytes || strlen($bytes) < 3000) return false;          // çok küçük / boş
    $info = @getimagesizefromstring($bytes);
    if (!$info || $info[0] < 60 || $info[1] < 90) return false;  // gri placeholder / 1x1 ele

    $mime = $info['mime'] ?? 'image/jpeg';
    $ext  = ['image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime] ?? 'jpg';

    $slug = sanitize_title($title);
    if ($slug === '') $slug = 'cover-' . $post_id;
    $up = wp_upload_bits($slug . '-cover.' . $ext, null, $bytes);
    if (!empty($up['error']) || empty($up['file'])) return false;

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $att_id = wp_insert_attachment([
        'post_mime_type' => $mime,
        'post_title'     => $title !== '' ? $title : ('Cover ' . $post_id),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ], $up['file'], $post_id);
    if (is_wp_error($att_id) || !$att_id) return false;

    $meta = wp_generate_attachment_metadata($att_id, $up['file']);
    wp_update_attachment_metadata($att_id, $meta);
    set_post_thumbnail($post_id, $att_id);
    delete_post_meta($post_id, '_tls_cover_skip');
    return true;
}

function cb_stats() {
    global $wpdb;
    $total = (int) wp_count_posts('post')->publish;
    $covered = (int) $wpdb->get_var("SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_status='publish' AND p.post_type='post'
        WHERE pm.meta_key='_thumbnail_id' AND pm.meta_value > 0");
    $skipped = (int) $wpdb->get_var("SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_status='publish' AND p.post_type='post'
        WHERE pm.meta_key='_tls_cover_skip' AND pm.meta_value='1'");
    return ['total' => $total, 'covered' => $covered, 'skipped' => $skipped,
            'remaining' => max(0, $total - $covered - $skipped)];
}

$action = $_POST['action'] ?? '';

/* ── Tara ── */
if ($action === 'scan') {
    $exclude = array_values(array_filter(array_map('intval', explode(',', (string)($_POST['exclude'] ?? '')))));
    $chunk   = 4;

    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $chunk,
        'post__not_in'   => $exclude,
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => '_thumbnail_id',   'compare' => 'NOT EXISTS' ],
            [ 'key' => '_tls_cover_skip', 'compare' => 'NOT EXISTS' ],
        ],
    ]);

    $rows = [];
    foreach ($q->posts as $pid) {
        [$book, $author, $cands] = cb_find_covers($pid);
        $rows[] = [
            'post_id'    => $pid,
            'book'       => $book,
            'author'     => $author,
            'edit_url'   => get_edit_post_link($pid, 'raw'),
            'view_url'   => get_permalink($pid),
            'cover'      => $cands[0]['url'] ?? '',
            'source'     => $cands[0]['src'] ?? '',
            'candidates' => array_map(function ($c) { return $c['url']; }, $cands),
        ];
    }

    echo json_encode([
        'ok'    => true,
        'rows'  => $rows,
        'done'  => count($q->posts) === 0,
        'stats' => cb_stats(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Uygula: adayları indirip featured image yap ── */
if ($action === 'apply') {
    $items = json_decode((string)($_POST['items'] ?? '[]'), true);
    $applied = 0; $failed = [];
    if (is_array($items)) {
        foreach ($items as $it) {
            $pid = (int) ($it['post_id'] ?? 0);
            if (!$pid || get_post_status($pid) === false) continue;
            if (has_post_thumbnail($pid)) { $applied++; continue; }
            $cands = $it['candidates'] ?? [];
            $title = get_the_title($pid);
            $ok = false;
            foreach ((array) $cands as $u) {
                $u = is_array($u) ? ($u['url'] ?? '') : $u;
                if ($u !== '' && cb_set_cover($pid, $u, $title)) { $ok = true; break; }
            }
            if ($ok) $applied++; else $failed[] = $pid;
        }
    }
    echo json_encode(['ok' => true, 'applied' => $applied, 'failed' => $failed, 'stats' => cb_stats()]);
    exit;
}

/* ── Atla: kapak bulunamayanları işaretle ── */
if ($action === 'skip') {
    $ids  = array_values(array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? '')))));
    $done = 0;
    foreach ($ids as $pid) {
        if (get_post_status($pid) === false) continue;
        if (has_post_thumbnail($pid)) continue;
        update_post_meta($pid, '_tls_cover_skip', '1');
        $done++;
    }
    echo json_encode(['ok' => true, 'skipped' => $done, 'stats' => cb_stats()]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'bad action']);
