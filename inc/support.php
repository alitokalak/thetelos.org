<?php
/**
 * The Telos — Support / Donation Settings
 *
 * WP Customizer'a "Support / Donations" bölümü ekler.
 * Customize → Support / Donations → Shopier URL alanları
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'customize_register', function( WP_Customize_Manager $wp_customize ) {

    $wp_customize->add_section( 'tls_support', [
        'title'    => 'Support / Donations',
        'priority' => 160,
    ]);

    $fields = [
        'tls_support_url_3'  => [ 'label' => 'Shopier URL — $3 tier',  'default' => '' ],
        'tls_support_url_10' => [ 'label' => 'Shopier URL — $10 tier', 'default' => '' ],
        'tls_support_url_25' => [ 'label' => 'Shopier URL — $25 tier', 'default' => '' ],
    ];

    foreach ( $fields as $key => $args ) {
        $wp_customize->add_setting( $key, [
            'default'           => $args['default'],
            'sanitize_callback' => 'esc_url_raw',
        ]);
        $wp_customize->add_control( $key, [
            'label'       => $args['label'],
            'description' => 'shopier.com → Ürünüm → Linki kopyala',
            'section'     => 'tls_support',
            'type'        => 'url',
        ]);
    }
});
