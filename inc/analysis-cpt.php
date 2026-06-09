<?php
/**
 * Analysis Custom Post Type
 * Kitap analiz yazıları için özel post tipi
 *
 * @package Mediumish / TheTelos
 */

// -----------------------------------------------------
// Custom Post Type: analysis
// -----------------------------------------------------
function thetelos_register_analysis_cpt() {
    register_post_type( 'analysis', [
        'labels' => [
            'name'               => 'Analyses',
            'singular_name'      => 'Analysis',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Analysis',
            'edit_item'          => 'Edit Analysis',
            'view_item'          => 'View Analysis',
            'search_items'       => 'Search Analyses',
            'not_found'          => 'No analyses found',
            'not_found_in_trash' => 'No analyses found in trash',
        ],
        'public'        => true,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'has_archive'   => true,
        'show_in_rest'  => true,
        'supports'      => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
        'menu_icon'     => 'dashicons-book-alt',
        'rewrite'       => [ 'slug' => 'analysis', 'with_front' => false ],
    ]);
}
add_action( 'init', 'thetelos_register_analysis_cpt' );

// -----------------------------------------------------
// Meta Box: Related Book
// Bağlı kitap varsa → sadece bilgi göster (salt okunur)
// Bağlı kitap yoksa → AJAX arama göster
// -----------------------------------------------------
function thetelos_analysis_meta_box() {
    add_meta_box(
        'analysis_related_post',
        'Related Book Post',
        'thetelos_analysis_meta_box_html',
        'analysis',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes', 'thetelos_analysis_meta_box' );

function thetelos_analysis_meta_box_html( $post ) {
    wp_nonce_field( 'thetelos_analysis_save', 'thetelos_analysis_nonce' );

    // Kayıtlı kitap ID'si
    $related_id = (int) get_post_meta( $post->ID, '_related_book_id', true );

    // Yeni analiz: URL'den ?book_id=X geldiyse otomatik ata
    if ( ! $related_id && isset( $_GET['book_id'] ) ) {
        $related_id = intval( $_GET['book_id'] );
    }

    // ── DURUM A: Kitap bağlı → salt okunur bilgi ──────────────
    if ( $related_id && get_post( $related_id ) ) {
        $book    = get_post( $related_id );
        $authors = get_the_terms( $related_id, 'authors' );
        $author  = ( ! empty( $authors ) && ! is_wp_error( $authors ) ) ? $authors[0]->name : '';
        $label   = $book->post_title . ( $author ? ' — ' . $author : '' );
        ?>
        <input type="hidden" name="related_book_id" value="<?php echo esc_attr( $related_id ); ?>">

        <div style="padding:10px 12px;background:#f0f7f4;border-left:3px solid #00ab6b;border-radius:2px;font-size:12px;line-height:1.5;">
            <div style="color:#00ab6b;font-size:10px;text-transform:uppercase;letter-spacing:.1em;margin-bottom:4px;">Linked Book</div>
            <strong><?php echo esc_html( $label ); ?></strong>
            <div style="margin-top:6px;">
                <a href="<?php echo esc_url( get_permalink( $related_id ) ); ?>" target="_blank"
                   style="color:#00ab6b;font-size:11px;text-decoration:none;">View post ↗</a>
            </div>
        </div>

        <p style="margin-top:8px;font-size:11px;color:#bbb;line-height:1.4;">
            Linked via <em>Edit Analysis</em> from the book post.
        </p>
        <?php
        return;
    }

    // ── DURUM B: Kitap yok → AJAX arama ───────────────────────
    ?>
    <p style="margin-bottom:8px;color:#555;font-size:12px;">
        Search and select the book this analysis belongs to:
    </p>

    <div style="position:relative;">
        <input
            type="text"
            id="thetelos_book_search"
            placeholder="Type book title or author…"
            autocomplete="off"
            style="width:100%;padding:6px 8px;border:1px solid #8c8f94;border-radius:3px;font-size:13px;box-sizing:border-box;"
        />
        <input type="hidden" id="thetelos_book_id" name="related_book_id" value="">

        <ul id="thetelos_book_results" style="
            display:none;position:absolute;top:100%;left:0;right:0;
            background:#fff;border:1px solid #8c8f94;border-top:none;
            max-height:220px;overflow-y:auto;margin:0;padding:0;
            list-style:none;z-index:9999;
            box-shadow:0 4px 10px rgba(0,0,0,.12);
            border-radius:0 0 3px 3px;
        "></ul>
    </div>

    <div id="thetelos_selected_book"
         style="display:none;margin-top:8px;padding:8px 10px;
                background:#f0f7f4;border-left:3px solid #00ab6b;
                font-size:12px;color:#333;border-radius:2px;"></div>

    <script>
    (function(){
        var inp    = document.getElementById('thetelos_book_search');
        var hidden = document.getElementById('thetelos_book_id');
        var list   = document.getElementById('thetelos_book_results');
        var sel    = document.getElementById('thetelos_selected_book');
        var timer  = null, xhr = null;

        inp.addEventListener('input', function(){
            hidden.value = '';
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 2) { close(); return; }
            timer = setTimeout(function(){ search(q); }, 280);
        });

        function search(q) {
            if (xhr) xhr.abort();
            list.innerHTML = '<li style="padding:8px 12px;color:#aaa;font-size:12px;">Searching…</li>';
            list.style.display = 'block';
            xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
            xhr.onload = function(){
                if (this.status===200){
                    try { render(JSON.parse(this.responseText)); }
                    catch(e){ list.innerHTML='<li style="padding:8px 12px;color:#aaa;font-size:12px;">Error.</li>'; }
                }
            };
            xhr.send('action=thetelos_search_books&q='+encodeURIComponent(q)+'&nonce=<?php echo wp_create_nonce("thetelos_book_search"); ?>');
        }

        function render(books){
            if (!books||!books.length){
                list.innerHTML='<li style="padding:8px 12px;color:#aaa;font-size:12px;">No books found.</li>';
                return;
            }
            list.innerHTML='';
            books.forEach(function(b){
                var li=document.createElement('li');
                li.style.cssText='padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #f0f0f0;line-height:1.4;';
                li.innerHTML='<strong>'+esc(b.title)+'</strong>'+(b.author?'<span style="color:#999;font-size:12px;"> — '+esc(b.author)+'</span>':'');
                li.onmouseenter=function(){this.style.background='#f0f7f4';};
                li.onmouseleave=function(){this.style.background='';};
                li.addEventListener('mousedown',function(e){e.preventDefault();pick(b);});
                list.appendChild(li);
            });
        }

        function pick(b){
            hidden.value=b.id;
            inp.value=b.title+(b.author?' — '+b.author:'');
            sel.innerHTML='✓ <strong>'+esc(b.title)+'</strong>'+(b.author?' — '+esc(b.author):'')+
                ' <a href="'+b.view_url+'" target="_blank" style="margin-left:6px;color:#00ab6b;font-size:11px;">View ↗</a>';
            sel.style.display='block';
            close();
        }

        function close(){ list.style.display='none'; list.innerHTML=''; }

        document.addEventListener('click',function(e){ if(e.target!==inp) close(); });
        inp.addEventListener('keydown',function(e){ if(e.key==='Escape'){close();this.blur();} });
        function esc(s){ var d=document.createElement('div');d.textContent=s;return d.innerHTML; }
    })();
    </script>
    <?php
}

// -----------------------------------------------------
// AJAX: Kitap Arama
// -----------------------------------------------------
add_action( 'wp_ajax_thetelos_search_books', 'thetelos_ajax_search_books' );

function thetelos_ajax_search_books() {
    if ( ! check_ajax_referer( 'thetelos_book_search', 'nonce', false ) ) {
        wp_send_json_error( 'Invalid nonce', 403 );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }

    $query = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
    if ( strlen( $query ) < 2 ) {
        wp_send_json( [] );
    }

    // Başlığa göre ara
    $posts = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 12,
        'orderby'        => 'title',
        'order'          => 'ASC',
        's'              => $query,
    ]);

    // Yazar adına göre ek arama
    if ( count( $posts ) < 3 ) {
        $author_terms = get_terms([
            'taxonomy'   => 'authors',
            'name__like' => $query,
            'hide_empty' => true,
            'number'     => 5,
        ]);

        if ( ! is_wp_error( $author_terms ) && ! empty( $author_terms ) ) {
            $term_ids     = wp_list_pluck( $author_terms, 'term_id' );
            $author_posts = get_posts([
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 10,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'tax_query'      => [[
                    'taxonomy' => 'authors',
                    'field'    => 'term_id',
                    'terms'    => $term_ids,
                ]],
            ]);

            $existing_ids = wp_list_pluck( $posts, 'ID' );
            foreach ( $author_posts as $ap ) {
                if ( ! in_array( $ap->ID, $existing_ids, true ) ) {
                    $posts[] = $ap;
                }
            }
        }
    }

    $results = [];
    foreach ( array_slice( $posts, 0, 12 ) as $p ) {
        $authors   = get_the_terms( $p->ID, 'authors' );
        $author    = ( ! empty( $authors ) && ! is_wp_error( $authors ) ) ? $authors[0]->name : '';
        $results[] = [
            'id'       => $p->ID,
            'title'    => $p->post_title,
            'author'   => $author,
            'view_url' => get_permalink( $p->ID ),
        ];
    }

    wp_send_json( $results );
}

// -----------------------------------------------------
// Meta Save
// -----------------------------------------------------
function thetelos_analysis_meta_save( $post_id ) {
    if ( ! isset( $_POST['thetelos_analysis_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['thetelos_analysis_nonce'], 'thetelos_analysis_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    // Sadece dolu gelirse kaydet (boşsa mevcut değeri koru)
    if ( isset( $_POST['related_book_id'] ) && ! empty( $_POST['related_book_id'] ) ) {
        update_post_meta( $post_id, '_related_book_id', intval( $_POST['related_book_id'] ) );
    }
}
add_action( 'save_post_analysis', 'thetelos_analysis_meta_save' );

// -----------------------------------------------------
// Helper: Kitaba ait analizi getir
// -----------------------------------------------------
function thetelos_get_analysis_for_post( $post_id ) {
    $analyses = get_posts([
        'post_type'   => 'analysis',
        'post_status' => 'publish',
        'numberposts' => 1,
        'meta_query'  => [[
            'key'     => '_related_book_id',
            'value'   => $post_id,
            'compare' => '=',
        ]],
    ]);

    return ! empty( $analyses ) ? $analyses[0] : null;
}

// -----------------------------------------------------
// SEO: ?analysis= eski URL'leri /analysis/slug/ 'a 301 yönlendir
// Sadece $_GET['analysis'] varsa tetiklenir — /analysis/slug/
// URL'lerini yakalamaz, sonsuz döngü oluşmaz.
// -----------------------------------------------------
add_action( 'template_redirect', 'thetelos_redirect_old_analysis_urls' );
function thetelos_redirect_old_analysis_urls() {
    if ( ! isset( $_GET['analysis'] ) || '' === $_GET['analysis'] ) return;

    $slug = sanitize_title( wp_unslash( $_GET['analysis'] ) );
    if ( empty( $slug ) ) return;

    $posts = get_posts([
        'post_type'     => 'analysis',
        'name'          => $slug,
        'post_status'   => 'publish',
        'numberposts'   => 1,
        'no_found_rows' => true,
    ]);

    if ( ! empty( $posts ) ) {
        wp_redirect( get_permalink( $posts[0]->ID ), 301 );
        exit;
    }

    $posts_by_title = get_posts([
        'post_type'     => 'analysis',
        'post_status'   => 'publish',
        'numberposts'   => 1,
        'no_found_rows' => true,
        's'             => str_replace( '-', ' ', $slug ),
    ]);

    if ( ! empty( $posts_by_title ) ) {
        wp_redirect( get_permalink( $posts_by_title[0]->ID ), 301 );
        exit;
    }
}

// -----------------------------------------------------
// SEO: Yoast entegrasyonu — Analysis CPT
// Analysis sayfalarını sitemap'e dahil eder,
// otomatik title ve meta description üretir.
// -----------------------------------------------------
add_action( 'init', 'thetelos_analysis_yoast_integration', 20 );
function thetelos_analysis_yoast_integration() {
    if ( ! defined( 'WPSEO_VERSION' ) ) return;

    // Analysis'i Yoast sitemap'inden asla çıkarma
    add_filter( 'wpseo_sitemap_exclude_post_type', function( $excluded, $post_type ) {
        if ( $post_type === 'analysis' ) return false;
        return $excluded;
    }, 10, 2 );
}

// Yoast boş bırakırsa otomatik SEO title üret
add_filter( 'wpseo_title', 'thetelos_analysis_seo_title' );
function thetelos_analysis_seo_title( $title ) {
    if ( ! is_singular( 'analysis' ) ) return $title;
    $post = get_post();
    if ( ! $post ) return $title;
    if ( ! empty( $title ) ) return $title; // Yoast zaten ürettiyse dokunma
    return $post->post_title . ' — Deep Analysis | ' . get_bloginfo( 'name' );
}

// Yoast boş bırakırsa otomatik meta description üret
add_filter( 'wpseo_metadesc', 'thetelos_analysis_seo_desc' );
function thetelos_analysis_seo_desc( $desc ) {
    if ( ! is_singular( 'analysis' ) ) return $desc;
    if ( ! empty( $desc ) ) return $desc; // Yoast doldurmuşsa dokunma
    $post = get_post();
    if ( ! $post ) return $desc;
    $excerpt = $post->post_excerpt
        ? $post->post_excerpt
        : wp_trim_words( strip_tags( $post->post_content ), 28, '…' );
    return $excerpt;
}
