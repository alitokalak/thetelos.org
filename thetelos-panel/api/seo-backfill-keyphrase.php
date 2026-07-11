<?php
/**
 * seo-backfill-keyphrase.php — Mevcut yazılara Yoast "odak anahtar kelimesi"
 * (_yoast_wpseo_focuskw) geriye dönük ekler.
 *
 * NEDEN: İçerik üreticisi meta description yazıyordu ama odak anahtar kelime
 * atamıyordu. Yoast'ın kırmızı/yeşil "SEO score"u odak kelime üzerinden
 * hesaplandığından, tüm otomatik yazılar kırmızı görünüyordu.
 *
 * NE YAPAR: Odak kelimesi BOŞ olan her yayınlanmış yazıya, başlıktan türetilen
 * temiz kitap adını odak kelime olarak yazar. İçeriğe / başlığa / meta'ya
 * DOKUNMAZ, dışarıya istek atmaz. Elle girilmiş odak kelimeleri KORUR
 * (üzerine yazmaz). Yeniden çalıştırmak güvenlidir (idempotent).
 *
 * KULLANIM: Panele girişli admin bu adresi tarayıcıda açar, "Başlat"a basar.
 * İşlem parça parça (chunk) ilerler; sekmeyi açık tut.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('yetkisiz'); }

// WordPress çekirdeğini yükle (doğrudan update_post_meta için — REST'e gerek yok).
ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();

/* Başlıktan temiz kitap adını (odak kelime) türet:
   "Kitap - Yazar" ekini ve sondaki parantezli orijinal adı temizle. */
function tls_kw_from_title($post_id, $title) {
    $title = trim(html_entity_decode((string) $title, ENT_QUOTES, 'UTF-8'));
    $book  = $title;

    // Yazar adını taksonomiden al, başlığın sonundaki " - Yazar" ekini sök.
    $authors = wp_get_post_terms($post_id, 'authors', ['fields' => 'names']);
    if (!is_wp_error($authors) && $authors) {
        foreach ($authors as $an) {
            $book = preg_replace('/\s*[-–—]\s*' . preg_quote($an, '/') . '\s*$/u', '', $book);
        }
    }
    // Sondaki parantezli kısmı temizle: "On Wealth (Περὶ Πλούτου …)" → "On Wealth"
    $book = trim(preg_replace('/\s*\([^()]*\)\s*$/u', '', $book));
    if ($book === '') $book = $title;
    return $book;
}

/* ── AJAX çalışma adımı ── */
if (isset($_GET['run'])) {
    header('Content-Type: application/json');
    $offset = max(0, (int) ($_GET['offset'] ?? 0));
    $chunk  = 50;

    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $chunk,
        'offset'         => $offset,
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'no_found_rows'  => false,
        'ignore_sticky_posts' => 1,
    ]);

    $total   = (int) $q->found_posts;
    $updated = 0;
    $seen    = 0;

    foreach ($q->posts as $pid) {
        $seen++;
        $existing = get_post_meta($pid, '_yoast_wpseo_focuskw', true);
        if (is_string($existing) && trim($existing) !== '') continue; // elle gireni koru
        $kw = tls_kw_from_title($pid, get_the_title($pid));
        if ($kw !== '') {
            update_post_meta($pid, '_yoast_wpseo_focuskw', $kw);
            $updated++;
        }
    }

    $next = $offset + $seen;
    echo json_encode([
        'ok'        => true,
        'total'     => $total,
        'processed' => $next,
        'updated'   => $updated,
        'done'      => ($seen === 0 || $next >= $total),
        'next'      => $next,
    ]);
    exit;
}

/* ── HTML arayüz ── */
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SEO — Odak Kelime Doldurma</title>
<style>
  body{font-family:system-ui,-apple-system,sans-serif;max-width:720px;margin:40px auto;padding:0 20px;line-height:1.6;color:#1a1814}
  h1{font-size:22px}
  .card{border:1px solid #e2dece;border-radius:10px;padding:20px;background:#fff}
  button{font:inherit;font-weight:600;padding:12px 26px;border-radius:999px;border:1px solid #1a1814;background:#1a1814;color:#fff;cursor:pointer}
  button:disabled{opacity:.5;cursor:default}
  .bar{height:10px;background:#eee;border-radius:6px;overflow:hidden;margin:18px 0 8px}
  .bar > i{display:block;height:100%;width:0;background:#00ab6b;transition:width .3s}
  .log{font-size:14px;color:#6b6560;margin-top:10px}
  .muted{color:#6b6560;font-size:13px}
  code{background:#f3f1ea;padding:2px 6px;border-radius:4px}
</style></head>
<body>
  <h1>Odak Anahtar Kelime — Toplu Doldurma</h1>
  <div class="card">
    <p>Odak kelimesi <b>boş olan</b> her yayınlanmış yazıya, başlıktan türetilen
       temiz kitap adını Yoast <b>odak anahtar kelimesi</b> olarak yazar.
       İçeriğe dokunmaz, elle girilmiş kelimeleri korur, tekrar çalıştırmak güvenlidir.</p>
    <button id="go">Başlat</button>
    <div class="bar"><i id="fill"></i></div>
    <div class="log" id="log">Hazır.</div>
    <p class="muted">İşlem bitene kadar bu sekmeyi kapatma. Örnek türetme:
       <code>On Wealth (Περὶ Πλούτου …) – Diogenes</code> → odak kelime: <code>On Wealth</code></p>
  </div>
<script>
(function(){
  var go=document.getElementById('go'), log=document.getElementById('log'), fill=document.getElementById('fill');
  var totalUpdated=0;
  function step(offset){
    fetch('?run=1&offset='+offset, {credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(d){
        if(!d.ok){ log.textContent='Hata oluştu. 3sn sonra tekrar…'; setTimeout(function(){step(offset);},3000); return; }
        totalUpdated += d.updated;
        var pct = d.total ? Math.min(100, Math.round(d.processed/d.total*100)) : 100;
        fill.style.width = pct+'%';
        log.textContent = d.processed+' / '+d.total+' tarandı · '+totalUpdated+' yazıya odak kelime yazıldı';
        if(d.done){ log.textContent='✓ Bitti. Toplam '+totalUpdated+' yazıya odak kelime eklendi.'; go.disabled=false; go.textContent='Tekrar Çalıştır'; return; }
        step(d.next);
      })
      .catch(function(){ log.textContent='Bağlantı hatası. 3sn sonra tekrar…'; setTimeout(function(){step(offset);},3000); });
  }
  go.addEventListener('click', function(){ go.disabled=true; go.textContent='Çalışıyor…'; totalUpdated=0; step(0); });
})();
</script>
</body></html>
