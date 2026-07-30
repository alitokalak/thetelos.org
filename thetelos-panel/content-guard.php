<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>İçerik Koruma — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.site-table{width:100%;border-collapse:collapse;font-size:13px}
.site-table th{text-align:left;padding:9px 10px;border-bottom:2px solid var(--border);color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.site-table td{padding:8px 10px;border-bottom:1px solid var(--border);vertical-align:middle}
.stats-row{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.stat-box{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 16px}
.stat-val{font-size:20px;font-weight:700}
.stat-lbl{font-size:11px;color:var(--muted);margin-top:2px}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600}
.badge-hit{background:rgba(255,140,0,.2);color:#ff8c00}
.badge-pub{background:rgba(204,24,24,.15);color:#cc1818}
.badge-draft{background:rgba(255,255,255,.08);color:var(--muted)}
.bulk-row{display:flex;gap:8px;margin:0 0 14px;flex-wrap:wrap;align-items:center}
#cg-status{font-size:12px;color:var(--tls-gold);min-height:16px}
.cg-link{font-size:12px;color:var(--tls-gold);text-decoration:none}
h3.sec{font-size:14px;margin:26px 0 10px;color:var(--text)}
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
      <a href="content-audit.php"><span class="ico">🩺</span> İçerik Denetimi</a>
      <a href="content-guard.php" class="active"><span class="ico">🛡️</span> İçerik Koruma</a>
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
        <h2>İçerik Koruma</h2>
        <p>Yetişkin/müstehcen içerik sitede yayınlanmaz — mevcutları bul ve kaldır</p>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-box"><div class="stat-val" id="st-posts" style="color:#cc1818">–</div><div class="stat-lbl">Eşleşen Yazı</div></div>
      <div class="stat-box"><div class="stat-val" id="st-cats" style="color:#ff8c00">–</div><div class="stat-lbl">Eşleşen Kategori</div></div>
    </div>

    <div class="bulk-row">
      <button class="btn btn-primary" id="btn-scan">🔎 Tara</button>
      <button class="btn" id="btn-draft" style="display:none">⬇ Seçilenleri Yayından Kaldır</button>
      <button class="btn" id="btn-trash" style="display:none;color:var(--red)">🗑 Seçilenleri Çöpe At</button>
      <span id="cg-status"></span>
    </div>

    <p style="font-size:12px;color:var(--muted);margin-bottom:14px;max-width:820px">
      Üretim tarafında bu içerikler <b>zaten engellendi</b> — bundan sonra hiç üretilmez.
      Bu ekran <b>geçmişte yayınlanmışları</b> bulur. Önerilen: <b>Yayından Kaldır</b> (taslağa
      alır, silmez) → gözden geçirip gerçekten istemediklerini <b>Çöpe At</b>.
      Yanlış eşleşme olabilir (ör. akademik bir eser), o yüzden önce taslak.
    </p>

    <div id="result"><div style="text-align:center;padding:40px;color:var(--muted)">Tara butonuna bas.</div></div>
  </main>
</div>

<script>
const $ = id => document.getElementById(id);
function post(body){
  return fetch('api/content-guard.php', {method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/x-www-form-urlencoded'}, body}).then(r=>r.json());
}
function escH(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}

function render(d){
  let html = '';
  if(d.cats && d.cats.length){
    html += '<h3 class="sec">⚠ Eşleşen Kategoriler</h3><table class="site-table"><thead><tr>'+
      '<th>Kategori</th><th style="width:70px">Yazı</th><th style="width:130px">Eşleşme</th><th style="width:190px">İşlem</th>'+
      '</tr></thead><tbody>';
    d.cats.forEach(c=>{
      html += '<tr><td><b>'+escH(c.name)+'</b><br><small style="color:var(--muted)">'+escH(c.slug)+'</small></td>'+
        '<td>'+c.count+'</td>'+
        '<td><span class="badge badge-hit">'+escH(c.match)+'</span></td>'+
        '<td><button class="btn btn-ghost btn-sm" style="color:var(--red)" onclick="delCat('+c.id+',this)">Kategoriyi Sil</button></td></tr>';
    });
    html += '</tbody></table>';
  }
  html += '<h3 class="sec">Eşleşen Yazılar</h3>';
  if(!d.posts || !d.posts.length){
    html += '<div style="padding:24px;color:var(--muted)">✓ Eşleşen yazı yok — site temiz.</div>';
  } else {
    html += '<table class="site-table"><thead><tr>'+
      '<th style="width:30px"><input type="checkbox" id="cg-all"></th><th>Kitap</th>'+
      '<th style="width:110px">Durum</th><th style="width:130px">Eşleşme</th><th style="width:80px">Sayfa</th>'+
      '</tr></thead><tbody>';
    d.posts.forEach(p=>{
      html += '<tr data-id="'+p.id+'">'+
        '<td><input type="checkbox" class="cg-cb" checked></td>'+
        '<td><b>'+escH(p.title)+'</b>'+(p.author?'<br><small style="color:var(--muted)">'+escH(p.author)+'</small>':'')+'</td>'+
        '<td>'+(p.status==='publish'?'<span class="badge badge-pub">Yayında</span>':'<span class="badge badge-draft">'+escH(p.status)+'</span>')+'</td>'+
        '<td><span class="badge badge-hit">'+escH(p.match)+'</span></td>'+
        '<td><a class="cg-link" href="'+escH(p.url)+'" target="_blank" rel="noopener">Aç ↗</a></td></tr>';
    });
    html += '</tbody></table>';
  }
  $('result').innerHTML = html;
  const all = $('cg-all');
  if(all) all.addEventListener('change', ()=>{
    document.querySelectorAll('.cg-cb').forEach(cb=>cb.checked = all.checked);
  });
  const has = d.posts && d.posts.length;
  $('btn-draft').style.display = has ? '' : 'none';
  $('btn-trash').style.display = has ? '' : 'none';
}

function selected(){
  return [...document.querySelectorAll('.cg-cb:checked')].map(cb=>cb.closest('tr').dataset.id);
}
function act(kind){
  const ids = selected();
  if(!ids.length){ $('cg-status').textContent='Seçim yok.'; return; }
  const label = kind==='trash' ? 'çöpe atılacak' : 'yayından kaldırılacak (taslak)';
  if(!confirm(ids.length+' yazı '+label+'. Onaylıyor musun?')) return;
  $('cg-status').textContent='İşleniyor…';
  post('action='+kind+'&ids='+encodeURIComponent(ids.join(',')))
    .then(d=>{ $('cg-status').textContent = d&&d.ok ? ('✓ '+d.done+' yazı işlendi.') : 'Hata.'; $('btn-scan').click(); });
}
function delCat(id, btn){
  if(!confirm('Bu kategori silinecek ve içindeki yazılar yayından kaldırılacak (taslak). Onaylıyor musun?')) return;
  btn.disabled = true;
  post('action=delcat&term_id='+id)
    .then(d=>{ $('cg-status').textContent = d&&d.ok ? ('✓ "'+d.name+'" silindi, '+d.drafted+' yazı taslağa alındı.') : 'Hata.'; $('btn-scan').click(); });
}

$('btn-scan').addEventListener('click', ()=>{
  $('cg-status').textContent='Taranıyor…'; $('btn-scan').disabled = true;
  post('action=scan').then(d=>{
    $('btn-scan').disabled = false;
    if(!d||!d.ok){ $('cg-status').textContent='Hata.'; return; }
    $('st-posts').textContent = (d.posts||[]).length;
    $('st-cats').textContent  = (d.cats||[]).length;
    $('cg-status').textContent = ((d.posts||[]).length||(d.cats||[]).length) ? 'Gözden geçir.' : '✓ Temiz.';
    render(d);
  }).catch(()=>{ $('btn-scan').disabled=false; $('cg-status').textContent='Bağlantı hatası.'; });
});
$('btn-draft').addEventListener('click', ()=>act('draft'));
$('btn-trash').addEventListener('click', ()=>act('trash'));
</script>
</body>
</html>
