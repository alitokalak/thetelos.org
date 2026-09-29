<?php
/**
 * WooCommerce uyarlamaları.
 *
 * @package Koytarla
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

/**
 * Ürünün indirim yüzdesi (sadece gerçekten indirimdeyse). İndirim yoksa 0.
 *
 * @param WC_Product|null $product Ürün.
 * @return int
 */
function koytarla_sale_percentage( $product ) {
	if ( ! $product instanceof WC_Product || ! $product->is_on_sale() ) {
		return 0;
	}
	$regular = (float) $product->get_regular_price();
	$sale    = (float) $product->get_sale_price();
	if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) {
		return 0;
	}
	return (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
}

/**
 * Rozet metni: "%30 İNDİRİM". Yüzde hesaplanamazsa "İNDİRİM".
 *
 * @param WC_Product|null $product Ürün.
 * @return string
 */
function koytarla_sale_badge_label( $product ) {
	$percent = koytarla_sale_percentage( $product );
	/* translators: %d: indirim yüzdesi */
	return $percent > 0 ? sprintf( __( '%%%d İNDİRİM', 'koytarla' ), $percent ) : __( 'İNDİRİM', 'koytarla' );
}

// Klasik şablonlardaki rozet.
add_filter(
	'woocommerce_sale_flash',
	static function ( $html, $post, $product ) {
		return '<span class="onsale">' . esc_html( koytarla_sale_badge_label( $product ) ) . '</span>';
	},
	10,
	3
);

// Blok (Product Sale Badge) rozeti.
add_filter(
	'woocommerce_sale_badge_text',
	static function ( $text, $product = null ) {
		return $product ? koytarla_sale_badge_label( $product ) : $text;
	},
	10,
	2
);

/**
 * Ücretsiz kargo eşiği (TL). Kargo ayarlarındaki "en düşük sipariş tutarı" ile aynı olmalı.
 * Mini sepet ilerleme çubuğu (ileriki adım) bu değeri kullanacak.
 *
 * @return float
 */
function koytarla_free_shipping_threshold() {
	return (float) apply_filters( 'koytarla_free_shipping_threshold', 1000 );
}
