<?php
/**
 * Template Name: Request a Summary
 * Template Post Type: page
 *
 * Özet istek formu sayfası.
 * WordPress admin → Pages → Add New → Page Attributes → Template: "Request a Summary"
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>

<main id="main" role="main">

<!-- ── Hero ── -->
<div style="background:var(--tls-bg-dark);padding:60px 0 52px;">
    <div class="container" style="text-align:center;">
        <p style="font-family:var(--tls-sans);font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--tls-gold);margin:0 0 14px;">The Archive</p>
        <h1 style="font-family:var(--tls-serif);font-size:clamp(32px,5vw,52px);font-weight:400;color:#fff;margin:0 0 16px;line-height:1.15;">Request a Summary</h1>
        <p style="font-family:var(--tls-sans);font-size:15px;color:rgba(255,255,255,.55);margin:0 auto;max-width:480px;line-height:1.7;">
            Can't find a book in our archive? Let us know — we'll add it to our reading list.
        </p>
    </div>
</div>

<!-- ── Form ── -->
<div style="background:#fff;padding:0;">
    <div class="container">
        <?php echo do_shortcode('[tls_request_form]'); ?>
    </div>
</div>

</main>

<?php get_footer(); ?>
