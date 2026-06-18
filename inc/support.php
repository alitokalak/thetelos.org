<?php
/**
 * The Telos — Support / Donation Settings
 *
 * WP Customizer'a "Support / Donations" bölümü ekler.
 * Customize → Support / Donations → Shopier URL + kripto adresleri
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'customize_register', function( WP_Customize_Manager $wp_customize ) {

    $wp_customize->add_section( 'tls_support', [
        'title'    => 'Support / Donations',
        'priority' => 160,
    ]);

    /* Patreon — primary support method */
    $wp_customize->add_setting( 'tls_patreon_url', [
        'default'           => 'https://www.patreon.com/c/Thetelos',
        'sanitize_callback' => 'esc_url_raw',
    ]);
    $wp_customize->add_control( 'tls_patreon_url', [
        'label'       => 'Patreon page URL',
        'description' => 'Fallback for the “Other” amount — links to the main membership page.',
        'section'     => 'tls_support',
        'type'        => 'url',
    ]);

    /* Patreon per-tier checkout URLs — each amount maps to its tier's checkout */
    $patreon_tiers = [
        'tls_patreon_url_5'   => '$5 tier — Patreon checkout URL (Supporter)',
        'tls_patreon_url_10'  => '$10 tier — Patreon checkout URL (Patron)',
        'tls_patreon_url_25'  => '$25 tier — Patreon checkout URL (Benefactor)',
        'tls_patreon_url_50'  => '$50 tier — Patreon checkout URL (Sponsor)',
        'tls_patreon_url_100' => '$100 tier — Patreon checkout URL (Guardian)',
    ];
    $patreon_defaults = [
        'tls_patreon_url_5'   => 'https://www.patreon.com/checkout/Thetelos?rid=28883858',
        'tls_patreon_url_10'  => 'https://www.patreon.com/checkout/Thetelos?rid=28884980',
        'tls_patreon_url_25'  => 'https://www.patreon.com/checkout/Thetelos?rid=28884986',
        'tls_patreon_url_50'  => 'https://www.patreon.com/checkout/Thetelos?rid=28884989',
        'tls_patreon_url_100' => 'https://www.patreon.com/checkout/Thetelos?rid=28884993',
    ];
    foreach ( $patreon_tiers as $key => $label ) {
        $wp_customize->add_setting( $key, [
            'default'           => $patreon_defaults[ $key ],
            'sanitize_callback' => 'esc_url_raw',
        ]);
        $wp_customize->add_control( $key, [
            'label'       => $label,
            'description' => 'patreon.com → Membership tier → Join → copy checkout URL',
            'section'     => 'tls_support',
            'type'        => 'url',
        ]);
    }

    /* Shopier URL fields */
    $tiers = [
        'tls_support_url_5'   => '$5 tier — Shopier URL',
        'tls_support_url_10'  => '$10 tier — Shopier URL',
        'tls_support_url_25'  => '$25 tier — Shopier URL',
        'tls_support_url_50'  => '$50 tier — Shopier URL',
        'tls_support_url_100' => '$100 tier — Shopier URL',
    ];

    foreach ( $tiers as $key => $label ) {
        $wp_customize->add_setting( $key, [
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ]);
        $wp_customize->add_control( $key, [
            'label'       => $label,
            'description' => 'shopier.com → Ürünüm → Satış Linki',
            'section'     => 'tls_support',
            'type'        => 'url',
        ]);
    }

    /* Crypto wallet addresses */
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
