<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kategori Organize — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.site-table{width:100%;border-collapse:collapse;font-size:13px}
.site-table th{text-align:left;padding:9px 10px;border-bottom:2px solid var(--border);color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.site-table td{padding:8px 10px;border-bottom:1px solid var(--border);vertical-align:middle}
.site-table tr:hover td{background:rgba(255,255,255,.02)}
.stats-row{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.stat-box{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 16px}
.stat-val{font-size:20px;font-weight:700}
.stat-lbl{font-size:11px;color:var(--muted);margin-top:2px}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600}
.badge-thin{background:rgba(212,180,131,.18);color:var(--tls-gold)}
.badge-none{background:rgba(204,24,24,.15);color:#cc1818}
.co-sel{padding:5px 8px;font-size:12px;background:var(--surface2);border:1px solid var(--border);border-radius:5px;color:var(--text);max-width:260px}
.co-sel.co-empty{border-color:#cc1818}
.bulk-row{display:flex;gap:8px;margin:0 0 14px;flex-wrap:wrap;align-items:center}
#co-search{padding:7px 10px;font-size:13px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;color:var(--text);width:240px}
#co-status{font-size:12px;color:var(--tls-gold);min-height:16px}
</style>
</head>
<body>
<div class="tls-shell">
  <aside class="tls-sidebar">
    <div class="tls-logo"><h1>Thetelos</h1><small>Content Panel</small></div>
    <nav class="tls-nav">
      <a href="panel.php"><span class="ico">✍</span> İçerik Üret</a>
      <a href="seo.php"><span class="ico">🔍</span> İçerik SEO</a>
      <a href="seo-site.php"><span class="ico">🌐</span> Site SEO</a>
      <a href="content-audit.php"><span class="ico">🩺</span> İçerik Denetimi</a>
      <a href="content-guard.php"><span class="ico">🛡️</span> İçerik Koruma</a>
      <a href="category-organize.php" class="active"><span class="ico">🗄️</span> Kategori Organize</a>
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
        <h2>Kategori Organize</h2>
        <p>Tüm kategorileri 14 kalıcı ana başlık altında grupla — URL'ler değişmez, SEO güvenli</p>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-box"><div class="stat-val" id="st-total">–</div><div class="stat-lbl">Toplam Kategori</div></div>
      <div class="stat-box"><div class="stat-val" id="st-assigned" style="color:#00ab6b">–</div><div class="stat-lbl">Atanmış</div></div>
      <div class="stat-box"><div class="stat-val" id="st-unassigned" style="color:var(--tls-gold)">–</div><div class="stat-lbl">Atanmamış</div></div>
    </div>

    <div class="bulk-row">
      <button class="btn btn-primary" id="btn-load">🔄 Kategorileri Tara</button>
      <button class="btn" id="btn-desc">📝 Boş açıklamaları doldur</button>
      <button class="btn" id="btn-autofill" style="display:none">✨ Boşlara Öneriyi Doldur</button>
      <button class="btn" id="btn-ai" style="display:none">🤖 Boşları AI ile Öner</button>
      <button class="btn btn-primary" id="btn-save" style="display:none">💾 Kaydet</button>
      <input type="search" id="co-search" placeholder="Kategori ara…" style="display:none">
      <label id="co-onlyempty-wrap" style="display:none;align-items:center;gap:6px;font-size:12px;color:var(--muted);cursor:pointer">
        <input type="checkbox" id="co-onlyempty"> Sadece boşlar</label>
      <span id="co-status"></span>
    </div>

    <p style="font-size:12px;color:var(--muted);margin-bottom:14px;max-width:860px">
      Her kategoriyi 14 <b>kalıcı ana kategoriden</b> birine bağla. Bu bağlama yalnızca
      <b>sunum içindir</b> (categories sayfası, menü, arama gruplaması) — <b>kategori
      URL'leri DEĞİŞMEZ</b>, hiçbir bağlantı kırılmaz. <b>✨ Boşlara Öneriyi Doldur</b>
      atanmamış satırlara otomatik tahmin koyar; gözden geçir, düzelt, <b>💾 Kaydet</b>.
      Çok ince/dar kategorileri (ör. "Peru Literature") tamamen <b>birleştirmek</b> için
      "Kategori Temizle" aracını kullan.
    </p>

    <div id="result"><div style="text-align:center;padding:40px;color:var(--muted)">Tara butonuna bas.</div></div>
  </main>
</div>

<script>
const $ = id => document.getElementById(id);
let mains = {}, rows = [];

function post(body){
  return fetch('api/category-organize.php', {method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/x-www-form-urlencoded'}, body}).then(r=>r.json());
}
function escH(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}

function mainOptions(sel){
  let o = '<option value="">— ana kategori —</option>';
  for(const slug in mains){
    o += '<option value="'+slug+'"'+(slug===sel?' selected':'')+'>'+escH(mains[slug])+'</option>';
  }
  return o;
}

function render(){
  let html = '<table class="site-table"><thead><tr>'+
    '<th>Kategori</th><th style="width:70px">Yazı</th>'+
    '<th style="width:90px">Durum</th><th style="width:280px">Ana Kategori</th>'+
    '</tr></thead><tbody>';
  rows.forEach(r=>{
    // Kayıtlı ana varsa onu, yoksa ÖNERİYİ otomatik seç → form asla boş açılmaz
    const val = r.current || r.suggested || '';
    html += '<tr data-id="'+r.id+'" data-name="'+escH((r.name+' '+r.slug).toLowerCase())+'">'+
      '<td><b>'+escH(r.name)+'</b><br><small style="color:var(--muted)">'+escH(r.slug)+'</small></td>'+
      '<td>'+r.count+'</td>'+
      '<td>'+(r.thin?'<span class="badge badge-thin">İnce</span>':'')+
             (!val && !r.suggested?'<span class="badge badge-none">öneri yok</span>':'')+'</td>'+
      '<td><select class="co-sel'+(val?'':' co-empty')+'" data-sug="'+escH(r.suggested||'')+'">'+mainOptions(val)+'</select></td>'+
      '</tr>';
  });
  html += '</tbody></table>';
  $('result').innerHTML = html;
  ['btn-autofill','btn-ai','btn-save','co-search'].forEach(id=>$(id).style.display='');
  $('co-onlyempty-wrap').style.display = 'inline-flex';
}
function recount(){
  const all = document.querySelectorAll('.co-sel').length;
  const empty = document.querySelectorAll('.co-sel.co-empty').length;
  $('st-assigned').textContent = all - empty;
  $('st-unassigned').textContent = empty;
}
function applyFilter(){
  const only = $('co-onlyempty').checked;
  const q = ($('co-search').value||'').trim().toLowerCase();
  document.querySelectorAll('#result tbody tr').forEach(tr=>{
    const sel = tr.querySelector('.co-sel');
    const isEmpty = sel && !sel.value;
    const okEmpty = !only || isEmpty;
    const okQ = !q || (tr.dataset.name||'').includes(q);
    tr.style.display = (okEmpty && okQ) ? '' : 'none';
  });
}

// seçim değişince kırmızı kenarı + sayaçları güncelle
document.addEventListener('change', e=>{
  if(e.target.classList.contains('co-sel')){ e.target.classList.toggle('co-empty', !e.target.value); recount(); applyFilter(); }
  if(e.target.id==='co-onlyempty') applyFilter();
});
// arama + "sadece boşlar" filtresi
document.addEventListener('input', e=>{ if(e.target.id==='co-search') applyFilter(); });

// Boş kategori açıklamalarını otomatik doldur (tek tık, parça parça)
$('btn-desc').addEventListener('click', ()=>{
  if(!confirm('Açıklaması boş TÜM kategoriler için tek cümlelik tanım üretilip kaydedilecek. Başlansın mı?')) return;
  $('btn-desc').disabled = true;
  // Sunucu taramayı kendi yapıyor; biz remaining=0 olana kadar tetikliyoruz.
  let done=0, aiN=0, fbN=0, skE=0, dbg='', total=0, guard=0;
  $('co-status').textContent = 'Boş açıklamalar dolduruluyor…';
  const step = ()=>{
    if(++guard > 60){ $('btn-desc').disabled=false; $('co-status').textContent='Durdu (çok fazla tur) · '+done+' yazıldı.'; return; }
    post('action=desc_fill').then(r=>{
      if(!r||!r.ok){ $('btn-desc').disabled=false; $('co-status').textContent='⚠ Hata — durdu: '+((r&&r.error)||'bilinmiyor')+' · '+done+' yazıldı'; return; }
      done+=(r.done||0); aiN+=(r.ai||0); fbN+=(r.fallback||0); skE+=(r.skip_err||0); if(!dbg&&r.debug)dbg=r.debug;
      if(total===0) total=(r.scanned||0);
      const remaining = r.remaining||0;
      if(remaining>0 && (r.done||0)>0){
        $('co-status').textContent='Açıklamalar yazılıyor… ('+done+'/'+total+') · kalan '+remaining;
        step();
        return;
      }
      // bitti (kalan yok) VEYA bu turda hiç ilerlemedi (takıldı)
      $('btn-desc').disabled=false;
      if(done>0){ $('co-status').textContent='✓ '+done+' açıklama yazıldı ('+aiN+' AI · '+fbN+' yedek). Categories sayfasında görünür — gerekirse cache temizle.'; }
      else if(total===0){ $('co-status').textContent='✓ Tüm kategorilerin açıklaması zaten dolu.'; }
      else { $('co-status').textContent='⚠ 0 yazıldı — teşhis: boş '+total+', hata '+skE+(dbg?(' · '+dbg):' · sebep bilinmiyor'); }
    }).catch(()=>{ $('btn-desc').disabled=false; $('co-status').textContent='Bağlantı hatası · '+done+' yazıldı.'; });
  };
  step();
});

$('btn-load').addEventListener('click', ()=>{
  $('co-status').textContent='Kategoriler okunuyor…';
  $('btn-load').disabled = true;
  post('action=list').then(d=>{
    $('btn-load').disabled = false;
    if(!d||!d.ok){ $('co-status').textContent='Hata.'; return; }
    mains = d.mains; rows = d.rows;
    $('st-total').textContent = d.total;
    render();
    // render() önerileri otomatik doldurdu → seçili sayısını istemciden say
    const filled = document.querySelectorAll('.co-sel').length
                 - document.querySelectorAll('.co-sel.co-empty').length;
    $('st-assigned').textContent = filled;
    $('st-unassigned').textContent = d.total - filled;
    $('co-status').textContent = d.total+' kategori — öneriler otomatik dolduruldu ('+filled+' atandı). Gözden geçir, düzelt, 💾 Kaydet.';
  }).catch(()=>{ $('btn-load').disabled=false; $('co-status').textContent='Bağlantı hatası.'; });
});

$('btn-autofill').addEventListener('click', ()=>{
  let n=0;
  document.querySelectorAll('.co-sel').forEach(sel=>{
    if(!sel.value && sel.dataset.sug){ sel.value = sel.dataset.sug; sel.classList.remove('co-empty'); n++; }
  });
  recount(); applyFilter();
  $('co-status').textContent = n+' satıra öneri dolduruldu. Gözden geçir ve Kaydet.';
});

// AI ile boşları öner (motorun tahmin edemedikleri → DeepSeek 14'ten seçer)
$('btn-ai').addEventListener('click', ()=>{
  // Sunucu, atanmamış + motorun tahmin edemediği kategorileri kendi bulur;
  // biz sadece tetikleriz ve dönen [{id,main}] önerilerini boş satırlara koyarız.
  $('btn-ai').disabled = true;
  $('co-status').textContent = 'AI öneriyor…';
  post('action=ai_suggest').then(d=>{
    $('btn-ai').disabled = false;
    if(d && d.ok===false){ $('co-status').textContent='AI hata: '+(d.error||'bilinmiyor'); return; }
    let filled = 0;
    if(d && d.map){ d.map.forEach(m=>{
      const sel = document.querySelector('tr[data-id="'+m.id+'"] .co-sel');
      if(sel && !sel.value){ sel.value = m.main; sel.classList.remove('co-empty'); filled++; }
    }); }
    recount(); applyFilter();
    if(filled>0){ $('co-status').textContent = '🤖 AI '+filled+' boşa öneri koydu. Gözden geçir ve Kaydet.'; }
    else if(d && d.asked===0){ $('co-status').textContent = '✓ AI\'ya sorulacak boş kalmadı — motor hepsini tahmin etti. Kaydet yeter.'; }
    else { $('co-status').textContent = '🤖 AI öneri döndürmedi'+(d&&d.debug?(' — '+d.debug):'')+'. Kalan boşları elle seçebilirsin.'; }
  }).catch(()=>{ $('btn-ai').disabled=false; $('co-status').textContent='AI bağlantı hatası.'; });
});

$('btn-save').addEventListener('click', ()=>{
  const map = [];
  document.querySelectorAll('#result tbody tr').forEach(tr=>{
    const sel = tr.querySelector('.co-sel');
    map.push({id: parseInt(tr.dataset.id,10), main: sel ? sel.value : ''});
  });
  const assigned = map.filter(m=>m.main).length;
  if(assigned === 0){
    alert('Hiçbir kategoriye ana başlık seçilmemiş. Önce menülerden seç (ya da "Boşlara Öneriyi Doldur"), sonra Kaydet.');
    return;
  }
  if(!confirm(assigned+' kategori ana başlıklara bağlanacak (URL değişmez). Kaydedilsin mi?')) return;
  $('btn-save').disabled = true;
  $('co-status').textContent = 'Kaydediliyor…';
  post('action=apply&map='+encodeURIComponent(JSON.stringify(map))).then(d=>{
    $('btn-save').disabled = false;
    if(!d||!d.ok){ $('co-status').textContent='Hata.'; return; }
    $('co-status').textContent = '✓ Kaydedildi — DB\'de '+d.stored+' kategori bağlı (bu turda '+d.set+' yazıldı).';
    alert('Kaydedildi ✓\nVeritabanında '+d.stored+' kategori ana başlığa bağlı.\n\nSitede görmek için: WP Admin → LiteSpeed → Purge All, sonra /categories/ sayfasını yenile. (Giriş yapmışken açarsan önbelleği atlar.)');
    $('btn-load').click();
  }).catch(()=>{ $('btn-save').disabled=false; $('co-status').textContent='Bağlantı hatası.'; });
});
</script>
</body>
</html>
