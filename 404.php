<?php

/**
 * The template for displaying 404 pages (not found)
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package Mediumish
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

get_header();
?>

<div class="container"> 
    <div class="row justify-content-center listrecent listrelated align-items-center">
        <div class="col-md-12 align-items-center text-center">
			<br/><br/><br/>
			<h1>404</h1>
			<h1 class="page-title"><?php _e('Page not Found', 'mediumish'); ?></h1>
			<div class="page-content">
			<p><?php _e('The page you are looking for does not exist. Maybe try a search?', 'mediumish'); ?></p>
			<br/>
			<?php get_search_form(); ?>
		</div>
		</div>
	</div>
</div>

<?php get_footer();
