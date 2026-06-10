<?php
/**
 * SEO Module
 * Open Graph / Twitter Card etiketleri + JSON-LD yapısal veri
 * (Book, Article, ProfilePage, BreadcrumbList)
 *
 * @package Mediumish / TheTelos
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// -----------------------------------------------------
// SEO eklentisi aktif mi? (Yoast / Rank Math / AIOSEO)
// Aktifse OG ve breadcrumb çıktısını eklentiye bırak.
// -----------------------------------------------------
function thetelos_seo_plugin_active() {
    return class_exists( 'WPSEO_Options' )
        || class_exists( 'RankMath' )
        || function_exists( 'aioseo' );
}

// -----------------------------------------------------
// Açıklama metni üret (sayfa tipine göre)
// -----------------------------------------------------
function thetelos_seo_get_description() {
    if ( is_singular() ) {
        $excerpt = get_the_excerpt();
        if ( $excerpt ) {
            return wp_trim_words( strip_tags( $excerpt ), 30, '…' );
        }
    }
    if ( is_tax( 'authors' ) || is_category() || is_tag() ) {
        $desc = term_description();
        if ( $desc ) {
            return wp_trim_words( strip_tags( $desc ), 30, '…' );
        }
        $term = get_queried_object();
        if ( $term && ! empty( $term->name ) ) {
            /* translators: yazar/kategori arşivi için otomatik açıklama */
            return sprintf( '%s — book summaries and deep analyses on %s.', $term->name, get_bloginfo( 'name' ) );
        }
    }
    return get_bloginfo( 'description' );
}

// -----------------------------------------------------
// Open Graph + Twitter Card
// -----------------------------------------------------
function thetelos_seo_og_tags() {
    if ( thetelos_seo_plugin_active() ) {
        return;
    }

    $title = wp_get_document_title();
    $desc  = thetelos_seo_get_description();
    $type  = is_singular() ? 'article' : 'website';

    if ( is_singular() ) {
        $url = get_permalink();
    } elseif ( is_tax() || is_category() || is_tag() ) {
        $url = get_term_link( get_queried_object() );
        if ( is_wp_error( $url ) ) { $url = home_url( '/' ); }
    } else {
        $url = home_url( add_query_arg( null, null ) );
    }

    $image = '';
    if ( is_singular() && has_post_thumbnail() ) {
        $image = get_the_post_thumbnail_url( get_the_ID(), 'large' );
    }

    echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
    echo '<meta property="og:type" content="' . esc_attr( $type ) . '" />' . "\n";
    echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
    echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
    if ( $image ) {
        echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
    }
    if ( is_singular() ) {
        echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '" />' . "\n";
        echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '" />' . "\n";
    }

    echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '" />' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '" />' . "\n";
    if ( $image ) {
        echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
    }
}
add_action( 'wp_head', 'thetelos_seo_og_tags', 2 );

// -----------------------------------------------------
// JSON-LD: Book şeması verisi (kitap = standart post)
// -----------------------------------------------------
function thetelos_seo_book_schema( $post_id ) {
    $book = [
        '@type' => 'Book',
        'name'  => get_the_title( $post_id ),
        'url'   => get_permalink( $post_id ),
    ];

    $authors = get_the_terms( $post_id, 'authors' );
    if ( ! empty( $authors ) && ! is_wp_error( $authors ) ) {
        $book['author'] = [
            '@type' => 'Person',
            'name'  => $authors[0]->name,
            'url'   => get_term_link( $authors[0] ),
        ];
    }

    $image = get_the_post_thumbnail_url( $post_id, 'large' );
    if ( $image ) {
        $book['image'] = $image;
    }

    return $book;
}

// -----------------------------------------------------
// JSON-LD çıktısı (sayfa tipine göre)
// -----------------------------------------------------
function thetelos_seo_jsonld() {
    $schema = null;

    if ( is_singular( 'post' ) ) {
        // Kitap sayfası → Book
        $schema = thetelos_seo_book_schema( get_the_ID() );
        $schema['@context'] = 'https://schema.org';
        $desc = thetelos_seo_get_description();
        if ( $desc ) {
            $schema['description'] = $desc;
        }

    } elseif ( is_singular( 'analysis' ) ) {
        // Analiz sayfası → Article (about: Book)
        $schema = [
            '@context'      => 'https://schema.org',
            '@type'         => 'Article',
            'headline'      => get_the_title(),
            'url'           => get_permalink(),
            'datePublished' => get_the_date( 'c' ),
            'dateModified'  => get_the_modified_date( 'c' ),
            'publisher'     => [
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
                'url'   => home_url( '/' ),
            ],
        ];
        $related_id = (int) get_post_meta( get_the_ID(), '_related_book_id', true );
        if ( $related_id ) {
            $schema['about'] = thetelos_seo_book_schema( $related_id );
            if ( isset( $schema['about']['author'] ) ) {
                $schema['author'] = $schema['about']['author'];
            }
        }

    } elseif ( is_tax( 'authors' ) ) {
        // Yazar sayfası → ProfilePage + Person
        $term = get_queried_object();
        if ( $term && ! is_wp_error( $term ) ) {
            $schema = [
                '@context'   => 'https://schema.org',
                '@type'      => 'ProfilePage',
                'mainEntity' => [
                    '@type' => 'Person',
                    'name'  => $term->name,
                    'url'   => get_term_link( $term ),
                ],
            ];
            $desc = strip_tags( term_description() );
            if ( $desc ) {
                $schema['mainEntity']['description'] = wp_trim_words( $desc, 40, '…' );
            }
        }
    }

    if ( $schema ) {
        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }
}
add_action( 'wp_head', 'thetelos_seo_jsonld', 3 );

// -----------------------------------------------------
// JSON-LD: BreadcrumbList (SEO eklentisi yoksa)
// Anasayfa → Yazar → Kitap  /  Anasayfa → Kitap → Analiz
// -----------------------------------------------------
function thetelos_seo_breadcrumb_jsonld() {
    if ( thetelos_seo_plugin_active() || ! is_singular( [ 'post', 'analysis' ] ) ) {
        return;
    }

    $items = [ [
        '@type'    => 'ListItem',
        'position' => 1,
        'name'     => get_bloginfo( 'name' ),
        'item'     => home_url( '/' ),
    ] ];

    if ( is_singular( 'post' ) ) {
        $authors = get_the_terms( get_the_ID(), 'authors' );
        if ( ! empty( $authors ) && ! is_wp_error( $authors ) ) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => $authors[0]->name,
                'item'     => get_term_link( $authors[0] ),
            ];
        }
    } else {
        $related_id = (int) get_post_meta( get_the_ID(), '_related_book_id', true );
        if ( $related_id ) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => get_the_title( $related_id ),
                'item'     => get_permalink( $related_id ),
            ];
        }
    }

    $items[] = [
        '@type'    => 'ListItem',
        'position' => count( $items ) + 1,
        'name'     => get_the_title(),
        'item'     => get_permalink(),
    ];

    $schema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    ];

    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
add_action( 'wp_head', 'thetelos_seo_breadcrumb_jsonld', 4 );
