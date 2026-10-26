<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Yanlış Atıf — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.site-table{width:100%;border-collapse:collapse;font-size:13px}
.site-table th{text-align:left;padding:10px 12px;border-bottom:2px solid var(--border);color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.site-table td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:top}
.old{color:var(--muted);text-decoration:line-through}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600}
.b-ok{background:rgba(0,171,107,.15);color:#00ab6b}.b-man{background:rgba(255,153,0,.15);color:#ff9900}
#st{font-size:12px;color:var(--tls-gold);min-height:16px}
</style>
</head>
<body>
<div class="tls-shell">
  <?php require __DIR__ . "/_nav.php"; ?>
  <main class="tls-main">
    <div class="tls-header"><div>
      <h2>Yanlış Atıf</h2>
      <p>Başlığına yapay zekâ notu karışmış ("… (by X — misattribution)") kitapları bulur. Düzelt'e basınca: not başlıktan silinir, kitap gerçek yazarına taşınır, eski adres yeni adrese otomatik yönlenir. İçerik silinmez.</p>
    </div></div>
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
      <button class="btn btn-primary" id="btn-scan">🔎 Tara</button>
      <button class="btn btn-primary" id="btn-fix" style="display:none">✔ Hazır olanların hepsini düzelt</button>
      <span id="st"></span>
    </div>
    <table class="site-table">
      <thead><tr><th>#</th><th>Şu anki başlık → Yeni başlık</th><th>Yazar</th><th>Durum</th><th></th></tr></thead>
      <tbody id="rows"><tr><td colspan="5" style="color:var(--muted)">Başlamak için "Tara"ya bas.</td></tr></tbody>
    </table>
  </main>
</div>
<script>
const $ = s => document.querySelector(s);
function esc(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
let items = [];
async function scan(){
  $('#btn-scan').disabled = true; $('#st').textContent = 'Taranıyor…';
  try {
    const d = await (await fetch('api/misattribution.php?action=list', {credentials:'same-origin'})).json();
    if (!d.ok) throw new Error('tarama başarısız');
    items = d.items;
    $('#rows').innerHTML = items.length ? items.map((it,i) => `<tr><td>${i+1}</td>
      <td><div class="old">${esc(it.title)}</div>${it.state==='ready' ? `<div style="font-weight:600">${esc(it.new_title)}</div>` : ''}</td>
      <td>${esc(it.old_author)} → <b>${esc(it.real_author || '?')}</b>${it.term_id===0 ? ' <span style="color:var(--muted)">(yeni yazar)</span>' : ''}</td>
      <td>${it.state==='ready' ? '<span class="badge b-ok">hazır</span>' : `<span class="badge b-man">elle bak</span><div style="font-size:11px;color:var(--muted)">${esc(it.why)}</div>`}</td>
      <td><a href="${esc(it.link)}" target="_blank" style="color:var(--tls-gold)">aç →</a></td></tr>`).join('')
      : '<tr><td colspan="5" style="color:#00ab6b">🎉 Yanlış atıflı başlık bulunamadı.</td></tr>';
    $('#btn-fix').style.display = d.ready ? '' : 'none';
    $('#btn-fix').textContent = `✔ Hazır olanların hepsini düzelt (${d.ready})`;
    $('#st').textContent = `${d.count} bulundu, ${d.ready} tanesi otomatik düzeltilebilir.`;
  } catch(e) { $('#st').textContent = '✗ ' + e.message; }
  $('#btn-scan').disabled = false;
}
$('#btn-scan').addEventListener('click', scan);
$('#btn-fix').addEventListener('click', async () => {
  const ids = items.filter(i => i.state==='ready').map(i => i.id);
  if (!ids.length || !confirm(ids.length + ' kitap düzeltilecek. Devam?')) return;
  $('#btn-fix').disabled = true; $('#st').textContent = 'Düzeltiliyor…';
  try {
    const d = await (await fetch('api/misattribution.php', {method:'POST', credentials:'same-origin',
      body: new URLSearchParams({action:'apply', ids: ids.join(',')})})).json();
    $('#st').textContent = `✓ ${d.done.length} düzeltildi` + (d.fail.length ? `, ${d.fail.length} başarısız` : '') + '.';
    await scan();
  } catch(e) { $('#st').textContent = '✗ ' + e.message; }
  $('#btn-fix').disabled = false;
});
</script>
</body>
</html>
