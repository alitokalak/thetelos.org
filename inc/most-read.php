<?php
/**
 * most-read.php — Anasayfadaki "Most Read Summaries" rafının veri ve kart üretimi.
 *
 * Raf hem ilk render'da (index.php) hem de kategori sekmesine tıklanınca
 * (AJAX) aynı fonksiyonları kullanır; böylece iki yerde iki farklı kart
 * markup'ı oluşmaz.
 *
 * Kartlar AJAX ile çekilir çünkü 10 kategori × 12 kart HTML'ini anasayfaya
 * baştan gömmek sayfayı gereksiz şişirirdi. Sonuçlar transient'te tutulur,
 * yani ikinci ziyaretçi için sorgu tekrar çalışmaz.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Okunma rozetinin görünmeye başladığı eşik. */
if ( ! defined( 'TLS_MR_VIEW_MIN' ) ) define( 'TLS_MR_VIEW_MIN', 250 );

/**
 * Rafta gösterilecek kitap ID'leri.
 *
 * Önce gerçek okunma verisine göre sıralar. Kapak şartı YOKTUR: bir kitap
 * gerçekten en çok okunansa kapağı olmadığı için gizlenmesi listeyi çarpıtır.
 * Yeterli okunma verisi yoksa liste arşivden tamamlanır; oradaki seçim zaten
 * keyfi olduğu için gerçek kapağı olanlar tercih edilir.
 *
 * @param int   $cat_id  Kategori terim ID'si; 0 = tüm arşiv.
 * @param int   $limit   Kart sayısı.
 * @param int[] $exclude Sayfanın üst bölümlerinde zaten gösterilen ID'ler.
 */
function tls_most_read_ids( $cat_id = 0, $limit = 12, $exclude = [] ) {
    $base = [
        'post_type'        => 'post',
        'post_status'      => 'publish',
        'fields'           => 'ids',
        'no_found_rows'    => true,
        'suppress_filters' => true,
    ];
    if ( $cat_id ) $base['cat'] = (int) $cat_id;

    $ids = get_posts( array_merge( $base, [
        'posts_per_page' => $limit,
        'post__not_in'   => $exclude ?: [ 0 ],
        'meta_key'       => 'post_views_count',
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
        'meta_query'     => [ [ 'key' => 'post_views_count', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ] ],
    ] ) );

    if ( count( $ids ) < $limit ) {
        $ids = array_merge( $ids, get_posts( array_merge( $base, [
            'posts_per_page' => $limit - count( $ids ),
            'post__not_in'   => array_merge( $exclude, $ids ) ?: [ 0 ],
            'meta_query'     => [ [ 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ] ],
            'orderby'        => 'date',
            'order'          => 'DESC',
            // Tüm arşiv sekmesinde en yeni 12 kayıt aşağıdaki "Latest Additions"
            // bölümüne bırakılır; kategori sekmelerinde havuz zaten dar.
            'offset'         => $cat_id ? 0 : 12,
        ] ) ) );
    }
    return $ids;
}

/** Tek bir raf kartının HTML'i. */
function tls_rail_card_html( $mid, $mi ) {
    $m_authors = get_the_terms( $mid, 'authors' );
    $m_auth    = ( ! empty( $m_authors ) && ! is_wp_error( $m_authors ) ) ? $m_authors[0]->name : '';
    $m_cats    = get_the_category( $mid );
    $m_cat     = ! empty( $m_cats ) ? $m_cats[0]->name : '';
    $m_views   = function_exists( 'tls_get_views' ) ? tls_get_views( $mid ) : 0;

    ob_start();
    ?>
    <a class="tls-rail-card" href="<?php echo esc_url( get_permalink( $mid ) ); ?>">
        <div class="tls-rail-num">
            <?php printf( '%02d', $mi ); ?>
            <?php if ( $m_cat ) echo ' &mdash; <span style="color:var(--tls-gold)">' . esc_html( strtoupper( $m_cat ) ) . '</span>'; ?>
        </div>
        <div class="tls-rail-cover">
            <?php if ( has_post_thumbnail( $mid ) ) :
                echo get_the_post_thumbnail( $mid, 'medium', [ 'alt' => esc_attr( get_the_title( $mid ) ), 'loading' => 'lazy' ] );
            else :
                echo thetelos_render_book_cover( $mid );
            endif; ?>
        </div>
        <div class="tls-rail-divider"></div>
        <div class="tls-rail-title"><?php echo esc_html( get_the_title( $mid ) ); ?></div>
        <?php if ( $m_auth ) : ?><div class="tls-rail-author"><?php echo esc_html( $m_auth ); ?></div><?php endif; ?>
        <?php if ( $m_views >= TLS_MR_VIEW_MIN ) : ?>
        <div class="tls-rail-views">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <?php echo esc_html( number_format_i18n( $m_views ) ); ?> reads
        </div>
        <?php endif; ?>
    </a>
    <?php
    return ob_get_clean();
}

/** Bir ID listesinin kartlarını sırayla üretir. */
function tls_rail_cards_html( $ids ) {
    $out = '';
    $i   = 1;
    foreach ( $ids as $id ) $out .= tls_rail_card_html( $id, $i++ );
    return $out;
}

/**
 * Sekmelerde gösterilecek ana kategoriler — kitap sayısına göre ilk N tanesi.
 * "General" gibi toplama kategoriler dışarıda bırakılır.
 */
function tls_most_read_tabs( $count = 10 ) {
    $cached = get_transient( 'tls_mr_tabs' );
    if ( is_array( $cached ) ) return $cached;

    $terms = get_terms( [
        'taxonomy'   => 'category',
        'orderby'    => 'count',
        'order'      => 'DESC',
        'hide_empty' => true,
        'number'     => $count + 6,
    ] );
    $skip = [ 'general', 'uncategorized', 'genel' ];
    $tabs = [];
    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $t ) {
            if ( in_array( $t->slug, $skip, true ) ) continue;
            $tabs[] = [ 'id' => (int) $t->term_id, 'name' => $t->name ];
            if ( count( $tabs ) >= $count ) break;
        }
    }
    set_transient( 'tls_mr_tabs', $tabs, 12 * HOUR_IN_SECONDS );
    return $tabs;
}

/* ── Sekme tıklamasında kartları döndüren AJAX ucu ── */
function tls_handle_most_read() {
    $cat = isset( $_REQUEST['cat'] ) ? (int) $_REQUEST['cat'] : 0;
    if ( $cat && ! term_exists( $cat, 'category' ) ) wp_send_json_error();

    $key  = 'tls_mr_html_' . $cat;
    $html = get_transient( $key );
    if ( ! is_string( $html ) || $html === '' ) {
        $html = tls_rail_cards_html( tls_most_read_ids( $cat, 12 ) );
        set_transient( $key, $html, 6 * HOUR_IN_SECONDS );
    }
    wp_send_json_success( [ 'html' => $html ] );
}
add_action( 'wp_ajax_tls_most_read',        'tls_handle_most_read' );
add_action( 'wp_ajax_nopriv_tls_most_read', 'tls_handle_most_read' );

/* Yazı kaydedildiğinde/silindiğinde sekme HTML'lerini tazele. */
function tls_flush_most_read_cache() {
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tls_mr_%' OR option_name LIKE '_transient_timeout_tls_mr_%'" );
}
add_action( 'save_post_post', 'tls_flush_most_read_cache' );
add_action( 'deleted_post',   'tls_flush_most_read_cache' );
