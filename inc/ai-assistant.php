<?php
/**
 * The Telos — AI Assistant
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── API Key ── */
function tls_get_claude_key(): string {
    if ( defined('THETELOS_CLAUDE_KEY') && THETELOS_CLAUDE_KEY ) return THETELOS_CLAUDE_KEY;
    return (string) get_option('tls_claude_api_key', '');
}

/* ── Settings sayfası ── */
add_action( 'admin_menu', function() {
    add_options_page( 'Thetelos AI', '🤖 Thetelos AI', 'manage_options', 'thetelos-ai', 'tls_ai_settings_page' );
} );

function tls_ai_settings_page() {
    if ( isset($_POST['tls_save_key']) && check_admin_referer('tls_ai_settings') ) {
        update_option( 'tls_claude_api_key', sanitize_text_field($_POST['tls_claude_api_key']) );
        echo '<div class="notice notice-success"><p>API key saved.</p></div>';
    }
    $key = get_option('tls_claude_api_key', '');
    ?>
    <div class="wrap">
        <h1>🤖 Thetelos AI Settings</h1>
        <form method="post">
            <?php wp_nonce_field('tls_ai_settings'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="tls_claude_api_key">Claude API Key</label></th>
                    <td>
                        <input type="password" id="tls_claude_api_key" name="tls_claude_api_key"
                               value="<?php echo esc_attr($key); ?>" class="regular-text"
                               placeholder="sk-ant-api03-...">
                        <?php if ($key) echo '<p class="description">Key is set ✓</p>'; ?>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="tls_save_key" class="button button-primary">Save</button>
            </p>
        </form>
    </div>
    <?php
}

/* ── Meta box ── */
add_action( 'add_meta_boxes', function() {
    add_meta_box( 'tls_ai_assistant', '🤖 AI Assistant', 'tls_ai_meta_box', 'post', 'side', 'high' );
} );

function tls_ai_meta_box( $post ) {
    $has_key      = (bool) tls_get_claude_key();
    $nonce        = wp_create_nonce('tls_ai_nonce');
    $disable      = get_post_meta( $post->ID, '_tls_disable_quotes', true ) === '1';
    wp_nonce_field( 'tls_dq_save', 'tls_dq_nonce' );
    ?>
    <div style="font-family:-apple-system,sans-serif;">

        <?php if ( ! $has_key ) : ?>
        <p style="font-size:12px;color:#b91c1c;background:#fff5f5;padding:8px 10px;border-radius:6px;border:1px solid #fca5a5;margin:0 0 12px;">
            ⚠️ No API key. <a href="<?php echo admin_url('options-general.php?page=thetelos-ai'); ?>">Settings → Thetelos AI</a>
        </p>
        <?php endif; ?>

        <!-- Quote Extractor -->
        <div style="margin-bottom:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <strong style="font-size:12px;">📝 Quote Extractor</strong>
                <label style="display:flex;align-items:center;gap:4px;font-size:11px;color:#666;cursor:pointer;">
                    <input type="checkbox" name="tls_disable_quotes" id="tls-dq-cb" value="1"
                           <?php checked($disable); ?> style="margin:0;">
                    Don't generate
                </label>
            </div>
            <button type="button" id="tls-ai-extract-quotes" class="button button-primary"
                    style="width:100%;"
                    data-post-id="<?php echo (int)$post->ID; ?>"
                    data-nonce="<?php echo esc_attr($nonce); ?>"
                    <?php echo ($disable || !$has_key) ? 'disabled style="opacity:.5"' : ''; ?>>
                ✨ Extract Quotes (2)
            </button>
            <div id="tls-ai-q-status" style="font-size:11px;margin-top:5px;min-height:16px;"></div>
        </div>

        <hr style="border:none;border-top:1px solid #eee;margin:12px 0;">

        <!-- Excerpt -->
        <div style="margin-bottom:16px;">
            <strong style="font-size:12px;display:block;margin-bottom:8px;">✍️ Excerpt Generator</strong>
            <button type="button" id="tls-ai-gen-excerpt" class="button" style="width:100%;"
                    data-post-id="<?php echo (int)$post->ID; ?>"
                    data-nonce="<?php echo esc_attr($nonce); ?>"
                    <?php echo !$has_key ? 'disabled style="opacity:.5"' : ''; ?>>
                ✨ Generate Excerpt
            </button>
            <div id="tls-ai-ex-status" style="font-size:11px;margin-top:5px;min-height:16px;"></div>
            <div id="tls-ai-ex-preview" style="display:none;margin-top:8px;padding:8px;background:#f9f7f4;border:1px solid #ddd;border-radius:6px;font-size:12px;line-height:1.6;"></div>
            <button id="tls-ai-ex-apply" type="button" class="button button-primary" style="display:none;width:100%;margin-top:6px;">✓ Apply</button>
        </div>

        <hr style="border:none;border-top:1px solid #eee;margin:12px 0;">

        <!-- Meta -->
        <div>
            <strong style="font-size:12px;display:block;margin-bottom:8px;">🔍 Meta Description</strong>
            <button type="button" id="tls-ai-gen-meta" class="button" style="width:100%;"
                    data-post-id="<?php echo (int)$post->ID; ?>"
                    data-nonce="<?php echo esc_attr($nonce); ?>"
                    <?php echo !$has_key ? 'disabled style="opacity:.5"' : ''; ?>>
                ✨ Generate Meta
            </button>
            <div id="tls-ai-mt-status" style="font-size:11px;margin-top:5px;min-height:16px;"></div>
            <div id="tls-ai-mt-preview" style="display:none;margin-top:8px;padding:8px;background:#f9f7f4;border:1px solid #ddd;border-radius:6px;font-size:12px;line-height:1.6;"></div>
            <button id="tls-ai-mt-copy" type="button" class="button" style="display:none;width:100%;margin-top:6px;">📋 Copy</button>
        </div>

    </div>

    <script>
    (function($){
        var ajax = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';

        /* Checkbox toggle */
        $('#tls-dq-cb').on('change', function(){
            var btn = $('#tls-ai-extract-quotes');
            btn.prop('disabled', this.checked).css('opacity', this.checked ? '.5' : '1');
        });

        /* Quote Extract */
        $('#tls-ai-extract-quotes').on('click', function(){
            var btn = $(this);
            btn.prop('disabled',true).text('⏳ Analyzing…');
            $('#tls-ai-q-status').css('color','#666').text('');
            $.post(ajax, {
                action:'tls_ai_extract_quotes',
                post_id: btn.data('post-id'),
                nonce: btn.data('nonce')
            }, function(res){
                btn.prop('disabled',false).text('✨ Extract Quotes (2)');
                if (res.success && res.data.quotes) {
                    // Tüm slotları temizle
                    for(var i=0;i<5;i++){
                        $('textarea[name="tls_quotes['+i+'][text]"]').val('');
                        $('input[name="tls_quotes['+i+'][source]"]').val('');
                    }
                    res.data.quotes.forEach(function(q,i){
                        $('textarea[name="tls_quotes['+i+'][text]"]').val(q.text||'');
                        $('input[name="tls_quotes['+i+'][source]"]').val(q.source||'');
                    });
                    $('#tls-ai-q-status').css('color','#15803d').text('✓ Done! Save the post.');
                } else {
                    $('#tls-ai-q-status').css('color','#b91c1c').text('⚠️ '+(res.data||'Failed.'));
                }
            }).fail(function(){ btn.prop('disabled',false).text('✨ Extract Quotes (2)'); $('#tls-ai-q-status').css('color','#b91c1c').text('Network error.'); });
        });

        /* Excerpt */
        var excerptText = '';
        $('#tls-ai-gen-excerpt').on('click', function(){
            var btn=$(this);
            btn.prop('disabled',true).text('⏳ Writing…');
            $('#tls-ai-ex-status').text('');$('#tls-ai-ex-preview,#tls-ai-ex-apply').hide();
            $.post(ajax,{action:'tls_ai_gen_excerpt',post_id:btn.data('post-id'),nonce:btn.data('nonce')},function(res){
                btn.prop('disabled',false).text('✨ Generate Excerpt');
                if(res.success&&res.data.excerpt){
                    excerptText=res.data.excerpt;
                    $('#tls-ai-ex-preview').show().text(excerptText);
                    $('#tls-ai-ex-apply').show();
                    $('#tls-ai-ex-status').css('color','#15803d').text('✓ Review below');
                } else { $('#tls-ai-ex-status').css('color','#b91c1c').text('⚠️ '+(res.data||'Failed.')); }
            }).fail(function(){btn.prop('disabled',false).text('✨ Generate Excerpt');});
        });
        $('#tls-ai-ex-apply').on('click',function(){
            $('#excerpt').val(excerptText);
            $('#tls-ai-ex-status').css('color','#15803d').text('✓ Applied!');
            $('#tls-ai-ex-preview,#tls-ai-ex-apply').hide();
        });

        /* Meta */
        var metaText='';
        $('#tls-ai-gen-meta').on('click',function(){
            var btn=$(this);
            btn.prop('disabled',true).text('⏳ Generating…');
            $('#tls-ai-mt-status').text('');$('#tls-ai-mt-preview,#tls-ai-mt-copy').hide();
            $.post(ajax,{action:'tls_ai_gen_meta',post_id:btn.data('post-id'),nonce:btn.data('nonce')},function(res){
                btn.prop('disabled',false).text('✨ Generate Meta');
                if(res.success&&res.data.meta){
                    metaText=res.data.meta;
                    var len=metaText.length;
                    $('#tls-ai-mt-preview').show().html(metaText+' <span style="color:'+(len>155?'#b91c1c':'#888')+'">('+ len+'/155)</span>');
                    $('#tls-ai-mt-copy').show();
                    $('#tls-ai-mt-status').css('color','#15803d').text('✓ Review below');
                } else { $('#tls-ai-mt-status').css('color','#b91c1c').text('⚠️ '+(res.data||'Failed.')); }
            }).fail(function(){btn.prop('disabled',false).text('✨ Generate Meta');});
        });
        $('#tls-ai-mt-copy').on('click',function(){
            navigator.clipboard.writeText(metaText).then(function(){
                $('#tls-ai-mt-copy').text('✓ Copied!');
                setTimeout(function(){$('#tls-ai-mt-copy').text('📋 Copy');},2000);
            });
        });

    })(jQuery);
    </script>
    <?php
}

/* ── Checkbox kaydet ── */
add_action( 'save_post_post', function( $post_id ) {
    if ( ! isset($_POST['tls_dq_nonce']) ) return;
    if ( ! wp_verify_nonce($_POST['tls_dq_nonce'], 'tls_dq_save') ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;

    if ( ! empty($_POST['tls_disable_quotes']) ) {
        update_post_meta( $post_id, '_tls_disable_quotes', '1' );
    } else {
        delete_post_meta( $post_id, '_tls_disable_quotes' );
    }
} );

/* ── Otomatik: post ilk yayınlandığında loopback request ──
   blocking:false → WordPress cevabı beklemeden devam eder.
   Ayrı bir PHP process'te çalışır, admin'i bloklamaz.
*/
add_action( 'transition_post_status', function( $new, $old, $post ) {
    if ( $post->post_type !== 'post' ) return;
    if ( $new !== 'publish' ) return;
    if ( $old === 'publish' ) return; // zaten yayındaysa çalışma
    if ( ! tls_get_claude_key() ) return;
    if ( get_post_meta( $post->ID, '_tls_disable_quotes', true ) === '1' ) return;

    $secret = wp_hash( 'tls_bg_' . $post->ID . '_' . get_option('auth_key') );

    // Non-blocking loopback — cevap beklenmez, arka planda çalışır
    wp_remote_post( admin_url('admin-ajax.php'), [
        'timeout'   => 0.01,
        'blocking'  => false,
        'sslverify' => false,
        'body'      => [
            'action'  => 'tls_bg_extract_quotes',
            'post_id' => $post->ID,
            'secret'  => $secret,
        ],
    ] );
}, 10, 3 );

/* ── Background handler: loopback isteği karşıla ── */
add_action( 'wp_ajax_nopriv_tls_bg_extract_quotes', 'tls_bg_extract_handler' );
add_action( 'wp_ajax_tls_bg_extract_quotes',        'tls_bg_extract_handler' );

function tls_bg_extract_handler() {
    $post_id = (int)( $_POST['post_id'] ?? 0 );
    $secret  = sanitize_text_field( $_POST['secret'] ?? '' );

    // Secret doğrula
    $expected = wp_hash( 'tls_bg_' . $post_id . '_' . get_option('auth_key') );
    if ( ! hash_equals( $expected, $secret ) ) wp_die('Unauthorized', 403);
    if ( ! $post_id ) wp_die('No post ID');

    // Zaten alıntı varsa atla
    $existing = get_post_meta( $post_id, '_tls_quotes', true );
    if ( ! empty($existing) ) wp_die('Already has quotes');

    // Disable seçildiyse atla
    if ( get_post_meta( $post_id, '_tls_disable_quotes', true ) === '1' ) wp_die('Disabled');

    tls_run_quote_extraction( $post_id );
    wp_die('OK');
}

/* ── Ortak yardımcılar ── */
function tls_get_post_content_for_ai( int $post_id ): string {
    $post    = get_post($post_id);
    if (!$post) return '';
    $title   = get_the_title($post_id);
    $authors = get_the_terms($post_id,'authors');
    $author  = (!empty($authors)&&!is_wp_error($authors)) ? $authors[0]->name : '';
    $content = wp_strip_all_tags(apply_filters('the_content',$post->post_content));
    $content = preg_replace('/\s+/',' ',$content);
    if (strlen($content)>6000) $content = substr($content,0,6000).'…';
    return "Title: {$title}\nAuthor: {$author}\n\nContent:\n{$content}";
}

function tls_call_claude( string $system, string $user ): ?string {
    $key = tls_get_claude_key();
    if (!$key) return null;
    $res = wp_remote_post('https://api.anthropic.com/v1/messages',[
        'timeout' => 30,
        'headers' => [
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ],
        'body' => json_encode([
            'model'    => 'claude-haiku-4-5-20251001',
            'max_tokens' => 2000,
            'system'   => $system,
            'messages' => [['role'=>'user','content'=>$user]],
        ]),
    ]);
    if (is_wp_error($res)) return null;
    $body = json_decode(wp_remote_retrieve_body($res),true);
    return $body['content'][0]['text'] ?? null;
}

/* ── Ortak: alıntı çıkarma ── */
function tls_run_quote_extraction( int $post_id ): array {
    $content = tls_get_post_content_for_ai( $post_id );
    if ( ! $content ) return [];

    $system = 'You are a literary curator for a philosophy book summary platform.
Extract the 2 most powerful, thought-provoking, and quotable passages from the given book summary.
Choose complete, standalone sentences that capture the most essential ideas.
Respond ONLY with a valid JSON array, no markdown, no explanation:
[{"text":"exact quote","source":"chapter or concept if known, else empty string"},...]';

    $raw = tls_call_claude( $system, $content );
    if ( ! $raw ) return [];

    $raw    = preg_replace( '/^```json?\s*/', '', trim($raw) );
    $raw    = preg_replace( '/```$/', '', trim($raw) );
    $quotes = json_decode( trim($raw), true );
    if ( ! is_array($quotes) ) return [];

    $quotes = array_slice( $quotes, 0, 2 );
    foreach ( $quotes as &$q ) {
        $q['text']   = sanitize_textarea_field( $q['text']   ?? '' );
        $q['source'] = sanitize_text_field(     $q['source'] ?? '' );
    }
    $quotes = array_values( array_filter($quotes, fn($q) => !empty($q['text'])) );
    update_post_meta( $post_id, '_tls_quotes', $quotes );
    return $quotes;
}

/* ── AJAX: Quote Extractor (manuel buton) ── */
add_action('wp_ajax_tls_ai_extract_quotes', function(){
    if (!check_ajax_referer('tls_ai_nonce','nonce',false)||!current_user_can('edit_posts'))
        wp_send_json_error('Unauthorized',403);

    $post_id = (int)($_POST['post_id']??0);
    if (!$post_id) wp_send_json_error('No post ID.');

    $quotes = tls_run_quote_extraction( $post_id );
    if ( empty($quotes) ) wp_send_json_error('API call failed. Check your API key.');
    wp_send_json_success(['quotes' => $quotes]);
});

/* ── AJAX: Excerpt Generator ── */
add_action('wp_ajax_tls_ai_gen_excerpt', function(){
    if (!check_ajax_referer('tls_ai_nonce','nonce',false)||!current_user_can('edit_posts'))
        wp_send_json_error('Unauthorized',403);

    $post_id = (int)($_POST['post_id']??0);
    $content = tls_get_post_content_for_ai($post_id);
    if (!$content) wp_send_json_error('No content.');

    $system = 'You write compelling scholarly excerpts for a philosophy book summary platform.
Write a 2-3 sentence excerpt capturing the essence of the book. Be precise and evocative.
Respond with ONLY the excerpt text, no quotes, no explanation.';

    $excerpt = tls_call_claude($system,$content);
    if (!$excerpt) wp_send_json_error('API call failed.');

    $excerpt = sanitize_textarea_field(trim($excerpt));
    wp_update_post(['ID'=>$post_id,'post_excerpt'=>$excerpt]);
    wp_send_json_success(['excerpt'=>$excerpt]);
});

/* ── AJAX: Meta Description ── */
add_action('wp_ajax_tls_ai_gen_meta', function(){
    if (!check_ajax_referer('tls_ai_nonce','nonce',false)||!current_user_can('edit_posts'))
        wp_send_json_error('Unauthorized',403);

    $post_id = (int)($_POST['post_id']??0);
    $content = tls_get_post_content_for_ai($post_id);
    if (!$content) wp_send_json_error('No content.');

    $system = 'Write an SEO meta description of MAXIMUM 155 characters for a philosophy book summary.
Mention the book/author, what readers learn, and create curiosity.
Respond with ONLY the meta description text.';

    $meta = tls_call_claude($system,$content);
    if (!$meta) wp_send_json_error('API call failed.');

    $meta = sanitize_text_field(trim($meta));
    if (strlen($meta)>160) $meta = substr($meta,0,157).'…';
    wp_send_json_success(['meta'=>$meta]);
});
