<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>İçerik Denetimi — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.site-table{width:100%;border-collapse:collapse;font-size:13px}
.site-table th{text-align:left;padding:9px 10px;border-bottom:2px solid var(--border);color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.site-table td{padding:8px 10px;border-bottom:1px solid var(--border);vertical-align:top}
.stats-row{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.stat-box{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 16px;min-width:104px}
.stat-val{font-size:20px;font-weight:700}
.stat-lbl{font-size:11px;color:var(--muted);margin-top:2px}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;margin:1px 3px 1px 0}
.sev3{background:rgba(204,24,24,.16);color:#e05252}
.sev2{background:rgba(255,140,0,.18);color:#ff8c00}
.sev1{background:rgba(255,255,255,.07);color:var(--muted)}
.bulk-row{display:flex;gap:8px;margin:0 0 14px;flex-wrap:wrap;align-items:center}
#ca-status{font-size:12px;color:var(--tls-gold);min-height:16px}
.ca-link{font-size:12px;color:var(--tls-gold);text-decoration:none;white-space:nowrap}
.sample{display:block;font-size:11px;color:var(--muted);margin-top:3px;font-family:ui-monospace,monospace;
    max-width:520px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.prog{height:5px;background:var(--surface2);border-radius:3px;overflow:hidden;margin:0 0 16px;display:none}
.prog i{display:block;height:100%;background:var(--tls-gold);width:0;transition:width .25s}
h3.sec{font-size:14px;margin:26px 0 10px;color:var(--text)}
.filters{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px}
.filters button{font-size:11px;padding:4px 11px;border-radius:12px;border:1px solid var(--border);
    background:transparent;color:var(--muted);cursor:pointer}
.filters button.on{background:var(--tls-gold);border-color:var(--tls-gold);color:#111}
</style>
</head>
<body>
<div class="tls-shell">
  <aside class="tls-sidebar">
    <div class="tls-logo"><h1>Thetelos</h1><small>Content Panel</small></div>
    <nav class="tls-nav">
      <a href="panel.php"><span class="ico">✍</span> İçerik Üret</a>
      <a href="seo.php"><span class="ico">🔍</span> İçerik SEO</a>
      <a href="seo-site.php"><span class="ico">🌐</span> Site SEO</a>
      <a href="content-audit.php" class="active"><span class="ico">🩺</span> İçerik Denetimi</a>
      <a href="content-guard.php"><span class="ico">🛡️</span> İçerik Koruma</a>
      <a href="recategorize.php"><span class="ico">🗂️</span> Kategori Düzelt</a>
      <a href="category-cleanup.php"><span class="ico">🧹</span> Kategori Temizle</a>
      <a href="cover-backfill.php"><span class="ico">🖼</span> Kapak Bul</a>
      <a href="amazon-match.php"><span class="ico">🛒</span> Amazon</a>
      <a href="settings.php"><span class="ico">⚙</span> Ayarlar</a>
      <a href="<?= rtrim(WP_URL,'/') ?>/wp-admin/" target="_blank"><span class="ico">🔗</span> WP Admin</a>
      <a href="<?= rtrim(WP_URL,'/') ?>/" target="_blank"><span class="ico">↗</span> Siteyi Gör</a>
    </nav>
    <div class="tls-sidebar-footer"><a href="index.php?logout=1">Çıkış Yap</a></div>
  </aside>

  <main class="tls-main">
    <div class="tls-header">
      <div>
        <h2>İçerik Denetimi</h2>
        <p>Yayındaki yazılarda yapay zekâ artığı, yarım metin ve tekrar arar <span style="opacity:.5;font-size:11px">· sürüm 2026-08-01 · arka plan işi</span></p>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-box"><div class="stat-val" id="st-scan">–</div><div class="stat-lbl">Taranan</div></div>
      <div class="stat-box"><div class="stat-val" id="st-3" style="color:#e05252">–</div><div class="stat-lbl">Ağır</div></div>
      <div class="stat-box"><div class="stat-val" id="st-2" style="color:#ff8c00">–</div><div class="stat-lbl">Orta</div></div>
      <div class="stat-box"><div class="stat-val" id="st-1" style="color:var(--muted)">–</div><div class="stat-lbl">Hafif</div></div>
    </div>

    <div class="bulk-row">
      <button class="btn btn-primary" id="btn-scan">🩺 Taramayı Başlat</button>
      <button class="btn" id="btn-stop" style="display:none">■ Durdur</button>
      <button class="btn" id="btn-kick" style="display:none">⟳ Canlandır</button>
      <label style="font-size:12px;color:var(--muted)">Kısa sayılacak sınır:
        <input type="number" id="min-words" value="1500" step="100" min="0"
               style="width:82px;padding:4px 6px;margin-left:4px">
        kelime
      </label>
      <button class="btn btn-primary" id="btn-auto">✨ Hepsini Düzelt</button>
      <button class="btn" id="btn-undo" style="color:#e05252">↩ Onarımı Geri Al</button>
      <button class="btn" id="btn-autofix">🔧 Onarılabilirleri Düzelt</button>
      <button class="btn" id="btn-complete">🩹 Yarım Kalanları Tamamla</button>
      <button class="btn" id="btn-regen">♻️ Kalanları Yeniden Üret</button>
      <button class="btn" id="btn-draft" style="color:#e05252">⛔ Yayından Kaldır</button>
      <button class="btn" id="btn-csv" style="display:none">↓ CSV indir</button>
      <span id="ca-status"></span>
    </div>

    <div class="prog" id="prog"><i></i></div>
    <div id="ca-errors" style="display:none;margin:0 0 14px;padding:10px 12px;border:1px solid rgba(204,24,24,.35);
         border-radius:8px;background:rgba(204,24,24,.07);font-size:12px;line-height:1.6;max-width:900px">
      <b style="color:#e05252">Başarısız olanlar</b>
      <div id="ca-errors-list" style="margin-top:6px;color:var(--muted);font-family:ui-monospace,monospace;font-size:11px"></div>
    </div>

    <div style="font-size:12px;color:var(--muted);margin-bottom:14px;max-width:900px;line-height:1.7">
      <b style="color:var(--text)">Kolay yol:</b> tarama bitince <b>✨ Hepsini Düzelt</b>'e bas ve sekmeyi kapat.
      Her yazı için doğru işlem sırayla uygulanır: artıkları temizle → eksikse tamamla →
      kitap tanınmıyorsa yayından kaldır. Aşağıdaki diğer butonlar tek tür işi ayrı ayrı
      yapmak istersen diye duruyor; satır seçersen yalnız seçtiklerin işlenir.
      <div style="margin-top:8px;display:grid;grid-template-columns:auto 1fr;gap:4px 10px;align-items:baseline">
        <span style="color:#3ec27a;white-space:nowrap">🔧 onarılabilir</span>
        <span>artık satır / biçim / tekrar temizliği — <b>bedava ve hızlı</b>, önce bunu çalıştır</span>
        <span style="color:#7aa2f7;white-space:nowrap">🩹 tamamlanabilir</span>
        <span>metin eksik; kaldığı yerden sürdürülür — <b>API, ücretli</b>, kitap başına ~1 dk</span>
        <span style="color:#c58af0;white-space:nowrap">♻️ yeniden üretilecek</span>
        <span>metin baştan geçersiz (üretim reddi) — <b>API, ücretli</b>, en yavaşı</span>
        <span style="color:var(--muted);white-space:nowrap">eylem gerekmiyor</span>
        <span>yalnızca üslup notu var, dokunmaya değmez</span>
      </div>
      <div style="margin-top:8px">
        Önem: <b style="color:#e05252">Ağır</b> okuyucunun gördüğü kaza (üretim reddi, prompt şablonu,
        parça işareti, yarım metin) · <b style="color:#ff8c00">Orta</b> biçim/tekrar ·
        <b>Hafif</b> üslup.
      </div>
    </div>

    <div class="filters" id="filters" style="display:none">
      <button data-f="all" class="on">Tümü</button>
      <button data-f="3">Sadece ağır</button>
      <button data-f="prompt_dump">Prompt şablonu</button>
      <button data-f="refusal">Üretim reddi</button>
      <button data-f="part_marker">Parça işareti</button>
      <button data-f="meta_talk">Model konuşması</button>
      <button data-f="prompt_leak">Prompt sızması</button>
      <button data-f="orphan_heading">Boş başlık</button>
      <button data-f="truncated">Cümle kesilmiş</button>
      <button data-f="dup_para">Tekrar</button>
      <button data-f="md_leak">Markdown</button>
      <button data-f="short">Kısa</button>
    </div>

    <div id="result"><div style="text-align:center;padding:40px;color:var(--muted)">Taramayı başlat.</div></div>
  </main>
</div>

<script>
const $ = id => document.getElementById(id);
/* Zaman sınırlı istek: sunucu yanıt vermezse ekran sonsuza kadar beklemesin,
   istek iptal edilip yeniden denensin. */
function post(body, ms){
  const ctl = new AbortController();
  const t   = setTimeout(()=>ctl.abort(), ms || 90000);
  return fetch('api/content-audit.php', {method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/x-www-form-urlencoded'}, body, signal: ctl.signal})
    .then(async r => {
      // Yanıt JSON değilse (PHP hatası, zaman aşımı sayfası, oturum bitmesi)
      // gövdeyi hata mesajına koy. Eskiden bu durum sessizce "atlandı" diye
      // sayılıyordu ve ekran "bu bulgular onarılamıyor" diye YANLIŞ bilgi
      // veriyordu — oysa istek hiç çalışmamıştı.
      const txt = await r.text();
      try { return JSON.parse(txt); }
      catch (e) {
        throw new Error('HTTP ' + r.status + ' — ' + txt.replace(/<[^>]*>/g,' ').trim().slice(0, 220));
      }
    })
    .finally(()=>clearTimeout(t));
}

/* Bekleme sırasında saniye sayan durum yazısı — "donmuş mu?" sorusunu bitirir. */
let tick = null;
function waiting(label){
  clearInterval(tick);
  const t0 = Date.now();
  const paint = ()=>{ $('ca-status').textContent = label + ' (' + Math.round((Date.now()-t0)/1000) + ' sn)'; };
  paint(); tick = setInterval(paint, 1000);
}
function waitingDone(){ clearInterval(tick); tick = null; }
function escH(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}

let all = [], stop = false, filter = 'all';

function counts(){
  return [3,2,1].map(s => all.filter(f => f.sev === s).length);
}

function render(){
  const rows = all.filter(f => {
    if (filter === 'all') return true;
    if (filter === '3')   return f.sev === 3;
    return f.flags.some(x => x.code === filter);
  });
  if(!rows.length){
    $('result').innerHTML = '<div style="padding:24px;color:var(--muted)">'+
      (all.length ? 'Bu filtrede kayıt yok.' : '✓ Bulgu yok.')+'</div>';
    return;
  }
  let h = '<table class="site-table"><thead><tr>'+
    '<th style="width:30px"><input type="checkbox" id="ca-all"></th>'+
    '<th>Kitap</th><th style="width:420px">Bulgular</th>'+
    '<th style="width:70px">Kelime</th><th style="width:120px"></th></tr></thead><tbody>';
  rows.forEach(p=>{
    // Eylem sırası: onar → tamamla → yeniden üret. Ama HAFİF bir not, koca bir
    // yazıyı yeniden yazdırmak için gerekçe değildir: sadece sev1 bulgusu olan
    // yazılar "eylem gerekmiyor" sayılır.
    const act = p.fixable ? '<span class="badge" style="background:rgba(40,160,90,.18);color:#3ec27a">🔧 onarılabilir</span>'
              : p.compl  ? '<span class="badge" style="background:rgba(80,120,220,.18);color:#7aa2f7">🩹 tamamlanabilir</span>'
              : p.sev>=2 ? '<span class="badge" style="background:rgba(190,120,220,.18);color:#c58af0">♻️ yeniden üretilecek</span>'
                         : '<span class="badge sev1">eylem gerekmiyor</span>';
    h += '<tr data-id="'+p.id+'"><td><input type="checkbox" class="ca-cb"></td>'+
      '<td><b>'+escH(p.title)+'</b><br><small style="color:var(--muted)">'+escH(p.date)+'</small>'+
      '<br>'+act+'</td><td>';
    p.flags.forEach(f=>{
      h += '<div><span class="badge sev'+f.sev+'">'+escH(f.label)+'</span>'+
           '<span class="sample">'+escH(f.sample)+'</span></div>';
    });
    h += '</td><td>'+p.words+'</td>'+
      '<td><a class="ca-link" href="'+escH(p.link)+'" target="_blank" rel="noopener">Aç ↗</a><br>'+
      '<a class="ca-link" href="'+escH(p.edit)+'" target="_blank" rel="noopener">Düzenle ✎</a></td></tr>';
  });
  h += '</tbody></table>';
  $('result').innerHTML = h;
  const a = $('ca-all');
  if(a) a.addEventListener('change', ()=>document.querySelectorAll('.ca-cb').forEach(cb=>cb.checked=a.checked));
}

function csv(){
  const head = 'id,tarih,baslik,kelime,onem,bulgular,link\n';
  const body = all.map(p =>
    [p.id, p.date, '"'+String(p.title).replace(/"/g,'""')+'"', p.words, p.sev,
     '"'+p.flags.map(f=>f.label).join(' | ')+'"', p.link].join(',')
  ).join('\n');
  const url = URL.createObjectURL(new Blob([head+body], {type:'text/csv;charset=utf-8'}));
  const a = document.createElement('a');
  a.href = url; a.download = 'icerik-denetimi.csv'; a.click();
  URL.revokeObjectURL(url);
}

async function scan(){
  all = []; stop = false; filter = 'all';
  $('btn-scan').disabled = true; $('btn-stop').style.display = '';
  $('prog').style.display = 'block'; $('filters').style.display = 'flex';
  $('btn-csv').style.display = 'none';
  const minw = parseInt($('min-words').value) || 0;
  let offset = 0, total = 0, scanned = 0, skipped = 0;

  while(!stop){
    // Dilim küçük tutulur ve geçici hatada beklenip tekrar denenir: 7500 yazılık
    // taramada tek bir zaman aşımı bütün işi çöpe atmamalı.
    let d = null;
    for (let attempt = 1; attempt <= 4 && !stop; attempt++) {
      // Dilim her denemede küçülür: yavaş bir dilim en azından parça parça geçsin.
      const lim = [50, 25, 10, 5][attempt - 1];
      waiting('Taranıyor: ' + scanned + '/' + (total || '?') + (attempt > 1 ? ' — yeniden deneme ' + attempt + '/4' : ''));
      try {
        d = await post('action=scan&offset='+offset+'&limit='+lim+'&min_words='+minw, 90000);
        if (d && d.ok) break;
        d = null;
      } catch(e) { d = null; }
      waitingDone();
      $('ca-status').textContent = 'Yanıt gelmedi, yeniden deneniyor ('+attempt+'/4)… ' + scanned + '/' + total;
      await new Promise(r => setTimeout(r, attempt * 2000));
    }
    waitingDone();
    // Bir dilim ısrarla düşüyorsa (ör. içindeki dev bir metin isteği zorluyorsa)
    // tüm taramayı bırakma: o dilimi atla, kalan 7000 yazı taransın.
    if(!d){
      skipped++;
      offset  += 50;
      scanned += 50;
      $('ca-status').textContent = '⚠ Bir dilim atlandı, devam ediliyor… ' + scanned + '/' + (total || '?');
      if(total && offset >= total) break;
      continue;
    }

    total   = d.total || total;
    scanned += d.scanned || 0;
    all = all.concat(d.findings || []);
    all.sort((a,b)=> b.sev - a.sev);

    const c = counts();
    $('st-scan').textContent = scanned + (total ? ' / ' + total : '');
    $('st-3').textContent = c[0]; $('st-2').textContent = c[1]; $('st-1').textContent = c[2];
    $('prog').firstElementChild.style.width = total ? Math.round(scanned/total*100)+'%' : '0';
    $('ca-status').textContent = 'Taranıyor… ' + scanned + '/' + total;
    render();

    if(d.next < 0) { $('ca-status').textContent = '✓ Tarama bitti — ' + scanned + ' yazı, ' + all.length + ' bulgu' + (skipped ? ', ' + skipped + ' dilim atlandı' : '') + '.'; break; }
    offset = d.next;
  }
  if(stop) $('ca-status').textContent = 'Durduruldu — ' + scanned + ' yazı tarandı.';
  $('btn-scan').disabled = false; $('btn-stop').style.display = 'none';
  $('btn-csv').style.display = all.length ? '' : 'none';
}

/* Otomatik onarım: tarama beklemeden tüm siteyi gezer. Süreç satırlarını
   siler, markdown kalıntısını HTML'e çevirir. Yarım biten / kısa içeriğe
   dokunmaz — onlar yeniden üretim ister. */
async function autofix(){
  // Hedefler bulgu TÜRÜNE göre tahmin edilmez: tarama her yazıda onarımı
  // deneyip 'fixable' bilgisini döndürüyor. Eskiden tür listesine bakılıyordu
  // ve tespit listesi silme listesinden geniş olduğu için buton "onar" deyip
  // hiçbir şey değiştirmiyordu.
  // Satır seçiliyse yalnız onlar işlenir — diğer iki buton da böyle davranıyor.
  const sel  = [...document.querySelectorAll('.ca-cb:checked')].map(cb => cb.closest('tr').dataset.id);
  const pool = sel.length ? all.filter(p => sel.includes(String(p.id))) : all;
  const targets = pool.filter(p => p.fixable).map(p => p.id);

  if (all.length && !targets.length) {
    const trunc = all.filter(p => p.compl).length;
    $('ca-status').textContent = 'Silinerek onarılabilecek bulgu yok.' +
      (trunc ? ' ' + trunc + ' yazı yarım kalmış — "Yarım Kalanları Tamamla" ile düzelir.'
             : ' Kalanlar yeniden üretim ister.');
    return;
  }

  if (targets.length) {
    if(!confirm(targets.length + ' yazı onarılacak:\n\n'+
                '• prompt/süreç satırları silinir\n'+
                '• parça işaretleri temizlenir\n'+
                '• "#### Başlık", "---" ve "**kalın**" gerçek HTML\'e çevrilir\n\n'+
                'Yarım biten ve kısa içeriğe DOKUNULMAZ. Eski metin yedeklenir. Devam?')) return;

    stop = false;
    $('btn-autofix').disabled = true;
    $('btn-stop').style.display = '';
    let done = 0, skipped = 0, failed = 0, lastErr = '';
    // Dilim küçük: her yazı için WordPress kaydı + revizyon yazılıyor, 20'lik
    // dilimler zaman aşımına düşüyordu ve hepsi "atlandı" sayılıyordu.
    for (let i = 0; i < targets.length && !stop; i += 5) {
      const chunk = targets.slice(i, i + 5);
      waiting('Onarılıyor: ' + done + '/' + targets.length);
      try {
        const d = await post('action=fix&mode=all&ids=' + chunk.join(','), 300000);
        if (d && d.ok) { done += d.fixed; skipped += d.skipped; }
        else { failed += chunk.length; lastErr = lastErr || (d && d.error) || 'bilinmeyen yanıt'; }
      } catch(e) { failed += chunk.length; lastErr = lastErr || e.message; }
      waitingDone();
      $('prog').style.display = 'block';
      $('prog').firstElementChild.style.width = Math.round((i+chunk.length)/targets.length*100)+'%';
    }
    $('btn-autofix').disabled = false;
    $('btn-stop').style.display = 'none';
    $('ca-status').textContent =
      failed ? '✗ ' + failed + ' yazıda istek başarısız: ' + lastErr
      : done  ? '✓ ' + done + ' yazı onarıldı' + (skipped ? ', ' + skipped + ' atlandı' : '') +
                '. Doğrulamak için taramayı tekrar çalıştır.'
              : '⚠ ' + skipped + ' yazıda silinecek bir şey çıkmadı — bunlar yeniden üretim ister.';
    return;
  }

  // Tarama yapılmadıysa: tüm siteyi gez, yine yalnız ağır kusurları onar.
  if(!confirm('Henüz tarama yapılmadı. Tüm site gezilip SADECE ağır kusurlar onarılacak '+
              '(prompt/süreç satırları, parça işaretleri). Markdown kalıntısına ve yarım '+
              'biten içeriğe dokunulmaz. Devam?')) return;

  stop = false;
  $('btn-autofix').disabled = true;
  $('btn-stop').style.display = '';
  $('prog').style.display = 'block';

  let offset = 0, seen = 0, fixed = 0, total = 0, skips = 0;

  while(!stop){
    let d = null;
    for (let attempt = 1; attempt <= 3 && !stop; attempt++) {
      const lim = [40, 15, 5][attempt - 1];
      waiting('Onarılıyor: ' + seen + '/' + (total || '?') + ' — ' + fixed + ' düzeltildi');
      try {
        d = await post('action=autofix&mode=all&offset='+offset+'&limit='+lim, 90000);
        if (d && d.ok) break;
        d = null;
      } catch(e) { d = null; }
      waitingDone();
      $('ca-status').textContent = 'Yanıt gelmedi, yeniden deneniyor ('+attempt+'/3)… ' + seen + '/' + total;
      await new Promise(r => setTimeout(r, attempt * 2000));
    }
    waitingDone();
    // Bir dilim ısrarla düşerse tüm işi bırakma: atla ve devam et.
    if(!d){ skips++; offset += 40; seen += 40; if(total && offset >= total) break; continue; }

    total  = d.total || total;
    seen  += d.seen || 0;
    fixed += d.fixed || 0;
    $('prog').firstElementChild.style.width = total ? Math.round(Math.min(seen,total)/total*100)+'%' : '0';
    $('ca-status').textContent = 'Onarılıyor… ' + seen + '/' + total + ' — ' + fixed + ' yazı düzeltildi';
    if(d.next < 0) break;
    offset = d.next;
  }

  $('btn-autofix').disabled = false;
  $('btn-stop').style.display = 'none';
  $('ca-status').textContent = (stop ? '■ Durduruldu — ' : '✓ Bitti — ') + fixed + ' yazı onarıldı (' +
    seen + ' yazı gezildi' + (skips ? ', ' + skips + ' dilim atlandı' : '') + ').';
}

/* Onarımın sildiği metni geri getirir. Meta yedeği yoksa WordPress
   revizyonundan kurtarır; yalnız SİLME geri alınır, elle yapılmış
   düzenlemeler korunur. */
async function undo(){
  /* Satır seçiliyse YALNIZ onlar geri alınır — tek bozuk yazı için tüm
     onarımları geri almak gerekmez. */
  const sel = [...document.querySelectorAll('.ca-cb:checked')].map(cb => cb.closest('tr').dataset.id);
  if (sel.length) {
    if(!confirm(sel.length + ' seçili yazı eski haline döndürülecek. Devam?')) return;
    $('btn-undo').disabled = true;
    waiting('Geri alınıyor: ' + sel.length + ' yazı');
    try {
      const d = await post('action=undo&ids=' + sel.join(','), 120000);
      $('ca-status').textContent = (d && d.ok)
        ? '✓ ' + d.restored + ' yazı eski haline döndü' +
          (d.samples && d.samples.length ? ': ' + d.samples.join(', ') : '') + '.'
        : '✗ Geri alınamadı.';
    } catch(e) { $('ca-status').textContent = '✗ ' + e.message; }
    waitingDone();
    $('btn-undo').disabled = false;
    return;
  }

  if(!confirm('DİKKAT: Hiç satır seçmedin — TÜM onarımlar geri alınacak.\n\n'+
              'Tek bir yazıyı geri almak için önce tabloda o satırı işaretle.\n\n'+
              'Gerçekten HEPSİNİ geri almak istiyor musun?')) return;

  stop = false;
  $('btn-undo').disabled = true; $('btn-stop').style.display = '';
  $('prog').style.display = 'block';

  let offset = 0, seen = 0, restored = 0, total = 0;
  while(!stop){
    let d = null;
    for (let attempt = 1; attempt <= 3 && !stop; attempt++) {
      const lim = [40, 15, 5][attempt - 1];
      waiting('Geri alınıyor: ' + seen + '/' + (total || '?') + ' — ' + restored + ' yazı kurtarıldı');
      try {
        d = await post('action=undo&offset='+offset+'&limit='+lim+'&hours=24', 90000);
        if (d && d.ok) break;
        d = null;
      } catch(e) { d = null; }
      waitingDone();
      await new Promise(r => setTimeout(r, attempt * 2000));
    }
    waitingDone();
    if(!d){ offset += 40; seen += 40; if(total && offset >= total) break; continue; }

    total = d.total || total;
    seen += d.seen || 0;
    restored += d.restored || 0;
    $('prog').firstElementChild.style.width = total ? Math.round(Math.min(seen,total)/total*100)+'%' : '0';
    $('ca-status').textContent = 'Geri alınıyor… ' + seen + '/' + total + ' — ' + restored + ' kurtarıldı';
    if(d.next < 0) break;
    offset = d.next;
  }
  $('btn-undo').disabled = false; $('btn-stop').style.display = 'none';
  $('ca-status').textContent = '✓ Geri alma bitti — ' + restored + ' yazı eski haline döndü.';
}

/* ── ARKA PLAN İŞİ ────────────────────────────────────────────────────────
   Cloudflare 100 saniyeden uzun isteği keser (HTTP 524) ve bir kitabın
   üretimi bundan uzun sürer. Bu yüzden burada "bekle ve sonucu al" YOK:
   iş sunucuda başlatılır, arka planda ilerler, ekran yalnızca durumu sorar.
   Sorgular milisaniyeler sürdüğü için 524 imkânsızdır; sekmeyi kapatsan bile
   iş sunucuda devam eder.                                                   */
let poller = null;

function jobLabel(j) {
  const total = (j.ids || []).length;
  const kind  = j.kind === 'regen' ? 'Yeniden üretiliyor'
              : j.kind === 'auto'  ? 'Düzeltiliyor' : 'Tamamlanıyor';
  if (j.status === 'done')    return '✓ Bitti — ' + j.done + ' yazı, ' + (j.words||0).toLocaleString('tr') +
                                     ' kelime' + (j.failed ? ', ' + j.failed + ' başarısız' : '') + '.';
  if (j.status === 'stopped') return '■ Durduruldu — ' + j.done + '/' + total + ' işlendi.';
  return kind + ' ' + (j.done + j.failed) + '/' + total + ' — ' + String(j.current || '').slice(0, 45) +
         (j.failed ? '  (' + j.failed + ' başarısız)' : '');
}

function jobPoll() {
  clearInterval(poller);
  poller = setInterval(async () => {
    let j;
    try { j = await post('action=job_status', 20000); } catch (e) { return; }
    if (!j || !j.ok || j.none) return;

    $('prog').style.display = 'block';
    const total = (j.ids || []).length || 1;
    $('prog').firstElementChild.style.width = Math.round((j.done + j.failed) / total * 100) + '%';
    $('ca-status').textContent = jobLabel(j);
    // Hata konsola gömülmez: ekranda görünür, yoksa "neden olmadı" bilinmiyor.
    if (j.errors && j.errors.length) {
      $('ca-errors').style.display = '';
      $('ca-errors-list').innerHTML = j.errors.map(e => escH(e)).join('<br>') +
        ((j.failed > j.errors.length) ? '<br>… ve ' + (j.failed - j.errors.length) + ' tane daha' : '');
    }

    $('btn-kick').style.display = (j.status === 'running') ? '' : 'none';
    if (j.status !== 'running') {
      clearInterval(poller);
      $('btn-complete').disabled = false;
      $('btn-regen').disabled    = false;
      $('btn-stop').style.display = 'none';
    }
  }, 4000);
}

async function startJob(kind, ids, onay) {
  if (!ids.length) { $('ca-status').textContent = 'İşlenecek yazı yok.'; return; }
  if (!confirm(onay)) return;

  $('btn-complete').disabled = true;
  $('btn-regen').disabled    = true;
  $('btn-stop').style.display = '';
  $('ca-status').textContent  = 'İş başlatılıyor…';

  try {
    const d = await post('action=job_start&kind=' + kind + '&ids=' + ids.join(','), 180000);
    if (!d || !d.ok) throw new Error((d && d.error) || 'başlatılamadı');
    $('ca-status').textContent = 'Arka planda başladı — ' + d.total + ' yazı. Sekmeyi kapatabilirsin.';
    jobPoll();
  } catch (e) {
    $('btn-complete').disabled = false;
    $('btn-regen').disabled    = false;
    $('btn-stop').style.display = 'none';
    // "signal is aborted" = bizim zaman aşımımız. Sunucu meşgulken (ör. büyük
    // bir batch çalışıyorken) istek geç dönüyor; bunu hata gibi göstermek
    // kullanıcıyı yanlış yönlendiriyordu.
    $('ca-status').textContent =
        e.message.startsWith('HTTP 401') ? '✗ Oturum düşmüş — sayfayı yenileyip tekrar giriş yap.'
      : /abort/i.test(e.message)         ? '✗ Sunucu meşgul (büyük bir üretim sürüyor olabilir) — birkaç dakika sonra tekrar dene.'
      :                                    '✗ Başlatılamadı: ' + e.message;
  }
}

function selectedPool() {
  const sel = [...document.querySelectorAll('.ca-cb:checked')].map(cb => cb.closest('tr').dataset.id);
  return sel.length ? all.filter(p => sel.includes(String(p.id))) : all;
}

function complete() {
  const ids = selectedPool().filter(p => p.compl).map(p => p.id);
  startJob('complete', ids,
    ids.length + ' yazı kaldığı yerden TAMAMLANACAK.\n\n' +
    'Arka planda çalışır, sekmeyi kapatabilirsin. API kullanır (ücretli).\n' +
    'Eski metin yedeklenir, "Onarımı Geri Al" ile dönülebilir.\n\nDevam?');
}

function regen() {
  const ids = selectedPool().filter(p => !p.fixable && !p.compl && p.sev >= 2).map(p => p.id);
  startJob('regen', ids,
    ids.length + ' yazı SIFIRDAN yeniden yazılacak.\n\n' +
    'Arka planda çalışır, sekmeyi kapatabilirsin. API kullanır (ücretli, yavaş).\n' +
    'Eski metin yedeklenir.\n\nDevam?');
}

/* Sayfa açılışında süren bir iş varsa kendiliğinden takibe başla. */
(async () => {
  try {
    const j = await post('action=job_status', 15000);
    if (j && j.ok && !j.none && j.status === 'running') {
      $('btn-complete').disabled = true;
      $('btn-regen').disabled    = true;
      $('btn-stop').style.display = '';
      $('ca-status').textContent = jobLabel(j);
      jobPoll();
    }
  } catch (e) {}
})();

/* Modelin tanımadığı kitaplar (üretim reddi) yeniden üretilemez: kitap ya
   yoktur ya da yazar yanlıştır. Bunlar liste hatasıdır; tek doğru eylem
   yazıyı yayından kaldırmaktır — silinmez, taslağa alınır. */
async function draftPosts(){
  const ids = selectedPool().filter(p => p.flags.some(f => f.code === 'refusal')).map(p => p.id);
  if(!ids.length){ $('ca-status').textContent = 'Yayından kaldırılacak yazı yok (üretim reddi bulunamadı).'; return; }
  if(!confirm(ids.length + ' yazı YAYINDAN KALDIRILACAK (taslağa alınır, silinmez).\n\n'+
              'Bunlar modelin tanımadığı kitaplar — büyük ihtimalle listede olmaması gereken '+
              'ya da yazarı yanlış kayıtlar. Devam?')) return;

  $('btn-draft').disabled = true;
  let done = 0;
  for (let i = 0; i < ids.length; i += 20) {
    const chunk = ids.slice(i, i + 20);
    waiting('Yayından kaldırılıyor: ' + done + '/' + ids.length);
    try {
      const d = await post('action=draft&ids=' + chunk.join(','), 120000);
      if (d && d.ok) done += (d.drafted || d.done || 0);
    } catch(e) {}
    waitingDone();
  }
  $('btn-draft').disabled = false;
  $('ca-status').textContent = '✓ ' + done + ' yazı yayından kaldırıldı (taslak). Listeden çıkarmak için taramayı tekrar çalıştır.';
}

/* TEK DÜĞME. Kusur türünü bilmene, hangi butona basacağını seçmene gerek yok:
   her yazı için sıra sabittir — onar → (reddiyeyse) yayından kaldır →
   tamamla → gerekiyorsa sıfırdan yaz. Arka planda çalışır, sekme kapanabilir. */
function autoAll(){
  const ids = selectedPool().map(p => p.id);
  startJob('auto', ids,
    ids.length + ' yazı otomatik düzeltilecek.\n\n' +
    'Her yazı için sırayla: artıkları temizle → eksikse tamamla → ' +
    'kitap tanınmıyorsa yayından kaldır.\n\n' +
    'Arka planda çalışır, SEKMEYİ KAPATABİLİRSİN. API kullanır (ücretli).\n' +
    'Eski metinler yedeklenir, "Onarımı Geri Al" ile dönülebilir.\n\nBaşlasın mı?');
}

$('btn-kick').addEventListener('click', async () => {
  $('ca-status').textContent = 'Yeniden ateşleniyor…';
  try { await post('action=job_kick', 20000); jobPoll(); } catch (e) {}
});
$('btn-auto').addEventListener('click', autoAll);
$('btn-draft').addEventListener('click', draftPosts);
$('btn-scan').addEventListener('click', scan);
$('btn-autofix').addEventListener('click', autofix);
$('btn-undo').addEventListener('click', undo);
$('btn-complete').addEventListener('click', complete);
$('btn-regen').addEventListener('click', regen);
$('btn-stop').addEventListener('click', ()=>{ stop = true; post('action=job_stop').catch(()=>{}); });
$('btn-csv').addEventListener('click', csv);
$('filters').addEventListener('click', e=>{
  const b = e.target.closest('button'); if(!b) return;
  filter = b.dataset.f;
  [...$('filters').children].forEach(x=>x.classList.toggle('on', x===b));
  render();
});
</script>
</body>
</html>
