<?php
/**
 * Template Name: Display Authors
 *
 * Authors taxonomy (filozoflar) listesi — arama, alfabe filtresi ve responsive grid.
 * WordPress kullanıcıları değil, `authors` custom taxonomy termlerini gösterir.
 *
 * URL parametreleri:
 *   ?letter=A          — ilk harfe göre filtrele (boş = tümü)
 *   ?author_search=xyz — arama terimi (server-side)
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Parametreler ── */
$current_letter  = isset( $_GET['letter'] )        ? strtoupper( sanitize_text_field( $_GET['letter'] ) ) : '';
$search_query    = isset( $_GET['author_search'] ) ? sanitize_text_field( $_GET['author_search'] )        : '';
$paged           = max( 1, get_query_var( 'paged' ) ?: ( isset( $_GET['authors_page'] ) ? (int) $_GET['authors_page'] : 1 ) );
$per_page        = 60;

/* ── Toplam yazar sayısı (filtre uygulanmadan) ── */
$total_terms_all = wp_count_terms( [ 'taxonomy' => 'authors', 'hide_empty' => false ] );
$total_count     = is_wp_error( $total_terms_all ) ? 0 : (int) $total_terms_all;

/* ── get_terms argümanları ── */
$term_args = [
    'taxonomy'   => 'authors',
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
    'number'     => 0,
];

if ( $search_query !== '' ) {
    $term_args['search'] = $search_query;
}

$all_terms = get_terms( $term_args );
if ( is_wp_error( $all_terms ) ) $all_terms = [];

/* ── Harf filtresi ── */
if ( $current_letter !== '' && $current_letter !== '#' ) {
    $all_terms = array_filter( $all_terms, function( $t ) use ( $current_letter ) {
        return mb_strtoupper( mb_substr( $t->name, 0, 1 ) ) === $current_letter;
    } );
    $all_terms = array_values( $all_terms );
} elseif ( $current_letter === '#' ) {
    $all_terms = array_filter( $all_terms, function( $t ) {
        $first = mb_strtoupper( mb_substr( $t->name, 0, 1 ) );
        return ! ctype_alpha( $first );
    } );
    $all_terms = array_values( $all_terms );
}

/* ── Sayfalama ── */
$filtered_count = count( $all_terms );
$total_pages    = (int) ceil( $filtered_count / $per_page );
$paged          = min( $paged, max( 1, $total_pages ) );
$offset         = ( $paged - 1 ) * $per_page;
$paged_terms    = array_slice( $all_terms, $offset, $per_page );

/* ── Mevcut harfleri bul ── */
$existing_letters = [];
$all_names = get_terms( [ 'taxonomy' => 'authors', 'hide_empty' => false, 'fields' => 'names', 'number' => 0 ] );
if ( ! is_wp_error( $all_names ) ) {
    foreach ( $all_names as $name ) {
        $first = mb_strtoupper( mb_substr( $name, 0, 1 ) );
        if ( ctype_alpha( $first ) ) {
            $existing_letters[ $first ] = true;
        } else {
            $existing_letters['#'] = true;
        }
    }
    ksort( $existing_letters );
}

/* ── Sayfa URL'si ── */
$base_url = get_permalink();

function tls_authors_url( $base, $letter = '', $search = '', $pg = 1 ) {
    $args = [];
    if ( $letter !== '' )  $args['letter']       = $letter;
    if ( $search !== '' )  $args['author_search'] = $search;
    if ( $pg > 1 )         $args['authors_page']  = $pg;
    return $args ? add_query_arg( $args, $base ) : $base;
}

get_header();
?>

<style>
/* ── Hero ── */
.tla-hero {
    background: #fff;
    border-bottom: 1px solid var(--tls-border);
    padding: 56px 0 40px;
}
.tla-hero-inner {
    max-width: var(--tls-container);
    margin: 0 auto;
    padding: 0 32px;
}
.tla-eyebrow {
    font-family: var(--tls-sans);
    font-size: 10px;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: var(--tls-gold);
    margin-bottom: 12px;
}
.tla-hero h1 {
    font-family: var(--tls-serif);
    font-size: clamp(30px, 4vw, 48px);
    font-weight: 400;
    color: var(--tls-bg-dark);
    margin: 0 0 10px;
    line-height: 1.1;
}
.tla-hero-sub {
    font-family: var(--tls-sans);
    font-size: 15px;
    color: var(--tls-muted);
    margin: 0;
    max-width: 520px;
    line-height: 1.6;
}
.tla-hero-meta {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-top: 20px;
    flex-wrap: wrap;
}
.tla-hero-stat {
    font-family: var(--tls-sans);
    font-size: 13px;
    color: var(--tls-muted);
}
.tla-hero-stat strong {
    font-weight: 600;
    color: var(--tls-bg-dark);
    margin-right: 4px;
}

/* ── Sticky Toolbar ── */
.tla-toolbar {
    position: sticky;
    top: var(--tls-nav-h);
    z-index: 200;                          /* kartların üstünde kalması için */
    background: var(--tls-bg);
    border-bottom: 1px solid var(--tls-border);
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    isolation: isolate;                    /* kendi stacking context'ini oluşturur */
}
.admin-bar .tla-toolbar { top: calc(var(--tls-nav-h) + 32px); }
@media screen and (max-width: 782px) {
    .admin-bar .tla-toolbar { top: calc(var(--tls-nav-h) + 46px); }
}
.tla-toolbar-inner {
    max-width: var(--tls-container);
    margin: 0 auto;
    padding: 0 32px;
}

/* Search row */
.tla-search-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 0 10px;
    border-bottom: 1px solid var(--tls-border);
}
.tla-search-wrap {
    position: relative;
    flex: 1;
    max-width: 480px;
}
.tla-search-wrap > svg {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    color: var(--tls-muted);
    pointer-events: none;
}
.tla-search-input {
    width: 100%;
    height: 40px;
    padding: 0 36px 0 38px;
    font-family: var(--tls-sans);
    font-size: 14px;
    color: var(--tls-bg-dark);
    background: #fff;
    border: 1px solid var(--tls-border);
    border-radius: 20px;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
    -webkit-appearance: none;
}
.tla-search-input::placeholder { color: #aaa; }
.tla-search-input:focus {
    border-color: var(--tls-green);
    box-shadow: 0 0 0 3px rgba(0,171,107,.12);
}
.tla-search-clear {
    display: none;
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: var(--tls-muted);
    padding: 4px;
    line-height: 1;
    font-size: 15px;
    align-items: center;
    justify-content: center;
}
.tla-search-clear:hover { color: var(--tls-bg-dark); }
.tla-search-clear.visible { display: flex; }
.tla-result-count {
    font-family: var(--tls-sans);
    font-size: 13px;
    color: var(--tls-muted);
    white-space: nowrap;
    flex-shrink: 0;
}
.tla-result-count span { font-weight: 600; color: var(--tls-bg-dark); }

/* Alphabet nav */
.tla-alpha-row {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 8px 0;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.tla-alpha-row::-webkit-scrollbar { display: none; }
.tla-alpha-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    height: 30px;
    padding: 0 5px;
    font-family: var(--tls-sans);
    font-size: 12px;
    font-weight: 600;
    border-radius: var(--tls-radius);
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none !important;
    color: var(--tls-muted) !important;
    background: transparent;
    transition: color .12s, background .12s, border-color .12s;
    letter-spacing: .02em;
}
.tla-alpha-btn:hover {
    color: var(--tls-bg-dark) !important;
    background: #fff;
    border-color: var(--tls-border);
}
.tla-alpha-btn.active {
    color: #fff !important;
    background: var(--tls-bg-dark);
    border-color: var(--tls-bg-dark);
}
.tla-alpha-btn.disabled {
    opacity: .28;
    cursor: default;
    pointer-events: none;
}
.tla-alpha-divider {
    width: 1px;
    height: 16px;
    background: var(--tls-border);
    margin: 0 4px;
    flex-shrink: 0;
}

/* ── Main ── */
.tla-main {
    max-width: var(--tls-container);
    margin: 0 auto;
    padding: 40px 32px 80px;
    position: relative;
    z-index: 1;                            /* toolbar'ın altında kalır */
}

/* No results */
.tla-no-results {
    text-align: center;
    padding: 80px 20px;
}
.tla-no-results svg { color: var(--tls-border); margin: 0 auto 16px; }
.tla-no-results strong {
    display: block;
    font-family: var(--tls-serif);
    font-size: 22px;
    color: var(--tls-bg-dark);
    font-weight: 400;
    margin-bottom: 8px;
}
.tla-no-results p {
    font-family: var(--tls-sans);
    font-size: 14px;
    color: var(--tls-muted);
    margin: 0;
}

/* Letter group header */
.tla-letter-header {
    font-family: var(--tls-sans);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .16em;
    text-transform: uppercase;
    color: var(--tls-muted);
    border-bottom: 1px solid var(--tls-border);
    padding-bottom: 8px;
    margin: 64px 0 18px;
    scroll-margin-top: calc(var(--tls-nav-h) + 100px);
}
.tla-letter-header:first-child { margin-top: 24px; }

/* Grid */
.tla-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 10px;
}

/* Author card */
.tla-card {
    display: flex;
    flex-direction: column;
    gap: 5px;
    padding: 14px 16px;
    background: #fff;
    border: 1px solid var(--tls-border);
    border-radius: var(--tls-radius-lg);
    text-decoration: none !important;
    color: var(--tls-bg-dark) !important;
    transition: border-color .15s, box-shadow .15s, transform .15s;
    position: relative;
    overflow: hidden;
}
.tla-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, var(--tls-green), var(--tls-gold));
    opacity: 0;
    transition: opacity .15s;
}
.tla-card:hover {
    border-color: transparent;
    box-shadow: var(--tls-shadow);
    transform: translateY(-2px);
    z-index: 2;                            /* grid içinde diğer kartların önünde, toolbar'ın altında */
}
.tla-card:hover::before { opacity: 1; }
.tla-card-initial {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--tls-bg);
    font-family: var(--tls-serif);
    font-size: 14px;
    color: var(--tls-bg-dark);
    flex-shrink: 0;
    margin-bottom: 2px;
    border: 1px solid var(--tls-border);
}
.tla-card-name {
    font-family: var(--tls-serif);
    font-size: 15px;
    font-weight: 400;
    color: var(--tls-bg-dark);
    line-height: 1.25;
    margin: 0;
}
.tla-card-desc {
    font-family: var(--tls-sans);
    font-size: 11.5px;
    color: var(--tls-muted);
    line-height: 1.5;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.tla-card-count {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: var(--tls-sans);
    font-size: 11px;
    color: var(--tls-muted);
    margin-top: auto;
    padding-top: 4px;
}
.tla-card-count svg { width: 10px; height: 10px; }

/* Pagination */
.tla-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    margin-top: 56px;
    flex-wrap: wrap;
}
.tla-page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    font-family: var(--tls-sans);
    font-size: 13px;
    font-weight: 500;
    border: 1px solid var(--tls-border);
    border-radius: var(--tls-radius);
    background: #fff;
    color: var(--tls-bg-dark) !important;
    text-decoration: none !important;
    transition: background .12s, border-color .12s;
}
.tla-page-btn:hover { background: var(--tls-bg); border-color: #bbb; }
.tla-page-btn.current {
    background: var(--tls-bg-dark);
    border-color: var(--tls-bg-dark);
    color: #fff !important;
    cursor: default;
}
.tla-page-btn.dots {
    border-color: transparent;
    background: none;
    cursor: default;
    color: var(--tls-muted) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .tla-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; }
}
@media (max-width: 480px) {
    .tla-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .tla-main { padding: 24px 16px 60px; }
    .tla-toolbar-inner, .tla-hero-inner { padding: 0 16px; }
    .tla-hero { padding: 40px 0 28px; }
}
</style>

<main id="main" role="main">

<!-- ══════════ HERO ══════════ -->
<div class="tla-hero">
    <div class="tla-hero-inner">
        <p class="tla-eyebrow">The Archive</p>
        <h1><?php the_title(); ?></h1>
        <?php while ( have_posts() ) : the_post(); ?>
            <?php $content = trim( get_the_content() ); ?>
            <p class="tla-hero-sub">
                <?php echo $content ? esc_html( wp_trim_words( wp_strip_all_tags( $content ), 28 ) ) : 'Every author, one archive. From ancient philosophers to contemporary novelists — summaries and analyses of works from across world literature.'; ?>
            </p>
        <?php endwhile; ?>
        <div class="tla-hero-meta">
            <span class="tla-hero-stat"><strong><?php echo number_format( $total_count ); ?></strong> authors in the archive</span>
            <?php if ( $search_query || $current_letter ) : ?>
                <span class="tla-hero-stat" style="color:var(--tls-green);">
                    <strong><?php echo number_format( $filtered_count ); ?></strong> matching
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══════════ STICKY TOOLBAR ══════════ -->
<div class="tla-toolbar" id="tla-toolbar">
    <div class="tla-toolbar-inner">

        <form class="tla-search-row" method="get" action="<?php echo esc_url( $base_url ); ?>" role="search" id="tla-search-form">
            <?php if ( $current_letter ) : ?>
                <input type="hidden" name="letter" value="<?php echo esc_attr( $current_letter ); ?>">
            <?php endif; ?>
            <div class="tla-search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="search" name="author_search" class="tla-search-input"
                       id="tla-search-input" placeholder="Search authors…"
                       value="<?php echo esc_attr( $search_query ); ?>"
                       autocomplete="off" spellcheck="false">
                <button type="button" class="tla-search-clear<?php echo $search_query ? ' visible' : ''; ?>"
                        id="tla-search-clear" aria-label="Clear search">&#x2715;</button>
            </div>
            <span class="tla-result-count" id="tla-result-count" aria-live="polite">
                <?php if ( $filtered_count > 0 ) : ?>
                    <span><?php echo number_format( $filtered_count ); ?></span>&nbsp;author<?php echo $filtered_count !== 1 ? 's' : ''; ?>
                <?php else : ?>
                    No results
                <?php endif; ?>
            </span>
        </form>

        <nav class="tla-alpha-row" aria-label="Filter by letter">
            <?php $all_active = ( $current_letter === '' ); ?>
            <a href="<?php echo esc_url( $search_query ? tls_authors_url( $base_url, '', $search_query ) : $base_url ); ?>"
               class="tla-alpha-btn<?php echo $all_active ? ' active' : ''; ?>"
               <?php echo $all_active ? 'aria-current="true"' : ''; ?>>All</a>
            <div class="tla-alpha-divider" aria-hidden="true"></div>
            <?php foreach ( array_merge( range( 'A', 'Z' ), [ '#' ] ) as $letter ) :
                $has      = isset( $existing_letters[ $letter ] );
                $is_act   = ( $current_letter === $letter );
                $cls      = 'tla-alpha-btn' . ( ! $has ? ' disabled' : '' ) . ( $is_act ? ' active' : '' );
            ?>
            <a href="<?php echo esc_url( $has ? tls_authors_url( $base_url, $letter, $search_query ) : '#' ); ?>"
               class="<?php echo esc_attr( $cls ); ?>"
               <?php echo ! $has ? 'aria-disabled="true" tabindex="-1"' : ''; ?>
               <?php echo $is_act ? 'aria-current="true"' : ''; ?>
               data-letter="<?php echo esc_attr( $letter ); ?>">
               <?php echo esc_html( $letter ); ?>
            </a>
            <?php endforeach; ?>
        </nav>

    </div>
</div>

<!-- ══════════ GRID ══════════ -->
<div class="tla-main" id="tla-authors-container">

<?php if ( empty( $paged_terms ) ) : ?>
    <div class="tla-no-results">
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
            <circle cx="22" cy="22" r="14"/><line x1="38" y1="38" x2="30" y2="30"/>
            <line x1="18" y1="22" x2="26" y2="22"/><line x1="22" y1="18" x2="22" y2="26"/>
        </svg>
        <strong>No authors found</strong>
        <p>
            <?php if ( $search_query ) : ?>
                No results for &ldquo;<?php echo esc_html( $search_query ); ?>&rdquo;. &nbsp;
                <a href="<?php echo esc_url( $base_url ); ?>" style="color:var(--tls-green);">Clear filter</a>
            <?php elseif ( $current_letter ) : ?>
                No authors starting with &ldquo;<?php echo esc_html( $current_letter ); ?>&rdquo;. &nbsp;
                <a href="<?php echo esc_url( $base_url ); ?>" style="color:var(--tls-green);">Show all</a>
            <?php else : ?>
                The authors taxonomy appears to be empty.
            <?php endif; ?>
        </p>
    </div>

<?php else :

    /* Group terms by first letter */
    $grouped = [];
    foreach ( $paged_terms as $term ) {
        $first = mb_strtoupper( mb_substr( $term->name, 0, 1 ) );
        if ( ! ctype_alpha( $first ) ) $first = '#';
        $grouped[ $first ][] = $term;
    }

    foreach ( $grouped as $grp_letter => $terms ) :
        $anchor = 'letter-' . strtolower( $grp_letter );
?>
    <div class="tla-letter-group" id="<?php echo esc_attr( $anchor ); ?>">
        <h2 class="tla-letter-header">
            <?php echo $grp_letter === '#' ? 'Other' : esc_html( $grp_letter ); ?>
        </h2>
        <div class="tla-grid">
        <?php foreach ( $terms as $term ) :
            $t_url   = get_term_link( $term );
            $t_name  = $term->name;
            $t_desc  = $term->description;
            $t_count = (int) $term->count;
            $initial = mb_strtoupper( mb_substr( $t_name, 0, 1 ) );
        ?>
            <a href="<?php echo esc_url( is_wp_error( $t_url ) ? '#' : $t_url ); ?>"
               class="tla-card"
               data-name="<?php echo esc_attr( mb_strtolower( $t_name ) ); ?>">
                <div class="tla-card-initial" aria-hidden="true"><?php echo esc_html( $initial ); ?></div>
                <p class="tla-card-name"><?php echo esc_html( $t_name ); ?></p>
                <?php if ( $t_desc ) : ?>
                    <p class="tla-card-desc"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $t_desc ), 12 ) ); ?></p>
                <?php endif; ?>
                <span class="tla-card-count">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/>
                    </svg>
                    <?php echo number_format( $t_count ) . ' ' . ( $t_count === 1 ? 'work' : 'works' ); ?>
                </span>
            </a>
        <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
    <nav class="tla-pagination" aria-label="Authors pagination">
        <?php if ( $paged > 1 ) : ?>
            <a href="<?php echo esc_url( tls_authors_url( $base_url, $current_letter, $search_query, $paged - 1 ) ); ?>"
               class="tla-page-btn" aria-label="Previous page">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
        <?php endif; ?>

        <?php
        $ellipsis = false;
        for ( $p = 1; $p <= $total_pages; $p++ ) :
            $show = ( $p === 1 || $p === $total_pages || abs( $p - $paged ) <= 2 );
            if ( ! $show ) {
                if ( ! $ellipsis ) { echo '<span class="tla-page-btn dots">&hellip;</span>'; $ellipsis = true; }
                continue;
            }
            $ellipsis = false;
        ?>
            <?php if ( $p === $paged ) : ?>
                <span class="tla-page-btn current" aria-current="page"><?php echo $p; ?></span>
            <?php else : ?>
                <a href="<?php echo esc_url( tls_authors_url( $base_url, $current_letter, $search_query, $p ) ); ?>"
                   class="tla-page-btn" aria-label="Page <?php echo $p; ?>"><?php echo $p; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ( $paged < $total_pages ) : ?>
            <a href="<?php echo esc_url( tls_authors_url( $base_url, $current_letter, $search_query, $paged + 1 ) ); ?>"
               class="tla-page-btn" aria-label="Next page">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
            </a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

<?php endif; ?>

</div><!-- /.tla-main -->
</main>

<!-- ══════════ CLIENT-SIDE INSTANT SEARCH ══════════ -->
<script>
(function () {
    'use strict';
    var input    = document.getElementById('tla-search-input');
    var clearBtn = document.getElementById('tla-search-clear');
    var countEl  = document.getElementById('tla-result-count');
    var container = document.getElementById('tla-authors-container');

    if (!input || !container) return;

    var timer = null;

    function filterCards(q) {
        q = q.trim().toLowerCase();
        var groups  = container.querySelectorAll('.tla-letter-group');
        var total   = 0;

        groups.forEach(function (group) {
            var cards   = group.querySelectorAll('.tla-card');
            var visible = 0;
            cards.forEach(function (card) {
                var match = !q || (card.dataset.name || '').includes(q);
                card.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            total += visible;
            group.style.display = visible ? '' : 'none';
        });

        if (countEl) {
            countEl.innerHTML = total
                ? '<span>' + total.toLocaleString() + '</span>&nbsp;author' + (total !== 1 ? 's' : '')
                : 'No results';
        }
        if (clearBtn) clearBtn.classList.toggle('visible', q.length > 0);
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var val = this.value;
        timer = setTimeout(function () { filterCards(val); }, 100);
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            filterCards('');
            input.focus();
        });
    }

    /* Scroll to active letter group on alphabet click */
    document.querySelectorAll('.tla-alpha-btn.active[data-letter]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var anchor = document.getElementById('letter-' + btn.dataset.letter.toLowerCase());
            if (anchor) {
                var toolbar = document.getElementById('tla-toolbar');
                var off = toolbar ? toolbar.offsetHeight + 12 : 80;
                window.scrollTo({ top: anchor.getBoundingClientRect().top + window.scrollY - off, behavior: 'smooth' });
            }
        });
    });

    if (input.value) filterCards(input.value);
})();
</script>

<?php get_footer(); ?>
