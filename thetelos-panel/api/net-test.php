<?php
/**
 * net-test.php — Sunucunun dış adreslere erişimini test eder (teşhis).
 * Tarayıcıda aç: thetelos.org/thetelos-panel/api/net-test.php
 * Giriş yapmış admin görebilir. Hiçbir veri değiştirmez.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('giriş gerekli'); }
session_write_close();
header('Content-Type: text/html; charset=utf-8');

function nt_curl($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_HTTPHEADER     => ['User-Agent: ThetelosBot/1.0 (https://thetelos.org; mailto:alitokalak@gmail.com)'],
    ]);
    $r   = curl_exec($ch);
    $c   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = ($r === false) ? curl_error($ch) : '';
    $ip  = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
    curl_close($ch);
    return ['http' => $c, 'err' => $err, 'ip' => $ip, 'len' => is_string($r) ? strlen($r) : 0];
}

$hosts = [
    'openlibrary.org'        => 'https://openlibrary.org/search/authors.json?q=plato&limit=1',
    'covers.openlibrary.org' => 'https://covers.openlibrary.org/b/id/240727-S.jpg',
    'query.wikidata.org'     => 'https://query.wikidata.org/sparql?format=json&query=' . rawurlencode('SELECT ?x WHERE{BIND(1 AS ?x)}'),
    'googleapis.com'         => 'https://www.googleapis.com/books/v1/volumes?q=plato&maxResults=1',
    'example.com'            => 'https://example.com/',
];

echo '<div style="font-family:monospace;max-width:900px;margin:30px auto;line-height:1.7">';
echo '<h2>Ağ / DNS Testi</h2>';
echo '<p>PHP SAPI: <b>' . htmlspecialchars(PHP_SAPI) . '</b> · curl: <b>' . htmlspecialchars(curl_version()['version']) . '</b></p>';
echo '<table border="1" cellpadding="8" style="border-collapse:collapse">';
echo '<tr style="background:#eee"><th>Adres</th><th>gethostbyname (DNS)</th><th>curl HTTP</th><th>IP</th><th>byte</th><th>hata</th></tr>';
foreach ($hosts as $host => $url) {
    $dns = @gethostbyname($host);
    $dns_ok = ($dns !== $host);   // çözülemezse aynı string döner
    $res = nt_curl($url);
    $row_ok = ($res['http'] >= 200 && $res['http'] < 400);
    echo '<tr style="background:' . ($row_ok ? '#e8ffe8' : '#ffe8e8') . '">';
    echo '<td>' . htmlspecialchars($host) . '</td>';
    echo '<td>' . ($dns_ok ? htmlspecialchars($dns) : '<b style="color:#c00">ÇÖZÜLEMEDİ</b>') . '</td>';
    echo '<td><b>' . (int)$res['http'] . '</b></td>';
    echo '<td>' . htmlspecialchars($res['ip']) . '</td>';
    echo '<td>' . (int)$res['len'] . '</td>';
    echo '<td style="color:#c00">' . htmlspecialchars($res['err']) . '</td>';
    echo '</tr>';
}
echo '</table>';
echo '<p style="color:#666;margin-top:16px">Yeşil satır = erişilebiliyor. Kırmızı = sorun. '
   . 'openlibrary.org kırmızı ama googleapis.com yeşilse → OL erişimi engelli, Google Books\'a geçeriz.</p>';
echo '</div>';
