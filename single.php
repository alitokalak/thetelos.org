<?php
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package Mediumish
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

get_header();
$disableauthorbox = get_theme_mod('disable_authorbox_sectionarticles');
$disablereadingtime = get_theme_mod('disable_readingtime_sectionarticles');
$disableshare = get_theme_mod('disable_share_sectionarticles');
$disabledate = get_theme_mod('disable_date_sectionarticles');
$disablerp = get_theme_mod('disable_rp_sectionarticles');
$disablecats = get_theme_mod('disable_cats_sectionarticles');
$disabletags = get_theme_mod('disable_tags_sectionarticles');
$disablebottomalert = get_theme_mod('disable_bottomalert_sectionarticles');
$mediumishbottomalert = get_theme_mod('mediumish_bottomalert');
?>

<main id="main" role="main">

<div class="container">
    <div class="row">
        <div class=" col-md-2">
            <div class="share">

                <div class="sidebarapplause">
                    <?php
                    if (class_exists('WPClapsApplause')) {
                        echo do_shortcode('[wp_claps_applause ]');
                    }
                    ?>
                </div>

                <?php if ($disableshare == 0) { ?>
                    <p class="sharecolour">
                        <?php _e('Share', 'mediumish'); ?>
                    </p>
                    <?php mediumish_share_post(); ?>
                <?php } ?>


                <?php if (comments_open() || get_comments_number()) : ?>
                    <div class="sep"></div>
                    <div class="hidden-xs-down">

                        <p>
                            <?php _e('Reply', 'mediumish'); ?>
                        </p>
                        <ul>
                            <li>
                                <a class="smoothscroll" href="#comments"><?php printf(_nx('1', '%1$s', get_comments_number(), 'comments title', 'mediumish'), number_format_i18n(get_comments_number()), get_the_title()); ?><br />
                                    <svg class="svgIcon-use" width="29" height="29" viewBox="0 0 29 29">
                                        <path d="M21.27 20.058c1.89-1.826 2.754-4.17 2.754-6.674C24.024 8.21 19.67 4 14.1 4 8.53 4 4 8.21 4 13.384c0 5.175 4.53 9.385 10.1 9.385 1.007 0 2-.14 2.95-.41.285.25.592.49.918.7 1.306.87 2.716 1.31 4.19 1.31.276-.01.494-.14.6-.36a.625.625 0 0 0-.052-.65c-.61-.84-1.042-1.71-1.282-2.58a5.417 5.417 0 0 1-.154-.75zm-3.85 1.324l-.083-.28-.388.12a9.72 9.72 0 0 1-2.85.424c-4.96 0-8.99-3.706-8.99-8.262 0-4.556 4.03-8.263 8.99-8.263 4.95 0 8.77 3.71 8.77 8.27 0 2.25-.75 4.35-2.5 5.92l-.24.21v.32c0 .07 0 .19.02.37.03.29.1.6.19.92.19.7.49 1.4.89 2.08-.93-.14-1.83-.49-2.67-1.06-.34-.22-.88-.48-1.16-.74z"></path>
                                    </svg>
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (have_posts()) : ?>
            <?php while (have_posts()) : the_post(); ?>
                <div <?php post_class('col-md-8  flex-first flex-md-unordered position-relative'); ?> id="post-<?php the_ID(); ?>">
                    <div class="mainheading">

                        <?php if ($disableauthorbox == 0) { ?>
                            <!-- Begin Author box -->
                            <div class="post-top-meta hidden-md-down">
                                <div>
                                    <a href="<?php echo get_author_posts_url($post->post_author); ?>">
                                        <?php echo get_avatar(get_the_author_meta('user_email'), '42', null, null, array('class' => array('imgavt'))); ?>
                                    </a>
                                </div>
                                <div class="col-md-10 col-xs-12">
                                    <a class="text-capitalize link-dark" href="<?php echo get_author_posts_url(get_the_author_meta('ID'), get_the_author_meta('user_nicename')); ?>">
                                        <?php the_author(); ?> <span class="btn follow"><?php _e('Follow', 'mediumish'); ?></span></a>
                                    <span class="author-description d-block"><?php the_author_meta('description'); ?></span>
                                </div>
                            </div>
                        <?php } ?>

                        <?php
                        if (function_exists('yoast_breadcrumb')) {
                            yoast_breadcrumb('
                        <p id="breadcrumbs">', '</p>
                        ');
                        }
                        ?>

                        <h1 class="posttitle">
                            <?php the_title(); ?>
                            <?php
                            $yazarlar = get_the_terms(get_the_ID(), 'authors');
                            if (!empty($yazarlar) && !is_wp_error($yazarlar)) :
                                $yazar = $yazarlar[0];
                            ?>
                            <a href="<?php echo esc_url(get_term_link($yazar)); ?>" style="font-size:0.45em;vertical-align:middle;display:inline-block;margin-left:8px;color:#00ab6b;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'"><?php echo esc_html($yazar->name); ?></a>
                            <?php endif; ?>
                        </h1>
						<?php
						$yazarlar = get_the_terms(get_the_ID(), 'authors');
						if (!empty($yazarlar) && !is_wp_error($yazarlar)) {
							$yazar = $yazarlar[0];
							echo '<h5 style="display:inline-flex; align-items:center; gap:10px; flex-wrap:wrap;">';
                    $thetelos_analysis_obj = function_exists('thetelos_get_analysis_for_post') ? thetelos_get_analysis_for_post( get_the_ID() ) : null;
                    if ( $thetelos_analysis_obj ) {
                        $thetelos_analysis_url = get_permalink( $thetelos_analysis_obj->ID );
                        echo '<a id="analysis-trigger" href="' . esc_url( $thetelos_analysis_url ) . '" class="analysis-trigger-btn" aria-haspopup="dialog">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="13" height="13">
                                <path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                            </svg>
                            Deep Analysis
                        </a>';
                    }
                    echo '<span class="thetelos-action-btns">
                        <a href="#" class="thetelos-action-btn" onclick="window.print(); return false;" title="Print">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/>
                            </svg>
                        </a>
                        <a href="#" class="thetelos-action-btn" onclick="window.print(); return false;" title="Save as PDF">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <path d="M14 2v6h6M12 18v-6M9 15l3 3 3-3"/>
                            </svg>
                        </a>
                    </span>';
                    echo '</h5>';
						}
						?>

                        <p>
                            <?php if ($disabledate == 0) { ?>
                                <span class="post-date"><time class="post-date">
                                        <?php echo get_the_date(); ?>
                                    </time></span>
                            <?php } ?>

                            <?php if ($disablereadingtime == 0) { ?>
                                <span class="readingtime"><?php echo mediumish_estimated_reading_time() ?></span>
                            <?php } ?>
                        </p>
                    </div>

                    <?php
                    $hide_featimg =  get_post_meta(get_the_ID(), "hide_featured_image_hide_featured_image_on_post", true);
                    if (!$hide_featimg) {
                        if (has_post_thumbnail()) {
                            the_post_thumbnail('large', array(
                                'class' => 'featured-image img-fluid'
                            ));
                        }
                    }
                    ?>
                    <article class="article-post">
                        <?php wtn_ad_block_top_article(); ?>
                        <?php the_content(); ?>
                        <div class="clearfix mb-0"></div>
                        <?php wp_link_pages(array(
                            'before' => '<div class="page-links pagination align-items-center"><span class="page-label">' . esc_html__('Pages', 'mediumish') . '</span>',
                            'after'  => '</div>',
                            'link_before' => '<span class="page-link">',
                            'link_after'  => '</span>',
                        ));
                        ?>
                        <div class="clearfix mb-0"></div>
                    </article>

                    <?php wtn_ad_block_bottom_article(); ?>
                    <div class="clearfix"></div>

                    <?php if ($disableauthorbox == 0) { ?>
                        <!-- Begin Author box -->
                        <div class="row post-top-meta hidden-lg-up">
                            <div class="col-md-2 col-xs-4">
                                <a href="<?php echo get_author_posts_url($post->post_author); ?>">
                                    <?php echo get_avatar(get_the_author_meta('user_email'), '72', null, null, array('class' => array('imgavt'))); ?>
                                </a>
                            </div>
                            <div class="col-md-10 col-xs-8">
                                <a class="text-capitalize link-dark" href="<?php echo get_author_posts_url(get_the_author_meta('ID'), get_the_author_meta('user_nicename')); ?>">
                                    <?php the_author(); ?> <span class="btn follow"><?php _e('Follow', 'mediumish'); ?></span></a>
                                <span class="author-description d-block"><?php the_author_meta('description'); ?></span>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="after-post-tags">
                        <?php if ($disablecats == 0) { ?>
                            <?php the_category(); ?>
                        <?php } ?>
                        <?php if ($disabletags == 0) { ?>
                            <ul class="post-categories aretags">
                                <?php
                                if (is_array(get_the_tags($post->ID)) || is_object(get_the_tags($post->ID))) :
                                    foreach (get_the_tags($post->ID) as $tag) {
                                        echo '<li><a href="' . get_tag_link($tag->term_id) . '">#' . $tag->name . '</a></li> ';
                                    }
                                endif;
                                ?>
                            </ul>
                        <?php } ?>
                    </div>


                    <div class="row mb-5 prevnextlinks justify-content-center align-items-center">
                        <div class="col-md-6 col-xs-12 rightborder pl-0">
                            <div class="thepostlink"><?php previous_post_link(); ?></div>
                        </div>
                        <div class="col-md-6 col-xs-12 text-right pr-0">
                            <div class="thepostlink"><?php next_post_link(); ?></div>
                        </div>
                    </div>

                </div>

            <?php endwhile; ?>

        <?php else : ?>
            <p><?php _e('Sorry, no posts matched your criteria.', 'mediumish'); ?></p>
        <?php endif; ?>



    </div>
</div>

<div class="hideshare"></div>

<?php if ($disablerp == 0) { ?>
    <div class="graybg">
        <div class="container">
            <?php echo mediumish_related_posts() ?>
            <?php
            if (comments_open() || get_comments_number()) :
                comments_template();
            endif;
            ?>
        </div>
    </div>
<?php } ?>

<?php if ($disablebottomalert == 0) { ?>
    <div class="alertbar">
        <div class="container text-center">
            <?php echo do_shortcode($mediumishbottomalert); ?>
        </div>
    </div>
<?php } ?>
	
</main>

<?php get_footer(); ?>