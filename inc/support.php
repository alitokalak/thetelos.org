<?php
/**
 * The Telos — Support / Donation Settings
 *
 * WP Customizer'a "Support / Donations" bölümü ekler.
 * Customize → Support / Donations → Polar link + LemonSqueezy link + kripto adresleri
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'customize_register', function( WP_Customize_Manager $wp_customize ) {

    $wp_customize->add_section( 'tls_support', [
        'title'    => 'Support / Donations',
        'priority' => 160,
    ]);

    /* Polar — primary method (global card payments, no account, pays out to Turkey) */
    $wp_customize->add_setting( 'tls_polar_url', [
        'default'           => 'https://buy.polar.sh/polar_cl_lo5vNnnTnFOlDvluWfQn62s5zSQq1AdVjTZzr1LqyE6',
        'sanitize_callback' => 'esc_url_raw',
    ]);
    $wp_customize->add_control( 'tls_polar_url', [
        'label'       => 'Polar checkout link',
        'description' => 'polar.sh → Products → Checkout Links → Copy link. The amount is appended automatically (?amount= in cents).',
        'section'     => 'tls_support',
        'type'        => 'url',
    ]);

    /* LemonSqueezy — alternative global card method.
       Her tutar için AYRI sabit fiyatlı ürün. Kullanıcı $25 seçince $25 ürünü açılır.
       (LemonSqueezy "Pay What You Want" üründe rakam URL'den ön-doldurulamadığı için
        her tutara ayrı ürün açıyoruz.) */
    $lemon_tiers = [
        'tls_lemon_url_5'   => '$5 ürün checkout linki',
        'tls_lemon_url_10'  => '$10 ürün checkout linki',
        'tls_lemon_url_25'  => '$25 ürün checkout linki',
        'tls_lemon_url_50'  => '$50 ürün checkout linki',
        'tls_lemon_url_100' => '$100 ürün checkout linki',
    ];
    foreach ( $lemon_tiers as $key => $label ) {
        $wp_customize->add_setting( $key, [
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ]);
        $wp_customize->add_control( $key, [
            'label'       => 'LemonSqueezy ' . $label,
            'section'     => 'tls_support',
            'type'        => 'url',
        ]);
    }

    /* LemonSqueezy — "Other / custom" tutar için Pay What You Want ürünü (fallback) */
    $wp_customize->add_setting( 'tls_lemonsqueezy_url', [
        'default'           => 'https://thetelos.lemonsqueezy.com/checkout/buy/5bd79218-a53a-4f5c-8b63-81c272bb80d3?media=0&logo=0&desc=0&discount=0',
        'sanitize_callback' => 'esc_url_raw',
    ]);
    $wp_customize->add_control( 'tls_lemonsqueezy_url', [
        'label'       => 'LemonSqueezy "Other" (Pay What You Want) URL',
        'description' => 'Sabit tutarlardan biri seçili değilse / "Other" seçildiğinde açılır. "Pay What You Want" türünde ürün.',
        'section'     => 'tls_support',
        'type'        => 'url',
    ]);

    /* Crypto wallet addresses — optional method */
    $crypto = [
        'tls_crypto_btc'  => 'Bitcoin (BTC) wallet address',
        'tls_crypto_eth'  => 'Ethereum (ETH) wallet address',
        'tls_crypto_usdc' => 'USD Coin (USDC) wallet address',
    ];

    foreach ( $crypto as $key => $label ) {
        $wp_customize->add_setting( $key, [
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        $wp_customize->add_control( $key, [
            'label'   => $label,
            'section' => 'tls_support',
            'type'    => 'text',
        ]);
    }
});
