<?php
/**
 * The Telos — Footer Template
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>

</div><!-- /.site-content -->

<!-- ══════════════════════════════════
     THE TELOS FOOTER
══════════════════════════════════ -->
<footer class="tls-footer" role="contentinfo">
    <div class="container">
        <div class="tls-footer-grid">

            <!-- Brand column -->
            <div>
                <a class="tls-footer-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <?php bloginfo( 'name' ); ?>
                </a>
                <p class="tls-footer-tagline">
                    <?php echo esc_html( get_bloginfo( 'description' ) ?: 'Preserving human knowledge for the digital age. A nonprofit initiative for open scholarship.' ); ?>
                </p>
            </div>

            <!-- Institutional -->
            <div>
                <div class="tls-footer-col-label">Institutional</div>
                <ul class="tls-footer-links">
                    <li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>">Terms of Use</a></li>
                </ul>
            </div>

            <!-- Archive -->
            <div>
                <div class="tls-footer-col-label">Archive</div>
                <ul class="tls-footer-links">
                    <?php
                    $footer_cats = get_categories( [
                        'orderby'  => 'count',
                        'order'    => 'DESC',
                        'number'   => 5,
                        'hide_empty' => true,
                    ] );
                    foreach ( $footer_cats as $fc ) :
                    ?>
                        <li>
                            <a href="<?php echo esc_url( get_category_link( $fc->term_id ) ); ?>">
                                <?php echo esc_html( $fc->name ); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Newsletter -->
            <div>
                <div class="tls-footer-col-label">The Newsletter</div>
                <p style="font-family:var(--tls-sans);font-size:13px;color:rgba(255,255,255,.4);line-height:1.55;margin-bottom:16px;">
                    Weekly summaries from the archive, curated for the modern scholar.
                </p>
                <?php if ( shortcode_exists( 'mc4wp_form' ) ) : ?>
                    <?php echo do_shortcode( '[mc4wp_form]' ); ?>
                <?php else : ?>
                <div class="tls-footer-newsletter">
                    <input type="email" placeholder="your@email.com" aria-label="Email address">
                    <button type="button">Subscribe &rarr;</button>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /.tls-footer-grid -->

        <div class="tls-footer-bottom">
            <p class="tls-footer-copy">
                <?php echo wp_kses_data( get_theme_mod( 'footer_textleft', '&copy; ' . date( 'Y' ) . ' ' . get_bloginfo( 'name' ) . '. All rights reserved.' ) ); ?>
            </p>
            <a href="#" class="tls-back-top" aria-label="Back to top">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
                Back to top
            </a>
        </div>

    </div><!-- /.container -->
</footer>

<?php wp_footer(); ?>
</body>
</html>
