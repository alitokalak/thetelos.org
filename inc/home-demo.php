<?php
/**
 * The Telos — Yeni ana sayfa DEMOSU (canlı ana sayfaya dokunmaz).
 *
 * Açmak: https://thetelos.org/?home_demo=1   (önbelleği tazelemek: &fresh=1)
 * Arama motorlarına kapalı (noindex). Onaylanınca index.php'ye taşınacak.
 *
 * Düzen: Hero (slogan + arama, mevcut) → Öne çıkan kitap → "En çok okunanlar" →
 * "Yeni eklenenler" → kategori kategori kapaklı raflar. Yalnız KAPAK GÖRSELİ
 * olan ve yer tutucu olmayan kitaplar listelenir.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'template_redirect', function () {
    if ( empty( $_GET['home_demo'] ) ) return;
    add_filter( 'wp_robots', function ( $r ) { $r['noindex'] = true; $r['nofollow'] = true; return $r; } );
    add_filter( 'wpseo_robots', fn() => 'noindex, nofollow' );
    if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
    get_header();
    echo tls_home_demo_html();
    get_footer();
    exit;
} );

/* Kapaklı, yer tutucu olmayan kitaplar. */
function tls_hd_query( $args ) {
    $ph = function_exists( 'tls_placeholder_ids' ) ? tls_placeholder_ids() : [];
    $q = new WP_Query( array_merge( [
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => 16,
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'post__not_in'        => $ph,
        'meta_query'          => [ [ 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ] ],
    ], $args ) );
    return $q->posts;
}

/* "Kitap (Orijinal) – Yazar" → kitap adı; yazar taksonomiden. */
function tls_hd_title( $p ) {
    $t = html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' );
    $t = preg_replace( '/\s+[–—-]\s+[^–—]+$/u', '', $t );
    return trim( $t );
}
function tls_hd_author( $p ) {
    $terms = get_the_terms( $p, 'authors' );
    return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
}

function tls_hd_card( $p, $size = 'medium' ) {
    $img = get_the_post_thumbnail_url( $p, $size );
    if ( ! $img ) return '';
    $t = tls_hd_title( $p ); $a = tls_hd_author( $p );
    return '<a class="hd-card" href="' . esc_url( get_permalink( $p ) ) . '">'
         . '<span class="hd-cover"><img src="' . esc_url( $img ) . '" alt="' . esc_attr( $t ) . '" loading="lazy" decoding="async"></span>'
         . '<span class="hd-t">' . esc_html( $t ) . '</span>'
         . ( $a !== '' ? '<span class="hd-a">' . esc_html( $a ) . '</span>' : '' )
         . '</a>';
}

function tls_hd_row( $title, $posts, $link = '', $sub = '' ) {
    $cards = '';
    foreach ( $posts as $p ) $cards .= tls_hd_card( $p );
    if ( $cards === '' ) return '';
    return '<section class="hd-row"><div class="hd-wrap">'
         . '<div class="hd-head"><div><h2>' . esc_html( $title ) . '</h2>' . ( $sub ? '<p>' . esc_html( $sub ) . '</p>' : '' ) . '</div>'
         . ( $link ? '<a class="hd-all" href="' . esc_url( $link ) . '">See all →</a>' : '' ) . '</div>'
         . '<div class="hd-rail-box"><button class="hd-nav hd-prev" aria-label="Previous">‹</button>'
         . '<div class="hd-rail">' . $cards . '</div>'
         . '<button class="hd-nav hd-next" aria-label="Next">›</button></div>'
         . '</div></section>';
}

function tls_home_demo_html() {
    $ck = 'tls_home_demo_v1';
    if ( empty( $_GET['fresh'] ) && ( $h = get_transient( $ck ) ) ) return $h;
    $seen = [];
    $take = function ( $posts, $n ) use ( &$seen ) {
        $out = [];
        foreach ( $posts as $p ) { if ( isset( $seen[ $p->ID ] ) ) continue; $seen[ $p->ID ] = 1; $out[] = $p; if ( count( $out ) >= $n ) break; }
        return $out;
    };

    $popular = tls_hd_query( [ 'posts_per_page' => 30, 'meta_key' => 'post_views_count', 'orderby' => 'meta_value_num', 'order' => 'DESC' ] );
    $feature = $popular ? array_shift( $popular ) : null;
    if ( $feature ) $seen[ $feature->ID ] = 1;
    $pop_row = $take( $popular, 14 );
    $new_row = $take( tls_hd_query( [ 'posts_per_page' => 30, 'orderby' => 'date', 'order' => 'DESC' ] ), 14 );

    ob_start();
    ?>
<style>
.hd-hero{background:var(--tls-bg);padding:44px 16px 36px;text-align:center;border-bottom:1px solid var(--tls-border)}
.hd-hero .eb{font:600 11px/1 var(--tls-sans);letter-spacing:.2em;text-transform:uppercase;color:var(--tls-gold)}
.hd-hero h1{font:400 clamp(30px,5vw,52px)/1.1 var(--tls-serif);color:var(--tls-bg-dark);margin:14px 0 10px}
.hd-hero h1 em{color:var(--tls-gold);font-style:italic}
.hd-hero p{font:16px/1.6 var(--tls-sans);color:var(--tls-muted);max-width:560px;margin:0 auto 22px}
.hd-search{display:flex;max-width:560px;margin:0 auto;background:#fff;border:1px solid var(--tls-border);border-radius:999px;padding:6px;box-shadow:var(--tls-shadow-sm)}
.hd-search input{flex:1;border:0;outline:0;background:transparent;padding:10px 16px;font:15px var(--tls-sans);min-width:0}
.hd-search button{border:0;background:var(--tls-bg-dark);color:#fff;border-radius:999px;padding:10px 22px;font:600 14px var(--tls-sans);cursor:pointer}
.hd-wrap{max-width:var(--tls-container);margin:0 auto;padding:0 16px}
.hd-feature{background:var(--tls-bg-dark);color:#fff}
.hd-feature .hd-wrap{display:grid;grid-template-columns:220px 1fr;gap:40px;align-items:center;padding-top:44px;padding-bottom:44px}
.hd-feature img{width:100%;aspect-ratio:2/3;object-fit:cover;border-radius:6px;box-shadow:0 18px 50px rgba(0,0,0,.5)}
.hd-feature .eb{font:600 11px var(--tls-sans);letter-spacing:.2em;text-transform:uppercase;color:var(--tls-gold-light)}
.hd-feature h2{font:400 clamp(26px,3.4vw,40px)/1.15 var(--tls-serif);margin:10px 0 6px;color:#fff}
.hd-feature .by{font:15px var(--tls-sans);color:#cfc8bd;margin-bottom:16px}
.hd-feature .ex{font:16px/1.7 var(--tls-sans);color:#e8e2d8;max-width:640px;margin-bottom:22px}
.hd-btn{display:inline-block;background:var(--tls-gold);color:#fff!important;text-decoration:none;padding:12px 26px;border-radius:999px;font:600 14px var(--tls-sans)}
.hd-btn:hover{background:var(--tls-gold-light)}
.hd-row{padding:34px 0 8px}
.hd-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:14px}
.hd-head h2{font:400 26px/1.2 var(--tls-serif);color:var(--tls-bg-dark);margin:0}
.hd-head p{font:14px var(--tls-sans);color:var(--tls-muted);margin:4px 0 0}
.hd-all{font:600 13px var(--tls-sans);color:var(--tls-gold);text-decoration:none;white-space:nowrap}
.hd-rail-box{position:relative}
.hd-rail{display:grid;grid-auto-flow:column;grid-auto-columns:150px;gap:18px;overflow-x:auto;scroll-snap-type:x mandatory;padding:6px 2px 18px;scrollbar-width:thin}
.hd-card{scroll-snap-align:start;text-decoration:none;display:block}
.hd-cover{display:block;aspect-ratio:2/3;border-radius:5px;overflow:hidden;background:#e9e4d8;box-shadow:var(--tls-shadow);transition:transform .2s,box-shadow .2s}
.hd-cover img{width:100%;height:100%;object-fit:cover;display:block}
.hd-card:hover .hd-cover{transform:translateY(-4px);box-shadow:var(--tls-shadow-lg)}
.hd-t{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;font:600 14px/1.35 var(--tls-sans);color:var(--tls-bg-dark);margin-top:10px}
.hd-a{display:block;font:13px var(--tls-sans);color:var(--tls-muted);margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hd-nav{position:absolute;top:calc(50% - 40px);z-index:2;width:38px;height:38px;border-radius:50%;border:1px solid var(--tls-border);background:#fff;box-shadow:var(--tls-shadow);font-size:22px;line-height:1;cursor:pointer;color:var(--tls-bg-dark)}
.hd-prev{left:-14px}.hd-next{right:-14px}
.hd-demo-note{background:#fff7e0;border-bottom:1px solid #f0dca0;text-align:center;font:13px var(--tls-sans);padding:8px 16px;color:#6b5a20}
@media (max-width:720px){
  .hd-feature .hd-wrap{grid-template-columns:120px 1fr;gap:18px;padding-top:28px;padding-bottom:28px}
  .hd-feature .ex{display:none}
  .hd-rail{grid-auto-columns:118px;gap:14px}
  .hd-nav{display:none}
  .hd-head h2{font-size:22px}
}
</style>
<div class="hd-demo-note">Önizleme — yeni ana sayfa tasarımı (canlı ana sayfa değişmedi)</div>
<section class="hd-hero">
  <span class="eb">The Digital Archive</span>
  <h1>Explore the World's<br><em>Great Book Summaries</em></h1>
  <p>A curated archive of the world's most influential texts, distilled for the modern scholar.</p>
  <form class="hd-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
    <input type="search" name="s" placeholder="Search by author, title, or concept…" aria-label="Search the archive">
    <button type="submit">Explore</button>
  </form>
</section>
<?php if ( $feature ) :
    $ft = tls_hd_title( $feature ); $fa = tls_hd_author( $feature );
    $ex = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $feature ) ?: $feature->post_content ), 48 ); ?>
<section class="hd-feature"><div class="hd-wrap">
  <a href="<?php echo esc_url( get_permalink( $feature ) ); ?>"><img src="<?php echo esc_url( get_the_post_thumbnail_url( $feature, 'medium_large' ) ); ?>" alt="<?php echo esc_attr( $ft ); ?>"></a>
  <div>
    <span class="eb">Most read this month</span>
    <h2><?php echo esc_html( $ft ); ?></h2>
    <?php if ( $fa ) : ?><div class="by">by <?php echo esc_html( $fa ); ?></div><?php endif; ?>
    <p class="ex"><?php echo esc_html( $ex ); ?></p>
    <a class="hd-btn" href="<?php echo esc_url( get_permalink( $feature ) ); ?>">Read the summary →</a>
  </div>
</div></section>
<?php endif; ?>
<?php
    echo tls_hd_row( 'Most Read', $pop_row, '', 'What readers are opening most right now' );
    echo tls_hd_row( 'New Summaries', $new_row, '', 'Freshly added to the archive' );

    // Kategori rafları: en dolu üst kategoriler, en az 8 kapaklı kitabı olanlar.
    $cats = get_terms( [ 'taxonomy' => 'category', 'parent' => 0, 'orderby' => 'count', 'order' => 'DESC', 'number' => 14, 'hide_empty' => true ] );
    $shown = 0;
    foreach ( (array) $cats as $c ) {
        if ( is_wp_error( $c ) || $c->slug === 'uncategorized' ) continue;
        $posts = $take( tls_hd_query( [ 'posts_per_page' => 28, 'cat' => $c->term_id, 'meta_key' => 'post_views_count', 'orderby' => 'meta_value_num', 'order' => 'DESC' ] ), 14 );
        if ( count( $posts ) < 8 ) continue;
        echo tls_hd_row( $c->name, $posts, get_category_link( $c->term_id ) );
        if ( ++$shown >= 8 ) break;
    }
?>
<div style="height:40px"></div>
<script>
document.querySelectorAll('.hd-rail-box').forEach(function(b){
  var r=b.querySelector('.hd-rail');
  b.querySelector('.hd-prev').onclick=function(){r.scrollBy({left:-r.clientWidth*.9,behavior:'smooth'})};
  b.querySelector('.hd-next').onclick=function(){r.scrollBy({left:r.clientWidth*.9,behavior:'smooth'})};
});
</script>
<?php
    $h = ob_get_clean();
    set_transient( $ck, $h, HOUR_IN_SECONDS );
    return $h;
}
