<?php
/**
 * tts-live.php — Anlık sesli dinleme ayarları + sesi olan kitapların listesi (panel oturumu gerekir).
 *   GET  action=status → ayarlar (anahtarlar maskeli), bugünkü/toplam kullanım, son hata, sesli kitaplar
 *   POST action=save   → deepinfra_key, r2_key, r2_secret, voice, daily_chars (boş alan = eskisi kalır)
 * Anahtarlar sunucuda (WordPress ayarı) saklanır, repoya girmez.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
ob_start(); require_once '/home/thetelos/public_html/wp-load.php'; ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

function tl_mask($k) { $k = (string) $k; return $k === '' ? '' : (strlen($k) <= 10 ? '••••' : substr($k, 0, 4) . '••••' . substr($k, -4)); }

$cfg = get_option('tls_tts_live', []);
if (!is_array($cfg)) $cfg = [];
$action = $_POST['action'] ?? $_GET['action'] ?? 'status';

if ($action === 'save') {
    foreach (['deepinfra_key', 'r2_key', 'r2_secret'] as $f) {
        $v = trim((string) ($_POST[$f] ?? ''));
        if ($v !== '') $cfg[$f] = $v;
    }
    $cfg['enabled'] = !empty($_POST['enabled']) ? 1 : 0;
    $voice = (string) ($_POST['voice'] ?? '');
    if (in_array($voice, ['am_michael', 'af_heart', 'am_fenrir', 'af_bella', 'bm_george', 'bf_emma'], true)) $cfg['voice'] = $voice;
    if (isset($_POST['daily_chars']) && $_POST['daily_chars'] !== '') $cfg['daily_chars'] = max(10000, min(50000000, (int) $_POST['daily_chars']));
    update_option('tls_tts_live', $cfg, false);
    if (function_exists('litespeed_purge_all')) litespeed_purge_all();
    do_action('litespeed_purge_all');
    echo json_encode(['ok' => true]); exit;
}

global $wpdb;
$rows = $wpdb->get_results("SELECT p.ID, p.post_title, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m
    JOIN {$wpdb->posts} p ON p.ID = m.post_id
    WHERE m.meta_key IN ('_tls_audio_url', '_tls_live_parts') AND m.meta_value <> '' AND p.post_status = 'publish'
    ORDER BY m.meta_id DESC LIMIT 500");
$books = [];
foreach ($rows as $r) {
    $id = (int) $r->ID;
    if (!isset($books[$id])) $books[$id] = ['id' => $id, 'title' => html_entity_decode($r->post_title, ENT_QUOTES, 'UTF-8'), 'url' => get_permalink($id), 'kind' => '', 'seconds' => 0, 'parts' => ''];
    if ($r->meta_key === '_tls_audio_url') {
        $books[$id]['kind'] = 'hazır';
        $books[$id]['seconds'] = (int) get_post_meta($id, '_tls_audio_seconds', true);
    } elseif ($books[$id]['kind'] === '') {
        $d = maybe_unserialize($r->meta_value);
        $s = is_array($d) ? (array) ($d['s'] ?? []) : [];
        $books[$id]['kind'] = 'anlık';
        $books[$id]['seconds'] = (int) array_sum($s);
        $books[$id]['parts'] = count($s) . '/' . (int) ($d['n'] ?? count($s));
    }
}
$use = get_option('tls_tts_live_usage', []);
echo json_encode([
    'ok' => true,
    'enabled' => !empty($cfg['enabled']),
    'ready' => !empty($cfg['enabled']) && !empty($cfg['deepinfra_key']) && !empty($cfg['r2_key']) && !empty($cfg['r2_secret']),
    'deepinfra_key' => tl_mask($cfg['deepinfra_key'] ?? ''), 'r2_key' => tl_mask($cfg['r2_key'] ?? ''), 'r2_secret' => tl_mask($cfg['r2_secret'] ?? ''),
    'voice' => $cfg['voice'] ?? 'am_michael', 'daily_chars' => (int) ($cfg['daily_chars'] ?? 1500000),
    'today_chars' => (($use['d'] ?? '') === gmdate('Y-m-d')) ? (int) $use['c'] : 0,
    'total_chars' => (int) ($use['total'] ?? 0),
    'last_error' => (string) get_option('tls_tts_live_err', ''),
    'books' => array_values($books),
], JSON_UNESCAPED_UNICODE);
