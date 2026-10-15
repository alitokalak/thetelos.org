<?php
/**
 * The Telos — Book yapısal verisi (JSON-LD)
 *
 * Her kitap özeti sayfasına schema.org/Book bloğu basar: kitap adı, yazar,
 * yayın yılı, kapak, okur puanı (aggregateRating). Google bu sayede sayfanın
 * bir KİTAP hakkında olduğunu anlar; puan varsa arama sonucunda yıldız
 * gösterebilir. Yoast'ın Article/Breadcrumb şemasına dokunmaz, ayrı blok.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_head', 'thetelos_book_schema', 30 );
function thetelos_book_schema() {
    if ( ! is_singular( 'post' ) ) return;

    $post_id = get_queried_object_id();
    if ( ! $post_id ) return;

    // Yer-tutucu yazılarda (içerik henüz yok) şema basma.
    $content = (string) get_post_field( 'post_content', $post_id );
    if ( strpos( $content, 'is being prepared and will be published here soon' ) !== false ) return;

    $authors = get_the_terms( $post_id, 'authors' );
    $author  = ( ! empty( $authors ) && ! is_wp_error( $authors ) ) ? $authors[0] : null;

    // Başlıktan " - Yazar" ekini ve sondaki parantezli orijinal adı temizle
    // (tls_book_query ile aynı kural).
    $name = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
    if ( $author ) {
        $name = preg_replace( '/\s*[-–—]\s*' . preg_quote( $author->name, '/' ) . '\s*$/u', '', $name );
    }
    $name = trim( preg_replace( '/\s*\([^()]*\)\s*$/u', '', $name ) );
    if ( $name === '' ) return;

    $url    = get_permalink( $post_id );
    $schema = [
        '@context' => 'https://schema.org',
        '@type'    => 'Book',
        '@id'      => $url . '#book',
        'name'     => $name,
        'url'      => $url,
        'mainEntityOfPage' => $url,
    ];

    if ( $author ) {
        $aurl = get_term_link( $author );
        $schema['author'] = array_filter( [
            '@type' => 'Person',
            'name'  => $author->name,
            'url'   => is_wp_error( $aurl ) ? '' : $aurl,
        ] );
    }

    // Kitabın yayın yılı (OpenLibrary'den panelce doldurulur).
    $year = (string) get_post_meta( $post_id, '_tls_pub_year', true );
    if ( preg_match( '/^\d{3,4}$/', $year ) ) $schema['datePublished'] = $year;

    if ( has_post_thumbnail( $post_id ) ) {
        $img = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'large' );
        if ( $img ) $schema['image'] = $img[0];
    } elseif ( function_exists( 'thetelos_og_cover_url' ) ) {
        $og = thetelos_og_cover_url( $post_id );
        if ( $og ) $schema['image'] = $og;
    }

    $desc = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : '';
    if ( $desc !== '' ) $schema['description'] = wp_strip_all_tags( $desc );

    // Okur puanı: yalnız gerçek oy varsa (sahte puan basmak Google cezası).
    if ( function_exists( 'thetelos_get_rating' ) ) {
        $r = thetelos_get_rating( $post_id );
        if ( $r['count'] > 0 && $r['avg'] > 0 ) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => round( (float) $r['avg'], 1 ),
                'ratingCount' => (int) $r['count'],
                'bestRating'  => 5,
                'worstRating' => 1,
            ];
        }
    }

    echo '<script type="application/ld+json">'
       . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG )
       . "</script>\n";
}
