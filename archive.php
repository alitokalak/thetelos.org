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
$cat_id        = (int) get_queried_object_id();
$is_filterable = ( is_category() || is_tag() );
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

<?php if ( $is_filterable && $total > 0 ) : ?>
<!-- ══════════════════════════════════
     FILTER TOOLBAR  (live search + A–Z)
══════════════════════════════════ -->
<div class="tls-filterbar" data-cat="<?php echo esc_attr( $cat_id ); ?>"
     data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
    <div class="container tls-filterbar-inner">
        <div class="tls-filter-row">
            <div class="tls-filter-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="search" class="tls-filter-input" autocomplete="off" spellcheck="false"
                       placeholder="Search works in this category…"
                       aria-label="Search this category">
                <button type="button" class="tls-filter-clear" aria-label="Clear search">&times;</button>
            </div>
            <div class="tls-filter-modes" role="group" aria-label="Filter by">
                <button type="button" class="tls-mode-btn is-active" data-mode="title">Works</button>
                <button type="button" class="tls-mode-btn" data-mode="author">Authors</button>
            </div>
            <span class="tls-filter-count" aria-live="polite"></span>
        </div>
        <div class="tls-alpha-row" role="group" aria-label="Filter by first letter">
            <button type="button" class="tls-alpha-btn is-active" data-letter="">All</button>
            <?php foreach ( str_split( 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' ) as $L ) : ?>
                <button type="button" class="tls-alpha-btn" data-letter="<?php echo esc_attr( $L ); ?>"><?php echo esc_html( $L ); ?></button>
            <?php endforeach; ?>
            <button type="button" class="tls-alpha-btn" data-letter="#">#</button>
        </div>
    </div>
</div>
<?php endif; ?>

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
    ?>
    <nav class="tls-pagination" aria-label="<?php esc_attr_e( 'Category pagination', 'mediumish' ); ?>"
         data-paged="<?php echo esc_attr( $paged ); ?>"
         data-max="<?php echo esc_attr( $max_pages ); ?>"
         data-total="<?php echo esc_attr( $total ); ?>">

        <div class="tls-loadmore-row"<?php echo $next_url ? '' : ' hidden'; ?>>
            <button type="button" class="tls-loadmore" data-next="<?php echo esc_url( $next_url ); ?>">
                <span class="tls-loadmore-txt"><?php esc_html_e( 'Load more summaries', 'mediumish' ); ?></span>
            </button>
        </div>

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

        <?php if ( $max_pages > 1 ) : ?>
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
        <?php endif; ?>
    </nav>

    <?php else : ?>
        <p style="color:var(--tls-muted);font-family:var(--tls-sans);padding:40px 0;">
            <?php _e( 'No posts found.', 'mediumish' ); ?>
        </p>
    <?php endif; ?>

</div>

</main>

<!-- ══════════════════════════════════
     ARCHIVE STYLES  (toolbar + pagination)
══════════════════════════════════ -->
<style>
/* ── Filter toolbar ─────────────────────────── */
.tls-filterbar{ margin:0 0 38px; }
.tls-filter-row{ display:flex; align-items:center; gap:14px; flex-wrap:wrap; padding-bottom:12px; }
.tls-filter-search{ position:relative; flex:1 1 300px; max-width:520px; }
.tls-filter-search > svg{ position:absolute; left:15px; top:50%; transform:translateY(-50%); width:17px; height:17px; color:var(--tls-muted); pointer-events:none; }
.tls-filter-input{
    width:100%; height:46px; padding:0 40px 0 43px;
    font-family:var(--tls-sans); font-size:15px; color:var(--tls-bg-dark);
    background:var(--tls-bg-card); border:1px solid var(--tls-border);
    border-radius:999px; outline:none; -webkit-appearance:none;
    transition:border-color .15s, box-shadow .15s;
}
.tls-filter-input::placeholder{ color:#a8a29a; }
.tls-filter-input:focus{ border-color:var(--tls-gold); box-shadow:0 0 0 3px rgba(184,146,42,.14); }
.tls-filter-clear{
    display:none; position:absolute; right:14px; top:50%; transform:translateY(-50%);
    background:none; border:none; cursor:pointer; color:var(--tls-muted);
    font-size:22px; line-height:1; padding:2px 4px;
}
.tls-filter-clear:hover{ color:var(--tls-bg-dark); }
.tls-filter-clear.is-visible{ display:block; }

.tls-filter-modes{ display:inline-flex; background:var(--tls-bg-card); border:1px solid var(--tls-border); border-radius:999px; padding:3px; }
.tls-mode-btn{
    font-family:var(--tls-sans); font-size:13px; font-weight:600; color:var(--tls-muted);
    background:none; border:none; padding:8px 17px; border-radius:999px; cursor:pointer;
    transition:background .15s, color .15s;
}
.tls-mode-btn:hover{ color:var(--tls-bg-dark); }
.tls-mode-btn.is-active{ background:var(--tls-bg-dark); color:#fff; }

.tls-filter-count{ font-family:var(--tls-sans); font-size:13px; color:var(--tls-muted); margin-left:auto; white-space:nowrap; }
.tls-filter-count strong{ color:var(--tls-bg-dark); font-weight:600; }

.tls-alpha-row{
    display:flex; align-items:center; gap:3px; overflow-x:auto;
    scrollbar-width:none; -webkit-overflow-scrolling:touch;
    padding:8px 0 2px; border-top:1px solid var(--tls-border);
}
.tls-alpha-row::-webkit-scrollbar{ display:none; }
.tls-alpha-btn{
    flex-shrink:0; min-width:32px; height:32px; padding:0 7px;
    display:inline-flex; align-items:center; justify-content:center;
    font-family:var(--tls-sans); font-size:12px; font-weight:600; letter-spacing:.02em;
    color:var(--tls-muted); background:none; border:1px solid transparent;
    border-radius:var(--tls-radius); cursor:pointer;
    transition:color .12s, background .12s, border-color .12s;
}
.tls-alpha-btn:hover{ color:var(--tls-bg-dark); background:var(--tls-bg-card); border-color:var(--tls-border); }
.tls-alpha-btn.is-active{ color:#fff; background:var(--tls-bg-dark); border-color:var(--tls-bg-dark); }

.tls-filter-empty{
    grid-column:1 / -1; text-align:center; color:var(--tls-muted);
    font-family:var(--tls-sans); font-size:15px; padding:56px 0;
}
.tls-filterbar.is-busy .tls-filter-input{ opacity:.75; }

/* ── Pagination / load more ─────────────────── */
.tls-pagination{ margin:12px 0 4px; text-align:center; }
.tls-loadmore-row{ display:none; justify-content:center; margin:8px 0 18px; }
.tls-pagination.is-enhanced .tls-loadmore-row{ display:flex; }
.tls-pagination.is-enhanced .tls-loadmore-row[hidden]{ display:none; }
.tls-pagination.is-enhanced .tls-pager{ display:none; }

.tls-loadmore{
    font-family:var(--tls-sans); font-size:15px; font-weight:600; letter-spacing:.01em;
    color:var(--tls-bg-dark); background:var(--tls-bg-card);
    border:1px solid var(--tls-border); border-radius:999px;
    padding:14px 34px; cursor:pointer;
    display:inline-flex; align-items:center; gap:10px;
    box-shadow:var(--tls-shadow-sm);
    transition:background .18s ease, color .18s ease, border-color .18s ease, transform .18s ease, box-shadow .18s ease;
}
.tls-loadmore:hover{ background:var(--tls-bg-dark); color:#fff; border-color:var(--tls-bg-dark); transform:translateY(-1px); box-shadow:var(--tls-shadow); }
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

.tls-loadmore-status{ font-family:var(--tls-sans); font-size:13px; color:var(--tls-muted); margin:2px 0 0; letter-spacing:.02em; }
.tls-loadmore-status strong{ color:var(--tls-bg-dark); font-weight:600; }

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

.tls-card-in{ animation:tls-fade-in .45s ease both; }
@keyframes tls-fade-in{ from{ opacity:0; transform:translateY(10px); } to{ opacity:1; transform:none; } }

@media (prefers-reduced-motion: reduce){
    .tls-loadmore:hover{ transform:none; }
    .tls-card-in{ animation:none; }
    .tls-loadmore.is-loading::after{ animation-duration:1.2s; }
}
@media (max-width:600px){
    .tls-filter-count{ margin-left:0; }
    .tls-filter-modes{ order:3; }
}
</style>

<!-- ══════════════════════════════════
     ARCHIVE CONTROLLER  (live filter + load more)
══════════════════════════════════ -->
<script>
(function () {
    var grid = document.getElementById('tls-books-grid');
    if (!grid) return;

    var box = document.querySelector('.tls-pagination');
    var bar = document.querySelector('.tls-filterbar');

    // ── Load-more elements ───────────────────────────────
    var btn, row, statusEl, shownEl;
    if (box) {
        box.classList.add('is-enhanced');
        row      = box.querySelector('.tls-loadmore-row');
        btn      = box.querySelector('.tls-loadmore');
        statusEl = box.querySelector('.tls-loadmore-status');
        shownEl  = box.querySelector('.tls-shown');
    }

    // ── State ────────────────────────────────────────────
    var ajaxUrl = bar ? bar.getAttribute('data-ajax') : '';
    var catId   = bar ? (parseInt(bar.getAttribute('data-cat'), 10) || 0) : 0;
    var total   = box ? (parseInt(box.getAttribute('data-total'), 10) || 0) : grid.children.length;

    var originalGrid   = grid.innerHTML;
    var originalNext   = btn ? btn.getAttribute('data-next') : '';
    var originalPaged  = box ? (parseInt(box.getAttribute('data-paged'), 10) || 1) : 1;

    var mode = 'title', letter = '', query = '';
    var loading = false;
    var uPaged = originalPaged;         // unfiltered page cursor
    var fPage = 1, fMax = 1;            // filtered page cursor

    function active() { return query !== '' || letter !== ''; }
    function canAjax() { return ajaxUrl && catId; }

    // ── Helpers ──────────────────────────────────────────
    function setLoading(v) {
        loading = v;
        if (btn) { btn.classList.toggle('is-loading', v); btn.disabled = v; }
        if (bar) bar.classList.toggle('is-busy', v);
    }
    function showRow(v) { if (row) { if (v) row.removeAttribute('hidden'); else row.setAttribute('hidden', ''); } }

    function appendHTML(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        var n = 0;
        while (tmp.firstElementChild) {
            var node = tmp.removeChild(tmp.firstElementChild);
            node.classList.add('tls-card-in');
            grid.appendChild(node);
            n++;
        }
        return n;
    }

    var countEl = bar ? bar.querySelector('.tls-filter-count') : null;
    function setCount(found) {
        if (!countEl) return;
        if (found === null) { countEl.textContent = ''; return; }
        countEl.innerHTML = '<strong>' + Number(found).toLocaleString() + '</strong> result' + (found === 1 ? '' : 's');
    }
    function setStatusVisible(v) { if (statusEl) statusEl.style.display = v ? '' : 'none'; }

    function updateShown(added) {
        if (!shownEl) return;
        var n = (parseInt(shownEl.textContent.replace(/[^0-9]/g, ''), 10) || 0) + added;
        if (total && n > total) n = total;
        shownEl.textContent = n.toLocaleString();
    }

    // ── AJAX filter request ──────────────────────────────
    function requestFilter(paged) {
        var body = 'action=tls_cat_filter'
            + '&cat=' + encodeURIComponent(catId)
            + '&mode=' + encodeURIComponent(mode)
            + '&letter=' + encodeURIComponent(letter)
            + '&s=' + encodeURIComponent(query)
            + '&paged=' + encodeURIComponent(paged);
        return fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(function (j) {
            if (!j || !j.success || !j.data) throw new Error('bad response');
            return j.data;
        });
    }

    // ── Apply a fresh filter (resets the grid) ───────────
    function applyFilter() {
        if (!active()) { restoreOriginal(); return; }
        if (!canAjax()) return;
        setLoading(true);
        requestFilter(1).then(function (d) {
            fPage = 1;
            fMax  = parseInt(d.max_pages, 10) || 1;
            grid.innerHTML = d.html && d.html.trim()
                ? d.html
                : '<p class="tls-filter-empty">No matches found. Try another search or letter.</p>';
            Array.prototype.forEach.call(grid.children, function (c) { c.classList.add('tls-card-in'); });
            setStatusVisible(false);
            setCount(parseInt(d.found, 10) || 0);
            showRow(fPage < fMax);
            setLoading(false);
        }).catch(function () { setLoading(false); restoreOriginal(); });
    }

    function restoreOriginal() {
        grid.innerHTML = originalGrid;
        uPaged = originalPaged;
        if (btn) btn.setAttribute('data-next', originalNext);
        setStatusVisible(true);
        setCount(null);
        showRow(!!originalNext);
    }

    // ── Load more (routes to filtered / unfiltered) ──────
    function loadMore() {
        if (loading) return;
        if (active()) loadMoreFiltered();
        else loadMoreUnfiltered();
    }

    function loadMoreFiltered() {
        if (fPage >= fMax) { showRow(false); return; }
        setLoading(true);
        requestFilter(fPage + 1).then(function (d) {
            appendHTML(d.html || '');
            fPage++;
            showRow(fPage < fMax);
            setLoading(false);
        }).catch(function () { setLoading(false); });
    }

    function loadMoreUnfiltered() {
        if (!btn) return;
        var url = btn.getAttribute('data-next');
        if (!url) { showRow(false); return; }
        setLoading(true);
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
            .then(function (html) {
                var doc     = new DOMParser().parseFromString(html, 'text/html');
                var newBox  = doc.querySelector('.tls-pagination');
                var newGrid = doc.getElementById('tls-books-grid');
                var fetched = newBox ? (parseInt(newBox.getAttribute('data-paged'), 10) || 0) : 0;

                // Guard against redirect loops (an over-reported page bouncing
                // back to page 1): only accept a strictly newer page.
                if (!newGrid || !newGrid.children.length || fetched <= uPaged) { showRow(false); return; }

                var added = appendHTML(newGrid.innerHTML);
                uPaged = fetched;
                updateShown(added);

                var nextBtn = newBox.querySelector('.tls-loadmore[data-next]');
                if (nextBtn) { btn.setAttribute('data-next', nextBtn.getAttribute('data-next')); setLoading(false); }
                else { showRow(false); setLoading(false); }
            })
            .catch(function () { setLoading(false); });
    }

    if (btn) btn.addEventListener('click', loadMore);

    // Infinite scroll: auto-load as the button nears the viewport.
    if (btn && 'IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) { if (e.isIntersecting) loadMore(); });
        }, { rootMargin: '500px 0px' });
        io.observe(btn);
    }

    // ── Toolbar wiring ───────────────────────────────────
    if (bar && canAjax()) {
        var input   = bar.querySelector('.tls-filter-input');
        var clearBtn= bar.querySelector('.tls-filter-clear');
        var alphaBtns = bar.querySelectorAll('.tls-alpha-btn');
        var modeBtns  = bar.querySelectorAll('.tls-mode-btn');
        var debounce;

        function setActiveAlpha(val) {
            Array.prototype.forEach.call(alphaBtns, function (b) {
                b.classList.toggle('is-active', b.getAttribute('data-letter') === val);
            });
        }

        function onQueryChange() {
            query = input.value.trim();
            clearBtn.classList.toggle('is-visible', query !== '');
            if (query !== '') { letter = ''; setActiveAlpha(''); }
            clearTimeout(debounce);
            // 1 char is noisy; require 2+ to search (letter filter is separate).
            if (query !== '' && query.length < 2) return;
            debounce = setTimeout(applyFilter, 240);
        }

        input.addEventListener('input', onQueryChange);
        input.addEventListener('search', onQueryChange); // clear (x) on native search inputs

        clearBtn.addEventListener('click', function () {
            input.value = ''; query = '';
            clearBtn.classList.remove('is-visible');
            applyFilter();
            input.focus();
        });

        Array.prototype.forEach.call(alphaBtns, function (b) {
            b.addEventListener('click', function () {
                letter = b.getAttribute('data-letter') || '';
                setActiveAlpha(letter);
                if (letter !== '') { query = ''; input.value = ''; clearBtn.classList.remove('is-visible'); }
                applyFilter();
            });
        });

        Array.prototype.forEach.call(modeBtns, function (b) {
            b.addEventListener('click', function () {
                var m = b.getAttribute('data-mode') === 'author' ? 'author' : 'title';
                if (m === mode) return;
                mode = m;
                Array.prototype.forEach.call(modeBtns, function (x) { x.classList.toggle('is-active', x === b); });
                input.placeholder = mode === 'author'
                    ? 'Search authors in this category…'
                    : 'Search works in this category…';
                if (active()) applyFilter();
            });
        });
    }
})();
</script>

<?php get_footer(); ?>
