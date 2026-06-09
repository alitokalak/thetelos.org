<?php
/**
 * Thetelos – Kategori Slug Düzeltici
 * Alt çizgili slug'ları tireli yapar.
 * Kullanım: themes/mediumish/ klasörüne at, bir kez aç, sil.
 */
$base = dirname(__FILE__);
foreach (['/../../../../wp-load.php','/../../../wp-load.php'] as $p) {
    if (file_exists($base.$p)) { require_once($base.$p); break; }
}
if (!function_exists('get_terms')) die('wp-load.php bulunamadı.');
if (!current_user_can('manage_options')) wp_die('Yetkisiz erişim.');

$all_cats = get_terms(['taxonomy'=>'category','hide_empty'=>false,'number'=>0]);
$log = [];

foreach ($all_cats as $cat) {
    if (strpos($cat->slug, '_') === false) continue; // alt çizgi yok, atla

    $new_slug = str_replace('_', '-', $cat->slug);

    // Bu slug zaten var mı?
    $existing = get_term_by('slug', $new_slug, 'category');
    if ($existing && $existing->term_id !== $cat->term_id) {
        $log[] = "⚠️ '{$cat->slug}' → '{$new_slug}' zaten mevcut, atlandı.";
        continue;
    }

    $result = wp_update_term($cat->term_id, 'category', ['slug' => $new_slug]);
    if (is_wp_error($result)) {
        $log[] = "❌ '{$cat->slug}' güncellenemedi: " . $result->get_error_message();
    } else {
        $log[] = "✅ <b>'{$cat->slug}'</b> → <b>'{$new_slug}'</b> olarak güncellendi.";
    }
}

if (empty($log)) $log[] = "✅ Alt çizgili slug bulunamadı, her şey temiz!";
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Slug Düzeltici</title>
<style>body{font-family:system-ui;padding:40px;background:#f5f5f5;max-width:800px;margin:0 auto;}
.log{background:#fff;padding:20px 28px;border-radius:8px;border:1px solid #ddd;line-height:2.2;}
.note{margin-top:16px;color:#888;font-size:13px;}</style>
</head><body>
<h2>🔧 Thetelos – Slug Düzeltici</h2>
<div class="log"><?php echo implode('<br>', $log); ?></div>
<p class="note">⚠️ Bu dosyayı sunucudan silebilirsiniz.</p>
</body></html>
