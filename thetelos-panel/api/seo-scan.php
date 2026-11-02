<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$type   = trim($_POST['type']   ?? 'posts');
$cat    = (int)($_POST['cat']   ?? 0);
$author = (int)($_POST['author'] ?? 0);

$wp_load = '/home/thetelos/public_html/wp-load.php';
ob_start(); require_once $wp_load; ob_end_clean();

$post_type = ($type === 'analysis') ? 'analysis' : 'post';
$args = [
    'post_type'      => $post_type,
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
];
if ($cat)    $args['cat'] = $cat;
if ($author) $args['tax_query'] = [['taxonomy'=>'authors','field'=>'term_id','terms'=>[$author]]];

// HIZLI TARAMA: binlerce yazıda yazı-yazı get_permalink/terim sorgusu zaman ve
// bellek sınırını aşıp sunucuyu HTML hata sayfasına düşürüyordu ("Unexpected token <").
// Artık tek SQL ile kimlik/başlık/özet/açıklama, tek SQL ile yazar adları alınır.
@set_time_limit(120);
@ini_set('memory_limit', '512M');
$args['fields'] = 'ids';
$args['no_found_rows'] = true;
$args['update_post_meta_cache'] = false;
$args['update_post_term_cache'] = false;
$ids = (new WP_Query($args))->posts;
$result = [];
if ($ids) {
    global $wpdb;
    $in = implode(',', array_map('intval', $ids));
    $rows = $wpdb->get_results("SELECT p.ID, p.post_title, p.post_excerpt, p.post_name, m.meta_value AS md
        FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_yoast_wpseo_metadesc'
        WHERE p.ID IN ($in)", ARRAY_A);
    $auth = [];
    foreach ((array) $wpdb->get_results("SELECT tr.object_id, t.name FROM {$wpdb->term_relationships} tr
        JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'authors'
        JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tr.object_id IN ($in)", ARRAY_A) as $a) {
        if (!isset($auth[$a['object_id']])) $auth[$a['object_id']] = $a['name'];
    }
    foreach ($rows as $r) {
        $result[] = [
            'id'               => (int) $r['ID'],
            'title'            => html_entity_decode($r['post_title'], ENT_QUOTES, 'UTF-8'),
            'author_name'      => $auth[$r['ID']] ?? '',
            'excerpt'          => (string) $r['post_excerpt'],
            'meta_description' => (string) ($r['md'] ?? ''),
            'link'             => home_url('/?p=' . (int) $r['ID']),
        ];
    }
}
echo json_encode(['ok'=>true,'posts'=>$result,'total'=>count($result)], JSON_UNESCAPED_UNICODE);
