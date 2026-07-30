<?php
/**
 * The Telos — Homepage Template
 *
 * Sections: Hero → Featured Works → Browse the Stacks → Latest Additions
 *
 * @package Mediumish / TheTelos
 */
get_header();

$paged = max( 1, get_query_var( 'paged' ) ?: get_query_var( 'page' ) ?: 1 );
$seen_ids = [];

// Helper — push IDs
function tls_push( &$seen, $id ) {
    $id = (int) $id;
    if ( $id && ! in_array( $id, $seen, true ) ) $seen[] = $id;
}
?>

<main id="main" role="main">

<?php if ( is_home() && ! is_paged() ) : ?>

<!-- ══════════════════════════════════
     HERO
══════════════════════════════════ -->
<section class="tls-hero">
    <div class="container">
        <p class="tls-hero-eyebrow">The Digital Archive</p>
        <h1 class="tls-hero-title">
            Explore the World's<br><em>Great Book Summaries</em>
        </h1>
        <p class="tls-hero-desc">
            A curated archive of the world's most influential texts, distilled for the modern scholar.
        </p>

        <!-- Search bar -->
        <form class="tls-hero-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <input type="search" name="s"
                   placeholder="Search by author, title, or concept…"
                   value="<?php echo esc_attr( get_search_query() ); ?>"
                   autocomplete="off"
                   aria-label="Search the archive">
            <button type="submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Explore
            </button>
        </form>

        <!-- Category marquee -->
        <?php
        $all_cats = get_terms( [
            'taxonomy'   => 'category',
            'orderby'    => 'count',
            'order'      => 'DESC',
            'number'     => 0,
            'hide_empty' => true,
        ] );
        if ( ! empty( $all_cats ) && ! is_wp_error( $all_cats ) ) :
            // Build pill HTML once, duplicate for seamless loop
            ob_start();
            foreach ( $all_cats as $pt ) :
        ?>
                <a class="tls-tag-pill"
                   href="<?php echo esc_url( get_category_link( $pt->term_id ) ); ?>">
                    <?php echo esc_html( $pt->name ); ?>
                </a>
        <?php
            endforeach;
            $pills_html = ob_get_clean();
        ?>
        <div class="tls-hero-tags">
            <div class="tls-hero-tags-track">
                <?php echo $pills_html; ?>
                <?php echo $pills_html; /* duplicate for seamless loop */ ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ══════════════════════════════════
     FEATURED WORKS
══════════════════════════════════ -->
<?php
// Use sticky posts as featured; fallback to 3 most recent
$sticky = get_option( 'sticky_posts', [] );
$featured_args = [
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'posts_per_page'      => 3,
    'ignore_sticky_posts' => 1,
    'orderby'             => 'date',
    'order'               => 'DESC',
    'no_found_rows'       => true,
];
if ( ! empty( $sticky ) ) {
    $featured_args['post__in'] = array_slice( $sticky, 0, 3 );
    $featured_args['orderby']  = 'post__in';
}
$featured_q = new WP_Query( $featured_args );

// Determine section title from first post's category
$section_cat = 'Featured Works';
if ( $featured_q->have_posts() ) {
    $first_cats = get_the_category( $featured_q->posts[0]->ID );
    if ( ! empty( $first_cats ) ) $section_cat = $first_cats[0]->name;
}
?>

<section class="tls-section">
    <div class="container">
        <p class="tls-section-label">Featured Works</p>
        <div class="tls-section-header">
            <h2 class="tls-section-title">Pillars of <?php echo esc_html( $section_cat ); ?></h2>
            <a class="tls-view-all" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                View entire archive
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>

        <?php if ( $featured_q->have_posts() ) : ?>
        <div class="tls-featured-grid">
            <?php
            $fi = 1;
            while ( $featured_q->have_posts() ) :
                $featured_q->the_post();
                tls_push( $seen_ids, get_the_ID() );
                $cats    = get_the_category();
                $cat_name = ! empty( $cats ) ? $cats[0]->name : '';
                $cat_link = ! empty( $cats ) ? get_category_link( $cats[0]->term_id ) : '';
                $authors  = get_the_terms( get_the_ID(), 'authors' );
                $auth     = ( ! empty( $authors ) && ! is_wp_error( $authors ) ) ? $authors[0]->name : get_the_author_meta( 'display_name' );
            ?>
            <a class="tls-featured-card" href="<?php the_permalink(); ?>">
                <div class="tls-featured-card-num">
                    <?php printf( 'BOOK %02d', $fi ); ?>
                    <?php if ( $cat_name ) echo ' &mdash; <span style="color:var(--tls-gold)">' . esc_html( strtoupper( $cat_name ) ) . '</span>'; ?>
                </div>
                <div class="tls-featured-card-cover">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail( 'medium', [ 'style' => 'max-width:120px;border-radius:2px;box-shadow:0 8px 28px rgba(0,0,0,.25);' ] ); ?>
                    <?php else : ?>
                        <?php echo thetelos_render_book_cover( get_the_ID() ); ?>
                    <?php endif; ?>
                </div>
                <div class="tls-featured-card-divider"></div>
                <div class="tls-featured-card-title"><?php the_title(); ?></div>
                <div class="tls-featured-card-author"><?php echo esc_html( $auth ); ?></div>
                <div class="tls-featured-card-desc"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></div>
            </a>
            <?php
            $fi++;
            endwhile;
            wp_reset_postdata();
            ?>
        </div>
        <?php else : ?>
            <p style="color:var(--tls-muted);font-family:var(--tls-sans);">No featured posts found. Mark posts as sticky to feature them here.</p>
        <?php endif; ?>
    </div>
</section>

<!-- ══════════════════════════════════
     MOST READ — yatay kaydırmalı raf
══════════════════════════════════ -->
<?php
/* En çok okunanlar. Sorgu, kart üretimi ve kategori sekmeleri
   inc/most-read.php içinde; aynı fonksiyonlar bir sekmeye tıklandığında
   AJAX tarafında da kullanılır, böylece kart markup'ı tek yerde durur.
   Yukarıdaki bölümlerde gösterilen kitaplar ($seen_ids) tekrar edilmez. */
$mr_ids  = function_exists( 'tls_most_read_ids' ) ? tls_most_read_ids( [], 12, $seen_ids ) : [];
$mr_tabs = function_exists( 'tls_most_read_tabs' ) ? tls_most_read_tabs( 10 ) : [];
foreach ( $mr_ids as $mr_id ) { tls_push( $seen_ids, $mr_id ); }
if ( $mr_ids ) :
?>
<style>
/* Kategori sekmeleri — hero'daki etiket hapları ile aynı dil, ama sabit ve tıklanır */
.tls-rail-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:-8px 0 26px}
.tls-rail-tab{font-family:var(--tls-sans);font-size:11px;letter-spacing:.05em;text-transform:uppercase;
    color:var(--tls-muted);background:transparent;padding:6px 15px;border:1px solid var(--tls-border);
    border-radius:20px;cursor:pointer;transition:color .15s,border-color .15s,background .15s}
.tls-rail-tab:hover{border-color:var(--tls-bg-dark);color:var(--tls-bg-dark)}
.tls-rail-tab.is-active{background:var(--tls-bg-dark);border-color:var(--tls-bg-dark);color:#fff}
.tls-rail-tabs.is-busy{opacity:.5;pointer-events:none}
.tls-rail.is-loading{opacity:.35;transition:opacity .15s}
.tls-rail-wrap{position:relative}
.tls-rail{display:flex;gap:22px;overflow-x:auto;scroll-behavior:smooth;scroll-snap-type:x mandatory;
    padding:4px 2px 18px;margin:0 -2px;-ms-overflow-style:none;scrollbar-width:none}
.tls-rail::-webkit-scrollbar{display:none}
.tls-rail-card{flex:0 0 264px;scroll-snap-align:start;display:block;text-decoration:none;
    background:#fff;border:1px solid var(--tls-border);border-radius:14px;padding:22px 20px 24px;
    transition:box-shadow .22s,transform .18s}
.tls-rail-card:hover{box-shadow:0 10px 30px rgba(0,0,0,.09);transform:translateY(-3px)}
.tls-rail-num{font-family:var(--tls-sans);font-size:10px;font-weight:700;letter-spacing:.1em;
    text-transform:uppercase;color:var(--tls-muted);margin-bottom:16px}
.tls-rail-cover{display:flex;align-items:flex-end;justify-content:center;height:170px;margin-bottom:18px}
.tls-rail-cover img{max-width:112px;max-height:170px;border-radius:2px;box-shadow:0 8px 26px rgba(0,0,0,.24)}
/* Tipografik yedek kapak 160×220 sabit üretiliyor; rafın kapak kutusuna
   oranı bozmadan ölçeklenir, böylece gerçek kapaklarla aynı hizada durur. */
.tls-rail-cover .thetelos-book-cover{transform:scale(.773);transform-origin:bottom center;
    box-shadow:0 8px 26px rgba(0,0,0,.24)}
.tls-rail-divider{width:34px;height:2px;background:var(--tls-gold);margin-bottom:14px}
.tls-rail-title{font-family:var(--tls-serif);font-size:17px;line-height:1.32;color:var(--tls-bg-dark);
    display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.tls-rail-author{font-family:var(--tls-sans);font-style:italic;font-size:13px;color:var(--tls-muted);margin-top:6px}
.tls-rail-views{font-family:var(--tls-sans);font-size:11px;color:var(--tls-muted);margin-top:10px;
    display:flex;align-items:center;gap:5px}
.tls-rail-views svg{width:13px;height:13px}
.tls-rail-nav{position:absolute;top:44%;transform:translateY(-50%);z-index:5;width:40px;height:40px;
    border-radius:50%;border:1px solid var(--tls-border);background:#fff;color:var(--tls-bg-dark);
    display:flex;align-items:center;justify-content:center;cursor:pointer;
    box-shadow:0 4px 14px rgba(0,0,0,.10);transition:opacity .2s,background .15s}
.tls-rail-nav:hover{background:var(--tls-bg)}
.tls-rail-nav[hidden]{display:none}
.tls-rail-prev{left:-18px}
.tls-rail-next{right:-18px}
.tls-rail-nav svg{width:17px;height:17px}
@media(max-width:640px){
  .tls-rail-card{flex-basis:210px;padding:18px 16px 20px}
  .tls-rail-cover{height:140px}
  .tls-rail-cover img{max-width:92px;max-height:140px}
  .tls-rail-cover .thetelos-book-cover{transform:scale(.636)}
  .tls-rail-nav{display:none}
}
</style>
<section class="tls-section">
    <div class="container">
        <p class="tls-section-label">Most Read</p>
        <div class="tls-section-header">
            <h2 class="tls-section-title">Most Read Summaries</h2>
            <a class="tls-view-all" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                View entire archive
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>

        <?php if ( $mr_tabs ) : ?>
        <div class="tls-rail-tabs" role="tablist" aria-label="Most read by field">
            <button class="tls-rail-tab is-active" type="button" data-group="" role="tab" aria-selected="true">All fields</button>
            <?php foreach ( $mr_tabs as $tab ) :
                if ( empty( $tab['key'] ) || empty( $tab['name'] ) ) continue; ?>
            <button class="tls-rail-tab" type="button" role="tab" aria-selected="false"
                    data-group="<?php echo esc_attr( $tab['key'] ); ?>"><?php echo esc_html( $tab['name'] ); ?></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="tls-rail-wrap">
            <button class="tls-rail-nav tls-rail-prev" type="button" aria-label="Önceki" hidden>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M15 18l-6-6 6-6"/></svg>
            </button>

            <div class="tls-rail" id="tls-most-read">
                <?php echo tls_rail_cards_html( $mr_ids ); ?>
            </div>

            <button class="tls-rail-nav tls-rail-next" type="button" aria-label="Sonraki">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 18l6-6-6-6"/></svg>
            </button>
        </div>
    </div>
</section>
<script>
(function () {
    var rail = document.getElementById('tls-most-read');
    if (!rail) return;
    var wrap = rail.closest('.tls-rail-wrap'),
        prev = wrap.querySelector('.tls-rail-prev'),
        next = wrap.querySelector('.tls-rail-next');

    // Kart genişliği + boşluk kadar kaydır (bir "sayfa" hissi)
    function step() {
        var card = rail.querySelector('.tls-rail-card');
        return card ? (card.offsetWidth + 22) * Math.max(1, Math.floor(rail.clientWidth / (card.offsetWidth + 22))) : 300;
    }
    function sync() {
        prev.hidden = rail.scrollLeft <= 4;
        next.hidden = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 4;
    }
    prev.addEventListener('click', function () { rail.scrollBy({ left: -step(), behavior: 'smooth' }); });
    next.addEventListener('click', function () { rail.scrollBy({ left:  step(), behavior: 'smooth' }); });
    rail.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync);
    sync();

    /* ── Kategori sekmeleri ──
       Kartlar AJAX ile gelir; bir kez getirilen sekme bellekte tutulur, aynı
       sekmeye dönüldüğünde tekrar istek atılmaz. */
    var tabsBox = document.querySelector('.tls-rail-tabs');
    if (!tabsBox) return;

    var AJAX  = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
        cache = { '': rail.innerHTML },   // açılıştaki liste "All fields" sekmesidir
        current = '';

    function show(html) {
        rail.innerHTML = html;
        rail.scrollLeft = 0;
        rail.classList.remove('is-loading');
        tabsBox.classList.remove('is-busy');
        sync();
    }

    tabsBox.addEventListener('click', function (e) {
        var btn = e.target.closest('.tls-rail-tab');
        if (!btn) return;
        var grp = btn.dataset.group || '';
        if (grp === current) return;
        current = grp;

        tabsBox.querySelectorAll('.tls-rail-tab').forEach(function (b) {
            var on = b === btn;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        if (cache[grp]) { show(cache[grp]); return; }

        rail.classList.add('is-loading');
        tabsBox.classList.add('is-busy');
        fetch(AJAX + '?action=tls_most_read&group=' + encodeURIComponent(grp), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var html = (j && j.success && j.data && j.data.html) ? j.data.html : '';
                if (!html) throw new Error('empty');
                cache[grp] = html;
                if (current === grp) show(html);
            })
            .catch(function () {
                // İstek başarısızsa sekmeyi zorlamayız; kullanıcı listesiz kalmasın diye
                // önceki içerik geri gelir ve "All fields" tekrar seçili olur.
                current = '';
                tabsBox.querySelectorAll('.tls-rail-tab').forEach(function (b) {
                    var on = !b.dataset.group;
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                show(cache['']);
            });
    });
})();
</script>
<?php endif; ?>

<!-- ══════════════════════════════════
     BOOK OF THE WEEK
══════════════════════════════════ -->
<?php
$botw = get_transient( 'tls_book_of_week' );
if ( false === $botw ) {
    $candidates = get_posts( [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 60,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'date_query'     => [ [ 'after' => '30 days ago' ] ],
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ] );
    if ( count( $candidates ) < 5 ) {
        $candidates = get_posts( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 80,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );
    }
    $best_id = 0; $best_score = -1;
    foreach ( $candidates as $cid ) {
        $r_data       = function_exists('thetelos_get_rating') ? thetelos_get_rating($cid) : ['avg'=>0,'count'=>0];
        $avg          = (float)($r_data['avg'] ?? 0);
        $rcnt         = (int)($r_data['count'] ?? 0);
        $views        = (int) get_post_meta( $cid, 'post_views_count', true );
        $rating_score = $rcnt > 0 ? ($avg * 20 * min($rcnt, 10)) : 0;
        $view_score   = min($views, 1000);
        $score        = $rating_score + $view_score;
        if ( $score > $best_score ) { $best_score = $score; $best_id = $cid; }
    }
    if ( !$best_id && !empty($candidates) ) $best_id = $candidates[0];
    $botw = (int) $best_id;
    $ttl  = max( 3600, strtotime('next monday midnight') - time() );
    set_transient( 'tls_book_of_week', $botw, $ttl );
}

if ( $botw ) :
    $bw_post    = get_post( $botw );
    $bw_authors = get_the_terms( $botw, 'authors' );
    $bw_author  = (!empty($bw_authors) && !is_wp_error($bw_authors)) ? $bw_authors[0]->name : '';
    $bw_cats    = get_the_category( $botw );
    $bw_cat     = !empty($bw_cats) ? $bw_cats[0] : null;
    $bw_excerpt = wp_trim_words( get_post_field('post_excerpt',$botw) ?: strip_tags($bw_post->post_content), 28 );
    $bw_time    = function_exists('thetelos_post_reading_time') ? thetelos_post_reading_time($botw) : '';
    $bw_rating  = function_exists('thetelos_get_rating') ? thetelos_get_rating($botw) : ['avg'=>0,'count'=>0];
    $bw_avg     = round((float)$bw_rating['avg'],1);
    $bw_rcnt    = (int)$bw_rating['count'];
    $bw_url     = get_permalink($botw);
    tls_push($seen_ids, $botw);
?>
<section class="tls-botw">
    <div class="container">
        <div class="tls-botw-inner">
            <span class="tls-botw-corner tls-botw-corner--tl"></span>
            <span class="tls-botw-corner tls-botw-corner--tr"></span>
            <span class="tls-botw-corner tls-botw-corner--bl"></span>
            <span class="tls-botw-corner tls-botw-corner--br"></span>

            <!-- Etiket -->
            <div class="tls-botw-badge">
                <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14" aria-hidden="true"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                Book of the Week
            </div>

            <!-- Kapak -->
            <div class="tls-botw-cover">
                <a href="<?php echo esc_url($bw_url); ?>">
                    <?php if ( has_post_thumbnail($botw) ) :
                        echo get_the_post_thumbnail($botw, 'medium', ['alt'=>esc_attr(get_the_title($botw))]);
                    else :
                        echo thetelos_render_book_cover($botw);
                    endif; ?>
                </a>
            </div>

            <!-- Metin -->
            <div class="tls-botw-body">
                <?php if ($bw_cat) : ?>
                <a class="tls-botw-cat" href="<?php echo esc_url(get_category_link($bw_cat->term_id)); ?>">
                    <?php echo esc_html($bw_cat->name); ?>
                </a>
                <?php endif; ?>

                <h2 class="tls-botw-title">
                    <a href="<?php echo esc_url($bw_url); ?>"><?php echo esc_html(get_the_title($botw)); ?></a>
                </h2>

                <?php if ($bw_author) : ?>
                <p class="tls-botw-author"><?php echo esc_html($bw_author); ?></p>
                <?php endif; ?>

                <p class="tls-botw-excerpt"><?php echo esc_html($bw_excerpt); ?></p>

                <div class="tls-botw-meta">
                    <?php if ($bw_time) : ?>
                    <span class="tls-botw-meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <?php echo esc_html($bw_time); ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($bw_avg > 0) : ?>
                    <span class="tls-botw-meta-item">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13" style="color:var(--tls-gold);"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <?php echo number_format($bw_avg,1); ?>
                        <?php if ($bw_rcnt) echo '(' . $bw_rcnt . ')'; ?>
                    </span>
                    <?php endif; ?>
                </div>

                <a class="tls-botw-cta" href="<?php echo esc_url($bw_url); ?>">
                    Read Summary
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════
     BROWSE THE STACKS
══════════════════════════════════ -->
<?php
$browse_cats = get_categories( [
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 4,
    'hide_empty' => true,
] );

// Category icons map (slug → emoji)
$cat_icons = [
    'philosophy'   => '🏛️',
    'history'      => '📜',
    'science'      => '⚗️',
    'art'          => '🎨',
    'theology'     => '✝️',
    'economics'    => '📊',
    'literature'   => '📚',
    'psychology'   => '🧠',
    'politics'     => '⚖️',
    'religion'     => '🕌',
    'biography'    => '👤',
    'mathematics'  => '∑',
    'technology'   => '💡',
    'music'        => '🎵',
];
?>

<section class="tls-section">
    <div class="container">
        <div class="tls-browse-wrap">

            <!-- Dark panel -->
            <div class="tls-browse-dark">
                <p class="tls-browse-dark-label">Navigation</p>
                <h2 class="tls-browse-dark-title">Browse the<br>Eternal Stacks</h2>
                <p class="tls-browse-dark-desc">
                    Organized by epoch, ideology, and influence. Find the missing piece of your intellectual puzzle.
                </p>
                <a class="tls-browse-cta" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    Open Collections
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>

            <!-- Category tiles grid -->
            <div class="tls-cat-grid">
                <?php
                $active_set = false;
                foreach ( $browse_cats as $bi => $bc ) :
                    $icon  = $cat_icons[ $bc->slug ] ?? '📖';
                    $active = ( ! $active_set && $bi === 1 ) ? 'tls-cat-active' : '';
                    if ( $active ) $active_set = true;
                ?>
                <a class="tls-cat-tile <?php echo $active; ?>"
                   href="<?php echo esc_url( get_category_link( $bc->term_id ) ); ?>">
                    <span class="tls-cat-tile-icon"><?php echo $icon; ?></span>
                    <span class="tls-cat-tile-name"><?php echo esc_html( $bc->name ); ?></span>
                    <span class="tls-cat-tile-count"><?php echo number_format( $bc->count ); ?> volumes</span>
                </a>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</section>

<!-- ══════════════════════════════════
     READING PATHS
══════════════════════════════════ -->
<?php
$reading_lists = function_exists('tls_get_reading_lists') ? tls_get_reading_lists(12) : [];
$rp_total      = count($reading_lists);
if ( ! empty($reading_lists) ) :
?>
<section class="tls-rp-section">
<div class="container">

    <!-- ── Page header ── -->
    <div class="tls-rp-page-header">
        <div>
            <p class="tls-rp-eyebrow">Curated Sequences &middot; <?php echo $rp_total; ?> Paths</p>
            <h2 class="tls-rp-page-title">Reading <em>Paths</em></h2>
        </div>
    </div>
    <hr class="tls-rp-divider">

    <!-- ── Navigation bar ── -->
    <div class="tls-rp-nav-bar">
        <div class="tls-rp-nav-left">
            <span class="tls-rp-featured-label-text">Featured Path</span>
            <span class="tls-rp-counter" id="tls-rp-counter">
                Path 01 / <?php echo str_pad($rp_total, 2, '0', STR_PAD_LEFT); ?>
            </span>
            <div class="tls-rp-arrows">
                <button class="tls-rp-arrow" id="tls-rp-prev" aria-label="Previous path" disabled>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="16" height="16" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                </button>
                <button class="tls-rp-arrow" id="tls-rp-next" aria-label="Next path" <?php if ($rp_total <= 1) echo 'disabled'; ?>>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="16" height="16" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- ── Panels ── -->
    <div id="tls-rp-panels">
    <?php foreach ($reading_lists as $idx => $rl) :
        $rp_bks   = $rl['books'];
        $rp_cnt   = count($rp_bks);
        $rp_hrs   = max(1, round($rp_cnt * 0.5));
        $rp_cats  = !empty($rp_bks) ? get_the_category($rp_bks[0]) : [];
        $rp_color = (function_exists('thetelos_get_category_palette') && !empty($rp_cats))
            ? thetelos_get_category_palette($rp_cats[0]->slug)[0] : '#1C3A2A';
        $rp_acc   = (function_exists('thetelos_get_category_palette') && !empty($rp_cats))
            ? thetelos_get_category_palette($rp_cats[0]->slug)[1] : '#C8A165';
        $rp_rows  = [];
        foreach (array_slice($rp_bks, 0, 8) as $pid) {
            $a = get_the_terms($pid, 'authors');
            $rp_rows[] = [
                'title'  => get_the_title($pid),
                'author' => (!empty($a) && !is_wp_error($a)) ? $a[0]->name : '',
                'url'    => get_permalink($pid),
            ];
        }
        $is_first = ($idx === 0);
    ?>
    <div class="tls-rp-panel<?php echo $is_first ? ' is-active' : ''; ?>"
         data-idx="<?php echo $idx; ?>"
         data-list-id="<?php echo (int)$rl['id']; ?>"
         <?php if (!$is_first) echo 'hidden'; ?>>

        <div class="tls-rp-featured">
            <div class="tls-rp-featured-left">
                <h3 class="tls-rp-featured-title"><?php echo esc_html($rl['title']); ?></h3>
                <p class="tls-rp-featured-desc"><?php echo esc_html($rl['desc']); ?></p>

                <?php if (!empty($rp_rows)) : ?>
                <div class="tls-rp-book-list">
                    <?php
                    $half = (int) ceil(count($rp_rows) / 2);
                    $num  = 1;
                    foreach ([array_slice($rp_rows, 0, $half), array_slice($rp_rows, $half)] as $col) :
                    ?>
                    <div class="tls-rp-book-col">
                        <?php foreach ($col as $b) : ?>
                        <a class="tls-rp-book-row" href="<?php echo esc_url($b['url']); ?>">
                            <span class="tls-rp-book-num"><?php echo str_pad($num++, 2, '0', STR_PAD_LEFT); ?></span>
                            <span class="tls-rp-book-title"><?php echo esc_html($b['title']); ?></span>
                            <span class="tls-rp-book-author"><?php echo esc_html($b['author']); ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="tls-rp-featured-actions">
                    <button class="tls-rp-save-btn"
                            type="button"
                            data-rp-save
                            data-list-id="<?php echo (int)$rl['id']; ?>">
                        <svg class="tls-rp-save-icon-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        <svg class="tls-rp-save-icon-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                        <span class="tls-rp-save-label">Add to Reading List</span>
                    </button>
                </div>
            </div>

            <!-- ── Book cover stack ── -->
            <div class="tls-rp-cover-wrap" aria-hidden="true">
                <div class="tls-rp-cover-stack">
                    <div class="tls-rp-cover tls-rp-cover--c" style="background:<?php echo esc_attr($rp_color); ?>;filter:brightness(.48);"></div>
                    <div class="tls-rp-cover tls-rp-cover--b" style="background:<?php echo esc_attr($rp_color); ?>;filter:brightness(.68);"></div>
                    <div class="tls-rp-cover tls-rp-cover--a" style="background:<?php echo esc_attr($rp_color); ?>;">
                        <div class="tls-rp-cover-inner">
                            <p class="tls-rp-cover-eyebrow" style="color:<?php echo esc_attr($rp_acc); ?>;">
                                PATH <?php echo str_pad($idx+1, 2, '0', STR_PAD_LEFT); ?> &middot; THE TELOS LIBRARY
                            </p>
                            <h4 class="tls-rp-cover-title"><?php echo esc_html($rl['title']); ?></h4>
                            <div class="tls-rp-cover-rule" style="background:<?php echo esc_attr($rp_acc); ?>;"></div>
                            <div class="tls-rp-cover-footer">
                                <span><?php echo $rp_cnt; ?> VOLUMES &middot; ~<?php echo $rp_hrs; ?>H</span>
                                <span class="tls-rp-tau" style="color:<?php echo esc_attr($rp_acc); ?>;">&#964;&#941;&#955;&#959;&#962;</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div><!-- /#tls-rp-panels -->

</div><!-- /.container -->
</section>
<?php endif; ?>


<!-- ══════════════════════════════════
     LATEST ADDITIONS + CURATOR'S NOTE
══════════════════════════════════ -->
<?php
$latest_q = new WP_Query( [
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'posts_per_page'      => 7,
    'post__not_in'        => $seen_ids,
    'ignore_sticky_posts' => 1,
    'orderby'             => 'date',
    'order'               => 'DESC',
    'no_found_rows'       => true,
] );
?>

<section class="tls-section">
    <div class="container">
        <p class="tls-section-label">Latest Additions</p>
        <h2 class="tls-section-title" style="margin-bottom:36px;">Recent to the Archive</h2>

        <div class="tls-latest-wrap">

            <!-- Book list -->
            <div>
                <?php if ( $latest_q->have_posts() ) : ?>
                <ul class="tls-latest-list">
                    <?php while ( $latest_q->have_posts() ) : $latest_q->the_post(); ?>
                    <li class="tls-latest-item">
                        <div class="tls-latest-date">
                            <strong><?php echo esc_html( get_the_date( 'j' ) ); ?></strong>
                            <?php echo esc_html( strtoupper( get_the_date( 'M' ) ) ); ?>
                        </div>
                        <div class="tls-latest-body">
                            <a class="tls-latest-title" href="<?php the_permalink(); ?>">
                                <?php the_title(); ?>
                            </a>
                            <?php
                            $l_authors = get_the_terms( get_the_ID(), 'authors' );
                            $l_auth    = ( ! empty( $l_authors ) && ! is_wp_error( $l_authors ) ) ? $l_authors[0]->name : '';
                            ?>
                            <p class="tls-latest-meta">
                                <?php if ( $l_auth ) echo esc_html( $l_auth ) . ' &mdash; '; ?>
                                <?php echo esc_html( wp_trim_words( get_the_excerpt(), 16 ) ); ?>
                            </p>
                            <div class="tls-latest-tags">
                                <?php
                                $l_cats = get_the_category();
                                foreach ( array_slice( $l_cats, 0, 2 ) as $lc ) :
                                ?>
                                <a class="tls-cat-badge" href="<?php echo esc_url( get_category_link( $lc->term_id ) ); ?>">
                                    <?php echo esc_html( $lc->name ); ?>
                                </a>
                                <?php endforeach; ?>
                                <?php if ( function_exists( 'thetelos_get_analysis_for_post' ) && thetelos_get_analysis_for_post( get_the_ID() ) ) : ?>
                                <span class="tls-cat-badge" style="border-color:var(--tls-green);color:var(--tls-green);">Deep Analysis</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endwhile; wp_reset_postdata(); ?>
                </ul>
                <?php else : ?>
                    <p style="color:var(--tls-muted);font-family:var(--tls-sans);">No posts found.</p>
                <?php endif; ?>

                <div style="margin-top:24px;">
                    <a class="tls-cta-btn" href="<?php echo esc_url( home_url( '/?paged=2' ) ); ?>"
                       style="display:inline-block;width:auto;padding:12px 32px;">
                        View all summaries &rarr;
                    </a>
                </div>
            </div>

            <!-- Curator's Note -->
            <aside class="tls-curator">
                <div class="tls-curator-label">The Curator's Note</div>
                <p class="tls-curator-quote">
                    &ldquo;The purpose of The Telos is not to replace the reading of great works, but to provide the maps necessary to navigate the vast landscape of human thought.&rdquo;
                </p>
                <div class="tls-stats-label">Live Archive Stats</div>
                <?php
                $total_books   = wp_count_posts( 'post' )->publish;
                $total_authors = wp_count_terms( [ 'taxonomy' => 'authors', 'hide_empty' => true ] );
                $total_cats    = wp_count_terms( [ 'taxonomy' => 'category', 'hide_empty' => true ] );
                if ( is_wp_error( $total_authors ) ) $total_authors = 0;
                if ( is_wp_error( $total_cats ) )    $total_cats = 0;
                ?>
                <div class="tls-stats-row">
                    <span>Total Summaries</span>
                    <strong><?php echo number_format( $total_books ); ?></strong>
                </div>
                <div class="tls-stats-row">
                    <span>Unique Authors</span>
                    <strong><?php echo number_format( (int) $total_authors ); ?></strong>
                </div>
                <div class="tls-stats-row">
                    <span>Categories</span>
                    <strong><?php echo number_format( (int) $total_cats ); ?></strong>
                </div>
                <button class="tls-cta-btn" id="tls-open-request" type="button">
                    Request a Summary
                </button>
            </aside>

        </div>
    </div>
</section>

<?php endif; // is_home && !is_paged ?>

<!-- ══════════════════════════════════
     PAGINATED / ARCHIVE FALLBACK
══════════════════════════════════ -->
<?php if ( is_paged() || ! is_home() ) : ?>
<section style="padding:56px 0;">
    <div class="container">
        <?php if ( is_search() ) : ?>
        <div class="tls-search-header">
            <p class="tls-search-for">Search results for</p>
            <h1 class="tls-search-term"><?php echo esc_html( get_search_query() ); ?></h1>
        </div>
        <?php elseif ( is_archive() ) : ?>
        <div class="tls-archive-hero" style="border-radius:var(--tls-radius);margin-bottom:36px;padding:48px 36px;">
            <p class="tls-archive-eyebrow">Archive</p>
            <h1 class="tls-archive-title"><?php echo esc_html( mediumish_archive_title() ); ?></h1>
        </div>
        <?php else : ?>
        <h1 class="tls-section-title" style="margin-bottom:32px;">All Summaries</h1>
        <?php endif; ?>

        <?php
        $main_q = new WP_Query( [
            'post_type'   => 'post',
            'post_status' => 'publish',
            'paged'       => $paged,
            'orderby'     => [ 'date' => 'DESC', 'ID' => 'DESC' ],
        ] );
        if ( $main_q->have_posts() ) :
        ?>
        <div class="tls-books-grid">
            <?php while ( $main_q->have_posts() ) : $main_q->the_post(); ?>
                <?php echo thetelos_book_card( get_the_ID() ); ?>
            <?php endwhile; ?>
        </div>
        <div class="bottompagination">
            <?php
            if ( function_exists( 'wp_bootstrap_pagination' ) ) {
                wp_bootstrap_pagination( [
                    'custom_query'    => $main_q,
                    'previous_string' => '&laquo;',
                    'next_string'     => '&raquo;',
                    'before_output'   => '<span class="navigation">',
                    'after_output'    => '</span>',
                ] );
            } else {
                the_posts_pagination( [ 'mid_size' => 2 ] );
            }
            ?>
        </div>
        <?php
        else :
        ?>
            <p style="color:var(--tls-muted);font-family:var(--tls-sans);">
                <?php _e( 'No posts matched your criteria.', 'mediumish' ); ?>
            </p>
        <?php
        endif;
        wp_reset_postdata();
        ?>
    </div>
</section>
<?php endif; ?>

</main>

<?php get_footer(); ?>
