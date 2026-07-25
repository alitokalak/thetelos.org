<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Site SEO — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.tab-bar{display:flex;gap:4px;margin-bottom:20px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:4px}
.tab-btn{flex:1;padding:8px 16px;border:none;border-radius:6px;background:transparent;color:var(--muted);font-size:13px;font-weight:500;cursor:pointer;transition:.15s}
.tab-btn.active{background:var(--surface);color:var(--text);box-shadow:0 1px 3px rgba(0,0,0,.3)}
.site-table{width:100%;border-collapse:collapse;font-size:13px}
.site-table th{text-align:left;padding:10px 12px;border-bottom:2px solid var(--border);color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.site-table td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:top}
.site-table tr:hover td{background:rgba(255,255,255,.02)}
.name-cell{min-width:180px;font-weight:500}
.name-cell a{color:var(--tls-gold);text-decoration:none}
.name-cell small{display:block;color:var(--muted);font-size:11px;font-weight:400}
.meta-cell{max-width:340px;font-size:12px;line-height:1.5}
.meta-empty{color:var(--muted);font-style:italic;font-size:12px}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;margin-bottom:4px}
.badge-ok{background:rgba(0,171,107,.15);color:#00ab6b}
.badge-err{background:rgba(204,24,24,.15);color:#cc1818}
.badge-warn{background:rgba(212,180,131,.15);color:var(--tls-gold)}
.action-cell{width:120px;white-space:nowrap}
.btn-fix{display:block;width:100%;padding:5px 10px;font-size:11px;font-weight:600;border:none;border-radius:4px;cursor:pointer;background:rgba(212,180,131,.2);color:var(--tls-gold);transition:.15s}
.btn-fix:hover{background:rgba(212,180,131,.35)}
.btn-fix:disabled{opacity:.4;cursor:default}
.stats-row{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.stat-box{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 16px}
.stat-val{font-size:20px;font-weight:700}
.stat-lbl{font-size:11px;color:var(--muted);margin-top:2px}
.progress-wrap{background:var(--surface);border-radius:4px;height:4px;margin-bottom:12px;display:none}
.progress-inner{height:4px;background:var(--tls-gold);border-radius:4px;transition:width .3s}
#fix-status{font-size:12px;color:var(--tls-gold);margin-top:6px;min-height:16px}
.bulk-row{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap}
.tech-section{margin-bottom:24px}
.tech-section h3{font-size:14px;font-weight:600;margin-bottom:8px;color:var(--text)}
.tech-item{padding:8px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;margin-bottom:6px;font-size:13px}
.tech-item.ok{border-color:rgba(0,171,107,.3)}
.tech-item.warn{border-color:rgba(212,180,131,.3)}
.tech-item.err{border-color:rgba(204,24,24,.3)}
</style>
</head>
<body>
<div class="tls-shell">
  <aside class="tls-sidebar">
    <div class="tls-logo"><h1>Thetelos</h1><small>Content Panel</small></div>
    <nav class="tls-nav">
      <a href="panel.php"><span class="ico">✍</span> İçerik Üret</a>
      <a href="seo.php"><span class="ico">🔍</span> İçerik SEO</a>
      <a href="seo-site.php" class="active"><span class="ico">🌐</span> Site SEO</a>
      <a href="recategorize.php"><span class="ico">🗂️</span> Kategori Düzelt</a>
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
        <h2>Site SEO</h2>
        <p>Kategori, yazar sayfaları ve teknik SEO sorunlarını tara ve düzelt</p>
      </div>
    </div>

    <div class="tab-bar">
      <button class="tab-btn active" data-tab="categories">📁 Kategoriler</button>
      <button class="tab-btn" data-tab="authors">👤 Yazarlar</button>
      <button class="tab-btn" data-tab="technical">🔧 Teknik SEO</button>
      <button class="tab-btn" data-tab="keyphrase">🔑 Odak Kelime</button>
    </div>

    <div id="tab-categories">
      <div style="display:flex;gap:10px;margin-bottom:16px;align-items:center">
        <button class="btn btn-primary" id="btn-scan-cats">🔍 Kategorileri Tara</button>
        <span id="fix-status-cats" style="font-size:12px;color:var(--tls-gold)"></span>
      </div>
      <div class="stats-row" id="stats-cats" style="display:none">
        <div class="stat-box"><div class="stat-val" id="sc-total">0</div><div class="stat-lbl">Toplam</div></div>
        <div class="stat-box"><div class="stat-val" id="sc-ok" style="color:#00ab6b">0</div><div class="stat-lbl">Sorunsuz</div></div>
        <div class="stat-box"><div class="stat-val" id="sc-missing" style="color:#cc1818">0</div><div class="stat-lbl">Meta Eksik</div></div>
      </div>
      <div class="bulk-row" id="bulk-cats" style="display:none">
        <button class="btn" id="btn-fix-all-cats">⚡ Tüm Eksik Meta'ları Oluştur</button>
      </div>
      <div class="progress-wrap" id="prog-cats"><div class="progress-inner" id="prog-cats-bar" style="width:0%"></div></div>
      <div id="result-cats"><div style="text-align:center;padding:40px;color:var(--muted)">Tara butonuna bas.</div></div>
    </div>

    <div id="tab-authors" style="display:none">
      <div style="display:flex;gap:10px;margin-bottom:16px;align-items:center">
        <button class="btn btn-primary" id="btn-scan-authors">🔍 Yazarları Tara</button>
        <span id="fix-status-authors" style="font-size:12px;color:var(--tls-gold)"></span>
      </div>
      <div class="stats-row" id="stats-authors" style="display:none">
        <div class="stat-box"><div class="stat-val" id="sa-total">0</div><div class="stat-lbl">Toplam</div></div>
        <div class="stat-box"><div class="stat-val" id="sa-ok" style="color:#00ab6b">0</div><div class="stat-lbl">Sorunsuz</div></div>
        <div class="stat-box"><div class="stat-val" id="sa-missing" style="color:#cc1818">0</div><div class="stat-lbl">Meta Eksik</div></div>
      </div>
      <div class="bulk-row" id="bulk-authors" style="display:none">
        <button class="btn" id="btn-fix-all-authors">⚡ Tüm Eksik Meta'ları Oluştur</button>
      </div>
      <div class="progress-wrap" id="prog-authors"><div class="progress-inner" id="prog-authors-bar" style="width:0%"></div></div>
      <div id="result-authors"><div style="text-align:center;padding:40px;color:var(--muted)">Tara butonuna bas.</div></div>
    </div>

    <div id="tab-technical" style="display:none">
      <div style="display:flex;gap:10px;margin-bottom:16px">
        <button class="btn btn-primary" id="btn-scan-tech">🔍 Teknik Analiz Yap</button>
      </div>
      <div id="result-tech"><div style="text-align:center;padding:40px;color:var(--muted)">Tara butonuna bas.</div></div>

      <div class="tech-section" style="margin-top:28px;border-top:1px solid var(--border);padding-top:20px">
        <h3>👤 Yazarı Bağlanmamış Yazıları Onar</h3>
        <p style="font-size:12px;color:var(--muted);margin-bottom:12px">
          Toplu üretimde bazı yazılar yazarına bağlanmadan kalmış olabilir (yazar sayfasında görünmezler).
          Bu araç tüm yazıları tarar; yazarı eksik olanları başlıktaki “Kitap - Yazar” bilgisinden
          ilgili yazara bağlar. Yazar terimi yoksa oluşturur.
        </p>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <button class="btn btn-primary" id="btn-fix-authors-link">🔗 Yazarı Eksik Yazıları Tara &amp; Onar</button>
          <span id="fix-authors-link-status" style="font-size:12px;color:var(--tls-gold)"></span>
        </div>
        <div class="progress-wrap" id="prog-authors-link" style="margin-top:12px"><div class="progress-inner" id="prog-authors-link-bar" style="width:0%"></div></div>
        <div id="result-authors-link" style="margin-top:12px"></div>
      </div>

      <div class="tech-section" style="margin-top:28px;border-top:1px solid var(--border);padding-top:20px">
        <h3>🧹 Duplicate (Kopya) Yazıları Temizle</h3>
        <p style="font-size:12px;color:var(--muted);margin-bottom:12px">
          Aynı başlıkla iki kez yayınlanmış yazıları bulur. Her başlıktan <b>en eskisini korur</b>,
          kopyaları <b>Çöp Kutusu'na</b> taşır (kalıcı silmez — yanlışlık olursa geri alırsın).
          Önce "Bul"a bas, listeyi gör; sonra "Çöpe Taşı"yı onayla.
        </p>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <button class="btn btn-primary" id="btn-dup-scan">🔎 Kopyaları Bul</button>
          <button class="btn" id="btn-dup-clean" style="display:none">🗑️ Kopyaları Çöpe Taşı</button>
          <span id="dup-status" style="font-size:12px;color:var(--tls-gold)"></span>
        </div>
        <div id="result-dup" style="margin-top:12px"></div>
      </div>
    </div>

    <div id="tab-keyphrase" style="display:none">
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px;max-width:640px;line-height:1.6">
        Yoast'ın kırmızı/yeşil <b>SEO skoru</b> "odak anahtar kelime" üzerinden hesaplanır.
        Otomatik üretilen yazılara bu kelime atanmadığından hepsi kırmızı görünüyordu.
        Bu araç, odak kelimesi <b>boş olan</b> her yayınlanmış yazıya başlıktan türetilen
        temiz kitap adını yazar (ör. <code>On Wealth (Περὶ Πλούτου…) – Diogenes</code> →
        <b>On Wealth</b>). İçeriğe/başlığa dokunmaz, elle girdiklerini korur, tekrar çalıştırmak güvenlidir.
      </p>
      <div style="display:flex;gap:10px;margin-bottom:16px;align-items:center;flex-wrap:wrap">
        <button class="btn btn-primary" id="btn-kw-run">🔑 Odak Kelimeleri Doldur</button>
        <span id="kw-status" style="font-size:12px;color:var(--tls-gold)"></span>
      </div>
      <div class="stats-row" id="stats-kw" style="display:none">
        <div class="stat-box"><div class="stat-val" id="kw-total">0</div><div class="stat-lbl">Toplam Yazı</div></div>
        <div class="stat-box"><div class="stat-val" id="kw-updated" style="color:#00ab6b">0</div><div class="stat-lbl">Kelime Yazıldı</div></div>
      </div>
      <div class="progress-wrap" id="prog-kw"><div class="progress-inner" id="prog-kw-bar" style="width:0%"></div></div>
      <div id="result-kw"><div style="text-align:center;padding:40px;color:var(--muted)">Doldur butonuna bas.</div></div>
    </div>

  </main>
</div>

<script>
const API = p => 'api/' + p;

// ── Tab geçişi ────────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    ['categories','authors','technical','keyphrase'].forEach(t => {
      document.getElementById('tab-' + t).style.display = t === btn.dataset.tab ? '' : 'none';
    });
  });
});

// ── Kategorileri tara ────────────────────────────────────
let catData = [];
document.getElementById('btn-scan-cats').addEventListener('click', async () => {
  const btn = document.getElementById('btn-scan-cats');
  btn.disabled = true; btn.textContent = 'Taranıyor...';
  document.getElementById('result-cats').innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted)"><span class="loader"></span> Yükleniyor...</div>';

  try {
    const fd = new FormData(); fd.append('section','categories');
    const res  = await fetch(API('seo-site-scan.php'), {method:'POST',body:fd});
    const data = await res.json();
    catData = data.data || [];
    renderTermTable(catData, 'cats', 'category');
  } catch(e) {
    document.getElementById('result-cats').innerHTML = '<div style="color:#cc1818;padding:20px">Hata: ' + e.message + '</div>';
  } finally {
    btn.disabled = false; btn.textContent = '🔍 Kategorileri Tara';
  }
});

// ── Yazarları tara ───────────────────────────────────────
let authorData = [];
document.getElementById('btn-scan-authors').addEventListener('click', async () => {
  const btn = document.getElementById('btn-scan-authors');
  btn.disabled = true; btn.textContent = 'Taranıyor...';
  document.getElementById('result-authors').innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted)"><span class="loader"></span> Yükleniyor...</div>';

  try {
    const fd = new FormData(); fd.append('section','authors');
    const res  = await fetch(API('seo-site-scan.php'), {method:'POST',body:fd});
    const data = await res.json();
    authorData = data.data || [];
    renderTermTable(authorData, 'authors', 'author');
  } catch(e) {
    document.getElementById('result-authors').innerHTML = '<div style="color:#cc1818;padding:20px">Hata: ' + e.message + '</div>';
  } finally {
    btn.disabled = false; btn.textContent = '🔍 Yazarları Tara';
  }
});

// ── Teknik tara ──────────────────────────────────────────
document.getElementById('btn-scan-tech').addEventListener('click', async () => {
  const btn = document.getElementById('btn-scan-tech');
  btn.disabled = true; btn.textContent = 'Analiz ediliyor...';
  document.getElementById('result-tech').innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted)"><span class="loader"></span></div>';

  try {
    const fd = new FormData(); fd.append('section','technical');
    const res  = await fetch(API('seo-site-scan.php'), {method:'POST',body:fd});
    const data = await res.json();
    renderTechnical(data.data);
  } catch(e) {
    document.getElementById('result-tech').innerHTML = '<div style="color:#cc1818;padding:20px">Hata: ' + e.message + '</div>';
  } finally {
    btn.disabled = false; btn.textContent = '🔍 Teknik Analiz Yap';
  }
});

// ── Yazarı bağlanmamış yazıları onar ─────────────────────
document.getElementById('btn-fix-authors-link').addEventListener('click', async () => {
  const btn    = document.getElementById('btn-fix-authors-link');
  const status = document.getElementById('fix-authors-link-status');
  const progW  = document.getElementById('prog-authors-link');
  const progB  = document.getElementById('prog-authors-link-bar');
  const out    = document.getElementById('result-authors-link');
  btn.disabled = true; btn.textContent = 'Çalışıyor...';
  progW.style.display = 'block'; progB.style.width = '0%';
  out.innerHTML = '';

  let totalScanned = 0, totalFixed = 0;
  const fixedItems = [];

  for (const type of ['posts', 'analysis']) {
    let page = 1, totalPages = 1;
    do {
      status.textContent = `${type === 'posts' ? 'Özetler' : 'Analizler'} taranıyor — sayfa ${page}${totalPages>1?'/'+totalPages:''} · ${totalFixed} onarıldı`;
      let data;
      try {
        const fd = new FormData();
        fd.append('type', type); fd.append('page', page); fd.append('per_page', 20);
        const res = await fetch(API('fix-post-authors.php'), { method:'POST', body: fd });
        data = await res.json();
      } catch (e) {
        out.innerHTML += `<div style="color:#cc1818;padding:8px">Sayfa ${page} (${type}) hatası: ${e.message} — devam ediliyor</div>`;
        page++;
        continue;
      }
      if (!data.ok) {
        out.innerHTML += `<div style="color:#cc1818;padding:8px">${type} sayfa ${page}: ${data.error || 'hata'}</div>`;
        break;
      }
      totalScanned += data.scanned || 0;
      totalFixed   += data.fixed   || 0;
      (data.items || []).forEach(it => fixedItems.push(it));
      totalPages = data.total_pages || page;
      progB.style.width = Math.round(page / Math.max(totalPages,1) * 100) + '%';
      if ((data.scanned || 0) === 0 && page >= totalPages) break;
      page++;
    } while (page <= totalPages);
  }

  progB.style.width = '100%';
  status.textContent = `Bitti — ${totalScanned} yazı tarandı, ${totalFixed} yazı yazarına bağlandı.`;
  btn.disabled = false; btn.textContent = '🔗 Yazarı Eksik Yazıları Tara & Onar';

  if (!fixedItems.length) {
    out.innerHTML = '<div style="padding:16px;color:#00ab6b">✓ Tüm yazılar zaten yazarına bağlı — onarılacak bir şey yok.</div>';
    return;
  }
  let html = `<div style="margin-bottom:8px;font-size:13px;color:#00ab6b">✓ ${totalFixed} yazı onarıldı:</div>`;
  html += '<table class="site-table"><thead><tr><th>Yazı</th><th>Bağlanan Yazar</th></tr></thead><tbody>';
  fixedItems.forEach(it => {
    html += `<tr>
      <td class="name-cell"><a href="${it.link}" target="_blank">${escHtml(it.title)}</a></td>
      <td>${escHtml(it.author)}${it.created ? ' <span class="badge badge-warn">yeni yazar</span>' : ''}</td>
    </tr>`;
  });
  html += '</tbody></table>';
  out.innerHTML = html;
});

// ── Term tablosu render ──────────────────────────────────
function renderTermTable(items, prefix, type) {
  const total   = items.length;
  const missing = items.filter(i => i.issues.includes('meta_missing')).length;
  const ok      = total - missing;

  document.getElementById('s'+prefix[0]+'-total').textContent   = total;
  document.getElementById('s'+prefix[0]+'-ok').textContent      = ok;
  document.getElementById('s'+prefix[0]+'-missing').textContent = missing;
  document.getElementById('stats-' + prefix).style.display      = 'flex';
  document.getElementById('bulk-'  + prefix).style.display      = missing > 0 ? 'flex' : 'none';

  if (!items.length) {
    document.getElementById('result-' + prefix).innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted)">Veri bulunamadı.</div>';
    return;
  }

  let html = `<table class="site-table"><thead><tr>
    <th>${type === 'category' ? 'Kategori' : 'Yazar'}</th>
    <th>Mevcut Açıklama</th>
    <th>Meta Description</th>
    <th>İşlem</th>
  </tr></thead><tbody>`;

  items.forEach(item => {
    const hasMeta    = !item.issues.includes('meta_missing');
    const hasDesc    = !item.issues.includes('desc_missing');
    const metaBadge  = hasMeta
      ? '<span class="badge badge-ok">OK</span>'
      : '<span class="badge badge-err">Eksik</span>';

    html += `<tr data-id="${item.id}" data-name="${escAttr(item.name)}" data-type="${type}">
      <td class="name-cell">
        <a href="${item.link}" target="_blank">${escHtml(item.name)}</a>
        <small>${item.count} içerik</small>
      </td>
      <td class="meta-cell">
        ${hasDesc ? escHtml(item.desc.substring(0,120)) + (item.desc.length > 120 ? '...' : '') : '<span class="meta-empty">— boş —</span>'}
      </td>
      <td class="meta-cell">
        ${metaBadge}
        ${item.meta ? '<div style="margin-top:4px">' + escHtml(item.meta) + '</div>' : '<div class="meta-empty">— henüz yok —</div>'}
      </td>
      <td class="action-cell">
        ${!hasMeta ? `<button class="btn-fix" onclick="fixTerm(${item.id},'${type}',this)">${type === 'category' ? 'Meta Oluştur' : 'Meta Oluştur'}</button>` : ''}
      </td>
    </tr>`;
  });

  html += '</tbody></table>';
  document.getElementById('result-' + prefix).innerHTML = html;
}

// ── Teknik rapor render ──────────────────────────────────
function renderTechnical(data) {
  if (!data) { document.getElementById('result-tech').innerHTML = '<div style="color:#cc1818;padding:20px">Veri alınamadı.</div>'; return; }

  let html = '';

  // Duplicate titles
  html += '<div class="tech-section"><h3>🔁 Duplicate Başlıklar</h3>';
  if (!data.duplicate_titles || !data.duplicate_titles.length) {
    html += '<div class="tech-item ok">✓ Duplicate başlık bulunamadı.</div>';
  } else {
    data.duplicate_titles.forEach(d => {
      html += `<div class="tech-item err">❌ "${escHtml(d.post_title)}" — ${d.cnt} kez</div>`;
    });
  }
  html += '</div>';

  // Short content
  html += '<div class="tech-section"><h3>📏 Kısa İçerikler (2000 karakter altı)</h3>';
  if (!data.short_content || !data.short_content.length) {
    html += '<div class="tech-item ok">✓ Kısa içerik bulunamadı.</div>';
  } else {
    data.short_content.forEach(p => {
      html += `<div class="tech-item warn">⚠ <a href="${WP_URL}/?p=${p.ID}" target="_blank" style="color:var(--tls-gold)">${escHtml(p.post_title)}</a> — ${p.len} karakter</div>`;
    });
  }
  html += '</div>';

  document.getElementById('result-tech').innerHTML = html;
}

// ── Tek term düzelt ──────────────────────────────────────
async function fixTerm(termId, type, btn) {
  const row  = btn.closest('tr');
  const name = row.dataset.name;
  const orig = btn.textContent;
  btn.disabled = true; btn.textContent = '...';

  const statusEl = document.getElementById('fix-status-' + (type === 'category' ? 'cats' : 'authors'));
  if (statusEl) statusEl.textContent = '"' + name + '" düzeltiliyor...';

  try {
    const fd = new FormData();
    fd.append('term_id', termId);
    fd.append('type',    type);
    fd.append('name',    name);
    const res  = await fetch(API('seo-term-fix.php'), {method:'POST',body:fd});
    const data = await res.json();

    if (data.ok) {
      row.cells[2].innerHTML = '<span class="badge badge-ok">OK</span><div style="margin-top:4px;font-size:12px">' + escHtml(data.meta) + '</div>';
      row.cells[3].innerHTML = '<span style="color:#00ab6b;font-size:12px">✓</span>';
      if (statusEl) statusEl.textContent = '"' + name + '" güncellendi ✓';
    } else {
      btn.disabled = false; btn.textContent = orig;
      if (statusEl) statusEl.textContent = 'Hata: ' + (data.error||'');
    }
  } catch(e) {
    btn.disabled = false; btn.textContent = orig;
  }
}

// ── Tümünü düzelt ────────────────────────────────────────
async function bulkFixTerms(type) {
  const prefix  = type === 'category' ? 'cats' : 'authors';
  const data    = type === 'category' ? catData : authorData;
  const toFix   = data.filter(i => i.issues.includes('meta_missing'));
  if (!toFix.length) return;

  const fixBtn = document.getElementById('btn-fix-all-' + prefix);
  fixBtn.disabled = true; fixBtn.textContent = 'Düzeltiliyor...';
  const prog = document.getElementById('prog-' + prefix);
  const bar  = document.getElementById('prog-' + prefix + '-bar');
  const statusEl = document.getElementById('fix-status-' + prefix);
  prog.style.display = 'block'; bar.style.width = '0%';

  let done = 0;
  for (const item of toFix) {
    if (statusEl) statusEl.textContent = '(' + (done+1) + '/' + toFix.length + ') ' + item.name;
    try {
      const fd = new FormData();
      fd.append('term_id', item.id);
      fd.append('type',    type);
      fd.append('name',    item.name);
      const res  = await fetch(API('seo-term-fix.php'), {method:'POST',body:fd});
      const d    = await res.json();
      if (d.ok) item.meta = d.meta;
    } catch(e) {}
    done++;
    bar.style.width = Math.round(done/toFix.length*100) + '%';
    await new Promise(r => setTimeout(r, 1500));
  }

  renderTermTable(data, prefix, type);
  fixBtn.disabled = false; fixBtn.textContent = '⚡ Tüm Eksik Meta\'ları Oluştur';
  prog.style.display = 'none';
  if (statusEl) statusEl.textContent = done + ' güncellendi ✓';
}

document.getElementById('btn-fix-all-cats').addEventListener('click',    () => bulkFixTerms('category'));
document.getElementById('btn-fix-all-authors').addEventListener('click', () => bulkFixTerms('author'));

const WP_URL = '<?= rtrim(WP_URL, '/') ?>';
function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escAttr(s){ return String(s).replace(/"/g,'&quot;'); }

// ── Duplicate (kopya) temizleme ──────────────────────────
(function(){
  const scanBtn = document.getElementById('btn-dup-scan');
  if (!scanBtn) return;
  const cleanBtn = document.getElementById('btn-dup-clean');
  const statusEl = document.getElementById('dup-status');
  const result   = document.getElementById('result-dup');

  scanBtn.addEventListener('click', () => {
    scanBtn.disabled = true; scanBtn.textContent = 'Aranıyor…';
    cleanBtn.style.display = 'none'; statusEl.textContent = '';
    result.innerHTML = '<div style="color:var(--muted);padding:12px">Tüm yazılar taranıyor…</div>';
    fetch(API('cleanup-duplicates.php') + '?dry_run=1', { credentials:'same-origin' })
      .then(r => r.json())
      .then(d => {
        scanBtn.disabled = false; scanBtn.textContent = '🔎 Kopyaları Bul';
        const n = d.to_delete_count || 0;
        if (!n) { result.innerHTML = '<div style="color:#00ab6b;padding:16px">✓ Kopya bulunamadı. Temiz!</div>'; return; }
        let html = '<div style="margin-bottom:10px;font-size:13px"><b>' + (d.duplicate_groups||0)
          + '</b> başlıkta kopya var · <b>' + n + '</b> yazı çöpe taşınacak (en eskiler korunur).</div>';
        html += '<table class="site-table"><thead><tr><th>Çöpe taşınacak (kopya)</th><th>ID</th></tr></thead><tbody>';
        (d.to_delete||[]).forEach(p => { html += '<tr><td>' + escHtml(p.title) + '</td><td>' + p.id + '</td></tr>'; });
        html += '</tbody></table>';
        result.innerHTML = html;
        cleanBtn.style.display = ''; cleanBtn.textContent = '🗑️ ' + n + ' Kopyayı Çöpe Taşı';
      })
      .catch(() => { scanBtn.disabled = false; scanBtn.textContent = '🔎 Kopyaları Bul'; result.innerHTML = '<div style="color:#cc1818;padding:12px">Hata oluştu.</div>'; });
  });

  cleanBtn.addEventListener('click', () => {
    if (!confirm('Kopyalar Çöp Kutusu\'na taşınacak. Devam edilsin mi?')) return;
    cleanBtn.disabled = true; cleanBtn.textContent = 'Taşınıyor…';
    fetch(API('cleanup-duplicates.php') + '?dry_run=0', { credentials:'same-origin' })
      .then(r => r.json())
      .then(d => {
        cleanBtn.disabled = false;
        const ok = d.deleted_count || 0, fail = d.failed_count || 0;
        statusEl.textContent = '✓ ' + ok + ' kopya çöpe taşındı' + (fail ? (' · ' + fail + ' başarısız') : '');
        result.innerHTML = '<div style="color:#00ab6b;padding:16px">✓ ' + ok + ' kopya Çöp Kutusu\'na taşındı.'
          + (fail ? ' <span style="color:#cc1818">' + fail + ' tanesi taşınamadı.</span>' : '')
          + '<br><span style="color:var(--muted);font-size:12px">Yanlışlık olursa WP Admin → Yazılar → Çöp\'ten geri alabilirsin.</span></div>';
        cleanBtn.style.display = 'none';
      })
      .catch(() => { cleanBtn.disabled = false; cleanBtn.textContent = '🗑️ Kopyaları Çöpe Taşı'; statusEl.textContent = 'Hata oluştu.'; });
  });
})();

// ── Odak kelime toplu doldurma ───────────────────────────
(function(){
  const btn = document.getElementById('btn-kw-run');
  if (!btn) return;
  const statusEl = document.getElementById('kw-status');
  const stats    = document.getElementById('stats-kw');
  const totalEl  = document.getElementById('kw-total');
  const updEl    = document.getElementById('kw-updated');
  const prog     = document.getElementById('prog-kw');
  const bar      = document.getElementById('prog-kw-bar');
  const result   = document.getElementById('result-kw');
  let totalUpdated = 0;

  function step(offset){
    fetch(API('seo-backfill-keyphrase.php') + '?run=1&offset=' + offset, { credentials:'same-origin' })
      .then(r => r.json())
      .then(d => {
        if (!d || !d.ok) { statusEl.textContent = 'Hata — 3sn sonra tekrar…'; setTimeout(() => step(offset), 3000); return; }
        totalUpdated += d.updated;
        stats.style.display = '';
        totalEl.textContent = (d.total || 0).toLocaleString();
        updEl.textContent   = totalUpdated.toLocaleString();
        const pct = d.total ? Math.min(100, Math.round(d.processed / d.total * 100)) : 100;
        bar.style.width = pct + '%';
        statusEl.textContent = d.processed + ' / ' + d.total + ' tarandı';
        if (d.done) {
          statusEl.textContent = '✓ Bitti · ' + totalUpdated.toLocaleString() + ' yazıya odak kelime eklendi';
          result.innerHTML = '<div style="text-align:center;padding:32px;color:#00ab6b;font-size:14px">✓ Tamamlandı. Yoast skorlarının çoğu artık yeşile dönecek.</div>';
          btn.disabled = false; btn.textContent = '🔑 Tekrar Çalıştır';
          return;
        }
        step(d.next);
      })
      .catch(() => { statusEl.textContent = 'Bağlantı hatası — 3sn sonra tekrar…'; setTimeout(() => step(offset), 3000); });
  }

  btn.addEventListener('click', () => {
    btn.disabled = true; btn.textContent = 'Çalışıyor…';
    totalUpdated = 0; bar.style.width = '0%';
    result.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted)">İşleniyor…</div>';
    step(0);
  });
})();
</script>
</body>
</html>
