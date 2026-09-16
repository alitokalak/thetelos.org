<?php
/**
 * Template Name: Display Categories
 *
 * Kategoriler 14 kalıcı ANA KATEGORİ altında gruplanır (chip filtre + sort +
 * bölüm başlığı/açıklaması + "View all"). Kategori URL'leri değişmez —
 * gruplama tls_cat_main_of() (panel override + otomatik motor) ile yapılır.
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Kategorileri getir (boş olanları gizle, uncategorized hariç) ── */
$cats = get_terms( [ 'taxonomy'=>'category', 'hide_empty'=>true, 'orderby'=>'count', 'order'=>'DESC', 'number'=>0 ] );
if ( is_wp_error( $cats ) ) $cats = [];
$cats = array_values( array_filter( $cats, function( $c ) { return $c->slug !== 'uncategorized'; } ) );
$total_count = count( $cats );

/* ── 14 ana kategori + kovalar ── */
$main_labels = function_exists( 'tls_cat_mains' ) ? tls_cat_mains() : [
    'literature-fiction'=>'Literature & Fiction','philosophy'=>'Philosophy','religion-spirituality'=>'Religion & Spirituality',
    'history'=>'History','biography-memoir'=>'Biography & Memoir','psychology'=>'Psychology',
    'social-sciences'=>'Social Sciences & Politics','science-nature'=>'Science & Nature','technology-engineering'=>'Technology & Engineering',
    'arts-culture'=>'Arts & Culture','business-economics'=>'Business & Economics','health-lifestyle'=>'Health & Lifestyle',
    'self-help'=>'Self-Help & Personal Growth','children-ya'=>'Children & Young Adult',
];
$main_desc = [
    'literature-fiction'=>'Novels, poetry, drama and genre fiction — from the canon to the contemporary.',
    'philosophy'=>'Systematic inquiry into existence, knowledge, morality and reason — the spine of the archive.',
    'religion-spirituality'=>'Faith traditions, theology, scripture and the world\'s spiritual thought.',
    'history'=>'The human past — civilizations, events and turning points across the eras.',
    'biography-memoir'=>'Lives told — biography, autobiography, memoir and letters.',
    'psychology'=>'The mind and behaviour — cognition, emotion and the unconscious.',
    'social-sciences'=>'Society, politics, law and how people live together.',
    'science-nature'=>'The natural world — physics, life, mathematics and the cosmos.',
    'technology-engineering'=>'Computing, engineering and the tools that shape modern life.',
    'arts-culture'=>'Art, music, film, architecture and cultural expression.',
    'business-economics'=>'Markets, management, money and economic thought.',
    'health-lifestyle'=>'Medicine, wellbeing, food and everyday living.',
    'self-help'=>'Personal growth, habits and the examined life.',
    'children-ya'=>'Books for younger readers and young adults.',
    '_other'=>'Cross-cutting subjects and themes across the archive.',
];
$buckets = [];
foreach ( array_keys( $main_labels ) as $ms ) $buckets[ $ms ] = [];
$buckets['_other'] = [];
foreach ( $cats as $cat ) {
    $ms = function_exists( 'tls_cat_main_of' ) ? tls_cat_main_of( $cat ) : '';
    if ( $ms === '' || ! isset( $buckets[ $ms ] ) ) $ms = '_other';
    $buckets[ $ms ][] = $cat;
}
foreach ( $buckets as &$_b ) { usort( $_b, function ( $a, $c ) { return $c->count <=> $a->count; } ); }
unset( $_b );

// Bölüm sırası (dolu olanlar) + toplam yazı sayıları
$sections = $main_labels; $sections['_other'] = 'Other Subjects';
$sec_entries = []; $total_entries = 0;
foreach ( $sections as $ms => $label ) {
    $sum = 0; foreach ( ($buckets[$ms] ?? []) as $c ) $sum += (int) $c->count;
    $sec_entries[$ms] = $sum; $total_entries += $sum;
}

$PREVIEW = 8;   // "All subjects" görünümünde bölüm başına önizleme kartı

get_header();
?>

<style>
.tlc-hero{ background:#fff; border-bottom:1px solid var(--tls-border); padding:52px 0 34px; }
.tlc-hero-inner{ max-width:var(--tls-container); margin:0 auto; padding:0 32px; }
.tlc-eyebrow{ font-family:var(--tls-sans); font-size:10px; letter-spacing:.22em; text-transform:uppercase; color:var(--tls-gold); margin-bottom:12px; }
.tlc-hero h1{ font-family:var(--tls-serif); font-size:clamp(30px,4vw,48px); font-weight:400; color:var(--tls-bg-dark); margin:0 0 10px; line-height:1.1; }
.tlc-hero-sub{ font-family:var(--tls-sans); font-size:15px; color:var(--tls-muted); margin:0; max-width:560px; line-height:1.6; }

/* ── Sticky toolbar: arama + sort ── */
.tlc-toolbar{ position:sticky; top:var(--tls-nav-h); z-index:200; background:var(--tls-bg); border-bottom:1px solid var(--tls-border); box-shadow:0 2px 12px rgba(0,0,0,.06); isolation:isolate; }
.admin-bar .tlc-toolbar{ top:calc(var(--tls-nav-h) + 32px); }
@media screen and (max-width:782px){ .admin-bar .tlc-toolbar{ top:calc(var(--tls-nav-h) + 46px); } }
.tlc-toolbar-inner{ max-width:var(--tls-container); margin:0 auto; padding:12px 32px; }
.tlc-toolrow{ display:flex; align-items:center; gap:14px; }
.tlc-search-wrap{ position:relative; flex:1; max-width:460px; }
.tlc-search-wrap>svg{ position:absolute; left:14px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:var(--tls-muted); pointer-events:none; }
.tlc-search-input{ width:100%; height:42px; padding:0 16px 0 40px; font-family:var(--tls-sans); font-size:14px; color:var(--tls-bg-dark); background:#fff; border:1px solid var(--tls-border); border-radius:999px; outline:none; transition:border-color .15s, box-shadow .15s; -webkit-appearance:none; }
.tlc-search-input::placeholder{ color:#aaa; }
.tlc-search-input:focus{ border-color:var(--tls-green); box-shadow:0 0 0 3px rgba(0,171,107,.12); }
.tlc-sort{ display:flex; align-items:center; gap:8px; margin-left:auto; }
.tlc-sort-lbl{ font-family:var(--tls-sans); font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--tls-muted); }
.tlc-sort-btns{ display:inline-flex; background:#fff; border:1px solid var(--tls-border); border-radius:999px; padding:3px; }
.tlc-sort-btn{ font-family:var(--tls-sans); font-size:12.5px; font-weight:600; white-space:nowrap; color:var(--tls-muted); background:none; border:none; padding:6px 14px; border-radius:999px; cursor:pointer; transition:all .15s; }
.tlc-sort-btn.active{ background:var(--tls-bg-dark); color:#fff; }
.tlc-count{ font-family:var(--tls-sans); font-size:13px; color:var(--tls-muted); white-space:nowrap; }
.tlc-count strong{ color:var(--tls-bg-dark); font-weight:600; }

/* ── Chip filtre satırı: TEK SATIR, yatay kaydırmalı (kompakt) ── */
.tlc-chips{ display:flex; flex-wrap:nowrap; gap:8px; padding:10px 0 4px; overflow-x:auto; scrollbar-width:none; -webkit-overflow-scrolling:touch; }
.tlc-chips::-webkit-scrollbar{ display:none; }
.tlc-chip{ flex-shrink:0; white-space:nowrap; display:inline-flex; align-items:center; gap:7px; font-family:var(--tls-sans); font-size:13px; font-weight:600; color:var(--tls-bg-dark); background:#fff; border:1px solid var(--tls-border); border-radius:999px; padding:7px 14px; cursor:pointer; transition:all .14s; }
.tlc-chip:hover{ border-color:var(--tls-bg-dark); }
.tlc-chip.active{ background:var(--tls-bg-dark); color:#fff; border-color:var(--tls-bg-dark); }
.tlc-chip-n{ font-size:11px; font-weight:700; color:var(--tls-muted); }
.tlc-chip.active .tlc-chip-n{ color:rgba(255,255,255,.65); }

/* ── Ana içerik ── */
.tlc-main{ max-width:var(--tls-container); margin:0 auto; padding:36px 32px 80px; position:relative; z-index:1; }

/* ── Bölüm ── */
.tlc-section{ margin-bottom:52px; scroll-margin-top:calc(var(--tls-nav-h) + 120px); }
.tlc-sec-head{ display:flex; align-items:flex-start; gap:20px; margin:0 0 20px; }
.tlc-sec-headmain{ flex:1; min-width:0; }
.tlc-sec-title{ font-family:var(--tls-serif); font-size:26px; font-weight:400; color:var(--tls-bg-dark); margin:0 0 6px; line-height:1.15; }
.tlc-sec-meta{ font-family:var(--tls-sans); font-size:12.5px; font-weight:600; color:var(--tls-muted); margin-left:2px; }
.tlc-sec-desc{ font-family:var(--tls-sans); font-size:14px; color:var(--tls-muted); line-height:1.55; margin:0; max-width:640px; }
.tlc-viewall{ flex-shrink:0; font-family:var(--tls-sans); font-size:13px; font-weight:600; color:var(--tls-bg-dark); background:none; border:1px solid var(--tls-border); border-radius:999px; padding:8px 16px; cursor:pointer; white-space:nowrap; transition:all .15s; }
.tlc-viewall:hover{ background:var(--tls-bg-dark); color:#fff; border-color:var(--tls-bg-dark); }

/* ── Grid + kart ── */
.tlc-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:12px; }
.tlc-card{ display:flex; flex-direction:column; gap:8px; padding:18px 20px; background:#fff; border:1px solid var(--tls-border); border-radius:var(--tls-radius-lg); text-decoration:none!important; color:var(--tls-bg-dark)!important; transition:border-color .15s, box-shadow .15s, transform .15s; position:relative; overflow:hidden; }
.tlc-card::before{ content:''; position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg,var(--tls-green),var(--tls-gold)); opacity:0; transition:opacity .15s; }
.tlc-card:hover{ border-color:transparent; box-shadow:var(--tls-shadow); transform:translateY(-2px); z-index:2; }
.tlc-card:hover::before{ opacity:1; }
.tlc-card-icon{ display:flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:9px; background:var(--tls-bg); color:var(--tls-gold); border:1px solid var(--tls-border); }
.tlc-card-icon svg{ width:16px; height:16px; }
.tlc-card-name{ font-family:var(--tls-serif); font-size:18px; font-weight:400; color:var(--tls-bg-dark); line-height:1.2; margin:2px 0 0; }
.tlc-card-desc{ font-family:var(--tls-sans); font-size:12.5px; color:var(--tls-muted); line-height:1.55; margin:0; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.tlc-card-count{ display:inline-flex; align-items:center; gap:5px; font-family:var(--tls-sans); font-size:11.5px; font-weight:500; color:var(--tls-muted); margin-top:auto; padding-top:6px; }
.tlc-card-count svg{ width:12px; height:12px; }

.tlc-no-results{ text-align:center; padding:80px 20px; }
.tlc-no-results strong{ display:block; font-family:var(--tls-serif); font-size:22px; color:var(--tls-bg-dark); font-weight:400; margin-bottom:8px; }
.tlc-no-results p{ font-family:var(--tls-sans); font-size:14px; color:var(--tls-muted); margin:0; }

@media (max-width:768px){
    .tlc-grid{ grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:10px; }
    .tlc-sort-lbl{ display:none; }
    /* Toolbar'ı iki satıra istifle: 1) arama tam genişlik  2) sayı + sort */
    .tlc-toolrow{ flex-wrap:wrap; gap:10px; }
    .tlc-search-wrap{ flex:1 1 100%; max-width:none; order:1; }
    .tlc-count{ order:2; }
    .tlc-sort{ order:3; margin-left:auto; }
}
@media (max-width:480px){
    .tlc-grid{ grid-template-columns:1fr 1fr; gap:8px; }
    .tlc-main{ padding:24px 16px 60px; }
    .tlc-toolbar-inner,.tlc-hero-inner{ padding-left:16px; padding-right:16px; }
    .tlc-toolbar-inner{ padding-top:10px; padding-bottom:10px; }
    .tlc-hero{ padding:32px 0 20px; }
    .tlc-count{ font-size:12px; }
    .tlc-sec-head{ flex-direction:column; gap:10px; }
    .tlc-sec-title{ font-size:22px; }
    .tlc-card-name{ font-size:16px; }
}
</style>

<main id="main" role="main">

<!-- HERO -->
<div class="tlc-hero">
    <div class="tlc-hero-inner">
        <p class="tlc-eyebrow">The Archive</p>
        <h1><?php the_title(); ?></h1>
        <?php while ( have_posts() ) : the_post(); $content = trim( get_the_content() ); ?>
            <p class="tlc-hero-sub"><?php echo $content
                ? esc_html( wp_trim_words( wp_strip_all_tags( $content ), 28 ) )
                : 'Browse the archive by subject — every category gathers the summaries and analyses that belong to it, grouped under fourteen enduring fields of knowledge.'; ?></p>
        <?php endwhile; ?>
    </div>
</div>

<!-- TOOLBAR: arama + sort + chip filtreler -->
<div class="tlc-toolbar" id="tlc-toolbar">
    <div class="tlc-toolbar-inner">
        <div class="tlc-toolrow">
            <div class="tlc-search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" class="tlc-search-input" id="tlc-search-input" placeholder="Search categories…" autocomplete="off" spellcheck="false">
            </div>
            <span class="tlc-count" id="tlc-count"><strong><?php echo number_format( $total_count ); ?></strong> categories · <?php echo number_format( $total_entries ); ?> entries</span>
            <div class="tlc-sort">
                <span class="tlc-sort-lbl">Sort</span>
                <div class="tlc-sort-btns">
                    <button class="tlc-sort-btn active" data-sort="entries" type="button">Most entries</button>
                    <button class="tlc-sort-btn" data-sort="az" type="button">A–Z</button>
                </div>
            </div>
        </div>
        <div class="tlc-chips" id="tlc-chips">
            <button class="tlc-chip active" data-main="all" type="button">All subjects <span class="tlc-chip-n"><?php echo number_format( $total_count ); ?></span></button>
            <?php foreach ( $sections as $ms => $label ) : $n = count( $buckets[$ms] ?? [] ); if ( ! $n ) continue; ?>
                <button class="tlc-chip" data-main="<?php echo esc_attr( $ms ); ?>" type="button"><?php echo esc_html( $label ); ?> <span class="tlc-chip-n"><?php echo number_format( $n ); ?></span></button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- SECTIONS -->
<div class="tlc-main" id="tlc-main">
<?php
foreach ( $sections as $ms => $label ) :
    $list = $buckets[ $ms ] ?? [];
    if ( empty( $list ) ) continue;
    $n = count( $list );
?>
    <section class="tlc-section" id="sec-<?php echo esc_attr( $ms ); ?>" data-main="<?php echo esc_attr( $ms ); ?>">
        <div class="tlc-sec-head">
            <div class="tlc-sec-headmain">
                <h2 class="tlc-sec-title"><?php echo esc_html( $label ); ?>
                    <span class="tlc-sec-meta"><?php echo number_format( $n ) . ' ' . ( $n === 1 ? 'subcategory' : 'subcategories' ) . ' · ' . number_format( $sec_entries[$ms] ) . ' entries'; ?></span>
                </h2>
                <?php if ( ! empty( $main_desc[$ms] ) ) : ?><p class="tlc-sec-desc"><?php echo esc_html( $main_desc[$ms] ); ?></p><?php endif; ?>
            </div>
            <?php if ( $n > $PREVIEW ) : ?>
                <button class="tlc-viewall" type="button" data-target="<?php echo esc_attr( $ms ); ?>">View all <?php echo number_format( $n ); ?> &rarr;</button>
            <?php endif; ?>
        </div>
        <div class="tlc-grid">
            <?php foreach ( $list as $cat ) :
                $c_url = get_category_link( $cat->term_id );
                $c_count = (int) $cat->count;
                $c_desc = trim( wp_strip_all_tags( (string) $cat->description ) );
            ?>
                <a href="<?php echo esc_url( is_wp_error( $c_url ) ? '#' : $c_url ); ?>" class="tlc-card"
                   data-name="<?php echo esc_attr( mb_strtolower( $cat->name ) ); ?>" data-count="<?php echo $c_count; ?>">
                    <div class="tlc-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg></div>
                    <p class="tlc-card-name"><?php echo esc_html( $cat->name ); ?></p>
                    <?php if ( $c_desc ) : ?><p class="tlc-card-desc"><?php echo esc_html( wp_trim_words( $c_desc, 16 ) ); ?></p><?php endif; ?>
                    <span class="tlc-card-count">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                        <?php echo number_format( $c_count ) . ' ' . ( $c_count === 1 ? 'entry' : 'entries' ); ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

    <div class="tlc-no-results" id="tlc-no-results" style="display:none">
        <strong>No results</strong>
        <p>Try a different search term.</p>
    </div>
</div><!-- /.tlc-main -->
</main>

<script>
(function () {
    'use strict';
    var main   = document.getElementById('tlc-main');
    var input  = document.getElementById('tlc-search-input');
    var countEl= document.getElementById('tlc-count');
    var chipRow= document.getElementById('tlc-chips');
    var noRes  = document.getElementById('tlc-no-results');
    if (!main) return;

    var PREVIEW  = <?php echo (int) $PREVIEW; ?>;
    var sections = Array.prototype.slice.call(main.querySelectorAll('.tlc-section'));
    var state = { main:'all', sort:'entries', q:'' };
    var timer = null;

    // Kartları bir kez diziye al (sıralama için)
    sections.forEach(function (sec) {
        sec._grid  = sec.querySelector('.tlc-grid');
        sec._cards = Array.prototype.slice.call(sec.querySelectorAll('.tlc-card'));
    });

    function sortCards(cards) {
        var arr = cards.slice();
        if (state.sort === 'az') {
            arr.sort(function (a,b){ return (a.dataset.name||'').localeCompare(b.dataset.name||''); });
        } else {
            arr.sort(function (a,b){ return (parseInt(b.dataset.count,10)||0) - (parseInt(a.dataset.count,10)||0); });
        }
        return arr;
    }

    function render() {
        var q = state.q.trim().toLowerCase();
        var totalShown = 0;

        sections.forEach(function (sec) {
            var inMain = (state.main === 'all' || state.main === sec.dataset.main);
            // sıralı kartları DOM'a diz
            var ordered = sortCards(sec._cards);
            ordered.forEach(function (c){ sec._grid.appendChild(c); });

            // filtre (arama) + önizleme sınırı
            var matches = ordered.filter(function (c){ return !q || (c.dataset.name||'').indexOf(q) >= 0; });
            var cap = (state.main === 'all' && !q) ? PREVIEW : matches.length;  // tek konu/arama → hepsi
            var shown = 0;
            ordered.forEach(function (c) {
                var isMatch = !q || (c.dataset.name||'').indexOf(q) >= 0;
                var vis = inMain && isMatch && shown < cap;
                c.style.display = vis ? '' : 'none';
                if (vis) shown++;
            });
            var secVisible = inMain && matches.length > 0;
            sec.style.display = secVisible ? '' : 'none';
            if (secVisible) totalShown += matches.length;

            // "View all" yalnız All-subjects + arama yokken ve sınır aşılıyorsa
            var va = sec.querySelector('.tlc-viewall');
            if (va) va.style.display = (state.main === 'all' && !q && matches.length > PREVIEW) ? '' : 'none';
        });

        if (noRes) noRes.style.display = totalShown ? 'none' : '';
        if (countEl) {
            countEl.innerHTML = q
                ? '<strong>' + totalShown.toLocaleString() + '</strong> matching categor' + (totalShown!==1?'ies':'y')
                : countEl.getAttribute('data-default') || countEl.innerHTML;
        }
    }
    if (countEl) countEl.setAttribute('data-default', countEl.innerHTML);

    // Chip tıklama
    if (chipRow) chipRow.addEventListener('click', function (e) {
        var chip = e.target.closest('.tlc-chip'); if (!chip) return;
        chipRow.querySelectorAll('.tlc-chip').forEach(function(c){ c.classList.remove('active'); });
        chip.classList.add('active');
        state.main = chip.dataset.main;
        render();
        if (state.main !== 'all') {
            var sec = document.getElementById('sec-' + state.main);
            if (sec) { var tb = document.getElementById('tlc-toolbar'); var off = (tb?tb.offsetHeight:0) + 20;
                window.scrollTo({ top: sec.getBoundingClientRect().top + window.scrollY - off, behavior:'smooth' }); }
        } else { window.scrollTo({ top:0, behavior:'smooth' }); }
    });

    // "View all" → o konuyu seç (chip'e tıklamış gibi)
    main.addEventListener('click', function (e) {
        var va = e.target.closest('.tlc-viewall'); if (!va) return;
        var chip = chipRow ? chipRow.querySelector('.tlc-chip[data-main="'+va.dataset.target+'"]') : null;
        if (chip) chip.click();
    });

    // Sort
    document.querySelectorAll('.tlc-sort-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.tlc-sort-btn').forEach(function(b){ b.classList.remove('active'); });
            btn.classList.add('active');
            state.sort = btn.dataset.sort;
            render();
        });
    });

    // Arama
    if (input) input.addEventListener('input', function () {
        clearTimeout(timer); var v = this.value;
        timer = setTimeout(function () { state.q = v; render(); }, 110);
    });

    render();
})();
</script>

<?php get_footer(); ?>
