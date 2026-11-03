<?php
/**
 * The Telos — Ana sayfada yalnız KAPAK GÖRSELİ olan kitaplar.
 *
 * Ana sayfadaki bütün yazı sorgularına (ana sorgu + bölüm sorguları) "öne çıkan
 * görseli olsun" ve "yer tutucu olmasın" koşulu eklenir. Diğer sayfalara dokunmaz.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'pre_get_posts', function ( $q ) {
    if ( is_admin() || ! isset( $GLOBALS['wp_query'] ) ) return;
    $on_home = $q->is_main_query() ? ( $q->is_home() || $q->is_front_page() ) : ( is_home() || is_front_page() );
    if ( ! $on_home || ! empty( $_GET['home_demo'] ) ) return;
    $pt = $q->get( 'post_type' );
    if ( ! ( $pt === '' || $pt === 'post' || $pt === [ 'post' ] ) ) return;   // menü vb. sorgulara dokunma
    if ( $q->get( 's' ) ) return;

    $mq = (array) $q->get( 'meta_query' );
    $mq[] = [ 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ];
    $q->set( 'meta_query', $mq );

    if ( function_exists( 'tls_placeholder_ids' ) ) {
        $ph = tls_placeholder_ids();
        if ( $ph ) $q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), $ph ) );
    }
}, 20 );
