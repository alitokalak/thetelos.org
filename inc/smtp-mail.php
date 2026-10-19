<?php
/**
 * The Telos — E-posta gönderimi: cPanel e-posta hesabı üzerinden SMTP
 *
 * Neden: WordPress varsayılanı PHP mail() — sunucudan "kimliksiz" çıkan bu
 * postalar SPF/DKIM hizalamasını tutturamıyor ve spam'e düşüyordu. Burada
 * tüm wp_mail() çağrıları (şifre sıfırlama, iletişim/istek bildirimleri,
 * WordPress çekirdek e-postaları) domain'e ait bir hesapla SMTP üzerinden
 * gönderilir; gönderen ve Return-Path aynı domain adresidir.
 *
 * Ayarlar: Ayarlar → E-posta (SMTP). Şifre wp-config.php'de TLS_SMTP_PASS
 * olarak da tanımlanabilir (tanımlıysa panel alanını ezer).
 *
 * Her e-posta HTML + düz metin (multipart/alternative) gider: düz metin
 * gönderilmişse markalı HTML sürümü üretilir; yalnız HTML gönderilmişse düz
 * metin sürümü HTML'den çıkarılır.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tls_smtp_settings() {
    $o = (array) get_option( 'tls_smtp', [] );
    $s = [
        'host'      => trim( (string) ( $o['host'] ?? '' ) ),
        'port'      => (int) ( $o['port'] ?? 465 ),
        'secure'    => in_array( $o['secure'] ?? 'ssl', [ 'ssl', 'tls', '' ], true ) ? ( $o['secure'] ?? 'ssl' ) : 'ssl',
        'user'      => trim( (string) ( $o['user'] ?? '' ) ),
        'pass'      => (string) ( $o['pass'] ?? '' ),
        'from'      => trim( (string) ( $o['from'] ?? '' ) ),
        'from_name' => trim( (string) ( $o['from_name'] ?? '' ) ),
        'reply_to'  => trim( (string) ( $o['reply_to'] ?? 'support@thetelos.org' ) ),
    ];
    // wp-config.php sabitleri paneli ezer (şifreyi veritabanında tutmamak isteyene).
    foreach ( [ 'host' => 'TLS_SMTP_HOST', 'port' => 'TLS_SMTP_PORT', 'secure' => 'TLS_SMTP_SECURE',
                'user' => 'TLS_SMTP_USER', 'pass' => 'TLS_SMTP_PASS', 'from' => 'TLS_SMTP_FROM' ] as $k => $c ) {
        if ( defined( $c ) ) $s[ $k ] = constant( $c );
    }
    if ( $s['from'] === '' )      $s['from'] = $s['user'];
    if ( $s['from_name'] === '' ) $s['from_name'] = get_bloginfo( 'name' ) ?: 'The Telos';
    return $s;
}

function tls_smtp_ready() {
    $s = tls_smtp_settings();
    return $s['host'] !== '' && $s['user'] !== '' && $s['pass'] !== '' && is_email( $s['from'] );
}

/* ── Gönderen: domain adresi (WordPress'in wordpress@… varsayılanı yerine) ── */
add_filter( 'wp_mail_from', function ( $from ) {
    $s = tls_smtp_settings();
    return is_email( $s['from'] ) ? $s['from'] : $from;
}, 99 );
add_filter( 'wp_mail_from_name', function ( $name ) {
    return tls_smtp_settings()['from_name'];
}, 99 );

/* ── SMTP + HTML/düz metin ─────────────────────────────────────────────── */
add_action( 'phpmailer_init', 'tls_smtp_phpmailer', 999 );
function tls_smtp_phpmailer( $phpmailer ) {
    $s = tls_smtp_settings();

    if ( tls_smtp_ready() ) {
        $phpmailer->isSMTP();
        $phpmailer->Host       = $s['host'];
        $phpmailer->Port       = $s['port'] ?: 465;
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = $s['user'];
        $phpmailer->Password   = $s['pass'];
        $phpmailer->SMTPSecure = $s['secure'];
        $phpmailer->SMTPAutoTLS = ( $s['secure'] !== '' );
        $phpmailer->Timeout    = 20;
        // Aynı sunucudaki posta servisine "localhost" ile bağlanılırsa sertifika
        // localhost adına değil sunucu adına düzenlenmiştir → doğrulama tutmaz.
        // Bağlantı makineden dışarı çıkmadığı için yalnız bu durumda doğrulama esnetilir.
        if ( in_array( strtolower( $s['host'] ), [ 'localhost', '127.0.0.1' ], true ) ) {
            $phpmailer->SMTPOptions = [ 'ssl' => [ 'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true ] ];
        }
    }
    if ( is_email( $s['from'] ) ) {
        $phpmailer->setFrom( $s['from'], $s['from_name'], false );
        $phpmailer->Sender = $s['from'];   // Return-Path = gönderen → SPF hizalaması
    }
    // noreply@ adresine gelen yanıtlar kaybolmasın: kendi Reply-To'su olmayan
    // e-postalarda (şifre sıfırlama vb.) yanıtlar destek adresine gider.
    // İletişim formu bildirimi kendi Reply-To'sunu (ziyaretçi) korur.
    if ( is_email( $s['reply_to'] ) && ! $phpmailer->getReplyToAddresses() ) {
        $phpmailer->addReplyTo( $s['reply_to'] );
    }

    // HTML + düz metin birlikte.
    $is_html = ( stripos( (string) $phpmailer->ContentType, 'html' ) !== false );
    if ( $is_html ) {
        if ( trim( (string) $phpmailer->AltBody ) === '' ) {
            $phpmailer->AltBody = tls_mail_html_to_text( $phpmailer->Body );
        }
    } else {
        $plain = (string) $phpmailer->Body;
        $phpmailer->isHTML( true );
        $phpmailer->Body    = tls_mail_wrap_html( $plain, (string) $phpmailer->Subject );
        $phpmailer->AltBody = $plain;
    }
    if ( ! $phpmailer->CharSet || strtolower( $phpmailer->CharSet ) !== 'utf-8' ) $phpmailer->CharSet = 'UTF-8';
}

function tls_mail_html_to_text( $html ) {
    $t = preg_replace( '#<(style|script|head)[^>]*>.*?</\1>#is', '', (string) $html );
    $t = preg_replace( '#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '$2 ($1)', $t );
    $t = preg_replace( '#<br\s*/?>|</(p|div|tr|h[1-6]|li)>#i', "\n", $t );
    $t = html_entity_decode( wp_strip_all_tags( $t, false ), ENT_QUOTES, 'UTF-8' );
    $t = preg_replace( "/[ \t]+/", ' ', $t );
    return trim( preg_replace( "/\n\s*\n\s*\n+/", "\n\n", $t ) );
}

/* Düz metni markalı, sade bir HTML şablonuna yerleştir (şifre sıfırlama
   e-postasıyla aynı görünüm). URL'ler tıklanabilir yapılır. */
function tls_mail_wrap_html( $plain, $subject = '' ) {
    $site = get_bloginfo( 'name' ) ?: 'The Telos';
    $logo = get_template_directory_uri() . '/assets/img/thetelos_logo.png';
    $plain = preg_replace( '#<(https?://[^>\s]+)>#', '$1', (string) $plain );   // "<url>" biçimini sadeleştir
    $body = nl2br( make_clickable( esc_html( $plain ) ) );
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . esc_html( $subject ) . '</title></head>'
         . '<body style="margin:0;padding:0;background:#F5F3EE;font-family:system-ui,-apple-system,sans-serif;">'
         . '<table width="100%" cellpadding="0" cellspacing="0" bgcolor="#F5F3EE"><tr><td align="center" style="padding:40px 16px;">'
         . '<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">'
         . '<tr><td align="center" bgcolor="#1A1814" style="padding:28px 40px;border-radius:8px 8px 0 0;">'
         . '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $site ) . '" width="76" height="46" style="display:inline-block;"></td></tr>'
         . '<tr><td bgcolor="#ffffff" style="padding:36px 40px;border:1px solid #E2DECE;border-top:none;font-size:15px;line-height:1.7;color:#1A1814;">'
         . $body . '</td></tr>'
         . '<tr><td align="center" style="padding:16px;font-size:12px;color:#6B6560;">thetelos.org &mdash; The Digital Archive</td></tr>'
         . '</table></td></tr></table></body></html>';
}

/* ── Ayarlar → E-posta (SMTP) ──────────────────────────────────────────── */
add_action( 'admin_menu', function () {
    add_options_page( 'E-posta (SMTP)', 'E-posta (SMTP)', 'manage_options', 'tls-smtp', 'tls_smtp_admin_page' );
} );

add_action( 'admin_post_tls_smtp_save', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Yetki yok.' );
    check_admin_referer( 'tls_smtp_save' );
    $old = (array) get_option( 'tls_smtp', [] );
    $new = [
        'host'      => sanitize_text_field( wp_unslash( $_POST['host'] ?? '' ) ),
        'port'      => (int) ( $_POST['port'] ?? 465 ),
        'secure'    => in_array( $_POST['secure'] ?? 'ssl', [ 'ssl', 'tls', '' ], true ) ? $_POST['secure'] : 'ssl',
        'user'      => sanitize_email( wp_unslash( $_POST['user'] ?? '' ) ),
        'from'      => sanitize_email( wp_unslash( $_POST['from'] ?? '' ) ),
        'from_name' => sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) ),
        'reply_to'  => sanitize_email( wp_unslash( $_POST['reply_to'] ?? '' ) ),
        // Şifre alanı boş bırakılırsa mevcut şifre korunur.
        'pass'      => ( (string) ( $_POST['pass'] ?? '' ) !== '' ) ? (string) wp_unslash( $_POST['pass'] ) : (string) ( $old['pass'] ?? '' ),
    ];
    update_option( 'tls_smtp', $new, false );
    wp_safe_redirect( admin_url( 'options-general.php?page=tls-smtp&saved=1' ) );
    exit;
} );

add_action( 'admin_post_tls_smtp_test', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Yetki yok.' );
    check_admin_referer( 'tls_smtp_test' );
    $to  = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) );
    $err = '';
    $cap = function ( $e ) use ( &$err ) { $err = $e->get_error_message(); };
    add_action( 'wp_mail_failed', $cap );
    $ok = is_email( $to ) && wp_mail( $to, '[thetelos] SMTP test', "Merhaba,\n\nBu bir test e-postasıdır. Bu mesajı görüyorsanız SMTP ayarları çalışıyor.\n\nhttps://thetelos.org" );
    remove_action( 'wp_mail_failed', $cap );
    set_transient( 'tls_smtp_test_result', $ok ? 'ok' : ( $err ?: 'Gönderilemedi.' ), 120 );
    wp_safe_redirect( admin_url( 'options-general.php?page=tls-smtp&tested=1' ) );
    exit;
} );

function tls_smtp_admin_page() {
    $s     = tls_smtp_settings();
    $o     = (array) get_option( 'tls_smtp', [] );
    $ready = tls_smtp_ready();
    ?>
    <div class="wrap">
        <h1>E-posta (SMTP)</h1>
        <?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success"><p>Kaydedildi.</p></div><?php endif; ?>
        <?php if ( isset( $_GET['tested'] ) ) : $r = get_transient( 'tls_smtp_test_result' ); ?>
            <div class="notice <?php echo $r === 'ok' ? 'notice-success' : 'notice-error'; ?>"><p>
                <?php echo $r === 'ok' ? 'Test e-postası gönderildi. Gelen kutusunu (ve spam klasörünü) kontrol edin.' : 'Hata: ' . esc_html( (string) $r ); ?>
            </p></div>
        <?php endif; ?>
        <p>Durum: <strong style="color:<?php echo $ready ? '#2e7d52' : '#b32d2e'; ?>"><?php echo $ready ? 'SMTP aktif' : 'SMTP ayarlanmadı — e-postalar PHP mail() ile gidiyor'; ?></strong></p>
        <p>cPanel'de oluşturduğunuz e-posta hesabının bilgilerini girin (cPanel → E-posta Hesapları → "Connect Devices" sayfasında yazar).</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="tls_smtp_save">
            <?php wp_nonce_field( 'tls_smtp_save' ); ?>
            <table class="form-table">
                <tr><th>SMTP sunucusu</th><td><input name="host" class="regular-text" value="<?php echo esc_attr( $o['host'] ?? 'mail.thetelos.org' ); ?>" placeholder="mail.thetelos.org"></td></tr>
                <tr><th>Port / Şifreleme</th><td>
                    <input name="port" type="number" style="width:90px" value="<?php echo esc_attr( $o['port'] ?? 465 ); ?>">
                    <select name="secure">
                        <?php foreach ( [ 'ssl' => 'SSL (465)', 'tls' => 'TLS / STARTTLS (587)', '' => 'Yok' ] as $v => $l ) : ?>
                        <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $o['secure'] ?? 'ssl', $v ); ?>><?php echo esc_html( $l ); ?></option>
                        <?php endforeach; ?>
                    </select></td></tr>
                <tr><th>Kullanıcı adı (e-posta)</th><td><input name="user" class="regular-text" value="<?php echo esc_attr( $o['user'] ?? 'noreply@thetelos.org' ); ?>" placeholder="noreply@thetelos.org"></td></tr>
                <tr><th>Şifre</th><td><input name="pass" type="password" class="regular-text" autocomplete="new-password" placeholder="<?php echo ! empty( $o['pass'] ) ? '•••••••• (değiştirmek için yazın)' : ''; ?>">
                    <?php if ( defined( 'TLS_SMTP_PASS' ) ) : ?><p class="description">Şifre wp-config.php'deki TLS_SMTP_PASS'tan alınıyor.</p><?php endif; ?></td></tr>
                <tr><th>Gönderen adresi</th><td><input name="from" class="regular-text" value="<?php echo esc_attr( $o['from'] ?? '' ); ?>" placeholder="boşsa kullanıcı adı">
                    <p class="description">thetelos.org alan adına ait olmalı.</p></td></tr>
                <tr><th>Yanıt adresi (Reply-To)</th><td><input name="reply_to" class="regular-text" value="<?php echo esc_attr( $o['reply_to'] ?? 'support@thetelos.org' ); ?>" placeholder="support@thetelos.org">
                    <p class="description">Kullanıcı e-postayı yanıtlarsa bu adrese gider.</p></td></tr>
                <tr><th>Gönderen adı</th><td><input name="from_name" class="regular-text" value="<?php echo esc_attr( $o['from_name'] ?? '' ); ?>" placeholder="The Telos"></td></tr>
            </table>
            <?php submit_button( 'Kaydet' ); ?>
        </form>
        <hr>
        <h2>Test e-postası</h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="tls_smtp_test">
            <?php wp_nonce_field( 'tls_smtp_test' ); ?>
            <input name="to" type="email" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
            <?php submit_button( 'Test gönder', 'secondary', 'submit', false ); ?>
        </form>
    </div>
    <?php
}
