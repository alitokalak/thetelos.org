<?php
/**
 * The Telos — Sunucu dış bağlantı teşhisi (yalnız yönetici).
 * Açmak: /wp-admin/?tls_netdiag=1
 * "WordPress could not establish a secure connection to WordPress.org" hatasının
 * sebebini bulmak için: DNS, IPv4/IPv6, SSL sertifika paketi, engelleme sabitleri.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_init', function () {
    if ( empty( $_GET['tls_netdiag'] ) || ! current_user_can( 'manage_options' ) ) return;
    @set_time_limit( 120 );
    header( 'Content-Type: text/plain; charset=utf-8' );
    $o = function ( $k, $v ) { echo str_pad( $k, 34 ) . ' ' . ( is_scalar( $v ) || $v === null ? var_export( $v, true ) : json_encode( $v ) ) . "\n"; };

    echo "== ORTAM\n";
    $o( 'PHP', PHP_VERSION );
    $o( 'curl', function_exists( 'curl_version' ) ? curl_version()['version'] . ' / ' . curl_version()['ssl_version'] : 'YOK' );
    $o( 'openssl.cafile', ini_get( 'openssl.cafile' ) );
    $o( 'curl.cainfo', ini_get( 'curl.cainfo' ) );
    $o( 'display_errors', ini_get( 'display_errors' ) );
    $o( 'disable_functions', ini_get( 'disable_functions' ) );
    $o( 'allow_url_fopen', ini_get( 'allow_url_fopen' ) );
    foreach ( [ 'WP_HTTP_BLOCK_EXTERNAL', 'WP_ACCESSIBLE_HOSTS', 'WP_PROXY_HOST', 'WP_PROXY_PORT', 'WP_DEBUG', 'WP_DEBUG_DISPLAY', 'AUTOMATIC_UPDATER_DISABLED' ] as $c ) {
        $o( $c, defined( $c ) ? constant( $c ) : '(tanımsız)' );
    }
    $o( 'pre_http_request filtreleri', has_filter( 'pre_http_request' ) ? 'VAR' : 'yok' );

    echo "\n== DNS\n";
    foreach ( [ 'api.wordpress.org', 'downloads.wordpress.org', 'www.google.com', 'api.deepseek.com' ] as $h ) {
        $a = @dns_get_record( $h, DNS_A ); $aaaa = @dns_get_record( $h, DNS_AAAA );
        $o( $h, 'A=' . implode( ',', array_column( (array) $a, 'ip' ) ) . ' AAAA=' . implode( ',', array_column( (array) $aaaa, 'ipv6' ) ) . ' gethostbyname=' . gethostbyname( $h ) );
    }

    echo "\n== CURL (doğrudan)\n";
    $urls = [ 'https://api.wordpress.org/core/version-check/1.7/', 'https://www.google.com/', 'https://api.deepseek.com/' ];
    foreach ( $urls as $u ) {
        foreach ( [ 'auto' => CURL_IPRESOLVE_WHATEVER, 'ipv4' => CURL_IPRESOLVE_V4, 'ipv6' => CURL_IPRESOLVE_V6 ] as $lbl => $ipr ) {
            $ch = curl_init( $u );
            curl_setopt_array( $ch, [ CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_IPRESOLVE => $ipr, CURLOPT_NOBODY => true ] );
            curl_exec( $ch );
            $o( parse_url( $u, PHP_URL_HOST ) . " [$lbl]", 'http=' . curl_getinfo( $ch, CURLINFO_HTTP_CODE ) . ' ip=' . curl_getinfo( $ch, CURLINFO_PRIMARY_IP ) . ' t=' . round( curl_getinfo( $ch, CURLINFO_TOTAL_TIME ), 2 ) . ' err=' . curl_errno( $ch ) . ' ' . curl_error( $ch ) );
            curl_close( $ch );
        }
    }
    // SSL doğrulamasız deneme: sertifika paketi mi, ağ mı?
    $ch = curl_init( $urls[0] );
    curl_setopt_array( $ch, [ CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_NOBODY => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0 ] );
    curl_exec( $ch );
    $o( 'wordpress.org [ssl-doğrulamasız]', 'http=' . curl_getinfo( $ch, CURLINFO_HTTP_CODE ) . ' err=' . curl_errno( $ch ) . ' ' . curl_error( $ch ) );
    curl_close( $ch );

    echo "\n== WORDPRESS HTTP API\n";
    foreach ( [ 'https://api.wordpress.org/core/version-check/1.7/', 'http://api.wordpress.org/core/version-check/1.7/' ] as $u ) {
        $r = wp_remote_get( $u, [ 'timeout' => 15 ] );
        $o( $u, is_wp_error( $r ) ? 'HATA: ' . $r->get_error_message() : 'http=' . wp_remote_retrieve_response_code( $r ) );
    }
    exit;
} );
