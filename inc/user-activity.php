<?php
/**
 * user-activity.php — Kullanıcı okuma aktivitesini wp-admin'de görünür kılar.
 *
 * - Users listesine "Reading Activity" sütunu: kaç kitap, want/reading/read
 *   dağılımı ve son aktivite tarihi. (Sahte/atıl hesap = "none"; gerçek
 *   kullanıcı = sayılar görünür.)
 * - Kullanıcı düzenleme ekranına (Edit User) tam aktivite kutusu: okuma
 *   listesi (kitap + durum + tarih), yıllık hedef ve kayıt izleri (IP / tarayıcı
 *   / referer) — hesabın gerçekliğini değerlendirmeye yarar.
 *
 * Salt-okunur; hiçbir kullanıcı verisini değiştirmez.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* Bir kullanıcının okuma aktivitesini topla. */
function tls_user_activity( $uid ) {
    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT meta_key, meta_value FROM {$wpdb->usermeta}
         WHERE user_id = %d AND meta_key LIKE '\\_tls\\_reading\\_status\\_%%'", $uid ) );

    $items  = [];
    $counts = [ 'want' => 0, 'reading' => 0, 'read' => 0 ];
    $last   = '';
    foreach ( $rows as $r ) {
        $st = $r->meta_value;
        if ( $st === '' || ! isset( $counts[ $st ] ) ) continue;
        $pid = (int) str_replace( '_tls_reading_status_', '', $r->meta_key );
        if ( ! $pid ) continue;
        $counts[ $st ]++;
        $date = get_user_meta( $uid, '_tls_status_date_' . $pid, true );
        if ( $date && $date > $last ) $last = $date;
        $items[] = [ 'pid' => $pid, 'status' => $st, 'date' => $date ];
    }
    usort( $items, function ( $a, $b ) { return strcmp( (string) $b['date'], (string) $a['date'] ); } );
    return [ 'items' => $items, 'counts' => $counts, 'last' => $last, 'total' => count( $items ) ];
}

/* ── Users listesi: "Reading Activity" sütunu ── */
add_filter( 'manage_users_columns', function ( $cols ) {
    $cols['tls_activity'] = 'Reading Activity';
    return $cols;
} );

add_filter( 'manage_users_custom_column', function ( $val, $col, $uid ) {
    if ( $col !== 'tls_activity' ) return $val;
    $a = tls_user_activity( $uid );
    if ( $a['total'] === 0 ) {
        return '<span style="color:#b32d2e;">— none —</span>';
    }
    $c    = $a['counts'];
    $last = $a['last'] ? date_i18n( 'j M Y', strtotime( $a['last'] ) ) : '—';
    return sprintf(
        '<strong>%d</strong> books &nbsp;<span title="Want to Read">📚 %d</span> '
        . '<span title="Reading">📖 %d</span> <span title="Finished">✓ %d</span>'
        . '<br><small style="color:#888;">last activity: %s</small>',
        $a['total'], $c['want'], $c['reading'], $c['read'], esc_html( $last )
    );
}, 10, 3 );

/* ── Edit User ekranı: tam aktivite kutusu ── */
function tls_render_user_activity_box( $user ) {
    if ( ! current_user_can( 'list_users' ) ) return;
    $uid = $user->ID;
    $a   = tls_user_activity( $uid );

    $reg_ip  = get_user_meta( $uid, '_tls_reg_ip', true );
    $reg_ua  = get_user_meta( $uid, '_tls_reg_ua', true );
    $reg_ref = get_user_meta( $uid, '_tls_reg_referer', true );
    $registered = $user->user_registered ? date_i18n( 'j M Y, H:i', strtotime( $user->user_registered ) ) : '—';

    $labels = [ 'want' => 'Want to Read', 'reading' => 'Reading', 'read' => 'Finished' ];
    ?>
    <h2>📚 Reading Activity (The Telos)</h2>
    <table class="form-table" role="presentation">
        <tr>
            <th>Summary</th>
            <td>
                <?php if ( $a['total'] === 0 ) : ?>
                    <span style="color:#b32d2e;font-weight:600;">No reading activity yet</span>
                    <p class="description">This account has saved no books. Likely a new sign-up that hasn't engaged, or a throwaway registration.</p>
                <?php else : ?>
                    <strong><?php echo (int) $a['total']; ?></strong> books &nbsp;·&nbsp;
                    📚 <?php echo (int) $a['counts']['want']; ?> want &nbsp;
                    📖 <?php echo (int) $a['counts']['reading']; ?> reading &nbsp;
                    ✓ <?php echo (int) $a['counts']['read']; ?> finished
                    <?php if ( $a['last'] ) : ?>
                        <br><span class="description">Last activity: <?php echo esc_html( date_i18n( 'j M Y, H:i', strtotime( $a['last'] ) ) ); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>Registered</th>
            <td>
                <?php echo esc_html( $registered ); ?>
                <?php if ( $reg_ip ) : ?><br><span class="description">IP: <code><?php echo esc_html( $reg_ip ); ?></code></span><?php endif; ?>
                <?php if ( $reg_ref ) : ?><br><span class="description">From: <?php echo esc_html( $reg_ref ); ?></span><?php endif; ?>
                <?php if ( $reg_ua ) : ?><br><span class="description" style="word-break:break-all;">UA: <?php echo esc_html( $reg_ua ); ?></span><?php endif; ?>
                <?php if ( ! $reg_ip ) : ?><br><span class="description">(Registered before IP logging was added.)</span><?php endif; ?>
            </td>
        </tr>
        <?php if ( ! empty( $a['items'] ) ) : ?>
        <tr>
            <th>Books</th>
            <td>
                <table class="widefat striped" style="max-width:640px;">
                    <thead><tr><th>Book</th><th style="width:120px;">Status</th><th style="width:150px;">Date</th></tr></thead>
                    <tbody>
                    <?php foreach ( $a['items'] as $it ) :
                        $title = get_the_title( $it['pid'] ) ?: ( '#' . $it['pid'] );
                        $link  = get_edit_post_link( $it['pid'] );
                        $date  = $it['date'] ? date_i18n( 'j M Y', strtotime( $it['date'] ) ) : '—';
                    ?>
                        <tr>
                            <td><?php echo $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ); ?></td>
                            <td><?php echo esc_html( $labels[ $it['status'] ] ?? $it['status'] ); ?></td>
                            <td><?php echo esc_html( $date ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    <?php
}
add_action( 'edit_user_profile', 'tls_render_user_activity_box' );
add_action( 'show_user_profile', 'tls_render_user_activity_box' );
