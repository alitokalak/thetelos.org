<?php
/**
 * Template Name: Display Categories
 *
 * 15 kalıcı ANA KATEGORİ akordeonu (mockup birebir): her konu bir satır —
 * sol "+"/"−" ikonu, serif başlık + "N subcategories · M entries", açıklama,
 * ilk 4 alt kategori düz amber metin, sağda "Open"/"Close". Açınca tüm alt
 * kategoriler görünür. Üstte arama + "Expand all" + durum yazısı.
 * Kategori URL'leri değişmez — gruplama tls_cat_main_of() ile.
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$cats = get_terms( [ 'taxonomy'=>'category', 'hide_empty'=>true, 'orderby'=>'count', 'order'=>'DESC', 'number'=>0 ] );
if ( is_wp_error( $cats ) ) $cats = [];
$cats = array_values( array_filter( $cats, function( $c ) { return $c->slug !== 'uncategorized'; } ) );
$total_count = count( $cats );

$main_labels = function_exists( 'tls_cat_mains' ) ? tls_cat_mains() : [
    'literature-fiction'=>'Literature & Fiction','philosophy'=>'Philosophy','religion-spirituality'=>'Religion & Spirituality',
    'history'=>'History','biography-memoir'=>'Biography & Memoir','psychology'=>'Psychology',
    'social-sciences'=>'Social Sciences & Politics','science-nature'=>'Science & Nature','technology-engineering'=>'Technology & Engineering',
    'arts-culture'=>'Arts & Culture','business-economics'=>'Business & Economics','health-lifestyle'=>'Health & Lifestyle',
    'self-help'=>'Self-Help & Personal Growth','children-ya'=>'Children & Young Adult',
];
$main_desc = [
    'literature-fiction'=>'Literary works, genres and criticism — from the canon to speculative fiction.',
    'philosophy'=>'Systematic inquiry into existence, knowledge, morality and reason — the spine of the archive.',
    'religion-spirituality'=>'Belief systems, theology and mystical traditions across the world\'s religions.',
    'history'=>'Civilizations, periods and intellectual movements read through their sources.',
    'biography-memoir'=>'Lives recounted — by their subjects and by their scholars.',
    'psychology'=>'Mental processes, behaviour and the sciences of the self.',
    'social-sciences'=>'Societies, institutions and power — sociology, politics, law and language.',
    'science-nature'=>'The natural world through observation, experiment and theoretical modeling.',
    'technology-engineering'=>'Technical knowledge applied to practical problems.',
    'arts-culture'=>'Visual art, music, architecture and performance in cultural context.',
    'business-economics'=>'Markets, institutions and the organization of economic life.',
    'health-lifestyle'=>'Medicine, public health and the literature of everyday living.',
    'self-help'=>'Habits, motivation and personal development.',
    'children-ya'=>'Educational and imaginative works for young readers.',
    '_other'=>'Cross-cutting schools, motifs and regional threads that sit across the main subjects.',
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

$sections = $main_labels; $sections['_other'] = 'Themes & Movements';
$sec_entries = []; $subject_count = 0;
foreach ( $sections as $ms => $label ) {
    $sum = 0; foreach ( ($buckets[$ms] ?? []) as $c ) $sum += (int) $c->count;
    $sec_entries[$ms] = $sum;
    if ( ! empty( $buckets[$ms] ) ) $subject_count++;
}

get_header();
?>

<style>
.cat-hero{ background:#fff; border-bottom:1px solid var(--tls-border); padding:52px 0 34px; }
.cat-wrap{ max-width:var(--tls-container); margin:0 auto; padding:0 32px; }
.cat-eyebrow{ font-family:var(--tls-sans); font-size:10px; letter-spacing:.22em; text-transform:uppercase; color:var(--tls-gold); margin-bottom:12px; }
.cat-hero h1{ font-family:var(--tls-serif); font-size:clamp(30px,4vw,48px); font-weight:400; color:var(--tls-bg-dark); margin:0 0 10px; line-height:1.1; }
.cat-hero-sub{ font-family:var(--tls-sans); font-size:15px; color:var(--tls-muted); margin:0; max-width:600px; line-height:1.6; }

/* Üst bar */
.cat-toolbar{ background:var(--tls-bg); border-bottom:1px solid var(--tls-border); }
.cat-toolrow{ max-width:var(--tls-container); margin:0 auto; padding:16px 32px; display:flex; align-items:center; gap:16px; }
.cat-search{ position:relative; flex:1; max-width:460px; }
.cat-search>svg{ position:absolute; left:15px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:var(--tls-muted); pointer-events:none; }
.cat-search input{ width:100%; height:44px; padding:0 16px 0 42px; font-family:var(--tls-sans); font-size:14px; color:var(--tls-bg-dark); background:#fff; border:1px solid var(--tls-border); border-radius:999px; outline:none; -webkit-appearance:none; transition:border-color .15s, box-shadow .15s; }
.cat-search input::placeholder{ color:#aaa; }
.cat-search input:focus{ border-color:var(--tls-green); box-shadow:0 0 0 3px rgba(0,171,107,.12); }
.cat-expand{ margin-left:auto; font-family:var(--tls-sans); font-size:13px; font-weight:600; white-space:nowrap; color:var(--tls-bg-dark); background:#fff; border:1px solid var(--tls-border); border-radius:999px; padding:9px 18px; cursor:pointer; transition:all .15s; }
.cat-expand:hover{ background:var(--tls-bg-dark); color:#fff; border-color:var(--tls-bg-dark); }
.cat-state{ font-family:var(--tls-sans); font-size:13px; color:var(--tls-muted); white-space:nowrap; }

/* Akordeon */
.cat-list{ max-width:var(--tls-container); margin:0 auto; padding:8px 32px 90px; }
.cat-row{ border-bottom:1px solid var(--tls-border); }
.cat-head{ position:relative; display:flex; gap:16px; padding:24px 96px 22px 0; cursor:pointer; }
.cat-ico{ flex-shrink:0; width:18px; font-family:var(--tls-sans); font-size:22px; font-weight:300; line-height:1.2; color:var(--tls-gold); user-select:none; }
.cat-ico::before{ content:'+'; }
.cat-row.open .cat-ico::before{ content:'\2212'; }   /* − */
.cat-headtext{ flex:1; min-width:0; }
.cat-titleline{ display:flex; align-items:baseline; flex-wrap:wrap; gap:6px 14px; }
.cat-title{ font-family:var(--tls-serif); font-size:26px; font-weight:400; color:var(--tls-bg-dark); line-height:1.1; }
.cat-head:hover .cat-title{ color:#000; }
.cat-meta{ font-family:var(--tls-sans); font-size:12.5px; font-weight:600; color:var(--tls-muted); }
.cat-desc{ font-family:var(--tls-sans); font-size:13.5px; color:var(--tls-muted); line-height:1.55; margin:8px 0 0; max-width:680px; }
.cat-subs{ margin:12px 0 0; line-height:2.1; }
.cat-sub{ font-family:var(--tls-sans); font-size:13px; color:var(--tls-gold); text-decoration:none!important; margin-right:18px; white-space:nowrap; }
.cat-sub:hover{ text-decoration:underline!important; }
.cat-sub .n{ color:var(--tls-muted); }
.cat-sub.cat-extra{ display:none; }
.cat-row.open .cat-sub.cat-extra{ display:inline; }
.cat-open{ position:absolute; right:0; top:26px; font-family:var(--tls-sans); font-size:13px; font-weight:600; color:var(--tls-muted); white-space:nowrap; }
.cat-head:hover .cat-open{ color:var(--tls-bg-dark); }
.cat-row.open .cat-open::after{ content:'Close'; }
.cat-row:not(.open) .cat-open::after{ content:'Open'; }

.cat-none{ text-align:center; padding:70px 20px; }
.cat-none strong{ display:block; font-family:var(--tls-serif); font-size:22px; color:var(--tls-bg-dark); font-weight:400; margin-bottom:8px; }
.cat-none p{ font-family:var(--tls-sans); font-size:14px; color:var(--tls-muted); margin:0; }

@media (max-width:768px){
    .cat-toolrow{ flex-wrap:wrap; gap:10px; }
    .cat-search{ flex:1 1 100%; max-width:none; }
    .cat-expand{ margin-left:0; }
    .cat-title{ font-size:22px; }
    .cat-head{ padding-right:70px; }
    .cat-open{ font-size:12px; }
}
@media (max-width:480px){
    .cat-wrap,.cat-toolrow,.cat-list{ padding-left:16px; padding-right:16px; }
    .cat-hero{ padding:32px 0 20px; }
    .cat-meta{ font-size:11.5px; }
}
</style>

<main id="main" role="main">

<!-- HERO -->
<div class="cat-hero">
    <div class="cat-wrap">
        <p class="cat-eyebrow">The Archive</p>
        <h1><?php the_title(); ?></h1>
        <?php while ( have_posts() ) : the_post(); endwhile; ?>
        <p class="cat-hero-sub"><?php echo esc_html( $subject_count ); ?> subjects, <?php echo number_format( $total_count ); ?> categories. Start from a subject; open it to see everything filed underneath.</p>
    </div>
</div>

<!-- TOOLBAR -->
<div class="cat-toolbar">
    <div class="cat-toolrow">
        <div class="cat-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="cat-search" placeholder="Search categories…" autocomplete="off" spellcheck="false">
        </div>
        <button class="cat-expand" id="cat-expand" type="button">Expand all</button>
        <span class="cat-state" id="cat-state">All subjects collapsed</span>
    </div>
</div>

<!-- ACCORDION -->
<div class="cat-list" id="cat-list">
<?php
foreach ( $sections as $ms => $label ) :
    $list = $buckets[ $ms ] ?? [];
    if ( empty( $list ) ) continue;
    $n = count( $list );
?>
    <section class="cat-row" id="sec-<?php echo esc_attr( $ms ); ?>" data-main="<?php echo esc_attr( $ms ); ?>">
        <div class="cat-head" role="button" tabindex="0" aria-expanded="false">
            <span class="cat-ico" aria-hidden="true"></span>
            <div class="cat-headtext">
                <div class="cat-titleline">
                    <span class="cat-title"><?php echo esc_html( $label ); ?></span>
                    <span class="cat-meta"><?php echo number_format( $n ) . ' ' . ( $n === 1 ? 'subcategory' : 'subcategories' ) . ' · ' . number_format( $sec_entries[$ms] ) . ' entries'; ?></span>
                </div>
                <?php if ( ! empty( $main_desc[$ms] ) ) : ?><p class="cat-desc"><?php echo esc_html( $main_desc[$ms] ); ?></p><?php endif; ?>
                <div class="cat-subs">
                    <?php foreach ( $list as $i => $cat ) :
                        $u = get_category_link( $cat->term_id );
                    ?>
                        <a class="cat-sub<?php echo $i >= 4 ? ' cat-extra' : ''; ?>" href="<?php echo esc_url( is_wp_error($u)?'#':$u ); ?>" data-name="<?php echo esc_attr( mb_strtolower( $cat->name ) ); ?>"><?php echo esc_html( $cat->name ); ?> <span class="n"><?php echo number_format( (int)$cat->count ); ?></span></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <span class="cat-open" aria-hidden="true"></span>
        </div>
    </section>
<?php endforeach; ?>
    <div class="cat-none" id="cat-none" style="display:none">
        <strong>No results</strong><p>Try a different search term.</p>
    </div>
</div>
</main>

<script>
(function () {
    'use strict';
    var list = document.getElementById('cat-list');
    var input = document.getElementById('cat-search');
    var expandBtn = document.getElementById('cat-expand');
    var stateEl = document.getElementById('cat-state');
    var none = document.getElementById('cat-none');
    if (!list) return;

    var rows = Array.prototype.slice.call(list.querySelectorAll('.cat-row'));
    rows.forEach(function (r) {
        r._head = r.querySelector('.cat-head');
        r._subs = Array.prototype.slice.call(r.querySelectorAll('.cat-sub'));
    });

    function open(r, v) { r.classList.toggle('open', v); if (r._head) r._head.setAttribute('aria-expanded', v ? 'true':'false'); }
    function visibleRows() { return rows.filter(function (r) { return r.style.display !== 'none'; }); }
    function anyClosed() { return visibleRows().some(function (r) { return !r.classList.contains('open'); }); }

    function syncState() {
        if (list.classList.contains('searching')) return;
        var vis = visibleRows(), openN = vis.filter(function (r){ return r.classList.contains('open'); }).length;
        expandBtn.textContent = anyClosed() ? 'Expand all' : 'Collapse all';
        stateEl.textContent = openN === 0 ? 'All subjects collapsed'
            : (openN === vis.length ? 'All subjects expanded' : openN + ' of ' + vis.length + ' open');
    }

    rows.forEach(function (r) {
        r._head.addEventListener('click', function (e) {
            if (e.target.closest('a')) return;                 // alt kategori linkine tıklama → git
            if (list.classList.contains('searching')) return;
            open(r, !r.classList.contains('open')); syncState();
        });
        r._head.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(r, !r.classList.contains('open')); syncState(); }
        });
    });

    expandBtn.addEventListener('click', function () {
        var openAll = anyClosed();
        visibleRows().forEach(function (r) { open(r, openAll); });
        syncState();
    });

    var timer = null;
    function search(q) {
        q = q.trim().toLowerCase();
        var on = q.length > 0;
        list.classList.toggle('searching', on);
        var total = 0;
        rows.forEach(function (r) {
            if (!on) { r.style.display=''; open(r, false); r._subs.forEach(function(s){ s.style.display=''; }); return; }
            var hit = 0;
            r._subs.forEach(function (s) {
                var m = (s.dataset.name||'').indexOf(q) >= 0;
                s.style.display = m ? '' : 'none';      // eşleşenleri göster (extra dahil)
                if (m) hit++;
            });
            r.style.display = hit ? '' : 'none';
            open(r, hit > 0);                            // eşleşen satırları aç
            total += hit;
        });
        if (none) none.style.display = (on && total === 0) ? '' : 'none';
        if (on) { expandBtn.textContent = 'Collapse all'; stateEl.textContent = total + ' matching'; }
        else syncState();
    }
    if (input) input.addEventListener('input', function () { clearTimeout(timer); var v=this.value; timer=setTimeout(function(){ search(v); },110); });

    // aramada gizli extra'lar görünsün diye: searching iken tüm eşleşen sub'lar inline
    var st = document.createElement('style');
    st.textContent = '.cat-list.searching .cat-sub.cat-extra{ display:inline; } .cat-list.searching .cat-open{ display:none; }';
    document.head.appendChild(st);

    syncState();
})();
</script>

<?php get_footer(); ?>
