<?php
/**
 * The Telos — Anlık sesli dinleme (her kitap sayfasında ▶).
 *
 * Akış: okuyucu ▶'a basar → metin paragraflara bölünür → her parça İLK dinlemede
 * DeepInfra'daki Kokoro (doğal ses) ile seslendirilir → Cloudflare R2'ye kaydedilir.
 * Aynı parça bir daha istenirse R2'den gelir: ücret YALNIZ İLK dinlemede, bir kez.
 * Colab'da önceden üretilmiş tam ses (_tls_audio_url) varsa o kullanılır.
 *
 * Ayarlar (panel → Sesli Özet): option 'tls_tts_live' =
 *   [deepinfra_key, r2_key, r2_secret, voice, daily_chars]
 * Koruma: günlük karakter tavanı + IP başına hız sınırı + aynı parçaya kilit.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

const TLS_R2_ACCOUNT = '3da5d617f59e35f9aeddbbef12785676';
const TLS_R2_BUCKET  = 'thetelos-audio';
const TLS_R2_PUBLIC  = 'https://audio.thetelos.org';

function tls_live_cfg() {
    $c = get_option( 'tls_tts_live', [] );
    return wp_parse_args( is_array( $c ) ? $c : [], [
        'deepinfra_key' => '', 'r2_key' => '', 'r2_secret' => '', 'voice' => 'am_michael', 'daily_chars' => 1500000,
    ] );
}
function tls_live_ready() {
    $c = tls_live_cfg();
    return $c['deepinfra_key'] !== '' && $c['r2_key'] !== '' && $c['r2_secret'] !== '';
}

/* Seslendirilecek düz metin: başlık + yazar + gövde. */
function tls_tts_text( $pid ) {
    $p = get_post( $pid );
    if ( ! $p ) return '';
    $title = html_entity_decode( get_the_title( $pid ), ENT_QUOTES, 'UTF-8' );
    $title = trim( preg_replace( '/\s+[–—-]\s+[^–—]+$/u', '', $title ) );
    $terms = get_the_terms( $pid, 'authors' );
    $author = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
    $html = strip_shortcodes( (string) $p->post_content );
    $html = preg_replace( '~<(script|style|figure|figcaption|table)[^>]*>.*?</\1>~is', ' ', $html );
    $html = preg_replace( '~<h[1-6][^>]*>(.*?)</h[1-6]>~is', "\n\n$1.\n\n", $html );
    $html = preg_replace( '~</(p|li|blockquote|div)>~i', "\n", $html );
    $txt = html_entity_decode( strip_tags( $html ), ENT_QUOTES, 'UTF-8' );
    $txt = preg_replace( '~https?://\S+~', '', $txt );
    $txt = preg_replace( '/[ \t]+/u', ' ', $txt );
    $txt = preg_replace( '/\n\s*\n+/u', "\n\n", trim( $txt ) );
    $txt = preg_replace( '/\.\.\n/u', ".\n", $txt );
    return $title . ( $author !== '' ? ', by ' . $author : '' ) . ".\n\n" . $txt;
}

/* Parçalar: ilk parça kısa (hemen başlasın), sonrakiler ~1200 karakter, cümle sınırında. */
function tls_live_chunks( $pid ) {
    $txt = tls_tts_text( $pid );
    $paras = preg_split( '/\n+/u', $txt );
    $units = [];
    foreach ( $paras as $p ) {
        $p = trim( $p );
        if ( $p === '' ) continue;
        if ( mb_strlen( $p ) <= 1200 ) { $units[] = $p; continue; }
        foreach ( preg_split( '/(?<=[.!?])\s+/u', $p ) as $s ) if ( trim( $s ) !== '' ) $units[] = trim( $s );
    }
    $out = []; $cur = '';
    foreach ( $units as $u ) {
        $lim = count( $out ) === 0 ? 350 : 1200;
        if ( $cur !== '' && mb_strlen( $cur ) + mb_strlen( $u ) + 1 > $lim ) { $out[] = $cur; $cur = ''; }
        $cur = $cur === '' ? $u : $cur . "\n" . $u;
    }
    if ( $cur !== '' ) $out[] = $cur;
    return $out;
}
function tls_live_hash( $chunks, $voice ) { return substr( md5( $voice . '|' . implode( '|', $chunks ) ), 0, 10 ); }

/* Bu yazı anlık dinlemeye uygun mu? */
function tls_live_eligible( $pid ) {
    if ( get_post_type( $pid ) !== 'post' || get_post_status( $pid ) !== 'publish' ) return false;
    if ( get_post_meta( $pid, '_tls_audio_skip', true ) ) return false;
    if ( function_exists( 'tls_placeholder_ids' ) && in_array( (int) $pid, array_map( 'intval', (array) tls_placeholder_ids() ), true ) ) return false;
    return str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $pid ) ) ) >= 200;
}

/* MP3 süresi (saniye): ilk karenin bit hızından (sabit bit hızı varsayımı). */
function tls_mp3_seconds( $bin ) {
    $n = strlen( $bin ); $o = 0;
    if ( substr( $bin, 0, 3 ) === 'ID3' && $n > 10 ) {
        $o = 10 + ( ( ord( $bin[6] ) & 127 ) << 21 | ( ord( $bin[7] ) & 127 ) << 14 | ( ord( $bin[8] ) & 127 ) << 7 | ( ord( $bin[9] ) & 127 ) );
    }
    for ( $i = $o; $i < min( $n - 4, $o + 8192 ); $i++ ) {
        if ( ord( $bin[ $i ] ) !== 0xFF || ( ord( $bin[ $i + 1 ] ) & 0xE0 ) !== 0xE0 ) continue;
        $ver = ( ord( $bin[ $i + 1 ] ) >> 3 ) & 3; $bi = ord( $bin[ $i + 2 ] ) >> 4;
        $t1 = [ 0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320 ];
        $t2 = [ 0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160 ];
        $kbps = ( $ver === 3 ? $t1 : $t2 )[ $bi ] ?? 0;
        if ( $kbps > 0 ) return round( ( $n - $i ) * 8 / ( $kbps * 1000 ), 2 );
    }
    return 0;
}

/* DeepInfra Kokoro → MP3 baytları ('' = hata). Önce OpenAI uyumlu uç, olmazsa yerel uç. */
function tls_live_synth( $text, $voice, $key ) {
    $ch = curl_init( 'https://api.deepinfra.com/v1/openai/audio/speech' );
    curl_setopt_array( $ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [ 'Content-Type: application/json', 'Authorization: Bearer ' . $key ],
        CURLOPT_POSTFIELDS => wp_json_encode( [ 'model' => 'hexgrad/Kokoro-82M', 'input' => $text, 'voice' => $voice, 'response_format' => 'mp3', 'speed' => 1 ] ),
    ] );
    $r = curl_exec( $ch ); $code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE ); $ct = (string) curl_getinfo( $ch, CURLINFO_CONTENT_TYPE ); curl_close( $ch );
    if ( $code === 200 && $r && stripos( $ct, 'json' ) === false && strlen( $r ) > 1000 ) return $r;

    $ch = curl_init( 'https://api.deepinfra.com/v1/inference/hexgrad/Kokoro-82M' );
    curl_setopt_array( $ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [ 'Content-Type: application/json', 'Authorization: Bearer ' . $key ],
        CURLOPT_POSTFIELDS => wp_json_encode( [ 'text' => $text, 'preset_voice' => [ $voice ], 'output_format' => 'mp3' ] ),
    ] );
    $r = curl_exec( $ch ); $code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE ); curl_close( $ch );
    $j = json_decode( (string) $r, true );
    $a = is_array( $j ) ? (string) ( $j['audio'] ?? '' ) : '';
    if ( $code === 200 && $a !== '' ) {
        $a = preg_replace( '~^data:[^,]*,~', '', $a );
        $bin = base64_decode( $a, true );
        if ( $bin !== false && strlen( $bin ) > 1000 ) return $bin;
    }
    update_option( 'tls_tts_live_err', gmdate( 'c' ) . " http=$code " . substr( (string) $r, 0, 300 ), false );
    return '';
}

/* R2'ye yükle (S3 imza v4). */
function tls_r2_put( $key, $body, $ctype, $ak, $sk ) {
    $host = TLS_R2_ACCOUNT . '.r2.cloudflarestorage.com';
    $path = '/' . TLS_R2_BUCKET . '/' . str_replace( '%2F', '/', rawurlencode( $key ) );
    $amz = gmdate( 'Ymd\THis\Z' ); $day = substr( $amz, 0, 8 ); $ph = hash( 'sha256', $body );
    $canon = "PUT\n$path\n\ncontent-type:$ctype\nhost:$host\nx-amz-content-sha256:$ph\nx-amz-date:$amz\n\ncontent-type;host;x-amz-content-sha256;x-amz-date\n$ph";
    $scope = "$day/auto/s3/aws4_request";
    $sts = "AWS4-HMAC-SHA256\n$amz\n$scope\n" . hash( 'sha256', $canon );
    $k = hash_hmac( 'sha256', 'aws4_request', hash_hmac( 'sha256', 's3', hash_hmac( 'sha256', 'auto', hash_hmac( 'sha256', $day, 'AWS4' . $sk, true ), true ), true ), true );
    $sig = hash_hmac( 'sha256', $sts, $k );
    $ch = curl_init( 'https://' . $host . $path );
    curl_setopt_array( $ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            "Content-Type: $ctype", "x-amz-content-sha256: $ph", "x-amz-date: $amz",
            "Authorization: AWS4-HMAC-SHA256 Credential=$ak/$scope, SignedHeaders=content-type;host;x-amz-content-sha256;x-amz-date, Signature=$sig",
        ],
    ] );
    $r = curl_exec( $ch ); $code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE ); curl_close( $ch );
    if ( $code !== 200 ) update_option( 'tls_tts_live_err', gmdate( 'c' ) . " r2=$code " . substr( (string) $r, 0, 300 ), false );
    return $code === 200;
}

function tls_live_nocache() {
    header( 'Cache-Control: no-store' );
    header( 'X-LiteSpeed-Cache-Control: no-cache' );
    header( 'X-Robots-Tag: noindex' );
}

add_action( 'rest_api_init', function () {
    /* Plan: parça sayısı + her parçanın (bilinen ya da tahmini) süresi. */
    register_rest_route( 'tls/v1', '/listen/(?P<id>\d+)', [
        'methods' => 'GET', 'permission_callback' => '__return_true',
        'callback' => function ( $req ) {
            tls_live_nocache();
            $pid = (int) $req['id'];
            if ( ! tls_live_ready() || ! tls_live_eligible( $pid ) ) return new WP_REST_Response( [ 'ok' => false ], 404 );
            $cfg = tls_live_cfg(); $ch = tls_live_chunks( $pid ); $h = tls_live_hash( $ch, $cfg['voice'] );
            $done = get_post_meta( $pid, '_tls_live_parts', true );
            $done = ( is_array( $done ) && ( $done['h'] ?? '' ) === $h ) ? (array) $done['s'] : [];
            $secs = [];
            foreach ( $ch as $i => $t ) $secs[] = (float) ( $done[ $i ] ?? round( mb_strlen( $t ) / 14.5, 1 ) );
            return [ 'ok' => true, 'h' => $h, 'n' => count( $ch ), 'secs' => $secs, 'base' => rest_url( "tls/v1/listen/$pid/$h/" ) ];
        },
    ] );

    /* Parça: R2'de varsa oraya yönlendir; yoksa üret → R2'ye koy → sesi döndür. */
    register_rest_route( 'tls/v1', '/listen/(?P<id>\d+)/(?P<h>[a-f0-9]{10})/(?P<i>\d+)', [
        'methods' => 'GET', 'permission_callback' => '__return_true',
        'callback' => function ( $req ) {
            tls_live_nocache();
            $pid = (int) $req['id']; $i = (int) $req['i']; $h = (string) $req['h'];
            if ( ! tls_live_ready() || ! tls_live_eligible( $pid ) ) return new WP_REST_Response( [ 'ok' => false ], 404 );
            $cfg = tls_live_cfg(); $ch = tls_live_chunks( $pid );
            if ( tls_live_hash( $ch, $cfg['voice'] ) !== $h ) return new WP_REST_Response( [ 'ok' => false, 'error' => 'stale' ], 409 );
            if ( ! isset( $ch[ $i ] ) ) return new WP_REST_Response( [ 'ok' => false ], 404 );
            $obj = "live/$pid/$h-$i.mp3";

            $parts = function () use ( $pid, $h ) {
                $d = get_post_meta( $pid, '_tls_live_parts', true );
                return ( is_array( $d ) && ( $d['h'] ?? '' ) === $h ) ? $d : [ 'h' => $h, 's' => [] ];
            };
            $redirect = function () use ( $obj ) { wp_redirect( TLS_R2_PUBLIC . '/' . $obj, 302 ); exit; };
            if ( isset( $parts()['s'][ $i ] ) ) $redirect();

            // Aynı parçayı iki kişi aynı anda isterse yalnız biri üretir; diğeri bekler.
            $lock = "tls_live_lock_{$pid}_{$i}";
            if ( get_transient( $lock ) ) {
                for ( $w = 0; $w < 40; $w++ ) {
                    usleep( 500000 ); wp_cache_delete( $pid, 'post_meta' );
                    if ( isset( $parts()['s'][ $i ] ) ) $redirect();
                }
                return new WP_REST_Response( [ 'ok' => false, 'error' => 'busy' ], 503 );
            }

            // Korumalar: IP başına 10 dakikada 40 yeni parça, günlük karakter tavanı.
            $ip = substr( md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ), 0, 12 );
            $rk = "tls_live_ip_$ip"; $rc = (int) get_transient( $rk );
            if ( $rc >= 40 ) return new WP_REST_Response( [ 'ok' => false, 'error' => 'rate' ], 429 );
            $use = get_option( 'tls_tts_live_usage', [] );
            $today = gmdate( 'Y-m-d' );
            if ( ( $use['d'] ?? '' ) !== $today ) $use = [ 'd' => $today, 'c' => 0, 'n' => 0 ];
            if ( (int) $use['c'] + mb_strlen( $ch[ $i ] ) > (int) $cfg['daily_chars'] ) return new WP_REST_Response( [ 'ok' => false, 'error' => 'budget' ], 429 );

            set_transient( $lock, 1, 120 );
            set_transient( $rk, $rc + 1, 600 );
            @set_time_limit( 150 );
            $bin = tls_live_synth( $ch[ $i ], $cfg['voice'], $cfg['deepinfra_key'] );
            if ( $bin === '' ) { delete_transient( $lock ); return new WP_REST_Response( [ 'ok' => false, 'error' => 'tts' ], 502 ); }

            $use['c'] = (int) $use['c'] + mb_strlen( $ch[ $i ] ); $use['n'] = (int) $use['n'] + 1;
            $use['total'] = (int) ( get_option( 'tls_tts_live_usage', [] )['total'] ?? 0 ) + mb_strlen( $ch[ $i ] );
            update_option( 'tls_tts_live_usage', $use, false );

            if ( tls_r2_put( $obj, $bin, 'audio/mpeg', $cfg['r2_key'], $cfg['r2_secret'] ) ) {
                $d = $parts(); $d['s'][ $i ] = tls_mp3_seconds( $bin ) ?: round( mb_strlen( $ch[ $i ] ) / 14.5, 1 );
                $d['n'] = count( $ch );
                update_post_meta( $pid, '_tls_live_parts', $d );
            }
            delete_transient( $lock );
            header( 'Content-Type: audio/mpeg' );
            header( 'Content-Length: ' . strlen( $bin ) );
            echo $bin; exit;
        },
    ] );
} );
