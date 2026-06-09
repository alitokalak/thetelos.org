<?php
/**
 * The template for displaying author archive pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Mediumish
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

get_header();
$twitter = get_the_author_meta('twitter');
$facebook = get_the_author_meta('facebook');
$youtube = get_the_author_meta('youtube');
$location = get_the_author_meta('location');
$urlposts = get_author_posts_url(get_the_author_meta('ID'), get_the_author_meta('user_nicename'));
$email = get_the_author_meta('user_email');
$get_author_id = get_the_author_meta('ID');
$get_author_gravatar = get_avatar($get_author_id);
$authorheaderbg = get_theme_mod('bg_authorpage');
?>


<div class="mainheading homecover forauthor" <?php if ($authorheaderbg) { ?> style="background-image:url(<?php echo $authorheaderbg; ?>);" <?php } ?>>
    <div class="row post-top-meta authorpage  justify-content-md-center  justify-content-lg-center">
        <div class="col-md-6 text-center">
            <h1><?php echo get_the_author_meta('display_name'); ?></h1>
            <i class="fa fa-globe"></i>&nbsp; <?php echo $location; ?> <span class="bull">&bull;</span>
            <a target="_blank" href="<?php echo esc_url(get_the_author_meta('user_url')); ?>">
                <?php echo esc_url(get_the_author_meta('user_url')); ?></a>
            <span class="author-description text-white mt-3 mb-3 d-block"><?php echo get_the_author_meta('description'); ?></span>
            <p class="d-block">

                <?php if ($facebook) { ?>
                    <a target="_blank" href="<?php echo esc_url($facebook); ?>"><i class="fab fa-facebook"></i></a> &nbsp;
                <?php } ?>

                <?php if ($twitter) { ?>
                    <a target="_blank" href="<?php echo esc_url($twitter); ?>"><i class="fab fa-twitter"></i></a> &nbsp;
                <?php } ?>

                <?php if ($youtube) { ?>
                    <a target="_blank" href="<?php echo esc_url($youtube); ?>"><i class="fab fa-youtube"></i></a> &nbsp;
                <?php } ?>

                <?php if ($urlposts) { ?>
                    <a target="_blank" href="<?php echo esc_url($urlposts); ?>feed/"><i class="fa fa-rss"></i></a> &nbsp;
                <?php } ?>

                <?php if ($email) { ?>
                    <a href="mailto:<?php echo $email; ?>"><i class="fa fa-send-o"></i></a> &nbsp;
                <?php } ?>

            </p>

            <p class="margbotneg100"><?php echo $get_author_gravatar; ?></p>

        </div>
    </div>
</div>

<br /><br />


<div class="container">

    <section class="recent-posts">
        <?php if (have_posts()) : ?>
            <div class="row listrecent justify-content-md-center">
                <div class="col-md-8">

                    <div class="section-title text-center">
                        <h2><?php echo the_author_posts(); ?> <?php _e('Stories by', 'mediumish'); ?> <span> <?php the_archive_title() ?></span></h2>
                    </div>

                    <!-- begin post -->
                    <?php while (have_posts()) : the_post(); ?>
                        <?php echo mediumish_authorpostbox(); ?>
                    <?php endwhile; ?>
                    <!-- end post -->

                    <!-- pagination -->
                    <div class="bottompagination mt-4">
                        <?php wp_bootstrap_pagination(array(
                            'previous_string' => '<i class="fa fa-angle-double-left"></i>',
                            'next_string' => '<i class="fa fa-angle-double-right"></i>',
                            'before_output' => '<span class="navigation">',
                            'after_output' => '</span>'
                        )); ?>
                    </div>
                </div>
            </div>
        <?php else : ?>
            <p><?php _e('Sorry, no posts matched your criteria.', 'mediumish'); ?></p>
        <?php endif; ?>

    </section>

</div>
<!-- /.container -->

<?php get_footer(); ?>