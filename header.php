<?php
/**
 * The Telos — Header Template
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- ══════════════════════════════════
     THE TELOS NAVIGATION
══════════════════════════════════ -->
<header class="tls-header" role="banner">
    <div class="tls-header-inner">

        <!-- Logo -->
        <a class="tls-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
            <?php
            $logo_url = get_theme_mod( 'logo_sectionlogonav' );
            if ( $logo_url ) :
            ?>
                <img src="<?php echo esc_url( $logo_url ); ?>"
                     alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
                     style="height:32px;width:auto;">
            <?php else : ?>
                <?php bloginfo( 'name' ); ?>
            <?php endif; ?>
        </a>

        <!-- Primary navigation links -->
        <nav class="tls-nav-links" aria-label="Primary">
            <?php
            wp_nav_menu( [
                'theme_location'  => 'primary',
                'container'       => false,
                'items_wrap'      => '%3$s',
                'fallback_cb'     => false,
                'walker'          => new class extends Walker_Nav_Menu {
                    public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
                        $classes  = ( is_array( $data_object->classes ) ? $data_object->classes : [] );
                        $active   = in_array( 'current-menu-item', $classes, true ) ? ' current' : '';
                        $output  .= '<a href="' . esc_url( $data_object->url ) . '" class="' . esc_attr( trim( $active ) ) . '">';
                        $output  .= esc_html( $data_object->title );
                        $output  .= '</a>';
                    }
                },
            ] );
            ?>
            <?php if ( ! has_nav_menu( 'primary' ) ) : ?>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Archive</a>
                <a href="<?php echo esc_url( home_url( '/authors/' ) ); ?>">Authors</a>
            <?php endif; ?>
        </nav>

        <!-- Actions: search + user -->
        <div class="tls-nav-actions">

            <!-- Search toggle -->
            <button class="tls-icon-btn" data-tls-search aria-label="Search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
            </button>

            <?php if ( is_user_logged_in() ) : ?>
                <a class="tls-icon-btn" href="<?php echo esc_url( get_edit_profile_url() ); ?>" aria-label="Account">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </a>
            <?php else : ?>
                <a class="tls-icon-btn" href="<?php echo esc_url( wp_login_url() ); ?>" aria-label="Login">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </a>
            <?php endif; ?>

        </div>

        <!-- Mobile hamburger -->
        <button class="tls-mobile-toggle" aria-label="Toggle menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

    </div><!-- /.tls-header-inner -->
</header>

<!-- Mobile nav drawer -->
<nav class="tls-mobile-nav" aria-label="Mobile navigation">
    <?php
    wp_nav_menu( [
        'theme_location'  => 'primary',
        'container'       => false,
        'items_wrap'      => '%3$s',
        'fallback_cb'     => false,
        'walker'          => new class extends Walker_Nav_Menu {
            public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
                $output .= '<a href="' . esc_url( $data_object->url ) . '">' . esc_html( $data_object->title ) . '</a>';
            }
        },
    ] );
    ?>
    <a href="<?php echo esc_url( get_search_link() ); ?>">Search</a>
</nav>

<!-- Search overlay -->
<div class="tls-search-overlay" role="dialog" aria-label="Search" aria-modal="true">
    <button class="tls-search-close" aria-label="Close search">&#x2715;</button>
    <div class="tls-search-overlay-inner">
        <form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <input type="search"
                   name="s"
                   placeholder="Search by title, author, or concept…"
                   value="<?php echo esc_attr( get_search_query() ); ?>"
                   autocomplete="off"
                   aria-label="Search">
            <button type="submit">Search</button>
        </form>
    </div>
</div>

<!-- Site content begins -->
<div class="site-content">
