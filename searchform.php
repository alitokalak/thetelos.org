<?php
/**
 * Template for displaying search forms
 *
 * @package Mediumish
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
<input type="hidden" name="post_type" value="post">
<input type="search" class="search-field"
placeholder="<?php echo esc_attr_x( 'Search books & authors', 'placeholder', 'mediumish' ) ?>"
value="<?php echo get_search_query() ?>" name="s"
title="<?php echo esc_attr_x( 'Search for:', 'label', 'mediumish' ) ?>" />
<button type="submit" class="search-submit" aria-label="Search">
<i class="fa fa-search"></i>
</button>
</form>