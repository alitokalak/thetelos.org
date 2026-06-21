<?php
/**
 * fix-post-authors.php — Yazarı bağlanmamış yayınları onar.
 *
 * Batch işlemede bazı yazılar 'authors' taksonomisine bağlanmadan kalmış
 * (duplicate-skip yolu veya yazar terimi oluşturma sırasında zaman aşımı).
 * Bu araç yazıları sayfa sayfa tarar; yazarı OLMAYAN her yazı için, başlıktaki
 * "Kitap - Yazar" kalıbından yazar adını çıkarır, mevcut yazar terimini bulur
 * (yoksa oluşturur) ve yazıya bağlar.
 *
 * POST: type (posts|analysis), page (1..), per_page (vars. 20)
 * Dönüş: { ok, type, page, total_pages, scanned, fixed, items:[{id,title,author,created}] }
 *
 * İstemci sayfa sayfa çağırır (Cloudflare ~100sn sınırına takılmamak için).
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
set_time_limit(120);

$type     = trim($_POST['type'] ?? 'posts');
if (!in_array($type, ['posts', 'analysis'], true)) $type = 'posts';
$page     = max(1, (int)($_POST['page'] ?? 1));
$per_page = max(1, min(50, (int)($_POST['per_page'] ?? 20)));

$auth   = 'Basic ' . base64_encode(WP_USER . ':' . WP_APP_PASS);
$wp_api = rtrim(WP_URL, '/') . '/wp-json/wp/v2';

/* curl GET — gövde + header (X-WP-TotalPages için) + http kodu döner */
function fpa_get($url, $auth) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $auth],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsz  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $head = substr((string)$resp, 0, $hsz);
    $body = substr((string)$resp, $hsz);
    $total_pages = 0;
    if (preg_match('/x-wp-totalpages:\s*(\d+)/i', $head, $m)) $total_pages = (int)$m[1];
    return [json_decode($body, true), $code, $total_pages];
}

function fpa_post($url, $body, $auth, $timeout = 30) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: ' . $auth],
        CURLOPT_POSTFIELDS     => json_encode($body),
    ]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [json_decode($r, true), $c];
}

/* Başlıktan yazar adını çıkar: "Kitap - Yazar" → "Yazar" (son " - " ayracı).
   Yazar kısmındaki olası "(Orijinal Ad)" parantezini de temizler. */
function fpa_author_from_title($title) {
    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $pos = mb_strrpos($title, ' - ');
    if ($pos === false) return '';
    $author = trim(mb_substr($title, $pos + 3));
    // Yazar adı mantıklı bir uzunlukta olmalı (yanlış kesmeleri ele)
    if ($author === '' || mb_strlen($author) > 80) return '';
    return $author;
}

$rows_url = "$wp_api/$type?page=$page&per_page=$per_page&status=publish,draft,future,pending,private"
          . "&_fields=id,title,authors,link&orderby=date&order=desc";
[$rows, $code, $total_pages] = fpa_get($rows_url, $auth);

if ($code === 400) {
    // Sayfa aralığı aşıldı — bittiği anlamına gelir
    echo json_encode(['ok'=>true,'type'=>$type,'page'=>$page,'total_pages'=>$page-1,'scanned'=>0,'fixed'=>0,'items'=>[]]);
    exit;
}
if (!is_array($rows)) {
    echo json_encode(['ok'=>false,'error'=>"WP yanıtı okunamadı (HTTP $code)"]);
    exit;
}

$scanned = 0; $fixed = 0; $items = [];

foreach ($rows as $post) {
    $scanned++;
    $pid     = $post['id'] ?? 0;
    $authors = $post['authors'] ?? [];
    if (!$pid) continue;
    // Zaten yazar(lar) bağlıysa atla
    if (is_array($authors) && count($authors) > 0) continue;

    $title  = $post['title']['rendered'] ?? '';
    $author = fpa_author_from_title($title);
    if ($author === '') continue;   // yazar adı çıkarılamadı — dokunma

    // Mevcut yazar terimini bul (tam eşleşme, büyük/küçük harf duyarsız)
    [$terms] = fpa_get("$wp_api/authors?search=" . urlencode($author) . '&per_page=20&_fields=id,name', $auth);
    $tid = null;
    foreach ($terms ?? [] as $t) {
        if (mb_strtolower($t['name'] ?? '', 'UTF-8') === mb_strtolower($author, 'UTF-8')) { $tid = $t['id']; break; }
    }

    $created = false;
    if (!$tid) {
        // Yazar terimi yok — oluştur (biyografi sonradan "Yazarları Tara" ile üretilebilir)
        [$nt, $nc] = fpa_post("$wp_api/authors", ['name' => $author], $auth);
        $tid = $nt['id'] ?? null;
        // Yarış durumu: başka bir istek aynı anda oluşturmuş olabilir → tekrar ara
        if (!$tid) {
            [$retry] = fpa_get("$wp_api/authors?search=" . urlencode($author) . '&per_page=20&_fields=id,name', $auth);
            foreach ($retry ?? [] as $t) {
                if (mb_strtolower($t['name'] ?? '', 'UTF-8') === mb_strtolower($author, 'UTF-8')) { $tid = $t['id']; break; }
            }
        } else {
            $created = true;
        }
    }
    if (!$tid) continue;

    // Yazıya bağla
    [$upd, $uc] = fpa_post("$wp_api/$type/$pid", ['authors' => [$tid]], $auth);
    if ($uc >= 200 && $uc < 300) {
        $fixed++;
        $items[] = ['id'=>$pid, 'title'=>$title, 'author'=>$author, 'created'=>$created, 'link'=>$post['link'] ?? ''];
    }
}

echo json_encode([
    'ok'          => true,
    'type'        => $type,
    'page'        => $page,
    'total_pages' => $total_pages,
    'scanned'     => $scanned,
    'fixed'       => $fixed,
    'items'       => $items,
], JSON_UNESCAPED_UNICODE);
