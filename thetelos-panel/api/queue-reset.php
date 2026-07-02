<?php
/**
 * queue-reset.php — Bir list_only kuyruğunu YERİNDE sıfırla: yazar listesini
 * KORUR, ilerlemeyi (authors_built) ve çekilmiş eserleri (.jsonl) siler, durumu
 * 'building' yapar. Böylece 17 binlik listeyi yeniden çekmeden, kapak/yıl
 * zenginleştirmesiyle baştan işlenir.
 *
 * Kullanım (tarayıcıda, giriş yapmış admin):
 *   thetelos.org/thetelos-panel/api/queue-reset.php?batch_id=queue_xxx
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('giriş gerekli'); }
session_write_close();
header('Content-Type: text/html; charset=utf-8');

$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_GET['batch_id'] ?? ''));
$file     = dirname(__DIR__) . '/jobs/' . $batch_id . '.json';
if (!$batch_id || !is_file($file)) { echo 'Kuyruk bulunamadı: ' . htmlspecialchars($batch_id); exit; }

$lock = fopen($file . '.lock', 'c');
if ($lock) flock($lock, LOCK_EX);
$b = json_decode((string)@file_get_contents($file), true);
if (!is_array($b)) { if ($lock){flock($lock,LOCK_UN);fclose($lock);} echo 'Dosya okunamadı/bozuk.'; exit; }

$authors_total = count($b['authors'] ?? []);
$b['authors_built']  = 0;
$b['books']          = [];
$b['total']          = 0;
$b['books_migrated'] = false;
$b['status']         = 'building';
$b['epoch']          = (int)($b['epoch'] ?? 0) + 1;   // nesil damgası: eski/çakışan tikler bunu görüp yazmayı iptal eder
$b['build_msg']      = 'Sıfırlandı — kapak/yıl ile baştan çekiliyor...';

// Çekilmiş eserler dosyasını temizle
$jsonl = preg_replace('/\.json$/', '.books.jsonl', $file);
@unlink($jsonl);

// Atomik yaz
$json = json_encode($b, JSON_UNESCAPED_UNICODE);
$tmp  = $file . '.tmp.' . getmypid() . '.' . mt_rand();
if ($json !== false && @file_put_contents($tmp, $json) === strlen($json)) @rename($tmp, $file); else @unlink($tmp);
if ($lock) { flock($lock, LOCK_UN); fclose($lock); }

echo '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;line-height:1.7">';
echo '<h2>✓ Kuyruk sıfırlandı</h2>';
echo '<p><b>' . number_format($authors_total) . '</b> yazar korundu. İlerleme 0\'a alındı, eski eserler silindi, '
   . 'durum <b>building</b> yapıldı.</p>';
echo '<p>cron (cron-job.org) birkaç dakika içinde baştan işlemeye başlar — bu sefer kapak/yıl zenginleştirmesiyle. '
   . '<b>Kuyruk</b> sayfasından takip et.</p>';
echo '</div>';
