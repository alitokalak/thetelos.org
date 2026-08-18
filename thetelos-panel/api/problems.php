<?php
/**
 * problems.php — "Yazdırılamayan / sorunlu eserler" kalıcı listesi.
 *
 * batch-worker.php, yeniden-yaz veya sıfırdan üretim sırasında temiz
 * yayınlanamayan her eseri jobs/rewrite-problems.jsonl dosyasına yazar
 * (bw_flag_problem). Bu uç nokta o günlüğü okur, tekilleştirir ve
 * kullanıcının indirebileceği hale getirir:
 *
 *   ?action=list  → JSON: sorunlu eserler + neden bazında sayım
 *   ?action=csv   → CSV indir (Kitap Adı,Yazar Adı,Yıl,Kapak,Neden) — AYNI
 *                   formatı yeniden yükleyip FARKLI modelle yazdırabilirsin.
 *   ?action=clear → günlüğü sıfırla
 *
 * Tekilleştirme: aynı eser (başlık+yazar normalize) birden çok kez loglanmış
 * olabilir (birden çok deneme). Her eserin EN SON durumu geçerli sayılır;
 * son durum 'ok' ise (sonradan temiz yayınlandı) listeden düşer → liste
 * kendini onarır.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }

$file   = dirname(__DIR__) . '/jobs/rewrite-problems.jsonl';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

/* Neden kodu → insan-okur etiket. */
function pr_label($code) {
    switch ($code) {
        case 'not_found':   return 'sitede bulunamadı';
        case 'shortnote':   return 'kaynaksız kısa not (yayında — zenginleştirilebilir)';
        case 'placeholder': return 'model tanımadı → yer tutucu (yayında)';
        case 'referee':     return 'hakem uydurma buldu → yer tutucu (yayında)';
        case 'source_fallback': return 'tam metin yok → Bilgi Metni ile yazıldı (kısa olabilir)';
        case 'unpublished': return 'yayından kaldırıldı (eski davranış)';
        case 'unknown':     return 'doğrulanamadı (model tanımadı)';
        case 'refused':     return 'model reddetti (uydurma önlendi)';
        case 'gate_draft':  return 'kapıdan geçemedi (eski davranış)';
        case 'wp_error':    return 'WordPress güncelleme hatası';
        case 'gen_error':   return 'içerik kusurlu → mevcut korundu';
        default:            return $code;
    }
}

/* Başlık+yazar normalize anahtarı (bc_norm_key ile uyumlu). */
function pr_key($book, $author) {
    $n = function ($s) {
        $s = mb_strtolower(trim((string) $s), 'UTF-8');
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false && $t !== '') $s = $t;
        $s = preg_replace('/\([^()]*\)/', ' ', $s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    };
    return $n($book) . '||' . $n($author);
}

/* JSONL'i oku → eser bazında SON durumu tut → 'ok' olanları ele. */
function pr_load($file) {
    if (!is_file($file)) return [];
    $latest = [];   // key => record (en son kazanır)
    $fh = @fopen($file, 'r');
    if (!$fh) return [];
    while (($line = fgets($fh)) !== false) {
        $line = trim($line);
        if ($line === '') continue;
        $r = json_decode($line, true);
        if (!is_array($r) || empty($r['book'])) continue;
        $latest[pr_key($r['book'], $r['author'] ?? '')] = $r;
    }
    fclose($fh);
    // Son durumu 'ok' olanları at; kalanları en yeni önce sırala.
    $out = [];
    foreach ($latest as $r) {
        if (($r['reason'] ?? '') === 'ok') continue;
        $out[] = $r;
    }
    usort($out, fn($a, $b) => ($b['t'] ?? 0) <=> ($a['t'] ?? 0));
    return $out;
}

if ($action === 'clear') {
    @unlink($file);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'csv') {
    $rows = pr_load($file);
    $fn   = 'sorunlu-eserler-' . count($rows) . '-' . date('Ymd-Hi') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");   // UTF-8 BOM → Excel Türkçe karakter
    fputcsv($out, ['Kitap Adı', 'Yazar Adı', 'Yıl', 'Kapak', 'Neden']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['book'] ?? '',
            $r['author'] ?? '',
            $r['year'] ?? '',
            $r['cover'] ?? '',
            pr_label($r['reason'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

// action=list (varsayılan)
header('Content-Type: application/json');
$rows   = pr_load($file);
$counts = [];
foreach ($rows as $r) {
    $c = $r['reason'] ?? 'other';
    $counts[$c] = ($counts[$c] ?? 0) + 1;
}
$items = array_map(fn($r) => [
    'book'   => $r['book'] ?? '',
    'author' => $r['author'] ?? '',
    'year'   => $r['year'] ?? '',
    'reason' => $r['reason'] ?? '',
    'label'  => pr_label($r['reason'] ?? ''),
    'pid'    => (int) ($r['pid'] ?? 0),
    'mode'   => $r['mode'] ?? '',
    't'      => (int) ($r['t'] ?? 0),
], $rows);

echo json_encode([
    'ok'     => true,
    'total'  => count($items),
    'counts' => $counts,
    'items'  => $items,
], JSON_UNESCAPED_UNICODE);
