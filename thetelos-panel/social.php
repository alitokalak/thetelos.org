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

const SERIF='"EB Garamond", Georgia, serif';
const SERIF2='"EB Garamond", Georgia, serif';
const SANS='"Inter", -apple-system, system-ui, sans-serif';
const LOGO_WORD_SVG='<svg xmlns="http://www.w3.org/2000/svg" width="40" height="9" viewBox="0 16.2 40 8.4"><path d="M2.28713 24.394C1.80837 24.394 1.43628 24.2771 1.17085 24.0409C0.905426 23.8071 0.773953 23.432 0.773953 22.9181V19.1553H0V19.0457L0.267907 19.0019C0.498605 18.9507 0.699535 18.8777 0.868217 18.7827C1.0369 18.6877 1.2031 18.5586 1.36682 18.3979L2.28465 17.3969H2.39628L2.36155 18.9386H3.61674V19.1577H2.34915V23.1178C2.34915 23.3736 2.4062 23.5684 2.52279 23.6999C2.63938 23.8314 2.78574 23.8972 2.96434 23.8972C3.12062 23.8972 3.25953 23.8582 3.3786 23.7827C3.49767 23.7048 3.61674 23.6001 3.73829 23.4685L3.84992 23.5781C3.70109 23.8266 3.49519 24.0263 3.23473 24.1724C2.97426 24.3185 2.65674 24.3916 2.28217 24.3916L2.28713 24.394Z" fill="__C__"/><path d="M4.29171 24.2283V24.1187L4.42566 24.0749C4.69357 23.9872 4.83 23.8045 4.83 23.5245V17.6476C4.83 17.4942 4.80271 17.3797 4.75062 17.3067C4.69853 17.2336 4.59682 17.1776 4.44798 17.1411L4.2793 17.0972V16.9876L6.2514 16.5151L6.43 16.615L6.38535 18.1664V19.5424C6.64581 19.3306 6.92861 19.1479 7.23124 18.9969C7.53388 18.8459 7.86876 18.7704 8.23341 18.7704C8.71961 18.7704 9.10659 18.9068 9.39434 19.1771C9.68209 19.4475 9.82597 19.8664 9.82597 20.4314V23.5342C9.82597 23.6803 9.85574 23.7972 9.91527 23.8873C9.97481 23.975 10.0789 24.0408 10.2278 24.0846L10.3171 24.1163V24.2259H7.69512V24.1163L7.82907 24.0846C8.09698 23.9969 8.23341 23.8094 8.23341 23.5245V20.0685C8.23341 19.7762 8.1838 19.5741 8.08209 19.4645C7.98039 19.3549 7.80426 19.2989 7.54876 19.2989C7.38504 19.2989 7.20395 19.3306 7.01047 19.3914C6.81698 19.4548 6.61853 19.5619 6.4176 19.7154V23.5439C6.4176 23.8289 6.55155 24.014 6.82194 24.0944L6.91124 24.1163V24.2259H4.28923L4.29171 24.2283Z" fill="__C__"/><path d="M13.6286 18.7704C14.1148 18.7704 14.5291 18.8678 14.8739 19.0626C15.2187 19.2575 15.4817 19.5205 15.6652 19.8542C15.8488 20.1878 15.9406 20.5678 15.9406 20.994C15.9406 21.0744 15.9356 21.1572 15.9307 21.2424C15.9232 21.3276 15.9083 21.4031 15.886 21.4689H12.5471C12.5545 22.2604 12.6984 22.8303 12.9787 23.181C13.259 23.5293 13.6758 23.7047 14.229 23.7047C14.6035 23.7047 14.9062 23.6462 15.1369 23.5293C15.3676 23.4124 15.5859 23.2395 15.7868 23.013L15.8984 23.1129C15.6677 23.5171 15.3576 23.8313 14.9731 24.0603C14.5886 24.2868 14.1272 24.4012 13.589 24.4012C13.0507 24.4012 12.5868 24.2892 12.1775 24.0651C11.7657 23.8411 11.4457 23.5196 11.2125 23.1031C10.9818 22.6842 10.8652 22.1874 10.8652 21.6077C10.8652 21.0281 10.9992 20.4947 11.2696 20.0734C11.5375 19.652 11.8848 19.3305 12.3065 19.1114C12.7282 18.8922 13.1722 18.7801 13.6336 18.7801L13.6286 18.7704ZM13.5939 18.992C13.3781 18.992 13.1921 19.0553 13.0383 19.1844C12.8845 19.3135 12.7654 19.54 12.6786 19.8664C12.5917 20.1927 12.5446 20.653 12.5397 21.2473H14.4572C14.5316 20.4484 14.5018 19.8712 14.3679 19.5205C14.2339 19.1674 13.9759 18.992 13.5939 18.992Z" fill="__C__"/><path d="M18.6817 24.394C18.2029 24.394 17.8308 24.2771 17.5654 24.0409C17.3 23.8071 17.1685 23.432 17.1685 22.9181V19.1553H16.3945V19.0457L16.6624 19.0019C16.8931 18.9507 17.0941 18.8777 17.2627 18.7827C17.4314 18.6877 17.5976 18.5586 17.7614 18.3979L18.6792 17.3969H18.7908L18.7561 18.9386H20.0113V19.1577H18.7437V23.1178C18.7437 23.3736 18.8007 23.5684 18.9173 23.6999C19.0339 23.8314 19.1803 23.8972 19.3589 23.8972C19.5152 23.8972 19.6541 23.8582 19.7731 23.7827C19.8922 23.7048 20.0113 23.6001 20.1328 23.4685L20.2445 23.5781C20.0956 23.8266 19.8897 24.0263 19.6293 24.1724C19.3688 24.3185 19.0513 24.3916 18.6767 24.3916L18.6817 24.394Z" fill="__C__"/><path d="M23.4343 18.7704C23.9205 18.7704 24.3348 18.8678 24.6796 19.0626C25.0244 19.2575 25.2873 19.5205 25.4709 19.8542C25.6545 20.1878 25.7462 20.5678 25.7462 20.994C25.7462 21.0744 25.7413 21.1572 25.7363 21.2424C25.7289 21.3276 25.714 21.4031 25.6917 21.4689H22.3528C22.3602 22.2604 22.5041 22.8303 22.7844 23.181C23.0647 23.5293 23.4814 23.7047 24.0346 23.7047C24.4092 23.7047 24.7118 23.6462 24.9425 23.5293C25.1732 23.4124 25.3915 23.2395 25.5924 23.013L25.7041 23.1129C25.4734 23.5171 25.1633 23.8313 24.7788 24.0603C24.3943 24.2868 23.9329 24.4012 23.3946 24.4012C22.8563 24.4012 22.3924 24.2892 21.9831 24.0651C21.5714 23.8411 21.2514 23.5196 21.0182 23.1031C20.7875 22.6842 20.6709 22.1874 20.6709 21.6077C20.6709 21.0281 20.8049 20.4947 21.0752 20.0734C21.3431 19.652 21.6904 19.3305 22.1121 19.1114C22.5338 18.8922 22.9779 18.7801 23.4393 18.7801L23.4343 18.7704ZM23.3996 18.992C23.1838 18.992 22.9977 19.0553 22.8439 19.1844C22.6901 19.3135 22.5711 19.54 22.4842 19.8664C22.3974 20.1927 22.3503 20.653 22.3453 21.2473H24.2628C24.3373 20.4484 24.3075 19.8712 24.1735 19.5205C24.0396 19.1674 23.7816 18.992 23.3996 18.992Z" fill="__C__"/><path d="M26.213 24.2283V24.1187L26.347 24.0749C26.6149 23.9872 26.7513 23.8045 26.7513 23.5245V17.6379C26.7513 17.4918 26.7215 17.3797 26.662 17.3018C26.6025 17.2239 26.4983 17.1679 26.3494 17.1313L26.2031 17.0997V16.9901L28.2546 16.5176L28.3885 16.6174L28.3439 18.1591V21.9219C28.3439 22.1947 28.3439 22.4626 28.3488 22.7305C28.3513 22.9984 28.3538 23.2639 28.3538 23.5293C28.3538 23.6755 28.3835 23.7948 28.4431 23.8873C28.5026 23.9799 28.6043 24.0481 28.7457 24.0919L28.8921 24.1236V24.2332H26.213V24.2283Z" fill="__C__"/><path d="M32.278 24.3939C31.7099 24.3939 31.2163 24.2795 30.7995 24.0481C30.3803 23.8167 30.0578 23.4879 29.8296 23.0641C29.6014 22.6379 29.4873 22.1411 29.4873 21.5688C29.4873 20.9964 29.6088 20.502 29.8519 20.0831C30.095 19.6642 30.4274 19.3427 30.8491 19.1138C31.2708 18.8873 31.7471 18.7728 32.278 18.7728C32.8088 18.7728 33.2826 18.8849 33.7018 19.1089C34.1211 19.333 34.451 19.6545 34.6941 20.0709C34.9372 20.4898 35.0587 20.9891 35.0587 21.5663C35.0587 22.1435 34.9446 22.6477 34.7164 23.069C34.4882 23.4904 34.1682 23.8167 33.7514 24.0481C33.3372 24.2795 32.846 24.3939 32.278 24.3939ZM32.278 24.1747C32.5384 24.1747 32.7493 24.1017 32.9056 23.9555C33.0618 23.8094 33.1759 23.5488 33.2479 23.1737C33.3198 22.7987 33.3545 22.2702 33.3545 21.5882C33.3545 20.9063 33.3198 20.3656 33.2479 19.993C33.1759 19.6179 33.0618 19.3598 32.9056 19.2112C32.7493 19.0651 32.5384 18.992 32.278 18.992C32.0175 18.992 31.8042 19.0651 31.6454 19.2112C31.4842 19.3573 31.37 19.6179 31.2981 19.993C31.2262 20.3681 31.1914 20.899 31.1914 21.5882C31.1914 22.2775 31.2262 22.7987 31.2981 23.1737C31.37 23.5488 31.4842 23.8094 31.6454 23.9555C31.8066 24.1017 32.0175 24.1747 32.278 24.1747Z" fill="__C__"/><path d="M37.5442 24.3939C37.1771 24.3939 36.8496 24.3525 36.5569 24.2673C36.2667 24.182 35.9963 24.0773 35.7507 23.9531L35.7631 22.6428H35.8747L36.2568 23.3028C36.4205 23.5975 36.6016 23.8143 36.8 23.958C36.9985 24.1017 37.249 24.1723 37.5566 24.1723C37.9163 24.1723 38.204 24.0943 38.4248 23.936C38.6456 23.7777 38.7547 23.5683 38.7547 23.3028C38.7547 23.0617 38.6754 22.862 38.5191 22.7086C38.3628 22.5551 38.0701 22.4139 37.6459 22.2897L37.1746 22.146C36.7181 22.0144 36.3609 21.8099 36.098 21.5298C35.8375 21.2521 35.7061 20.9112 35.7061 20.5069C35.7061 20.0222 35.8995 19.6131 36.289 19.2745C36.6785 18.9384 37.2341 18.7679 37.9585 18.7679C38.2487 18.7679 38.524 18.7996 38.782 18.8605C39.04 18.9238 39.2881 19.0164 39.5262 19.1406L39.4592 20.3291H39.3476L38.9557 19.6472C38.8143 19.406 38.6729 19.2356 38.5364 19.1357C38.3975 19.0358 38.1916 18.9871 37.9138 18.9871C37.6608 18.9871 37.4202 19.0578 37.1969 19.1966C36.9737 19.3354 36.8595 19.5351 36.8595 19.7908C36.8595 20.0466 36.9538 20.239 37.1448 20.3851C37.3358 20.5312 37.6161 20.6676 37.9907 20.7918L38.4943 20.9355C39.0326 21.0963 39.4171 21.3179 39.6478 21.6004C39.8785 21.8829 39.995 22.2215 39.995 22.6184C39.995 23.1542 39.7792 23.5829 39.3501 23.9068C38.9209 24.2307 38.3181 24.3915 37.5392 24.3915L37.5442 24.3939Z" fill="__C__"/></svg>'
let _logoCache={};
function svgImg(svg,color){ var k=color+':'+svg.length; if(_logoCache[k])return _logoCache[k]; var p=loadImg('data:image/svg+xml;charset=utf-8,'+encodeURIComponent(svg.replace(/__C__/g,color))); _logoCache[k]=p; return p; }

function loadImg(url){ return new Promise(function(res){ if(!url){res(null);return;} var im=new Image(); im.crossOrigin='anonymous'; im.onload=function(){res(im);}; im.onerror=function(){res(null);}; im.src=url; }); }
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
  if(style==='cover'){
    const img=await loadImg(item.cover);
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

  // ── KAPAK GÖRSELİ (~ekranın yarısı, orantılı sığdır) ──
  const topMargin=118;                  // üstte nefes payı (logo kaldırıldı)
  let quoteTop=topMargin+40;            // kapak yoksa alıntı bloğu üstten başlar
  if(cover){
    const boxW=W*0.46, boxH=H*0.42;    // ~yarım ekran kutusu
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

  // ── ALT BLOK (SABİT konumlar → asla üst üste binmez) ──
  // En altta: ikonsuz "thetelos" logosu. Üstündeki yazılar biraz yukarıda → aralarında boşluk.
  const wlogo=await svgImg(LOGO_WORD_SVG,cGold);
  const wlw=176, wlh=wlw*8.4/40, wlx=(W-wlw)/2, wly=H-60-wlh;   // logo en altta
  const attr=item.author?item.author.toUpperCase():'';
  const ySite=H-150, yBook=H-194, yAuthor=item.book?H-242:H-198;
  const attrTop=attr?yAuthor:(item.book?yBook:ySite);   // atıf bloğunun en üstü

  // ── ALINTI (Playfair italic, logo/kapak ile atıf arasına ortalanır) ──
  const quote='“'+item.quote+'”';
  const maxW=W-210, quoteBottom=attrTop-46, maxBlockH=Math.max(200, quoteBottom-quoteTop);
  let fs=cover?58:74, lines=[];
  function wrap(fontSize){ x.font='500 italic '+fontSize+'px '+SERIF; const words=quote.split(' '); let ln='',out=[];
    for(const w of words){ const t=ln?ln+' '+w:w; if(x.measureText(t).width>maxW&&ln){out.push(ln);ln=w;}else ln=t; } if(ln)out.push(ln); return out; }
  while(fs>28){ lines=wrap(fs); if(lines.length*(fs*1.34)<=maxBlockH) break; fs-=3; }
  x.font='500 italic '+fs+'px '+SERIF; x.fillStyle=cText;
  const lh=fs*1.34, blockH=lines.length*lh;
  let y=quoteTop+(maxBlockH-blockH)/2+fs*0.74;          // kalan alanda dikey ortala
  for(const ln of lines){ x.fillText(ln,W/2,y); y+=lh; }

  // ── ATIF (yazar + kitap, sabit konum) ──
  if(attr){ x.fillStyle=cGold; x.font='600 32px '+SERIF; x.letterSpacing='4px'; x.fillText(attr,W/2,yAuthor); x.letterSpacing='0px'; }
  if(item.book){ x.fillStyle=cMuted; x.font='500 italic 30px '+SERIF2;
    let b=item.book; if(x.measureText(b).width>maxW){while(x.measureText(b+'…').width>maxW&&b.length>4)b=b.slice(0,-1);b+='…';} x.fillText(b,W/2,yBook); }

  // ── ALT: site + logo (sabit konum) ──
  x.fillStyle=cMuted; x.font='500 22px '+SANS; x.letterSpacing='1px';
  x.fillText(item.site||'thetelos.org', W/2, ySite); x.letterSpacing='0px';
  if(wlogo){ x.drawImage(wlogo, wlx, wly, wlw, wlh); }
  else { x.fillStyle=cGold; x.font='600 26px '+SANS; x.fillText('thetelos', W/2, H-64); }
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
