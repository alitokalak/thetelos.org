<?php
/**
 * placeholders.php — Sitedeki YER-TUTUCU (özeti olmayan, "yakında yazılacak")
 * yazıları tarar, listeler ve CSV indirir.
 *   ?action=list  → JSON {ok, count, items:[{id,title,book,author,url,status}]}
 *   ?action=csv   → toplu yükleme formatında CSV (doğrudan tekrar yazdırılabilir)
 *
 * Tespit: gövdede yer-tutucu cümlesi ("is being prepared and will be published
 * here soon") geçen yazılar. WP arama sonuçları alaka sırasıyla gelir; gerçek
 * eşleşmeyi strpos ile DOĞRULAR (kısmi kelime eşleşmelerini eler).
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
@set_time_limit(180);
@ini_set('memory_limit', '512M');

const PH_MARKER = 'is being prepared and will be published here soon';
$auth   = 'Basic ' . base64_encode(WP_USER . ':' . WP_APP_PASS);
$wp_api = rtrim(WP_URL, '/') . '/wp-json/wp/v2';

function ph_wp($url, $auth) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45, CURLOPT_HTTPHEADER => ['Authorization: ' . $auth]]);
    $r = curl_exec($ch); $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$c, json_decode((string) $r, true)];
}

/* Başlıktan yazarı ayır: "Kitap (orijinal) – Yazar" → [kitap, yazar]. Son
   boşluklu tire (– — -) ayıracına göre böler; parantezli orijinal başlıkta kalır. */
function ph_split_title($t) {
    if (preg_match('/^(.*\S)\s+[–—-]\s+([^–—-]+)$/u', $t, $m)) return [trim($m[1]), trim($m[2])];
    return [trim($t), ''];
}

function ph_scan($wp_api, $auth) {
    $items = []; $seen = [];
    foreach (['posts'] as $ep) {
        $zero_streak = 0;
        for ($pg = 1; $pg <= 60; $pg++) {
            $url = "$wp_api/$ep?search=" . rawurlencode(PH_MARKER)
                 . "&per_page=100&page=$pg&status=publish,draft,pending,future&orderby=relevance&_fields=id,title,link,content,status";
            [$c, $rows] = ph_wp($url, $auth);
            if ($c !== 200 || !is_array($rows) || !$rows) break;
            $hit_this_page = 0;
            foreach ($rows as $p) {
                $content = $p['content']['rendered'] ?? '';
                if (strpos($content, PH_MARKER) === false) continue;   // GERÇEKTEN yer-tutucu mu?
                $id = (int) ($p['id'] ?? 0);
                if ($id === 0 || isset($seen[$id])) continue;
                $seen[$id] = 1; $hit_this_page++;
                $title = html_entity_decode($p['title']['rendered'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                [$b, $a] = ph_split_title($title);
                $items[] = ['id' => $id, 'title' => $title, 'book' => $b, 'author' => $a,
                            'url' => $p['link'] ?? '', 'status' => $p['status'] ?? ''];
            }
            // Alaka sırası: gerçek eşleşmeler önce gelir. Arka arkaya 2 sayfa
            // hiç gerçek yer-tutucu yoksa gerisi alakasızdır → dur.
            $zero_streak = $hit_this_page === 0 ? $zero_streak + 1 : 0;
            if ($zero_streak >= 2) break;
            if (count($rows) < 100) break;
        }
    }
    // Başlığa göre sırala (okunur liste)
    usort($items, fn($x, $y) => strcasecmp($x['title'], $y['title']));
    return $items;
}

$action = $_GET['action'] ?? 'list';

if ($action === 'csv') {
    $items = ph_scan($wp_api, $auth);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="yer-tutucular-' . date('Ymd-Hi') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Kitap Adı', 'Yazar Adı', 'Yıl', 'Kapak']);   // toplu yükleme formatı
    foreach ($items as $it) fputcsv($out, [$it['book'], $it['author'], '', '']);
    fclose($out);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$items = ph_scan($wp_api, $auth);
echo json_encode(['ok' => true, 'count' => count($items), 'items' => $items], JSON_UNESCAPED_UNICODE);
