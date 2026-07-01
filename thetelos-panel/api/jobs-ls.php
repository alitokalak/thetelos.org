<?php
/**
 * jobs-ls.php — jobs/ klasöründeki tüm iş dosyalarını ham listele (teşhis).
 * Tarayıcıda aç: thetelos.org/thetelos-panel/api/jobs-ls.php
 * Kuyruk "kayboldu" mu, yoksa JSON'u mu bozuldu görmek için.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('giriş gerekli'); }
session_write_close();
header('Content-Type: text/html; charset=utf-8');

$jobs_dir = dirname(__DIR__) . '/jobs';
echo '<div style="font-family:monospace;max-width:1100px;margin:24px auto;line-height:1.6">';
echo '<h2>jobs/ klasörü</h2>';
$files = glob("$jobs_dir/*.json") ?: [];
echo '<p>Toplam .json dosyası: <b>' . count($files) . '</b></p>';
echo '<table border="1" cellpadding="6" style="border-collapse:collapse;width:100%">';
echo '<tr style="background:#eee"><th>Dosya</th><th>Boyut</th><th>Değişme</th><th>JSON</th><th>status</th><th>authors_total</th><th>books</th><th>authors_built</th></tr>';
foreach ($files as $f) {
    $raw  = @file_get_contents($f);
    $d    = json_decode((string)$raw, true);
    $valid = is_array($d);
    // JSON bozuksa ham metinden kurtarılabilir bilgiyi tahmin et
    $guess_authors = '';
    if (!$valid && is_string($raw)) {
        if (preg_match('/"authors_total"\s*:\s*(\d+)/', $raw, $m)) $guess_authors = $m[1] . ' (ham)';
    }
    echo '<tr style="background:' . ($valid ? '#e8ffe8' : '#ffe8e8') . '">';
    echo '<td>' . htmlspecialchars(basename($f)) . '</td>';
    echo '<td>' . number_format(strlen((string)$raw)) . ' B</td>';
    echo '<td>' . date('H:i:s', @filemtime($f)) . '</td>';
    echo '<td>' . ($valid ? '✅ geçerli' : '<b style="color:#c00">❌ BOZUK</b>') . '</td>';
    echo '<td>' . htmlspecialchars($valid ? ($d['status'] ?? '') : '?') . '</td>';
    echo '<td>' . htmlspecialchars($valid ? ($d['authors_total'] ?? '') : $guess_authors) . '</td>';
    echo '<td>' . htmlspecialchars($valid ? (string)count($d['books'] ?? []) : '?') . '</td>';
    echo '<td>' . htmlspecialchars($valid ? ($d['authors_built'] ?? '') : '?') . '</td>';
    echo '</tr>';
}
echo '</table>';
echo '<p style="color:#666">Kırmızı satır = JSON bozuk (eşzamanlı yazımdan). '
   . 'queue_philosophy_... dosyası burada görünüyorsa veri duruyor demektir; bozuksa kurtarırız.</p>';
echo '</div>';
