<?php
/**
 * member-digest.php (API) — Üyelere kişisel okuma önerisi maili. Panel oturumu gerekir.
 *   GET  ?action=list               → üyeler + öneri sayıları (değişiklik yapmaz)
 *   GET  ?action=preview&uid=N      → o üyenin mailinin HTML önizlemesi
 *   POST action=test&uid=N          → o üyenin maili YALNIZ yöneticiye (site admin e-postası) gider
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
            'uid' => $d['uid'], 'name' => $d['name'], 'email' => $d['email'], 'optout' => $d['optout'],
            'recs' => count($d['recs']), 'reminders' => count($d['reminders']),
            'basis' => implode(' · ', $d['basis']) ?: '— veri yok', 'has_data' => $d['has_data'],
            'last_sent' => $d['last_sent'],
        ];
    }
    usort($out, fn($a, $b) => ($b['has_data'] <=> $a['has_data']) ?: strcmp($a['name'], $b['name']));
    exit(json_encode(['ok' => true, 'admin_email' => get_option('admin_email'), 'members' => $out], JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(json_encode(['ok' => false, 'error' => 'POST gerekli'])); }

if ($action === 'test') {
    $r = tls_digest_send((int) ($_POST['uid'] ?? 0), (string) get_option('admin_email'));
    exit(json_encode($r + ['to' => get_option('admin_email')], JSON_UNESCAPED_UNICODE));
}

if ($action === 'send') {
    $uids = array_filter(array_map('intval', explode(',', (string) ($_POST['uids'] ?? ''))));
    $sent = 0; $fail = [];
    foreach ($uids as $uid) {
        $r = tls_digest_send($uid);
        if ($r['ok']) $sent++; else $fail[] = ['uid' => $uid, 'error' => $r['error']];
        usleep(300000);   // SMTP'yi yormamak için kısa ara
    }
    exit(json_encode(['ok' => true, 'sent' => $sent, 'failed' => $fail], JSON_UNESCAPED_UNICODE));
}

echo json_encode(['ok' => false, 'error' => 'bilinmeyen işlem']);
