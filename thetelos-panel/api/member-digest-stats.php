<?php
/**
 * member-digest-stats.php — Üye mailleri ANALİTİĞİ (salt okunur). Panel oturumu gerekir.
 * Kaynaklar (tema, inc/member-digest.php + inc/post-views.php):
 *   tls_digest_sends        [tarih, sayı, üye]            — her gerçek gönderim
 *   tls_digest_click_log    [tarih, sayı, üye, kitap, bot] — maildeki her tıklama
 *   tls_views_daily_total   [gün => okunma]                — sitenin günlük toplam okunması
 * Takipten önceki gönderimler üyelerin "son gönderim" kaydından geri doldurulur.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('Önce panele giriş yapın.'); }
session_write_close();
ob_start(); require_once '/home/thetelos/public_html/wp-load.php'; ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$sends  = get_option('tls_digest_sends', []);      if (!is_array($sends))  $sends = [];
$clicks = get_option('tls_digest_click_log', []);  if (!is_array($clicks)) $clicks = [];

// Geri doldurma: kayıt tutulmadan önce gönderilmiş mailler (üye meta'sından).
$seen = [];
foreach ($sends as $r) $seen[(int) $r[2] . '|' . (int) $r[1]] = 1;
if (function_exists('tls_digest_member_ids')) {
    foreach (tls_digest_member_ids() as $uid) {
        $ls = (string) get_user_meta($uid, '_tls_digest_last_sent', true);
        if ($ls === '') continue;
        $li = (int) get_user_meta($uid, '_tls_digest_last_issue', true) ?: 1;
        if (!isset($seen[$uid . '|' . $li])) { $sends[] = [$ls, $li, (int) $uid, 'eski']; $seen[$uid . '|' . $li] = 1; }
    }
}

$users = [];
$uinfo = function ($uid) use (&$users) {
    if (!isset($users[$uid])) {
        $u = get_userdata($uid);
        $users[$uid] = $u ? ['name' => $u->display_name ?: $u->user_login, 'email' => $u->user_email] : ['name' => '#' . $uid, 'email' => ''];
    }
    return $users[$uid];
};
$titles = [];
$title = function ($pid) use (&$titles) {
    if ($pid <= 0) return 'Ana sayfa (Browse the library)';
    if (!isset($titles[$pid])) $titles[$pid] = html_entity_decode(get_the_title($pid), ENT_QUOTES, 'UTF-8') ?: ('#' . $pid);
    return $titles[$pid];
};

// Şüpheli (otomatik tarayıcı): bot UA YA DA aynı üye+sayı için 10 sn içinde 3+ tıklama.
$grp = [];
foreach ($clicks as $i => $c) $grp[(int) $c[2] . '|' . (int) $c[1]][] = $i;
$sus = [];
foreach ($grp as $idxs) {
    $ts = array_map(fn($i) => strtotime($clicks[$i][0]), $idxs); sort($ts);
    $burst = count($ts) >= 3 && ($ts[2] - $ts[0]) <= 10;
    foreach ($idxs as $i) $sus[$i] = !empty($clicks[$i][4]) || $burst;
}

// Sayı (issue) bazında özet
$issues = [];
foreach ($sends as $r) {
    $n = (int) $r[1];
    $issues[$n] ??= ['issue' => $n, 'date' => $r[0], 'sent' => 0, 'clickers' => [], 'clicks' => 0, 'books' => []];
    $issues[$n]['sent']++;
    if ($r[0] < $issues[$n]['date']) $issues[$n]['date'] = $r[0];
}
foreach ($clicks as $i => $c) {
    if ($sus[$i]) continue;
    $n = (int) $c[1];
    $issues[$n] ??= ['issue' => $n, 'date' => $c[0], 'sent' => 0, 'clickers' => [], 'clicks' => 0, 'books' => []];
    $issues[$n]['clicks']++;
    $issues[$n]['clickers'][(int) $c[2]] = 1;
    $t = $title((int) $c[3]);
    $issues[$n]['books'][$t] = ($issues[$n]['books'][$t] ?? 0) + 1;
}
foreach ($issues as &$is) {
    $is['unique'] = count($is['clickers']); unset($is['clickers']);
    $is['rate'] = $is['sent'] ? round($is['unique'] * 100 / $is['sent']) : 0;
    arsort($is['books']); $is['books'] = array_slice($is['books'], 0, 5, true);
}
unset($is);
krsort($issues);

// Tıklama listesi (yeniden eskiye)
$list = [];
foreach (array_reverse($clicks, true) as $i => $c) {
    $u = $uinfo((int) $c[2]);
    $list[] = ['time' => $c[0], 'issue' => (int) $c[1], 'uid' => (int) $c[2], 'name' => $u['name'], 'email' => $u['email'],
               'book' => $title((int) $c[3]), 'url' => (int) $c[3] > 0 ? get_permalink((int) $c[3]) : home_url('/'), 'suspect' => $sus[$i]];
}

// Üye bazında
$mem = [];
foreach ($sends as $r) { $id = (int) $r[2]; $mem[$id] ??= ['sent' => 0, 'clicks' => 0, 'last_click' => '']; $mem[$id]['sent']++; }
foreach ($clicks as $i => $c) {
    if ($sus[$i]) continue;
    $id = (int) $c[2]; $mem[$id] ??= ['sent' => 0, 'clicks' => 0, 'last_click' => ''];
    $mem[$id]['clicks']++; if ($c[0] > $mem[$id]['last_click']) $mem[$id]['last_click'] = $c[0];
}
$members = [];
foreach ($mem as $id => $m) { $u = $uinfo($id); $members[] = $m + ['uid' => $id, 'name' => $u['name'], 'email' => $u['email']]; }
usort($members, fn($a, $b) => $b['clicks'] <=> $a['clicks'] ?: $b['sent'] <=> $a['sent']);

// Günlük seri (son 45 gün): mail tıklaması + sitenin toplam okunması + o gün gönderilen mail
$days = []; $tot = (array) get_option('tls_views_daily_total', []);
for ($k = 44; $k >= 0; $k--) { $d = wp_date('Y-m-d', time() - $k * DAY_IN_SECONDS); $days[$d] = ['day' => $d, 'clicks' => 0, 'views' => (int) ($tot[$d] ?? 0), 'sent' => 0]; }
foreach ($clicks as $i => $c) { $d = substr($c[0], 0, 10); if (isset($days[$d]) && !$sus[$i]) $days[$d]['clicks']++; }
foreach ($sends as $r) { $d = substr($r[0], 0, 10); if (isset($days[$d])) $days[$d]['sent']++; }

$all_clickers = [];
foreach ($clicks as $i => $c) if (!$sus[$i]) $all_clickers[(int) $c[2]] = 1;
$real_clicks = count(array_filter($sus, fn($v) => !$v));
echo json_encode([
    'ok' => true,
    'totals' => ['issues' => count($issues), 'mails' => count($sends), 'clickers' => count($all_clickers),
                 'clicks' => $real_clicks, 'suspect' => count($clicks) - $real_clicks,
                 'views_tracked_since' => $tot ? array_key_first($tot) : ''],
    'issues' => array_values($issues), 'clicks' => $list, 'members' => $members, 'days' => array_values($days),
], JSON_UNESCAPED_UNICODE);
