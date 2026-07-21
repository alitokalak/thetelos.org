<?php
/**
 * interests.php — Üye ilgi alanları (Phase 1).
 *
 * 20 ana ilgi alanı tanımı, kullanıcı meta'sında saklama (_tls_interests),
 * kaydetme AJAX'ı ve chip-grid render yardımcısı. Her alanın 'match' anahtar
 * kelimeleri, ileride (haftalık digest) kitabın kategorileriyle eşleştirmek
 * için kullanılacak.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* 20 ana ilgi alanı: slug => [label, emoji, kategori eşleştirme kelimeleri]. */
function tls_interest_areas() {
    return [
        'philosophy' => [ 'label' => 'Philosophy',                'emoji' => '🧠', 'match' => [ 'philosoph', 'metaphysic', 'epistemolog', 'existential' ] ],
        'religion'   => [ 'label' => 'Religion & Theology',       'emoji' => '🕊️', 'match' => [ 'relig', 'theolog', 'christian', 'islam', 'scriptur', 'faith', 'divine' ] ],
        'history'    => [ 'label' => 'History',                   'emoji' => '🏛️', 'match' => [ 'history', 'histor', 'ancient', 'medieval', 'revolution' ] ],
        'science'    => [ 'label' => 'Science & Mathematics',     'emoji' => '🔬', 'match' => [ 'science', 'physic', 'mathematic', 'biolog', 'chemist', 'astronom' ] ],
        'psychology' => [ 'label' => 'Psychology',                'emoji' => '🧩', 'match' => [ 'psycholog', 'cognit', 'behavior' ] ],
        'literature' => [ 'label' => 'Literature & Fiction',      'emoji' => '📖', 'match' => [ 'literatur', 'fiction', 'poetry', 'novel', 'drama', 'prose' ] ],
        'politics'   => [ 'label' => 'Politics & Society',        'emoji' => '🏳️', 'match' => [ 'politic', 'government', 'state', 'democracy', 'law' ] ],
        'economics'  => [ 'label' => 'Economics & Business',      'emoji' => '💹', 'match' => [ 'econom', 'business', 'capital', 'market', 'finance', 'trade' ] ],
        'ethics'     => [ 'label' => 'Ethics & Morality',         'emoji' => '⚖️', 'match' => [ 'ethic', 'moral', 'virtue' ] ],
        'arts'       => [ 'label' => 'Art & Aesthetics',          'emoji' => '🎨', 'match' => [ 'art', 'aesthetic', 'music', 'beauty' ] ],
        'classics'   => [ 'label' => 'Classical Antiquity',       'emoji' => '🏺', 'match' => [ 'classic', 'antiquit', 'greek', 'roman', 'stoic' ] ],
        'eastern'    => [ 'label' => 'Eastern Thought',           'emoji' => '☯️', 'match' => [ 'eastern', 'taois', 'confuci', 'zen', 'hindu', 'vedic', 'buddh' ] ],
        'mysticism'  => [ 'label' => 'Mysticism & Spirituality',  'emoji' => '🕯️', 'match' => [ 'mystic', 'spiritual', 'esoteric', 'gnostic' ] ],
        'sociology'  => [ 'label' => 'Sociology & Culture',       'emoji' => '🌍', 'match' => [ 'sociolog', 'cultur', 'anthropolog', 'society' ] ],
        'biography'  => [ 'label' => 'Biography & Memoir',        'emoji' => '👤', 'match' => [ 'biograph', 'memoir', 'autobiograph', 'letters' ] ],
        'nature'     => [ 'label' => 'Nature & Environment',      'emoji' => '🌿', 'match' => [ 'nature', 'environ', 'ecolog' ] ],
        'mind'       => [ 'label' => 'Mind & Consciousness',      'emoji' => '💭', 'match' => [ 'conscious', 'phenomenolog', 'percept', 'the mind' ] ],
        'growth'     => [ 'label' => 'Self-Development',          'emoji' => '🌱', 'match' => [ 'self', 'develop', 'education', 'wisdom', 'habit', 'productiv' ] ],
        'war'        => [ 'label' => 'War & Strategy',            'emoji' => '⚔️', 'match' => [ 'war', 'strateg', 'milit', 'conflict' ] ],
        'language'   => [ 'label' => 'Language & Rhetoric',       'emoji' => '🗣️', 'match' => [ 'languag', 'linguist', 'rhetoric', 'logic' ] ],
    ];
}

/* Kullanıcının seçtiği ilgi alanları (slug dizisi). */
function tls_get_user_interests( $uid ) {
    $v = get_user_meta( $uid, '_tls_interests', true );
    return is_array( $v ) ? $v : [];
}

/* Kaydet: geçerli slug'ları filtreleyip _tls_interests'e yaz. */
add_action( 'wp_ajax_tls_save_interests', function () {
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'login_required' ] );
    check_ajax_referer( 'tls_auth_nonce', 'nonce', false );

    $areas = tls_interest_areas();
    $raw   = $_POST['interests'] ?? [];
    if ( is_string( $raw ) ) $raw = explode( ',', $raw );

    $clean = [];
    foreach ( (array) $raw as $s ) {
        $s = sanitize_key( $s );
        if ( isset( $areas[ $s ] ) && ! in_array( $s, $clean, true ) ) $clean[] = $s;
    }
    update_user_meta( get_current_user_id(), '_tls_interests', $clean );
    wp_send_json_success( [ 'count' => count( $clean ), 'interests' => $clean ] );
} );

/* Chip-grid HTML (sunucu tarafı, seçili olanlar işaretli döner). */
function tls_render_interest_grid( $selected = [] ) {
    $sel = array_flip( (array) $selected );
    $out = '<div class="tls-int-grid">';
    foreach ( tls_interest_areas() as $slug => $a ) {
        $on = isset( $sel[ $slug ] ) ? ' tls-int-chip--on' : '';
        $out .= '<button type="button" class="tls-int-chip' . $on . '" data-slug="' . esc_attr( $slug ) . '" aria-pressed="' . ( $on ? 'true' : 'false' ) . '">'
              . '<span class="tls-int-emoji">' . $a['emoji'] . '</span>'
              . '<span class="tls-int-label">' . esc_html( $a['label'] ) . '</span>'
              . '<span class="tls-int-check" aria-hidden="true">✓</span>'
              . '</button>';
    }
    $out .= '</div>';
    return $out;
}
