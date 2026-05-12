<?php
/**
 * The Telos — Homepage Template
 *
 * Sections: Hero → Featured Works → Browse the Stacks → Latest Additions
 *
 * @package Mediumish / TheTelos
 */
get_header();

$paged = max( 1, get_query_var( 'paged' ) ?: get_query_var( 'page' ) ?: 1 );
$seen_ids = [];

// Helper — push IDs
function tls_push( &$seen, $id ) {
    $id = (int) $id;
    if ( $id && ! in_array( $id, $seen, true ) ) $seen[] = $id;
}
?>

<main id="main" role="main">

<?php if ( is_home() && ! is_paged() ) : ?>

<!-- ══════════════════════════════════
     HERO
══════════════════════════════════ -->
<section class="tls-hero">
    <div class="container">
        <p class="tls-hero-eyebrow">The Digital Archive</p>
        <h1 class="tls-hero-title">
            Explore the World's<br><em>Great Book Summaries</em>
        </h1>
        <p class="tls-hero-desc">
            A curated archive of the world's most influential texts, distilled for the modern scholar.
        </p>

        <!-- Search bar -->
        <form class="tls-hero-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <svg style="width:18px;height:18px;color:#bbb;margin-left:16px;flex-shrink:0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" name="s"
                   placeholder="Search by author, title, or concept…"
                   value="<?php echo esc_attr( get_search_query() ); ?>"
                   autocomplete="off"
                   aria-label="Search the archive">
            <button type="submit">Explore</button>
        </form>

        <!-- Category marquee -->
        <?php
        $all_cats = get_terms( [
            'taxonomy'   => 'category',
            'orderby'    => 'count',
            'order'      => 'DESC',
            'number'     => 0,
            'hide_empty' => true,
        ] );
        if ( ! empty( $all_cats ) && ! is_wp_error( $all_cats ) ) :
            // Build pill HTML once, duplicate for seamless loop
            ob_start();
            foreach ( $all_cats as $pt ) :
        ?>
                <a class="tls-tag-pill"
                   href="<?php echo esc_url( get_category_link( $pt->term_id ) ); ?>">
                    <?php echo esc_html( $pt->name ); ?>
                </a>
        <?php
            endforeach;
            $pills_html = ob_get_clean();
        ?>
        <div class="tls-hero-tags">
            <span class="tls-hero-tags-label">Popular:</span>
            <div class="tls-hero-tags-track">
                <?php echo $pills_html; ?>
                <?php echo $pills_html; /* duplicate for seamless loop */ ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ══════════════════════════════════
     FEATURED WORKS
══════════════════════════════════ -->
<?php
// Use sticky posts as featured; fallback to 3 most recent
$sticky = get_option( 'sticky_posts', [] );
$featured_args = [
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'posts_per_page'      => 3,
    'ignore_sticky_posts' => 1,
    'orderby'             => 'date',
    'order'               => 'DESC',
    'no_found_rows'       => true,
];
if ( ! empty( $sticky ) ) {
    $featured_args['post__in'] = array_slice( $sticky, 0, 3 );
    $featured_args['orderby']  = 'post__in';
}
$featured_q = new WP_Query( $featured_args );

// Determine section title from first post's category
$section_cat = 'Featured Works';
if ( $featured_q->have_posts() ) {
    $first_cats = get_the_category( $featured_q->posts[0]->ID );
    if ( ! empty( $first_cats ) ) $section_cat = $first_cats[0]->name;
}
?>

<section class="tls-section">
    <div class="container">
        <p class="tls-section-label">Featured Works</p>
        <div class="tls-section-header">
            <h2 class="tls-section-title">Pillars of <?php echo esc_html( $section_cat ); ?></h2>
            <a class="tls-view-all" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                View entire archive
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>

        <?php if ( $featured_q->have_posts() ) : ?>
        <div class="tls-featured-grid">
            <?php
            $fi = 1;
            while ( $featured_q->have_posts() ) :
                $featured_q->the_post();
                tls_push( $seen_ids, get_the_ID() );
                $cats    = get_the_category();
                $cat_name = ! empty( $cats ) ? $cats[0]->name : '';
                $cat_link = ! empty( $cats ) ? get_category_link( $cats[0]->term_id ) : '';
                $authors  = get_the_terms( get_the_ID(), 'authors' );
                $auth     = ( ! empty( $authors ) && ! is_wp_error( $authors ) ) ? $authors[0]->name : get_the_author_meta( 'display_name' );
            ?>
            <a class="tls-featured-card" href="<?php the_permalink(); ?>">
                <div class="tls-featured-card-num">
                    <?php printf( 'BOOK %02d', $fi ); ?>
                    <?php if ( $cat_name ) echo ' &mdash; <span style="color:var(--tls-gold)">' . esc_html( strtoupper( $cat_name ) ) . '</span>'; ?>
                </div>
                <div class="tls-featured-card-cover">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail( 'medium', [ 'style' => 'max-width:120px;border-radius:2px;box-shadow:0 8px 28px rgba(0,0,0,.25);' ] ); ?>
                    <?php else : ?>
                        <?php echo thetelos_render_book_cover( get_the_ID() ); ?>
                    <?php endif; ?>
                </div>
                <div class="tls-featured-card-divider"></div>
                <div class="tls-featured-card-title"><?php the_title(); ?></div>
                <div class="tls-featured-card-author"><?php echo esc_html( $auth ); ?></div>
                <div class="tls-featured-card-desc"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></div>
            </a>
            <?php
            $fi++;
            endwhile;
            wp_reset_postdata();
            ?>
        </div>
        <?php else : ?>
            <p style="color:var(--tls-muted);font-family:var(--tls-sans);">No featured posts found. Mark posts as sticky to feature them here.</p>
        <?php endif; ?>
    </div>
</section>

<!-- ══════════════════════════════════
     BROWSE THE STACKS
══════════════════════════════════ -->
<?php
$browse_cats = get_categories( [
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 4,
    'hide_empty' => true,
] );

// Category icons map (slug → emoji)
$cat_icons = [
    'philosophy'   => '🏛️',
    'history'      => '📜',
    'science'      => '⚗️',
    'art'          => '🎨',
    'theology'     => '✝️',
    'economics'    => '📊',
    'literature'   => '📚',
    'psychology'   => '🧠',
    'politics'     => '⚖️',
    'religion'     => '🕌',
    'biography'    => '👤',
    'mathematics'  => '∑',
    'technology'   => '💡',
    'music'        => '🎵',
];
?>

<section class="tls-section">
    <div class="container">
        <div class="tls-browse-wrap">

            <!-- Dark panel -->
            <div class="tls-browse-dark">
                <p class="tls-browse-dark-label">Navigation</p>
                <h2 class="tls-browse-dark-title">Browse the<br>Eternal Stacks</h2>
                <p class="tls-browse-dark-desc">
                    Organized by epoch, ideology, and influence. Find the missing piece of your intellectual puzzle.
                </p>
                <a class="tls-browse-cta" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    Open Collections
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>

            <!-- Category tiles grid -->
            <div class="tls-cat-grid">
                <?php
                $active_set = false;
                foreach ( $browse_cats as $bi => $bc ) :
                    $icon  = $cat_icons[ $bc->slug ] ?? '📖';
                    $active = ( ! $active_set && $bi === 1 ) ? 'tls-cat-active' : '';
                    if ( $active ) $active_set = true;
                ?>
                <a class="tls-cat-tile <?php echo $active; ?>"
                   href="<?php echo esc_url( get_category_link( $bc->term_id ) ); ?>">
                    <span class="tls-cat-tile-icon"><?php echo $icon; ?></span>
                    <span class="tls-cat-tile-name"><?php echo esc_html( $bc->name ); ?></span>
                    <span class="tls-cat-tile-count"><?php echo number_format( $bc->count ); ?> volumes</span>
                </a>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</section>

<!-- ══════════════════════════════════
     LATEST ADDITIONS + CURATOR'S NOTE
══════════════════════════════════ -->
<?php
$latest_q = new WP_Query( [
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'posts_per_page'      => 7,
    'post__not_in'        => $seen_ids,
    'ignore_sticky_posts' => 1,
    'orderby'             => 'date',
    'order'               => 'DESC',
    'no_found_rows'       => true,
] );
?>

<section class="tls-section">
    <div class="container">
        <p class="tls-section-label">Latest Additions</p>
        <h2 class="tls-section-title" style="margin-bottom:36px;">Recent to the Archive</h2>

        <div class="tls-latest-wrap">

            <!-- Book list -->
            <div>
                <?php if ( $latest_q->have_posts() ) : ?>
                <ul class="tls-latest-list">
                    <?php while ( $latest_q->have_posts() ) : $latest_q->the_post(); ?>
                    <li class="tls-latest-item">
                        <div class="tls-latest-date">
                            <strong><?php echo esc_html( get_the_date( 'j' ) ); ?></strong>
                            <?php echo esc_html( strtoupper( get_the_date( 'M' ) ) ); ?>
                        </div>
                        <div class="tls-latest-body">
                            <a class="tls-latest-title" href="<?php the_permalink(); ?>">
                                <?php the_title(); ?>
                            </a>
                            <?php
                            $l_authors = get_the_terms( get_the_ID(), 'authors' );
                            $l_auth    = ( ! empty( $l_authors ) && ! is_wp_error( $l_authors ) ) ? $l_authors[0]->name : '';
                            ?>
                            <p class="tls-latest-meta">
                                <?php if ( $l_auth ) echo esc_html( $l_auth ) . ' &mdash; '; ?>
                                <?php echo esc_html( wp_trim_words( get_the_excerpt(), 16 ) ); ?>
                            </p>
                            <div class="tls-latest-tags">
                                <?php
                                $l_cats = get_the_category();
                                foreach ( array_slice( $l_cats, 0, 2 ) as $lc ) :
                                ?>
                                <a class="tls-cat-badge" href="<?php echo esc_url( get_category_link( $lc->term_id ) ); ?>">
                                    <?php echo esc_html( $lc->name ); ?>
                                </a>
                                <?php endforeach; ?>
                                <?php if ( function_exists( 'thetelos_get_analysis_for_post' ) && thetelos_get_analysis_for_post( get_the_ID() ) ) : ?>
                                <span class="tls-cat-badge" style="border-color:var(--tls-green);color:var(--tls-green);">Deep Analysis</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endwhile; wp_reset_postdata(); ?>
                </ul>
                <?php else : ?>
                    <p style="color:var(--tls-muted);font-family:var(--tls-sans);">No posts found.</p>
                <?php endif; ?>

                <div style="margin-top:24px;">
                    <a class="tls-cta-btn" href="<?php echo esc_url( home_url( '/?paged=2' ) ); ?>"
                       style="display:inline-block;width:auto;padding:12px 32px;">
                        View all summaries &rarr;
                    </a>
                </div>
            </div>

            <!-- Curator's Note -->
            <aside class="tls-curator">
                <div class="tls-curator-label">The Curator's Note</div>
                <p class="tls-curator-quote">
                    &ldquo;The purpose of The Telos is not to replace the reading of great works, but to provide the maps necessary to navigate the vast landscape of human thought.&rdquo;
                </p>
                <div class="tls-stats-label">Live Archive Stats</div>
                <?php
                $total_books   = wp_count_posts( 'post' )->publish;
                $total_authors = wp_count_terms( [ 'taxonomy' => 'authors', 'hide_empty' => true ] );
                $total_cats    = wp_count_terms( [ 'taxonomy' => 'category', 'hide_empty' => true ] );
                if ( is_wp_error( $total_authors ) ) $total_authors = 0;
                if ( is_wp_error( $total_cats ) )    $total_cats = 0;
                ?>
                <div class="tls-stats-row">
                    <span>Total Summaries</span>
                    <strong><?php echo number_format( $total_books ); ?></strong>
                </div>
                <div class="tls-stats-row">
                    <span>Unique Authors</span>
                    <strong><?php echo number_format( (int) $total_authors ); ?></strong>
                </div>
                <div class="tls-stats-row">
                    <span>Categories</span>
                    <strong><?php echo number_format( (int) $total_cats ); ?></strong>
                </div>
                <a class="tls-cta-btn" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">
                    Request a Summary
                </a>
            </aside>

        </div>
    </div>
</section>

<?php endif; // is_home && !is_paged ?>

<!-- ══════════════════════════════════
     PAGINATED / ARCHIVE FALLBACK
══════════════════════════════════ -->
<?php if ( is_paged() || ! is_home() ) : ?>
<section style="padding:56px 0;">
    <div class="container">
        <?php if ( is_search() ) : ?>
        <div class="tls-search-header">
            <p class="tls-search-for">Search results for</p>
            <h1 class="tls-search-term"><?php echo esc_html( get_search_query() ); ?></h1>
        </div>
        <?php elseif ( is_archive() ) : ?>
        <div class="tls-archive-hero" style="border-radius:var(--tls-radius);margin-bottom:36px;padding:48px 36px;">
            <p class="tls-archive-eyebrow">Archive</p>
            <h1 class="tls-archive-title"><?php echo esc_html( mediumish_archive_title() ); ?></h1>
        </div>
        <?php else : ?>
        <h1 class="tls-section-title" style="margin-bottom:32px;">All Summaries</h1>
        <?php endif; ?>

        <?php
        $main_q = new WP_Query( [
            'post_type'   => 'post',
            'post_status' => 'publish',
            'paged'       => $paged,
            'orderby'     => [ 'date' => 'DESC', 'ID' => 'DESC' ],
        ] );
        if ( $main_q->have_posts() ) :
        ?>
        <div class="tls-books-grid">
            <?php while ( $main_q->have_posts() ) : $main_q->the_post(); ?>
                <?php echo thetelos_book_card( get_the_ID() ); ?>
            <?php endwhile; ?>
        </div>
        <div class="bottompagination">
            <?php
            if ( function_exists( 'wp_bootstrap_pagination' ) ) {
                wp_bootstrap_pagination( [
                    'custom_query'    => $main_q,
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
        <?php
        else :
        ?>
            <p style="color:var(--tls-muted);font-family:var(--tls-sans);">
                <?php _e( 'No posts matched your criteria.', 'mediumish' ); ?>
            </p>
        <?php
        endif;
        wp_reset_postdata();
        ?>
    </div>
</section>
<?php endif; ?>

</main>

<?php get_footer(); ?>
