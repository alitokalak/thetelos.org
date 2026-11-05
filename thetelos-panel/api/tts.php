<?php
/**
 * tts.php — Sesli özet (Kokoro) için sitenin uç noktası.
 *
 * Google Colab'daki seslendirme defteri bu adresle konuşur (oturum yok, anahtarla):
 *   GET  ?action=list&key=…&limit=20     → sesi olmayan kitaplar (en çok okunandan başlayarak)
 *                                          + seslendirilecek düz metin
 *   POST action=attach key post_id url seconds → kitaba ses dosyasını bağlar
 *   GET  ?action=stats&key=…              → kaç kitabın sesi var
 * Anahtar panelde "Sesli Özet" sayfasında görünür.
 */
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$tts_key = hash('sha256', WP_APP_PASS . '|tls-tts');
$key = (string) ($_REQUEST['key'] ?? '');
if (!hash_equals($tts_key, $key)) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'anahtar hatalı']); exit; }

ob_start(); require_once '/home/thetelos/public_html/wp-load.php'; ob_end_clean();
@set_time_limit(120);

/* Seslendirilecek metin: başlık + yazar + gövde (başlıklar ayrı cümle, linksiz, kısa kodsuz). */
function tts_text($pid) {
    $p = get_post($pid);
    if (!$p) return '';
    $title = html_entity_decode(get_the_title($pid), ENT_QUOTES, 'UTF-8');
    $title = trim(preg_replace('/\s+[–—-]\s+[^–—]+$/u', '', $title));
    $terms = get_the_terms($pid, 'authors');
    $author = ($terms && !is_wp_error($terms)) ? $terms[0]->name : '';
    $html = strip_shortcodes((string) $p->post_content);
    $html = preg_replace('~<(script|style|figure|figcaption|table)[^>]*>.*?</\1>~is', ' ', $html);
    $html = preg_replace('~<h[1-6][^>]*>(.*?)</h[1-6]>~is', "\n\n$1.\n\n", $html);
    $html = preg_replace('~</(p|li|blockquote|div)>~i', "\n", $html);
    $txt = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    $txt = preg_replace('~https?://\S+~', '', $txt);
    $txt = preg_replace('/[ \t]+/u', ' ', $txt);
    $txt = preg_replace('/\n\s*\n+/u', "\n\n", trim($txt));
    $txt = preg_replace('/\.\.\n/u', ".\n", $txt);
    $head = $title . ($author !== '' ? ', by ' . $author : '') . ".\n\n";
    return $head . $txt;
}

$action = $_REQUEST['action'] ?? 'list';

if ($action === 'attach') {
    $pid = (int) ($_POST['post_id'] ?? 0);
    $url = esc_url_raw((string) ($_POST['url'] ?? ''));
    if (!$pid || get_post_type($pid) !== 'post' || $url === '' || !preg_match('~^https://~', $url)) {
        echo json_encode(['ok' => false, 'error' => 'post_id/url geçersiz']); exit;
    }
    update_post_meta($pid, '_tls_audio_url', $url);
    update_post_meta($pid, '_tls_audio_seconds', max(0, (int) ($_POST['seconds'] ?? 0)));
    if (function_exists('litespeed_purge_single_post')) litespeed_purge_single_post($pid);
    do_action('litespeed_purge_post', $pid);
    echo json_encode(['ok' => true]); exit;
}

global $wpdb;
if ($action === 'stats') {
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
    $with  = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_tls_audio_url' AND meta_value<>''");
    echo json_encode(['ok' => true, 'total' => $total, 'with_audio' => $with]); exit;
}

// list: sesi olmayan, yer tutucu olmayan, en çok okunan önce.
$limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
$ph = function_exists('tls_placeholder_ids') ? tls_placeholder_ids() : [];
$q = new WP_Query([
    'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => $limit, 'fields' => 'ids',
    'no_found_rows' => true, 'post__not_in' => $ph,
    'meta_key' => 'post_views_count', 'orderby' => 'meta_value_num', 'order' => 'DESC',
    'meta_query' => [
        'relation' => 'AND',
        ['key' => 'post_views_count', 'compare' => 'EXISTS'],
        ['relation' => 'OR', ['key' => '_tls_audio_url', 'compare' => 'NOT EXISTS'], ['key' => '_tls_audio_url', 'value' => '', 'compare' => '=']],
        ['key' => '_tls_audio_skip', 'compare' => 'NOT EXISTS'],
    ],
]);
$out = [];
foreach ($q->posts as $pid) {
    $t = tts_text($pid);
    if (str_word_count($t) < 200) { update_post_meta($pid, '_tls_audio_skip', 1); continue; }   // çok kısa → seslendirmeye değmez, bir daha listelenmez
    $out[] = ['post_id' => (int) $pid, 'title' => get_the_title($pid), 'text' => $t];
}
echo json_encode(['ok' => true, 'items' => $out], JSON_UNESCAPED_UNICODE);
