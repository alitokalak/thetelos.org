<?php
/**
 * openrouter-save.php — OpenRouter (Qwen vb.) anahtar/model ayarı.
 *
 * action=status  → mevcut durum (anahtar gizli, sadece var/yok + model).
 * action=save    → key/model/enabled kaydet (openrouter.secret.php'e yaz).
 * action=test    → verilen (veya kayıtlı) anahtarla OpenRouter'a küçük bir istek.
 *
 * Anahtar SUNUCUDA saklanır, repoya girmez (.gitignore). Dosya yüklemeye gerek
 * yok — panelden yapıştırıp kaydetmek yeterli.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
require_once dirname(__DIR__) . '/or-config.php';
header('Content-Type: application/json');

$action       = $_GET['action'] ?? $_POST['action'] ?? 'status';
$default_model = 'qwen/qwen-2.5-72b-instruct';

/* Kayıtlı ham paneli oku (maskesiz — sadece sunucu içi mantık için) */
function or_raw() {
    if (is_file(TLS_OR_SECRET)) {
        $d = @include TLS_OR_SECRET;
        if (is_array($d)) return $d;
    }
    return ['enabled' => false, 'key' => '', 'model' => ''];
}
function or_mask($key) {
    $key = (string) $key;
    if ($key === '') return '';
    $n = mb_strlen($key);
    return $n <= 12 ? str_repeat('•', $n) : mb_substr($key, 0, 8) . str_repeat('•', 6) . mb_substr($key, -4);
}

/* OpenRouter'a küçük bir doğrulama isteği at → [ok, http, error, model] */
function or_ping($key, $model) {
    if ($key === '') return ['ok' => false, 'http' => 0, 'error' => 'anahtar boş'];
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
            'HTTP-Referer: https://thetelos.org', 'X-Title: The Telos',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model ?: 'qwen/qwen-2.5-72b-instruct',
            'max_tokens' => 8,
            'messages' => [['role' => 'user', 'content' => 'Reply with the single word: OK']],
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $r = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    $j = json_decode((string) $r, true);
    $txt = trim((string) ($j['choices'][0]['message']['content'] ?? ''));
    if ($http >= 200 && $http < 300 && $txt !== '') {
        return ['ok' => true, 'http' => $http, 'reply' => mb_substr($txt, 0, 40), 'model' => $model];
    }
    $msg = $j['error']['message'] ?? ($cerr ?: 'boş yanıt');
    return ['ok' => false, 'http' => $http, 'error' => mb_substr((string) $msg, 0, 180)];
}

if ($action === 'status') {
    $raw = or_raw();
    $conf = tls_or_conf();
    echo json_encode([
        'ok'            => true,
        'has_key'       => ($raw['key'] ?? '') !== '',
        'key_mask'      => or_mask($raw['key'] ?? ''),
        'model'         => ($raw['model'] ?? '') !== '' ? $raw['model'] : $default_model,
        'enabled'       => !empty($raw['enabled']),
        'active'        => $conf['active'],           // gerçekte devrede mi (config yedeği dahil)
        'active_model'  => $conf['model'],
        'active_source' => $conf['source'],           // panel | config | none
        'writable'      => is_writable(dirname(TLS_OR_SECRET)) || (is_file(TLS_OR_SECRET) && is_writable(TLS_OR_SECRET)),
    ]);
    exit;
}

if ($action === 'test') {
    // Test için: form'da anahtar geldiyse onu, yoksa kayıtlıyı kullan.
    $raw = or_raw();
    $key   = trim((string) ($_POST['key']   ?? '')) ?: (string) ($raw['key'] ?? '');
    $model = trim((string) ($_POST['model'] ?? '')) ?: (($raw['model'] ?? '') !== '' ? $raw['model'] : $default_model);
    echo json_encode(['ok' => true, 'result' => or_ping($key, $model)]);
    exit;
}

if ($action === 'save') {
    $raw     = or_raw();
    $key_in  = trim((string) ($_POST['key'] ?? ''));
    // Boş bırakılırsa mevcut anahtar korunur (maskeyi geri yollamak zorunda kalma).
    $key     = $key_in !== '' ? $key_in : (string) ($raw['key'] ?? '');
    $model   = trim((string) ($_POST['model'] ?? '')) ?: $default_model;
    $enabled = (($_POST['enabled'] ?? '') === '1');

    if ($key === '') { echo json_encode(['ok' => false, 'error' => 'API anahtarı gerekli.']); exit; }

    $php = "<?php\n// OpenRouter gizli ayarı — panelden yazıldı. Repoya GİRMEZ.\n"
         . "return " . var_export(['enabled' => $enabled, 'key' => $key, 'model' => $model], true) . ";\n";

    $tmp = TLS_OR_SECRET . '.tmp';
    if (@file_put_contents($tmp, $php) === false || !@rename($tmp, TLS_OR_SECRET)) {
        @unlink($tmp);
        echo json_encode(['ok' => false, 'error' => 'Dosya yazılamadı. ' . dirname(TLS_OR_SECRET) . ' klasörüne yazma izni (chmod 755) gerekli.']);
        exit;
    }
    @chmod(TLS_OR_SECRET, 0600);

    echo json_encode(['ok' => true, 'enabled' => $enabled, 'model' => $model, 'key_mask' => or_mask($key)]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Bilinmeyen işlem.']);
