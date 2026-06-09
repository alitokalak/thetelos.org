<?php
/**
 * The Telos — OG Cover Image Generator v5
 *
 * - Featured image OLAN postlar: Yoast'a bırak, dokunma
 * - Featured image OLMAYAN postlar: kitap kapağı PNG üret, Yoast'a ver
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ─────────────────────────────────────────────────────
   1. Yoast og:image filter — tek müdahale noktası
   Yoast featured image varsa zaten onu döndürür,
   yoksa boş döner — biz sadece boş olduğunda devreye gireriz.
───────────────────────────────────────────────────── */
add_filter( 'wpseo_opengraph_image', function( $url ) {
    if ( ! is_singular( 'post' ) ) return $url;
    // Yoast featured image bulduysa dokunma
    if ( $url ) return $url;
    global $post;
    return thetelos_og_cover_url( (int) $post->ID ) ?: $url;
}, 20 );

/* Yoast yoksa (veya twitter card için) fallback */
add_action( 'wp_head', function() {
    if ( ! is_singular( 'post' ) ) return;
    // Yoast aktifse o halleder, çık
    if ( defined( 'WPSEO_VERSION' ) ) return;

    global $post;
    $post_id = (int) $post->ID;
    $img_url = '';

    if ( has_post_thumbnail( $post_id ) ) {
        $thumb = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'large' );
        if ( $thumb ) $img_url = $thumb[0];
    }
    if ( ! $img_url ) $img_url = thetelos_og_cover_url( $post_id );
    if ( ! $img_url ) return;

    echo '<meta property="og:image" content="' . esc_url( $img_url ) . "\" />\n";
    echo '<meta property="og:image:width" content="1200" />' . "\n";
    echo '<meta property="og:image:height" content="1200" />' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    echo '<meta name="twitter:image" content="' . esc_url( $img_url ) . "\" />\n";
}, 10 );

/* ─────────────────────────────────────────────────────
   2. PNG üretici — kare kitap kapağı (1200×1200)
   Kare format sosyal medyada hem FB hem IG hem Twitter'da iyi görünür.
───────────────────────────────────────────────────── */
function thetelos_og_cover_url( int $post_id ): string {
    $upload   = wp_upload_dir();
    $dir      = $upload['basedir'] . '/thetelos-covers';
    $filename = 'cover-' . $post_id . '-v5.png';
    $filepath = $dir . '/' . $filename;
    $fileurl  = $upload['baseurl'] . '/thetelos-covers/' . $filename;

    if ( file_exists( $filepath ) ) return $fileurl;
    if ( ! function_exists( 'imagecreatetruecolor' ) ) return '';
    wp_mkdir_p( $dir );

    /* ── Renk paleti ── */
    $cats = get_the_category( $post_id );
    $slug = ! empty( $cats ) ? $cats[0]->slug : 'default';
    $pal  = function_exists( 'thetelos_get_category_palette' )
        ? thetelos_get_category_palette( $slug )
        : [ '#1C2B3A', '#C8A165', '#F5EFE6', '#A89070' ];

    [ $bg_r, $bg_g, $bg_b ] = tls_ogc_hex2rgb( $pal[0] ); // koyu zemin
    [ $ac_r, $ac_g, $ac_b ] = tls_ogc_hex2rgb( $pal[1] ); // altın vurgu
    [ $su_r, $su_g, $su_b ] = tls_ogc_hex2rgb( $pal[3] ); // ikincil

    /* ── Canvas: 1200×1200 kare ── */
    $S   = 1200;
    $img = imagecreatetruecolor( $S, $S );
    imageantialias( $img, true );

    /* Renkler */
    $c_bg    = imagecolorallocate( $img, $bg_r, $bg_g, $bg_b );
    $c_gold  = imagecolorallocate( $img, $ac_r, $ac_g, $ac_b );
    $c_sub   = imagecolorallocate( $img, $su_r, $su_g, $su_b );
    $c_white = imagecolorallocate( $img, 255, 255, 255 );
    $c_offwh = imagecolorallocate( $img, 235, 228, 218 ); // krem
    $c_shd   = imagecolorallocate( $img,
        max( 0, $bg_r - 30 ), max( 0, $bg_g - 30 ), max( 0, $bg_b - 30 )
    );

    /* ── Zemin ── */
    imagefill( $img, 0, 0, $c_bg );

    /* ── Köşe dekorasyon çizgileri (kitap kapağı hissi) ── */
    $m = 40; // kenar boşluğu
    // Dış çerçeve
    imagerectangle( $img, $m, $m, $S - $m, $S - $m, $c_gold );
    // İç ince çerçeve
    $m2 = $m + 12;
    imagerectangle( $img, $m2, $m2, $S - $m2, $S - $m2, $c_sub );

    /* ── Fontlar ── */
    $f_serif = tls_ogc_find_font( [
        get_template_directory() . '/assets/fonts/DMSerifDisplay-Italic.ttf',
        get_template_directory() . '/assets/fonts/DMSerifDisplay-Regular.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Italic.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf',
        '/usr/share/fonts/truetype/freefont/FreeSerifItalic.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSerif-Italic.ttf',
    ] );
    $f_sans = tls_ogc_find_font( [
        get_template_directory() . '/assets/fonts/DMSans-Medium.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
    ] );

    /* ── İçerik ── */
    $title  = tls_ogc_strip_meta( get_the_title( $post_id ) );
    $raw_a  = get_the_terms( $post_id, 'authors' );
    $author = ( ! empty( $raw_a ) && ! is_wp_error( $raw_a ) ) ? $raw_a[0]->name : '';
    $cat    = ! empty( $cats ) ? mb_strtoupper( $cats[0]->name, 'UTF-8' ) : '';

    $pad  = $m + 12 + 50; // çerçeve içi padding
    $usable = $S - $pad * 2;

    if ( $f_serif && $f_sans ) {

        /* Kategori — üst orta */
        if ( $cat ) {
            $bbox = imagettfbbox( 22, 0, $f_sans, $cat );
            $cw   = abs( $bbox[2] - $bbox[0] );
            $cx   = (int)( ( $S - $cw ) / 2 );
            imagettftext( $img, 22, 0, $cx, $pad + 30, $c_gold, $f_sans, $cat );
        }

        /* Yatay ince çizgi (kategori altı) */
        $line_y = $pad + 50;
        $line_x1 = (int)( $S * 0.25 );
        $line_x2 = (int)( $S * 0.75 );
        imageline( $img, $line_x1, $line_y, $line_x2, $line_y, $c_sub );

        /* Başlık — orta, büyük, satır kırmalı */
        $title_size = 72;
        // Uzun başlıklarda küçült
        $lines = tls_ogc_wrap_ttf( $title, $f_serif, $title_size, $usable );
        while ( count( $lines ) > 4 && $title_size > 44 ) {
            $title_size -= 4;
            $lines = tls_ogc_wrap_ttf( $title, $f_serif, $title_size, $usable );
        }
        $lh      = (int)( $title_size * 1.3 );
        $total_h = count( $lines ) * $lh;
        // Dikey ortala (kategori ve yazar arasında)
        $title_y = (int)( ( $S - $total_h ) / 2 ) + $lh;

        foreach ( $lines as $i => $line ) {
            $bbox = imagettfbbox( $title_size, 0, $f_serif, $line );
            $lw   = abs( $bbox[2] - $bbox[0] );
            $lx   = (int)( ( $S - $lw ) / 2 );
            imagettftext( $img, $title_size, 0, $lx, $title_y + $i * $lh, $c_white, $f_serif, $line );
        }

        /* Yatay ince çizgi (başlık altı) */
        $line2_y = $title_y + count( $lines ) * $lh + 20;
        imageline( $img, $line_x1, $line2_y, $line_x2, $line2_y, $c_sub );

        /* Yazar — başlık altı, ortalı, italic */
        if ( $author ) {
            $bbox = imagettfbbox( 34, 0, $f_serif, $author );
            $aw   = abs( $bbox[2] - $bbox[0] );
            $ax   = (int)( ( $S - $aw ) / 2 );
            imagettftext( $img, 34, 0, $ax, $line2_y + 56, $c_offwh, $f_serif, $author );
        }

        /* thetelos — alt orta */
        $brand = 'thetelos';
        $bbox  = imagettfbbox( 26, 0, $f_serif, $brand );
        $bw    = abs( $bbox[2] - $bbox[0] );
        $bx    = (int)( ( $S - $bw ) / 2 );
        imagettftext( $img, 26, 0, $bx, $S - $pad - 10, $c_gold, $f_serif, $brand );

    } else {
        /* GD fallback */
        imagestring( $img, 5, $pad, (int)($S/2) - 20, $title,  $c_white );
        imagestring( $img, 3, $pad, (int)($S/2) + 20, $author, $c_offwh );
    }

    imagepng( $img, $filepath, 8 );
    imagedestroy( $img );
    return $fileurl;
}

/* ── Yardımcılar ── */
function tls_ogc_hex2rgb( string $hex ): array {
    $hex = ltrim( $hex, '#' );
    if ( strlen($hex) === 3 ) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    return [ hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2)) ];
}
function tls_ogc_find_font( array $c ): ?string {
    foreach ( $c as $f ) { if ( file_exists($f) ) return $f; }
    return null;
}
function tls_ogc_strip_meta( string $t ): string {
    $t = preg_replace( '/\s*[\(\(].*?[\)\)]/u', '', $t );
    $t = preg_replace( '/\s*[-–—].*$/u',         '', $t );
    return trim( $t );
}
function tls_ogc_wrap_ttf( string $text, string $font, float $size, int $max_w ): array {
    $words = explode( ' ', $text );
    $lines = []; $line = '';
    foreach ( $words as $word ) {
        $test = $line === '' ? $word : $line . ' ' . $word;
        $bbox = imagettfbbox( $size, 0, $font, $test );
        if ( abs( $bbox[2] - $bbox[0] ) > $max_w && $line !== '' ) {
            $lines[] = $line; $line = $word;
        } else { $line = $test; }
    }
    if ( $line !== '' ) $lines[] = $line;
    return $lines;
}

/* ── Cache temizle ── */
add_action( 'save_post', function( int $post_id ) {
    if ( get_post_type($post_id) !== 'post' ) return;
    $upload = wp_upload_dir();
    $f = $upload['basedir'] . '/thetelos-covers/cover-' . $post_id . '-v5.png';
    if ( file_exists($f) ) @unlink($f);
} );
