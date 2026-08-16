<?php
/**
 * prototype.php — Kaynak-odaklı özet PROTOTİPİ test arayüzü (Sistem A vs B).
 * Hiçbir şey WordPress'e yayınlanmaz; yalnız gerçek-metin boru hattını dener.
 */
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kaynak-Odaklı Özet Prototipi — The Telos</title>
<style>
  :root{--bg:#0f1115;--card:#171a21;--bd:#262b36;--tx:#e6e9ef;--mut:#9aa4b2;--gold:#d4b483;--grn:#3fb27f;--red:#e06666;--blu:#4a9eff}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--tx);font:15px/1.6 -apple-system,Segoe UI,Roboto,sans-serif;padding:28px}
  h1{font-size:20px;margin:0 0 4px}.sub{color:var(--mut);font-size:13px;margin-bottom:20px}
  .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:18px;margin-bottom:18px}
  label{display:block;font-size:12px;color:var(--mut);margin:8px 0 3px}
  input,select{width:100%;padding:9px 11px;background:#0e1116;border:1px solid var(--bd);border-radius:8px;color:var(--tx);font-size:14px}
  .row{display:grid;grid-template-columns:2fr 2fr 1fr 1fr;gap:10px}
  .presets{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}
  .preset{padding:7px 12px;background:#0e1116;border:1px solid var(--bd);border-radius:20px;color:var(--tx);font-size:12px;cursor:pointer}
  .preset:hover{border-color:var(--gold)}
  .btn{margin-top:14px;padding:11px 22px;background:var(--gold);color:#1a1a1a;border:none;border-radius:8px;font-weight:600;font-size:14px;cursor:pointer}
  .btn:disabled{opacity:.5;cursor:not-allowed}
  #log{font-family:ui-monospace,Menlo,monospace;font-size:12px;color:var(--mut);white-space:pre-wrap;max-height:150px;overflow:auto;margin-top:12px;padding:10px;background:#0e1116;border-radius:8px;display:none}
  .cols{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  @media(max-width:800px){.cols{grid-template-columns:1fr}.row{grid-template-columns:1fr 1fr}}
  .colhead{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
  .tag{font-size:11px;padding:2px 9px;border-radius:20px;font-weight:600}
  .tagA{background:rgba(224,102,102,.15);color:var(--red)}
  .tagB{background:rgba(63,178,127,.15);color:var(--grn)}
  .metrics{font-size:12px;color:var(--mut);margin:6px 0 10px;line-height:1.8}
  .metrics b{color:var(--tx)}
  .summary{font-size:14px;line-height:1.7;max-height:560px;overflow:auto;border-top:1px solid var(--bd);padding-top:10px}
  .summary h2{font-size:15px;color:var(--gold);margin:16px 0 6px}
  .summary h3{font-size:14px;margin:12px 0 4px}
  .snf{color:var(--red);font-weight:600;padding:20px;text-align:center}
  .chapters{font-size:11px;color:var(--mut);max-height:120px;overflow:auto;margin-top:6px}
  a{color:var(--blu)}
</style>
</head>
<body>
  <h1>Kaynak-Odaklı Özet Prototipi</h1>
  <div class="sub">Sistem A (başlık→DeepSeek, kaynaksız) &nbsp;vs&nbsp; Sistem B (Project Gutenberg gerçek tam metin → parça analiz → kapsamlı özet). Hiçbir şey yayınlanmaz.</div>

  <div class="card">
    <div class="row">
      <div><label>Kitap adı</label><input id="book" placeholder="Republic"></div>
      <div><label>Yazar</label><input id="author" placeholder="Plato"></div>
      <div><label>Dil</label><input id="lang" placeholder="en" value="en"></div>
      <div><label>Yıl</label><input id="year" placeholder="—"></div>
    </div>
    <div class="presets">
      <span class="preset" data-b="Relativity: The Special and the General Theory" data-a="Albert Einstein">① Einstein — Relativity</span>
      <span class="preset" data-b="Republic" data-a="Plato">② Plato — Republic</span>
      <span class="preset" data-b="The Imitation of Christ" data-a="Thomas à Kempis">③ Nadir — The Imitation of Christ</span>
    </div>
    <label>Ne çalıştırılsın?</label>
    <select id="sys" style="max-width:280px">
      <option value="both">İkisi (A vs B karşılaştır)</option>
      <option value="B">Sadece Sistem B (kaynak-odaklı)</option>
      <option value="A">Sadece Sistem A (klasik)</option>
    </select>
    <br><button class="btn" id="run">▶ Çalıştır</button>
    <div id="log"></div>
  </div>

  <div class="cols">
    <div class="card" id="cardB">
      <div class="colhead"><b>Sistem B — Kaynak-Odaklı</b><span class="tag tagB">gerçek metin</span></div>
      <div id="mB" class="metrics">—</div>
      <div id="sB" class="summary"></div>
    </div>
    <div class="card" id="cardA">
      <div class="colhead"><b>Sistem A — Klasik</b><span class="tag tagA">kaynaksız</span></div>
      <div id="mA" class="metrics">—</div>
      <div id="sA" class="summary"></div>
    </div>
  </div>

<script>
const $ = s => document.querySelector(s);
document.querySelectorAll('.preset').forEach(p => p.onclick = () => {
  $('#book').value = p.dataset.b; $('#author').value = p.dataset.a;
});
let es = null;
function logln(t){ const l=$('#log'); l.style.display='block'; l.textContent += t+'\n'; l.scrollTop=l.scrollHeight; }

$('#run').onclick = () => {
  const book = $('#book').value.trim(); if(!book){ alert('Kitap adı gir'); return; }
  const author=$('#author').value.trim(), sys=$('#sys').value;
  $('#run').disabled=true; $('#log').textContent=''; $('#log').style.display='block';
  $('#mA').textContent='—'; $('#sA').innerHTML=''; $('#mB').textContent='—'; $('#sB').innerHTML='';
  if(es) es.close();
  const qs = new URLSearchParams({book,author,lang:$('#lang').value,year:$('#year').value,sys});
  es = new EventSource('api/proto.php?'+qs.toString());

  es.addEventListener('status', e => { const d=JSON.parse(e.data); logln('['+(d.sys||'-')+'] '+d.msg); });

  es.addEventListener('debug', e => {
    const d=JSON.parse(e.data);
    logln('— GUTENBERG DEBUG —');
    logln('  gutendex sonuç: '+d.gutendex_results+(d.match?(' · eşleşen: #'+d.match.id+' '+d.match.title):''));
    (d.tried||[]).forEach(t => logln('  ['+t.code+'] '+t.bytes+' bayt  '+t.url+(t.err?(' ERR:'+t.err):'')));
    if(d.note) logln('  not: '+d.note);
    if(d.sample) logln('  örnek: '+d.sample.slice(0,160));
  });

  es.addEventListener('resultB', e => {
    const d=JSON.parse(e.data);
    if(d.state==='SOURCE_NOT_FOUND'){ $('#sB').innerHTML='<div class="snf">SOURCE_NOT_FOUND<br><span style="font-weight:400;font-size:13px">'+d.msg+'</span></div>'; $('#mB').innerHTML='<b>süre:</b> '+d.time+'s'; return; }
    let m='<b>kaynak:</b> '+d.source+' · <a href="'+d.url+'" target="_blank">metin</a><br>';
    m+='<b>kitap:</b> '+Number(d.book_words).toLocaleString()+' kelime · <b>parça:</b> '+d.chunks+' · <b>tespit edilen bölüm:</b> '+(d.chapters?d.chapters.length:0)+'<br>';
    m+='<b>ÖZET:</b> '+Number(d.summary_words).toLocaleString()+' kelime · <b>süre:</b> '+d.time+'s';
    $('#mB').innerHTML=m;
    let h=d.summary_html||'';
    if(d.chapters&&d.chapters.length) h+='<div class="chapters"><b>Tespit edilen bölümler:</b><br>'+d.chapters.join(' · ')+'</div>';
    $('#sB').innerHTML=h;
  });

  es.addEventListener('resultA', e => {
    const d=JSON.parse(e.data);
    $('#mA').innerHTML='<b>ÖZET:</b> '+Number(d.summary_words).toLocaleString()+' kelime · <b>süre:</b> '+d.time+'s · <span style="color:var(--red)">kaynak yok — doğrulanmadı</span>';
    $('#sA').innerHTML=d.summary_html||'(boş)';
  });

  es.addEventListener('error', e => { try{const d=JSON.parse(e.data);logln('HATA: '+d.error);}catch(_){ logln('HATA: bağlantı kesildi'); } });
  es.addEventListener('done', e => { logln('✔ bitti'); $('#run').disabled=false; es.close(); });
  es.onerror = () => { logln('⚠ bağlantı kapandı'); $('#run').disabled=false; if(es) es.close(); };
};
</script>
</body>
</html>
