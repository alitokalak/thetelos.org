<?php
/**
 * The Telos — Membership System v3
 * - Header dropdown
 * - Full profile page via template_redirect (no rewrite rules needed)
 * - Auth AJAX handlers
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════
   1. PROFIL SAYFASI — direkt URI kontrolü
   /profile/ → profil template
   Hiç flush_rewrite_rules gerekmez
══════════════════════════════════════════════ */
add_action( 'template_redirect', function() {
    $path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

    if ( $path === 'profile' || $path === 'profile/' ) {
        if ( ! is_user_logged_in() ) {
            wp_redirect( home_url('/') );
            exit;
        }
        include get_template_directory() . '/tls-page-profile.php';
        exit;
    }
}, 1 );

/* ══════════════════════════════════════════════
   2. AJAX HANDLERS
══════════════════════════════════════════════ */

/* Kayıt */
add_action( 'wp_ajax_nopriv_tls_register', function() {
    if ( ! check_ajax_referer('tls_auth_nonce', 'nonce', false) )
        wp_send_json_error(['message' => 'Security check failed.']);
    if ( ! empty($_POST['tls_hp']) ) wp_send_json_success();

    $name  = sanitize_text_field( $_POST['tls_name']  ?? '' );
    $email = sanitize_email(      $_POST['tls_email'] ?? '' );
    $pass  = $_POST['tls_pass']  ?? '';

    if ( ! $name || ! is_email($email) || strlen($pass) < 6 )
        wp_send_json_error(['message' => 'Please fill all fields. Password min. 6 characters.']);
    if ( email_exists($email) )
        wp_send_json_error(['message' => 'This email is already registered.']);

    $uid = wp_create_user( $email, $pass, $email );
    if ( is_wp_error($uid) ) wp_send_json_error(['message' => $uid->get_error_message()]);

    wp_update_user(['ID'=>$uid,'display_name'=>$name,'first_name'=>$name,'role'=>'subscriber']);
    wp_set_current_user($uid);
    wp_set_auth_cookie($uid, true);
    wp_send_json_success(['redirect' => home_url('/profile/')]);
} );

/* Giriş */
add_action( 'wp_ajax_nopriv_tls_login', function() {
    if ( ! check_ajax_referer('tls_auth_nonce', 'nonce', false) )
        wp_send_json_error(['message' => 'Security check failed.']);

    $email = sanitize_email( $_POST['tls_email'] ?? '' );
    $pass  = $_POST['tls_pass'] ?? '';
    $rem   = ! empty($_POST['tls_remember']);

    if ( ! $email || ! $pass )
        wp_send_json_error(['message' => 'Please enter your email and password.']);

    $ip  = md5($_SERVER['REMOTE_ADDR'] ?? '');
    $key = 'tls_login_' . $ip;
    if ( (int)get_transient($key) >= 5 )
        wp_send_json_error(['message' => 'Too many attempts. Try again in an hour.']);

    $user = wp_authenticate($email, $pass);
    if ( is_wp_error($user) ) {
        set_transient($key, (int)get_transient($key)+1, HOUR_IN_SECONDS);
        wp_send_json_error(['message' => 'Incorrect email or password.']);
    }

    delete_transient($key);
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, $rem);
    wp_send_json_success(['redirect' => home_url('/profile/')]);
} );

/* Şifre sıfırla */
add_action( 'wp_ajax_nopriv_tls_forgot', function() {
    if ( ! check_ajax_referer('tls_auth_nonce', 'nonce', false) )
        wp_send_json_error(['message' => 'Security check failed.']);

    $email = sanitize_email($_POST['tls_email'] ?? '');
    if ( ! is_email($email) ) wp_send_json_error(['message' => 'Please enter a valid email.']);

    $user = get_user_by('email', $email);
    if ( $user ) {
        $key  = get_password_reset_key($user);
        $link = network_site_url("wp-login.php?action=rp&key={$key}&login=".rawurlencode($user->user_login));
        wp_mail($email, '[thetelos] Reset your password',
            "Hello {$user->display_name},\n\nReset your password:\n{$link}\n\nExpires in 24 hours.\n\nthetelos.org");
    }
    wp_send_json_success(['message' => "If that email exists, we've sent a reset link."]);
} );

/* Çıkış */
add_action( 'wp_ajax_tls_logout', function() {
    check_ajax_referer('tls_auth_nonce', 'nonce');
    wp_logout();
    wp_send_json_success(['redirect' => home_url()]);
} );

/* Reading status */
add_action( 'wp_ajax_tls_set_status', function() {
    if ( ! is_user_logged_in() ) wp_send_json_error(['message' => 'login_required']);
    /* Nonce soft check — cache sorunu yaşanabileceğinden hard fail yok */
    check_ajax_referer('tls_status_nonce', 'nonce', false);

    $post_id = (int)($_POST['post_id'] ?? 0);
    $status  = sanitize_text_field($_POST['status'] ?? '');
    $uid     = get_current_user_id();

    if ( ! in_array($status, ['want','reading','read',''], true) )
        wp_send_json_error(['message' => 'Invalid status.']);

    $key = '_tls_reading_status_' . $post_id;
    if ( $status === '' ) {
        delete_user_meta($uid, $key);
    } else {
        update_user_meta($uid, $key, $status);
        update_user_meta($uid, '_tls_status_date_' . $post_id, current_time('mysql'));
    }
    wp_send_json_success(['status' => $status]);
} );

/* Kullanıcı kütüphanesi */
add_action( 'wp_ajax_tls_get_library', function() {
    if ( ! is_user_logged_in() ) wp_send_json_error(['message' => 'login_required']);

    $uid    = get_current_user_id();
    $filter = sanitize_text_field($_POST['filter'] ?? 'all');

    global $wpdb;
    $metas = $wpdb->get_results( $wpdb->prepare(
        "SELECT meta_key, meta_value FROM {$wpdb->usermeta}
         WHERE user_id = %d AND meta_key LIKE %s",
        $uid, '_tls_reading_status_%'
    ) );

    $books = [];
    foreach ( $metas as $m ) {
        $pid    = (int) str_replace('_tls_reading_status_', '', $m->meta_key);
        $status = $m->meta_value;
        if ( $filter !== 'all' && $status !== $filter ) continue;
        $post   = get_post($pid);
        if ( ! $post || $post->post_status !== 'publish' ) continue;

        $authors = get_the_terms($pid, 'authors');
        $author  = (!empty($authors) && !is_wp_error($authors)) ? $authors[0]->name : '';
        $cats    = get_the_category($pid);
        $cat     = !empty($cats) ? $cats[0]->name : '';

        $books[] = [
            'id'       => $pid,
            'title'    => get_the_title($pid),
            'author'   => $author,
            'category' => $cat,
            'url'      => get_permalink($pid),
            'status'   => $status,
            'date'     => get_user_meta($uid, '_tls_status_date_'.$pid, true),
            'cover'    => has_post_thumbnail($pid) ? get_the_post_thumbnail_url($pid,'medium') : '',
        ];
    }
    usort($books, fn($a,$b) => strcmp($b['date'], $a['date']));
    wp_send_json_success(['books' => $books, 'total' => count($books)]);
} );

/* Profil güncelle */
add_action( 'wp_ajax_tls_update_profile', function() {
    if ( ! is_user_logged_in() ) wp_send_json_error(['message' => 'Not logged in.']);
    if ( ! check_ajax_referer('tls_auth_nonce', 'nonce', false) )
        wp_send_json_error(['message' => 'Security check failed.']);

    $uid     = get_current_user_id();
    $name    = sanitize_text_field($_POST['tls_name'] ?? '');
    $email   = sanitize_email($_POST['tls_email'] ?? '');
    $pass    = $_POST['tls_pass'] ?? '';
    $passold = $_POST['tls_pass_old'] ?? '';

    $data = ['ID' => $uid];
    if ($name) $data['display_name'] = $data['first_name'] = $name;
    if ($email && $email !== wp_get_current_user()->user_email) {
        if ( email_exists($email) ) wp_send_json_error(['message' => 'This email is already in use.']);
        $data['user_email'] = $email;
    }
    if ($pass) {
        if (!$passold) wp_send_json_error(['message'=>'Enter current password to change it.']);
        $user = get_user_by('id',$uid);
        if (!wp_check_password($passold,$user->user_pass,$uid))
            wp_send_json_error(['message'=>'Current password is incorrect.']);
        if (strlen($pass)<6) wp_send_json_error(['message'=>'New password must be at least 6 characters.']);
        $data['user_pass'] = $pass;
    }
    wp_update_user($data);
    wp_send_json_success(['message'=>'Saved successfully.']);
} );

/* ══════════════════════════════════════════════
   3. LOGOUT / LOGIN REDIRECT
══════════════════════════════════════════════ */
add_filter( 'logout_redirect', fn() => home_url('/') );

/* ══════════════════════════════════════════════
   4. ADMIN BAR — subscriber için gizle
══════════════════════════════════════════════ */
add_action( 'after_setup_theme', function() {
    if ( is_user_logged_in() && ! current_user_can('edit_posts') ) {
        show_admin_bar(false);
    }
} );

/* ══════════════════════════════════════════════
   5. FRONTEND DEĞİŞKENLERİ
══════════════════════════════════════════════ */
add_action( 'wp_enqueue_scripts', function() {
    wp_localize_script( 'thetelos-js', 'tlsAuth', [
        'ajaxUrl'     => admin_url('admin-ajax.php'),
        'isLoggedIn'  => is_user_logged_in(),
        'profileUrl'  => home_url('/profile/'),
        'statusNonce' => wp_create_nonce('tls_status_nonce'),
        'authNonce'   => wp_create_nonce('tls_auth_nonce'),
        'user'        => is_user_logged_in() ? [
            'name'     => wp_get_current_user()->display_name,
            'email'    => wp_get_current_user()->user_email,
            'initials' => strtoupper(substr(wp_get_current_user()->display_name, 0, 1)),
        ] : null,
    ]);
} );

/* ══════════════════════════════════════════════
   tls_get_user_state — footer.php'nin AJAX çağrısı
   Login durumu + nonce + post reading status döner
   Cache'den bağımsız çalışır
══════════════════════════════════════════════ */
add_action( 'wp_ajax_tls_get_user_state',        'tls_handle_user_state' );
add_action( 'wp_ajax_nopriv_tls_get_user_state', 'tls_handle_user_state' );

function tls_handle_user_state() {
    $logged_in = is_user_logged_in();
    $post_id   = (int)( $_POST['post_id'] ?? 0 );

    $user_data = null;
    $status    = '';

    if ( $logged_in ) {
        $user = wp_get_current_user();
        $user_data = [
            'name'    => $user->display_name,
            'initial' => strtoupper( substr( $user->display_name, 0, 1 ) ),
            'email'   => $user->user_email,
            'profile' => home_url( '/profile/' ),
        ];

        if ( $post_id ) {
            $status = get_user_meta( $user->ID, '_tls_reading_status_' . $post_id, true ) ?: '';
        }
    }

    wp_send_json_success( [
        'logged_in' => $logged_in,
        'user'      => $user_data,
        'status'    => $status,
        'nonces'    => [
            'status' => wp_create_nonce( 'tls_status_nonce' ),
            'auth'   => wp_create_nonce( 'tls_auth_nonce' ),
        ],
    ] );
}

/* ══════════════════════════════════════════════
   tls_save_goal — yıllık okuma hedefi
══════════════════════════════════════════════ */
add_action( 'wp_ajax_tls_save_goal', function() {
    if ( ! is_user_logged_in() ) wp_send_json_error( ['message' => 'Not logged in.'] );
    $goal = (int)( $_POST['goal'] ?? 0 );
    $year = (int)( $_POST['year'] ?? date('Y') );
    if ( $goal < 1 || $goal > 365 ) wp_send_json_error( ['message' => 'Invalid goal.'] );
    update_user_meta( get_current_user_id(), '_tls_reading_goal_' . $year, $goal );
    wp_send_json_success( ['goal' => $goal] );
} );
