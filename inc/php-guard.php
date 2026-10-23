<?php
/**
 * The Telos — PHP sürüm koruması (.htaccess)
 *
 * cPanel, sitenin PHP sürümünü .htaccess içindeki "cPanel-generated handler"
 * bloğuyla seçer. LiteSpeed Cache gibi eklentiler .htaccess'i yeniden
 * yazarken bu blok silinirse site sunucu varsayılanına (PHP 7.4) düşer ve
 * PHP 8.3 isteyen eklentiler (Contact Form 7) siteyi tamamen çökertir.
 *
 * Koruma: blok varken birebir kopyası saklanır; .htaccess her değiştiğinde
 * (istek sonunda, yalnız dosya zamanı değişmişse) blok kontrol edilir ve
 * silinmişse aynı istek bitmeden geri yazılır — sonraki istek yine PHP 8.3
 * ile çalışır. Hiçbir koşulda hata fırlatmaz.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'shutdown', 'tls_php_guard_check', 999 );
function tls_php_guard_check() {
    if ( PHP_VERSION_ID < 80000 ) return;               // yanlış sürümde çalışıyorsak saklanan bloğu ezme
    $file = ABSPATH . '.htaccess';
    if ( ! @is_file( $file ) ) return;

    clearstatcache( true, $file );
    $mtime = (int) @filemtime( $file );
    if ( $mtime && $mtime === (int) get_option( 'tls_php_guard_mtime', 0 ) ) return;   // değişmemiş → iş yok

    $ht = (string) @file_get_contents( $file );
    if ( $ht === '' ) return;
    $re = '/# php -- BEGIN cPanel-generated handler.*?# php -- END cPanel-generated handler[^\n]*\n?/s';

    if ( preg_match( $re, $ht, $m ) ) {
        // Blok yerinde: güncel kopyasını sakla (PHP 8 bloğuysa).
        if ( stripos( $m[0], 'php8' ) !== false && $m[0] !== get_option( 'tls_php_guard_block', '' ) ) {
            update_option( 'tls_php_guard_block', $m[0], true );
        }
    } else {
        // Blok silinmiş: sakladığımız kopyayı dosyanın sonuna geri ekle.
        $block = (string) get_option( 'tls_php_guard_block', '' );
        if ( $block !== '' && @is_writable( $file ) ) {
            $new = rtrim( $ht, "\n" ) . "\n\n" . rtrim( $block, "\n" ) . "\n";
            if ( @file_put_contents( $file, $new, LOCK_EX ) !== false ) {
                error_log( '[thetelos] .htaccess PHP handler bloğu silinmişti, geri yazıldı.' );
                clearstatcache( true, $file );
                $mtime = (int) @filemtime( $file );
            }
        }
    }
    update_option( 'tls_php_guard_mtime', $mtime, true );
}
