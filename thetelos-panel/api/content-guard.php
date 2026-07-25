<?php
/**
 * content-guard.php — Yetişkin/müstehcen içeriği sitede bırakmaz.
 *
 * Site bu tür içerik yayınlamaz. Bu araç YAYINDAKİ yazıları ve kategorileri
 * tarar; eşleşenleri tek tuşla yayından kaldırır (taslak) veya çöpe atar.
 * Üretim tarafındaki engel batch-worker'da ayrıca vardır (hiç üretilmez).
 *
 * POST action=scan    → eşleşen yayınlar + kategoriler
 * POST action=draft   → seçilenleri taslağa al (yayından kaldır, silme)
 * POST action=trash   → seçilenleri çöpe at
 * POST action=delcat  → yetişkin kategoriyi sil (yazıları taslağa alarak)
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-LiteSpeed-Cache-Control: no-cache');
@set_time_limit(300);

ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();

function cg_terms() {
    return ['erotic','erotica','porn','pornograph','kama sutra','kamasutra',
            'sexual','sexuality','sexology','nude','nudity','naked',
            'fetish','bdsm','sadomasochism','orgasm','aphrodisiac','libertine',
            'obscene','obscenity','brothel','prostitut','courtesan','harem','concubine',
            'perfumed garden','ananga ranga','fanny hill','venus in furs',
            '120 days of sodom','delta of venus','lady chatterley'];
}
function cg_norm($s) {
    $s = ' ' . mb_strtolower((string)$s, 'UTF-8') . ' ';
    return preg_replace('/[^a-z0-9 ]+/u', ' ', $s);
}
function cg_hit($text) {
    $t = cg_norm($text);
    foreach (cg_terms() as $kw) {
        if (strpos($t, trim($kw)) !== false) return $kw;
    }
    return '';
}

$action = $_POST['action'] ?? '';

/* ── Tara ── */
if ($action === 'scan') {
    global $wpdb;
    $like = [];
    foreach (cg_terms() as $kw) $like[] = $wpdb->prepare('p.post_title LIKE %s', '%' . $wpdb->esc_like($kw) . '%');
    $sql = "SELECT p.ID, p.post_title, p.post_status FROM {$wpdb->posts} p
            WHERE p.post_type IN ('post','analysis') AND p.post_status IN ('publish','draft','pending')
              AND (" . implode(' OR ', $like) . ") ORDER BY p.post_date DESC LIMIT 500";
    $found = $wpdb->get_results($sql);

    $posts = [];
    foreach ($found as $p) {
        $kw = cg_hit($p->post_title);
        if (!$kw) continue;                       // LIKE geniş; kesin eşleşmeyi doğrula
        $authors = get_the_terms($p->ID, 'authors');
        $posts[] = [
            'id'     => (int)$p->ID,
            'title'  => $p->post_title,
            'author' => (!empty($authors) && !is_wp_error($authors)) ? $authors[0]->name : '',
            'status' => $p->post_status,
            'match'  => $kw,
            'url'    => get_permalink($p->ID),
        ];
    }

    $cats = [];
    foreach (get_categories(['hide_empty' => false]) as $c) {
        $kw = cg_hit($c->name . ' ' . $c->slug);
        if (!$kw) continue;
        $cats[] = ['id'=>(int)$c->term_id, 'name'=>$c->name, 'slug'=>$c->slug,
                   'count'=>(int)$c->count, 'match'=>$kw];
    }

    echo json_encode(['ok'=>true, 'posts'=>$posts, 'cats'=>$cats], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Yayından kaldır (taslak) / çöpe at ── */
if ($action === 'draft' || $action === 'trash') {
    $ids  = array_values(array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? '')))));
    $done = 0;
    foreach ($ids as $pid) {
        if (get_post_status($pid) === false) continue;
        if ($action === 'trash') { wp_trash_post($pid); }
        else { wp_update_post(['ID'=>$pid, 'post_status'=>'draft']); }
        $done++;
    }
    echo json_encode(['ok'=>true, 'done'=>$done]);
    exit;
}

/* ── Yetişkin kategoriyi sil: içindeki yazıları önce taslağa al ── */
if ($action === 'delcat') {
    $tid = (int)($_POST['term_id'] ?? 0);
    $t = $tid ? get_term($tid, 'category') : null;
    if (!$t || is_wp_error($t)) { echo json_encode(['ok'=>false,'error'=>'kategori yok']); exit; }

    $posts = get_posts(['post_type'=>'any','post_status'=>'any','numberposts'=>-1,
                        'fields'=>'ids','cat'=>$tid,'suppress_filters'=>true]);
    foreach ($posts as $pid) wp_update_post(['ID'=>$pid, 'post_status'=>'draft']);
    wp_delete_term($tid, 'category');

    echo json_encode(['ok'=>true, 'drafted'=>count($posts), 'name'=>$t->name], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok'=>false, 'error'=>'bad action']);
