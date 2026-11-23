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
  const rows = r.members.map(m => `<tr>
    <td><input type="checkbox" class="md-cb" value="${m.uid}" ${m.optout||!m.recs&&!m.reminders?'disabled':''} data-hasdata="${m.has_data?1:0}"></td>
    <td><b>${esc(m.name)}</b><br><span class="muted">${esc(m.email)}</span>${m.optout?'<br><span class="tag-off">abonelikten çıktı · '+esc(m.optout_at)+' — mail gitmez</span>':''}</td>
    <td class="muted">${esc(m.basis)}</td>
    <td>${m.recs} öneri${m.reminders?` + ${m.reminders} hatırlatma`:''}</td>
    <td class="muted">${m.last_sent||'—'}</td>
    <td style="white-space:nowrap"><button class="btn btn-sm" onclick="prev(${m.uid})">👁 Önizle</button>
        <button class="btn btn-sm" onclick="test(${m.uid})">🧪 Bana test gönder</button></td></tr>`).join('');
  $('#md-body').innerHTML = rows || '<tr><td colspan="6" class="muted">Üye yok.</td></tr>';
  const withData = r.members.filter(m => m.has_data).length;
  const outs = r.members.filter(m => m.optout).length;
  st(`${r.members.length} üye · ${withData} tanesinin verisi var · ${outs} kişi abonelikten çıktı (onlara mail gitmez). Test mailleri ${ADMIN} adresine gider.`);
}
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function prev(uid){ const f=$('#md-prev'); f.style.display='block'; f.src='api/member-digest.php?action=preview&uid='+uid; f.scrollIntoView({behavior:'smooth'}); }
async function post(data){ const fd=new FormData(); for(const k in data) fd.append(k,data[k]); return fetch('api/member-digest.php',{method:'POST',body:fd}).then(x=>x.json()).catch(e=>({ok:false,error:e.message})); }
async function test(uid){ st('Test maili gönderiliyor…'); const r=await post({action:'test',uid,to:($('#md-testto').value||'').trim()}); st(r.ok?('✓ Test maili gönderildi → '+r.to):('✗ '+(r.error||'hata'))); }
$('#md-load').onclick = load;
$('#md-all').onclick = () => document.querySelectorAll('.md-cb').forEach(c => { if(!c.disabled && c.dataset.hasdata==='1') c.checked = true; });
$('#md-send').onclick = async () => {
  const ids = [...document.querySelectorAll('.md-cb:checked')].map(c => c.value);
  if (!ids.length) { st('Önce üye seçin.'); return; }
  if (!confirm(ids.length + ' üyeye GERÇEK mail gönderilsin mi?')) return;
  st('Gönderiliyor… (' + ids.length + ' üye)');
  const r = await post({action:'send', uids: ids.join(',')});
  if (!r.ok) { st('✗ ' + (r.error||'hata')); return; }
  st(`✓ ${r.sent} mail gönderildi` + (r.failed.length ? ` · ${r.failed.length} gönderilemedi (${r.failed.map(f=>f.error).join(', ')})` : ''));
  load();
};
load();
</script>
</body>
</html>
