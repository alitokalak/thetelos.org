<?php
/**
 * The Telos — Reading Lists
 * AI destekli kuratoryal okuma listeleri
 *
 * CPT: tls_reading_list
 * Admin'de AI ile otomatik üretim
 * Anasayfa + Profil + Post sayfasında gösterim
 */
if ( ! defined('ABSPATH') ) exit;

/* ══════════════════════════════════════════════
   1. CUSTOM POST TYPE
══════════════════════════════════════════════ */
add_action('init', function() {
    register_post_type('tls_reading_list', [
        'labels' => [
            'name'          => 'Reading Lists',
            'singular_name' => 'Reading List',
            'menu_name'     => '📚 Reading Lists',
            'add_new'       => 'Add New List',
            'add_new_item'  => 'Add New Reading List',
            'edit_item'     => 'Edit Reading List',
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-book-alt',
        'supports'     => ['title'],
        'menu_position'=> 6,
    ]);
});

/* ══════════════════════════════════════════════
   2. META BOXES (description + book list)
══════════════════════════════════════════════ */
add_action('add_meta_boxes', function() {
    add_meta_box('tls_rl_details', 'Reading List Details',
        'tls_rl_meta_box', 'tls_reading_list', 'normal', 'high');
});

function tls_rl_meta_box($post) {
    wp_nonce_field('tls_rl_save', 'tls_rl_nonce');
    $desc  = get_post_meta($post->ID, '_tls_rl_desc',  true) ?: '';
    $books = get_post_meta($post->ID, '_tls_rl_books', true) ?: [];
    $emoji = get_post_meta($post->ID, '_tls_rl_emoji', true) ?: '📖';
    ?>
    <p style="margin-bottom:4px;">
        <label style="font-weight:600;">Emoji</label><br>
        <input type="text" name="tls_rl_emoji" value="<?php echo esc_attr($emoji); ?>"
               style="width:60px;font-size:20px;" maxlength="2">
    </p>
    <p style="margin-top:14px;margin-bottom:4px;">
        <label style="font-weight:600;">Description</label><br>
        <textarea name="tls_rl_desc" rows="3"
                  style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;font-size:14px;"><?php echo esc_textarea($desc); ?></textarea>
    </p>
    <p style="margin-top:14px;margin-bottom:8px;font-weight:600;">Books (in order)</p>
    <div id="tls-rl-books">
    <?php foreach ($books as $i => $pid) :
        $title = get_the_title($pid);
        if (!$title) continue;
    ?>
    <div class="tls-rl-book-row" style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
        <span style="color:#aaa;font-size:12px;min-width:20px;"><?php echo $i+1; ?>.</span>
        <input type="hidden" name="tls_rl_books[]" value="<?php echo (int)$pid; ?>">
        <span style="flex:1;font-size:13px;"><?php echo esc_html($title); ?></span>
        <button type="button" onclick="this.closest('.tls-rl-book-row').remove()"
                style="background:none;border:none;color:#b91c1c;cursor:pointer;font-size:16px;">×</button>
    </div>
    <?php endforeach; ?>
    </div>
    <p style="margin-top:8px;">
        <label style="font-size:12px;color:#666;">Add post by ID:</label><br>
        <input type="number" id="tls-rl-add-id" style="width:120px;padding:6px;border:1px solid #ddd;border-radius:6px;">
        <button type="button" id="tls-rl-add-btn" class="button">Add</button>
    </p>
    <script>
    document.getElementById('tls-rl-add-btn').addEventListener('click', function(){
        var pid = document.getElementById('tls-rl-add-id').value.trim();
        if (!pid) return;
        var row = document.createElement('div');
        row.className = 'tls-rl-book-row';
        row.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:6px;';
        row.innerHTML = '<span style="color:#aaa;font-size:12px;min-width:20px;">•</span>'
            + '<input type="hidden" name="tls_rl_books[]" value="'+pid+'">'
            + '<span style="flex:1;font-size:13px;">Post ID: '+pid+'</span>'
            + '<button type="button" onclick="this.closest(\'.tls-rl-book-row\').remove()" style="background:none;border:none;color:#b91c1c;cursor:pointer;font-size:16px;">×</button>';
        document.getElementById('tls-rl-books').appendChild(row);
        document.getElementById('tls-rl-add-id').value = '';
    });
    </script>
    <?php
}

add_action('save_post_tls_reading_list', function($post_id) {
    if (!isset($_POST['tls_rl_nonce'])) return;
    if (!wp_verify_nonce($_POST['tls_rl_nonce'], 'tls_rl_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    update_post_meta($post_id, '_tls_rl_desc',  sanitize_textarea_field($_POST['tls_rl_desc']  ?? ''));
    update_post_meta($post_id, '_tls_rl_emoji', sanitize_text_field($_POST['tls_rl_emoji'] ?? '📖'));

    $books = array_map('intval', (array)($_POST['tls_rl_books'] ?? []));
    $books = array_filter($books);
    $books = array_values(array_unique($books));
    update_post_meta($post_id, '_tls_rl_books', $books);
});

/* ══════════════════════════════════════════════
   3. AI ÜRETİM — Admin panelde buton
══════════════════════════════════════════════ */
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=tls_reading_list',
        'Generate with AI',
        '🤖 Generate with AI',
        'manage_options',
        'tls-generate-lists',
        'tls_generate_lists_page'
    );
});

function tls_generate_lists_page() {
    $key = function_exists('tls_get_claude_key') ? tls_get_claude_key() : '';
    ?>
    <div class="wrap">
        <h1>🤖 Generate Reading Lists with AI</h1>
        <p style="color:#666;max-width:600px;">
            Claude will analyze the archive and generate curated reading paths based on themes,
            authors, and chronology. Existing lists will not be affected.
        </p>
        <?php if (!$key) : ?>
            <div class="notice notice-warning"><p>
                ⚠ No API key. Go to <a href="<?php echo admin_url('options-general.php?page=thetelos-ai'); ?>">Settings → Thetelos AI</a>.
            </p></div>
        <?php else : ?>
        <button id="tls-gen-lists-btn" class="button button-primary button-large">
            ✨ Generate Reading Lists
        </button>
        <div id="tls-gen-status" style="margin-top:16px;font-size:14px;"></div>
        <div id="tls-gen-preview" style="margin-top:20px;display:none;"></div>
        <script>
        document.getElementById('tls-gen-lists-btn').addEventListener('click', function(){
            var btn = this;
            btn.disabled = true; btn.textContent = '⏳ Generating…';
            document.getElementById('tls-gen-status').textContent = 'Asking Claude to analyze the archive…';

            fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                body: new URLSearchParams({
                    action: 'tls_ai_generate_lists',
                    nonce:  '<?php echo wp_create_nonce("tls_ai_nonce"); ?>'
                })
            })
            .then(function(r){ return r.json(); })
            .then(function(res){
                btn.disabled = false; btn.textContent = '✨ Generate Reading Lists';
                if (res.success) {
                    document.getElementById('tls-gen-status').innerHTML =
                        '✅ <strong>'+res.data.count+'</strong> reading lists created! '
                        +'<a href="<?php echo admin_url("edit.php?post_type=tls_reading_list"); ?>">View all →</a>';
                } else {
                    document.getElementById('tls-gen-status').innerHTML =
                        '<span style="color:#b91c1c;">⚠ ' + (res.data || 'Failed.') + '</span>';
                }
            })
            .catch(function(){
                btn.disabled = false; btn.textContent = '✨ Generate Reading Lists';
                document.getElementById('tls-gen-status').textContent = 'Network error.';
            });
        });
        </script>
        <?php endif; ?>
    </div>
    <?php
}

/* ══════════════════════════════════════════════
   4. AI AJAX HANDLER — Listeleri üret ve kaydet
══════════════════════════════════════════════ */
add_action('wp_ajax_tls_ai_generate_lists', function() {
    if (!check_ajax_referer('tls_ai_nonce', 'nonce', false) || !current_user_can('manage_options'))
        wp_send_json_error('Unauthorized');

    // Arşivdeki tüm postları çek
    $posts = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 150,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    $archive = [];
    foreach ($posts as $pid) {
        $cats    = get_the_category($pid);
        $authors = get_the_terms($pid, 'authors');
        $archive[] = [
            'id'     => $pid,
            'title'  => get_the_title($pid),
            'cat'    => !empty($cats) ? $cats[0]->name : '',
            'author' => (!empty($authors) && !is_wp_error($authors)) ? $authors[0]->name : '',
        ];
    }

    $archive_json = json_encode($archive, JSON_UNESCAPED_UNICODE);
    if (strlen($archive_json) > 12000) {
        // Çok uzunsa kısalt
        $archive_json = json_encode(array_slice($archive, 0, 80), JSON_UNESCAPED_UNICODE);
    }

    $system = 'You are a philosophy scholar and curator for thetelos.org.
Create exactly 6 curated reading lists based on the provided archive.

CRITICAL: Respond with ONLY a JSON array. No explanation, no markdown, no code blocks, no extra text before or after.
Start your response with [ and end with ]

Format:
[{"title":"List Title","emoji":"📖","description":"2-sentence description","book_ids":[123,456,789]},...]

Rules:
- Use only IDs from the archive
- 4-8 books per list in logical reading order
- Diverse themes: chronological, by school, by difficulty, thematic
- Compelling specific titles';

    $user_msg = "Archive (id, title, category, author):\n" . $archive_json . "\n\nRespond with ONLY the JSON array of 6 reading lists. Start with [";

    $raw = tls_call_claude($system, $user_msg);

    if (!$raw) wp_send_json_error('API call failed.');

    // Temizle
    $cleaned = trim($raw);
    $cleaned = preg_replace('/^```json?\s*/i', '', $cleaned);
    $cleaned = preg_replace('/```\s*$/', '', $cleaned);
    $cleaned = trim($cleaned);

    // [ ile başlayan kısmı bul
    $start = strpos($cleaned, '[');
    $end   = strrpos($cleaned, ']');
    if ($start !== false && $end !== false && $end > $start) {
        $cleaned = substr($cleaned, $start, $end - $start + 1);
    }

    $lists = json_decode($cleaned, true);
    if (!is_array($lists)) {
        wp_send_json_error('Could not parse AI response. Raw: ' . substr($cleaned, 0, 200));
    }

    $created = 0;
    foreach ($lists as $list) {
        if (empty($list['title']) || empty($list['book_ids'])) continue;

        $pid = wp_insert_post([
            'post_title'  => sanitize_text_field($list['title']),
            'post_status' => 'publish',
            'post_type'   => 'tls_reading_list',
        ]);
        if (is_wp_error($pid)) continue;

        update_post_meta($pid, '_tls_rl_desc',  sanitize_textarea_field($list['description'] ?? ''));
        update_post_meta($pid, '_tls_rl_emoji', sanitize_text_field($list['emoji'] ?? '📖'));
        $book_ids = array_map('intval', (array)$list['book_ids']);
        $book_ids = array_filter($book_ids);
        update_post_meta($pid, '_tls_rl_books', array_values($book_ids));
        $created++;
    }

    wp_send_json_success(['count' => $created]);
});

/* ══════════════════════════════════════════════
   5. HELPER — listeleri çek
══════════════════════════════════════════════ */
function tls_get_reading_lists(int $limit = 6): array {
    $lists = get_posts([
        'post_type'        => 'tls_reading_list',
        'post_status'      => 'publish',
        'posts_per_page'   => $limit,
        'no_found_rows'    => true,
        'suppress_filters' => false,
    ]);
    $result = [];
    foreach ($lists as $list) {
        $result[] = [
            'id'    => $list->ID,
            'title' => $list->post_title,
            'desc'  => get_post_meta($list->ID, '_tls_rl_desc',  true) ?: '',
            'emoji' => get_post_meta($list->ID, '_tls_rl_emoji', true) ?: '📖',
            'books' => (array)(get_post_meta($list->ID, '_tls_rl_books', true) ?: []),
        ];
    }
    return $result;
}

/* Bir post hangi listelerde → post sayfası için */
function tls_get_lists_for_post(int $post_id): array {
    $all_lists = get_posts([
        'post_type'      => 'tls_reading_list',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    $result = [];
    foreach ($all_lists as $lid) {
        $books = (array)(get_post_meta($lid, '_tls_rl_books', true) ?: []);
        if (in_array($post_id, $books)) {
            $result[] = [
                'id'    => $lid,
                'title' => get_the_title($lid),
                'emoji' => get_post_meta($lid, '_tls_rl_emoji', true) ?: '📖',
                'count' => count($books),
            ];
        }
    }
    return $result;
}

/* ══════════════════════════════════════════════
   6. AJAX — Kullanıcı listeyi kütüphanesine eklesin
══════════════════════════════════════════════ */
add_action('wp_ajax_tls_add_list_to_library',        'tls_handle_add_list');
add_action('wp_ajax_nopriv_tls_add_list_to_library', 'tls_handle_add_list');

function tls_handle_add_list() {
    if (!is_user_logged_in())
        wp_send_json_error(['message' => 'login_required']);

    $list_id = (int)($_POST['list_id'] ?? 0);
    if (!$list_id) wp_send_json_error(['message' => 'Invalid list.']);

    $uid = get_current_user_id();

    /* Kullanıcının kayıtlı listelerini çek */
    $saved = (array)(get_user_meta($uid, '_tls_saved_reading_lists', true) ?: []);

    if (in_array($list_id, $saved)) {
        /* Zaten eklenmiş — çıkar (toggle) */
        $saved = array_values(array_diff($saved, [$list_id]));
        update_user_meta($uid, '_tls_saved_reading_lists', $saved);
        wp_send_json_success(['action' => 'removed', 'count' => count($saved)]);
    } else {
        /* Ekle */
        $saved[] = $list_id;
        update_user_meta($uid, '_tls_saved_reading_lists', $saved);
        $book_count = count((array)(get_post_meta($list_id, '_tls_rl_books', true) ?: []));
        wp_send_json_success(['action' => 'added', 'books' => $book_count, 'count' => count($saved)]);
    }
}

/* ══════════════════════════════════════════════
   7. NONCE endpoint
══════════════════════════════════════════════ */
add_action('wp_ajax_nopriv_tls_get_lists', 'tls_ajax_get_lists');
add_action('wp_ajax_tls_get_lists',        'tls_ajax_get_lists');
function tls_ajax_get_lists() {
    $lists  = tls_get_reading_lists(12);
    $uid    = get_current_user_id();
    $saved  = $uid ? (array)(get_user_meta($uid, '_tls_saved_reading_lists', true) ?: []) : [];

    /* Her listeye kullanıcının kaydetmiş mi bilgisini ekle */
    foreach ($lists as &$l) {
        $l['saved'] = in_array($l['id'], $saved);
    }
    wp_send_json_success(['lists' => $lists]);
}

/* Kullanıcının kayıtlı listelerini getir */
add_action('wp_ajax_tls_get_my_lists', 'tls_ajax_get_my_lists');
function tls_ajax_get_my_lists() {
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'login_required']);
    $uid   = get_current_user_id();
    $saved = (array)(get_user_meta($uid, '_tls_saved_reading_lists', true) ?: []);
    $result = [];
    foreach ($saved as $lid) {
        $post = get_post($lid);
        if (!$post || $post->post_status !== 'publish') continue;
        $book_ids = (array)(get_post_meta($lid, '_tls_rl_books', true) ?: []);

        /* Enrich each book with display data */
        $book_details = [];
        foreach ($book_ids as $pid) {
            $bp = get_post($pid);
            if (!$bp || $bp->post_status !== 'publish') continue;
            $authors = get_the_terms($pid, 'authors');
            $cats    = get_the_category($pid);
            $palette = (function_exists('thetelos_get_category_palette') && !empty($cats))
                ? thetelos_get_category_palette($cats[0]->slug)
                : ['#1C2B3A', '#C8A165', '#F5EFE6', '#A89070'];
            /* Try featured image first, then generated cover */
            $cover = '';
            if (has_post_thumbnail($pid)) {
                $cover = get_the_post_thumbnail_url($pid, 'thumbnail');
            }
            $book_details[] = [
                'id'         => $pid,
                'title'      => $bp->post_title,
                'author'     => (!empty($authors) && !is_wp_error($authors)) ? $authors[0]->name : '',
                'url'        => get_permalink($pid),
                'cover'      => $cover ?: '',
                'cover_html' => !has_post_thumbnail($pid) && function_exists('thetelos_render_book_cover') ? thetelos_render_book_cover($pid) : '',
                'color'      => $palette[0],
                'border'     => $palette[1],
                'text_color' => $palette[2],
                'sub_color'  => $palette[3],
            ];
        }

        $result[] = [
            'id'           => $lid,
            'title'        => $post->post_title,
            'emoji'        => get_post_meta($lid, '_tls_rl_emoji', true) ?: '📚',
            'desc'         => get_post_meta($lid, '_tls_rl_desc',  true) ?: '',
            'books'        => $book_ids,
            'book_details' => $book_details,
            'count'        => count($book_ids),
        ];
    }
    wp_send_json_success(['lists' => $result]);
}
