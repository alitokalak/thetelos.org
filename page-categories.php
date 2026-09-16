<?php
/**
 * Template Name: Display Categories
 *
 * Tüm WordPress kategorilerini kapak/sayı ile listeler.
 * Authors sayfasıyla aynı tasarım dilinde (kartlı grid + anlık arama).
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Kategorileri getir (boş olanları gizle) ── */
$cats = get_terms( [
    'taxonomy'   => 'category',
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 0,
] );
if ( is_wp_error( $cats ) ) $cats = [];

/* "Uncategorized" / kategorisiz olanı ele */
$cats = array_values( array_filter( $cats, function( $c ) {
    return $c->slug !== 'uncategorized';
} ) );

$total_count = count( $cats );

get_header();
?>

<style>
/* ── Hero ── */
.tlc-hero {
    background: #fff;
    border-bottom: 1px solid var(--tls-border);
    padding: 56px 0 40px;
}
.tlc-hero-inner {
    max-width: var(--tls-container);
    margin: 0 auto;
    padding: 0 32px;
}
.tlc-eyebrow {
    font-family: var(--tls-sans);
    font-size: 10px;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: var(--tls-gold);
    margin-bottom: 12px;
}
.tlc-hero h1 {
    font-family: var(--tls-serif);
    font-size: clamp(30px, 4vw, 48px);
    font-weight: 400;
    color: var(--tls-bg-dark);
    margin: 0 0 10px;
    line-height: 1.1;
}
.tlc-hero-sub {
    font-family: var(--tls-sans);
    font-size: 15px;
    color: var(--tls-muted);
    margin: 0;
    max-width: 540px;
    line-height: 1.6;
}
.tlc-hero-meta {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-top: 20px;
    flex-wrap: wrap;
}
.tlc-hero-stat {
    font-family: var(--tls-sans);
    font-size: 13px;
    color: var(--tls-muted);
}
.tlc-hero-stat strong {
    font-weight: 600;
    color: var(--tls-bg-dark);
    margin-right: 4px;
}

/* ── Sticky search toolbar ── */
.tlc-toolbar {
    position: sticky;
    top: var(--tls-nav-h);
    z-index: 200;
    background: var(--tls-bg);
    border-bottom: 1px solid var(--tls-border);
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    isolation: isolate;
}
.admin-bar .tlc-toolbar { top: calc(var(--tls-nav-h) + 32px); }
@media screen and (max-width: 782px) {
    .admin-bar .tlc-toolbar { top: calc(var(--tls-nav-h) + 46px); }
}
.tlc-toolbar-inner {
    max-width: var(--tls-container);
    margin: 0 auto;
    padding: 12px 32px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.tlc-search-wrap {
    position: relative;
    flex: 1;
    max-width: 480px;
}
.tlc-search-wrap > svg {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    color: var(--tls-muted);
    pointer-events: none;
}
.tlc-search-input {
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
.tlc-search-input::placeholder { color: #aaa; }
.tlc-search-input:focus {
    border-color: var(--tls-green);
    box-shadow: 0 0 0 3px rgba(0,171,107,.12);
}
.tlc-result-count {
    font-family: var(--tls-sans);
    font-size: 13px;
    color: var(--tls-muted);
    white-space: nowrap;
    flex-shrink: 0;
}
.tlc-result-count span { font-weight: 600; color: var(--tls-bg-dark); }

/* ── Main grid ── */
.tlc-main {
    max-width: var(--tls-container);
    margin: 0 auto;
    padding: 40px 32px 80px;
    position: relative;
    z-index: 1;
}
.tlc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 12px;
}

/* ── Ana kategori bölümü ── */
.tlc-section { margin-bottom: 40px; }
.tlc-section:last-child { margin-bottom: 0; }
.tlc-section-title {
    font-family: var(--tls-serif);
    font-size: 22px;
    font-weight: 400;
    color: var(--tls-bg-dark);
    margin: 0 0 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--tls-border);
    display: flex;
    align-items: baseline;
    gap: 10px;
    scroll-margin-top: calc(var(--tls-nav-h) + 80px);
}
.tlc-section-count {
    font-family: var(--tls-sans);
    font-size: 12px;
    font-weight: 600;
    color: var(--tls-muted);
}

/* ── Category card ── */
.tlc-card {
    display: flex;
    flex-direction: column;
    gap: 7px;
    padding: 20px 22px;
    background: #fff;
    border: 1px solid var(--tls-border);
    border-radius: var(--tls-radius-lg);
    text-decoration: none !important;
    color: var(--tls-bg-dark) !important;
    transition: border-color .15s, box-shadow .15s, transform .15s;
    position: relative;
    overflow: hidden;
}
.tlc-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, var(--tls-green), var(--tls-gold));
    opacity: 0;
    transition: opacity .15s;
}
.tlc-card:hover {
    border-color: transparent;
    box-shadow: var(--tls-shadow);
    transform: translateY(-2px);
    z-index: 2;
}
.tlc-card:hover::before { opacity: 1; }
.tlc-card-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: var(--tls-radius);
    background: var(--tls-bg);
    color: var(--tls-gold);
    flex-shrink: 0;
    border: 1px solid var(--tls-border);
    margin-bottom: 2px;
}
.tlc-card-icon svg { width: 18px; height: 18px; }
.tlc-card-name {
    font-family: var(--tls-serif);
    font-size: 19px;
    font-weight: 400;
    color: var(--tls-bg-dark);
    line-height: 1.2;
    margin: 0;
}
.tlc-card-desc {
    font-family: var(--tls-sans);
    font-size: 12.5px;
    color: var(--tls-muted);
    line-height: 1.55;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.tlc-card-count {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-family: var(--tls-sans);
    font-size: 11.5px;
    font-weight: 500;
    color: var(--tls-muted);
    margin-top: auto;
    padding-top: 6px;
}
.tlc-card-count svg { width: 12px; height: 12px; }

/* No results */
.tlc-no-results {
    text-align: center;
    padding: 80px 20px;
    grid-column: 1 / -1;
}
.tlc-no-results strong {
    display: block;
    font-family: var(--tls-serif);
    font-size: 22px;
    color: var(--tls-bg-dark);
    font-weight: 400;
    margin-bottom: 8px;
}
.tlc-no-results p {
    font-family: var(--tls-sans);
    font-size: 14px;
    color: var(--tls-muted);
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .tlc-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; }
}
@media (max-width: 480px) {
    .tlc-grid { grid-template-columns: 1fr; }
    .tlc-main { padding: 24px 16px 60px; }
    .tlc-toolbar-inner, .tlc-hero-inner { padding-left: 16px; padding-right: 16px; }
    .tlc-hero { padding: 40px 0 28px; }
}
</style>

<main id="main" role="main">

<!-- ══════════ HERO ══════════ -->
<div class="tlc-hero">
    <div class="tlc-hero-inner">
        <p class="tlc-eyebrow">The Archive</p>
        <h1><?php the_title(); ?></h1>
        <?php while ( have_posts() ) : the_post(); ?>
            <?php $content = trim( get_the_content() ); ?>
            <p class="tlc-hero-sub">
                <?php echo $content
                    ? esc_html( wp_trim_words( wp_strip_all_tags( $content ), 28 ) )
                    : 'Browse the archive by subject — from philosophy and ethics to science, history, and literature. Every category gathers the summaries and analyses that belong to it.'; ?>
            </p>
        <?php endwhile; ?>
        <div class="tlc-hero-meta">
            <span class="tlc-hero-stat"><strong><?php echo number_format( $total_count ); ?></strong> categories</span>
        </div>
    </div>
</div>

<!-- ══════════ STICKY SEARCH ══════════ -->
<div class="tlc-toolbar" id="tlc-toolbar">
    <div class="tlc-toolbar-inner">
        <div class="tlc-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="search" class="tlc-search-input" id="tlc-search-input"
                   placeholder="Search categories…" autocomplete="off" spellcheck="false">
        </div>
        <span class="tlc-result-count" id="tlc-result-count" aria-live="polite">
            <span><?php echo number_format( $total_count ); ?></span>&nbsp;categor<?php echo $total_count !== 1 ? 'ies' : 'y'; ?>
        </span>
    </div>
</div>

<?php
/* ── 14 ANA KATEGORİ GRUPLAMASI ─────────────────────────────────────────
   "Kategori Organize" panelinden kaydedilen eşleme (tls_cat_group_of) okunur;
   kategoriler ana başlıklar altında bölümlenir. Kategori URL'leri değişmez.
   Eşleme kaydedilmemişse eski düz grid'e güvenli düşer. */
$main_labels = function_exists( 'tls_cat_mains' ) ? tls_cat_mains() : [
    'literature-fiction'=>'Literature & Fiction','philosophy'=>'Philosophy',
    'religion-spirituality'=>'Religion & Spirituality','history'=>'History',
    'biography-memoir'=>'Biography & Memoir','psychology'=>'Psychology',
    'social-sciences'=>'Social Sciences & Politics','science-nature'=>'Science & Nature',
    'technology-engineering'=>'Technology & Engineering','arts-culture'=>'Arts & Culture',
    'business-economics'=>'Business & Economics','health-lifestyle'=>'Health & Lifestyle',
    'self-help'=>'Self-Help & Personal Growth','children-ya'=>'Children & Young Adult',
];
// HER ZAMAN grupla: panelden kaydedilen özel atama (override) varsa o, yoksa
// otomatik tahmin. Böylece kayıt yapılmasa bile sayfa gruplu görünür.
$use_groups = true;

// Kovalar: ana sıra + sonda "Other" (motorun emin olamadıkları)
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

// Tek kart çizen yardımcı
$tlc_card = function ( $cat ) {
    $c_url   = get_category_link( $cat->term_id );
    $c_desc  = $cat->description;
    $c_count = (int) $cat->count;
    ?>
    <a href="<?php echo esc_url( $c_url ); ?>" class="tlc-card"
       data-name="<?php echo esc_attr( mb_strtolower( $cat->name ) ); ?>">
        <div class="tlc-card-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
            </svg>
        </div>
        <p class="tlc-card-name"><?php echo esc_html( $cat->name ); ?></p>
        <?php if ( $c_desc ) : ?>
            <p class="tlc-card-desc"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $c_desc ), 16 ) ); ?></p>
        <?php endif; ?>
        <span class="tlc-card-count">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/>
            </svg>
            <?php echo number_format( $c_count ) . ' ' . ( $c_count === 1 ? 'entry' : 'entries' ); ?>
        </span>
    </a>
    <?php
};
?>

<!-- ══════════ GRID ══════════ -->
<div class="tlc-main" id="tlc-main">
<?php if ( empty( $cats ) ) : ?>
    <div class="tlc-grid"><div class="tlc-no-results">
        <strong>No categories yet</strong>
        <p>Categories will appear here as content is published.</p>
    </div></div>

<?php elseif ( $use_groups ) :
    $sections = $main_labels;
    $sections['_other'] = 'Other';
    foreach ( $sections as $ms => $label ) :
        $list = $buckets[ $ms ] ?? [];
        if ( empty( $list ) ) continue;
?>
    <section class="tlc-section" data-main="<?php echo esc_attr( $ms ); ?>">
        <h2 class="tlc-section-title"><?php echo esc_html( $label ); ?>
            <span class="tlc-section-count"><?php echo number_format( count( $list ) ); ?></span>
        </h2>
        <div class="tlc-grid">
            <?php foreach ( $list as $cat ) $tlc_card( $cat ); ?>
        </div>
    </section>
<?php endforeach;

else : ?>
    <div class="tlc-grid">
        <?php foreach ( $cats as $cat ) $tlc_card( $cat ); ?>
    </div>
<?php endif; ?>

    <div class="tlc-no-results" id="tlc-no-results" style="display:none">
        <strong>No results</strong>
        <p>Try a different search term.</p>
    </div>
</div><!-- /.tlc-main -->
</main>

<!-- ══════════ INSTANT SEARCH ══════════ -->
<script>
(function () {
    'use strict';
    var input   = document.getElementById('tlc-search-input');
    var countEl = document.getElementById('tlc-result-count');
    var main    = document.getElementById('tlc-main');
    if (!input || !main) return;

    var cards    = Array.prototype.slice.call(main.querySelectorAll('.tlc-card'));
    var sections = Array.prototype.slice.call(main.querySelectorAll('.tlc-section'));
    var noRes    = document.getElementById('tlc-no-results');
    var timer = null;

    function filterCards(q) {
        q = q.trim().toLowerCase();
        var total = 0;
        cards.forEach(function (card) {
            var match = !q || (card.dataset.name || '').includes(q);
            card.style.display = match ? '' : 'none';
            if (match) total++;
        });
        // Görünür kartı olmayan bölüm başlığını gizle
        sections.forEach(function (sec) {
            var any = sec.querySelector('.tlc-card:not([style*="display: none"])');
            sec.style.display = any ? '' : 'none';
        });
        if (noRes) noRes.style.display = total ? 'none' : '';
        if (countEl) {
            countEl.innerHTML = total
                ? '<span>' + total.toLocaleString() + '</span>&nbsp;categor' + (total !== 1 ? 'ies' : 'y')
                : 'No results';
        }
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var val = this.value;
        timer = setTimeout(function () { filterCards(val); }, 100);
    });
})();
</script>

<?php get_footer(); ?>
