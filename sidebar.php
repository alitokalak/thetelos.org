<?php
/**
 * The sidebar containing the main widget area
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package Mediumish
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

	<div id="sidebarposts" class="widget-area" role="complementary">
		<?php if ( ! dynamic_sidebar( 'sidebar-posts' ) ) :
		endif; ?>
	</div>