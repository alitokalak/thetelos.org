<?php
/**
 * The Telos — Yazarın "Other Works" listesi
 *
 * Özeti henüz yazılmamış (ama katalogda doğrulanmış) eserler ayrı boş sayfa
 * olarak açılmaz; yazar teriminin 'tls_other_works' meta'sında [{t, y}] olarak
 * tutulur (panel REST ile yazar) ve yazar sayfasının altında listelenir. Özeti
 * sonradan yazılan eser listede kendiliğinden o yazıya link olur.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', function () {
    register_term_meta( 'authors', 'tls_other_works', [
        'type'          => 'array',
        'single'        => true,
        'default'       => [],
        'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
        'show_in_rest'  => [
            'schema' => [
                'type'  => 'array',
                'items' => [
                    'type'       => 'object',
                    'properties' => [ 't' => [ 'type' => 'string' ], 'y' => [ 'type' => 'integer' ] ],
                ],
            ],
        ],
    ] );
} );

function tls_ow_norm( $s ) {
    $s = html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' );
    $s = preg_replace( '/\s*\([^()]*\)\s*$/u', '', $s );
    return trim( preg_replace( '/[^a-z0-9]+/', ' ', remove_accents( mb_strtolower( $s, 'UTF-8' ) ) ) );
}

/* Yazar sayfasında listeyi bas. Özeti olan eserler (bu yazarın yazıları
   arasında başlığı eşleşen) link olur; olmayanlar düz metin. */
function tls_render_other_works( $term ) {
    if ( ! $term || empty( $term->term_id ) ) return;
    $works = get_term_meta( $term->term_id, 'tls_other_works', true );
    if ( empty( $works ) || ! is_array( $works ) ) return;

    // Bu yazarın yayındaki yazıları: normalize başlık → link
    $have = [];
    $ids  = get_posts( [ 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids',
                         'tax_query' => [ [ 'taxonomy' => 'authors', 'field' => 'term_id', 'terms' => $term->term_id ] ] ] );
    foreach ( $ids as $pid ) {
        $t = get_the_title( $pid );
        $t = preg_replace( '/\s+[–—-]\s+' . preg_quote( $term->name, '/' ) . '\s*$/u', '', html_entity_decode( $t, ENT_QUOTES, 'UTF-8' ) );
        $have[ tls_ow_norm( $t ) ] = get_permalink( $pid );
    }

    usort( $works, function ( $a, $b ) { return ( (int) ( $a['y'] ?? 0 ) ?: 9999 ) <=> ( (int) ( $b['y'] ?? 0 ) ?: 9999 ); } );
    ?>
    <section class="tls-other-works" aria-labelledby="tls-ow-h">
        <h2 id="tls-ow-h">Other Works by <?php echo esc_html( $term->name ); ?></h2>
        <ul>
        <?php foreach ( $works as $w ) :
            $t = trim( (string) ( $w['t'] ?? '' ) ); if ( $t === '' ) continue;
            $y = (int) ( $w['y'] ?? 0 );
            $u = $have[ tls_ow_norm( $t ) ] ?? '';
        ?>
            <li><?php if ( $u ) : ?><a href="<?php echo esc_url( $u ); ?>"><?php echo esc_html( $t ); ?></a><?php else : ?><span><?php echo esc_html( $t ); ?></span><?php endif; ?><?php if ( $y ) : ?> <span class="tls-ow-y">(<?php echo $y; ?>)</span><?php endif; ?></li>
        <?php endforeach; ?>
        </ul>
    </section>
    <style>
    .tls-other-works{margin:48px 0 0;padding-top:28px;border-top:1px solid var(--tls-border)}
    .tls-other-works h2{font-family:var(--tls-serif);font-size:22px;margin:0 0 16px;color:var(--tls-bg-dark)}
    .tls-other-works ul{list-style:none;margin:0;padding:0;columns:2;column-gap:40px}
    .tls-other-works li{break-inside:avoid;padding:6px 0;font-family:var(--tls-sans);font-size:15px;color:var(--tls-text,#2a2a2a);border-bottom:1px dashed var(--tls-border)}
    .tls-other-works a{color:var(--tls-bg-dark);text-decoration:none;border-bottom:1px solid var(--tls-gold)}
    .tls-other-works a:hover{color:var(--tls-gold)}
    .tls-ow-y{color:var(--tls-muted);font-size:13px}
    @media (max-width:640px){.tls-other-works ul{columns:1}}
    </style>
    <?php
}
