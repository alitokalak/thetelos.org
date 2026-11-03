<?php
/**
 * post-views.php — Kitap sayfası okunma sayacı.
 *
 * NEDEN JS İLE: Sayfalar LiteSpeed tarafından cache'lendiği için ziyaretlerin
 * çoğunda PHP hiç çalışmaz; sayacı sayfa render'ında artırmak sayıları olduğundan
 * çok düşük gösterirdi. Bu yüzden sayaç, sayfa açıldıktan sonra küçük bir AJAX
 * çağrısıyla artırılır — cache'li sayfalarda da çalışır.
 *
 * Sayım kuralları: yöneticiler sayılmaz, aynı ziyaretçi aynı yazıyı oturum
 * başına bir kez sayar (sessionStorage), bariz botlar elenir.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Bir yazının okunma sayısı. */
function tls_get_views( $post_id ) {
    return (int) get_post_meta( $post_id, 'post_views_count', true );
}

/* ── GÜNLÜK SAYAÇ (son 8 gün) ── "Haftanın kitabı" = son 7 günde en çok okunan.
   Toplam sayaç (post_views_count) yıllar boyunca birikir; haftalık seçim için
   gün gün sayım gerekir. Tek seçenekte [gün => [yazı => sayı]] tutulur. */
function tls_views_daily_bump( $pid ) {
    $d   = (array) get_option( 'tls_views_daily', [] );
    $day = gmdate( 'Y-m-d' );
    $d[ $day ][ $pid ] = (int) ( $d[ $day ][ $pid ] ?? 0 ) + 1;
    ksort( $d );
    while ( count( $d ) > 8 ) array_shift( $d );
    update_option( 'tls_views_daily', $d, false );
}

/** Son 7 günde en çok okunan yazılar: [post_id => okunma], çoktan aza. */
function tls_views_week_top( $limit = 30 ) {
    $d = (array) get_option( 'tls_views_daily', [] );
    $from = gmdate( 'Y-m-d', time() - 6 * DAY_IN_SECONDS );
    $sum = [];
    foreach ( $d as $day => $rows ) {
        if ( $day < $from ) continue;
        foreach ( (array) $rows as $pid => $n ) $sum[ (int) $pid ] = ( $sum[ (int) $pid ] ?? 0 ) + (int) $n;
    }
    arsort( $sum );
    return array_slice( $sum, 0, $limit, true );
}

/* ── Sayacı artıran AJAX ucu ── */
function tls_handle_view_ping() {
    $pid = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
    if ( ! $pid || get_post_status( $pid ) !== 'publish' ) wp_send_json_error();

    // Yönetici/editör kendi ziyaretini şişirmesin
    if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) wp_send_json_success( [ 'skipped' => 'admin' ] );

    // Kaba bot elemesi
    $ua = strtolower( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
    if ( $ua === '' || preg_match( '/bot|crawl|spider|slurp|preview|monitor|curl|wget|headless/', $ua ) ) {
        wp_send_json_success( [ 'skipped' => 'bot' ] );
    }

    $views = tls_get_views( $pid ) + 1;
    update_post_meta( $pid, 'post_views_count', $views );
    tls_views_daily_bump( $pid );
    wp_send_json_success( [ 'views' => $views ] );
}
add_action( 'wp_ajax_tls_view_ping',        'tls_handle_view_ping' );
add_action( 'wp_ajax_nopriv_tls_view_ping', 'tls_handle_view_ping' );

/* ── Kitap sayfasında sayacı tetikle (oturum başına bir kez) ── */
add_action( 'wp_footer', function () {
    if ( ! is_singular( 'post' ) ) return;
    $pid = get_the_ID();
    ?>
    <script>
    (function () {
        if (/bot|crawl|spider|slurp|bingpreview|headless|lighthouse|preview|facebookexternalhit|embedly|scrap/i.test(navigator.userAgent||'') || navigator.webdriver) return;   // botlar okunma sayılmaz, sunucuyu da yormasın
        var id = <?php echo (int) $pid; ?>, key = 'tls_v_' + id;
        try { if (sessionStorage.getItem(key)) return; sessionStorage.setItem(key, '1'); } catch (e) {}
        var send = function () {
            var fd = new FormData();
            fd.append('action', 'tls_view_ping');
            fd.append('post_id', id);
            fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
                  { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true }).catch(function(){});
        };
        // Sayfa gerçekten okunmaya başlasın diye kısa gecikme (hemen kapatılan sekmeler sayılmaz)
        setTimeout(send, 4000);
    })();
    </script>
    <?php
}, 100 );
