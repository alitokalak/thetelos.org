<?php
/**
 * queue-authors.php — Kuyruktaki YAZAR listesini indir (eserler değil).
 *   ?batch_id=queue_xxx            → tek CSV
 *   ?batch_id=queue_xxx&zip=1      → 100'erli gruplara bölünmüş ayrı CSV'ler, tek ZIP
 *   ?per=100                       → grup başına yazar sayısı (vars. 100)
 * Sütunlar: Sıra, Yazar, Dönem, Not
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
@ini_set('memory_limit', '512M');

$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_GET['batch_id'] ?? ''));
$file     = dirname(__DIR__) . '/jobs/' . $batch_id . '.json';
if (!$batch_id || !is_file($file)) { http_response_code(404); exit('Kuyruk bulunamadı'); }

$batch    = json_decode((string)file_get_contents($file), true);
$authors  = $batch['authors'] ?? [];
$cat      = ucfirst(preg_replace('/\s+/', '_', trim($batch['category'] ?? 'authors')));
$per      = max(1, min(1000, (int)($_GET['per'] ?? 100)));
$want_zip = !empty($_GET['zip']);
$HEADER   = "\xEF\xBB\xBFSıra,Yazar,Dönem,Not\n";   // UTF-8 BOM + başlık

/* Bir yazar dizisini CSV metnine çevir (baştaki sıra numarasıyla) */
function qa_csv($list, $start_no, $HEADER) {
    $csv = $HEADER; $n = $start_no;
    foreach ($list as $a) {
        $name = str_replace('"', '""', trim($a['author'] ?? ''));
        $era  = str_replace('"', '""', trim($a['era']    ?? ''));
        $note = str_replace('"', '""', trim($a['note']   ?? ''));
        $csv .= "$n,\"$name\",\"$era\",\"$note\"\n"; $n++;
    }
    return $csv;
}

/* ── 100'erli ZIP ── */
if ($want_zip && class_exists('ZipArchive')) {
    $groups = array_chunk($authors, $per);
    $tmp = tempnam(sys_get_temp_dir(), 'tlsauth');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $gi = 0;
    foreach ($groups as $grp) {
        $gi++;
        $start = ($gi - 1) * $per + 1;
        $end   = $start + count($grp) - 1;
        $name  = sprintf('%s_yazarlar_%05d-%05d.csv', $cat, $start, $end);
        $zip->addFromString($name, qa_csv($grp, $start, $HEADER));
    }
    $zip->close();
    $zipname = "{$cat}_yazarlar_" . count($authors) . "_100erli.zip";
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipname . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

/* ── Tek CSV ── */
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $cat . '_yazarlar_' . count($authors) . '.csv"');
echo qa_csv($authors, 1, $HEADER);
