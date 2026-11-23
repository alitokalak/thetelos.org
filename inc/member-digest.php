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

/* Abonelikten çıkış linki.
   GET (linke tıklama) → yalnız ONAY sayfası; çıkış POST ile olur. Sebep (23.11.2026): spam test
   sitesi ve kurumsal güvenlik tarayıcıları maildeki linkleri otomatik açıyor → üye habersiz çıkmış
   sayılıyordu. Gmail/Outlook "tek tık" (List-Unsubscribe-Post) zaten POST gönderir → doğrudan çıkar. */
add_action( 'init', function () {
    if ( ! isset( $_GET['tls_unsub'], $_GET['k'] ) ) return;
    $uid = (int) $_GET['tls_unsub'];
    if ( ! ( $uid > 0 && hash_equals( tls_digest_unsub_key( $uid ), (string) $_GET['k'] ) && get_userdata( $uid ) ) ) {
        wp_die( 'Invalid unsubscribe link.', 'Unsubscribe', [ 'response' => 400 ] );
    }
    $back = '<p><a href="' . esc_url( home_url( '/' ) ) . '">Back to The Telos</a></p>';
    if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST' ) {
        update_user_meta( $uid, '_tls_digest_optout', current_time( 'mysql' ) );
        wp_die( '<h2>You have been unsubscribed.</h2><p>You will no longer receive reading recommendations from The Telos. You can turn them back on anytime in your <a href="' . esc_url( home_url( '/profile/' ) ) . '">profile settings</a>.</p>' . $back, 'Unsubscribed', [ 'response' => 200 ] );
    }
    $action = esc_url( tls_digest_unsub_url( $uid ) );
    wp_die( '<h2>Unsubscribe from reading recommendations?</h2>'
        . '<p>You will stop receiving personal reading recommendation emails from The Telos.</p>'
        . '<form method="post" action="' . $action . '"><button type="submit" style="background:#111;color:#fff;border:0;border-radius:20px;padding:12px 24px;font-size:14px;cursor:pointer">Yes, unsubscribe me</button></form>'
        . $back, 'Unsubscribe', [ 'response' => 200 ] );
}, 1 );

/* Profil → Settings → Email Preferences: üye bülteni kendisi açar/kapatır.
   Kapatma = _tls_digest_optout (tarih); açma = kayıt silinir + _tls_digest_optin (tarih) iz olarak tutulur. */
add_action( 'wp_ajax_tls_digest_pref', function () {
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'login_required' ] );
    check_ajax_referer( 'tls_auth_nonce', 'nonce', false );
    $uid = get_current_user_id();
    if ( ( $_POST['on'] ?? '' ) === '1' ) {
        delete_user_meta( $uid, '_tls_digest_optout' );
        update_user_meta( $uid, '_tls_digest_optin', current_time( 'mysql' ) );
        wp_send_json_success( [ 'on' => true ] );
    }
    update_user_meta( $uid, '_tls_digest_optout', current_time( 'mysql' ) );
    wp_send_json_success( [ 'on' => false ] );
} );

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
        'optout' => tls_digest_optout( $uid ), 'optout_at' => (string) get_user_meta( $uid, '_tls_digest_optout', true ), 'reminders' => array_slice( $want, 0, 3 ), 'recs' => $recs,
        'basis' => $basis, 'has_data' => ( (bool) $act['items'] || (bool) $saved || (bool) $ints ),
        'last_sent' => (string) get_user_meta( $uid, '_tls_digest_last_sent', true ),
    ];
}

/* Mail HTML'i (İngilizce — site dili). */
function tls_digest_html( array $d ) {
    $esc  = function ( $s ) { return esc_html( html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) ); };
    $SERIF = "Georgia,'Times New Roman',serif"; $SANS = "Helvetica,Arial,sans-serif";
    $INK = '#1a1a1a'; $MUTED = '#8a877f'; $LINE = '#e6e3dc'; $CARD = '#faf9f6'; $BG = '#f1efea';
    $book_title = function ( $pid ) {
        $t = html_entity_decode( get_post_field( 'post_title', $pid ), ENT_QUOTES, 'UTF-8' );
        $t = preg_replace( '/\s+[-–—]\s+[^–—]+$/u', '', $t );            // " – Yazar" kuyruğu
        return trim( $t );
    };
    $item = function ( $pid, $last ) use ( $esc, $book_title, $SERIF, $SANS, $INK, $MUTED, $LINE ) {
        $mins = max( 1, (int) round( tls_digest_words( $pid ) / 200 ) );
        $ex   = wp_trim_words( get_the_excerpt( $pid ), 34 );
        $url  = esc_url( get_permalink( $pid ) );
        $cats = array_values( array_filter( get_the_category( $pid ), function ( $c ) { return $c->term_id != (int) get_option( 'default_category' ); } ) );
        $cat  = $cats ? $cats[0]->name : '';
        $au   = function_exists( 'thetelos_get_book_author' ) ? thetelos_get_book_author( $pid ) : tls_digest_title_author( $pid );
        $bt   = $book_title( $pid );
        // KAPAK: dikey öne çıkan görsel = gerçek kapak; yoksa mailde güvenle çizilen HTML kapak kutusu.
        $img = ''; $tid = get_post_thumbnail_id( $pid );
        if ( $tid ) { $src = wp_get_attachment_image_src( $tid, 'medium' ); if ( $src && (int) $src[2] > (int) $src[1] * 1.15 ) $img = $src[0]; }
        if ( $img ) {
            $cover = '<a href="' . $url . '"><img src="' . esc_url( $img ) . '" width="84" alt="" style="display:block;width:84px;height:auto;border:0"></a>';
        } else {
            $pal = function_exists( 'thetelos_get_category_palette' ) ? thetelos_get_category_palette( $cats ? $cats[0]->slug : 'philosophy' ) : [ '#1C2B3A', '#C8A165', '#F5EFE6', '#A89070' ];
            $ct  = mb_strlen( $bt ) > 40 ? rtrim( mb_substr( $bt, 0, 38 ), ' ,;:' ) . '…' : $bt;
            $ca  = mb_strlen( $au ) > 22 ? mb_substr( $au, 0, 20 ) . '…' : $au;
            $cover = '<a href="' . $url . '" style="text-decoration:none"><table width="84" cellpadding="0" cellspacing="0" role="presentation" style="width:84px;table-layout:fixed;background:' . $pal[0] . '">'
                   . '<tr><td height="122" valign="top" style="height:122px;padding:9px 8px">'
                   . '<div style="font:7px/1.3 ' . $SANS . ';letter-spacing:.8px;text-transform:uppercase;color:' . $pal[3] . '">The Telos</div>'
                   . '<div style="font:11px/1.25 ' . $SERIF . ';word-break:break-word;color:' . $pal[2] . ';margin-top:18px">' . $esc( $ct ) . '</div>'
                   . '<div style="font:8px/1.2 ' . $SANS . ';color:' . $pal[3] . ';margin-top:6px">' . $esc( $ca ) . '</div>'
                   . '</td></tr></table></a>';
        }
        $meta = $esc( $au ) . ( $cat !== '' ? ' <span style="color:#c9c5bc">&middot;</span> <span style="color:' . $MUTED . '">' . $esc( $cat ) . '</span>' : '' )
              . ' <span style="color:#c9c5bc">&middot;</span> <span style="color:' . $MUTED . '">' . $mins . ' min</span>';
        return '<tr><td style="padding:26px 0;' . ( $last ? '' : 'border-bottom:1px solid ' . $LINE ) . '">'
             . '<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>'
             . '<td width="108" valign="top" style="width:108px;padding-right:24px">' . $cover . '</td>'
             . '<td valign="top">'
             . '<div style="font:11px/1.4 ' . $SANS . ';color:' . $INK . '">' . $meta . '</div>'
             . '<a href="' . $url . '" style="display:block;font:19px/1.3 ' . $SERIF . ';color:' . $INK . ';text-decoration:none;margin-top:6px;word-break:break-word">' . $esc( $bt ) . '</a>'
             . '<div style="font:13px/1.65 ' . $SANS . ';color:#5a5750;margin-top:8px">' . $esc( $ex ) . '</div>'
             . '<a href="' . $url . '" style="display:inline-block;font:12px ' . $SANS . ';color:' . $INK . ';text-decoration:none;margin-top:12px">Read summary &#8599;</a>'
             . '</td></tr></table></td></tr>';
    };
    $section = function ( $label, $pids ) use ( $item, $SANS, $MUTED, $LINE ) {
        if ( ! $pids ) return '';
        $n = count( $pids );
        $h = '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:30px;border-bottom:1px solid ' . $LINE . '"><tr>'
           . '<td style="font:10px ' . $SANS . ';letter-spacing:2px;text-transform:uppercase;color:' . $MUTED . ';padding-bottom:10px">' . $label . '</td>'
           . '<td align="right" style="font:10px ' . $SANS . ';color:' . $MUTED . ';padding-bottom:10px">' . $n . ' ' . ( $n === 1 ? 'Summary' : 'Summaries' ) . '</td></tr></table>'
           . '<table width="100%" cellpadding="0" cellspacing="0" role="presentation">';
        foreach ( $pids as $i => $pid ) $h .= $item( $pid, $i === $n - 1 );
        return $h . '</table>';
    };

    $logo  = get_template_directory_uri() . '/assets/img/thetelos_wordmark_email.png';
    $issue = (int) get_option( 'tls_digest_issue', 0 ) + 1;
    $total = (int) ( wp_count_posts( 'post' )->publish ?? 0 );
    $total_txt = $total >= 100 ? 'Over ' . number_format( (int) ( floor( $total / 100 ) * 100 ) ) . ' book summaries, essays and analyses.' : '';
    // Ad alanına e-posta yazılmışsa ("ali@gmail.com") @'ten sonrasını gösterme.
    $nm    = (string) $d['name'];
    if ( strpos( $nm, '@' ) !== false ) $nm = substr( $nm, 0, strpos( $nm, '@' ) );
    $first = trim( explode( ' ', trim( $nm ) )[0] );

    $h  = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:' . $BG . '">'
        . '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:' . $BG . '"><tr><td align="center" style="padding:18px 12px 32px">'
        . '<div style="max-width:600px;text-align:right;font:11px ' . $SANS . ';color:' . $MUTED . ';padding:0 4px 10px">'
        . 'Don\'t want these emails? <a href="' . esc_url( tls_digest_unsub_url( $d['uid'] ) ) . '" style="color:' . $MUTED . ';text-decoration:underline">Unsubscribe</a></div>'
        . '<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;max-width:600px;background:' . $CARD . ';border:1px solid ' . $LINE . '">'
        . '<tr><td style="padding:44px 44px 36px">';
    // Başlık: logo + sayı / alt başlık + tarih
    $h .= '<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>'
        . '<td valign="middle"><a href="' . esc_url( home_url( '/' ) ) . '"><img src="' . esc_url( $logo ) . '" width="132" alt="thetelos" style="display:block;width:132px;height:auto;border:0"></a></td>'
        . '<td align="right" valign="middle" style="font:10px ' . $SANS . ';letter-spacing:2px;text-transform:uppercase;color:' . $MUTED . '">Issue N&ordm; ' . $issue . '</td></tr>'
        . '<tr><td style="font:11px ' . $SANS . ';color:' . $MUTED . ';padding-top:12px">Your personal reading selection</td>'
        . '<td align="right" style="font:11px ' . $SANS . ';color:' . $MUTED . ';padding-top:12px">' . esc_html( date_i18n( 'F j, Y' ) ) . '</td></tr></table>'
        . '<div style="border-top:1px solid ' . $LINE . ';margin:28px 0 30px;font-size:0;line-height:0">&nbsp;</div>';
    $h .= '<div style="font:24px ' . $SERIF . ';color:' . $INK . '">' . ( $first !== '' ? 'Hi ' . $esc( $first ) . ',' : 'Hi,' ) . '</div>'
        . '<p style="font:13px/1.7 ' . $SANS . ';color:#5a5750;margin:14px 0 0">Based on the books you have read, saved and marked on The Telos, here are a few summaries selected for your next reading.</p>';
    $h .= $section( 'Curated selection', $d['recs'] );
    $h .= $section( 'Still on your reading list', $d['reminders'] );
    // Kütüphane düğmesi
    $h .= '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:36px"><tr><td align="center">'
        . '<a href="' . esc_url( home_url( '/' ) ) . '" style="display:inline-block;background:#111;color:#fff;font:bold 11px ' . $SANS . ';letter-spacing:1px;text-transform:uppercase;text-decoration:none;padding:14px 26px;border-radius:22px">Browse the library on thetelos.org &rarr;</a>'
        . ( $total_txt ? '<div style="font:11px ' . $SANS . ';color:' . $MUTED . ';margin-top:12px">' . $total_txt . '</div>' : '' )
        . '</td></tr></table>';
    // Alt bilgi
    $h .= '<div style="border-top:1px solid ' . $LINE . ';margin:36px 0 22px;font-size:0;line-height:0">&nbsp;</div>'
        . '<div style="text-align:center;font:11px/1.7 ' . $SANS . ';color:' . $MUTED . '">Delivered to <span style="color:' . $INK . '">' . esc_html( $d['email'] ) . '</span> based on your reading activity on The Telos.</div>'
        . '<div style="text-align:center;font:11px ' . $SANS . ';margin-top:12px">'
        . '<a href="' . esc_url( home_url( '/profile/' ) ) . '" style="color:' . $MUTED . ';text-decoration:underline">Reading preferences</a>'
        . ' <span style="color:#c9c5bc">&nbsp;&middot;&nbsp;</span> '
        . '<a href="' . esc_url( tls_digest_unsub_url( $d['uid'] ) ) . '" style="color:' . $MUTED . ';text-decoration:underline">Unsubscribe from these emails</a></div>'
        . '<div style="text-align:center;font:10px ' . $SANS . ';letter-spacing:2px;text-transform:uppercase;color:#b5b1a8;margin-top:18px">The Telos &middot; thetelos.org &middot; Issue ' . $issue . ' &middot; ' . esc_html( date_i18n( 'M j, Y H:i' ) ) . '</div>';
    $h .= '</td></tr></table></td></tr></table></body></html>';
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
    // DÜZ METİN SÜRÜMÜ (multipart/alternative): yalnız-HTML mailler spam puanını yükseltir.
    $html = tls_digest_html( $d );
    $alt  = "Hi,\n\nHere are a few summaries picked for you on The Telos:\n\n";
    foreach ( array_merge( $d['recs'], $d['reminders'] ) as $pid ) {
        $alt .= '- ' . html_entity_decode( get_the_title( $pid ), ENT_QUOTES, 'UTF-8' ) . "\n  " . get_permalink( $pid ) . "\n";
    }
    $alt .= "\nUnsubscribe: " . tls_digest_unsub_url( $uid ) . "\n";
    $set_alt = function ( $m ) use ( $alt ) { $m->AltBody = $alt; };
    add_action( 'phpmailer_init', $set_alt );
    $ok = wp_mail( $to, $subject, $html, [
        'Content-Type: text/html; charset=UTF-8',
        'List-Unsubscribe: <' . tls_digest_unsub_url( $uid ) . '>',
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',   // Gmail/Outlook tek tık "Abonelikten çık" düğmesi
    ] );
    remove_action( 'phpmailer_init', $set_alt );
    if ( $ok && $to_override === '' ) update_user_meta( $uid, '_tls_digest_last_sent', current_time( 'mysql' ) );
    return [ 'ok' => (bool) $ok, 'error' => $ok ? '' : 'mail gönderilemedi (SMTP)' ];
}

/* Mail gidebilecek üyeler: yönetici/yazar dışındaki tüm hesaplar. */
function tls_digest_member_ids() {
    return get_users( [ 'fields' => 'ID', 'role__not_in' => [ 'administrator', 'editor', 'author' ], 'number' => 2000 ] );
}
