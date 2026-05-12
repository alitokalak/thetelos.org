<?php
/**
 * Book Cover Fetcher
 * Post editörüne Google Books + Open Library üzerinden kapak arama meta box'u ekler.
 * Seçilen kapağı medya kütüphanesine indirir ve featured image olarak ayarlar.
 *
 * @package Mediumish / TheTelos
 */

// -----------------------------------------------------
// Meta Box — post edit ekranına ekle
// -----------------------------------------------------
add_action( 'add_meta_boxes', 'thetelos_cover_fetcher_add_meta_box' );

function thetelos_cover_fetcher_add_meta_box() {
    add_meta_box(
        'thetelos_cover_fetcher',
        'Book Cover Fetcher',
        'thetelos_cover_fetcher_html',
        'post',
        'side',
        'high'
    );
}

function thetelos_cover_fetcher_html( $post ) {
    // Arama sorgusunu başlıktan türet
    $title = $post->post_title ?: '';

    // Yazar adını başlıktan çıkar (book-cover.php mantığıyla uyumlu)
    $authors = get_the_terms( $post->ID, 'authors' );
    if ( ! empty( $authors ) && ! is_wp_error( $authors ) ) {
        $title = trim( str_ireplace( $authors[0]->name, '', $title ) );
    }
    $title = preg_replace( '/\s*[\(\(].*?[\)\)]/u', '', $title );
    $title = preg_replace( '/\s*[-–—].*$/u', '', $title );
    $title = trim( $title );

    $has_thumb = has_post_thumbnail( $post->ID );
    $search_nonce = wp_create_nonce( 'thetelos_cover_fetcher' );
    $set_nonce    = wp_create_nonce( 'thetelos_cover_fetcher' );
    ?>
    <div id="tcf-wrap">

        <?php if ( $has_thumb ) : ?>
        <div id="tcf-current" style="margin-bottom:10px;text-align:center;">
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:#aaa;margin-bottom:5px;">Current Cover</div>
            <?php echo get_the_post_thumbnail( $post->ID, [ 80, 110 ], [
                'style' => 'border-radius:3px;box-shadow:0 2px 10px rgba(0,0,0,.25);max-width:80px;height:auto;'
            ] ); ?>
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:5px;margin-bottom:6px;">
            <input
                type="text"
                id="tcf-query"
                value="<?php echo esc_attr( $title ); ?>"
                placeholder="Book title…"
                autocomplete="off"
                style="flex:1;padding:5px 8px;border:1px solid #8c8f94;border-radius:3px;font-size:12px;box-sizing:border-box;"
            />
            <button type="button" id="tcf-search-btn"
                style="padding:5px 10px;background:#00ab6b;color:#fff;border:none;border-radius:3px;font-size:12px;cursor:pointer;flex-shrink:0;transition:background .2s;">
                Search
            </button>
        </div>

        <div id="tcf-status" style="font-size:11px;color:#aaa;min-height:16px;margin-bottom:6px;"></div>

        <div id="tcf-results" style="
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:5px;
        "></div>
    </div>

    <script>
    (function() {
        var btn    = document.getElementById('tcf-search-btn');
        var input  = document.getElementById('tcf-query');
        var status = document.getElementById('tcf-status');
        var grid   = document.getElementById('tcf-results');
        var postId = <?php echo (int) $post->ID; ?>;
        var xhr    = null;
        var setXhr = null;

        // ── Buton hover ──
        btn.addEventListener('mouseenter', function() { this.style.background = '#009960'; });
        btn.addEventListener('mouseleave', function() { this.style.background = '#00ab6b'; });

        // ── Enter → ara ──
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
        });
        btn.addEventListener('click', doSearch);

        // ── Yeni post: başlık alanını dinle, otomatik ara ──
        <?php if ( empty( $post->post_title ) ) : ?>
        var titleField = document.getElementById('title');
        if (titleField) {
            var autoTimer = null;
            titleField.addEventListener('input', function() {
                clearTimeout(autoTimer);
                var v = this.value.trim();
                if (v.length > 3) {
                    autoTimer = setTimeout(function() {
                        input.value = v;
                        doSearch();
                    }, 1400);
                }
            });
        }
        <?php endif; ?>

        // ── Başlık doluysa sayfa açılınca otomatik ara ──
        <?php if ( ! empty( $title ) ) : ?>
        setTimeout(doSearch, 600);
        <?php endif; ?>

        function doSearch() {
            var q = input.value.trim();
            if (!q) return;

            if (xhr) xhr.abort();
            grid.innerHTML = '';
            setStatus('Searching…', '#aaa');
            btn.disabled = true;

            xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                btn.disabled = false;
                try {
                    var res = JSON.parse(this.responseText);
                    if (res.success && res.data && res.data.length) {
                        setStatus(res.data.length + ' covers found — click to use', '#888');
                        renderCovers(res.data);
                    } else {
                        setStatus('No covers found. Try a different title.', '#cc8800');
                    }
                } catch(e) {
                    setStatus('Response error.', '#cc1818');
                }
            };
            xhr.onerror = function() { btn.disabled = false; setStatus('Network error.', '#cc1818'); };
            xhr.send(
                'action=thetelos_fetch_book_covers' +
                '&nonce=' + encodeURIComponent('<?php echo esc_js( $search_nonce ); ?>') +
                '&q=' + encodeURIComponent(q)
            );
        }

        function renderCovers(covers) {
            grid.innerHTML = '';
            covers.forEach(function(c, i) {
                var cell = document.createElement('div');
                cell.style.cssText = 'cursor:pointer;border:2px solid transparent;border-radius:3px;overflow:hidden;transition:border-color .15s;background:#f5f5f5;';

                var img = document.createElement('img');
                img.src = c.cover;
                img.alt = c.title;
                img.style.cssText = 'width:100%;height:80px;object-fit:cover;display:block;';
                img.loading = 'lazy';

                var label = document.createElement('div');
                label.style.cssText = 'font-size:9px;color:#555;padding:2px 3px;line-height:1.3;max-height:28px;overflow:hidden;';
                label.textContent = c.title;

                cell.appendChild(img);
                cell.appendChild(label);

                cell.addEventListener('mouseenter', function() { this.style.borderColor = '#00ab6b'; });
                cell.addEventListener('mouseleave', function() {
                    if (!this.dataset.selected) this.style.borderColor = 'transparent';
                });
                cell.addEventListener('click', function() { setFeaturedImage(c, cell); });

                grid.appendChild(cell);
            });
        }

        function setFeaturedImage(c, selectedCell) {
            if (setXhr) setXhr.abort();

            // Seçim vurgusu
            Array.from(grid.children).forEach(function(ch) {
                ch.style.borderColor = 'transparent';
                ch.style.opacity = '0.45';
                delete ch.dataset.selected;
            });
            selectedCell.style.borderColor = '#00ab6b';
            selectedCell.style.opacity = '1';
            selectedCell.dataset.selected = '1';

            setStatus('Downloading & setting cover…', '#aaa');

            setXhr = new XMLHttpRequest();
            setXhr.open('POST', ajaxurl, true);
            setXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            setXhr.onload = function() {
                try {
                    var res = JSON.parse(this.responseText);
                    if (res.success) {
                        setStatus('✓ Cover set as featured image!', '#00ab6b');

                        // Mevcut kapak önizlemesini güncelle
                        var cur = document.getElementById('tcf-current');
                        if (!cur) {
                            cur = document.createElement('div');
                            cur.id = 'tcf-current';
                            cur.style.cssText = 'margin-bottom:10px;text-align:center;';
                            document.getElementById('tcf-wrap').insertBefore(cur, document.getElementById('tcf-wrap').firstChild);
                        }
                        cur.innerHTML = '<div style="font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:#aaa;margin-bottom:5px;">Current Cover</div>' +
                            '<img src="' + escAttr(res.data.thumb_src) + '" style="border-radius:3px;box-shadow:0 2px 10px rgba(0,0,0,.25);max-width:80px;height:auto;" alt="">';

                        // WP'nin native Featured Image kutusunu güncelle
                        if (res.data.wp_html) {
                            var wpBox = document.getElementById('set-post-thumbnail');
                            if (wpBox) wpBox.innerHTML = res.data.wp_html;
                        }
                    } else {
                        setStatus('Error: ' + (res.data || 'Unknown'), '#cc1818');
                        Array.from(grid.children).forEach(function(ch) { ch.style.opacity = '1'; });
                    }
                } catch(e) {
                    setStatus('Parse error.', '#cc1818');
                }
            };
            setXhr.onerror = function() { setStatus('Network error.', '#cc1818'); };
            setXhr.send(
                'action=thetelos_set_book_cover' +
                '&nonce=' + encodeURIComponent('<?php echo esc_js( $set_nonce ); ?>') +
                '&post_id=' + encodeURIComponent(postId) +
                '&cover_url=' + encodeURIComponent(c.cover) +
                '&title=' + encodeURIComponent(c.title)
            );
        }

        function setStatus(msg, color) {
            status.textContent = msg;
            status.style.color = color || '#aaa';
        }

        function escAttr(s) { return String(s).replace(/"/g, '&quot;'); }
    })();
    </script>
    <?php
}

// -----------------------------------------------------
// AJAX: Google Books + Open Library'den kapakları getir
// -----------------------------------------------------
add_action( 'wp_ajax_thetelos_fetch_book_covers', 'thetelos_ajax_fetch_book_covers' );

function thetelos_ajax_fetch_book_covers() {
    if ( ! check_ajax_referer( 'thetelos_cover_fetcher', 'nonce', false ) ) {
        wp_send_json_error( 'Invalid nonce', 403 );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }

    $query = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
    if ( strlen( $query ) < 2 ) {
        wp_send_json_error( 'Query too short' );
    }

    $results = [];

    // ── 1. Google Books API (birincil kaynak) ────────────────────────
    $google_url = 'https://www.googleapis.com/books/v1/volumes?' . http_build_query( [
        'q'          => 'intitle:' . $query,
        'maxResults' => 8,
        'printType'  => 'books',
        'fields'     => 'items(volumeInfo(title,authors,imageLinks))',
    ] );

    $response = wp_remote_get( $google_url, [ 'timeout' => 8 ] );

    if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body['items'] ) ) {
            foreach ( $body['items'] as $item ) {
                $info  = $item['volumeInfo'] ?? [];
                $links = $info['imageLinks'] ?? [];

                // En iyi kaliteyi seç
                $cover = $links['thumbnail'] ?? ( $links['smallThumbnail'] ?? '' );
                if ( ! $cover ) continue;

                // HTTP → HTTPS, zoom kalitesini artır
                $cover = str_replace( 'http://', 'https://', $cover );
                $cover = preg_replace( '/&zoom=\d/', '&zoom=1', $cover );
                // Kenar curl efektini kaldır (daha temiz görüntü)
                $cover = str_replace( '&edge=curl', '', $cover );

                $results[] = [
                    'cover'  => esc_url_raw( $cover ),
                    'title'  => sanitize_text_field( $info['title'] ?? '' ),
                    'author' => sanitize_text_field(
                        ! empty( $info['authors'] ) ? implode( ', ', array_slice( $info['authors'], 0, 2 ) ) : ''
                    ),
                    'source' => 'google',
                ];

                if ( count( $results ) >= 6 ) break;
            }
        }
    }

    // ── 2. Open Library (Google sonuç yetersizse fallback) ───────────
    if ( count( $results ) < 3 ) {
        $ol_url = 'https://openlibrary.org/search.json?' . http_build_query( [
            'title'  => $query,
            'limit'  => 8,
            'fields' => 'key,title,author_name,cover_i',
        ] );

        $ol_response = wp_remote_get( $ol_url, [ 'timeout' => 8 ] );

        if ( ! is_wp_error( $ol_response ) && 200 === wp_remote_retrieve_response_code( $ol_response ) ) {
            $ol_body = json_decode( wp_remote_retrieve_body( $ol_response ), true );

            if ( ! empty( $ol_body['docs'] ) ) {
                foreach ( $ol_body['docs'] as $doc ) {
                    if ( empty( $doc['cover_i'] ) ) continue;

                    $cover_id = (int) $doc['cover_i'];
                    $results[] = [
                        'cover'  => "https://covers.openlibrary.org/b/id/{$cover_id}-M.jpg",
                        'title'  => sanitize_text_field( $doc['title'] ?? '' ),
                        'author' => sanitize_text_field(
                            ! empty( $doc['author_name'] )
                                ? implode( ', ', array_slice( $doc['author_name'], 0, 2 ) )
                                : ''
                        ),
                        'source' => 'openlibrary',
                    ];

                    if ( count( $results ) >= 6 ) break;
                }
            }
        }
    }

    if ( empty( $results ) ) {
        wp_send_json_error( 'No covers found' );
    }

    wp_send_json_success( $results );
}

// -----------------------------------------------------
// AJAX: Seçilen kapağı indir → medya kütüphanesine ekle → featured image yap
// -----------------------------------------------------
add_action( 'wp_ajax_thetelos_set_book_cover', 'thetelos_ajax_set_book_cover' );

function thetelos_ajax_set_book_cover() {
    if ( ! check_ajax_referer( 'thetelos_cover_fetcher', 'nonce', false ) ) {
        wp_send_json_error( 'Invalid nonce', 403 );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }

    $post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $cover_url = isset( $_POST['cover_url'] ) ? esc_url_raw( wp_unslash( $_POST['cover_url'] ) ) : '';
    $title     = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : 'Book Cover';

    if ( ! $post_id || ! $cover_url ) {
        wp_send_json_error( 'Missing parameters' );
    }

    // Yalnızca güvenilir kaynaklardan izin ver
    $allowed_hosts = [ 'books.google.com', 'covers.openlibrary.org', 'lh3.googleusercontent.com' ];
    $host = wp_parse_url( $cover_url, PHP_URL_HOST );
    if ( ! in_array( $host, $allowed_hosts, true ) ) {
        wp_send_json_error( 'Untrusted image source' );
    }

    if ( ! get_post( $post_id ) ) {
        wp_send_json_error( 'Post not found' );
    }

    // Medya kütüphanesi fonksiyonları
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    // Görseli indir ve medya kütüphanesine ekle
    $attachment_id = media_sideload_image( $cover_url, $post_id, $title, 'id' );

    if ( is_wp_error( $attachment_id ) ) {
        wp_send_json_error( $attachment_id->get_error_message() );
    }

    // Featured image olarak ayarla
    if ( ! set_post_thumbnail( $post_id, $attachment_id ) ) {
        wp_send_json_error( 'Could not set featured image' );
    }

    // WP native thumbnail kutusunu güncellemek için HTML döndür
    $thumb_src = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
    $wp_html   = _wp_post_thumbnail_html( $attachment_id, $post_id );

    wp_send_json_success( [
        'attachment_id' => $attachment_id,
        'thumb_src'     => $thumb_src,
        'wp_html'       => $wp_html,
    ] );
}
