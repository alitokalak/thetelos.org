<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$wp_load = '/home/thetelos/public_html/wp-load.php';
ob_start(); require_once $wp_load; ob_end_clean();

$cats_raw = get_categories(['hide_empty'=>true,'number'=>200,'orderby'=>'count','order'=>'DESC']);
$categories = [];
foreach ($cats_raw as $c) {
    $categories[] = ['id'=>$c->term_id,'name'=>$c->name,'count'=>$c->count];
}

$authors_raw = get_terms(['taxonomy'=>'authors','hide_empty'=>true,'number'=>500,'orderby'=>'name','order'=>'ASC']);
$authors = [];
if (!is_wp_error($authors_raw)) {
    foreach ($authors_raw as $a) {
        $authors[] = ['id'=>$a->term_id,'name'=>$a->name];
    }
}

echo json_encode(['ok'=>true,'categories'=>$categories,'authors'=>$authors]);
