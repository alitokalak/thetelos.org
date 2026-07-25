<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kategori Düzelt — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.site-table{width:100%;border-collapse:collapse;font-size:13px}
.site-table th{text-align:left;padding:10px 12px;border-bottom:2px solid var(--border);color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.site-table td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:middle}
.stats-row{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.stat-box{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 16px}
.stat-val{font-size:20px;font-weight:700}
.stat-lbl{font-size:11px;color:var(--muted);margin-top:2px}
.cat-chip{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:rgba(0,171,107,.15);color:#00ab6b;margin:1px 3px 1px 0}
.badge-err{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:rgba(204,24,24,.15);color:#cc1818}
.bulk-row{display:flex;gap:8px;margin:0 0 14px;flex-wrap:wrap;align-items:center}
label.auto{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px;cursor:pointer}
#rc-status{font-size:12px;color:var(--gold);min-height:16px}
.rc-link{font-size:12px;color:var(--gold);text-decoration:none}
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
      <a href="recategorize.php" class="active"><span class="ico">🗂️</span> Kategori Düzelt</a>
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
        <h2>Kategori Düzelt</h2>
        <p>"General" kovasında kalan yazıları içeriğine göre doğru kategorilere taşı</p>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-box"><div class="stat-val" id="st-general" style="color:var(--gold)">–</div><div class="stat-lbl">General'de</div></div>
      <div class="stat-box"><div class="stat-val" id="st-fixed" style="color:#00ab6b">–</div><div class="stat-lbl">Düzeltildi</div></div>
      <div class="stat-box"><div class="stat-val" id="st-skipped" style="color:var(--muted)">–</div><div class="stat-lbl">Atlandı</div></div>
    </div>

    <div class="bulk-row">
      <button class="btn btn-primary" id="btn-run">▶ Düzeltmeye Başla</button>
      <label class="auto"><input type="checkbox" id="auto-loop" checked> Otomatik devam et</label>
      <span id="rc-status"></span>
    </div>

    <p style="font-size:12px;color:var(--muted);margin-bottom:14px;max-width:760px">
      Her yazının başlığı, yazarı ve içeriği okunur; <b>yalnızca sitede zaten var olan</b>
      kategoriler arasından en uygun 2-5 tanesi seçilip atanır — <b>yeni kategori açılmaz</b>,
      "General" kaldırılır. Uygun kategori bulunamayan yazı atlanır ve bir daha sorulmaz.
      Sekmeyi açık tut.
    </p>

    <div id="result"><div style="text-align:center;padding:40px;color:var(--muted)">Başlat butonuna bas.</div></div>
  </main>
</div>

<script>
const $ = id => document.getElementById(id);
let tbody = null, running = false;

function post(body){
  return fetch('api/recategorize.php', {method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/x-www-form-urlencoded'}, body}).then(r=>r.json());
}
function setStats(s){
  if(!s) return;
  $('st-general').textContent = (s.general||0).toLocaleString();
  $('st-fixed').textContent   = (s.fixed||0).toLocaleString();
  $('st-skipped').textContent = (s.skipped||0).toLocaleString();
}
function escH(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
function ensureTable(){
  if(tbody) return;
  $('result').innerHTML = '<table class="site-table"><thead><tr>'+
    '<th>Kitap</th><th>Atanan Kategoriler</th><th style="width:100px">Sayfa</th>'+
    '</tr></thead><tbody id="rc-tbody"></tbody></table>';
  tbody = $('rc-tbody');
}
function addRow(r){
  ensureTable();
  const tr = document.createElement('tr');
  tr.innerHTML = '<td>'+escH(r.title)+'</td>'+
    '<td>'+(r.ok ? r.cats.map(c=>'<span class="cat-chip">'+escH(c)+'</span>').join('')
                 : '<span class="badge-err">Bulunamadı</span>')+'</td>'+
    '<td>'+(r.url?'<a class="rc-link" href="'+escH(r.url)+'" target="_blank" rel="noopener">Aç ↗</a>':'')+'</td>';
  tbody.prepend(tr);
}
function runOnce(){
  if(running) return;
  running = true;
  $('btn-run').disabled = true; $('btn-run').textContent = 'Çalışıyor…';
  $('rc-status').textContent = 'Kategoriler belirleniyor (5 yazı)…';
  post('action=run&chunk=5').then(d=>{
    running = false;
    $('btn-run').disabled = false; $('btn-run').textContent = '▶ Düzeltmeye Başla';
    if(!d || !d.ok){ $('rc-status').textContent = 'Hata oluştu.'; return; }
    setStats(d.stats);
    (d.rows||[]).forEach(addRow);
    if(d.done || !(d.rows||[]).length){
      $('rc-status').textContent = '✓ General kovasında düzeltilecek yazı kalmadı.';
      $('auto-loop').checked = false; return;
    }
    $('rc-status').textContent = d.rows.length+' yazı işlendi.';
    if($('auto-loop').checked) setTimeout(runOnce, 400);
  }).catch(()=>{ running=false; $('btn-run').disabled=false; $('btn-run').textContent='▶ Düzeltmeye Başla'; $('rc-status').textContent='Bağlantı hatası.'; });
}
$('btn-run').addEventListener('click', runOnce);
post('action=stats').then(d=>{ if(d&&d.ok) setStats(d.stats); });
</script>
</body>
</html>
