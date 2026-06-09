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

$query = new WP_Query($args);
$result = [];

foreach ($query->posts as $pid) {
    $excerpt   = get_post_field('post_excerpt', $pid);
    $meta_desc = get_post_meta($pid, '_yoast_wpseo_metadesc', true);
    $title     = get_the_title($pid);
    $link      = get_permalink($pid);
    $authors   = wp_get_post_terms($pid, 'authors', ['fields'=>'names']);
    $aname     = (!is_wp_error($authors) && $authors) ? $authors[0] : '';

    $result[] = [
        'id'               => $pid,
        'title'            => html_entity_decode($title),
        'author_name'      => $aname,
        'excerpt'          => $excerpt ?: '',
        'meta_description' => $meta_desc ?: '',
        'link'             => $link,
    ];
}

wp_reset_postdata();
echo json_encode(['ok'=>true,'posts'=>$result,'total'=>count($result)]);
