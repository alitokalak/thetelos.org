<?php
/**
 * cleanup-duplicates.php — Aynı başlıklı duplicate postları bul ve sil.
 * GET  ?dry_run=1  → sadece listele, silme
 * GET  ?dry_run=0  → sil (en düşük ID'yi koru, gerisini sil)
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');

$dry_run = ($_GET['dry_run'] ?? '1') !== '0';

$auth   = 'Basic ' . base64_encode(WP_USER . ':' . WP_APP_PASS);
$wp_api = rtrim(WP_URL, '/') . '/wp-json/wp/v2';

function cd_wp_get($url, $auth) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $auth],
    ]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $c === 200 ? json_decode($r, true) : [];
}

function cd_wp_delete($url, $auth) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $auth],
    ]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $c;
}

// Tüm postları çek (sayfalı)
$all_posts = [];
$page = 1;
while (true) {
    $posts = cd_wp_get("$wp_api/posts?per_page=100&page=$page&status=any&_fields=id,title,link,date", $auth);
    if (empty($posts)) break;
    $all_posts = array_merge($all_posts, $posts);
    if (count($posts) < 100) break;
    $page++;
}

// Başlığa göre grupla (küçük harf, boşluk normalize)
$by_title = [];
foreach ($all_posts as $p) {
    $key = strtolower(trim(html_entity_decode(strip_tags($p['title']['rendered'] ?? ''), ENT_QUOTES, 'UTF-8')));
    $by_title[$key][] = $p;
}

// Duplikatları bul: aynı başlıkta 2+ post var, en düşük ID'yi koru
$to_delete = [];
$to_keep   = [];
foreach ($by_title as $title => $posts) {
    if (count($posts) <= 1) continue;
    // ID'ye göre sırala — en küçük ID en eski post
    usort($posts, fn($a, $b) => $a['id'] - $b['id']);
    $to_keep[]  = $posts[0];
    for ($i = 1; $i < count($posts); $i++) {
        $to_delete[] = $posts[$i];
    }
}

if ($dry_run) {
    echo json_encode([
        'dry_run'        => true,
        'total_posts'    => count($all_posts),
        'duplicate_groups'=> count(array_filter($by_title, fn($p) => count($p) > 1)),
        'to_delete_count' => count($to_delete),
        'to_delete'      => array_map(fn($p) => [
            'id'    => $p['id'],
            'title' => html_entity_decode(strip_tags($p['title']['rendered'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'date'  => $p['date'],
            'link'  => $p['link'],
        ], $to_delete),
        'to_keep'        => array_map(fn($p) => [
            'id'    => $p['id'],
            'title' => html_entity_decode(strip_tags($p['title']['rendered'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'date'  => $p['date'],
        ], $to_keep),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Gerçek silme
$deleted = []; $failed = [];
foreach ($to_delete as $p) {
    // force=true → çöp kutusuna atmadan direkt sil
    $code = cd_wp_delete("$wp_api/posts/{$p['id']}?force=true", $auth);
    $title = html_entity_decode(strip_tags($p['title']['rendered'] ?? ''), ENT_QUOTES, 'UTF-8');
    if ($code === 200) {
        $deleted[] = ['id' => $p['id'], 'title' => $title];
    } else {
        $failed[]  = ['id' => $p['id'], 'title' => $title, 'http' => $code];
    }
}

echo json_encode([
    'dry_run' => false,
    'deleted' => $deleted,
    'failed'  => $failed,
    'deleted_count' => count($deleted),
    'failed_count'  => count($failed),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
