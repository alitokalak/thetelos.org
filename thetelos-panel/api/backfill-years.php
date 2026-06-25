<?php
/**
 * backfill-years.php — Mevcut postlara kitabın ilk yayın yılını (OpenLibrary)
 * geriye dönük ekler. _tls_pub_year meta'sı olmayan postları tarar, başlıktan
 * "Kitap - Yazar" ayrıştırır, OpenLibrary first_publish_year'ı çekip kaydeder.
 *
 * Panele girişli admin bu adresi tarayıcıda açar → ilerleme çubuğuyla çalışır.
 * Her "tur" birkaç postu işler (OpenLibrary'yi yormamak için), JS bitene kadar
 * tekrar çağırır.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('yetkisiz'); }

$auth   = 'Basic ' . base64_encode(WP_USER . ':' . WP_APP_PASS);
$wp_api = rtrim(WP_URL, '/') . '/wp-json/wp/v2';

/* WP REST GET — gövde + HTTP kodu + X-WP-Total döndürür */
function bfy_get($url, $auth, $timeout = 20) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $auth],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hlen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $head = substr((string)$resp, 0, $hlen);
    $body = substr((string)$resp, $hlen);
    $total = 0;
    if (preg_match('/x-wp-total:\s*(\d+)/i', $head, $m)) $total = (int)$m[1];
    return [json_decode($body, true), $code, $total];
}

function bfy_post_meta($url, $body, $auth, $timeout = 20) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: ' . $auth],
        CURLOPT_POSTFIELDS     => json_encode($body),
    ]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [json_decode($r, true), $c];
}

/* "Kitap - Yazar" başlığını ayrıştır (entity/dash toleranslı) */
function bfy_split_title($t) {
    $t = html_entity_decode((string)$t, ENT_QUOTES, 'UTF-8');
    if (preg_match('/^(.*?)\s+[-–—]\s+([^-–—]+)$/u', $t, $m)) {
        return [trim($m[1]), trim($m[2])];
    }
    return [trim($t), ''];
}

/* ── İşleme turu (JS tarafından çağrılır) ── */
if (isset($_GET['run'])) {
    header('Content-Type: application/json');
    session_write_close();
    @set_time_limit(120);

    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $per    = 5;
    $page   = intdiv($offset, $per) + 1;

    [$posts, $code, $total] = bfy_get(
        "$wp_api/posts?per_page=$per&page=$page&orderby=date&order=asc&_fields=id,title,meta",
        $auth
    );
    if ($code !== 200 || !is_array($posts)) {
        echo json_encode(['ok' => false, 'error' => "WP HTTP $code", 'offset' => $offset, 'total' => $total]);
        exit;
    }

    $updated = 0; $skipped = 0; $nofound = 0;
    foreach ($posts as $p) {
        $pid = $p['id'] ?? 0;
        if (!$pid) continue;

        // Zaten yılı varsa atla
        $existing = $p['meta']['_tls_pub_year'] ?? '';
        if ($existing !== '' && $existing !== null) { $skipped++; continue; }

        $title = $p['title']['rendered'] ?? '';
        [$book, $author] = bfy_split_title($title);
        $book = trim(preg_replace('/\s*\([^()]*\)\s*$/', '', $book)) ?: $book;
        if ($book === '') { $nofound++; continue; }

        $ol_url = 'https://openlibrary.org/search.json?title=' . urlencode($book)
                . ($author !== '' ? '&author=' . urlencode($author) : '')
                . '&limit=1&fields=first_publish_year';
        $oly = json_decode((string)@file_get_contents($ol_url), true);
        $year = $oly['docs'][0]['first_publish_year'] ?? null;

        if ($year) {
            bfy_post_meta("$wp_api/posts/$pid", ['meta' => ['_tls_pub_year' => (string)(int)$year]], $auth);
            $updated++;
        } else {
            // Bulunamadı → '-' işaretle: "(–)" göster + sonraki taramada atla
            bfy_post_meta("$wp_api/posts/$pid", ['meta' => ['_tls_pub_year' => '-']], $auth);
            $nofound++;
        }
        usleep(250000); // OpenLibrary'yi yorma
    }

    $next = $offset + count($posts);
    $done = (count($posts) < $per) || ($total > 0 && $next >= $total);
    echo json_encode([
        'ok'      => true,
        'total'   => $total,
        'offset'  => $offset,
        'next'    => $next,
        'updated' => $updated,
        'skipped' => $skipped,
        'nofound' => $nofound,
        'done'    => $done,
    ]);
    exit;
}

/* ── Arayüz ── */
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">
<title>Yayın Yılı Doldurma</title>
<style>
  body{font-family:system-ui,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;color:#222}
  h2{margin-bottom:4px}
  .bar{height:14px;background:#eee;border-radius:7px;overflow:hidden;margin:18px 0}
  .bar>div{height:100%;width:0;background:#2e7d32;transition:width .3s}
  button{background:#2e7d32;color:#fff;border:0;padding:12px 22px;border-radius:8px;font-size:15px;cursor:pointer}
  button:disabled{opacity:.5;cursor:default}
  .log{font-size:13px;color:#555;margin-top:14px;white-space:pre-line}
  .muted{color:#888;font-size:13px}
</style></head><body>
<h2>Yayın Yılı Doldurma</h2>
<p class="muted">Mevcut postları OpenLibrary'den tarayıp kitabın ilk yayın yılını ekler. Yılı zaten olan postlar atlanır. Sekmeyi açık tut.</p>
<button id="start">▶ Başlat</button>
<div class="bar"><div id="fill"></div></div>
<div class="log" id="log"></div>
<script>
(function(){
  var btn=document.getElementById('start'), fill=document.getElementById('fill'), log=document.getElementById('log');
  var totUpdated=0, totSkipped=0, totNofound=0;
  function step(offset){
    fetch('?run=1&offset='+offset).then(function(r){return r.json();}).then(function(d){
      if(!d.ok){ log.textContent='Hata: '+(d.error||'bilinmiyor')+' (offset '+offset+'). 3sn sonra tekrar...'; setTimeout(function(){step(offset);},3000); return; }
      totUpdated+=d.updated||0; totSkipped+=d.skipped||0; totNofound+=d.nofound||0;
      var pct = d.total>0 ? Math.min(100, Math.round(d.next/d.total*100)) : 0;
      fill.style.width=pct+'%';
      log.textContent='İşlenen: '+d.next+(d.total?(' / '+d.total):'')+'  ('+pct+'%)\n'
        +'✓ Yıl eklendi: '+totUpdated+'   ⏭ Zaten vardı: '+totSkipped+'   — Bulunamadı: '+totNofound;
      if(d.done){ log.textContent+='\n\n✅ Tamamlandı.'; btn.disabled=false; btn.textContent='▶ Tekrar tara'; return; }
      setTimeout(function(){ step(d.next); }, 400);
    }).catch(function(){ log.textContent='Bağlantı hatası (offset '+offset+'). 3sn sonra tekrar...'; setTimeout(function(){step(offset);},3000); });
  }
  btn.addEventListener('click',function(){ btn.disabled=true; btn.textContent='Çalışıyor...'; totUpdated=totSkipped=totNofound=0; step(0); });
})();
</script>
</body></html>
