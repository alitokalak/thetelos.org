<?php
/**
 * batch-results.php — Bir batch'in TÜM kitaplarını YÖNTEM damgasıyla CSV indir.
 *   ?batch_id=...
 * Amaç: bitişte her kitabın nasıl yazıldığını tek bakışta görmek —
 *   kaynak-temelli / bilgi-metni / yer-tutucu / eski-korundu / hata / kapı.
 * Böylece "sorunlu" olanlar (yer-tutucu, eski-korundu, bilgi-metni, hata)
 * kolayca süzülüp başka modelle/yolla yeniden ele alınabilir.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();

$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_GET['batch_id'] ?? ''));
if (!$batch_id) { http_response_code(400); exit; }
$file = dirname(__DIR__) . '/jobs/' . $batch_id . '.json';
if (!file_exists($file)) { http_response_code(404); exit; }

@ini_set('memory_limit', '512M');
$batch = json_decode(file_get_contents($file), true);
if (!$batch || empty($batch['books'])) { http_response_code(404); exit; }

/* Kısa SONUÇ etiketi — insan okuyabilsin: NE OLDU?
   Öncelik: temizlik(elendi/birleştirildi) → hata → yer-tutucu → eski-korundu →
   yazıldı(yöntem) → bekliyor/işleniyor. */
function br_label(array $b): string {
    if (($b['status'] ?? '') === 'skipped') {
        if (!empty($b['clean_merged']))  return 'birleştirildi (çeviri/kopya)';
        if (!empty($b['clean_removed'])) return 'elendi (yazara ait değil)';
        return 'atlandı';
    }
    if (($b['status'] ?? '') === 'error')       return 'hata';
    if (!empty($b['placeholder']))              return 'yer-tutucu (içerik yok)';
    if (!empty($b['kept']))                     return 'eski-korundu (yenilenmedi)';
    if (!empty($b['gated']))                    return 'kapıda (yayında değil)';
    if (($b['status'] ?? '') === 'pending')     return 'bekliyor';
    if (($b['status'] ?? '') === 'processing')  return 'işleniyor';
    $m = trim((string)($b['method'] ?? ''));
    if (($b['status'] ?? '') === 'done')        return 'YAZILDI' . ($m !== '' ? " ($m)" : '');
    if (($b['status'] ?? '') === 'duplicate')   return 'zaten var';
    return $b['status'] ?? '?';
}
/* Bu satır için açıklama: temizlik sebebi / hata metni / kaynak. */
function br_reason(array $b): string {
    if (($b['status'] ?? '') === 'skipped') return trim((string)($b['skip_reason'] ?? ''));
    if (($b['status'] ?? '') === 'error')   return trim((string)($b['error'] ?? ''));
    return trim((string)($b['source'] ?? ''));
}
/* "Sorunlu mu?" — üç durum, kafa karışmasın:
   • hayır       → gerçek içerik var (kaynak-temelli / kaynaksız-tam)
   • bilgi metni → gerçek ama kaynak-temelli değil (Wikipedia/Wikidata/Claude);
                   içerik VAR, sorun değil ama istersen sonra kaynaktan zenginleştir
   • EVET        → gerçekten ele alınmalı: yer tutucu (boş) / eski korundu / hata */
function br_problem(string $label): string {
    foreach (['kaynak-temelli', 'kaynaksız', 'yazıldı', 'zaten var'] as $o)
        if (strpos($label, $o) === 0) return 'hayır';
    foreach (['bilgi-metni', 'claude-bilgi', 'claude'] as $o)
        if (strpos($label, $o) === 0) return 'bilgi metni (içerik var)';
    return 'EVET';   // yer-tutucu / eski-korundu / hata
}

$fname = 'sonuc-' . $batch_id . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');

// YAZARA GÖRE SIRALA (gruplu okunsun) — orijinal sırayı koruyarak.
$rows = $batch['books'];
$ord  = range(0, count($rows) - 1);
usort($ord, function ($a, $b) use ($rows) {
    $aa = mb_strtolower(trim((string)($rows[$a]['author_name'] ?? '')), 'UTF-8');
    $bb = mb_strtolower(trim((string)($rows[$b]['author_name'] ?? '')), 'UTF-8');
    if ($aa !== $bb) return $aa <=> $bb;
    return $a <=> $b;   // aynı yazarda özgün sıra
});

$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF");   // UTF-8 BOM (Excel için)
fputcsv($out, ['#', 'Yazar', 'Kitap (nihai ad)', 'Sonuç', 'Açıklama / Sebep', 'Post ID', 'URL']);

$i = 0;
foreach ($ord as $ix) {
    $b = $rows[$ix];
    $i++;
    $pid = (int) ($b['post_id'] ?? $b['target_pid'] ?? 0);
    $url = $b['post_url'] ?? '';
    if ($url === '' && $pid) $url = rtrim(WP_URL, '/') . '/?p=' . $pid;
    fputcsv($out, [
        $i,
        $b['author_name'] ?? '',
        $b['book_title']  ?? '',
        br_label($b),
        br_reason($b),
        $pid ?: '',
        $url,
    ]);
}
fclose($out);
