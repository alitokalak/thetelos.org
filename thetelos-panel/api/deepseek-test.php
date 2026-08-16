<?php
/**
 * deepseek-test.php — DeepSeek erişim TEŞHİSİ (canlı sunucuda).
 *
 * "DeepSeek çağrıları connection timed out veriyor" sorununun KESİN nedenini
 * ölçer: DNS (A/AAAA), ham TCP/TLS bağlantısı (varsayılan / IPv4 / IPv6 ayrı
 * ayrı, süreyle), ve gerçek bir mini API çağrısı (anahtarla) → HTTP kodu.
 * Böylece "IP engeli (timeout) mı, 401 anahtar mı, 402 bakiye mi, 404 endpoint
 * mi" net görünür. Anahtar EKRANA yazılmaz.
 *
 * KULLANIM: panele giriş yaptıktan sonra
 *   /thetelos-panel/api/deepseek-test.php
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); echo 'auth'; exit; }
header('Content-Type: text/plain; charset=utf-8');

$url  = defined('DEEPSEEK_API_URL') ? DEEPSEEK_API_URL : '(DEEPSEEK_API_URL tanımsız)';
$host = parse_url($url, PHP_URL_HOST) ?: 'api.deepseek.com';
$key  = defined('DEEPSEEK_KEY') ? DEEPSEEK_KEY : '';

echo "DEEPSEEK ERİŞİM TEŞHİSİ\n";
echo "endpoint : {$url}\n";
echo "host     : {$host}\n";
echo "anahtar  : " . ($key ? ('tanımlı, ' . strlen($key) . ' karakter, ' . substr($key, 0, 6) . '…') : 'YOK') . "\n";
echo str_repeat('─', 60) . "\n\n";

/* 1) DNS — IPv4 (A) ve IPv6 (AAAA) kayıtları */
echo "1) DNS ÇÖZÜMLEME\n";
$a4 = @dns_get_record($host, DNS_A);
$a6 = @dns_get_record($host, DNS_AAAA);
echo "  A    (IPv4): " . ($a4 ? implode(', ', array_map(fn($r) => $r['ip'] ?? '?', $a4)) : '(yok)') . "\n";
echo "  AAAA (IPv6): " . ($a6 ? implode(', ', array_map(fn($r) => $r['ipv6'] ?? '?', $a6)) : '(yok)') . "\n\n";

/* 2) Ham bağlantı — varsayılan / IPv4 / IPv6 ayrı ayrı, süreyle */
function ds_conn($url, $ipmode) {
    $t0 = microtime(true);
    $ch = curl_init($url);
    $o = [CURLOPT_NOBODY => false, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 20,
          CURLOPT_TIMEOUT => 25, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_POST => true,
          CURLOPT_POSTFIELDS => '{}', CURLOPT_HTTPHEADER => ['Content-Type: application/json']];
    if ($ipmode === 4) $o[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    if ($ipmode === 6) $o[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V6;
    curl_setopt_array($ch, $o);
    $r = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ct   = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
    $ip   = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
    $err  = curl_error($ch);
    curl_close($ch);
    $dt = round((microtime(true) - $t0) * 1000);
    printf("  %-10s HTTP=%-3d connect=%.2fs ip=%-24s %s\n",
        ($ipmode ? 'IPv' . $ipmode : 'varsayılan'), $code, $ct, $ip ?: '-',
        ($err ? 'ERR: ' . $err : 'bağlandı') . " (toplam {$dt}ms)");
    return $code;
}
echo "2) HAM BAĞLANTI (boş gövde; 200 beklenmez, amaç BAĞLANABİLMEK)\n";
ds_conn($url, 0);
ds_conn($url, 4);
ds_conn($url, 6);
echo "\n";

/* 3) Gerçek mini API çağrısı — anahtarla, kısa istek → HTTP kodu + mesaj */
echo "3) GERÇEK API ÇAĞRISI (anahtarla, 5 token)\n";
if (!$key) { echo "  anahtar yok → atlandı\n"; exit; }
$model = (defined('DEEPSEEK_MODEL') && !in_array(DEEPSEEK_MODEL, ['deepseek-chat', 'deepseek-reasoner'], true)) ? DEEPSEEK_MODEL : 'deepseek-v4-flash';
$body = json_encode(['model' => $model, 'max_tokens' => 5, 'messages' => [['role' => 'user', 'content' => 'Reply with: OK']]]);
foreach ([0, 4] as $mode) {
    $t0 = microtime(true);
    $ch = curl_init($url);
    $o = [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_TIMEOUT => 40,
          CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key], CURLOPT_POSTFIELDS => $body];
    if ($mode === 4) $o[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    curl_setopt_array($ch, $o);
    $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $err = curl_error($ch);
    curl_close($ch);
    $dt = round((microtime(true) - $t0) * 1000);
    $j = json_decode((string) $r, true);
    $msg = $j['error']['message'] ?? trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags((string) $r)), 0, 200));
    printf("  %-10s HTTP=%-3d %s  (%dms)\n", ($mode ? 'IPv4' : 'varsayılan'), $code, ($err ? 'ERR: ' . $err : ($msg ?: 'boş')), $dt);
}
echo "\n— YORUM —\n";
echo "  connect başarısız/timeout → sunucu→DeepSeek AĞ engeli (IP throttle/firewall)\n";
echo "  HTTP 401 → anahtar geçersiz · 402 → bakiye bitti · 404 → endpoint değişti · 200 → çalışıyor\n";
