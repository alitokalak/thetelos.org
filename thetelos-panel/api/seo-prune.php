<?php
/**
 * seo-prune.php — Seçilmiş yazıları Google'dan gizle / geri aç (panel oturumu gerekir).
 * Liste: data-seo-prune-1.json (2026-11-09: sitede 0 okunma + Search Console'da tıklama/dizin kaydı yok).
 *   /thetelos-panel/api/seo-prune.php            → durum (değişiklik yapmaz)
 *   /thetelos-panel/api/seo-prune.php?apply=1    → listedekileri gizle
 *   /thetelos-panel/api/seo-prune.php?revert=1   → hepsini geri aç
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('Önce panele giriş yapın.'); }
session_write_close();
ob_start(); require_once '/home/thetelos/public_html/wp-load.php'; ob_end_clean();
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(300);

$list = json_decode((string) file_get_contents(__DIR__ . '/data-seo-prune-1.json'), true);
$ids  = array_map('intval', (array) ($list['ids'] ?? []));
global $wpdb;
$now = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tls_seo_pruned'");

if (!empty($_GET['revert'])) {
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_tls_seo_pruned'");
    delete_transient('tls_pruned_ids'); do_action('litespeed_purge_all');
    exit("GERİ ALINDI ✓  $now yazı tekrar Google'a açıldı.\n");
}
if (!empty($_GET['apply'])) {
    $n = 0;
    foreach ($ids as $id) {
        if (get_post_status($id) !== 'publish' || (int) get_post_meta($id, 'post_views_count', true) > 0) continue;   // bu arada okunduysa dokunma
        update_post_meta($id, '_tls_seo_pruned', '2026-11-09'); $n++;
    }
    delete_transient('tls_pruned_ids'); do_action('litespeed_purge_all');
    exit("TAMAM ✓  $n yazı Google'dan gizlendi (sitede okunmaya devam eder).\nGeri almak için: ?revert=1\n");
}
echo "Kural : " . ($list['rule'] ?? '') . "\nListede: " . count($ids) . " yazı\nŞu an gizli: $now\n\n"
   . "Uygulamak için adresin sonuna ?apply=1 ekleyin. Geri almak için ?revert=1.\n";
