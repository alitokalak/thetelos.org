<?php
/**
 * The Telos — Google'dan gizlenen (noindex) yazılar.
 * Panel → api/seo-prune.php ile işaretlenir: post meta _tls_seo_pruned.
 * Sayfa sitede okunmaya devam eder; yalnız Google'a "dizine alma" denir ve
 * site haritasından çıkar. Meta silinince (geri al) her şey eski haline döner.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tls_is_pruned( $post_id ) { return (bool) get_post_meta( $post_id, '_tls_seo_pruned', true ); }

add_filter( 'wpseo_robots', function ( $robots ) {
    if ( is_singular( 'post' ) && tls_is_pruned( get_queried_object_id() ) ) return 'noindex, follow';
    return $robots;
}, 20 );
add_filter( 'wp_robots', function ( $robots ) {
    if ( is_singular( 'post' ) && tls_is_pruned( get_queried_object_id() ) ) { $robots['noindex'] = true; $robots['follow'] = true; unset( $robots['index'] ); }
    return $robots;
}, 20 );

/* Site haritasından çıkar (Yoast + WordPress çekirdeği). */
function tls_pruned_ids() {
    $ids = get_transient( 'tls_pruned_ids' );
    if ( ! is_array( $ids ) ) {
        global $wpdb;
        $ids = array_map( 'intval', $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tls_seo_pruned'" ) );
        set_transient( 'tls_pruned_ids', $ids, 6 * HOUR_IN_SECONDS );
    }
    return $ids;
}
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', function ( $ids ) { return array_merge( (array) $ids, tls_pruned_ids() ); } );
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $type ) {
    if ( $type === 'post' ) $args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? [] ), tls_pruned_ids() );
    return $args;
}, 10, 2 );
