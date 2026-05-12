<?php
/**
 * The Telos — Authors Taxonomy Archive
 *
 * Displays all book summaries by a specific book author (custom taxonomy).
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$term  = get_queried_object();   // WP_Term for current author
$paged = max( 1, get_query_var( 'paged' ) ?: 1 );

$author_name  = $term ? $term->name        : '';
$author_desc  = $term ? $term->description : '';
$author_count = $term ? (int) $term->count  : 0;
?>

<main id="main" role="main">

<!-- ══════════════════════════════════
     AUTHOR HERO
══════════════════════════════════ -->
<div class="tls-author-hero">
    <div class="container">
        <p class="tls-author-eyebrow">Author Archive</p>
        <h1 class="tls-author-name"><?php echo esc_html( $author_name ); ?></h1>
        <?php if ( $author_desc ) : ?>
            <p class="tls-author-desc"><?php echo wp_kses_post( $author_desc ); ?></p>
        <?php endif; ?>
        <p class="tls-author-count">
            <?php printf(
                '%s summar%s in the archive',
                number_format( $author_count ),
                $author_count === 1 ? 'y' : 'ies'
            ); ?>
        </p>
    </div>
</div>

<!-- ══════════════════════════════════
     BOOK LIST
══════════════════════════════════ -->
<div class="container" style="padding-bottom:64px;">

    <?php if ( have_posts() ) : ?>
    <ul class="tls-author-list">
        <?php while ( have_posts() ) : the_post();
            $post_id  = get_the_ID();
            $cats     = get_the_category( $post_id );
            $has_thumb = has_post_thumbnail( $post_id );
            $analysis  = function_exists( 'thetelos_get_analysis_for_post' )
                ? thetelos_get_analysis_for_post( $post_id ) : null;
        ?>
        <li class="tls-author-list-item">

            <!-- Book cover -->
            <div class="tls-author-list-cover">
                <a href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
                    <?php if ( $has_thumb ) : ?>
                        <?php the_post_thumbnail( [ 80, 120 ], [ 'alt' => esc_attr( get_the_title() ) ] ); ?>
                    <?php else : ?>
                        <?php echo thetelos_render_book_cover( $post_id ); ?>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Meta -->
            <div class="tls-author-list-body">
                <?php if ( ! empty( $cats ) ) : ?>
                <a class="tls-author-list-cat"
                   href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>">
                    <?php echo esc_html( $cats[0]->name ); ?>
                </a>
                <?php endif; ?>

                <a class="tls-author-list-title" href="<?php the_permalink(); ?>">
                    <?php the_title(); ?>
                </a>

                <p class="tls-author-list-desc">
                    <?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?>
                </p>

                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <span class="tls-author-list-meta">
                        <?php echo esc_html( get_the_date( 'Y' ) ); ?>
                        &middot;
                        <?php echo esc_html( function_exists( 'thetelos_post_reading_time' )
                            ? thetelos_post_reading_time( $post_id )
                            : mediumish_estimated_reading_time() ); ?>
                    </span>
                    <?php if ( $analysis ) : ?>
                    <a href="<?php the_permalink(); ?>#deep-analysis"
                       class="tls-analysis-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                        </svg>
                        Deep Analysis
                    </a>
                    <?php endif; ?>
                </div>
            </div>

        </li>
        <?php endwhile; ?>
    </ul>

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
        <p style="color:var(--tls-muted);font-family:var(--tls-sans);padding:40px 0;">
            No books found for this author.
        </p>
    <?php endif; ?>

</div>

</main>

<?php get_footer(); ?>
