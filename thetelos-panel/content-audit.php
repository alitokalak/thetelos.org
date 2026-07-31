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
        <p>Yayındaki yazılarda yapay zekâ artığı, yarım metin ve tekrar arar</p>
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
      <label style="font-size:12px;color:var(--muted)">Kısa sayılacak sınır:
        <input type="number" id="min-words" value="1500" step="100" min="0"
               style="width:82px;padding:4px 6px;margin-left:4px">
        kelime
      </label>
      <button class="btn" id="btn-undo" style="color:#e05252">↩ Onarımı Geri Al</button>
      <button class="btn" id="btn-autofix">🔧 Ağır Hataları Onar</button>
      <button class="btn" id="btn-csv" style="display:none">↓ CSV indir</button>
      <span id="ca-status"></span>
    </div>

    <div class="prog" id="prog"><i></i></div>

    <p style="font-size:12px;color:var(--muted);margin-bottom:14px;max-width:860px">
      Bu ekran <b>hiçbir şeyi değiştirmez</b> — sadece bulur ve gösterir.
      <b style="color:#e05252">Ağır</b> bulgular okuyucunun doğrudan göreceği kazalardır
      (parça işareti sızması, modelin kendi süreciyle konuşması, yarım biten metin) —
      bunlar öncelikli. <b style="color:#ff8c00">Orta</b> genelde yeniden üretimle düzelir.
      <b>Hafif</b> olanlar üslup notu, acil değil.
    </p>

    <div class="filters" id="filters" style="display:none">
      <button data-f="all" class="on">Tümü</button>
      <button data-f="3">Sadece ağır</button>
      <button data-f="refusal">Üretim reddi</button>
      <button data-f="part_marker">Parça işareti</button>
      <button data-f="meta_talk">Model konuşması</button>
      <button data-f="prompt_leak">Prompt sızması</button>
      <button data-f="truncated">Yarım bitmiş</button>
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
    .then(r=>r.json()).finally(()=>clearTimeout(t));
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
    h += '<tr data-id="'+p.id+'"><td><input type="checkbox" class="ca-cb"></td>'+
      '<td><b>'+escH(p.title)+'</b><br><small style="color:var(--muted)">'+escH(p.date)+'</small></td><td>';
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
/* Ağır bulgulardan SADECE onarılabilir olanlar. "Yarım bitmiş" ağır ama
   onarılamaz — eksik metni uydurmak onarım değil, tahrifat olur. */
const FIXABLE = ['prompt_leak','meta_talk','part_marker'];

async function autofix(){
  // Tarama yapıldıysa doğrudan o kayıtlara git: 7500 yazıyı baştan gezmek
  // yerine ~50 yazıya dokunulur, işlem saniyeler sürer.
  const targets = all
    .filter(p => p.sev === 3 && p.flags.some(f => FIXABLE.includes(f.code)))
    .map(p => p.id);

  if (all.length && !targets.length) {
    $('ca-status').textContent = '✓ Onarılabilir ağır bulgu yok. (Yarım biten yazılar yeniden üretim ister.)';
    return;
  }

  if (targets.length) {
    if(!confirm(targets.length + ' yazıda ağır kusur onarılacak:\n\n'+
                '• prompt/süreç satırları silinir\n'+
                '• parça işaretleri temizlenir\n\n'+
                'Markdown kalıntısına, yarım biten ve kısa içeriğe DOKUNULMAZ. Devam?')) return;

    stop = false;
    $('btn-autofix').disabled = true;
    $('btn-stop').style.display = '';
    let done = 0, skipped = 0;
    for (let i = 0; i < targets.length && !stop; i += 20) {
      const chunk = targets.slice(i, i + 20);
      waiting('Onarılıyor: ' + done + '/' + targets.length);
      try {
        const d = await post('action=fix&mode=severe&ids=' + chunk.join(','), 90000);
        if (d && d.ok) { done += d.fixed; skipped += d.skipped; }
      } catch(e) { skipped += chunk.length; }
      waitingDone();
    }
    $('btn-autofix').disabled = false;
    $('btn-stop').style.display = 'none';
    $('ca-status').textContent = '✓ ' + done + ' yazı onarıldı' + (skipped ? ', ' + skipped + ' atlandı' : '') +
                                 '. Doğrulamak için taramayı tekrar çalıştır.';
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
        d = await post('action=autofix&mode=severe&offset='+offset+'&limit='+lim, 90000);
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
  if(!confirm('Onarımın sildiği paragraflar geri getirilecek.\n\n'+
              'Son 24 saatte yapılan silmeler, WordPress revizyonlarından geri yüklenir. '+
              'Elle yaptığın düzenlemelere dokunulmaz. Devam?')) return;

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

$('btn-scan').addEventListener('click', scan);
$('btn-autofix').addEventListener('click', autofix);
$('btn-undo').addEventListener('click', undo);
$('btn-stop').addEventListener('click', ()=>{ stop = true; });
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
