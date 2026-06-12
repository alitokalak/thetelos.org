<?php
/**
 * Firestore Book Browser
 * Firebase Firestore'a yüklenen OpenLibrary verisinden
 * kategori (subject) → yazar → eser tarama ve seçilen eserlerden
 * taslak yazı oluşturma için admin paneli.
 *
 * Erişim: Firestore REST API (public read kuralları veya Web API Key ile çalışır).
 * Tüm istekler sunucu tarafından (wp_remote_post) yapılır ve transient ile önbelleklenir,
 * böylece tarayıcı kilitlenmez ve OpenLibrary'nin yavaş API'sine hiç gidilmez.
 *
 * @package Mediumish / TheTelos
 */

// -------------------------------------------------------
// Ayarlar
// -------------------------------------------------------
function thetelos_fb_get_settings() {
    $defaults = [
        'project_id'            => '',
        'api_key'               => '',
        'database_id'           => '(default)',
        'subjects_collection'   => 'subjects',
        'authors_collection'    => 'authors',
        'works_collection'      => 'works',
        'subject_name_field'    => 'name',
        'author_name_field'     => 'name',
        'author_subjects_field' => 'subjects',
        'work_title_field'      => 'title',
        'work_author_field'     => 'author_name',
        'work_subjects_field'   => 'subjects',
        'cache_minutes'         => 60,
    ];

    $saved = get_option( 'thetelos_fb_settings', [] );
    return wp_parse_args( is_array( $saved ) ? $saved : [], $defaults );
}

// -------------------------------------------------------
// Firestore REST yardımcıları
// -------------------------------------------------------

/** Firestore'un tipli value formatını düz PHP değerine çevirir. */
function thetelos_fb_decode_value( $v ) {
    if ( ! is_array( $v ) ) return null;
    if ( array_key_exists( 'stringValue', $v ) )    return $v['stringValue'];
    if ( array_key_exists( 'integerValue', $v ) )   return (int) $v['integerValue'];
    if ( array_key_exists( 'doubleValue', $v ) )    return (float) $v['doubleValue'];
    if ( array_key_exists( 'booleanValue', $v ) )   return (bool) $v['booleanValue'];
    if ( array_key_exists( 'nullValue', $v ) )      return null;
    if ( array_key_exists( 'timestampValue', $v ) ) return $v['timestampValue'];
    if ( array_key_exists( 'referenceValue', $v ) ) return $v['referenceValue'];

    if ( isset( $v['arrayValue'] ) ) {
        $out = [];
        foreach ( $v['arrayValue']['values'] ?? [] as $item ) {
            $out[] = thetelos_fb_decode_value( $item );
        }
        return $out;
    }
    if ( isset( $v['mapValue'] ) ) {
        $out = [];
        foreach ( $v['mapValue']['fields'] ?? [] as $k => $item ) {
            $out[ $k ] = thetelos_fb_decode_value( $item );
        }
        return $out;
    }
    return null;
}

function thetelos_fb_filter_eq( $field, $value ) {
    return [ 'fieldFilter' => [
        'field' => [ 'fieldPath' => $field ],
        'op'    => 'EQUAL',
        'value' => [ 'stringValue' => $value ],
    ] ];
}

function thetelos_fb_filter_contains( $field, $value ) {
    return [ 'fieldFilter' => [
        'field' => [ 'fieldPath' => $field ],
        'op'    => 'ARRAY_CONTAINS',
        'value' => [ 'stringValue' => $value ],
    ] ];
}

/** "abc" → abc ile başlayan tüm değerler (>= "abc" AND < "abc") */
function thetelos_fb_filter_prefix( $field, $prefix ) {
    return [ 'compositeFilter' => [
        'op'      => 'AND',
        'filters' => [
            [ 'fieldFilter' => [
                'field' => [ 'fieldPath' => $field ],
                'op'    => 'GREATER_THAN_OR_EQUAL',
                'value' => [ 'stringValue' => $prefix ],
            ] ],
            [ 'fieldFilter' => [
                'field' => [ 'fieldPath' => $field ],
                'op'    => 'LESS_THAN',
                'value' => [ 'stringValue' => $prefix . "\xEF\xA3\xBF" ],
            ] ],
        ],
    ] ];
}

/**
 * structuredQuery çalıştırır, sonuçları düz dizilere çevirir.
 * Her doküman alanlarına ek olarak '__id' anahtarıyla doküman ID'sini içerir.
 *
 * @return array|WP_Error
 */
function thetelos_fb_run_query( $structured_query, $use_cache = true ) {
    $s = thetelos_fb_get_settings();

    if ( empty( $s['project_id'] ) ) {
        return new WP_Error( 'no_project', 'Firestore Project ID tanımlı değil. Book Browser → Settings sekmesinden girin.' );
    }

    $url = sprintf(
        'https://firestore.googleapis.com/v1/projects/%s/databases/%s/documents:runQuery',
        rawurlencode( $s['project_id'] ),
        rawurlencode( $s['database_id'] )
    );
    if ( ! empty( $s['api_key'] ) ) {
        $url .= '?key=' . rawurlencode( $s['api_key'] );
    }

    $cache_key = 'tfb_' . md5( $url . wp_json_encode( $structured_query ) );
    if ( $use_cache ) {
        $hit = get_transient( $cache_key );
        if ( false !== $hit ) return $hit;
    }

    $response = wp_remote_post( $url, [
        'timeout' => 25,
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( [ 'structuredQuery' => $structured_query ] ),
    ] );

    if ( is_wp_error( $response ) ) return $response;

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( 200 !== $code ) {
        $msg = $body[0]['error']['message'] ?? ( $body['error']['message'] ?? ( 'HTTP ' . $code ) );
        return new WP_Error( 'firestore_error', $msg );
    }

    $docs = [];
    foreach ( (array) $body as $row ) {
        if ( empty( $row['document'] ) ) continue; // sonuçsuz yanıtlar sadece readTime döner
        $fields = [];
        foreach ( $row['document']['fields'] ?? [] as $k => $v ) {
            $fields[ $k ] = thetelos_fb_decode_value( $v );
        }
        $name           = $row['document']['name'];
        $fields['__id'] = rawurldecode( substr( $name, strrpos( $name, '/' ) + 1 ) );
        $docs[]         = $fields;
    }

    if ( $use_cache ) {
        set_transient( $cache_key, $docs, max( 1, (int) $s['cache_minutes'] ) * MINUTE_IN_SECONDS );
    }
    return $docs;
}

// -------------------------------------------------------
// Admin menü + sayfa
// -------------------------------------------------------
add_action( 'admin_menu', function () {
    add_menu_page(
        'Book Browser',
        'Book Browser',
        'edit_posts',
        'thetelos-book-browser',
        'thetelos_fb_admin_page',
        'dashicons-book',
        26
    );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( 'toplevel_page_thetelos-book-browser' !== $hook ) return;

    $dir = get_template_directory();
    $uri = get_template_directory_uri();

    wp_enqueue_style(
        'thetelos-fb',
        $uri . '/inc/firestore-browser.css',
        [],
        file_exists( $dir . '/inc/firestore-browser.css' ) ? filemtime( $dir . '/inc/firestore-browser.css' ) : '1.0'
    );
    wp_enqueue_script(
        'thetelos-fb',
        $uri . '/inc/firestore-browser.js',
        [],
        file_exists( $dir . '/inc/firestore-browser.js' ) ? filemtime( $dir . '/inc/firestore-browser.js' ) : '1.0',
        true
    );
    wp_localize_script( 'thetelos-fb', 'tfbData', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'thetelos_fb' ),
    ] );
} );

function thetelos_fb_admin_page() {
    if ( ! current_user_can( 'edit_posts' ) ) return;

    // Ayar kaydetme (sadece yöneticiler)
    if ( isset( $_POST['thetelos_fb_settings_nonce'] )
        && wp_verify_nonce( $_POST['thetelos_fb_settings_nonce'], 'thetelos_fb_save_settings' )
        && current_user_can( 'manage_options' )
    ) {
        $keys = array_keys( thetelos_fb_get_settings() );
        $new  = [];
        foreach ( $keys as $key ) {
            if ( isset( $_POST[ 'tfb_' . $key ] ) ) {
                $new[ $key ] = sanitize_text_field( wp_unslash( $_POST[ 'tfb_' . $key ] ) );
            }
        }
        $new['cache_minutes'] = max( 1, intval( $new['cache_minutes'] ?? 60 ) );
        update_option( 'thetelos_fb_settings', $new );

        // Şema/proje değişmiş olabilir → önbelleği boşalt
        thetelos_fb_flush_cache();
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved, cache cleared.</p></div>';
    }

    $s   = thetelos_fb_get_settings();
    $tab = isset( $_GET['tab'] ) && 'settings' === $_GET['tab'] ? 'settings' : 'browser';
    if ( empty( $s['project_id'] ) ) $tab = 'settings';

    $base_url = admin_url( 'admin.php?page=thetelos-book-browser' );
    ?>
    <div class="wrap tfb-wrap">
        <h1>Book Browser <span class="tfb-sub">OpenLibrary @ Firestore</span></h1>

        <nav class="nav-tab-wrapper">
            <a href="<?php echo esc_url( $base_url ); ?>" class="nav-tab <?php echo 'browser' === $tab ? 'nav-tab-active' : ''; ?>">Browser</a>
            <a href="<?php echo esc_url( $base_url . '&tab=settings' ); ?>" class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">Settings</a>
        </nav>

        <?php if ( 'settings' === $tab ) : ?>

        <form method="post" class="tfb-settings">
            <?php wp_nonce_field( 'thetelos_fb_save_settings', 'thetelos_fb_settings_nonce' ); ?>

            <h2>Firebase Connection</h2>
            <table class="form-table">
                <tr>
                    <th><label for="tfb_project_id">Project ID *</label></th>
                    <td><input type="text" id="tfb_project_id" name="tfb_project_id" class="regular-text" value="<?php echo esc_attr( $s['project_id'] ); ?>" placeholder="my-openlibrary-project">
                        <p class="description">Firebase konsolu → Project settings → Project ID</p></td>
                </tr>
                <tr>
                    <th><label for="tfb_api_key">Web API Key</label></th>
                    <td><input type="text" id="tfb_api_key" name="tfb_api_key" class="regular-text" value="<?php echo esc_attr( $s['api_key'] ); ?>">
                        <p class="description">Opsiyonel — Firestore kuralları herkese açık okumaya izin veriyorsa boş bırakılabilir.</p></td>
                </tr>
                <tr>
                    <th><label for="tfb_database_id">Database ID</label></th>
                    <td><input type="text" id="tfb_database_id" name="tfb_database_id" class="regular-text" value="<?php echo esc_attr( $s['database_id'] ); ?>">
                        <p class="description">Varsayılan veritabanı için <code>(default)</code> bırakın.</p></td>
                </tr>
            </table>

            <h2>Collections &amp; Fields</h2>
            <p class="description">Firestore'a yüklediğin verinin koleksiyon ve alan adlarını buraya gir. Alan doküman ID'sinin kendisiyse <code>__id</code> yaz.</p>
            <table class="form-table">
                <tr>
                    <th>Subjects (kategoriler)</th>
                    <td>
                        collection: <input type="text" name="tfb_subjects_collection" value="<?php echo esc_attr( $s['subjects_collection'] ); ?>">
                        name field: <input type="text" name="tfb_subject_name_field" value="<?php echo esc_attr( $s['subject_name_field'] ); ?>">
                    </td>
                </tr>
                <tr>
                    <th>Authors (yazarlar)</th>
                    <td>
                        collection: <input type="text" name="tfb_authors_collection" value="<?php echo esc_attr( $s['authors_collection'] ); ?>">
                        name field: <input type="text" name="tfb_author_name_field" value="<?php echo esc_attr( $s['author_name_field'] ); ?>">
                        subjects field: <input type="text" name="tfb_author_subjects_field" value="<?php echo esc_attr( $s['author_subjects_field'] ); ?>">
                    </td>
                </tr>
                <tr>
                    <th>Works (eserler)</th>
                    <td>
                        collection: <input type="text" name="tfb_works_collection" value="<?php echo esc_attr( $s['works_collection'] ); ?>">
                        title field: <input type="text" name="tfb_work_title_field" value="<?php echo esc_attr( $s['work_title_field'] ); ?>">
                        author field: <input type="text" name="tfb_work_author_field" value="<?php echo esc_attr( $s['work_author_field'] ); ?>">
                        subjects field: <input type="text" name="tfb_work_subjects_field" value="<?php echo esc_attr( $s['work_subjects_field'] ); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="tfb_cache_minutes">Cache (dakika)</label></th>
                    <td><input type="number" id="tfb_cache_minutes" name="tfb_cache_minutes" min="1" value="<?php echo esc_attr( $s['cache_minutes'] ); ?>" style="width:90px;"></td>
                </tr>
            </table>

            <?php if ( current_user_can( 'manage_options' ) ) : ?>
                <p><button type="submit" class="button button-primary">Save Settings</button></p>
            <?php endif; ?>
        </form>

        <hr>
        <h2>Connection Test / Schema Preview</h2>
        <p class="description">Bir koleksiyondan ilk 3 dokümanı çekip yapısını gösterir — alan adlarını doğrulamak için kullan.</p>
        <p>
            <input type="text" id="tfb-preview-collection" value="<?php echo esc_attr( $s['works_collection'] ); ?>" placeholder="collection name">
            <button type="button" class="button" id="tfb-preview-btn">Fetch Sample</button>
        </p>
        <pre id="tfb-preview-output" class="tfb-preview" style="display:none;"></pre>

        <?php else : ?>

        <div class="tfb-toolbar">
            <label>Import into category:
                <?php wp_dropdown_categories( [
                    'id'              => 'tfb-wp-category',
                    'name'            => 'tfb-wp-category',
                    'hide_empty'      => false,
                    'show_option_none' => '— none —',
                    'option_none_value' => '0',
                ] ); ?>
            </label>
            <button type="button" class="button" id="tfb-import-selected" disabled>Import selected as drafts (<span id="tfb-sel-count">0</span>)</button>
            <span id="tfb-toolbar-status"></span>
        </div>

        <div id="tfb-error" class="notice notice-error" style="display:none;"><p></p></div>

        <div class="tfb-columns">
            <div class="tfb-col" id="tfb-col-subjects">
                <div class="tfb-col-head">
                    <strong>Subjects</strong>
                    <input type="text" id="tfb-subject-search" placeholder="Search subject… (Enter = use as filter)">
                </div>
                <ul class="tfb-list" id="tfb-subject-list"></ul>
                <div class="tfb-col-foot"><button type="button" class="button button-small tfb-more" id="tfb-subjects-more" style="display:none;">Load more</button></div>
            </div>

            <div class="tfb-col" id="tfb-col-authors">
                <div class="tfb-col-head">
                    <strong>Authors</strong> <span class="tfb-context" id="tfb-author-context"></span>
                    <input type="text" id="tfb-author-search" placeholder="Search author by name…">
                </div>
                <ul class="tfb-list" id="tfb-author-list"></ul>
                <div class="tfb-col-foot"><button type="button" class="button button-small tfb-more" id="tfb-authors-more" style="display:none;">Load more</button></div>
            </div>

            <div class="tfb-col tfb-col-wide" id="tfb-col-works">
                <div class="tfb-col-head">
                    <strong>Works</strong> <span class="tfb-context" id="tfb-work-context"></span>
                    <label class="tfb-selectall"><input type="checkbox" id="tfb-select-all"> select all</label>
                </div>
                <ul class="tfb-list" id="tfb-work-list"></ul>
                <div class="tfb-col-foot"><button type="button" class="button button-small tfb-more" id="tfb-works-more" style="display:none;">Load more</button></div>
            </div>
        </div>

        <?php endif; ?>
    </div>
    <?php
}

// -------------------------------------------------------
// Önbellek temizleme
// -------------------------------------------------------
function thetelos_fb_flush_cache() {
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tfb_%' OR option_name LIKE '_transient_timeout_tfb_%'" );
}

// -------------------------------------------------------
// AJAX ortak güvenlik kontrolü
// -------------------------------------------------------
function thetelos_fb_ajax_guard() {
    if ( ! check_ajax_referer( 'thetelos_fb', 'nonce', false ) ) {
        wp_send_json_error( 'Invalid nonce', 403 );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }
}

// -------------------------------------------------------
// AJAX: Şema önizleme (bağlantı testi)
// -------------------------------------------------------
add_action( 'wp_ajax_thetelos_fb_preview', function () {
    thetelos_fb_ajax_guard();

    $collection = isset( $_POST['collection'] ) ? sanitize_text_field( wp_unslash( $_POST['collection'] ) ) : '';
    if ( ! $collection ) wp_send_json_error( 'Collection name required' );

    $docs = thetelos_fb_run_query( [
        'from'  => [ [ 'collectionId' => $collection ] ],
        'limit' => 3,
    ], false );

    if ( is_wp_error( $docs ) ) wp_send_json_error( $docs->get_error_message() );
    wp_send_json_success( $docs );
} );

// -------------------------------------------------------
// AJAX: Subject (kategori) listesi
// -------------------------------------------------------
add_action( 'wp_ajax_thetelos_fb_subjects', function () {
    thetelos_fb_ajax_guard();

    $s      = thetelos_fb_get_settings();
    $q      = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
    $offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
    $limit  = 100;
    $field  = $s['subject_name_field'];

    $sq = [
        'from'   => [ [ 'collectionId' => $s['subjects_collection'] ] ],
        'limit'  => $limit,
        'offset' => $offset,
    ];

    if ( '__id' === $field ) {
        // Doküman ID'sine göre sıralı liste; prefix araması ID üstünde yapılamaz, client filtreler
        $sq['orderBy'] = [ [ 'field' => [ 'fieldPath' => '__name__' ], 'direction' => 'ASCENDING' ] ];
    } else {
        if ( $q ) $sq['where'] = thetelos_fb_filter_prefix( $field, $q );
        $sq['orderBy'] = [ [ 'field' => [ 'fieldPath' => $field ], 'direction' => 'ASCENDING' ] ];
    }

    $docs = thetelos_fb_run_query( $sq );
    if ( is_wp_error( $docs ) ) wp_send_json_error( $docs->get_error_message() );

    $items = [];
    foreach ( $docs as $d ) {
        $name = ( '__id' !== $field && ! empty( $d[ $field ] ) && is_string( $d[ $field ] ) ) ? $d[ $field ] : $d['__id'];
        if ( '__id' === $field && $q && stripos( $name, $q ) !== 0 ) continue;
        $items[] = [ 'name' => $name, 'id' => $d['__id'] ];
    }

    wp_send_json_success( [ 'items' => $items, 'has_more' => count( $docs ) === $limit ] );
} );

// -------------------------------------------------------
// AJAX: Yazar listesi (subject'e göre veya isim önekiyle)
// -------------------------------------------------------
add_action( 'wp_ajax_thetelos_fb_authors', function () {
    thetelos_fb_ajax_guard();

    $s       = thetelos_fb_get_settings();
    $subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
    $q       = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
    $offset  = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
    $limit   = 100;
    $via     = 'authors';

    $name_field = $s['author_name_field'];
    $items      = [];
    $has_more   = false;

    if ( $subject ) {
        // 1) authors koleksiyonunda subjects array'i varsa doğrudan filtrele
        // (array-contains + orderBy bileşik index ister; index gerektirmemek için sıralamayı client yapar)
        $docs = thetelos_fb_run_query( [
            'from'   => [ [ 'collectionId' => $s['authors_collection'] ] ],
            'where'  => thetelos_fb_filter_contains( $s['author_subjects_field'], $subject ),
            'limit'  => $limit,
            'offset' => $offset,
        ] );

        if ( ! is_wp_error( $docs ) && ! empty( $docs ) ) {
            foreach ( $docs as $d ) {
                $name    = ( '__id' !== $name_field && ! empty( $d[ $name_field ] ) ) ? $d[ $name_field ] : $d['__id'];
                $items[] = [ 'name' => is_string( $name ) ? $name : $d['__id'], 'id' => $d['__id'] ];
            }
            $has_more = count( $docs ) === $limit;
        } else {
            // 2) Fallback: works koleksiyonundan subject'e göre eser çek, yazarları topla
            $via  = 'works';
            $docs = thetelos_fb_run_query( [
                'from'   => [ [ 'collectionId' => $s['works_collection'] ] ],
                'where'  => thetelos_fb_filter_contains( $s['work_subjects_field'], $subject ),
                'limit'  => 300,
                'offset' => $offset * 3, // works tarafında daha geniş pencere
            ] );

            if ( is_wp_error( $docs ) ) wp_send_json_error( $docs->get_error_message() );

            $seen = [];
            foreach ( $docs as $d ) {
                $val     = $d[ $s['work_author_field'] ] ?? null;
                $authors = is_array( $val ) ? $val : ( $val ? [ $val ] : [] );
                foreach ( $authors as $a ) {
                    if ( ! is_string( $a ) || '' === $a || isset( $seen[ $a ] ) ) continue;
                    $seen[ $a ] = true;
                    $items[]    = [ 'name' => $a, 'id' => $a ];
                }
            }
            usort( $items, function ( $x, $y ) { return strcasecmp( $x['name'], $y['name'] ); } );
            $has_more = count( $docs ) === 300;
        }
    } elseif ( $q && '__id' !== $name_field ) {
        $docs = thetelos_fb_run_query( [
            'from'    => [ [ 'collectionId' => $s['authors_collection'] ] ],
            'where'   => thetelos_fb_filter_prefix( $name_field, $q ),
            'orderBy' => [ [ 'field' => [ 'fieldPath' => $name_field ], 'direction' => 'ASCENDING' ] ],
            'limit'   => $limit,
            'offset'  => $offset,
        ] );
        if ( is_wp_error( $docs ) ) wp_send_json_error( $docs->get_error_message() );

        foreach ( $docs as $d ) {
            $items[] = [ 'name' => $d[ $name_field ] ?? $d['__id'], 'id' => $d['__id'] ];
        }
        $has_more = count( $docs ) === $limit;
    } else {
        $order_field = '__id' === $name_field ? '__name__' : $name_field;
        $docs = thetelos_fb_run_query( [
            'from'    => [ [ 'collectionId' => $s['authors_collection'] ] ],
            'orderBy' => [ [ 'field' => [ 'fieldPath' => $order_field ], 'direction' => 'ASCENDING' ] ],
            'limit'   => $limit,
            'offset'  => $offset,
        ] );
        if ( is_wp_error( $docs ) ) wp_send_json_error( $docs->get_error_message() );

        foreach ( $docs as $d ) {
            $name = ( '__id' !== $name_field && ! empty( $d[ $name_field ] ) ) ? $d[ $name_field ] : $d['__id'];
            if ( $q && stripos( (string) $name, $q ) !== 0 ) continue;
            $items[] = [ 'name' => is_string( $name ) ? $name : $d['__id'], 'id' => $d['__id'] ];
        }
        $has_more = count( $docs ) === $limit;
    }

    wp_send_json_success( [ 'items' => $items, 'has_more' => $has_more, 'via' => $via ] );
} );

// -------------------------------------------------------
// AJAX: Eser listesi (yazara göre, opsiyonel subject filtresi)
// -------------------------------------------------------
add_action( 'wp_ajax_thetelos_fb_works', function () {
    thetelos_fb_ajax_guard();

    $s       = thetelos_fb_get_settings();
    $author  = isset( $_POST['author'] ) ? sanitize_text_field( wp_unslash( $_POST['author'] ) ) : '';
    $subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
    $offset  = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
    $limit   = 100;

    if ( ! $author ) wp_send_json_error( 'Author required' );

    $base = [
        'from'   => [ [ 'collectionId' => $s['works_collection'] ] ],
        'limit'  => $limit,
        'offset' => $offset,
    ];

    // Yazar alanı string da olabilir (EQUAL) array de (ARRAY_CONTAINS) → otomatik dene
    $sq          = $base;
    $sq['where'] = thetelos_fb_filter_eq( $s['work_author_field'], $author );
    $docs        = thetelos_fb_run_query( $sq );

    if ( ( is_wp_error( $docs ) || empty( $docs ) ) && 0 === $offset ) {
        $sq          = $base;
        $sq['where'] = thetelos_fb_filter_contains( $s['work_author_field'], $author );
        $retry       = thetelos_fb_run_query( $sq );
        if ( ! is_wp_error( $retry ) ) $docs = $retry;
    }

    if ( is_wp_error( $docs ) ) wp_send_json_error( $docs->get_error_message() );

    $title_field    = $s['work_title_field'];
    $subjects_field = $s['work_subjects_field'];

    $items  = [];
    $titles = [];
    foreach ( $docs as $d ) {
        $title = $d[ $title_field ] ?? '';
        if ( ! is_string( $title ) || '' === $title ) continue;

        $subjects = $d[ $subjects_field ] ?? [];
        $subjects = is_array( $subjects ) ? array_values( array_filter( $subjects, 'is_string' ) ) : [];

        // Subject filtresi client/server karışık: tek sorguda 2. filtre bileşik index isterdi,
        // index zorunluluğu olmasın diye burada PHP tarafında eleniyor
        if ( $subject && ! in_array( $subject, $subjects, true ) ) continue;

        $items[] = [
            'id'       => $d['__id'],
            'title'    => $title,
            'subjects' => array_slice( $subjects, 0, 4 ),
        ];
        $titles[] = $title;
    }

    // Sitede zaten var olan başlıkları tek sorguda işaretle
    $existing = [];
    if ( $titles ) {
        global $wpdb;
        $placeholders = implode( ',', array_fill( 0, count( $titles ), '%s' ) );
        $found        = $wpdb->get_col( $wpdb->prepare(
            "SELECT post_title FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status NOT IN ('trash','auto-draft') AND post_title IN ($placeholders)",
            $titles
        ) );
        $existing = array_flip( $found );
    }
    foreach ( $items as &$item ) {
        $item['exists'] = isset( $existing[ $item['title'] ] );
    }
    unset( $item );

    wp_send_json_success( [ 'items' => $items, 'has_more' => count( $docs ) === $limit ] );
} );

// -------------------------------------------------------
// AJAX: Eseri taslak yazı olarak içe aktar
// -------------------------------------------------------
add_action( 'wp_ajax_thetelos_fb_import', function () {
    thetelos_fb_ajax_guard();

    $title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
    $author      = isset( $_POST['author'] ) ? sanitize_text_field( wp_unslash( $_POST['author'] ) ) : '';
    $work_id     = isset( $_POST['work_id'] ) ? sanitize_text_field( wp_unslash( $_POST['work_id'] ) ) : '';
    $category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;

    if ( ! $title ) wp_send_json_error( 'Title required' );

    // Aynı başlıkla yazı varsa tekrar oluşturma
    global $wpdb;
    $dup = $wpdb->get_var( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status NOT IN ('trash','auto-draft') AND post_title = %s LIMIT 1",
        $title
    ) );
    if ( $dup ) {
        wp_send_json_success( [
            'post_id'   => (int) $dup,
            'duplicate' => true,
            'edit_link' => get_edit_post_link( (int) $dup, 'raw' ),
        ] );
    }

    $postarr = [
        'post_type'   => 'post',
        'post_status' => 'draft',
        'post_title'  => $title,
    ];
    if ( $category_id ) $postarr['post_category'] = [ $category_id ];

    $post_id = wp_insert_post( $postarr, true );
    if ( is_wp_error( $post_id ) ) wp_send_json_error( $post_id->get_error_message() );

    if ( $author && taxonomy_exists( 'authors' ) ) {
        wp_set_object_terms( $post_id, $author, 'authors', false );
    }
    if ( $work_id ) {
        update_post_meta( $post_id, '_ol_work_id', $work_id );
    }

    wp_send_json_success( [
        'post_id'   => $post_id,
        'duplicate' => false,
        'edit_link' => get_edit_post_link( $post_id, 'raw' ),
    ] );
} );
