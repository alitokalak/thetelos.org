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

    // Boş excerpt'li postları bul
    $all_posts = get_posts([
        'post_type'   => ['post', 'analysis'],
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields'      => 'ids',
    ]);
    $empty_ids = array_values(array_filter($all_posts, function($id) {
        $p = get_post($id);
        return empty(trim($p->post_excerpt));
    }));
    $empty_count = count($empty_ids);
    ?>
    <div class="wrap">
        <h1>&#x1F916; Thetelos AI Settings</h1>
        <form method="post">
            <?php wp_nonce_field('tls_ai_settings'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="tls_claude_api_key">Claude API Key</label></th>
                    <td>
                        <input type="password" id="tls_claude_api_key" name="tls_claude_api_key"
                               value="<?php echo esc_attr($key); ?>" class="regular-text"
                               placeholder="sk-ant-api03-...">
                        <?php if ($key) echo '<p class="description">Key is set &#x2713;</p>'; ?>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="tls_save_key" class="button button-primary">Save</button>
            </p>
        </form>

        <hr>
        <h2>&#x1F4E6; Bulk Excerpt Generator</h2>
        <p>Posts and analyses without an excerpt: <strong><?php echo $empty_count; ?></strong></p>
        <?php if ( $empty_count > 0 && $key ) : ?>
        <p style="color:#666;max-width:560px;">
            Generates a short AI excerpt for each post/analysis that has none.
            Runs one by one — do not close the page until complete.
        </p>
        <button id="tls-bulk-start" class="button button-primary">
            &#x25B6; Start Bulk Generation (<?php echo $empty_count; ?> posts)
        </button>
        <div id="tls-bulk-progress" style="margin-top:16px;display:none;">
            <div style="background:#e5e7eb;border-radius:4px;height:20px;width:400px;">
                <div id="tls-bulk-bar" style="background:#2563eb;height:20px;border-radius:4px;width:0%;transition:width .3s;"></div>
            </div>
            <p id="tls-bulk-status" style="margin-top:8px;font-family:monospace;"></p>
        </div>
        <div id="tls-bulk-log" style="margin-top:12px;max-height:300px;overflow-y:auto;
             font-family:monospace;font-size:12px;background:#f9fafb;
             border:1px solid #e5e7eb;padding:12px;border-radius:4px;display:none;"></div>
        <script>
        jQuery(function($){
            var ids   = <?php echo json_encode($empty_ids); ?>;
            var total = ids.length;
            var nonce = '<?php echo wp_create_nonce("tls_ai_nonce"); ?>';
            var done  = 0;

            $('#tls-bulk-start').on('click', function(){
                $(this).prop('disabled', true).text('Running...');
                $('#tls-bulk-progress, #tls-bulk-log').show();
                processNext();
            });

            function processNext() {
                if (done >= total) {
                    $('#tls-bulk-status').css('color','#15803d')
                        .text('All done! ' + total + ' excerpts generated.');
                    $('#tls-bulk-start').text('Complete');
                    return;
                }
                var id = ids[done];
                $('#tls-bulk-status').text('Processing ' + (done+1) + ' / ' + total + ' (ID: ' + id + ')...');
                $.post(ajaxurl, {
                    action: 'tls_ai_gen_excerpt',
                    post_id: id,
                    nonce: nonce
                }, function(res) {
                    done++;
                    var pct = Math.round((done/total)*100);
                    $('#tls-bulk-bar').css('width', pct + '%');
                    var log = res.success
                        ? 'OK: ID' + id + ' — ' + (res.data && res.data.excerpt ? res.data.excerpt.substring(0,60)+'...' : 'done')
                        : 'FAIL: ID' + id + ' — ' + (res.data || 'error');
                    $('#tls-bulk-log').append('<div>' + log + '</div>');
                    $('#tls-bulk-log').scrollTop($('#tls-bulk-log')[0].scrollHeight);
                    setTimeout(processNext, 1000);
                }).fail(function(){
                    done++;
                    $('#tls-bulk-log').append('<div>FAIL: ID' + id + ' — request failed</div>');
                    setTimeout(processNext, 1000);
                });
            }
        });
        </script>
        <?php elseif ( !$key ) : ?>
        <p style="color:#b91c1c;">API key not set. Please save your API key above first.</p>
        <?php else : ?>
        <p style="color:#15803d;">All posts already have excerpts!</p>
        <?php endif; ?>
    </div>
    <?php
}

/* ── Meta box ── */
add_action( 'add_meta_boxes', function() {
    add_meta_box( 'tls_ai_assistant', '🤖 AI Assistant', 'tls_ai_meta_box', 'post', 'side', 'high' );
    add_meta_box( 'tls_ai_assistant', '🤖 AI Assistant', 'tls_ai_meta_box', 'analysis', 'side', 'high' );
} );

function tls_ai_meta_box( $post ) {
    $has_key      = (bool) tls_get_claude_key();
    $nonce        = wp_create_nonce('tls_ai_nonce');
    $meta_val = get_post_meta( $post->ID, '_tls_disable_quotes', true );
    $disable  = ( $meta_val === '' ) ? true : ( $meta_val === '1' );
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
                    // Gutenberg (analysis ve post) için de güncelle
                    if ( typeof wp !== 'undefined' && wp.data ) {
                        try {
                            wp.data.dispatch('core/editor').editPost({ excerpt: excerptText });
                        } catch(e) {}
                    }
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

                    /* Yoast alanına otomatik yaz */
                    var injected = false;
                    /* Klasik editör */
                    if($('#yoast_wpseo_metadesc').length){
                        $('#yoast_wpseo_metadesc').val(metaText).trigger('input').trigger('change');
                        injected = true;
                    }
                    /* Gutenberg — Yoast metabox textarea */
                    if($('textarea#yoast_wpseo_metadesc, textarea[id*="metadesc"]').length){
                        $('textarea#yoast_wpseo_metadesc, textarea[id*="metadesc"]').val(metaText).trigger('input').trigger('change');
                        injected = true;
                    }
                    /* Gutenberg — React controlled input (Yoast snippet editor) */
                    $('div[class*="SnippetEditor"] textarea, .yoast-field-group textarea').each(function(){
                        var nativeInput = this;
                        var nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype,'value').set;
                        nativeSetter.call(nativeInput, metaText);
                        nativeInput.dispatchEvent(new Event('input',{bubbles:true}));
                        injected = true;
                    });

                    $('#tls-ai-mt-status').css('color','#15803d').text(injected ? '✓ Yoast alanına eklendi' : '✓ Review below');
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
add_action( 'wp_after_insert_post', function( $post_id, $post, $update ) {
    // wp_after_insert_post: meta box'lar kaydedildikten SONRA tetiklenir
    if ( ! in_array( $post->post_type, [ 'post', 'analysis' ], true ) ) return;
    if ( $post->post_status !== 'publish' ) return;
    if ( $update ) return; // zaten yayındaysa çalışma — sadece ilk publish
    if ( ! tls_get_claude_key() ) return;
    if ( get_post_meta( $post_id, '_tls_disable_quotes', true ) === '1' ) return;

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

// Excerpt için hafif versiyon — sadece başlık + ilk 1500 karakter
function tls_get_post_content_for_excerpt( int $post_id ): string {
    $post    = get_post($post_id);
    if (!$post) return '';
    $title   = get_the_title($post_id);
    $authors = get_the_terms($post_id,'authors');
    $author  = (!empty($authors)&&!is_wp_error($authors)) ? $authors[0]->name : '';
    $content = wp_strip_all_tags(apply_filters('the_content',$post->post_content));
    $content = preg_replace('/\s+/',' ',$content);
    if (strlen($content)>1500) $content = substr($content,0,1500).'…';
    return "Title: {$title}\nAuthor: {$author}\n\nContent (opening):\n{$content}";
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
            'max_tokens' => 800,
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
    $content = tls_get_post_content_for_excerpt($post_id);
    if (!$content || strlen(strip_tags($content)) < 200) wp_send_json_error('No content.');

    $system = 'You write compelling scholarly excerpts for a philosophy book summary platform.
Write exactly 2 short sentences (max 160 characters total) capturing the core argument of the work.
Be precise and direct. No flowery language. No semicolons or long clauses.
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
    if (!$content || strlen(strip_tags($content)) < 200) wp_send_json_error('No content.');

    $system = 'Write an SEO meta description for a philosophy book summary. STRICT RULES:
- Write ONE complete sentence ending with a period
- Maximum 140 characters including the period
- Mention the book/author and the core argument
- No "Discover", no "Learn", no clickbait
- Respond with ONLY the sentence, nothing else';

    $meta = tls_call_claude($system,$content);
    if (!$meta) wp_send_json_error('API call failed.');

    $meta = sanitize_text_field(trim($meta));
    // Kelime sınırında kes — asla ortadan kesme
    if (strlen($meta) > 155) {
        $meta = substr($meta, 0, 155);
        $meta = substr($meta, 0, strrpos($meta, ' '));
    }
    wp_send_json_success(['meta'=>$meta]);
});
