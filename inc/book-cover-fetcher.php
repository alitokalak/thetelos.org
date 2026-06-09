<?php
/**
 * Book Cover Fetcher — Gutenberg Sidebar Panel
 * Post editörünün sağ paneline Google Books + Open Library kapak arama ekler.
 * Seçilen kapak WP medya kütüphanesine indirilir ve featured image olarak ayarlanır.
 *
 * @package Mediumish / TheTelos
 */

// -------------------------------------------------------
// Gutenberg sidebar JS'ini enqueue et
// -------------------------------------------------------
add_action( 'enqueue_block_editor_assets', 'thetelos_cover_fetcher_enqueue' );

function thetelos_cover_fetcher_enqueue() {
    $screen = get_current_screen();
    if ( ! $screen || ! $screen->is_block_editor() || $screen->post_type !== 'post' ) return;

    $js_path = get_template_directory() . '/inc/book-cover-fetcher.js';
    $version = file_exists( $js_path ) ? filemtime( $js_path ) : '1.2';

    wp_enqueue_script(
        'thetelos-cover-fetcher',
        get_template_directory_uri() . '/inc/book-cover-fetcher.js',
        [ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data', 'wp-components' ],
        $version,
        true
    );

    global $post;

    wp_localize_script( 'thetelos-cover-fetcher', 'tcfData', [
        'nonce'  => wp_create_nonce( 'thetelos_cover_fetcher' ),
        'postId' => $post ? (int) $post->ID : 0,
    ] );
}

// -------------------------------------------------------
// AJAX: Google Books + Open Library'den kapakları getir
// -------------------------------------------------------
add_action( 'wp_ajax_thetelos_fetch_book_covers', 'thetelos_ajax_fetch_book_covers' );

function thetelos_ajax_fetch_book_covers() {
    if ( ! check_ajax_referer( 'thetelos_cover_fetcher', 'nonce', false ) ) {
        wp_send_json_error( 'Invalid nonce', 403 );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }

    $query  = isset( $_POST['q'] )      ? sanitize_text_field( wp_unslash( $_POST['q'] ) )      : '';
    $author = isset( $_POST['author'] ) ? sanitize_text_field( wp_unslash( $_POST['author'] ) ) : '';

    if ( strlen( $query ) < 2 ) {
        wp_send_json_error( 'Query too short' );
    }

    // ── Sonucu skorla ─────────────────────────────────────────────────
    // Başlıkta kitap kelimesi varsa +2, yazar adı varsa +3 (birlikte bulunursa en yüksek skor)
    $score_result = function( $result_title, $result_authors ) use ( $query, $author ) {
        $score = 0;
        $rt    = strtolower( $result_title );
        $ra    = strtolower( implode( ' ', (array) $result_authors ) );

        $book_words = array_filter( explode( ' ', strtolower( $query ) ), fn($w) => strlen($w) > 3 );
        foreach ( $book_words as $w ) {
            if ( strpos( $rt, $w ) !== false ) { $score += 2; break; }
        }

        if ( $author ) {
            $auth_words = array_filter( explode( ' ', strtolower( $author ) ), fn($w) => strlen($w) > 2 );
            foreach ( $auth_words as $w ) {
                if ( strpos( $rt, $w ) !== false || strpos( $ra, $w ) !== false ) { $score += 3; break; }
            }
        }

        return $score;
    };

    $raw_results = [];

    // ── Sorgu sırası: en spesifikten en genele ─────────────────────────
    $queries_to_try = array_filter( [
        // 1. intitle + inauthor — hem kitap hem yazar geçiyorsa ideal
        $author ? ( 'intitle:' . $query . ' inauthor:' . $author ) : '',
        // 2. intitle + yazar serbest metin
        $author ? ( 'intitle:' . $query . ' ' . $author ) : '',
        // 3. Sadece intitle
        'intitle:' . $query,
        // 4. Serbest metin (en geniş, fallback)
        $query,
    ] );

    foreach ( $queries_to_try as $q ) {
        if ( count( $raw_results ) >= 8 ) break;

        $google_url = 'https://www.googleapis.com/books/v1/volumes?' . http_build_query( [
            'q'          => $q,
            'maxResults' => 6,
            'printType'  => 'books',
            'fields'     => 'items(volumeInfo(title,authors,imageLinks))',
        ] );

        $response = wp_remote_get( $google_url, [ 'timeout' => 8 ] );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) continue;

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        foreach ( $body['items'] ?? [] as $item ) {
            $info   = $item['volumeInfo'] ?? [];
            $links  = $info['imageLinks'] ?? [];
            $cover  = $links['thumbnail'] ?? ( $links['smallThumbnail'] ?? '' );
            if ( ! $cover ) continue;

            $cover = str_replace( 'http://', 'https://', $cover );
            $cover = str_replace( '&edge=curl', '', $cover );
            // Yüksek çözünürlük
            $cover = preg_replace( '/zoom=\d/', 'zoom=3', $cover );

            // Duplicate kontrolü
            $already = array_filter( $raw_results, fn($r) => $r['cover'] === $cover );
            if ( $already ) continue;

            $result_title   = $info['title']   ?? '';
            $result_authors = $info['authors'] ?? [];

            $raw_results[] = [
                'cover'  => esc_url_raw( $cover ),
                'title'  => sanitize_text_field( $result_title ),
                'author' => sanitize_text_field( implode( ', ', array_slice( $result_authors, 0, 2 ) ) ),
                'source' => 'google',
                'score'  => $score_result( $result_title, $result_authors ),
            ];
        }
    }

    // ── Skora göre sırala ─────────────────────────────────────────────
    usort( $raw_results, fn($a, $b) => $b['score'] - $a['score'] );

    $results = array_map(
        fn($r) => [ 'cover' => $r['cover'], 'title' => $r['title'], 'author' => $r['author'], 'source' => $r['source'] ],
        array_slice( $raw_results, 0, 6 )
    );

    // ── Open Library fallback ─────────────────────────────────────────
    if ( count( $results ) < 3 ) {
        $ol_q   = $query . ( $author ? ' ' . $author : '' );
        $ol_url = 'https://openlibrary.org/search.json?' . http_build_query( [
            'q'      => $ol_q,
            'limit'  => 6,
            'fields' => 'key,title,author_name,cover_i',
        ] );

        $ol_response = wp_remote_get( $ol_url, [ 'timeout' => 8 ] );
        if ( ! is_wp_error( $ol_response ) && 200 === wp_remote_retrieve_response_code( $ol_response ) ) {
            $ol_body = json_decode( wp_remote_retrieve_body( $ol_response ), true );
            foreach ( $ol_body['docs'] ?? [] as $doc ) {
                if ( empty( $doc['cover_i'] ) ) continue;
                $results[] = [
                    'cover'  => 'https://covers.openlibrary.org/b/id/' . (int) $doc['cover_i'] . '-L.jpg',
                    'title'  => sanitize_text_field( $doc['title'] ?? '' ),
                    'author' => sanitize_text_field( implode( ', ', array_slice( $doc['author_name'] ?? [], 0, 2 ) ) ),
                    'source' => 'openlibrary',
                ];
                if ( count( $results ) >= 6 ) break;
            }
        }
    }

    if ( empty( $results ) ) {
        wp_send_json_error( 'No covers found' );
    }

    wp_send_json_success( $results );
}

// -------------------------------------------------------
// AJAX: Kapağı indir → medya kütüphanesi → featured image
// -------------------------------------------------------
add_action( 'wp_ajax_thetelos_set_book_cover', 'thetelos_ajax_set_book_cover' );

function thetelos_ajax_set_book_cover() {
    if ( ! check_ajax_referer( 'thetelos_cover_fetcher', 'nonce', false ) ) {
        wp_send_json_error( 'Invalid nonce', 403 );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }

    $post_id   = isset( $_POST['post_id'] )   ? absint( $_POST['post_id'] )                               : 0;
    $cover_url = isset( $_POST['cover_url'] ) ? esc_url_raw( wp_unslash( $_POST['cover_url'] ) )          : '';
    $title     = isset( $_POST['title'] )     ? sanitize_text_field( wp_unslash( $_POST['title'] ) )      : 'Book Cover';

    if ( ! $post_id || ! $cover_url ) {
        wp_send_json_error( 'Missing parameters' );
    }

    // Yalnızca güvenilir hostlardan izin ver
    $allowed_hosts = [ 'books.google.com', 'covers.openlibrary.org', 'lh3.googleusercontent.com', 'lh4.googleusercontent.com' ];
    $host = wp_parse_url( $cover_url, PHP_URL_HOST );
    if ( ! in_array( $host, $allowed_hosts, true ) ) {
        wp_send_json_error( 'Untrusted image source' );
    }

    if ( ! get_post( $post_id ) ) {
        wp_send_json_error( 'Post not found' );
    }

    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_sideload_image( $cover_url, $post_id, $title, 'id' );

    if ( is_wp_error( $attachment_id ) ) {
        wp_send_json_error( $attachment_id->get_error_message() );
    }

    if ( ! set_post_thumbnail( $post_id, $attachment_id ) ) {
        wp_send_json_error( 'Could not set featured image' );
    }

    wp_send_json_success( [
        'attachment_id' => $attachment_id,
        'thumb_src'     => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
    ] );
}
