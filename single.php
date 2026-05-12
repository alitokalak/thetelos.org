<?php
/**
 * The Telos — Single Book Post Template
 *
 * Layout: Book cover + metadata | Article content | Related books
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$disable_rp   = get_theme_mod( 'disable_rp_sectionarticles' );
$disable_share = get_theme_mod( 'disable_share_sectionarticles' );
$disable_tags  = get_theme_mod( 'disable_tags_sectionarticles' );
$disable_cats  = get_theme_mod( 'disable_cats_sectionarticles' );
$bottom_alert  = get_theme_mod( 'mediumish_bottomalert' );
$disable_alert = get_theme_mod( 'disable_bottomalert_sectionarticles' );
?>

<main id="main" role="main">

<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>

<?php
// ── Gather data ──────────────────────────────────────────
$post_id   = get_the_ID();

// Book authors taxonomy
$raw_authors = get_the_terms( $post_id, 'authors' );
$book_author = ( ! empty( $raw_authors ) && ! is_wp_error( $raw_authors ) ) ? $raw_authors[0] : null;

// Categories
$cats = get_the_category( $post_id );

// Analysis
$analysis       = function_exists( 'thetelos_get_analysis_for_post' ) ? thetelos_get_analysis_for_post( $post_id ) : null;
$analysis_url   = $analysis ? get_permalink( $analysis->ID ) : '';

// Hide featured image option
$hide_featimg = get_post_meta( $post_id, 'hide_featured_image_hide_featured_image_on_post', true );

// Rating data
$rating_data = function_exists( 'thetelos_get_rating' ) ? thetelos_get_rating( $post_id ) : [ 'avg' => 0, 'count' => 0 ];
$rating_avg  = round( (float) $rating_data['avg'], 1 );
$rating_cnt  = (int) $rating_data['count'];

// Reading time
$reading_time = function_exists( 'thetelos_post_reading_time' ) ? thetelos_post_reading_time( $post_id ) : mediumish_estimated_reading_time();
?>

<!-- ══════════════════════════════════
     BOOK HERO (cover + metadata)
══════════════════════════════════ -->
<div class="tls-book-hero" data-tls-post-id="<?php echo (int) $post_id; ?>">
    <div class="container">

        <!-- Back link -->
        <a class="tls-back-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Archive
        </a>

        <div class="tls-book-hero-grid">

            <!-- Cover column -->
            <div class="tls-book-cover-col">
                <div class="tls-book-cover-shadow">
                    <?php if ( has_post_thumbnail() && ! $hide_featimg ) : ?>
                        <?php the_post_thumbnail( 'medium', [ 'alt' => esc_attr( get_the_title() ) ] ); ?>
                    <?php else : ?>
                        <?php echo thetelos_render_book_cover( $post_id ); ?>
                    <?php endif; ?>
                </div>

                <?php if ( $disable_share == 0 ) : ?>
                <!-- Share -->
                <div style="margin-top:20px;display:flex;gap:10px;justify-content:center;">
                    <?php
                    $share_url   = urlencode( get_permalink() );
                    $share_title = rawurlencode( get_the_title() );
                    $tw_url  = 'https://twitter.com/intent/tweet?text=' . $share_title . '&url=' . $share_url;
                    $fb_url  = 'https://www.facebook.com/sharer/sharer.php?u=' . $share_url;
                    ?>
                    <a href="<?php echo esc_url( $tw_url ); ?>" target="_blank" rel="noopener"
                       class="tls-icon-btn" title="Share on X"
                       style="border:1px solid var(--tls-border);border-radius:50%;">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <a href="<?php echo esc_url( $fb_url ); ?>" target="_blank" rel="noopener"
                       class="tls-icon-btn" title="Share on Facebook"
                       style="border:1px solid var(--tls-border);border-radius:50%;">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Metadata column -->
            <div class="tls-book-meta-col">

                <!-- Genre badges -->
                <?php if ( ! empty( $cats ) ) : ?>
                <div class="tls-book-genres">
                    <?php foreach ( array_slice( $cats, 0, 3 ) as $cat ) : ?>
                    <a class="tls-book-genre" href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>">
                        <?php echo esc_html( $cat->name ); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Title -->
                <h1 class="tls-book-title"><?php the_title(); ?></h1>

                <!-- Author -->
                <?php if ( $book_author ) : ?>
                <a class="tls-book-author-link"
                   href="<?php echo esc_url( get_term_link( $book_author ) ); ?>">
                    <?php echo esc_html( $book_author->name ); ?>
                </a>
                <?php endif; ?>

                <!-- Star rating -->
                <div class="tls-rating-block">
                    <div class="tls-rating-label">Rate this book</div>
                    <div class="tls-stars" data-post-id="<?php echo (int) $post_id; ?>" role="group" aria-label="Book rating">
                        <?php for ( $s = 1; $s <= 5; $s++ ) :
                            $filled = $s <= round( $rating_avg ) ? ' filled' : '';
                        ?>
                        <svg class="tls-star<?php echo $filled; ?>" data-value="<?php echo $s; ?>"
                             viewBox="0 0 24 24" fill="currentColor" aria-label="<?php echo $s; ?> star">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <?php endfor; ?>
                    </div>
                    <div class="tls-rating-count">
                        <span class="tls-rating-avg"><?php echo $rating_avg > 0 ? number_format( $rating_avg, 1 ) : '—'; ?></span>
                        <?php if ( $rating_cnt > 0 ) : ?>
                        (<?php echo $rating_cnt; ?> rating<?php echo $rating_cnt !== 1 ? 's' : ''; ?>)
                        <?php endif; ?>
                    </div>
                    <div class="tls-rating-msg"></div>
                </div>

                <!-- Reading status -->
                <div class="tls-read-status" role="group" aria-label="Reading status">
                    <button class="tls-status-btn" data-status="want" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
                        Want to Read
                    </button>
                    <button class="tls-status-btn" data-status="reading" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
                        Currently Reading
                    </button>
                    <button class="tls-status-btn" data-status="read" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                        Read
                    </button>
                </div>

                <!-- Meta bar (reading time, date) -->
                <div class="tls-book-meta-bar">
                    <div class="tls-meta-item">
                        <span class="tls-meta-label">Reading time</span>
                        <span class="tls-meta-value"><?php echo esc_html( $reading_time ); ?></span>
                    </div>
                    <div class="tls-meta-item">
                        <span class="tls-meta-label">Published</span>
                        <span class="tls-meta-value"><?php echo esc_html( get_the_date( 'M Y' ) ); ?></span>
                    </div>
                    <?php if ( $book_author ) : ?>
                    <div class="tls-meta-item">
                        <span class="tls-meta-label">Author</span>
                        <span class="tls-meta-value">
                            <a href="<?php echo esc_url( get_term_link( $book_author ) ); ?>"
                               style="color:inherit;text-decoration:none;border-bottom:1px solid var(--tls-border);">
                                <?php echo esc_html( $book_author->name ); ?>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Deep Analysis CTA -->
                <?php if ( $analysis ) : ?>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a class="tls-analysis-btn" id="analysis-trigger"
                       href="<?php echo esc_url( $analysis_url ); ?>"
                       aria-haspopup="dialog">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="15" height="15" aria-hidden="true">
                            <path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                        </svg>
                        Deep Analysis
                    </a>
                    <!-- Print button -->
                    <button class="tls-status-btn" onclick="window.print(); return false;" title="Print">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
                        Print
                    </button>
                </div>
                <?php endif; ?>

            </div><!-- /.tls-book-meta-col -->
        </div><!-- /.tls-book-hero-grid -->
    </div>
</div><!-- /.tls-book-hero -->

<!-- ══════════════════════════════════
     ARTICLE CONTENT
══════════════════════════════════ -->
<div class="container">
    <div class="tls-article-wrap">

        <?php if ( function_exists( 'yoast_breadcrumb' ) ) : ?>
            <?php yoast_breadcrumb( '<p class="tls-back-link" style="margin-bottom:0;">', '</p>' ); ?>
        <?php endif; ?>

        <?php wtn_ad_block_top_article(); ?>

        <article class="tls-article-content article-post">
            <?php the_content(); ?>
            <?php
            wp_link_pages( [
                'before'      => '<div class="page-links">',
                'after'       => '</div>',
                'link_before' => '<span class="page-link">',
                'link_after'  => '</span>',
            ] );
            ?>
        </article>

        <?php wtn_ad_block_bottom_article(); ?>

        <!-- Tags -->
        <?php if ( $disable_tags == 0 || $disable_cats == 0 ) : ?>
        <div class="after-post-tags">
            <?php if ( $disable_cats == 0 ) the_category(); ?>
            <?php if ( $disable_tags == 0 ) : ?>
            <ul class="post-categories aretags">
                <?php foreach ( (array) get_the_tags( $post_id ) as $tag ) : ?>
                <li><a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>">#<?php echo esc_html( $tag->name ); ?></a></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Prev / Next -->
        <nav class="tls-prevnext" aria-label="Post navigation">
            <div>
                <?php
                $prev = get_previous_post();
                if ( $prev ) :
                ?>
                <a class="tls-prevnext-link" href="<?php echo esc_url( get_permalink( $prev ) ); ?>">
                    <span>&larr; Previous</span>
                    <strong><?php echo esc_html( $prev->post_title ); ?></strong>
                </a>
                <?php endif; ?>
            </div>
            <div>
                <?php
                $next = get_next_post();
                if ( $next ) :
                ?>
                <a class="tls-prevnext-link next" href="<?php echo esc_url( get_permalink( $next ) ); ?>">
                    <span>Next &rarr;</span>
                    <strong><?php echo esc_html( $next->post_title ); ?></strong>
                </a>
                <?php endif; ?>
            </div>
        </nav>

        <!-- Comments -->
        <?php if ( comments_open() || get_comments_number() ) comments_template(); ?>

    </div>
</div>

<?php endwhile; endif; ?>

<!-- ══════════════════════════════════
     RELATED BOOKS
══════════════════════════════════ -->
<?php if ( $disable_rp == 0 ) : ?>
<div class="tls-related">
    <div class="container">
        <h2 class="tls-related-title">You might also enjoy</h2>
        <?php echo mediumish_related_posts( [ 'limit' => 4 ] ); ?>
    </div>
</div>
<?php endif; ?>

<!-- Alert bar -->
<?php if ( $disable_alert == 0 && $bottom_alert ) : ?>
<div class="alertbar">
    <div class="container">
        <?php echo do_shortcode( $bottom_alert ); ?>
    </div>
</div>
<?php endif; ?>

</main>

<?php get_footer(); ?>
