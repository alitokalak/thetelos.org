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

        <!-- Donate CTA -->
        <a class="tls-support-cta" href="<?php echo esc_url( home_url( '/support/' ) ); ?>">
            <svg viewBox="0 0 24 24" fill="currentColor"
                 width="14" height="14" aria-hidden="true">
                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
            </svg>
            Donate
        </a>

        <!-- Actions: search + user -->
        <div class="tls-nav-actions">

            <!-- Search toggle -->
            <button class="tls-icon-btn tls-search-btn" data-tls-search aria-label="Search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
            </button>

            <?php if ( is_user_logged_in() ) :
                $curr = wp_get_current_user();
                $first_name = $curr->first_name ?: explode(' ', $curr->display_name)[0];
            ?>
            <div class="tls-user-menu" id="tls-user-menu">
                <button class="tls-user-menu-trigger" id="tls-user-trigger" type="button" aria-haspopup="true" aria-expanded="false">
                    <span class="tls-user-avatar"><?php echo esc_html(strtoupper(substr($curr->display_name,0,1))); ?></span>
                    <span class="tls-user-name"><?php echo esc_html($first_name); ?></span>
                    <svg class="tls-user-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="tls-user-dropdown" id="tls-user-dropdown" role="menu">
                    <div class="tls-user-dropdown-header">
                        <span class="tls-user-dropdown-name"><?php echo esc_html($curr->display_name); ?></span>
                        <span class="tls-user-dropdown-email"><?php echo esc_html($curr->user_email); ?></span>
                    </div>
                    <div class="tls-user-dropdown-divider"></div>
                    <a class="tls-user-dropdown-item" href="<?php echo esc_url(home_url('/profile/')); ?>" role="menuitem">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                        My Library
                    </a>
                    <a class="tls-user-dropdown-item" href="<?php echo esc_url(home_url('/profile/?tab=settings')); ?>" role="menuitem">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                        Settings
                    </a>
                    <div class="tls-user-dropdown-divider"></div>
                    <button class="tls-user-dropdown-item tls-user-dropdown-item--danger" id="tls-header-signout" type="button" role="menuitem">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                        Sign Out
                    </button>
                </div>
            </div>
            <?php else : ?>
            <a class="tls-icon-btn" href="#" id="tls-user-icon" aria-label="Sign In">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </a>
            <?php endif; ?>

        </div>

    </div><!-- /.tls-header-inner -->
</header>

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
