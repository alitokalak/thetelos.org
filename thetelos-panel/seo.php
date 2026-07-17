<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>İçerik SEO — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.seo-filters{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px}
.seo-field{display:flex;flex-direction:column;gap:4px}
.seo-field label{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.06em}
.seo-field select{padding:8px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:13px}
.author-box{position:relative}
.author-input{padding:8px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:13px;width:180px;box-sizing:border-box}
.author-input:focus{outline:none;border-color:var(--tls-gold)}
.author-drop{position:absolute;top:calc(100% + 2px);left:0;min-width:220px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;max-height:220px;overflow-y:auto;z-index:200;display:none;box-shadow:0 4px 12px rgba(0,0,0,.4)}
.author-drop.open{display:block}
.author-opt{padding:8px 12px;font-size:13px;cursor:pointer;color:var(--text)}
.author-opt:hover,.author-opt.active{background:var(--surface)}
.author-opt.all{color:var(--muted)}
.author-tag{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;background:rgba(212,180,131,.15);color:var(--tls-gold);border-radius:12px;font-size:12px;margin-top:5px}
.author-tag button{background:none;border:none;color:inherit;cursor:pointer;padding:0;font-size:15px;line-height:1}
.seo-table{width:100%;border-collapse:collapse;font-size:13px}
.seo-table th{text-align:left;padding:10px 12px;border-bottom:2px solid var(--border);color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.seo-table td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:top}
.seo-table tr:hover td{background:rgba(255,255,255,.02)}
.post-link{color:var(--tls-gold);text-decoration:none;font-weight:500;font-size:13px;line-height:1.4;display:block}
.post-link:hover{text-decoration:underline}
.post-author{color:var(--muted);font-size:11px;margin-top:2px}
.meta-cell{max-width:260px;font-size:12px;line-height:1.5}
.meta-empty{color:var(--muted);font-style:italic}
.char-count{font-size:10px;color:var(--muted);margin-top:2px}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;margin-bottom:3px}
.badge-ok{background:rgba(0,171,107,.15);color:#00ab6b}
.badge-err{background:rgba(204,24,24,.15);color:#cc1818}
.badge-warn{background:rgba(212,180,131,.15);color:var(--tls-gold)}
.issue-hint{font-size:10px;color:var(--muted);display:block;margin-bottom:4px}
.action-cell{width:130px}
.btn-seo{display:block;width:100%;margin-bottom:4px;padding:5px 10px;font-size:11px;font-weight:600;border:none;border-radius:4px;cursor:pointer;transition:.15s}
.btn-seo.ex{background:rgba(212,180,131,.2);color:var(--tls-gold)}
.btn-seo.ex:hover{background:rgba(212,180,131,.35)}
.btn-seo.mt{background:rgba(74,158,255,.15);color:#4a9eff}
.btn-seo.mt:hover{background:rgba(74,158,255,.25)}
.btn-seo.bt{background:rgba(0,171,107,.15);color:#00ab6b}
.btn-seo.bt:hover{background:rgba(0,171,107,.25)}
.btn-seo:disabled{opacity:.4;cursor:default}
.seo-stats{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.seo-stat{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 16px}
.seo-stat-val{font-size:20px;font-weight:700}
.seo-stat-lbl{font-size:11px;color:var(--muted);margin-top:2px}
.prog-wrap{background:var(--surface);border-radius:4px;height:4px;margin-bottom:12px;display:none}
.prog-bar{height:4px;background:var(--tls-gold);border-radius:4px;transition:width .3s}
#status-line{font-size:12px;color:var(--tls-gold);margin-top:6px;min-height:16px}
.bulk-btns{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
</style>
</head>
<body>
<div class="tls-shell">
  <aside class="tls-sidebar">
    <div class="tls-logo"><h1>Thetelos</h1><small>Content Panel</small></div>
    <nav class="tls-nav">
      <a href="panel.php"><span class="ico">✍</span> İçerik Üret</a>
      <a href="seo.php" class="active"><span class="ico">🔍</span> İçerik SEO</a>
      <a href="seo-site.php"><span class="ico">🌐</span> Site SEO</a>
      <a href="amazon-match.php"><span class="ico">🛒</span> Amazon</a>
      <a href="settings.php"><span class="ico">⚙</span> Ayarlar</a>
      <a href="<?= rtrim(WP_URL,'/') ?>/wp-admin/" target="_blank"><span class="ico">🔗</span> WP Admin</a>
      <a href="<?= rtrim(WP_URL,'/') ?>/" target="_blank"><span class="ico">↗</span> Siteyi Gör</a>
    </nav>
    <div class="tls-sidebar-footer"><a href="index.php?logout=1">Çıkış Yap</a></div>
  </aside>

  <main class="tls-main">
    <div class="tls-header">
      <div><h2>İçerik SEO</h2><p>Excerpt ve meta description kalitesini kontrol et, düzelt</p></div>
    </div>

    <div class="card" style="margin-bottom:16px">
      <div class="seo-filters">
        <div class="seo-field">
          <label>İçerik Tipi</label>
          <select id="f-type">
            <option value="posts">Kitap Özetleri</option>
            <option value="analysis">Analizler</option>
          </select>
        </div>
        <div class="seo-field">
          <label>Kategori</label>
          <select id="f-cat"><option value="">Tüm Kategoriler</option></select>
        </div>
        <div class="seo-field">
          <label>Yazar</label>
          <div class="author-box">
            <input type="text" id="f-author-input" class="author-input" placeholder="Yazar ara veya tümü...">
            <div class="author-drop" id="f-author-drop"></div>
          </div>
          <div id="f-author-tag"></div>
          <input type="hidden" id="f-author" value="">
        </div>
        <div class="seo-field">
          <label>Durum</label>
          <select id="f-issue">
            <option value="">Tüm Durumlar</option>
            <option value="missing">Eksik</option>
            <option value="truncated">Kesilmiş</option>
            <option value="too_long">Çok Uzun</option>
          </select>
        </div>
        <div class="seo-field" style="justify-content:flex-end">
          <button class="btn btn-primary" id="btn-scan">🔍 Tara</button>
        </div>
      </div>
      <div id="status-line"></div>
    </div>

    <div class="seo-stats" id="seo-stats" style="display:none">
      <div class="seo-stat"><div class="seo-stat-val" id="s-total">0</div><div class="seo-stat-lbl">Toplam</div></div>
      <div class="seo-stat"><div class="seo-stat-val" id="s-ok" style="color:#00ab6b">0</div><div class="seo-stat-lbl">Sorunsuz</div></div>
      <div class="seo-stat"><div class="seo-stat-val" id="s-issue" style="color:#cc1818">0</div><div class="seo-stat-lbl">Sorunlu</div></div>
      <div class="seo-stat"><div class="seo-stat-val" id="s-missing" style="color:var(--tls-gold)">0</div><div class="seo-stat-lbl">Eksik</div></div>
    </div>

    <div class="bulk-btns" id="bulk-btns" style="display:none">
      <button class="btn" id="btn-fix-ex">📝 Tüm Excerpt Sorunları</button>
      <button class="btn" id="btn-fix-mt">🏷 Tüm Meta Sorunları</button>
      <button class="btn btn-primary" id="btn-fix-all">⚡ Tümünü Düzelt</button>
      <button class="btn" id="btn-fix-fast" title="AI kullanmadan: yarım/uzun excerpt & meta'yı anlamlı tam cümleye getirir. Tüm yazılarda çalışır, token harcamaz.">🚀 Hızlı Düzelt (AI'sız)</button>
    </div>

    <div class="prog-wrap" id="prog-wrap"><div class="prog-bar" id="prog-bar" style="width:0%"></div></div>
    <div id="seo-results"><div style="text-align:center;padding:60px;color:var(--muted)">Filtreleri seçip <strong>Tara</strong> butonuna basın.</div></div>
  </main>
</div>

<script>
const API = p => 'api/' + p;
let allAuthors = [], allPosts = [], selectedAuthorId = '', selectedAuthorName = '';

// ── Filtreleri yükle ─────────────────────────────────────
fetch(API('seo-filters.php'))
  .then(r => r.json())
  .then(data => {
    if (data.categories) {
      const sel = document.getElementById('f-cat');
      data.categories.forEach(c => {
        const o = document.createElement('option');
        o.value = c.id; o.textContent = c.name + ' (' + c.count + ')';
        sel.appendChild(o);
      });
    }
    allAuthors = data.authors || [];
  }).catch(() => {});

// ── Yazar arama ──────────────────────────────────────────
const ainput = document.getElementById('f-author-input');
const adrop  = document.getElementById('f-author-drop');
const ahid   = document.getElementById('f-author');
const atag   = document.getElementById('f-author-tag');

function buildDrop(list) {
  adrop.innerHTML = '<div class="author-opt all" data-id="" data-name="">✓ Tüm Yazarlar</div>' +
    list.map(a => `<div class="author-opt" data-id="${a.id}" data-name="${a.name.replace(/"/g,'&quot;')}">${a.name}</div>`).join('');
  adrop.classList.add('open');
}

ainput.addEventListener('focus', () => {
  buildDrop(allAuthors.slice(0, 30));
});

ainput.addEventListener('input', () => {
  const q = ainput.value.trim().toLowerCase();
  const filtered = q.length < 1
    ? allAuthors.slice(0, 30)
    : allAuthors.filter(a => a.name.toLowerCase().includes(q));
  if (!filtered.length && q.length > 0) { adrop.classList.remove('open'); return; }
  buildDrop(filtered);
});

adrop.addEventListener('click', e => {
  const opt = e.target.closest('.author-opt');
  if (!opt) return;
  selectedAuthorId   = opt.dataset.id;
  selectedAuthorName = opt.dataset.name;
  ahid.value = selectedAuthorId;
  ainput.value = selectedAuthorName || '';
  adrop.classList.remove('open');
  atag.innerHTML = selectedAuthorId
    ? `<span class="author-tag">${selectedAuthorName}<button onclick="clearAuthor()">×</button></span>`
    : '';
});

document.addEventListener('click', e => {
  if (!e.target.closest('.author-box')) adrop.classList.remove('open');
});

function clearAuthor() {
  selectedAuthorId = selectedAuthorName = '';
  ahid.value = ''; ainput.value = ''; atag.innerHTML = '';
}

// ── Sorun tespiti ────────────────────────────────────────
const HINTS = {
  missing:   'Hiç yazılmamış',
  too_long:  '160 karakteri geçiyor',
  truncated: 'Nokta/? /! ile bitmiyor',
};

function detectIssues(post) {
  const issues = [];
  const ex = (post.excerpt || '').trim();
  const mt = (post.meta_description || '').trim();

  if (!ex)            issues.push({f:'excerpt',t:'missing'});
  else if (ex.length > 160)   issues.push({f:'excerpt',t:'too_long'});
  else if (!/[.!?]$/.test(ex)) issues.push({f:'excerpt',t:'truncated'});

  if (!mt)            issues.push({f:'meta',t:'missing'});
  else if (mt.length > 160)   issues.push({f:'meta',t:'too_long'});
  else if (!/[.!?]$/.test(mt)) issues.push({f:'meta',t:'truncated'});

  return issues;
}

function badge(t) {
  const cls = t==='missing'?'badge-err':'badge-warn';
  const lbl = {missing:'Eksik',too_long:'Çok Uzun',truncated:'Kesilmiş'}[t]||t;
  return `<span class="badge ${cls}" title="${HINTS[t]||''}">${lbl}</span><span class="issue-hint">${HINTS[t]||''}</span>`;
}

// ── Tara ─────────────────────────────────────────────────
document.getElementById('btn-scan').addEventListener('click', async () => {
  const btn = document.getElementById('btn-scan');
  btn.disabled = true; btn.textContent = 'Taranıyor...';
  document.getElementById('seo-results').innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted)"><span class="loader"></span> Yükleniyor...</div>';
  document.getElementById('seo-stats').style.display = 'none';
  document.getElementById('bulk-btns').style.display = 'none';

  try {
    const fd = new FormData();
    fd.append('type',   document.getElementById('f-type').value);
    fd.append('cat',    document.getElementById('f-cat').value);
    fd.append('author', ahid.value);
    const res  = await fetch(API('seo-scan.php'), {method:'POST', body:fd});
    const data = await res.json();
    if (!data.ok) { setStatus('Hata: ' + (data.error||'?')); return; }
    allPosts = data.posts || [];
    renderTable();
  } catch(e) {
    setStatus('Hata: ' + e.message);
    document.getElementById('seo-results').innerHTML = '<div style="text-align:center;padding:40px;color:#cc1818">' + e.message + '</div>';
  } finally {
    btn.disabled = false; btn.textContent = '🔍 Tara';
  }
});

document.getElementById('f-issue').addEventListener('change', () => { if (allPosts.length) renderTable(); });

// ── Render ───────────────────────────────────────────────
function renderTable() {
  const issueF  = document.getElementById('f-issue').value;
  const filtered = allPosts.filter(p => {
    const iss = detectIssues(p);
    return !issueF || iss.some(i => i.t === issueF);
  });

  const total   = allPosts.length;
  const withIss = allPosts.filter(p => detectIssues(p).length > 0).length;
  const missing = allPosts.filter(p => detectIssues(p).some(i => i.t==='missing')).length;
  document.getElementById('s-total').textContent   = total;
  document.getElementById('s-ok').textContent      = total - withIss;
  document.getElementById('s-issue').textContent   = withIss;
  document.getElementById('s-missing').textContent = missing;
  document.getElementById('seo-stats').style.display  = 'flex';
  document.getElementById('bulk-btns').style.display  = withIss > 0 ? 'flex' : 'none';

  if (!filtered.length) {
    document.getElementById('seo-results').innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted)">Sorun bulunamadı ✓</div>';
    return;
  }

  let html = '<table class="seo-table"><thead><tr><th>Post</th><th>Excerpt</th><th>Meta Description</th><th>İşlem</th></tr></thead><tbody>';
  filtered.forEach(p => {
    const iss = detectIssues(p);
    const exI = iss.filter(i => i.f==='excerpt');
    const mtI = iss.filter(i => i.f==='meta');
    const exB = exI.length ? exI.map(i=>badge(i.t)).join('') : '<span class="badge badge-ok">OK</span>';
    const mtB = mtI.length ? mtI.map(i=>badge(i.t)).join('') : '<span class="badge badge-ok">OK</span>';
    const exT = p.excerpt ? `<div class="meta-cell">${escH(p.excerpt)}</div><div class="char-count">${p.excerpt.length} kr</div>` : '<div class="meta-empty">— boş —</div>';
    const mtT = p.meta_description ? `<div class="meta-cell">${escH(p.meta_description)}</div><div class="char-count">${p.meta_description.length} kr</div>` : '<div class="meta-empty">— boş —</div>';
    const hasEx = exI.length > 0, hasMt = mtI.length > 0;
    html += `<tr data-id="${p.id}" data-title="${escA(p.title)}" data-author="${escA(p.author_name||'')}">
      <td><a class="post-link" href="${p.link}" target="_blank">${escH(p.title)}</a><div class="post-author">${escH(p.author_name||'')}</div></td>
      <td>${exB}${exT}</td>
      <td>${mtB}${mtT}</td>
      <td class="action-cell">
        ${hasEx?`<button class="btn-seo ex" onclick="fixOne(${p.id},'excerpt',this)">Excerpt Oluştur</button>`:''}
        ${hasMt?`<button class="btn-seo mt" onclick="fixOne(${p.id},'meta',this)">Meta Oluştur</button>`:''}
        ${hasEx&&hasMt?`<button class="btn-seo bt" onclick="fixOne(${p.id},'both',this)">İkisini Düzelt</button>`:''}
      </td>
    </tr>`;
  });
  html += '</tbody></table>';
  document.getElementById('seo-results').innerHTML = html;
}

// ── Tek düzelt ───────────────────────────────────────────
async function fixOne(id, field, btn) {
  const row = btn.closest('tr');
  const orig = btn.textContent;
  btn.disabled = true; btn.textContent = '...';
  setStatus('"' + row.dataset.title.substring(0,40) + '..." düzeltiliyor...');
  try {
    const fd = new FormData();
    fd.append('post_id', id); fd.append('field', field);
    fd.append('title', row.dataset.title); fd.append('author', row.dataset.author);
    const res = await fetch(API('seo-fix.php'), {method:'POST', body:fd});
    const d   = await res.json();
    if (d.ok) {
      const p = allPosts.find(x => x.id === id);
      if (p && d.excerpt)          p.excerpt          = d.excerpt;
      if (p && d.meta_description) p.meta_description = d.meta_description;
      if (d.excerpt)          row.cells[1].innerHTML = '<span class="badge badge-ok">OK</span><div class="meta-cell">' + escH(d.excerpt) + '</div><div class="char-count">' + d.excerpt.length + ' kr</div>';
      if (d.meta_description) row.cells[2].innerHTML = '<span class="badge badge-ok">OK</span><div class="meta-cell">' + escH(d.meta_description) + '</div><div class="char-count">' + d.meta_description.length + ' kr</div>';
      row.cells[3].innerHTML = '<span style="color:#00ab6b;font-size:12px">✓</span>';
      setStatus('"' + row.dataset.title.substring(0,40) + '..." güncellendi ✓');
    } else { btn.disabled = false; btn.textContent = orig; setStatus('Hata: '+(d.error||'?')); }
  } catch(e) { btn.disabled = false; btn.textContent = orig; }
}

// ── Toplu düzelt ─────────────────────────────────────────
async function bulkFix(ff) {
  const toFix = allPosts.filter(p => {
    const iss = detectIssues(p);
    if (ff==='excerpt') return iss.some(i=>i.f==='excerpt');
    if (ff==='meta')    return iss.some(i=>i.f==='meta');
    return iss.length > 0;
  });
  if (!toFix.length) return;

  ['btn-fix-ex','btn-fix-mt','btn-fix-all','btn-scan'].forEach(id => { const b=document.getElementById(id); if(b)b.disabled=true; });
  const pw = document.getElementById('prog-wrap'); pw.style.display='block';
  const pb = document.getElementById('prog-bar');  pb.style.width='0%';

  let done = 0;
  for (const p of toFix) {
    setStatus('('+(done+1)+'/'+toFix.length+') '+p.title.substring(0,50));
    const iss  = detectIssues(p);
    const hasEx= iss.some(i=>i.f==='excerpt');
    const hasMt= iss.some(i=>i.f==='meta');
    const field= ff==='excerpt'?'excerpt': ff==='meta'?'meta': hasEx&&hasMt?'both':hasEx?'excerpt':'meta';

    let attempts = 0;
    while (attempts < 3) {
      try {
        const fd = new FormData();
        fd.append('post_id', p.id); fd.append('field', field);
        fd.append('title', p.title); fd.append('author', p.author_name||'');
        const res = await fetch(API('seo-fix.php'), {method:'POST', body:fd});
        const d   = await res.json();
        if (d.ok) {
          if (d.excerpt)          p.excerpt          = d.excerpt;
          if (d.meta_description) p.meta_description = d.meta_description;
          break;
        } else { attempts++; if(attempts<3) await sleep(5000); }
      } catch(e) { attempts++; if(attempts<3) await sleep(5000); }
    }
    done++;
    pb.style.width = Math.round(done/toFix.length*100)+'%';
    await sleep(2000);
  }

  renderTable();
  ['btn-fix-ex','btn-fix-mt','btn-fix-all','btn-scan'].forEach(id => { const b=document.getElementById(id); if(b)b.disabled=false; });
  pw.style.display='none';
  setStatus(done+' post güncellendi ✓');
}

document.getElementById('btn-fix-ex').addEventListener('click',  () => bulkFix('excerpt'));
document.getElementById('btn-fix-mt').addEventListener('click',  () => bulkFix('meta'));
document.getElementById('btn-fix-all').addEventListener('click', () => bulkFix('all'));

// ── Hızlı Düzelt (AI'sız, sunucu tarafı, TÜM yazılar) ──
document.getElementById('btn-fix-fast').addEventListener('click', () => {
  if (!confirm('Tüm yazıların yarım/uzun excerpt ve meta açıklamaları AI kullanmadan anlamlı cümlelere düzeltilecek.\n\nMetni tam cümleye kırpar; olmazsa yazının gövdesinden anlamlı bir cümle üretir. Devam edilsin mi?')) return;
  const ids = ['btn-fix-ex','btn-fix-mt','btn-fix-all','btn-fix-fast','btn-scan'];
  ids.forEach(id => { const b = document.getElementById(id); if (b) b.disabled = true; });
  const pw = document.getElementById('prog-wrap'), pb = document.getElementById('prog-bar');
  if (pw) pw.style.display = 'block';
  if (pb) pb.style.width = '0%';
  let totalFixed = 0;
  const run = (offset) => {
    fetch(API('seo-fix-sentences.php'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'run=1&offset=' + offset
    })
      .then(r => r.json())
      .then(d => {
        if (!d || !d.ok) throw new Error('bad');
        totalFixed += d.fixed;
        const pct = d.total ? Math.min(100, Math.round(d.processed / d.total * 100)) : 100;
        if (pb) pb.style.width = pct + '%';
        setStatus(d.processed + ' / ' + d.total + ' tarandı · ' + totalFixed + ' alan düzeltildi');
        if (d.done) {
          setStatus('✓ Bitti · ' + totalFixed + ' alan anlamlı cümleye getirildi. Yeniden taranıyor…');
          ids.forEach(id => { const b = document.getElementById(id); if (b) b.disabled = false; });
          setTimeout(() => document.getElementById('btn-scan').click(), 700);
          return;
        }
        run(d.next);
      })
      .catch(() => { setStatus('Hata — 3 sn sonra tekrar deneniyor…'); setTimeout(() => run(offset), 3000); });
  };
  run(0);
});

const sleep = ms => new Promise(r => setTimeout(r, ms));
function setStatus(m) { document.getElementById('status-line').textContent = m; }
function escH(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escA(s){ return String(s).replace(/"/g,'&quot;'); }
</script>
</body>
</html>
