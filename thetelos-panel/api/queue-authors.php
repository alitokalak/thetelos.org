<?php
/**
 * queue-authors.php — Kuyruktaki YAZAR listesini CSV olarak indir (eserler değil).
 *   ?batch_id=queue_xxx
 * Sütunlar: Sıra, Yazar, Dönem, Not
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
@ini_set('memory_limit', '512M');

$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_GET['batch_id'] ?? ''));
$file     = dirname(__DIR__) . '/jobs/' . $batch_id . '.json';
if (!$batch_id || !is_file($file)) { http_response_code(404); exit('Kuyruk bulunamadı'); }

$batch   = json_decode((string)file_get_contents($file), true);
$authors = $batch['authors'] ?? [];
$cat     = ucfirst(preg_replace('/\s+/', '_', trim($batch['category'] ?? 'authors')));

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $cat . '_yazarlar_' . count($authors) . '.csv"');

echo "\xEF\xBB\xBF";                       // UTF-8 BOM (Excel için)
echo "Sıra,Yazar,Dönem,Not\n";
$i = 0;
foreach ($authors as $a) {
    $i++;
    $name = str_replace('"', '""', trim($a['author'] ?? ''));
    $era  = str_replace('"', '""', trim($a['era']    ?? ''));
    $note = str_replace('"', '""', trim($a['note']   ?? ''));
    echo "$i,\"$name\",\"$era\",\"$note\"\n";
}
