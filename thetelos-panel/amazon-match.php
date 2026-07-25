<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Amazon Eşleştirme — Thetelos Panel</title>
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
.badge-ok{background:rgba(0,171,107,.15);color:#00ab6b}
.badge-err{background:rgba(204,24,24,.15);color:#cc1818}
.am-cover{width:34px;height:50px;object-fit:cover;border-radius:3px;background:var(--surface2)}
.am-book{font-weight:600}
.am-book small{display:block;color:var(--muted);font-weight:400;font-size:11px}
.am-ol{font-size:12px;color:var(--muted);max-width:260px}
.am-asin input{width:120px;padding:5px 8px;font-size:12px;font-family:monospace;background:var(--surface2);border:1px solid var(--border);border-radius:5px;color:var(--text)}
.am-link{font-size:12px;color:var(--tls-gold);text-decoration:none;white-space:nowrap}
.am-link:hover{text-decoration:underline}
#am-status{font-size:12px;color:var(--tls-gold);min-height:16px}
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
      <a href="cover-backfill.php"><span class="ico">🖼</span> Kapak Bul</a>
      <a href="amazon-match.php" class="active"><span class="ico">🛒</span> Amazon</a>
      <a href="settings.php"><span class="ico">⚙</span> Ayarlar</a>
      <a href="<?= rtrim(WP_URL,'/') ?>/wp-admin/" target="_blank"><span class="ico">🔗</span> WP Admin</a>
      <a href="<?= rtrim(WP_URL,'/') ?>/" target="_blank"><span class="ico">↗</span> Siteyi Gör</a>
    </nav>
    <div class="tls-sidebar-footer"><a href="index.php?logout=1">Çıkış Yap</a></div>
  </aside>

  <main class="tls-main">
    <div class="tls-header">
      <div>
        <h2>Amazon Eşleştirme</h2>
        <p>Kitapları Amazon ürün sayfasına (ASIN) bağla — "Buy on Amazon" butonu aramaya değil doğrudan ürüne gitsin</p>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-box"><div class="stat-val" id="st-total">–</div><div class="stat-lbl">Toplam Kitap</div></div>
      <div class="stat-box"><div class="stat-val" id="st-matched" style="color:#00ab6b">–</div><div class="stat-lbl">Eşleşti</div></div>
      <div class="stat-box"><div class="stat-val" id="st-skipped" style="color:var(--muted)">–</div><div class="stat-lbl">Atlandı</div></div>
      <div class="stat-box"><div class="stat-val" id="st-remaining" style="color:var(--tls-gold)">–</div><div class="stat-lbl">Kalan</div></div>
    </div>

    <div class="bulk-row">
      <button class="btn btn-primary" id="btn-scan">🔎 Tara ve Öner</button>
      <label class="auto"><input type="checkbox" id="auto-loop"> Otomatik devam et</label>
      <button class="btn" id="btn-save" style="display:none">💾 Seçilenleri Kaydet</button>
      <button class="btn" id="btn-skip" style="display:none">🚫 Bulunamayanları bir daha sorma</button>
      <span id="am-status"></span>
    </div>

    <p style="font-size:12px;color:var(--muted);margin-bottom:14px;max-width:720px">
      Her satırda önerilen Amazon eşleşmesi var. <b>"Amazon'da Aç"</b> ile doğrula,
      yanlışsa kutudaki ASIN'i elle düzelt ya da işaretini kaldır. <b>Kaydet</b>'e
      basınca onaylananların butonu doğrudan ürün sayfasına gider.
      Kaydedilmeyenler sonraki taramalarda tekrar gelir.
    </p>

    <div id="result"><div style="text-align:center;padding:40px;color:var(--muted)">Tara butonuna bas.</div></div>
  </main>
</div>

<script>
const API = p => 'api/' + p;
const $  = id => document.getElementById(id);

let shown = [];          // bu oturumda gösterilen post_id'ler
let tbody = null;
let scanning = false;

function post(body){
  return fetch(API('amazon-match.php'), {
    method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body
  }).then(r => r.json());
}

function setStats(s){
  if(!s) return;
  $('st-total').textContent     = (s.total||0).toLocaleString();
  $('st-matched').textContent   = (s.matched||0).toLocaleString();
  $('st-skipped').textContent   = (s.skipped||0).toLocaleString();
  $('st-remaining').textContent = (s.remaining||0).toLocaleString();
}

function escH(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}

function ensureTable(){
  if(tbody) return;
  $('result').innerHTML =
    '<table class="site-table"><thead><tr>'+
    '<th style="width:34px"></th><th style="width:40px"></th><th>Kitap</th>'+
    '<th>OpenLibrary Eşleşmesi</th><th>ASIN</th><th style="width:120px">Önizle</th>'+
    '</tr></thead><tbody id="am-tbody"></tbody></table>';
  tbody = $('am-tbody');
}

function addRow(r){
  ensureTable();
  const found = !!r.asin;
  const tr = document.createElement('tr');
  tr.dataset.postId = r.post_id;
  tr.dataset.found  = found ? '1' : '0';
  tr.innerHTML =
    '<td>'+(found?'<input type="checkbox" class="am-cb" checked>':'')+'</td>'+
    '<td>'+(r.cover?'<img class="am-cover" src="'+escH(r.cover)+'" loading="lazy">':'<div class="am-cover"></div>')+'</td>'+
    '<td class="am-book">'+escH(r.book)+'<small>'+escH(r.author)+'</small></td>'+
    '<td class="am-ol">'+(found
        ? escH(r.ol_title)+(r.ol_year?' <span style="opacity:.7">('+escH(r.ol_year)+')</span>':'')
        : '<span class="badge badge-err">Bulunamadı</span>')+'</td>'+
    '<td class="am-asin"><input type="text" value="'+escH(r.asin)+'" placeholder="elle gir…" maxlength="10"></td>'+
    '<td>'+(found?'<a class="am-link" href="https://www.amazon.com/dp/'+escH(r.asin)+'" target="_blank" rel="noopener">Amazon\'da Aç ↗</a>':'')+'</td>';
  // Elle ASIN girilirse önizleme linkini ve checkbox'ı canlandır
  tr.querySelector('.am-asin input').addEventListener('input', function(){
    const v = this.value.trim().toUpperCase();
    const cellPrev = tr.cells[5], cellCb = tr.cells[0];
    if(/^[0-9A-Z]{10}$/.test(v)){
      cellPrev.innerHTML = '<a class="am-link" href="https://www.amazon.com/dp/'+v+'" target="_blank" rel="noopener">Amazon\'da Aç ↗</a>';
      if(!cellCb.querySelector('.am-cb')) cellCb.innerHTML = '<input type="checkbox" class="am-cb" checked>';
      refreshButtons();
    }
  });
  tbody.appendChild(tr);
}

function refreshButtons(){
  const sel   = tbody ? tbody.querySelectorAll('.am-cb:checked').length : 0;
  const notF  = tbody ? tbody.querySelectorAll('tr[data-found="0"]').length : 0;
  $('btn-save').style.display = sel ? '' : 'none';
  $('btn-save').textContent   = '💾 Seçilenleri Kaydet ('+sel+')';
  $('btn-skip').style.display = notF ? '' : 'none';
  $('btn-skip').textContent   = '🚫 Bulunamayan '+notF+' kitabı bir daha sorma';
}

function scanOnce(){
  if(scanning) return;
  scanning = true;
  $('btn-scan').disabled = true; $('btn-scan').textContent = 'Aranıyor…';
  $('am-status').textContent = 'OpenLibrary üzerinden eşleşme aranıyor (6 kitap)…';
  post('action=scan&exclude=' + encodeURIComponent(shown.join(',')))
    .then(d => {
      scanning = false;
      $('btn-scan').disabled = false; $('btn-scan').textContent = '🔎 Tara ve Öner';
      if(!d || !d.ok){ $('am-status').textContent = 'Hata oluştu.'; return; }
      setStats(d.stats);
      d.rows.forEach(r => { shown.push(r.post_id); addRow(r); });
      refreshButtons();
      if(d.done){ $('am-status').textContent = '✓ Eşleştirilecek kitap kalmadı.'; $('auto-loop').checked = false; return; }
      $('am-status').textContent = d.rows.length + ' öneri eklendi.';
      if($('auto-loop').checked) setTimeout(scanOnce, 400);
    })
    .catch(() => { scanning = false; $('btn-scan').disabled = false; $('btn-scan').textContent = '🔎 Tara ve Öner'; $('am-status').textContent = 'Bağlantı hatası.'; });
}

$('btn-scan').addEventListener('click', scanOnce);
$('auto-loop').addEventListener('change', function(){ if(this.checked && !scanning) scanOnce(); });

document.addEventListener('change', e => { if(e.target.classList.contains('am-cb')) refreshButtons(); });

$('btn-save').addEventListener('click', () => {
  const pairs = [];
  tbody.querySelectorAll('tr').forEach(tr => {
    const cb = tr.querySelector('.am-cb');
    if(!cb || !cb.checked) return;
    const asin = tr.querySelector('.am-asin input').value.trim().toUpperCase();
    if(/^[0-9A-Z]{10}$/.test(asin)) pairs.push({ post_id: +tr.dataset.postId, asin });
  });
  if(!pairs.length) return;
  $('btn-save').disabled = true;
  post('action=save&pairs=' + encodeURIComponent(JSON.stringify(pairs)))
    .then(d => {
      $('btn-save').disabled = false;
      if(!d || !d.ok){ $('am-status').textContent = 'Kaydetme hatası.'; return; }
      setStats(d.stats);
      // kaydedilen satırları listeden düşür
      pairs.forEach(p => { const tr = tbody.querySelector('tr[data-post-id="'+p.post_id+'"]'); if(tr) tr.remove(); });
      $('am-status').textContent = '✓ ' + d.saved + ' kitap Amazon ürününe bağlandı.';
      refreshButtons();
    })
    .catch(() => { $('btn-save').disabled = false; $('am-status').textContent = 'Bağlantı hatası.'; });
});

$('btn-skip').addEventListener('click', () => {
  const ids = [];
  tbody.querySelectorAll('tr[data-found="0"]').forEach(tr => {
    const asin = tr.querySelector('.am-asin input').value.trim();
    if(asin === '') ids.push(tr.dataset.postId);   // elle ASIN girilmişse atlama
  });
  if(!ids.length) return;
  if(!confirm(ids.length + ' kitap "bulunamadı" olarak işaretlenecek ve bir daha sorulmayacak. (Butonları normal Amazon aramasına gitmeye devam eder.) Onaylıyor musun?')) return;
  post('action=skip&ids=' + encodeURIComponent(ids.join(',')))
    .then(d => {
      if(!d || !d.ok){ $('am-status').textContent = 'Hata.'; return; }
      setStats(d.stats);
      ids.forEach(id => { const tr = tbody.querySelector('tr[data-post-id="'+id+'"]'); if(tr) tr.remove(); });
      $('am-status').textContent = '✓ ' + d.skipped + ' kitap atlandı.';
      refreshButtons();
    });
});

// tr[data-post-id] seçicisi için dataset attribute adı düzeltmesi
// (dataset.postId → attribute data-post-id olarak yazılır; addRow'da ayarlandı)
</script>
</body>
</html>
