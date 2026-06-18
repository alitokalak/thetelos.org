<?php

// -----------------------------------------------------
// Theme Version
// -----------------------------------------------------
$theme = wp_get_theme();
define( 'THEME_VERSION', $theme->Version );
// -----------------------------------------------------
// Setup
// -----------------------------------------------------
if ( !function_exists( 'mediumish_setup' ) ) {
    function mediumish_setup() {
        if ( !isset( $content_width ) ) {
            $content_width = 730;
        }
        load_theme_textdomain( 'mediumish', get_template_directory() . '/languages' );
        add_theme_support( 'automatic-feed-links' );
        add_theme_support( 'title-tag' );
        add_theme_support( 'post-thumbnails' );
        add_theme_support( 'woocommerce' );
        register_nav_menus( array(
            'primary' => __( 'Primary Menu', 'mediumish' ),
        ) );
        add_theme_support( 'html5', array(
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption'
        ) );
        add_theme_support( 'wp-block-styles' );
        add_theme_support( 'align-wide' );
        add_theme_support( 'responsive-embeds' );
    }

}
add_action( 'after_setup_theme', 'mediumish_setup' );
// ============================
// KITCHEN START
// ============================
// ============================
// LOAD NECESSARY CLASSES EARLY
// ============================
require_once get_template_directory() . '/inc/wt-plugin-installer.php';
// =========================
// FREEMIUS INTEGRATION
// =========================
if ( !function_exists( 'wow_med_fs' ) ) {
    // Create a helper function for easy SDK access.
    function wow_med_fs() {
        global $wow_med_fs;
        if ( !isset( $wow_med_fs ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/freemius/start.php';
            $wow_med_fs = fs_dynamic_init( array(
                'id'               => '12216',
                'slug'             => 'mediumish',
                'premium_slug'     => 'mediumish',
                'type'             => 'theme',
                'public_key'       => 'pk_294633332ac6fdede3054f98d94d4',
                'is_premium'       => true,
                'is_premium_only'  => true,
                'has_addons'       => false,
                'has_paid_plans'   => true,
                'is_org_compliant' => false,
                'has_affiliation'  => 'selected',
                'menu'             => array(
                    'slug'        => 'mediumish',
                    'support'     => false,
                    'affiliation' => false,
                ),
                'is_live'          => true,
            ) );
        }
        return $wow_med_fs;
    }

    // Init Freemius.
    wow_med_fs();
    // Signal that SDK was initiated.
    do_action( 'wow_med_fs_loaded' );
}
// ==================================
// ADMIN MENU - change slug as needed
// ==================================
add_action( 'admin_menu', 'mediumish_top_lvl_menu' );
function mediumish_top_lvl_menu() {
    add_menu_page(
        'Theme Setup',
        // page title
        'Theme Setup',
        // menu title
        'manage_options',
        // capability
        'mediumish',
        // *** DO NOT CHANGE: preserves Freemius path ***
        'mediumish_admin_page_callback',
        // callback
        'dashicons-admin-generic',
        // icon
        4
    );
}

// ===========================================================
// REQUIRED PLUGIN INSTALLER redirects back to page=theme-slug
// ===========================================================
add_action( 'admin_post_wt_install_plugins', function () {
    if ( !current_user_can( 'install_plugins' ) ) {
        wp_die( 'Insufficient permissions.' );
    }
    check_admin_referer( 'wt_install_plugins' );
    $plugins = [
        [
            'name' => 'One Click Demo Import',
            'slug' => 'one-click-demo-import',
        ],
        [
            'name' => 'Contact Form 7',
            'slug' => 'contact-form-7',
        ],
        [
            'name' => 'Mailchimp for WP',
            'slug' => 'mailchimp-for-wp',
        ],
        [
            'name'   => 'Kirki Wow',
            'slug'   => 'kirki-wow',
            'source' => 'https://public.wowthemes.net/wp-plugins/kirki-wow.zip',
        ],
        [
            'name'   => 'WP Claps',
            'slug'   => 'wp-claps-applause',
            'source' => 'https://public.wowthemes.net/wp-plugins/wp-claps-applause.zip',
        ]
    ];
    // Fully-qualified call (no mid-file `use`)
    if ( class_exists( '\\WT\\PluginInstaller\\Installer' ) ) {
        $results = \WT\PluginInstaller\Installer::install_many( $plugins, true );
    } else {
        $results = [
            'installer' => new WP_Error('wt_installer_missing', 'Installer class not loaded'),
        ];
    }
    set_transient( 'wt_install_results', $results, 60 );
    wp_safe_redirect( admin_url( 'admin.php?page=mediumish' ) );
    // back to YOUR slug
    exit;
} );
/*
 * Notices after redirect — only render on page=mediumish
 */
add_action( 'admin_notices', function () {
    if ( !isset( $_GET['page'] ) || $_GET['page'] !== 'mediumish' ) {
        return;
    }
    $results = get_transient( 'wt_install_results' );
    if ( !$results ) {
        return;
    }
    delete_transient( 'wt_install_results' );
    foreach ( $results as $key => $res ) {
        if ( is_wp_error( $res ) ) {
            printf( '<div class="notice notice-error"><p><strong>%s</strong> failed: %s</p></div>', esc_html( $key ), esc_html( $res->get_error_message() ) );
        } else {
            printf( '<div class="notice notice-success"><p><strong>%s</strong> installed/activated.</p></div>', esc_html( $key ) );
        }
    }
} );
// ===========================================================
// WELCOME PAGE CALLBACK - change slug as needed
// ===========================================================
function mediumish_admin_page_callback() {
    global $wow_med_fs;
    // Respect Freemius whitelabel if present
    if ( false ) {
        echo '<div class="wrap"><p>This page is hidden by white-label.</p></div>';
        return;
    }
    $service_url = 'https://wowthemes.net/theme-installation/';
    $imported = isset( $_GET['imported'] ) && $_GET['imported'] === '1';
    ?>
    <div class="wrap">
        <h1>Welcome to your theme setup!</h1>

        <div class="notice notice-info" style="padding:10px;">
            If this site belongs to your client, you can hide this page and other account data (including contact form) from the “Account” submenu using the white-label option.
        </div>

        <h2>Get Started</h2>
        <ul>
            <li>1. Install and activate the required plugins (WP Claps, Kirki, Mailchimp, Contact Form).</li>
            <li>2. Once the plugins are installed, import the demo content from Appearance → Import Demo Data.</li>
            <li>3. Next, visit Appearance → Customize → Theme Options to set up your site. <a target="_blank" href="https://wowthemes.net/docs/">Docs</a></li>
        </ul>

        <h3>1. Install/activate required plugins</h3>
        <form id="wt-install-plugins-form" method="post" action="<?php 
    echo esc_url( admin_url( 'admin-post.php' ) );
    ?>">
            <?php 
    wp_nonce_field( 'wt_install_plugins' );
    ?>
            <input type="hidden" name="action" value="wt_install_plugins">
            <p><button id="wt-install-plugins-btn" class="button button-primary button-hero">Install Plugins</button></p>
        </form>

        <!-- WAIT PANEL for step 1 — give it a unique id -->
        <div id="wt-install-wait"
            style="display:none;align-items:center;gap:12px;padding:14px 16px;border:1px solid #ccd0d4;border-radius:8px;background:#fff;max-width:620px;">
        <svg width="28" height="28" viewBox="0 0 50 50" aria-hidden="true" focusable="false">
            <circle cx="25" cy="25" r="20" fill="none" stroke-width="6" stroke="#ccd0d4" opacity=".35"></circle>
            <circle cx="25" cy="25" r="20" fill="none" stroke-width="6" stroke="#2271b1" stroke-linecap="round" stroke-dasharray="100" stroke-dashoffset="60">
            <animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="0.9s" repeatCount="indefinite"></animateTransform>
            </circle>
        </svg>
        <div>
            <strong>Please wait…</strong><br>
            This will take a minute. Do not close this window while we install and activate the required plugins.
        </div>
        </div>

        <h3>2. Import Starter Site</h3>
        <?php 
    // If you’re not already in admin, ensure plugin.php is loaded for is_plugin_active()
    // require_once ABSPATH . 'wp-admin/includes/plugin.php';
    if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'one-click-demo-import/one-click-demo-import.php' ) ) {
        ?>
            <a href="<?php 
        echo esc_url( admin_url( 'themes.php?page=one-click-demo-import' ) );
        ?>" class="button button-secondary">
                Go to Demo Importer →
            </a>
        <?php 
    } else {
        ?>
            <p>One Click Demo Import is not yet installed and active.</p>
        <?php 
    }
    ?>

        <!-- WAIT PANEL for step 2 (same markup, different id) -->
        <div id="wt-import-wait"
            style="display:none;align-items:center;gap:12px;padding:14px 16px;border:1px solid #ccd0d4;border-radius:8px;background:#fff;max-width:620px;">
        <svg width="28" height="28" viewBox="0 0 50 50" aria-hidden="true" focusable="false">
            <circle cx="25" cy="25" r="20" fill="none" stroke-width="6" stroke="#ccd0d4" opacity=".35"></circle>
            <circle cx="25" cy="25" r="20" fill="none" stroke-width="6" stroke="#2271b1" stroke-linecap="round" stroke-dasharray="100" stroke-dashoffset="60">
            <animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="0.9s" repeatCount="indefinite"></animateTransform>
            </circle>
        </svg>
        <div>
            <strong>Please wait…</strong><br>
            This will take a minute. Do not close this window while we import the starter site.
        </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('wt-install-plugins-form');
        var btn  = document.getElementById('wt-install-plugins-btn');
        var wait = document.getElementById('wt-install-wait');
        if (!form || !btn || !wait) return;

        form.addEventListener('submit', function () {
            wait.style.display = 'flex';
            btn.disabled = true;
            btn.classList.add('disabled');
            btn.dataset.label = btn.textContent;
            btn.textContent = 'Installing…';
        });
        });
        </script>


        <hr>
        <h2>24h Theme Installation &amp; Demo Setup</h2>
        <ul class="mb-4 list-unstyled">
            <li>✓ Full setup of your theme.</li>
            <li>✓ Plugins, settings, options ready.</li>
            <li>✓ Full setup of demo content.</li>
            <li>✓ Your website will look just like the demo.</li>
        </ul>
        <p>
            <a target="_blank" rel="noopener" href="<?php 
    echo esc_url( $service_url );
    ?>" class="button button-secondary">
                Make my website look like the demo &rarr;
            </a>
        </p>
    </div>
    <?php 
}

// ======================================================================================================
// ONE CLICK DEMO IMPORT (OCDI) SUPPORT customizer.dat, context.xml, widgets.wie in r2bucket/theme-slug/
// ======================================================================================================
/** Don’t waste time regenerating thumbs during content import. */
add_filter( 'ocdi/regenerate_thumbnails_in_content_import', '__return_false' );
add_filter( 'pt-ocdi/regenerate_thumbnails_in_content_import', '__return_false' );
/**
 * Change $slug.
 */
function wow_mediumish_ocdi_import_files() {
    $base = 'https://public.wowthemes.net/demo-files/';
    $slug = ( defined( 'WOWTHEMES_THEME_SLUG' ) && WOWTHEMES_THEME_SLUG ? WOWTHEMES_THEME_SLUG : 'mediumish' );
    return [[
        'import_file_name'           => 'Starter Site',
        'import_file_url'            => trailingslashit( $base ) . rawurlencode( $slug ) . '/content.xml',
        'import_customizer_file_url' => trailingslashit( $base ) . rawurlencode( $slug ) . '/customizer.dat',
        'import_preview_image_url'   => 'https://demo.wowthemes.net/mediumish/wp-content/themes/mediumish/screenshot.png',
        'preview_url'                => 'https://demo.wowthemes.net/mediumish/',
    ]];
}

add_filter( 'ocdi/import_files', 'wow_mediumish_ocdi_import_files' );
add_filter( 'pt-ocdi/import_files', 'wow_mediumish_ocdi_import_files' );
/**
 * After-import setup:
 * - Assign menu “Top Menu” → primary
 * - Use latest posts for the front page
 */
function wow_mediumish_after_import_setup() {
    $menu = get_term_by( 'name', 'Top Menu', 'nav_menu' );
    if ( !$menu || is_wp_error( $menu ) ) {
        $menus = wp_get_nav_menus();
        if ( !empty( $menus ) ) {
            // Pick the one with the most items; if counts missing, take the first.
            $menu = array_reduce( $menus, function ( $carry, $m ) {
                if ( !$carry ) {
                    return $m;
                }
                return ( (int) $m->count > (int) $carry->count ? $m : $carry );
            }, null );
        }
    }
    if ( $menu && !is_wp_error( $menu ) ) {
        $locations = (array) get_theme_mod( 'nav_menu_locations', [] );
        $locations['primary'] = (int) $menu->term_id;
        // location slug “primary”
        set_theme_mod( 'nav_menu_locations', $locations );
    }
    // Keep homepage = latest posts (no static front page)
    update_option( 'show_on_front', 'posts' );
    delete_option( 'page_on_front' );
    delete_option( 'page_for_posts' );
}

add_action( 'ocdi/after_import', 'wow_mediumish_after_import_setup' );
add_action( 'pt-ocdi/after_import', 'wow_mediumish_after_import_setup' );
// ============================
// KITCHEN END
// ============================
// -----------------------------------------------------
// Require
// -----------------------------------------------------
require_once get_template_directory() . '/inc/bootstrap/wp_bootstrap_pagination.php';
require_once get_template_directory() . '/inc/bootstrap/wp_bootstrap_navwalker.php';
require_once get_template_directory() . '/inc/thetelos-rating.php';
function mediumish_load_customizer() {
    require_once get_template_directory() . '/inc/customizer.php';
}
require_once get_template_directory() . '/inc/book-cover.php';
require_once get_template_directory() . '/inc/analysis-cpt.php';
require_once get_template_directory() . '/inc/analysis-panel.php';
require_once get_template_directory() . '/inc/book-cover-fetcher.php';
require_once get_template_directory() . '/inc/og-cover.php';
require_once get_template_directory() . '/inc/summary-request.php';
require_once get_template_directory() . '/inc/contact-form.php';
require_once get_template_directory() . '/inc/support.php';
require_once get_template_directory() . '/inc/ai-assistant.php';
require_once get_template_directory() . '/inc/membership.php';
require_once get_template_directory() . '/inc/reading-lists.php';

/* Reading List CPT — fallback (dosya yüklenemezse) */
add_action('init', function() {
    if ( post_type_exists('tls_reading_list') ) return;
    register_post_type('tls_reading_list', [
        'labels' => [
            'name'          => 'Reading Lists',
            'singular_name' => 'Reading List',
            'menu_name'     => '📚 Reading Lists',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-book-alt',
        'supports'      => ['title'],
        'menu_position' => 6,
    ]);
});

add_action( 'init', 'mediumish_load_customizer', 1 );
// -----------------------------------------------------
// WH-L STYLE
// -----------------------------------------------------
add_action( 'admin_head', 'wow_med_whitel_dash_info' );
// admin_head hook for admin styles
function wow_med_whitel_dash_info() {
    global $wow_med_fs;
    if ( $wow_med_fs->is_whitelabeled() ) {
        echo '<style> #toplevel_page_mediumish, #fs_account .inside, #fs_account .debug-license-trigger {display:none;} </style>';
    }
}

// -----------------------------------------------------
// Scripts & Styles
// -----------------------------------------------------
if ( ! function_exists( 'mediumish_enqueue_scripts' ) ) {
    function mediumish_enqueue_scripts() {

        wp_enqueue_script(
			'bootstrap4tether',
			get_template_directory_uri() . '/assets/js/tether.min.js',
			array(),
			null,
			true
		);

		wp_enqueue_script(
			'bootstrap4',
			get_template_directory_uri() . '/assets/js/bootstrap.min.js',
			array('jquery', 'bootstrap4tether'),
			null,
			true
		);

        // mediumish.js jQuery KULLANMIYORSA dependency array() yap
        wp_enqueue_script(
            'mediumish',
            get_template_directory_uri() . '/assets/js/mediumish.js',
            array(),
            null,
            true
        );

        wp_enqueue_style(
            'bootstrap4',
            get_template_directory_uri() . '/assets/css/bootstrap.min.css',
            array(),
            null,
            'all'
        );

        wp_enqueue_style(
            'font-awesome-7',
            'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7.1.0/css/all.min.css',
            array(),
            '7.1.0',
            'all'
        );

        wp_style_add_data( 'font-awesome-7', 'integrity', 'sha384-YgSbYtJcfPnMV/aJ0UdQk84ctht/ckX0MrfQwxOhw43RMBw2WSaDSMVh4gQwLdE4' );
        wp_style_add_data( 'font-awesome-7', 'crossorigin', 'anonymous' );

        wp_enqueue_style(
            'mediumish-style',
            get_stylesheet_uri(),
            array( 'bootstrap4' ),
            THEME_VERSION,
            'all'
        );

        if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
            wp_enqueue_script( 'comment-reply' );
        }
    }

    add_action( 'wp_enqueue_scripts', 'mediumish_enqueue_scripts' );
}

// ──────────────────────────────────────────────────────────
// TheTelos brand fonts (DM Serif Display + DM Sans)
// ──────────────────────────────────────────────────────────
function thetelos_enqueue_brand_fonts() {
    wp_enqueue_style(
        'thetelos-brand-fonts',
        'https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap',
        [],
        null
    );
}
add_action( 'wp_enqueue_scripts', 'thetelos_enqueue_brand_fonts', 5 );

// ──────────────────────────────────────────────────────────
// TheTelos custom CSS + JS
// ──────────────────────────────────────────────────────────
function thetelos_enqueue_custom_assets() {
    wp_enqueue_style(
        'thetelos-styles',
        get_template_directory_uri() . '/assets/css/thetelos.css',
        [ 'mediumish-style' ],
        THEME_VERSION . '.m16'
    );

    wp_enqueue_script(
        'thetelos-scripts',
        get_template_directory_uri() . '/assets/js/thetelos.js',
        [],
        THEME_VERSION . '.m16',
        true
    );

    $user     = wp_get_current_user();
    $logged   = is_user_logged_in();

    wp_localize_script( 'thetelos-scripts', 'thelosData', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'thetelos_rating' ),
    ] );

    wp_localize_script( 'thetelos-scripts', 'tlsAuth', [
        'ajaxUrl'     => admin_url('admin-ajax.php'),
        'isLoggedIn'  => $logged,
        'profileUrl'  => home_url('/profile/'),
        'statusNonce' => wp_create_nonce('tls_status_nonce'),
        'authNonce'   => wp_create_nonce('tls_auth_nonce'),
        'user'        => $logged ? [
            'name'  => $user->display_name,
            'email' => $user->user_email,
        ] : null,
    ] );
}
add_action( 'wp_enqueue_scripts', 'thetelos_enqueue_custom_assets', 20 );

add_filter( 'kirki_enqueue_google_fonts', '__return_empty_array' );
/**
 * List typography setting keys that may request Google Fonts.
 *
 * @return array<string>
 */
function mediumish_typography_settings_keys() {
    return [
        'body_typography',
        'headers_typography',
        'slidertitle_typography',
        'sliderdescription_typography',
        'logo_typography',
        'menu_typography',
        'card1big_typography',
        'card1_typography',
        'card2_typography',
        'card3_typography',
        'card4_typography',
        'card5_typography',
        'card6_typography',
        'card_default_typography',
        'card_text_typography',
        'headline_section_typography',
        'singlearticletitle_typography',
        'singlearticle_typography',
        'comments_typography',
        'singlepage_typography',
        'mediumish_footer_typography'
    ];
}

/**
 * Allowed Google Font subsets.
 *
 * @return array<string,string>
 */
function mediumish_allowed_font_subsets() {
    return [
        'latin'        => __( 'Latin', 'mediumish' ),
        'latin-ext'    => __( 'Latin Extended', 'mediumish' ),
        'cyrillic'     => __( 'Cyrillic', 'mediumish' ),
        'cyrillic-ext' => __( 'Cyrillic Extended', 'mediumish' ),
        'greek'        => __( 'Greek', 'mediumish' ),
        'greek-ext'    => __( 'Greek Extended', 'mediumish' ),
        'vietnamese'   => __( 'Vietnamese', 'mediumish' ),
    ];
}

/**
 * Normalize a multi-select theme_mod against allowed values.
 *
 * @param mixed             $value
 * @param array<int,string> $allowed
 * @param array<int,string> $default
 * @return array<int,string>
 */
function mediumish_normalize_selection(  $value, array $allowed, array $default  ) {
    if ( !is_array( $value ) ) {
        if ( is_string( $value ) && $value !== '' ) {
            $value = array_map( 'trim', explode( ',', $value ) );
        } else {
            $value = [];
        }
    }
    $normalized = [];
    foreach ( $value as $key => $entry ) {
        if ( is_string( $entry ) && $entry !== '' ) {
            $normalized[] = $entry;
            continue;
        }
        if ( is_bool( $entry ) ) {
            if ( $entry && is_string( $key ) && $key !== '' ) {
                $normalized[] = $key;
            }
            continue;
        }
        if ( is_numeric( $entry ) ) {
            $normalized[] = (string) $entry;
            continue;
        }
        if ( '' === $entry && is_string( $key ) && $key !== '' ) {
            $normalized[] = $key;
        }
    }
    $normalized = array_values( array_unique( array_map( 'strval', $normalized ) ) );
    $filtered = array_values( array_intersect( $normalized, $allowed ) );
    return ( !empty( $filtered ) ? $filtered : $default );
}

/**
 * Allowed Google Font variants for preloading.
 *
 * @return array<string,string>
 */
function mediumish_allowed_font_variants() {
    return [
        'regular'   => __( 'Regular 400', 'mediumish' ),
        'italic'    => __( 'Regular 400 Italic', 'mediumish' ),
        '300'       => __( 'Light 300', 'mediumish' ),
        '300italic' => __( 'Light 300 Italic', 'mediumish' ),
        '500'       => __( 'Medium 500', 'mediumish' ),
        '500italic' => __( 'Medium 500 Italic', 'mediumish' ),
        '600'       => __( 'Semi Bold 600', 'mediumish' ),
        '600italic' => __( 'Semi Bold 600 Italic', 'mediumish' ),
        '700'       => __( 'Bold 700', 'mediumish' ),
        '700italic' => __( 'Bold 700 Italic', 'mediumish' ),
        '800'       => __( 'Extra Bold 800', 'mediumish' ),
        '800italic' => __( 'Extra Bold 800 Italic', 'mediumish' ),
        '900'       => __( 'Black 900', 'mediumish' ),
        '900italic' => __( 'Black 900 Italic', 'mediumish' ),
    ];
}

/**
 * Return globally selected default variants.
 *
 * @return array<string>
 */
function mediumish_get_selected_font_variants() {
    $default = [
        'regular',
        'italic',
        '700',
        '700italic'
    ];
    $selected = get_theme_mod( 'mediumish_google_fonts_variants', $default );
    return mediumish_normalize_selection( $selected, array_keys( mediumish_allowed_font_variants() ), $default );
}

/**
 * Selected subsets from the Customizer field.
 *
 * @return array<string>
 */
function mediumish_get_selected_font_subsets() {
    $default = ['latin', 'latin-ext'];
    $selected = get_theme_mod( 'mediumish_google_fonts_subsets', $default );
    return mediumish_normalize_selection( $selected, array_keys( mediumish_allowed_font_subsets() ), $default );
}

/**
 * Determine whether the font family is a system font and should be skipped.
 */
function mediumish_is_system_font(  $family  ) {
    if ( !is_string( $family ) ) {
        return true;
    }
    $normalized = strtolower( trim( $family ) );
    if ( $normalized === '' || false !== strpos( $normalized, ',' ) ) {
        return true;
    }
    $system_fonts = [
        'serif',
        'sans-serif',
        'monospace',
        'cursive',
        'fantasy',
        'inherit',
        'initial',
        'unset',
        'system-ui',
        'ui-sans-serif',
        'ui-serif',
        'ui-monospace',
        'ui-rounded',
        'emoji',
        'math',
        'fangsong'
    ];
    return in_array( $normalized, $system_fonts, true );
}

/**
 * Normalize variant string from typography settings.
 *
 * @param array<string,mixed> $value Field values.
 * @return string
 */
function mediumish_determine_variant(  array $value  ) {
    if ( !empty( $value['variant'] ) && is_string( $value['variant'] ) ) {
        return strtolower( $value['variant'] );
    }
    $weight = '';
    if ( !empty( $value['font-weight'] ) && is_string( $value['font-weight'] ) ) {
        $weight = strtolower( $value['font-weight'] );
    }
    $style = '';
    if ( !empty( $value['font-style'] ) && is_string( $value['font-style'] ) ) {
        $style = strtolower( $value['font-style'] );
    }
    $variant = '';
    if ( in_array( $weight, ['normal', 'regular'], true ) || $weight === '' ) {
        $weight = '';
    }
    if ( $weight === 'bold' ) {
        $weight = '700';
    }
    if ( $weight !== '' ) {
        $variant .= $weight;
    }
    if ( $style === 'italic' ) {
        $variant .= 'italic';
    }
    return ( $variant !== '' ? $variant : 'regular' );
}

/**
 * Collect Google Fonts and variants selected in typography controls.
 *
 * @return array<string,array>
 */
function mediumish_collect_google_fonts() {
    $fonts = [];
    foreach ( mediumish_typography_settings_keys() as $setting ) {
        $value = get_theme_mod( $setting, [] );
        if ( !is_array( $value ) ) {
            continue;
        }
        $family = ( isset( $value['font-family'] ) ? trim( (string) $value['font-family'] ) : '' );
        if ( $family === '' || mediumish_is_system_font( $family ) ) {
            continue;
        }
        $variant = mediumish_determine_variant( $value );
        if ( !isset( $fonts[$family] ) ) {
            $fonts[$family] = [];
        }
        $fonts[$family][$variant] = true;
    }
    if ( empty( $fonts ) ) {
        return [];
    }
    $extra_variants = mediumish_get_selected_font_variants();
    if ( !empty( $extra_variants ) ) {
        foreach ( $fonts as $family => $variants ) {
            foreach ( $extra_variants as $variant ) {
                $fonts[$family][$variant] = true;
            }
        }
    }
    $output = [];
    foreach ( $fonts as $family => $variants ) {
        $output[$family] = array_keys( $variants );
    }
    return $output;
}

/**
 * Encode family name for Google Fonts CSS2 URLs.
 *
 * @param string $family
 * @return string
 */
function mediumish_google_fonts_family_slug(  $family  ) {
    $normalized = trim( (string) $family );
    return str_replace( '%20', '+', rawurlencode( $normalized ) );
}

/**
 * Build CSS2 family query fragment.
 *
 * @param string            $family
 * @param array<int,string> $variants
 * @return string
 */
function mediumish_format_css2_family_query(  $family, array $variants  ) {
    if ( empty( $variants ) ) {
        $variants = ['regular'];
    }
    $matrix = [];
    foreach ( $variants as $raw_variant ) {
        $variant = strtolower( trim( (string) $raw_variant ) );
        $italic = 0;
        if ( false !== strpos( $variant, 'italic' ) ) {
            $italic = 1;
            $variant = str_replace( 'italic', '', $variant );
        }
        $variant = trim( $variant );
        $weight = (int) $variant;
        if ( $weight === 0 ) {
            if ( $variant === '' || $variant === 'regular' ) {
                $weight = 400;
            } elseif ( $variant === 'bold' ) {
                $weight = 700;
            } elseif ( $variant === 'medium' ) {
                $weight = 500;
            } elseif ( $variant === 'semibold' ) {
                $weight = 600;
            } elseif ( $variant === 'light' ) {
                $weight = 300;
            } else {
                $weight = 400;
            }
        }
        $weight = max( 100, min( 900, $weight ) );
        if ( !isset( $matrix[$italic] ) ) {
            $matrix[$italic] = [];
        }
        $matrix[$italic][$weight] = true;
    }
    foreach ( $matrix as &$weights ) {
        ksort( $weights, SORT_NUMERIC );
    }
    unset($weights);
    $family_slug = mediumish_google_fonts_family_slug( $family );
    if ( count( $matrix ) === 1 && isset( $matrix[0] ) ) {
        $weights = implode( ';', array_map( 'strval', array_keys( $matrix[0] ) ) );
        return $family_slug . ':wght@' . $weights;
    }
    $pairs = [];
    foreach ( $matrix as $italic => $weights ) {
        foreach ( array_keys( $weights ) as $weight ) {
            $pairs[] = $italic . ',' . $weight;
        }
    }
    sort( $pairs, SORT_STRING );
    return $family_slug . ':ital,wght@' . implode( ';', $pairs );
}

/**
 * Build Google Fonts CSS URL.
 *
 * @param array<string,array> $fonts
 * @param array<string>       $subsets
 * @return string
 */
function mediumish_build_google_fonts_url(  array $fonts, array $subsets  ) {
    if ( empty( $fonts ) ) {
        return '';
    }
    $families = [];
    foreach ( $fonts as $family => $variants ) {
        $families[] = 'family=' . mediumish_format_css2_family_query( $family, $variants );
    }
    if ( empty( $families ) ) {
        return '';
    }
    $url = 'https://fonts.googleapis.com/css2?' . implode( '&', $families ) . '&display=swap';
    if ( !empty( $subsets ) ) {
        $url .= '&subset=' . implode( ',', array_map( 'rawurlencode', $subsets ) );
    }
    return $url;
}

/**
 * Should Google Fonts be loaded from the CDN?
 */
function mediumish_google_fonts_enabled() {
    return (bool) get_theme_mod( 'mediumish_google_fonts_enable', true );
}

/**
 * Enqueue Google Fonts from the CDN.
 */
function mediumish_enqueue_google_fonts() {
    if ( !mediumish_google_fonts_enabled() ) {
        return;
    }
    $fonts = mediumish_collect_google_fonts();
    if ( empty( $fonts ) ) {
        return;
    }
    $url = mediumish_build_google_fonts_url( $fonts, mediumish_get_selected_font_subsets() );
    if ( $url ) {
        wp_enqueue_style(
            'mediumish-google-fonts',
            $url,
            [],
            null
        );
    }
}

add_action( 'wp_enqueue_scripts', 'mediumish_enqueue_google_fonts', 4 );
/**
 * Ensure resource hint entries stay unique.
 *
 * @param array<int,array|string> $urls
 * @param string                  $href
 * @param string|null             $crossorigin
 * @return array
 */
function mediumish_add_unique_resource_hint(  array $urls, $href, $crossorigin = null  ) {
    foreach ( $urls as $entry ) {
        if ( is_array( $entry ) && isset( $entry['href'] ) && $entry['href'] === $href ) {
            return $urls;
        }
        if ( is_string( $entry ) && $entry === $href ) {
            return $urls;
        }
    }
    if ( null === $crossorigin ) {
        $urls[] = [
            'href' => $href,
        ];
    } else {
        $urls[] = [
            'href'        => $href,
            'crossorigin' => $crossorigin,
        ];
    }
    return $urls;
}

/**
 * Add resource hints for Google Fonts hosts.
 *
 * @param array  $urls
 * @param string $relation_type
 * @return array
 */
function mediumish_google_fonts_resource_hints(  $urls, $relation_type  ) {
    if ( !mediumish_google_fonts_enabled() ) {
        return $urls;
    }
    if ( 'preconnect' === $relation_type ) {
        $urls = mediumish_add_unique_resource_hint( $urls, 'https://fonts.googleapis.com' );
        $urls = mediumish_add_unique_resource_hint( $urls, 'https://fonts.gstatic.com', 'anonymous' );
    }
    return $urls;
}

add_filter(
    'wp_resource_hints',
    'mediumish_google_fonts_resource_hints',
    10,
    2
);
add_filter(
    'style_loader_tag',
    'mediumish_non_blocking_font_awesome',
    10,
    4
);
/**
 * Defer remote stylesheets to avoid render-blocking (Font Awesome & Google Fonts).
 */
function mediumish_non_blocking_font_awesome(
    $html,
    $handle,
    $href,
    $media
) {
    $supported = ['font-awesome-7', 'mediumish-google-fonts'];
    if ( !in_array( $handle, $supported, true ) ) {
        return $html;
    }
    $styles = wp_styles();
    $integrity = $styles->get_data( $handle, 'integrity' );
    $crossorigin = $styles->get_data( $handle, 'crossorigin' );
    if ( 'mediumish-google-fonts' === $handle && empty( $crossorigin ) ) {
        $crossorigin = 'anonymous';
    }
    $target_media = ( $media && 'all' !== $media ? $media : 'all' );
    $attributes = [
        'rel="preload"',
        'as="style"',
        'href="' . esc_url( $href ) . '"',
        'media="' . esc_attr( $target_media ) . '"'
    ];
    if ( !empty( $integrity ) ) {
        $attributes[] = 'integrity="' . esc_attr( $integrity ) . '"';
    }
    if ( !empty( $crossorigin ) ) {
        $attributes[] = 'crossorigin="' . esc_attr( $crossorigin ) . '"';
    }
    $preload_tag = '<link ' . implode( ' ', $attributes ) . ' onload="this.onload=null;this.rel=\'stylesheet\';">';
    $fallback_attributes = ['rel="stylesheet"', 'href="' . esc_url( $href ) . '"', 'media="' . esc_attr( $target_media ) . '"'];
    if ( !empty( $integrity ) ) {
        $fallback_attributes[] = 'integrity="' . esc_attr( $integrity ) . '"';
    }
    if ( !empty( $crossorigin ) ) {
        $fallback_attributes[] = 'crossorigin="' . esc_attr( $crossorigin ) . '"';
    }
    $noscript_tag = '<noscript><link ' . implode( ' ', $fallback_attributes ) . '></noscript>';
    return $preload_tag . $noscript_tag;
}

add_action( 'admin_footer', 'mediumish_adjust_kirki_settings_screen' );
/**
 * Update the Kirki settings page to reflect CDN font delivery.
 */
function mediumish_adjust_kirki_settings_screen() {
    if ( !is_admin() ) {
        return;
    }
    $screen = ( function_exists( 'get_current_screen' ) ? get_current_screen() : null );
    if ( !$screen || 'appearance_page_kirki' !== $screen->id ) {
        return;
    }
    $message = __( 'Mediumish now serves Google Fonts from the Google CDN when enabled. Local font caching and the “Clear Font Cache” button are no longer required.', 'mediumish' );
    ?>
    <style>
        .mediumish-kirki-note {
            margin-top: 16px;
        }
    </style>
    <script>
        (function ($) {
            $(function () {
                var note = $('<div class="notice notice-info mediumish-kirki-note"><p><?php 
    echo esc_js( $message );
    ?></p></div>');
                var $wrap = $('.wrap');
                if ($wrap.length) {
                    $wrap.children('h1').first().after(note);
                }

                $wrap.find('button:contains("Clear Cache"), button:contains("Clear Font Cache")').each(function () {
                    $(this).closest('.card, .kirki-card, .postbox, .wrap > div').remove();
                });
            });
        })(jQuery);
    </script>
    <?php 
}

add_filter(
    'wp_resource_hints',
    'mediumish_preconnect_cdn_jsdelivr',
    10,
    2
);
/**
 * Ensure early connection setup for the Font Awesome CDN.
 */
function mediumish_preconnect_cdn_jsdelivr(  $urls, $relation_type  ) {
    if ( 'preconnect' !== $relation_type ) {
        return $urls;
    }
    $href = 'https://cdn.jsdelivr.net';
    foreach ( $urls as $entry ) {
        if ( is_string( $entry ) && $entry === $href ) {
            return $urls;
        }
        if ( is_array( $entry ) && isset( $entry['href'] ) && $entry['href'] === $href ) {
            return $urls;
        }
    }
    $urls[] = [
        'href'        => $href,
        'crossorigin' => 'anonymous',
    ];
    return $urls;
}

// ----------------------------------------------------
// Register Widgets
// -----------------------------------------------------
if ( !function_exists( 'mediumish_sidebar_widgets_init' ) ) {
    function _widgets_init() {
        register_sidebar( array(
            'name'          => __( 'Sidebar WooCommerce Shop', 'mediumish' ),
            'id'            => 'sidebar-woocommerce',
            'before_widget' => '<aside id="%1$s" class="widget %2$s">',
            'after_widget'  => '</aside>',
            'before_title'  => '<h4 class="widget-title"><span>',
            'after_title'   => '</span></h4>',
        ) );
    }

}
// _widgets_init
add_action( 'widgets_init', '_widgets_init' );
// -----------------------------------------------------
// Excerpt
// -----------------------------------------------------
function excerpt(  $limit  ) {
    $excerpt = explode( ' ', get_the_excerpt(), $limit );
    if ( count( $excerpt ) >= $limit ) {
        array_pop( $excerpt );
        $excerpt = implode( ' ', $excerpt ) . '...';
    } else {
        $excerpt = implode( ' ', $excerpt );
    }
    $excerpt = preg_replace( '`\\[[^\\]]*\\]`', '', $excerpt );
    return $excerpt;
}

function content(  $limit  ) {
    $content = explode( ' ', get_the_content(), $limit );
    if ( count( $content ) >= $limit ) {
        array_pop( $content );
        $content = implode( ' ', $content ) . '...';
    } else {
        $content = implode( ' ', $content );
    }
    $content = preg_replace( '/\\[.+\\]/', '', $content );
    $content = apply_filters( 'the_content', $content );
    $content = str_replace( ']]>', ']]&gt;', $content );
    return $content;
}

// -----------------------------------------------------
// Reading Time
// -----------------------------------------------------
function mediumish_estimated_reading_time() {
    $post = get_post();
    $content = strip_tags( $post->post_content );
    $words = str_word_count( $content );
    // Count the number of Chinese characters
    preg_match_all( '/[\\x{4e00}-\\x{9fa5}]/u', $content, $matches );
    $chinese_characters = count( $matches[0] );
    // If the content contains Chinese characters
    if ( $chinese_characters > 0 ) {
        $minutes = floor( $chinese_characters / 500 );
        $seconds = floor( $chinese_characters % 500 / (500 / 60) );
    } else {
        $minutes = floor( $words / 295 );
        $seconds = floor( $words % 295 / (295 / 60) );
    }
    if ( 1 <= $minutes ) {
        $estimated_time = $minutes . ' ' . esc_attr__( 'min read', 'mediumish' );
    } else {
        $estimated_time = $seconds . ' ' . esc_attr__( 'sec read', 'mediumish' );
    }
    return $estimated_time;
}

// -----------------------------------------------------
// Limit title characters
// -----------------------------------------------------
function limit_word_count(  $title  ) {
    $limitcharacterstitle = get_theme_mod( 'mediumish_limitcharacterstitle' );
    if ( $limitcharacterstitle ) {
        $len = $limitcharacterstitle;
    } else {
        $len = 9;
    }
    return wp_trim_words( $title, $len, '&hellip;' );
}

// -----------------------------------------------------
// Share
// -----------------------------------------------------
if ( !function_exists( 'mediumish_share_post' ) ) {
    function mediumish_share_post() {
        global $post;
        $shareURL = urlencode( get_permalink() );
        $shareTitle = str_replace( ' ', '%20', get_the_title() );
        $shareThumbnail = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'large' );
        $twitterURL = 'https://twitter.com/intent/tweet?text=' . $shareTitle . '&amp;url=' . $shareURL;
        $facebookURL = 'https://www.facebook.com/sharer/sharer.php?u=' . $shareURL;
        $linkedinURL = 'https://www.linkedin.com/shareArticle?mini=true&url=' . $shareURL . '&amp;title=' . $shareTitle;
        $disablesharetwitter = get_theme_mod( 'disable_share_twitter' );
        $disablesharefb = get_theme_mod( 'disable_share_fb' );
        $disablesharelinkedin = get_theme_mod( 'disable_share_linkedin' );
        echo '<ul class="shareitnow">';
        if ( $disablesharetwitter == 0 ) {
            echo '<li>
        <a target="_blank" href="' . $twitterURL . '">
        <i class="fab fa-x-twitter"></i>
        </a></li>';
        }
        if ( $disablesharefb == 0 ) {
            echo '<li>
        <a target="_blank" href="' . $facebookURL . '">
        <i class="fab fa-facebook"></i>
        </a></li>';
        }
        if ( $disablesharelinkedin == 0 ) {
            echo '<li>
        <a target="_blank" href="' . $linkedinURL . '">
        <i class="fab fa-linkedin"></i>
        </a></li>';
        }
        echo '</ul>';
    }

}
// -----------------------------------------------------
// Hide applause button plugin, it's already in theme
// -----------------------------------------------------
add_filter( 'wpli/autoadd', function () {
    return false;
} );
// -----------------------------------------------------
// Meta Tag
// -----------------------------------------------------
function mediumish_custom_get_meta_excerpt() {
    global $post;
    $temp = $post;
    $post = get_post();
    setup_postdata( $post );
    $excerpt = esc_attr( strip_tags( get_the_excerpt() ) );
    wp_reset_postdata();
    $post = $temp;
    return $excerpt;
}

function mediumish_custom_add_meta_description_tag() {
    ?>
    <meta name="description" content="<?php 
    if ( is_single() || is_page() ) {
        $excerpt = mediumish_custom_get_meta_excerpt( get_the_ID() );
        echo $excerpt;
    } else {
        bloginfo( 'description' );
    }
    ?>" />
<?php 
}

if ( !class_exists( 'WPSEO_Options' ) ) {
    add_action( 'wp_head', 'mediumish_custom_add_meta_description_tag', 1 );
}
// -----------------------------------------------------
// Comment Form
// -----------------------------------------------------
function my_update_comment_fields(  $fields  ) {
    $commenter = wp_get_current_commenter();
    $req = get_option( 'require_name_email' );
    $aria_req = ( $req ? 'aria-required="true" required' : '' );
    // Author
    $fields['author'] = sprintf(
        '<div class="row">
            <p class="comment-form-author col-md-4">
                <input id="author" name="author" type="text"
                       placeholder="%s"
                       value="%s" size="30" %s />
            </p>',
        esc_attr__( 'Name', 'mediumish' ),
        esc_attr( $commenter['comment_author'] ),
        $aria_req
    );
    // Email
    $fields['email'] = sprintf(
        '<p class="comment-form-email col-md-4">
            <input id="email" name="email" type="email"
                   placeholder="%s"
                   value="%s" size="30" %s />
        </p>',
        esc_attr__( 'E-mail address', 'mediumish' ),
        esc_attr( $commenter['comment_author_email'] ),
        $aria_req
    );
    // URL
    $fields['url'] = sprintf( '<p class="comment-form-url col-md-4">
            <input id="url" name="url" type="url"
                   placeholder="%s"
                   value="%s" size="30" />
        </p></div>', esc_attr__( 'Website Link', 'mediumish' ), esc_attr( $commenter['comment_author_url'] ) );
    return $fields;
}

add_filter( 'comment_form_default_fields', 'my_update_comment_fields' );
function my_update_comment_field(  $comment_field  ) {
    $comment_field = '<p class="comment-form-comment">
            <textarea required id="comment" name="comment" placeholder="' . esc_attr__( 'Write a response...', 'mediumish' ) . '" cols="45" rows="8" aria-required="true"></textarea>
        </p>';
    return $comment_field;
}

add_filter( 'comment_form_field_comment', 'my_update_comment_field' );
// ---------------------------------------------
// Template helpers (define BEFORE template funcs)
// ---------------------------------------------
if ( !function_exists( 'mediumish_preview_title' ) ) {
    function mediumish_preview_title(  $max = 200  ) {
        $t = get_the_title();
        if ( function_exists( 'mb_substr' ) ) {
            $t = mb_substr( $t, 0, (int) $max );
        } else {
            $t = substr( $t, 0, (int) $max );
        }
        return $t;
    }

}
if ( !function_exists( 'mediumish_icon_readmore' ) ) {
    function mediumish_icon_readmore() {
        return '<svg class="svgIcon-use" width="25" height="25" viewBox="0 0 25 25"><path d="M19 6c0-1.1-.9-2-2-2H8c-1.1 0-2 .9-2 2v14.66h.012c.01.103.045.204.12.285a.5.5 0 0 0 .706.03L12.5 16.85l5.662 4.126a.508.508 0 0 0 .708-.03.5.5 0 0 0 .118-.285H19V6zm-6.838 9.97L7 19.636V6c0-.55.45-1 1-1h9c.55 0 1 .45 1 1v13.637l-5.162-3.668a.49.49 0 0 0-.676 0z" fill-rule="evenodd"></path></svg>';
    }

}
// -----------------------------------------------------
// Postbox
// -----------------------------------------------------
function mediumish_postbox() {
    add_filter( 'the_title', 'limit_word_count' );
    global $post;
    ?>
    <div class="card post postbox_default h-100">
        <?php 
    if ( has_post_thumbnail() ) {
        ?>
            <a class="postbox_default_thumbnail" href="<?php 
        echo esc_url( get_permalink() );
        ?>" aria-label="<?php 
        echo esc_attr( get_the_title() );
        ?>">
                <?php 
        the_post_thumbnail( 'medium_large' );
        ?>
            </a>
        <?php 
    }
    ?>

        <div class="card-block">
            <h2 class="card-title">
                <a href="<?php 
    echo esc_url( get_permalink() );
    ?>"><?php 
    echo esc_html( mediumish_preview_title( 200 ) );
    ?></a>
            </h2>

            <span class="card-text d-block"><?php 
    echo esc_html( excerpt( 22 ) );
    ?></span>

            <div class="metafooter">
                <div class="wrapfooter">
                    <span class="meta-footer-thumb">
                        <a href="<?php 
    echo esc_url( get_author_posts_url( $post->post_author ) );
    ?>">
                            <?php 
    echo get_avatar(
        get_the_author_meta( 'user_email' ),
        40,
        null,
        null,
        [
            'class' => ['author-thumb'],
        ]
    );
    ?>
                        </a>
                    </span>

                    <span class="author-meta">
                        <span class="post-name">
                            <a href="<?php 
    echo esc_url( get_author_posts_url( $post->post_author ) );
    ?>"><?php 
    echo esc_html( get_the_author_meta( 'display_name' ) );
    ?></a>
                        </span><br>
                        <span class="post-date"><?php 
    echo esc_html( get_the_date( 'M j, Y' ) );
    ?></span>
                        <span class="dot"></span>
                        <span class="readingtime"><?php 
    echo esc_html( mediumish_estimated_reading_time() );
    ?></span>
                    </span>

                    <span class="post-read-more">
                        <a href="<?php 
    echo esc_url( get_permalink() );
    ?>" title="Read Story"><?php 
    echo mediumish_icon_readmore();
    ?></a>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php 
}

// -----------------------------------------------------
// Postbox Default
// -----------------------------------------------------
// -----------------------------------------------------
// Postbox Default
// -----------------------------------------------------
function mediumish_postbox_default() {
    global $post;
    $first_letter = mb_strtoupper( mb_substr( get_the_title(), 0, 1 ) );
    $letter_colors = ['#111827'];
    $letter_color = $letter_colors[ $post->ID % count($letter_colors) ];
    $bg_colors = ['#EDEDED'];
    $bg_color = $bg_colors[ $post->ID % count($bg_colors) ];
    ?>
    <div class="card post postbox_default h-100" style="background:#ffffff; overflow:hidden;">
        <a class="postbox_default_thumbnail" href="<?php echo esc_url( get_permalink() ); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>" style="display:flex; justify-content:center; align-items:center; padding:24px 24px 0 24px; background:#f5f5f3; position:relative; overflow:hidden; min-height:220px;">
            <span style="position:absolute; font-size:200px; font-weight:900; font-family:'Playfair Display', serif; color:<?php echo esc_attr($letter_color); ?>; opacity:0.25; line-height:1; left:50%; top:50%; transform:translate(-50%,-50%); pointer-events:none; user-select:none;"><?php echo esc_html( $first_letter ); ?></span>
            <?php if ( has_post_thumbnail() ) { ?>
            <div style="position:relative; z-index:1; box-shadow: 0 12px 40px rgba(0,0,0,0.35), 0 4px 12px rgba(0,0,0,0.2); border-radius:4px; overflow:hidden; max-width:160px;">
                <?php the_post_thumbnail( 'medium_large', array('style' => 'width:100%; height:auto; display:block;') ); ?>
            </div>
        <?php } else { ?>
            <?php echo thetelos_render_book_cover( $post->ID ); ?>
        <?php } ?>
        </a>

        <div class="card-block d-flex flex-column" style="padding:16px;">
            <h2 class="card-title" style="font-size:16px; margin-top:12px;">
                <a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( mediumish_preview_title( 200 ) ); ?></a>
            </h2>
            <span class="card-text" style="font-size:13px; color:#666;"><?php echo esc_html( excerpt( 22 ) ); ?></span>

            <?php if ( function_exists( 'thetelos_get_analysis_for_post' ) && thetelos_get_analysis_for_post( $post->ID ) ) : ?>
            <div style="margin-top:10px;">
                <a href="<?php echo esc_url( get_permalink() . '#deep-analysis' ); ?>"
                   style="display:inline-flex; align-items:center; line-height:1; gap:6px; background:#00ab6b; color:#fff; font-size:12px; font-family:sans-serif; letter-spacing:0.03em; padding:6px 13px; border-radius:20px; text-decoration:none; font-weight:600; transition:background 0.2s;"
                   onmouseover="this.style.background='#009960'" onmouseout="this.style.background='#00ab6b'">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="flex-shrink:0; display:block;">
                        <path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                    </svg>
                    Deep Analysis
                </a>
            </div>
            <?php endif; ?>

            <div class="metafooter mt-auto">
                <div class="wrapfooter" style="display:flex;align-items:center;gap:6px;flex-wrap:nowrap;">
                    <?php
                    $card_authors = get_the_terms( $post->ID, 'authors' );
                    $card_author  = ( ! empty($card_authors) && ! is_wp_error($card_authors) ) ? $card_authors[0] : null;
                    ?>
                    <span class="meta-footer-thumb" style="flex-shrink:0;margin:0;padding:0;">
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:#f0f0ee;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.6" width="16" height="16">
                                <path d="M20.24 12.24a6 6 0 00-8.49-8.49L5 10.5V19h8.5l6.74-6.76zM16 8L2 22M17.5 15H9"/>
                            </svg>
                        </span>
                    </span>
                    <span class="author-meta" style="flex:1;min-width:0;margin:0;padding:0;">
                        <span class="post-name" style="display:block;font-size:13px;font-weight:400;line-height:1.2;margin:0 0 2px 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?php if ( $card_author ) : ?>
                                <a href="<?php echo esc_url(get_term_link($card_author)); ?>" style="color:#1a1a1a;text-decoration:none;"><?php echo esc_html($card_author->name); ?></a>
                            <?php else : ?>
                                <span style="color:#aaa;">—</span>
                            <?php endif; ?>
                        </span>
                        <span class="readingtime" style="display:block;font-size:12px;color:#888;line-height:1.2;"><?php echo esc_html( function_exists('thetelos_post_reading_time') ? thetelos_post_reading_time($post->ID) : mediumish_estimated_reading_time() ); ?></span>
                    </span>
                    <span class="post-read-more" style="flex-shrink:0;margin-left:auto;">
                        <a href="<?php echo esc_url( get_permalink() ); ?>" title="Read Story"><?php echo mediumish_icon_readmore(); ?></a>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// -----------------------------------------------------
// Author Postbox (from list all authors)
// -----------------------------------------------------
function mediumish_authorpostbox() {
    $thumb = wp_get_attachment_url( get_post_thumbnail_id( get_the_ID() ) );
    ?>
    <div class="card post authorpost">
        <?php 
    if ( $thumb ) {
        ?>
            <a class="thumbimage" href="<?php 
        echo esc_url( get_permalink() );
        ?>" style="background-image:url(<?php 
        echo esc_url( $thumb );
        ?>);"></a>
        <?php 
    }
    ?>

        <div class="card-block">
            <h2 class="card-title">
                <a href="<?php 
    echo esc_url( get_permalink() );
    ?>"><?php 
    echo esc_html( mediumish_preview_title( 200 ) );
    ?></a>
            </h2>

            <span class="card-text d-block"><?php 
    echo esc_html( excerpt( 25 ) );
    ?></span>

            <div class="metafooter">
                <div class="wrapfooter">
                    <span class="author-meta">
                        <span class="post-date"><?php 
    echo esc_html( get_the_date() );
    ?></span>
                        <span class="dot"></span>
                        <?php 
    if ( comments_open() ) {
        ?>
                            <span class="muted"><i class="fa fa-comments"></i> <?php 
        echo (int) get_comments_number();
        ?></span>
                            <span class="dot"></span>
                        <?php 
    }
    ?>
                        <span class="readingtime"><?php 
    echo esc_html( mediumish_estimated_reading_time() );
    ?></span>
                    </span>

                    <span class="post-read-more">
                        <a href="<?php 
    echo esc_url( get_permalink() );
    ?>"><?php 
    echo mediumish_icon_readmore();
    ?></a>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php 
}

// -----------------------------------------------------
// Post Card Highlight First in Row
// -----------------------------------------------------
function mediumish_post_card_highlight_first() {
    $thumb = get_the_post_thumbnail_url( get_the_ID(), 'medium_large' );
    ?>
    <div class="card post poststyle1big h-100">
        <?php 
    if ( $thumb ) {
        ?>
            <a href="<?php 
        echo esc_url( get_permalink() );
        ?>">
                <img class="poststyle1big-img" src="<?php 
        echo esc_url( $thumb );
        ?>" alt="<?php 
        echo esc_attr( get_the_title() );
        ?>" />
            </a>
        <?php 
    }
    ?>

        <div class="card-block d-flex flex-column">
            <h2 class="card-title">
                <a href="<?php 
    echo esc_url( get_permalink() );
    ?>"><?php 
    echo esc_html( get_the_title() );
    ?></a>
            </h2>

            <span class="card-text d-block"><?php 
    echo esc_html( excerpt( 30 ) );
    ?></span>

            <div class="metafooter mt-auto">
                <div class="wrapfooter">
                    <span class="meta-footer-thumb">
                        <a href="<?php 
    echo esc_url( get_author_posts_url( get_post_field( 'post_author', get_the_ID() ) ) );
    ?>">
                            <?php 
    echo get_avatar(
        get_the_author_meta( 'user_email' ),
        40,
        null,
        null,
        [
            'class' => ['author-thumb'],
        ]
    );
    ?>
                        </a>
                    </span>

                    <span class="author-meta">
                        <span class="post-name">
                            <a href="<?php 
    echo esc_url( get_author_posts_url( get_post_field( 'post_author', get_the_ID() ) ) );
    ?>"><?php 
    echo esc_html( get_the_author_meta( 'display_name' ) );
    ?></a>
                        </span><br>
                        <span class="post-date"><?php 
    echo esc_html( get_the_date( 'M j, Y' ) );
    ?></span>
                        <span class="dot"></span>
                        <span class="readingtime"><?php 
    echo esc_html( mediumish_estimated_reading_time() );
    ?></span>
                    </span>

                    <span class="post-read-more">
                        <a href="<?php 
    echo esc_url( get_permalink() );
    ?>" title="Read Story"><?php 
    echo mediumish_icon_readmore();
    ?></a>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php 
}

// -----------------------------------------------------
// Post Card After Highlight First in Row
// -----------------------------------------------------
function mediumish_post_card_after_highlight() {
    $thumb = get_the_post_thumbnail_url( get_the_ID(), 'medium_large' );
    ?>
    <div class="card post <?php 
    if ( !is_single() ) {
        echo 'h-100';
    }
    ?>">
        <?php 
    if ( $thumb ) {
        ?>
            <a href="<?php 
        echo esc_url( get_permalink() );
        ?>">
                <img class="poststyle1-img" src="<?php 
        echo esc_url( $thumb );
        ?>" alt="<?php 
        echo esc_attr( get_the_title() );
        ?>" />
            </a>
        <?php 
    }
    ?>

        <div class="card-block d-flex flex-column">
            <h2 class="card-title">
                <a href="<?php 
    echo esc_url( get_permalink() );
    ?>"><?php 
    echo esc_html( mediumish_preview_title( 200 ) );
    ?></a>
            </h2>

            <?php 
    if ( !$thumb ) {
        ?>
                <span class="card-text d-block"><?php 
        echo esc_html( excerpt( 25 ) );
        ?></span>
            <?php 
    }
    ?>

            <div class="metafooter mt-auto pt-0">
                <div class="wrapfooter">
                    <span class="meta-footer-thumb">
                        <a href="<?php 
    echo esc_url( get_author_posts_url( get_post_field( 'post_author', get_the_ID() ) ) );
    ?>">
                            <?php 
    echo get_avatar(
        get_the_author_meta( 'user_email' ),
        28,
        null,
        null,
        [
            'class' => ['author-thumb'],
        ]
    );
    ?>
                        </a>
                    </span>

                    <span class="author-meta">
                        <span class="post-name">
                            <a href="<?php 
    echo esc_url( get_author_posts_url( get_post_field( 'post_author', get_the_ID() ) ) );
    ?>"><?php 
    echo esc_html( get_the_author_meta( 'display_name' ) );
    ?></a>
                        </span><br>
                        <span class="post-date"><?php 
    echo esc_html( get_the_date( 'M j, Y' ) );
    ?></span>
                        <span class="dot"></span>
                        <span class="readingtime"><?php 
    echo esc_html( mediumish_estimated_reading_time() );
    ?></span>
                    </span>

                    <span class="post-read-more">
                        <a href="<?php 
    echo esc_url( get_permalink() );
    ?>"><?php 
    echo mediumish_icon_readmore();
    ?></a>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php 
}

// -----------------------------------------------------
// Post Card Tall
// -----------------------------------------------------
function mediumish_post_card_tall() {
    $thumb = get_the_post_thumbnail_url( get_the_ID(), 'medium_large' );
    ?>
    <div class="card post_card_tall h-100" id="post-<?php 
    echo (int) get_the_ID();
    ?>">
        <div class="row h-100">
            <?php 
    if ( $thumb ) {
        ?>
                <div class="col-lg-5 wrapthumbnail">
                    <a class="d-block h-100" href="<?php 
        echo esc_url( get_permalink() );
        ?>">
                        <img src="<?php 
        echo esc_url( $thumb );
        ?>" alt="<?php 
        echo esc_attr( get_the_title() );
        ?>" />
                    </a>
                </div>
            <?php 
    }
    ?>

            <div class="<?php 
    echo ( $thumb ? 'col-lg-7' : 'nothumbimage' );
    ?>">
                <div class="card-block d-flex flex-column h-100">
                    <h2 class="card-title">
                        <a href="<?php 
    echo esc_url( get_permalink() );
    ?>"><?php 
    echo esc_html( mediumish_preview_title( 200 ) );
    ?></a>
                    </h2>

                    <span class="card-text d-block"><?php 
    echo esc_html( excerpt( 20 ) );
    ?></span>

                    <div class="metafooter mt-auto">
                        <div class="wrapfooter">
                            <span class="meta-footer-thumb">
                                <a href="<?php 
    echo esc_url( get_author_posts_url( get_post_field( 'post_author', get_the_ID() ) ) );
    ?>">
                                    <?php 
    echo get_avatar(
        get_the_author_meta( 'user_email' ),
        40,
        null,
        null,
        [
            'class' => ['author-thumb'],
        ]
    );
    ?>
                                </a>
                            </span>

                            <span class="author-meta">
                                <span class="post-name">
                                    <a href="<?php 
    echo esc_url( get_author_posts_url( get_post_field( 'post_author', get_the_ID() ) ) );
    ?>"><?php 
    echo esc_html( get_the_author_meta( 'display_name' ) );
    ?></a>
                                </span><br>
                                <span class="post-date"><?php 
    echo esc_html( get_the_date( 'M j, Y' ) );
    ?></span>
                                <span class="dot"></span>
                                <span class="readingtime"><?php 
    echo esc_html( mediumish_estimated_reading_time() );
    ?></span>
                            </span>

                            <span class="post-read-more">
                                <a href="<?php 
    echo esc_url( get_permalink() );
    ?>"><?php 
    echo mediumish_icon_readmore();
    ?></a>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php 
}

// ──────────────────────────────────────────────────────────
// TheTelos Book Card
// ──────────────────────────────────────────────────────────
function thetelos_book_card( $post_id ) {
    $title     = get_the_title( $post_id );
    $permalink = get_permalink( $post_id );
    $cats      = get_the_category( $post_id );
    $excerpt   = wp_trim_words( get_the_excerpt( $post_id ), 18 );
    $authors   = get_the_terms( $post_id, 'authors' );
    $author    = ( ! empty( $authors ) && ! is_wp_error( $authors ) ) ? $authors[0] : null;
    $analysis  = function_exists( 'thetelos_get_analysis_for_post' ) ? thetelos_get_analysis_for_post( $post_id ) : null;
    $rt        = function_exists( 'thetelos_post_reading_time' ) ? thetelos_post_reading_time( $post_id ) : mediumish_estimated_reading_time();

    ob_start();
    ?>
    <article class="tls-book-card">
        <div class="tls-book-card-cover">
            <a href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true">
                <?php if ( has_post_thumbnail( $post_id ) ) : ?>
                    <?php echo get_the_post_thumbnail( $post_id, [ 200, 300 ], [ 'alt' => esc_attr( $title ) ] ); ?>
                <?php else : ?>
                    <?php echo thetelos_render_book_cover( $post_id ); ?>
                <?php endif; ?>
            </a>
        </div>
        <div class="tls-book-card-body">
            <?php if ( ! empty( $cats ) ) : ?>
                <a class="tls-book-card-cat"
                   href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>">
                    <?php echo esc_html( $cats[0]->name ); ?>
                </a>
            <?php endif; ?>
            <a class="tls-book-card-title" href="<?php echo esc_url( $permalink ); ?>">
                <?php echo esc_html( $title ); ?>
            </a>
            <?php if ( $author ) : ?>
                <a class="tls-book-card-author"
                   href="<?php echo esc_url( get_term_link( $author ) ); ?>">
                    <?php echo esc_html( $author->name ); ?>
                </a>
            <?php endif; ?>
            <p class="tls-book-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
            <div class="tls-book-card-footer">
                <span class="tls-book-card-meta"><?php echo esc_html( $rt ); ?></span>
                <?php if ( $analysis ) : ?>
                    <a class="tls-analysis-badge"
                       href="<?php echo esc_url( $permalink ); ?>#deep-analysis">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                        </svg>
                        Deep Analysis
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

// -----------------------------------------------------
// Related Posts
// -----------------------------------------------------
function mediumish_related_posts(  $args = array()  ) {
    global $post;
    add_filter( 'the_title', 'limit_word_count' );
    // default args
    $args = wp_parse_args( $args, array(
        'post_id'   => ( !empty( $post ) ? $post->ID : '' ),
        'taxonomy'  => 'category',
        'limit'     => 3,
        'post_type' => ( !empty( $post ) ? $post->post_type : 'post' ),
        'orderby'   => 'date',
        'order'     => 'DESC',
    ) );
    // check taxonomy
    if ( !taxonomy_exists( $args['taxonomy'] ) ) {
        return;
    }
    // post taxonomies
    $taxonomies = wp_get_post_terms( $args['post_id'], $args['taxonomy'], array(
        'fields' => 'ids',
    ) );
    if ( empty( $taxonomies ) ) {
        return;
    }
    // query
    $related_posts = get_posts( array(
        'post__not_in'   => (array) $args['post_id'],
        'post_type'      => $args['post_type'],
        'limit'          => 3,
        'tax_query'      => array(array(
            'taxonomy' => $args['taxonomy'],
            'field'    => 'term_id',
            'terms'    => $taxonomies,
        )),
        'posts_per_page' => $args['limit'],
        'orderby'        => $args['orderby'],
        'order'          => $args['order'],
    ) );
    if ( !empty( $related_posts ) ) {
        echo '<div class="tls-books-grid">';
        foreach ( $related_posts as $post ) {
            setup_postdata( $post );
            echo thetelos_book_card( $post->ID );
        }
        echo '</div>';
    }
    wp_reset_postdata();
}

// -----------------------------------------------------
// Return an alternate title, without prefix, for every type used in the get_the_archive_title().
// -----------------------------------------------------
function mediumish_archive_title() {
    if ( is_category() ) {
        $title = single_cat_title( '', false );
    } elseif ( is_tag() ) {
        $title = single_tag_title( '', false );
    } elseif ( is_author() ) {
        $title = '<span class="vcard">' . get_the_author() . '</span>';
    } elseif ( is_year() ) {
        $title = get_the_date( 'Y', 'yearly archives date format' );
    } elseif ( is_month() ) {
        $title = get_the_date( 'F Y', 'monthly archives date format' );
    } elseif ( is_day() ) {
        $title = get_the_date( 'F j, Y', 'daily archives date format' );
    } elseif ( is_post_type_archive() ) {
        $title = post_type_archive_title( '', false );
    } elseif ( is_tax() ) {
        $title = single_term_title( '', false );
    } else {
        $title = _( 'Posts', 'mediumish' );
    }
    return $title;
}

add_filter( 'get_the_archive_title', 'mediumish_archive_title' );
// -----------------------------------------------------
// Add social fields to user profile
// -----------------------------------------------------
if ( !function_exists( 'mediumish_user_fields' ) ) {
    function mediumish_user_fields(  $contactmethods  ) {
        $contactmethods['twitter'] = 'Twitter';
        $contactmethods['facebook'] = 'Facebook';
        $contactmethods['youtube'] = 'YouTube';
        $contactmethods['location'] = 'Location';
        return $contactmethods;
    }

    add_filter(
        'user_contactmethods',
        'mediumish_user_fields',
        10,
        1
    );
}
// -----------------------------------------------------
// Ad Blocks
// -----------------------------------------------------
if ( !function_exists( 'wtn_ad_block_top_article' ) ) {
    function wtn_ad_block_top_article() {
        $toparticle = get_theme_mod( 'toparticle_sectionad' );
        if ( !empty( $toparticle ) ) {
            echo '<div class="wtntopadarticle"><p>' . get_theme_mod( 'toparticle_sectionad' ) . '</p></div>';
        }
    }

}
if ( !function_exists( 'wtn_ad_block_bottom_article' ) ) {
    function wtn_ad_block_bottom_article() {
        $bottomarticle = get_theme_mod( 'bottomarticle_sectionad' );
        if ( !empty( $bottomarticle ) ) {
            echo '<div class="wtnbottomadarticle"><p>' . get_theme_mod( 'bottomarticle_sectionad' ) . '</p></div>';
        }
    }

}
// -----------------------------------------------------
// Hide Featured Image from post
// -----------------------------------------------------
function hide_featured_image_get_meta(  $value  ) {
    global $post;
    $field = get_post_meta( $post->ID, $value, true );
    if ( !empty( $field ) ) {
        return ( is_array( $field ) ? stripslashes_deep( $field ) : stripslashes( wp_kses_decode_entities( $field ) ) );
    } else {
        return false;
    }
}

function hide_featured_image_add_meta_box() {
    add_meta_box(
        'hide_featured_image-hide-featured-image',
        __( 'Hide Featured Image', 'mediumish' ),
        'hide_featured_image_html',
        'post',
        'side',
        'default'
    );
}

add_action( 'add_meta_boxes', 'hide_featured_image_add_meta_box' );
function hide_featured_image_html(  $post  ) {
    wp_nonce_field( '_hide_featured_image_nonce', 'hide_featured_image_nonce' );
    $checkbox_state = ( hide_featured_image_get_meta( 'hide_featured_image_hide_featured_image_on_post' ) === 'hide-featured-image-on-post' ? 'checked' : '' );
    echo '<p>
        <input type="checkbox" name="hide_featured_image_hide_featured_image_on_post" id="hide_featured_image_hide_featured_image_on_post" value="hide-featured-image-on-post" ' . $checkbox_state . '>
        <label for="hide_featured_image_hide_featured_image_on_post">' . __( 'Hide featured image on post', 'mediumish' ) . '</label>
    </p>';
}

function hide_featured_image_save(  $post_id  ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( !isset( $_POST['hide_featured_image_nonce'] ) || !wp_verify_nonce( $_POST['hide_featured_image_nonce'], '_hide_featured_image_nonce' ) ) {
        return;
    }
    if ( !current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( isset( $_POST['hide_featured_image_hide_featured_image_on_post'] ) ) {
        update_post_meta( $post_id, 'hide_featured_image_hide_featured_image_on_post', esc_attr( $_POST['hide_featured_image_hide_featured_image_on_post'] ) );
    } else {
        update_post_meta( $post_id, 'hide_featured_image_hide_featured_image_on_post', null );
    }
}

add_action( 'save_post', 'hide_featured_image_save' );
/* Usage: hide_featured_image_get_meta( 'hide_featured_image_hide_featured_image_on_post' ) */

/**
 * Admin Bar: "Edit Analysis" / "New Analysis" butonu
 * Kitap postlarını görüntülerken admin bar'a eklenir.
 *
 * Bu kodu functions.php'in sonuna ekle.
 *
 * @package Mediumish / TheTelos
 */

add_action( 'admin_bar_menu', 'thetelos_admin_bar_analysis_button', 90 );

function thetelos_admin_bar_analysis_button( $wp_admin_bar ) {

    // Sadece tek kitap postunda çalış
    if ( ! is_singular( 'post' ) ) return;

    // Sadece edit yetkisi olanlar görsün
    if ( ! current_user_can( 'edit_posts' ) ) return;

    global $post;

    $analysis = thetelos_get_analysis_for_post( $post->ID );

    if ( $analysis ) {
        // ── Analiz var: direkt edit sayfasına git ──
        $wp_admin_bar->add_node([
            'id'     => 'thetelos-edit-analysis',
            'title'  => '<span class="ab-icon dashicons dashicons-book-alt" style="top:2px;font-size:16px;"></span> Edit Analysis',
            'href'   => get_edit_post_link( $analysis->ID, 'raw' ),
            'meta'   => [
                'title' => 'Edit analysis for this book',
            ],
        ]);

    } else {
        // ── Analiz yok: yeni analiz sayfası aç, kitap ID'sini URL'e ekle ──
        $new_url = add_query_arg([
            'post_type' => 'analysis',
            'book_id'   => $post->ID,
        ], admin_url( 'post-new.php' ) );

        $wp_admin_bar->add_node([
            'id'     => 'thetelos-new-analysis',
            'title'  => '<span class="ab-icon dashicons dashicons-plus-alt2" style="top:2px;font-size:16px;"></span> New Analysis',
            'href'   => $new_url,
            'meta'   => [
                'title' => 'Create a new analysis for this book',
            ],
        ]);
    }
}


function thetelos_post_reading_time($post_id=null){if(!$post_id)$post_id=get_the_ID();$post=get_post($post_id);$content=strip_tags($post->post_content);$words=str_word_count($content);$minutes=floor($words/295);$seconds=floor($words%295/(295/60));return $minutes>=1?$minutes.' min read':$seconds.' sec read';}

function thetelos_analysis_reading_time($analysis_post){$content=strip_tags($analysis_post->post_content);$words=str_word_count($content);$minutes=floor($words/295);$seconds=floor($words%295/(295/60));return $minutes>=1?$minutes.' min read':$seconds.' sec read';}

function thetelos_similarity($a,$b){$a=mb_strtolower($a);$b=mb_strtolower($b);if($a===$b)return 100;similar_text($a,$b,$pct);$lev=levenshtein($a,$b);$max=max(mb_strlen($a),mb_strlen($b));$lev_score=$max>0?(1-$lev/$max)*100:0;return($pct+$lev_score)/2;}

function thetelos_fuzzy_match($needle,$haystack,$threshold=72){$needle=mb_strtolower(trim($needle));$haystack=mb_strtolower(trim($haystack));if(mb_strlen($needle)<3)return false;if(mb_strpos($haystack,$needle)!==false)return true;foreach(preg_split('/\s+/',$haystack)as $t){if(thetelos_similarity($needle,$t)>=$threshold)return true;}return thetelos_similarity($needle,$haystack)>=$threshold;}

function thetelos_smart_search($query){
    if(!$query->is_search()||!$query->is_main_query()||is_admin())return;
    $raw=trim(get_query_var('s'));
    if(empty($raw))return;
    $all_authors=get_transient('thetelos_all_authors');
    if(false===$all_authors){$all_authors=get_terms(['taxonomy'=>'authors','hide_empty'=>true,'number'=>0]);if(is_wp_error($all_authors))$all_authors=[];set_transient('thetelos_all_authors',$all_authors,3600);}
    $has_sep=preg_match('/[–—\-\/]/',$raw);
    $parts=$has_sep?array_values(array_filter(array_map('trim',preg_split('/\s*[–—\-\/]\s*/u',$raw)),function($p){return mb_strlen($p)>=2;})):array_values(array_filter(preg_split('/\s+/',$raw),function($w){return mb_strlen($w)>=2;}));
    $author_term_ids=[];$title_tokens=[];
    foreach($parts as $part){$found=false;foreach($all_authors as $term){if(thetelos_fuzzy_match($part,$term->name)){$author_term_ids[]=$term->term_id;$found=true;break;}}if(!$found)$title_tokens[]=$part;}
    $author_term_ids=array_unique($author_term_ids);
    if(empty($author_term_ids))return;
    $query->set('tax_query',[['taxonomy'=>'authors','field'=>'term_id','terms'=>$author_term_ids,'operator'=>'IN']]);
    if(!empty($title_tokens)){
        add_filter('posts_search',function($search,$q)use($title_tokens){global $wpdb;if(!$q->is_main_query()||!$q->is_search())return $search;$clauses=[];foreach($title_tokens as $token){$clauses[]=$wpdb->prepare("({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s)",'%'.$wpdb->esc_like($token).'%','%'.$wpdb->esc_like($token).'%');}return' AND ('.implode(' AND ',$clauses).')';},10,2);
    }else{
        add_filter('posts_search',function($search,$q){if(!$q->is_main_query()||!$q->is_search())return $search;return '';},10,2);
    }
}
add_action('pre_get_posts','thetelos_smart_search',5);
add_action('created_authors',function(){delete_transient('thetelos_all_authors');});
add_action('deleted_authors',function(){delete_transient('thetelos_all_authors');});
add_action('edited_authors',function(){delete_transient('thetelos_all_authors');});

/* ══════════════════════════════════════════════════════════════
   SUMMARY REQUEST SYSTEM
   - CPT: tls_request (admin panelde görünür)
   - AJAX handler: tls_submit_req
   - Spam: honeypot + zaman kontrolü + rate limit
══════════════════════════════════════════════════════════════ */

/* 1. CPT kayıt */
add_action( 'init', 'tls_register_request_cpt' );
function tls_register_request_cpt() {
    register_post_type( 'tls_request', [
        'labels' => [
            'name'          => 'Summary Requests',
            'singular_name' => 'Summary Request',
            'menu_name'     => 'Summary Requests',
            'all_items'     => 'All Requests',
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-book-alt',
        'menu_position'=> 25,
        'supports'     => [ 'title' ],
        'capabilities' => [ 'create_posts' => 'do_not_allow' ],
        'map_meta_cap' => true,
    ] );
}

/* 2. Admin kolonları */
add_filter( 'manage_tls_request_posts_columns', function( $cols ) {
    return [
        'cb'         => $cols['cb'],
        'title'      => 'Name',
        'tls_email'  => 'Email',
        'tls_author' => 'Author',
        'tls_book'   => 'Book',
        'tls_status' => 'Status',
        'date'       => 'Date',
    ];
} );
add_action( 'manage_tls_request_posts_custom_column', function( $col, $id ) {
    $map = [
        'tls_email'  => '_tls_email',
        'tls_author' => '_tls_author',
        'tls_book'   => '_tls_book',
    ];
    if ( isset( $map[ $col ] ) ) {
        echo esc_html( get_post_meta( $id, $map[ $col ], true ) );
    } elseif ( $col === 'tls_status' ) {
        $s = get_post_meta( $id, '_tls_status', true ) ?: 'new';
        $l = [ 'new' => '🟡 New', 'reviewed' => '🔵 Reviewed', 'done' => '✅ Done' ];
        echo esc_html( $l[ $s ] ?? $s );
    }
}, 10, 2 );

/* 3. Meta box */
add_action( 'add_meta_boxes', function() {
    add_meta_box( 'tls_req_detail', 'Request Details', 'tls_req_meta_box', 'tls_request', 'normal', 'high' );
} );
function tls_req_meta_box( $post ) {
    $fields = [
        'Email'       => get_post_meta( $post->ID, '_tls_email',  true ),
        'Author'      => get_post_meta( $post->ID, '_tls_author', true ),
        'Book'        => get_post_meta( $post->ID, '_tls_book',   true ),
        'IP'          => get_post_meta( $post->ID, '_tls_ip',     true ),
    ];
    $status = get_post_meta( $post->ID, '_tls_status', true ) ?: 'new';
    wp_nonce_field( 'tls_req_save', 'tls_req_nonce_field' );
    echo '<table style="width:100%;border-collapse:collapse;">';
    foreach ( $fields as $label => $val ) {
        echo '<tr><td style="padding:7px 0;width:100px;color:#666;font-weight:600;">' . esc_html($label) . '</td>';
        echo '<td>' . esc_html($val) . '</td></tr>';
    }
    echo '<tr><td style="padding:7px 0;color:#666;font-weight:600;">Status</td><td>';
    echo '<select name="tls_req_status" style="padding:4px 8px;">';
    foreach ( ['new'=>'New','reviewed'=>'Reviewed','done'=>'Done'] as $v => $l ) {
        echo '<option value="' . esc_attr($v) . '"' . selected($status,$v,false) . '>' . esc_html($l) . '</option>';
    }
    echo '</select></td></tr></table>';
}
add_action( 'save_post_tls_request', function( $id ) {
    if ( ! isset($_POST['tls_req_nonce_field']) ) return;
    if ( ! wp_verify_nonce($_POST['tls_req_nonce_field'], 'tls_req_save') ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( isset($_POST['tls_req_status']) ) {
        update_post_meta( $id, '_tls_status', sanitize_text_field($_POST['tls_req_status']) );
    }
} );

/* 4. AJAX handler */
add_action( 'wp_ajax_tls_submit_req',        'tls_handle_req' );
add_action( 'wp_ajax_nopriv_tls_submit_req', 'tls_handle_req' );
function tls_handle_req() {
    if ( ! check_ajax_referer( 'tls_req_nonce', 'nonce', false ) ) {
        wp_send_json_error( ['message' => 'Security check failed.'], 403 );
    }
    /* Honeypot */
    if ( ! empty( $_POST['tls_hp'] ) ) {
        wp_send_json_success( ['message' => 'ok'] );
        return;
    }
    /* Zaman kontrolü */
    if ( ! is_user_logged_in() && (int)( $_POST['tls_time'] ?? 0 ) && time() - (int)$_POST['tls_time'] < 2 ) {
        wp_send_json_error( ['message' => 'Please slow down.'] );
        return;
    }
    /* Rate limit */
    $ip  = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
    $key = 'tls_r_' . md5( $ip );
    if ( (int) get_transient( $key ) >= 5 ) {
        wp_send_json_error( ['message' => 'Too many requests. Try again later.'] );
        return;
    }
    /* Alanlar */
    $name   = sanitize_text_field( $_POST['tls_name']   ?? '' );
    $email  = sanitize_email(      $_POST['tls_email']  ?? '' );
    $author = sanitize_text_field( $_POST['tls_author'] ?? '' );
    $book   = sanitize_text_field( $_POST['tls_book']   ?? '' );

    if ( ! $name || ! is_email($email) || ! $author || ! $book ) {
        wp_send_json_error( ['message' => 'Please fill in all required fields.'] );
        return;
    }
    /* Kaydet */
    $pid = wp_insert_post( [
        'post_type'   => 'tls_request',
        'post_title'  => $name,
        'post_status' => 'publish',
    ] );
    if ( is_wp_error($pid) ) {
        wp_send_json_error( ['message' => 'Could not save. Please try again.'] );
        return;
    }
    update_post_meta( $pid, '_tls_email',  $email );
    update_post_meta( $pid, '_tls_author', $author );
    update_post_meta( $pid, '_tls_book',   $book );
    update_post_meta( $pid, '_tls_status', 'new' );
    update_post_meta( $pid, '_tls_ip',     $ip );

    set_transient( $key, (int) get_transient($key) + 1, HOUR_IN_SECONDS );

    /* Email */
    wp_mail(
        get_option('admin_email'),
        '[thetelos] New Request: ' . $book,
        "Name: {$name}\nEmail: {$email}\nAuthor: {$author}\nBook: {$book}\n\nAdmin: " . admin_url("post.php?post={$pid}&action=edit")
    );

    wp_send_json_success( ['message' => 'ok'] );
}

/* ── Helper — post'un alıntılarını döndür (AI tarafından doldurulur) ── */
function tls_get_quotes( int $post_id ): array {
    return (array)( get_post_meta( $post_id, '_tls_quotes', true ) ?: [] );
}

/* ══════════════════════════════════════════════
   NEWSLETTER SUBSCRIBER SİSTEMİ
   - Mailler wp_options'da saklanır
   - Admin → Tools → Newsletter Subscribers
══════════════════════════════════════════════ */

/* AJAX kayıt */
add_action('wp_ajax_tls_newsletter_subscribe',        'tls_handle_newsletter');
add_action('wp_ajax_nopriv_tls_newsletter_subscribe', 'tls_handle_newsletter');

function tls_handle_newsletter() {
    if ( ! check_ajax_referer('tls_newsletter', 'nonce', false) )
        wp_send_json_error(['message' => 'Security check failed.']);

    $email = sanitize_email($_POST['email'] ?? '');
    if ( ! is_email($email) )
        wp_send_json_error(['message' => 'Please enter a valid email address.']);

    $subscribers = get_option('tls_newsletter_subscribers', []);

    // Zaten kayıtlıysa
    foreach ($subscribers as $sub) {
        if ($sub['email'] === $email) {
            wp_send_json_success(['message' => 'Already subscribed!']);
        }
    }

    $subscribers[] = [
        'email' => $email,
        'date'  => current_time('Y-m-d H:i:s'),
        'ip'    => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
    ];

    update_option('tls_newsletter_subscribers', $subscribers);
    wp_send_json_success(['message' => 'Subscribed!']);
}

/* Admin sayfası */
add_action('admin_menu', function() {
    add_management_page(
        'Newsletter Subscribers',
        '📧 Newsletter',
        'manage_options',
        'tls-newsletter',
        'tls_newsletter_admin_page'
    );
});

function tls_newsletter_admin_page() {
    // Silme işlemi
    if ( isset($_POST['tls_delete_email']) && check_admin_referer('tls_nl_delete') ) {
        $email = sanitize_email($_POST['tls_delete_email']);
        $subs  = get_option('tls_newsletter_subscribers', []);
        $subs  = array_filter($subs, fn($s) => $s['email'] !== $email);
        update_option('tls_newsletter_subscribers', array_values($subs));
        echo '<div class="notice notice-success"><p>Removed: ' . esc_html($email) . '</p></div>';
    }

    $subscribers = get_option('tls_newsletter_subscribers', []);
    ?>
    <div class="wrap">
        <h1>📧 Newsletter Subscribers <span style="font-size:14px;color:#666;">(<?php echo count($subscribers); ?> total)</span></h1>

        <?php if (empty($subscribers)) : ?>
            <p>No subscribers yet.</p>
        <?php else : ?>

        <!-- Export CSV -->
        <p>
            <a href="<?php echo admin_url('tools.php?page=tls-newsletter&export=csv'); ?>" class="button">
                ⬇ Export CSV
            </a>
        </p>

        <table class="widefat striped" style="max-width:700px;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Email</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (array_reverse($subscribers) as $i => $sub) : ?>
                <tr>
                    <td><?php echo count($subscribers) - $i; ?></td>
                    <td><?php echo esc_html($sub['email']); ?></td>
                    <td><?php echo esc_html($sub['date']); ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('tls_nl_delete'); ?>
                            <input type="hidden" name="tls_delete_email" value="<?php echo esc_attr($sub['email']); ?>">
                            <button type="submit" class="button-link" style="color:#b91c1c;"
                                onclick="return confirm('Remove this subscriber?')">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php

    // CSV export
    if ( isset($_GET['export']) && $_GET['export'] === 'csv' ) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="newsletter-subscribers.csv"');
        echo "Email,Date\n";
        foreach ($subscribers as $sub) {
            echo esc_html($sub['email']) . ',' . esc_html($sub['date']) . "\n";
        }
        exit;
    }
}

// -----------------------------------------------------
// SEO: Yoast — Custom "authors" taxonomy entegrasyonu
// custom-post-type-ui ile kayıtlı authors taxonomy'sini
// Yoast'a tanıtır: sitemap dahil edilir, Search Appearance
// panelinde görünür hale gelir.
// -----------------------------------------------------
add_action( 'init', 'thetelos_authors_yoast_integration', 20 );
function thetelos_authors_yoast_integration() {
    if ( ! defined( 'WPSEO_VERSION' ) ) return;

    // Authors taxonomy'sini Yoast sitemap'ine dahil et
    add_filter( 'wpseo_sitemap_exclude_taxonomy', function( $excluded, $taxonomy ) {
        if ( $taxonomy === 'authors' ) return false;
        return $excluded;
    }, 10, 2 );
}

// Authors taxonomy sayfaları için otomatik SEO title
add_filter( 'wpseo_title', 'thetelos_authors_taxonomy_seo_title' );
function thetelos_authors_taxonomy_seo_title( $title ) {
    if ( ! is_tax( 'authors' ) ) return $title;
    if ( ! empty( $title ) ) return $title;
    $term = get_queried_object();
    if ( ! $term ) return $title;
    return $term->name . ' Book Summaries | ' . get_bloginfo( 'name' );
}

// Authors taxonomy sayfaları için otomatik meta description
add_filter( 'wpseo_metadesc', 'thetelos_authors_taxonomy_seo_desc' );
function thetelos_authors_taxonomy_seo_desc( $desc ) {
    if ( ! is_tax( 'authors' ) ) return $desc;
    if ( ! empty( $desc ) ) return $desc;
    $term = get_queried_object();
    if ( ! $term ) return $desc;
    if ( ! empty( $term->description ) ) return $term->description;
    return 'Explore book summaries and key insights from ' . $term->name . ' on The Telos.';
}

// -----------------------------------------------------
// SEO: 301 Redirects — Eski ve hatalı URL'ler
// Tüm kalıcı yönlendirmeler buradan yönetilir.
// Yeni redirect eklemek için sadece $redirects dizisine
// 'eski-url' => 'yeni-url' formatında satır ekle.
// -----------------------------------------------------
add_action( 'template_redirect', 'thetelos_seo_redirects' );
function thetelos_seo_redirects() {
    $redirects = [
        // Türkçe sayfalar → İngilizce
        '/yazarlar/'                          => '/authors/',
        '/gizlilik-ilkesi/'                   => '/privacy-policy/',
        '/ornek-sayfa/'                       => '/about/',

        // Eski WordPress author URL → authors taxonomy
        '/author/ad392f64cca76908d723/'       => '/authors/',
        '/author/ad392f64cca76908d723/page/10/' => '/authors/',

        // Pagination 404'leri → kategori ana sayfaları
        '/category/history-of-philosophy/page/8/' => '/category/history-of-philosophy/',
        '/category/science/page/3/'           => '/category/science/',
        '/category/metaphysics/page/4/'       => '/category/metaphysics/',
        '/authors/plato/page/3/'              => '/authors/plato/',
        '/page/10/'                           => '/',
    ];

    $request = isset( $_SERVER['REQUEST_URI'] )
        ? rtrim( parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' ) . '/'
        : '';

    foreach ( $redirects as $old => $new ) {
        if ( $request === $old ) {
            wp_redirect( home_url( $new ), 301 );
            exit;
        }
    }
}
