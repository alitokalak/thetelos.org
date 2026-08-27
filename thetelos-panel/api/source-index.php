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
$si_toks = function ($s) {
    $s = mb_strtolower((string) $s, 'UTF-8');
    $x = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s); if ($x !== false && $x !== '') $s = $x;
    $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
    return array_values(array_unique(array_filter(explode(' ', $s), fn($w) => mb_strlen($w) >= 4)));
};
$si_check = function ($book, $author, $url) use ($si_toks) {
    $url = (string) $url;
    if (!preg_match('~wikisource\.org/wiki/(.+)$~i', $url, $m)) return '';   // denetlenemez
    $title = str_replace('_', ' ', rawurldecode($m[1]));
    $leaf  = $title; if (($p = strrpos($title, '/')) !== false) $leaf = substr($title, $p + 1);
    $btitle = trim(preg_replace('/\s*\([^()]*\)\s*$/', '', (string) $book)); if ($btitle === '') $btitle = (string) $book;
    $orig = ''; if (preg_match('/\(([^()]+)\)\s*$/u', (string) $book, $mm)) $orig = trim($mm[1]);
    $eng_t = $si_toks($btitle); $orig_t = $si_toks($orig); $tt = $si_toks($leaf);
    $ov_e = $eng_t  ? count(array_intersect($eng_t,  $tt)) : 0;
    $ov_o = $orig_t ? count(array_intersect($orig_t, $tt)) : 0;
    $title_ok = ($eng_t  && $ov_e >= max(1, (int) ceil(count($eng_t)  * 0.5)))
             || ($orig_t && $ov_o >= max(1, (int) ceil(count($orig_t) * 0.5)));
    $surname = '';
    if (trim((string) $author) !== '') { $ap = preg_split('/\s+/', trim((string) $author)); $surname = mb_strtolower(end($ap), 'UTF-8'); }
    $auth_ok = ($surname !== '' && mb_stripos($title, $surname) !== false)
            || (trim((string) $author) !== '' && mb_stripos($title, (string) $author) !== false);
    return ($title_ok || $auth_ok) ? 'ok' : 'suspect';
};

$site = rtrim(WP_URL, '/');
foreach ($items as &$it) {
    $it['post_url'] = !empty($it['pid']) ? "$site/?p=" . (int) $it['pid'] : '';
    $it['check']    = $si_check($it['book'] ?? '', $it['author'] ?? '', $it['url'] ?? '');
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
