<?php
/**
 * The Telos — Yer-tutucu yazıları Google dizininden uzak tut
 *
 * İçeriği henüz yazılmamış ("… is being prepared and will be published here
 * soon") kitap sayfaları ince/boş içeriktir. Binlercesi dizinde durursa Google
 * siteyi bütünüyle "düşük değerli, ölçekli içerik" sayıp tüm sayfaları geri
 * itebilir. Bu yüzden:
 *   - sayfada robots "noindex, follow" (linkler takip edilir, sayfa dizine girmez),
 *   - Yoast site haritasından çıkarılır.
 * İçerik yazılınca işaret kalkar, sayfa kendiliğinden yeniden dizine açılır.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

const TLS_PH_MARKER = 'is being prepared and will be published here soon';

function tls_is_placeholder_post( $post_id ) {
    return strpos( (string) get_post_field( 'post_content', $post_id ), TLS_PH_MARKER ) !== false;
}

/* Yoast robots meta */
add_filter( 'wpseo_robots', function ( $robots ) {
    if ( is_singular( 'post' ) && tls_is_placeholder_post( get_queried_object_id() ) ) return 'noindex, follow';
    return $robots;
}, 99 );

/* Yoast yoksa WordPress çekirdeğinin robots meta'sı */
add_filter( 'wp_robots', function ( $robots ) {
    if ( ! defined( 'WPSEO_VERSION' ) && is_singular( 'post' ) && tls_is_placeholder_post( get_queried_object_id() ) ) {
        $robots['noindex'] = true; $robots['follow'] = true; unset( $robots['index'] );
    }
    return $robots;
}, 99 );

/* Yoast site haritasından çıkar (liste 6 saatte bir yenilenir) */
function tls_placeholder_ids() {
    $ids = get_transient( 'tls_placeholder_ids' );
    if ( is_array( $ids ) ) return $ids;
    global $wpdb;
    $ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_content LIKE %s",
        '%' . $wpdb->esc_like( TLS_PH_MARKER ) . '%'
    ) ) );
    set_transient( 'tls_placeholder_ids', $ids, 6 * HOUR_IN_SECONDS );
    return $ids;
}
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', function ( $excluded ) {
    return array_values( array_unique( array_merge( (array) $excluded, tls_placeholder_ids() ) ) );
} );
/* İçerik değişince liste tazelensin */
add_action( 'save_post_post', function () { delete_transient( 'tls_placeholder_ids' ); } );
