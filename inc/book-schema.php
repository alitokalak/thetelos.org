<?php
/**
 * Book Schema Integration (Yoast SEO)
 * -----------------------------------------------------------------------------
 * Bu site bir kitap özeti sitesi; ancak Yoast tek başına bir yazıyı yalnızca
 * "Article" olarak işaretler — sayfanın bir KİTAP hakkında olduğunu bilmez.
 *
 * Burada Yoast'ın RESMİ `wpseo_schema_graph` filtresine bağlanarak, mevcut
 * şema grafiğinin İÇİNE bir "Book" (ve yazarı için "Person") düğümü ekliyoruz
 * ve sayfayı bu kitabın "about" / "mainEntity"si olarak işaretliyoruz.
 *
 * Neden bu yöntem: Ayrı bir <script type="application/ld+json"> bloğu basıp
 * Yoast ile yarışmıyoruz. Tek, temiz bir @graph kalır → çakışma yok, Yoast
 * güncellense de bozulmaz, Google'ın "şema görünen içerikle uyuşmalı" kuralına
 * uyar (kitap adı + yazar zaten sayfada görünüyor).
 *
 * Yoast aktif değilse bu filtre hiç çalışmaz; site eski hâline döner (güvenli).
 *
 * @package Mediumish / TheTelos
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Görünen yazı başlığını şema için temizler:
 * yazar adını, parantez içini ve sondaki tireyi atar.
 * (Kapak üreticisindeki temizleme mantığıyla tutarlı.)
 */
function thetelos_schema_clean_book_title( $post_id, $author = '' ) {
    $title = get_the_title( $post_id );

    if ( $author ) {
        $title = str_ireplace( $author, '', $title );
    }

    $title = preg_replace( '/\s*[\(\(].*?[\)\)]/u', '', $title ); // parantez içi
    $title = preg_replace( '/\s*[-–—]\s*$/u', '', $title );        // sondaki tire
    $title = preg_replace( '/^\s*[-–—]\s*/u', '', $title );        // baştaki tire

    return trim( $title );
}

/**
 * Tekil kitap yazılarında Yoast grafiğine Book + Person düğümü ekler.
 *
 * @param array $graph   Yoast'ın şema düğümleri dizisi.
 * @param mixed $context Yoast Meta_Tags_Context nesnesi.
 * @return array
 */
function thetelos_add_book_schema( $graph, $context = null ) {

    // Yalnızca tekil "post" (kitap) sayfaları.
    if ( ! is_singular( 'post' ) ) {
        return $graph;
    }

    $post_id = 0;
    if ( is_object( $context ) && ! empty( $context->id ) ) {
        $post_id = (int) $context->id;
    }
    if ( ! $post_id ) {
        $post_id = (int) get_the_ID();
    }
    if ( ! $post_id ) {
        return $graph;
    }

    // Bir yazıyı yalnızca "authors" taksonomisinde bir yazarı varsa KİTAP sayarız.
    // Böylece kitap olmayan normal yazılar yanlışlıkla Book olarak işaretlenmez.
    $author_terms = get_the_terms( $post_id, 'authors' );
    if ( empty( $author_terms ) || is_wp_error( $author_terms ) ) {
        return $graph;
    }
    $author_name = $author_terms[0]->name;

    $permalink = get_permalink( $post_id );
    if ( ! $permalink ) {
        return $graph;
    }

    $book_id   = $permalink . '#/schema/book';
    $author_id = $permalink . '#/schema/person/author';

    $book_title = thetelos_schema_clean_book_title( $post_id, $author_name );
    if ( '' === $book_title ) {
        $book_title = get_the_title( $post_id );
    }

    // --- Yazar (Person) düğümü ---
    $author_node = array(
        '@type' => 'Person',
        '@id'   => $author_id,
        'name'  => $author_name,
    );
    $author_link = get_term_link( $author_terms[0] );
    if ( ! is_wp_error( $author_link ) ) {
        $author_node['url'] = $author_link;
    }

    // --- Kitap (Book) düğümü ---
    $book_node = array(
        '@type'  => 'Book',
        '@id'    => $book_id,
        'name'   => $book_title,
        'author' => array( '@id' => $author_id ),
        'url'    => $permalink,
    );

    // Tür: birincil kategori.
    $cats = get_the_category( $post_id );
    if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
        $book_node['genre'] = $cats[0]->name;
    }

    // Kapak: öne çıkan görsel (varsa).
    if ( has_post_thumbnail( $post_id ) ) {
        $img = wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'full' );
        if ( $img ) {
            $book_node['image'] = $img;
        }
    }

    // Düğümleri grafiğe ekle.
    $graph[] = $author_node;
    $graph[] = $book_node;

    // WebPage düğümünü bu kitabın "about" / "mainEntity"si olarak işaretle.
    foreach ( $graph as &$node ) {
        if ( empty( $node['@type'] ) ) {
            continue;
        }
        $types = (array) $node['@type'];
        if ( in_array( 'WebPage', $types, true ) ) {
            $node['about'] = array( '@id' => $book_id );
            if ( empty( $node['mainEntity'] ) ) {
                $node['mainEntity'] = array( '@id' => $book_id );
            }
            break;
        }
    }
    unset( $node );

    return $graph;
}
add_filter( 'wpseo_schema_graph', 'thetelos_add_book_schema', 11, 2 );
