<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Yer Tutucular — Thetelos Panel</title>
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
#ph-status{font-size:12px;color:var(--tls-gold);min-height:16px}
.ph-link{font-size:12px;color:var(--tls-gold);text-decoration:none;white-space:nowrap}
.ph-link:hover{text-decoration:underline}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600}
.badge-pub{background:rgba(0,171,107,.15);color:#00ab6b}
.badge-draft{background:rgba(255,153,0,.15);color:#ff9900}
</style>
</head>
<body>
<div class="tls-shell">
  <aside class="tls-sidebar">
    <div class="tls-logo"><h1>Thetelos</h1><small>Content Panel</small></div>
    <nav class="tls-nav">
      <a href="panel.php"><span class="ico">✍</span> İçerik Üret</a>
      <a href="panel.php?mode=queue"><span class="ico">📋</span> Kuyruk</a>
      <a href="placeholders.php" class="active"><span class="ico">⏳</span> Yer Tutucular</a>
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
        <h2>Yer Tutucular</h2>
        <p>Sitede içeriği YAZILAMAMIŞ ("… is being prepared and will be published here soon") yazıları bulur — kaçının özeti eksik gör, listeyi indir, tekrar yazdır.</p>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-box"><div class="stat-val" id="st-count" style="color:var(--tls-gold)">–</div><div class="stat-lbl">Yer Tutucu (içerik yok)</div></div>
    </div>

    <div class="bulk-row">
      <button class="btn btn-primary" id="btn-scan">🔎 Siteyi Tara</button>
      <a class="btn" id="btn-csv" href="api/placeholders.php?action=csv" style="display:none">⬇ CSV indir</a>
      <select id="ph-words" style="display:none;max-width:220px">
        <option value="2500">Kısa (~2.500 kelime)</option>
        <option value="3500" selected>Standart (~3.500 kelime)</option>
        <option value="5000">Uzun (~5.000 kelime)</option>
      </select>
      <select id="ph-workers" style="display:none;max-width:150px" title="Aynı anda kaç worker (site yükü için düşük tut)">
        <option value="1">1 worker</option>
        <option value="2" selected>2 worker</option>
        <option value="3">3 worker</option>
      </select>
      <button class="btn btn-primary" id="btn-rewrite-all" style="display:none">⚡ Hepsini kaynak-temelli yeniden yaz</button>
      <span id="ph-status"></span>
    </div>

    <div style="font-size:12px;color:var(--muted);margin-bottom:12px;line-height:1.5">
      İndirilen CSV toplu yükleme formatındadır (<b>Kitap Adı | Yazar Adı</b>). Doğrudan
      <b>Toplu Batch</b>'e yükleyip "yeniden yaz" moduyla tekrar yazdırabilirsin — kaynak
      bulunursa gerçek özet, bulunmazsa yine yer-tutucu kalır (dürüstlük).
    </div>

    <table class="site-table">
      <thead><tr><th>#</th><th>Kitap</th><th>Yazar</th><th>Durum</th><th></th></tr></thead>
      <tbody id="ph-rows"><tr><td colspan="5" style="color:var(--muted);padding:16px">Taramak için "Siteyi Tara"ya bas.</td></tr></tbody>
    </table>
  </main>
</div>

<script>
const $ = s => document.querySelector(s);
let phItems = [];   // taranan yer-tutucu kitaplar (book/author)

$('#btn-scan').addEventListener('click', async () => {
  const btn = $('#btn-scan');
  btn.disabled = true; btn.textContent = '⏳ Taranıyor…';
  $('#ph-status').textContent = 'Site taranıyor, birkaç dakika sürebilir…';
  $('#btn-csv').style.display = 'none';
  try {
    const r = await fetch('api/placeholders.php?action=list', { credentials: 'same-origin' });
    const d = await r.json();
    if (!d || !d.ok) throw new Error(d && d.error || 'tarama başarısız');
    phItems = d.items || [];
    $('#st-count').textContent = d.count.toLocaleString('tr');
    const rows = $('#ph-rows');
    if (!d.items.length) {
      rows.innerHTML = '<tr><td colspan="5" style="color:#00ab6b;padding:16px">🎉 Yer-tutucu bulunamadı — tüm yazıların içeriği var.</td></tr>';
    } else {
      rows.innerHTML = d.items.map((it, i) =>
        `<tr><td>${i + 1}</td>`
        + `<td style="font-weight:600">${esc(it.book)}</td>`
        + `<td style="color:var(--muted)">${esc(it.author || '—')}</td>`
        + `<td><span class="badge ${it.status === 'draft' ? 'badge-draft' : 'badge-pub'}">${it.status || '?'}</span></td>`
        + `<td><a class="ph-link" href="${it.url}" target="_blank">aç →</a></td></tr>`
      ).join('');
      $('#btn-csv').style.display = '';
      $('#ph-words').style.display = '';
      $('#ph-workers').style.display = '';
      $('#btn-rewrite-all').style.display = '';
      $('#btn-rewrite-all').textContent = `⚡ Hepsini kaynak-temelli yeniden yaz (${d.count.toLocaleString('tr')})`;
    }
    $('#ph-status').textContent = `✓ ${d.count.toLocaleString('tr')} yer-tutucu bulundu.`;
  } catch (e) {
    $('#ph-status').textContent = '✗ ' + e.message;
  }
  btn.disabled = false; btn.textContent = '🔎 Siteyi Tara';
});

// TEK TIK: taranan tüm yer-tutucuları kaynak-temelli yeniden-yaz batch'i olarak kur + worker başlat.
$('#btn-rewrite-all').addEventListener('click', async () => {
  if (!phItems.length) return;
  const words   = $('#ph-words').value || '3500';
  const workers = parseInt($('#ph-workers').value || '2');
  if (!confirm(`${phItems.length} yer-tutucu kitap KAYNAK-TEMELLİ yeniden yazılacak (${workers} worker). Motor her kitabın kaynağını otomatik arar; bulamazsa yine yer-tutucu kalır. Devam?`)) return;
  const btn = $('#btn-rewrite-all');
  btn.disabled = true; btn.textContent = '⏳ Batch kuruluyor…';
  try {
    const books = phItems.map(it => ({ book_title: it.book, author_name: it.author }));
    const fd = new URLSearchParams();
    fd.set('books', JSON.stringify(books));
    fd.set('type', 'source');
    fd.set('source_words', words);
    fd.set('post_status', 'publish');
    fd.set('rewrite', '1');
    fd.set('workers', String(workers));
    fd.set('api_provider', 'deepseek');
    const r = await fetch('api/batch-create.php', { method: 'POST', credentials: 'same-origin', body: fd });
    const d = await r.json();
    if (!d || !d.ok) throw new Error(d && d.error || 'batch kurulamadı');
    // Worker'ları ateşle (arka planda çalışır)
    for (let i = 0; i < workers; i++) {
      fetch('api/batch-worker.php', { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ batch_id: d.batch_id }) }).catch(() => {});
    }
    $('#ph-status').innerHTML = `✓ Batch başladı (${(d.total||books.length).toLocaleString('tr')} kitap). İlerlemeyi <a href="panel.php?mode=queue" style="color:var(--tls-gold)">Kuyruk</a>'tan izle.`;
    btn.textContent = '✓ Başladı — Kuyruk\'ta';
    setTimeout(() => { window.location = 'panel.php?mode=queue'; }, 1500);
  } catch (e) {
    $('#ph-status').textContent = '✗ ' + e.message;
    btn.disabled = false; btn.textContent = '⚡ Hepsini kaynak-temelli yeniden yaz';
  }
});
function esc(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
</script>
</body>
</html>
