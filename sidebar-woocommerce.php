<?php
/**
 * The sidebar containing the WooCommerce widget area
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

	<div id="sidebarwoocommerce" class="widget-area" role="complementary">
		<?php if ( ! dynamic_sidebar( 'sidebar-woocommerce' ) ) :
		endif; ?>
	</div>