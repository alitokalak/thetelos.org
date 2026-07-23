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
        'religion'   => [ 'label' => 'Religion & Theology',       'emoji' => '🕊️', 'match' => [ 'relig', 'theolog', 'christian', 'islam', 'judaism', 'athei', 'agnostic', 'scriptur', 'faith', 'divine', 'myth', 'folklore' ] ],
        'history'    => [ 'label' => 'History',                   'emoji' => '🏛️', 'match' => [ 'history', 'histor', 'ancient', 'medieval', 'revolution' ] ],
        'science'    => [ 'label' => 'Science & Mathematics',     'emoji' => '🔬', 'match' => [ 'science', 'physic', 'mathematic', 'statistic', 'biolog', 'chemist', 'astronom', 'evolution', 'genetic', 'medicin', 'health', 'neuro' ] ],
        'technology' => [ 'label' => 'Technology & Computing',    'emoji' => '💻', 'match' => [ 'technolog', 'computer', 'programming', 'software', 'artificial', 'data science', 'coding', 'internet', 'robot' ] ],
        'psychology' => [ 'label' => 'Psychology',                'emoji' => '🧩', 'match' => [ 'psycholog', 'psychoanaly', 'cognit', 'behavior' ] ],
        'literature' => [ 'label' => 'Literature & Fiction',      'emoji' => '📖', 'match' => [ 'literatur', 'fiction', 'poetry', 'novel', 'drama', 'prose', 'fantasy', 'horror', 'mystery', 'adventure', 'children', 'young' ] ],
        'politics'   => [ 'label' => 'Politics & Society',        'emoji' => '🏳️', 'match' => [ 'politic', 'government', 'state', 'democracy', 'law' ] ],
        'economics'  => [ 'label' => 'Economics & Business',      'emoji' => '💹', 'match' => [ 'econom', 'business', 'capital', 'market', 'finance', 'trade', 'manage', 'entrepreneur' ] ],
        'ethics'     => [ 'label' => 'Ethics & Morality',         'emoji' => '⚖️', 'match' => [ 'ethic', 'moral', 'virtue' ] ],
        'arts'       => [ 'label' => 'Art & Aesthetics',          'emoji' => '🎨', 'match' => [ 'art', 'aesthetic', 'music', 'beauty', 'architect', 'design', 'photograph', 'film', 'cinema', 'theatre', 'sculpt' ] ],
        'classics'   => [ 'label' => 'Classical Antiquity',       'emoji' => '🏺', 'match' => [ 'classic', 'antiquit', 'greek', 'roman', 'stoic' ] ],
        'eastern'    => [ 'label' => 'Eastern Thought',           'emoji' => '☯️', 'match' => [ 'eastern', 'taois', 'confuci', 'zen', 'hindu', 'vedic', 'buddh' ] ],
        'mysticism'  => [ 'label' => 'Mysticism & Spirituality',  'emoji' => '🕯️', 'match' => [ 'mystic', 'spiritual', 'esoteric', 'gnostic' ] ],
        'sociology'  => [ 'label' => 'Sociology & Culture',       'emoji' => '🌍', 'match' => [ 'sociolog', 'cultur', 'anthropolog', 'society' ] ],
        'biography'  => [ 'label' => 'Biography & Memoir',        'emoji' => '👤', 'match' => [ 'biograph', 'memoir', 'autobiograph', 'letters' ] ],
        'nature'     => [ 'label' => 'Nature & Places',           'emoji' => '🌿', 'match' => [ 'nature', 'environ', 'ecolog', 'geograph', 'travel' ] ],
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

/* Chip-grid HTML (sunucu tarafı, seçili olanlar işaretli döner).
   CSS yalnızca ilk çağrıda basılır → hem profil sayfasında hem kayıt
   pop-up'ında (footer overlay) çalışır. */
function tls_render_interest_grid( $selected = [] ) {
    static $css_done = false;
    $out = '';
    if ( ! $css_done ) {
        $css_done = true;
        $out .= '<style>'
            . '.tls-int-help{font-family:var(--tls-sans);font-size:13px;color:var(--tls-muted);margin:0 0 16px;line-height:1.6}'
            . '.tls-int-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:9px}'
            . '.tls-int-chip{position:relative;display:flex;align-items:center;gap:9px;padding:11px 13px;text-align:left;background:var(--tls-bg);border:1.5px solid var(--tls-border);border-radius:12px;font-family:var(--tls-sans);font-size:12.5px;font-weight:500;color:var(--tls-bg-dark);cursor:pointer;transition:all .15s}'
            . '.tls-int-chip:hover{border-color:var(--tls-gold);background:#fff}'
            . '.tls-int-chip--on{border-color:var(--tls-gold);background:#fff;box-shadow:0 0 0 2px rgba(184,146,42,.18)}'
            . '.tls-int-emoji{font-size:17px;line-height:1;flex-shrink:0}'
            . '.tls-int-label{flex:1;line-height:1.2}'
            . '.tls-int-check{flex-shrink:0;width:17px;height:17px;border-radius:50%;background:var(--tls-gold);color:#fff;font-size:10px;display:flex;align-items:center;justify-content:center;opacity:0;transform:scale(.5);transition:all .15s}'
            . '.tls-int-chip--on .tls-int-check{opacity:1;transform:scale(1)}'
            . '</style>';
    }
    $sel = array_flip( (array) $selected );
    $out .= '<div class="tls-int-grid">';
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
