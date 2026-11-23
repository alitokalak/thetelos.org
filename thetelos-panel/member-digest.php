<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Üye Mailleri — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.site-table{width:100%;border-collapse:collapse;font-size:13px}
.site-table th{text-align:left;padding:10px 12px;border-bottom:2px solid var(--border);color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.site-table td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:middle}
.bulk-row{display:flex;gap:8px;margin:0 0 14px;flex-wrap:wrap;align-items:center}
#md-status{font-size:13px;color:var(--tls-gold);min-height:18px}
.bulk-row{position:sticky;top:0;z-index:5;background:var(--bg,#111);padding:8px 0}
.md-ok{color:#3fb168;font-weight:600}.md-err{color:#e06060;font-weight:600}.md-wait{color:var(--tls-gold)}
.muted{color:var(--muted);font-size:12px}
.tag-off{color:#c55;font-size:11px}
#md-prev{width:100%;height:640px;border:1px solid var(--border);border-radius:8px;background:#fff;display:none;margin-top:14px}
</style>
</head>
<body>
<div class="tls-shell">
  <?php require __DIR__ . "/_nav.php"; ?>
  <main class="tls-main">
    <div class="tls-header"><div>
      <h2>Üye Mailleri</h2>
      <p>Her üyeye, işaretlediği kitaplar, kaydettiği özetler ve ilgi alanlarına göre <b>kişisel okuma önerisi</b> maili. Mail yalnız siz <b>Gönder</b>'e bastığınızda gider. Önce <b>Önizle</b> ya da <b>Bana test gönder</b> ile kontrol edin.</p>
    </div></div>

    <div class="bulk-row">
      <button class="btn" id="md-load">🔄 Üyeleri yükle</button>
      <button class="btn" id="md-all">☑ Verisi olanların hepsini seç</button>
      <button class="btn btn-primary" id="md-send">✉ Seçilenlere gönder</button>
      <label class="muted" style="display:flex;align-items:center;gap:6px">Test adresi
        <input type="email" id="md-testto" value="thetelosorg@gmail.com" style="width:230px;padding:5px 8px"></label>
      <span id="md-status"></span>
    </div>

    <div id="md-summary" style="font-size:14px;margin:4px 0 12px"></div>
    <div class="card">
      <table class="site-table">
        <thead><tr><th></th><th>Üye</th><th>Neye göre</th><th>Öneri</th><th>Son gönderim</th><th></th></tr></thead>
        <tbody id="md-body"><tr><td colspan="6" class="muted">Yükleniyor…</td></tr></tbody>
      </table>
    </div>
    <iframe id="md-prev"></iframe>
  </main>
</div>
<script>
const $ = s => document.querySelector(s);
const st = t => { $('#md-status').textContent = t; };
let ADMIN = '';
async function load() {
  st('Üyeler ve öneriler hesaplanıyor…');
  const r = await fetch('api/member-digest.php?action=list').then(x => x.json()).catch(e => ({ok:false,error:e.message}));
  if (!r.ok) { st('Hata: ' + (r.error||'?')); return; }
  ADMIN = r.admin_email || '';
  const rows = r.members.map(m => `<tr data-uid="${m.uid}">
    <td><input type="checkbox" class="md-cb" value="${m.uid}" ${m.optout||!m.recs&&!m.reminders?'disabled':''} data-hasdata="${m.has_data?1:0}"></td>
    <td><b>${esc(m.name)}</b><br><span class="muted">${esc(m.email)}</span>${m.optout?'<br><span class="tag-off">abonelikten çıktı · '+esc(m.optout_at)+' — mail gitmez</span> <button class="btn btn-sm" style="padding:1px 8px;font-size:11px" onclick="resub('+m.uid+')">↺ Geri aç</button>':''}</td>
    <td class="muted">${esc(m.basis)}${(m.log&&m.log.length)?'<br><span title="'+esc(m.log.map(l=>l.join(' · ')).join('\n'))+'" style="cursor:help;text-decoration:underline dotted">📜 son: '+esc(m.log[m.log.length-1].join(' · '))+'</span>':''}</td>
    <td>${m.recs} öneri${m.reminders?` + ${m.reminders} hatırlatma`:''}</td>
    <td class="muted md-sent">${m.last_sent?('✓ '+esc(m.last_sent)):'—'}${clk(m)}</td>
    <td style="white-space:nowrap"><button class="btn btn-sm" onclick="prev(${m.uid})">👁 Önizle</button>
        <button class="btn btn-sm" onclick="test(${m.uid})">🧪 Bana test gönder</button></td></tr>`).join('');
  $('#md-body').innerHTML = rows || '<tr><td colspan="6" class="muted">Üye yok.</td></tr>';
  const withData = r.members.filter(m => m.has_data).length;
  const outs = r.members.filter(m => m.optout).length;
  // Son sayının dönüş özeti
  const maxIssue = Math.max(0, ...r.members.map(m => m.last_issue||0));
  const inIssue = r.members.filter(m => maxIssue && m.last_issue === maxIssue);
  const came = inIssue.filter(m => m.click && m.click.clicks && !m.click.bot).length;
  $('#md-summary').innerHTML = maxIssue
    ? `📊 <b>Sayı ${maxIssue}</b>: ${inIssue.length} kişiye gitti · <b>${came} kişi siteye geldi</b> (%${inIssue.length?Math.round(came*100/inIssue.length):0})`
    : '📊 Tıklama takibi bir sonraki gönderimden itibaren ölçülecek.';
  st(`${r.members.length} üye · ${withData} tanesinin verisi var · ${outs} kişi abonelikten çıktı (onlara mail gitmez). Test mailleri ${ADMIN} adresine gider.`);
}
function clk(m){
  if (!m.last_sent) return '';
  if (!m.click) return '<br><span class="muted" style="font-size:11px">(takip öncesi gönderim)</span>';
  const c = m.click;
  if (!c.clicks) return '<br><span class="muted" style="font-size:11px">Sayı '+m.last_issue+' · henüz tıklamadı</span>';
  if (c.bot) return '<br><span style="font-size:11px;color:#c90">⚠ Sayı '+m.last_issue+' · '+c.clicks+' tıklama — otomatik tarayıcı olabilir</span>';
  return '<br><span class="md-ok" style="font-size:12px" title="'+esc(c.titles.join('\n'))+'">👆 Sayı '+m.last_issue+' · siteye geldi ('+c.books.length+' kitap) · '+esc(c.first)+'</span>';
}
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function prev(uid){ const f=$('#md-prev'); f.style.display='block'; f.src='api/member-digest.php?action=preview&uid='+uid; f.scrollIntoView({behavior:'smooth'}); }
async function post(data){ const fd=new FormData(); for(const k in data) fd.append(k,data[k]); return fetch('api/member-digest.php',{method:'POST',body:fd}).then(x=>x.json()).catch(e=>({ok:false,error:e.message})); }
async function test(uid){ st('Test maili gönderiliyor…'); const r=await post({action:'test',uid,to:($('#md-testto').value||'').trim()}); st(r.ok?('✓ Test maili gönderildi → '+r.to):('✗ '+(r.error||'hata'))); }
async function resub(uid){ if(!confirm('Bu üyenin aboneliği geri açılsın mı? (Yalnız üye gerçekten çıkmadıysa — ör. otomatik tarayıcı tıkladıysa.)')) return; const r=await post({action:'resub',uid}); st(r.ok?'✓ Abonelik geri açıldı.':('✗ '+(r.error||'hata'))); load(); }
$('#md-load').onclick = load;
$('#md-all').onclick = () => document.querySelectorAll('.md-cb').forEach(c => { if(!c.disabled && c.dataset.hasdata==='1') c.checked = true; });
$('#md-send').onclick = async () => {
  const ids = [...document.querySelectorAll('.md-cb:checked')].map(c => c.value);
  if (!ids.length) { st('Önce üye seçin.'); return; }
  if (!confirm(ids.length + ' üyeye GERÇEK mail gönderilsin mi?')) return;
  const btn = $('#md-send'); btn.disabled = true;
  let ok = 0, bad = 0;
  for (let i = 0; i < ids.length; i++) {
    const uid = ids[i];
    const cell = document.querySelector(`tr[data-uid="${uid}"] .md-sent`);
    if (cell) cell.innerHTML = '<span class="md-wait">⏳ gönderiliyor…</span>';
    st(`Gönderiliyor… ${i + 1} / ${ids.length} · ✓ ${ok} · ✗ ${bad}`);
    const r = await post({action:'send', uids: uid, no_bump: '1'});
    const failed = (r && r.ok) ? (r.failed || []) : [{error: (r && r.error) || 'bağlantı hatası'}];
    if (r && r.ok && r.sent > 0) {
      ok++;
      if (cell) cell.innerHTML = '<span class="md-ok">✓ gönderildi ' + new Date().toLocaleTimeString('tr-TR', {hour:'2-digit', minute:'2-digit'}) + '</span>';
      const cb = document.querySelector(`tr[data-uid="${uid}"] .md-cb`); if (cb) cb.checked = false;
    } else {
      bad++;
      if (cell) cell.innerHTML = '<span class="md-err">✗ ' + esc(failed[0] ? failed[0].error : 'gönderilemedi') + '</span>';
    }
    if (i < ids.length - 1) await new Promise(res => setTimeout(res, 2000));   // mailler arası 2 sn (spam koruması)
  }
  if (ok > 0) await post({action:'bump'});   // sayı (Issue Nº) bu gönderim için 1 artar
  btn.disabled = false;
  st(`Bitti ✓ ${ok} mail gönderildi` + (bad ? ` · ✗ ${bad} gönderilemedi (satırlarda sebebi yazıyor)` : ''));
};
load();
</script>
</body>
</html>
