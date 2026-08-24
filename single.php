<?php
/**
 * The Telos — Single Book Post Template
 *
 * Layout: Sticky sidebar (cover + controls) | Flowing right (meta + article)
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$disable_rp    = get_theme_mod( 'disable_rp_sectionarticles' );
$disable_share = get_theme_mod( 'disable_share_sectionarticles' );
$disable_tags  = get_theme_mod( 'disable_tags_sectionarticles' );
$disable_cats  = get_theme_mod( 'disable_cats_sectionarticles' );
$bottom_alert  = get_theme_mod( 'mediumish_bottomalert' );
$disable_alert = get_theme_mod( 'disable_bottomalert_sectionarticles' );
?>

<main id="main" role="main">

<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>

<?php
$post_id      = get_the_ID();
$raw_authors  = get_the_terms( $post_id, 'authors' );
$book_author  = ( ! empty( $raw_authors ) && ! is_wp_error( $raw_authors ) ) ? $raw_authors[0] : null;
$cats         = get_the_category( $post_id );
$analysis     = function_exists( 'thetelos_get_analysis_for_post' ) ? thetelos_get_analysis_for_post( $post_id ) : null;
$analysis_url = $analysis ? get_permalink( $analysis->ID ) : '';
$hide_featimg = get_post_meta( $post_id, 'hide_featured_image_hide_featured_image_on_post', true );
$rating_data  = function_exists( 'thetelos_get_rating' ) ? thetelos_get_rating( $post_id ) : [ 'avg' => 0, 'count' => 0 ];
$rating_avg   = round( (float) $rating_data['avg'], 1 );
$rating_cnt   = (int) $rating_data['count'];
$reading_time = function_exists( 'thetelos_post_reading_time' ) ? thetelos_post_reading_time( $post_id ) : mediumish_estimated_reading_time();
?>

<!-- ══════════════════════════════════
     SINGLE PAGE WRAPPER
══════════════════════════════════ -->
<div class="tls-single-wrap" data-tls-post-id="<?php echo (int) $post_id; ?>">
    <div class="container">

        <!-- Back link -->
        <a class="tls-back-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Archive
        </a>

        <div class="tls-single-grid">

            <!-- ── LEFT: Sticky Sidebar ── -->
            <aside class="tls-single-sidebar">

                <!-- Book cover -->
                <div class="tls-book-cover-shadow">
                    <?php if ( has_post_thumbnail() && ! $hide_featimg ) : ?>
                        <?php the_post_thumbnail( 'medium', [ 'alt' => esc_attr( get_the_title() ) ] ); ?>
                    <?php else : ?>
                        <?php echo thetelos_render_book_cover( $post_id ); ?>
                    <?php endif; ?>
                </div>

                <?php if ( $disable_share == 0 ) : ?>
                <!-- Share buttons -->
                <div class="tls-single-share">
                    <?php
                    $share_url   = urlencode( get_permalink() );
                    $share_title = rawurlencode( get_the_title() );
                    $tw_url = 'https://twitter.com/intent/tweet?text=' . $share_title . '&url=' . $share_url;
                    $fb_url = 'https://www.facebook.com/sharer/sharer.php?u=' . $share_url;
                    $wa_url = 'https://api.whatsapp.com/send?text=' . $share_title . '%20' . $share_url;
                    $li_url = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $share_url;
                    ?>
                    <a href="<?php echo esc_url( $tw_url ); ?>" target="_blank" rel="noopener" class="tls-icon-btn tls-share-btn" title="Share on X">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <a href="<?php echo esc_url( $fb_url ); ?>" target="_blank" rel="noopener" class="tls-icon-btn tls-share-btn" title="Share on Facebook">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener" class="tls-icon-btn tls-share-btn" title="Share on WhatsApp">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    </a>
                    <a href="<?php echo esc_url( $li_url ); ?>" target="_blank" rel="noopener" class="tls-icon-btn tls-share-btn" title="Share on LinkedIn">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Rating -->
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
                    <div class="tls-rating-count" style="margin-left:0;display:block;margin-top:4px;">
                        <span class="tls-rating-avg"><?php echo $rating_avg > 0 ? number_format( $rating_avg, 1 ) : '—'; ?></span>
                        <?php if ( $rating_cnt > 0 ) : ?>
                        (<?php echo $rating_cnt; ?> rating<?php echo $rating_cnt !== 1 ? 's' : ''; ?>)
                        <?php endif; ?>
                    </div>
                    <div class="tls-rating-msg"></div>
                </div>

                <?php
                // ── Affiliate CTA (sidebar): tam genişlik Buy dropdown'u ──
                if ( function_exists( 'tls_buy_dropdown' ) ) {
                    echo '<div style="width:100%;margin:0 0 10px">';
                    tls_buy_dropdown( $post_id, 'side' );
                    echo '</div>';
                }
                ?>

                <!-- Reading status -->
                <div class="tls-read-status" role="group" aria-label="Reading status"
                     data-post-id="<?php echo (int)$post_id; ?>">
                    <?php
                    $user_status = '';
                    if ( is_user_logged_in() ) {
                        $user_status = get_user_meta(get_current_user_id(), '_tls_reading_status_' . $post_id, true) ?: '';
                    }
                    ?>
                    <button class="tls-status-btn<?php echo $user_status === 'want' ? ' active' : ''; ?>"
                            data-status="want" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
                        Want to Read
                    </button>
                    <button class="tls-status-btn<?php echo $user_status === 'reading' ? ' active' : ''; ?>"
                            data-status="reading" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
                        Currently Reading
                    </button>
                    <button class="tls-status-btn<?php echo $user_status === 'read' ? ' active' : ''; ?>"
                            data-status="read" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                        Read
                    </button>
                </div>

            </aside>

            <!-- ── RIGHT: Meta + Article (continuous flow) ── -->
            <div class="tls-single-content">

                <!-- Breadcrumbs: Home › Authors › Yazar -->
                <?php if ( function_exists( 'thetelos_breadcrumbs' ) ) thetelos_breadcrumbs(); ?>

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
                <a class="tls-book-author-link" href="<?php echo esc_url( get_term_link( $book_author ) ); ?>">
                    <?php echo esc_html( $book_author->name ); ?>
                </a>
                <?php endif; ?>

                <!-- Meta bar -->
                <div class="tls-book-meta-bar">
                    <div class="tls-meta-item">
                        <span class="tls-meta-label">Reading time</span>
                        <span class="tls-meta-value"><?php echo esc_html( $reading_time ); ?></span>
                    </div>
                    <div class="tls-meta-item">
                        <span class="tls-meta-label">Published</span>
                        <span class="tls-meta-value"><?php
                            $tls_py = get_post_meta( get_the_ID(), '_tls_pub_year', true );
                            if ( preg_match( '/^\d{3,4}$/', (string) $tls_py ) ) {
                                echo esc_html( $tls_py );          // kitabın yayın yılı
                            } elseif ( $tls_py === '-' ) {
                                echo '(&ndash;)';                  // OpenLibrary'de bulunamadı
                            } else {
                                echo esc_html( get_the_date( 'M Y' ) ); // henüz taranmamış eski post
                            }
                        ?></span>
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

                <!-- Action buttons -->
                <?php
                // YER-TUTUCU tespiti: gövdesi "… is being prepared …" olan (henüz
                // içeriği yazılmamış) yazılarda Print / Save PDF gösterme — basacak
                // gerçek içerik yok, aldatıcı olmasın.
                $tls_is_placeholder = ( strpos(
                    (string) get_post_field( 'post_content', get_the_ID() ),
                    'is being prepared and will be published here soon'
                ) !== false );
                ?>
                <div class="tls-post-actions">
                    <?php if ( $analysis ) : ?>
                    <a class="tls-analysis-btn" id="analysis-trigger"
                       href="<?php echo esc_url( $analysis_url ); ?>"
                       aria-haspopup="dialog">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="15" height="15" aria-hidden="true">
                            <path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                        </svg>
                        Deep Analysis
                    </a>
                    <?php endif; ?>

                    <?php if ( ! $tls_is_placeholder ) : ?>
                    <!-- Print post (POST içeriğini basar) — yer-tutucuda gizli -->
                    <button class="tls-status-btn" id="tls-print-post" type="button" title="Print this summary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
                        Print
                    </button>

                    <!-- PDF indir (POST içeriğini basar) — yer-tutucuda gizli -->
                    <button class="tls-status-btn" id="tls-pdf-post" type="button" title="Save as PDF">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M12 18v-6M9 15l3 3 3-3"/></svg>
                        Save PDF
                    </button>
                    <?php endif; ?>

                    <?php
                    // ── Affiliate CTA: Buy dropdown (Amazon / Kindle / Audible) ──
                    $tls_has_buy = function_exists( 'tls_retailer_urls' ) && ! empty( tls_retailer_urls( get_the_ID() ) );
                    if ( $tls_has_buy ) {
                        tls_buy_dropdown( get_the_ID(), 'inline' );
                    }
                    ?>
                </div>
                <?php if ( ! empty( $tls_has_buy ) ) : ?>
                <p class="tls-affiliate-note" style="font-family:var(--tls-sans);font-size:11px;color:var(--tls-muted);margin:8px 0 0">As an Amazon Associate, The Telos earns from qualifying purchases.</p>
                <?php endif; ?>


                <!-- ── Article content (flows directly below meta) ── -->
                <div class="tls-single-divider"></div>

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

                <!-- ── Key Quotes ── -->
                <?php
                $quotes = function_exists('tls_get_quotes') ? tls_get_quotes($post_id) : [];
                if ( ! empty($quotes) ) :
                    $book_title  = get_the_title();
                    $author_name = $book_author ? $book_author->name : '';
                ?>
                <div class="tls-quotes-section">
                    <div class="tls-quotes-header">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18" style="color:var(--tls-gold);flex-shrink:0;"><path d="M4.583 17.321C3.553 16.227 3 15 3 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5c-1.073 0-2.099-.49-2.748-1.179zm10 0C13.553 16.227 13 15 13 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5c-1.073 0-2.099-.49-2.748-1.179z"/></svg>
                        <span>Key Passages</span>
                    </div>

                    <div class="tls-quotes-grid">
                    <?php foreach ( $quotes as $qi => $quote ) :
                        if ( empty($quote['text']) ) continue;
                        $qtext   = $quote['text'];
                        $qsource = $quote['source'] ?? '';
                    ?>
                        <div class="tls-quote-card" data-quote="<?php echo esc_attr($qtext); ?>"
                             data-author="<?php echo esc_attr($author_name); ?>"
                             data-book="<?php echo esc_attr($book_title); ?>">
                            <div class="tls-quote-mark">&ldquo;</div>
                            <p class="tls-quote-text"><?php echo esc_html($qtext); ?></p>
                            <?php if ($qsource) : ?>
                            <p class="tls-quote-source"><?php echo esc_html($qsource); ?></p>
                            <?php endif; ?>
                            <div class="tls-quote-actions">
                                <button class="tls-quote-share-btn" type="button" title="Share this quote"
                                        onclick="tlsOpenQuoteShare(this)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                                    Share Quote
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; // quotes ?>

                <!-- Tags -->
                <?php if ( $disable_tags == 0 || $disable_cats == 0 ) : ?>
                <div class="after-post-tags">
                    <?php if ( $disable_cats == 0 ) the_category(); ?>
                    <?php if ( $disable_tags == 0 ) : ?>
                    <ul class="post-categories aretags">
                        <?php $post_tags = get_the_tags( $post_id ); if ( $post_tags && ! is_wp_error( $post_tags ) ) : foreach ( $post_tags as $tag ) : ?>
                        <li><a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>">#<?php echo esc_html( $tag->name ); ?></a></li>
                        <?php endforeach; endif; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Prev / Next -->
                <nav class="tls-prevnext" aria-label="Post navigation">
                    <div>
                        <?php $prev = get_previous_post(); if ( $prev ) : ?>
                        <a class="tls-prevnext-link" href="<?php echo esc_url( get_permalink( $prev ) ); ?>">
                            <span>&larr; Previous</span>
                            <strong><?php echo esc_html( $prev->post_title ); ?></strong>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php $next = get_next_post(); if ( $next ) : ?>
                        <a class="tls-prevnext-link next" href="<?php echo esc_url( get_permalink( $next ) ); ?>">
                            <span>Next &rarr;</span>
                            <strong><?php echo esc_html( $next->post_title ); ?></strong>
                        </a>
                        <?php endif; ?>
                    </div>
                </nav>

                <!-- Comments -->
                <?php if ( comments_open() || get_comments_number() ) comments_template(); ?>

            </div><!-- /.tls-single-content -->

        </div><!-- /.tls-single-grid -->
    </div><!-- /.container -->
</div><!-- /.tls-single-wrap -->

<?php endwhile; endif; ?>

<!-- More by this author (iç linkleme) -->
<?php
if ( $book_author ) :
    $tls_more_by = get_posts( [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 4,
        'post__not_in'   => [ get_the_ID() ],
        'no_found_rows'  => true,
        'tax_query'      => [ [
            'taxonomy' => 'authors',
            'field'    => 'term_id',
            'terms'    => $book_author->term_id,
        ] ],
    ] );
    if ( ! empty( $tls_more_by ) ) : ?>
<div class="tls-related tls-more-author">
    <div class="container">
        <h2 class="tls-related-title">More by <?php echo esc_html( $book_author->name ); ?></h2>
        <div class="tls-books-grid">
            <?php foreach ( $tls_more_by as $tls_mb ) { echo thetelos_book_card( $tls_mb->ID ); } ?>
        </div>
        <p class="tls-more-author-all">
            <a href="<?php echo esc_url( get_term_link( $book_author ) ); ?>">
                All <?php echo (int) $book_author->count; ?> summaries by <?php echo esc_html( $book_author->name ); ?> &rarr;
            </a>
        </p>
    </div>
</div>
<?php endif; endif; ?>

<!-- Related books -->
<?php if ( $disable_rp == 0 ) : ?>
<div class="tls-related">
    <div class="container">
        <h2 class="tls-related-title">You might also enjoy</h2>
        <?php echo mediumish_related_posts( [ 'limit' => 4 ] ); ?>
    </div>
</div>
<?php endif; ?>

</main>

<?php
/* ── Print / PDF scripti — wp_footer'da temiz çalışır, content filter'larından etkilenmez ── */
$tls_print_title  = get_the_title();
$tls_print_author = $book_author ? $book_author->name : '';
add_action( 'wp_footer', function() use ( $tls_print_title, $tls_print_author ) {
    ?>
<script>
(function() {
    var postTitle  = <?php echo json_encode( $tls_print_title ); ?>;
    var postAuthor = <?php echo json_encode( $tls_print_author ); ?>;

    function openPostPrint() {
        var contentEl = document.querySelector('.tls-article-content');
        if (!contentEl) return;
        var win = window.open('', '_blank');
        win.document.write(
            '<!DOCTYPE html><html><head>' +
            '<meta charset="UTF-8">' +
            '<title>' + postTitle + ' \u2014 thetelos.org<\/title>' +
            '<style>' +
            '@page{margin:2cm}' +
            'body{font-family:Georgia,serif;font-size:12pt;line-height:1.8;color:#2a2a2a;max-width:680px;margin:0 auto}' +
            '.tls-label{font-size:9pt;letter-spacing:.2em;text-transform:uppercase;color:#aaa;font-family:sans-serif;margin-bottom:6px}' +
            'h1{font-size:20pt;font-weight:700;color:#1a1a1a;margin:0 0 6px;line-height:1.3}' +
            '.author{font-style:italic;font-size:13pt;color:#555;margin-bottom:28px}' +
            'h2,h3,h4{font-family:Georgia,serif;margin-top:1.6em;margin-bottom:.5em;color:#1a1a1a}' +
            'p{margin-bottom:1.2em}' +
            'blockquote{border-left:3px solid #ddd;margin-left:0;padding-left:20px;color:#555;font-style:italic}' +
            '.tls-footer{margin-top:48px;padding-top:20px;border-top:1px solid #ddd;font-size:10pt;color:#888;font-family:sans-serif;text-align:center}' +
            '<\/style><\/head><body>' +
            '<div class="tls-label">Book Summary \u2014 thetelos.org<\/div>' +
            '<h1>' + postTitle + '<\/h1>' +
            (postAuthor ? '<div class="author">' + postAuthor + '<\/div>' : '') +
            contentEl.innerHTML +
            '<div class="tls-footer">thetelos.org<\/div>' +
            '<\/body><\/html>'
        );
        win.document.close();
        win.focus();
        setTimeout(function(){ win.print(); }, 600);
    }

    var printBtn = document.getElementById('tls-print-post');
    var pdfBtn   = document.getElementById('tls-pdf-post');
    if (printBtn) printBtn.addEventListener('click', openPostPrint);
    if (pdfBtn)   pdfBtn.addEventListener('click', openPostPrint);
})();
</script>
    <?php
} );
?>

<?php get_footer(); ?>
