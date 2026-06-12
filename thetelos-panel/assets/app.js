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
async function postData(url, data, timeoutMs = 150000) {
  const fd = new FormData();
  for (const [k,v] of Object.entries(data)) fd.append(k, v);
  // Mutlak zaman aşımı: sunucu hiç yanıt vermese bile istek en geç timeoutMs'de düşer,
  // buton sonsuza kadar "yükleniyor"da kalamaz.
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), timeoutMs);
  let r, text;
  try {
    r = await fetch(url, {method:'POST', body:fd, signal: ctrl.signal});
    text = await r.text();
  } catch(e) {
    if (e.name === 'AbortError') throw new Error('Zaman aşımı — sunucu yanıt vermedi, tekrar dene.');
    throw e;
  } finally {
    clearTimeout(timer);
  }
  try { return JSON.parse(text); }
  catch(_) {
    // Sunucu JSON yerine HTML hata sayfası döndürdü (timeout / 502 / 503)
    const code = r.status;
    throw new Error(code >= 500 ? `Sunucu hatası (${code}) — lütfen tekrar dene.` : `Beklenmeyen yanıt (${code})`);
  }
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

// Başlık normalleştir (PHP lw_norm ile uyumlu): aksan kaldır, parantez içini at, yalnız harf/rakam
function normTitle(s) {
  s = (s || '').toString().toLowerCase();
  s = s.normalize('NFD').replace(/[̀-ͯ]/g, '');  // aksanları kaldır
  s = s.replace(/\([^)]*\)/g, ' ');                        // parantez içi
  s = s.replace(/[^a-z0-9]+/g, ' ');                       // yalnız harf/rakam
  return s.replace(/\s+/g, ' ').trim();
}

// Aynı eseri farklı çevirilerde tanımak için: başlığı anlamlı kelime köklerine indir.
// "Commentary on Aristotle's Physics" ≈ "Exposition of the Physics of Aristotle" → her ikisi {physics, physicorum}
const TITLE_STOP = new Set((
  'against those attack book books gospel epistle epistles letter letters saint part parts four '
+ 'commentary commentaries exposition expositions expositio commentaria commentarium '
+ 'compendium treatise office feast officium rule introduction '
+ 'sentencia sententia sentencie super libri liber librum libros '
+ 'quaestiones quaestio questiones questio disputatae disputata disputatio quaestione '
+ 'questions question disputed disputation '
+ 'litteram litera evangelium evangelii evangelio epistola epistolas epistolam festo '
+ 'aristotle aristotles aristotelis'
).split(/\s+/).filter(Boolean));

function titleTokens(s) {
  s = (s || '').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  s = s.replace(/[^a-z0-9]+/g, ' ');
  return s.split(' ').filter(w => w.length >= 4 && !TITLE_STOP.has(w));
}
function tokMatch(a, b) {
  if (a === b) return true;
  let k = 0; const n = Math.min(a.length, b.length);
  while (k < n && a[k] === b[k]) k++;
  return k >= 5;  // aynı kök (ör. physicorum/physicam, posterior/posteriorum)
}
function titlesSame(ta, tb) {
  if (!ta.length || !tb.length) return false;
  const small = ta.length <= tb.length ? ta : tb;
  const big   = ta.length <= tb.length ? tb : ta;
  let m = 0;
  for (const x of small) { if (big.some(y => tokMatch(x, y))) m++; }
  return m >= 2 || (m >= 1 && m === small.length);
}

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
  queue:   ['Kuyruk',        'Kategori bazlı otomatik kuyruk — tarayıcı kapansa da devam eder'],
};

function switchMode(mode) {
  document.querySelectorAll('.tab-top-btn').forEach(b => b.classList.remove('active'));
  document.querySelector(`.tab-top-btn[data-mode="${mode}"]`)?.classList.add('active');
  ['single','bulk','builder','queue'].forEach(m => {
    const el = document.getElementById('mode-' + m);
    if (el) el.style.display = m === mode ? '' : 'none';
  });
  const t = modeTitles[mode] || modeTitles.single;
  document.getElementById('page-title').textContent = t[0];
  document.getElementById('page-desc').textContent  = t[1];
  if (mode === 'queue') loadQueueList();
}

document.querySelectorAll('.tab-top-btn').forEach(btn => {
  btn.addEventListener('click', () => switchMode(btn.dataset.mode));
});

// URL'den başlangıç modunu oku
const _initMode = new URLSearchParams(location.search).get('mode');
if (_initMode && modeTitles[_initMode]) switchMode(_initMode);

/* ── Aktif işlem göstergesi (her 10sn kontrol) ── */
async function checkActiveJobs() {
  try {
    const res = await postData(API('server-status.php'), {});
    const bar  = document.getElementById('active-jobs-bar');
    const list = document.getElementById('active-jobs-list');
    if (!bar || !list) return;
    if (!res.ok || !res.active || res.active.length === 0) {
      bar.style.display = 'none'; return;
    }
    bar.style.display = '';
    list.innerHTML = res.active.map(j => {
      const pct = j.total > 0 ? Math.round(j.done / j.total * 100) : 0;
      return `<div style="margin-top:4px">
        <strong>${j.category || j.id}</strong> — ${j.done}/${j.total} işlendi · ${j.ok} başarılı · ${j.failed} hata · ${j.books_processing} aktif
        <div style="background:#2a2a2a;border-radius:3px;height:4px;margin-top:3px;overflow:hidden">
          <div style="background:var(--gold);height:100%;width:${pct}%;transition:width 0.5s"></div>
        </div>
      </div>`;
    }).join('');
  } catch(_) {}
}
async function stopAllJobs() {
  if (!confirm('Tüm çalışan batch\'ler durdurulacak. Emin misin?')) return;
  try {
    const res = await postData(API('server-status.php'), {});
    for (const j of res.active || []) {
      await postData(API('batch-cancel.php'), { batch_id: j.id });
    }
    document.getElementById('active-jobs-bar').style.display = 'none';
    notify('gen-notif', 'Tüm işlemler durduruldu.', 'ok');
  } catch(_) {}
}
checkActiveJobs();
setInterval(checkActiveJobs, 10000);


let state = { content:'', categories:[], selectedCover:'', quotes:[] };
let pollTimer = null;

/* ── API Provider Toggle ─────────────────────────── */
let activeProvider = 'deepseek';
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
let batchWorkerCount = 1;
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

  // Sitedeki yazarları kontrol et (checkbox işaretliyse)
  const skipOnSite = document.getElementById('bulk-skip-onsite')?.checked !== false;
  const uniqueAuthors = [...new Set(res.books.map(b => b.author_name).filter(Boolean))];
  let onSiteAuthors = new Set();
  if (skipOnSite) {
    try {
      const chk = await postData(API('author-check.php'), { authors: JSON.stringify(uniqueAuthors) }, 60000);
      if (chk.ok && chk.on_site?.length) onSiteAuthors = new Set(chk.on_site.map(a => a.toLowerCase()));
    } catch(_) {}
  }

  const filteredBooks = res.books.filter(b => !onSiteAuthors.has((b.author_name || '').toLowerCase()));
  const skippedAuthors = uniqueAuthors.filter(a => onSiteAuthors.has(a.toLowerCase()));

  // Listeye ekle (dedup by title+author)
  const existing = new Set(batchBooks.map(b => (b.book_title + '||' + b.author_name).toLowerCase()));
  let added = 0;
  for (const bk of filteredBooks) {
    const key = (bk.book_title + '||' + bk.author_name).toLowerCase();
    if (!existing.has(key)) { batchBooks.push(bk); existing.add(key); added++; }
  }

  uploadedFiles.push(file.name);
  updateFileList();
  updateBatchBadge();
  renderBulkTable(batchBooks);
  document.getElementById('btn-batch-start').disabled = false;
  document.getElementById('upload-actions').style.display = 'flex';
  const skipMsg = skippedAuthors.length ? ` · ${skippedAuthors.length} yazar sitede var, çıkarıldı (${skippedAuthors.slice(0,3).join(', ')}${skippedAuthors.length>3?'…':''})` : '';
  notify('bulk-notif', `✓ ${file.name}: ${added} kitap eklendi. Toplam: ${batchBooks.length}${skipMsg}`, 'ok');
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
  const workerCount = parseInt(document.getElementById('bulk_workers')?.value || '1');
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

  // Sunucu tarafı drain worker'ları ATEŞLE (bekleme yok — Cloudflare kesse bile
  // ignore_user_abort sayesinde sunucu kuyruğu arka planda boşaltır)
  batchWorkerCount = workerCount;
  fireDrainWorkers(workerCount);

  // Durumu sorgulayarak ilerlemeyi izle
  await pollBatchUntilDone();

  // Tamamlandı
  batchRunning = false;
  document.getElementById('btn-batch-pause').style.display = 'none';
  document.getElementById('batch-status-badge').textContent = 'Tamamlandı';
  document.getElementById('batch-status-badge').className = 'badge badge-green';
  btn.disabled = false;
  btn.innerHTML = '▶ Yeni Batch Başlat';

  await updateBatchUI();
  notify('bulk-notif', 'Batch tamamlandı!', 'ok');
});

/* ── Duraklat / Devam ─────────────────────────────── */
document.getElementById('btn-batch-pause')?.addEventListener('click', async () => {
  batchPaused = !batchPaused;
  const btn = document.getElementById('btn-batch-pause');
  btn.innerHTML = batchPaused ? '▶ Devam Et' : '⏸ Duraklat';
  document.getElementById('batch-status-badge').textContent = batchPaused ? 'Duraklatıldı' : 'Çalışıyor';
  document.getElementById('batch-status-badge').className = batchPaused ? 'badge badge-gray' : 'badge badge-gold';

  // Sunucu durumunu güncelle (drain worker'lar bunu kontrol eder)
  await postData(API('batch-control.php'), {
    batch_id: batchId, action: batchPaused ? 'pause' : 'resume',
  }).catch(() => {});

  if (batchPaused) {
    notify('bulk-notif', 'Batch duraklatıldı. Devam için tekrar tıkla.', 'ok');
  } else {
    // Devam: worker'ları yeniden ateşle
    fireDrainWorkers(batchWorkerCount);
    notify('bulk-notif', 'Batch devam ediyor.', 'ok');
  }
});

/* ── Drain Worker'ları ateşle ─────────────────────────
 * Her istek sunucuda kuyruğu boşaltır; tarayıcı YANITI BEKLEMEZ.
 * Cloudflare bağlantıyı kesse bile (ignore_user_abort) sunucu çalışmaya devam eder.
 */
function fireDrainWorkers(count) {
  const statusWrap = document.getElementById('batch-worker-status');
  if (statusWrap) statusWrap.textContent = `${count} worker arka planda çalışıyor — ilerleme aşağıda.`;
  for (let i = 0; i < count; i++) {
    postData(API('batch-worker.php'), { batch_id: batchId }).catch(() => {});
  }
}

/* ── Durum sorgulama döngüsü (watchdog'lu) ─────────────
 * İlerlemeyi gösterir; uzun süre ilerleme yoksa worker'ları yeniden ateşler.
 */
async function pollBatchUntilDone() {
  let lastDone = -1;
  let lastChange = Date.now();
  let pollFailures = 0;

  while (batchRunning) {
    if (batchPaused) { await delay(2000); continue; }

    let b = null;
    try {
      const r = await fetch(API('batch-status.php?batch_id=') + batchId).then(x => x.json());
      if (r.ok) { b = r.batch; renderBatchStatus(b); pollFailures = 0; }
    } catch(e) {
      pollFailures++;
      const sw = document.getElementById('batch-worker-status');
      if (sw && pollFailures >= 2) sw.textContent = 'Sunucu meşgul, durum sorgusu bekleniyor...';
    }

    if (b) {
      if (b.status === 'done' || b.status === 'cancelled' || b.done >= b.total) break;

      // Watchdog: ilerleme değiştiyse zamanlayıcıyı sıfırla
      if (b.done !== lastDone) { lastDone = b.done; lastChange = Date.now(); }
      // 90 sn'dir ilerleme yok ve hâlâ bekleyen var → worker'ları yeniden ateşle
      else if (!batchPaused && (Date.now() - lastChange) > 90000 && b.done < b.total) {
        fireDrainWorkers(batchWorkerCount);
        lastChange = Date.now();
      }
    }

    await delay(4000);
  }
}

/* ── Batch UI Güncelle ────────────────────────────── */
async function updateBatchUI() {
  if (!batchId) return;
  try {
    const res = await fetch(API('batch-status.php?batch_id=') + batchId).then(r => r.json());
    if (res.ok) renderBatchStatus(res.batch);
  } catch(e) {}
}

function renderBatchStatus(b) {
  if (!b) return;
  const pct = b.total > 0 ? Math.round((b.done / b.total) * 100) : 0;
  document.getElementById('bulk-bar').style.width = pct + '%';
  document.getElementById('bulk-bar-label').textContent =
    `${b.done.toLocaleString('tr')} / ${b.total.toLocaleString('tr')} — %${pct}`;

  document.getElementById('bulk-summary').innerHTML =
    `<span class="badge badge-green">✓ ${b.ok} başarılı</span>&nbsp;`
    + `<span class="badge badge-red">✗ ${b.failed} hatalı</span>&nbsp;`
    + (b.total - b.done > 0 ? `<span class="badge badge-gray">⏳ ${(b.total-b.done).toLocaleString('tr')} bekliyor</span>` : '');

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
}

function setRowStatus(idx, st, html) {
  const cell = document.querySelector(`#brow-${idx} .status-cell`);
  if (!cell) return;
  const cls = st==='ok'?'badge-green':st==='err'?'badge-red':st==='working'?'badge-gold':'badge-gray';
  cell.innerHTML = `<span class="badge ${cls}">${html}</span>`;
}

/* ══ LİSTE OLUŞTUR (Builder) ═════════════════════════════ */
let builderAuthors      = [];   // [{author, era, note, onSite}]
let builderList         = [];   // [{title, author, year, verified, cover}]
let builderAuthorsOffset = 0;   // sayfalama: şu ana kadar kaç yazar yüklendi
let builderShowExisting  = false; // "sitede var" eserleri göster/gizle

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

// Kategorinin yazarlarını getir — 50'şerlik AYRI isteklerle (her biri Cloudflare 100sn sınırına sığar)
document.getElementById('btn-fetch-authors')?.addEventListener('click', async () => {
  const category = document.getElementById('builder-category').value.trim();
  const total    = parseInt(document.getElementById('builder-author-count').value || '40');
  if (!category) { notify('builder-notif','Kategori girin.','err'); return; }
  const btn = document.getElementById('btn-fetch-authors');
  setLoading(btn, true, 'Yazarlar getiriliyor...');
  builderAuthors = [];
  builderAuthorsOffset = 0;
  try {
    while (builderAuthorsOffset < total) {
      const chunk = Math.min(50, total - builderAuthorsOffset);
      btn.innerHTML = `<span class="loader"></span> Yazarlar getiriliyor... (${builderAuthorsOffset}/${total})`;
      const res = await postData(API('list-authors.php'), {
        category, count: chunk, offset: builderAuthorsOffset, api_provider: activeProvider,
      });
      if (!res.ok) { notify('builder-notif', res.error, 'err'); break; }
      // Önceki turlarla mükerrer olanları ele
      const have = new Set(builderAuthors.map(a => a.author.toLowerCase()));
      const fresh = res.authors.filter(a => !have.has(a.author.toLowerCase()));
      builderAuthors = builderAuthors.concat(fresh);
      builderAuthorsOffset += chunk;
      renderBuilderAuthors();   // her turda ekrana yansıt — kullanıcı ilerlemeyi görür
      if (!fresh.length) break; // model yeni isim üretemiyor, dur
    }
    if (builderAuthors.length) {
      checkAuthorsOnSite();
      notify('builder-notif', `✓ ${builderAuthors.length} yazar getirildi. İstediğinin eserlerini getir.`, 'ok');
    }
  } catch(e) {
    notify('builder-notif', 'Hata: ' + e.message, 'err');
  } finally {
    setLoading(btn, false);
  }
});

function renderBuilderAuthors() {
  document.getElementById('builder-authors-card').style.display = '';
  document.getElementById('builder-authors-count').textContent = `(${builderAuthors.length})`;
  const tb = document.querySelector('#builder-authors-table tbody');
  tb.innerHTML = builderAuthors.map((a,i) => {
    const siteBadge = a.onSite
      ? `<span class="badge badge-green" title="Bu yazarın eserleri sitede mevcut">✓ Sitede var</span>`
      : '';
    return `<tr id="bauthor-${i}">
      <td style="color:var(--muted)">${i+1}</td>
      <td><strong>${a.author}</strong> ${siteBadge}</td>
      <td style="color:var(--muted);font-size:12px">${a.era||''}</td>
      <td style="color:var(--muted);font-size:12px">${a.note||''}</td>
      <td class="action-cell"><button class="btn btn-ghost btn-sm" onclick="fetchOneAuthor(${i})">📚 Eserleri</button></td>
    </tr>`;
  }).join('');

  // Sonraki 100 butonu
  let nextBtn = document.getElementById('btn-next-authors');
  if (!nextBtn) {
    nextBtn = document.createElement('button');
    nextBtn.id = 'btn-next-authors';
    nextBtn.className = 'btn btn-ghost btn-sm';
    nextBtn.style.marginTop = '8px';
    document.getElementById('builder-authors-card').appendChild(nextBtn);
  }
  const category = document.getElementById('builder-category').value.trim();
  nextBtn.textContent = `Sonraki 50 yazar (${builderAuthorsOffset + 1}–${builderAuthorsOffset + 50})`;
  nextBtn.onclick = async () => {
    setLoading(nextBtn, true, 'Yükleniyor...');
    try {
      const res = await postData(API('list-authors.php'), {
        category, count: 50, offset: builderAuthorsOffset, api_provider: activeProvider,
      });
      if (!res.ok) { notify('builder-notif', res.error, 'err'); return; }
      const have = new Set(builderAuthors.map(a => a.author.toLowerCase()));
      const fresh = res.authors.filter(a => !have.has(a.author.toLowerCase()));
      builderAuthors = builderAuthors.concat(fresh);
      builderAuthorsOffset += 50;
      renderBuilderAuthors();
      checkAuthorsOnSite();
      if (fresh.length < 10) {
        notify('builder-notif',
          `Yalnız ${fresh.length} YENİ isim geldi — "${category}" kategorisi tükeniyor. `
          + `Alt kategori dene: ethics, metaphysics, ancient philosophy, political philosophy...`, 'err');
      } else {
        notify('builder-notif', `✓ ${fresh.length} yeni yazar eklendi. Toplam: ${builderAuthors.length}`, 'ok');
      }
    } catch(e) { notify('builder-notif', e.message, 'err'); }
    setLoading(nextBtn, false);
  };
}

// Sitedeki yazar terimlerini çek — eşleşenlere "Sitede var" rozeti koy
async function checkAuthorsOnSite() {
  try {
    const res = await postData(API('list-works.php'), { author: '_', mode: 'authors_exist' });
    if (!res.ok || !Array.isArray(res.authors)) return;
    const siteSet = new Set(res.authors);
    let changed = false;
    builderAuthors.forEach(a => {
      const was = a.onSite;
      a.onSite = siteSet.has(a.author.toLowerCase());
      if (a.onSite !== was) changed = true;
    });
    if (changed) renderBuilderAuthors();
  } catch(_) {}
}

window.fetchOneAuthor = async function(i) {
  const a = builderAuthors[i];
  if (!a) return;
  const cell = document.querySelector(`#bauthor-${i} .action-cell`);
  if (cell) cell.innerHTML = '<span class="loader"></span>';
  const [added, errMsg] = await fetchAuthorWorks(a.author);
  if (cell) cell.innerHTML = added < 0
    ? `<span class="badge badge-gray" title="${errMsg||''}">hata</span>`
    : `<span class="badge badge-green">+${added}</span>`;
};

// "Tüm yazarların eserlerini getir" — sırayla, sitede olan yazarları atla
document.getElementById('btn-fetch-all-works')?.addEventListener('click', async () => {
  const btn = document.getElementById('btn-fetch-all-works');
  setLoading(btn, true, 'Hepsi getiriliyor...');
  let skipped = 0;
  for (let i = 0; i < builderAuthors.length; i++) {
    const cell = document.querySelector(`#bauthor-${i} .action-cell`);
    if (builderAuthors[i].onSite) {
      skipped++;
      if (cell) cell.innerHTML = `<span class="badge badge-gray" title="Yazar zaten sitede">atlandı</span>`;
      continue;
    }
    if (cell) cell.innerHTML = '<span class="loader"></span>';
    const [added, errMsg] = await fetchAuthorWorks(builderAuthors[i].author);
    if (cell) cell.innerHTML = added < 0
      ? `<span class="badge badge-gray" title="${errMsg||''}">hata</span>`
      : `<span class="badge badge-green">+${added}</span>`;
  }
  setLoading(btn, false);
  const msg = skipped > 0
    ? `✓ Tamamlandı — ${builderList.length} kitap (${skipped} sitede olan yazar atlandı).`
    : `✓ Tamamlandı — toplam ${builderList.length} kitap.`;
  notify('builder-notif', msg, 'ok');
});

// Bir yazarın eserlerini çek ve listeye ekle (dedup)
// Öncelik: 1) Firebase  2) OpenLibrary  3) LLM (DeepSeek)
async function fetchAuthorWorks(author) {
  const doVerify = document.getElementById('builder-verify').checked;
  try {
    let worksRaw = null;
    let source = 'llm';

    // 1) Firebase /yazarlar/ — indexer çalıştırıldıysa anlık gelir
    try {
      const fb = await postData(API('firebase-search.php'), { author, limit: 200 }, 12000);
      if (fb.ok && fb.works && fb.works.length > 0) { worksRaw = fb.works; source = 'firebase'; }
    } catch(_) {}

    // 2) OpenLibrary public API — ücretsiz, kapak resimli
    if (!worksRaw) {
      try {
        const ol = await postData(API('openlibrary-search.php'), { author, limit: 50 }, 25000);
        if (ol.ok && ol.works && ol.works.length > 0) { worksRaw = ol.works; source = 'openlibrary'; }
      } catch(_) {}
    }

    // 3) LLM fallback — son çare
    if (!worksRaw) {
      const res = await postData(API('list-works.php'), { author, api_provider: activeProvider, mode: 'list' });
      if (!res.ok) { notify('builder-notif', res.error || 'Liste alınamadı.', 'err'); return [-1, res.error||'Liste alınamadı']; }
      worksRaw = res.works;
    }
    const existing = new Set(builderList.map(b => (b.title+'||'+b.author).toLowerCase()));
    const newRows = [];
    for (const w of worksRaw) {
      const key = (w.title+'||'+author).toLowerCase();
      if (existing.has(key)) continue;
      existing.add(key);
      const row = {
        title: w.title, original: w.original || '', author: author, year: w.year || '',
        verified: false, cover: w.cover || '', exists: false, post_url: '',
      };
      builderList.push(row);
      newRows.push(row);
    }
    renderBuilderList();
    if (source !== 'llm') notify('builder-notif', `✓ ${author}: ${newRows.length} eser (${source}).`, 'ok');

    // Sitede zaten var mı? — yazarın TÜM mevcut eserlerini TEK istekle çek, kök bazlı eşleştir
    try {
      const er = await postData(API('list-works.php'), { author, mode: 'exists' });
      if (er.ok && Array.isArray(er.existing)) {
        const ex = er.existing.map(e => ({ ...e, tok: titleTokens(e.title) }));
        for (const row of newRows) {
          const rt = titleTokens(workLabel(row));
          const hit = ex.find(e => titlesSame(rt, e.tok));
          if (hit) { row.exists = true; row.post_url = hit.post_url || ''; }
        }
        renderBuilderList();
      }
    } catch(_) {}

    // 2) Kapak/yıl doğrulamasını parça parça (6'lı) yap — her istek 100s altında kalır
    if (doVerify && newRows.length) {
      for (let i = 0; i < newRows.length; i += 6) {
        const chunk = newRows.slice(i, i + 6);
        try {
          const vr = await postData(API('list-works.php'), {
            author, mode: 'verify',
            titles: JSON.stringify(chunk.map(r => r.title)),
          });
          if (vr.ok && vr.results) {
            for (const r of vr.results) {
              const row = chunk.find(c => c.title === r.title);
              if (!row) continue;
              row.verified = !!r.verified;
              if (r.cover) row.cover = r.cover;
              if (r.year && !row.year) row.year = r.year;
            }
            renderBuilderList();
          }
        } catch(_) { /* doğrulama hatası listeyi engellemez */ }
      }
    }
    return [newRows.length, ''];
  } catch(e) {
    notify('builder-notif', 'Hata: ' + e.message, 'err');
    return [-1, e.message];
  }
}

// Yayın başlığı: "English Title (Original Title)" — orijinal varsa ekle
function workLabel(b) {
  return b.original ? `${b.title} (${b.original})` : b.title;
}

function renderBuilderList() {
  document.getElementById('builder-list-card').style.display = builderList.length ? '' : 'none';

  const existsCount = builderList.filter(b => b.exists).length;
  const newCount    = builderList.length - existsCount;
  const vCount      = builderList.filter(b => b.verified).length;

  // Sayaç: "212 kitap (45 sitede var, gizlendi)"
  let countText = `${builderList.length} kitap`;
  if (existsCount > 0 && !builderShowExisting) countText += ` · ${newCount} yeni (${existsCount} sitede var, gizlendi)`;
  else if (existsCount > 0)                     countText += ` · ${existsCount} sitede var`;
  document.getElementById('builder-list-count').textContent = countText;

  const vBadge = document.getElementById('builder-list-verified');
  if (vCount > 0) { vBadge.style.display = ''; vBadge.textContent = `✓ ${vCount} doğrulandı`; }
  else vBadge.style.display = 'none';

  // Toggle butonu
  const toggleBtn = document.getElementById('btn-toggle-existing');
  if (toggleBtn) {
    toggleBtn.style.display = existsCount > 0 ? '' : 'none';
    toggleBtn.textContent = builderShowExisting ? '⊘ Sitede olanları gizle' : `⊘ Sitede olanları göster (${existsCount})`;
  }

  const tb = document.querySelector('#builder-list-table tbody');
  tb.innerHTML = builderList.map((b, i) => {
    if (!builderShowExisting && b.exists) return '';
    return `<tr id="brow-list-${i}">
      <td style="color:var(--muted)">${i+1}</td>
      <td>${b.cover ? `<img src="${b.cover}" style="width:32px;height:46px;object-fit:cover;border-radius:3px" onerror="this.style.display='none'">` : '—'}</td>
      <td>${workLabel(b)}</td>
      <td style="color:var(--muted)">${b.author}</td>
      <td style="color:var(--muted)">${b.year||''}</td>
      <td>${b.exists
        ? `<span class="badge badge-gray">⊘ Sitede var</span>`
        : (b.verified ? '<span class="badge badge-green">✓</span>' : '<span class="badge badge-gray">?</span>')}</td>
      <td><button class="btn btn-ghost btn-sm" onclick="removeBuilderRow(${i})" style="color:var(--red)">✕</button></td>
    </tr>`;
  }).join('');
}

document.getElementById('btn-toggle-existing')?.addEventListener('click', () => {
  builderShowExisting = !builderShowExisting;
  renderBuilderList();
});

window.removeBuilderRow = function(i) {
  builderList.splice(i, 1);
  renderBuilderList();
};

// Toplu Batch'e aktar
document.getElementById('btn-builder-to-batch')?.addEventListener('click', () => {
  if (!builderList.length) { notify('builder-notif','Liste boş.','err'); return; }
  // Sitede zaten olanları atla — sadece eksikleri aktar
  const missing = builderList.filter(b => !b.exists);
  if (!missing.length) { notify('builder-notif','Tüm eserler sitede zaten var, eklenecek yeni eser yok.','ok'); return; }
  const skipped = builderList.length - missing.length;
  batchBooks = missing.map(b => ({ book_title: workLabel(b), author_name: b.author, category: '', cover: b.cover || '' }));
  updateBatchBadge();
  renderBulkTable(batchBooks);
  document.getElementById('btn-batch-start').disabled = false;
  // Toplu Batch sekmesine geç
  document.querySelector('.tab-top-btn[data-mode=bulk]')?.click();
  notify('bulk-notif', `✓ ${batchBooks.length} eksik kitap batch'e aktarıldı${skipped ? ` (${skipped} tanesi sitede zaten var, atlandı)` : ''}. Ayarları seçip başlat.`, 'ok');
});

// CSV indir
document.getElementById('btn-builder-csv')?.addEventListener('click', () => {
  if (!builderList.length) { notify('builder-notif','Liste boş.','err'); return; }
  let csv = 'Kitap Adı,Yazar Adı,Yıl,Kapak\n';
  csv += builderList.map(b =>
    `"${workLabel(b).replace(/"/g,'""')}","${(b.author||'').replace(/"/g,'""')}","${b.year||''}","${b.cover||''}"`
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

/* ══ OTOMATİK KUYRUK ════════════════════════════════════════════ */


async function loadQueueList() {
  const body = document.getElementById('queue-list-body');
  if (!body) return;
  try {
    const res = await postData(API('queue-list.php'), {});
    if (!res.ok || !res.queues.length) {
      body.innerHTML = '<p style="color:var(--muted);font-size:13px">Henüz kuyruk yok.</p>';
      return;
    }
    body.innerHTML = res.queues.map(q => {
      const pct   = q.total > 0 ? Math.round(q.done / q.total * 100) : 0;
      const color = q.status === 'done' ? 'var(--green)' : q.status === 'error' ? 'var(--red)' : 'var(--gold)';
      const statusLabel = {building:'Oluşturuluyor',running:'Çalışıyor',done:'Tamamlandı',error:'Hata',paused:'Duraklatıldı'}[q.status] || q.status;
      return `<div class="card" style="margin-bottom:10px;padding:14px">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
          <div>
            <strong>${q.category.charAt(0).toUpperCase()+q.category.slice(1)} ${q.author_offset||0}-${(q.author_offset||0)+(q.authors_total||50)}</strong>
            <span class="badge" style="background:${color};color:#fff;margin-left:8px">${statusLabel}</span>
          </div>
          <div style="font-size:12px;color:var(--muted)">${new Date(q.created_at*1000).toLocaleString('tr-TR')}</div>
        </div>
        ${q.build_msg ? `<div style="font-size:12px;color:var(--muted);margin-top:4px">${q.build_msg}</div>` : ''}
        <div style="margin-top:8px">
          <div style="background:#2a2a2a;border-radius:4px;height:6px;overflow:hidden">
            <div style="background:var(--green);height:100%;width:${pct}%;transition:width 0.3s"></div>
          </div>
          <div style="font-size:11px;color:var(--muted);margin-top:4px">${q.done}/${q.total} işlendi · ${q.ok} başarılı · ${q.failed} hata</div>
        </div>
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
          ${q.status === 'building' ? `<button class="btn btn-primary btn-sm" onclick="continueBuilding('${q.id}',${q.authors_built||0},${q.authors_total||50})">⚙ Oluşturmayı Devam Ettir</button>` : ''}
          ${q.status === 'running'  ? `<button class="btn btn-primary btn-sm" onclick="resumeQueue('${q.id}')">▶ İşlemi Başlat</button>` : ''}
          ${q.status === 'done'     ? `<span class="badge badge-green">✓ Tamamlandı</span>` : ''}
          ${q.total > 0 ? `<a class="btn btn-ghost btn-sm" href="api/queue-export.php?batch_id=${q.id}" download>⬇ CSV İndir</a>` : ''}
          ${(q.status === 'running' || q.status === 'done') ? `<button class="btn btn-ghost btn-sm" onclick="addNextAuthors('${q.category}',${(q.author_offset||0)+(q.authors_total||50)})">➕ Sonraki 50 yazar</button>` : ''}
          <button class="btn btn-ghost btn-sm" style="color:var(--red)" onclick="deleteQueue('${q.id}')">✕ Sil</button>
        </div>
      </div>`;
    }).join('');
  } catch(e) {
    body.innerHTML = `<p style="color:var(--red);font-size:13px">Yüklenemedi: ${e.message}</p>`;
  }
}

// Kuyruk oluştur — 2 adım: 1) yazarlar (LLM, hızlı)  2) eserler (chunked polling)
document.getElementById('btn-queue-create')?.addEventListener('click', async () => {
  const category = document.getElementById('queue-category')?.value.trim();
  const count    = document.getElementById('queue-author-count')?.value || 50;
  if (!category) { notify('queue-create-notif', 'Kategori girin.', 'err'); return; }

  const btn = document.getElementById('btn-queue-create');
  setLoading(btn, true, 'Yazarlar getiriliyor...');
  try {
    // Adım 1: yazar listesi al (10-15s)
    const offset = parseInt(document.getElementById('queue-offset')?.value || '0');
    const res = await postData(API('queue-create.php'), {
      category, author_count: count, offset,
    }, 90000);
    if (!res.ok) { notify('queue-create-notif', res.error || 'Hata.', 'err'); return; }

    notify('queue-create-notif',
      `✓ ${res.authors.length} yazar bulundu — eserler getiriliyor...`, 'ok');
    await loadQueueList();

    // Adım 2: her 5 yazarın eserlerini sırayla çek (her istek ~15-20s)
    const batchId = res.batch_id;
    let done = false;
    while (!done) {
      btn.innerHTML = `<span class="loader"></span> Eserler getiriliyor...`;
      try {
        const br = await postData(API('queue-build.php'), { batch_id: batchId, chunk: 5 }, 60000);
        if (!br.ok) break;
        notify('queue-create-notif', `${br.build_msg}`, 'ok');
        await loadQueueList();
        done = br.done;
      } catch(e) { break; }
    }

    notify('queue-create-notif', '✓ Kuyruk hazır! "Devam Et" ile işlemi başlat.', 'ok');
    await loadQueueList();
  } catch(e) {
    notify('queue-create-notif', e.message, 'err');
  } finally {
    setLoading(btn, false);
  }
});

document.getElementById('btn-queue-refresh')?.addEventListener('click', loadQueueList);

async function resumeQueue(batchId) {
  try {
    await postData(API('batch-worker.php'), { batch_id: batchId }, 10000);
    notify('queue-create-notif', '✓ İşlem başladı.', 'ok');
    loadQueueList();
  } catch(_) {}
}

async function continueBuilding(batchId, authorsBuilt, authorsTotal) {
  notify('queue-create-notif', 'Eserler getiriliyor...', 'ok');
  let built = authorsBuilt;
  while (built < authorsTotal) {
    try {
      const br = await postData(API('queue-build.php'), { batch_id: batchId, chunk: 5 }, 60000);
      if (!br.ok) break;
      built = br.authors_built;
      notify('queue-create-notif', br.build_msg, 'ok');
      await loadQueueList();
      if (br.done) break;
    } catch(e) { notify('queue-create-notif', e.message, 'err'); break; }
  }
  notify('queue-create-notif', '✓ Kuyruk hazır! "İşlemi Başlat" ile devam et.', 'ok');
}

function addNextAuthors(category, offset) {
  document.getElementById('queue-category').value = category;
  document.getElementById('queue-author-count').value = 50;
  document.getElementById('queue-offset').value = offset;
  document.getElementById('queue-category').scrollIntoView({behavior:'smooth'});
  document.getElementById('btn-queue-create').textContent = `▶ ${category} — ${offset+1}-${offset+50}. yazarları ekle`;
}

async function deleteQueue(batchId) {
  if (!confirm('Bu kuyruğu silmek istediğinden emin misin?')) return;
  await postData(API('queue-delete.php'), { batch_id: batchId });
  loadQueueList();
}
