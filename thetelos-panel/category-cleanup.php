<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kategori Temizle — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.site-table{width:100%;border-collapse:collapse;font-size:13px}
.site-table th{text-align:left;padding:9px 10px;border-bottom:2px solid var(--border);color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.site-table td{padding:8px 10px;border-bottom:1px solid var(--border);vertical-align:middle}
.site-table tr:hover td{background:rgba(255,255,255,.02)}
.stats-row{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.stat-box{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 16px}
.stat-val{font-size:20px;font-weight:700}
.stat-lbl{font-size:11px;color:var(--muted);margin-top:2px}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600}
.badge-core{background:rgba(0,171,107,.15);color:#00ab6b}
.badge-extra{background:rgba(212,180,131,.18);color:var(--tls-gold)}
.badge-thin{background:rgba(204,24,24,.15);color:#cc1818}
.cc-sel{padding:4px 8px;font-size:12px;background:var(--surface2);border:1px solid var(--border);border-radius:5px;color:var(--text);max-width:230px}
.bulk-row{display:flex;gap:8px;margin:0 0 14px;flex-wrap:wrap;align-items:center}
#cc-status{font-size:12px;color:var(--tls-gold);min-height:16px}
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
      <a href="category-cleanup.php" class="active"><span class="ico">🧹</span> Kategori Temizle</a>
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
        <h2>Kategori Temizle</h2>
        <p>Çekirdek dışı kategorileri uygun ana kategoriye birleştir — kategori enflasyonunu bitir</p>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-box"><div class="stat-val" id="st-total">–</div><div class="stat-lbl">Toplam Kategori</div></div>
      <div class="stat-box"><div class="stat-val" id="st-core" style="color:#00ab6b">–</div><div class="stat-lbl">Çekirdek (kalacak)</div></div>
      <div class="stat-box"><div class="stat-val" id="st-extra" style="color:var(--tls-gold)">–</div><div class="stat-lbl">Fazla (birleşecek)</div></div>
    </div>

    <div class="bulk-row">
      <button class="btn btn-primary" id="btn-load">🔄 Kategorileri Tara</button>
      <button class="btn" id="btn-merge-sel" style="display:none">⇢ Seçilenleri Birleştir</button>
      <button class="btn" id="btn-del-empty" style="display:none">🗑 Boşları Sil</button>
      <span id="cc-status"></span>
    </div>

    <p style="font-size:12px;color:var(--muted);margin-bottom:14px;max-width:820px">
      <b>Çekirdek</b> = sitenin sabit ana kategori listesi (~100) — bunlar kalır.
      <b>Fazla</b> = sonradan birikmiş kategoriler; her biri için uygun bir hedef önerilir,
      yanlışsa açılır menüden değiştir. Birleştirince <b>yazılar hedefe taşınır</b> ve boşalan
      kategori silinir — hiçbir yazı kategorisiz kalmaz, içerik kaybolmaz.
      Az yazılı olanlar en üstte listelenir.
    </p>

    <div id="result"><div style="text-align:center;padding:40px;color:var(--muted)">Tara butonuna bas.</div></div>
  </main>
</div>

<script>
const $ = id => document.getElementById(id);
let coreList = [], rows = [];

function post(body){
  return fetch('api/category-cleanup.php', {method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/x-www-form-urlencoded'}, body}).then(r=>r.json());
}
function escH(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}

function render(){
  const opts = coreList.map(c=>'<option value="'+c.id+'">'+escH(c.name)+'</option>').join('');
  let html = '<table class="site-table"><thead><tr>'+
    '<th style="width:30px"></th><th>Kategori</th><th style="width:70px">Yazı</th>'+
    '<th style="width:80px">Durum</th><th style="width:250px">Birleştirilecek Hedef</th>'+
    '</tr></thead><tbody>';
  rows.forEach(r=>{
    const thin = !r.core && r.count <= 3;
    html += '<tr data-id="'+r.id+'">'+
      '<td>'+(!r.core && r.slug!=='general' && r.slug!=='uncategorized'
              ? '<input type="checkbox" class="cc-cb"'+(r.target_id?' checked':'')+'>' : '')+'</td>'+
      '<td><b>'+escH(r.name)+'</b><br><small style="color:var(--muted)">'+escH(r.slug)+'</small></td>'+
      '<td>'+r.count+'</td>'+
      '<td>'+(r.core?'<span class="badge badge-core">Çekirdek</span>'
              : thin?'<span class="badge badge-thin">İnce</span>'
              : '<span class="badge badge-extra">Fazla</span>')+'</td>'+
      '<td>'+(!r.core && r.slug!=='general' && r.slug!=='uncategorized'
              ? '<select class="cc-sel"><option value="0">— seç —</option>'+opts+'</select>' : '')+'</td>'+
      '</tr>';
  });
  html += '</tbody></table>';
  $('result').innerHTML = html;
  // hedef önerilerini seç
  rows.forEach(r=>{
    if(!r.target_id) return;
    const sel = document.querySelector('tr[data-id="'+r.id+'"] .cc-sel');
    if(sel) sel.value = r.target_id;
  });
  $('btn-merge-sel').style.display = '';
  $('btn-del-empty').style.display = '';
  updateBtn();
}
function updateBtn(){
  const n = document.querySelectorAll('.cc-cb:checked').length;
  $('btn-merge-sel').textContent = '⇢ Seçilen '+n+' kategoriyi birleştir';
}
document.addEventListener('change', e=>{ if(e.target.classList.contains('cc-cb')) updateBtn(); });

$('btn-load').addEventListener('click', ()=>{
  $('cc-status').textContent='Kategoriler okunuyor…';
  $('btn-load').disabled = true;
  post('action=list').then(d=>{
    $('btn-load').disabled = false;
    if(!d||!d.ok){ $('cc-status').textContent='Hata.'; return; }
    rows = d.rows; coreList = d.core_list;
    $('st-total').textContent = d.total; $('st-core').textContent = d.core; $('st-extra').textContent = d.extra;
    $('cc-status').textContent = d.extra+' fazla kategori bulundu.';
    render();
  }).catch(()=>{ $('btn-load').disabled=false; $('cc-status').textContent='Bağlantı hatası.'; });
});

$('btn-merge-sel').addEventListener('click', ()=>{
  const pairs = [];
  document.querySelectorAll('.cc-cb:checked').forEach(cb=>{
    const tr = cb.closest('tr'), sel = tr.querySelector('.cc-sel');
    const to = sel ? parseInt(sel.value,10) : 0;
    if(to) pairs.push({from_id: parseInt(tr.dataset.id,10), to_id: to});
  });
  if(!pairs.length){ $('cc-status').textContent='Hedefi seçili kategori yok.'; return; }
  if(!confirm(pairs.length+' kategori birleştirilecek. Yazılar hedef kategoriye taşınacak, boşalan kategoriler silinecek. Onaylıyor musun?')) return;

  $('btn-merge-sel').disabled = true;
  let i = 0, mergedTotal = 0, movedTotal = 0;
  const step = () => {
    if(i >= pairs.length){
      $('btn-merge-sel').disabled = false;
      $('cc-status').textContent = '✓ '+mergedTotal+' kategori birleşti, '+movedTotal+' yazı taşındı. Yeniden tarayın.';
      $('btn-load').click();
      return;
    }
    const slice = pairs.slice(i, i+5); i += 5;
    $('cc-status').textContent = 'Birleştiriliyor… ('+Math.min(i,pairs.length)+'/'+pairs.length+')';
    post('action=merge_bulk&pairs='+encodeURIComponent(JSON.stringify(slice)))
      .then(d=>{ if(d&&d.ok){ mergedTotal+=d.merged||0; movedTotal+=d.moved||0; } step(); })
      .catch(()=>{ $('btn-merge-sel').disabled=false; $('cc-status').textContent='Bağlantı hatası.'; });
  };
  step();
});

$('btn-del-empty').addEventListener('click', ()=>{
  if(!confirm('Hiç yazısı olmayan çekirdek-dışı kategoriler silinecek. Onaylıyor musun?')) return;
  $('cc-status').textContent='Boşlar siliniyor…';
  post('action=delete_empty').then(d=>{
    if(!d||!d.ok){ $('cc-status').textContent='Hata.'; return; }
    $('cc-status').textContent = '✓ '+d.deleted+' boş kategori silindi.';
    $('btn-load').click();
  });
});
</script>
</body>
</html>
