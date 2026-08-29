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

/* Her kitabın nihai YÖNTEM/DURUM etiketini normalize et. Öncelik sırası:
   hata → yer-tutucu → eski-korundu → kaynak-temelli/bilgi/kaynaksız (method alanı). */
function br_label(array $b): string {
    if (($b['status'] ?? '') === 'error')       return 'hata';
    if (!empty($b['placeholder']))              return 'yer-tutucu (içerik yok)';
    if (!empty($b['kept']))                     return 'eski-korundu (yenilenmedi)';
    if (!empty($b['gated']))                    return 'kapıda (yayında değil)';
    $m = trim((string)($b['method'] ?? ''));
    if ($m !== '')                              return $m;
    if (($b['status'] ?? '') === 'done')        return 'yazıldı';
    if (($b['status'] ?? '') === 'duplicate')   return 'zaten var';
    return $b['status'] ?? '?';
}
/* "Sorunlu mu?" — üç durum, kafa karışmasın:
   • hayır       → gerçek içerik var (kaynak-temelli / kaynaksız-tam)
   • bilgi metni → gerçek ama kaynak-temelli değil (Wikipedia/Wikidata/Claude);
                   içerik VAR, sorun değil ama istersen sonra kaynaktan zenginleştir
   • EVET        → gerçekten ele alınmalı: yer tutucu (boş) / eski korundu / hata */
function br_problem(string $label): string {
    foreach (['kaynak-temelli', 'kaynaksız', 'yazıldı', 'zaten var'] as $o)
        if (strpos($label, $o) === 0) return 'hayır';
    foreach (['bilgi-metni', 'claude-bilgi'] as $o)
        if (strpos($label, $o) === 0) return 'bilgi metni (içerik var)';
    return 'EVET';   // yer-tutucu / eski-korundu / hata
}

$fname = 'sonuc-' . $batch_id . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');

$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF");   // UTF-8 BOM (Excel için)
fputcsv($out, ['#', 'Kitap Adı', 'Yazar Adı', 'Durum/Yöntem', 'Sorunlu?', 'Kaynak', 'URL']);

$i = 0;
foreach ($batch['books'] as $b) {
    $i++;
    $label = br_label($b);
    $url   = $b['post_url'] ?? '';
    if ($url === '' && !empty($b['post_id'])) $url = rtrim(WP_URL, '/') . '/?p=' . $b['post_id'];
    fputcsv($out, [
        $i,
        $b['book_title']  ?? '',
        $b['author_name'] ?? '',
        $label,
        br_problem($label),
        $b['source'] ?? '',
        $url,
    ]);
}
fclose($out);
