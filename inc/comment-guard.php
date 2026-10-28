<?php
/**
 * The Telos — Yorum güvenliği
 *
 * - "Website" alanı yok; gönderilse bile adres kaydedilmez ve gösterilmez.
 * - Bot tuzağı: gizli alan (insan görmez, bot doldurur) + JavaScript işareti
 *   (form JS ile doldurulmadan gönderilirse reddedilir; çoğu spam botu JS çalıştırmaz).
 * - Yorumda link yasak; HTML temizlenir; aşırı uzun yorum reddedilir.
 * - Pingback/trackback kapalı.
 * Giriş yapmış kullanıcılar bot kontrollerinden muaf.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* Website alanını tüm formlardan kaldır. */
add_filter( 'comment_form_default_fields', function ( $f ) { unset( $f['url'] ); return $f; }, 99 );
add_filter( 'comment_form_fields', function ( $f ) { unset( $f['url'] ); return $f; }, 99 );

/* Yazar adresi hiçbir durumda kaydedilmez/gösterilmez. */
add_filter( 'pre_comment_author_url', '__return_empty_string' );
add_filter( 'get_comment_author_url', '__return_empty_string' );

/* Pingback/trackback kapalı. */
add_filter( 'pings_open', '__return_false', 99 );
add_filter( 'xmlrpc_methods', function ( $m ) { unset( $m['pingback.ping'], $m['pingback.extensions.getPingbacks'] ); return $m; } );

/* Forma tuzak alanları ekle. */
add_action( 'comment_form', function () {
    echo '<p style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">'
       . '<label>Leave this empty<input type="text" name="tls_hp" value="" tabindex="-1" autocomplete="off"></label></p>'
       . '<input type="hidden" name="tls_js" value="">'
       . '<script>(function(){var f=document.getElementById("commentform")||document.querySelector("form.comment-form");'
       . 'if(!f)return;var t=Date.now(),s=function(){var e=f.querySelector("[name=tls_js]");if(e)e.value="ok-"+Math.round((Date.now()-t)/1000);};'
       . 'f.addEventListener("input",s,{once:true});f.addEventListener("submit",s);})();</script>';
} );

add_filter( 'preprocess_comment', function ( $c ) {
    $type = $c['comment_type'] ?? '';
    if ( in_array( $type, [ 'pingback', 'trackback' ], true ) ) {
        wp_die( 'Pingbacks are disabled.', 403 );
    }
    $c['comment_author_url'] = '';
    $c['comment_content']    = trim( wp_strip_all_tags( (string) ( $c['comment_content'] ?? '' ) ) );

    if ( ! is_user_logged_in() ) {
        // Bot tuzağı dolduruldu ya da JS hiç çalışmadı → bot.
        if ( ! empty( $_POST['tls_hp'] ) || strpos( (string) ( $_POST['tls_js'] ?? '' ), 'ok-' ) !== 0 ) {
            wp_die( 'Your comment could not be submitted. Please enable JavaScript and try again.', 'Comment blocked', [ 'response' => 403, 'back_link' => true ] );
        }
    }
    $txt = $c['comment_content'] . ' ' . ( $c['comment_author'] ?? '' );
    if ( preg_match( '~https?://|www\.|\b[a-z0-9-]+\.(com|net|org|ru|info|xyz|top|pl|io|biz|online|site|shop|click)\b|\[url|<a\s~i', $txt ) ) {
        wp_die( 'Links are not allowed in comments.', 'Comment blocked', [ 'response' => 403, 'back_link' => true ] );
    }
    if ( mb_strlen( $c['comment_content'] ) > 3000 ) {
        wp_die( 'Your comment is too long (max 3000 characters).', 'Comment blocked', [ 'response' => 403, 'back_link' => true ] );
    }
    return $c;
}, 1 );
