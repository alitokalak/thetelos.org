<?php
/**
 * The Telos — Archive / Category Template
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$archive_title = function_exists( 'mediumish_archive_title' )
    ? mediumish_archive_title()
    : get_the_archive_title();

$archive_desc  = get_the_archive_description();
$paged         = max( 1, get_query_var( 'paged' ) ?: 1 );
?>

<main id="main" role="main">

<!-- ══════════════════════════════════
     ARCHIVE HERO
══════════════════════════════════ -->
<div class="tls-archive-hero">
    <div class="container">
        <p class="tls-archive-eyebrow">
            <?php
            if ( is_category() ) echo 'Category';
            elseif ( is_tag() )  echo 'Tag';
            elseif ( is_date() ) echo 'Archive';
            else                 echo 'Archive';
            ?>
        </p>
        <h1 class="tls-archive-title"><?php echo wp_kses_post( $archive_title ); ?></h1>
        <?php if ( $archive_desc ) : ?>
            <p class="tls-archive-desc"><?php echo wp_kses_post( $archive_desc ); ?></p>
        <?php endif; ?>
        <?php
        global $wp_query;
        $total = (int) $wp_query->found_posts;
        if ( $total > 0 ) :
        ?>
        <p class="tls-archive-count"><?php echo number_format( $total ); ?> summaries</p>
        <?php endif; ?>
    </div>
</div>

<!-- ══════════════════════════════════
     BOOKS GRID
══════════════════════════════════ -->
<div class="container" style="padding-bottom:56px;">

    <?php if ( have_posts() ) : ?>
    <div class="tls-books-grid" id="tls-books-grid">
        <?php while ( have_posts() ) : the_post(); ?>
            <?php echo thetelos_book_card( get_the_ID() ); ?>
        <?php endwhile; ?>
    </div>

    <!-- ══════════════════════════════════
         PAGINATION  (Load More + infinite scroll,
         with a numeric pager fallback for no-JS / SEO)
    ══════════════════════════════════ -->
    <?php
    $max_pages = (int) $wp_query->max_num_pages;
    $per_page  = (int) get_query_var( 'posts_per_page' );
    if ( $per_page < 1 ) { $per_page = (int) get_option( 'posts_per_page', 10 ); }
    $shown_now = $total > 0 ? min( $paged * $per_page, $total ) : $wp_query->post_count;
    $next_url  = ( $paged < $max_pages ) ? get_pagenum_link( $paged + 1 ) : '';

    if ( $max_pages > 1 ) :
    ?>
    <nav class="tls-pagination" aria-label="<?php esc_attr_e( 'Category pagination', 'mediumish' ); ?>"
         data-paged="<?php echo esc_attr( $paged ); ?>"
         data-max="<?php echo esc_attr( $max_pages ); ?>"
         data-total="<?php echo esc_attr( $total ); ?>">

        <?php if ( $next_url ) : ?>
        <div class="tls-loadmore-row">
            <button type="button" class="tls-loadmore" data-next="<?php echo esc_url( $next_url ); ?>">
                <span class="tls-loadmore-txt"><?php esc_html_e( 'Load more summaries', 'mediumish' ); ?></span>
            </button>
        </div>
        <?php endif; ?>

        <p class="tls-loadmore-status" aria-live="polite">
            <?php
            printf(
                /* translators: 1: number of summaries shown, 2: total summaries */
                esc_html__( 'Showing %1$s of %2$s summaries', 'mediumish' ),
                '<strong class="tls-shown">' . esc_html( number_format( $shown_now ) ) . '</strong>',
                esc_html( number_format( $total ) )
            );
            ?>
        </p>

        <!-- Numeric pager — no-JS / SEO fallback -->
        <div class="tls-pager">
            <?php
            echo paginate_links( [
                'total'     => $max_pages,
                'current'   => $paged,
                'mid_size'  => 1,
                'end_size'  => 1,
                'prev_text' => '&lsaquo;',
                'next_text' => '&rsaquo;',
            ] );
            ?>
        </div>
    </nav>
    <?php endif; ?>

    <?php else : ?>
        <p style="color:var(--tls-muted);font-family:var(--tls-sans);padding:40px 0;">
            <?php _e( 'No posts found.', 'mediumish' ); ?>
        </p>
    <?php endif; ?>

</div>

</main>

<!-- ══════════════════════════════════
     PAGINATION STYLES
══════════════════════════════════ -->
<style>
.tls-pagination{ margin:12px 0 4px; text-align:center; }

/* Load More is progressive enhancement: hidden until JS opts in */
.tls-loadmore-row{ display:none; justify-content:center; margin:8px 0 18px; }
.tls-pagination.is-enhanced .tls-loadmore-row{ display:flex; }
.tls-pagination.is-enhanced .tls-pager{ display:none; }

.tls-loadmore{
    font-family:var(--tls-sans); font-size:15px; font-weight:600; letter-spacing:.01em;
    color:var(--tls-bg-dark); background:var(--tls-bg-card);
    border:1px solid var(--tls-border); border-radius:999px;
    padding:14px 34px; cursor:pointer;
    display:inline-flex; align-items:center; gap:10px;
    box-shadow:var(--tls-shadow-sm); transition:background .18s ease, color .18s ease, border-color .18s ease, transform .18s ease, box-shadow .18s ease;
}
.tls-loadmore:hover{
    background:var(--tls-bg-dark); color:#fff; border-color:var(--tls-bg-dark);
    transform:translateY(-1px); box-shadow:var(--tls-shadow);
}
.tls-loadmore:focus-visible{ outline:2px solid var(--tls-gold); outline-offset:3px; }
.tls-loadmore:disabled{ cursor:default; }
.tls-loadmore::after{
    content:""; width:15px; height:15px; border-radius:50%;
    border:2px solid currentColor; border-top-color:transparent;
    display:none; animation:tls-spin .6s linear infinite;
}
.tls-loadmore.is-loading .tls-loadmore-txt{ opacity:.55; }
.tls-loadmore.is-loading::after{ display:inline-block; }
@keyframes tls-spin{ to{ transform:rotate(360deg); } }

.tls-loadmore-status{
    font-family:var(--tls-sans); font-size:13px; color:var(--tls-muted);
    margin:2px 0 0; letter-spacing:.02em;
}
.tls-loadmore-status strong{ color:var(--tls-bg-dark); font-weight:600; }

/* Numeric pager (fallback) */
.tls-pager{ display:flex; flex-wrap:wrap; gap:8px; justify-content:center; margin-top:14px; }
.tls-pager .page-numbers{
    min-width:42px; height:42px; padding:0 13px;
    display:inline-flex; align-items:center; justify-content:center;
    font-family:var(--tls-sans); font-size:14px; line-height:1;
    color:var(--tls-muted); text-decoration:none;
    background:var(--tls-bg-card); border:1px solid var(--tls-border);
    border-radius:var(--tls-radius); transition:all .15s ease;
}
.tls-pager a.page-numbers:hover{ border-color:var(--tls-bg-dark); color:var(--tls-bg-dark); }
.tls-pager .page-numbers.current{ background:var(--tls-bg-dark); border-color:var(--tls-bg-dark); color:#fff; font-weight:600; }
.tls-pager .page-numbers.dots{ border:none; background:none; min-width:20px; }

/* Newly appended cards fade in */
.tls-card-in{ animation:tls-fade-in .45s ease both; }
@keyframes tls-fade-in{ from{ opacity:0; transform:translateY(10px); } to{ opacity:1; transform:none; } }

@media (prefers-reduced-motion: reduce){
    .tls-loadmore:hover{ transform:none; }
    .tls-card-in{ animation:none; }
    .tls-loadmore.is-loading::after{ animation-duration:1.2s; }
}
</style>

<!-- ══════════════════════════════════
     LOAD MORE / INFINITE SCROLL
══════════════════════════════════ -->
<script>
(function () {
    var box = document.querySelector('.tls-pagination');
    if (!box) return;
    var grid = document.getElementById('tls-books-grid');
    var btn  = box.querySelector('.tls-loadmore');
    if (!grid || !btn) return;

    // Opt in to the enhanced experience (swaps numeric pager for Load More).
    box.classList.add('is-enhanced');

    var shownEl     = box.querySelector('.tls-shown');
    var total       = parseInt(box.getAttribute('data-total'), 10) || 0;
    var currentPage = parseInt(box.getAttribute('data-paged'), 10) || 1;
    var loading = false, done = false;

    function setLoading(v) {
        loading = v;
        btn.classList.toggle('is-loading', v);
        btn.disabled = v;
    }

    function stop() {
        done = true;
        setLoading(false);
        var row = box.querySelector('.tls-loadmore-row');
        if (row) row.style.display = 'none';
    }

    // If anything goes wrong, fall back to the classic numeric pager so
    // the reader is never stranded without a way forward.
    function bailToPager() {
        setLoading(false);
        box.classList.remove('is-enhanced');
    }

    function updateShown(added) {
        if (!shownEl) return;
        var n = parseInt(shownEl.textContent.replace(/[^0-9]/g, ''), 10) || 0;
        n += added;
        if (total && n > total) n = total;
        shownEl.textContent = n.toLocaleString();
    }

    function loadNext() {
        if (loading || done) return;
        var url = btn.getAttribute('data-next');
        if (!url) { stop(); return; }
        setLoading(true);

        fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
            .then(function (html) {
                var doc     = new DOMParser().parseFromString(html, 'text/html');
                var newBox  = doc.querySelector('.tls-pagination');
                var newGrid = doc.getElementById('tls-books-grid');
                var fetchedPage = newBox ? (parseInt(newBox.getAttribute('data-paged'), 10) || 0) : 0;

                // Guard against redirect loops (e.g. an over-reported last page
                // bouncing back to page 1): only accept a strictly newer page.
                if (!newGrid || !newGrid.children.length || fetchedPage <= currentPage) {
                    stop();
                    return;
                }

                var added = 0;
                while (newGrid.firstElementChild) {
                    var node = newGrid.removeChild(newGrid.firstElementChild);
                    node.classList.add('tls-card-in');
                    grid.appendChild(node);
                    added++;
                }
                currentPage = fetchedPage;
                updateShown(added);

                var nextBtn = newBox.querySelector('.tls-loadmore[data-next]');
                if (nextBtn) {
                    btn.setAttribute('data-next', nextBtn.getAttribute('data-next'));
                    setLoading(false);
                } else {
                    stop();
                }
            })
            .catch(function () { bailToPager(); });
    }

    btn.addEventListener('click', loadNext);

    // Infinite scroll: auto-load as the button nears the viewport.
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) { if (e.isIntersecting) loadNext(); });
        }, { rootMargin: '500px 0px' });
        io.observe(btn);
    }
})();
</script>

<?php get_footer(); ?>
