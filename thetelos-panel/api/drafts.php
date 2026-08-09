<?php
/**
 * drafts.php — TASLAKTA KALAN yazıları bulup tek tıkla yayına al.
 *
 * Eski (buggy) turlarda bazı gerçek yazılar yanlışlıkla taslağa çekilmişti.
 * Bunlar ön yüzde görünmez (404). Bu uç nokta WordPress'teki TÜM taslakları
 * listeler (özet + analiz) ve seçileni / hepsini yayına alır. "Bir şey olmuş
 * ama hangisi bilmiyorum" durumunu bitirir.
 *
 *   ?action=list          → JSON: taslaktaki yazılar [{id,type,title,link,modified}]
 *   ?action=publish&id&type  → tek yazıyı yayına al
 *   ?action=publish_all   → listedeki hepsini yayına al (POST)
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$auth   = 'Basic ' . base64_encode(WP_USER . ':' . WP_APP_PASS);
$wp_api = rtrim(WP_URL, '/') . '/wp-json/wp/v2';

function dr_wp($url, $method, $body, $auth, $timeout = 30) {
    $ch = curl_init($url);
    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $auth, 'Content-Type: application/json'],
    ];
    if ($body !== null) $opt[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
    curl_setopt_array($ch, $opt);
    $r = curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [json_decode((string) $r, true), $c];
}

/* Taslakları topla: hem 'posts' (özet) hem 'analysis' (derin analiz). */
function dr_collect($wp_api, $auth) {
    $out = [];
    foreach (['posts', 'analysis'] as $ep) {
        for ($pg = 1; $pg <= 5; $pg++) {   // en çok 5×100 = 500 taslak/tip
            [$res, $code] = dr_wp(
                "$wp_api/$ep?status=draft&per_page=100&page=$pg&_fields=id,title,link,modified,status",
                'GET', null, $auth, 25
            );
            if ($code !== 200 || !is_array($res) || !$res) break;
            foreach ($res as $p) {
                $out[] = [
                    'id'       => (int) ($p['id'] ?? 0),
                    'type'     => $ep,
                    'title'    => is_array($p['title'] ?? null) ? ($p['title']['rendered'] ?? '') : (string) ($p['title'] ?? ''),
                    'link'     => (string) ($p['link'] ?? ''),
                    'modified' => (string) ($p['modified'] ?? ''),
                ];
            }
            if (count($res) < 100) break;
        }
    }
    // En son değişen üstte
    usort($out, fn($a, $b) => strcmp($b['modified'], $a['modified']));
    return $out;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'publish') {
    $id   = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    $type = ($_POST['type'] ?? $_GET['type'] ?? 'posts') === 'analysis' ? 'analysis' : 'posts';
    if (!$id) { echo json_encode(['ok' => false, 'error' => 'id gerekli']); exit; }
    [$res, $code] = dr_wp("$wp_api/$type/$id", 'POST', ['status' => 'publish'], $auth, 30);
    echo json_encode([
        'ok'   => ($code >= 200 && $code < 300),
        'code' => $code,
        'link' => $res['link'] ?? '',
    ]);
    exit;
}

if ($action === 'publish_all') {
    $items = dr_collect($wp_api, $auth);
    $done = 0; $fail = 0;
    foreach ($items as $it) {
        [$res, $code] = dr_wp("$wp_api/{$it['type']}/{$it['id']}", 'POST', ['status' => 'publish'], $auth, 30);
        if ($code >= 200 && $code < 300) $done++; else $fail++;
    }
    echo json_encode(['ok' => true, 'published' => $done, 'failed' => $fail, 'total' => count($items)]);
    exit;
}

// action=list
$items = dr_collect($wp_api, $auth);
echo json_encode(['ok' => true, 'total' => count($items), 'items' => array_slice($items, 0, 300)], JSON_UNESCAPED_UNICODE);
