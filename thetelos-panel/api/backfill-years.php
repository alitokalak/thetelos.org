<?php
/**
 * backfill-years.php — Mevcut postlara kitabın ilk yayın yılını (OpenLibrary)
 * geriye dönük ekler. ÖNİZLEMELİ:
 *   1) "Tara": _tls_pub_year'ı olmayan postları gezer, OpenLibrary'den yılı
 *      bulur ama KAYDETMEZ — sonuçları düzenlenebilir bir liste olarak döner.
 *   2) Listeyi gözden geçir/düzelt.
 *   3) "Hepsini Kaydet": onayladığın yılları siteye yazar. Boş bıraktığın
 *      satır "(–)" olarak işaretlenir.
 *
 * Panele girişli admin bu adresi tarayıcıda açar.
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

/* Başlığı ayrıştır → [arama_kitabı, yazar].
   Biçimler: "Kitap -Yazar-" (tire ile sarılı), "Kitap - Yazar" (boşluklu),
   parantezli orijinal adı temizler. */
function bfy_parse($t) {
    $t = trim(html_entity_decode((string)$t, ENT_QUOTES, 'UTF-8'));
    $author = '';
    // Biçim A: "Kitap -Yazar-" (yazar tireyle sarılı; yazar içinde tire olabilir: Al-Farabi)
    if (preg_match('/^(.*\S)\s+-\s*(.+?)\s*-\s*$/u', $t, $m)) {
        $book = $m[1]; $author = $m[2];
    // Biçim B: "Kitap - Yazar" (boşluklu tire)
    } elseif (preg_match('/^(.*?)\s+[-–—]\s+([^-–—]+)$/u', $t, $m)) {
        $book = $m[1]; $author = $m[2];
    } else {
        $book = $t;
    }
    // Arama için: parantez içlerini (orijinal ad, çeviri) at
    $book_q = trim(preg_replace('/\([^()]*\)/u', ' ', $book));
    $book_q = trim(preg_replace('/\s+/', ' ', $book_q));
    if ($book_q === '') $book_q = trim($book);
    return [$book_q, trim($author)];
}

/* UA'lı HTTP GET — OpenLibrary UA'sız istekleri 403'lüyor; curl + UA şart. */
function bfy_http($url, $timeout = 12) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['User-Agent: ThetelosBot/1.0 (+https://thetelos.org; backfill)'],
    ]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($c >= 200 && $c < 300) ? (string)$r : '';
}

/* Kitabın ilk yayın yılını bul: önce OpenLibrary (first_publish_year),
   yoksa Google Books (publishedDate'teki en erken 4 haneli yıl). */
function bfy_year($book_q, $author) {
    if ($book_q === '') return '';
    // 1) OpenLibrary
    $ol_url = 'https://openlibrary.org/search.json?title=' . urlencode($book_q)
            . ($author !== '' ? '&author=' . urlencode($author) : '')
            . '&limit=1&fields=first_publish_year';
    $oly = json_decode(bfy_http($ol_url), true);
    if (!empty($oly['docs'][0]['first_publish_year'])) {
        return (string)(int)$oly['docs'][0]['first_publish_year'];
    }
    // 2) Google Books fallback
    $q = 'intitle:' . $book_q . ($author !== '' ? ' inauthor:' . $author : '');
    $gb_url = 'https://www.googleapis.com/books/v1/volumes?' . http_build_query([
        'q' => $q, 'maxResults' => 5, 'printType' => 'books',
        'fields' => 'items(volumeInfo(publishedDate))',
    ]);
    $gb = json_decode(bfy_http($gb_url), true);
    $best = 0;
    foreach ($gb['items'] ?? [] as $it) {
        if (preg_match('/(\d{4})/', $it['volumeInfo']['publishedDate'] ?? '', $mm)) {
            $y = (int)$mm[1];
            if ($y >= 1 && $y <= (int)date('Y') && ($best === 0 || $y < $best)) $best = $y;
        }
    }
    return $best ? (string)$best : '';
}

/* ── TARAMA TURU: yılları bul ama KAYDETME, listeyi döndür ── */
if (isset($_GET['scan'])) {
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

    $items = [];
    foreach ($posts as $p) {
        $pid = $p['id'] ?? 0;
        if (!$pid) continue;

        // Zaten yılı/işareti olanı önizlemeye alma
        $existing = $p['meta']['_tls_pub_year'] ?? '';
        if ($existing !== '' && $existing !== null) continue;

        $title = $p['title']['rendered'] ?? '';
        [$book_q, $author] = bfy_parse($title);
        $year = bfy_year($book_q, $author);
        usleep(200000); // dış API'leri yorma

        $items[] = [
            'id'     => $pid,
            'title'  => $title,
            'book'   => $book_q,
            'author' => $author,
            'year'   => $year,   // '' = bulunamadı
        ];
    }

    $next = $offset + count($posts);
    $done = (count($posts) < $per) || ($total > 0 && $next >= $total);
    echo json_encode([
        'ok'    => true,
        'total' => $total,
        'next'  => $next,
        'done'  => $done,
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── KAYDETME: onaylanan yılları yaz ── */
if (isset($_POST['save'])) {
    header('Content-Type: application/json');
    @set_time_limit(120);

    $items = json_decode($_POST['items'] ?? '[]', true);
    if (!is_array($items)) { echo json_encode(['ok' => false, 'error' => 'geçersiz veri']); exit; }

    $saved = 0;
    foreach ($items as $it) {
        $pid  = (int)($it['id'] ?? 0);
        if (!$pid) continue;
        $year = trim((string)($it['year'] ?? ''));
        // Geçerli 3-4 haneli yıl → onu yaz; aksi halde "(–)" işareti '-'
        $val  = preg_match('/^\d{3,4}$/', $year) ? $year : '-';
        bfy_post_meta("$wp_api/posts/$pid", ['meta' => ['_tls_pub_year' => $val]], $auth);
        $saved++;
    }
    echo json_encode(['ok' => true, 'saved' => $saved]);
    exit;
}

/* ── Arayüz ── */
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">
<title>Yayın Yılı Doldurma</title>
<style>
  body{font-family:system-ui,sans-serif;max-width:860px;margin:32px auto;padding:0 16px;color:#222}
  h2{margin-bottom:4px}
  .muted{color:#888;font-size:13px}
  .bar{height:12px;background:#eee;border-radius:7px;overflow:hidden;margin:16px 0}
  .bar>div{height:100%;width:0;background:#2e7d32;transition:width .3s}
  button{background:#2e7d32;color:#fff;border:0;padding:11px 20px;border-radius:8px;font-size:15px;cursor:pointer}
  button.secondary{background:#444}
  button:disabled{opacity:.45;cursor:default}
  .row-actions{display:flex;gap:10px;align-items:center;margin:10px 0 4px}
  table{width:100%;border-collapse:collapse;margin-top:14px;font-size:14px}
  th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #eee;vertical-align:top}
  th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#888}
  td .bk{font-weight:600}
  td .au{color:#777;font-size:13px}
  input.yr{width:78px;padding:6px 8px;border:1px solid #ccc;border-radius:6px;font-size:14px;text-align:center}
  input.yr.empty{border-color:#e0a800;background:#fffbe6}
  .log{font-size:13px;color:#555;margin-top:10px;white-space:pre-line}
  .pill{display:inline-block;font-size:11px;padding:2px 8px;border-radius:10px;background:#eee;color:#555;margin-left:6px}
</style></head><body>
<h2>Yayın Yılı Doldurma</h2>
<p class="muted">Önce <b>Tara</b>: yılı olmayan postları OpenLibrary'den tarar ve listeler — <u>siteye yazmaz</u>.
Listeyi düzelt, sonra <b>Hepsini Kaydet</b>'e bas. Boş bıraktığın yıl <b>"(–)"</b> olarak kaydedilir. Sekmeyi açık tut.</p>

<div class="row-actions">
  <button id="scan">▶ Tara</button>
  <button id="save" class="secondary" disabled>Hepsini Kaydet</button>
</div>
<div class="bar"><div id="fill"></div></div>
<div class="log" id="log"></div>

<table id="tbl" hidden>
  <thead><tr><th>#</th><th>Kitap</th><th>Yıl</th></tr></thead>
  <tbody id="rows"></tbody>
</table>

<script>
(function(){
  var scanBtn=document.getElementById('scan'), saveBtn=document.getElementById('save');
  var fill=document.getElementById('fill'), log=document.getElementById('log');
  var tbl=document.getElementById('tbl'), rows=document.getElementById('rows');
  var collected=[], foundCount=0, missCount=0;

  function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }

  function addRows(items){
    var html='';
    for(var i=0;i<items.length;i++){
      var it=items[i];
      var has = /^\d{3,4}$/.test(it.year||'');
      if(has) foundCount++; else missCount++;
      html += '<tr>'
        + '<td>'+esc(it.id)+'</td>'
        + '<td><span class="bk">'+esc(it.book||it.title)+'</span>'
            + (it.author?'<br><span class="au">'+esc(it.author)+'</span>':'')+'</td>'
        + '<td><input class="yr'+(has?'':' empty')+'" data-id="'+esc(it.id)+'" value="'+esc(it.year||'')+'" placeholder="(–)"></td>'
        + '</tr>';
    }
    rows.insertAdjacentHTML('beforeend', html);
    tbl.hidden = rows.children.length===0;
  }

  function scanStep(offset){
    fetch('?scan=1&offset='+offset).then(function(r){return r.json();}).then(function(d){
      if(!d.ok){ log.textContent='Hata: '+(d.error||'bilinmiyor')+'. 3sn sonra tekrar...'; setTimeout(function(){scanStep(offset);},3000); return; }
      if(d.items && d.items.length){ collected=collected.concat(d.items); addRows(d.items); }
      var pct=d.total>0?Math.min(100,Math.round(d.next/d.total*100)):0;
      fill.style.width=pct+'%';
      log.textContent='Tarandı: '+d.next+(d.total?(' / '+d.total):'')+'  ('+pct+'%)   '
        +'✓ Yıl bulundu: '+foundCount+'   — Bulunamadı: '+missCount;
      if(d.done){
        log.textContent+='\n\n✅ Tarama bitti. '+collected.length+' post listelendi. Düzelt ve "Hepsini Kaydet"e bas.';
        scanBtn.disabled=false; scanBtn.textContent='▶ Tekrar tara';
        if(collected.length>0){ saveBtn.disabled=false; saveBtn.textContent='Hepsini Kaydet ('+collected.length+')'; }
        return;
      }
      setTimeout(function(){ scanStep(d.next); }, 300);
    }).catch(function(){ log.textContent='Bağlantı hatası. 3sn sonra tekrar...'; setTimeout(function(){scanStep(offset);},3000); });
  }

  scanBtn.addEventListener('click',function(){
    scanBtn.disabled=true; scanBtn.textContent='Taranıyor...';
    saveBtn.disabled=true;
    collected=[]; foundCount=0; missCount=0; rows.innerHTML=''; tbl.hidden=true;
    scanStep(0);
  });

  saveBtn.addEventListener('click',function(){
    var inputs=rows.querySelectorAll('input.yr');
    var payload=[];
    inputs.forEach(function(inp){ payload.push({id:parseInt(inp.getAttribute('data-id'),10), year:inp.value.trim()}); });
    if(payload.length===0) return;
    if(!confirm(payload.length+' post güncellenecek. Devam edilsin mi?')) return;
    saveBtn.disabled=true; saveBtn.textContent='Kaydediliyor...'; scanBtn.disabled=true;
    var body='save=1&items='+encodeURIComponent(JSON.stringify(payload));
    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body})
      .then(function(r){return r.json();}).then(function(d){
        if(d.ok){ log.textContent='✅ Kaydedildi: '+d.saved+' post güncellendi.'; saveBtn.textContent='Kaydedildi ✓'; }
        else { log.textContent='Kaydetme hatası: '+(d.error||'bilinmiyor'); saveBtn.disabled=false; saveBtn.textContent='Tekrar dene'; }
        scanBtn.disabled=false;
      }).catch(function(){ log.textContent='Kaydetme bağlantı hatası.'; saveBtn.disabled=false; saveBtn.textContent='Tekrar dene'; scanBtn.disabled=false; });
  });
})();
</script>
</body></html>
