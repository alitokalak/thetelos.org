<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Claude Batch — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.cb-card{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:18px 20px;margin-bottom:16px;max-width:820px}
.cb-card h3{margin:0 0 4px;font-size:15px}
.cb-card p.hint{margin:0 0 14px;font-size:12px;color:var(--muted);line-height:1.5}
.cb-row{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
.cb-row label{font-size:12px;color:var(--muted)}
textarea#cb-books{width:100%;min-height:150px;background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:10px 12px;font-size:13px;font-family:inherit;resize:vertical}
select,input[type=number]{padding:6px 10px;font-size:13px;background:var(--surface);border:1px solid var(--border);border-radius:6px;color:var(--text)}
input#cb-batchid{padding:6px 10px;font-size:13px;background:var(--surface);border:1px solid var(--border);border-radius:6px;color:var(--text);min-width:320px}
#cb-status,#cb-status2{font-size:12px;color:var(--tls-gold);min-height:18px;margin-top:8px;white-space:pre-wrap}
.cb-out{font-size:12.5px;line-height:1.7;margin-top:10px}
.cb-out a{color:#4a9eff}
.warn{background:rgba(255,140,0,.12);border:1px solid rgba(255,140,0,.35);border-radius:8px;padding:10px 12px;font-size:12px;color:#ffb15a;max-width:820px;margin-bottom:16px}
</style>
</head>
<body>
<div class="tls-shell">
  <aside class="tls-sidebar">
    <div class="tls-logo"><h1>Thetelos</h1><small>Content Panel</small></div>
    <nav class="tls-nav">
      <a href="panel.php"><span class="ico">✍</span> İçerik Üret</a>
      <a href="claude-batch.php" class="active"><span class="ico">⏳</span> Claude Batch</a>
      <a href="seo.php"><span class="ico">🔍</span> İçerik SEO</a>
      <a href="seo-site.php"><span class="ico">🌐</span> Site SEO</a>
      <a href="content-audit.php"><span class="ico">🩺</span> İçerik Denetimi</a>
      <a href="content-guard.php"><span class="ico">🛡️</span> İçerik Koruma</a>
      <a href="category-organize.php"><span class="ico">🗄️</span> Kategori Organize</a>
      <a href="recategorize.php"><span class="ico">🗂️</span> Kategori Düzelt</a>
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
        <h2>Claude Batch <span style="font-size:12px;color:var(--muted)">(yavaş · ucuz)</span></h2>
        <p>Anthropic Batch API ile toplu özet — %50 indirim, sonuçlar 24 saate kadar döner. Sonuçlar <b>taslak</b> yazılır, sen inceleyip yayınlarsın.</p>
      </div>
    </div>

    <div class="warn">⚠️ Bu araç Claude'un <b>kendi bilgisinden</b> özet yazar (kaynak taraması yok) ve <b>ücretlidir</b> (Anthropic kredisi gerekir). Sonuçlar taslak olur; yayınlamadan önce oku. Emin olmadığı eserleri atlar (uydurma yok).</div>

    <!-- 1) GÖNDER -->
    <div class="cb-card">
      <h3>1) Batch gönder</h3>
      <p class="hint">Her satıra bir kitap: <code>Başlık — Yazar</code> (tire ayırıcı). Yazar opsiyonel.</p>
      <textarea id="cb-books" placeholder="Meditations on First Philosophy — René Descartes&#10;The Republic — Plato&#10;Sefiller — Victor Hugo"></textarea>
      <div class="cb-row" style="margin-top:12px">
        <label>Model:
          <select id="cb-model">
            <option value="sonnet">Sonnet (kaliteli)</option>
            <option value="haiku">Haiku (en ucuz)</option>
          </select>
        </label>
        <label>Hedef kelime:
          <input type="number" id="cb-words" value="1200" min="400" max="6000" step="100" style="width:90px">
        </label>
        <button class="btn btn-primary" id="cb-send">⏳ Batch Gönder</button>
      </div>
      <div id="cb-status"></div>
    </div>

    <!-- 2) SONUÇ AL -->
    <div class="cb-card">
      <h3>2) Sonuçları al → taslak yaz</h3>
      <p class="hint">Batch bitince (genelde birkaç dk–birkaç saat) buradan sonuçları çek. Biten sonuçlar taslak yazıya dönüşür.</p>
      <div class="cb-row">
        <input type="text" id="cb-batchid" placeholder="msgbatch_..." />
        <button class="btn" id="cb-check">Durumu Kontrol Et</button>
        <button class="btn btn-primary" id="cb-collect">Sonuçları Al (taslak yaz)</button>
      </div>
      <div id="cb-status2"></div>
      <div class="cb-out" id="cb-out"></div>
    </div>
  </main>
</div>

<script>
function s(id,t,c){var e=document.getElementById(id);e.textContent=t;if(c)e.style.color=c;}
async function post(url,data){
  var fd=new FormData();for(var k in data)fd.append(k,data[k]);
  var r=await fetch('api/'+url,{method:'POST',body:fd});return r.json();
}
function parseBooks(){
  var lines=document.getElementById('cb-books').value.split('\n');
  var out=[];
  lines.forEach(function(l){
    l=l.trim();if(!l)return;
    var parts=l.split(/\s+[—–-]\s+/);   // — – veya - (boşluklu)
    var title=(parts[0]||'').trim();
    var author=(parts.slice(1).join(' - ')||'').trim();
    if(title)out.push({book_title:title,author_name:author});
  });
  return out;
}

document.getElementById('cb-send').onclick=async function(){
  var books=parseBooks();
  if(!books.length){s('cb-status','En az bir kitap gir (Başlık — Yazar).','#ff8c00');return;}
  s('cb-status','Gönderiliyor… ('+books.length+' kitap)','#e6c65a');
  var j=await post('claude-batch-submit.php',{
    books:JSON.stringify(books),
    claude_model:document.getElementById('cb-model').value,
    target_words:document.getElementById('cb-words').value
  });
  if(!j.ok){s('cb-status','Hata: '+(j.error||'bilinmeyen'),'#cc1818');return;}
  document.getElementById('cb-batchid').value=j.batch_id;
  s('cb-status','✅ Gönderildi — '+j.count+' kitap. Batch ID aşağı 2. adıma kopyalandı. Durum: '+(j.status||'in_progress')+'. Biraz sonra "Sonuçları Al".','#00ab6b');
};

document.getElementById('cb-check').onclick=async function(){
  var id=document.getElementById('cb-batchid').value.trim();
  if(!id){s('cb-status2','Batch ID gir.','#ff8c00');return;}
  s('cb-status2','Kontrol ediliyor…','#e6c65a');
  var j=await post('claude-batch-collect.php',{batch_id:id,check_only:'1'});
  if(!j.ok){s('cb-status2','Hata: '+(j.error||'?'),'#cc1818');return;}
  if(j.pending){s('cb-status2','⏳ Henüz bitmedi — durum: '+j.status+'. Sonra tekrar dene.','#e6c65a');return;}
  s('cb-status2','✅ Batch bitti — "Sonuçları Al" ile taslakları yaz.','#00ab6b');
};

document.getElementById('cb-collect').onclick=async function(){
  var id=document.getElementById('cb-batchid').value.trim();
  if(!id){s('cb-status2','Batch ID gir.','#ff8c00');return;}
  s('cb-status2','Sonuçlar alınıyor ve taslaklar yazılıyor…','#e6c65a');
  var j=await post('claude-batch-collect.php',{batch_id:id});
  if(!j.ok){s('cb-status2','Hata: '+(j.error||'?'),'#cc1818');return;}
  if(j.pending){s('cb-status2','⏳ Henüz bitmedi — durum: '+j.status,'#e6c65a');return;}
  if(j.already){s('cb-status2','ℹ️ Bu batch zaten taslağa yazılmıştı.','#4a9eff');return;}
  s('cb-status2','✅ Bitti: '+j.written_count+' taslak yazıldı · '+j.skipped_count+' atlandı (UNKNOWN) · '+j.error_count+' hata','#00ab6b');
  var html='';
  (j.written||[]).forEach(function(w){html+='📝 <a href="'+w.edit_url+'" target="_blank">'+w.book+'</a> (taslak #'+w.post_id+')<br>';});
  if((j.skipped||[]).length)html+='<br>⏭️ Atlanan: '+j.skipped.join(', ')+'<br>';
  (j.errors||[]).forEach(function(e){html+='⚠️ '+e.book+': '+e.error+'<br>';});
  document.getElementById('cb-out').innerHTML=html;
};
</script>
</body>
</html>
