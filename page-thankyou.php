<?php
/**
 * Template Name: Thank You — Payment Received
 * Template Post Type: page
 *
 * Polar başarılı ödeme sonrası buraya yönlendirir (Success URL).
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>

<main id="main" role="main">
<div class="tls-thanks-wrap">
    <div class="tls-thanks-card">

        <!-- Card header — matches donation card style -->
        <div class="tls-thanks-card-head">
            <span class="tls-thanks-live">
                <span class="tls-thanks-live-dot" aria-hidden="true"></span>
                Payment confirmed
            </span>
            <span class="tls-thanks-secure">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     width="12" height="12" aria-hidden="true">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                Secure
            </span>
        </div>

        <!-- Card body -->
        <div class="tls-thanks-card-body">

            <!-- Heart icon -->
            <div class="tls-thanks-heart" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="currentColor"
                     width="32" height="32">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </div>

            <p class="tls-thanks-eyebrow">The Archive &middot; With Gratitude</p>

            <h1 class="tls-thanks-headline">Your gift<br><em>was received.</em></h1>

            <p class="tls-thanks-body">
                Thank you. Your support keeps the world's great books
                free to read — no ads, no paywalls, no investors. A
                confirmation has been sent to your email.
            </p>

            <p class="tls-thanks-sig">— The Telos Editorial</p>

            <a class="tls-thanks-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                Return to the archive
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2.5" width="14" height="14" aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                </svg>
            </a>

        </div><!-- /.tls-thanks-card-body -->
    </div><!-- /.tls-thanks-card -->
</div><!-- /.tls-thanks-wrap -->
</main>

<?php get_footer(); ?>
