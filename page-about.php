<?php
/**
 * page-about.php — "About" sayfası için özel modern şablon.
 *
 * Slug'ı "about" olan WordPress sayfasına OTOMATİK uygulanır
 * (page-{slug}.php WordPress şablon hiyerarşisi). Şablon atamaya gerek yok.
 *
 * Mevcut sayfa içeriği (the_content) korunur ve şık bir "prose" kutusunda
 * render edilir; üstüne hero + canlı istatistikler, altına yönlendirme
 * kartları ve güçlü bir CTA bandı eklenir.
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Belirli bir sayfa şablonunu kullanan sayfanın URL'sini bul ──
   Slug'a güvenmek yerine şablon-atamasından bulur; her kurulumda çalışır. */
if ( ! function_exists( 'tls_about_tpl_url' ) ) {
    function tls_about_tpl_url( $tpl ) {
        $pages = get_pages( [
            'meta_key'   => '_wp_page_template',
            'meta_value' => $tpl,
            'number'     => 1,
        ] );
        return $pages ? get_permalink( $pages[0]->ID ) : '';
    }
}

/* ── Canlı istatistikler ── */
$stat_summaries = (int) ( wp_count_posts( 'post' )->publish ?? 0 );
$stat_analyses  = post_type_exists( 'analysis' ) ? (int) ( wp_count_posts( 'analysis' )->publish ?? 0 ) : 0;
$stat_authors   = (int) wp_count_terms( [ 'taxonomy' => 'authors', 'hide_empty' => true ] );
$cats_terms     = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => true, 'number' => 0 ] );
$stat_cats      = is_array( $cats_terms ) ? count( array_filter( $cats_terms, fn( $c ) => $c->slug !== 'uncategorized' ) ) : 0;

/* ── Yönlendirme hedefleri ── */
$url_authors    = tls_about_tpl_url( 'template-listauthors.php' );
$url_categories = tls_about_tpl_url( 'page-categories.php' );
$url_home       = home_url( '/' );

get_header();
?>

<style>
/* ════════ ABOUT (tla-) ════════ */
.tla-hero {
    background: #fff;
    border-bottom: 1px solid var(--tls-border);
    padding: 64px 0 44px;
}
.tla-inner {
    max-width: 880px;
    margin: 0 auto;
    padding: 0 32px;
}
.tla-eyebrow {
    font-family: var(--tls-sans);
    font-size: 10px;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: var(--tls-gold);
    margin-bottom: 14px;
}
.tla-hero h1 {
    font-family: var(--tls-serif);
    font-size: clamp(34px, 5vw, 56px);
    font-weight: 400;
    color: var(--tls-bg-dark);
    margin: 0 0 16px;
    line-height: 1.08;
}
.tla-lead {
    font-family: var(--tls-sans);
    font-size: clamp(16px, 2vw, 19px);
    color: var(--tls-muted);
    margin: 0;
    max-width: 620px;
    line-height: 1.65;
}

/* ── İstatistik şeridi ── */
.tla-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 36px;
    margin-top: 34px;
    padding-top: 28px;
    border-top: 1px solid var(--tls-border);
}
.tla-stat-num {
    font-family: var(--tls-serif);
    font-size: clamp(26px, 3.4vw, 38px);
    font-weight: 400;
    color: var(--tls-bg-dark);
    line-height: 1;
}
.tla-stat-label {
    font-family: var(--tls-sans);
    font-size: 12px;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--tls-muted);
    margin-top: 7px;
}

/* ── Gövde metni (the_content) ── */
.tla-body {
    max-width: 720px;
    margin: 0 auto;
    padding: 56px 32px 8px;
}
.tla-prose {
    font-family: var(--tls-sans);
    font-size: 17px;
    line-height: 1.78;
    color: #2c3038;
}
.tla-prose > *:first-child { margin-top: 0; }
.tla-prose p { margin: 0 0 1.25em; }
.tla-prose h2 {
    font-family: var(--tls-serif);
    font-weight: 400;
    font-size: 27px;
    color: var(--tls-bg-dark);
    margin: 1.8em 0 .6em;
    line-height: 1.2;
}
.tla-prose h3 {
    font-family: var(--tls-serif);
    font-weight: 400;
    font-size: 21px;
    color: var(--tls-bg-dark);
    margin: 1.5em 0 .5em;
}
.tla-prose a { color: var(--tls-gold); text-decoration: underline; text-underline-offset: 2px; }
.tla-prose ul, .tla-prose ol { margin: 0 0 1.25em; padding-left: 1.3em; }
.tla-prose li { margin-bottom: .5em; }
.tla-prose blockquote {
    margin: 1.5em 0;
    padding: 4px 0 4px 22px;
    border-left: 3px solid var(--tls-gold);
    color: var(--tls-muted);
    font-style: italic;
}
.tla-prose img { max-width: 100%; height: auto; border-radius: var(--tls-radius); }

/* ── Yönlendirme kartları ── */
.tla-explore {
    max-width: 880px;
    margin: 48px auto 0;
    padding: 0 32px;
}
.tla-explore-title {
    font-family: var(--tls-sans);
    font-size: 11px;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: var(--tls-muted);
    text-align: center;
    margin-bottom: 20px;
}
.tla-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
}
.tla-card {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 24px;
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
    position: absolute; top: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, var(--tls-green), var(--tls-gold));
    opacity: 0; transition: opacity .15s;
}
.tla-card:hover { border-color: transparent; box-shadow: var(--tls-shadow); transform: translateY(-2px); }
.tla-card:hover::before { opacity: 1; }
.tla-card-icon {
    display: flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: var(--tls-radius);
    background: var(--tls-bg); color: var(--tls-gold);
    border: 1px solid var(--tls-border);
}
.tla-card-icon svg { width: 19px; height: 19px; }
.tla-card-name {
    font-family: var(--tls-serif); font-size: 20px; font-weight: 400;
    color: var(--tls-bg-dark); margin: 2px 0 0;
}
.tla-card-desc {
    font-family: var(--tls-sans); font-size: 13px; color: var(--tls-muted);
    line-height: 1.55; margin: 0;
}
.tla-card-go {
    font-family: var(--tls-sans); font-size: 12.5px; font-weight: 600;
    color: var(--tls-gold); margin-top: auto; padding-top: 4px;
    display: inline-flex; align-items: center; gap: 5px;
}
.tla-card-go svg { width: 13px; height: 13px; transition: transform .15s; }
.tla-card:hover .tla-card-go svg { transform: translateX(3px); }

/* ── Son CTA bandı ── */
.tla-cta {
    margin: 64px 0 0;
    background: var(--tls-bg-dark);
    position: relative;
    overflow: hidden;
}
.tla-cta::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(circle at 80% 20%, rgba(184,146,42,.18), transparent 55%);
    pointer-events: none;
}
.tla-cta-inner {
    max-width: 720px; margin: 0 auto; padding: 72px 32px;
    text-align: center; position: relative; z-index: 1;
}
.tla-cta h2 {
    font-family: var(--tls-serif); font-weight: 400;
    font-size: clamp(28px, 4vw, 42px); color: #fff;
    margin: 0 0 14px; line-height: 1.15;
}
.tla-cta p {
    font-family: var(--tls-sans); font-size: 16px;
    color: rgba(255,255,255,.7); margin: 0 0 32px; line-height: 1.6;
}
.tla-btns { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }
.tla-btn {
    display: inline-flex; align-items: center; gap: 8px;
    font-family: var(--tls-sans); font-size: 15px; font-weight: 600;
    padding: 14px 28px; border-radius: 30px; text-decoration: none !important;
    transition: transform .15s, box-shadow .15s, background .15s;
}
.tla-btn svg { width: 17px; height: 17px; }
.tla-btn-primary {
    background: var(--tls-gold); color: #1a1a1a !important;
    box-shadow: 0 6px 20px rgba(184,146,42,.32);
}
.tla-btn-primary:hover { transform: translateY(-2px); background: var(--tls-gold-light); }
.tla-btn-ghost {
    background: transparent; color: #fff !important;
    border: 1px solid rgba(255,255,255,.28);
}
.tla-btn-ghost:hover { transform: translateY(-2px); background: rgba(255,255,255,.08); }

@media (max-width: 600px) {
    .tla-hero { padding: 44px 0 32px; }
    .tla-inner, .tla-body, .tla-explore { padding-left: 20px; padding-right: 20px; }
    .tla-stats { gap: 24px; }
    .tla-cta-inner { padding: 56px 24px; }
}
</style>

<main id="main" role="main">

<!-- ══════════ HERO ══════════ -->
<div class="tla-hero">
    <div class="tla-inner">
        <p class="tla-eyebrow">About The Telos</p>
        <h1><?php the_title(); ?></h1>
        <p class="tla-lead">
            Thoughtful summaries and in-depth analyses of the books that shaped human thought —
            philosophy, science, history, and literature, distilled for the curious reader.
        </p>
        <div class="tla-stats">
            <?php if ( $stat_summaries ) : ?>
            <div>
                <div class="tla-stat-num"><?php echo number_format( $stat_summaries ); ?></div>
                <div class="tla-stat-label">Summaries</div>
            </div>
            <?php endif; ?>
            <?php if ( $stat_analyses ) : ?>
            <div>
                <div class="tla-stat-num"><?php echo number_format( $stat_analyses ); ?></div>
                <div class="tla-stat-label">Deep Analyses</div>
            </div>
            <?php endif; ?>
            <?php if ( $stat_authors ) : ?>
            <div>
                <div class="tla-stat-num"><?php echo number_format( $stat_authors ); ?></div>
                <div class="tla-stat-label">Authors</div>
            </div>
            <?php endif; ?>
            <?php if ( $stat_cats ) : ?>
            <div>
                <div class="tla-stat-num"><?php echo number_format( $stat_cats ); ?></div>
                <div class="tla-stat-label">Subjects</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══════════ GÖVDE (mevcut sayfa içeriği) ══════════ -->
<?php
while ( have_posts() ) : the_post();
    $body = trim( get_the_content() );
    if ( $body !== '' ) : ?>
        <div class="tla-body">
            <div class="tla-prose">
                <?php the_content(); ?>
            </div>
        </div>
    <?php endif;
endwhile;
?>

<!-- ══════════ KEŞFET KARTLARI ══════════ -->
<div class="tla-explore">
    <p class="tla-explore-title">What you'll find here</p>
    <div class="tla-cards">

        <a href="<?php echo esc_url( $url_home ); ?>" class="tla-card">
            <div class="tla-card-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/>
                </svg>
            </div>
            <p class="tla-card-name">Book Summaries</p>
            <p class="tla-card-desc">Clear, faithful summaries of landmark works — the core ideas without the noise.</p>
            <span class="tla-card-go">Start reading
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </span>
        </a>

        <?php if ( $url_authors ) : ?>
        <a href="<?php echo esc_url( $url_authors ); ?>" class="tla-card">
            <div class="tla-card-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <p class="tla-card-name">By Author</p>
            <p class="tla-card-desc">Explore the thinkers — from the ancients to the moderns — and everything they wrote.</p>
            <span class="tla-card-go">Browse authors
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </span>
        </a>
        <?php endif; ?>

        <?php if ( $url_categories ) : ?>
        <a href="<?php echo esc_url( $url_categories ); ?>" class="tla-card">
            <div class="tla-card-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
                </svg>
            </div>
            <p class="tla-card-name">By Subject</p>
            <p class="tla-card-desc">Browse by field — philosophy, ethics, science, history, literature and more.</p>
            <span class="tla-card-go">Browse subjects
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </span>
        </a>
        <?php endif; ?>

    </div>
</div>

<!-- ══════════ SON CTA ══════════ -->
<div class="tla-cta">
    <div class="tla-cta-inner">
        <h2>Start reading today</h2>
        <p>Thousands of ideas from the greatest books, distilled and ready whenever you are.</p>
        <div class="tla-btns">
            <a href="<?php echo esc_url( $url_home ); ?>" class="tla-btn tla-btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                Start Reading
            </a>
            <?php if ( $url_authors ) : ?>
            <a href="<?php echo esc_url( $url_authors ); ?>" class="tla-btn tla-btn-ghost">Browse by Author</a>
            <?php endif; ?>
        </div>
    </div>
</div>

</main>

<?php get_footer(); ?>
