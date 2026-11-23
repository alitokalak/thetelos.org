<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mail Analitiği — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.cards{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px}
.cardx{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px 18px;min-width:150px}
.cardx b{display:block;font-size:26px}
.cardx span{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
.site-table{width:100%;border-collapse:collapse;font-size:13px}
.site-table th{text-align:left;padding:9px 10px;border-bottom:2px solid var(--border);color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.site-table td{padding:9px 10px;border-bottom:1px solid var(--border);vertical-align:top}
.muted{color:var(--muted);font-size:12px}
.sus{color:#c90}
h3.sec{margin:26px 0 10px;font-size:15px}
.chart{display:flex;align-items:flex-end;gap:3px;height:170px;padding:10px 0;border-bottom:1px solid var(--border)}
.col{flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:1px;position:relative;height:100%}
.bar-v{width:100%;background:rgba(120,150,200,.35);border-radius:2px 2px 0 0}
.bar-c{width:100%;background:#3fb168;border-radius:2px 2px 0 0}
.sent-dot{position:absolute;top:0;font-size:10px}
.legend{font-size:12px;color:var(--muted);margin-top:6px}
.legend i{display:inline-block;width:10px;height:10px;border-radius:2px;margin:0 4px 0 12px;vertical-align:middle}
.tabs button{margin-right:6px}
</style>
</head>
<body>
<div class="tls-shell">
  <?php require __DIR__ . "/_nav.php"; ?>
  <main class="tls-main">
    <div class="tls-header"><div>
      <h2>Mail Analitiği</h2>
      <p>Gönderilen okuma önerisi maillerinin dönüşleri: hangi sayı kime gitti, kim hangi tarihte hangi kitaba tıkladı, gönderim sonrası site trafiği. <a href="member-digest.php">← Üye Mailleri</a></p>
    </div></div>

    <div class="cards" id="cards"><div class="muted">Yükleniyor…</div></div>

    <h3 class="sec">📈 Son 45 gün — mailden gelen tıklamalar ve site trafiği</h3>
    <div class="card">
      <div class="chart" id="chart"></div>
      <div class="legend"><i style="background:#3fb168"></i>Mailden gelen tıklama <i style="background:rgba(120,150,200,.6)"></i>Sitenin toplam okunması (ölçek ayrı) <span style="margin-left:12px">✉ = o gün mail gönderildi</span></div>
      <div class="muted" id="chart-note" style="margin-top:6px"></div>
    </div>

    <h3 class="sec">✉ Sayı (gönderim) bazında</h3>
    <div class="card"><table class="site-table">
      <thead><tr><th>Sayı</th><th>Tarih</th><th>Gönderilen</th><th>Siteye gelen</th><th>Dönüş</th><th>Toplam tıklama</th><th>En çok tıklanan</th></tr></thead>
      <tbody id="t-issues"></tbody></table></div>

    <h3 class="sec">👆 Tüm tıklamalar (yeniden eskiye)</h3>
    <div class="card"><table class="site-table">
      <thead><tr><th>Tarih / saat</th><th>Sayı</th><th>Üye</th><th>Tıkladığı</th></tr></thead>
      <tbody id="t-clicks"></tbody></table></div>

    <h3 class="sec">👤 Üye bazında</h3>
    <div class="card"><table class="site-table">
      <thead><tr><th>Üye</th><th>Aldığı mail</th><th>Tıklama</th><th>Son tıklama</th></tr></thead>
      <tbody id="t-members"></tbody></table></div>
  </main>
</div>
<script>
const $ = s => document.querySelector(s);
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
(async () => {
  const r = await fetch('api/member-digest-stats.php').then(x => x.json()).catch(e => ({ok:false,error:e.message}));
  if (!r.ok) { $('#cards').innerHTML = '<div class="muted">Hata: '+esc(r.error||'?')+'</div>'; return; }
  const T = r.totals, rate = T.mails ? Math.round(T.clickers*100/T.mails) : 0;
  $('#cards').innerHTML = [
    [T.issues,'Gönderilen sayı'],[T.mails,'Giden mail'],[T.clickers,'Siteye gelen üye'],
    ['%'+rate,'Genel dönüş'],[T.clicks,'Gerçek tıklama'],[T.suspect,'Elenen (otomatik tarayıcı)']
  ].map(([v,l]) => `<div class="cardx"><b>${v}</b><span>${l}</span></div>`).join('');

  // Grafik
  const D = r.days, maxC = Math.max(1,...D.map(d=>d.clicks)), maxV = Math.max(1,...D.map(d=>d.views));
  $('#chart').innerHTML = D.map(d => `<div class="col" title="${d.day}\nMailden tıklama: ${d.clicks}\nSite okunması: ${d.views}${d.sent?'\nGönderilen mail: '+d.sent:''}">
      ${d.sent?'<span class="sent-dot">✉</span>':''}
      <div class="bar-v" style="height:${Math.round(d.views/maxV*45)}%"></div>
      <div class="bar-c" style="height:${Math.round(d.clicks/maxC*50)}%"></div></div>`).join('');
  $('#chart-note').textContent = T.views_tracked_since
    ? `Günlük site okunması ${T.views_tracked_since} tarihinden beri kaydediliyor. Çubukların üstüne gelince sayılar görünür.`
    : 'Günlük site okunması bugünden itibaren kaydedilmeye başlandı; grafik günler geçtikçe dolacak.';

  $('#t-issues').innerHTML = r.issues.map(i => `<tr><td><b>Sayı ${i.issue}</b></td><td class="muted">${esc(i.date)}</td><td>${i.sent}</td>
     <td><b>${i.unique}</b></td><td>%${i.rate}</td><td>${i.clicks}</td>
     <td class="muted">${Object.entries(i.books).map(([t,n])=>esc(t)+' ('+n+')').join('<br>')||'—'}</td></tr>`).join('')
     || '<tr><td colspan="7" class="muted">Henüz gönderim yok.</td></tr>';

  $('#t-clicks').innerHTML = r.clicks.map(c => `<tr${c.suspect?' class="sus"':''}><td>${esc(c.time)}</td><td>Sayı ${c.issue}</td>
     <td><b>${esc(c.name)}</b><br><span class="muted">${esc(c.email)}</span></td>
     <td><a href="${esc(c.url)}" target="_blank" style="color:inherit">${esc(c.book)}</a>${c.suspect?'<br><span class="sus" style="font-size:11px">⚠ otomatik tarayıcı — sayılmadı</span>':''}</td></tr>`).join('')
     || '<tr><td colspan="4" class="muted">Henüz tıklama yok. (Takipli linkler 23.11.2026 sonrası gönderimlerde var.)</td></tr>';

  $('#t-members').innerHTML = r.members.map(m => `<tr><td><b>${esc(m.name)}</b><br><span class="muted">${esc(m.email)}</span></td>
     <td>${m.sent}</td><td>${m.clicks?'<b>'+m.clicks+'</b>':'—'}</td><td class="muted">${esc(m.last_click||'—')}</td></tr>`).join('')
     || '<tr><td colspan="4" class="muted">Henüz veri yok.</td></tr>';
})();
</script>
</body>
</html>
