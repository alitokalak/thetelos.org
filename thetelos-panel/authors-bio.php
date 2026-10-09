<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Yazar Bio — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.site-table{width:100%;border-collapse:collapse;font-size:13px}
.site-table th{text-align:left;padding:10px 12px;border-bottom:2px solid var(--border);color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.site-table td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:top}
.stats-row{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.stat-box{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 16px}
.stat-val{font-size:20px;font-weight:700}
.stat-lbl{font-size:11px;color:var(--muted);margin-top:2px}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;white-space:nowrap}
.b-ok{background:rgba(0,171,107,.15);color:#00ab6b}
.b-missing{background:rgba(204,24,24,.15);color:#cc1818}
.b-truncated{background:rgba(214,158,0,.18);color:#d69e00}
.b-markdown{background:rgba(214,158,0,.18);color:#d69e00}
.b-short{background:rgba(140,140,140,.18);color:var(--muted)}
.ab-name{font-weight:600}
.ab-name a{color:var(--tls-gold);text-decoration:none}
.ab-name a:hover{text-decoration:underline}
.ab-name small{display:block;color:var(--muted);font-weight:400;font-size:11px}
.ab-bio{width:100%;min-height:62px;padding:7px 9px;font-size:12.5px;line-height:1.5;background:var(--surface2);border:1px solid var(--border);border-radius:6px;color:var(--text);resize:vertical;font-family:inherit}
.ab-act{display:flex;flex-direction:column;gap:6px;min-width:120px}
.bulk-row{display:flex;gap:8px;margin:0 0 14px;flex-wrap:wrap;align-items:center}
label.chk{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px;cursor:pointer}
#ab-status{font-size:12px;color:var(--tls-gold);min-height:16px}
.btn-sm{font-size:11px;padding:5px 10px}
</style>
</head>
<body>
<div class="tls-shell">
  <?php require __DIR__ . "/_nav.php"; ?>

  <main class="tls-main">
    <div class="tls-header">
      <div>
        <h2>Yazar Bio Denetimi</h2>
        <p>Yazar biyografilerini kontrol et; yarıda kesik / eksik / markdown'lı olanları yeniden üret ya da elle düzelt</p>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-box"><div class="stat-val" id="st-total">–</div><div class="stat-lbl">Toplam Yazar</div></div>
      <div class="stat-box"><div class="stat-val" id="st-scanned" style="color:var(--tls-gold)">0</div><div class="stat-lbl">Tarandı</div></div>
      <div class="stat-box"><div class="stat-val" id="st-issues" style="color:#cc1818">0</div><div class="stat-lbl">Sorunlu (listede)</div></div>
      <div class="stat-box"><div class="stat-val" id="st-fixed" style="color:#00ab6b">0</div><div class="stat-lbl">Düzeltildi</div></div>
    </div>

    <div class="bulk-row">
      <button class="btn btn-primary" id="btn-scan">🔎 Tara</button>
      <button class="btn" id="btn-stop" style="display:none">⏹ Durdur</button>
      <label class="chk"><input type="checkbox" id="only-issues" checked> Sadece sorunluları göster</label>
      <span id="ab-status"></span>
    </div>

    <p style="font-size:12px;color:var(--muted);margin-bottom:14px;max-width:760px">
      Durumlar: <span class="badge b-missing">eksik</span> bio yok ·
      <span class="badge b-truncated">yarıda kesik</span> cümle bitmemiş ·
      <span class="badge b-markdown">markdown</span> ham <code>*</code>/<code>#</code> var ·
      <span class="badge b-short">kısa</span> çok kısa ·
      <span class="badge b-ok">iyi</span>.
      <b>Yeniden Üret</b> ile taslak gelir (kaydetmez) — metni gözden geçir, gerekirse elle düzelt, <b>Kaydet</b>'e bas.
    </p>

    <div id="result"><div style="text-align:center;padding:40px;color:var(--muted)">Tara'ya bas.</div></div>
  </main>
</div>

<script>
const API = p => 'api/' + p;
const $ = id => document.getElementById(id);
let tbody=null, scanning=false, stopFlag=false, offset=0, scanned=0, issues=0, fixed=0;

function post(body){
  return fetch(API('authors-bio.php'), {method:'POST',credentials:'same-origin',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},body}).then(r=>r.json());
}
function escH(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
const BADGE={ok:'iyi',missing:'eksik',truncated:'yarıda kesik',markdown:'markdown',short:'kısa'};
function badge(st){return '<span class="badge b-'+st+'">'+(BADGE[st]||st)+'</span>'}

function ensureTable(){
  if(tbody) return;
  $('result').innerHTML='<table class="site-table"><thead><tr>'+
    '<th style="width:22%">Yazar</th><th style="width:90px">Durum</th><th>Bio</th><th style="width:130px"></th>'+
    '</tr></thead><tbody id="ab-tbody"></tbody></table>';
  tbody=$('ab-tbody');
}
function addRow(r){
  ensureTable();
  const tr=document.createElement('tr'); tr.dataset.id=r.id;
  tr.innerHTML=
    '<td class="ab-name"><a href="'+escH(r.link)+'" target="_blank" rel="noopener">'+escH(r.name)+' ↗</a>'+
      '<small>'+r.count+' kitap</small></td>'+
    '<td class="ab-st">'+badge(r.status)+'</td>'+
    '<td><textarea class="ab-bio">'+escH(r.bio)+'</textarea></td>'+
    '<td><div class="ab-act">'+
      '<button class="btn btn-sm ab-regen">✨ Yeniden Üret</button>'+
      '<button class="btn btn-sm btn-primary ab-save">💾 Kaydet</button>'+
    '</div></td>';
  tr.querySelector('.ab-regen').addEventListener('click',()=>regen(tr));
  tr.querySelector('.ab-save').addEventListener('click',()=>save(tr));
  tbody.appendChild(tr);
}

function regen(tr){
  const btn=tr.querySelector('.ab-regen'); btn.disabled=true; btn.textContent='Üretiliyor…';
  post('action=regen&id='+tr.dataset.id).then(d=>{
    btn.disabled=false; btn.textContent='✨ Yeniden Üret';
    if(!d||!d.ok){ $('ab-status').textContent=d&&d.error?('Hata: '+d.error):'Üretim hatası.'; return; }
    tr.querySelector('.ab-bio').value=d.bio;
    tr.querySelector('.ab-st').innerHTML=badge(d.status)+' <span style="font-size:10px;color:var(--muted)">taslak — kaydet</span>';
  }).catch(()=>{btn.disabled=false; btn.textContent='✨ Yeniden Üret'; $('ab-status').textContent='Bağlantı hatası.';});
}
function save(tr){
  const btn=tr.querySelector('.ab-save'), bio=tr.querySelector('.ab-bio').value.trim();
  if(bio===''){ if(!confirm('Bio boş kaydedilecek. Emin misin?')) return; }
  btn.disabled=true; btn.textContent='Kaydediliyor…';
  const fd=new URLSearchParams(); fd.set('action','save'); fd.set('id',tr.dataset.id); fd.set('bio',bio);
  post(fd.toString()).then(d=>{
    btn.disabled=false; btn.textContent='💾 Kaydet';
    if(!d||!d.ok){ $('ab-status').textContent=d&&d.error?('Hata: '+d.error):'Kaydetme hatası.'; return; }
    tr.querySelector('.ab-st').innerHTML=badge(d.status)+' <span style="font-size:10px;color:#00ab6b">✓ kaydedildi</span>';
    fixed++; $('st-fixed').textContent=fixed.toLocaleString();
  }).catch(()=>{btn.disabled=false; btn.textContent='💾 Kaydet'; $('ab-status').textContent='Bağlantı hatası.';});
}

function scanOnce(){
  if(scanning) return; scanning=true;
  $('btn-scan').disabled=true; $('btn-stop').style.display='';
  $('ab-status').textContent='Yazarlar taranıyor…';
  post('action=scan&offset='+offset+'&limit=60&only_issues='+($('only-issues').checked?1:0)).then(d=>{
    scanning=false; $('btn-scan').disabled=false;
    if(!d||!d.ok){ $('ab-status').textContent=d&&d.error?('Hata: '+d.error):'Tarama hatası.'; $('btn-stop').style.display='none'; return; }
    if(typeof d.total==='number') $('st-total').textContent=d.total.toLocaleString();
    scanned+=d.scanned; $('st-scanned').textContent=scanned.toLocaleString();
    d.rows.forEach(r=>{ addRow(r); if(r.status!=='ok'){issues++;} });
    $('st-issues').textContent=issues.toLocaleString();
    offset=d.next_offset;
    if(d.done){ $('ab-status').textContent='✓ Tüm yazarlar tarandı. Sorunlu: '+issues; $('btn-stop').style.display='none'; return; }
    $('ab-status').textContent=scanned+' yazar tarandı · '+issues+' sorunlu…';
    if(stopFlag){ stopFlag=false; $('btn-stop').style.display='none'; $('ab-status').textContent='⏸ Durduruldu. '+scanned+' tarandı · '+issues+' sorunlu.'; return; }
    setTimeout(scanOnce, 250);
  }).catch(()=>{scanning=false; $('btn-scan').disabled=false; $('btn-stop').style.display='none'; $('ab-status').textContent='Bağlantı hatası.';});
}

$('btn-scan').addEventListener('click',()=>{ stopFlag=false; scanOnce(); });
$('btn-stop').addEventListener('click',()=>{ stopFlag=true; $('ab-status').textContent='Durduruluyor…'; });
</script>
</body>
</html>
