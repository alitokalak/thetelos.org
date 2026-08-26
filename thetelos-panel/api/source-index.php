<?php
/**
 * source-index.php — KAYNAK ARŞİVİ: hangi kitap (thetelos postu) hangi kaynaktan/
 * linkten yazıldı. jobs/source-index.jsonl'i okur, post'a göre TEKİLLEŞTİRİR
 * (son kayıt geçerli), listeler / CSV / JSON verir.
 *   ?action=list  → JSON {ok, count, items:[{pid,book,author,source,url,chars,post_url,t}]}
 *   ?action=csv   → CSV
 *   ?action=json  → ham JSON indir
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();

$file = dirname(__DIR__) . '/jobs/source-index.jsonl';
$rows = [];
if (is_file($file)) {
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $r = json_decode($line, true);
        if (!is_array($r) || empty($r['pid'])) continue;
        $rows[(int) $r['pid']] = $r;   // son kayıt geçerli (post başına tek)
    }
}
$items = array_values($rows);
usort($items, fn($a, $b) => strcasecmp((string)($a['book'] ?? ''), (string)($b['book'] ?? '')));

$site = rtrim(WP_URL, '/');
foreach ($items as &$it) {
    $it['post_url'] = !empty($it['pid']) ? "$site/?p=" . (int) $it['pid'] : '';
}
unset($it);

$action = $_GET['action'] ?? 'list';

if ($action === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kaynak-arsivi-' . date('Ymd-Hi') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Post ID', 'Kitap', 'Yazar', 'Kaynak', 'Kaynak URL', 'Kelime', 'Thetelos Linki']);
    foreach ($items as $it) {
        fputcsv($out, [$it['pid'] ?? '', $it['book'] ?? '', $it['author'] ?? '', $it['source'] ?? '',
                       $it['url'] ?? '', $it['chars'] ?? '', $it['post_url'] ?? '']);
    }
    fclose($out);
    exit;
}
if ($action === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="kaynak-arsivi-' . date('Ymd-Hi') . '.json"');
    echo json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'count' => count($items), 'items' => $items], JSON_UNESCAPED_UNICODE);
