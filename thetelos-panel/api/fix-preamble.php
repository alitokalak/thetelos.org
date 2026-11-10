<?php
/**
 * fix-preamble.php — Başına modelin arama notu sızmış yazıları bul / temizle.
 * ("I'll start by searching for information… I'll run one more search…# Başlık")
 *   ?            → listeler (değiştirmez)
 *   ?apply=1     → baştaki o paragrafı siler
 * Kimlik: panel oturumu veya Sesli Özet anahtarı (key=…), yalnız listeleme için.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
$key_ok = hash_equals(hash('sha256', WP_APP_PASS . '|tls-tts'), (string) ($_GET['key'] ?? ''));
$sess = !empty($_SESSION['tls_auth']);
if (!$sess && !$key_ok) { http_response_code(401); exit; }
session_write_close();
ob_start(); require_once '/home/thetelos/public_html/wp-load.php'; ob_end_clean();
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(300);

$re = '/^\s*(?:<p[^>]*>)?\s*(?:I[\'’]ll|I will|I[\'’]m going to|I am going to|Let me|First,? I[\'’]ll|Now I[\'’]ll|I need to)\b[^<]{0,600}?(?:search|look|find|check|research|gather|run|verify)/iu';
global $wpdb;
$rows = $wpdb->get_results("SELECT ID, post_title, LEFT(post_content, 900) head FROM {$wpdb->posts}
    WHERE post_type IN ('post','analysis') AND post_status IN ('publish','future')
      AND (LEFT(post_content, 60) LIKE '%I%ll %' OR LEFT(post_content, 60) LIKE '%Let me %' OR LEFT(post_content, 60) LIKE '%I will %'
           OR LEFT(post_content, 60) LIKE '%going to %' OR LEFT(post_content, 60) LIKE '%I need to %')");
$hits = [];
foreach ($rows as $r) if (preg_match($re, $r->head)) $hits[] = $r;
echo "Bulunan: " . count($hits) . " yazı\n\n";
$apply = $sess && !empty($_GET['apply']);
$fixed = 0;
foreach ($hits as $r) {
    echo "#{$r->ID}  " . html_entity_decode($r->post_title, ENT_QUOTES, 'UTF-8') . "\n    " . mb_substr(wp_strip_all_tags($r->head), 0, 160) . "\n";
    if (!$apply) continue;
    $c = (string) get_post_field('post_content', $r->ID);
    // Baştaki ilk paragrafı (not + yapışık "# Başlık") sil; paragraf yoksa ilk satırı.
    $new = preg_replace('/^\s*<p[^>]*>.*?<\/p>\s*/is', '', $c, 1, $n);
    if (!$n) $new = preg_replace('/^[^\n]*\n/', '', $c, 1, $n);
    if ($n && trim($new) !== '') {
        wp_update_post(['ID' => $r->ID, 'post_content' => $new]);
        if (function_exists('litespeed_purge_single_post')) litespeed_purge_single_post($r->ID);
        $fixed++;
    }
}
echo $apply ? "\nTEMİZLENDİ ✓  $fixed yazı\n" : "\nTemizlemek için (panele girişliyken) adresin sonuna ?apply=1 ekleyin.\n";
