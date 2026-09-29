<?php
/**
 * Köytarla tema fonksiyonları.
 *
 * @package Koytarla
 */

defined( 'ABSPATH' ) || exit;

define( 'KOYTARLA_VERSION', wp_get_theme( get_template() )->get( 'Version' ) );
define( 'KOYTARLA_DIR', get_template_directory() );
define( 'KOYTARLA_URI', get_template_directory_uri() );

/**
 * Tema desteği.
 */
function koytarla_setup() {
	load_theme_textdomain( 'koytarla', KOYTARLA_DIR . '/languages' );

	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );

	add_editor_style( 'assets/css/theme.css' );
}
add_action( 'after_setup_theme', 'koytarla_setup' );

/**
 * Ön yüz stilleri.
 */
function koytarla_enqueue_assets() {
	$css = '/assets/css/theme.css';
	wp_enqueue_style(
		'koytarla-theme',
		KOYTARLA_URI . $css,
		array(),
		(string) filemtime( KOYTARLA_DIR . $css )
	);
}
add_action( 'wp_enqueue_scripts', 'koytarla_enqueue_assets' );

/**
 * En çok kullanılan iki font dosyasını (latin alt kümesi) önceden yükle.
 * Türkçe karakterler (ğ, ş, İ) latin-ext dosyasındadır; o dosya unicode-range ile ihtiyaç anında iner.
 */
function koytarla_preload_fonts() {
	$fonts = array(
		'/assets/fonts/manrope/manrope-latin-wght-normal.woff2',
		'/assets/fonts/fraunces/fraunces-latin-wght-normal.woff2',
	);
	foreach ( $fonts as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( KOYTARLA_URI . $font )
		);
	}
}
add_action( 'wp_head', 'koytarla_preload_fonts', 1 );

/**
 * Desen kategorisi.
 */
function koytarla_register_pattern_categories() {
	register_block_pattern_category(
		'koytarla',
		array( 'label' => __( 'Köytarla', 'koytarla' ) )
	);
	register_block_pattern_category(
		'koytarla-anasayfa',
		array( 'label' => __( 'Köytarla — Ana sayfa', 'koytarla' ) )
	);
}
add_action( 'init', 'koytarla_register_pattern_categories' );

/**
 * Sepet, ödeme ve hesap sayfaları hiçbir katmanda (LiteSpeed, Cloudflare) önbelleğe alınmasın.
 * WooCommerce zaten nocache başlıkları gönderir; burada CDN'e özel başlıklarla pekiştiriyoruz.
 */
function koytarla_no_cache_for_wc_pages() {
	if ( ! function_exists( 'is_cart' ) ) {
		return;
	}
	if ( is_cart() || is_checkout() || is_account_page() ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
			header( 'CDN-Cache-Control: no-store' );
			header( 'Cloudflare-CDN-Cache-Control: no-store' );
			header( 'X-LiteSpeed-Cache-Control: no-cache' );
		}
	}
}
add_action( 'template_redirect', 'koytarla_no_cache_for_wc_pages', 1 );

require_once KOYTARLA_DIR . '/inc/woocommerce.php';
