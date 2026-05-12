<?php
/**
 * The Telos — Archive / Category Template
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$archive_title = function_exists( 'mediumish_archive_title' )
    ? mediumish_archive_title()
    : get_the_archive_title();

$archive_desc  = get_the_archive_description();
$paged         = max( 1, get_query_var( 'paged' ) ?: 1 );
?>

<main id="main" role="main">

<!-- ══════════════════════════════════
     ARCHIVE HERO
══════════════════════════════════ -->
<div class="tls-archive-hero">
    <div class="container">
        <p class="tls-archive-eyebrow">
            <?php
            if ( is_category() ) echo 'Category';
            elseif ( is_tag() )  echo 'Tag';
            elseif ( is_date() ) echo 'Archive';
            else                 echo 'Archive';
            ?>
        </p>
        <h1 class="tls-archive-title"><?php echo wp_kses_post( $archive_title ); ?></h1>
        <?php if ( $archive_desc ) : ?>
            <p class="tls-archive-desc"><?php echo wp_kses_post( $archive_desc ); ?></p>
        <?php endif; ?>
        <?php
        global $wp_query;
        $total = (int) $wp_query->found_posts;
        if ( $total > 0 ) :
        ?>
        <p class="tls-archive-count"><?php echo number_format( $total ); ?> summaries</p>
        <?php endif; ?>
    </div>
</div>

<!-- ══════════════════════════════════
     BOOKS GRID
══════════════════════════════════ -->
<div class="container" style="padding-bottom:64px;">

    <?php if ( have_posts() ) : ?>
    <div class="tls-books-grid">
        <?php while ( have_posts() ) : the_post(); ?>
            <?php echo thetelos_book_card( get_the_ID() ); ?>
        <?php endwhile; ?>
    </div>

    <!-- Pagination -->
    <div class="bottompagination">
        <?php
        if ( function_exists( 'wp_bootstrap_pagination' ) ) {
            wp_bootstrap_pagination( [
                'previous_string' => '&laquo;',
                'next_string'     => '&raquo;',
                'before_output'   => '<span class="navigation">',
                'after_output'    => '</span>',
            ] );
        } else {
            the_posts_pagination( [
                'mid_size'  => 2,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            ] );
        }
        ?>
    </div>

    <?php else : ?>
        <p style="color:var(--tls-muted);font-family:var(--tls-sans);padding:40px 0;">
            <?php _e( 'No posts found.', 'mediumish' ); ?>
        </p>
    <?php endif; ?>

</div>

</main>

<?php get_footer(); ?>
