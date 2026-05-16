<?php
/**
 * The Telos — Summary Request System
 *
 * - Özel CPT: tls_request (admin panelde görünür)
 * - Shortcode: [tls_request_form]
 * - AJAX handler + honeypot + nonce + zaman kontrolü
 * - Email bildirimi
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════
   1. CUSTOM POST TYPE — tls_request
══════════════════════════════════════════════════ */
add_action( 'init', function() {
    register_post_type( 'tls_request', [
        'labels' => [
            'name'               => 'Summary Requests',
            'singular_name'      => 'Summary Request',
            'menu_name'          => 'Summary Requests',
            'all_items'          => 'All Requests',
            'view_item'          => 'View Request',
            'search_items'       => 'Search Requests',
            'not_found'          => 'No requests found.',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-book-alt',
        'menu_position' => 25,
        'supports'      => [ 'title' ],
        'capability_type' => 'post',
        'capabilities'  => [ 'create_posts' => 'do_not_allow' ],
        'map_meta_cap'  => true,
    ]);
});

/* ══════════════════════════════════════════════════
   2. ADMIN KOLONLARI
══════════════════════════════════════════════════ */
add_filter( 'manage_tls_request_posts_columns', function( $cols ) {
    return [
        'cb'           => $cols['cb'],
        'title'        => 'Name',
        'tls_email'    => 'Email',
        'tls_book'     => 'Requested Book',
        'tls_status'   => 'Status',
        'date'         => 'Date',
    ];
});

add_action( 'manage_tls_request_posts_custom_column', function( $col, $post_id ) {
    switch ( $col ) {
        case 'tls_email':
            echo esc_html( get_post_meta( $post_id, '_tls_email', true ) );
            break;
        case 'tls_book':
            echo '<strong>' . esc_html( get_post_meta( $post_id, '_tls_book', true ) ) . '</strong>';
            break;
        case 'tls_status':
            $status = get_post_meta( $post_id, '_tls_status', true ) ?: 'new';
            $colors = [ 'new' => '#C8A165', 'reviewed' => '#7AAFC8', 'done' => '#8BAF7C' ];
            $labels = [ 'new' => '🟡 New', 'reviewed' => '🔵 Reviewed', 'done' => '✅ Done' ];
            echo '<span style="color:' . esc_attr($colors[$status] ?? '#999') . ';font-weight:600;">'
                . esc_html( $labels[$status] ?? $status ) . '</span>';
            break;
    }
}, 10, 2 );

/* ── Admin: request detay meta box ── */
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'tls_request_detail',
        'Request Details',
        'tls_request_meta_box_cb',
        'tls_request',
        'normal',
        'high'
    );
});

function tls_request_meta_box_cb( $post ) {
    $email   = get_post_meta( $post->ID, '_tls_email',   true );
    $book    = get_post_meta( $post->ID, '_tls_book',    true );
    $message = get_post_meta( $post->ID, '_tls_message', true );
    $status  = get_post_meta( $post->ID, '_tls_status',  true ) ?: 'new';
    $ip      = get_post_meta( $post->ID, '_tls_ip',      true );
    wp_nonce_field( 'tls_request_save', 'tls_request_nonce' );
    ?>
    <table style="width:100%;border-collapse:collapse;">
        <tr><td style="padding:8px 0;width:140px;color:#666;font-weight:600;">Name</td>
            <td><?php echo esc_html( $post->post_title ); ?></td></tr>
        <tr><td style="padding:8px 0;color:#666;font-weight:600;">Email</td>
            <td><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></td></tr>
        <tr><td style="padding:8px 0;color:#666;font-weight:600;">Requested Book</td>
            <td><strong><?php echo esc_html($book); ?></strong></td></tr>
        <tr><td style="padding:8px 0;color:#666;font-weight:600;vertical-align:top;">Message</td>
            <td style="white-space:pre-wrap;"><?php echo esc_html($message); ?></td></tr>
        <tr><td style="padding:8px 0;color:#666;font-weight:600;">Status</td>
            <td>
                <select name="tls_status" style="padding:4px 8px;">
                    <?php foreach(['new'=>'New','reviewed'=>'Reviewed','done'=>'Done'] as $v=>$l): ?>
                    <option value="<?php echo $v; ?>" <?php selected($status,$v); ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </td></tr>
        <tr><td style="padding:8px 0;color:#666;font-weight:600;">IP</td>
            <td style="color:#999;font-size:12px;"><?php echo esc_html($ip); ?></td></tr>
    </table>
    <?php
}

add_action( 'save_post_tls_request', function( $post_id ) {
    if ( ! isset($_POST['tls_request_nonce']) ) return;
    if ( ! wp_verify_nonce($_POST['tls_request_nonce'], 'tls_request_save') ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( isset($_POST['tls_status']) ) {
        update_post_meta( $post_id, '_tls_status', sanitize_text_field($_POST['tls_status']) );
    }
});

/* ══════════════════════════════════════════════════
   3. AJAX HANDLER
══════════════════════════════════════════════════ */
add_action( 'wp_ajax_tls_submit_request',        'tls_handle_summary_request' );
add_action( 'wp_ajax_nopriv_tls_submit_request', 'tls_handle_summary_request' );

function tls_handle_summary_request() {
    // Nonce
    if ( ! check_ajax_referer('tls_request_nonce', 'nonce', false) ) {
        wp_send_json_error(['message' => 'Security check failed.'], 403);
    }

    // Honeypot — botlar doldurur, insanlar görmez
    if ( ! empty( $_POST['tls_hp'] ) ) {
        wp_send_json_success(['message' => 'Thank you! Your request has been received.']);
        return;
    }

    // Zaman kontrolü — 3 saniyeden hızlı gönderim = bot
    $form_time = (int)( $_POST['tls_time'] ?? 0 );
    if ( time() - $form_time < 3 ) {
        wp_send_json_error(['message' => 'Please take your time filling the form.']);
        return;
    }

    // Rate limiting — aynı IP'den 1 saat içinde max 3 istek
    $ip       = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
    $rate_key = 'tls_req_' . md5( $ip );
    $count    = (int) get_transient( $rate_key );
    if ( $count >= 3 ) {
        wp_send_json_error(['message' => 'Too many requests. Please try again later.']);
        return;
    }

    // Alanlar
    $name    = sanitize_text_field( $_POST['tls_name']    ?? '' );
    $email   = sanitize_email(      $_POST['tls_email']   ?? '' );
    $book    = sanitize_text_field( $_POST['tls_book']    ?? '' );
    $message = sanitize_textarea_field( $_POST['tls_message'] ?? '' );

    if ( ! $name || ! is_email($email) || ! $book ) {
        wp_send_json_error(['message' => 'Please fill in all required fields.']);
        return;
    }

    // CPT olarak kaydet
    $post_id = wp_insert_post([
        'post_type'   => 'tls_request',
        'post_title'  => $name,
        'post_status' => 'publish',
    ]);

    if ( is_wp_error($post_id) ) {
        wp_send_json_error(['message' => 'Something went wrong. Please try again.']);
        return;
    }

    update_post_meta( $post_id, '_tls_email',   $email );
    update_post_meta( $post_id, '_tls_book',    $book );
    update_post_meta( $post_id, '_tls_message', $message );
    update_post_meta( $post_id, '_tls_status',  'new' );
    update_post_meta( $post_id, '_tls_ip',      $ip );

    // Rate limit güncelle
    set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

    // Email bildirimi
    $admin_email = get_option('admin_email');
    $subject     = '[thetelos] New Summary Request: ' . $book;
    $body        = "New summary request received.\n\n"
                 . "Name: {$name}\n"
                 . "Email: {$email}\n"
                 . "Requested Book: {$book}\n"
                 . "Message: {$message}\n\n"
                 . "View in admin: " . admin_url("post.php?post={$post_id}&action=edit");
    wp_mail( $admin_email, $subject, $body );

    wp_send_json_success(['message' => 'Your request has been received! We\'ll get back to you soon.']);
}

/* ══════════════════════════════════════════════════
   4. SHORTCODE — [tls_request_form]
══════════════════════════════════════════════════ */
add_shortcode( 'tls_request_form', 'tls_render_request_form' );

function tls_render_request_form() {
    ob_start();
    $nonce = wp_create_nonce('tls_request_nonce');
    ?>
    <div class="tls-req-wrap">

        <div class="tls-req-header">
            <div class="tls-req-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28">
                    <path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                </svg>
            </div>
            <div>
                <h2 class="tls-req-title">Request a Summary</h2>
                <p class="tls-req-subtitle">Tell us which book you'd like us to summarize next. We read, distill, and add it to the archive.</p>
            </div>
        </div>

        <div id="tls-req-success" class="tls-req-success" style="display:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="40" height="40"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>
            <h3>Request Received</h3>
            <p>Thank you! We've added your request to our reading list. We'll notify you when the summary goes live.</p>
        </div>

        <div id="tls-req-form-wrap">
            <div id="tls-req-error" class="tls-req-error" style="display:none;"></div>

            <div class="tls-req-fields">

                <div class="tls-req-row tls-req-row--2">
                    <div class="tls-req-field">
                        <label class="tls-req-label" for="tls-name">Your Name <span>*</span></label>
                        <input class="tls-req-input" type="text" id="tls-name" name="tls_name"
                               placeholder="Marcus Aurelius" autocomplete="name" required>
                    </div>
                    <div class="tls-req-field">
                        <label class="tls-req-label" for="tls-email">Email Address <span>*</span></label>
                        <input class="tls-req-input" type="email" id="tls-email" name="tls_email"
                               placeholder="you@example.com" autocomplete="email" required>
                    </div>
                </div>

                <div class="tls-req-field">
                    <label class="tls-req-label" for="tls-book">Book You'd Like Summarized <span>*</span></label>
                    <input class="tls-req-input" type="text" id="tls-book" name="tls_book"
                           placeholder="e.g. Meditations by Marcus Aurelius" required>
                    <span class="tls-req-hint">Include the author's name if possible.</span>
                </div>

                <div class="tls-req-field">
                    <label class="tls-req-label" for="tls-message">Why this book? <span class="tls-req-optional">(optional)</span></label>
                    <textarea class="tls-req-input tls-req-textarea" id="tls-message" name="tls_message"
                              placeholder="Tell us why this book matters to you, or why you think it belongs in the archive…" rows="4"></textarea>
                </div>

                <!-- Honeypot — görünmez, botlar doldurur -->
                <div class="tls-req-hp" aria-hidden="true">
                    <input type="text" name="tls_hp" tabindex="-1" autocomplete="off" value="">
                </div>

                <div class="tls-req-footer">
                    <p class="tls-req-privacy">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        Your information is private and will only be used to notify you about your request.
                    </p>
                    <button class="tls-req-submit" id="tls-req-submit" type="button">
                        <span class="tls-req-submit-text">Submit Request</span>
                        <span class="tls-req-submit-loading" style="display:none;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" class="tls-spin"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                            Sending…
                        </span>
                    </button>
                </div>

            </div><!-- /.tls-req-fields -->
        </div><!-- /#tls-req-form-wrap -->

    </div><!-- /.tls-req-wrap -->

    <script>
    (function(){
        var form   = document.getElementById('tls-req-form-wrap');
        var btn    = document.getElementById('tls-req-submit');
        var err    = document.getElementById('tls-req-error');
        var succ   = document.getElementById('tls-req-success');
        var formTime = Math.floor(Date.now()/1000);

        if (!btn) return;
        btn.addEventListener('click', function(){
            // Collect
            var name    = document.getElementById('tls-name').value.trim();
            var email   = document.getElementById('tls-email').value.trim();
            var book    = document.getElementById('tls-book').value.trim();
            var message = document.getElementById('tls-message').value.trim();
            var hp      = document.querySelector('[name="tls_hp"]').value;

            // Client-side validation
            err.style.display = 'none';
            if (!name || !email || !book) {
                err.textContent = 'Please fill in all required fields.';
                err.style.display = 'block';
                return;
            }

            // Loading state
            btn.disabled = true;
            btn.querySelector('.tls-req-submit-text').style.display    = 'none';
            btn.querySelector('.tls-req-submit-loading').style.display  = 'inline-flex';

            var data = new FormData();
            data.append('action',      'tls_submit_request');
            data.append('nonce',       '<?php echo esc_js($nonce); ?>');
            data.append('tls_name',    name);
            data.append('tls_email',   email);
            data.append('tls_book',    book);
            data.append('tls_message', message);
            data.append('tls_hp',      hp);
            data.append('tls_time',    formTime);

            fetch('<?php echo esc_js(admin_url("admin-ajax.php")); ?>', {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success) {
                    form.style.opacity = '0';
                    form.style.transition = 'opacity 0.3s';
                    setTimeout(function(){
                        form.style.display = 'none';
                        succ.style.display = 'flex';
                        succ.style.opacity = '0';
                        succ.style.transition = 'opacity 0.4s';
                        requestAnimationFrame(function(){
                            succ.style.opacity = '1';
                        });
                    }, 300);
                } else {
                    err.textContent = res.data.message || 'Something went wrong.';
                    err.style.display = 'block';
                    btn.disabled = false;
                    btn.querySelector('.tls-req-submit-text').style.display   = 'inline';
                    btn.querySelector('.tls-req-submit-loading').style.display = 'none';
                }
            })
            .catch(function(){
                err.textContent = 'Network error. Please try again.';
                err.style.display = 'block';
                btn.disabled = false;
                btn.querySelector('.tls-req-submit-text').style.display   = 'inline';
                btn.querySelector('.tls-req-submit-loading').style.display = 'none';
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
