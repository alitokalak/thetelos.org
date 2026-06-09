<?php
/**
 * Template Name: Reading Paths
 * The Telos — Curated Reading Paths Page
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$lists = function_exists( 'tls_get_reading_lists' ) ? tls_get_reading_lists( 0 ) : [];
$total = count( $lists );
$ai_nonce   = wp_create_nonce( 'tls_ai_suggest' );
$save_nonce = wp_create_nonce( 'tls_add_list_to_library' );

// Enrich each list with per-book data for JS
$lists_js = [];
foreach ( $lists as $l ) {
    $books_data = [];
    foreach ( $l['books'] as $bid ) {
        if ( ! $p = get_post( $bid ) ) continue;
        $aths = get_the_terms( $bid, 'authors' );
        $auth = ( $aths && ! is_wp_error( $aths ) ) ? $aths[0]->name : '';
        $books_data[] = [ 'id' => $bid, 'title' => $p->post_title, 'author' => $auth ];
    }
    $lists_js[] = array_merge( $l, [ 'books_data' => $books_data ] );
}

// Fallback demo lists when no real data
if ( empty( $lists_js ) ) {
    $lists_js = [
        [
            'id'       => 0,
            'title'    => "Aristotle's Natural Philosophy",
            'subtitle' => 'From Logic to Living Nature',
            'desc'     => 'Navigate the full spectrum of Aristotelian inquiry from logical foundations through metaphysics to empirical natural science.',
            'era'      => 'Classical Greece',
            'color'    => '#7c5c3a',
            'time'     => '34',
            'emoji'    => '⚗️',
            'books'    => [],
            'books_data' => [
                [ 'id' => 0, 'title' => 'Categories',         'author' => 'Aristotle' ],
                [ 'id' => 0, 'title' => 'Prior Analytics',    'author' => 'Aristotle' ],
                [ 'id' => 0, 'title' => 'Physics',            'author' => 'Aristotle' ],
                [ 'id' => 0, 'title' => 'On the Soul',        'author' => 'Aristotle' ],
                [ 'id' => 0, 'title' => 'Metaphysics',        'author' => 'Aristotle' ],
                [ 'id' => 0, 'title' => 'History of Animals', 'author' => 'Aristotle' ],
                [ 'id' => 0, 'title' => 'Parts of Animals',   'author' => 'Aristotle' ],
                [ 'id' => 0, 'title' => 'Posterior Analytics','author' => 'Aristotle' ],
            ],
        ],
        [
            'id'       => 0,
            'title'    => "From Cosmos to Character",
            'subtitle' => 'Ancient Views on Nature and the Good',
            'desc'     => 'Tracing the ancient connections between cosmology and ethics.',
            'era'      => 'Hellenistic',
            'color'    => '#3a5c5c',
            'time'     => '14',
            'emoji'    => '🌌',
            'books'    => [],
            'books_data' => [
                [ 'id' => 0, 'title' => 'On the Nature of Things', 'author' => 'Lucretius' ],
                [ 'id' => 0, 'title' => 'Timaeus',                 'author' => 'Plato' ],
            ],
        ],
        [
            'id'       => 0,
            'title'    => "Plato's Inquiry",
            'subtitle' => 'Knowledge, Virtue, and the Good Life',
            'desc'     => 'A guided journey through the Platonic dialogues in sequence.',
            'era'      => 'Classical Greece',
            'color'    => '#3a4a6b',
            'time'     => '22',
            'emoji'    => '🏛️',
            'books'    => [],
            'books_data' => [
                [ 'id' => 0, 'title' => 'Apology', 'author' => 'Plato' ],
                [ 'id' => 0, 'title' => 'Meno',    'author' => 'Plato' ],
            ],
        ],
        [
            'id'       => 0,
            'title'    => "Ancient Ethics",
            'subtitle' => 'Virtue, Pleasure, and the Good',
            'desc'     => 'Comparing the major schools of ancient moral philosophy.',
            'era'      => 'Classical',
            'color'    => '#5c3a3a',
            'time'     => '18',
            'emoji'    => '⚖️',
            'books'    => [],
            'books_data' => [
                [ 'id' => 0, 'title' => 'Nicomachean Ethics',  'author' => 'Aristotle' ],
                [ 'id' => 0, 'title' => 'Letter to Menoeceus', 'author' => 'Epicurus' ],
            ],
        ],
    ];
    $total = count( $lists_js );
}

get_header();
?>

<style>
/* ───────────────────────────────────────────────
   Reading Paths Page — Light Parchment Theme
   Fonts: IBM Plex Sans (sans) + Source Serif 4 (serif)
   ─────────────────────────────────────────────── */
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=Source+Serif+4:ital,wght@0,300..900;1,300..900&display=swap');

:root {
  --rp-cream:   #f5f1e8;
  --rp-cream2:  #ede9df;
  --rp-ink:     #1c1a16;
  --rp-ink2:    rgba(28,26,22,.55);
  --rp-border:  rgba(28,26,22,.12);
  --rp-gold:    #b5893a;
  --rp-sans:    'IBM Plex Sans', system-ui, -apple-system, sans-serif;
  --rp-serif:   'Source Serif 4', Georgia, serif;
}

.rp-page {
  background: var(--rp-cream);
  color: var(--rp-ink);
  font-family: var(--rp-sans);
}

/* ── Section Head ─────────────────────────────── */
.rp-head {
  border-bottom: 1px solid var(--rp-border);
  padding: 40px 0 32px;
}
.rp-head-inner {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 48px;
}
.rp-head-left {}
.rp-eyebrow {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--rp-ink2);
  margin: 0 0 8px;
}
.rp-eyebrow strong { color: var(--rp-ink); font-weight: 600; }
.rp-head h2 {
  font-family: var(--rp-serif);
  font-size: 2.8rem;
  font-weight: 600;
  line-height: 1.1;
  margin: 0;
  color: var(--rp-ink);
}
.rp-head h2 em { font-style: italic; font-weight: 300; }
.rp-head-lede {
  max-width: 400px;
  font-size: .875rem;
  line-height: 1.65;
  color: var(--rp-ink2);
  margin: 0;
  padding-bottom: 4px;
}

/* ── Hero ─────────────────────────────────────── */
.rp-hero {
  padding: 56px 0 0;
}
.rp-hero-inner {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 64px;
  align-items: start;
}

/* Hero — Text side */
.rp-hero-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}
.rp-path-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: .16em;
  text-transform: uppercase;
  background: var(--rp-ink);
  color: #fff;
  padding: 5px 10px;
  border-radius: 2px;
}
.rp-path-nav {
  display: flex;
  align-items: center;
  gap: 4px;
}
.rp-path-serial {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: .1em;
  color: var(--rp-ink2);
  padding: 0 8px;
}
.rp-nav-btn {
  width: 30px; height: 30px;
  border: 1px solid var(--rp-border);
  background: transparent;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  border-radius: 2px;
  color: var(--rp-ink);
  transition: background .15s, border-color .15s;
}
.rp-nav-btn:hover { background: var(--rp-cream2); border-color: rgba(28,26,22,.25); }
.rp-nav-btn svg { pointer-events: none; }

.rp-hero-title {
  font-family: var(--rp-serif);
  font-size: 2rem;
  font-weight: 600;
  line-height: 1.2;
  margin: 0 0 6px;
  color: var(--rp-ink);
}
.rp-hero-sub {
  font-size: .8125rem;
  font-weight: 500;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--rp-gold);
  margin: 0 0 16px;
}
.rp-hero-desc {
  font-size: .9375rem;
  line-height: 1.65;
  color: var(--rp-ink2);
  margin: 0 0 28px;
  max-width: 540px;
}

/* Numbered TOC list */
.rp-toc {
  list-style: none;
  margin: 0 0 28px;
  padding: 0;
  border-top: 1px solid var(--rp-border);
}
.rp-toc li {
  display: grid;
  grid-template-columns: 28px 1fr auto;
  align-items: baseline;
  gap: 10px;
  padding: 9px 0;
  border-bottom: 1px solid var(--rp-border);
  font-size: .8125rem;
}
.rp-toc .n {
  font-family: 'IBM Plex Mono', monospace;
  font-size: .6875rem;
  color: var(--rp-ink2);
  font-weight: 400;
}
.rp-toc .t {
  font-weight: 500;
  color: var(--rp-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.rp-toc .a {
  font-size: .75rem;
  color: var(--rp-ink2);
  white-space: nowrap;
}

/* CTA row */
.rp-cta {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.rp-btn-solid {
  display: inline-flex; align-items: center; gap: 6px;
  background: var(--rp-ink);
  color: #fff;
  font-family: var(--rp-sans);
  font-size: .8125rem;
  font-weight: 600;
  padding: 10px 18px;
  border: none; border-radius: 2px;
  cursor: pointer;
  letter-spacing: .02em;
  text-decoration: none;
  transition: opacity .15s;
}
.rp-btn-solid:hover { opacity: .82; color: #fff; }
.rp-btn-ghost {
  display: inline-flex; align-items: center; gap: 6px;
  background: transparent;
  color: var(--rp-ink);
  font-family: var(--rp-sans);
  font-size: .8125rem;
  font-weight: 500;
  padding: 9px 17px;
  border: 1px solid rgba(28,26,22,.35);
  border-radius: 2px;
  cursor: pointer;
  letter-spacing: .02em;
  text-decoration: none;
  transition: background .15s;
}
.rp-btn-ghost:hover { background: var(--rp-cream2); }
.rp-btn-ghost.saved { border-color: var(--rp-gold); color: var(--rp-gold); }
.rp-meta-line {
  font-size: .75rem;
  color: var(--rp-ink2);
  letter-spacing: .06em;
  white-space: nowrap;
}

/* Hero — Visual side (book cover stack) */
.rp-hero-visual {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-top: 8px;
}
.rp-peek {
  display: flex;
  gap: 10px;
  margin-bottom: -18px;
  padding: 0 24px;
  z-index: 0;
  position: relative;
}
.rp-peek-cover {
  flex: 1;
  background: #4a3525;
  border-radius: 3px;
  padding: 14px 12px 12px;
  min-height: 90px;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  box-shadow: 0 4px 12px rgba(0,0,0,.18);
  opacity: .7;
}
.rp-peek-cover .ct {
  font-size: .625rem;
  font-weight: 600;
  color: rgba(255,255,255,.9);
  line-height: 1.3;
  margin-bottom: 2px;
}
.rp-peek-cover .ca {
  font-size: .5625rem;
  color: rgba(255,255,255,.55);
}

/* Main path cover card */
.rp-path-cover {
  position: relative;
  z-index: 1;
  width: 100%;
  border-radius: 4px;
  overflow: hidden;
  box-shadow: 0 12px 40px rgba(0,0,0,.22);
  display: flex;
  flex-direction: column;
  min-height: 300px;
  background: var(--book-bg, #7c5c3a);
  transition: background .35s ease;
}
.rp-pc-top {
  padding: 16px 20px 14px;
  border-bottom: 1px solid rgba(255,255,255,.12);
  font-family: 'IBM Plex Mono', monospace;
  font-size: .5625rem;
  letter-spacing: .18em;
  text-transform: uppercase;
  color: rgba(255,255,255,.5);
}
.rp-pc-mid {
  flex: 1;
  padding: 20px 20px 16px;
}
.rp-pc-mid h5 {
  font-family: var(--rp-serif);
  font-size: 1.125rem;
  font-weight: 600;
  color: #fff;
  margin: 0 0 6px;
  line-height: 1.3;
}
.rp-pc-sub {
  font-size: .75rem;
  color: rgba(255,255,255,.6);
  font-style: italic;
  font-family: var(--rp-serif);
}
.rp-pc-rule {
  height: 1px;
  background: rgba(255,255,255,.12);
  margin: 0 20px;
}
.rp-pc-bot {
  padding: 14px 20px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.rp-pc-vol {
  font-family: 'IBM Plex Mono', monospace;
  font-size: .625rem;
  color: rgba(255,255,255,.45);
  letter-spacing: .1em;
}
.rp-pc-mark {
  font-family: var(--rp-serif);
  font-size: .75rem;
  font-style: italic;
  color: rgba(255,255,255,.35);
}

/* ── Below: More Paths ────────────────────────── */
.rp-below {
  padding: 56px 0 64px;
}
.rp-below-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--rp-border);
}
.rp-below-head h3 {
  font-family: var(--rp-serif);
  font-size: 1.125rem;
  font-weight: 500;
  margin: 0;
  color: var(--rp-ink);
}
.rp-view-all {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: .8125rem;
  font-weight: 500;
  color: var(--rp-ink);
  text-decoration: none;
  cursor: pointer;
  border: none; background: none; padding: 0;
}
.rp-view-all:hover { color: var(--rp-gold); }

.rp-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
@media (max-width: 1100px) { .rp-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 780px)  { .rp-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 500px)  { .rp-grid { grid-template-columns: 1fr; } }

.rp-card {
  background: #fff;
  border: 1px solid var(--rp-border);
  border-radius: 4px;
  overflow: hidden;
  cursor: pointer;
  transition: box-shadow .2s, transform .2s;
}
.rp-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.1); transform: translateY(-2px); }
.rp-card.active { outline: 2px solid var(--rp-ink); outline-offset: -1px; }

.rp-ministack {
  display: flex;
  gap: 6px;
  padding: 14px 14px 10px;
  background: var(--rp-cream2);
}
.rp-mini-cover {
  flex: 1;
  border-radius: 2px;
  padding: 10px 8px 8px;
  min-height: 60px;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  background: var(--book-bg, #7c5c3a);
}
.rp-mini-cover .ct {
  font-size: .5rem;
  font-weight: 600;
  color: rgba(255,255,255,.9);
  line-height: 1.3;
  margin-bottom: 2px;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
.rp-mini-cover .ca {
  font-size: .4375rem;
  color: rgba(255,255,255,.55);
}
.rp-card-body {
  padding: 12px 14px 14px;
}
.rp-card-body h5 {
  font-family: var(--rp-serif);
  font-size: .9375rem;
  font-weight: 600;
  margin: 0 0 4px;
  line-height: 1.3;
  color: var(--rp-ink);
}
.rp-card-meta {
  font-size: .6875rem;
  color: var(--rp-ink2);
  font-family: 'IBM Plex Mono', monospace;
  letter-spacing: .06em;
}

/* ── AI Suggest ───────────────────────────────── */
.rp-ai-section {
  background: var(--rp-ink);
  color: #fff;
  padding: 64px 0;
}
.rp-ai-inner {
  max-width: 680px;
}
.rp-ai-eyebrow {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: .18em;
  text-transform: uppercase;
  color: var(--rp-gold);
  margin: 0 0 12px;
}
.rp-ai-title {
  font-family: var(--rp-serif);
  font-size: 1.75rem;
  font-weight: 600;
  margin: 0 0 10px;
  line-height: 1.25;
}
.rp-ai-title em { font-style: italic; font-weight: 300; }
.rp-ai-desc {
  font-size: .9rem;
  line-height: 1.65;
  color: rgba(255,255,255,.55);
  margin: 0 0 28px;
}
.rp-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 24px;
}
.rp-chip {
  padding: 6px 13px;
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 100px;
  font-size: .75rem;
  background: transparent;
  color: rgba(255,255,255,.7);
  cursor: pointer;
  transition: background .15s, border-color .15s, color .15s;
  font-family: var(--rp-sans);
}
.rp-chip:hover, .rp-chip.active {
  background: rgba(255,255,255,.1);
  border-color: rgba(255,255,255,.4);
  color: #fff;
}
.rp-ai-form {
  display: flex;
  gap: 8px;
  margin-bottom: 32px;
}
.rp-ai-input {
  flex: 1;
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.18);
  color: #fff;
  padding: 11px 14px;
  font-family: var(--rp-sans);
  font-size: .9rem;
  border-radius: 2px;
  outline: none;
  transition: border-color .15s;
}
.rp-ai-input::placeholder { color: rgba(255,255,255,.35); }
.rp-ai-input:focus { border-color: rgba(255,255,255,.4); }
.rp-ai-submit {
  padding: 11px 20px;
  background: var(--rp-gold);
  color: #fff;
  font-family: var(--rp-sans);
  font-size: .8125rem;
  font-weight: 600;
  border: none;
  border-radius: 2px;
  cursor: pointer;
  white-space: nowrap;
  transition: opacity .15s;
}
.rp-ai-submit:hover:not(:disabled) { opacity: .85; }
.rp-ai-submit:disabled { opacity: .5; cursor: wait; }

.rp-ai-results {
  display: none;
}
.rp-ai-results.visible { display: block; }
.rp-ai-intro {
  font-size: .875rem;
  line-height: 1.65;
  color: rgba(255,255,255,.65);
  margin: 0 0 20px;
  padding: 16px;
  border-left: 2px solid var(--rp-gold);
  background: rgba(255,255,255,.04);
}
.rp-ai-books {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 12px;
}
.rp-ai-book {
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 3px;
  padding: 14px 12px 12px;
  text-decoration: none;
  transition: background .15s;
  display: block;
}
.rp-ai-book:hover { background: rgba(255,255,255,.1); }
.rp-ai-book-cover {
  width: 100%;
  aspect-ratio: 2/3;
  border-radius: 2px;
  margin-bottom: 10px;
  object-fit: cover;
  background: rgba(255,255,255,.08);
}
.rp-ai-book-title {
  font-size: .75rem;
  font-weight: 600;
  color: rgba(255,255,255,.9);
  margin: 0 0 2px;
  line-height: 1.3;
}
.rp-ai-book-author {
  font-size: .6875rem;
  color: rgba(255,255,255,.45);
}
.rp-ai-empty {
  font-size: .875rem;
  color: rgba(255,255,255,.45);
  text-align: center;
  padding: 32px 0;
}
.rp-ai-loading {
  display: none;
  font-size: .875rem;
  color: rgba(255,255,255,.45);
  margin-bottom: 16px;
}
.rp-ai-loading.visible { display: block; }

/* ── Responsive ───────────────────────────────── */
@media (max-width: 900px) {
  .rp-head-inner { flex-direction: column; align-items: flex-start; gap: 12px; }
  .rp-head-lede { max-width: 100%; }
  .rp-hero-inner { grid-template-columns: 1fr; }
  .rp-hero-visual { order: -1; max-width: 340px; margin: 0 auto; width: 100%; }
  .rp-head h2 { font-size: 2.2rem; }
}
@media (max-width: 600px) {
  .rp-head h2 { font-size: 1.8rem; }
  .rp-hero-title { font-size: 1.5rem; }
  .rp-cta { gap: 8px; }
}
</style>

<div class="rp-page">
  <div class="container">

    <?php /* ── Section Head ─── */ ?>
    <div class="rp-head">
      <div class="rp-head-inner">
        <div class="rp-head-left">
          <p class="rp-eyebrow">Curated Sequences · <strong><?php echo $total; ?> paths</strong></p>
          <h2>Reading <em>Paths</em></h2>
        </div>
        <p class="rp-head-lede">Hand-curated sequences that build understanding step by step — from foundational texts to advanced inquiry.</p>
      </div>
    </div>

    <?php /* ── Hero (Featured Path) ─── */ if ( ! empty( $lists_js ) ) : $first = $lists_js[0]; ?>
    <div class="rp-hero">
      <div class="rp-hero-inner">

        <?php /* Left: text */ ?>
        <div class="rp-hero-text">
          <div class="rp-hero-top">
            <span class="rp-path-badge">
              <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M1 5h8M5 1l4 4-4 4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Featured Path
            </span>
            <div class="rp-path-nav">
              <button class="rp-nav-btn" id="rp-prev" aria-label="Previous path">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M7 2L3.5 6 7 10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
              <span class="rp-path-serial" id="rp-serial">PATH 01 / <?php echo $total; ?></span>
              <button class="rp-nav-btn" id="rp-next" aria-label="Next path">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M5 2L8.5 6 5 10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
            </div>
          </div>

          <h3 class="rp-hero-title" id="rp-title"><?php echo esc_html( $first['title'] ); ?></h3>
          <p class="rp-hero-sub" id="rp-sub"><?php echo esc_html( $first['subtitle'] ?? '' ); ?></p>
          <p class="rp-hero-desc" id="rp-desc"><?php echo esc_html( $first['desc'] ?? '' ); ?></p>

          <ol class="rp-toc" id="rp-toc">
            <?php foreach ( array_slice( $first['books_data'], 0, 8 ) as $i => $b ) : ?>
            <li>
              <span class="n"><?php printf( '%02d', $i + 1 ); ?></span>
              <span class="t"><?php echo esc_html( $b['title'] ); ?></span>
              <span class="a"><?php echo esc_html( $b['author'] ); ?></span>
            </li>
            <?php endforeach; ?>
          </ol>

          <div class="rp-cta">
            <a href="#" class="rp-btn-solid" id="rp-begin-btn">
              <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6h8m0 0L6.5 2.5M10 6L6.5 9.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Begin Path
            </a>
            <button class="rp-btn-ghost" id="rp-save-btn" data-list-id="<?php echo esc_attr( $first['id'] ); ?>">
              <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M6 1.5v9M1.5 6h9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
              Add to Library
            </button>
            <span class="rp-meta-line" id="rp-meta">
              ~<?php echo esc_html( $first['time'] ?? '?' ); ?>H
              · <?php echo count( $first['books_data'] ); ?> VOL.
              <?php if ( ! empty( $first['era'] ) ) echo ' · ' . esc_html( strtoupper( $first['era'] ) ); ?>
            </span>
          </div>
        </div><!-- /hero-text -->

        <?php /* Right: book cover visual */ ?>
        <div class="rp-hero-visual">
          <div class="rp-peek" id="rp-peek">
            <?php
            $peek_books = array_slice( $first['books_data'], 1, 2 );
            foreach ( $peek_books as $pb ) :
            ?>
            <div class="rp-peek-cover" style="background:<?php echo esc_attr( $first['color'] ?? '#7c5c3a' ); ?>;">
              <div class="ct"><?php echo esc_html( $pb['title'] ); ?></div>
              <div class="ca"><?php echo esc_html( $pb['author'] ); ?></div>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="rp-path-cover" id="rp-cover" style="--book-bg:<?php echo esc_attr( $first['color'] ?? '#7c5c3a' ); ?>;">
            <div class="rp-pc-top" id="rp-cover-top">PATH 01 · The Telos Library</div>
            <div class="rp-pc-mid">
              <h5 id="rp-cover-title"><?php echo esc_html( $first['title'] ); ?></h5>
              <div class="rp-pc-sub" id="rp-cover-sub"><?php echo esc_html( $first['subtitle'] ?? '' ); ?></div>
            </div>
            <div class="rp-pc-rule"></div>
            <div class="rp-pc-bot">
              <div class="rp-pc-vol" id="rp-cover-vol">
                <?php echo count( $first['books_data'] ); ?> Volumes · ~<?php echo esc_html( $first['time'] ?? '?' ); ?>h
              </div>
              <div class="rp-pc-mark">τέλος</div>
            </div>
          </div>
        </div><!-- /hero-visual -->

      </div><!-- /hero-inner -->
    </div><!-- /hero -->
    <?php endif; ?>

    <?php /* ── More Paths Grid ─── */ if ( count( $lists_js ) > 1 ) : ?>
    <div class="rp-below">
      <div class="rp-below-head">
        <h3>More Reading Paths</h3>
        <button class="rp-view-all" id="rp-view-all-btn">
          View all
          <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6h8m0 0L6.5 2.5M10 6L6.5 9.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </div>
      <div class="rp-grid" id="rp-grid">
        <?php foreach ( $lists_js as $idx => $l ) : ?>
        <div class="rp-card<?php echo $idx === 0 ? ' active' : ''; ?>" data-idx="<?php echo $idx; ?>" tabindex="0" role="button" aria-label="<?php echo esc_attr( $l['title'] ); ?>">
          <div class="rp-ministack">
            <?php
            $show_books = ! empty( $l['books_data'] ) ? array_slice( $l['books_data'], 0, 2 ) : [['title'=>'','author'=>''],['title'=>'','author'=>'']];
            foreach ( $show_books as $sb ) :
            ?>
            <div class="rp-mini-cover" style="--book-bg:<?php echo esc_attr( $l['color'] ?? '#7c5c3a' ); ?>;">
              <div class="ct"><?php echo esc_html( $sb['title'] ); ?></div>
              <div class="ca"><?php echo esc_html( $sb['author'] ); ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="rp-card-body">
            <h5><?php echo esc_html( $l['title'] ); ?></h5>
            <div class="rp-card-meta">
              <?php echo count( $l['books_data'] ); ?> VOL
              <?php if ( ! empty( $l['time'] ) ) echo ' · ~' . esc_html( $l['time'] ) . 'H'; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /container -->

  <?php /* ── AI Suggest ─── */ ?>
  <div class="rp-ai-section">
    <div class="container">
      <div class="rp-ai-inner">
        <p class="rp-ai-eyebrow">AI-Powered · Personalized</p>
        <h2 class="rp-ai-title">Build Your Own<br><em>Reading Path</em></h2>
        <p class="rp-ai-desc">Type a philosophical topic and we'll search our archive to build a curated reading sequence just for you.</p>

        <div class="rp-chips" id="rp-chips">
          <button class="rp-chip" data-topic="Problem of evil">Problem of evil</button>
          <button class="rp-chip" data-topic="Stoic philosophy">Stoic philosophy</button>
          <button class="rp-chip" data-topic="Political theory">Political theory</button>
          <button class="rp-chip" data-topic="Theory of knowledge">Theory of knowledge</button>
          <button class="rp-chip" data-topic="Free will">Free will</button>
          <button class="rp-chip" data-topic="Nature of reality">Nature of reality</button>
          <button class="rp-chip" data-topic="Ethics &amp; virtue">Ethics &amp; virtue</button>
          <button class="rp-chip" data-topic="Mind &amp; consciousness">Mind &amp; consciousness</button>
        </div>

        <div class="rp-ai-form">
          <input type="text" class="rp-ai-input" id="rp-ai-input" placeholder="e.g. free will, theory of knowledge, political philosophy…">
          <button class="rp-ai-submit" id="rp-ai-submit">Suggest Path</button>
        </div>

        <p class="rp-ai-loading" id="rp-ai-loading">Searching the archive…</p>
        <div class="rp-ai-results" id="rp-ai-results">
          <p class="rp-ai-intro" id="rp-ai-intro"></p>
          <div class="rp-ai-books" id="rp-ai-books"></div>
        </div>
      </div>
    </div>
  </div>

</div><!-- /rp-page -->

<script>
(function () {
  const LISTS  = <?php echo json_encode( array_values( $lists_js ), JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
  const TOTAL  = LISTS.length;
  const AJURL  = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
  const SAVENONCE = <?php echo json_encode( $save_nonce ); ?>;
  const AINONCE   = <?php echo json_encode( $ai_nonce ); ?>;

  let cur = 0;

  /* ── Featured path update ─── */
  function updateFeatured(idx) {
    if (!LISTS[idx]) return;
    const l = LISTS[idx];
    cur = idx;

    // Text side
    document.getElementById('rp-serial').textContent = 'PATH ' + pad(idx+1) + ' / ' + pad(TOTAL);
    document.getElementById('rp-title').textContent  = l.title;
    document.getElementById('rp-sub').textContent    = l.subtitle || '';
    document.getElementById('rp-desc').textContent   = l.desc     || '';

    // TOC
    const toc   = document.getElementById('rp-toc');
    const books = (l.books_data || []).slice(0, 8);
    toc.innerHTML = books.map((b, i) =>
      `<li><span class="n">${pad(i+1)}</span><span class="t">${esc(b.title)}</span><span class="a">${esc(b.author)}</span></li>`
    ).join('');

    // Meta
    document.getElementById('rp-meta').textContent =
      '~' + (l.time||'?') + 'H · ' + books.length + ' VOL.' + (l.era ? ' · ' + l.era.toUpperCase() : '');

    // Cover card
    const cover = document.getElementById('rp-cover');
    cover.style.setProperty('--book-bg', l.color || '#7c5c3a');
    document.getElementById('rp-cover-top').textContent   = 'PATH ' + pad(idx+1) + ' · The Telos Library';
    document.getElementById('rp-cover-title').textContent = l.title;
    document.getElementById('rp-cover-sub').textContent   = l.subtitle || '';
    document.getElementById('rp-cover-vol').textContent   = books.length + ' Volumes · ~' + (l.time||'?') + 'h';

    // Peek books
    const peek  = document.getElementById('rp-peek');
    const peekB = (l.books_data || []).slice(1, 3);
    peek.innerHTML = peekB.map(b =>
      `<div class="rp-peek-cover" style="background:${l.color||'#7c5c3a'}">
        <div class="ct">${esc(b.title)}</div>
        <div class="ca">${esc(b.author)}</div>
       </div>`
    ).join('');

    // Save button
    const saveBtn = document.getElementById('rp-save-btn');
    if (saveBtn) saveBtn.dataset.listId = l.id || 0;

    // Begin path link
    const beginBtn = document.getElementById('rp-begin-btn');
    if (beginBtn && l.id) beginBtn.href = '/?post_type=tls_reading_list&p=' + l.id;

    // Grid active state
    document.querySelectorAll('.rp-card').forEach((c, i) => {
      c.classList.toggle('active', i === idx);
    });
  }

  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  /* ── Nav buttons ─── */
  document.getElementById('rp-prev')?.addEventListener('click', () => updateFeatured((cur - 1 + TOTAL) % TOTAL));
  document.getElementById('rp-next')?.addEventListener('click', () => updateFeatured((cur + 1) % TOTAL));

  /* ── Grid cards ─── */
  document.getElementById('rp-grid')?.addEventListener('click', function(e) {
    const card = e.target.closest('.rp-card');
    if (!card) return;
    const idx = parseInt(card.dataset.idx, 10);
    updateFeatured(idx);
    document.querySelector('.rp-hero')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  /* ── Save to library ─── */
  document.getElementById('rp-save-btn')?.addEventListener('click', function() {
    const btn    = this;
    const listId = btn.dataset.listId;
    if (!listId || listId === '0') return;

    btn.disabled = true;
    const fd = new FormData();
    fd.append('action',  'tls_add_list_to_library');
    fd.append('nonce',   SAVENONCE);
    fd.append('list_id', listId);

    fetch(AJURL, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          const saved = res.data?.saved;
          btn.classList.toggle('saved', !!saved);
          btn.innerHTML = saved
            ? '<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg> Saved'
            : '<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M6 1.5v9M1.5 6h9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg> Add to Library';
        }
      })
      .finally(() => { btn.disabled = false; });
  });

  /* ── AI Suggest ─── */
  function doSuggest(topic) {
    const input   = document.getElementById('rp-ai-input');
    const submit  = document.getElementById('rp-ai-submit');
    const loading = document.getElementById('rp-ai-loading');
    const results = document.getElementById('rp-ai-results');
    const intro   = document.getElementById('rp-ai-intro');
    const booksEl = document.getElementById('rp-ai-books');

    if (!topic || !topic.trim()) return;
    input.value = topic;
    submit.disabled = true;
    loading.classList.add('visible');
    results.classList.remove('visible');

    document.querySelectorAll('.rp-chip').forEach(c => c.classList.toggle('active', c.dataset.topic === topic));

    const fd = new FormData();
    fd.append('action', 'tls_ai_suggest_path');
    fd.append('nonce',  AINONCE);
    fd.append('topic',  topic);

    fetch(AJURL, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        loading.classList.remove('visible');
        results.classList.add('visible');
        if (!res.success) {
          intro.textContent = res.data || 'No results found. Try a different topic.';
          booksEl.innerHTML = '';
          return;
        }
        const data = res.data;
        intro.textContent = data.intro || '';
        intro.style.display = data.intro ? '' : 'none';
        booksEl.innerHTML = renderBooks(data.books || []);
      })
      .catch(() => {
        loading.classList.remove('visible');
        results.classList.add('visible');
        intro.textContent = 'Something went wrong. Please try again.';
        booksEl.innerHTML = '';
      })
      .finally(() => { submit.disabled = false; });
  }

  function renderBooks(books) {
    if (!books.length) return '<p class="rp-ai-empty">No matching books found in the archive.</p>';
    return books.map(b => {
      const cover = b.cover
        ? `<img class="rp-ai-book-cover" src="${esc(b.cover)}" alt="${esc(b.title)}">`
        : `<div class="rp-ai-book-cover" style="background:rgba(255,255,255,.06);border-radius:2px;"></div>`;
      const href = b.url || '#';
      return `<a class="rp-ai-book" href="${esc(href)}" ${href==='#'?'':'target="_blank" rel="noopener"'}>
        ${cover}
        <div class="rp-ai-book-title">${esc(b.title)}</div>
        <div class="rp-ai-book-author">${esc(b.author||'')}</div>
      </a>`;
    }).join('');
  }

  document.getElementById('rp-ai-submit')?.addEventListener('click', () => {
    doSuggest(document.getElementById('rp-ai-input').value.trim());
  });
  document.getElementById('rp-ai-input')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') doSuggest(e.target.value.trim());
  });
  document.querySelectorAll('.rp-chip').forEach(chip => {
    chip.addEventListener('click', () => doSuggest(chip.dataset.topic));
  });

  /* init */
  if (TOTAL > 0) updateFeatured(0);
})();
</script>

<?php get_footer(); ?>
