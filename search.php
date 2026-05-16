<?php
/**
 * The Telos — Search Results Template
 * Yazar filtresi · Kategori filtresi · Sıralama · Sonuç sayısı
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

global $wp_query;
$search_query = get_search_query();
$total        = (int) $wp_query->found_posts;
$paged        = max( 1, get_query_var( 'paged' ) );

/* ── Aktif filtreler (GET parametreleri) ── */
$filter_cat    = isset( $_GET['tls_cat'] )    ? (int) $_GET['tls_cat']                        : 0;
$filter_author = isset( $_GET['tls_author'] ) ? sanitize_text_field( $_GET['tls_author'] )    : '';
$filter_sort   = isset( $_GET['tls_sort'] )   ? sanitize_text_field( $_GET['tls_sort'] )      : 'relevance';

/* ── Arama sorgusunu filtrelerle yeniden çalıştır ── */
$search_args = [
    'post_type'      => 'post',
    'post_status'    => 'publish',
    's'              => $search_query,
    'posts_per_page' => 12,
    'paged'          => $paged,
];

if ( $filter_cat ) {
    $search_args['cat'] = $filter_cat;
}

if ( $filter_author ) {
    $search_args['tax_query'] = [[
        'taxonomy' => 'authors',
        'field'    => 'slug',
        'terms'    => $filter_author,
    ]];
}

switch ( $filter_sort ) {
    case 'newest':
        $search_args['orderby'] = 'date';
        $search_args['order']   = 'DESC';
        break;
    case 'oldest':
        $search_args['orderby'] = 'date';
        $search_args['order']   = 'ASC';
        break;
    case 'alpha':
        $search_args['orderby'] = 'title';
        $search_args['order']   = 'ASC';
        break;
    default:
        /* relevance — WordPress varsayılanı */
        break;
}

$search_q = new WP_Query( $search_args );
$total     = (int) $search_q->found_posts;

/* ── Kenar çubuğu verileri ── */
$all_cats = get_categories( [
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 20,
    'hide_empty' => true,
] );

$all_authors = get_terms( [
    'taxonomy'   => 'authors',
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 30,
    'hide_empty' => true,
] );

/* ── Filtre URL oluşturucu ── */
function tls_search_url( $extra = [] ) {
    $base = [ 's' => get_search_query() ];
    if ( isset( $_GET['tls_cat'] )    && $_GET['tls_cat'] )    $base['tls_cat']    = (int) $_GET['tls_cat'];
    if ( isset( $_GET['tls_author'] ) && $_GET['tls_author'] ) $base['tls_author'] = sanitize_text_field( $_GET['tls_author'] );
    if ( isset( $_GET['tls_sort'] )   && $_GET['tls_sort'] )   $base['tls_sort']   = sanitize_text_field( $_GET['tls_sort'] );
    $merged = array_merge( $base, $extra );
    return home_url( '/?' . http_build_query( array_filter( $merged ) ) );
}
?>

<main id="main" role="main" class="tls-search-page">

<!-- ════════════════════════════════
     SEARCH HERO
════════════════════════════════ -->
<div class="tls-search-hero">
    <div class="container">
        <p class="tls-search-eyebrow">Search the Archive</p>
        <form class="tls-search-hero-form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <input type="search" name="s"
                   value="<?php echo esc_attr( $search_query ); ?>"
                   placeholder="Search by title, author, or concept…"
                   autocomplete="off"
                   aria-label="Search the archive"
                   class="tls-search-hero-input">
            <?php if ( $filter_cat )    : ?><input type="hidden" name="tls_cat"    value="<?php echo (int)$filter_cat; ?>"><?php endif; ?>
            <?php if ( $filter_author ) : ?><input type="hidden" name="tls_author" value="<?php echo esc_attr($filter_author); ?>"><?php endif; ?>
            <?php if ( $filter_sort )   : ?><input type="hidden" name="tls_sort"   value="<?php echo esc_attr($filter_sort); ?>"><?php endif; ?>
            <button type="submit" class="tls-search-hero-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Search
            </button>
        </form>

        <?php if ( $search_query ) : ?>
        <div class="tls-search-meta">
            <?php if ( $total > 0 ) : ?>
                <span class="tls-search-count">
                    <strong><?php echo number_format( $total ); ?></strong>
                    result<?php echo $total !== 1 ? 's' : ''; ?> for
                    &ldquo;<?php echo esc_html( $search_query ); ?>&rdquo;
                </span>
            <?php else : ?>
                <span class="tls-search-count">No results for &ldquo;<?php echo esc_html( $search_query ); ?>&rdquo;</span>
            <?php endif; ?>

            <!-- Aktif filtre pill'leri -->
            <?php if ( $filter_cat || $filter_author || ( $filter_sort && $filter_sort !== 'relevance' ) ) : ?>
            <div class="tls-active-filters">
                <?php if ( $filter_cat ) :
                    $cat_obj = get_category( $filter_cat );
                    if ( $cat_obj ) :
                ?>
                <a class="tls-filter-pill" href="<?php echo esc_url( tls_search_url( ['tls_cat' => ''] ) ); ?>">
                    <?php echo esc_html( $cat_obj->name ); ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="10" height="10" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
                <?php endif; endif; ?>

                <?php if ( $filter_author ) : ?>
                <a class="tls-filter-pill" href="<?php echo esc_url( tls_search_url( ['tls_author' => ''] ) ); ?>">
                    <?php echo esc_html( $filter_author ); ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="10" height="10" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
                <?php endif; ?>

                <?php if ( $filter_sort && $filter_sort !== 'relevance' ) :
                    $sort_labels = [ 'newest' => 'Newest first', 'oldest' => 'Oldest first', 'alpha' => 'A → Z' ];
                ?>
                <a class="tls-filter-pill" href="<?php echo esc_url( tls_search_url( ['tls_sort' => ''] ) ); ?>">
                    <?php echo esc_html( $sort_labels[ $filter_sort ] ?? $filter_sort ); ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="10" height="10" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
                <?php endif; ?>

                <a class="tls-filter-pill tls-filter-pill--clear" href="<?php echo esc_url( home_url( '/?s=' . urlencode( $search_query ) ) ); ?>">
                    Clear all filters
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ════════════════════════════════
     İÇERİK: Kenar Çubuğu + Sonuçlar
════════════════════════════════ -->
<div class="container tls-search-layout">

    <!-- ── Sol: Filtre Kenar Çubuğu ── -->
    <aside class="tls-search-sidebar" aria-label="Search filters">

        <!-- Sıralama -->
        <div class="tls-filter-group">
            <h3 class="tls-filter-group-title">Sort by</h3>
            <div class="tls-sort-options">
                <?php
                $sort_options = [
                    'relevance' => 'Most Relevant',
                    'newest'    => 'Newest First',
                    'oldest'    => 'Oldest First',
                    'alpha'     => 'Title A → Z',
                ];
                foreach ( $sort_options as $val => $label ) :
                    $active = ( $filter_sort === $val || ( $val === 'relevance' && ! $filter_sort ) );
                ?>
                <a class="tls-sort-opt<?php echo $active ? ' active' : ''; ?>"
                   href="<?php echo esc_url( tls_search_url( ['tls_sort' => $val] ) ); ?>">
                    <?php if ( $active ) : ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php endif; ?>
                    <?php echo esc_html( $label ); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Kategori filtresi -->
        <?php if ( ! empty( $all_cats ) ) : ?>
        <div class="tls-filter-group">
            <h3 class="tls-filter-group-title">Category</h3>
            <ul class="tls-filter-list">
                <li>
                    <a class="tls-filter-item<?php echo ! $filter_cat ? ' active' : ''; ?>"
                       href="<?php echo esc_url( tls_search_url( ['tls_cat' => ''] ) ); ?>">
                        All Categories
                        <span class="tls-filter-count"><?php echo number_format( $total ); ?></span>
                    </a>
                </li>
                <?php foreach ( $all_cats as $cat ) : ?>
                <li>
                    <a class="tls-filter-item<?php echo $filter_cat === $cat->term_id ? ' active' : ''; ?>"
                       href="<?php echo esc_url( tls_search_url( ['tls_cat' => $cat->term_id] ) ); ?>">
                        <?php echo esc_html( $cat->name ); ?>
                        <span class="tls-filter-count"><?php echo number_format( $cat->count ); ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Yazar filtresi -->
        <?php if ( ! empty( $all_authors ) && ! is_wp_error( $all_authors ) ) : ?>
        <div class="tls-filter-group">
            <h3 class="tls-filter-group-title">Author</h3>
            <ul class="tls-filter-list" id="tls-author-list">
                <li>
                    <a class="tls-filter-item<?php echo ! $filter_author ? ' active' : ''; ?>"
                       href="<?php echo esc_url( tls_search_url( ['tls_author' => ''] ) ); ?>">
                        All Authors
                    </a>
                </li>
                <?php foreach ( $all_authors as $i => $auth ) : ?>
                <li<?php echo $i >= 8 ? ' class="tls-author-extra" style="display:none;"' : ''; ?>>
                    <a class="tls-filter-item<?php echo $filter_author === $auth->slug ? ' active' : ''; ?>"
                       href="<?php echo esc_url( tls_search_url( ['tls_author' => $auth->slug] ) ); ?>">
                        <?php echo esc_html( $auth->name ); ?>
                        <span class="tls-filter-count"><?php echo number_format( $auth->count ); ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
                <?php if ( count( $all_authors ) > 8 ) : ?>
                <li>
                    <button class="tls-show-more-authors" type="button" id="tls-show-more-authors">
                        Show <?php echo count( $all_authors ) - 8; ?> more
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Arşive git -->
        <div class="tls-filter-group tls-filter-group--archive">
            <a class="tls-archive-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                Browse full archive
            </a>
        </div>

    </aside>

    <!-- ── Sağ: Sonuçlar ── -->
    <div class="tls-search-results">

        <?php if ( $search_q->have_posts() ) : ?>

        <div class="tls-books-grid">
            <?php while ( $search_q->have_posts() ) : $search_q->the_post(); ?>
                <?php echo thetelos_book_card( get_the_ID() ); ?>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>

        <!-- Sayfalama -->
        <?php if ( $search_q->max_num_pages > 1 ) : ?>
        <div class="bottompagination" style="margin-top:40px;">
            <?php
            if ( function_exists( 'wp_bootstrap_pagination' ) ) {
                wp_bootstrap_pagination( [
                    'custom_query'    => $search_q,
                    'previous_string' => '&laquo;',
                    'next_string'     => '&raquo;',
                    'before_output'   => '<span class="navigation">',
                    'after_output'    => '</span>',
                ] );
            } else {
                $big = 999999;
                echo paginate_links( [
                    'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
                    'format'  => '?paged=%#%',
                    'current' => $paged,
                    'total'   => $search_q->max_num_pages,
                ] );
            }
            ?>
        </div>
        <?php endif; ?>

        <?php else : ?>

        <!-- Sonuç yok -->
        <div class="tls-search-empty">
            <div class="tls-search-empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" width="56" height="56" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
            <h2 class="tls-search-empty-title">No results found</h2>
            <p class="tls-search-empty-desc">
                Try different keywords<?php echo ( $filter_cat || $filter_author ) ? ' or remove some filters' : ''; ?>.
            </p>
            <div class="tls-search-suggestions">
                <p class="tls-suggestions-label">Suggestions</p>
                <ul>
                    <li>Check the spelling of your search term</li>
                    <li>Try broader or fewer keywords</li>
                    <li>Search for the author's name instead</li>
                    <?php if ( $filter_cat || $filter_author ) : ?>
                    <li><a href="<?php echo esc_url( home_url( '/?s=' . urlencode( $search_query ) ) ); ?>">Remove all active filters</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <?php
            /* Popüler kitaplar göster */
            $popular = get_posts( [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 4,
                'meta_key'       => 'post_views_count',
                'orderby'        => 'meta_value_num',
                'order'          => 'DESC',
            ] );
            if ( ! empty( $popular ) ) :
            ?>
            <div class="tls-search-popular">
                <p class="tls-suggestions-label">Popular summaries</p>
                <div class="tls-books-grid">
                    <?php foreach ( $popular as $p ) : ?>
                        <?php echo thetelos_book_card( $p->ID ); ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>

    </div><!-- /.tls-search-results -->

</div><!-- /.container -->

</main>

<style>
/* ════════════════════════════════
   SEARCH PAGE STYLES
════════════════════════════════ */

/* Hero */
.tls-search-hero {
    background: var(--tls-bg-dark);
    padding: 52px 0 44px;
}
.tls-search-eyebrow {
    font-family: var(--tls-sans);
    font-size: 11px; font-weight: 700;
    letter-spacing: .16em; text-transform: uppercase;
    color: var(--tls-gold); margin: 0 0 14px;
}
.tls-search-hero-form {
    display: flex;
    max-width: 620px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.12);
    border-radius: var(--tls-radius-lg);
    overflow: hidden;
    transition: border-color .2s;
}
.tls-search-hero-form:focus-within {
    border-color: rgba(200,161,101,.5);
}
.tls-search-hero-input {
    flex: 1;
    padding: 14px 20px;
    border: none;
    background: none;
    font-family: var(--tls-sans);
    font-size: 16px;
    color: #fff;
    outline: none;
    min-width: 0;
}
.tls-search-hero-input::placeholder { color: rgba(255,255,255,.3); }
.tls-search-hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 0 24px;
    background: var(--tls-gold);
    color: #fff;
    border: none;
    font-family: var(--tls-sans);
    font-size: 12px; font-weight: 700;
    letter-spacing: .08em; text-transform: uppercase;
    cursor: pointer;
    white-space: nowrap;
    transition: background .2s;
    flex-shrink: 0;
}
.tls-search-hero-btn:hover { background: var(--tls-gold-light); }

/* Meta satırı */
.tls-search-meta {
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.tls-search-count {
    font-family: var(--tls-sans);
    font-size: 14px;
    color: rgba(255,255,255,.45);
}
.tls-search-count strong {
    color: #fff;
    font-weight: 600;
}

/* Aktif filtre pill'leri */
.tls-active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.tls-filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: rgba(200,161,101,.15);
    border: 1px solid rgba(200,161,101,.35);
    border-radius: 20px;
    font-family: var(--tls-sans);
    font-size: 11px; font-weight: 600;
    color: var(--tls-gold) !important;
    text-decoration: none !important;
    transition: all .15s;
}
.tls-filter-pill:hover {
    background: rgba(200,161,101,.25);
}
.tls-filter-pill--clear {
    background: rgba(255,255,255,.06);
    border-color: rgba(255,255,255,.12);
    color: rgba(255,255,255,.45) !important;
}
.tls-filter-pill--clear:hover {
    background: rgba(255,255,255,.1);
    color: #fff !important;
}

/* Layout */
.tls-search-layout {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 40px;
    padding-top: 40px;
    padding-bottom: 64px;
    align-items: start;
}
@media (max-width: 768px) {
    .tls-search-layout {
        grid-template-columns: 1fr;
    }
    .tls-search-sidebar {
        order: 2;
    }
    .tls-search-results {
        order: 1;
    }
}

/* Sidebar */
.tls-search-sidebar {
    position: sticky;
    top: calc(var(--tls-nav-h) + 20px);
}
.tls-filter-group {
    margin-bottom: 28px;
    padding-bottom: 28px;
    border-bottom: 1px solid var(--tls-border);
}
.tls-filter-group:last-child {
    border-bottom: none;
    margin-bottom: 0;
}
.tls-filter-group-title {
    font-family: var(--tls-sans);
    font-size: 10px; font-weight: 700;
    letter-spacing: .14em; text-transform: uppercase;
    color: var(--tls-muted);
    margin: 0 0 12px;
}

/* Sıralama */
.tls-sort-options {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.tls-sort-opt {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 10px;
    border-radius: 6px;
    font-family: var(--tls-sans);
    font-size: 13px;
    color: var(--tls-muted) !important;
    text-decoration: none !important;
    transition: background .15s, color .15s;
}
.tls-sort-opt:hover { background: var(--tls-bg); color: var(--tls-bg-dark) !important; }
.tls-sort-opt.active {
    background: var(--tls-bg);
    color: var(--tls-bg-dark) !important;
    font-weight: 600;
}

/* Filtre listesi */
.tls-filter-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.tls-filter-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 7px 10px;
    border-radius: 6px;
    font-family: var(--tls-sans);
    font-size: 13px;
    color: var(--tls-muted) !important;
    text-decoration: none !important;
    transition: background .15s, color .15s;
}
.tls-filter-item:hover { background: var(--tls-bg); color: var(--tls-bg-dark) !important; }
.tls-filter-item.active {
    background: var(--tls-bg-dark);
    color: #fff !important;
    font-weight: 600;
}
.tls-filter-count {
    font-size: 11px;
    color: var(--tls-muted);
    background: var(--tls-bg);
    padding: 1px 6px;
    border-radius: 8px;
    margin-left: 4px;
    flex-shrink: 0;
}
.tls-filter-item.active .tls-filter-count {
    background: rgba(255,255,255,.15);
    color: rgba(255,255,255,.6);
}
.tls-show-more-authors {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 7px 10px;
    background: none;
    border: none;
    font-family: var(--tls-sans);
    font-size: 12px;
    color: var(--tls-gold);
    cursor: pointer;
    transition: color .15s;
}
.tls-show-more-authors:hover { color: var(--tls-gold-light); }

/* Arşiv linki */
.tls-filter-group--archive { border: none; }
.tls-archive-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-family: var(--tls-sans);
    font-size: 12px; font-weight: 600;
    letter-spacing: .06em; text-transform: uppercase;
    color: var(--tls-muted) !important;
    text-decoration: none !important;
    transition: color .15s;
}
.tls-archive-link:hover { color: var(--tls-bg-dark) !important; }

/* Sonuç yok */
.tls-search-empty {
    padding: 48px 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.tls-search-empty-icon { color: var(--tls-border); }
.tls-search-empty-title {
    font-family: var(--tls-serif);
    font-size: 26px; font-weight: 400;
    color: var(--tls-bg-dark);
    margin: 0;
}
.tls-search-empty-desc {
    font-family: var(--tls-sans);
    font-size: 15px; color: var(--tls-muted);
    margin: 0;
}
.tls-suggestions-label {
    font-family: var(--tls-sans);
    font-size: 10px; font-weight: 700;
    letter-spacing: .12em; text-transform: uppercase;
    color: var(--tls-muted);
    margin: 0 0 8px;
}
.tls-search-suggestions ul {
    list-style: disc;
    padding-left: 18px;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.tls-search-suggestions li {
    font-family: var(--tls-sans);
    font-size: 14px;
    color: var(--tls-muted);
}
.tls-search-suggestions a {
    color: var(--tls-gold) !important;
}
.tls-search-popular { margin-top: 8px; }

/* Mobile: sidebar collapse */
@media (max-width: 768px) {
    .tls-search-sidebar {
        position: static;
        background: #fff;
        border: 1px solid var(--tls-border);
        border-radius: 12px;
        padding: 20px;
    }
    .tls-filter-group {
        margin-bottom: 20px;
        padding-bottom: 20px;
    }
}
</style>

<script>
(function(){
    /* Yazar listesi "show more" */
    var showMoreBtn = document.getElementById('tls-show-more-authors');
    if (showMoreBtn) {
        showMoreBtn.addEventListener('click', function() {
            document.querySelectorAll('.tls-author-extra').forEach(function(el) {
                el.style.display = '';
            });
            showMoreBtn.parentElement.style.display = 'none';
        });
    }

    /* Mobile: sidebar toggle */
    var sidebar = document.querySelector('.tls-search-sidebar');
    if (sidebar && window.innerWidth <= 768) {
        var toggleBtn = document.createElement('button');
        toggleBtn.className = 'tls-sidebar-toggle';
        toggleBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M4 6h16M4 12h16M4 18h7"/></svg> Filters';
        toggleBtn.style.cssText = 'display:flex;align-items:center;gap:6px;padding:8px 16px;background:var(--tls-bg-dark);color:#fff;border:none;border-radius:6px;font-family:var(--tls-sans);font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;margin-bottom:16px;';

        var resultsDiv = document.querySelector('.tls-search-results');
        if (resultsDiv) {
            resultsDiv.insertAdjacentElement('beforebegin', toggleBtn);
        }

        var sidebarContent = sidebar.innerHTML;
        sidebar.style.display = 'none';

        toggleBtn.addEventListener('click', function() {
            if (sidebar.style.display === 'none') {
                sidebar.style.display = '';
                toggleBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M18 6L6 18M6 6l12 12"/></svg> Close';
            } else {
                sidebar.style.display = 'none';
                toggleBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M4 6h16M4 12h16M4 18h7"/></svg> Filters';
            }
        });
    }
})();
</script>

<?php get_footer(); ?>
