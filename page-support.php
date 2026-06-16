<?php
/**
 * Template Name: Support Us
 * Template Post Type: page
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* Shopier ürün URL'leri — Shopier hesabını açınca buraya yapıştır */
$tiers = [
    [
        'icon'   => '☕',
        'amount' => '$3',
        'label'  => 'Buy us a coffee',
        'desc'   => 'A small token of appreciation. Every cup counts.',
        'url'    => get_theme_mod( 'tls_support_url_3', '#' ),
        'featured' => false,
    ],
    [
        'icon'   => '📚',
        'amount' => '$10',
        'label'  => 'Support a summary',
        'desc'   => 'Help us research, read, and write the next book in the archive.',
        'url'    => get_theme_mod( 'tls_support_url_10', '#' ),
        'featured' => true,
    ],
    [
        'icon'   => '🌟',
        'amount' => '$25',
        'label'  => 'Patron of the archive',
        'desc'   => 'Become a sustaining patron and keep the archive alive.',
        'url'    => get_theme_mod( 'tls_support_url_25', '#' ),
        'featured' => false,
    ],
];

get_header();
?>

<main id="main" role="main">

<!-- ── Hero ── -->
<div style="background:var(--tls-bg-dark);padding:72px 0 60px;">
    <div class="container" style="text-align:center;">
        <p class="tls-sup-eyebrow">The Archive</p>
        <h1 class="tls-sup-title">Support Our Work</h1>
        <p class="tls-sup-subtitle">
            The Telos is free, independent, and ad-free. If it has brought you value,
            consider supporting the work that keeps it going.
        </p>
    </div>
</div>

<!-- ── Tier cards ── -->
<div class="tls-sup-section">
    <div class="container">

        <div class="tls-sup-cards">
            <?php foreach ( $tiers as $tier ) : ?>
            <a class="tls-sup-card<?php echo $tier['featured'] ? ' tls-sup-card--featured' : ''; ?>"
               href="<?php echo esc_url( $tier['url'] ); ?>"
               target="_blank" rel="noopener noreferrer">

                <?php if ( $tier['featured'] ) : ?>
                <div class="tls-sup-card-badge">Most Popular</div>
                <?php endif; ?>

                <span class="tls-sup-card-icon" aria-hidden="true"><?php echo $tier['icon']; ?></span>
                <div class="tls-sup-card-amount"><?php echo esc_html( $tier['amount'] ); ?></div>
                <div class="tls-sup-card-label"><?php echo esc_html( $tier['label'] ); ?></div>
                <p class="tls-sup-card-desc"><?php echo esc_html( $tier['desc'] ); ?></p>

                <div class="tls-sup-card-cta">
                    Support with <?php echo esc_html( $tier['amount'] ); ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         width="14" height="14" aria-hidden="true">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Trust signals -->
        <div class="tls-sup-trust">
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     width="14" height="14" aria-hidden="true">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                Secure payment via Shopier
            </span>
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     width="14" height="14" aria-hidden="true">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                </svg>
                Visa &amp; Mastercard accepted
            </span>
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     width="14" height="14" aria-hidden="true">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                No account required
            </span>
        </div>

    </div>
</div>

<!-- ── Why we exist ── -->
<div class="tls-sup-why">
    <div class="container">
        <div class="tls-sup-why-inner">
            <h2 class="tls-sup-why-title">What your support makes possible</h2>
            <div class="tls-sup-why-grid">
                <div class="tls-sup-why-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                         width="22" height="22" aria-hidden="true">
                        <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/>
                        <path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>
                    </svg>
                    <div>
                        <strong>New summaries</strong>
                        <p>Each summary takes 6–12 hours of reading, distilling, and writing. Your support funds the next one.</p>
                    </div>
                </div>
                <div class="tls-sup-why-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                         width="22" height="22" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/>
                    </svg>
                    <div>
                        <strong>Free &amp; open to all</strong>
                        <p>The Telos will always be free and ad-free. No paywalls, no tracking, no sponsored content.</p>
                    </div>
                </div>
                <div class="tls-sup-why-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                         width="22" height="22" aria-hidden="true">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                    <div>
                        <strong>Independence</strong>
                        <p>We are not backed by publishers, algorithms, or advertisers. Only by readers like you.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</main>

<?php get_footer(); ?>
