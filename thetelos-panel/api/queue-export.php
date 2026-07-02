<?php
/**
 * queue-export.php — Kuyruğun eserlerini CSV olarak indir.
 *   ?batch_id=...            → tek CSV (geriye dönük uyumlu)
 *   ?batch_id=...&zip=1      → yazarları 100'erli gruplara bölüp her grubu
 *                             ayrı CSV yapar, hepsini tek ZIP içinde indirir.
 *   ?per=100                → grup başına yazar sayısı (varsayılan 100)
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }

$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_GET['batch_id'] ?? ''));
if (!$batch_id) { http_response_code(400); exit; }
$file = dirname(__DIR__) . '/jobs/' . $batch_id . '.json';
if (!file_exists($file)) { http_response_code(404); exit; }

@ini_set('memory_limit', '512M');   // büyük listelerde tüm eserleri belleğe alırken

$batch     = json_decode(file_get_contents($file), true);
$books     = $batch['books'] ?? [];
// list_only kuyruklarda eserler ayrı .jsonl dosyasında (ölçeklenir model).
if (empty($books)) {
    $jsonl = preg_replace('/\.json$/', '.books.jsonl', $file);
    if (is_file($jsonl)) {
        foreach (file($jsonl, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
            $o = json_decode($ln, true);
            if (is_array($o)) $books[] = $o;
        }
    }
}
$cat_raw   = $batch['category'] ?? 'export';
$cat_slug  = ucfirst(preg_replace('/\s+/', '_', trim($cat_raw)));
$per       = max(1, min(500, (int)($_GET['per'] ?? 100)));
$want_zip  = !empty($_GET['zip']);

/* Tek kitap satırını CSV alanına çevir */
function qx_row($b) {
    $title  = str_replace('"', '""', trim($b['book_title'] ?? ''));
    $author = str_replace('"', '""', trim($b['author_name'] ?? ''));
    $year   = trim($b['year']      ?? '');
    $cover  = trim($b['cover_url']  ?? '');
    return "\"$title\",\"$author\",\"$year\",\"$cover\"\n";
}
$HEADER = "\xEF\xBB\xBFKitap Adı,Yazar Adı,Yıl,Kapak\n"; // UTF-8 BOM + başlık

/* ── Tek CSV (zip istenmediyse) ── */
if (!$want_zip) {
    $offset    = (int)($batch['author_offset'] ?? 0);
    $aut_total = (int)($batch['authors_total'] ?? 50);
    $filename  = "{$cat_slug}_{$offset}-" . ($offset + $aut_total) . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $HEADER;
    foreach ($books as $b) echo qx_row($b);
    exit;
}

/* ── ZIP: yazarları 100'erli grupla, her grubu ayrı CSV yap ── */
if (!class_exists('ZipArchive')) {
    // ZipArchive yoksa tek CSV'ye düş
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $cat_slug . '_all.csv"');
    echo $HEADER;
    foreach ($books as $b) echo qx_row($b);
    exit;
}

// Yazarları görülme sırasına göre benzersiz çıkar; her yazarın kitaplarını grupla
$order = [];            // sıra: yazar adı
$byAuthor = [];        // yazar → [satır,...]
foreach ($books as $b) {
    $a = trim($b['author_name'] ?? '');
    if ($a === '') $a = '(yazarsız)';
    if (!isset($byAuthor[$a])) { $byAuthor[$a] = []; $order[] = $a; }
    $byAuthor[$a][] = qx_row($b);
}

$groups = array_chunk($order, $per);   // 100'erli yazar grupları
$tmp = tempnam(sys_get_temp_dir(), 'tlsexp');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::OVERWRITE);

$gi = 0;
foreach ($groups as $grp) {
    $gi++;
    $start = ($gi - 1) * $per + 1;
    $end   = $start + count($grp) - 1;
    $csv   = $HEADER;
    foreach ($grp as $author) {
        foreach ($byAuthor[$author] as $row) $csv .= $row;
    }
    $name = sprintf('%s_%03d-%03d.csv', $cat_slug, $start, $end);
    $zip->addFromString($name, $csv);
}
$zip->close();

$zipname = "{$cat_slug}_works_" . count($order) . "authors.zip";
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipname . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
