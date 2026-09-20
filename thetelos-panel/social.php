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
<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500;1,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
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
      <label>Görsel
        <select id="sc-style">
          <option value="cover">Kitap kapağı</option>
          <option value="author">Yazar portresi</option>
          <option value="classic">Düz (renk)</option>
        </select>
      </label>
      <label>Format
        <select id="sc-format">
          <option value="single">Tek kart</option>
          <option value="carousel">Carousel (slaytlar)</option>
        </select>
      </label>
      <label>Elle başlık (opsiyonel)
        <input type="text" id="sc-title" placeholder="Örn: Meditations" style="min-width:220px">
      </label>
      <label style="flex-direction:row;align-items:center;gap:6px;align-self:end;padding-bottom:6px">
        <input type="checkbox" id="sc-hideshared" checked> Paylaşılanları gizle
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

const SERIF='"EB Garamond", Georgia, serif';
const SERIF2='"EB Garamond", Georgia, serif';
const SANS='"Inter", -apple-system, system-ui, sans-serif';
const LOGO_ICON_SVG='<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="13.5606 0 12.8789 12.8789"><path d="M20.0128 0C21.196 6.11323e-05 22.2541 0.256124 23.1876 0.768555C24.1204 1.28101 24.858 2.01586 25.3995 2.97363C25.941 3.93147 26.2119 5.07385 26.212 6.40137C26.212 7.72887 25.9584 8.87584 25.4503 9.8418C24.9419 10.8084 24.2248 11.5567 23.2999 12.0859C22.375 12.6145 21.2791 12.8789 20.0128 12.8789C18.7463 12.8789 17.6459 12.6146 16.7129 12.0859C15.7795 11.5562 15.0591 10.8046 14.5508 9.83008C14.042 8.85591 13.7881 7.71233 13.7881 6.40137C13.7882 5.09067 14.0592 3.95683 14.6006 2.99902C15.1417 2.04162 15.8837 1.30185 16.8253 0.78125C17.7663 0.260663 18.8294 0 20.0128 0ZM19.9004 3.4502C19.472 3.4502 19.1034 3.59546 18.794 3.88574C18.4835 4.17551 18.3282 4.55845 18.3282 5.03418C18.3282 5.46592 18.4647 5.83024 18.7374 6.12695C19.0103 6.4248 19.3832 6.64875 19.8555 6.79688L20.3204 6.93066C20.1874 7.42145 20.0073 7.8385 19.7784 8.18066C19.55 8.52284 19.2871 8.81249 18.9922 9.0498L19.2139 9.42969C19.9072 9.08804 20.4906 8.55978 20.9629 7.8457C21.4347 7.13165 21.6709 6.36579 21.671 5.54688C21.671 4.90803 21.5084 4.39749 21.1836 4.01855C20.8593 3.6391 20.4317 3.45024 19.9004 3.4502Z" fill="__C__"/></svg>'
let _logoCache={};
function svgImg(svg,color){ var k=color+':'+svg.length; if(_logoCache[k])return _logoCache[k]; var p=loadImg('data:image/svg+xml;charset=utf-8,'+encodeURIComponent(svg.replace(/__C__/g,color))); _logoCache[k]=p; return p; }

function loadImg(url){ return new Promise(function(res){ if(!url){res(null);return;} var im=new Image(); im.crossOrigin='anonymous'; im.onload=function(){res(im);}; im.onerror=function(){res(null);}; im.src=url; }); }
// Arka plan görselini seç: yazar portresi (sunucu proxy) → yoksa kitap kapağı → yoksa yok
async function pickBg(item, style){
  if(style==='author' && item.author){ const im=await loadImg('api/author-img.php?name='+encodeURIComponent(item.author)); if(im) return im; }
  if((style==='author'||style==='cover') && item.cover){ const im=await loadImg(item.cover); if(im) return im; }
  return null;
}
let _fontsReady=false;
async function ensureFonts(){ if(_fontsReady)return; try{ await Promise.all([
  document.fonts.load('500 60px "EB Garamond"'), document.fonts.load('600 60px "EB Garamond"'),
  document.fonts.load('italic 500 60px "EB Garamond"'), document.fonts.load('italic 600 40px "EB Garamond"'),
  document.fonts.load('600 24px "Inter"'), document.fonts.load('500 24px "Inter"')
]); await document.fonts.ready; }catch(e){} _fontsReady=true; }

// Kapağın baskın rengini + parlaklığını örnekle (küçük offscreen canvas)
function sampleColor(img){
  try{
    const s=36, cv=document.createElement('canvas'); cv.width=s; cv.height=s;
    const c=cv.getContext('2d'); c.drawImage(img,0,0,s,s);
    const d=c.getImageData(0,0,s,s).data; let r=0,g=0,b=0,n=0;
    for(let i=0;i<d.length;i+=4){ if(d[i+3]<128)continue; r+=d[i];g+=d[i+1];b+=d[i+2];n++; }
    if(!n)return {r:40,g:34,b:26,l:36};
    r=Math.round(r/n);g=Math.round(g/n);b=Math.round(b/n);
    const l=0.299*r+0.587*g+0.114*b;   // 0..255 parlaklık
    return {r:r,g:g,b:b,l:l};
  }catch(e){ return {r:40,g:34,b:26,l:36}; }   // CORS vb. → koyu varsayılan
}
// İki rengi t oranında karıştır → 'rgb(...)'
function mix(c,t,f){ const r=Math.round(c.r+(t[0]-c.r)*f),g=Math.round(c.g+(t[1]-c.g)*f),b=Math.round(c.b+(t[2]-c.b)*f); return 'rgb('+r+','+g+','+b+')'; }
function roundRect(x,rx,ry,w,h,rad){ x.beginPath(); x.moveTo(rx+rad,ry); x.arcTo(rx+w,ry,rx+w,ry+h,rad); x.arcTo(rx+w,ry+h,rx,ry+h,rad); x.arcTo(rx,ry+h,rx,ry,rad); x.arcTo(rx,ry,rx+w,ry,rad); x.closePath(); }

// Canvas kart üreticisi (1080×1350 portre) — async (font + kapak arka planı)
async function drawCard(canvas, item, style){
  await ensureFonts();
  const W=1080,H=1350; canvas.width=W; canvas.height=H;
  const x=canvas.getContext('2d'); x.textAlign='center';

  // ── Tema: varsayılan koyu; kapak modunda kapağın rengine göre uyarla ──
  let cover=null, light=false;
  let bgTop='#1c1712', bgBot='#0d0906';
  let cText=CREAM, cMuted=MUTE, cGold=GOLD, cFrame='rgba(201,162,75,.45)', cDiv='rgba(201,162,75,.5)';
  {
    const img=await pickBg(item, style);
    if(img){
      cover=img;
      const col=sampleColor(img);
      light=col.l>=145;                       // açık kapak mı?
      if(light){                              // AÇIK kapak → açık filtre + koyu metin
        bgTop=mix(col,[255,255,255],0.78); bgBot=mix(col,[255,255,255],0.55);
        cText='#241b10'; cMuted='rgba(36,27,16,.66)'; cGold='#8a6a1e';
        cFrame='rgba(90,70,30,.35)'; cDiv='rgba(90,70,30,.4)';
      } else {                                // KOYU kapak → koyu filtre + krem metin
        bgTop=mix(col,[14,10,6],0.55); bgBot=mix(col,[8,5,3],0.82);
        cText=CREAM; cMuted='rgba(236,231,220,.72)'; cGold=GOLD;
        cFrame='rgba(201,162,75,.45)'; cDiv='rgba(201,162,75,.5)';
      }
    }
  }

  // ── ARKA PLAN (renge uyarlı degrade) ──
  const base=x.createLinearGradient(0,0,0,H); base.addColorStop(0,bgTop); base.addColorStop(1,bgBot);
  x.fillStyle=base; x.fillRect(0,0,W,H);
  // vinyet (koyu temada belirgin, açık temada hafif)
  const vg=x.createRadialGradient(W/2,H/2,H*0.3,W/2,H/2,H*0.8);
  vg.addColorStop(0,'rgba(0,0,0,0)'); vg.addColorStop(1, light?'rgba(0,0,0,.10)':'rgba(0,0,0,.5)');
  x.fillStyle=vg; x.fillRect(0,0,W,H);
  // ince çerçeve
  x.strokeStyle=cFrame; x.lineWidth=2; x.strokeRect(46,46,W-92,H-92);

  // ── ÜST: ikon yok; çerçeveden ferah nefes payı ──
  const topMargin=132;                  // çerçeve (46) ile içerik arası boşluk
  let quoteTop=topMargin+20;            // kapak yoksa alıntı bloğu üstten başlar
  if(cover){
    const boxW=W*0.46, boxH=H*0.40;    // ~yarım ekran kutusu
    const sc=Math.min(boxW/cover.width, boxH/cover.height);
    const cw=cover.width*sc, ch=cover.height*sc;
    const cx=(W-cw)/2, cy=topMargin;
    x.save();                          // yumuşak gölge
    x.shadowColor='rgba(0,0,0,.5)'; x.shadowBlur=42; x.shadowOffsetY=20;
    roundRect(x,cx,cy,cw,ch,10); x.fillStyle='#000'; x.fill();
    x.restore();
    x.save(); roundRect(x,cx,cy,cw,ch,10); x.clip(); x.drawImage(cover,cx,cy,cw,ch); x.restore();
    x.strokeStyle=light?'rgba(0,0,0,.14)':'rgba(255,255,255,.14)'; x.lineWidth=1.5; roundRect(x,cx,cy,cw,ch,10); x.stroke();
    quoteTop=cy+ch+52;
  }

  // ── ALT BLOK (SABİT konumlar → asla üst üste binmez, ferah) ──
  // En altta: thetelos tırnak ikonu (çerçeveden ferah boşlukla). Site yazısı YOK.
  const icon=await svgImg(LOGO_ICON_SVG,cGold);
  const icoW=50, icoH=icoW, icoX=(W-icoW)/2, icoY=H-152;   // ikon en altta, çizgiden ~52px yukarıda
  const attr=item.author?item.author.toUpperCase():'';
  const yBook=H-198, yAuthor=item.book?H-244:H-204;
  const attrTop=attr?yAuthor:(item.book?yBook:(H-190));   // atıf bloğunun en üstü

  // ── ALINTI (Playfair italic, logo/kapak ile atıf arasına ortalanır) ──
  const quote='“'+item.quote+'”';
  const maxW=W-210, quoteBottom=attrTop-46, maxBlockH=Math.max(200, quoteBottom-quoteTop);
  let fs=cover?58:74, lines=[];
  function wrap(fontSize){ x.font='500 '+fontSize+'px '+SERIF; const words=quote.split(' '); let ln='',out=[];
    for(const w of words){ const t=ln?ln+' '+w:w; if(x.measureText(t).width>maxW&&ln){out.push(ln);ln=w;}else ln=t; } if(ln)out.push(ln); return out; }
  while(fs>28){ lines=wrap(fs); if(lines.length*(fs*1.34)<=maxBlockH) break; fs-=3; }
  x.font='500 '+fs+'px '+SERIF; x.fillStyle=cText;
  const lh=fs*1.34, blockH=lines.length*lh;
  let y=quoteTop+(maxBlockH-blockH)/2+fs*0.74;          // kalan alanda dikey ortala
  for(const ln of lines){ x.fillText(ln,W/2,y); y+=lh; }

  // ── ATIF (yazar + kitap, sabit konum) ──
  if(attr){ x.fillStyle=cGold; x.font='600 32px '+SERIF; x.letterSpacing='4px'; x.fillText(attr,W/2,yAuthor); x.letterSpacing='0px'; }
  { let b=cleanBook(item); if(b){ x.fillStyle=cMuted; x.font='500 30px '+SERIF2;
    if(x.measureText(b).width>maxW){while(x.measureText(b+'…').width>maxW&&b.length>4)b=b.slice(0,-1);b+='…';} x.fillText(b,W/2,yBook); } }

  // ── ALT: thetelos tırnak ikonu (site yazısı yok) ──
  if(icon){ x.drawImage(icon, icoX, icoY, icoW, icoH); }
}

// Twitter uzunluğu: URL'ler t.co'da her zaman 23 karakter sayılır
function twLen(s){ return [...String(s).replace(/https?:\/\/\S+/g, 'x'.repeat(23))].length; }
// Alıntı + kısa atıf (— yazar) + link + etiket → GARANTİLİ ≤280 tweet metni
function composeTweet(item){
  const url=item.url||''; const author=item.author||''; const tags='#books #thetelos';
  const attrib = author ? ('— '+author) : '';
  const tail = (attrib?('\n\n'+attrib):'') + '\n\n' + url + ' ' + tags;
  let qbudget = 280 - twLen(tail) - 2;                 // 2 = tırnaklar
  let q = item.quote||'';
  if([...q].length > qbudget) q = [...q].slice(0, Math.max(20, qbudget-1)).join('') + '…';
  return '“'+q+'”'+tail;
}

// ── CAROUSEL (slayt) desteği ─────────────────────────────────────────────
function computeTheme(bgImg){
  const t={bgTop:'#1c1712',bgBot:'#0d0906',cText:CREAM,cMuted:MUTE,cGold:GOLD,cFrame:'rgba(201,162,75,.45)',light:false,bgImg:bgImg||null};
  if(bgImg){ const col=sampleColor(bgImg); t.light=col.l>=145;
    if(t.light){ t.bgTop=mix(col,[255,255,255],0.80); t.bgBot=mix(col,[255,255,255],0.60); t.cText='#241b10'; t.cMuted='rgba(36,27,16,.66)'; t.cGold='#8a6a1e'; t.cFrame='rgba(90,70,30,.35)'; }
    else { t.bgTop=mix(col,[14,10,6],0.55); t.bgBot=mix(col,[8,5,3],0.82); t.cMuted='rgba(236,231,220,.72)'; }
  }
  return t;
}
// Kitap başlığında zaten "– Yazar" varsa onu ayıkla (yazar ayrıca gösteriliyor → tekrar olmasın)
function cleanBook(item){ let b=String(item.book||''); const a=String(item.author||'');
  if(a){ const esc=a.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'); b=b.replace(new RegExp('\\s*[–—-]\\s*'+esc+'\\s*$','i'),''); }
  return b.trim(); }
function wrapText(x,text,font,maxW){ x.font=font; const words=String(text).split(' '); let ln='',out=[];
  for(const w of words){ const tt=ln?ln+' '+w:w; if(x.measureText(tt).width>maxW&&ln){out.push(ln);ln=w;}else ln=tt; } if(ln)out.push(ln); return out; }

async function drawSlide(cv,item,th,slide,n,total){
  await ensureFonts();
  const W=1080,H=1350; cv.width=W; cv.height=H; const x=cv.getContext('2d'); x.textAlign='center';
  const isCover=slide.kind==='cover', hasImg=isCover&&th.bgImg;
  if(hasImg){
    const img=th.bgImg, r=Math.max(W/img.width,H/img.height), iw=img.width*r, ih=img.height*r;
    x.drawImage(img,(W-iw)/2,(H-ih)/2,iw,ih);
    const ov=x.createLinearGradient(0,0,0,H); ov.addColorStop(0,'rgba(8,6,4,.72)'); ov.addColorStop(.5,'rgba(8,6,4,.55)'); ov.addColorStop(1,'rgba(8,6,4,.90)');
    x.fillStyle=ov; x.fillRect(0,0,W,H);
  } else {
    const g=x.createLinearGradient(0,0,0,H); g.addColorStop(0,th.bgTop); g.addColorStop(1,th.bgBot); x.fillStyle=g; x.fillRect(0,0,W,H);
    const vg=x.createRadialGradient(W/2,H/2,H*0.3,W/2,H/2,H*0.8); vg.addColorStop(0,'rgba(0,0,0,0)'); vg.addColorStop(1, th.light?'rgba(0,0,0,.10)':'rgba(0,0,0,.5)'); x.fillStyle=vg; x.fillRect(0,0,W,H);
  }
  const tCol=hasImg?'#efe9dc':th.cText, mCol=hasImg?'rgba(236,231,220,.8)':th.cMuted, gCol=hasImg?'#e8c877':th.cGold;
  x.strokeStyle=hasImg?'rgba(232,200,120,.5)':th.cFrame; x.lineWidth=2; x.strokeRect(46,46,W-92,H-92);
  x.textAlign='right'; x.fillStyle=mCol; x.font='600 22px '+SANS; x.letterSpacing='2px'; x.fillText(n+' / '+total, W-70, 98); x.letterSpacing='0px'; x.textAlign='center';

  if(isCover){
    const quote='“'+item.quote+'”'; let fs=76,lines=[]; const maxW=W-200, maxBlockH=760;
    while(fs>34){ lines=wrapText(x,quote,'500 '+fs+'px '+SERIF,maxW); if(lines.length*(fs*1.32)<=maxBlockH)break; fs-=3; }
    x.font='500 '+fs+'px '+SERIF; x.fillStyle=tCol;
    if(hasImg){ x.shadowColor='rgba(0,0,0,.55)'; x.shadowBlur=16; }
    const lh=fs*1.32; let y=H/2-(lines.length*lh)/2+fs*0.7-24; for(const ln of lines){ x.fillText(ln,W/2,y); y+=lh; }
    x.shadowColor='transparent'; x.shadowBlur=0; y+=30;
    if(item.author){ x.fillStyle=gCol; x.font='600 30px '+SERIF; x.letterSpacing='4px'; x.fillText(item.author.toUpperCase(),W/2,y); x.letterSpacing='0px'; y+=44; }
    { let b=cleanBook(item); if(b){ x.fillStyle=mCol; x.font='500 28px '+SERIF2; if(x.measureText(b).width>maxW){while(x.measureText(b+'…').width>maxW&&b.length>4)b=b.slice(0,-1);b+='…';} x.fillText(b,W/2,y); } }
  } else if(slide.kind==='point'){
    let fs=58,lines=[]; const maxW=W-220, maxBlockH=800;
    while(fs>30){ lines=wrapText(x,slide.text||'','500 '+fs+'px '+SERIF,maxW); if(lines.length*(fs*1.4)<=maxBlockH)break; fs-=3; }
    x.font='500 '+fs+'px '+SERIF; x.fillStyle=tCol;
    const lh=fs*1.4, blockH=lines.length*lh; let y=(H-blockH)/2+fs*0.72; for(const ln of lines){ x.fillText(ln,W/2,y); y+=lh; }
    x.fillStyle=mCol; x.font='500 24px '+SERIF2; let f=cleanBook(item)+(item.author?' · '+item.author:'');
    if(x.measureText(f).width>maxW){while(x.measureText(f+'…').width>maxW&&f.length>4)f=f.slice(0,-1);f+='…';} x.fillText(f,W/2,H-152);
  } else {
    // KAPANIŞ — siteye yönlendiren tek ve net çağrı
    let cy=H*0.37;
    x.fillStyle=gCol; x.font='600 26px '+SANS; x.letterSpacing='5px'; x.fillText('READ THE FULL SUMMARY', W/2, cy); x.letterSpacing='0px';
    cy+=96; x.fillStyle=tCol; x.font='600 66px '+SERIF; x.fillText('thetelos.org', W/2, cy);
    cy+=76; x.fillStyle=mCol; x.font='500 30px '+SERIF2; let b=cleanBook(item); const mw=W-240;
    if(x.measureText(b).width>mw){while(x.measureText(b+'…').width>mw&&b.length>4)b=b.slice(0,-1);b+='…';}
    if(b) x.fillText(b, W/2, cy);
    cy+=56; x.fillStyle=mCol; x.font='500 24px '+SANS; x.letterSpacing='1px'; x.fillText('@thetelos', W/2, cy); x.letterSpacing='0px';
  }
  const icon=await svgImg(LOGO_ICON_SVG, gCol);
  if(icon){ const s=46; x.drawImage(icon,(W-s)/2,H-118,s,s); }
}

async function makeCarouselCard(item, style){
  const card=document.createElement('div'); card.className='sc-card';
  const strip=document.createElement('div'); strip.style.cssText='display:flex;gap:8px;overflow-x:auto;padding:8px;background:#14100c';
  const body=document.createElement('div'); body.className='sc-body';
  const meta=document.createElement('div'); meta.className='sc-meta';
  const sharedBadge=item.shared?' <span style="color:#e0a03a;font-weight:600">✓ paylaşıldı</span>':'';
  meta.innerHTML='<b>'+(item.book||'')+'</b>'+(item.author?' · '+item.author:'')+' <span style="color:#5aa0ff">(carousel)</span>'+sharedBadge;
  const cap=document.createElement('textarea'); cap.className='sc-cap'; cap.value=item.caption;
  const acts=document.createElement('div'); acts.className='sc-actions';
  const dl=document.createElement('button'); dl.className='btn btn-primary'; dl.textContent='⬇ Slaytları indir (ZIP)';
  const cp=document.createElement('button'); cp.className='btn'; cp.textContent='📋 Caption kopyala';
  const open=document.createElement('a'); open.className='btn'; open.textContent='↗ Yazı'; open.href=item.url; open.target='_blank';
  acts.append(dl,cp,open); body.append(meta,cap,acts); card.append(strip,body);
  const bgImg=await pickBg(item, style);
  const th=computeTheme(bgImg);
  const pts=(item.slides&&item.slides.length)?item.slides:[];
  const defs=[{kind:'cover'}].concat(pts.map(t=>({kind:'point',text:t}))).concat([{kind:'cta'}]);
  const total=defs.length, canvases=[];
  for(let i=0;i<defs.length;i++){ const cv=document.createElement('canvas'); cv.style.cssText='height:230px;width:auto;flex:0 0 auto;border-radius:6px'; await drawSlide(cv,item,th,defs[i],i+1,total); strip.appendChild(cv); canvases.push(cv); }
  dl.onclick=async function(){
    if(typeof JSZip==='undefined'){ alert('ZIP kütüphanesi yüklenemedi (internet?).'); return; }
    const zip=new JSZip(), base=(item.book||'thetelos').replace(/[^a-z0-9]+/gi,'-').toLowerCase();
    for(let i=0;i<canvases.length;i++){ const d=canvases[i].toDataURL('image/jpeg',0.92).split(',')[1]; zip.file(base+'-'+String(i+1).padStart(2,'0')+'.jpg', d, {base64:true}); }
    const blob=await zip.generateAsync({type:'blob'}); const a=document.createElement('a'); a.download=base+'-carousel.zip'; a.href=URL.createObjectURL(blob); a.click();
  };
  cp.onclick=async function(){ try{ await navigator.clipboard.writeText(cap.value); cp.textContent='✓ Kopyalandı'; setTimeout(()=>cp.textContent='📋 Caption kopyala',1500);}catch(e){ cap.select(); document.execCommand('copy'); } };
  return card;
}

async function makeCard(item, style){
  const card=document.createElement('div'); card.className='sc-card';
  const cv=document.createElement('canvas'); card.appendChild(cv);
  const body=document.createElement('div'); body.className='sc-body';
  const meta=document.createElement('div'); meta.className='sc-meta';
  const sharedBadge=item.shared?' <span class="sc-shared" style="color:#e0a03a;font-weight:600">✓ daha önce paylaşıldı'+(item.shared_at?' ('+item.shared_at+')':'')+'</span>':'';
  meta.innerHTML='<b>'+ (item.book||'') +'</b>'+(item.author?' · '+item.author:'')+(item.quote_kind==='insight'?' <span style="color:#c58af0">(özet cümlesi)</span>':' <span style="color:#00ab6b">(alıntı)</span>')+sharedBadge;
  if(item.shared) card.style.opacity='0.72';
  const cap=document.createElement('textarea'); cap.className='sc-cap'; cap.value=item.caption;
  const twbox=document.createElement('textarea'); twbox.className='sc-cap'; twbox.value=composeTweet(item); twbox.style.minHeight='70px';
  const twlbl=document.createElement('div'); twlbl.className='sc-meta'; twlbl.innerHTML='🐦 Tweet metni <span class="twc" style="color:var(--muted)"></span>';
  const acts=document.createElement('div'); acts.className='sc-actions';
  const dl=document.createElement('button'); dl.className='btn btn-primary'; dl.textContent='⬇ Görseli indir (JPG)';
  const cp=document.createElement('button'); cp.className='btn'; cp.textContent='📋 Caption kopyala';
  const tw=document.createElement('button'); tw.className='btn'; tw.textContent='🐦 Tweet at'; tw.style.borderColor='#1d9bf0'; tw.style.color='#1d9bf0';
  const open=document.createElement('a'); open.className='btn'; open.textContent='↗ Yazı'; open.href=item.url; open.target='_blank';
  acts.append(dl,cp,tw,open);
  function updTwc(){ const n=twLen(twbox.value); const el=twlbl.querySelector('.twc'); el.textContent='('+n+'/280)'; el.style.color=n>280?'#cc4444':'var(--muted)'; }
  twbox.addEventListener('input',updTwc);
  body.append(meta,cap,twlbl,twbox,acts);
  card.append(body);
  await drawCard(cv,item,style);
  updTwc();
  dl.onclick=function(){ var a=document.createElement('a'); a.download=(item.book||'thetelos').replace(/[^a-z0-9]+/gi,'-').toLowerCase()+'.jpg'; a.href=cv.toDataURL('image/jpeg',0.92); a.click(); };
  cp.onclick=async function(){ try{ await navigator.clipboard.writeText(cap.value); cp.textContent='✓ Kopyalandı'; setTimeout(()=>cp.textContent='📋 Caption kopyala',1500);}catch(e){ cap.select(); document.execCommand('copy'); } };
  tw.onclick=async function(){
    // Daha önce paylaşıldıysa uyar (boşa para harcama)
    if(item.shared && !confirm('⚠️ Bu içeriği DAHA ÖNCE'+(item.shared_at?' ('+item.shared_at+')':'')+' paylaştın. Tekrar atmak X\'te tekrar ÜCRETLENDİRİLİR. Yine de atmak istiyor musun?')) return;
    // Paylaşırken 280'i aşıyorsa OTOMATİK olarak uygun formata kısalt
    let text=twbox.value;
    if(twLen(text)>280){ text=composeTweet(item); twbox.value=text; updTwc(); }
    if(!item.shared && !confirm('Bu kartı görseliyle birlikte X\'e (Twitter) göndermek üzeresin. Devam?')) return;
    tw.disabled=true; const old=tw.textContent; tw.textContent='⏳ Gönderiliyor…';
    let img=''; try{ img=cv.toDataURL('image/jpeg',0.92); }catch(e){ img=''; }
    async function send(withImg){ return post('twitter.php',{action:'post',text:text,image:withImg?img:'',post_id:item.post_id||''}); }
    try{
      let r=await send(true);
      // Görsel yükleme ücret istiyorsa → görselsiz (metin+link) tekrar dene
      if(!r.ok && r.step==='media' && r.paid){
        tw.textContent=old; tw.disabled=false;
        if(confirm('X görsel yüklemeyi ücretli tutuyor (Payment Required). Görselsiz — sadece metin + link — tweet atmayı denememi ister misin? (Bu genelde ücretsiz çalışır)')){
          tw.disabled=true; tw.textContent='⏳ Metin gönderiliyor…';
          r=await send(false);
        } else { return; }
      }
      if(r.ok){
        tw.textContent='✓ Paylaşıldı'; tw.style.color='#00ab6b'; tw.style.borderColor='#00ab6b';
        item.shared=true;   // kartı işaretle (bu oturumda tekrar atmayı önler)
        if(!meta.querySelector('.sc-shared')){ meta.insertAdjacentHTML('beforeend',' <span class="sc-shared" style="color:#e0a03a;font-weight:600">✓ paylaşıldı</span>'); }
        if(r.url)window.open(r.url,'_blank');
      }
      else{ tw.textContent=old; tw.disabled=false; alert('Tweet hatası ('+(r.code||'?')+'): '+(r.error||'?')); }
    }catch(e){ tw.textContent=old; tw.disabled=false; alert('Bağlantı hatası: '+e.message); }
  };
  return card;
}

document.getElementById('sc-gen').onclick=async function(){
  const format=document.getElementById('sc-format').value;
  status(format==='carousel'?'AI slaytları hazırlanıyor… (ilk seferde biraz sürebilir)':'Üretiliyor…','#e6c65a');
  const grid=document.getElementById('sc-grid');
  const j=await post('social-generate.php',{
    count:document.getElementById('sc-count').value,
    source:document.getElementById('sc-source').value,
    title:document.getElementById('sc-title').value.trim(),
    exclude_shared:document.getElementById('sc-hideshared').checked?'1':'0',
    ai_slides:format==='carousel'?'1':'0',
    queue:'1'
  });
  if(!j.ok){status('Hata: '+(j.error||'?'),'#cc1818');return;}
  grid.innerHTML='';
  const style=document.getElementById('sc-style').value;
  status('Kartlar çiziliyor…','#e6c65a');
  for(const it of j.items){ grid.appendChild(format==='carousel' ? await makeCarouselCard(it,style) : await makeCard(it,style)); }
  status('✅ '+j.count+(format==='carousel'?' carousel':' kart')+' üretildi.','#00ab6b');
};
</script>
</body>
</html>
