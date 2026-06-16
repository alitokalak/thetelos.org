<?php
/**
 * The Telos — Contact Form
 *
 * - Shortcode: [tls_contact_form]
 * - AJAX handler + honeypot + nonce + rate limit
 * - DB'ye kaydeder (tls_contact CPT) + admin'e email gönderir
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── CPT ─────────────────────────────────────────────────────── */
add_action( 'init', function() {
    register_post_type( 'tls_contact', [
        'labels'          => [
            'name'          => 'Contact Messages',
            'singular_name' => 'Contact Message',
            'menu_name'     => 'Contact Messages',
            'all_items'     => 'All Messages',
        ],
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => true,
        'menu_icon'       => 'dashicons-email-alt',
        'menu_position'   => 26,
        'supports'        => [ 'title' ],
        'capability_type' => 'post',
        'capabilities'    => [ 'create_posts' => 'do_not_allow' ],
        'map_meta_cap'    => true,
    ]);
});

/* ── Admin kolonları ─────────────────────────────────────────── */
add_filter( 'manage_tls_contact_posts_columns', function( $cols ) {
    return [
        'cb'          => $cols['cb'],
        'title'       => 'Name',
        'tls_c_email' => 'Email',
        'tls_c_subj'  => 'Subject',
        'date'        => 'Date',
    ];
});
add_action( 'manage_tls_contact_posts_custom_column', function( $col, $post_id ) {
    if ( $col === 'tls_c_email' ) echo esc_html( get_post_meta( $post_id, '_tls_c_email',   true ) );
    if ( $col === 'tls_c_subj'  ) echo esc_html( get_post_meta( $post_id, '_tls_c_subject', true ) );
}, 10, 2 );

/* ── Admin meta box ─────────────────────────────────────────── */
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'tls_contact_detail',
        'Message Details',
        function( $post ) {
            $email   = get_post_meta( $post->ID, '_tls_c_email',   true );
            $subject = get_post_meta( $post->ID, '_tls_c_subject', true );
            $message = get_post_meta( $post->ID, '_tls_c_message', true );
            $ip      = get_post_meta( $post->ID, '_tls_c_ip',      true );
            $rows = [
                'Name'    => esc_html( $post->post_title ),
                'Email'   => '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>',
                'Subject' => esc_html( $subject ),
                'Message' => '<pre style="white-space:pre-wrap;font-family:inherit;margin:0;">' . esc_html($message) . '</pre>',
                'IP'      => esc_html( $ip ),
            ];
            echo '<table style="width:100%;border-collapse:collapse;">';
            foreach ( $rows as $label => $val ) {
                echo '<tr><th style="text-align:left;padding:8px 12px 8px 0;width:80px;vertical-align:top;color:#666;">'
                    . esc_html($label) . '</th>'
                    . '<td style="padding:8px 0;border-bottom:1px solid #f0f0f0;">' . $val . '</td></tr>';
            }
            echo '</table>';
        },
        'tls_contact', 'normal', 'high'
    );
});

/* ── AJAX handler ────────────────────────────────────────────── */
add_action( 'wp_ajax_tls_submit_contact',        'tls_handle_contact' );
add_action( 'wp_ajax_nopriv_tls_submit_contact', 'tls_handle_contact' );

function tls_handle_contact() {
    if ( ! check_ajax_referer('tls_contact_nonce', 'nonce', false) ) {
        wp_send_json_error(['message' => 'Security check failed.'], 403);
    }

    // Honeypot
    if ( ! empty( $_POST['tls_hp'] ) ) {
        wp_send_json_success(['message' => 'ok']);
        return;
    }

    // Zaman kontrolü
    if ( time() - (int)( $_POST['tls_time'] ?? 0 ) < 3 ) {
        wp_send_json_error(['message' => 'Please take your time filling the form.']);
        return;
    }

    // Rate limit
    $ip      = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
    $rk      = 'tls_contact_' . md5( $ip );
    $count   = (int) get_transient( $rk );
    if ( $count >= 5 ) {
        wp_send_json_error(['message' => 'Too many messages. Please try again later.']);
        return;
    }

    $name    = sanitize_text_field(     $_POST['tls_name']    ?? '' );
    $email   = sanitize_email(          $_POST['tls_email']   ?? '' );
    $subject = sanitize_text_field(     $_POST['tls_subject'] ?? '' );
    $message = sanitize_textarea_field( $_POST['tls_message'] ?? '' );

    if ( ! $name || ! is_email($email) || ! $subject || ! $message ) {
        wp_send_json_error(['message' => 'Please fill in all required fields.']);
        return;
    }

    // DB'ye kaydet
    $post_id = wp_insert_post([
        'post_type'   => 'tls_contact',
        'post_title'  => $name,
        'post_status' => 'publish',
    ]);
    if ( is_wp_error($post_id) ) {
        wp_send_json_error(['message' => 'Something went wrong. Please try again.']);
        return;
    }

    update_post_meta( $post_id, '_tls_c_email',   $email );
    update_post_meta( $post_id, '_tls_c_subject',  $subject );
    update_post_meta( $post_id, '_tls_c_message',  $message );
    update_post_meta( $post_id, '_tls_c_ip',       $ip );

    set_transient( $rk, $count + 1, HOUR_IN_SECONDS );

    // Email bildirimi
    wp_mail(
        get_option('admin_email'),
        '[thetelos] ' . $subject,
        "New contact message received.\n\nName: {$name}\nEmail: {$email}\nSubject: {$subject}\n\nMessage:\n{$message}\n\nAdmin: " . admin_url("post.php?post={$post_id}&action=edit"),
        [ 'Reply-To: ' . $name . ' <' . $email . '>' ]
    );

    wp_send_json_success(['message' => 'ok']);
}

/* ── Shortcode ───────────────────────────────────────────────── */
add_shortcode( 'tls_contact_form', 'tls_render_contact_form' );

function tls_render_contact_form() {
    $nonce = wp_create_nonce('tls_contact_nonce');
    ob_start();
    ?>
    <div class="tls-req-wrap">

        <div id="tls-contact-success" class="tls-req-success" style="display:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="40" height="40"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>
            <h3>Message Sent</h3>
            <p>Thank you for reaching out. We'll get back to you as soon as possible.</p>
        </div>

        <div id="tls-contact-form-wrap">
            <div id="tls-contact-error" class="tls-req-error" style="display:none;"></div>

            <div class="tls-req-fields">

                <div class="tls-req-row tls-req-row--2">
                    <div class="tls-req-field">
                        <label class="tls-req-label" for="tls-c-name">Your Name <span>*</span></label>
                        <input class="tls-req-input" type="text" id="tls-c-name"
                               placeholder="Marcus Aurelius" autocomplete="name" required>
                    </div>
                    <div class="tls-req-field">
                        <label class="tls-req-label" for="tls-c-email">Email Address <span>*</span></label>
                        <input class="tls-req-input" type="email" id="tls-c-email"
                               placeholder="you@example.com" autocomplete="email" required>
                    </div>
                </div>

                <div class="tls-req-field">
                    <label class="tls-req-label" for="tls-c-subject">Subject <span>*</span></label>
                    <input class="tls-req-input" type="text" id="tls-c-subject"
                           placeholder="How can we help?" required>
                </div>

                <div class="tls-req-field">
                    <label class="tls-req-label" for="tls-c-message">Message <span>*</span></label>
                    <textarea class="tls-req-input tls-req-textarea" id="tls-c-message"
                              placeholder="Write your message here…" rows="5" required></textarea>
                </div>

                <!-- Honeypot -->
                <div class="tls-req-hp" aria-hidden="true">
                    <input type="text" name="tls_hp" tabindex="-1" autocomplete="off" value="">
                </div>

                <div class="tls-req-footer">
                    <p class="tls-req-privacy">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        Your message is private and will only be read by our team.
                    </p>
                    <button class="tls-req-submit" id="tls-contact-submit" type="button">
                        <span class="tls-contact-submit-text">Send Message</span>
                        <span class="tls-contact-submit-loading" style="display:none;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" class="tls-spin"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                            Sending…
                        </span>
                    </button>
                </div>

            </div>
        </div>
    </div>

    <script>
    (function(){
        var wrap = document.getElementById('tls-contact-form-wrap');
        var btn  = document.getElementById('tls-contact-submit');
        var err  = document.getElementById('tls-contact-error');
        var succ = document.getElementById('tls-contact-success');
        var t0   = Math.floor(Date.now()/1000);
        if (!btn) return;
        btn.addEventListener('click', function(){
            var name    = document.getElementById('tls-c-name').value.trim();
            var email   = document.getElementById('tls-c-email').value.trim();
            var subject = document.getElementById('tls-c-subject').value.trim();
            var message = document.getElementById('tls-c-message').value.trim();
            var hp      = document.querySelector('.tls-req-hp input').value;
            err.style.display = 'none';
            if (!name || !email || !subject || !message) {
                err.textContent = 'Please fill in all required fields.';
                err.style.display = 'block';
                return;
            }
            btn.disabled = true;
            btn.querySelector('.tls-contact-submit-text').style.display   = 'none';
            btn.querySelector('.tls-contact-submit-loading').style.display = 'inline-flex';
            var fd = new FormData();
            fd.append('action',      'tls_submit_contact');
            fd.append('nonce',       '<?php echo esc_js($nonce); ?>');
            fd.append('tls_name',    name);
            fd.append('tls_email',   email);
            fd.append('tls_subject', subject);
            fd.append('tls_message', message);
            fd.append('tls_hp',      hp);
            fd.append('tls_time',    t0);
            fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
                method: 'POST', body: fd, credentials: 'same-origin'
            })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success) {
                    wrap.style.opacity = '0';
                    wrap.style.transition = 'opacity 0.3s';
                    setTimeout(function(){
                        wrap.style.display = 'none';
                        succ.style.display = 'flex';
                        succ.style.opacity = '0';
                        succ.style.transition = 'opacity 0.4s';
                        requestAnimationFrame(function(){ succ.style.opacity = '1'; });
                    }, 300);
                } else {
                    err.textContent = res.data.message || 'Something went wrong.';
                    err.style.display = 'block';
                    btn.disabled = false;
                    btn.querySelector('.tls-contact-submit-text').style.display   = 'inline';
                    btn.querySelector('.tls-contact-submit-loading').style.display = 'none';
                }
            })
            .catch(function(){
                err.textContent = 'Network error. Please try again.';
                err.style.display = 'block';
                btn.disabled = false;
                btn.querySelector('.tls-contact-submit-text').style.display   = 'inline';
                btn.querySelector('.tls-contact-submit-loading').style.display = 'none';
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
