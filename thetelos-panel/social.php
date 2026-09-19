<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sosyal — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.sc-bar{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:18px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px 16px}
.sc-bar label{font-size:12px;color:var(--muted);display:flex;flex-direction:column;gap:4px}
.sc-bar select,.sc-bar input{padding:6px 10px;font-size:13px;background:var(--surface);border:1px solid var(--border);border-radius:6px;color:var(--text)}
.sc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px}
.sc-card{background:var(--surface2);border:1px solid var(--border);border-radius:12px;overflow:hidden;display:flex;flex-direction:column}
.sc-card canvas{width:100%;height:auto;display:block;background:#14100c}
.sc-body{padding:12px 14px;display:flex;flex-direction:column;gap:8px}
.sc-meta{font-size:12px;color:var(--muted)}
.sc-cap{width:100%;min-height:120px;background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:12px;padding:8px 10px;font-family:inherit;resize:vertical;line-height:1.5}
.sc-actions{display:flex;gap:8px;flex-wrap:wrap}
.sc-actions .btn{font-size:12px;padding:6px 10px}
#sc-status{font-size:12px;color:var(--tls-gold);min-height:18px;margin-top:4px}
.sc-note{background:rgba(74,158,255,.1);border:1px solid rgba(74,158,255,.3);border-radius:8px;padding:10px 12px;font-size:12px;color:#8fbaff;margin-bottom:16px;line-height:1.5}
</style>
</head>
<body>
<div class="tls-shell">
  <aside class="tls-sidebar">
    <div class="tls-logo"><h1>Thetelos</h1><small>Content Panel</small></div>
    <nav class="tls-nav">
      <a href="panel.php"><span class="ico">✍</span> İçerik Üret</a>
      <a href="social.php" class="active"><span class="ico">📣</span> Sosyal</a>
      <a href="seo.php"><span class="ico">🔍</span> İçerik SEO</a>
      <a href="seo-site.php"><span class="ico">🌐</span> Site SEO</a>
      <a href="content-audit.php"><span class="ico">🩺</span> İçerik Denetimi</a>
      <a href="content-guard.php"><span class="ico">🛡️</span> İçerik Koruma</a>
      <a href="category-organize.php"><span class="ico">🗄️</span> Kategori Organize</a>
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
        <h2>Sosyal İçerik <span style="font-size:12px;color:var(--muted)">(Faz 1 · üret + indir)</span></h2>
        <p>Popüler yazılardan alıntı kartı + hazır caption üret. Kartı indir, caption'ı kopyala, Instagram/X'e paylaş.</p>
      </div>
    </div>

    <div class="sc-note">📌 Şu an: kart görselini üretip <b>indiriyorsun</b>, caption'ı kopyalıyorsun, elle paylaşıyorsun (müziği Instagram uygulamasında eklersin). Otomatik yayın (Instagram/X API) sonraki faz.</div>

    <div class="sc-bar">
      <label>Adet
        <select id="sc-count"><option>5</option><option selected>10</option><option>15</option><option>20</option></select>
      </label>
      <label>Kaynak
        <select id="sc-source">
          <option value="popular">En popüler (okunma)</option>
          <option value="recent">En yeni</option>
        </select>
      </label>
      <label>Elle başlık (opsiyonel)
        <input type="text" id="sc-title" placeholder="Örn: Meditations" style="min-width:220px">
      </label>
      <label>&nbsp;
        <button class="btn btn-primary" id="sc-gen">✦ Üret</button>
      </label>
    </div>
    <div id="sc-status"></div>

    <div class="sc-grid" id="sc-grid"></div>
  </main>
</div>

<script>
const GOLD='#c9a24b', BG='#14100c', CREAM='#efe9dc', MUTE='#b7ad9c';
function status(t,c){var e=document.getElementById('sc-status');e.textContent=t;if(c)e.style.color=c;}

async function post(url,data){var fd=new FormData();for(var k in data)fd.append(k,data[k]);var r=await fetch('api/'+url,{method:'POST',body:fd});return r.json();}

// Canvas kart üreticisi (1080×1350 portre)
function drawCard(canvas, item){
  const W=1080,H=1350; canvas.width=W; canvas.height=H;
  const x=canvas.getContext('2d');
  // arka plan: koyu dikey degrade
  const g=x.createLinearGradient(0,0,0,H); g.addColorStop(0,'#1c1712'); g.addColorStop(1,'#0f0b08');
  x.fillStyle=g; x.fillRect(0,0,W,H);
  // ince çerçeve
  x.strokeStyle='rgba(201,162,75,.35)'; x.lineWidth=3; x.strokeRect(40,40,W-80,H-80);
  // üst marka
  x.fillStyle=GOLD; x.font='600 26px Georgia, serif'; x.textAlign='center';
  x.fillText('THE TELOS', W/2, 120);
  x.strokeStyle='rgba(201,162,75,.4)'; x.lineWidth=1; x.beginPath(); x.moveTo(W/2-40,140); x.lineTo(W/2+40,140); x.stroke();

  // ALINTI — sığana kadar fontu küçült
  const quote='“'+item.quote+'”';
  let fs=70; let lines=[];
  const maxW=W-200, maxBlockH=760;
  function wrap(fontSize){
    x.font='italic '+fontSize+'px Georgia, serif';
    const words=quote.split(' '); let ln=''; const out=[];
    for(const w of words){ const t=ln?ln+' '+w:w; if(x.measureText(t).width>maxW && ln){out.push(ln);ln=w;} else ln=t; }
    if(ln)out.push(ln); return out;
  }
  while(fs>30){ lines=wrap(fs); if(lines.length*(fs*1.32)<=maxBlockH) break; fs-=3; }
  x.font='italic '+fs+'px Georgia, serif'; x.fillStyle=CREAM; x.textAlign='center';
  const lh=fs*1.32; let y=H/2-(lines.length*lh)/2+fs/2-40;
  for(const ln of lines){ x.fillText(ln,W/2,y); y+=lh; }

  // atıf (yazar / kitap)
  y+=30;
  x.fillStyle=GOLD; x.font='600 30px Georgia, serif';
  const attr=(item.quote_kind==='quote'&&item.author)?item.author.toUpperCase():(item.author?item.author.toUpperCase():'');
  if(attr){ x.fillText(attr,W/2,y); y+=42; }
  if(item.book){ x.fillStyle=MUTE; x.font='italic 26px Georgia, serif';
    let b=item.book; if(x.measureText(b).width>maxW){while(x.measureText(b+'…').width>maxW&&b.length>4)b=b.slice(0,-1);b+='…';}
    x.fillText(b,W/2,y); }

  // alt: site + handle
  x.fillStyle=MUTE; x.font='500 24px -apple-system, system-ui, sans-serif';
  x.fillText(item.site||'thetelos.org', W/2, H-120);
  x.fillStyle='rgba(201,162,75,.75)'; x.font='500 22px -apple-system, system-ui, sans-serif';
  x.fillText(item.handle||'@thetelos', W/2, H-88);
}

function makeCard(item){
  const card=document.createElement('div'); card.className='sc-card';
  const cv=document.createElement('canvas'); card.appendChild(cv);
  const body=document.createElement('div'); body.className='sc-body';
  const meta=document.createElement('div'); meta.className='sc-meta';
  meta.innerHTML='<b>'+ (item.book||'') +'</b>'+(item.author?' · '+item.author:'')+(item.quote_kind==='insight'?' <span style="color:#c58af0">(özet cümlesi)</span>':' <span style="color:#00ab6b">(alıntı)</span>');
  const cap=document.createElement('textarea'); cap.className='sc-cap'; cap.value=item.caption;
  const acts=document.createElement('div'); acts.className='sc-actions';
  const dl=document.createElement('button'); dl.className='btn btn-primary'; dl.textContent='⬇ Görseli indir (JPG)';
  const cp=document.createElement('button'); cp.className='btn'; cp.textContent='📋 Caption kopyala';
  const open=document.createElement('a'); open.className='btn'; open.textContent='↗ Yazı'; open.href=item.url; open.target='_blank';
  acts.append(dl,cp,open);
  body.append(meta,cap,acts);
  card.append(body);
  // çiz
  drawCard(cv,item);
  dl.onclick=function(){ var a=document.createElement('a'); a.download=(item.book||'thetelos').replace(/[^a-z0-9]+/gi,'-').toLowerCase()+'.jpg'; a.href=cv.toDataURL('image/jpeg',0.92); a.click(); };
  cp.onclick=async function(){ try{ await navigator.clipboard.writeText(cap.value); cp.textContent='✓ Kopyalandı'; setTimeout(()=>cp.textContent='📋 Caption kopyala',1500);}catch(e){ cap.select(); document.execCommand('copy'); } };
  return card;
}

document.getElementById('sc-gen').onclick=async function(){
  status('Üretiliyor…','#e6c65a');
  const grid=document.getElementById('sc-grid');
  const j=await post('social-generate.php',{
    count:document.getElementById('sc-count').value,
    source:document.getElementById('sc-source').value,
    title:document.getElementById('sc-title').value.trim(),
    queue:'1'
  });
  if(!j.ok){status('Hata: '+(j.error||'?'),'#cc1818');return;}
  grid.innerHTML='';
  j.items.forEach(function(it){ grid.appendChild(makeCard(it)); });
  status('✅ '+j.count+' kart üretildi — indir + caption kopyala.','#00ab6b');
};
</script>
</body>
</html>
