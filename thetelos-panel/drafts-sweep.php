<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Taslakları Süpür — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.site-table{width:100%;border-collapse:collapse;font-size:13px}
.site-table th{text-align:left;padding:9px 10px;border-bottom:2px solid var(--border);color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em}
.site-table td{padding:9px 10px;border-bottom:1px solid var(--border);vertical-align:top}
.stats-row{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap}
.stat-box{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:8px 14px}
.stat-val{font-size:19px;font-weight:700}
.stat-lbl{font-size:10.5px;color:var(--muted);margin-top:2px}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;white-space:nowrap}
.b-good{background:rgba(0,171,107,.15);color:#00ab6b}
.b-fix{background:rgba(214,158,0,.18);color:#d69e00}
.b-wrong{background:rgba(204,24,24,.15);color:#cc1818}
.b-unsure{background:rgba(140,140,140,.18);color:var(--muted)}
.ds-title a{color:var(--tls-gold);text-decoration:none;font-weight:600}
.ds-title a:hover{text-decoration:underline}
.ds-sub{display:block;color:var(--muted);font-size:11px;margin-top:2px}
.ds-reason{font-size:11px;color:var(--muted)}
.ds-act{display:flex;flex-direction:column;gap:5px;min-width:150px}
.btn-sm{font-size:11px;padding:4px 9px}
.bulk-row{display:flex;gap:8px;margin:0 0 14px;flex-wrap:wrap;align-items:center}
#ds-status{font-size:12px;color:var(--tls-gold);min-height:16px}
.note{font-size:12px;color:var(--muted);max-width:820px;line-height:1.6;margin-bottom:14px}
</style>
</head>
<body>
<div class="tls-shell">
  <?php require __DIR__ . "/_nav.php"; ?>

  <main class="tls-main">
    <div class="tls-header">
      <div>
        <h2>Taslakları Süpür</h2>
        <p>Yayınlanmayıp taslakta bekleyen kitapları topluca denetle: iyileri yayınla, yanlışları sil</p>
      </div>
    </div>

    <p class="note">
      Bu kitaplar <b>sitede yayında değil</b> (ziyaretçi görmez) — sistem şüpheli bulup taslakta bekletmiş.
      <b>Denetle</b> dersen Claude her birini "doğru kişi/eser hakkında mı, yayınlanabilir mi?" diye kontrol eder:
      <span class="badge b-good">iyi</span> yayınlanabilir ·
      <span class="badge b-fix">düzeltildi</span> Claude ufak hataları düzeltti (Düzelt&Yayınla) ·
      <span class="badge b-wrong">yanlış</span> farklı kişi/eser → sil ·
      <span class="badge b-unsure">belirsiz</span> elle bak. Silinenler <b>çöpe</b> gider, kalıcı değil (geri alınabilir).
    </p>

    <div class="stats-row">
      <div class="stat-box"><div class="stat-val" id="st-total">–</div><div class="stat-lbl">Toplam Taslak</div></div>
      <div class="stat-box"><div class="stat-val" id="st-good" style="color:#00ab6b">0</div><div class="stat-lbl">İyi</div></div>
      <div class="stat-box"><div class="stat-val" id="st-fix" style="color:#d69e00">0</div><div class="stat-lbl">Düzeltildi</div></div>
      <div class="stat-box"><div class="stat-val" id="st-wrong" style="color:#cc1818">0</div><div class="stat-lbl">Yanlış</div></div>
      <div class="stat-box"><div class="stat-val" id="st-done" style="color:var(--tls-gold)">0</div><div class="stat-lbl">İşlendi</div></div>
    </div>

    <div class="bulk-row">
      <button class="btn btn-primary" id="btn-scan">🔎 Taslakları Tara</button>
      <button class="btn" id="btn-checkall" style="display:none">🤖 Hepsini Denetle</button>
      <button class="btn" id="btn-stop" style="display:none">⏹ Durdur</button>
      <button class="btn" id="btn-pub-good" style="display:none">✅ İyileri Yayınla</button>
      <button class="btn" id="btn-del-wrong" style="display:none">🗑 Yanlışları Sil</button>
      <span id="ds-status"></span>
    </div>

    <div id="result"><div style="text-align:center;padding:40px;color:var(--muted)">Taslakları Tara'ya bas.</div></div>
  </main>
</div>

<script>
const $=id=>document.getElementById(id);
let tbody=null, scanning=false, stopFlag=false, offset=0, total=0;
let cGood=0,cFix=0,cWrong=0,cDone=0;
function post(b){return fetch('api/drafts-sweep.php',{method:'POST',credentials:'same-origin',
  headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b}).then(r=>r.json());}
function escH(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
const VB={good:'iyi',fix:'düzeltildi',wrong:'yanlış',unsure:'belirsiz'};
function badge(v){return '<span class="badge b-'+v+'">'+(VB[v]||v)+'</span>';}

function ensureTable(){
  if(tbody) return;
  $('result').innerHTML='<table class="site-table"><thead><tr>'+
    '<th style="width:42%">Kitap</th><th style="width:70px">Kelime</th><th>Sebep / Denetim</th><th style="width:170px"></th>'+
    '</tr></thead><tbody id="ds-tb"></tbody></table>';
  tbody=$('ds-tb');
}
function addRow(r){
  ensureTable();
  const tr=document.createElement('tr'); tr.dataset.id=r.id; tr.dataset.verdict='';
  tr.innerHTML=
    '<td class="ds-title"><a href="'+escH(r.link)+'" target="_blank" rel="noopener">'+escH(r.title)+' ↗</a>'+
      '<span class="ds-sub">'+escH(r.author||'—')+' · <a href="'+escH(r.edit)+'" target="_blank" rel="noopener" style="color:var(--muted)">düzenle</a></span></td>'+
    '<td>'+r.words+'</td>'+
    '<td class="ds-cell"><span class="ds-reason">'+escH(r.reason||'—')+'</span></td>'+
    '<td><div class="ds-act">'+
      '<button class="btn btn-sm ds-check">🤖 Denetle</button>'+
      '<button class="btn btn-sm btn-primary ds-pub">✅ Yayınla</button>'+
      '<button class="btn btn-sm ds-del">🗑 Sil</button>'+
    '</div></td>';
  tr.querySelector('.ds-check').addEventListener('click',()=>checkRow(tr));
  tr.querySelector('.ds-pub').addEventListener('click',()=>pubRow(tr,false));
  tr.querySelector('.ds-del').addEventListener('click',()=>delRow(tr,true));
  tbody.appendChild(tr);
  // Daha önce DENETLENMİŞSE (meta'da kayıtlı) → badge'i geri getir, sayacı say.
  if(r.verdict && ['good','fix','wrong','unsure'].includes(r.verdict)){
    setVerdict(tr,r.verdict,r.vnote||'');
    if(r.verdict==='good')cGood++; else if(r.verdict==='fix')cFix++; else if(r.verdict==='wrong')cWrong++;
    $('st-good').textContent=cGood; $('st-fix').textContent=cFix; $('st-wrong').textContent=cWrong;
    $('btn-pub-good').style.display=''; $('btn-del-wrong').style.display='';
  }
}
function setVerdict(tr,v,note){
  tr.dataset.verdict=v;
  const cell=tr.querySelector('.ds-cell');
  cell.innerHTML=badge(v)+(note?' <span class="ds-reason">'+escH(note)+'</span>':'');
  const pub=tr.querySelector('.ds-pub');
  if(v==='fix'){ pub.textContent='✨ Düzelt & Yayınla'; pub.onclick=()=>pubRow(tr,true); }
}

function checkRow(tr){
  const b=tr.querySelector('.ds-check'); b.disabled=true; b.textContent='…';
  return post('action=check&id='+tr.dataset.id).then(d=>{
    b.disabled=false; b.textContent='🤖 Denetle';
    if(!d||!d.ok){ tr.querySelector('.ds-cell').innerHTML='<span class="ds-reason" style="color:#cc1818">✕ '+escH(d&&d.error||'hata')+'</span>'; return; }
    const prev=tr.dataset.verdict;
    setVerdict(tr,d.verdict,d.note);
    if(prev!==d.verdict){
      if(d.verdict==='good')cGood++; else if(d.verdict==='fix')cFix++; else if(d.verdict==='wrong')cWrong++;
    }
    $('st-good').textContent=cGood; $('st-fix').textContent=cFix; $('st-wrong').textContent=cWrong;
    $('btn-pub-good').style.display=''; $('btn-del-wrong').style.display='';
  });
}
function pubRow(tr,applyFix){
  const b=tr.querySelector('.ds-pub'); b.disabled=true; const o=b.textContent; b.textContent='…';
  const fd=new URLSearchParams(); fd.set('action','publish'); fd.set('id',tr.dataset.id); if(applyFix)fd.set('apply_fix','1');
  return post(fd.toString()).then(d=>{
    if(!d||!d.ok){ b.disabled=false; b.textContent=o; $('ds-status').textContent='Hata: '+(d&&d.error||'yayın hatası'); return; }
    tr.querySelector('.ds-cell').innerHTML='<span class="badge b-good">✓ yayınlandı</span>';
    tr.querySelector('.ds-act').innerHTML='';
    cDone++; $('st-done').textContent=cDone;
  });
}
function delRow(tr,ask){
  if(ask && !confirm('Bu taslak çöpe atılacak (kalıcı değil, geri alınabilir). Emin misin?')) return Promise.resolve();
  const b=tr.querySelector('.ds-del'); if(b){b.disabled=true;b.textContent='…';}
  return post('action=delete&id='+tr.dataset.id).then(d=>{
    if(!d||!d.ok){ if(b){b.disabled=false;b.textContent='🗑 Sil';} $('ds-status').textContent='Hata: '+(d&&d.error||'silme hatası'); return; }
    tr.querySelector('.ds-cell').innerHTML='<span class="badge b-wrong">🗑 çöpe atıldı</span>';
    tr.querySelector('.ds-act').innerHTML='';
    cDone++; $('st-done').textContent=cDone;
  });
}

function scanOnce(){
  if(scanning) return; scanning=true; $('btn-scan').disabled=true;
  $('ds-status').textContent='Taslaklar taranıyor…';
  post('action=scan&offset='+offset+'&limit=30').then(d=>{
    scanning=false; $('btn-scan').disabled=false;
    if(!d||!d.ok){ $('ds-status').textContent='Hata: '+(d&&d.error||'tarama hatası'); return; }
    if(typeof d.total==='number'){ total=d.total; $('st-total').textContent=total.toLocaleString(); }
    d.rows.forEach(addRow);
    offset=d.next_offset;
    if(d.rows.length){ $('btn-checkall').style.display=''; }
    if(d.done){ $('ds-status').textContent='✓ Tüm taslaklar listelendi ('+offset+').'; return; }
    $('ds-status').textContent=offset+' taslak listelendi…';
    setTimeout(scanOnce,200);
  }).catch(()=>{scanning=false;$('btn-scan').disabled=false;$('ds-status').textContent='Bağlantı hatası.';});
}

async function checkAll(){
  stopFlag=false; $('btn-checkall').style.display='none'; $('btn-stop').style.display='';
  const rows=[...tbody.querySelectorAll('tr')].filter(tr=>!tr.dataset.verdict && tr.querySelector('.ds-check'));
  let i=0;
  for(const tr of rows){
    if(stopFlag) break; i++;
    $('ds-status').textContent='Denetleniyor '+i+'/'+rows.length+'… (iyi '+cGood+' · düzeltildi '+cFix+' · yanlış '+cWrong+')';
    try{ await checkRow(tr); }catch(e){}
  }
  $('btn-stop').style.display='none'; $('btn-checkall').style.display='';
  $('ds-status').textContent=(stopFlag?'⏸ Durduruldu. ':'✓ Denetim bitti. ')+
    'İyi '+cGood+' · Düzeltildi '+cFix+' · Yanlış '+cWrong+'. Şimdi "İyileri Yayınla" / "Yanlışları Sil" diyebilirsin.';
}

async function pubAllGood(){
  const rows=[...tbody.querySelectorAll('tr')].filter(tr=>tr.dataset.verdict==='good'||tr.dataset.verdict==='fix');
  if(!rows.length){ $('ds-status').textContent='Yayınlanacak (iyi/düzeltildi) taslak yok. Önce Denetle.'; return; }
  if(!confirm(rows.length+' taslak yayınlanacak (düzeltilmiş olanların düzeltmesi uygulanır). Devam?')) return;
  for(const tr of rows){ await pubRow(tr, tr.dataset.verdict==='fix'); }
  $('ds-status').textContent='✓ İyi/düzeltilmiş taslaklar yayınlandı.';
}
async function delAllWrong(){
  const rows=[...tbody.querySelectorAll('tr')].filter(tr=>tr.dataset.verdict==='wrong');
  if(!rows.length){ $('ds-status').textContent='Silinecek (yanlış) taslak yok.'; return; }
  if(!confirm(rows.length+' "yanlış" taslak çöpe atılacak (geri alınabilir). Devam?')) return;
  for(const tr of rows){ await delRow(tr,false); }
  $('ds-status').textContent='✓ Yanlış taslaklar çöpe atıldı.';
}

$('btn-scan').addEventListener('click',()=>{ offset=0; tbody=null; cGood=cFix=cWrong=cDone=0;
  ['st-good','st-fix','st-wrong','st-done'].forEach(x=>$(x).textContent='0'); $('result').innerHTML=''; scanOnce(); });
$('btn-checkall').addEventListener('click',checkAll);
$('btn-stop').addEventListener('click',()=>{ stopFlag=true; $('ds-status').textContent='Durduruluyor…'; });
$('btn-pub-good').addEventListener('click',pubAllGood);
$('btn-del-wrong').addEventListener('click',delAllWrong);
</script>
</body>
</html>
