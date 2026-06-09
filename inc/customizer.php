<?php

use Kirki\Util\Helper;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

// Do not proceed if Kirki does not exist.
if (!class_exists('Kirki')) {
	return;
}

// -----------------------------------------------------
// Kirki Fonts All Variants
// -----------------------------------------------------
function mediumish_font_add_all_variants()
{
    if (class_exists('Kirki_Fonts_Google')) {
        Kirki_Fonts_Google::$force_load_all_variants = false;
    }
}

add_action('init', 'mediumish_font_add_all_variants');

/**
 * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
 */
function mediumish_customize_preview_js()
{
	wp_enqueue_script('mediumish_customizer', get_template_directory_uri() . '/assets/js/customizer.js', array('customize-preview'), '20151215', true);
}
add_action('customize_preview_init', 'mediumish_customize_preview_js');

add_action( 'customize_controls_print_styles', function() {
	?>
	<style>
		.customize-control-repeater .repeater-fields .repeater-row .repeater-field-categorystyle label img {
			display: block;
			margin-bottom: 10px;
			border: 3px solid #c4e3d9;
			border-radius: 5px;
	}
		#sub-accordion-section-typography .customize-control-label .customize-control-title {
			color:#ff3475;
		}
		 /* Hide "Number of Posts" only when the row has style-1 selected */
		.customize-control-repeater .repeater-row:has(.repeater-field-categorystyle input[value="style-1"]:checked)
		.repeater-field-postsperpage {
		display: none !important;
		}
	</style>
	<?php
} );

// === choices helper: return taxonomy choices keyed by SLUG (stable across sites)
if (!function_exists('mediumish_terms_choices_by_slug')) {
    function mediumish_terms_choices_by_slug(string $taxonomy): array {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        $out = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                $out[$t->slug] = $t->name; // KEY = slug
            }
        }
        return $out;
    }
}

// === normalize helper: accept slug OR ID and return a real term_id (for back-compat)
if (!function_exists('wt_normalize_term_id')) {
    function wt_normalize_term_id($value, string $taxonomy): int {
        if (is_numeric($value)) return (int) $value;               // old saved ID
        if (is_string($value) && $value !== '') {
            if ($t = get_term_by('slug', $value, $taxonomy)) return (int) $t->term_id;
            if ($t = get_term_by('name', $value, $taxonomy)) return (int) $t->term_id;
        }
        return 0;
    }
}


//Theme Config
Kirki::add_config(
	'mediumish_theme',
	[
		'option_type' => 'theme_mod',
		'capability'  => 'manage_options',
	]
);


// Theme Panel
new \Kirki\Panel(
	'mainthemepanel_mediumish',
	[
		'priority'    => 10,
		'title'       => esc_html__('Theme Options', 'kirki'),
		'description' => esc_html__('Theme Options', 'kirki'),
	]
);


//-----------------------------------------------------
// SECTION: COLOR SCHEMES
//-----------------------------------------------------

new \Kirki\Section(
	'section_colorschemes',
	[
		'title'       => esc_html__('Global Colors', 'kirki'),
		'panel'       => 'mainthemepanel_mediumish',
		'priority'    => 1,
	]
);

/**
 * Slider Button Colour
 */

new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_sliderbutton',
		'label'       => esc_attr__('Slider Button', 'mediumish'),
		'section'     => 'section_colorschemes',
		'priority'    => 10,
		'default'     => '#1C9963',
		'output' => array(
			array(
				'element'  => '.btn-simple',
				'property' => 'background-color',
			),
			array(
				'element'  => '.btn-simple',
				'property' => 'border-color',
			),
		),
	]
);

/**
 * Article Links Color
 */
new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_articlelinks_color',
		'label'       => esc_attr__('Article Links', 'mediumish'),
		'section'     => 'section_colorschemes',
		'priority'    => 10,
		'default'     => '#26a16e',
		'output' => array(
			array(
				'element'  => '.page-link, .article-post a:not(.wp-block-button__link), .post .btn.follow, .post .post-top-meta .author-description a, article.page a:not(.wp-block-button__link), .alertbar a',
				'property' => 'color',
			),
			array(
				'element'  => '.post .btn.follow, .alertbar input[type="submit"]',
				'property' => 'border-color',
			),
		),
	]
);

/**
 * Blockquotes Border Colour
 */
new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_blockquote_border_color',
		'label'       => esc_attr__('Blockquote Border', 'mediumish'),
		'section'     => 'section_colorschemes',
		'priority'    => 10,
		'default'     => '#1C9963',
		'output' => array(
			array(
				'element'  => 'blockquote',
				'property' => 'border-color',
			),
		),
	]
);


/**
 * Button BG Color
 */
new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_submitbutton_color',
		'label'       => esc_attr__('Submit Button', 'mediumish'),
		'section'     => 'section_colorschemes',
		'priority'    => 10,
		'default'     => '#1C9963',
		'output' => array(
			array(
				'element'  => '.current .page-link, .entry-content input[type=submit], .alertbar input[type="submit"]',
				'property' => 'background-color',
			),
			array(
				'element'  => '.current .page-link, .entry-content input[type=submit], .alertbar input[type="submit"]',
				'property' => 'border-color',
			),
		),
	]
);

/**
 * Social Title Color
 */
new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_share_color_title',
		'label'       => esc_attr__('Share Title', 'mediumish'),
		'section'     => 'section_colorschemes',
		'priority'    => 10,
		'default'     => '#999999',
		'output' => array(
			array(
				'element'  => 'p.sharecolour',
				'property' => 'color',
			),
		),
	]
);

/**
 * Social Icon Color
 */
new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_share_color',
		'label'       => esc_attr__('Share Icons', 'mediumish'),
		'section'     => 'section_colorschemes',
		'priority'    => 10,
		'default'     => '#b3b3b3',
		'output' => array(
			array(
				'element'  => '.shareitnow ul li a svg, .shareitnow a',
				'property' => 'fill',
			),
			array(
				'element'  => '.shareitnow li a',
				'property' => 'color',
			),
		),
	]
);

/**
 * Social Icon Color
 */
new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_share_color_border',
		'label'       => esc_attr__('Share Icons Border', 'mediumish'),
		'section'     => 'section_colorschemes',
		'priority'    => 10,
		'default'     => '#d2d2d2',
		'output' => array(
			array(
				'element'  => '.shareitnow li a',
				'property' => 'border-color',
			),
		),
	]
);

/**
 * Comment Accent Colour
 */
new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_comments_accentcolor',
		'label'       => esc_attr__('Comments Accent Color', 'mediumish'),
		'section'     => 'section_colorschemes',
		'priority'    => 10,
		'default'     => '#1C9963',
		'output' => array(
			array(
				'element'  => '#comments a',
				'property' => 'color',
			),
			array(
				'element'  => '.comment-form input.submit',
				'property' => 'background-color',
			),
			array(
				'element'  => '.comment-form input.submit',
				'property' => 'border-color',
			),
		),
	]
);


/**
 * Footer Links Color
 */
new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_footerlinks_color',
		'label'       => esc_attr__('Footer Links', 'mediumish'),
		'section'     => 'section_colorschemes',
		'priority'    => 10,
		'default'     => '#1C9963',
		'output' => array(
			array(
				'element'  => 'footer.footer a',
				'property' => 'color',
			),
		),
	]
);

/**
 * Instruction
 */
new \Kirki\Field\Custom(
	[
		'settings'    => 'mediumish_instruction_colorschemes',
		'label'       => esc_attr__('More options', 'mediumish'),
		'section'     => 'section_colorschemes',
		'priority'    => 10,
		'default'     => 'You can navigate back to select Header/Logo Area and Typography sections for more specific color customization.',
	]
);

//-----------------------------------------------------
// SECTION: TYPOGRAPHY
//-----------------------------------------------------
new \Kirki\Section(
	'typography',
	[
		'title'       => esc_html__('Typography', 'kirki'),
		'panel'       => 'mainthemepanel_mediumish',
		'priority'    => 1,
	]
);

new \Kirki\Section(
	'mediumish_google_fonts_delivery',
	[
		'title'       => esc_html__('Google Fonts Delivery', 'mediumish'),
		'panel'       => 'mainthemepanel_mediumish',
		'priority'    => 2,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings' => 'mediumish_google_fonts_enable',
		'label' => esc_attr__('Load Google Fonts from the Google CDN', 'mediumish'),
		'section' => 'mediumish_google_fonts_delivery',
		'priority' => 1,
		'default' => true,
	]
);

new \Kirki\Field\Multicheck(
	[
		'settings' => 'mediumish_google_fonts_subsets',
		'label' => esc_attr__('Font subsets to request', 'mediumish'),
		'section' => 'mediumish_google_fonts_delivery',
		'priority' => 2,
		'default' => ['latin', 'latin-ext'],
		'choices' => mediumish_allowed_font_subsets(),
		'active_callback' => [
			[
				'setting' => 'mediumish_google_fonts_enable',
				'operator' => '==',
				'value' => true,
			],
		],
		'description' => esc_html__('Only the selected subsets are requested from Google to keep downloads lean.', 'mediumish'),
	]
);

new \Kirki\Field\Multicheck(
	[
		'settings' => 'mediumish_google_fonts_variants',
		'label' => esc_attr__('Extra font variants to preload', 'mediumish'),
		'section' => 'mediumish_google_fonts_delivery',
		'priority' => 3,
		'default' => ['regular', 'italic', '700', '700italic'],
		'choices' => mediumish_allowed_font_variants(),
		'description' => esc_html__('These variants are added for every selected font so bold and italic text render crisply. Keep the list lean to avoid unnecessary payload.', 'mediumish'),
		'active_callback' => [
			[
				'setting' => 'mediumish_google_fonts_enable',
				'operator' => '==',
				'value' => true,
			],
		],
	]
);

new \Kirki\Field\Custom(
	[
		'settings' => 'mediumish_google_fonts_notice',
		'section' => 'mediumish_google_fonts_delivery',
		'priority' => 4,
		'default' => '<p><strong>' . esc_html__('Important:', 'mediumish') . '</strong> ' . esc_html__('If you enable Google-hosted fonts you must update your privacy policy or cookie notice to inform visitors, in accordance with GDPR.', 'mediumish') . '</p>',
	]
);


/**
 * Add the body-typography control
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'body_typography',
		'label'       => esc_attr__('Body Typography', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'variant'        => 'regular',
			'font-size'      => '16px',
			'line-height'    => '1.5',
			'color'          => '#5f636d',
			'letter-spacing' => '0px'
		),
		'output' => array(
			array(
				'element' => array('body'),
			),
		),
	]
);


/**
 * Header Typograf
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'headers_typography',
		'label'       => esc_attr__('Headings', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'variant'        => '700',
			'color'          => '#222',
			'letter-spacing' => '-0.01em'
		),
		'output' => array(
			array(
				'element' => array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', '.h1', '.h2', '.h3', '.h4', '.h5', '.h6, .card-title a'),
			),
		),
	]
);


/**
 * Slider
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'slidertitle_typography',
		'label'       => esc_attr__('Slider Title', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'variant'        => '600',
			'font-size'      => '1.7em',
			'line-height'      => '1.2',
			'color'          => '#ffffff',
			'text-transform' => 'none',
			'letter-spacing' => '0',
		),
		'output' => array(
			array(
				'element' => array('h3.carousel-excerpt .title'),
			),
		),
	]
);

new \Kirki\Field\Typography(
	[
		'settings'    => 'sliderdescription_typography',
		'label'       => esc_attr__('Slider Excerpt', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'   => '',
			'variant'        => 'regular',
			'font-size'      => '18px',
			'line-height'      => '1.4',
			'color'          => '#ffffff',
			'text-transform' => 'none',
			'letter-spacing' => '0px'
		),
		'output' => array(
			array(
				'element' => array('h3.carousel-excerpt .fontlight'),
			),
		),
	]
);


/**
 * Add the logo-typography control
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'logo_typography',
		'label'       => esc_attr__('Logo', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => 'serif',
			'variant'        => '700',
			'font-size'      => '30px',
			'text-transform' => 'none',
			'letter-spacing' => '0px'
		),
		'output' => array(
			array(
				'element' => array('.mediumnavigation .navbar-brand'),
			),
		),
	]
);


/**
 * Add the menu_typography control
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'menu_typography',
		'label'       => esc_attr__('Menu', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'font-size'      => '14px',
			'variant'        => '400',
			'letter-spacing' => '0.02em',
			'text-transform' => 'uppercase',
		),
		'output' => array(
			array(
				'element' => array('.navbar .nav-link'),
			),
		),
	]
);




/**
 * Card 1 Bg
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'card1big_typography',
		'label'       => esc_attr__('Card Style 1 - Bigger Headline', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'line-height'    => '1.3',
			'font-size'      => '1.75em',
			'text-transform' => 'none',
			'letter-spacing' => '0px'

		),
		'output' => array(
			array(
				'element' => array('.post-style-1 .poststyle1big .card-title'),
			),
		),
	]
);

/**
 * Card 1
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'card1_typography',
		'label'       => esc_attr__('Card Style 1 - Headline', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'line-height'    => '1.3',
			'font-size'      => '1.27em',
			'text-transform' => 'none',
			'letter-spacing' => '0px'

		),
		'output' => array(
			array(
				'element' => array('.post-style-1 .card-title'),
			),
		),
	]
);

/**
 * Card 2
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'card2_typography',
		'label'       => esc_attr__('Card Style 2 - Headline', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'line-height'    => '1.3',
			'font-size'      => '1.4em',
			'text-transform' => 'none',
			'letter-spacing' => '0px'

		),
		'output' => array(
			array(
				'element' => array('.post-style-2 .card-title'),
			),
		),
	]
);

/**
 * Card 3
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'card3_typography',
		'label'       => esc_attr__('Card Style 3 - Headline', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'line-height'    => '1.27',
			'font-size'      => '1.4em',
			'text-transform' => 'none',
			'letter-spacing' => '0px'

		),
		'output' => array(
			array(
				'element' => array('.post-style-3 .card-title'),
			),
		),
	]
);

/**
 * Card 4
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'card4_typography',
		'label'       => esc_attr__('Card Style 4 - Headline', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'line-height'    => '1.27',
			'font-size'      => '1.2em',
			'text-transform' => 'none',
			'letter-spacing' => '0px'

		),
		'output' => array(
			array(
				'element' => array('.post-style-4 .card-title'),
			),
		),
	]
);

/**
 * Card 5
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'card5_typography',
		'label'       => esc_attr__('Card Style 5 - Headline', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'line-height'    => '1.27',
			'font-size'      => '1.15em',
			'text-transform' => 'none',
			'letter-spacing' => '0px'

		),
		'output' => array(
			array(
				'element' => array('.post-style-5 .card-title'),
			),
		),
	]
);

/**
 * Card 6
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'card6_typography',
		'label'       => esc_attr__('Card Style 6 - Headline', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'line-height'    => '1.27',
			'font-size'      => '1.45em',
			'text-transform' => 'none',
			'letter-spacing' => '0px'

		),
		'output' => array(
			array(
				'element' => array('.post-style-6 .card-title'),
			),
		),
	]
);

/**
 * Default
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'card_default_typography',
		'label'       => esc_attr__('Card Default - Headline', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'line-height'    => '1.3',
			'font-size'      => '1.4em',
			'text-transform' => 'none',
			'letter-spacing' => '0px'

		),
		'output' => array(
			array(
				'element' => array('.card-title'),
			),
		),
	]
);

/**
 * Card Text
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'card_text_typography',
		'label'       => esc_attr__('Card Excerpt', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'line-height'    => '1.55',
			'font-size'      => '1em',
			'text-transform' => 'none',
			'letter-spacing' => '0px'

		),
		'output' => array(
			array(
				'element' => array('.card-text'),
				'media_query' => '@media (min-width: 768px)',
			),
		),
	]
);

/**
 * Headline Sections
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'headline_section_typography',
		'label'       => esc_attr__('Sections - Headline', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'line-height'    => '1.3',
			'font-size'      => '1.4em',
			'text-transform' => 'capitalize',
			'letter-spacing' => '0px'

		),
		'output' => array(
			array(
				'element' => array('.section-title h2'),
			),
		),
	]
);

new \Kirki\Field\Typography(
	[
		'settings'    => 'singlearticletitle_typography',
		'label'       => esc_attr__('Article Headline', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'line-height'    => '1.1',
			'color'          => '#222222',
			'font-size'      => '3.4em'

		),
		'output' => array(
			array(
				'element' => array('.mainheading h1.posttitle, h1.entry-title'),
			),
		),
	]
);

/**
 * Article Typography
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'singlearticle_typography',
		'label'       => esc_attr__('Article', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => 'Merriweather',
			'line-height'    => '1.75',
			'color'          => '#222222',
			'font-size'      => '18.5px'

		),
		'output' => array(
			array(
				'element' => array('.article-post'),
			),
		),
	]
);


/**
 * Comments Typography
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'comments_typography',
		'label'       => esc_attr__('Comments', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => 'Merriweather',
			'line-height'    => '1.6',
			'color'          => '#555',
			'font-size'      => '1em'

		),
		'output' => array(
			array(
				'element' => array('#comments .comment-content'),
			),
		),
	]
);




/**
 * Page Typography
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'singlepage_typography',
		'label'       => esc_attr__('Page', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'line-height'    => '1.7',
			'color'          => '',
			'font-size'      => '1.2em'

		),
		'output' => array(
			array(
				'element' => array('body.page .entry-content.page-content'),
			),
		),
	]
);


/**
 * Card 3
 */
new \Kirki\Field\Typography(
	[
		'settings'    => 'mediumish_footer_typography',
		'label'       => esc_attr__('Footer', 'mediumish'),
		'section'     => 'typography',
		'priority'    => 10,
		'default'     => array(
			'line-height'    => '1.1',
			'font-size'      => '0.85em',
			'text-transform' => 'none',
			'letter-spacing' => '0px'

		),
		'output' => array(
			array(
				'element' => array('.footer'),
			),
		),
	]
);



//-----------------------------------------------------
// SECTION: Logo & Header Area
//-----------------------------------------------------

/**
 * sectionlogonav Logo
 */

new \Kirki\Section(
	'sectionlogonav',
	[
		'title'       => esc_html__('Header & Logo', 'kirki'),
		'panel'       => 'mainthemepanel_mediumish',
		'priority'    => 1,
	]
);


new \Kirki\Field\Image(
	[
		'settings'    => 'logo_sectionlogonav',
		'label'       => esc_html__('Logo', 'mediumish'),
		'description'     => 'Maximum recommended px size: 200x60',
		'section'     => 'sectionlogonav',
		'priority'    => 10,
		'default'     => '',
	]
);

/**
 * sectionlogonav Header Bg Color
 */

new \Kirki\Field\Color(
	[
		'settings'    => 'bg_navbgcolor',
		'label'       => esc_attr__('Header Background Color', 'mediumish'),
		'section'     => 'sectionlogonav',
		'priority'    => 10,
		'default'     => '#fff',
		'output' => array(
			array(
				'element'  => '.mediumnavigation, .dropdown-menu, .dropdown-item',
				'property' => 'background-color',
			),
			array(
				'element'  => '.navbar-collapse',
				'property' => 'background-color',
				'media_query' => '@media (max-width: 767px)',
			),

		),
	]
);



new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_headertextcolor',
		'label'       => esc_attr__('Header Text Color', 'mediumish'),
		'section'     => 'sectionlogonav',
		'priority'    => 10,
		'default'     => '#888',
		'output' => array(
			array(
				'element'  => '.mediumnavigation, .mediumnavigation a, .navbar-light .navbar-nav .nav-link',
				'property' => 'color',
			),

		),
	]
);

new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_logotextcolor',
		'label'       => esc_attr__('Site Title Text Color', 'mediumish'),
		'description' => esc_attr__('If you do not use a logo', 'mediumish'),
		'section'     => 'sectionlogonav',
		'priority'    => 10,
		'default'     => '#111111',
		'output' => array(
			array(
				'element'  => '.navbar-light .navbar-brand',
				'property' => 'color',
			),
		),
	]
);

new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_logohovercolor',
		'label'       => esc_attr__('Site title Hover Color', 'mediumish'),
		'description' => esc_attr__('If you do not use a logo', 'mediumish'),
		'section'     => 'sectionlogonav',
		'priority'    => 10,
		'default'     => '#111',
		'output' => array(
			array(
				'element'  => '.navbar-light .navbar-brand:hover',
				'property' => 'color',
			),
		),
	]
);

/**
 * sectionlogonav Header social
 */

new \Kirki\Field\URL(
	[
		'settings'    => 'mediumish_headertwitterlink',
		'label'       => esc_attr__('Twitter Link', 'mediumish'),
		'description' => __('Leave blank to disable', 'mediumish'),
		'section'     => 'sectionlogonav',
		'priority'    => 10,
		'default'     => 'https://x.com/',

	]
);

new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_headertwittercolor',
		'label'       => esc_attr__('Twitter Button Color', 'mediumish'),
		'section'     => 'sectionlogonav',
		'priority'    => 10,
		'default'     => '#02B875',
		'output' => array(
			array(
				'element'  => '.customarea .btn.follow',
				'property' => 'border-color',
			),
			array(
				'element'  => '.customarea .btn.follow',
				'property' => 'color',
			),
		),

	]
);

new \Kirki\Field\Repeater(
	[
		'label'       => esc_attr__('Custom social link', 'mediumish'),
		'section'     => 'sectionlogonav',
		'priority'    => 10,
		'row_label' => array(
			'type' => 'text',
			'value' => esc_attr__('Social Link', 'mediumish'),
		),
		'settings'    => 'mediumish_headersociallink',
		'default'     => array(
			array(
				'social_icon' => esc_attr__('youtube', 'mediumish'),
				'social_url'  => 'https://www.youtube.com/',
			),
			array(
				'social_icon' => esc_attr__('facebook', 'mediumish'),
				'social_url'  => 'https://facebook.com/wowthemesnet/',
			),
		),
		'fields' => array(
			'social_icon' => array(
				'type'        => 'text',
				'label'       => esc_attr__('Social Icon Keyword', 'mediumish'),
				'description' => esc_attr__('Enter Font Awesome keywords for social icons such as facebook, youtube etc. For more social keywords see the Font Awesome icon list.', 'mediumish'),
				'default'     => '',
			),
			'social_url' => array(
				'type'        => 'text',
				'label'       => esc_attr__('Social Link URL', 'mediumish'),
				'description' => esc_attr__('Enter the corresponding Link URL. Link opens in new tab.', 'mediumish'),
				'default'     => '',
			),
		)
	]
);


/**
 * sectionlogonav Header Search
 */

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'mediumish_headersearch_active',
		'label'       => esc_attr__('Disable search in header', 'mediumish'),
		'section'     => 'sectionlogonav',
		'priority'    => 10,
		'default'     => false,
	]
);

new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_headersearchcolor',
		'label'       => esc_attr__('Search Button Color', 'mediumish'),
		'section'     => 'sectionlogonav',
		'priority'    => 10,
		'default'     => '#1C9963',
		'output' => array(
			array(
				'element'  => '.search-form .search-submit',
				'property' => 'background-color',
			),
		),
		'active_callback' => [['setting' => 'mediumish_headersearch_active', 'operator' => '==', 'value' => 0]],
	]
);

new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_headersearchcolor_border',
		'label'       => esc_attr__('Search Border Color', 'mediumish'),
		'section'     => 'sectionlogonav',
		'priority'    => 10,
		'default'     => '#eeeeee',
		'output' => array(
			array(
				'element'  => '.search-form .search-field',
				'property' => 'border-color',
			),
		),
		'active_callback' => [['setting' => 'mediumish_headersearch_active', 'operator' => '==', 'value' => 0]],
	]
);


new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_headersearchcolor_icon',
		'label'       => esc_attr__('Search Button Icon Color', 'mediumish'),
		'section'     => 'sectionlogonav',
		'priority'    => 10,
		'default'     => '#ffffff',
		'output' => array(
			array(
				'element'  => '.search-form .search-submit .fa',
				'property' => 'color',
			),
		),
		'active_callback' => [['setting' => 'mediumish_headersearch_active', 'operator' => '==', 'value' => 0]],
	]
);

new \Kirki\Field\Color(
	[
		'settings'    => 'mediumish_headersearchcolor_placeholder',
		'label'       => esc_attr__('Search Placeholder Color', 'mediumish'),
		'section'     => 'sectionlogonav',
		'priority'    => 10,
		'default'     => '#b2b2b2',
		'output' => array(
			array(
				'element'  => '.search-form .search-field, .search-form .search-field::placeholder',
				'property' => 'color',
			),
		),
		'active_callback' => [['setting' => 'mediumish_headersearch_active', 'operator' => '==', 'value' => 0]],
	]
);


//-----------------------------------------------------
// SECTION: Home
//-----------------------------------------------------

/**
 * sectionhome Slider
 */

new \Kirki\Section(
	'sectionhome',
	[
		'title'       => esc_html__('Home', 'kirki'),
		'panel'       => 'mainthemepanel_mediumish',
		'priority'    => 1,
	]
);


new \Kirki\Field\Custom(
	[
		'settings' => 'heading1',
		'section' => 'sectionhome',
		'default' => '<h1 class="kirki-separator">' . esc_html__('Home Slider', 'mediumish') . '</h1><hr>',
		'priority' => 1,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'mediumish_homeslider_active',
		'label'       => esc_attr__('Disable home slider', 'mediumish'),
		'section'     => 'sectionhome',
		'priority'    => 1,
		'default'     => false,
	]
);

new \Kirki\Field\Select(
	[
		'settings'    => 'mediumish_option_homeslider',
		'label'       => esc_attr__('Slider by tag', 'mediumish'),
		'section'     => 'sectionhome',
		'priority'    => 1,	
		//'choices'     => Kirki\Util\Helper::get_terms( array('taxonomy' => 'post_tag') ),
		'choices'   => mediumish_terms_choices_by_slug('post_tag'),
		'default'     => '',
		'active_callback' => [['setting' => 'mediumish_homeslider_active', 'operator' => '==', 'value' => 0]],
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'mediumish_option_homeslider_recentposts',
		'label'       => esc_attr__('Slider by recent posts', 'mediumish'),
		'description' => __('Check this box if you want the slider to display recent posts instead.', 'mediumish'),
		'section'     => 'sectionhome',
		'priority'    => 1,
		'default'	  => false,
		'active_callback' => [['setting' => 'mediumish_homeslider_active', 'operator' => '==', 'value' => 0]],
	]
);

new \Kirki\Field\Text(
	[
		'settings'    => 'mediumish_option_homeslider_numberposts',
		'label'       => esc_attr__('Enter number of slides', 'mediumish'),
		'description' => __('Important: slider posts are not duplicated anywhere on homepage so we recommend a minimum number of slides to ensure their visibility.', 'mediumish'),
		'section'     => 'sectionhome',
		'priority'    => 1,
		'default'     => '1',
		'active_callback' => [['setting' => 'mediumish_homeslider_active', 'operator' => '==', 'value' => 0]],
	]
);


new \Kirki\Field\Custom(
	[
		'settings' => 'heading2',
		'section' => 'sectionhome',
		'default' => '<h1 class="kirki-separator">' . esc_html__('Home Posts by Category', 'mediumish') . '</h1><hr>',
		'priority' => 1,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'mediumish_postsbycategory_active',
		'label'       => esc_attr__('Disable home posts by category', 'mediumish'),
		'section'     => 'sectionhome',
		'priority'    => 1,
		'default'     => false,
	]
);

new \Kirki\Field\Repeater(
	[
		'settings' => 'mediumish_option_postsbycategory',
		'label'    => esc_html__( 'Posts by Category', 'kirki' ),
		'description' => __('Important: make sure you have enough posts. Posts are not duplicated.', 'mediumish'),
		'section'  => 'sectionhome',
		'active_callback' => [['setting' => 'mediumish_postsbycategory_active', 'operator' => '==', 'value' => 0]],
		'priority' => 1,
		'row_label'    => [
			'type'  => 'field',
			'value' => esc_html__( 'Category Raw', 'kirki' ),
			'field' => 'link_text',
		],
		'default'  => [
			[
				'categoryfield'   => '',
				'categorystyle'    => 'style-1',
				'postsperpage'    => '5',
			],
			[
				'categoryfield'   => '',
				'categorystyle'    => 'style-2',
				'postsperpage'    => '4',
			],			
			[
				'categoryfield'   => '',
				'categorystyle'    => 'style-3',
				'postsperpage'    => '3',
			],
			[
				'categoryfield'   => '',
				'categorystyle'    => 'style-4',
				'postsperpage'    => '4',
			],			
			[
				'categoryfield'   => '',
				'categorystyle'    => 'style-5',
				'postsperpage'    => '3',
			],			
			[
				'categoryfield'   => '',
				'categorystyle'    => 'style-6',
				'postsperpage'    => '2',
			],	
		],
		'fields'   => [
			'categoryfield'   => [
				'type'        => 'select',
				'label'       => esc_html__( 'Select Category', 'kirki' ),
				//'choices'     => Kirki\Util\Helper::get_terms( array('taxonomy' => 'category') ),
				'choices' => mediumish_terms_choices_by_slug('category'), 
				'default'     => '',
			],
			'categorystyle'    => [
				'type'        => 'radio-image',
				'label'       => esc_html__( 'Select Card Style', 'kirki' ),
				'choices'     => [
					'style-1' => get_template_directory_uri() . '/assets/img/options/card1.jpg',
					'style-2' => get_template_directory_uri() . '/assets/img/options/card2.jpg',
					'style-3' => get_template_directory_uri() . '/assets/img/options/card3.jpg',
					'style-4' => get_template_directory_uri() . '/assets/img/options/card4.jpg',
					'style-5' => get_template_directory_uri() . '/assets/img/options/card5.jpg',
					'style-6' => get_template_directory_uri() . '/assets/img/options/card6.jpg',
				],
			],
			'postsperpage'   => [
				'type'        => 'number',
				'label'       => esc_html__( 'Number of Posts', 'kirki' ),
				'default'     => '',
			],
		],
	]
);

new \Kirki\Field\Custom(
	[
		'settings' => 'heading3',
		'section' => 'sectionhome',
		'default' => '<h1 class="kirki-separator">' . esc_html__('Home Category Cloud', 'mediumish') . '</h1><hr>',
		'priority' => 2,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'mediumish_homecategorycloud_active',
		'label'       => esc_attr__('Disable category cloud', 'mediumish'),
		'section'     => 'sectionhome',
		'priority'    => 2,
		'default'     => false,
	]
);

new \Kirki\Field\Image(
	[
		'settings'    => 'mediumish_homecategorycloud_bg',
		'label'       => esc_attr__('Intro Background Image', 'mediumish'),
		'description' => __('Upload an image for background category cloud', 'mediumish'),
		'section'     => 'sectionhome',
		'priority'    => 2,
		'active_callback' => [['setting' => 'mediumish_homecategorycloud_active', 'operator' => '==', 'value' => 0]],
	]
);


//-----------------------------------------------------
// SECTION: Articles
//-----------------------------------------------------

new \Kirki\Section(
	'sectionarticles',
	[
		'title'       => esc_html__('Articles', 'kirki'),
		'panel'       => 'mainthemepanel_mediumish',
		'priority'    => 1,
	]
);


new \Kirki\Field\Number(
    [
        'settings' => 'article_container_max_width',
        'label'    => esc_attr__( 'Article Max-Width - px', 'mediumish' ),
        'section'  => 'sectionarticles',
        'priority' => 10,
        'default'  => 850,
        'choices'     => [
            'min'  => 0,
            'max'  => 850,
            'step' => 1,
        ],
        'output' => [
            [
                'element'  => '.article-post',
                'property' => 'max-width',
                'units'    => 'px',
            ],
        ],
    ]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_authorbox_sectionarticles',
		'label'       => esc_html__('Disable Author Box in Articles', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => false,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_authorbox_sectionarticles_card',
		'label'       => esc_html__('Disable Author in Post Cards', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => false,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_readingtime_sectionarticles',
		'label'       => esc_html__('Disable Reading Time in Articles', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => false,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_readingtime_sectionarticles_card',
		'label'       => esc_html__('Disable Reading Time in Post Cards', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => false,
	]
);


new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_date_sectionarticles',
		'label'       => esc_html__('Disable Meta Date in Articles', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => false,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_date_sectionarticles_card',
		'label'       => esc_html__('Disable Meta Date in Post Cards', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => false,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_dot_sectionarticles_card',
		'label'       => esc_html__('Remove the dot in Post Cards', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => false,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_share_twitter',
		'label'       => esc_html__('Disable Twitter Share in Articles', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => false,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_share_fb',
		'label'       => esc_html__('Disable Facebook Share in Articles', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => false,
	]
);


new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_share_linkedin',
		'label'       => esc_html__('Disable Linkedin Share in Articles', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => false,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_share_sectionarticles',
		'label'       => esc_html__('Disable All Social Share in Articles', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => false,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_cats_sectionarticles',
		'label'       => esc_html__('Disable Categories in Articles', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => false,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_tags_sectionarticles',
		'label'       => esc_html__('Disable Tags in Articles', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => false,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_rp_sectionarticles',
		'label'       => esc_html__('Disable Related Posts in Articles', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => false,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_prevnext_sectionarticles',
		'label'       => esc_html__('Disable Prev/Next in Articles', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => true,
	]
);

new \Kirki\Field\Checkbox(
	[
		'settings'    => 'disable_bottomalert_sectionarticles',
		'label'       => esc_html__('Disable Bottom Alert Bar in Articles', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'default'     => false,
	]
);

new \Kirki\Field\Textarea(
	[
		'settings'    => 'mediumish_bottomalert',
		'label'       => esc_attr__('Bottom Alert Bar', 'mediumish'),
		'description' => __('If you want to display a MailChimp form, make sure you have Mailchimp for WP installed and paste the generated shortcode. The code should be similar to: [mc4wp_form id="xxxx"]', 'mediumish'),
		'section'     => 'sectionarticles',
		'priority'    => 10,
		'active_callback' => [['setting' => 'disable_bottomalert_sectionarticles', 'operator' => '==', 'value' => 0]],
	]
);


//-----------------------------------------------------
// SECTION: Footer
//-----------------------------------------------------

new \Kirki\Section(
	'sectionfooter',
	[
		'title'       => esc_html__('Footer', 'kirki'),
		'panel'       => 'mainthemepanel_mediumish',
		'priority'    => 1,
	]
);


new \Kirki\Field\Textarea(
	[
		'settings'    => 'footer_textleft',
		'label'       => esc_attr__('Footer Text Left', 'mediumish'),
		'section'     => 'sectionfooter',
		'priority'    => 10,
		'default'     => '&copy; Copyright Your Website Name',
	]
);


new \Kirki\Field\Textarea(
	[
		'settings'    => 'footer_textright',
		'label'       => esc_attr__('Footer Text Right', 'mediumish'),
		'section'     => 'sectionfooter',
		'priority'    => 10,
		'default'     => 'Theme by <a target="_blank" href="https://www.wowthemes.net">WowThemesNet</a>',
	]
);

//-----------------------------------------------------
// SECTION: Ad Blocks
//-----------------------------------------------------

new \Kirki\Section(
	'sectionad',
	[
		'title'       => esc_html__('Ads/Banners', 'kirki'),
		'panel'       => 'mainthemepanel_mediumish',
		'priority'    => 1,
	]
);

new \Kirki\Field\Textarea(
	[
		'settings'    => 'toparticle_sectionad',
		'label'       => esc_attr__('Top article', 'mediumish'),
		'description' => 'Enter your ad/banner code here to appear at the beginning of an article.',
		'section'     => 'sectionad',
		'priority'    => 10,
		'default'     => '',
	]
);


new \Kirki\Field\Textarea(
	[
		'settings'    => 'bottomarticle_sectionad',
		'label'       => esc_attr__('Bottom Article', 'mediumish'),
		'description'     => 'Enter your ad/banner code here to appear at the end of an article.',
		'section'     => 'sectionad',
		'priority'    => 10,
		'default'     => '',
	]
);


//-----------------------------------------------------
// SECTION: Other
//-----------------------------------------------------

new \Kirki\Section(
	'section_misc',
	[
		'title'       => esc_html__('Misc', 'kirki'),
		'panel'       => 'mainthemepanel_mediumish',
		'priority'    => 1,
	]
);

new \Kirki\Field\Image(
	[
		'settings'    => 'bg_authorpage',
		'label'       => esc_html__('Author Page Header', 'mediumish'),
		'section'     => 'section_misc',
		'priority'    => 10,
		'transport' => 'auto',
		'default'     => '',
	]
);

new \Kirki\Field\Number(
	[
		'settings'    => 'mediumish_limitcharacterstitle',
		'label'       => esc_html__('Limit Title Words in Cards and Slider', 'mediumish'),
		'section'     => 'section_misc',
		'priority'    => 10,
		'default'     => '9',
	]
);

new \Kirki\Field\Text(
	[
		'settings'    => 'all_stories_text',
		'label'       => 'Change "All Stories" text', 'mediumish',
		'section'     => 'section_misc',
		'priority'    => 10,
		'default'     => 'All Stories',
	]
);
