<?php
/**
 * Template Name: Display Categories
 *
 * 15 kalıcı ANA KATEGORİ (chip filtre üstte) + aşağıda AKORDEON:
 * her ana başlık açılır-kapanır; kapalıyken ilk 4 alt kategori önizlemesi,
 * açıkken tüm alt kategoriler kompakt liste (ad · sayı). Kategori URL'leri
 * değişmez — gruplama tls_cat_main_of() (panel override + motor) ile.
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
$sec_entries = []; $total_entries = 0; $subject_count = 0;
foreach ( $sections as $ms => $label ) {
    $sum = 0; foreach ( ($buckets[$ms] ?? []) as $c ) $sum += (int) $c->count;
    $sec_entries[$ms] = $sum; $total_entries += $sum;
    if ( ! empty( $buckets[$ms] ) ) $subject_count++;
}

$chev = '<svg class="tlc-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';

get_header();
?>

<style>
.tlc-hero{ background:#fff; border-bottom:1px solid var(--tls-border); padding:52px 0 34px; }
.tlc-hero-inner{ max-width:var(--tls-container); margin:0 auto; padding:0 32px; }
.tlc-eyebrow{ font-family:var(--tls-sans); font-size:10px; letter-spacing:.22em; text-transform:uppercase; color:var(--tls-gold); margin-bottom:12px; }
.tlc-hero h1{ font-family:var(--tls-serif); font-size:clamp(30px,4vw,48px); font-weight:400; color:var(--tls-bg-dark); margin:0 0 10px; line-height:1.1; }
.tlc-hero-sub{ font-family:var(--tls-sans); font-size:15px; color:var(--tls-muted); margin:0; max-width:600px; line-height:1.6; }

/* ── Sticky toolbar ── */
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
.tlc-count{ font-family:var(--tls-sans); font-size:13px; color:var(--tls-muted); white-space:nowrap; margin-left:auto; }
.tlc-count strong{ color:var(--tls-bg-dark); font-weight:600; }
.tlc-expandall{ font-family:var(--tls-sans); font-size:12.5px; font-weight:600; white-space:nowrap; color:var(--tls-bg-dark); background:#fff; border:1px solid var(--tls-border); border-radius:999px; padding:8px 15px; cursor:pointer; transition:all .15s; }
.tlc-expandall:hover{ background:var(--tls-bg-dark); color:#fff; border-color:var(--tls-bg-dark); }

/* ── Chip filtre satırı ── */
.tlc-chips{ display:flex; flex-wrap:wrap; gap:8px; padding:10px 0 4px; max-height:400px; opacity:1; overflow:hidden; transition:max-height .28s ease, opacity .18s ease, padding .28s ease; }
.tlc-chips::-webkit-scrollbar{ display:none; }
.tlc-toolbar.is-collapsed .tlc-chips{ max-height:0; opacity:0; padding-top:0; padding-bottom:0; pointer-events:none; }
.tlc-chip{ flex-shrink:0; white-space:nowrap; display:inline-flex; align-items:center; gap:7px; font-family:var(--tls-sans); font-size:13px; font-weight:600; color:var(--tls-bg-dark); background:#fff; border:1px solid var(--tls-border); border-radius:999px; padding:7px 14px; cursor:pointer; transition:all .14s; }
.tlc-chip:hover{ border-color:var(--tls-bg-dark); }
.tlc-chip.active{ background:var(--tls-bg-dark); color:#fff; border-color:var(--tls-bg-dark); }
.tlc-chip-n{ font-size:11px; font-weight:700; color:var(--tls-muted); }
.tlc-chip.active .tlc-chip-n{ color:rgba(255,255,255,.65); }

/* ── Akordeon ── */
.tlc-main{ max-width:var(--tls-container); margin:0 auto; padding:20px 32px 90px; }
.tlc-panel{ border-bottom:1px solid var(--tls-border); }
.tlc-phead{ display:flex; align-items:center; gap:12px; width:100%; background:none; border:none; padding:20px 0 4px; cursor:pointer; text-align:left; }
.tlc-chev{ width:18px; height:18px; color:var(--tls-muted); flex-shrink:0; transition:transform .22s ease; }
.tlc-panel.open .tlc-chev{ transform:rotate(180deg); color:var(--tls-bg-dark); }
.tlc-ptitle{ font-family:var(--tls-serif); font-size:23px; font-weight:400; color:var(--tls-bg-dark); line-height:1.15; }
.tlc-pmeta{ font-family:var(--tls-sans); font-size:12.5px; font-weight:600; color:var(--tls-muted); white-space:nowrap; }
.tlc-phead:hover .tlc-ptitle{ color:#000; }
.tlc-pdesc{ font-family:var(--tls-sans); font-size:13.5px; color:var(--tls-muted); line-height:1.55; margin:0 0 10px 30px; max-width:660px; }

/* alt kategori satırı (hem önizleme hem tam liste) */
.tlc-sublist,.tlc-preview{ padding-left:30px; }
.tlc-preview{ display:flex; flex-wrap:wrap; gap:6px 10px; padding-bottom:18px; }
.tlc-panel.open .tlc-preview{ display:none; }
.tlc-body{ display:none; padding-bottom:18px; }
.tlc-panel.open .tlc-body{ display:block; }
.tlc-sublist{ display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:0 28px; padding-left:30px; }
.tlc-sub{ display:flex; align-items:baseline; justify-content:space-between; gap:12px; padding:9px 2px; border-bottom:1px solid var(--tls-border); text-decoration:none!important; font-family:var(--tls-sans); }
.tlc-sub-name{ font-size:14px; color:var(--tls-bg-dark); }
.tlc-sub:hover .tlc-sub-name{ color:var(--tls-green); }
.tlc-sub-n{ font-size:12px; color:var(--tls-muted); font-variant-numeric:tabular-nums; flex-shrink:0; }
/* önizleme pill'leri */
.tlc-pill{ display:inline-flex; align-items:baseline; gap:6px; font-family:var(--tls-sans); font-size:12.5px; color:var(--tls-muted); background:var(--tls-bg); border:1px solid var(--tls-border); border-radius:999px; padding:4px 11px; text-decoration:none!important; transition:all .14s; }
.tlc-pill:hover{ border-color:var(--tls-bg-dark); color:var(--tls-bg-dark); }
.tlc-pill b{ color:var(--tls-bg-dark); font-weight:600; }
.tlc-pill .n{ font-size:11px; }
.tlc-pill-more{ font-size:12px; color:var(--tls-muted); align-self:center; }

.tlc-no-results{ text-align:center; padding:70px 20px; }
.tlc-no-results strong{ display:block; font-family:var(--tls-serif); font-size:22px; color:var(--tls-bg-dark); font-weight:400; margin-bottom:8px; }
.tlc-no-results p{ font-family:var(--tls-sans); font-size:14px; color:var(--tls-muted); margin:0; }

@media (max-width:768px){
    .tlc-toolrow{ flex-wrap:wrap; gap:10px; }
    .tlc-search-wrap{ flex:1 1 100%; max-width:none; order:1; }
    .tlc-count{ order:2; margin-left:0; }
    .tlc-expandall{ order:3; margin-left:auto; }
    .tlc-chips{ flex-wrap:nowrap; overflow-x:auto; overflow-y:hidden; -webkit-overflow-scrolling:touch; scrollbar-width:none; }
    .tlc-toolbar.is-collapsed .tlc-chips{ overflow:hidden; }
    .tlc-sublist{ grid-template-columns:1fr 1fr; gap:0 20px; }
}
@media (max-width:480px){
    .tlc-main{ padding:16px 16px 64px; }
    .tlc-toolbar-inner,.tlc-hero-inner{ padding-left:16px; padding-right:16px; }
    .tlc-hero{ padding:32px 0 20px; }
    .tlc-ptitle{ font-size:20px; }
    .tlc-pmeta{ display:none; }                 /* dar ekranda meta gizle, başlık nefes alsın */
    .tlc-pdesc,.tlc-preview,.tlc-body,.tlc-sublist{ padding-left:0; margin-left:0; }
    .tlc-pdesc{ margin-left:0; }
    .tlc-sublist{ grid-template-columns:1fr; }
}
</style>

<main id="main" role="main">

<!-- HERO -->
<div class="tlc-hero">
    <div class="tlc-hero-inner">
        <p class="tlc-eyebrow">The Archive</p>
        <h1><?php the_title(); ?></h1>
        <?php while ( have_posts() ) : the_post(); endwhile; ?>
        <p class="tlc-hero-sub"><?php echo esc_html( $subject_count ); ?> subjects, <?php echo number_format( $total_count ); ?> categories. Start from a subject; open it to see everything filed underneath.</p>
    </div>
</div>

<!-- TOOLBAR -->
<div class="tlc-toolbar" id="tlc-toolbar">
    <div class="tlc-toolbar-inner">
        <div class="tlc-toolrow">
            <div class="tlc-search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" class="tlc-search-input" id="tlc-search-input" placeholder="Search categories…" autocomplete="off" spellcheck="false">
            </div>
            <span class="tlc-count" id="tlc-count"><strong><?php echo number_format( $total_count ); ?></strong> categories · <?php echo number_format( $total_entries ); ?> entries</span>
            <button class="tlc-expandall" id="tlc-expandall" type="button">Expand all</button>
        </div>
        <div class="tlc-chips" id="tlc-chips">
            <button class="tlc-chip active" data-main="all" type="button">All subjects <span class="tlc-chip-n"><?php echo number_format( $total_count ); ?></span></button>
            <?php foreach ( $sections as $ms => $label ) : $n = count( $buckets[$ms] ?? [] ); if ( ! $n ) continue; ?>
                <button class="tlc-chip" data-main="<?php echo esc_attr( $ms ); ?>" type="button"><?php echo esc_html( $label ); ?> <span class="tlc-chip-n"><?php echo number_format( $n ); ?></span></button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ACCORDION -->
<div class="tlc-main" id="tlc-acc">
<?php
foreach ( $sections as $ms => $label ) :
    $list = $buckets[ $ms ] ?? [];
    if ( empty( $list ) ) continue;
    $n = count( $list );
    $preview = array_slice( $list, 0, 4 );
?>
    <section class="tlc-panel" id="sec-<?php echo esc_attr( $ms ); ?>" data-main="<?php echo esc_attr( $ms ); ?>">
        <button class="tlc-phead" type="button" aria-expanded="false">
            <?php echo $chev; ?>
            <span class="tlc-ptitle"><?php echo esc_html( $label ); ?></span>
            <span class="tlc-pmeta"><?php echo number_format( $n ) . ' ' . ( $n === 1 ? 'subcategory' : 'subcategories' ) . ' · ' . number_format( $sec_entries[$ms] ) . ' entries'; ?></span>
        </button>
        <?php if ( ! empty( $main_desc[$ms] ) ) : ?><p class="tlc-pdesc"><?php echo esc_html( $main_desc[$ms] ); ?></p><?php endif; ?>

        <!-- kapalı önizleme: ilk 4 -->
        <div class="tlc-preview">
            <?php foreach ( $preview as $cat ) :
                $u = get_category_link( $cat->term_id );
            ?>
                <a class="tlc-pill" href="<?php echo esc_url( is_wp_error($u)?'#':$u ); ?>"><b><?php echo esc_html( $cat->name ); ?></b> <span class="n"><?php echo number_format( (int)$cat->count ); ?></span></a>
            <?php endforeach; ?>
            <?php if ( $n > 4 ) : ?><span class="tlc-pill-more">+<?php echo number_format( $n - 4 ); ?> more</span><?php endif; ?>
        </div>

        <!-- açık tam liste -->
        <div class="tlc-body">
            <div class="tlc-sublist">
                <?php foreach ( $list as $cat ) :
                    $u = get_category_link( $cat->term_id );
                ?>
                    <a class="tlc-sub" href="<?php echo esc_url( is_wp_error($u)?'#':$u ); ?>" data-name="<?php echo esc_attr( mb_strtolower( $cat->name ) ); ?>">
                        <span class="tlc-sub-name"><?php echo esc_html( $cat->name ); ?></span>
                        <span class="tlc-sub-n"><?php echo number_format( (int)$cat->count ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
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
    var acc     = document.getElementById('tlc-acc');
    var input   = document.getElementById('tlc-search-input');
    var countEl = document.getElementById('tlc-count');
    var chipRow = document.getElementById('tlc-chips');
    var expandBtn = document.getElementById('tlc-expandall');
    var noRes   = document.getElementById('tlc-no-results');
    var toolbar = document.getElementById('tlc-toolbar');
    if (!acc) return;

    var panels = Array.prototype.slice.call(acc.querySelectorAll('.tlc-panel'));
    panels.forEach(function (p) {
        p._head = p.querySelector('.tlc-phead');
        p._subs = Array.prototype.slice.call(p.querySelectorAll('.tlc-sub'));
    });

    function setOpen(panel, open) {
        panel.classList.toggle('open', open);
        if (panel._head) panel._head.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    function anyClosed() { return panels.some(function (p) { return p.style.display !== 'none' && !p.classList.contains('open'); }); }
    function syncExpandLabel() { if (expandBtn) expandBtn.textContent = anyClosed() ? 'Expand all' : 'Collapse all'; }

    // Panel başlığına tıkla → aç/kapa
    panels.forEach(function (p) {
        p._head.addEventListener('click', function () {
            if (acc.classList.contains('searching')) return;          // aramada hepsi zaten açık
            setOpen(p, !p.classList.contains('open'));
            syncExpandLabel();
        });
    });

    // Tümünü aç / kapat
    if (expandBtn) expandBtn.addEventListener('click', function () {
        var openAll = anyClosed();
        panels.forEach(function (p) { if (p.style.display !== 'none') setOpen(p, openAll); });
        syncExpandLabel();
    });

    // Chip → o paneli aç, diğerlerini kapat, üstüne kaydır
    if (chipRow) chipRow.addEventListener('click', function (e) {
        var chip = e.target.closest('.tlc-chip'); if (!chip) return;
        chipRow.querySelectorAll('.tlc-chip').forEach(function (c) { c.classList.remove('active'); });
        chip.classList.add('active');
        var m = chip.dataset.main;
        if (m === 'all') {
            panels.forEach(function (p) { setOpen(p, false); });
            window.scrollTo({ top:0, behavior:'smooth' });
        } else {
            panels.forEach(function (p) { setOpen(p, p.dataset.main === m); });
            var sec = document.getElementById('sec-' + m);
            if (sec) { var off = (toolbar ? toolbar.offsetHeight : 0) + 16;
                window.scrollTo({ top: sec.getBoundingClientRect().top + window.scrollY - off, behavior:'smooth' }); }
        }
        syncExpandLabel();
    });

    // Arama: eşleşen alt kategoriler; eşleşen paneller açılır, boşlar gizlenir
    var timer = null;
    function doSearch(q) {
        q = q.trim().toLowerCase();
        var searching = q.length > 0;
        acc.classList.toggle('searching', searching);
        var total = 0;
        panels.forEach(function (p) {
            if (!searching) {
                p.style.display = '';
                p._subs.forEach(function (s) { s.style.display = ''; });
                return;
            }
            var hit = 0;
            p._subs.forEach(function (s) {
                var m = (s.dataset.name || '').indexOf(q) >= 0;
                s.style.display = m ? '' : 'none';
                if (m) hit++;
            });
            p.style.display = hit ? '' : 'none';
            if (hit) { p.classList.add('open'); total += hit; }
        });
        if (noRes) noRes.style.display = (searching && total === 0) ? '' : 'none';
        if (countEl) {
            if (searching) countEl.innerHTML = '<strong>' + total.toLocaleString() + '</strong> matching categor' + (total !== 1 ? 'ies' : 'y');
            else countEl.innerHTML = countEl.getAttribute('data-default');
            if (!searching) syncExpandLabel();
        }
    }
    if (countEl) countEl.setAttribute('data-default', countEl.innerHTML);
    if (input) input.addEventListener('input', function () {
        clearTimeout(timer); var v = this.value; timer = setTimeout(function () { doSearch(v); }, 110);
    });

    // Chip satırı: aşağı kaydırınca daralt (sabit eşik bant → titremez)
    if (toolbar) {
        var ticking = false;
        function onScroll() {
            var y = window.scrollY || 0;
            if (y > 200)      toolbar.classList.add('is-collapsed');
            else if (y < 120) toolbar.classList.remove('is-collapsed');
            ticking = false;
        }
        window.addEventListener('scroll', function () { if (!ticking) { requestAnimationFrame(onScroll); ticking = true; } }, { passive:true });
        onScroll();
    }

    syncExpandLabel();
})();
</script>

<?php get_footer(); ?>
