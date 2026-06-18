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

        <!-- Actions: search + donate + user -->
        <div class="tls-nav-actions">

            <!-- Search toggle -->
            <button class="tls-icon-btn tls-search-btn" data-tls-search aria-label="Search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="17" y1="17" x2="21" y2="21"/>
                </svg>
            </button>

            <!-- Donate CTA -->
            <a class="tls-support-cta" href="<?php echo esc_url( home_url( '/support/' ) ); ?>">
                <svg viewBox="0 0 24 24" fill="currentColor"
                     width="16" height="16" aria-hidden="true">
                    <path d="M9.3349 11.5022L11.5049 11.5027C13.9902 11.5027 16.0049 13.5174 16.0049 16.0027L9.00388 16.0018L9.00488 17.0027L17.0049 17.0019V16.0027C17.0049 14.9202 16.6867 13.8996 16.1188 13.0019L19.0049 13.0027C20.9972 13.0027 22.7173 14.1679 23.521 15.8541C21.1562 18.9747 17.3268 21.0027 13.0049 21.0027C10.2436 21.0027 7.90437 20.4121 6.00447 19.3779L6.00592 10.0737C7.25147 10.2521 8.39122 10.7583 9.3349 11.5022ZM4.00488 9.00268C4.51772 9.00268 4.94039 9.38872 4.99816 9.88606L5.00488 10.0018V19.0027C5.00488 19.555 4.55717 20.0027 4.00488 20.0027H2.00488C1.4526 20.0027 1.00488 19.555 1.00488 19.0027V10.0027C1.00488 9.45039 1.4526 9.00268 2.00488 9.00268H4.00488ZM13.6513 3.57806L14.0046 3.93183L14.3584 3.57806C15.3347 2.60175 16.9177 2.60175 17.894 3.57806C18.8703 4.55437 18.8703 6.13728 17.894 7.11359L14.0049 11.0027L10.1158 7.11359C9.13948 6.13728 9.13948 4.55437 10.1158 3.57806C11.0921 2.60175 12.675 2.60175 13.6513 3.57806Z"/>
                </svg>
                Donate
            </a>

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

            <!-- Hamburger (mobile only) -->
            <button class="tls-hamburger" id="tls-hamburger"
                    aria-label="Open menu" aria-expanded="false" aria-controls="tls-mobile-menu">
                <span class="tls-hamburger-bar"></span>
                <span class="tls-hamburger-bar"></span>
                <span class="tls-hamburger-bar"></span>
            </button>

        </div>

    </div><!-- /.tls-header-inner -->
</header>

<!-- Mobile drawer -->
<div class="tls-mobile-menu" id="tls-mobile-menu" aria-hidden="true">
    <div class="tls-mobile-menu-inner">

        <!-- Nav links -->
        <nav class="tls-mobile-menu-nav" aria-label="Mobile navigation">
            <?php
            wp_nav_menu( [
                'theme_location' => 'primary',
                'container'      => false,
                'items_wrap'     => '%3$s',
                'fallback_cb'    => false,
                'walker'         => new class extends Walker_Nav_Menu {
                    public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
                        $classes = ( is_array( $data_object->classes ) ? $data_object->classes : [] );
                        $active  = in_array( 'current-menu-item', $classes, true ) ? ' current' : '';
                        $output .= '<a href="' . esc_url( $data_object->url ) . '" class="tls-mobile-menu-link' . esc_attr( $active ) . '">';
                        $output .= esc_html( $data_object->title );
                        $output .= '</a>';
                    }
                },
            ] );
            ?>
            <?php if ( ! has_nav_menu( 'primary' ) ) : ?>
                <a class="tls-mobile-menu-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">Archive</a>
                <a class="tls-mobile-menu-link" href="<?php echo esc_url( home_url( '/authors/' ) ); ?>">Authors</a>
            <?php endif; ?>
        </nav>

        <div class="tls-mobile-menu-divider"></div>

        <!-- User section -->
        <?php if ( is_user_logged_in() ) :
            $curr = wp_get_current_user();
        ?>
        <div class="tls-mobile-menu-user">
            <div class="tls-mobile-menu-user-info">
                <span class="tls-mobile-menu-avatar"><?php echo esc_html( strtoupper( substr( $curr->display_name, 0, 1 ) ) ); ?></span>
                <div>
                    <div class="tls-mobile-menu-user-name"><?php echo esc_html( $curr->display_name ); ?></div>
                    <div class="tls-mobile-menu-user-email"><?php echo esc_html( $curr->user_email ); ?></div>
                </div>
            </div>
            <a class="tls-mobile-menu-link" href="<?php echo esc_url( home_url( '/profile/' ) ); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                My Library
            </a>
            <a class="tls-mobile-menu-link" href="<?php echo esc_url( home_url( '/profile/?tab=settings' ) ); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                Settings
            </a>
            <a class="tls-mobile-menu-link tls-mobile-menu-signout"
               href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                Sign Out
            </a>
        </div>
        <?php else : ?>
        <div class="tls-mobile-menu-user">
            <a class="tls-mobile-menu-signin" href="#" id="tls-mobile-signin">
                <span class="tls-mobile-menu-signin-avatar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="20" height="20" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </span>
                Sign In
            </a>
        </div>
        <?php endif; ?>

    </div><!-- /.tls-mobile-menu-inner -->
</div><!-- /.tls-mobile-menu -->

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
