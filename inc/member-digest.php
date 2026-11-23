<?php
/**
 * member-digest.php — Üyelere KİŞİSEL okuma önerisi maili (elle, panelden gönderilir).
 *
 * Öneri, üyenin kendi verisinden hesaplanır (yapay zekâ yok, maliyet sıfır):
 *   - "Okudum / Okuyorum / Okumak istiyorum" işaretlediği kitapların kategorileri,
 *   - kaydettiği özetlerin kategorileri,
 *   - seçtiği ilgi alanları (_tls_interests → tls_interest_areas() eşleşme kelimeleri).
 * Bu kategorilerden, üyenin henüz işaretlemediği/kaydetmediği, Google'dan gizlenmemiş,
 * yer tutucu olmayan ve UZUN (≥2.000 kelime) özetler seçilir (aynı yazardan en çok 2).
 * Ayrıca "okumak istiyorum" deyip başlamadığı kitaplar hatırlatılır.
 *
 * Her mailde abonelikten çıkış linki var (?tls_unsub=UID&k=İMZA) → _tls_digest_optout.
 * Panel: thetelos-panel/member-digest.php (+ api/member-digest.php).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tls_digest_unsub_key( $uid ) { return substr( wp_hash( 'tls-unsub|' . (int) $uid ), 0, 16 ); }
function tls_digest_unsub_url( $uid ) {
    return add_query_arg( [ 'tls_unsub' => (int) $uid, 'k' => tls_digest_unsub_key( $uid ) ], home_url( '/' ) );
}
function tls_digest_optout( $uid ) { return (bool) get_user_meta( $uid, '_tls_digest_optout', true ); }

/* Abonelikten çıkış linki */
add_action( 'init', function () {
    if ( ! isset( $_GET['tls_unsub'], $_GET['k'] ) ) return;
    $uid = (int) $_GET['tls_unsub'];
    if ( $uid > 0 && hash_equals( tls_digest_unsub_key( $uid ), (string) $_GET['k'] ) && get_userdata( $uid ) ) {
        update_user_meta( $uid, '_tls_digest_optout', current_time( 'mysql' ) );
        wp_die( '<h2>You have been unsubscribed.</h2><p>You will no longer receive reading recommendations from The Telos.</p><p><a href="' . esc_url( home_url( '/' ) ) . '">Back to The Telos</a></p>', 'Unsubscribed', [ 'response' => 200 ] );
    }
    wp_die( 'Invalid unsubscribe link.', 'Unsubscribe', [ 'response' => 400 ] );
}, 1 );

/* Yazının gerçek kelime sayısı (önbellekli). */
function tls_digest_words( $pid ) {
    $w = get_post_meta( $pid, '_tls_word_count', true );
    if ( $w === '' ) {
        $w = str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $pid ) ) );
        update_post_meta( $pid, '_tls_word_count', $w );
    }
    return (int) $w;
}

function tls_digest_post_ok( $pid ) {
    if ( get_post_status( $pid ) !== 'publish' || get_post_type( $pid ) !== 'post' ) return false;
    if ( function_exists( 'tls_is_pruned' ) && tls_is_pruned( $pid ) ) return false;
    if ( function_exists( 'tls_is_placeholder_post' ) && tls_is_placeholder_post( $pid ) ) return false;
    return true;
}

/* Başlıktaki "Kitap – Yazar" kalıbından yazar (aynı yazardan en çok 2 öneri için). */
function tls_digest_title_author( $pid ) {
    $t = html_entity_decode( get_the_title( $pid ), ENT_QUOTES, 'UTF-8' );
    return preg_match( '/\s[–—-]\s([^–—]+)$/u', $t, $m ) ? trim( $m[1] ) : '';
}

/**
 * Bir üye için öneri verisi.
 * @return array ['uid','name','email','optout','reminders'=>[pid],'recs'=>[pid],'basis'=>[metin],'has_data'=>bool]
 */
function tls_digest_build( $uid, $limit = 5 ) {
    $u = get_userdata( $uid );
    if ( ! $u ) return null;
    $act   = function_exists( 'tls_user_activity' ) ? tls_user_activity( $uid ) : [ 'items' => [] ];
    $saved = function_exists( 'tls_get_saved_summaries' ) ? (array) tls_get_saved_summaries( $uid ) : [];

    $exclude = []; $catw = []; $basis = []; $want = [];
    $w_by_status = [ 'read' => 3, 'reading' => 3, 'want' => 2 ];
    foreach ( (array) $act['items'] as $it ) {
        $pid = (int) $it['pid']; $exclude[ $pid ] = 1;
        if ( $it['status'] === 'want' && tls_digest_post_ok( $pid ) ) $want[] = $pid;
        foreach ( wp_get_post_categories( $pid ) as $c ) $catw[ $c ] = ( $catw[ $c ] ?? 0 ) + ( $w_by_status[ $it['status'] ] ?? 1 );
    }
    foreach ( $saved as $pid ) {
        $pid = (int) $pid; $exclude[ $pid ] = 1;
        foreach ( wp_get_post_categories( $pid ) as $c ) $catw[ $c ] = ( $catw[ $c ] ?? 0 ) + 1;
    }
    if ( $act['items'] ) $basis[] = count( $act['items'] ) . ' işaretli kitap';
    if ( $saved )        $basis[] = count( $saved ) . ' kayıtlı özet';

    // İlgi alanları → kategori adı/slug eşleşmesi
    $ints = get_user_meta( $uid, '_tls_interests', true );
    $ints = is_array( $ints ) ? array_values( array_filter( $ints ) ) : [];
    if ( $ints && function_exists( 'tls_interest_areas' ) ) {
        $areas = tls_interest_areas(); $words = [];
        foreach ( $ints as $slug ) if ( isset( $areas[ $slug ] ) ) $words = array_merge( $words, $areas[ $slug ]['match'] );
        if ( $words ) {
            foreach ( get_categories( [ 'hide_empty' => true ] ) as $cat ) {
                $hay = mb_strtolower( $cat->name . ' ' . $cat->slug );
                foreach ( $words as $wd ) if ( strpos( $hay, $wd ) !== false ) { $catw[ $cat->term_id ] = ( $catw[ $cat->term_id ] ?? 0 ) + 2; break; }
            }
            $basis[] = count( $ints ) . ' ilgi alanı';
        }
    }
    unset( $catw[ (int) get_option( 'default_category' ) ] );   // "Uncategorized" öneri sebebi olmasın
    arsort( $catw );
    $topcats = array_slice( array_keys( $catw ), 0, 10 );

    $q = [ 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 150, 'fields' => 'ids',
           'no_found_rows' => true, 'post__not_in' => array_keys( $exclude ), 'orderby' => 'date', 'order' => 'DESC' ];
    if ( $topcats ) $q['category__in'] = $topcats;
    $cands = get_posts( $q );

    $scored = [];
    foreach ( $cands as $pid ) {
        if ( ! tls_digest_post_ok( $pid ) ) continue;
        $wc = tls_digest_words( $pid );
        if ( $wc < 2000 ) continue;                                   // yalnız dolu, uzun özetler
        $s = 0; foreach ( wp_get_post_categories( $pid ) as $c ) $s += $catw[ $c ] ?? 0;
        $s += min( 3, $wc / 2000 );                                   // uzunluk küçük bonus
        $s += min( 2, (int) get_post_meta( $pid, 'post_views_count', true ) / 50 );   // okunan biraz öne
        $scored[ $pid ] = $s;
    }
    arsort( $scored );
    $recs = []; $per_author = [];
    foreach ( array_keys( $scored ) as $pid ) {
        $a = tls_digest_title_author( $pid );
        if ( $a !== '' && ( $per_author[ $a ] ?? 0 ) >= 2 ) continue;
        $per_author[ $a ] = ( $per_author[ $a ] ?? 0 ) + 1;
        $recs[] = $pid;
        if ( count( $recs ) >= $limit ) break;
    }
    return [
        'uid' => (int) $uid, 'name' => $u->display_name ?: $u->user_login, 'email' => $u->user_email,
        'optout' => tls_digest_optout( $uid ), 'reminders' => array_slice( $want, 0, 3 ), 'recs' => $recs,
        'basis' => $basis, 'has_data' => ( (bool) $act['items'] || (bool) $saved || (bool) $ints ),
        'last_sent' => (string) get_user_meta( $uid, '_tls_digest_last_sent', true ),
    ];
}

/* Mail HTML'i (İngilizce — site dili). */
function tls_digest_html( array $d ) {
    $esc = function ( $s ) { return esc_html( html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) ); };
    $item = function ( $pid ) use ( $esc ) {
        $mins = max( 1, (int) round( tls_digest_words( $pid ) / 200 ) );
        $ex   = wp_trim_words( get_the_excerpt( $pid ), 32 );
        $url  = esc_url( get_permalink( $pid ) );
        // KAPAK: gerçek kitap kapağı (dikey öne çıkan görsel) varsa o; yoksa ya da görsel yatay/kare
        // (otomatik üretilmiş, mailde bozuk görünüyor) ise mailde güvenle çalışan HTML kapak kutusu
        // — sitedeki kapakla aynı renk paleti, başlık + yazar.
        $img = ''; $tid = get_post_thumbnail_id( $pid );
        if ( $tid ) {
            $src = wp_get_attachment_image_src( $tid, 'medium' );
            if ( $src && (int) $src[2] > (int) $src[1] * 1.15 ) $img = $src[0];   // yalnız dikey = gerçek kapak
        }
        if ( $img ) {
            $cell = '<td width="84" valign="top" style="padding:14px 14px 14px 0;border-bottom:1px solid #eee">'
                  . '<a href="' . $url . '"><img src="' . esc_url( $img ) . '" width="76" alt="" '
                  . 'style="display:block;border-radius:4px;border:1px solid #e5e5e5;width:76px;height:auto"></a></td>';
        } else {
            $cats = get_the_category( $pid );
            $pal  = function_exists( 'thetelos_get_category_palette' )
                  ? thetelos_get_category_palette( $cats ? $cats[0]->slug : 'philosophy' ) : [ '#1C2B3A', '#C8A165', '#F5EFE6', '#A89070' ];
            $au   = function_exists( 'thetelos_get_book_author' ) ? thetelos_get_book_author( $pid ) : tls_digest_title_author( $pid );
            $bt   = html_entity_decode( get_post_field( 'post_title', $pid ), ENT_QUOTES, 'UTF-8' );
            $bt   = trim( preg_replace( [ '/\s*[\(\（].*$/u', '/\s+[-–—]\s+\S.*$/u' ], '', $bt ) );
            if ( mb_strlen( $bt ) > 48 ) { $bt = mb_substr( $bt, 0, 46 ); $bt = rtrim( mb_substr( $bt, 0, (int) ( mb_strrpos( $bt, ' ' ) ?: 46 ) ), ' ,;:' ) . '…'; }
            $cell = '<td width="84" valign="top" style="padding:14px 14px 14px 0;border-bottom:1px solid #eee">'
                  . '<a href="' . $url . '" style="text-decoration:none">'
                  . '<table width="76" cellpadding="0" cellspacing="0" style="width:76px;background:' . $pal[0] . ';border:2px solid ' . $pal[1] . ';border-radius:4px">'
                  . '<tr><td height="104" align="center" valign="middle" style="height:104px;padding:6px 5px">'
                  . '<div style="font:7px/1.2 Arial,sans-serif;letter-spacing:1px;text-transform:uppercase;color:' . $pal[3] . ';margin-bottom:6px">' . $esc( $au ) . '</div>'
                  . '<div style="font:italic 10px/1.25 Georgia,serif;color:' . $pal[2] . '">' . $esc( $bt ) . '</div>'
                  . '</td></tr></table></a></td>';
        }
        return '<tr>' . $cell . '<td valign="top" style="padding:14px 0;border-bottom:1px solid #eee">'
             . '<a href="' . $url . '" style="font:600 17px Georgia,serif;color:#1a1a1a;text-decoration:none;word-break:break-word">' . $esc( get_the_title( $pid ) ) . '</a>'
             . '<div style="font:13px/1.5 Arial,sans-serif;color:#555;margin-top:6px">' . $esc( $ex ) . '</div>'
             . '<div style="font:12px Arial,sans-serif;color:#1f7a3d;margin-top:6px">' . $mins . ' min read</div></td></tr>';
    };
    $h  = '<div style="max-width:600px;margin:0 auto;padding:24px;background:#fff">';
    $h .= '<div style="font:700 26px Georgia,serif;color:#111;margin-bottom:18px">thetelos</div>';
    $h .= '<p style="font:15px/1.6 Arial,sans-serif;color:#222">Hi ' . $esc( $d['name'] ) . ',</p>';
    $h .= '<p style="font:15px/1.6 Arial,sans-serif;color:#222">Based on the books you have read, saved and marked on The Telos, here are a few summaries we think you will enjoy.</p>';
    if ( $d['recs'] ) {
        $h .= '<h3 style="font:600 16px Arial,sans-serif;color:#111;margin:22px 0 4px">Picked for you</h3><table width="100%" cellpadding="0" cellspacing="0">';
        foreach ( $d['recs'] as $pid ) $h .= $item( $pid );
        $h .= '</table>';
    }
    if ( $d['reminders'] ) {
        $h .= '<h3 style="font:600 16px Arial,sans-serif;color:#111;margin:26px 0 4px">Still on your “Want to Read” list</h3><table width="100%" cellpadding="0" cellspacing="0">';
        foreach ( $d['reminders'] as $pid ) $h .= $item( $pid );
        $h .= '</table>';
    }
    $h .= '<p style="font:15px/1.6 Arial,sans-serif;color:#222;margin-top:24px">Happy reading,<br>The Telos</p>';
    $h .= '<p style="font:11px/1.5 Arial,sans-serif;color:#999;margin-top:30px;border-top:1px solid #eee;padding-top:12px">You are receiving this because you have an account at thetelos.org. '
        . '<a href="' . esc_url( tls_digest_unsub_url( $d['uid'] ) ) . '" style="color:#999">Unsubscribe</a></p></div>';
    return $h;
}

/**
 * Gönder. $to_override verilirse (test) mail o adrese gider ve "son gönderim" kaydedilmez.
 * @return array ['ok'=>bool,'error'=>string]
 */
function tls_digest_send( $uid, $to_override = '' ) {
    $d = tls_digest_build( $uid );
    if ( ! $d ) return [ 'ok' => false, 'error' => 'üye yok' ];
    if ( $to_override === '' && $d['optout'] ) return [ 'ok' => false, 'error' => 'abonelikten çıkmış' ];
    if ( ! $d['recs'] && ! $d['reminders'] ) return [ 'ok' => false, 'error' => 'önerilecek kitap bulunamadı' ];
    $to = $to_override !== '' ? $to_override : $d['email'];
    if ( ! is_email( $to ) ) return [ 'ok' => false, 'error' => 'geçersiz e-posta' ];
    $subject = ( $to_override !== '' ? '[TEST] ' : '' ) . 'Your reading picks from The Telos';
    $ok = wp_mail( $to, $subject, tls_digest_html( $d ), [
        'Content-Type: text/html; charset=UTF-8',
        'List-Unsubscribe: <' . tls_digest_unsub_url( $uid ) . '>',
    ] );
    if ( $ok && $to_override === '' ) update_user_meta( $uid, '_tls_digest_last_sent', current_time( 'mysql' ) );
    return [ 'ok' => (bool) $ok, 'error' => $ok ? '' : 'mail gönderilemedi (SMTP)' ];
}

/* Mail gidebilecek üyeler: yönetici/yazar dışındaki tüm hesaplar. */
function tls_digest_member_ids() {
    return get_users( [ 'fields' => 'ID', 'role__not_in' => [ 'administrator', 'editor', 'author' ], 'number' => 2000 ] );
}
