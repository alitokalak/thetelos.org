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

/* ── KAYNAK↔KİTAP EŞLEŞME DENETİMİ (offline, bedava) ────────────────────────
   Wikisource URL'i sayfa başlığını İÇİNDE taşır (/wiki/Sayfa_Adı). Aynı güvenli
   eşleştiriciyle (proto_wikisource ile birebir) kitap başlığıyla örtüşüyor mu
   diye bakarız. Örtüşmüyorsa 'suspect' → o post YANLIŞ kaynaktan yazılmış
   olabilir (ör. "Stanzas and Poems" → ".../The_Nature_and_Elements_of_Poetry/
   Melancholia"). Gutenberg/Archive URL'i id taşır, başlık yok → 'unverifiable'
   (URL'den denetlenemez; ayrı olgu denetimi gerekir). */
$si_check = function ($book, $author, $url, $source) {
    // POLİTİKA: Wikisource artık ÖZET kaynağı değil (yapısal olmayan tam-metin
    // araması yanlış eşleştiriyordu; kelime örtüşmesi bir kitabı ona yazılmış
    // eleştiriden ayıramıyor — "The Man vs the State" ↔ "The State vs the Man").
    // Bu yüzden kaynağı Wikisource olan TÜM eski özetler ŞÜPHELİdir → yeniden
    // yazdırılmalı. (Gutenberg/Archive artık yazar-teyitli; onlar için URL'den
    // denetim yapılamaz → 'unverifiable'.)
    $url = (string) $url; $source = (string) $source;
    if (stripos($source, 'wikisource') !== false || preg_match('~wikisource\.org~i', $url)) return 'suspect';
    return '';   // denetlenemez (Gutenberg/Archive id'li URL)
};

$site = rtrim(WP_URL, '/');
foreach ($items as &$it) {
    $it['post_url'] = !empty($it['pid']) ? "$site/?p=" . (int) $it['pid'] : '';
    $it['check']    = $si_check($it['book'] ?? '', $it['author'] ?? '', $it['url'] ?? '', $it['source'] ?? '');
}
unset($it);

// ?only=suspect → yalnız şüpheli (yanlış kaynak) satırları döndür.
if (($_GET['only'] ?? '') === 'suspect') {
    $items = array_values(array_filter($items, fn($x) => ($x['check'] ?? '') === 'suspect'));
}

$action = $_GET['action'] ?? 'list';

if ($action === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kaynak-arsivi-' . date('Ymd-Hi') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Post ID', 'Kitap', 'Yazar', 'Kaynak', 'Eşleşme', 'Kaynak URL', 'Kelime', 'Thetelos Linki']);
    $ck_lbl = ['suspect' => 'ŞÜPHELİ (yanlış kaynak?)', 'ok' => 'eşleşiyor', '' => 'denetlenemez'];
    foreach ($items as $it) {
        fputcsv($out, [$it['pid'] ?? '', $it['book'] ?? '', $it['author'] ?? '', $it['source'] ?? '',
                       $ck_lbl[$it['check'] ?? ''] ?? '', $it['url'] ?? '', $it['chars'] ?? '', $it['post_url'] ?? '']);
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

$suspect = 0; foreach ($items as $x) if (($x['check'] ?? '') === 'suspect') $suspect++;
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'count' => count($items), 'suspect' => $suspect, 'items' => $items], JSON_UNESCAPED_UNICODE);
