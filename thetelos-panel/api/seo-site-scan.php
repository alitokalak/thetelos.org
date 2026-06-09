<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$section = trim($_POST['section'] ?? 'categories');

$wp_load = '/home/thetelos/public_html/wp-load.php';
ob_start(); require_once $wp_load; ob_end_clean();

$result = [];

if ($section === 'categories') {
    $terms = get_terms(['taxonomy'=>'category','hide_empty'=>true,'number'=>200]);
    foreach ($terms as $term) {
        $meta_desc = get_term_meta($term->term_id, 'wpseo_metadesc', true);
        $desc      = $term->description;
        $issues    = [];
        if (!$meta_desc) $issues[] = 'meta_missing';
        if (!$desc)      $issues[] = 'desc_missing';
        $result[] = [
            'id'       => $term->term_id,
            'name'     => $term->name,
            'slug'     => $term->slug,
            'count'    => $term->count,
            'desc'     => $desc,
            'meta'     => $meta_desc,
            'issues'   => $issues,
            'link'     => get_term_link($term),
        ];
    }
} elseif ($section === 'authors') {
    $terms = get_terms(['taxonomy'=>'authors','hide_empty'=>true,'number'=>500]);
    foreach ($terms as $term) {
        $meta_desc = get_term_meta($term->term_id, 'wpseo_metadesc', true);
        $desc      = $term->description;
        $issues    = [];
        if (!$meta_desc) $issues[] = 'meta_missing';
        if (!$desc)      $issues[] = 'desc_missing';
        $result[] = [
            'id'     => $term->term_id,
            'name'   => $term->name,
            'slug'   => $term->slug,
            'count'  => $term->count,
            'desc'   => $desc,
            'meta'   => $meta_desc,
            'issues' => $issues,
            'link'   => get_term_link($term),
        ];
    }
} elseif ($section === 'technical') {
    // Duplicate title kontrolü
    global $wpdb;
    $dupes = $wpdb->get_results("
        SELECT post_title, COUNT(*) as cnt
        FROM {$wpdb->posts}
        WHERE post_status = 'publish' AND post_type IN ('post','analysis')
        GROUP BY post_title HAVING cnt > 1
    ");
    // Kısa içerik
    $short = $wpdb->get_results("
        SELECT ID, post_title, LENGTH(post_content) as len
        FROM {$wpdb->posts}
        WHERE post_status = 'publish' AND post_type IN ('post','analysis')
        AND LENGTH(post_content) < 2000
        ORDER BY len ASC LIMIT 20
    ");
    $result = [
        'duplicate_titles' => $dupes,
        'short_content'    => $short,
    ];
}

echo json_encode(['ok'=>true,'data'=>$result,'total'=>is_array($result)?count($result):0]);
