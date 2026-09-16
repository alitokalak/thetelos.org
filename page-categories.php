<?php
/**
 * Template Name: Display Categories
 *
 * Üstte ANA KATEGORİ kutucukları (chip) + aşağıda 15 konu AKORDEONU.
 * Kapalı: sol "+" ikonu, serif başlık + "N subcategories · M entries",
 *   açıklama, ilk 4 alt kategori düz amber metin, sağda "Open".
 * Açık: "−" ikonu, sağda "Close"; alt kategoriler İKİ SÜTUNLU liste
 *   (serif ad + sağda sayı + altında açıklama).
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
$sec_entries = []; $subject_count = 0; $total_entries = 0;
foreach ( $sections as $ms => $label ) {
    $sum = 0; foreach ( ($buckets[$ms] ?? []) as $c ) $sum += (int) $c->count;
    $sec_entries[$ms] = $sum; $total_entries += $sum;
    if ( ! empty( $buckets[$ms] ) ) $subject_count++;
}

get_header();
?>

<style>
/* Başlık bloğu artık panelin İÇİNDE — kaydırınca kutucuklarla birlikte daralır */
.cat-intro{ max-width:var(--tls-container); margin:0 auto; padding:40px 32px 6px; overflow:hidden; max-height:400px; opacity:1; transition:max-height .28s ease, opacity .18s ease, padding .28s ease; }
.cat-eyebrow{ font-family:var(--tls-sans); font-size:10px; letter-spacing:.22em; text-transform:uppercase; color:var(--tls-gold); margin:0 0 10px; }
.cat-intro h1{ font-family:var(--tls-serif); font-size:clamp(28px,3.4vw,42px); font-weight:400; color:var(--tls-bg-dark); margin:0 0 8px; line-height:1.1; }
.cat-hero-sub{ font-family:var(--tls-sans); font-size:14px; color:var(--tls-muted); margin:0; max-width:620px; line-height:1.55; }
.cat-toolbar.is-collapsed .cat-intro{ max-height:0; opacity:0; padding-top:0; padding-bottom:0; }

/* Üst bar (sabit) */
.cat-toolbar{ position:sticky; top:var(--tls-nav-h); z-index:200; background:var(--tls-bg); border-bottom:1px solid var(--tls-border); box-shadow:0 2px 12px rgba(0,0,0,.06); isolation:isolate; }
.admin-bar .cat-toolbar{ top:calc(var(--tls-nav-h) + 32px); }
@media screen and (max-width:782px){ .admin-bar .cat-toolbar{ top:calc(var(--tls-nav-h) + 46px); } }
.cat-toolrow{ max-width:var(--tls-container); margin:0 auto; padding:16px 32px 10px; display:flex; align-items:center; gap:16px; }
.cat-search{ position:relative; flex:1; max-width:460px; }
.cat-search>svg{ position:absolute; left:15px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:var(--tls-muted); pointer-events:none; }
.cat-search input{ width:100%; height:44px; padding:0 16px 0 42px; font-family:var(--tls-sans); font-size:14px; color:var(--tls-bg-dark); background:#fff; border:1px solid var(--tls-border); border-radius:999px; outline:none; -webkit-appearance:none; transition:border-color .15s, box-shadow .15s; }
.cat-search input::placeholder{ color:#aaa; }
.cat-search input:focus{ border-color:var(--tls-green); box-shadow:0 0 0 3px rgba(0,171,107,.12); }
.cat-count{ font-family:var(--tls-sans); font-size:12.5px; color:var(--tls-muted); white-space:nowrap; }
.cat-count strong{ color:var(--tls-bg-dark); font-weight:700; }
.cat-sort{ display:flex; align-items:center; gap:8px; margin-left:auto; }
.cat-sort-lbl{ font-family:var(--tls-sans); font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--tls-muted); }
.cat-sort-btns{ display:inline-flex; background:#fff; border:1px solid var(--tls-border); border-radius:999px; padding:3px; }
.cat-sort-btn{ font-family:var(--tls-sans); font-size:12.5px; font-weight:600; color:var(--tls-muted); background:none; border:none; padding:6px 14px; border-radius:999px; cursor:pointer; transition:all .15s; white-space:nowrap; }
.cat-sort-btn.active{ background:var(--tls-bg-dark); color:#fff; }
.cat-expand{ font-family:var(--tls-sans); font-size:13px; font-weight:600; white-space:nowrap; color:var(--tls-bg-dark); background:#fff; border:1px solid var(--tls-border); border-radius:999px; padding:9px 18px; cursor:pointer; transition:all .15s; }
.cat-expand:hover{ background:var(--tls-bg-dark); color:#fff; border-color:var(--tls-bg-dark); }
.cat-state{ font-family:var(--tls-sans); font-size:13px; color:var(--tls-muted); white-space:nowrap; }

/* Ana kategori kutucukları (chip) */
.cat-chips{ max-width:var(--tls-container); margin:0 auto; padding:0 32px 12px; display:flex; flex-wrap:wrap; gap:8px; max-height:400px; opacity:1; overflow:hidden; transition:max-height .28s ease, opacity .18s ease, padding .28s ease; }
.cat-chips::-webkit-scrollbar{ display:none; }
.cat-toolbar.is-collapsed .cat-chips{ max-height:0; opacity:0; padding-top:0; padding-bottom:0; pointer-events:none; }
.cat-chip{ flex-shrink:0; white-space:nowrap; display:inline-flex; align-items:center; gap:7px; font-family:var(--tls-sans); font-size:13px; font-weight:600; color:var(--tls-bg-dark); background:#fff; border:1px solid var(--tls-border); border-radius:999px; padding:7px 14px; cursor:pointer; transition:all .14s; }
.cat-chip:hover{ border-color:var(--tls-bg-dark); }
.cat-chip.active{ background:var(--tls-bg-dark); color:#fff; border-color:var(--tls-bg-dark); }
.cat-chip-n{ font-size:11px; font-weight:700; color:var(--tls-muted); }
.cat-chip.active .cat-chip-n{ color:rgba(255,255,255,.65); }

/* Akordeon */
.cat-list{ max-width:var(--tls-container); margin:0 auto; padding:8px 32px 90px; }
.cat-row{ border-bottom:1px solid var(--tls-border); scroll-margin-top:calc(var(--tls-nav-h) + 150px); }
.cat-head{ position:relative; display:flex; gap:16px; padding:24px 96px 22px 0; cursor:pointer; }
.cat-ico{ flex-shrink:0; width:18px; font-family:var(--tls-sans); font-size:22px; font-weight:300; line-height:1.25; color:var(--tls-gold); user-select:none; }
.cat-ico::before{ content:'+'; }
.cat-row.open .cat-ico::before{ content:'\2212'; }
.cat-headtext{ flex:1; min-width:0; }
.cat-titleline{ display:flex; align-items:baseline; flex-wrap:wrap; gap:6px 14px; }
.cat-title{ font-family:var(--tls-serif); font-size:26px; font-weight:400; color:var(--tls-bg-dark); line-height:1.1; }
.cat-head:hover .cat-title{ color:#000; }
.cat-meta{ font-family:var(--tls-sans); font-size:12.5px; font-weight:600; color:var(--tls-muted); }
.cat-desc{ font-family:var(--tls-sans); font-size:13.5px; color:var(--tls-muted); line-height:1.55; margin:8px 0 0; max-width:680px; }
.cat-open{ position:absolute; right:0; top:26px; font-family:var(--tls-sans); font-size:13px; font-weight:600; color:var(--tls-muted); white-space:nowrap; }
.cat-head:hover .cat-open{ color:var(--tls-bg-dark); }
.cat-row.open .cat-open::after{ content:'Close'; }
.cat-row:not(.open) .cat-open::after{ content:'Open'; }

/* Kapalı önizleme: ilk 4, düz amber metin */
.cat-preview{ margin:12px 0 0; line-height:2.1; }
.cat-pv{ font-family:var(--tls-sans); font-size:13px; color:var(--tls-gold); text-decoration:none!important; margin-right:18px; white-space:nowrap; }
.cat-pv:hover{ text-decoration:underline!important; }
.cat-pv .n{ color:var(--tls-muted); }
.cat-row.open .cat-preview{ display:none; }

/* Açık: iki sütunlu detaylı liste */
.cat-grid{ display:none; grid-template-columns:1fr 1fr; gap:0 48px; margin-top:10px; }
.cat-row.open .cat-grid{ display:grid; }
.cat-item{ display:block; padding:15px 0; border-top:1px solid var(--tls-border); text-decoration:none!important; }
.cat-item-top{ display:flex; align-items:baseline; justify-content:space-between; gap:14px; }
.cat-item-name{ font-family:var(--tls-serif); font-size:18px; font-weight:400; color:var(--tls-bg-dark); line-height:1.2; }
.cat-item:hover .cat-item-name{ color:var(--tls-green); }
.cat-item-n{ font-family:var(--tls-sans); font-size:12px; color:var(--tls-muted); white-space:nowrap; flex-shrink:0; }
.cat-item-desc{ font-family:var(--tls-sans); font-size:12.5px; color:var(--tls-muted); line-height:1.5; margin:5px 0 0; }

.cat-none{ text-align:center; padding:70px 20px; }
.cat-none strong{ display:block; font-family:var(--tls-serif); font-size:22px; color:var(--tls-bg-dark); font-weight:400; margin-bottom:8px; }
.cat-none p{ font-family:var(--tls-sans); font-size:14px; color:var(--tls-muted); margin:0; }

@media (max-width:768px){
    .cat-toolrow{ flex-wrap:wrap; gap:10px; }
    .cat-search{ flex:1 1 100%; max-width:none; }
    .cat-sort{ margin-left:auto; }
    .cat-sort-lbl{ display:none; }
    .cat-count{ order:3; flex:1 1 100%; }
    .cat-chips{ flex-wrap:nowrap; overflow-x:auto; overflow-y:hidden; -webkit-overflow-scrolling:touch; }
    .cat-title{ font-size:22px; }
    .cat-head{ padding-right:70px; }
    .cat-grid{ grid-template-columns:1fr; gap:0; }
}
@media (max-width:480px){
    .cat-intro,.cat-toolrow,.cat-chips,.cat-list{ padding-left:16px; padding-right:16px; }
    .cat-intro{ padding-top:26px; }
    .cat-meta{ font-size:11.5px; }
}
</style>

<main id="main" role="main">

<!-- BAŞLIK + ARAÇ ÇUBUĞU + KUTUCUKLAR: tek bütün panel -->
<div class="cat-toolbar" id="cat-toolbar">
    <div class="cat-intro" id="cat-intro">
        <p class="cat-eyebrow">The Archive</p>
        <h1><?php the_title(); ?></h1>
        <?php while ( have_posts() ) : the_post(); endwhile; ?>
        <p class="cat-hero-sub">Start from a subject, then open it to see everything filed underneath.</p>
    </div>
    <div class="cat-toolrow">
        <div class="cat-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="cat-search" placeholder="Search categories…" autocomplete="off" spellcheck="false">
        </div>
        <span class="cat-count" id="cat-count"><strong><?php echo number_format( $subject_count ); ?></strong> subjects · <strong><?php echo number_format( $total_count ); ?></strong> categories · <?php echo number_format( $total_entries ); ?> entries</span>
        <div class="cat-sort">
            <span class="cat-sort-lbl">Sort</span>
            <div class="cat-sort-btns" id="cat-sort">
                <button class="cat-sort-btn active" data-sort="entries" type="button">Most entries</button>
                <button class="cat-sort-btn" data-sort="az" type="button">A&ndash;Z</button>
            </div>
        </div>
        <button class="cat-expand" id="cat-expand" type="button">Expand all</button>
        <span class="cat-state" id="cat-state">All subjects collapsed</span>
    </div>
    <div class="cat-chips" id="cat-chips">
        <button class="cat-chip active" data-main="all" type="button">All subjects <span class="cat-chip-n"><?php echo number_format( $total_count ); ?></span></button>
        <?php foreach ( $sections as $ms => $label ) : $n = count( $buckets[$ms] ?? [] ); if ( ! $n ) continue; ?>
            <button class="cat-chip" data-main="<?php echo esc_attr( $ms ); ?>" type="button"><?php echo esc_html( $label ); ?> <span class="cat-chip-n"><?php echo number_format( $n ); ?></span></button>
        <?php endforeach; ?>
    </div>
</div>

<!-- ACCORDION -->
<div class="cat-list" id="cat-list">
<?php
foreach ( $sections as $ms => $label ) :
    $list = $buckets[ $ms ] ?? [];
    if ( empty( $list ) ) continue;
    $n = count( $list );
    $preview = array_slice( $list, 0, 4 );
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

                <!-- kapalı: ilk 4 -->
                <div class="cat-preview">
                    <?php foreach ( $preview as $cat ) : $u = get_category_link( $cat->term_id ); ?>
                        <a class="cat-pv" href="<?php echo esc_url( is_wp_error($u)?'#':$u ); ?>"><?php echo esc_html( $cat->name ); ?> <span class="n"><?php echo number_format( (int)$cat->count ); ?></span></a>
                    <?php endforeach; ?>
                </div>

                <!-- açık: iki sütunlu detaylı -->
                <div class="cat-grid">
                    <?php foreach ( $list as $cat ) :
                        $u = get_category_link( $cat->term_id );
                        $d = trim( wp_strip_all_tags( (string) $cat->description ) );
                    ?>
                        <a class="cat-item" href="<?php echo esc_url( is_wp_error($u)?'#':$u ); ?>" data-name="<?php echo esc_attr( mb_strtolower( $cat->name ) ); ?>" data-count="<?php echo (int)$cat->count; ?>">
                            <span class="cat-item-top">
                                <span class="cat-item-name"><?php echo esc_html( $cat->name ); ?></span>
                                <span class="cat-item-n"><?php echo number_format( (int)$cat->count ) . ' entries'; ?></span>
                            </span>
                            <?php if ( $d ) : ?><span class="cat-item-desc"><?php echo esc_html( wp_trim_words( $d, 18 ) ); ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <span class="cat-open" aria-hidden="true"></span>
        </div>
    </section>
<?php endforeach; ?>
    <div class="cat-none" id="cat-none" style="display:none"><strong>No results</strong><p>Try a different search term.</p></div>
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
    var chipRow = document.getElementById('cat-chips');
    var toolbar = document.getElementById('cat-toolbar');
    if (!list) return;

    var rows = Array.prototype.slice.call(list.querySelectorAll('.cat-row'));
    var TOTAL = rows.length;
    rows.forEach(function (r) {
        r._head = r.querySelector('.cat-head');
        r._items = Array.prototype.slice.call(r.querySelectorAll('.cat-item'));
    });
    function open(r, v){ r.classList.toggle('open', v); if (r._head) r._head.setAttribute('aria-expanded', v?'true':'false'); }
    function vis(){ return rows.filter(function(r){ return r.style.display !== 'none'; }); }
    function anyClosed(){ return vis().some(function(r){ return !r.classList.contains('open'); }); }
    function syncState(){
        if (list.classList.contains('searching')) return;
        var v = vis(), o = v.filter(function(r){ return r.classList.contains('open'); }).length;
        expandBtn.textContent = anyClosed() ? 'Expand all' : 'Collapse all';
        stateEl.textContent = o === 0 ? 'All subjects collapsed' : (o + ' of ' + v.length + ' subjects open');
    }

    rows.forEach(function (r) {
        r._head.addEventListener('click', function (e) {
            if (e.target.closest('a')) return;
            if (list.classList.contains('searching')) return;
            open(r, !r.classList.contains('open')); syncState();
        });
        r._head.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(r, !r.classList.contains('open')); syncState(); }
        });
    });

    expandBtn.addEventListener('click', function () {
        var openAll = anyClosed(); vis().forEach(function(r){ open(r, openAll); }); syncState();
    });

    // SORT: açık listedeki alt kategorileri en çok özet / alfabetik diz
    var sortBox = document.getElementById('cat-sort');
    function applySort(mode) {
        rows.forEach(function (r) {
            var grid = r.querySelector('.cat-grid'); if (!grid) return;
            var items = Array.prototype.slice.call(grid.querySelectorAll('.cat-item'));
            items.sort(function (a, b) {
                if (mode === 'az') return (a.dataset.name||'').localeCompare(b.dataset.name||'');
                return (parseInt(b.dataset.count,10)||0) - (parseInt(a.dataset.count,10)||0);
            });
            items.forEach(function (it) { grid.appendChild(it); });   // yeniden sırala
        });
    }
    if (sortBox) sortBox.addEventListener('click', function (e) {
        var btn = e.target.closest('.cat-sort-btn'); if (!btn) return;
        sortBox.querySelectorAll('.cat-sort-btn').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
        applySort(btn.dataset.sort);
    });

    // Chip → o konuyu aç + üstüne kaydır (all → hepsini kapat)
    if (chipRow) chipRow.addEventListener('click', function (e) {
        var chip = e.target.closest('.cat-chip'); if (!chip) return;
        chipRow.querySelectorAll('.cat-chip').forEach(function(c){ c.classList.remove('active'); });
        chip.classList.add('active');
        var m = chip.dataset.main;
        if (m === 'all') { rows.forEach(function(r){ open(r,false); }); window.scrollTo({top:0,behavior:'smooth'}); }
        else {
            rows.forEach(function(r){ open(r, r.dataset.main === m); });
            var sec = document.getElementById('sec-'+m);
            if (sec){ var off=(toolbar?toolbar.offsetHeight:0)+16; window.scrollTo({top:sec.getBoundingClientRect().top+window.scrollY-off, behavior:'smooth'}); }
        }
        syncState();
    });

    var timer = null;
    function search(q) {
        q = q.trim().toLowerCase(); var on = q.length>0;
        list.classList.toggle('searching', on);
        var total = 0;
        rows.forEach(function (r) {
            if (!on) { r.style.display=''; open(r,false); r._items.forEach(function(it){ it.style.display=''; }); return; }
            var hit = 0;
            r._items.forEach(function (it) { var mm=(it.dataset.name||'').indexOf(q)>=0; it.style.display=mm?'':'none'; if(mm)hit++; });
            r.style.display = hit ? '' : 'none'; open(r, hit>0); total += hit;
        });
        if (none) none.style.display = (on && total===0) ? '' : 'none';
        if (on) { expandBtn.textContent='Collapse all'; stateEl.textContent = total+' matching'; }
        else syncState();
    }
    if (input) input.addEventListener('input', function(){ clearTimeout(timer); var v=this.value; timer=setTimeout(function(){ search(v); },110); });
    // aramada iki sütun yerine tek akış görünür kalsın diye grid zaten .open ile açılıyor

    // Chip satırı: aşağı kaydırınca daralt (sabit eşik → titremez)
    if (toolbar) {
        var ticking=false;
        function onScroll(){ var y=window.scrollY||0; if(y>200) toolbar.classList.add('is-collapsed'); else if(y<120) toolbar.classList.remove('is-collapsed'); ticking=false; }
        window.addEventListener('scroll', function(){ if(!ticking){ requestAnimationFrame(onScroll); ticking=true; } }, {passive:true});
        onScroll();
    }

    syncState();
})();
</script>

<?php get_footer(); ?>
