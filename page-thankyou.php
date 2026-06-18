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

        <span class="tls-thanks-check" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" width="34" height="34">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </span>

        <p class="tls-thanks-eyebrow">The Archive &middot; With Gratitude</p>

        <h1 class="tls-thanks-headline">Your gift was received.</h1>

        <p class="tls-thanks-body">
            Thank you. Your support keeps the world's great books free to read —
            no ads, no paywalls, no investors. A confirmation has been sent to
            your email, and your contribution goes directly toward the next book
            in the archive.
        </p>

        <p class="tls-thanks-body tls-thanks-body-quiet">
            With our sincere thanks,<br>
            <em>The Telos Editorial</em>
        </p>

        <div class="tls-thanks-actions">
            <a class="tls-thanks-btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                Return to the archive
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2.5" width="15" height="15" aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                </svg>
            </a>
        </div>

    </div><!-- /.tls-thanks-card -->
</div><!-- /.tls-thanks-wrap -->
</main>

<?php get_footer(); ?>
