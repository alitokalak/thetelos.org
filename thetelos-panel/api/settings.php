<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'save_prompts') {
    $data = ['summary'=>$_POST['summary']??'','analysis'=>$_POST['analysis']??''];
    if (!file_exists(PROMPTS_FILE)) { @file_put_contents(PROMPTS_FILE,'{}'); @chmod(PROMPTS_FILE,0666); }
    if (!is_writable(PROMPTS_FILE)) { echo json_encode(['ok'=>false,'error'=>'prompts.json yazılamıyor. FTP ile chmod 666 yapın.']); exit; }
    $r = file_put_contents(PROMPTS_FILE, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    echo $r !== false ? json_encode(['ok'=>true]) : json_encode(['ok'=>false,'error'=>'Yazma hatası.']);

} elseif ($action === 'get_prompts') {
    $data = file_exists(PROMPTS_FILE) ? json_decode(file_get_contents(PROMPTS_FILE),true) : ['summary'=>'','analysis'=>''];
    echo json_encode(['ok'=>true,'data'=>$data]);

} elseif ($action === 'save_verify' || $action === 'get_verify') {
    /* Yayın kapısı ayarları. Doğrulama katmanları API harcadığı için
       kapatılabilir olmalı; ama VARSAYILAN AÇIKTIR — bir okuyucunun uydurma
       içerik bildirmesinden sonra güvenli varsayılan budur. */
    $f = dirname(__DIR__) . '/settings.json';
    $s = file_exists($f) ? json_decode((string) file_get_contents($f), true) : [];
    if (!is_array($s)) $s = [];

    if ($action === 'save_verify') {
        $s['verify_gate']      = !empty($_POST['gate']);
        $s['verify_probe']     = !empty($_POST['probe']);
        $s['verify_factcheck'] = !empty($_POST['factcheck']);
        $s['verify_min_conf']  = max(0, min(100, (int) ($_POST['min_conf'] ?? 55)));
        $json = json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $tmp  = $f . '.tmp';
        if (@file_put_contents($tmp, $json) === strlen($json) && @rename($tmp, $f)) {
            @chmod($f, 0664);
            echo json_encode(['ok' => true]);
        } else {
            @unlink($tmp);
            echo json_encode(['ok' => false, 'error' => 'settings.json yazılamadı — FTP ile klasöre yazma izni ver.']);
        }
        exit;
    }

    echo json_encode(['ok' => true, 'data' => [
        'gate'      => !isset($s['verify_gate'])      || (bool) $s['verify_gate'],
        'probe'     => !isset($s['verify_probe'])     || (bool) $s['verify_probe'],
        'factcheck' => !isset($s['verify_factcheck']) || (bool) $s['verify_factcheck'],
        'min_conf'  => (int) ($s['verify_min_conf'] ?? 55),
    ]]);

} elseif ($action === 'test_connection') {
    $auth = 'Basic ' . base64_encode(WP_USER . ':' . WP_APP_PASS);
    $ch   = curl_init(rtrim(WP_URL,'/') . '/wp-json/wp/v2/users/me');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Authorization: '.$auth]]);
    $raw  = curl_exec($ch); $code = curl_getinfo($ch,CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($err) { echo json_encode(['ok'=>false,'error'=>'cURL: '.$err]); exit; }
    $user = json_decode($raw,true);
    if ($code===200) echo json_encode(['ok'=>true,'user'=>$user['name']??'OK']);
    else echo json_encode(['ok'=>false,'error'=>$user['message']??"HTTP $code"]);
} else {
    echo json_encode(['ok'=>false,'error'=>'Geçersiz işlem.']);
}
