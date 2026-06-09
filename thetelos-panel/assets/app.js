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

/* ── Token Slider ─────────────────────────────────── */
function tokenInfo(val) {
  const w = parseInt(val);
  const label = w <= 700 ? 'Çok kısa' : w <= 1500 ? 'Kısa' : w <= 3000 ? 'Orta' : w <= 5000 ? 'Uzun' : 'Çok uzun';
  return `${w.toLocaleString('tr')} kelime · ${label}`;
}
function updateTokenDisplay(val) {
  const el = document.getElementById('token-display');
  el.textContent = tokenInfo(val);
  el.style.color = parseInt(val) >= 3000 ? 'var(--green)' : 'var(--gold)';
  const pct = ((val - 500) / (8000 - 500)) * 100;
  document.getElementById('token-slider').style.background =
    `linear-gradient(90deg, var(--gold) ${pct}%, var(--border) ${pct}%)`;
}
function updateBulkTokenDisplay(val) {
  const el = document.getElementById('bulk-token-display');
  el.textContent = tokenInfo(val);
  el.style.color = parseInt(val) >= 3000 ? 'var(--green)' : 'var(--gold)';
  const pct = ((val - 500) / (8000 - 500)) * 100;
  document.getElementById('bulk-token-slider').style.background =
    `linear-gradient(90deg, var(--gold) ${pct}%, var(--border) ${pct}%)`;
}

/* ── Mod Geçişi ───────────────────────────────────── */
const modeTitles = {
  single: ['Tek Kitap', 'Kitap adı ve yazar girerek özet veya analiz üretin'],
  bulk:   ['Toplu Liste', 'CSV veya XLSX yükleyerek yüzlerce kitabı sırayla işleyin'],
};
document.querySelectorAll('.tab-top-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-top-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const mode = btn.dataset.mode;
    document.getElementById('mode-single').style.display = mode === 'single' ? '' : 'none';
    document.getElementById('mode-bulk').style.display   = mode === 'bulk'   ? '' : 'none';
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
  const providerLabel = isDeepSeek ? 'DeepSeek' : 'Claude';

  document.getElementById('single-result').style.display = '';
  document.getElementById('gen-stats').innerHTML = '';
  document.getElementById('preview-content').innerHTML =
    `<div class="loading-row"><span class="loader"></span> <span id="stream-status">${providerLabel} içerik üretiyor${isDeepSeek ? ' (Part 1/2)' : ''}...</span></div>`;
  document.getElementById('cover-card').style.display = 'none';

  // ── SSE stream fonksiyonu — tek bir generate.php çağrısı ────────
  function runStream(extraParams, onDone, onError) {
    const fd = new FormData();
    fd.append('book_title',   book);
    fd.append('author_name',  author);
    fd.append('type',         type);
    fd.append('max_tokens',   tokens);
    fd.append('api_provider', activeProvider);
    fd.append('api_model',    activeModel);
    if (extraParams) Object.entries(extraParams).forEach(([k,v]) => fd.append(k, v));

    let streamText = '';
    let buffer = '';

    fetch(API('generate.php'), {method:'POST', body:fd})
      .then(response => {
        const ct = response.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
          return response.json().then(data => onError(data.error || 'Hata'));
        }
        const reader  = response.body.getReader();
        const decoder = new TextDecoder();

        function read() {
          reader.read().then(({done, value}) => {
            if (done) {
              if (!streamText) onError('İçerik üretilemedi.');
              else onDone(streamText, {});
              return;
            }
            buffer += decoder.decode(value, {stream:true});
            const lines = buffer.split('\n');
            buffer = lines.pop();

            for (let i = 0; i < lines.length; i++) {
              const line = lines[i].trim();
              if (!line) continue;
              if (line.startsWith('event: ')) {
                const evName   = line.slice(7).trim();
                const dataLine = (lines[i+1] || '').trim();
                if (!dataLine.startsWith('data: ')) continue;
                let evData;
                try { evData = JSON.parse(dataLine.slice(6)); } catch(e) { i++; continue; }
                i++;

                if (evName === 'chunk') {
                  streamText += evData.text;
                  state.content = streamText;
                  document.getElementById('preview-content').innerHTML = md2html(streamText);
                  document.getElementById('preview-content').scrollTop = 9999;
                } else if (evName === 'status') {
                  const st = document.getElementById('stream-status');
                  if (st) st.textContent = evData.msg;
                } else if (evName === 'error') {
                  onError(evData.error);
                } else if (evName === 'done') {
                  onDone(streamText, evData);
                }
              }
            }
            read();
          }).catch(err => {
            if (streamText) onDone(streamText, {});
            else onError('Bağlantı kesildi: ' + err.message);
          });
        }
        read();
      })
      .catch(err => onError('Hata: ' + err.message));
  }

  // ── DeepSeek: iki parçalı üretim ────────────────────────────────
  if (isDeepSeek) {
    runStream({ part: '1' }, (part1Raw, stats1) => {
      // %%PART1_END%% işaretini temizle (varsa), Part 1 içeriğini al
      const part1 = part1Raw.replace(/%%PART1_END%%/g, '').trim();

      // Part 1'in tüm içeriğini bağlam olarak gönder (PHP H3 başlıklarını çıkaracak)
      const part1_tail = part1.slice(-300);

      // Part 2 başlıyor
      const st = document.getElementById('stream-status');
      if (st) st.textContent = 'DeepSeek içerik üretiyor (Part 2/2)...';

      // Part 2 preview'a eklenecek — mevcut içeriğin üzerine
      let combined = part1;
      document.getElementById('preview-content').innerHTML = md2html(combined);

      runStream({ part: '2', part1_content: part1 }, (part2Raw, stats2) => {
        // H1 ve H2 başlığını Part 2'den temizle (tekrar yazılmışsa)
        let part2 = part2Raw.replace(/^#[^\n]*\n/m, '').replace(/^##[^\n]*\n/m, '').trim();

        combined = part1 + '\n\n' + part2;

        // Tüm PART işaretlerini temizle
        combined = combined.replace(/%%PART[12]_END%%/g, '');
        combined = combined.replace(/%%PART[12]_START%%/g, '');

        // DeepSeek meta notlarını temizle
        combined = combined.replace(/\[Note:[^\]]*\]/gi, '');
        combined = combined.replace(/\[Already[^\]]*\]/gi, '');
        combined = combined.replace(/\[This[^\]]*\]/gi, '');
        combined = combined.replace(/\[.*?already.*?\]/gis, '');
        combined = combined.replace(/\[.*?covered.*?\]/gis, '');
        combined = combined.replace(/\[.*?Part 1.*?\]/gis, '');
        combined = combined.replace(/\[.*?structure.*?\]/gis, '');
        // Fazla boş satırları temizle
        combined = combined.replace(/\n{4,}/g, '\n\n\n').trim();
        state.content = combined;
        document.getElementById('preview-content').innerHTML = md2html(combined);

        const totalWords = (stats1.word_count || 0) + (stats2.word_count || 0)
          || combined.split(/\s+/).filter(Boolean).length;

        setLoading(btn, false);
        document.getElementById('gen-stats').innerHTML = `
          <div class="stats-bar">
            <div class="stat"><div class="stat-label">Kelime</div><div class="stat-value">${totalWords.toLocaleString('tr')}</div></div>
            <div class="stat"><div class="stat-label">Durum</div><div class="stat-value"><span class="badge badge-green">2/2 tamamlandı</span></div></div>
          </div>`;
        notify('gen-notif', `✓ İçerik hazır — ${totalWords.toLocaleString('tr')} kelime. Meta ve kategoriler yükleniyor...`, 'ok');
        fetchMeta(book, author, type, state.content);
      }, err => {
        // Part 2 başarısız — Part 1 ile devam et
        state.content = part1;
        setLoading(btn, false);
        notify('gen-notif', `⚠ Part 2 alınamadı, Part 1 kullanılıyor. Hata: ${err}`, 'warn');
        fetchMeta(book, author, type, state.content);
      });
    }, err => {
      setLoading(btn, false);
      notify('gen-notif', err, 'err');
      document.getElementById('single-result').style.display = 'none';
    });

  // ── Anthropic: tek parça üretim (değişmedi) ─────────────────────
  } else {
    runStream({}, (content, evData) => {
      state.content = content;
      setLoading(btn, false);
      if (evData.word_count) {
        document.getElementById('gen-stats').innerHTML = `
          <div class="stats-bar">
            <div class="stat"><div class="stat-label">Kelime</div><div class="stat-value">${(evData.word_count||0).toLocaleString('tr')}</div></div>
            <div class="stat"><div class="stat-label">Girdi Token</div><div class="stat-value">${(evData.input_tokens||0).toLocaleString()}</div></div>
            <div class="stat"><div class="stat-label">Çıktı Token</div><div class="stat-value">${(evData.output_tokens||0).toLocaleString()}</div></div>
            <div class="stat"><div class="stat-label">Durum</div><div class="stat-value" style="font-size:13px">
              <span class="badge ${evData.stop_reason==='end_turn'?'badge-green':evData.stop_reason==='max_tokens'?'badge-red':'badge-gold'}">${evData.stop_reason||'—'}</span>
            </div></div>
          </div>`;
      }
      notify('gen-notif', `✓ İçerik hazır — ${(evData.word_count || content.split(/\s+/).filter(Boolean).length || 0).toLocaleString('tr')} kelime. Meta ve kategoriler yükleniyor...`, 'ok');
      fetchMeta(book, author, type, state.content);
    }, err => {
      setLoading(btn, false);
      notify('gen-notif', err, 'err');
      document.getElementById('single-result').style.display = 'none';
    });
  }

  return;
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
  } catch(e) { /* ağ hatası, tekrar dene */ }
}

function renderResult(data) {
  // content zaten stream'den state.content'e yazıldı
  if (data.categories) state.categories = data.categories;

  const cacheInfo = (data.cache_read||0) > 0
    ? `<div style="font-size:12px;color:var(--green);margin-top:6px">⚡ Cache: ${data.cache_read.toLocaleString()} token tasarruf</div>`
    : '';
  const maxWarn = data.stop_reason === 'max_tokens'
    ? `<div style="background:#f0a04018;border:1px solid #f0a04055;border-radius:7px;padding:10px 14px;margin-top:10px;font-size:13px;color:#f0c070">
        ⚠ Token sınırına çarptı. Slider'ı artırın ve tekrar üretin.</div>` : '';

  document.getElementById('gen-stats').innerHTML = `
    <div class="stats-bar">
      <div class="stat"><div class="stat-label">Kelime</div><div class="stat-value">${(data.word_count||0).toLocaleString('tr')}</div></div>
      <div class="stat"><div class="stat-label">Girdi Token</div><div class="stat-value">${(data.input_tokens||0).toLocaleString()}</div></div>
      <div class="stat"><div class="stat-label">Çıktı Token</div><div class="stat-value">${(data.output_tokens||0).toLocaleString()}</div></div>
      <div class="stat"><div class="stat-label">Durum</div><div class="stat-value" style="font-size:13px">
        <span class="badge ${data.stop_reason==='end_turn'?'badge-green':data.stop_reason==='max_tokens'?'badge-red':'badge-gold'}">${data.stop_reason||'—'}</span>
      </div></div>
    </div>${cacheInfo}${maxWarn}`;

  if (data.covers && data.covers.length) {
    document.getElementById('cover-card').style.display = '';
    renderCovers(data.covers);
  }
  if (data.categories && data.categories.length) renderCatTags(data.categories);

  const excEl  = document.getElementById('field_excerpt');
  const metaEl = document.getElementById('field_meta_desc');
  if (excEl  && data.excerpt)          { excEl.value  = data.excerpt;          excEl.dispatchEvent(new Event('input')); }
  if (metaEl && data.meta_description) { metaEl.value = data.meta_description; metaEl.dispatchEvent(new Event('input')); }

  notify('gen-notif', `✓ Tamamlandı — ${(data.word_count||0).toLocaleString('tr')} kelime`, 'ok');
}

/* ── Meta & Kapak — ayrı çağrı ───────────────────── */
async function fetchMeta(book, author, type, content) {
  try {
    const res = await postData(API('get-meta.php'), {
      book_title: book, author_name: author, type,
      content: content.substring(0, 3000),
      api_provider: activeProvider,
      api_model:    activeModel
    });
    if (!res.ok) return;
    if (res.categories && res.categories.length) {
      state.categories = res.categories;
      renderCatTags(res.categories);
    }
    if (res.covers && res.covers.length) {
      document.getElementById('cover-card').style.display = '';
      renderCovers(res.covers);
    }
    if (res.quotes) state.quotes = res.quotes;
    const excEl  = document.getElementById('field_excerpt');
    const metaEl = document.getElementById('field_meta_desc');
    if (excEl  && res.excerpt)          { excEl.value  = res.excerpt;          excEl.dispatchEvent(new Event('input')); }
    if (metaEl && res.meta_description) { metaEl.value = res.meta_description; metaEl.dispatchEvent(new Event('input')); }
    notify('gen-notif', `✓ Tamamlandı — meta ve kategoriler eklendi`, 'ok');
  } catch(e) { }
}

/* ── Kapak Grid ───────────────────────────────────── */
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

/* ── Kategori Etiketleri ─────────────────────────── */
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

/* ── Publish ──────────────────────────────────────── */
document.getElementById('btn-publish')?.addEventListener('click', async () => {
  if (!state.content) { notify('gen-notif','Önce içerik üretin.','err'); return; }
  const btn  = document.getElementById('btn-publish');
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

/* ══ BULK ════════════════════════════════════════════ */
let bulkBooks = [];

const uploadZone = document.getElementById('upload-zone');
const fileInput  = document.getElementById('bulk-file');

uploadZone?.addEventListener('click', () => fileInput.click());
uploadZone?.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
uploadZone?.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
uploadZone?.addEventListener('drop', e => {
  e.preventDefault(); uploadZone.classList.remove('dragover');
  const f = e.dataTransfer.files[0]; if(f){ fileInput.files = e.dataTransfer.files; uploadFile(f); }
});
fileInput?.addEventListener('change', () => { if(fileInput.files[0]) uploadFile(fileInput.files[0]); });

async function uploadFile(file) {
  const fd = new FormData(); fd.append('bulk_file', file);
  uploadZone.innerHTML = '<div class="loading-row"><span class="loader"></span> Dosya okunuyor...</div>';
  const res = await fetch(API('bulk-upload.php'), {method:'POST', body:fd}).then(r=>r.json());
  if (!res.ok) { notify('bulk-notif', res.error, 'err'); resetUploadZone(); return; }
  bulkBooks = res.books;
  document.getElementById('btn-bulk-run').disabled = false;
  renderBulkTable(res.books);
  notify('bulk-notif', `✓ ${res.count} kitap yüklendi.`, 'ok');
}
function resetUploadZone() {
  uploadZone.innerHTML = '<div class="icon">📂</div><p>CSV veya XLSX dosyasını buraya sürükle ya da tıkla</p><p style="font-size:11px;margin-top:6px;color:#555">Format: <strong>Kitap Adı | Yazar</strong></p>';
}
function renderBulkTable(books) {
  resetUploadZone();
  document.getElementById('bulk-preview').innerHTML = `
    <div class="card">
      <div class="card-title">${books.length} kitap yüklendi</div>
      <table class="bulk-table">
        <thead><tr><th>#</th><th>Kitap</th><th>Yazar</th><th>Durum</th></tr></thead>
        <tbody>${books.map((b,i)=>`
          <tr id="brow-${i}"><td style="color:var(--muted)">${i+1}</td>
          <td>${b.book_title||'—'}</td><td>${b.author_name||'—'}</td>
          <td class="status-cell"><span class="badge badge-gray">Bekliyor</span></td></tr>`).join('')}
        </tbody>
      </table>
    </div>`;
}

document.getElementById('btn-bulk-run')?.addEventListener('click', async () => {
  if (!bulkBooks.length) { notify('bulk-notif','Önce dosya yükleyin.','err'); return; }
  const type   = document.querySelector('input[name=bulk_type]:checked')?.value || 'summary';
  const status = document.getElementById('bulk_post_status')?.value || 'draft';
  const tokens = document.getElementById('bulk-token-slider').value;
  const btn    = document.getElementById('btn-bulk-run');

  setLoading(btn, true, 'Çalışıyor...');
  document.getElementById('bulk-progress-wrap').style.display = '';

  let done=0, ok=0, fail=0;
  const total = bulkBooks.length;

  const delay = ms => new Promise(r => setTimeout(r, ms));

  for (const bk of bulkBooks) {
    const idx = bulkBooks.indexOf(bk);
    setRowStatus(idx, 'working', activeProvider === 'deepseek' ? 'Part 1 üretiliyor...' : 'İçerik üretiliyor...');

    let content = null;
    let attempts = 0;

    // Hata durumunda 1 kez retry yap
    while (attempts < 2 && content === null) {
      try {
        if (attempts > 0) {
          setRowStatus(idx, 'working', 'Tekrar deneniyor...');
          await delay(3000);
        }
        content = await bulkGenerateSSE(bk.book_title, bk.author_name, type, tokens);
      } catch(e) {
        attempts++;
        if (attempts >= 2) {
          done++; fail++;
          setRowStatus(idx, 'err', '✗ ' + e.message);
          content = 'FAILED';
        }
      }
    }

    if (content === 'FAILED') {
      const pct = Math.round((done/total)*100);
      document.getElementById('bulk-bar').style.width = pct + '%';
      document.getElementById('bulk-bar-label').textContent = `${done} / ${total} — %${pct}`;
      await delay(1000);
      continue;
    }

    try {
      setRowStatus(idx, 'working', 'Yayınlanıyor...');

      // 2. Meta
      const metaRes = await postData(API('get-meta.php'), {
        book_title: bk.book_title, author_name: bk.author_name, type,
        content: content.substring(0, 3000),
        api_provider: activeProvider,
        api_model:    activeModel
      });

      // 3. Publish
      const pubRes = await postData(API('publish.php'), {
        book_title:       bk.book_title,
        author_name:      bk.author_name,
        content:          content,
        type,
        post_status:      status,
        excerpt:          metaRes.excerpt          || '',
        meta_description: metaRes.meta_description || '',
        categories:       JSON.stringify(metaRes.categories || []),
        cover_url:        (metaRes.covers || [])[0]?.url || '',
        quotes:           JSON.stringify(metaRes.quotes || []),
      });

      done++;
      const pct = Math.round((done/total)*100);
      document.getElementById('bulk-bar').style.width = pct + '%';
      document.getElementById('bulk-bar-label').textContent = `${done} / ${total} — %${pct}`;

      if (pubRes.ok) {
        ok++;
        setRowStatus(idx, 'ok', `✓ <a href="${pubRes.edit_url}" target="_blank">#${pubRes.post_id}</a>${pubRes.cover_set?' 🖼':''}`);
      } else {
        fail++;
        setRowStatus(idx, 'err', '✗ ' + (pubRes.error||'Yayın hatası'));
      }
    } catch(e) {
      done++;
      fail++;
      setRowStatus(idx, 'err', '✗ ' + e.message);
    }

    // Kitaplar arası 2 saniye bekle — API rate limit için
    if (idx < total - 1) await delay(2000);
  }

  setLoading(btn, false);
  document.getElementById('bulk-summary').innerHTML =
    `<span class="badge badge-green">${ok} başarılı</span>&nbsp;<span class="badge badge-red">${fail} hatalı</span>`;
  notify('bulk-notif', `Tamamlandı: ${ok} başarılı, ${fail} hatalı`, fail?'warn':'ok');
});

// Bulk için SSE generate — DeepSeek'te iki parçalı, Anthropic'te tek parça
function bulkGenerateSSE(bookTitle, authorName, type, tokens) {

  // Tek bir SSE stream'i çalıştır ve içeriği döndür
  function runStream(extraParams) {
    return new Promise((resolve, reject) => {
      const fd = new FormData();
      fd.append('book_title',   bookTitle);
      fd.append('author_name',  authorName);
      fd.append('type',         type);
      fd.append('max_tokens',   tokens);
      fd.append('api_provider', activeProvider);
      fd.append('api_model',    activeModel);
      if (extraParams) Object.entries(extraParams).forEach(([k,v]) => fd.append(k, v));

      let content = '';
      fetch(API('generate.php'), {method:'POST', body:fd})
        .then(response => {
          const reader  = response.body.getReader();
          const decoder = new TextDecoder();
          let buffer = '';

          function read() {
            reader.read().then(({done, value}) => {
              if (done) { resolve(content); return; }
              buffer += decoder.decode(value, {stream:true});
              const lines = buffer.split('\n');
              buffer = lines.pop();
              for (let i = 0; i < lines.length; i++) {
                const line = lines[i].trim();
                if (!line) continue;
                if (line.startsWith('event: ')) {
                  const evName   = line.slice(7).trim();
                  const dataLine = (lines[i+1]||'').trim();
                  if (!dataLine.startsWith('data: ')) continue;
                  try {
                    const evData = JSON.parse(dataLine.slice(6));
                    if (evName === 'chunk') content += evData.text;
                    else if (evName === 'done')  { resolve(content); return; }
                    else if (evName === 'error') { reject(new Error(evData.error)); return; }
                  } catch(e) {}
                  i++;
                }
              }
              read();
            }).catch(e => { if (content) resolve(content); else reject(e); });
          }
          read();
        }).catch(reject);
    });
  }

  // DeepSeek: iki parçalı üretim
  if (activeProvider === 'deepseek') {
    return runStream({ part: '1' }).then(part1Raw => {
      const part1 = part1Raw.replace(/%%PART[12]_END%%/g, '').trim();
      return runStream({ part: '2', part1_content: part1 }).then(part2Raw => {
        let part2 = part2Raw.replace(/%%PART[12]_END%%/g, '').trim();
        // H1/H2 tekrarını temizle
        part2 = part2.replace(/^#[^\n]*\n+/m, '').replace(/^##[^\n]*\n+/m, '').trim();
        let combined = part1 + '\n\n' + part2;
        // Meta notları temizle
        combined = combined.replace(/\[Note:[^\]]*\]/gi, '');
        combined = combined.replace(/\[Already[^\]]*\]/gi, '');
        combined = combined.replace(/\[This[^\]]*\]/gi, '');
        combined = combined.replace(/\[.*?already.*?\]/gis, '');
        combined = combined.replace(/\[.*?covered.*?\]/gis, '');
        combined = combined.replace(/\[.*?Part 1.*?\]/gis, '');
        combined = combined.replace(/\[.*?structure.*?\]/gis, '');
        combined = combined.replace(/\n{4,}/g, '\n\n\n').trim();
        return combined;
      }).catch(() => part1); // Part 2 başarısız olursa Part 1 ile devam et
    });
  }

  // Anthropic: tek parça
  return runStream({});
}


async function waitForJob(job_id, idx) {
  return new Promise(resolve => {
    const timer = setInterval(async () => {
      try {
        const res = await fetch(API('check-job.php?job_id=') + job_id).then(r => r.json());
        if (!res.ok) return;
        const job = res.job;
        if (job.status === 'done' || job.status === 'error') {
          clearInterval(timer);
          resolve(job);
        }
      } catch(e) {}
    }, 4000);
  });
}

function setRowStatus(idx, st, html) {
  const cell = document.querySelector(`#brow-${idx} .status-cell`);
  if (!cell) return;
  const cls = st==='ok'?'badge-green':st==='err'?'badge-red':st==='working'?'badge-gold':'badge-gray';
  cell.innerHTML = `<span class="badge ${cls}">${html}</span>`;
}

/* ── Char counter ─────────────────────────────────── */
document.querySelectorAll('[data-maxlen]').forEach(el => {
  const max = +el.dataset.maxlen;
  const cnt = document.createElement('div'); cnt.className = 'char-count';
  el.parentNode.appendChild(cnt);
  const upd = () => { cnt.textContent=`${el.value.length}/${max}`; cnt.className='char-count'+(el.value.length>max*.9?' warn':''); };
  el.addEventListener('input', upd); upd();
});
