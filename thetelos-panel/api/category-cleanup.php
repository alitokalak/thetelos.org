<?php
/**
 * category-cleanup.php — Kategori enflasyonunu temizler.
 *
 * SORUN: Zaman içinde 200+ kategori birikti; "Veterinary", "Construction" gibi
 * 1-2 yazılık tekil kategoriler crawl bütçesi yiyor ve spam görüntüsü veriyor.
 *
 * ÇÖZÜM: Site, başlangıçta belirlenen ÇEKİRDEK listeye (aşağıdaki ~100 slug)
 * indirgenir. Çekirdek dışındaki her kategori için en uygun çekirdek hedef
 * bulunur (anahtar kelime eşleşmesi + gerekirse AI), yazılar oraya taşınır ve
 * boşalan kategori silinir. Hiçbir yazı kategorisiz kalmaz.
 *
 * POST action=list         → tüm kategoriler + sayı + çekirdek/fazla ayrımı + hedef önerisi
 * POST action=merge        → {from_id,to_id} yazıları taşı, kaynağı sil
 * POST action=merge_bulk   → [{from_id,to_id},...] toplu taşı
 * POST action=delete_empty → yazısı olmayan çekirdek-dışı kategorileri sil
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

/** Sitenin sabit çekirdek kategori listesi (üretici de bunu palet olarak kullanır). */
function cc_core_slugs() {
    return array_map('trim', explode(',', 'philosophy,philosophy_of_religion,ethics,metaphysics,epistemology,logic,aesthetics,political_philosophy,history_of_philosophy,religion,theology,systematic_theology,christian_theology,islamic_theology,christianity,islam,judaism,buddhism,hinduism,atheism,agnosticism,history,world_history,ancient_history,medieval_history,modern_history,military_history,cultural_history,biography,autobiography,memoir,literature,classic_literature,world_literature,poetry,drama,novel,fiction,historical_fiction,science_fiction,dystopian_fiction,fantasy,horror,mystery,detective_fiction,romance,adventure,psychology,cognitive_psychology,social_psychology,psychoanalysis,sociology,anthropology,politics,political_science,economics,microeconomics,macroeconomics,education,law,international_law,science,physics,astronomy,chemistry,mathematics,statistics,biology,evolution,genetics,medicine,neuroscience,public_health,technology,computers,artificial_intelligence,programming,data_science,art,art_history,music,music_history,architecture,design,photography,film,theatre,geography,travel,culture,mythology,folklore,children,young_adult,self_help,personal_development,business,management,marketing,entrepreneurship'));
}

/** WP slug'ları tire kullanır; çekirdek liste alt çizgi. İkisini de karşılaştırabilmek için normalize et. */
function cc_norm($s) { return str_replace('_', '-', strtolower(trim((string)$s))); }

/** Çekirdek dışı bir kategori için en uygun çekirdek hedefi anahtar kelimeyle bul. */
function cc_guess_target($cat, $core_terms) {
    $name = cc_norm($cat->name . ' ' . $cat->slug);
    $words = array_filter(preg_split('/[^a-z0-9]+/', $name), fn($w) => strlen($w) >= 4);

    $best = null; $best_score = 0;
    foreach ($core_terms as $ct) {
        $cname = cc_norm($ct->name . ' ' . $ct->slug);
        $score = 0;
        // Birebir/parça içerme en güçlü sinyal
        foreach ($words as $w) {
            if (strpos($cname, $w) !== false) $score += 3;
            elseif (strlen($w) >= 6 && strpos($cname, substr($w, 0, 5)) !== false) $score += 1;
        }
        if ($score > $best_score) { $best_score = $score; $best = $ct; }
    }
    // Konu ailesi kaba eşlemesi (anahtar kelime tutmayanlar için)
    if (!$best) {
        $fam = [
            'science'    => ['veterinar','agricultur','engineer','construct','nutrition','environment','ecolog','zoolog','botan','geolog','climate'],
            'technology' => ['software','internet','robot','cyber','digital','comput','machine'],
            'business'   => ['finance','account','trade','industry','manufactur','commerce','startup','leadership'],
            'psychology' => ['emotion','behaviour','behavior','therap','mental'],
            'sociology'  => ['gender','feminis','race','urban','migration','communit'],
            'politics'   => ['govern','democra','war','peace','diplomac','revolution','commun','social'],
            'religion'   => ['spiritual','mystic','saint','church','bible','quran','prayer'],
            'literature' => ['essay','story','tale','narrat','writing','author'],
            'history'    => ['century','empire','civiliz','antiqu','war'],
            'art'        => ['paint','sculpt','craft','fashion','cinema'],
            'medicine'   => ['health','disease','anatom','surg','clinic'],
            'education'  => ['teach','learn','pedagog','school','universit'],
        ];
        foreach ($fam as $target_slug => $keys) {
            foreach ($keys as $k) {
                if (strpos($name, $k) !== false) {
                    foreach ($core_terms as $ct) {
                        if (cc_norm($ct->slug) === cc_norm($target_slug)) return $ct;
                    }
                }
            }
        }
    }
    return $best;
}

/** Yazıları kaynaktan hedefe taşı, kaynağı sil. */
function cc_merge($from_id, $to_id) {
    $from = get_term($from_id, 'category');
    $to   = get_term($to_id, 'category');
    if (!$from || is_wp_error($from) || !$to || is_wp_error($to) || $from_id === $to_id) return 0;

    $posts = get_posts([
        'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1,
        'fields' => 'ids', 'cat' => $from_id, 'suppress_filters' => true,
    ]);
    foreach ($posts as $pid) {
        wp_set_object_terms($pid, [(int)$to_id], 'category', true);    // hedefi ekle
        wp_remove_object_terms($pid, [(int)$from_id], 'category');     // kaynağı çıkar
    }
    wp_delete_term($from_id, 'category');
    return count($posts);
}

$action = $_POST['action'] ?? '';

/* ── Listele: kategoriler + çekirdek/fazla + hedef önerisi ── */
if ($action === 'list') {
    $core_slugs = array_map('cc_norm', cc_core_slugs());
    $all = get_categories(['hide_empty' => false]);

    $core_terms = [];
    foreach ($all as $c) {
        if (in_array(cc_norm($c->slug), $core_slugs, true)) $core_terms[] = $c;
    }

    $rows = [];
    foreach ($all as $c) {
        $is_core = in_array(cc_norm($c->slug), $core_slugs, true);
        $row = [
            'id' => (int)$c->term_id, 'name' => $c->name, 'slug' => $c->slug,
            'count' => (int)$c->count, 'core' => $is_core,
            'target_id' => 0, 'target_name' => '',
        ];
        if (!$is_core && cc_norm($c->slug) !== 'general' && cc_norm($c->slug) !== 'uncategorized') {
            $t = cc_guess_target($c, $core_terms);
            if ($t) { $row['target_id'] = (int)$t->term_id; $row['target_name'] = $t->name; }
        }
        $rows[] = $row;
    }
    // Fazlalar önce, az yazılı olan en üstte
    usort($rows, function ($a, $b) {
        if ($a['core'] !== $b['core']) return $a['core'] ? 1 : -1;
        return $a['count'] <=> $b['count'];
    });

    $core_n  = count(array_filter($rows, fn($r) => $r['core']));
    echo json_encode(['ok'=>true, 'rows'=>$rows, 'total'=>count($rows),
        'core'=>$core_n, 'extra'=>count($rows)-$core_n,
        'core_list'=>array_map(fn($t)=>['id'=>(int)$t->term_id,'name'=>$t->name], $core_terms),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Tek birleştirme ── */
if ($action === 'merge') {
    $moved = cc_merge((int)($_POST['from_id'] ?? 0), (int)($_POST['to_id'] ?? 0));
    echo json_encode(['ok'=>true, 'moved'=>$moved]);
    exit;
}

/* ── Toplu birleştirme (parça parça çağrılır) ── */
if ($action === 'merge_bulk') {
    $pairs = json_decode((string)($_POST['pairs'] ?? '[]'), true);
    $done = 0; $moved = 0;
    foreach ((array)$pairs as $p) {
        $m = cc_merge((int)($p['from_id'] ?? 0), (int)($p['to_id'] ?? 0));
        if ($m !== 0 || !get_term((int)($p['from_id'] ?? 0), 'category')) $done++;
        $moved += $m;
    }
    echo json_encode(['ok'=>true, 'merged'=>$done, 'moved'=>$moved]);
    exit;
}

/* ── Boş (yazısız) çekirdek-dışı kategorileri sil ── */
if ($action === 'delete_empty') {
    $core_slugs = array_map('cc_norm', cc_core_slugs());
    $deleted = [];
    foreach (get_categories(['hide_empty' => false]) as $c) {
        if (in_array(cc_norm($c->slug), $core_slugs, true)) continue;
        if (cc_norm($c->slug) === 'general' || cc_norm($c->slug) === 'uncategorized') continue;
        if ((int)$c->count > 0) continue;
        wp_delete_term((int)$c->term_id, 'category');
        $deleted[] = $c->name;
    }
    echo json_encode(['ok'=>true, 'deleted'=>count($deleted), 'names'=>$deleted], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok'=>false, 'error'=>'bad action']);
