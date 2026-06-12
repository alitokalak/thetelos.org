<?php
/**
 * author-check.php — Verilen yazar listesinden sitede zaten olanları döner.
 * POST: authors (JSON array of name strings)
 * Dönüş: { ok, on_site: ["Author Name", ...] }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
set_time_limit(60);

$authors = json_decode($_POST['authors'] ?? '[]', true);
if (!is_array($authors) || empty($authors)) {
    echo json_encode(['ok' => true, 'on_site' => []]); exit;
}

$auth   = 'Basic ' . base64_encode(WP_USER . ':' . WP_APP_PASS);
$wp_api = rtrim(WP_URL, '/') . '/wp-json/wp/v2';

function ac_wp_get($url, $auth) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: ' . $auth],
    ]);
    $r = curl_exec($ch); curl_close($ch);
    return json_decode($r, true) ?? [];
}

$on_site = [];
foreach (array_unique($authors) as $author) {
    $author = trim($author);
    if (!$author) continue;

    $terms = ac_wp_get("$wp_api/authors?search=" . urlencode($author) . '&per_page=5', $auth);
    $atid  = null;
    foreach ($terms as $t) {
        if (mb_strtolower($t['name'] ?? '') === mb_strtolower($author)) { $atid = $t['id']; break; }
    }
    if (!$atid && !empty($terms[0]['id'])) $atid = $terms[0]['id'];
    if (!$atid) continue;

    foreach (['posts', 'analysis'] as $ep) {
        $rows = ac_wp_get("$wp_api/$ep?authors=$atid&per_page=1&status=publish,draft", $auth);
        if (!empty($rows[0]['id'])) { $on_site[] = $author; break; }
    }
}

echo json_encode(['ok' => true, 'on_site' => $on_site], JSON_UNESCAPED_UNICODE);
