<?php
/**
 * Thetelos – Akıllı Kategori Birleştirici
 */

/* wp-load.php'yi bul — themes/mediumish/ → themes/ → wp-content/ → ABSPATH */
$base = dirname(__FILE__);
$candidates = [
    $base . '/../../../../wp-load.php',   // wp-content/themes/mediumish/
    $base . '/../../../wp-load.php',      // wp-content/themes/
    $base . '/../../wp-load.php',         // wp-content/
    $base . '/../wp-load.php',            // kök
];
$loaded = false;
foreach ($candidates as $path) {
    if (file_exists($path)) {
        require_once($path);
        $loaded = true;
        break;
    }
}
if (!$loaded) die('wp-load.php bulunamadı. Dosyayı manuel olarak düzenleyin.');
if (!current_user_can('manage_options')) wp_die('Yetkisiz erişim. Lütfen WordPress admin olarak giriş yapın.');

/* Tüm kategorileri çek */
$all_cats = get_terms(['taxonomy'=>'category','hide_empty'=>false,'number'=>0]);

/* Slug'ları normalize et: boşluk, alt çizgi, tire hepsini tireye çevir */
function normalize_slug($slug) {
    return strtolower(trim(preg_replace('/[\s_]+/', '-', $slug)));
}

/* Normalize slug'a göre grupla */
$groups = [];
foreach ($all_cats as $cat) {
    $key = normalize_slug($cat->slug);
    $groups[$key][] = $cat;
}

$log = [];
$merged = 0;

foreach ($groups as $key => $cats) {
    if (count($cats) <= 1) continue; /* Tekil — atla */

    /* En çok postu olan kazanır */
    usort($cats, fn($a,$b) => $b->count - $a->count);
    $winner = $cats[0];
    $losers = array_slice($cats, 1);

    foreach ($losers as $loser) {
        /* Bu kategorideki tüm postları bul */
        $posts = get_posts([
            'numberposts' => -1,
            'post_type'   => 'any',
            'post_status' => 'any',
            'category'    => $loser->term_id,
            'fields'      => 'ids',
        ]);

        foreach ($posts as $pid) {
            $current = wp_get_post_categories($pid);
            $current = array_diff($current, [$loser->term_id]);
            $current[] = $winner->term_id;
            wp_set_post_categories($pid, array_unique($current));
        }

        wp_delete_term($loser->term_id, 'category');
        $log[] = "✅ <b>'{$loser->slug}'</b> ({$loser->count} post) → <b>'{$winner->slug}'</b> kategorisine taşındı ve silindi.";
        $merged++;
    }
}

if ($merged === 0) $log[] = "✅ Çift kategori bulunamadı, her şey temiz!";
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Kategori Birleştirici</title>
<style>
body{font-family:system-ui,sans-serif;padding:40px;background:#f5f5f5;max-width:800px;margin:0 auto;}
h2{color:#1a1a2a;}
.log{background:#fff;padding:20px 28px;border-radius:8px;border:1px solid #ddd;line-height:2.2;}
.note{margin-top:16px;color:#888;font-size:13px;}
</style></head><body>
<h2>🔀 Thetelos – Kategori Birleştirici</h2>
<div class="log"><?php echo implode("<br>", $log); ?></div>
<p class="note">⚠️ Bu dosyayı sunucudan silebilirsiniz.</p>
</body></html>
