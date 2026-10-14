<?php
/**
 * cover-set.php — YAYINLANMIŞ bir yazıya manuel kapak (JPG/PNG/WebP) yükle.
 *
 * Kaynak-temelli tekli üretim doğrudan yayınladığı için publish.php'nin kapak
 * adımına uğramaz; kullanıcı kendi kapağını sonradan bu uçla ekler.
 *
 * POST: post_id, cover_data ("data:image/..;base64,.."), [cover_name], [ep=posts|analysis]
 * Yanıt: { ok, media_id, thumb }  |  { ok:false, error }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('yetki yok'); }
header('Content-Type: application/json; charset=utf-8');

$post_id    = (int) ($_POST['post_id'] ?? 0);
$cover_data = (string) ($_POST['cover_data'] ?? '');
$cover_name = trim($_POST['cover_name'] ?? '');
$ep         = (($_POST['ep'] ?? 'posts') === 'analysis') ? 'analysis' : 'posts';

if ($post_id <= 0) { echo json_encode(['ok'=>false,'error'=>'post_id gerekli']); exit; }
if (!preg_match('~^data:image/(jpeg|jpg|png|webp);base64,~i', $cover_data, $dm)) {
    echo json_encode(['ok'=>false,'error'=>'Geçerli bir görsel değil (JPG/PNG/WebP gerekli)']); exit;
}

$raw = base64_decode(substr($cover_data, strpos($cover_data, ',') + 1), true);
if ($raw === false || strlen($raw) < 500) {
    echo json_encode(['ok'=>false,'error'=>'Görsel çözülemedi/çok küçük']); exit;
}

$auth   = 'Basic ' . base64_encode(WP_USER . ':' . WP_APP_PASS);
$wp_api = rtrim(WP_URL, '/') . '/wp-json/wp/v2';

$ext  = strtolower($dm[1]) === 'jpeg' ? 'jpg' : strtolower($dm[1]);
$mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
$base = $cover_name !== '' ? pathinfo($cover_name, PATHINFO_FILENAME) : ('cover-' . $post_id);
$fn   = (preg_replace('/[^a-z0-9]+/', '-', strtolower($base)) ?: 'cover') . '.' . $ext;

// 1) Medyaya yükle
$cm = curl_init("$wp_api/media");
curl_setopt_array($cm, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 40,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $auth,
        'Content-Type: ' . $mime,
        'Content-Disposition: attachment; filename="' . $fn . '"',
    ],
    CURLOPT_POSTFIELDS => $raw,
]);
$mr   = curl_exec($cm);
$mc   = curl_getinfo($cm, CURLINFO_HTTP_CODE);
curl_close($cm);
$mj   = json_decode($mr, true);
$mid  = $mj['id'] ?? null;
if (!$mid) {
    echo json_encode(['ok'=>false,'error'=>'WP medya yükleme hatası (HTTP '.$mc.'): '.($mj['message'] ?? '')]); exit;
}

// 2) Öne çıkan görsel yap (önce posts, olmazsa analysis)
$ch = curl_init("$wp_api/$ep/$post_id");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: ' . $auth],
    CURLOPT_POSTFIELDS => json_encode(['featured_media' => $mid]),
]);
$pr = curl_exec($ch);
$pc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($pc < 200 || $pc >= 300) {
    // tip yanlışsa diğer uçta dene
    $alt = $ep === 'posts' ? 'analysis' : 'posts';
    $ch2 = curl_init("$wp_api/$alt/$post_id");
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: ' . $auth],
        CURLOPT_POSTFIELDS => json_encode(['featured_media' => $mid]),
    ]);
    $pr = curl_exec($ch2);
    $pc = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    if ($pc < 200 || $pc >= 300) {
        echo json_encode(['ok'=>false,'error'=>'Kapak yüklendi ama yazıya bağlanamadı (HTTP '.$pc.')']); exit;
    }
}

$thumb = $mj['media_details']['sizes']['thumbnail']['source_url']
      ?? $mj['media_details']['sizes']['medium']['source_url']
      ?? $mj['source_url'] ?? '';

echo json_encode(['ok'=>true, 'media_id'=>$mid, 'thumb'=>$thumb], JSON_UNESCAPED_UNICODE);
