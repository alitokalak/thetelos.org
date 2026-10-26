<?php
/**
 * The Telos — Bot yükünü azalt (robots.txt)
 *
 * 12 bin sayfalık siteyi aynı anda tarayan botlar sunucuyu CPU limitine
 * dayatıyordu. WordPress'in sanal robots.txt'sine eklenir (kökte fiziksel
 * robots.txt dosyası varsa WordPress bunu kullanmaz):
 *   - Bingbot / Yandex / Baidu: Crawl-delay (bu botlar uyar; Google uymaz ama
 *     Google zaten yükü kendisi ayarlar).
 *   - Trafik getirmeyen SEO/veri toplama botları: tamamen kapalı.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'robots_txt', function ( $out ) {
    $out .= "\n# thetelos: sunucu yükü için tarama hızı sınırı\n";
    foreach ( [ 'bingbot', 'msnbot', 'YandexBot', 'Baiduspider' ] as $ua ) {
        $out .= "User-agent: {$ua}\nCrawl-delay: 10\n\n";
    }
    foreach ( [ 'AhrefsBot', 'SemrushBot', 'MJ12bot', 'DotBot', 'PetalBot', 'Bytespider', 'DataForSeoBot', 'BLEXBot', 'serpstatbot' ] as $ua ) {
        $out .= "User-agent: {$ua}\nDisallow: /\n\n";
    }
    return $out;
}, 99 );
