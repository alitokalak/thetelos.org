<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Yazar Ayrıştır — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.as-search{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center}
.as-search input{flex:1;min-width:220px;padding:9px 11px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:14px}
.as-auths{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.as-auth{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:7px 11px;cursor:pointer;font-size:13px}
.as-auth:hover{border-color:var(--tls-gold)}
.as-auth b{color:var(--tls-gold)}
.as-auth small{color:var(--muted);margin-left:6px}
.grp{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:14px}
.grp.uncertain{opacity:.8;border-style:dashed}
.grp-h{display:flex;gap:10px;align-items:center;margin-bottom:8px;flex-wrap:wrap}
.grp-h input{flex:1;min-width:220px;padding:7px 10px;background:var(--surface);border:1px solid var(--border);border-radius:7px;color:var(--text);font-size:14px;font-weight:600}
.grp-who{font-size:12.5px;color:var(--muted);margin:2px 0 10px}
.grp-cnt{font-size:11px;color:var(--muted);white-space:nowrap}
.grp-books{display:flex;flex-direction:column;gap:3px;max-height:220px;overflow:auto;font-size:12.5px}
.grp-books div{padding:3px 6px;border-bottom:1px solid var(--border)}
.pill{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:rgba(214,158,0,.18);color:#d69e00}
.pill.keep{background:rgba(0,171,107,.15);color:#00ab6b}
#as-status{font-size:12.5px;color:var(--tls-gold);min-height:16px;margin:8px 0}
.as-actions{display:flex;gap:8px;margin:14px 0;flex-wrap:wrap}
.note{font-size:12px;color:var(--muted);max-width:820px;line-height:1.6;margin-bottom:14px}
</style>
</head>
<body>
<div class="tls-shell">
  <?php require __DIR__ . "/_nav.php"; ?>

  <main class="tls-main">
    <div class="tls-header">
      <div>
        <h2>Yazar Ayrıştır</h2>
        <p>Aynı isimdeki farklı kişileri (ör. fizikçi vs romancı "William Gilbert") kitaplarına göre ayrı yazarlara böl</p>
      </div>
    </div>

    <p class="note">
      <b>Nasıl çalışır:</b> Bir yazar ara ve seç → <b>Analiz Et</b>. Claude (web aramalı) o yazarın tüm
      kitaplarını <b>gerçek yazarına göre</b> gruplar. Önizlemeyi gözden geçir, etiketleri gerekirse düzelt,
      sonra <b>Uygula</b>'ya bas: her grup ayrı yazara bölünür, kitaplar doğru kişiye taşınır.
      En büyük grup (ya da tam isimle eşleşen) eski yazarda kalır; "Belirsiz" grubu taşınmaz.
      Yanlış olursa <b>Geri Al</b> ile tek tıkla eski haline döner. Bio'ları sonra "Yazar Bio" panelinden yazdırırsın.
    </p>

    <div class="as-search">
      <input type="text" id="as-q" placeholder="Yazar adı ara (ör. William Gilbert)…">
      <button class="btn btn-primary" id="btn-find">🔎 Ara</button>
    </div>
    <div class="as-auths" id="as-auths"></div>

    <div id="as-status"></div>

    <div class="as-actions" id="as-actions" style="display:none">
      <button class="btn btn-primary" id="btn-apply">✅ Uygula (grupları ayır)</button>
      <button class="btn" id="btn-restore" style="display:none">↩ Son Ayırmayı Geri Al</button>
    </div>

    <div id="as-groups"></div>
  </main>
</div>

<script>
const $=id=>document.getElementById(id);
let curTerm=0, curName='';
function post(body){return fetch('api/author-split.php',{method:'POST',credentials:'same-origin',
  headers:{'Content-Type':'application/x-www-form-urlencoded'},body}).then(r=>r.json());}
function escH(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

function findAuthors(){
  const q=$('as-q').value.trim();
  $('as-auths').innerHTML='<span style="color:var(--muted);font-size:13px">Aranıyor…</span>';
  post('action=authors&q='+encodeURIComponent(q)).then(d=>{
    if(!d||!d.ok){ $('as-auths').innerHTML='<span style="color:#cc1818">'+escH(d&&d.error||'hata')+'</span>'; return; }
    if(!d.rows.length){ $('as-auths').innerHTML='<span style="color:var(--muted)">Yazar bulunamadı.</span>'; return; }
    $('as-auths').innerHTML='';
    d.rows.forEach(r=>{
      const el=document.createElement('div'); el.className='as-auth';
      el.innerHTML='<b>'+escH(r.name)+'</b><small>'+r.count+' kitap</small>';
      el.addEventListener('click',()=>analyze(r.id,r.name));
      $('as-auths').appendChild(el);
    });
  });
}

function analyze(termId,name){
  curTerm=termId; curName=name;
  $('as-groups').innerHTML=''; $('as-actions').style.display='none';
  $('as-status').textContent='"'+name+'" kitapları Claude + web araması ile gruplanıyor… (biraz sürebilir)';
  post('action=analyze&term_id='+termId).then(d=>{
    if(!d||!d.ok){ $('as-status').textContent='Hata: '+(d&&d.error||'analiz başarısız'); return; }
    renderGroups(d);
  }).catch(()=>{ $('as-status').textContent='Bağlantı hatası.'; });
}

function renderGroups(d){
  const real=d.groups.filter(g=>g.label.toLowerCase()!=='uncertain'&&g.label.toLowerCase()!=='belirsiz');
  $('as-status').innerHTML='<b>'+escH(d.name)+'</b>: '+d.analyzed+' kitap analiz edildi'+
    (d.total>d.analyzed?(' ('+d.total+' kitaptan ilk '+d.analyzed+')'):'')+
    ' → <b>'+real.length+' farklı kişi</b> tespit edildi.';
  // En büyük grup eski yazarda kalır (ya da tam isim eşleşen).
  let keep=-1,mx=-1;
  d.groups.forEach((g,i)=>{ if(g.label.toLowerCase()===d.name.toLowerCase()){keep=i;} });
  if(keep<0){ d.groups.forEach((g,i)=>{ if(g.ids.length>mx){mx=g.ids.length;keep=i;} }); }

  const wrap=$('as-groups'); wrap.innerHTML='';
  d.groups.forEach((g,i)=>{
    const unc=(g.label.toLowerCase()==='uncertain'||g.label.toLowerCase()==='belirsiz');
    const isKeep=(i===keep);
    const div=document.createElement('div'); div.className='grp'+(unc?' uncertain':''); div.dataset.idx=i;
    let booksHtml='';
    g.ids.forEach(id=>{ booksHtml+='<div>'+escH(d.titles[id]||('#'+id))+'</div>'; });
    div.innerHTML=
      '<div class="grp-h">'+
        (unc
          ? '<b style="flex:1">❓ Belirsiz (taşınmaz)</b>'
          : '<input class="grp-label" value="'+escH(g.label)+'" '+(isKeep?'disabled':'')+'>')+
        '<span class="grp-cnt">'+g.ids.length+' kitap</span>'+
        (isKeep?' <span class="pill keep">eski yazarda kalır</span>':(unc?'':' <span class="pill">yeni yazar</span>'))+
      '</div>'+
      (g.who?'<div class="grp-who">'+escH(g.who)+'</div>':'')+
      '<div class="grp-books">'+booksHtml+'</div>';
    div.__ids=g.ids; div.__keep=isKeep; div.__unc=unc;
    wrap.appendChild(div);
  });
  $('as-actions').style.display='';
  $('btn-restore').style.display='none';
}

function apply(){
  if(!curTerm) return;
  const divs=[...document.querySelectorAll('.grp')];
  const groups=divs.map(div=>{
    const lblEl=div.querySelector('.grp-label');
    return { label: div.__unc ? 'Uncertain' : (lblEl?lblEl.value.trim():''), ids: div.__ids, keep: !!div.__keep };
  });
  const moveCount=groups.filter(g=>!g.keep && g.label.toLowerCase()!=='uncertain').reduce((a,g)=>a+g.ids.length,0);
  if(moveCount===0){ $('as-status').textContent='Taşınacak kitap yok (tek kişi gibi görünüyor).'; return; }
  if(!confirm(moveCount+' kitap, tespit edilen gerçek yazarlarına taşınacak. Yeni yazar terimleri oluşturulacak.\nGeri alınabilir. Devam edilsin mi?')) return;
  $('btn-apply').disabled=true; $('as-status').textContent='Uygulanıyor…';
  const fd=new URLSearchParams(); fd.set('action','apply'); fd.set('term_id',curTerm); fd.set('groups',JSON.stringify(groups));
  post(fd.toString()).then(d=>{
    $('btn-apply').disabled=false;
    if(!d||!d.ok){ $('as-status').textContent='Hata: '+(d&&d.error||'uygulama başarısız'); return; }
    let msg='✓ Bitti. '+d.moved+' kitap taşındı. Yeni yazarlar: '+(d.created||[]).map(c=>c.label+' ('+c.count+')').join(', ');
    $('as-status').textContent=msg;
    if(d.can_restore) $('btn-restore').style.display='';
  }).catch(()=>{ $('btn-apply').disabled=false; $('as-status').textContent='Bağlantı hatası.'; });
}

function restore(){
  if(!curTerm) return;
  if(!confirm('Son ayırma geri alınacak; kitaplar eski yazara dönecek. Emin misin?')) return;
  $('btn-restore').disabled=true;
  post('action=restore&term_id='+curTerm).then(d=>{
    $('btn-restore').disabled=false;
    if(!d||!d.ok){ $('as-status').textContent='Hata: '+(d&&d.error||'geri alma başarısız'); return; }
    $('as-status').textContent='↩ Geri alındı. '+d.restored+' kitap eski yazara döndürüldü.';
    $('btn-restore').style.display='none';
  });
}

$('btn-find').addEventListener('click',findAuthors);
$('as-q').addEventListener('keydown',e=>{ if(e.key==='Enter') findAuthors(); });
$('btn-apply').addEventListener('click',apply);
$('btn-restore').addEventListener('click',restore);
findAuthors();
</script>
</body>
</html>
