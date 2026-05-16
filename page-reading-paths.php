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
$ai_nonce = wp_create_nonce( 'tls_ai_suggest' );

// Enrich each list with per-book data for JS
$lists_js = [];
foreach ( $lists as $l ) {
    $books_data = [];
    foreach ( $l['books'] as $bid ) {
        if ( ! $p = get_post( $bid ) ) continue;
        $aths = get_the_terms( $bid, 'authors' );
        $auth = ( $aths && ! is_wp_error($aths) ) ? $aths[0]->name : '';
        $books_data[] = [ 'id' => $bid, 'title' => $p->post_title, 'author' => $auth ];
    }
    $lists_js[] = array_merge( $l, [ 'books_data' => $books_data ] );
}

get_header();
?>

<style>
/* ── Reading Paths Page ─────────────────────────── */
.tls-rp-page { background: var(--tls-bg); min-height: 80vh; }

/* Page header */
.tls-rp-header {
  background: var(--tls-bg-dark);
  padding: 56px 0 48px;
  border-bottom: 1px solid rgba(255,255,255,.06);
}
.tls-rp-header-inner {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: end;
  gap: 24px;
}
.tls-rp-eyebrow {
  font-family: var(--tls-sans);
  font-size: 10px; letter-spacing: .22em; text-transform: uppercase;
  color: var(--tls-gold); margin: 0 0 10px;
}
.tls-rp-page-title {
  font-family: var(--tls-serif);
  font-size: clamp(36px,5vw,56px); font-weight: 400;
  color: #fff; margin: 0; line-height: 1.1;
}
.tls-rp-page-title em {
  font-style: italic; color: var(--tls-gold-light);
}
.tls-rp-page-desc {
  font-family: var(--tls-sans); font-size: 15px;
  color: rgba(255,255,255,.42); margin: 14px 0 0;
  max-width: 520px; line-height: 1.7;
}
.tls-rp-counter {
  font-family: var(--tls-serif); font-size: 48px; font-weight: 400;
  color: rgba(255,255,255,.08); line-height: 1; text-align: right;
  white-space: nowrap;
}
.tls-rp-counter span {
  display: block; font-size: 11px; font-family: var(--tls-sans);
  letter-spacing: .18em; text-transform: uppercase;
  color: rgba(255,255,255,.3); margin-top: 4px;
}

/* ── Featured Path (split) ─────────────────────── */
.tls-rp-featured {
  background: var(--tls-bg-dark);
  padding: 0 0 72px;
}
.tls-rp-featured-inner {
  display: grid;
  grid-template-columns: 1fr 420px;
  gap: 64px;
  align-items: start;
}
.tls-rp-path-label {
  display: flex; align-items: center; gap: 20px;
  margin-bottom: 28px;
}
.tls-rp-path-badge {
  font-family: var(--tls-sans); font-size: 10px;
  letter-spacing: .2em; text-transform: uppercase;
  color: var(--tls-gold); background: rgba(184,146,42,.1);
  border: 1px solid rgba(184,146,42,.25); border-radius: 20px;
  padding: 5px 14px;
}
.tls-rp-path-nav {
  display: flex; align-items: center; gap: 6px; margin-left: auto;
}
.tls-rp-nav-btn {
  width: 34px; height: 34px; border-radius: 50%;
  background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
  color: rgba(255,255,255,.5); font-size: 16px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all .15s; flex-shrink: 0;
}
.tls-rp-nav-btn:hover { background: rgba(255,255,255,.12); color: #fff; }
.tls-rp-path-progress {
  font-family: var(--tls-sans); font-size: 11px;
  color: rgba(255,255,255,.35); letter-spacing: .08em; white-space: nowrap;
}

.tls-rp-featured-title {
  font-family: var(--tls-serif);
  font-size: clamp(28px,4vw,48px); font-weight: 400;
  color: #fff; margin: 0 0 8px; line-height: 1.15;
}
.tls-rp-featured-subtitle {
  font-family: var(--tls-serif); font-style: italic;
  font-size: 18px; color: var(--tls-gold-light);
  margin: 0 0 20px; line-height: 1.4;
}
.tls-rp-featured-desc {
  font-family: var(--tls-sans); font-size: 14px;
  color: rgba(255,255,255,.48); line-height: 1.75;
  margin: 0 0 32px; max-width: 520px;
}
.tls-rp-divider {
  height: 1px; background: rgba(255,255,255,.08); margin-bottom: 28px;
}

/* Book list */
.tls-rp-book-list {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 0; margin: 0 0 32px; list-style: none; padding: 0;
}
.tls-rp-book-item {
  display: flex; align-items: baseline; gap: 8px;
  padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,.06);
}
.tls-rp-book-num {
  font-family: var(--tls-sans); font-size: 10px;
  color: rgba(255,255,255,.25); min-width: 22px;
  font-weight: 600; letter-spacing: .04em; flex-shrink: 0;
}
.tls-rp-book-name {
  font-family: var(--tls-serif); font-style: italic;
  font-size: 13px; color: rgba(255,255,255,.75);
  display: block; line-height: 1.35;
}
.tls-rp-book-auth {
  font-family: var(--tls-sans); font-size: 11px;
  color: rgba(255,255,255,.3); white-space: nowrap;
  flex-shrink: 0;
}

/* Actions */
.tls-rp-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
.tls-rp-btn-primary {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 13px 28px; background: #fff; color: var(--tls-bg-dark);
  border: none; border-radius: 6px; font-family: var(--tls-sans);
  font-size: 13px; font-weight: 700; cursor: pointer;
  text-decoration: none; transition: all .15s; white-space: nowrap;
}
.tls-rp-btn-primary:hover { background: var(--tls-gold); color: #fff; }
.tls-rp-btn-outline {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 12px 24px;
  background: transparent; border: 1px solid rgba(255,255,255,.2);
  color: rgba(255,255,255,.7); border-radius: 6px;
  font-family: var(--tls-sans); font-size: 13px; font-weight: 600;
  cursor: pointer; transition: all .15s; white-space: nowrap;
}
.tls-rp-btn-outline:hover { border-color: #fff; color: #fff; }
.tls-rp-btn-outline.saved { border-color: var(--tls-gold); color: var(--tls-gold); }
.tls-rp-meta-strip {
  font-family: var(--tls-sans); font-size: 11px;
  color: rgba(255,255,255,.3); letter-spacing: .1em;
  text-transform: uppercase;
}
.tls-rp-meta-strip span { margin: 0 6px; opacity: .4; }

/* ── CSS 3D Book Cover ──────────────────────────── */
.tls-rp-bookwrap {
  display: flex; align-items: flex-start; justify-content: center;
  padding-top: 12px;
}
.tls-rp-bookstack {
  position: relative; width: 300px; height: 440px;
}

/* Back books (decorative) */
.tls-rp-book-back2 {
  position: absolute; top: 30px; left: 60px;
  width: 200px; height: 290px;
  border-radius: 2px 5px 5px 2px;
  transform: rotate(3deg) translateX(8px);
  box-shadow: 4px 8px 28px rgba(0,0,0,.45);
  background: #3a1a1a;
}
.tls-rp-book-back1 {
  position: absolute; top: 18px; left: 48px;
  width: 208px; height: 300px;
  border-radius: 2px 5px 5px 2px;
  transform: rotate(1.5deg) translateX(4px);
  box-shadow: 4px 8px 24px rgba(0,0,0,.4);
  background: #5a2a20;
}

/* Main book */
.tls-rp-book-main {
  position: absolute; top: 0; left: 20px;
  width: 220px; height: 314px;
  border-radius: 2px 5px 5px 2px;
  transform: rotate(-1deg);
  box-shadow:
    6px 12px 40px rgba(0,0,0,.55),
    1px 0 0 rgba(255,255,255,.04) inset;
  display: flex; flex-direction: column;
  overflow: hidden;
  background: var(--book-bg, #1A3A2B);
  transition: background .4s;
}
/* Spine shadow */
.tls-rp-book-main::before {
  content: '';
  position: absolute; left: 0; top: 0; bottom: 0; width: 22px;
  background: linear-gradient(to right, rgba(0,0,0,.35) 0%, transparent 100%);
  pointer-events: none;
}
/* Top gold line */
.tls-rp-book-main::after {
  content: '';
  position: absolute; top: 0; left: 22px; right: 0; height: 1px;
  background: rgba(184,146,42,.4);
}

.tls-rp-book-content {
  padding: 22px 20px 18px 28px;
  display: flex; flex-direction: column; height: 100%;
  position: relative; z-index: 1;
}
.tls-rp-book-content-label {
  font-family: var(--tls-sans); font-size: 7.5px;
  letter-spacing: .18em; text-transform: uppercase;
  color: var(--book-accent, var(--tls-gold)); opacity: .75;
  margin-bottom: 10px;
}
/* Inner border */
.tls-rp-book-border {
  position: absolute; inset: 12px 12px 12px 20px;
  border: 1px solid rgba(255,255,255,.1); pointer-events: none;
  border-radius: 1px;
}
.tls-rp-book-main-title {
  font-family: var(--tls-serif); font-weight: 400;
  font-size: 17px; line-height: 1.3; color: #fff;
  margin: 0 0 6px; flex: 1;
}
.tls-rp-book-main-sub {
  font-family: var(--tls-serif); font-style: italic;
  font-size: 11px; color: rgba(255,255,255,.45);
  margin-bottom: 16px; line-height: 1.4;
}
.tls-rp-book-footer {
  display: flex; justify-content: space-between; align-items: flex-end;
  border-top: 1px solid rgba(255,255,255,.1); padding-top: 10px;
}
.tls-rp-book-footer-meta {
  font-family: var(--tls-sans); font-size: 7px;
  letter-spacing: .12em; text-transform: uppercase;
  color: rgba(255,255,255,.4); line-height: 1.5;
}
.tls-rp-book-logo {
  font-family: var(--tls-serif); font-style: italic;
  font-size: 14px; color: var(--book-accent, var(--tls-gold)); opacity: .6;
}

/* ── AI Suggest Section ─────────────────────────── */
.tls-rp-ai-section {
  padding: 80px 0;
  background: var(--tls-bg);
  border-top: 1px solid var(--tls-border);
}
.tls-rp-ai-inner { max-width: 720px; margin: 0 auto; text-align: center; }
.tls-rp-ai-eyebrow {
  font-family: var(--tls-sans); font-size: 10px;
  letter-spacing: .22em; text-transform: uppercase;
  color: var(--tls-gold); margin: 0 0 12px;
}
.tls-rp-ai-title {
  font-family: var(--tls-serif);
  font-size: clamp(26px,3.5vw,38px); font-weight: 400;
  color: var(--tls-bg-dark); margin: 0 0 12px; line-height: 1.2;
}
.tls-rp-ai-title em { font-style: italic; color: var(--tls-gold); }
.tls-rp-ai-desc {
  font-family: var(--tls-sans); font-size: 14px;
  color: var(--tls-muted); margin: 0 0 36px; line-height: 1.7;
}
.tls-rp-ai-form {
  display: flex; gap: 0; max-width: 560px; margin: 0 auto 16px;
  background: #fff; border: 1.5px solid var(--tls-border);
  border-radius: 8px; overflow: hidden;
  box-shadow: var(--tls-shadow);
  transition: border-color .2s, box-shadow .2s;
}
.tls-rp-ai-form:focus-within {
  border-color: var(--tls-gold); box-shadow: 0 0 0 3px rgba(184,146,42,.12);
}
.tls-rp-ai-input {
  flex: 1; padding: 16px 20px; border: none; outline: none;
  font-family: var(--tls-sans); font-size: 15px;
  color: var(--tls-bg-dark); background: transparent;
  min-width: 0;
}
.tls-rp-ai-input::placeholder { color: rgba(0,0,0,.3); }
.tls-rp-ai-submit {
  padding: 14px 24px; background: var(--tls-bg-dark); border: none;
  color: #fff; font-family: var(--tls-sans); font-size: 13px;
  font-weight: 700; cursor: pointer; letter-spacing: .06em;
  text-transform: uppercase; white-space: nowrap;
  display: flex; align-items: center; gap: 8px; transition: background .15s;
  flex-shrink: 0;
}
.tls-rp-ai-submit:hover { background: var(--tls-gold); }
.tls-rp-ai-submit svg { transition: transform .3s; }
.tls-rp-ai-submit.loading svg { animation: tlsRPSpin .7s linear infinite; }

.tls-rp-topic-chips {
  display: flex; flex-wrap: wrap; gap: 8px; justify-content: center;
  margin-bottom: 40px;
}
.tls-rp-chip {
  padding: 6px 16px; border-radius: 20px;
  border: 1px solid var(--tls-border); background: #fff;
  font-family: var(--tls-sans); font-size: 12px;
  color: var(--tls-muted); cursor: pointer;
  transition: all .15s;
}
.tls-rp-chip:hover { border-color: var(--tls-gold); color: var(--tls-gold); }

/* AI Results */
.tls-rp-ai-results { margin-top: 48px; text-align: left; }
.tls-rp-ai-results-header {
  text-align: center; margin-bottom: 28px;
}
.tls-rp-ai-results-label {
  font-family: var(--tls-sans); font-size: 10px;
  letter-spacing: .2em; text-transform: uppercase;
  color: var(--tls-muted); margin: 0 0 8px;
}
.tls-rp-ai-results-title {
  font-family: var(--tls-serif); font-size: 22px;
  font-weight: 400; color: var(--tls-bg-dark); margin: 0 0 12px;
}
.tls-rp-ai-intro {
  font-family: var(--tls-sans); font-size: 14px;
  color: var(--tls-muted); max-width: 560px; margin: 0 auto 32px;
  line-height: 1.7; font-style: italic;
}
.tls-rp-ai-books {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 12px;
}
.tls-rp-ai-book {
  display: flex; gap: 14px; align-items: flex-start;
  background: #fff; border: 1px solid var(--tls-border);
  border-radius: 10px; padding: 14px;
  text-decoration: none;
  transition: box-shadow .2s, transform .15s;
}
.tls-rp-ai-book:hover { box-shadow: var(--tls-shadow); transform: translateY(-1px); }
.tls-rp-ai-book-cover {
  width: 48px; height: 68px; flex-shrink: 0;
  border-radius: 3px; object-fit: cover;
  box-shadow: 0 4px 12px rgba(0,0,0,.15);
}
.tls-rp-ai-book-ph {
  width: 48px; height: 68px; flex-shrink: 0;
  border-radius: 3px; display: flex; align-items: center; justify-content: center;
  font-family: var(--tls-serif); font-size: 16px;
  color: rgba(255,255,255,.5);
}
.tls-rp-ai-book-body { flex: 1; min-width: 0; }
.tls-rp-ai-book-cat {
  font-family: var(--tls-sans); font-size: 9px;
  letter-spacing: .12em; text-transform: uppercase;
  color: var(--tls-gold); margin-bottom: 4px; display: block;
}
.tls-rp-ai-book-title {
  font-family: var(--tls-serif); font-size: 14px;
  color: var(--tls-bg-dark); line-height: 1.3;
  display: block; margin-bottom: 3px;
}
.tls-rp-ai-book-author {
  font-family: var(--tls-sans); font-style: italic;
  font-size: 11px; color: var(--tls-muted);
  display: block; margin-bottom: 5px;
}
.tls-rp-ai-book-excerpt {
  font-family: var(--tls-sans); font-size: 11px;
  color: var(--tls-muted); line-height: 1.5;
}
.tls-rp-ai-save-btn {
  margin-top: 28px; text-align: center;
}

/* AI States */
.tls-rp-ai-empty {
  text-align: center; padding: 48px 24px;
  font-family: var(--tls-sans); font-size: 14px; color: var(--tls-muted);
}

/* ── All Paths Grid ─────────────────────────────── */
.tls-rp-grid-section {
  padding: 72px 0 80px;
  background: var(--tls-bg);
}
.tls-rp-grid-header {
  display: flex; align-items: flex-end; justify-content: space-between;
  margin-bottom: 32px;
}
.tls-rp-grid-label {
  font-family: var(--tls-sans); font-size: 10px;
  letter-spacing: .22em; text-transform: uppercase;
  color: var(--tls-muted); margin-bottom: 8px;
}
.tls-rp-grid-title {
  font-family: var(--tls-serif); font-size: 28px; font-weight: 400;
  color: var(--tls-bg-dark); margin: 0;
}
.tls-rp-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
}
.tls-rp-card {
  background: #fff; border: 1px solid var(--tls-border);
  border-radius: 10px; overflow: hidden;
  transition: box-shadow .2s, transform .15s; cursor: pointer;
}
.tls-rp-card:hover { box-shadow: var(--tls-shadow-lg); transform: translateY(-2px); }
.tls-rp-card-top {
  height: 100px; display: flex; align-items: flex-end;
  padding: 16px 20px; position: relative; overflow: hidden;
  background: var(--card-color, #1C2B3A);
}
.tls-rp-card-top-gradient {
  position: absolute; inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,.05) 0%, transparent 60%);
}
.tls-rp-card-top::before {
  content: '';
  position: absolute; inset: 8px;
  border: 1px solid rgba(255,255,255,.12); border-radius: 3px;
  pointer-events: none;
}
.tls-rp-card-emoji {
  font-size: 28px; line-height: 1; position: relative; z-index: 1;
  margin-bottom: 2px;
}
.tls-rp-card-path-num {
  position: absolute; top: 14px; right: 16px;
  font-family: var(--tls-sans); font-size: 9px;
  letter-spacing: .14em; text-transform: uppercase;
  color: rgba(255,255,255,.35);
}
.tls-rp-card-body { padding: 16px 20px 20px; }
.tls-rp-card-title {
  font-family: var(--tls-serif); font-size: 16px; font-weight: 400;
  color: var(--tls-bg-dark); margin: 0 0 4px; line-height: 1.3;
}
.tls-rp-card-subtitle {
  font-family: var(--tls-serif); font-style: italic;
  font-size: 12px; color: var(--tls-muted); margin: 0 0 8px;
}
.tls-rp-card-meta {
  font-family: var(--tls-sans); font-size: 10px;
  letter-spacing: .1em; text-transform: uppercase;
  color: var(--tls-muted); margin-bottom: 12px;
}
.tls-rp-card-meta strong { color: var(--tls-bg-dark); }
.tls-rp-card-footer {
  display: flex; align-items: center; justify-content: space-between;
  padding-top: 10px; border-top: 1px solid var(--tls-border);
}
.tls-rp-card-vol {
  font-family: var(--tls-sans); font-size: 11px; color: var(--tls-muted);
  display: flex; align-items: center; gap: 5px;
}
.tls-rp-card-add {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 6px 14px; border-radius: 20px;
  border: 1px solid var(--tls-border); background: transparent;
  font-family: var(--tls-sans); font-size: 11px; font-weight: 600;
  color: var(--tls-muted); cursor: pointer; transition: all .15s;
}
.tls-rp-card-add:hover { border-color: var(--tls-gold); color: var(--tls-gold); }
.tls-rp-card-add.saved { border-color: var(--tls-green); color: var(--tls-green); }

/* ── Empty state ─────────────────────────────────── */
.tls-rp-empty {
  text-align: center; padding: 96px 24px;
  font-family: var(--tls-sans); color: var(--tls-muted);
}
.tls-rp-empty svg { color: var(--tls-border); margin: 0 auto 20px; display: block; }
.tls-rp-empty h2 { font-family: var(--tls-serif); font-size: 22px; font-weight:400; color: var(--tls-bg-dark); margin: 0 0 8px; }
.tls-rp-empty p { font-size: 14px; max-width: 360px; margin: 0 auto 20px; line-height: 1.6; }

/* ── Responsive ─────────────────────────────────── */
@media (max-width: 900px) {
  .tls-rp-featured-inner { grid-template-columns: 1fr; gap: 40px; }
  .tls-rp-bookwrap { order: -1; }
  .tls-rp-bookstack { height: 320px; }
  .tls-rp-book-main { width: 180px; height: 258px; left: 10px; }
  .tls-rp-book-back1 { width: 172px; height: 248px; left: 30px; top: 14px; }
  .tls-rp-book-back2 { width: 166px; height: 238px; left: 46px; top: 24px; }
  .tls-rp-header-inner { grid-template-columns: 1fr; }
  .tls-rp-counter { display: none; }
}
@media (max-width: 600px) {
  .tls-rp-book-list { grid-template-columns: 1fr; }
  .tls-rp-actions { flex-direction: column; align-items: flex-start; }
  .tls-rp-ai-form { flex-direction: column; border-radius: 8px; }
  .tls-rp-ai-submit { border-radius: 0 0 6px 6px; justify-content: center; }
}
@keyframes tlsRPSpin { to { transform: rotate(360deg); } }
</style>

<main class="tls-rp-page">

<!-- ══════════════════════════════════
     PAGE HEADER
══════════════════════════════════ -->
<div class="tls-rp-header">
  <div class="container">
    <div class="tls-rp-header-inner">
      <div>
        <p class="tls-rp-eyebrow">Curated Sequences<?php if ($total) echo ' · ' . $total . ' Path' . ($total !== 1 ? 's' : ''); ?></p>
        <h1 class="tls-rp-page-title">Reading <em>Paths</em></h1>
        <p class="tls-rp-page-desc">Hand-curated sequences that build understanding step by step — from foundational texts to advanced inquiry.</p>
      </div>
      <?php if ($total) : ?>
      <div class="tls-rp-counter"><?php echo str_pad($total,2,'0',STR_PAD_LEFT); ?><span>Paths</span></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ( ! empty( $lists ) ) : ?>

<!-- ══════════════════════════════════
     FEATURED PATH
══════════════════════════════════ -->
<div class="tls-rp-featured" id="tls-featured-path">
  <div class="container">
    <div class="tls-rp-featured-inner">

      <!-- Left: content -->
      <div class="tls-rp-featured-left">

        <!-- Label + nav -->
        <div class="tls-rp-path-label">
          <span class="tls-rp-path-badge" id="rp-badge">Featured Path</span>
          <div class="tls-rp-path-nav">
            <span class="tls-rp-path-progress" id="rp-progress">Path 01 / <?php echo str_pad($total,2,'0',STR_PAD_LEFT); ?></span>
            <button class="tls-rp-nav-btn" id="rp-prev" aria-label="Previous path">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <button class="tls-rp-nav-btn" id="rp-next" aria-label="Next path">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M9 18l6-6-6-6"/></svg>
            </button>
          </div>
        </div>

        <h2 class="tls-rp-featured-title" id="rp-title"><?php echo esc_html( $lists[0]['title'] ); ?></h2>
        <?php if ( $lists[0]['subtitle'] ) : ?>
        <p class="tls-rp-featured-subtitle" id="rp-subtitle"><?php echo esc_html( $lists[0]['subtitle'] ); ?></p>
        <?php else : ?>
        <p class="tls-rp-featured-subtitle" id="rp-subtitle" style="display:none;"></p>
        <?php endif; ?>
        <p class="tls-rp-featured-desc" id="rp-desc"><?php echo esc_html( $lists[0]['desc'] ); ?></p>

        <div class="tls-rp-divider"></div>

        <!-- Books list -->
        <ul class="tls-rp-book-list" id="rp-books">
          <?php
          $i = 1;
          foreach ( array_slice($lists[0]['books'], 0, 8) as $bid ) :
            if ( ! $p = get_post($bid) ) continue;
            $aths = get_the_terms($bid,'authors');
            $auth = ($aths && !is_wp_error($aths)) ? $aths[0]->name : '';
          ?>
          <li class="tls-rp-book-item">
            <span class="tls-rp-book-num"><?php echo str_pad($i,2,'0',STR_PAD_LEFT); ?></span>
            <a class="tls-rp-book-name" href="<?php echo esc_url(get_permalink($bid)); ?>"><?php echo esc_html($p->post_title); ?></a>
            <?php if ($auth) : ?><span class="tls-rp-book-auth"><?php echo esc_html($auth); ?></span><?php endif; ?>
          </li>
          <?php $i++; endforeach; ?>
        </ul>

        <!-- Actions -->
        <div class="tls-rp-actions">
          <a class="tls-rp-btn-primary" id="rp-begin-btn"
             href="<?php echo $lists[0]['books'] ? esc_url(get_permalink($lists[0]['books'][0])) : '#'; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="14" height="14"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            Begin Path
          </a>
          <button class="tls-rp-btn-outline" id="rp-save-btn"
                  data-list-id="<?php echo (int)$lists[0]['id']; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M12 5v14M5 12h14"/></svg>
            Add to Library
          </button>
        </div>
        <p class="tls-rp-meta-strip" id="rp-meta">
          <?php if ($lists[0]['time']) echo esc_html($lists[0]['time']); ?>
          <?php if ($lists[0]['time'] && count($lists[0]['books'])) echo '<span>·</span>'; ?>
          <?php echo count($lists[0]['books']); ?> VOL<?php if (count($lists[0]['books']) !== 1) echo 'S'; ?>
          <?php if ($lists[0]['era']) echo '<span>·</span>' . esc_html($lists[0]['era']); ?>
        </p>

      </div><!-- /.left -->

      <!-- Right: 3D book -->
      <div class="tls-rp-bookwrap">
        <div class="tls-rp-bookstack" id="rp-bookstack">
          <div class="tls-rp-book-back2" id="rp-book-back2"
               style="background:<?php echo esc_attr($lists[0]['color']); ?>;filter:brightness(.5) saturate(.6);"></div>
          <div class="tls-rp-book-back1" id="rp-book-back1"
               style="background:<?php echo esc_attr($lists[0]['color']); ?>;filter:brightness(.65) saturate(.7);"></div>
          <div class="tls-rp-book-main" id="rp-book-main"
               style="--book-bg:<?php echo esc_attr($lists[0]['color']); ?>;">
            <div class="tls-rp-book-border"></div>
            <div class="tls-rp-book-content">
              <div class="tls-rp-book-content-label" id="rp-book-label">
                PATH 01 · THE TELOS LIBRARY
              </div>
              <div class="tls-rp-book-main-title" id="rp-book-title">
                <?php echo esc_html($lists[0]['title']); ?>
              </div>
              <div class="tls-rp-book-main-sub" id="rp-book-sub">
                <?php echo esc_html($lists[0]['subtitle']); ?>
              </div>
              <div class="tls-rp-book-footer">
                <div class="tls-rp-book-footer-meta" id="rp-book-meta">
                  <?php echo count($lists[0]['books']); ?> VOLUMES<br>
                  <?php echo esc_html($lists[0]['time'] ?: ''); ?>
                </div>
                <div class="tls-rp-book-logo">τέλος</div>
              </div>
            </div>
          </div>
        </div>
      </div><!-- /.bookwrap -->

    </div><!-- /.featured-inner -->
  </div>
</div>

<!-- ══════════════════════════════════
     ALL PATHS GRID
══════════════════════════════════ -->
<div class="tls-rp-grid-section">
  <div class="container">
    <div class="tls-rp-grid-header">
      <div>
        <p class="tls-rp-grid-label">All Sequences</p>
        <h2 class="tls-rp-grid-title">More Reading Paths</h2>
      </div>
    </div>
    <div class="tls-rp-grid">
      <?php foreach ( $lists as $idx => $l ) : ?>
      <div class="tls-rp-card" data-idx="<?php echo $idx; ?>" style="--card-color:<?php echo esc_attr($l['color']); ?>;">
        <div class="tls-rp-card-top">
          <div class="tls-rp-card-top-gradient"></div>
          <div class="tls-rp-card-path-num">PATH <?php echo str_pad($idx+1,2,'0',STR_PAD_LEFT); ?></div>
          <div class="tls-rp-card-emoji"><?php echo esc_html($l['emoji']); ?></div>
        </div>
        <div class="tls-rp-card-body">
          <div class="tls-rp-card-title"><?php echo esc_html($l['title']); ?></div>
          <?php if ($l['subtitle']) : ?>
          <div class="tls-rp-card-subtitle"><?php echo esc_html($l['subtitle']); ?></div>
          <?php endif; ?>
          <div class="tls-rp-card-meta">
            <?php if ($l['time']) : ?><strong><?php echo esc_html($l['time']); ?></strong> · <?php endif; ?>
            <?php echo count($l['books']); ?> volumes
            <?php if ($l['era']) echo ' · ' . esc_html($l['era']); ?>
          </div>
          <div class="tls-rp-card-footer">
            <span class="tls-rp-card-vol">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
              <?php echo count($l['books']); ?> books
            </span>
            <button class="tls-rp-card-add" data-list-id="<?php echo (int)$l['id']; ?>" type="button">
              + Add
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php else : ?>
<!-- Empty state -->
<div class="tls-rp-empty container">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" width="56" height="56"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
  <h2>No reading paths yet</h2>
  <p>Reading paths are curated sequences of books. Create the first one from the WordPress admin panel.</p>
  <?php if (current_user_can('manage_options')) : ?>
  <a class="tls-botw-cta" href="<?php echo esc_url(admin_url('post-new.php?post_type=tls_reading_list')); ?>">+ Create a Path</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════
     AI SUGGEST SECTION
══════════════════════════════════ -->
<div class="tls-rp-ai-section" id="tls-rp-ai">
  <div class="container">
    <div class="tls-rp-ai-inner">
      <p class="tls-rp-ai-eyebrow">Intelligent Discovery</p>
      <h2 class="tls-rp-ai-title">Build Your Own <em>Reading Path</em></h2>
      <p class="tls-rp-ai-desc">
        Enter a topic, theme, or philosophical question. We'll search the archive and assemble a personalized reading path just for you.
      </p>

      <!-- Topic suggestions -->
      <div class="tls-rp-topic-chips">
        <?php
        $chips = [ 'Problem of evil', 'Stoic philosophy', 'Political theory', 'Theory of knowledge', 'Free will', 'Nature of reality', 'Ethics & virtue', 'Mind & consciousness' ];
        foreach ($chips as $chip) :
        ?>
        <button class="tls-rp-chip" type="button" data-topic="<?php echo esc_attr($chip); ?>"><?php echo esc_html($chip); ?></button>
        <?php endforeach; ?>
      </div>

      <!-- Form -->
      <div class="tls-rp-ai-form">
        <input class="tls-rp-ai-input" type="text" id="tls-ai-input"
               placeholder="e.g. &quot;problem of evil&quot;, &quot;Stoic ethics&quot;, &quot;origin of language&quot;…"
               autocomplete="off" maxlength="120">
        <button class="tls-rp-ai-submit" id="tls-ai-submit" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="15" height="15" id="tls-ai-icon"><path d="M9 18l6-6-6-6"/></svg>
          Explore
        </button>
      </div>

      <!-- Results area -->
      <div id="tls-ai-results" style="display:none;"></div>
    </div>
  </div>
</div>

</main>

<script>
(function(){
    var ajax     = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
    var aiNonce  = '<?php echo esc_js( $ai_nonce ); ?>';
    var lists    = <?php echo wp_json_encode( $lists_js ); ?>;
    var total    = lists.length;
    var current  = 0;
    var loggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;

    /* ── Featured path navigation ─────────────────── */
    function pad(n){ return n < 10 ? '0'+n : ''+n; }

    function updateFeatured(idx) {
        if (!lists.length) return;
        idx = ((idx % total) + total) % total;
        current = idx;
        var l = lists[idx];

        // Text updates
        document.getElementById('rp-badge').textContent = idx === 0 ? 'Featured Path' : 'Path '+pad(idx+1);
        document.getElementById('rp-progress').textContent = 'Path '+pad(idx+1)+' / '+pad(total);
        document.getElementById('rp-title').textContent = l.title;

        var subEl = document.getElementById('rp-subtitle');
        subEl.textContent = l.subtitle || '';
        subEl.style.display = l.subtitle ? '' : 'none';

        document.getElementById('rp-desc').textContent = l.desc;

        // Books list
        var bl = document.getElementById('rp-books');
        bl.innerHTML = '';
        (l.books_data || []).slice(0,8).forEach(function(b,i){
            var li = document.createElement('li');
            li.className = 'tls-rp-book-item';
            li.innerHTML = '<span class="tls-rp-book-num">'+pad(i+1)+'</span>'
                +'<a class="tls-rp-book-name" href="/'+b.id+'">'+esc(b.title)+'</a>'
                +(b.author?'<span class="tls-rp-book-auth">'+esc(b.author)+'</span>':'');
            bl.appendChild(li);
        });

        // Begin btn
        var beginBtn = document.getElementById('rp-begin-btn');
        if (beginBtn && l.books_data && l.books_data.length) {
            beginBtn.href = '/?p='+l.books_data[0].id;
        }

        // Save btn
        var saveBtn = document.getElementById('rp-save-btn');
        if (saveBtn) saveBtn.dataset.listId = l.id;

        // Meta strip
        var meta = '';
        if (l.time) meta += l.time;
        if (l.time && l.books.length) meta += ' <span>·</span> ';
        meta += l.books.length + (l.books.length===1?' VOL':' VOLS');
        if (l.era) meta += ' <span>·</span> ' + esc(l.era);
        document.getElementById('rp-meta').innerHTML = meta;

        // Book cover
        document.getElementById('rp-book-title').textContent = l.title;
        document.getElementById('rp-book-sub').textContent   = l.subtitle || '';
        document.getElementById('rp-book-label').textContent = 'PATH '+pad(idx+1)+' · THE TELOS LIBRARY';
        document.getElementById('rp-book-meta').innerHTML    = l.books.length+' VOLUMES'+(l.time?'<br>'+l.time:'');

        // Book color
        var bookMain = document.getElementById('rp-book-main');
        var back1    = document.getElementById('rp-book-back1');
        var back2    = document.getElementById('rp-book-back2');
        if (bookMain) {
            bookMain.style.setProperty('--book-bg', l.color);
            if (back1) back1.style.background = l.color;
            if (back2) back2.style.background = l.color;
        }
    }

    var prevBtn = document.getElementById('rp-prev');
    var nextBtn = document.getElementById('rp-next');
    if (prevBtn) prevBtn.addEventListener('click', function(){ updateFeatured(current-1); });
    if (nextBtn) nextBtn.addEventListener('click', function(){ updateFeatured(current+1); });

    // Grid card click → scroll to featured + update
    document.querySelectorAll('.tls-rp-card').forEach(function(card){
        card.addEventListener('click', function(e){
            if (e.target.closest('.tls-rp-card-add')) return;
            var idx = parseInt(this.dataset.idx, 10);
            updateFeatured(idx);
            var featured = document.getElementById('tls-featured-path');
            if (featured) featured.scrollIntoView({behavior:'smooth', block:'start'});
        });
    });

    /* ── Add to Library (save btn) ─────────────────── */
    function handleSave(btn, listId) {
        if (!loggedIn) {
            // Show auth prompt
            var ev = new CustomEvent('tls:auth-required', {detail:{reason:'save_list'}});
            document.dispatchEvent(ev);
            return;
        }
        btn.disabled = true;
        var fd = new FormData();
        fd.append('action',  'tls_add_list_to_library');
        fd.append('list_id', listId);
        fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res){
            btn.disabled = false;
            if (res.success) {
                var added = res.data.action === 'added';
                btn.classList.toggle('saved', added);
                btn.innerHTML = added
                    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg> Saved'
                    : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M12 5v14M5 12h14"/></svg> Add to Library';
            }
        })
        .catch(function(){ btn.disabled = false; });
    }

    var mainSaveBtn = document.getElementById('rp-save-btn');
    if (mainSaveBtn) {
        mainSaveBtn.addEventListener('click', function(){
            handleSave(this, parseInt(this.dataset.listId,10));
        });
    }

    document.querySelectorAll('.tls-rp-card-add').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.stopPropagation();
            handleSave(this, parseInt(this.dataset.listId,10));
        });
    });

    /* ── AI Path Suggestions ───────────────────────── */
    var aiInput    = document.getElementById('tls-ai-input');
    var aiSubmit   = document.getElementById('tls-ai-submit');
    var aiIcon     = document.getElementById('tls-ai-icon');
    var aiResults  = document.getElementById('tls-ai-results');

    function doSuggest(topic) {
        if (!topic || topic.length < 2) return;
        aiInput.value = topic;

        // Loading state
        aiSubmit.disabled = true;
        aiSubmit.classList.add('loading');
        aiIcon.innerHTML = '<path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>';
        aiResults.style.display = 'none';

        var fd = new FormData();
        fd.append('action', 'tls_ai_suggest_path');
        fd.append('nonce',  aiNonce);
        fd.append('topic',  topic);

        fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res){
            aiSubmit.disabled = false;
            aiSubmit.classList.remove('loading');
            aiIcon.innerHTML = '<path d="M9 18l6-6-6-6"/>';
            renderResults(res);
        })
        .catch(function(){
            aiSubmit.disabled = false;
            aiSubmit.classList.remove('loading');
            aiIcon.innerHTML = '<path d="M9 18l6-6-6-6"/>';
            aiResults.innerHTML = '<div class="tls-rp-ai-empty">Network error. Please try again.</div>';
            aiResults.style.display = 'block';
        });
    }

    function renderResults(res) {
        aiResults.style.display = 'block';
        if (!res.success) {
            aiResults.innerHTML = '<div class="tls-rp-ai-empty">'+(res.data&&res.data.message ? esc(res.data.message) : 'Something went wrong.')+'</div>';
            return;
        }
        var d = res.data;
        if (!d.books || !d.books.length) {
            aiResults.innerHTML = '<div class="tls-rp-ai-empty">No results found for <strong>'+esc(d.topic)+'</strong>. Try a different topic.</div>';
            return;
        }
        var html = '<div class="tls-rp-ai-results">'
            +'<div class="tls-rp-ai-results-header">'
            +'<p class="tls-rp-ai-results-label">Reading Path for</p>'
            +'<h3 class="tls-rp-ai-results-title">"'+esc(d.topic)+'"</h3>'
            +(d.ai_intro ? '<p class="tls-rp-ai-intro">'+esc(d.ai_intro)+'</p>' : '')
            +'</div>'
            +'<div class="tls-rp-ai-books">';

        d.books.forEach(function(b, i){
            var coverHtml = b.cover
                ? '<img class="tls-rp-ai-book-cover" src="'+esc(b.cover)+'" alt="'+esc(b.title)+'" loading="lazy">'
                : '<div class="tls-rp-ai-book-ph" style="background:'+esc(b.color||'#1C2B3A')+';">'+esc((b.title||'?').charAt(0))+'</div>';

            html += '<a class="tls-rp-ai-book" href="'+esc(b.url)+'">'
                + coverHtml
                +'<div class="tls-rp-ai-book-body">'
                +(b.cat ? '<span class="tls-rp-ai-book-cat">'+esc(b.cat)+'</span>' : '')
                +'<span class="tls-rp-ai-book-title">'+esc(b.title)+'</span>'
                +(b.author ? '<span class="tls-rp-ai-book-author">'+esc(b.author)+'</span>' : '')
                +(b.excerpt ? '<span class="tls-rp-ai-book-excerpt">'+esc(b.excerpt)+'</span>' : '')
                +'</div></a>';
        });

        html += '</div></div>';
        aiResults.innerHTML = html;
        aiResults.scrollIntoView({behavior:'smooth', block:'nearest'});
    }

    if (aiSubmit) {
        aiSubmit.addEventListener('click', function(){
            doSuggest(aiInput.value.trim());
        });
    }
    if (aiInput) {
        aiInput.addEventListener('keydown', function(e){
            if (e.key === 'Enter') doSuggest(this.value.trim());
        });
    }

    // Chip clicks
    document.querySelectorAll('.tls-rp-chip').forEach(function(chip){
        chip.addEventListener('click', function(){
            doSuggest(this.dataset.topic);
        });
    });

    function esc(s){
        if (typeof s !== 'string') s = String(s||'');
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>

<?php get_footer(); ?>
