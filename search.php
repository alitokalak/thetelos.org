<?php
/**
 * The Telos — Search Results Template
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

global $wp_query;
$search_query = get_search_query();
$total        = (int) $wp_query->found_posts;
?>

<main id="main" role="main">

<!-- Search Hero -->
<div class="tls-archive-hero">
    <div class="container">
        <p class="tls-archive-eyebrow">Search Results</p>
        <h1 class="tls-archive-title">
            <?php if ( $search_query ) : ?>
                &ldquo;<?php echo esc_html( $search_query ); ?>&rdquo;
            <?php else : ?>
                All Results
            <?php endif; ?>
        </h1>
        <?php if ( $total > 0 ) : ?>
            <p class="tls-archive-count"><?php echo number_format( $total ); ?> result<?php echo $total !== 1 ? 's' : ''; ?> found</p>
        <?php endif; ?>

        <!-- Search bar -->
        <form class="tls-search-hero-form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" style="margin-top:28px;display:flex;max-width:540px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);border-radius:6px;overflow:hidden;">
            <input type="search" name="s"
                   placeholder="Refine your search…"
                   value="<?php echo esc_attr( $search_query ); ?>"
                   autocomplete="off"
                   style="flex:1;padding:14px 20px;border:none;background:none;font-family:var(--tls-sans);font-size:15px;color:#fff;outline:none;"
                   aria-label="Search">
            <button type="submit" style="padding:0 24px;background:var(--tls-gold);color:#fff;border:none;font-family:var(--tls-sans);font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;white-space:nowrap;">Search</button>
        </form>
    </div>
</div>

<!-- Results Grid -->
<div class="container" style="padding-bottom:64px;">
    <?php if ( have_posts() ) : ?>
        <div class="tls-books-grid">
            <?php while ( have_posts() ) : the_post(); ?>
                <?php echo thetelos_book_card( get_the_ID() ); ?>
            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <div class="bottompagination" style="margin-top:40px;">
            <?php
            if ( function_exists( 'wp_bootstrap_pagination' ) ) {
                wp_bootstrap_pagination( [
                    'previous_string' => '&laquo;',
                    'next_string'     => '&raquo;',
                    'before_output'   => '<span class="navigation">',
                    'after_output'    => '</span>',
                ] );
            } else {
                the_posts_pagination( [ 'mid_size' => 2 ] );
            }
            ?>
        </div>
    <?php else : ?>
        <div style="padding:80px 0;text-align:center;">
            <p style="font-family:var(--tls-serif);font-size:22px;color:var(--tls-bg-dark);margin-bottom:12px;">No results found</p>
            <p style="font-family:var(--tls-sans);font-size:15px;color:var(--tls-muted);">Try different keywords or browse by category.</p>
        </div>
    <?php endif; ?>
</div>

</main>

<?php get_footer(); ?>
