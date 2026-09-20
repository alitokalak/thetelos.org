<?php
/**
 * twitter.php — X (Twitter) API v2 entegrasyonu (OAuth 1.0a, user context).
 *
 * Panelden kart görseli + metin ile otomatik tweet atar. Kullanıcı adı/şifre
 * KULLANILMAZ; yalnızca X Developer'dan alınan 4 token ile çalışır:
 *   API Key (consumer key), API Secret, Access Token, Access Token Secret.
 * Token'lar sunucuda PHP-sarmalı bir dosyada (doğrudan URL'den okunamaz) tutulur.
 *
 * POST action=save   : ck,cs,at,ats → twitter.secret.php'ye yazar
 * GET/POST action=status : token var mı + /2/users/me ile doğrula
 * POST action=post   : text (+ image=dataURL) → medya yükle + tweet at
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

define('TW_SECRET_FILE', dirname(__DIR__) . '/twitter.secret.php');

function tw_load() {
    if (!file_exists(TW_SECRET_FILE)) return null;
    $d = @include TW_SECRET_FILE;
    if (!is_array($d) || empty($d['ck']) || empty($d['cs']) || empty($d['at']) || empty($d['ats'])) return null;
    return $d;
}

/* ── OAuth 1.0a imzalı Authorization başlığı ──
   $body_params: yalnızca application/x-www-form-urlencoded gövdeler imzaya girer.
   Multipart (medya) ve JSON (tweet) gövdeleri imzaya GİRMEZ → boş bırakılır. */
function tw_auth_header($method, $url, $tok, $body_params = []) {
    $oauth = [
        'oauth_consumer_key'     => $tok['ck'],
        'oauth_nonce'            => bin2hex(random_bytes(16)),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp'        => (string) time(),
        'oauth_token'            => $tok['at'],
        'oauth_version'          => '1.0',
    ];
    $all = array_merge($body_params, $oauth);
    ksort($all);
    $pairs = [];
    foreach ($all as $k => $v) $pairs[] = rawurlencode($k) . '=' . rawurlencode($v);
    $base = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode(implode('&', $pairs));
    $key  = rawurlencode($tok['cs']) . '&' . rawurlencode($tok['ats']);
    $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $base, $key, true));
    ksort($oauth);
    $head = [];
    foreach ($oauth as $k => $v) $head[] = rawurlencode($k) . '="' . rawurlencode($v) . '"';
    return 'OAuth ' . implode(', ', $head);
}

function tw_json_response($ch) {
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, json_decode((string) $raw, true), $raw, $err];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ─────────────────────────── SAVE ─────────────────────────── */
if ($action === 'save') {
    $d = [
        'ck'  => trim((string) ($_POST['ck'] ?? '')),
        'cs'  => trim((string) ($_POST['cs'] ?? '')),
        'at'  => trim((string) ($_POST['at'] ?? '')),
        'ats' => trim((string) ($_POST['ats'] ?? '')),
    ];
    if (!$d['ck'] || !$d['cs'] || !$d['at'] || !$d['ats']) {
        echo json_encode(['ok' => false, 'error' => '4 alanın da doldurulması gerekir.']); exit;
    }
    $php = "<?php\n// X (Twitter) API token'ları — bu dosya repoya girmez, doğrudan URL'den okunamaz.\nreturn " . var_export($d, true) . ";\n";
    if (@file_put_contents(TW_SECRET_FILE, $php) === false) {
        echo json_encode(['ok' => false, 'error' => 'twitter.secret.php yazılamadı — klasöre yazma izni gerekli.']); exit;
    }
    @chmod(TW_SECRET_FILE, 0640);
    echo json_encode(['ok' => true]); exit;
}

/* ─────────────────────────── STATUS ─────────────────────────── */
if ($action === 'status') {
    $tok = tw_load();
    if (!$tok) { echo json_encode(['ok' => true, 'configured' => false]); exit; }
    // /2/users/me ile doğrula
    $url = 'https://api.twitter.com/2/users/me';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: ' . tw_auth_header('GET', $url, $tok)],
    ]);
    [$code, $j, $raw, $err] = tw_json_response($ch);
    if ($err) { echo json_encode(['ok' => true, 'configured' => true, 'valid' => false, 'error' => 'cURL: ' . $err]); exit; }
    if ($code === 200 && !empty($j['data'])) {
        echo json_encode(['ok' => true, 'configured' => true, 'valid' => true,
            'handle' => '@' . ($j['data']['username'] ?? '?'), 'name' => $j['data']['name'] ?? '']); exit;
    }
    $msg = $j['title'] ?? ($j['detail'] ?? ($j['errors'][0]['message'] ?? "HTTP $code"));
    echo json_encode(['ok' => true, 'configured' => true, 'valid' => false, 'error' => $msg]); exit;
}

/* ─────────────────────────── POST (tweet) ─────────────────────────── */
if ($action === 'post') {
    $tok = tw_load();
    if (!$tok) { echo json_encode(['ok' => false, 'error' => 'Önce Ayarlar\'dan X token\'larını gir.']); exit; }

    $text  = trim((string) ($_POST['text'] ?? ''));
    $image = (string) ($_POST['image'] ?? '');   // data:image/jpeg;base64,...
    if ($text === '') { echo json_encode(['ok' => false, 'error' => 'Boş metin gönderilemez.']); exit; }

    $media_id = '';
    if ($image !== '') {
        if (!preg_match('#^data:image/(jpe?g|png);base64,(.+)$#is', $image, $m)) {
            echo json_encode(['ok' => false, 'error' => 'Görsel biçimi tanınmadı.']); exit;
        }
        $bin = base64_decode($m[2]);
        if ($bin === false || strlen($bin) < 100) { echo json_encode(['ok' => false, 'error' => 'Görsel çözülemedi.']); exit; }
        $tmp = tempnam(sys_get_temp_dir(), 'twmedia');
        file_put_contents($tmp, $bin);
        $ext = (stripos($m[1], 'png') !== false) ? 'png' : 'jpg';
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';

        // Medya yükle (v1.1 multipart) — gövde imzaya girmez
        $up = 'https://upload.twitter.com/1.1/media/upload.json';
        $ch = curl_init($up);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: ' . tw_auth_header('POST', $up, $tok)],
            CURLOPT_POSTFIELDS => ['media' => new CURLFile($tmp, $mime, 'card.' . $ext)],
        ]);
        [$code, $j, $raw, $err] = tw_json_response($ch);
        @unlink($tmp);
        if ($err) { echo json_encode(['ok' => false, 'error' => 'Medya yükleme cURL: ' . $err]); exit; }
        if ($code !== 200 || empty($j['media_id_string'])) {
            $msg = $j['errors'][0]['message'] ?? ($j['error'] ?? "Medya yükleme HTTP $code");
            $paid = ($code === 402 || $code === 403 || $code === 453);
            echo json_encode(['ok' => false, 'step' => 'media', 'paid' => $paid,
                'error' => 'Görsel yükleme: ' . $msg, 'code' => $code, 'raw' => $raw]); exit;
        }
        $media_id = $j['media_id_string'];
    }

    // Tweet oluştur (v2 JSON) — gövde imzaya girmez
    $payload = ['text' => $text];
    if ($media_id) $payload['media'] = ['media_ids' => [$media_id]];
    $tw = 'https://api.twitter.com/2/tweets';
    $ch = curl_init($tw);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: ' . tw_auth_header('POST', $tw, $tok), 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    [$code, $j, $raw, $err] = tw_json_response($ch);
    if ($err) { echo json_encode(['ok' => false, 'error' => 'Tweet cURL: ' . $err]); exit; }
    if (($code === 200 || $code === 201) && !empty($j['data']['id'])) {
        echo json_encode(['ok' => true, 'id' => $j['data']['id'],
            'url' => 'https://x.com/i/web/status/' . $j['data']['id']]); exit;
    }
    $msg = $j['title'] ?? ($j['detail'] ?? ($j['errors'][0]['message'] ?? "HTTP $code"));
    $paid = ($code === 402 || $code === 403 || $code === 453);
    echo json_encode(['ok' => false, 'step' => 'tweet', 'paid' => $paid,
        'error' => $msg, 'code' => $code, 'raw' => $raw]); exit;
}

echo json_encode(['ok' => false, 'error' => 'Geçersiz işlem.']);
