<?php
/* gen-debug.php — üretim/yayın teşhis günlüğünün son satırlarını gösterir.
   Sadece giriş yapmış panel kullanıcısı görebilir. Salt-okunur. */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('yetkisiz'); }
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$f = dirname(__DIR__) . '/jobs/gen-debug.log';
if (!is_file($f)) { echo "(henüz kayıt yok: {$f})"; exit; }

// temizle isteği
if (($_GET['clear'] ?? '') === '1') { @file_put_contents($f, ''); echo "temizlendi."; exit; }

$lines = @file($f, FILE_IGNORE_NEW_LINES) ?: [];
$tail  = array_slice($lines, -60);
echo "=== gen-debug.log (son " . count($tail) . " satır) ===\n";
echo implode("\n", $tail) . "\n";
echo "\n(temizlemek için sonuna ?clear=1 ekle)\n";
