<?php
/**
 * member-digest.php (API) — Üyelere kişisel okuma önerisi maili. Panel oturumu gerekir.
 *   GET  ?action=list               → üyeler + öneri sayıları (değişiklik yapmaz)
 *   GET  ?action=preview&uid=N      → o üyenin mailinin HTML önizlemesi
 *   POST action=test&uid=N          → o üyenin maili YALNIZ test adresine (TLS_DIGEST_TEST_TO) gider
 *   POST action=send&uids=1,2,3     → seçilen üyelere gerçek gönderim (çıkış yapanlar atlanır)
 * Mantık temada: inc/member-digest.php
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('Önce panele giriş yapın.'); }
session_write_close();
ob_start(); require_once '/home/thetelos/public_html/wp-load.php'; ob_end_clean();
@set_time_limit(300);

$action = $_REQUEST['action'] ?? 'list';
const TLS_DIGEST_TEST_TO = 'thetelosorg@gmail.com';   // test mailleri (kullanıcı isteği 23.11.2026)
if (!function_exists('tls_digest_build')) { header('Content-Type: application/json'); exit(json_encode(['ok' => false, 'error' => 'tema modülü yüklenmemiş (inc/member-digest.php)'])); }

if ($action === 'preview') {
    $d = tls_digest_build((int) ($_GET['uid'] ?? 0));
    header('Content-Type: text/html; charset=utf-8');
    if (!$d) exit('Üye bulunamadı.');
    if (!$d['recs'] && !$d['reminders']) exit('<p style="font-family:sans-serif">Bu üye için önerilecek kitap bulunamadı.</p>');
    exit(tls_digest_html($d));
}

header('Content-Type: application/json; charset=utf-8');

if ($action === 'list') {
    $out = [];
    foreach (tls_digest_member_ids() as $uid) {
        $d = tls_digest_build((int) $uid);
        if (!$d) continue;
        $out[] = [
            'uid' => $d['uid'], 'name' => $d['name'], 'email' => $d['email'], 'optout' => $d['optout'], 'optout_at' => $d['optout_at'] ?? '', 'log' => $d['log'] ?? [],
            'recs' => count($d['recs']), 'reminders' => count($d['reminders']),
            'basis' => implode(' · ', $d['basis']) ?: '— veri yok', 'has_data' => $d['has_data'],
            'last_sent' => $d['last_sent'],
        ];
    }
    usort($out, fn($a, $b) => ($b['has_data'] <=> $a['has_data']) ?: strcmp($a['name'], $b['name']));
    exit(json_encode(['ok' => true, 'admin_email' => TLS_DIGEST_TEST_TO, 'members' => $out], JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(json_encode(['ok' => false, 'error' => 'POST gerekli'])); }

if ($action === 'test') {
    // Test adresi panelden değiştirilebilir (ör. mail-tester.com spam testi); boşsa varsayılan.
    $to = trim((string) ($_POST['to'] ?? '')); if (!is_email($to)) $to = TLS_DIGEST_TEST_TO;
    $r = tls_digest_send((int) ($_POST['uid'] ?? 0), $to);
    exit(json_encode($r + ['to' => $to], JSON_UNESCAPED_UNICODE));
}

if ($action === 'resub') {   // yöneticinin geri açması (ör. otomatik tarayıcı yanlışlıkla çıkardıysa)
    $uid = (int) ($_POST['uid'] ?? 0);
    if ($uid > 0 && get_userdata($uid)) {
        delete_user_meta($uid, '_tls_digest_optout');
        update_user_meta($uid, '_tls_digest_optin', current_time('mysql') . ' (panel)');
        if (function_exists('tls_digest_log')) tls_digest_log($uid, 'açtı', 'yönetici paneli');
        exit(json_encode(['ok' => true]));
    }
    exit(json_encode(['ok' => false, 'error' => 'üye yok']));
}

if ($action === 'bump') {   // tek tek gönderim bittikten sonra sayı bir kez artar
    update_option('tls_digest_issue', (int) get_option('tls_digest_issue', 0) + 1, false);
    exit(json_encode(['ok' => true]));
}

if ($action === 'send') {
    $uids = array_filter(array_map('intval', explode(',', (string) ($_POST['uids'] ?? ''))));
    $sent = 0; $fail = [];
    foreach ($uids as $uid) {
        $r = tls_digest_send($uid);
        if ($r['ok']) $sent++; else $fail[] = ['uid' => $uid, 'error' => $r['error']];
        if (count($uids) > 1) sleep(2);   // çoklu gönderimde yavaş git (panel artık tek tek gönderir, ara orada)
    }
    if ($sent > 0 && empty($_POST['no_bump'])) update_option('tls_digest_issue', (int) get_option('tls_digest_issue', 0) + 1, false);   // sayı (Issue Nº) gerçek gönderimde artar
    exit(json_encode(['ok' => true, 'sent' => $sent, 'failed' => $fail], JSON_UNESCAPED_UNICODE));
}

echo json_encode(['ok' => false, 'error' => 'bilinmeyen işlem']);
