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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&family=Cormorant+Garamond:ital,wght@0,500;1,500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
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
      <label>Stil
        <select id="sc-style">
          <option value="cover">Kapak arka plan</option>
          <option value="classic">Klasik (koyu)</option>
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

const SERIF='"Playfair Display", Georgia, serif';
const SERIF2='"Cormorant Garamond", Georgia, serif';
const SANS='"Inter", -apple-system, system-ui, sans-serif';

function loadImg(url){ return new Promise(function(res){ if(!url){res(null);return;} var im=new Image(); im.crossOrigin='anonymous'; im.onload=function(){res(im);}; im.onerror=function(){res(null);}; im.src=url; }); }
let _fontsReady=false;
async function ensureFonts(){ if(_fontsReady)return; try{ await Promise.all([
  document.fonts.load('600 60px "Playfair Display"'), document.fonts.load('500 60px "Playfair Display"'),
  document.fonts.load('italic 60px "Playfair Display"'), document.fonts.load('italic 40px "Cormorant Garamond"'),
  document.fonts.load('600 24px "Inter"'), document.fonts.load('500 24px "Inter"')
]); await document.fonts.ready; }catch(e){} _fontsReady=true; }

// Canvas kart üreticisi (1080×1350 portre) — async (font + kapak arka planı)
async function drawCard(canvas, item, style){
  await ensureFonts();
  const W=1080,H=1350; canvas.width=W; canvas.height=H;
  const x=canvas.getContext('2d'); x.textAlign='center';

  // ── ARKA PLAN ──
  const base=x.createLinearGradient(0,0,0,H); base.addColorStop(0,'#1c1712'); base.addColorStop(1,'#0d0906');
  x.fillStyle=base; x.fillRect(0,0,W,H);
  let overImg=false;
  if(style==='cover'){
    const img=await loadImg(item.cover);
    if(img){ overImg=true;
      // object-fit: cover
      const r=Math.max(W/img.width,H/img.height), iw=img.width*r, ih=img.height*r;
      x.drawImage(img,(W-iw)/2,(H-ih)/2,iw,ih);
      // okunurluk için koyu degrade örtü
      const ov=x.createLinearGradient(0,0,0,H);
      ov.addColorStop(0,'rgba(8,6,4,.78)'); ov.addColorStop(.45,'rgba(8,6,4,.62)'); ov.addColorStop(1,'rgba(8,6,4,.90)');
      x.fillStyle=ov; x.fillRect(0,0,W,H);
    }
  }
  // vinyet
  const vg=x.createRadialGradient(W/2,H/2,H*0.3,W/2,H/2,H*0.75);
  vg.addColorStop(0,'rgba(0,0,0,0)'); vg.addColorStop(1,'rgba(0,0,0,.45)');
  x.fillStyle=vg; x.fillRect(0,0,W,H);
  // ince altın çerçeve
  x.strokeStyle='rgba(201,162,75,.45)'; x.lineWidth=2; x.strokeRect(46,46,W-92,H-92);

  // ── ÜST MARKA ──
  x.fillStyle=GOLD; x.font='600 24px '+SANS; x.letterSpacing='6px';
  x.fillText('THE TELOS', W/2, 122); x.letterSpacing='0px';
  x.strokeStyle='rgba(201,162,75,.5)'; x.lineWidth=1; x.beginPath(); x.moveTo(W/2-34,142); x.lineTo(W/2+34,142); x.stroke();

  // ── ALINTI (Playfair italic, sığana kadar küçült) ──
  const quote='“'+item.quote+'”';
  let fs=76, lines=[]; const maxW=W-210, maxBlockH=740;
  function wrap(fontSize){ x.font='500 italic '+fontSize+'px '+SERIF; const words=quote.split(' '); let ln='',out=[];
    for(const w of words){ const t=ln?ln+' '+w:w; if(x.measureText(t).width>maxW&&ln){out.push(ln);ln=w;}else ln=t; } if(ln)out.push(ln); return out; }
  while(fs>32){ lines=wrap(fs); if(lines.length*(fs*1.34)<=maxBlockH) break; fs-=3; }
  x.font='500 italic '+fs+'px '+SERIF; x.fillStyle=CREAM;
  if(overImg){ x.shadowColor='rgba(0,0,0,.6)'; x.shadowBlur=16; x.shadowOffsetY=2; }
  const lh=fs*1.34; let y=H/2-(lines.length*lh)/2+fs/2-30;
  for(const ln of lines){ x.fillText(ln,W/2,y); y+=lh; }
  x.shadowColor='transparent'; x.shadowBlur=0; x.shadowOffsetY=0;

  // ── ATIF ──
  y+=34;
  const attr=item.author?item.author.toUpperCase():'';
  if(attr){ x.fillStyle=GOLD; x.font='600 28px '+SANS; x.letterSpacing='3px'; x.fillText(attr,W/2,y); x.letterSpacing='0px'; y+=46; }
  if(item.book){ x.fillStyle=overImg?'rgba(236,231,220,.85)':MUTE; x.font='500 italic 30px '+SERIF2;
    let b=item.book; if(x.measureText(b).width>maxW){while(x.measureText(b+'…').width>maxW&&b.length>4)b=b.slice(0,-1);b+='…';} x.fillText(b,W/2,y); }

  // ── ALT: site + handle ──
  x.fillStyle=overImg?'rgba(236,231,220,.7)':MUTE; x.font='500 22px '+SANS; x.letterSpacing='1px';
  x.fillText(item.site||'thetelos.org', W/2, H-118);
  x.fillStyle='rgba(201,162,75,.8)'; x.font='600 22px '+SANS;
  x.fillText(item.handle||'@thetelos', W/2, H-86); x.letterSpacing='0px';
}

async function makeCard(item, style){
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
  await drawCard(cv,item,style);
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
  const style=document.getElementById('sc-style').value;
  status('Kartlar çiziliyor…','#e6c65a');
  for(const it of j.items){ grid.appendChild(await makeCard(it,style)); }
  status('✅ '+j.count+' kart üretildi — indir + caption kopyala.','#00ab6b');
};
</script>
</body>
</html>
