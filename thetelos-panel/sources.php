<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kaynak Arşivi — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.site-table{width:100%;border-collapse:collapse;font-size:13px}
.site-table th{text-align:left;padding:10px 12px;border-bottom:2px solid var(--border);color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.site-table td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:middle}
.site-table tr:hover td{background:rgba(255,255,255,.02)}
.stats-row{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.stat-box{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 16px}
.stat-val{font-size:22px;font-weight:700}
.stat-lbl{font-size:11px;color:var(--muted);margin-top:2px}
.bulk-row{display:flex;gap:8px;margin:0 0 14px;flex-wrap:wrap;align-items:center}
#src-status{font-size:12px;color:var(--tls-gold);min-height:16px}
.src-link{font-size:12px;color:var(--tls-gold);text-decoration:none}
.src-link:hover{text-decoration:underline}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:rgba(0,171,107,.15);color:#00ab6b}
</style>
</head>
<body>
<div class="tls-shell">
  <aside class="tls-sidebar">
    <div class="tls-logo"><h1>Thetelos</h1><small>Content Panel</small></div>
    <nav class="tls-nav">
      <a href="panel.php"><span class="ico">✍</span> İçerik Üret</a>
      <a href="panel.php?mode=queue"><span class="ico">📋</span> Kuyruk</a>
      <a href="placeholders.php"><span class="ico">⏳</span> Yer Tutucular</a>
      <a href="sources.php" class="active"><span class="ico">📚</span> Kaynak Arşivi</a>
      <a href="seo.php"><span class="ico">🔍</span> İçerik SEO</a>
      <a href="seo-site.php"><span class="ico">🌐</span> Site SEO</a>
      <a href="content-audit.php"><span class="ico">🩺</span> İçerik Denetimi</a>
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
        <h2>Kaynak Arşivi</h2>
        <p>Hangi kitap (thetelos yazısı) HANGİ kaynaktan / hangi linkten kaynak-temelli yazıldı. Her başarılı kaynak-temelli özet otomatik kaydedilir. Link postun kendi meta'sında da tutulur (ileride "Download source" butonu buradan otomatik eklenir).</p>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-box"><div class="stat-val" id="st-count" style="color:#00ab6b">–</div><div class="stat-lbl">Arşivlenen kaynak</div></div>
      <div class="stat-box"><div class="stat-val" id="st-suspect" style="color:#e0524d">–</div><div class="stat-lbl">Şüpheli eşleşme (yanlış kaynak?)</div></div>
    </div>

    <div class="bulk-row">
      <button class="btn btn-primary" id="btn-load">🔄 Arşivi Göster (otomatik toplanır)</button>
      <label style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px;cursor:pointer">
        <input type="checkbox" id="only-suspect"> yalnız şüpheliler</label>
      <a class="btn" id="btn-csv" href="api/source-index.php?action=csv" style="display:none">⬇ CSV</a>
      <a class="btn" id="btn-csv-suspect" href="api/source-index.php?action=csv&only=suspect" style="display:none;color:#e0524d">⬇ Şüpheli CSV</a>
      <a class="btn" id="btn-json" href="api/source-index.php?action=json" style="display:none">⬇ JSON</a>
      <span id="src-status"></span>
    </div>

    <p style="font-size:12px;color:var(--muted);margin:-6px 0 14px;line-height:1.5">
      <b style="color:#e0524d">Şüpheli</b> = kaynağı <b>Wikisource</b> olan tüm eski özetler. Wikisource artık özet kaynağı değil (yapısal olmayan arama yanlış eşleştiriyordu — bir kitabı ona yazılmış eleştiriden ayıramıyor). Bunların hepsini yeniden yazdırma kuyruğuna ver. (Gutenberg/Archive artık yazar-teyitli; onlar URL'den denetlenemez → “denetlenemez”.)
    </p>

    <table class="site-table">
      <thead><tr><th>#</th><th>Kitap</th><th>Yazar</th><th>Kaynak</th><th>Eşleşme</th><th>Kaynak Linki</th><th>Kelime</th><th>Thetelos</th></tr></thead>
      <tbody id="src-rows"><tr><td colspan="8" style="color:var(--muted);padding:16px">Yüklemek için "Listeyi Yükle"ye bas.</td></tr></tbody>
    </table>
  </main>
</div>

<script>
const $ = s => document.querySelector(s);
function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

async function load() {
  const btn = $('#btn-load'); btn.disabled = true; btn.textContent = '⏳ Yükleniyor…';
  $('#src-status').textContent = '';
  try {
    const r = await fetch('api/source-index.php?action=list', { credentials: 'same-origin' });
    const d = await r.json();
    if (!d || !d.ok) throw new Error(d && d.error || 'okunamadı');
    $('#st-count').textContent = d.count.toLocaleString('tr');
    $('#st-suspect').textContent = (d.suspect||0).toLocaleString('tr');
    const onlySus = $('#only-suspect').checked;
    let items = d.items;
    if (onlySus) items = items.filter(x => (x.check||'') === 'suspect');
    const rows = $('#src-rows');
    if (!items.length) {
      rows.innerHTML = '<tr><td colspan="8" style="color:var(--muted);padding:16px">' + (onlySus ? 'Şüpheli eşleşme yok.' : 'Henüz kayıt yok. Kaynak-temelli özet yazıldıkça burası dolar.') + '</td></tr>';
    } else {
      const ckBadge = { suspect:'<span class="badge" style="background:rgba(224,82,77,.16);color:#e0524d">⚠ ŞÜPHELİ</span>', ok:'<span class="badge">✓ eşleşiyor</span>', '':'<span style="font-size:11px;color:var(--muted)">denetlenemez</span>' };
      rows.innerHTML = items.map((it,i) => {
        const url = it.url || '';
        const local = url.indexOf('local:') === 0;
        const sus = (it.check||'') === 'suspect';
        const linkHtml = local
          ? '<span class="badge">yüklenen metin (server)</span>'
          : (url ? `<a class="src-link" href="${esc(url)}" target="_blank">${esc(url.length>52?url.slice(0,52)+'…':url)}</a>` : '—');
        const pl = it.post_url ? `<a class="src-link" href="${esc(it.post_url)}" target="_blank">#${it.pid} aç →</a>` : '';
        return `<tr${sus?' style="background:rgba(224,82,77,.06)"':''}><td>${i+1}</td><td style="font-weight:600">${esc(it.book)}</td>`
          + `<td style="color:var(--muted)">${esc(it.author||'—')}</td>`
          + `<td><span class="badge">${esc(it.source||'?')}</span></td>`
          + `<td>${ckBadge[it.check||'']||''}</td>`
          + `<td>${linkHtml}</td>`
          + `<td style="color:var(--muted)">${it.chars?Number(it.chars).toLocaleString('tr'):'—'}</td>`
          + `<td>${pl}</td></tr>`;
      }).join('');
      $('#btn-csv').style.display = ''; $('#btn-json').style.display = '';
      $('#btn-csv-suspect').style.display = (d.suspect>0) ? '' : 'none';
    }
    $('#src-status').textContent = `✓ ${d.count.toLocaleString('tr')} kaynak · ${(d.suspect||0).toLocaleString('tr')} şüpheli.`;
  } catch(e) { $('#src-status').textContent = '✗ ' + e.message; }
  btn.disabled = false; btn.textContent = '🔄 Listeyi Yükle';
}
$('#btn-load').addEventListener('click', load);
$('#only-suspect').addEventListener('change', load);
load();
</script>
</body>
</html>
