<?php
/**
 * category-audit.php — Onaylı ~100 kategori DIŞINDAKİ ("kaçak") kategorileri
 * listeler ve güvenle siler.
 *
 *   action=list   → kaçak kategorileri (ad, slug, yazı sayısı) döndürür (silmez).
 *   action=delete → seçilen kaçak kategori id'lerini siler. Silmeden ÖNCE, o
 *                   kategorideki her yazının başka bir ONAYLI kategorisi var mı
 *                   diye bakar; yoksa yazıyı "General" kovasına bağlar. Böylece
 *                   hiçbir yazı kategorisiz kalmaz.
 *
 * Güvenlik: onaylı bir kategori (100 liste + general + uncategorized) ASLA
 * silinmez, id'si gönderilse bile.
 *
 * POST (cache'lenmesin diye). Panele girişli admin çalıştırır.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('yetkisiz'); }
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-LiteSpeed-Cache-Control: no-cache');

ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();

/* Onaylı ~100 kategori (üreticiyle aynı liste) + korunan sistem kategorileri. */
$cats_list = 'philosophy,philosophy_of_religion,ethics,metaphysics,epistemology,logic,aesthetics,political_philosophy,history_of_philosophy,religion,theology,systematic_theology,christian_theology,islamic_theology,christianity,islam,judaism,buddhism,hinduism,atheism,agnosticism,history,world_history,ancient_history,medieval_history,modern_history,military_history,cultural_history,biography,autobiography,memoir,literature,classic_literature,world_literature,poetry,drama,novel,fiction,historical_fiction,science_fiction,dystopian_fiction,fantasy,horror,mystery,detective_fiction,romance,adventure,psychology,cognitive_psychology,social_psychology,psychoanalysis,sociology,anthropology,politics,political_science,economics,microeconomics,macroeconomics,education,law,international_law,science,physics,astronomy,chemistry,mathematics,statistics,biology,evolution,genetics,medicine,neuroscience,public_health,technology,computers,artificial_intelligence,programming,data_science,art,art_history,music,music_history,architecture,design,photography,film,theatre,geography,travel,culture,mythology,folklore,children,young_adult,self_help,personal_development,business,management,marketing,entrepreneurship';

$approved = array_fill_keys( array_map('trim', explode(',', $cats_list)), true );
$approved['general']       = true;   // sistem kovası — korunur
$approved['uncategorized'] = true;   // WordPress varsayılanı — korunur
// Hem tire hem alt-çizgi varyantlarını onayla.
foreach (array_keys($approved) as $s) { $approved[str_replace('_', '-', $s)] = true; }

function ca_is_rogue($slug, $approved) {
    $s = strtolower($slug);
    return empty($approved[$s]) && empty($approved[str_replace('-', '_', $s)]);
}

$action = $_REQUEST['action'] ?? 'list';

/* ── Kaçak kategorileri listele ── */
if ($action === 'list') {
    $terms = get_terms([ 'taxonomy' => 'category', 'hide_empty' => false ]);
    $rogue = [];
    if (!is_wp_error($terms)) {
        foreach ($terms as $t) {
            if (ca_is_rogue($t->slug, $approved)) {
                $rogue[] = [
                    'id'    => (int) $t->term_id,
                    'name'  => $t->name,
                    'slug'  => $t->slug,
                    'count' => (int) $t->count,
                ];
            }
        }
    }
    usort($rogue, fn($a, $b) => $b['count'] - $a['count']);
    echo json_encode([ 'ok' => true, 'rogue' => $rogue, 'rogue_count' => count($rogue) ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Seçilen kaçak kategorileri sil (yazıları korumaya alarak) ── */
if ($action === 'delete') {
    $ids = $_REQUEST['ids'] ?? '';
    $ids = is_array($ids) ? $ids : explode(',', (string) $ids);
    $ids = array_values(array_filter(array_map('intval', $ids)));

    // "General" kovasını hazırla (yoksa oluştur).
    $general = get_term_by('slug', 'general', 'category');
    if ($general && !is_wp_error($general)) {
        $general_id = (int) $general->term_id;
    } else {
        $r = wp_insert_term('General', 'category', [ 'slug' => 'general' ]);
        $general_id = is_wp_error($r) ? 0 : (int) $r['term_id'];
    }

    $deleted = []; $failed = []; $reassigned = 0;

    foreach ($ids as $tid) {
        $term = get_term($tid, 'category');
        if (!$term || is_wp_error($term)) { $failed[] = $tid; continue; }
        // GÜVENLİK: onaylı kategori asla silinmez.
        if (!ca_is_rogue($term->slug, $approved)) { $failed[] = $tid; continue; }

        // Bu kategorideki tüm yazılar.
        $posts = get_posts([
            'post_type'   => [ 'post', 'analysis' ],
            'post_status' => 'any',
            'numberposts' => -1,
            'fields'      => 'ids',
            'tax_query'   => [[ 'taxonomy' => 'category', 'field' => 'term_id', 'terms' => $tid ]],
        ]);

        foreach ($posts as $pid) {
            $cur  = wp_get_post_categories($pid);           // mevcut term_id'ler
            $rest = array_values(array_diff($cur, [ $tid ])); // bu kategoriyi çıkar

            // Kalanlar arasında onaylı (kaçak olmayan) kategori var mı?
            $has_ok = false;
            foreach ($rest as $rid) {
                $rt = get_term($rid, 'category');
                if ($rt && !is_wp_error($rt) && !ca_is_rogue($rt->slug, $approved)) { $has_ok = true; break; }
            }
            if (!$has_ok && $general_id) { $rest[] = $general_id; $reassigned++; }

            wp_set_post_categories($pid, $rest, false);     // false = tümünü değiştir
        }

        $del = wp_delete_term($tid, 'category');
        if ($del === true) { $deleted[] = [ 'id' => $tid, 'name' => $term->name ]; }
        else { $failed[] = $tid; }
    }

    echo json_encode([
        'ok'            => true,
        'deleted'       => $deleted,
        'deleted_count' => count($deleted),
        'reassigned'    => $reassigned,   // General'e bağlanan yazı sayısı
        'failed'        => $failed,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([ 'ok' => false, 'error' => 'bad action' ]);
