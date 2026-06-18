<?php
/**
 * The Telos — Support / Donation Settings
 *
 * WP Customizer'a "Support / Donations" bölümü ekler.
 * Customize → Support / Donations → Polar checkout link + kripto adresleri
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'customize_register', function( WP_Customize_Manager $wp_customize ) {

    $wp_customize->add_section( 'tls_support', [
        'title'    => 'Support / Donations',
        'priority' => 160,
    ]);

    /* Polar — primary support method (global card payments, no account, pays out to Turkey) */
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

    /* Crypto wallet addresses — optional secondary method */
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
