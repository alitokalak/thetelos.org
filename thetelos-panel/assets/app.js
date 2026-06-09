/* ── Thetelos Content Panel · app.js ── */
const API = p => 'api/' + p;

/* ── Yardımcılar ─────────────────────────────────── */
function notify(id, msg, type='ok') {
  const el = document.getElementById(id);
  if (!el) return;
  el.className = `notif notif-${type} show`;
  el.textContent = msg;
  if (type === 'ok') setTimeout(() => el.classList.remove('show'), 6000);
}
function setLoading(btn, state, label='') {
  btn.disabled = state;
  if (state) { btn._orig = btn.innerHTML; btn.innerHTML = `<span class="loader"></span> ${label||'İşleniyor...'}`; }
  else { btn.innerHTML = btn._orig || btn.innerHTML; }
}
async function postData(url, data) {
  const fd = new FormData();
  for (const [k,v] of Object.entries(data)) fd.append(k, v);
  return fetch(url, {method:'POST', body:fd}).then(r => r.json());
}
function md2html(text) {
  if (!text) return '';
  text = text.replace(/\r\n/g,'\n').replace(/\r/g,'\n');
  text = text.replace(/^#{1} \*\*(.+?)\*\*/gm,'<h1><strong>$1</strong></h1>');
  text = text.replace(/^#{2} \*\*(.+?)\*\*/gm,'<h2><strong>$1</strong></h2>');
  text = text.replace(/^#{3} \*\*(.+?)\*\*/gm,'<h3><strong>$1</strong></h3>');
  text = text.replace(/^# (.+)/gm,'<h1>$1</h1>');
  text = text.replace(/^## (.+)/gm,'<h2>$1</h2>');
  text = text.replace(/^### (.+)/gm,'<h3>$1</h3>');
  text = text.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>');
  const lines = text.split('\n'); let html='', buf=[];
  const flush = () => { if(buf.length){html+=`<p>${buf.join(' ')}</p>`;buf=[];} };
  for (const line of lines) {
    const l=line.trim();
    if(!l){flush();continue;}
    if(/^<(h[1-6]|hr)/.test(l)){flush();html+=l+'\n';continue;}
    buf.push(l);
  }
  flush(); return html;
}
const delay = ms => new Promise(r => setTimeout(r, ms));

/* ── Token Slider ─────────────────────────────────── */
function tokenInfo(val) {
  const w = parseInt(val);
  const label = w <= 700 ? 'Çok kısa' : w <= 1500 ? 'Kısa' : w <= 3000 ? 'Orta' : w <= 5000 ? 'Uzun' : 'Çok uzun';
  return `${w.toLocaleString('tr')} kelime · ${label}`;
}
function updateTokenDisplay(val) {
  const el = document.getElementById('token-display');
  if (!el) return;
  el.textContent = tokenInfo(val);
  el.style.color = parseInt(val) >= 3000 ? 'var(--green)' : 'var(--gold)';
  const pct = ((val - 500) / (8000 - 500)) * 100;
  document.getElementById('token-slider').style.background =
    `linear-gradient(90deg, var(--gold) ${pct}%, var(--border) ${pct}%)`;
}
function updateBulkTokenDisplay(val) {
  const el = document.getElementById('bulk-token-display');
  if (!el) return;
  el.textContent = tokenInfo(val);
  el.style.color = parseInt(val) >= 3000 ? 'var(--green)' : 'var(--gold)';
  const pct = ((val - 500) / (8000 - 500)) * 100;
  document.getElementById('bulk-token-slider').style.background =
    `linear-gradient(90deg, var(--gold) ${pct}%, var(--border) ${pct}%)`;
}

/* ── Mod Geçişi ───────────────────────────────────── */
const modeTitles = {
  single:  ['Tek Kitap',     'Kitap adı ve yazar girerek özet veya analiz üretin'],
  bulk:    ['Toplu Batch',   'CSV/XLSX yükleyerek binlerce kitabı sunucu taraflı paralel işleyin'],
  builder: ['Liste Oluştur', 'Kategori/yazar bazlı kitap listesi üret — LLM küratör + OpenLibrary doğrulama'],
};
document.querySelectorAll('.tab-top-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-top-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const mode = btn.dataset.mode;
    document.getElementById('mode-single').style.display  = mode === 'single'  ? '' : 'none';
    document.getElementById('mode-bulk').style.display    = mode === 'bulk'    ? '' : 'none';
    document.getElementById('mode-builder').style.display = mode === 'builder' ? '' : 'none';
    document.getElementById('page-title').textContent = modeTitles[mode][0];
    document.getElementById('page-desc').textContent  = modeTitles[mode][1];
  });
});

/* ══ TEK KİTAP ══════════════════════════════════════ */
let state = { content:'', categories:[], selectedCover:'', quotes:[] };
let pollTimer = null;

/* ── API Provider Toggle ─────────────────────────── */
let activeProvider = 'anthropic';
let activeModel    = 'claude-haiku-4-5-20251001';

const subGroup = document.getElementById('api-sub-anthropic');

function updateActiveLabel() {
  const label = activeProvider === 'deepseek'
    ? 'deepseek-chat'
    : document.querySelector('.api-sub-btn.active')?.dataset.label || 'haiku';
  document.getElementById('api-active-label').textContent = label;
}

document.querySelectorAll('.api-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.api-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    activeProvider = btn.dataset.provider;
    subGroup.style.display = activeProvider === 'anthropic' ? '' : 'none';
    if (activeProvider === 'deepseek') activeModel = 'deepseek-chat';
    updateActiveLabel();
  });
});

document.querySelectorAll('.api-sub-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.api-sub-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    activeModel = btn.dataset.model;
    updateActiveLabel();
  });
});

// DeepSeek'in bıraktığı meta notlarını ve PART işaretlerini temizle
function cleanGenerated(text) {
  return text
    .replace(/%%PART[0-9]*_(?:END|START)%%/gi, '')
    .replace(/%%PART_END%%/gi, '')
    .replace(/\[Note:[^\]]*\]/gi, '')
    .replace(/\[Already[^\]]*\]/gi, '')
    .replace(/\[.*?already.*?\]/gis, '')
    .replace(/\[.*?covered.*?\]/gis, '')
    .replace(/\[.*?Part \d.*?\]/gis, '')
    .replace(/\n{4,}/g, '\n\n\n')
    .trim();
}

// Tek bir generate.php SSE çağrısı — Promise döner, canlı önizleme için onLive çağırır
function runGenerateStream(params, onLive) {
  return new Promise((resolve, reject) => {
    const fd = new FormData();
    Object.entries(params).forEach(([k, v]) => fd.append(k, v));

    let streamText = '', buffer = '', stats = {};

    fetch(API('generate.php'), { method: 'POST', body: fd })
      .then(response => {
        const ct = response.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
          return response.json().then(data => reject(new Error(data.error || 'Hata')));
        }
        const reader = response.body.getReader();
        const decoder = new TextDecoder();

        function read() {
          reader.read().then(({ done, value }) => {
            if (done) {
              if (!streamText) reject(new Error('İçerik üretilemedi.'));
              else resolve({ text: streamText, stats });
              return;
            }
            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop();
            for (let i = 0; i < lines.length; i++) {
              const line = lines[i].trim();
              if (!line) continue;
              if (line.startsWith('event: ')) {
                const evName = line.slice(7).trim();
                const dataLine = (lines[i + 1] || '').trim();
                if (!dataLine.startsWith('data: ')) continue;
                let evData;
                try { evData = JSON.parse(dataLine.slice(6)); } catch (e) { i++; continue; }
                i++;
                if (evName === 'chunk') {
                  streamText += evData.text;
                  if (onLive) onLive(streamText);
                } else if (evName === 'status') {
                  const st = document.getElementById('stream-status');
                  if (st) st.textContent = evData.msg;
                } else if (evName === 'error') {
                  reject(new Error(evData.error));
                } else if (evName === 'done') {
                  stats = evData;
                }
              }
            }
            read();
          }).catch(err => {
            if (streamText) resolve({ text: streamText, stats });
            else reject(new Error('Bağlantı kesildi: ' + err.message));
          });
        }
        read();
      })
      .catch(err => reject(new Error('Hata: ' + err.message)));
  });
}

document.getElementById('btn-generate')?.addEventListener('click', async () => {
  const book   = document.getElementById('book_title').value.trim();
  const author = document.getElementById('author_name').value.trim();
  const type   = document.querySelector('input[name=type]:checked')?.value || 'summary';
  const tokens = document.getElementById('token-slider').value;

  if (!book || !author) { notify('gen-notif','Kitap adı ve yazar adı zorunludur.','err'); return; }

  const btn = document.getElementById('btn-generate');
  setLoading(btn, true, 'Üretiliyor...');
  document.getElementById('single-result').style.display = 'none';
  document.getElementById('publish-result').innerHTML = '';
  document.getElementById('gen-notif').classList.remove('show');
  document.getElementById('job-status-wrap').style.display = 'none';
  state = { content:'', categories:[], selectedCover:'', quotes:[] };

  const isDeepSeek = activeProvider === 'deepseek';
  const parts = isDeepSeek ? (parseInt(document.getElementById('parts-select')?.value) || 2) : 1;
  const preview = document.getElementById('preview-content');

  document.getElementById('single-result').style.display = '';
  document.getElementById('gen-stats').innerHTML = '';
  preview.innerHTML =
    `<div class="loading-row"><span class="loader"></span> <span id="stream-status">${isDeepSeek ? `DeepSeek içerik üretiyor (Part 1/${parts})` : 'Claude içerik üretiyor'}...</span></div>`;
  document.getElementById('cover-card').style.display = 'none';

  const baseParams = {
    book_title:   book,
    author_name:  author,
    type:         type,
    max_tokens:   tokens,
    api_provider: activeProvider,
    api_model:    activeModel,
  };

  try {
    let finalContent = '';
    let finalStats = {};

    if (isDeepSeek && parts > 1) {
      // ── Çok parçalı üretim ──────────────────────────────
      let accumulated = '';
      for (let k = 1; k <= parts; k++) {
        const st = document.getElementById('stream-status');
        if (st) st.textContent = `DeepSeek içerik üretiyor (Part ${k}/${parts})...`;

        const { text } = await runGenerateStream(
          { ...baseParams, part: k, parts: parts, prev_content: accumulated },
          (live) => {
            const merged = accumulated ? (accumulated + '\n\n' + live) : live;
            preview.innerHTML = md2html(cleanGenerated(merged));
            preview.scrollTop = 9999;
          }
        );

        let piece = cleanGenerated(text);
        if (k > 1) {
          // sonraki parçalardan kazara yazılan H1/H2 başlıklarını temizle
          piece = piece.replace(/^#[^\n]*\n+/m, '').replace(/^##[^\n]*\n+/m, '').trim();
        }
        accumulated = accumulated ? (accumulated + '\n\n' + piece) : piece;
        preview.innerHTML = md2html(accumulated);
      }
      finalContent = accumulated;

    } else {
      // ── Tek parça (Anthropic veya DeepSeek 1 parça) ─────
      const { text, stats } = await runGenerateStream(
        { ...baseParams },
        (live) => { preview.innerHTML = md2html(live); preview.scrollTop = 9999; }
      );
      finalContent = cleanGenerated(text);
      finalStats = stats || {};
    }

    state.content = finalContent;
    preview.innerHTML = md2html(finalContent);
    setLoading(btn, false);

    const totalWords = finalStats.word_count || finalContent.split(/\s+/).filter(Boolean).length;
    const partsBadge = (isDeepSeek && parts > 1)
      ? `<div class="stat"><div class="stat-label">Parça</div><div class="stat-value"><span class="badge badge-green">${parts}/${parts} tamamlandı</span></div></div>`
      : (finalStats.stop_reason
        ? `<div class="stat"><div class="stat-label">Durum</div><div class="stat-value" style="font-size:13px"><span class="badge ${finalStats.stop_reason==='end_turn'?'badge-green':finalStats.stop_reason==='max_tokens'?'badge-red':'badge-gold'}">${finalStats.stop_reason}</span></div></div>`
        : '');

    document.getElementById('gen-stats').innerHTML = `
      <div class="stats-bar">
        <div class="stat"><div class="stat-label">Kelime</div><div class="stat-value">${totalWords.toLocaleString('tr')}</div></div>
        ${finalStats.input_tokens ? `<div class="stat"><div class="stat-label">Girdi Token</div><div class="stat-value">${finalStats.input_tokens.toLocaleString()}</div></div>` : ''}
        ${finalStats.output_tokens ? `<div class="stat"><div class="stat-label">Çıktı Token</div><div class="stat-value">${finalStats.output_tokens.toLocaleString()}</div></div>` : ''}
        ${partsBadge}
      </div>`;
    notify('gen-notif', `✓ İçerik hazır — ${totalWords.toLocaleString('tr')} kelime. Meta yükleniyor...`, 'ok');
    fetchMeta(book, author, type, state.content);

  } catch (err) {
    setLoading(btn, false);
    notify('gen-notif', err.message || String(err), 'err');
    document.getElementById('single-result').style.display = 'none';
  }
});

async function pollJob(job_id, btn) {
  try {
    const res = await fetch(API('check-job.php?job_id=') + job_id).then(r => r.json());
    if (!res.ok) return;
    const job = res.job;
    const statusEl = document.getElementById('job-status-text');
    if (job.status === 'processing') {
      statusEl.textContent = 'Claude içerik yazıyor...';
    } else if (job.status === 'done') {
      clearInterval(pollTimer);
      document.getElementById('job-status-wrap').style.display = 'none';
      setLoading(btn, false);
      renderResult(job);
    } else if (job.status === 'error') {
      clearInterval(pollTimer);
      document.getElementById('job-status-wrap').style.display = 'none';
      setLoading(btn, false);
      notify('gen-notif', '✗ ' + job.error, 'err');
    }
  } catch(e) {}
}

function renderResult(data) {
  if (data.categories) state.categories = data.categories;
  const cacheInfo = (data.cache_read||0) > 0
    ? `<div style="font-size:12px;color:var(--green);margin-top:6px">⚡ Cache: ${data.cache_read.toLocaleString()} token tasarruf</div>`
    : '';
  document.getElementById('gen-stats').innerHTML = `
    <div class="stats-bar">
      <div class="stat"><div class="stat-label">Kelime</div><div class="stat-value">${(data.word_count||0).toLocaleString('tr')}</div></div>
      <div class="stat"><div class="stat-label">Girdi Token</div><div class="stat-value">${(data.input_tokens||0).toLocaleString()}</div></div>
      <div class="stat"><div class="stat-label">Çıktı Token</div><div class="stat-value">${(data.output_tokens||0).toLocaleString()}</div></div>
      <div class="stat"><div class="stat-label">Durum</div><div class="stat-value" style="font-size:13px">
        <span class="badge ${data.stop_reason==='end_turn'?'badge-green':data.stop_reason==='max_tokens'?'badge-red':'badge-gold'}">${data.stop_reason||'—'}</span>
      </div></div>
    </div>${cacheInfo}`;
  if (data.covers && data.covers.length) { document.getElementById('cover-card').style.display=''; renderCovers(data.covers); }
  if (data.categories && data.categories.length) renderCatTags(data.categories);
  const excEl  = document.getElementById('field_excerpt');
  const metaEl = document.getElementById('field_meta_desc');
  if (excEl  && data.excerpt)          { excEl.value  = data.excerpt;          excEl.dispatchEvent(new Event('input')); }
  if (metaEl && data.meta_description) { metaEl.value = data.meta_description; metaEl.dispatchEvent(new Event('input')); }
  notify('gen-notif', `✓ Tamamlandı — ${(data.word_count||0).toLocaleString('tr')} kelime`, 'ok');
}

async function fetchMeta(book, author, type, content) {
  try {
    const res = await postData(API('get-meta.php'), {
      book_title: book, author_name: author, type,
      content: content.substring(0, 3000),
      api_provider: activeProvider,
      api_model:    activeModel,
    });
    if (!res.ok) return;
    if (res.categories && res.categories.length) { state.categories = res.categories; renderCatTags(res.categories); }
    if (res.covers && res.covers.length) { document.getElementById('cover-card').style.display=''; renderCovers(res.covers); }
    if (res.quotes) state.quotes = res.quotes;
    const excEl  = document.getElementById('field_excerpt');
    const metaEl = document.getElementById('field_meta_desc');
    if (excEl  && res.excerpt)          { excEl.value  = res.excerpt;          excEl.dispatchEvent(new Event('input')); }
    if (metaEl && res.meta_description) { metaEl.value = res.meta_description; metaEl.dispatchEvent(new Event('input')); }
    notify('gen-notif', '✓ Meta ve kategoriler eklendi', 'ok');
  } catch(e) {}
}

function renderCovers(covers) {
  const grid = document.getElementById('cover-grid');
  if (!covers || !covers.length) {
    grid.innerHTML = '<p style="color:var(--muted);font-size:13px">Kapak bulunamadı. WP\'den manuel ekleyebilirsiniz.</p>';
    return;
  }
  grid.innerHTML = covers.map((c,i) => `
    <div class="cover-item ${i===0?'selected':''}" data-url="${c.url}" onclick="selectCover(this,'${c.url}')">
      <img src="${c.url}" alt="${c.title}" onerror="this.closest('.cover-item').style.display='none'">
      <div class="cover-meta">
        <div class="cover-title">${c.title||''}</div>
        <span class="cover-source badge badge-gray">${c.source}</span>
      </div>
    </div>`).join('');
  if (covers[0]) state.selectedCover = covers[0].url;
}
function selectCover(el, url) {
  document.querySelectorAll('.cover-item').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  state.selectedCover = url;
}

function renderCatTags(cats) {
  const el = document.getElementById('cat-tags');
  if (!cats || !cats.length) { el.innerHTML = '<span style="color:var(--muted);font-size:12px">Kategori tespit edilemedi</span>'; return; }
  el.innerHTML = cats.map(c => `
    <span class="cat-tag active" data-slug="${c}" onclick="toggleCat(this)">
      ${c.replace(/_/g,' ')} ✓
    </span>`).join('');
}
function toggleCat(el) {
  el.classList.toggle('active');
  const slug = el.dataset.slug;
  if (el.classList.contains('active')) {
    if (!state.categories.includes(slug)) state.categories.push(slug);
    el.innerHTML = slug.replace(/_/g,' ') + ' ✓';
  } else {
    state.categories = state.categories.filter(c => c !== slug);
    el.innerHTML = slug.replace(/_/g,' ');
  }
}

document.getElementById('btn-publish')?.addEventListener('click', async () => {
  if (!state.content) { notify('gen-notif','Önce içerik üretin.','err'); return; }
  const btn    = document.getElementById('btn-publish');
  const book   = document.getElementById('book_title').value.trim();
  const author = document.getElementById('author_name').value.trim();
  const type   = document.querySelector('input[name=type]:checked')?.value || 'summary';
  const status = document.getElementById('post_status')?.value || 'draft';
  const cats   = [...document.querySelectorAll('.cat-tag.active')].map(t => t.dataset.slug);
  setLoading(btn, true, 'Yayınlanıyor...');
  const res = await postData(API('publish.php'), {
    book_title: book, author_name: author, content: state.content, type,
    post_status: status,
    excerpt:          document.getElementById('field_excerpt')?.value  || '',
    meta_description: document.getElementById('field_meta_desc')?.value || '',
    categories:  JSON.stringify(cats),
    cover_url:   state.selectedCover || '',
    quotes:      JSON.stringify(state.quotes || []),
  });
  setLoading(btn, false);
  if (!res.ok) { notify('gen-notif', res.error, 'err'); return; }
  document.getElementById('publish-result').innerHTML = `
    <div class="card">
      <div style="color:var(--green);font-size:16px;font-weight:600;margin-bottom:12px">✓ Yayınlandı</div>
      <div style="font-size:13px;color:var(--muted);display:flex;gap:16px;flex-wrap:wrap">
        <span>Post <strong style="color:var(--text)">#${res.post_id}</strong></span>
        <span>Kapak: <span class="badge ${res.cover_set?'badge-green':'badge-gray'}">${res.cover_set?'✓ Eklendi':'—'}</span></span>
        <span>Kategoriler: <span class="badge badge-gold">${(res.categories||[]).length} adet</span></span>
      </div>
      <div style="display:flex;gap:10px;margin-top:14px">
        <a class="btn btn-ghost btn-sm" href="${res.post_url}"  target="_blank">↗ Siteyi Gör</a>
        <a class="btn btn-ghost btn-sm" href="${res.edit_url}" target="_blank">✏ WP'de Düzenle</a>
      </div>
    </div>`;
  notify('gen-notif', '✓ Post oluşturuldu!', 'ok');
});

document.getElementById('btn-reset')?.addEventListener('click', () => {
  document.getElementById('single-result').style.display = 'none';
  document.getElementById('job-status-wrap').style.display = 'none';
  document.getElementById('publish-result').innerHTML = '';
  document.getElementById('book_title').value = '';
  document.getElementById('author_name').value = '';
  document.getElementById('gen-notif').classList.remove('show');
  if (pollTimer) clearInterval(pollTimer);
  state = { content:'', categories:[], selectedCover:'', quotes:[] };
});

/* ── Char counter ─────────────────────────────────── */
document.querySelectorAll('[data-maxlen]').forEach(el => {
  const max = +el.dataset.maxlen;
  const cnt = document.createElement('div'); cnt.className = 'char-count';
  el.parentNode.appendChild(cnt);
  const upd = () => { cnt.textContent=`${el.value.length}/${max}`; cnt.className='char-count'+(el.value.length>max*.9?' warn':''); };
  el.addEventListener('input', upd); upd();
});

/* ══ TOPLU BATCH ═════════════════════════════════════ */
let batchBooks    = [];   // merged book list from all uploaded files
let batchId       = null;
let batchRunning  = false;
let batchPaused   = false;
let uploadedFiles = [];

const uploadZone = document.getElementById('upload-zone');
const fileInput  = document.getElementById('bulk-file');

uploadZone?.addEventListener('click', () => fileInput.click());
uploadZone?.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
uploadZone?.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
uploadZone?.addEventListener('drop', e => {
  e.preventDefault(); uploadZone.classList.remove('dragover');
  const files = [...e.dataTransfer.files];
  files.forEach(f => uploadFile(f));
});
fileInput?.addEventListener('change', () => {
  [...fileInput.files].forEach(f => uploadFile(f));
  fileInput.value = '';
});

document.getElementById('btn-add-more')?.addEventListener('click', () => fileInput.click());

document.getElementById('btn-clear-list')?.addEventListener('click', () => {
  batchBooks = [];
  uploadedFiles = [];
  renderBulkTable([]);
  updateBatchBadge();
  document.getElementById('btn-batch-start').disabled = true;
  document.getElementById('file-list').innerHTML = '';
  document.getElementById('upload-actions').style.display = 'none';
  notify('bulk-notif', 'Liste temizlendi.', 'ok');
});

async function uploadFile(file) {
  const fd = new FormData();
  fd.append('bulk_file', file);
  const res = await fetch(API('bulk-upload.php'), {method:'POST', body:fd}).then(r=>r.json());
  if (!res.ok) { notify('bulk-notif', res.error, 'err'); return; }

  // Listeye ekle (dedup by title+author)
  const existing = new Set(batchBooks.map(b => (b.book_title + '||' + b.author_name).toLowerCase()));
  let added = 0;
  for (const bk of res.books) {
    const key = (bk.book_title + '||' + bk.author_name).toLowerCase();
    if (!existing.has(key)) { batchBooks.push(bk); existing.add(key); added++; }
  }

  uploadedFiles.push(file.name);
  updateFileList();
  updateBatchBadge();
  renderBulkTable(batchBooks);
  document.getElementById('btn-batch-start').disabled = false;
  document.getElementById('upload-actions').style.display = 'flex';
  notify('bulk-notif', `✓ ${file.name}: ${added} kitap eklendi. Toplam: ${batchBooks.length}`, 'ok');
}

function updateFileList() {
  const el = document.getElementById('file-list');
  if (!el) return;
  el.innerHTML = uploadedFiles.map(f => `<span style="margin-right:10px">📄 ${f}</span>`).join('');
}

function updateBatchBadge() {
  const badge = document.getElementById('batch-total-badge');
  if (!badge) return;
  if (batchBooks.length > 0) {
    badge.textContent = batchBooks.length.toLocaleString('tr') + ' kitap';
    badge.style.display = '';
  } else {
    badge.style.display = 'none';
  }
}

function renderBulkTable(books) {
  const preview = document.getElementById('bulk-preview');
  if (!preview) return;
  if (!books.length) { preview.innerHTML = ''; return; }
  preview.innerHTML = `
    <div class="card">
      <div class="card-title">${books.length.toLocaleString('tr')} kitap listesi</div>
      <div style="overflow-x:auto">
      <table class="bulk-table">
        <thead><tr><th>#</th><th>Kitap</th><th>Yazar</th><th>Durum</th></tr></thead>
        <tbody>${books.map((b,i)=>`
          <tr id="brow-${i}"><td style="color:var(--muted)">${i+1}</td>
          <td>${b.book_title||'—'}</td><td>${b.author_name||'—'}</td>
          <td class="status-cell"><span class="badge badge-gray">Bekliyor</span></td></tr>`).join('')}
        </tbody>
      </table>
      </div>
    </div>`;
}

/* ── Batch Başlat ─────────────────────────────────── */
document.getElementById('btn-batch-start')?.addEventListener('click', async () => {
  if (!batchBooks.length) { notify('bulk-notif','Önce dosya yükleyin.','err'); return; }
  if (batchRunning) return;

  const type        = document.querySelector('input[name=bulk_type]:checked')?.value || 'summary';
  const status      = document.getElementById('bulk_post_status')?.value || 'draft';
  const tokens      = document.getElementById('bulk-token-slider').value;
  const workerCount = parseInt(document.getElementById('bulk_workers')?.value || '3');
  const parts       = parseInt(document.getElementById('bulk-parts-select')?.value || '2');
  const btn         = document.getElementById('btn-batch-start');

  setLoading(btn, true, 'Batch oluşturuluyor...');

  // Sunucuda batch oluştur
  const res = await postData(API('batch-create.php'), {
    books:        JSON.stringify(batchBooks),
    type,
    post_status:  status,
    max_tokens:   tokens,
    api_provider: activeProvider,
    parts:        parts,
  });

  if (!res.ok) {
    setLoading(btn, false);
    notify('bulk-notif', res.error, 'err');
    return;
  }

  batchId      = res.batch_id;
  batchRunning = true;
  batchPaused  = false;

  document.getElementById('batch-progress-wrap').style.display = '';
  document.getElementById('btn-batch-pause').style.display = '';
  setLoading(btn, false);
  btn.disabled = true;
  btn.innerHTML = '✓ Batch Çalışıyor';

  notify('bulk-notif', `✓ Batch oluşturuldu (${res.total} kitap). ${workerCount} worker başlatılıyor...`, 'ok');

  // N paralel worker başlat
  const workers = Array.from({length: workerCount}, (_, i) => runBatchWorker(i + 1, workerCount));
  await Promise.all(workers);

  // Tamamlandı
  batchRunning = false;
  document.getElementById('btn-batch-pause').style.display = 'none';
  document.getElementById('batch-status-badge').textContent = 'Tamamlandı';
  document.getElementById('batch-status-badge').className = 'badge badge-green';
  btn.disabled = false;
  btn.innerHTML = '▶ Yeni Batch Başlat';

  // Final durum
  await updateBatchUI();
  notify('bulk-notif', 'Batch tamamlandı!', 'ok');
});

/* ── Duraklat / Devam ─────────────────────────────── */
document.getElementById('btn-batch-pause')?.addEventListener('click', () => {
  batchPaused = !batchPaused;
  const btn = document.getElementById('btn-batch-pause');
  btn.innerHTML = batchPaused ? '▶ Devam Et' : '⏸ Duraklat';
  document.getElementById('batch-status-badge').textContent = batchPaused ? 'Duraklatıldı' : 'Çalışıyor';
  document.getElementById('batch-status-badge').className = batchPaused ? 'badge badge-gray' : 'badge badge-gold';
  notify('bulk-notif', batchPaused ? 'Batch duraklatıldı. Devam için tekrar tıkla.' : 'Batch devam ediyor.', 'ok');
});

/* ── Worker ───────────────────────────────────────── */
async function runBatchWorker(workerNum, total) {
  const statusWrap = document.getElementById('batch-worker-status');
  const id = `worker-lbl-${workerNum}`;

  if (statusWrap && !document.getElementById(id)) {
    const span = document.createElement('span');
    span.id = id;
    span.textContent = `W${workerNum}: Başlatılıyor...`;
    statusWrap.appendChild(span);
  }

  const setW = txt => {
    const el = document.getElementById(id);
    if (el) el.textContent = `W${workerNum}: ${txt}`;
  };

  while (batchRunning) {
    // Duraklat kontrolü
    if (batchPaused) {
      setW('Bekliyor...');
      await delay(2000);
      continue;
    }

    setW('İşleniyor...');

    try {
      const res = await postData(API('batch-worker.php'), { batch_id: batchId });

      if (res.status === 'no_more' || res.status === 'cancelled') {
        setW('Bitti ✓');
        break;
      }
      if (res.status === 'retry') {
        setW('Kilit bekleniyor...');
        await delay(800);
        continue;
      }
      if (res.status === 'error') {
        setW('Hata: ' + (res.error || '?'));
        await delay(2000);
        continue;
      }

      // 'done' — UI güncelle
      const label = res.ok
        ? `✓ #${res.post_id}${res.cover_set?' 🖼':''}`
        : (res.error === 'duplicate' ? '⊘ Mevcut' : '✗ Hata');
      setW(label);
      await updateBatchUI();

    } catch(e) {
      setW('Ağ hatası — tekrar deniyor...');
      await delay(3000);
    }
  }

  setW('Bitti');
}

/* ── Batch UI Güncelle ────────────────────────────── */
async function updateBatchUI() {
  if (!batchId) return;
  try {
    const res = await fetch(API('batch-status.php?batch_id=') + batchId).then(r => r.json());
    if (!res.ok) return;
    const b = res.batch;

    const pct = b.total > 0 ? Math.round((b.done / b.total) * 100) : 0;
    document.getElementById('bulk-bar').style.width = pct + '%';
    document.getElementById('bulk-bar-label').textContent =
      `${b.done.toLocaleString('tr')} / ${b.total.toLocaleString('tr')} — %${pct}`;

    document.getElementById('bulk-summary').innerHTML =
      `<span class="badge badge-green">✓ ${b.ok} başarılı</span>&nbsp;`
      + `<span class="badge badge-red">✗ ${b.failed} hatalı</span>&nbsp;`
      + (b.total - b.done > 0 ? `<span class="badge badge-gray">⏳ ${(b.total-b.done).toLocaleString('tr')} bekliyor</span>` : '');

    // Satır durumlarını güncelle
    b.books.forEach((bk, i) => {
      const st  = bk.status;
      const cls = st==='done'?'ok':st==='error'?'err':st==='duplicate'?'gray':st==='processing'?'working':'gray';
      const lbl = st==='done'
        ? `✓ <a href="${bk.edit_url}" target="_blank">#${bk.post_id}</a>${bk.cover_set?' 🖼':''}`
        : st==='error'     ? '✗ ' + (bk.error||'Hata')
        : st==='duplicate' ? '⊘ Zaten var'
        : st==='processing'? 'İşleniyor...'
        : 'Bekliyor';
      setRowStatus(i, cls, lbl);
    });
  } catch(e) {}
}

function setRowStatus(idx, st, html) {
  const cell = document.querySelector(`#brow-${idx} .status-cell`);
  if (!cell) return;
  const cls = st==='ok'?'badge-green':st==='err'?'badge-red':st==='working'?'badge-gold':'badge-gray';
  cell.innerHTML = `<span class="badge ${cls}">${html}</span>`;
}

/* ══ LİSTE OLUŞTUR (Builder) ═════════════════════════════ */
let builderAuthors = [];   // [{author, era, note}]
let builderList    = [];   // [{title, author, year, verified, cover}]

// Mod geçişi: yazara göre / kategoriye göre
document.querySelectorAll('input[name=builder_mode]').forEach(r => {
  r.addEventListener('change', () => {
    const m = document.querySelector('input[name=builder_mode]:checked')?.value;
    document.getElementById('builder-author-box').style.display   = m === 'author'   ? '' : 'none';
    document.getElementById('builder-category-box').style.display = m === 'category' ? '' : 'none';
  });
});

// Tek yazarın eserlerini getir
document.getElementById('btn-fetch-works')?.addEventListener('click', async () => {
  const author = document.getElementById('builder-author').value.trim();
  if (!author) { notify('builder-notif','Yazar adı girin.','err'); return; }
  const btn = document.getElementById('btn-fetch-works');
  setLoading(btn, true, 'Eserler getiriliyor...');
  const added = await fetchAuthorWorks(author);
  setLoading(btn, false);
  if (added >= 0) notify('builder-notif', `✓ ${author}: ${added} eser eklendi.`, 'ok');
});

// Kategorinin yazarlarını getir
document.getElementById('btn-fetch-authors')?.addEventListener('click', async () => {
  const category = document.getElementById('builder-category').value.trim();
  const count    = document.getElementById('builder-author-count').value || 40;
  if (!category) { notify('builder-notif','Kategori girin.','err'); return; }
  const btn = document.getElementById('btn-fetch-authors');
  setLoading(btn, true, 'Yazarlar getiriliyor...');
  const res = await postData(API('list-authors.php'), {
    category, count, api_provider: activeProvider,
  });
  setLoading(btn, false);
  if (!res.ok) { notify('builder-notif', res.error, 'err'); return; }
  builderAuthors = res.authors;
  renderBuilderAuthors();
  notify('builder-notif', `✓ ${res.count} yazar getirildi. İstediğinin eserlerini getir.`, 'ok');
});

function renderBuilderAuthors() {
  document.getElementById('builder-authors-card').style.display = '';
  document.getElementById('builder-authors-count').textContent = `(${builderAuthors.length})`;
  const tb = document.querySelector('#builder-authors-table tbody');
  tb.innerHTML = builderAuthors.map((a,i) => `
    <tr id="bauthor-${i}">
      <td style="color:var(--muted)">${i+1}</td>
      <td><strong>${a.author}</strong></td>
      <td style="color:var(--muted);font-size:12px">${a.era||''}</td>
      <td style="color:var(--muted);font-size:12px">${a.note||''}</td>
      <td><button class="btn btn-ghost btn-sm" onclick="fetchOneAuthor(${i})">📚 Eserleri</button></td>
    </tr>`).join('');
}

window.fetchOneAuthor = async function(i) {
  const a = builderAuthors[i];
  if (!a) return;
  const cell = document.querySelector(`#bauthor-${i} td:last-child`);
  if (cell) cell.innerHTML = '<span class="loader"></span>';
  const added = await fetchAuthorWorks(a.author);
  if (cell) cell.innerHTML = `<span class="badge badge-green">+${added}</span>`;
};

// "Tüm yazarların eserlerini getir" — sırayla
document.getElementById('btn-fetch-all-works')?.addEventListener('click', async () => {
  const btn = document.getElementById('btn-fetch-all-works');
  setLoading(btn, true, 'Hepsi getiriliyor...');
  for (let i = 0; i < builderAuthors.length; i++) {
    const cell = document.querySelector(`#bauthor-${i} td:last-child`);
    if (cell) cell.innerHTML = '<span class="loader"></span>';
    const added = await fetchAuthorWorks(builderAuthors[i].author);
    if (cell) cell.innerHTML = `<span class="badge badge-green">+${added}</span>`;
  }
  setLoading(btn, false);
  notify('builder-notif', `✓ Tamamlandı — toplam ${builderList.length} kitap.`, 'ok');
});

// Bir yazarın eserlerini çek ve listeye ekle (dedup)
async function fetchAuthorWorks(author) {
  const verify = document.getElementById('builder-verify').checked ? '1' : '0';
  try {
    const res = await postData(API('list-works.php'), {
      author, api_provider: activeProvider, verify,
    });
    if (!res.ok) { notify('builder-notif', res.error, 'err'); return 0; }
    const existing = new Set(builderList.map(b => (b.title+'||'+b.author).toLowerCase()));
    let added = 0;
    for (const w of res.works) {
      const key = (w.title+'||'+author).toLowerCase();
      if (existing.has(key)) continue;
      existing.add(key);
      builderList.push({
        title: w.title, author: author, year: w.year || '',
        verified: w.verified, cover: w.cover || '',
      });
      added++;
    }
    renderBuilderList();
    return added;
  } catch(e) {
    notify('builder-notif', 'Hata: ' + e.message, 'err');
    return 0;
  }
}

function renderBuilderList() {
  document.getElementById('builder-list-card').style.display = builderList.length ? '' : 'none';
  document.getElementById('builder-list-count').textContent = `${builderList.length} kitap`;
  const vCount = builderList.filter(b => b.verified).length;
  const vBadge = document.getElementById('builder-list-verified');
  if (vCount > 0) { vBadge.style.display = ''; vBadge.textContent = `✓ ${vCount} doğrulandı`; }
  else vBadge.style.display = 'none';

  const tb = document.querySelector('#builder-list-table tbody');
  tb.innerHTML = builderList.map((b,i) => `
    <tr id="brow-list-${i}">
      <td style="color:var(--muted)">${i+1}</td>
      <td>${b.cover ? `<img src="${b.cover}" style="width:32px;height:46px;object-fit:cover;border-radius:3px" onerror="this.style.display='none'">` : '—'}</td>
      <td>${b.title}</td>
      <td style="color:var(--muted)">${b.author}</td>
      <td style="color:var(--muted)">${b.year||''}</td>
      <td>${b.verified ? '<span class="badge badge-green">✓</span>' : '<span class="badge badge-gray">?</span>'}</td>
      <td><button class="btn btn-ghost btn-sm" onclick="removeBuilderRow(${i})" style="color:var(--red)">✕</button></td>
    </tr>`).join('');
}

window.removeBuilderRow = function(i) {
  builderList.splice(i, 1);
  renderBuilderList();
};

// Toplu Batch'e aktar
document.getElementById('btn-builder-to-batch')?.addEventListener('click', () => {
  if (!builderList.length) { notify('builder-notif','Liste boş.','err'); return; }
  batchBooks = builderList.map(b => ({ book_title: b.title, author_name: b.author, category: '' }));
  updateBatchBadge();
  renderBulkTable(batchBooks);
  document.getElementById('btn-batch-start').disabled = false;
  // Toplu Batch sekmesine geç
  document.querySelector('.tab-top-btn[data-mode=bulk]')?.click();
  notify('bulk-notif', `✓ ${batchBooks.length} kitap batch'e aktarıldı. Ayarları seçip başlat.`, 'ok');
});

// CSV indir
document.getElementById('btn-builder-csv')?.addEventListener('click', () => {
  if (!builderList.length) { notify('builder-notif','Liste boş.','err'); return; }
  let csv = 'Kitap Adı,Yazar Adı,Yıl\n';
  csv += builderList.map(b =>
    `"${(b.title||'').replace(/"/g,'""')}","${(b.author||'').replace(/"/g,'""')}","${b.year||''}"`
  ).join('\n');
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'thetelos-liste.csv';
  a.click();
});

// Listeyi temizle
document.getElementById('btn-builder-clear')?.addEventListener('click', () => {
  builderList = [];
  renderBuilderList();
  notify('builder-notif', 'Liste temizlendi.', 'ok');
});
