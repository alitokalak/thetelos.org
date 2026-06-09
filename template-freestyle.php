<?php
/**
 * Template Name: Free Style
 * Template Post Type: post, page, product
 *
 * A custom page template without sidebar.
 *
 * @package Mediumish
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

get_header(); ?>
<div class="container">
    <div class="row justify-content-center listrecent listrelated">
        <div class="col-md-12">
			<?php
			while ( have_posts() ) : the_post(); ?>
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>                    
                    <div class="entry-content">
                        <?php
                            the_content();
                            wp_link_pages( array(
                                'before' => '<div class="page-links">' . __( 'Pages:', 'mediumish' ),
                                'after'  => '</div>',
                            ) );
                        ?>
                    </div><!-- .entry-content -->
                </article><!-- #post-## -->            
				<?php // If comments are open or we have at least one comment, load up the comment template.
				if ( comments_open() || get_comments_number() ) :
					comments_template();
				endif;
			endwhile; // End of the loop.
			?>
		</div>
	</div>
</div>
<?php get_footer();