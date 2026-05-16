<?php
/**
 * The Telos — Reading Lists / Reading Paths
 *
 * CPT: tls_reading_list
 * - Admin meta boxes for books, subtitle, era, color, emoji
 * - tls_get_reading_lists(), tls_get_lists_for_post()
 * - AJAX: add/remove from library, get library, AI path suggest
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════
   1. CUSTOM POST TYPE
══════════════════════════════════════════════════ */
add_action( 'init', function() {
    if ( post_type_exists( 'tls_reading_list' ) ) return;
    register_post_type( 'tls_reading_list', [
        'labels'        => [
            'name'          => 'Reading Paths',
            'singular_name' => 'Reading Path',
            'menu_name'     => '📚 Reading Paths',
            'add_new_item'  => 'Add New Path',
            'edit_item'     => 'Edit Path',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'show_in_rest'  => false,
        'menu_icon'     => 'dashicons-book-alt',
        'menu_position' => 6,
        'supports'      => [ 'title', 'excerpt', 'page-attributes' ],
    ] );
} );

/* ══════════════════════════════════════════════════
   2. ADMIN META BOXES
══════════════════════════════════════════════════ */
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'tls_rl_books', 'Books in this Path',
        'tls_rl_books_cb', 'tls_reading_list', 'normal', 'high'
    );
    add_meta_box(
        'tls_rl_details', 'Path Details',
        'tls_rl_details_cb', 'tls_reading_list', 'side', 'high'
    );
} );

function tls_rl_books_cb( $post ) {
    wp_nonce_field( 'tls_rl_save', 'tls_rl_nonce' );
    $books = get_post_meta( $post->ID, '_tls_rl_books', true );
    if ( ! is_array( $books ) ) $books = [];
    ?>
    <div id="tls-rl-book-list" style="margin-bottom:12px;">
        <?php foreach ( $books as $i => $bid ) :
            if ( ! $p = get_post( $bid ) ) continue;
            $aths = get_the_terms( $bid, 'authors' );
            $auth = ( $aths && ! is_wp_error($aths) ) ? $aths[0]->name : '';
        ?>
        <div class="tls-rl-book-row" style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f0f0f0;">
            <span style="color:#aaa;font-size:11px;min-width:24px;"><?php echo ($i+1); ?></span>
            <strong style="flex:1;font-size:13px;"><?php echo esc_html($p->post_title); ?></strong>
            <?php if ($auth) echo '<span style="color:#999;font-size:12px;">' . esc_html($auth) . '</span>'; ?>
            <input type="hidden" name="tls_rl_books[]" value="<?php echo (int)$bid; ?>">
            <button type="button" class="tls-rl-remove-book button-link" style="color:#a00;font-size:11px;">✕</button>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="position:relative;margin-top:8px;">
        <input type="text" id="tls-rl-book-search" placeholder="Search books to add…"
               autocomplete="off"
               style="width:100%;padding:6px 8px;border:1px solid #8c8f94;border-radius:3px;font-size:13px;">
        <ul id="tls-rl-results" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #8c8f94;border-top:none;max-height:220px;overflow-y:auto;margin:0;padding:0;list-style:none;z-index:9999;box-shadow:0 4px 10px rgba(0,0,0,.12);"></ul>
    </div>

    <script>
    (function(){
        var list   = document.getElementById('tls-rl-book-list');
        var inp    = document.getElementById('tls-rl-book-search');
        var drop   = document.getElementById('tls-rl-results');
        var timer  = null, xhr = null;

        inp.addEventListener('input', function(){
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 2) { drop.style.display='none'; return; }
            timer = setTimeout(function(){ search(q); }, 280);
        });

        function search(q){
            if (xhr) xhr.abort();
            drop.innerHTML = '<li style="padding:8px 12px;color:#aaa;font-size:12px;">Searching…</li>';
            drop.style.display = 'block';
            xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
            xhr.onload = function(){
                if (this.status===200) {
                    try { render(JSON.parse(this.responseText)); }
                    catch(e){ drop.innerHTML='<li style="padding:8px 12px;color:#aaa;">Error</li>'; }
                }
            };
            xhr.send('action=thetelos_search_books&q='+encodeURIComponent(q)+'&nonce=<?php echo wp_create_nonce("thetelos_book_search"); ?>');
        }

        function render(books){
            if (!books||!books.length){ drop.innerHTML='<li style="padding:8px 12px;color:#aaa;font-size:12px;">No books found.</li>'; return; }
            drop.innerHTML='';
            books.forEach(function(b){
                var li=document.createElement('li');
                li.style.cssText='padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #f0f0f0;';
                li.innerHTML='<strong>'+esc(b.title)+'</strong>'+(b.author?'<span style="color:#999;font-size:12px;"> — '+esc(b.author)+'</span>':'');
                li.onmouseenter=function(){this.style.background='#f0f7f4';};
                li.onmouseleave=function(){this.style.background='';};
                li.addEventListener('mousedown',function(e){ e.preventDefault(); addBook(b); });
                drop.appendChild(li);
            });
        }

        function addBook(b){
            // Duplicate check
            var existing = list.querySelectorAll('input[name="tls_rl_books[]"]');
            for (var i=0;i<existing.length;i++) { if (existing[i].value == b.id) { drop.style.display='none'; inp.value=''; return; } }
            var count = existing.length + 1;
            var div = document.createElement('div');
            div.className = 'tls-rl-book-row';
            div.style.cssText = 'display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f0f0f0;';
            div.innerHTML = '<span style="color:#aaa;font-size:11px;min-width:24px;">'+count+'</span>'
                +'<strong style="flex:1;font-size:13px;">'+esc(b.title)+'</strong>'
                +(b.author?'<span style="color:#999;font-size:12px;">'+esc(b.author)+'</span>':'')
                +'<input type="hidden" name="tls_rl_books[]" value="'+b.id+'">'
                +'<button type="button" class="tls-rl-remove-book button-link" style="color:#a00;font-size:11px;">✕</button>';
            list.appendChild(div);
            inp.value = '';
            drop.style.display = 'none';
        }

        list.addEventListener('click', function(e){
            var btn = e.target.closest('.tls-rl-remove-book');
            if (btn) btn.closest('.tls-rl-book-row').remove();
        });

        document.addEventListener('click', function(e){ if (e.target !== inp) drop.style.display='none'; });
        function esc(s){ var d=document.createElement('div');d.textContent=s;return d.innerHTML; }
    })();
    </script>
    <?php
}

function tls_rl_details_cb( $post ) {
    $subtitle = get_post_meta( $post->ID, '_tls_rl_subtitle', true );
    $emoji    = get_post_meta( $post->ID, '_tls_rl_emoji',    true );
    $era      = get_post_meta( $post->ID, '_tls_rl_era',      true );
    $color    = get_post_meta( $post->ID, '_tls_rl_color',    true ) ?: '#1A3A2B';
    $time     = get_post_meta( $post->ID, '_tls_rl_time',     true );
    ?>
    <table style="width:100%;border-collapse:collapse;">
        <tr><td style="padding:6px 0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#555;">Subtitle</td></tr>
        <tr><td style="padding-bottom:10px;"><input type="text" name="tls_rl_subtitle" value="<?php echo esc_attr($subtitle); ?>" style="width:100%;font-size:13px;" placeholder="e.g. From Logic to Living Nature"></td></tr>

        <tr><td style="padding:6px 0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#555;">Emoji Icon</td></tr>
        <tr><td style="padding-bottom:10px;"><input type="text" name="tls_rl_emoji" value="<?php echo esc_attr($emoji); ?>" style="width:60px;font-size:18px;text-align:center;" placeholder="📚"></td></tr>

        <tr><td style="padding:6px 0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#555;">Era / Period</td></tr>
        <tr><td style="padding-bottom:10px;"><input type="text" name="tls_rl_era" value="<?php echo esc_attr($era); ?>" style="width:100%;font-size:13px;" placeholder="e.g. CLASSICAL GREECE"></td></tr>

        <tr><td style="padding:6px 0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#555;">Cover Color</td></tr>
        <tr><td style="padding-bottom:10px;display:flex;align-items:center;gap:8px;">
            <input type="color" name="tls_rl_color" value="<?php echo esc_attr($color); ?>" style="height:32px;width:48px;border:1px solid #ddd;border-radius:3px;cursor:pointer;">
            <input type="text" name="tls_rl_color_text" value="<?php echo esc_attr($color); ?>" style="width:90px;font-size:12px;font-family:monospace;" placeholder="#1A3A2B">
        </td></tr>

        <tr><td style="padding:6px 0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#555;">Est. Reading Time</td></tr>
        <tr><td><input type="text" name="tls_rl_time" value="<?php echo esc_attr($time); ?>" style="width:100%;font-size:13px;" placeholder="e.g. ~34H"></td></tr>
    </table>
    <script>
    document.querySelector('[name="tls_rl_color"]').addEventListener('input',function(){
        document.querySelector('[name="tls_rl_color_text"]').value = this.value;
    });
    </script>
    <?php
}

add_action( 'save_post_tls_reading_list', function( $post_id ) {
    if ( ! isset( $_POST['tls_rl_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['tls_rl_nonce'], 'tls_rl_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $books = isset( $_POST['tls_rl_books'] ) ? array_map( 'intval', (array) $_POST['tls_rl_books'] ) : [];
    update_post_meta( $post_id, '_tls_rl_books', $books );

    $fields = [ 'subtitle', 'emoji', 'era', 'color', 'time' ];
    foreach ( $fields as $f ) {
        if ( isset( $_POST[ 'tls_rl_' . $f ] ) ) {
            update_post_meta( $post_id, '_tls_rl_' . $f, sanitize_text_field( wp_unslash( $_POST[ 'tls_rl_' . $f ] ) ) );
        }
    }
} );

/* ══════════════════════════════════════════════════
   3. DATA FUNCTIONS
══════════════════════════════════════════════════ */
function tls_get_reading_lists( int $limit = 0 ): array {
    $query = new WP_Query( [
        'post_type'      => 'tls_reading_list',
        'post_status'    => 'publish',
        'posts_per_page' => $limit ?: -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ] );
    $lists = [];
    foreach ( $query->posts as $post ) {
        $books_raw = get_post_meta( $post->ID, '_tls_rl_books', true );
        $books     = is_array( $books_raw ) ? array_values( array_filter( array_map( 'intval', $books_raw ) ) ) : [];
        $color     = get_post_meta( $post->ID, '_tls_rl_color', true ) ?: '#1A3A2B';
        $time_meta = get_post_meta( $post->ID, '_tls_rl_time', true );

        // Auto-calculate time if not set
        if ( ! $time_meta && ! empty( $books ) ) {
            $total_words = 0;
            foreach ( $books as $bid ) {
                $p = get_post( $bid );
                if ( $p ) $total_words += str_word_count( strip_tags( $p->post_content ) );
            }
            $hrs = round( $total_words / 17700, 1 ); // ~295wpm * 60 = 17700 words/hr
            $time_meta = $hrs >= 1 ? '~' . $hrs . 'H' : '< 1H';
        }

        $lists[] = [
            'id'       => $post->ID,
            'title'    => $post->post_title,
            'subtitle' => get_post_meta( $post->ID, '_tls_rl_subtitle', true ),
            'emoji'    => get_post_meta( $post->ID, '_tls_rl_emoji',    true ) ?: '📚',
            'desc'     => $post->post_excerpt ?: get_post_meta( $post->ID, '_tls_rl_desc', true ),
            'era'      => get_post_meta( $post->ID, '_tls_rl_era',      true ),
            'color'    => $color,
            'time'     => $time_meta,
            'books'    => $books,
        ];
    }
    wp_reset_postdata();
    return $lists;
}

function tls_get_lists_for_post( int $post_id ): array {
    $lists  = tls_get_reading_lists();
    $result = [];
    foreach ( $lists as $l ) {
        if ( in_array( $post_id, $l['books'], true ) ) {
            $result[] = [
                'id'    => $l['id'],
                'title' => $l['title'],
                'emoji' => $l['emoji'],
                'count' => count( $l['books'] ),
            ];
        }
    }
    return $result;
}

/* ══════════════════════════════════════════════════
   4. AJAX — Add list to user library
══════════════════════════════════════════════════ */
add_action( 'wp_ajax_tls_add_list_to_library', 'tls_handle_add_list_to_library' );

function tls_handle_add_list_to_library() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Login required.' ], 401 );
    }
    $list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;
    if ( ! $list_id || get_post_type( $list_id ) !== 'tls_reading_list' ) {
        wp_send_json_error( [ 'message' => 'Invalid list.' ], 400 );
    }
    $uid      = get_current_user_id();
    $saved    = get_user_meta( $uid, '_tls_saved_lists', true );
    if ( ! is_array( $saved ) ) $saved = [];

    if ( in_array( $list_id, $saved, true ) ) {
        // Toggle: remove
        $saved = array_values( array_diff( $saved, [ $list_id ] ) );
        update_user_meta( $uid, '_tls_saved_lists', $saved );
        wp_send_json_success( [ 'action' => 'removed' ] );
    } else {
        $saved[] = $list_id;
        update_user_meta( $uid, '_tls_saved_lists', $saved );
        wp_send_json_success( [ 'action' => 'added' ] );
    }
}

/* ══════════════════════════════════════════════════
   5. AJAX — Get my saved lists (profile page)
══════════════════════════════════════════════════ */
add_action( 'wp_ajax_tls_get_my_lists', 'tls_handle_get_my_lists' );

function tls_handle_get_my_lists() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_success( [ 'lists' => [] ] );
    }
    $uid   = get_current_user_id();
    $saved = get_user_meta( $uid, '_tls_saved_lists', true );
    if ( ! is_array( $saved ) || empty( $saved ) ) {
        wp_send_json_success( [ 'lists' => [] ] );
    }

    $all   = tls_get_reading_lists();
    $lists = [];
    foreach ( $all as $l ) {
        if ( ! in_array( $l['id'], $saved, true ) ) continue;
        $book_details = [];
        foreach ( array_slice( $l['books'], 0, 6 ) as $bid ) {
            if ( ! $p = get_post( $bid ) ) continue;
            $aths = get_the_terms( $bid, 'authors' );
            $auth = ( $aths && ! is_wp_error($aths) ) ? $aths[0]->name : '';
            $cats = get_the_category( $bid );
            $col  = ( function_exists('thetelos_get_category_palette') && ! empty($cats) )
                ? thetelos_get_category_palette( $cats[0]->slug )[0] : '#1C2B3A';
            $book_details[] = [
                'id'     => $bid,
                'title'  => $p->post_title,
                'author' => $auth,
                'cover'  => get_the_post_thumbnail_url( $bid, 'thumbnail' ) ?: '',
                'color'  => $col,
                'url'    => get_permalink( $bid ),
            ];
        }
        $lists[] = [
            'id'           => $l['id'],
            'title'        => $l['title'],
            'emoji'        => $l['emoji'],
            'desc'         => $l['desc'],
            'count'        => count( $l['books'] ),
            'book_details' => $book_details,
        ];
    }
    wp_send_json_success( [ 'lists' => $lists ] );
}

/* ══════════════════════════════════════════════════
   6. AJAX — Get library (for profile page)
══════════════════════════════════════════════════ */
add_action( 'wp_ajax_tls_get_library', 'tls_handle_get_library' );

function tls_handle_get_library() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_success( [ 'books' => [] ] );
    }
    $uid    = get_current_user_id();
    $filter = sanitize_text_field( $_POST['filter'] ?? 'all' );

    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT meta_key, meta_value FROM {$wpdb->usermeta}
         WHERE user_id=%d AND meta_key LIKE '_tls_reading_status_%%'",
        $uid
    ) );

    $books = [];
    foreach ( $rows as $row ) {
        $status = $row->meta_value;
        if ( $filter !== 'all' && $status !== $filter ) continue;
        $bid = (int) str_replace( '_tls_reading_status_', '', $row->meta_key );
        if ( ! $bid || ! get_post( $bid ) ) continue;
        $aths = get_the_terms( $bid, 'authors' );
        $auth = ( $aths && ! is_wp_error($aths) ) ? $aths[0]->name : '';
        $cats = get_the_category( $bid );
        $col  = ( function_exists('thetelos_get_category_palette') && ! empty($cats) )
            ? thetelos_get_category_palette( $cats[0]->slug )[0] : '#1C2B3A';
        $books[] = [
            'id'     => $bid,
            'title'  => get_the_title( $bid ),
            'author' => $auth,
            'cover'  => get_the_post_thumbnail_url( $bid, 'thumbnail' ) ?: '',
            'color'  => $col,
            'url'    => get_permalink( $bid ),
            'status' => $status,
        ];
    }
    wp_send_json_success( [ 'books' => $books ] );
}

/* ══════════════════════════════════════════════════
   7. AJAX — Set reading status
══════════════════════════════════════════════════ */
add_action( 'wp_ajax_tls_set_reading_status', 'tls_handle_set_reading_status' );

function tls_handle_set_reading_status() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Login required.' ], 401 );
    }
    if ( ! check_ajax_referer( 'tls_reading_status', 'nonce', false ) ) {
        wp_send_json_error( 'Invalid nonce', 403 );
    }
    $post_id = absint( $_POST['post_id'] ?? 0 );
    $status  = sanitize_text_field( $_POST['status'] ?? '' );
    if ( ! $post_id || ! in_array( $status, [ 'want', 'reading', 'read', '' ], true ) ) {
        wp_send_json_error( 'Invalid params', 400 );
    }
    $uid = get_current_user_id();
    $key = '_tls_reading_status_' . $post_id;
    if ( $status ) {
        $current = get_user_meta( $uid, $key, true );
        if ( $current === $status ) {
            delete_user_meta( $uid, $key );
            wp_send_json_success( [ 'status' => '' ] );
        } else {
            update_user_meta( $uid, $key, $status );
            wp_send_json_success( [ 'status' => $status ] );
        }
    } else {
        delete_user_meta( $uid, $key );
        wp_send_json_success( [ 'status' => '' ] );
    }
}

/* ══════════════════════════════════════════════════
   8. AJAX — AI / Smart Path Suggestions
══════════════════════════════════════════════════ */
add_action( 'wp_ajax_tls_ai_suggest_path',        'tls_handle_ai_suggest' );
add_action( 'wp_ajax_nopriv_tls_ai_suggest_path', 'tls_handle_ai_suggest' );

function tls_handle_ai_suggest() {
    if ( ! check_ajax_referer( 'tls_ai_suggest', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
    }

    $topic = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) );
    if ( mb_strlen( $topic ) < 2 ) {
        wp_send_json_error( [ 'message' => 'Please enter a topic.' ] );
    }

    // Rate limiting
    $ip      = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
    $rl_key  = 'tls_aip_' . md5( $ip . ($topic[0] ?? '') );
    $rl_hits = (int) get_transient( $rl_key );
    if ( $rl_hits >= 10 ) {
        wp_send_json_error( [ 'message' => 'Too many requests. Please try again later.' ] );
    }
    set_transient( $rl_key, $rl_hits + 1, 15 * MINUTE_IN_SECONDS );

    // ── Stage 1: WordPress full-text search ──────────────────
    $posts = get_posts( [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 16,
        's'              => $topic,
        'orderby'        => 'relevance',
        'no_found_rows'  => true,
    ] );

    // ── Stage 2: Category/tag enrichment ──────────────────
    if ( count( $posts ) < 4 ) {
        $keywords = array_filter( array_map( 'trim', explode( ' ', strtolower( $topic ) ) ), fn($w) => strlen($w) > 3 );
        $cat_ids  = [];
        foreach ( $keywords as $kw ) {
            $cats = get_categories( [ 'name__like' => $kw, 'hide_empty' => true, 'number' => 3 ] );
            foreach ( $cats as $c ) $cat_ids[] = $c->term_id;
        }
        if ( $cat_ids ) {
            $extra = get_posts( [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 8,
                'tax_query'      => [ [ 'taxonomy' => 'category', 'field' => 'id', 'terms' => array_unique($cat_ids) ] ],
                'no_found_rows'  => true,
            ] );
            $exist = wp_list_pluck( $posts, 'ID' );
            foreach ( $extra as $ep ) {
                if ( ! in_array( $ep->ID, $exist, true ) ) $posts[] = $ep;
            }
        }
    }

    // ── Stage 3: Author taxonomy enrichment ───────────────
    if ( count( $posts ) < 4 ) {
        $ath_terms = get_terms( [ 'taxonomy' => 'authors', 'name__like' => $topic, 'hide_empty' => true, 'number' => 5 ] );
        if ( ! is_wp_error( $ath_terms ) && ! empty( $ath_terms ) ) {
            $extra = get_posts( [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 8,
                'tax_query'      => [ [ 'taxonomy' => 'authors', 'field' => 'id', 'terms' => wp_list_pluck($ath_terms,'term_id') ] ],
                'no_found_rows'  => true,
            ] );
            $exist = wp_list_pluck( $posts, 'ID' );
            foreach ( $extra as $ep ) {
                if ( ! in_array( $ep->ID, $exist, true ) ) $posts[] = $ep;
            }
        }
    }

    $posts = array_slice( $posts, 0, 8 );

    // ── Format results ────────────────────────────────────
    $books = [];
    foreach ( $posts as $p ) {
        $aths = get_the_terms( $p->ID, 'authors' );
        $auth = ( $aths && ! is_wp_error($aths) ) ? $aths[0]->name : '';
        $cats = get_the_category( $p->ID );
        $col  = ( function_exists('thetelos_get_category_palette') && ! empty($cats) )
            ? thetelos_get_category_palette( $cats[0]->slug )[0] : '#1C2B3A';
        $books[] = [
            'id'      => $p->ID,
            'title'   => $p->post_title,
            'author'  => $auth,
            'excerpt' => wp_trim_words( $p->post_excerpt ?: strip_tags($p->post_content), 18 ),
            'cover'   => get_the_post_thumbnail_url( $p->ID, 'medium' ) ?: '',
            'color'   => $col,
            'url'     => get_permalink( $p->ID ),
            'cat'     => ! empty($cats) ? $cats[0]->name : '',
            'time'    => function_exists('thetelos_post_reading_time') ? thetelos_post_reading_time($p->ID) : '',
        ];
    }

    // ── Optional: Claude API enrichment ───────────────────
    $api_key = defined('TLS_CLAUDE_KEY') ? TLS_CLAUDE_KEY : get_option('tls_claude_api_key','');
    $ai_intro = '';
    if ( $api_key && ! empty($books) ) {
        $book_titles = implode(', ', array_map(fn($b) => '"'.$b['title'].'"', array_slice($books,0,5)));
        $prompt = "The reader wants to explore: \"{$topic}\". From our archive we found these books: {$book_titles}. In 1-2 sentences, explain why this collection is relevant to \"{$topic}\" and how to approach it. Be intellectual but concise.";
        $resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 12,
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 150,
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
            ] ),
        ] );
        if ( ! is_wp_error($resp) && 200 === wp_remote_retrieve_response_code($resp) ) {
            $data = json_decode( wp_remote_retrieve_body($resp), true );
            $ai_intro = $data['content'][0]['text'] ?? '';
        }
    }

    wp_send_json_success( [
        'topic'    => $topic,
        'books'    => $books,
        'ai_intro' => $ai_intro,
        'count'    => count($books),
    ] );
}
