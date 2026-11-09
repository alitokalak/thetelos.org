<?php
/**
 * seo-inventory.php — YALNIZ OKUR, hiçbir şeyi değiştirmez.
 * Her yayındaki yazı için: id, slug, yayın tarihi, sitedeki okunma sayısı,
 * şu an noindex olup olmadığı. Hangi sayfaların hiç ziyaret almadığını görmek için.
 * Kimlik: panel oturumu VEYA Sesli Özet anahtarı (key=…).
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
$key_ok = hash_equals(hash('sha256', WP_APP_PASS . '|tls-tts'), (string) ($_GET['key'] ?? ''));
if (empty($_SESSION['tls_auth']) && !$key_ok) { http_response_code(401); exit; }
session_write_close();
ob_start(); require_once '/home/thetelos/public_html/wp-load.php'; ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(120);

global $wpdb;
$rows = $wpdb->get_results("
    SELECT p.ID id, p.post_name slug, DATE(p.post_date) d,
           CAST(COALESCE(v.meta_value, '0') AS UNSIGNED) views,
           COALESCE(n.meta_value, '') noidx
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} v ON v.post_id = p.ID AND v.meta_key = 'post_views_count'
    LEFT JOIN {$wpdb->postmeta} n ON n.post_id = p.ID AND n.meta_key = '_yoast_wpseo_meta-robots-noindex'
    WHERE p.post_type = 'post' AND p.post_status = 'publish'
    GROUP BY p.ID", ARRAY_A);
$ph = function_exists('tls_placeholder_ids') ? array_flip(array_map('intval', (array) tls_placeholder_ids())) : [];
foreach ($rows as &$r) { $r['id'] = (int) $r['id']; $r['views'] = (int) $r['views']; $r['ph'] = isset($ph[$r['id']]) ? 1 : 0; }
echo json_encode(['ok' => true, 'n' => count($rows), 'rows' => $rows], JSON_UNESCAPED_UNICODE);
