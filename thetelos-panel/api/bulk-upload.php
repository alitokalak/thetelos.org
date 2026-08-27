<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

if (empty($_FILES['bulk_file'])) { echo json_encode(['ok'=>false,'error'=>'Dosya bulunamadı.']); exit; }

$file = $_FILES['bulk_file'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$rows = [];   // TÜM satırlar (başlık dâhil) — başlık tespiti aşağıda yapılır.

if ($ext === 'csv') {
    $handle = fopen($file['tmp_name'], 'r');
    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        // Tamamen boş satırı atla; başlığı BURADA atma (aşağıda tespit edip mapliyoruz).
        $joined = trim(implode('', array_map(fn($c) => (string)$c, $row)));
        if ($joined === '') continue;
        $rows[] = $row;
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
    $ws = new SimpleXMLElement($ws_xml);
    foreach ($ws->sheetData->row as $row) {
        $r = [];
        foreach ($row->c as $cell) {
            $t = (string)($cell['t']??''); $v = '';
            if ($t === 's') { $v = $shared[(int)(string)$cell->v] ?? ''; }
            elseif ($t === 'inlineStr') { $v = (string)($cell->is->t ?? ''); }
            else { $v = isset($cell->v) ? (string)$cell->v : ''; }
            $r[] = $v;
        }
        $joined = trim(implode('', array_map(fn($c) => (string)$c, $r)));
        if ($joined === '') continue;
        $rows[] = $r;
    }
} else {
    echo json_encode(['ok'=>false,'error'=>'Yalnızca CSV ve XLSX destekleniyor.']); exit;
}

if (!$rows) { echo json_encode(['ok'=>true, 'books'=>[], 'count'=>0]); exit; }

/* ── BAŞLIK SATIRI + SÜTUN EŞLEME ───────────────────────────────────────────
   Panelin CSV'leri (Kaynak Arşivi, sonuç CSV'si, builder) farklı sütun
   sıralarına sahip. İlk satır BAŞLIK ise sütunları ADINA göre bul; değilse
   eski konum mantığına düş (0=kitap, 1=yazar). Ayrıca POST ID sütununu ya da
   Thetelos linkindeki ?p=NNN'yi yakala → yeniden yazma DOĞRUDAN id ile eşleşsin
   (bulanık başlık aramasına hiç gerek kalmaz, "bulunamadı" biter). */
$norm = fn($s) => strtolower(trim((string)$s));
$first = array_map($norm, $rows[0]);
$looks_header = false;
foreach ($first as $c) {
    if (preg_match('/^(kitap|book|title|eser|ba[şs]l[ıi]k|kitap ad[ıi]|yazar|author|post ?id|thetelos|kaynak|url)/', $c)) { $looks_header = true; break; }
}

$idx = ['title'=>-1,'author'=>-1,'pid'=>-1,'cat'=>-1,'year'=>-1,'cover'=>-1,'link'=>-1];
if ($looks_header) {
    foreach ($first as $i => $c) {
        if ($idx['title']  < 0 && preg_match('/(kitap|title|eser|book|ba[şs]l[ıi]k)/', $c) && !preg_match('/id/', $c)) $idx['title'] = $i;
        if ($idx['author'] < 0 && preg_match('/(yazar|author)/', $c)) $idx['author'] = $i;
        if ($idx['pid']    < 0 && preg_match('/^(post ?id|id|post_id)$/', $c)) $idx['pid'] = $i;
        if ($idx['cat']    < 0 && preg_match('/(kategori|category)/', $c)) $idx['cat'] = $i;
        if ($idx['year']   < 0 && preg_match('/(y[ıi]l|year)/', $c)) $idx['year'] = $i;
        if ($idx['cover']  < 0 && preg_match('/(kapak|cover)/', $c)) $idx['cover'] = $i;
        if ($idx['link']   < 0 && preg_match('/(thetelos|link|url)/', $c)) $idx['link'] = $i;
    }
    array_shift($rows);   // başlık satırını at
}
// Konumsal varsayılanlar (başlık yoksa ya da ad tespit edilemezse).
if ($idx['title']  < 0) $idx['title']  = 0;
if ($idx['author'] < 0) $idx['author'] = 1;

$books = [];
foreach ($rows as $r) {
    $title  = trim((string)($r[$idx['title']]  ?? ''));
    $author = trim((string)($r[$idx['author']] ?? ''));

    // POST ID: id sütunu → ya da herhangi bir hücredeki ?p=NNN / .../?p=NNN linki.
    $pid = 0;
    if ($idx['pid'] >= 0 && preg_match('/^\d+$/', trim((string)($r[$idx['pid']] ?? '')))) $pid = (int)$r[$idx['pid']];
    if (!$pid) {
        foreach ($r as $cell) {
            if (preg_match('/[?&]p=(\d+)/', (string)$cell, $m)) { $pid = (int)$m[1]; break; }
            if (preg_match('#/\?p=(\d+)#', (string)$cell, $m)) { $pid = (int)$m[1]; break; }
        }
    }
    // Eğer "başlık" alanı yanlışlıkla salt sayı geldiyse (ID'yi başlık sanma) ve
    // gerçek başlık başka sütundaysa: salt-sayı başlığı reddet.
    if (preg_match('/^\d+$/', $title)) { if (!$pid) $pid = (int)$title; $title = ''; }

    // Kapak: herhangi bir hücrede http(s) görsel/ços linki (post linki değil).
    $cover = '';
    if ($idx['cover'] >= 0) { $cv = trim((string)($r[$idx['cover']] ?? '')); if (preg_match('#^https?://#i', $cv)) $cover = $cv; }
    if ($cover === '') foreach ($r as $cell) { $cell = trim((string)$cell); if (preg_match('#^https?://#i', $cell) && !preg_match('/[?&]p=\d+/', $cell) && preg_match('/\.(jpg|jpeg|png|webp|gif)/i', $cell)) { $cover = $cell; break; } }

    // Yıl
    $year = '';
    if ($idx['year'] >= 0) { $yv = trim((string)($r[$idx['year']] ?? '')); if (preg_match('/^\d{3,4}$/', $yv)) $year = $yv; }
    if ($year === '') foreach ($r as $cell) { $cell = trim((string)$cell); if (preg_match('/^(\d{3,4})$/', $cell, $ym) && (int)$ym[1] >= 100 && (int)$ym[1] <= 2099) { $year = (string)(int)$ym[1]; break; } }

    // Kategori
    $cat = $idx['cat'] >= 0 ? trim((string)($r[$idx['cat']] ?? '')) : trim((string)($r[2] ?? ''));
    if (preg_match('/^\d{3,4}$/', $cat) || preg_match('#^https?://#i', $cat)) $cat = '';

    // Ne başlık ne id varsa satırı atla.
    if ($title === '' && !$pid) continue;

    $books[] = [
        'book_title'  => $title,
        'author_name' => $author,
        'post_id'     => $pid ?: '',   // varsa: yeniden yazma DOĞRUDAN bu id'yi kullanır
        'category'    => $cat,
        'cover'       => $cover,
        'year'        => $year,
    ];
}

echo json_encode(['ok'=>true, 'books'=>$books, 'count'=>count($books)]);
