<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

if (empty($_FILES['bulk_file'])) { echo json_encode(['ok'=>false,'error'=>'Dosya bulunamadı.']); exit; }

$file = $_FILES['bulk_file'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$rows = [];

$header_keywords = ['book','kitap','title','book title','başlık','kitap adı','book name','thetelos'];

if ($ext === 'csv') {
    $handle = fopen($file['tmp_name'], 'r');
    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        $first_cell = strtolower(trim($row[0]??''));
        // Başlık veya bilgi satırı ise atla
        if (in_array($first_cell, $header_keywords)) continue;
        if (empty($first_cell)) continue;
        if (!empty(trim($row[0]??''))) $rows[] = $row;
    }
    fclose($handle);

} elseif ($ext === 'xlsx') {
    // Hafif XLSX okuyucu — ZipArchive + SimpleXML
    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true) { echo json_encode(['ok'=>false,'error'=>'XLSX açılamadı.']); exit; }

    $shared = [];
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss) {
        $xml = new SimpleXMLElement($ss);
        foreach ($xml->si as $si) {
            if (isset($si->t)) { $shared[] = (string)$si->t; }
            else { $txt=''; foreach ($si->r as $r) $txt .= (string)$r->t; $shared[] = $txt; }
        }
    }

    $ws_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if (!$ws_xml) { echo json_encode(['ok'=>false,'error'=>'sheet1.xml bulunamadı.']); exit; }
    $ws    = new SimpleXMLElement($ws_xml);
    $skip_words = ['book','kitap','title','book title','başlık','kitap adı','book name','thetelos','notlar','yazar'];
    foreach ($ws->sheetData->row as $row) {
        $r = [];
        foreach ($row->c as $cell) {
            $t = (string)($cell['t']??'');
            $v = '';
            if ($t === 's') {
                // Shared string
                $v = $shared[(int)(string)$cell->v] ?? '';
            } elseif ($t === 'inlineStr') {
                // Inline string (openpyxl ile üretilen Excel'lerde)
                $v = (string)($cell->is->t ?? '');
            } else {
                $v = isset($cell->v) ? (string)$cell->v : '';
            }
            $r[] = $v;
        }
        $first_cell = strtolower(trim($r[0]??''));
        // Başlık, bilgi veya boş satırları atla
        if (empty($first_cell)) continue;
        if (in_array($first_cell, $skip_words)) continue;
        // İlk kelimesi "thetelos" içeriyorsa bilgi satırı — atla
        if (strpos($first_cell, 'thetelos') !== false) continue;
        $rows[] = $r;
    }
} else {
    echo json_encode(['ok'=>false,'error'=>'Yalnızca CSV ve XLSX destekleniyor.']); exit;
}

$books = [];
foreach ($rows as $r) {
    // Kapak: satırdaki herhangi bir hücrede http(s) URL varsa onu kullan
    // (builder CSV'sinde 4. sütun, ama sütun sırası değişse de bulunur).
    $cover = '';
    foreach ($r as $cell) {
        $cell = trim((string)$cell);
        if (preg_match('#^https?://#i', $cell)) { $cover = $cell; break; }
    }
    // Yıl: salt 4 haneli (ör. 1987) bir hücre = kitabın yayın yılı ("Yıl" sütunu).
    $year = '';
    foreach ($r as $cell) {
        $cell = trim((string)$cell);
        if (preg_match('/^(\d{3,4})$/', $cell, $ym)) {
            $yv = (int)$ym[1];
            if ($yv >= 100 && $yv <= 2099) { $year = (string)$yv; break; }
        }
    }
    // 3. sütun: salt yıl (4 haneli sayı) veya URL ise kategori DEĞİLDİR.
    $cat = trim($r[2] ?? '');
    if (preg_match('/^\d{3,4}$/', $cat) || preg_match('#^https?://#i', $cat)) $cat = '';
    $books[] = [
        'book_title'  => trim($r[0] ?? ''),
        'author_name' => trim($r[1] ?? ''),
        'category'    => $cat,
        'cover'       => $cover,
        'year'        => $year,
    ];
}

echo json_encode(['ok'=>true, 'books'=>$books, 'count'=>count($books)]);
