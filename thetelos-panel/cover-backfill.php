<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kapak Bul — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.site-table{width:100%;border-collapse:collapse;font-size:13px}
.site-table th{text-align:left;padding:10px 12px;border-bottom:2px solid var(--border);color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.site-table td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:middle}
.site-table tr:hover td{background:rgba(255,255,255,.02)}
.stats-row{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.stat-box{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 16px}
.stat-val{font-size:20px;font-weight:700}
.stat-lbl{font-size:11px;color:var(--muted);margin-top:2px}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600}
.badge-ol{background:rgba(0,120,212,.15);color:#3ba0ff}
.badge-google{background:rgba(0,171,107,.15);color:#00ab6b}
.badge-amazon{background:rgba(255,153,0,.15);color:#ff9900}
.badge-err{background:rgba(204,24,24,.15);color:#cc1818}
.cb-cover{width:44px;height:66px;object-fit:cover;border-radius:3px;background:var(--surface2);border:1px solid var(--border)}
.cb-cover.empty{display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:18px}
.cb-book{font-weight:600}
.cb-book small{display:block;color:var(--muted);font-weight:400;font-size:11px}
.cb-link{font-size:12px;color:var(--tls-gold);text-decoration:none;white-space:nowrap}
.cb-link:hover{text-decoration:underline}
#cb-status{font-size:12px;color:var(--tls-gold);min-height:16px}
.bulk-row{display:flex;gap:8px;margin:0 0 14px;flex-wrap:wrap;align-items:center}
label.auto{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px;cursor:pointer}
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
      <a href="recategorize.php"><span class="ico">🗂️</span> Kategori Düzelt</a>
      <a href="category-cleanup.php"><span class="ico">🧹</span> Kategori Temizle</a>
      <a href="cover-backfill.php" class="active"><span class="ico">🖼</span> Kapak Bul</a>
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
        <h2>Kapak Bul</h2>
        <p>Kapağı olmayan kitaplara OpenLibrary / Google Books / Amazon'dan kapak bul ve ata</p>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-box"><div class="stat-val" id="st-total">–</div><div class="stat-lbl">Toplam Kitap</div></div>
      <div class="stat-box"><div class="stat-val" id="st-covered" style="color:#00ab6b">–</div><div class="stat-lbl">Kapaklı</div></div>
      <div class="stat-box"><div class="stat-val" id="st-skipped" style="color:var(--muted)">–</div><div class="stat-lbl">Atlandı</div></div>
      <div class="stat-box"><div class="stat-val" id="st-remaining" style="color:var(--tls-gold)">–</div><div class="stat-lbl">Kapaksız</div></div>
    </div>

    <div class="bulk-row">
      <button class="btn btn-primary" id="btn-scan">🔎 Tara ve Öner</button>
      <label class="auto"><input type="checkbox" id="auto-loop"> Otomatik devam et</label>
      <label class="auto"><input type="checkbox" id="auto-apply"> Bulunanları otomatik uygula</label>
      <button class="btn" id="btn-apply" style="display:none">✅ Bulunanları Uygula</button>
      <button class="btn" id="btn-skip" style="display:none">🚫 Bulunamayanları atla</button>
      <span id="cb-status"></span>
    </div>

    <p style="font-size:12px;color:var(--muted);margin-bottom:14px;max-width:760px">
      Her satır kapağı olmayan bir kitap. Bulunan aday kapak önizlemede görünür (kaynak rozeti:
      <span class="badge badge-ol">OL</span> <span class="badge badge-google">Google</span>
      <span class="badge badge-amazon">Amazon</span>). <b>Uygula</b> ile kapağı indirip featured
      image yaparız. En hızlısı: <b>"Otomatik devam et"</b> + <b>"Bulunanları otomatik uygula"</b>
      kutularını işaretle, araç tüm kapaksız kitapları tarar, bulduklarını atar. Sekmeyi açık tut.
    </p>

    <div id="result"><div style="text-align:center;padding:40px;color:var(--muted)">Tara butonuna bas.</div></div>
  </main>
</div>

<script>
const $ = id => document.getElementById(id);
let shown = [];
let tbody = null;
let scanning = false, applying = false;

function post(body){
  return fetch('api/cover-backfill.php', {
    method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/x-www-form-urlencoded'}, body
  }).then(r => r.json());
}
function setStats(s){
  if(!s) return;
  $('st-total').textContent     = (s.total||0).toLocaleString();
  $('st-covered').textContent   = (s.covered||0).toLocaleString();
  $('st-skipped').textContent   = (s.skipped||0).toLocaleString();
  $('st-remaining').textContent = (s.remaining||0).toLocaleString();
}
function escH(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}

function ensureTable(){
  if(tbody) return;
  $('result').innerHTML =
    '<table class="site-table"><thead><tr>'+
    '<th style="width:34px"></th><th style="width:52px">Kapak</th><th>Kitap</th>'+
    '<th style="width:90px">Kaynak</th><th style="width:110px">Sayfa</th>'+
    '</tr></thead><tbody id="cb-tbody"></tbody></table>';
  tbody = $('cb-tbody');
}
function srcBadge(s){
  if(s==='openlibrary') return '<span class="badge badge-ol">OL</span>';
  if(s==='google')      return '<span class="badge badge-google">Google</span>';
  if(s==='amazon')      return '<span class="badge badge-amazon">Amazon</span>';
  return '<span class="badge badge-err">Yok</span>';
}
function addRow(r){
  ensureTable();
  const found = !!r.cover;
  const tr = document.createElement('tr');
  tr.dataset.postId = r.post_id;
  tr.dataset.found  = found ? '1' : '0';
  tr.dataset.cands  = JSON.stringify(r.candidates || []);
  tr.innerHTML =
    '<td>'+(found?'<input type="checkbox" class="cb-cb" checked>':'')+'</td>'+
    '<td>'+(found
        ? '<img class="cb-cover" src="'+escH(r.cover)+'" loading="lazy" referrerpolicy="no-referrer">'
        : '<div class="cb-cover empty">?</div>')+'</td>'+
    '<td class="cb-book">'+escH(r.book)+'<small>'+escH(r.author)+'</small></td>'+
    '<td>'+srcBadge(r.source)+'</td>'+
    '<td><a class="cb-link" href="'+escH(r.view_url)+'" target="_blank" rel="noopener">Sayfayı Aç ↗</a></td>';
  tbody.appendChild(tr);
}
function refreshButtons(){
  const sel  = tbody ? tbody.querySelectorAll('.cb-cb:checked').length : 0;
  const notF = tbody ? tbody.querySelectorAll('tr[data-found="0"]').length : 0;
  $('btn-apply').style.display = sel ? '' : 'none';
  $('btn-apply').textContent   = '✅ Bulunanları Uygula ('+sel+')';
  $('btn-skip').style.display  = notF ? '' : 'none';
  $('btn-skip').textContent    = '🚫 Bulunamayan '+notF+' kitabı atla';
}

// Seçili satırların kapaklarını uygula. auto=true → sessiz (otomatik akış).
function applySelected(auto){
  if(applying) return Promise.resolve();
  const items = [];
  tbody && tbody.querySelectorAll('tr').forEach(tr => {
    const cb = tr.querySelector('.cb-cb');
    if(!cb || !cb.checked) return;
    let cands = []; try { cands = JSON.parse(tr.dataset.cands||'[]'); } catch(e){}
    if(cands.length) items.push({ post_id:+tr.dataset.postId, candidates:cands });
  });
  if(!items.length) return Promise.resolve();
  applying = true;
  $('btn-apply').disabled = true;
  $('cb-status').textContent = items.length+' kapak indiriliyor…';
  return post('action=apply&items='+encodeURIComponent(JSON.stringify(items)))
    .then(d => {
      applying = false; $('btn-apply').disabled = false;
      if(!d || !d.ok){ $('cb-status').textContent='Uygulama hatası.'; return; }
      setStats(d.stats);
      const failed = new Set((d.failed||[]).map(Number));
      // Başarılı satırları listeden düşür; başarısızları "bulunamadı"ya çevir
      items.forEach(it => {
        const tr = tbody.querySelector('tr[data-post-id="'+it.post_id+'"]');
        if(!tr) return;
        if(failed.has(it.post_id)){
          tr.dataset.found='0'; tr.dataset.cands='[]';
          tr.cells[0].innerHTML=''; tr.cells[1].innerHTML='<div class="cb-cover empty">?</div>';
          tr.cells[3].innerHTML=srcBadge('');
        } else { tr.remove(); }
      });
      $('cb-status').textContent = '✓ '+(d.applied||0)+' kapak atandı.'+((d.failed||[]).length?' '+d.failed.length+' başarısız.':'');
      refreshButtons();
    })
    .catch(() => { applying=false; $('btn-apply').disabled=false; $('cb-status').textContent='Bağlantı hatası.'; });
}

function scanOnce(){
  if(scanning || applying) return;
  scanning = true;
  $('btn-scan').disabled = true; $('btn-scan').textContent = 'Aranıyor…';
  $('cb-status').textContent = 'Kapak aranıyor (4 kitap)…';
  post('action=scan&exclude='+encodeURIComponent(shown.join(',')))
    .then(d => {
      scanning = false;
      $('btn-scan').disabled = false; $('btn-scan').textContent = '🔎 Tara ve Öner';
      if(!d || !d.ok){ $('cb-status').textContent='Hata oluştu.'; return; }
      setStats(d.stats);
      d.rows.forEach(r => { shown.push(r.post_id); addRow(r); });
      refreshButtons();
      if(d.done){ $('cb-status').textContent='✓ Kapaksız kitap kalmadı.'; $('auto-loop').checked=false; return; }
      $('cb-status').textContent = d.rows.length+' kitap tarandı.';
      // Otomatik uygula açıksa: önce bu turda bulunanları uygula, sonra devam et
      const cont = () => { if($('auto-loop').checked) setTimeout(scanOnce, 350); };
      if($('auto-apply').checked){ applySelected(true).then(cont); }
      else cont();
    })
    .catch(() => { scanning=false; $('btn-scan').disabled=false; $('btn-scan').textContent='🔎 Tara ve Öner'; $('cb-status').textContent='Bağlantı hatası.'; });
}

$('btn-scan').addEventListener('click', scanOnce);
$('auto-loop').addEventListener('change', function(){ if(this.checked && !scanning && !applying) scanOnce(); });
$('btn-apply').addEventListener('click', () => applySelected(false));
document.addEventListener('change', e => { if(e.target.classList.contains('cb-cb')) refreshButtons(); });

$('btn-skip').addEventListener('click', () => {
  const ids = [];
  tbody.querySelectorAll('tr[data-found="0"]').forEach(tr => ids.push(tr.dataset.postId));
  if(!ids.length) return;
  if(!confirm(ids.length+' kitap "kapak bulunamadı" olarak işaretlenecek ve tekrar taranmayacak. Onaylıyor musun?')) return;
  post('action=skip&ids='+encodeURIComponent(ids.join(',')))
    .then(d => {
      if(!d || !d.ok){ $('cb-status').textContent='Hata.'; return; }
      setStats(d.stats);
      ids.forEach(id => { const tr = tbody.querySelector('tr[data-post-id="'+id+'"]'); if(tr) tr.remove(); });
      $('cb-status').textContent = '✓ '+(d.skipped||0)+' kitap atlandı.';
      refreshButtons();
    });
});
</script>
</body>
</html>
