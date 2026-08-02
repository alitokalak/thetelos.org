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
  cleaner: ['Liste Temizle', 'Eser CSV\'sindeki tekrarları/çevirileri birleştir, yazara ait olmayanları ele'],
  queue:   ['Kuyruk',        'Kategori bazlı otomatik kuyruk — tarayıcı kapansa da devam eder'],
};

function switchMode(mode) {
  document.querySelectorAll('.tab-top-btn').forEach(b => b.classList.remove('active'));
  document.querySelector(`.tab-top-btn[data-mode="${mode}"]`)?.classList.add('active');
  ['single','bulk','builder','cleaner','queue'].forEach(m => {
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

/* ── Aktif işlem göstergesi + KÜRESEL NÖBETÇİ (her 10sn) ──────────────
 * Bu fonksiyon HER panel sayfasında (hangi sekme açık olursa olsun) çalışır.
 * İki iş yapar:
 *   1) Aktif batch'lerin canlı ilerlemesini üstteki çubukta gösterir —
 *      tarayıcıyı kapatıp açsan bile yeniden açtığında kaldığı yeri görürsün.
 *   2) Takılan batch'leri (bekleyen iş var ama hiçbiri işlenmiyor = worker'lar
 *      ölmüş) OTOMATİK olarak yeniden başlatır. Artık elle "Devam Et"e
 *      basmana gerek yok; panel açık olduğu sürece kendi kendini onarır.
 */
var _reviveCooldown = {};   // batch_id -> en erken tekrar ateşleme zamanı (ms)
function _fireWorkersFor(id, count) {
  for (var i = 0; i < count; i++) {
    postData(API('batch-worker.php'), { batch_id: id }).catch(function(){});
  }
}
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
      const pct   = j.total > 0 ? Math.round(j.done / j.total * 100) : 0;
      const pend  = j.books_pending  || 0;
      const stale = j.books_stale    || 0;
      const proc  = j.books_processing || 0;
      const staleTag = stale > 0 ? ` · <span style="color:#cc6b00">${stale} takılı</span>` : '';
      return `<div style="margin-top:4px">
        <strong>${j.category || j.id}</strong> — ${j.done}/${j.total} işlendi · ${j.ok} başarılı · ${j.failed} hata · ${proc} aktif · ${pend} bekliyor${staleTag}
        <a href="panel.php?mode=queue" style="margin-left:10px;color:var(--gold);font-size:12px;text-decoration:underline">📋 Listeyi Gör</a>
        <div style="background:#2a2a2a;border-radius:3px;height:4px;margin-top:3px;overflow:hidden">
          <div style="background:var(--gold);height:100%;width:${pct}%;transition:width 0.5s"></div>
        </div>
      </div>`;
    }).join('');

    // ── Küresel nöbetçi: takılan batch'leri otomatik canlandır ─────────────
    // Batch'in SEÇİLEN worker sayısına saygı duyar: yalnızca canlı worker
    // sayısı hedefin altındaysa eksik kadarını ateşler. "1 worker (güvenli)"
    // seçtiysen asla 1'den fazla paralel worker açılmaz.
    // TEK MOTOR: tüm aktif batch'ler için worker sayısını burası korur.
    // proc = canlı worker (ucuz heartbeat taze). desired = seçilen sayı.
    // Yalnızca eksik kadarını ateşler → ASLA seçilenden fazla worker açılmaz
    // (flood yok). Ölü worker'ın kitabı bayatlayınca proc düşer → yeri dolar.
    const now = Date.now();
    for (const j of res.active) {
      if (j.status === 'paused') continue;
      const pend    = j.books_pending    || 0;
      const stale   = j.books_stale      || 0;
      const proc    = j.books_processing || 0;   // gerçekten canlı (bayatlar hariç)
      const desired = Math.max(1, Math.min(5, j.workers || 1));

      // Canlandırma yalnızca: hâlâ iş var (pending/bayat) VE canlı worker eksik.
      const workLeft = pend > 0 || stale > 0;
      const toFire   = desired - proc;
      if (workLeft && toFire > 0) {
        if (!_reviveCooldown[j.id] || now >= _reviveCooldown[j.id]) {
          _fireWorkersFor(j.id, toFire);
          _reviveCooldown[j.id] = now + 20000;  // 20sn cooldown
        }
      }
    }
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
function runGenerateStream(params, onLive, label) {
  return new Promise((resolve, reject) => {
    const fd = new FormData();
    Object.entries(params).forEach(([k, v]) => fd.append(k, v));

    let streamText = '', buffer = '', stats = {};

    /* GEÇEN SÜREYİ GÖSTER + ÖLÜ İSTEĞİ BEKLEME.
       Ekranda hiçbir şey değişmeyen bir çember dönüyordu: kullanıcı ne kadar
       beklediğini, ilerleme olup olmadığını, sistemin ölüp ölmediğini
       göremiyordu. Üstelik nabız düzeltmesinden sonra bağlantı artık
       kopmadığı için, DeepSeek hiç yanıt vermediğinde bekleme 100 sn yerine
       280 sn sürüyor ve 3 denemeyle dakikalarca dönebiliyordu.

       İki şey birden: saniye sayan bir durum yazısı ve ilk içerik 90 saniyede
       gelmezse isteği iptal et. Üretim BAŞLADIYSA sayaç sıfırlanır ve uzun
       sürmesine izin verilir — kesilen şey yalnızca yanıtsız istek. */
    const ac    = new AbortController();
    const t0    = Date.now();
    let lastData = Date.now();
    const tickEl = () => document.getElementById('stream-status');
    const timer = setInterval(() => {
      const sn = Math.round((Date.now() - t0) / 1000);
      const el = tickEl();
      if (el) el.textContent = (label || 'İçerik üretiliyor') + ' — ' + sn + ' sn' +
        (streamText ? ' · ' + streamText.split(/\s+/).length.toLocaleString('tr') + ' kelime' : ' · ilk yanıt bekleniyor');
      if (!streamText && Date.now() - lastData > 90000) { clearInterval(timer); ac.abort('no-first-token'); }
    }, 1000);
    const stopTimer = () => clearInterval(timer);

    fetch(API('generate.php'), { method: 'POST', body: fd, signal: ac.signal })
      .then(response => {
        const ct = response.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
          stopTimer();
          return response.json().then(data => reject(new Error(data.error || 'Hata')));
        }
        const reader = response.body.getReader();
        const decoder = new TextDecoder();

        function read() {
          reader.read().then(({ done, value }) => {
            if (done) {
              stopTimer();
              // Sunucu hata olayı göndermeden akışı kapattıysa (PHP çöktü,
              // Cloudflare kesti) buraya düşeriz. Sebebi bilmiyoruz; en
              // azından NEYİN olmadığını söyle.
              if (!streamText) reject(new Error('Yanıt boş geldi (akış içerik gelmeden kapandı — sunucu meşgul olabilir)'));
              else resolve({ text: streamText, stats });
              return;
            }
            lastData = Date.now();
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
                  stopTimer();
                  reject(new Error(evData.error));
                } else if (evName === 'done') {
                  stats = evData;
                }
              }
            }
            read();
          }).catch(err => {
            stopTimer();
            if (streamText) resolve({ text: streamText, stats });
            else if (ac.signal.reason === 'no-first-token')
              reject(new Error('90 saniyede tek kelime gelmedi — istek yanıtsız kaldı (sunucu ya da DeepSeek meşgul)'));
            else reject(new Error('Bağlantı kesildi: ' + err.message));
          });
        }
        read();
      })
      .catch(err => {
        stopTimer();
        if (ac.signal.reason === 'no-first-token')
          reject(new Error('90 saniyede tek kelime gelmedi — istek yanıtsız kaldı (sunucu ya da DeepSeek meşgul)'));
        else reject(new Error('Hata: ' + err.message));
      });
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
  // Parça sayısı hedef kelimeye göre yükseltilir: model tek istekte ~1800
  // kelimeden sonra kendini toparlayıp bitiriyor, dolayısıyla "8000 kelime /
  // 2 parça" seçilirse yazı hedefin yarısı uzunlukta çıkar. Seçilen değer taban.
  const parts = isDeepSeek
    ? Math.max(parseInt(document.getElementById('parts-select')?.value) || 2,
               Math.min(6, Math.ceil(Math.max(500, Math.min(8000, parseInt(tokens) || 3000)) / 1800)))
    : 1;
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

  let doneParts = 0, partialErr = '';

  try {
    let finalContent = '';
    let finalStats = {};

    if (isDeepSeek && parts > 1) {
      // ── Çok parçalı üretim ──────────────────────────────
      // İKİ KURAL:
      // 1) Geçici hata üretimi bitirmez — parça 3 kez denenir. Toplu üretim
      //    çalışırken API sık sık 429/zaman aşımı döndürüyor; tek bir aksaklık
      //    yüzünden baştan başlamak gerekmemeli.
      // 2) BİR PARÇA ALINAMAZSA ELDEKİ ÇÖPE GİTMEZ. Eskiden hata fırlatılıyor,
      //    catch bloğu sonucu tümden gizliyordu: kullanıcı 3 parçanın yazılışını
      //    izleyip sonunda hepsini kaybediyordu.
      let accumulated = '';
      for (let k = 1; k <= parts; k++) {
        const st = () => document.getElementById('stream-status');
        let text = null;

        for (let attempt = 1; attempt <= 3; attempt++) {
          const s = st();
          if (s) s.textContent = `DeepSeek içerik üretiyor (Part ${k}/${parts})` +
                                 (attempt > 1 ? ` — yeniden deneme ${attempt}/3` : '') + '...';
          try {
            const r = await runGenerateStream(
              { ...baseParams, part: k, parts: parts, prev_content: accumulated },
              (live) => {
                const merged = accumulated ? (accumulated + '\n\n' + live) : live;
                preview.innerHTML = md2html(cleanGenerated(merged));
                preview.scrollTop = 9999;
              },
              `Part ${k}/${parts}` + (attempt > 1 ? ` (deneme ${attempt}/3)` : '')
            );
            if (r && r.text && r.text.trim()) { text = r.text; break; }
            partialErr = 'boş yanıt';
          } catch (e) {
            partialErr = e.message || String(e);
          }
          if (attempt < 3) {
            const s2 = st();
            if (s2) s2.textContent = `Part ${k}/${parts} alınamadı (${partialErr}) — ` +
                                     `${attempt * 5} sn sonra tekrar denenecek...`;
            await new Promise(res => setTimeout(res, attempt * 5000));
          }
        }

        if (text === null) break;          // 3 deneme de tutmadı → eldekiyle devam

        let piece = cleanGenerated(text);
        if (k > 1) {
          // sonraki parçalardan kazara yazılan H1/H2 başlıklarını temizle
          piece = piece.replace(/^#[^\n]*\n+/m, '').replace(/^##[^\n]*\n+/m, '').trim();
        }
        accumulated = accumulated ? (accumulated + '\n\n' + piece) : piece;
        preview.innerHTML = md2html(accumulated);
        doneParts = k;
      }
      finalContent = accumulated;
      // Hiç metin yoksa gösterecek bir şey de yok; gerçek sebebi göster.
      if (!finalContent) throw new Error(`Part 1/${parts} alınamadı — ${partialErr || 'bilinmeyen hata'}`);

    } else {
      // ── Tek parça (Anthropic veya DeepSeek 1 parça) ─────
      // Burada da geçici hata üretimi bitirmez. Akış yarıda kesilirse
      // runGenerateStream eldeki metinle çözülür, yani tekrar denemek ancak
      // HİÇ metin gelmediğinde olur — mükerrer içerik riski yok.
      let res = null;
      for (let attempt = 1; attempt <= 3; attempt++) {
        const s = document.getElementById('stream-status');
        if (s && attempt > 1) s.textContent = `İçerik üretiliyor — yeniden deneme ${attempt}/3...`;
        try {
          const r = await runGenerateStream(
            { ...baseParams },
            (live) => { preview.innerHTML = md2html(live); preview.scrollTop = 9999; },
            'İçerik üretiliyor' + (attempt > 1 ? ` (deneme ${attempt}/3)` : '')
          );
          if (r && r.text && r.text.trim()) { res = r; break; }
          partialErr = 'boş yanıt';
        } catch (e) {
          partialErr = e.message || String(e);
        }
        if (attempt < 3) {
          const s2 = document.getElementById('stream-status');
          if (s2) s2.textContent = `Alınamadı (${partialErr}) — ${attempt * 5} sn sonra tekrar denenecek...`;
          await new Promise(r => setTimeout(r, attempt * 5000));
        }
      }
      if (!res) throw new Error(partialErr || 'İçerik üretilemedi.');
      finalContent = cleanGenerated(res.text);
      finalStats   = res.stats || {};
      doneParts    = 1;
    }

    state.content = finalContent;
    preview.innerHTML = md2html(finalContent);
    setLoading(btn, false);

    const eksik      = (isDeepSeek && parts > 1 && doneParts < parts);
    const totalWords = finalStats.word_count || finalContent.split(/\s+/).filter(Boolean).length;
    const partsBadge = (isDeepSeek && parts > 1)
      ? `<div class="stat"><div class="stat-label">Parça</div><div class="stat-value"><span class="badge ${eksik ? 'badge-red' : 'badge-green'}">${doneParts}/${parts} ${eksik ? 'alındı' : 'tamamlandı'}</span></div></div>`
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
    // Eksik parça gizlenmez: metin duruyor, ama yarım olduğu açıkça söylenir.
    // Yayınlamak kullanıcının kararı; "İçerik Denetimi → Yarım Kalanları
    // Tamamla" bu metni kaldığı yerden sürdürebilir.
    if (eksik) {
      notify('gen-notif',
        `⚠ ${doneParts}/${parts} parça alındı — ${totalWords.toLocaleString('tr')} kelime elde. ` +
        `Kalan parça alınamadı: ${partialErr}. Metin duruyor; taslak kaydedip sonra tamamlayabilirsin.`, 'err');
    } else {
      notify('gen-notif', `✓ İçerik hazır — ${totalWords.toLocaleString('tr')} kelime. Meta yükleniyor...`, 'ok');
    }
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
let batchSettings = {};          // son batch ayarları (retry için)
let batchStatusBooks = [];       // son pollBatchStatus'tan gelen kitap listesi

/* batchId'yi localStorage'a yaz / oku — sayfa yenilenince kaybolmasın */
function saveBatchId(id) {
  try { if (id) localStorage.setItem('tls_batchId', id); else localStorage.removeItem('tls_batchId'); } catch(e) {}
}
function loadBatchId() {
  try { return localStorage.getItem('tls_batchId') || null; } catch(e) { return null; }
}

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
    workers:      workerCount,
  });

  if (!res.ok) {
    setLoading(btn, false);
    notify('bulk-notif', res.error, 'err');
    return;
  }

  batchId      = res.batch_id;
  saveBatchId(batchId);
  batchRunning = true;
  batchPaused  = false;
  batchSettings = { type, post_status: status, max_tokens: tokens, api_provider: activeProvider, parts, workerCount };

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

  // Poll bittikten sonra son durumu al (processing items hâlâ yazılıyor olabilir)
  for (let i = 0; i < 5; i++) {
    await delay(3000);
    const fr = await fetch(API('batch-status.php?batch_id=') + batchId).then(x => x.json()).catch(() => null);
    if (fr?.ok) {
      renderBatchStatus(fr.batch);
      const stillProcessing = (fr.batch.books || []).some(b => b.status === 'processing');
      if (!stillProcessing) break;
    }
  }

  // Tamamlandı
  batchRunning = false;
  saveBatchId(null);
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

/* ── Retry: hatalı kitapları yeni mini-batch ile tekrar işle ── */
async function retryBooks(books) {
  if (!books || !books.length) return;
  if (batchRunning) { alert('Önce mevcut batch tamamlanmalı veya durdurulmalı.'); return; }

  const s = batchSettings;
  if (!s.type) { alert('Batch ayarları bulunamadı. Sayfayı yenileyin.'); return; }

  // Kitap listesini batch-create formatına çevir
  const bookList = books.map(bk => ({
    book_title:  bk.book_title  || bk.title  || '',
    author_name: bk.author_name || bk.author || '',
    cover:       bk.cover_url   || bk.cover  || '',
  })).filter(b => b.book_title);

  if (!bookList.length) return;

  // "Hepsini Tekrar Dene" butonunu kilitle
  const retryAllBtn = document.getElementById('btn-retry-all-failed');
  if (retryAllBtn) { retryAllBtn.disabled = true; retryAllBtn.textContent = 'Batch oluşturuluyor...'; }

  const res = await postData(API('batch-create.php'), {
    books:        JSON.stringify(bookList),
    type:         s.type         || 'summary',
    post_status:  s.post_status  || 'draft',
    max_tokens:   s.max_tokens   || 3000,
    api_provider: s.api_provider || 'deepseek',
    parts:        s.parts        || 2,
  });

  if (!res.ok) {
    alert('Retry batch oluşturulamadı: ' + (res.error || 'bilinmeyen hata'));
    if (retryAllBtn) { retryAllBtn.disabled = false; retryAllBtn.textContent = `↺ ${books.length} hatayı tekrar dene`; }
    return;
  }

  batchId      = res.batch_id;
  batchRunning = true;
  batchPaused  = false;

  const wc = s.workerCount || 2;
  notify('bulk-notif', `↺ Retry batch oluşturuldu (${res.total} kitap). ${wc} worker başlatılıyor...`, 'ok');

  document.getElementById('batch-progress-wrap').style.display = '';
  document.getElementById('btn-batch-pause').style.display = '';

  // Yeni listeyi tabloya yaz
  renderBulkTable(bookList.map(b => ({ book_title: b.book_title, author_name: b.author_name })));

  fireDrainWorkers(wc);
  await pollBatchUntilDone();
}

function fireDrainWorkers(count) {
  const statusWrap = document.getElementById('batch-worker-status');
  if (statusWrap) statusWrap.textContent = `${count} worker arka planda çalışıyor — ilerleme aşağıda.`;
  for (let i = 0; i < count; i++) {
    postData(API('batch-worker.php'), { batch_id: batchId }).catch(() => {});
  }
}

/* ── Durum sorgulama döngüsü ───────────────────────────
 * Yalnızca ilerlemeyi gösterir. Takılan worker'ları yeniden ateşleme işini
 * artık küresel nöbetçi (checkActiveJobs) üstleniyor — o, batch'in SEÇİLEN
 * worker sayısına saygı duyar ve fazladan worker açmaz. Burada ayrıca ateşlemek
 * "1 worker (güvenli)" seçimini bozardı, bu yüzden kaldırıldı.
 */
async function pollBatchUntilDone() {
  let pollFailures = 0;

  // SADECE GÖSTERİM. Worker'ları canlı tutma/yeniden ateşleme işini tek bir
  // motor üstlenir: küresel nöbetçi (checkActiveJobs, her 10sn). O, ucuz
  // heartbeat'e bakarak SEÇİLEN worker sayısını korur ve asla aşmaz — bu
  // yüzden burada ayrıca ateşlemiyoruz (eskiden buradaki kör 90sn re-fire
  // 4-parça yavaş kitaplarda her 90sn'de fazladan worker açıp DeepSeek'i
  // boğuyordu).
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

    if (b && (b.status === 'done' || b.status === 'cancelled' || b.done >= b.total)) break;

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

  batchStatusBooks = b.books;

  const nowSec = Math.floor(Date.now() / 1000);
  b.books.forEach((bk, i) => {
    const st  = bk.status;
    // İşlenen kitap için geçen süre — "takıldı mı yavaş mı" ayrımı için
    let procLabel = 'İşleniyor...';
    let procCls = 'working';
    if (st === 'processing' && bk.processing_since > 0) {
      const el = nowSec - bk.processing_since;
      const m = Math.floor(el / 60), s = el % 60;
      const t = m > 0 ? `${m}dk` : `${s}sn`;
      // Canlılık: heartbeat yaşı > 180sn (veya hb yok) ve post yoksa = worker ölmüş
      const dead = (bk.hb_age === null || bk.hb_age > 180) && !bk.post_id;
      if (dead) { procLabel = `⚠ ${t} takılı`; procCls = 'err'; }
      else { procLabel = `İşleniyor... ${t}`; }
    }
    const cls = st==='done'?'ok':st==='error'?'err':st==='duplicate'?'gray':st==='processing'?procCls:'gray';
    // Tamamlandı ama bir parçası eksik kaldıysa ⚠ ile göster (içerik kısa olabilir)
    const partial = st==='done' && bk.error && bk.error !== 'duplicate_skipped';
    const lbl = st==='done'
      ? `✓ <a href="${bk.edit_url}" target="_blank">#${bk.post_id}</a>${bk.cover_set?' 🖼':''}`
        + (partial ? ` <span title="${String(bk.error).replace(/"/g,'&quot;')}" style="color:#e0a800">⚠</span>` : '')
      : st==='error'     ? '✗ ' + (bk.error||'Hata')
      : st==='duplicate' ? '⊘ Zaten var'
      : st==='processing'? procLabel
      : 'Bekliyor';
    setRowStatus(i, cls, lbl, st==='error' ? bk : null);
  });

  // "Hepsini Tekrar Dene" butonu
  const failedBooks = b.books.filter(bk => bk.status === 'error');
  let retryAllBtn = document.getElementById('btn-retry-all-failed');
  if (failedBooks.length > 0 && !batchRunning) {
    if (!retryAllBtn) {
      retryAllBtn = document.createElement('button');
      retryAllBtn.id = 'btn-retry-all-failed';
      retryAllBtn.className = 'btn btn-ghost btn-sm';
      retryAllBtn.style.color = 'var(--gold)';
      retryAllBtn.onclick = () => retryBooks(failedBooks);
      document.getElementById('bulk-summary').after(retryAllBtn);
    }
    retryAllBtn.textContent = `↺ ${failedBooks.length} hatayı tekrar dene`;
    retryAllBtn.style.display = '';
  } else if (retryAllBtn) {
    retryAllBtn.style.display = 'none';
  }
}

function setRowStatus(idx, st, html, bookData) {
  const cell = document.querySelector(`#brow-${idx} .status-cell`);
  if (!cell) return;
  const cls = st==='ok'?'badge-green':st==='err'?'badge-red':st==='working'?'badge-gold':'badge-gray';
  const retryBtn = bookData
    ? ` <button class="btn-retry-single" title="Tekrar dene"
          onclick="retryBooks([${JSON.stringify(bookData).replace(/"/g,'&quot;')}])"
          style="margin-left:6px;padding:2px 8px;font-size:11px;border:1px solid var(--gold);background:none;color:var(--gold);border-radius:4px;cursor:pointer">↺</button>`
    : '';
  cell.innerHTML = `<span class="badge ${cls}">${html}</span>${retryBtn}`;
}

/* ══ LİSTE OLUŞTUR (Builder) ═════════════════════════════ */
let builderAuthors      = [];   // [{author, era, note, onSite}]
let builderList         = [];   // [{title, author, year, verified, cover}]
let builderAuthorsOffset = 0;   // sayfalama: şu ana kadar kaç yazar yüklendi
let builderShowExisting  = false;

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

// "Tümünü Getir" — kategorideki tüm yazarları otomatik sayfalayarak çek
document.getElementById('btn-fetch-all-authors')?.addEventListener('click', async () => {
  const category = document.getElementById('builder-category').value.trim();
  if (!category) { notify('builder-notif','Kategori girin.','err'); return; }
  const btn = document.getElementById('btn-fetch-all-authors');
  setLoading(btn, true, 'Tümü getiriliyor...');
  builderAuthors = [];
  builderAuthorsOffset = 0;
  const PAGE = 200;     // sayfa başına (büyük → az tur → hızlı)
  const CAP  = 20000;   // sonsuz döngü güvenliği
  let exhausted = false;
  try {
    let interrupted = false;
    while (builderAuthorsOffset < CAP) {
      btn.innerHTML = `<span class="loader"></span> Getiriliyor... (${builderAuthors.length})`;
      // Derin offset'te WDQS arada zaman aşımına uğrayabilir → aynı sayfayı
      // 3 kez dene (kısa backoff). Yine olmazsa kopmadan dur, buton kalsın.
      let res = null;
      for (let attempt = 1; attempt <= 3; attempt++) {
        res = await postData(API('list-authors.php'), { category, count: PAGE, offset: builderAuthorsOffset }, 120000);
        if (res.ok) break;
        btn.innerHTML = `<span class="loader"></span> Yeniden deneniyor... (${builderAuthors.length})`;
        await new Promise(r => setTimeout(r, attempt * 1500));
      }
      if (!res || !res.ok) {
        interrupted = true;
        notify('builder-notif',
          `Wikidata ${builderAuthorsOffset}. sırada yanıt vermedi. ${builderAuthors.length} yazar getirildi — `
          + `"Sonraki 200 yazar" ile kaldığın yerden devam edebilirsin.`, 'err');
        break;
      }
      const got = res.authors || [];
      const have = new Set(builderAuthors.map(a => a.author.toLowerCase()));
      const fresh = got.filter(a => !have.has(a.author.toLowerCase()));
      builderAuthors = builderAuthors.concat(fresh);
      builderAuthorsOffset += PAGE;
      renderBuilderAuthors();
      // Tükendi kararı HAM QID sayısına göre (etiketsiz yazarlar filtrelendiği
      // için got.length < PAGE olabilir ama kategoride hâlâ sayfa kalmış olabilir).
      const raw = (res.raw != null) ? res.raw : got.length;
      if (raw < PAGE) { exhausted = true; break; }   // kategori tükendi
    }
    if (builderAuthors.length && !interrupted) {
      // Tümü geldiyse "Sonraki" butonunu gizle — gereksiz
      if (exhausted) { const nb = document.getElementById('btn-next-authors'); if (nb) nb.style.display = 'none'; }
      checkAuthorsOnSite();
      notify('builder-notif', `✓ Toplam ${builderAuthors.length} yazar getirildi${exhausted ? ' (kategori tamamı)' : ''}.`, 'ok');
    } else if (builderAuthors.length && interrupted) {
      checkAuthorsOnSite();
    }
  } catch(e) {
    notify('builder-notif', 'Hata: ' + e.message, 'err');
  } finally {
    setLoading(btn, false);
  }
});

// "Sitede olanları çıkar" — listeden sitede zaten olan yazarları kaldır
document.getElementById('btn-remove-onsite')?.addEventListener('click', async () => {
  const btn = document.getElementById('btn-remove-onsite');
  if (!builderAuthors.length) { notify('builder-notif','Önce yazar listesi getir.','err'); return; }
  setLoading(btn, true, 'Kontrol ediliyor...');
  try {
    await checkAuthorsOnSite();   // sitede-var durumunu tazele
    const before = builderAuthors.length;
    builderAuthors = builderAuthors.filter(a => !a.onSite);
    const removed = before - builderAuthors.length;
    renderBuilderAuthors();
    notify('builder-notif',
      removed ? `✓ ${removed} sitede olan yazar listeden çıkarıldı. Kalan: ${builderAuthors.length}` : 'Sitede olan yazar bulunamadı.',
      removed ? 'ok' : 'err');
  } catch(e) {
    notify('builder-notif', 'Hata: ' + e.message, 'err');
  } finally {
    setLoading(btn, false);
  }
});

// "Sunucuda Eserleri Çek" — builder listesini sunucuya gönder, arka planda (cron) çekilsin
document.getElementById('btn-builder-to-queue')?.addEventListener('click', async () => {
  if (!builderAuthors.length) { notify('builder-notif','Önce yazar listesi getir.','err'); return; }
  const category = document.getElementById('builder-category').value.trim() || 'liste';
  if (!confirm(`${builderAuthors.length} yazar sunucuya gönderilecek. Eserleri arka planda (tarayıcı/oturum kapalı olsa bile) çekilecek. Bittiğinde "Kuyruk" sayfasından 100'erli ZIP indirebilirsin. Devam edilsin mi?`)) return;
  const btn = document.getElementById('btn-builder-to-queue');
  setLoading(btn, true, 'Sunucuya gönderiliyor...');
  try {
    const res = await postData(API('queue-create.php'), {
      category,
      list_only: 1,   // yalnız liste — içerik otomatik ÜRETİLMEZ
      authors: JSON.stringify(builderAuthors.map(a => a.author)),  // sadece isimler (küçük payload)
    }, 120000);
    if (!res.ok) { notify('builder-notif', res.error || 'Hata', 'err'); return; }
    notify('builder-notif',
      `✓ Sunucu kuyruğu oluşturuldu (${builderAuthors.length} yazar). "Kuyruk" sayfasına geç — cron arka planda eserleri çekiyor. Bitince oradan "100'erli ZIP" indir.`, 'ok');
  } catch(e) {
    notify('builder-notif', e.message, 'err');
  } finally {
    setLoading(btn, false);
  }
});

// ── Toplu Yazar Ekle (CSV) → sunucu kuyruğu (arka planda eser çekme) ──
// Basit CSV ayrıştırıcı (tırnaklı alanlar + gömülü virgül/newline destekli)
function parseCSV(text) {
  text = text.replace(/^﻿/, '');        // BOM at
  const rows = []; let row = []; let field = ''; let inQ = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (inQ) {
      if (c === '"') { if (text[i+1] === '"') { field += '"'; i++; } else inQ = false; }
      else field += c;
    } else {
      if (c === '"') inQ = true;
      else if (c === ',') { row.push(field); field = ''; }
      else if (c === '\n') { row.push(field); rows.push(row); row = []; field = ''; }
      else if (c === '\r') { /* yoksay */ }
      else field += c;
    }
  }
  if (field !== '' || row.length) { row.push(field); rows.push(row); }
  return rows.filter(r => r.some(x => (x || '').trim() !== ''));
}

// Modern sürükle-bırak dosya alanı
let bulkCsvFile = null;
(function initBulkDropzone(){
  const dz    = document.getElementById('bulk-dropzone');
  const input = document.getElementById('bulk-authors-file');
  const nameEl= document.getElementById('bulk-dz-filename');
  const btn   = document.getElementById('btn-bulk-authors-upload');
  if (!dz || !input) return;

  const setFile = (f) => {
    bulkCsvFile = f || null;
    if (nameEl) nameEl.textContent = f ? ('✓ ' + f.name) : '';
    if (btn) btn.disabled = !f;
  };
  dz.addEventListener('click', () => input.click());
  dz.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
  input.addEventListener('change', () => setFile(input.files?.[0] || null));
  ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('dragover'); }));
  ['dragleave','dragend','drop'].forEach(ev => dz.addEventListener(ev, () => dz.classList.remove('dragover')));
  dz.addEventListener('drop', (e) => {
    e.preventDefault();
    const f = e.dataTransfer?.files?.[0];
    if (f) setFile(f);
  });
})();

// "Listeyi Yükle" → CSV'yi ayrıştır, yazarları EKRANDAKİ listeye doldur.
// Sonra kullanıcı "⚡ tarayıcıda" veya "🌐 sunucuda" seçer (iki akış da hazır).
document.getElementById('btn-bulk-authors-upload')?.addEventListener('click', () => {
  const file = bulkCsvFile || document.getElementById('bulk-authors-file')?.files?.[0];
  if (!file) { notify('builder-notif', 'Önce bir CSV dosyası seç ya da sürükle.', 'err'); return; }
  const btn = document.getElementById('btn-bulk-authors-upload');
  const reader = new FileReader();
  reader.onload = () => {
    try {
      const rows = parseCSV(String(reader.result || ''));
      if (!rows.length) { notify('builder-notif', 'CSV boş görünüyor.', 'err'); return; }

      // Sütunları tespit et: başlıkta Yazar/Author + varsa Dönem/Not.
      const header = rows[0].map(h => (h || '').trim().toLowerCase());
      let col   = header.findIndex(h => /^(yazar|yazar ad[ıi]|author|name|ad)$/.test(h));
      let eraCol= header.findIndex(h => /^(d[öo]nem|era|y[ıi]llar)$/.test(h));
      let noteCol=header.findIndex(h => /^(not|note|a[çc][ıi]klama|description)$/.test(h));
      let dataRows = rows;
      if (col >= 0) { dataRows = rows.slice(1); }
      else {
        const first = rows[0];
        col = first.findIndex(x => (x || '').trim() !== '' && isNaN(Number((x || '').trim())));
        if (col < 0) col = first.length > 1 ? 1 : 0;
      }

      const seen = new Set(); const list = [];
      for (const r of dataRows) {
        const n = (r[col] || '').trim();
        if (!n) continue;
        const k = n.toLowerCase();
        if (seen.has(k)) continue;
        seen.add(k);
        list.push({ author: n, era: eraCol >= 0 ? (r[eraCol] || '').trim() : '', note: noteCol >= 0 ? (r[noteCol] || '').trim() : '' });
      }
      if (!list.length) { notify('builder-notif', 'CSV\'de yazar adı bulunamadı (sütun tespit edilemedi).', 'err'); return; }

      // Ekrandaki listeye yükle → mevcut butonlar (⚡/🌐) bu liste üstünde çalışır.
      const listName = document.getElementById('bulk-authors-name')?.value.trim() || file.name.replace(/\.csv$/i, '');
      if (listName) { const bc = document.getElementById('builder-category'); if (bc) bc.value = listName; }
      builderAuthors = list;
      builderAuthorsOffset = list.length;
      renderBuilderAuthors();
      checkAuthorsOnSite();
      document.getElementById('builder-authors-card')?.scrollIntoView({ behavior: 'smooth' });
      // "Sonraki" butonu CSV yüklemesinde anlamsız → gizle
      const nb = document.getElementById('btn-next-authors'); if (nb) nb.style.display = 'none';
      notify('builder-notif',
        `✓ ${list.length} yazar yüklendi. Aşağıdan seç: "⚡ Tüm Yazarların Eserlerini Getir" (tarayıcıda, hızlı) ya da "🌐 Sunucuda Eserleri Çek" (arka plan, binlerce yazar için).`, 'ok');
    } catch (e) {
      notify('builder-notif', 'Hata: ' + e.message, 'err');
    } finally {
      setLoading(btn, false);
    }
  };
  reader.onerror = () => notify('builder-notif', 'Dosya okunamadı.', 'err');
  reader.readAsText(file, 'UTF-8');
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

  // Toplam yazar sayısı — listenin ALTINDA her zaman görünür (uzun listede
  // üstteki sayaç kayboluyor; aşağı inince burada görürsün)
  let totalEl = document.getElementById('builder-authors-total');
  if (!totalEl) {
    totalEl = document.createElement('div');
    totalEl.id = 'builder-authors-total';
    totalEl.style.cssText = 'margin-top:12px;font-weight:700;font-size:14px;color:var(--gold)';
    document.getElementById('builder-authors-card').appendChild(totalEl);
  }
  totalEl.textContent = `Toplam: ${builderAuthors.length} yazar`;

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
  const STEP = 200;   // manuel adım: 200'erli (eskiden 50)
  nextBtn.textContent = `Sonraki ${STEP} yazar (${builderAuthorsOffset + 1}–${builderAuthorsOffset + STEP})`;
  nextBtn.onclick = async () => {
    setLoading(nextBtn, true, 'Yükleniyor...');
    try {
      const res = await postData(API('list-authors.php'), {
        category, count: STEP, offset: builderAuthorsOffset, api_provider: activeProvider,
      }, 120000);
      if (!res.ok) { notify('builder-notif', res.error, 'err'); return; }
      const have = new Set(builderAuthors.map(a => a.author.toLowerCase()));
      const fresh = res.authors.filter(a => !have.has(a.author.toLowerCase()));
      builderAuthors = builderAuthors.concat(fresh);
      builderAuthorsOffset += STEP;
      // Ham QID sayısı STEP'ten azsa kategori tükendi → butonu gizle
      const raw = (res.raw != null) ? res.raw : res.authors.length;
      renderBuilderAuthors();
      if (raw < STEP) { const nb = document.getElementById('btn-next-authors'); if (nb) nb.style.display = 'none'; }
      checkAuthorsOnSite();
      if (raw < STEP) {
        notify('builder-notif', `✓ ${fresh.length} yeni yazar eklendi. Kategori tamamı: ${builderAuthors.length}.`, 'ok');
      } else {
        notify('builder-notif', `✓ ${fresh.length} yeni yazar eklendi. Toplam: ${builderAuthors.length}`, 'ok');
      }
    } catch(e) { notify('builder-notif', e.message, 'err'); }
    setLoading(nextBtn, false);
  };
}

// Sitedeki yazar terimlerini çek — eşleşenlere "Sitede var" rozeti koy
// Yazar adı normalize: aksan/noktalama/parantez duyarsız eşleşme.
// "Avicenna (İbn Sînâ)" → hem "avicenna" hem "ibn sina" anahtarı üretir.
function normAuthorName(s) {
  return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '')
    .toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
}
function authorNameKeys(s) {
  const keys = new Set();
  const full = normAuthorName(s);
  if (full) keys.add(full);
  const m = String(s || '').match(/^(.*?)\s*[\(（](.+?)[\)）]\s*$/);
  if (m) {
    const outer = normAuthorName(m[1]); if (outer) keys.add(outer);
    const inner = normAuthorName(m[2]); if (inner) keys.add(inner);
  }
  return keys;
}
// Sitedeki yazar terimlerinden normalize anahtar seti kur (tüm varyantlar dahil)
async function fetchSiteAuthorKeys() {
  const res = await postData(API('list-works.php'), { author: '_', mode: 'authors_exist' });
  if (!res.ok || !Array.isArray(res.authors)) return null;
  const set = new Set();
  for (const n of res.authors) for (const k of authorNameKeys(n)) set.add(k);
  return set;
}

async function checkAuthorsOnSite() {
  try {
    const siteSet = await fetchSiteAuthorKeys();
    if (!siteSet) return;
    let changed = false;
    builderAuthors.forEach(a => {
      const was = a.onSite;
      // Liste tarafındaki adın da tüm varyantlarını dene
      a.onSite = [...authorNameKeys(a.author)].some(k => siteSet.has(k));
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
  const sleep = (ms) => new Promise(r => setTimeout(r, ms));
  let skipped = 0, failed = 0;
  const total = builderAuthors.length;
  for (let i = 0; i < builderAuthors.length; i++) {
    const cell = document.querySelector(`#bauthor-${i} .action-cell`);
    if (builderAuthors[i].onSite) {
      skipped++;
      if (cell) cell.innerHTML = `<span class="badge badge-gray" title="Yazar zaten sitede">atlandı</span>`;
      continue;
    }
    if (cell) cell.innerHTML = '<span class="loader"></span>';
    btn.innerHTML = `<span class="loader"></span> Getiriliyor... (${i+1}/${total})`;

    // Hız sınırı (429) gibi geçici hatalarda yazarı 3 kez dene, artan beklemeyle.
    let added = -1, errMsg = '';
    for (let attempt = 1; attempt <= 3; attempt++) {
      [added, errMsg] = await fetchAuthorWorks(builderAuthors[i].author);
      if (added >= 0) break;
      if (cell) cell.innerHTML = `<span class="loader"></span>`;
      await sleep(attempt * 1500);   // 1.5s, 3s backoff
    }
    if (added < 0) failed++;
    if (cell) cell.innerHTML = added < 0
      ? `<span class="badge badge-gray" title="${errMsg||''}">hata</span>`
      : `<span class="badge badge-green">+${added}</span>`;

    await sleep(250);   // yazarlar arası küçük bekleme → OpenLibrary'yi boğma
  }
  setLoading(btn, false);
  let msg = `✓ Tamamlandı — toplam ${builderList.length} kitap`;
  if (skipped) msg += ` · ${skipped} sitede olan atlandı`;
  if (failed)  msg += ` · ${failed} yazar hata (tekrar denemek için "Eserleri" butonuna bas)`;
  notify('builder-notif', msg, failed ? 'err' : 'ok');
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
        verified: false, cover: w.cover || '',
      };
      builderList.push(row);
      newRows.push(row);
    }
    renderBuilderList();
    if (source !== 'llm') notify('builder-notif', `✓ ${author}: ${newRows.length} eser (${source}).`, 'ok');

    // Kapak/yıl doğrulamasını parça parça (6'lı) yap — her istek 100s altında kalır
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

  const vCount = builderList.filter(b => b.verified).length;

  let countText = `${builderList.length} kitap`;
  document.getElementById('builder-list-count').textContent = countText;

  const vBadge = document.getElementById('builder-list-verified');
  if (vCount > 0) { vBadge.style.display = ''; vBadge.textContent = `✓ ${vCount} doğrulandı`; }
  else vBadge.style.display = 'none';

  const toggleBtn = document.getElementById('btn-toggle-existing');
  if (toggleBtn) toggleBtn.style.display = 'none';

  const tb = document.querySelector('#builder-list-table tbody');
  tb.innerHTML = builderList.map((b, i) => {
    return `<tr id="brow-list-${i}">
      <td style="color:var(--muted)">${i+1}</td>
      <td>${b.cover ? `<img src="${b.cover}" style="width:32px;height:46px;object-fit:cover;border-radius:3px" onerror="this.style.display='none'">` : '—'}</td>
      <td>${workLabel(b)}</td>
      <td style="color:var(--muted)">${b.author}</td>
      <td style="color:var(--muted)">${b.year||''}</td>
      <td>${b.verified ? '<span class="badge badge-green">✓</span>' : '<span class="badge badge-gray">?</span>'}</td>
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
  batchBooks = builderList.map(b => ({ book_title: workLabel(b), author_name: b.author, category: '', cover: b.cover || '', year: b.year || '' }));
  updateBatchBadge();
  renderBulkTable(batchBooks);
  document.getElementById('btn-batch-start').disabled = false;
  document.querySelector('.tab-top-btn[data-mode=bulk]')?.click();
  notify('bulk-notif', `✓ ${batchBooks.length} kitap batch'e aktarıldı. Ayarları seçip başlat.`, 'ok');
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


async function resumeBatch(id) {
  await postData(API('batch-control.php'), { batch_id: id, action: 'resume' }).catch(() => {});
  await postData(API('batch-worker.php'), { batch_id: id }, 10000).catch(() => {});
  notify('queue-create-notif', '✓ Worker yeniden başlatıldı. Toplu Batch sekmesinden izleyebilirsin.', 'ok');
  loadQueueList();
}

async function retryBatchErrors(id) {
  await postData(API('batch-control.php'), { batch_id: id, action: 'retry_errors' }).catch(() => {});
  await postData(API('batch-worker.php'), { batch_id: id }, 10000).catch(() => {});
  notify('queue-create-notif', '✓ Hatalı kitaplar kuyruğa alındı, worker başlatıldı.', 'ok');
  loadQueueList();
}

async function loadQueueList() {
  const body = document.getElementById('queue-list-body');
  if (!body) return;

  /* ── Yarım kalan toplu batchler ──
     NOT: Kuyruk sayfasında bu blok ARTIK render edilmez — aynı bilgiyi kitap
     listesiyle birlikte PHP tarafı (Yarım Kalan Toplu Batchler kartı) gösteriyor;
     iki ayrı blok kafa karıştırıyordu. Kod, PHP kartı olmayan sayfalar için duruyor. */
  let incompleteBatchHtml = '';
  const phpBatchCardExists = !!document.querySelector('[data-batch-card]');
  try {
    const bl = phpBatchCardExists ? { batches: [] } : await postData(API('batch-list.php'), {});
    const incomplete = (bl.batches || []).filter(b => b.status !== 'done' && b.total > 0 && b.done < b.total);
    if (incomplete.length) {
      incompleteBatchHtml = `<div style="margin-bottom:14px">
        <div style="font-size:12px;font-weight:600;color:var(--gold);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em">
          ⚠ Yarım Kalan Toplu Batchler
        </div>
        ${incomplete.map(b => {
          const pending = b.total - b.done;
          const pct = Math.round(b.done / b.total * 100);
          // Canlılık göstergesi: son ilerlemeden bu yana geçen süre
          const agoS = b.last_activity ? Math.max(0, Math.floor(Date.now()/1000 - b.last_activity)) : null;
          const agoTxt = agoS === null ? '' :
            agoS < 90 ? `${agoS} sn önce ilerledi` :
            agoS < 5400 ? `${Math.round(agoS/60)} dk önce ilerledi` :
            `${Math.round(agoS/3600)} sa önce ilerledi`;
          const alive = agoS !== null && agoS < 300;   // 5 dk içinde ilerlemişse canlı say
          const liveBadge = agoS === null ? '' :
            `<span style="font-size:11px;color:${alive ? 'var(--green)' : '#cc6b00'}">${alive ? '● çalışıyor' : '⏸ duraklamış olabilir'} · ${agoTxt}</span>`;
          return `<div class="card" style="margin-bottom:8px;padding:12px;border-left:3px solid var(--gold)">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
              <div>
                <strong>${b.type === 'analysis' ? 'Derin Analiz' : 'Özet'}</strong>
                <span style="color:var(--muted);font-size:12px;margin-left:8px">${new Date(b.created_at*1000).toLocaleString('tr-TR')}</span>
              </div>
              <span style="font-size:12px;color:var(--muted)">${b.ok} ✓ · ${b.failed} hata · ${pending} bekliyor</span>
            </div>
            ${liveBadge ? `<div style="margin-top:4px">${liveBadge}</div>` : ''}
            <div style="background:#2a2a2a;border-radius:4px;height:5px;overflow:hidden;margin:8px 0">
              <div style="background:var(--gold);height:100%;width:${pct}%"></div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              ${pending > b.failed ? `<button class="btn btn-primary btn-sm" onclick="resumeBatch('${b.id}')">▶ Kaldığı Yerden Devam Et</button>` : ''}
              ${b.failed > 0 ? `<button class="btn btn-ghost btn-sm" style="color:var(--gold)" onclick="retryBatchErrors('${b.id}')">↺ ${b.failed} hatayı tekrar dene</button>` : ''}
            </div>
          </div>`;
        }).join('')}
      </div>`;
    }
  } catch(e) {}

  try {
    const res = await postData(API('queue-list.php'), {});
    if (!res.ok || !res.queues.length) {
      body.innerHTML = incompleteBatchHtml + '<p style="color:var(--muted);font-size:13px">Henüz otomatik kuyruk yok.</p>';
      return;
    }
    body.innerHTML = incompleteBatchHtml + res.queues.map(q => {
      // 'building' (list_only) kuyrukta gerçek ilerleme = işlenen yazar oranı;
      // diğerlerinde = üretilen içerik (done/total).
      const isBuilding = q.status === 'building';
      const pct = isBuilding
        ? (q.authors_total > 0 ? Math.round((q.authors_built||0) / q.authors_total * 100) : 0)
        : (q.total > 0 ? Math.round(q.done / q.total * 100) : 0);
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
          <div style="font-size:11px;color:var(--muted);margin-top:4px">${isBuilding
            ? `${q.authors_built||0}/${q.authors_total||0} yazar işlendi · ${q.total||0} eser bulundu`
            : `${q.done}/${q.total} işlendi · ${q.ok} başarılı · ${q.failed} hata`}</div>
        </div>
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
          ${q.status === 'building' ? `<button class="btn btn-primary btn-sm" onclick="continueBuilding('${q.id}',${q.authors_built||0},${q.authors_total||50})">⚙ Oluşturmayı Devam Ettir</button>` : ''}
          ${q.status === 'running'  ? `<button class="btn btn-primary btn-sm" onclick="resumeQueue('${q.id}')">▶ İşlemi Başlat</button>` : ''}
          ${q.status === 'done'     ? `<span class="badge badge-green">✓ Tamamlandı</span>` : ''}
          ${q.status === 'list_ready' ? `<span class="badge badge-green">✓ Liste hazır — indir</span>` : ''}
          ${q.status === 'building' ? `<span class="badge badge-gold">⏳ Eserler çekiliyor (cron arka planda)</span>` : ''}
          ${q.total > 0 ? `<a class="btn btn-ghost btn-sm" href="api/queue-export.php?batch_id=${q.id}" download>⬇ CSV (tek)</a>
          <a class="btn btn-ghost btn-sm" href="api/queue-export.php?batch_id=${q.id}&zip=1&per=100" download title="100'erli yazar gruplarına bölünmüş ayrı CSV'ler, tek ZIP">⬇ 100'erli ZIP</a>` : ''}
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

    // Adım 2: her 5 yazarın eserlerini sırayla çek (her istek ~30-90s, Wikidata SPARQL)
    const batchId = res.batch_id;
    let done = false;
    let failStreak = 0;
    while (!done) {
      btn.innerHTML = `<span class="loader"></span> Eserler getiriliyor...`;
      try {
        const br = await postData(API('queue-build.php'), { batch_id: batchId, chunk: 3 }, 180000);
        if (!br.ok) break;
        failStreak = 0;
        notify('queue-create-notif', `${br.build_msg}`, 'ok');
        await loadQueueList();
        done = br.done;
      } catch(e) {
        failStreak++;
        if (failStreak >= 3) break;
        await new Promise(r => setTimeout(r, 3000));
      }
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
  let failStreak = 0;
  while (built < authorsTotal) {
    try {
      const br = await postData(API('queue-build.php'), { batch_id: batchId, chunk: 3 }, 180000);
      if (!br.ok) break;
      // İlerleme yoksa (DNS/erişim hatası ya da takılı yazar) sonsuz döngüye girme.
      if (br.authors_built <= built) {
        notify('queue-create-notif',
          br.infra_fail ? `Bu tarayıcıdan da OpenLibrary'ye erişilemedi: ${br.build_msg}` : br.build_msg, 'err');
        break;
      }
      failStreak = 0;
      built = br.authors_built;
      notify('queue-create-notif', br.build_msg, 'ok');
      await loadQueueList();
      if (br.done) break;
    } catch(e) {
      failStreak++;
      notify('queue-create-notif', `Hata (${failStreak}/3): ${e.message} — yeniden deneniyor...`, 'err');
      if (failStreak >= 3) break;
      await new Promise(r => setTimeout(r, 3000));
    }
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

/* ══ LİSTE TEMİZLE ═══════════════════════════════════════════
 * CSV (Kitap Adı, Yazar Adı, Yıl, Kapak) → yazar bazında grupla →
 * her yazar için clean-list.php (kural + AI hakem) → önizleme → temiz CSV.
 */
let cleanerFile = null;
let cleanerWorks = [];     // temiz sonuç [{title,author,year,cover,merged}]
let cleanerRemoved = [];   // elenenler  [{title,author,year,cover,reason,restored}]
let cleanerCancel = false;

(function initCleanerDropzone(){
  const dz    = document.getElementById('cleaner-dropzone');
  const input = document.getElementById('cleaner-file');
  const nameEl= document.getElementById('cleaner-dz-filename');
  const btn   = document.getElementById('btn-cleaner-start');
  if (!dz || !input) return;
  const setFile = (f) => {
    cleanerFile = f || null;
    if (nameEl) nameEl.textContent = f ? ('✓ ' + f.name) : '';
    if (btn) btn.disabled = !f;
  };
  dz.addEventListener('click', () => input.click());
  dz.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
  input.addEventListener('change', () => setFile(input.files?.[0] || null));
  ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('dragover'); }));
  ['dragleave','dragend','drop'].forEach(ev => dz.addEventListener(ev, () => dz.classList.remove('dragover')));
  dz.addEventListener('drop', (e) => { e.preventDefault(); const f = e.dataTransfer?.files?.[0]; if (f) setFile(f); });

  /* Yarım kalan temizleme var mı? Varsa dosyayı tekrar yüklemeye gerek yok —
     durum sunucuda duruyor, kaldığı yerden devam edilebilir. */
  postData(API('clean-progress.php'), { action: 'load' }, 60000)
    .then(job => {
      if (!job || !job.ok || job.none) return;
      const pending = job.pending || [];
      const bar = document.createElement('div');
      bar.style.cssText = 'margin:0 0 14px;padding:12px 16px;background:rgba(212,180,131,.12);border:1px solid var(--gold);border-radius:8px;font-size:13px;color:var(--gold)';
      bar.innerHTML = pending.length
        ? '&#9202; <b>Yarım kalan temizleme var</b> — ' + job.done + '/' + job.total +
          ' yazar bitti, <b>' + pending.length + '</b> kaldı. <span style="color:var(--muted)">(' +
          String(job.file_name||'').replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])) + ')</span>'
        : '&#10003; <b>Tamamlanmış temizleme sonucu duruyor</b> — ' + job.total +
          ' yazar. <span style="color:var(--muted)">(' + String(job.file_name||'').replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])) + ')</span>';

      const go = document.createElement('button');
      go.className = 'btn btn-primary btn-sm';
      go.style.cssText = 'margin-left:12px';
      go.textContent = pending.length ? '▶ Kaldığı Yerden Devam Et' : '⬇ Sonucu Göster';
      go.onclick = async () => {
        go.disabled = true;
        cleanerWorks   = job.works   || [];
        cleanerRemoved = job.removed || [];
        if (!pending.length) { renderCleanerResult(job.total_in || 0, job.file_name || 'liste.csv', job.ai_fails || 0); return; }
        const map = new Map();
        for (const a of pending) map.set(a, (job.by_author || {})[a] || []);
        document.getElementById('cleaner-progress-card').style.display = '';
        await runCleanerLoop(pending, map, job.use_ai ? 1 : 0, job.total_in || 0, job.file_name || 'liste.csv', job.done || 0);
      };

      const del = document.createElement('button');
      del.className = 'btn btn-ghost btn-sm';
      del.style.cssText = 'margin-left:8px;color:var(--red)';
      del.textContent = '✕ Sil';
      del.onclick = async () => {
        if (!confirm('Kayıtlı temizleme işi silinecek. Emin misin?')) return;
        await postData(API('clean-progress.php'), { action: 'clear' }, 30000).catch(()=>{});
        bar.remove();
      };

      bar.appendChild(go); bar.appendChild(del);
      dz.parentNode.insertBefore(bar, dz);
    })
    .catch(()=>{});
})();

document.getElementById('btn-cleaner-cancel')?.addEventListener('click', () => { cleanerCancel = true; });

document.getElementById('btn-cleaner-start')?.addEventListener('click', () => {
  const file = cleanerFile || document.getElementById('cleaner-file')?.files?.[0];
  if (!file) { notify('cleaner-notif', 'Önce bir CSV seç.', 'err'); return; }
  const reader = new FileReader();
  reader.onload = () => runCleaner(String(reader.result || ''), file.name);
  reader.onerror = () => notify('cleaner-notif', 'Dosya okunamadı.', 'err');
  reader.readAsText(file, 'UTF-8');
});

async function runCleaner(text, fileName) {
  const rows = parseCSV(text);
  if (rows.length < 2) { notify('cleaner-notif', 'CSV boş ya da yalnız başlık satırı var.', 'err'); return; }

  // Sütunları bul
  const header = rows[0].map(h => (h || '').trim().toLowerCase());
  const tCol = header.findIndex(h => /kitap|title|eser/.test(h));
  const aCol = header.findIndex(h => /yazar|author/.test(h));
  const yCol = header.findIndex(h => /y[ıi]l|year/.test(h));
  const cCol = header.findIndex(h => /kapak|cover/.test(h));
  if (tCol < 0 || aCol < 0) { notify('cleaner-notif', 'CSV\'de "Kitap Adı" ve "Yazar Adı" sütunları bulunamadı.', 'err'); return; }

  // Yazara göre grupla (sıra korunur)
  const byAuthor = new Map();
  for (const r of rows.slice(1)) {
    const t = (r[tCol] || '').trim(); if (!t) continue;
    const a = (r[aCol] || '').trim() || '(yazarsız)';
    if (!byAuthor.has(a)) byAuthor.set(a, []);
    byAuthor.get(a).push({ title: t, year: yCol >= 0 ? (r[yCol] || '').trim() : '', cover: cCol >= 0 ? (r[cCol] || '').trim() : '' });
  }

  let authors = [...byAuthor.keys()];
  const useAI   = document.getElementById('cleaner-use-ai')?.checked ? 1 : 0;
  const dropOnsite = document.getElementById('cleaner-drop-onsite')?.checked;
  const totalIn = rows.length - 1;
  if (!confirm(`${authors.length} yazar, ${totalIn} satır bulundu. ${useAI ? 'AI hakem AÇIK (yazar başına 1 istek).' : 'Yalnız kural katmanı (AI kapalı).'} Başlatılsın mı?`)) return;

  cleanerWorks = []; cleanerRemoved = [];

  // Sitede zaten olan yazarlar → tüm eserleri Elenenler'e (AI'ya da gitmez, token yakmaz)
  if (dropOnsite) {
    try {
      const siteSet = await fetchSiteAuthorKeys();
      if (siteSet) {
        const kept = [];
        for (const a of authors) {
          const onSite = [...authorNameKeys(a)].some(k => siteSet.has(k));
          if (onSite) {
            for (const w of byAuthor.get(a)) {
              cleanerRemoved.push({ title: w.title, author: a, year: w.year, cover: w.cover, reason: 'yazar sitede zaten var', restored: false });
            }
            byAuthor.delete(a);
          } else kept.push(a);
        }
        if (authors.length !== kept.length) {
          notify('cleaner-notif', `⊘ ${authors.length - kept.length} yazar sitede zaten var — eserleri Elenenler'e taşındı.`, 'ok');
        }
        authors = kept;
      }
    } catch(_) {}
  }

  // Sunucuda kontrol noktası oluştur: sekme kapansa/oturum düşse bile
  // sayfa yeniden açıldığında kaldığı yerden devam edilebilsin.
  const byAuthorObj = {};
  for (const a of authors) byAuthorObj[a] = byAuthor.get(a);
  await postData(API('clean-progress.php'), {
    action: 'start', file_name: fileName, use_ai: useAI, total_in: totalIn,
    authors: JSON.stringify(authors), by_author: JSON.stringify(byAuthorObj),
    removed: JSON.stringify(cleanerRemoved)
  }, 60000).catch(()=>{});

  await runCleanerLoop(authors, byAuthor, useAI, totalIn, fileName);
}

/* Yazar döngüsü — hem ilk çalıştırmada hem "kaldığı yerden devam"da kullanılır.
   Her yazar bitince sonuç sunucuya eklenir (checkpoint). */
async function runCleanerLoop(authors, byAuthor, useAI, totalIn, fileName, doneOffset = 0) {
  cleanerCancel = false;   // (works/removed yukarıda sıfırlandı; onsite elenenler korunur)
  const startBtn = document.getElementById('btn-cleaner-start');
  const cancelBtn= document.getElementById('btn-cleaner-cancel');
  setLoading(startBtn, true, 'Temizleniyor...');
  if (cancelBtn) cancelBtn.style.display = '';
  document.getElementById('cleaner-progress-card').style.display = '';
  document.getElementById('cleaner-result-card').style.display = 'none';

  let aiFails = 0;
  const grandTotal = authors.length + doneOffset;

  /* PARALEL HAVUZ: yazarlar birbirinden bağımsız olduğu için aynı anda birkaçı
     işlenebilir. Sıralı çalışırken yazar başına ~100sn sürüyordu (100 yazar ≈ 3 saat);
     4 eşzamanlı istekle bu süre ~4 kat kısalır. Sunucuyu boğmamak için 4'te tutuldu. */
  const CONCURRENCY = 4;
  let nextIdx = 0, finished = 0;

  const processOne = async (a) => {
    const works = byAuthor.get(a) || [];
    let res = null;
    for (let attempt = 1; attempt <= 3; attempt++) {
      if (cleanerCancel) return;
      try {
        res = await postData(API('clean-list.php'), { author: a, works: JSON.stringify(works), use_ai: useAI }, 150000);
        if (res && res.ok) break;
      } catch(_) {}
      await new Promise(r => setTimeout(r, attempt * 2000));
    }

    let stepWorks, stepRemoved, stepAiFail = 0;
    if (res && res.ok) {
      stepWorks   = res.works || [];
      stepRemoved = (res.removed || []).map(x => ({ ...x, restored: false }));
      if (useAI && !res.ai_used) { aiFails++; stepAiFail = 1; }
    } else {
      // Sunucu 3 denemede yanıt vermedi → bu yazarın satırlarını OLDUĞU GİBİ koru (veri kaybı olmasın)
      stepWorks   = works.map(w => ({ title: w.title, author: a, year: w.year, cover: w.cover, merged: 1 }));
      stepRemoved = [];
      aiFails++; stepAiFail = 1;
    }
    cleanerWorks.push(...stepWorks);
    cleanerRemoved.push(...stepRemoved);

    // Kontrol noktası: bu yazar bitti → sunucuya yaz (tarayıcı kapansa bile kalıcı)
    postData(API('clean-progress.php'), {
      action: 'append', author: a,
      works: JSON.stringify(stepWorks), removed: JSON.stringify(stepRemoved),
      ai_fail: stepAiFail
    }, 60000).catch(()=>{});

    finished++;
    setCleanerProgress(finished + doneOffset, grandTotal, a);
  };

  const runner = async () => {
    while (true) {
      if (cleanerCancel) return;
      const i = nextIdx++;
      if (i >= authors.length) return;
      await processOne(authors[i]);
    }
  };

  await Promise.all(Array.from({ length: Math.min(CONCURRENCY, authors.length) }, runner));

  if (cleanerCancel) {
    notify('cleaner-notif', `Durduruldu — ${finished + doneOffset}/${grandTotal} yazar işlendi (sonuçlar sunucuda korunuyor, sonra devam edebilirsin).`, 'err');
  }
  setCleanerProgress(finished + doneOffset, grandTotal, '');

  setLoading(startBtn, false);
  if (cancelBtn) cancelBtn.style.display = 'none';
  renderCleanerResult(totalIn, fileName, aiFails);
}

function setCleanerProgress(done, total, current) {
  const pct = total ? Math.round(done / total * 100) : 0;
  document.getElementById('cleaner-progress-bar').style.width = pct + '%';
  document.getElementById('cleaner-progress-text').textContent = `${done}/${total} yazar`;
  document.getElementById('cleaner-stats').textContent =
    (current ? `İşleniyor: ${current} · ` : '') +
    `${cleanerWorks.length} temiz eser · ${cleanerRemoved.length} elendi`;
}

function renderCleanerResult(totalIn, fileName, aiFails) {
  const card = document.getElementById('cleaner-result-card');
  card.style.display = '';
  const kept = cleanerWorks.length;
  const mergedAway = totalIn - kept - cleanerRemoved.length;
  document.getElementById('cleaner-result-summary').textContent =
    ` — ${totalIn} satır → ${kept} temiz eser (${mergedAway > 0 ? mergedAway + ' tekrar birleşti, ' : ''}${cleanerRemoved.length} elendi)` +
    (aiFails ? ` · ⚠ ${aiFails} yazarda AI atlandı (kural sonucu kullanıldı)` : '');

  const tb = document.querySelector('#cleaner-table tbody');
  tb.innerHTML = cleanerWorks.map((w, i) => `<tr>
    <td style="color:var(--muted)">${i+1}</td>
    <td>${escHtml(w.title)}</td>
    <td style="font-size:12px">${escHtml(w.author)}</td>
    <td style="font-size:12px">${escHtml(w.year || '')}</td>
    <td style="font-size:12px;color:var(--muted)">${w.merged > 1 ? ('×' + w.merged) : ''}</td>
  </tr>`).join('');

  const rw = document.getElementById('cleaner-removed-wrap');
  if (cleanerRemoved.length) {
    rw.style.display = '';
    document.getElementById('cleaner-removed-count').textContent = `(${cleanerRemoved.length})`;
    renderCleanerRemoved();
  } else rw.style.display = 'none';

  notify('cleaner-notif', `✓ Temizlik bitti: ${totalIn} → ${kept} eser.`, 'ok');
  card.scrollIntoView({ behavior: 'smooth' });
}

function renderCleanerRemoved() {
  const tb = document.querySelector('#cleaner-removed-table tbody');
  tb.innerHTML = cleanerRemoved.map((r, i) => `<tr style="${r.restored ? 'opacity:.45' : ''}">
    <td>${escHtml(r.title)}</td>
    <td style="font-size:12px">${escHtml(r.author)}</td>
    <td style="font-size:12px;color:var(--muted)">${escHtml(r.reason || '')}</td>
    <td>${r.restored
      ? '<span class="badge badge-green">geri alındı</span>'
      : `<button class="btn btn-ghost btn-sm" onclick="cleanerRestore(${i})">↩ Geri al</button>`}</td>
  </tr>`).join('');
}

window.cleanerRestore = function(i) {
  const r = cleanerRemoved[i];
  if (!r || r.restored) return;
  r.restored = true;
  cleanerWorks.push({ title: r.title, author: r.author, year: r.year || '', cover: r.cover || '', merged: 1 });
  renderCleanerResult(
    cleanerWorks.length + cleanerRemoved.filter(x => !x.restored).length,
    '', 0
  );
};

function escHtml(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function cleanerCsvEscape(s) { return '"' + String(s ?? '').replace(/"/g, '""') + '"'; }

function cleanerDownload(name, content) {
  const blob = new Blob(['﻿' + content], { type: 'text/csv;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob); a.download = name;
  document.body.appendChild(a); a.click();
  setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500);
}

document.getElementById('btn-cleaner-export')?.addEventListener('click', () => {
  if (!cleanerWorks.length) { notify('cleaner-notif', 'İndirilecek temiz eser yok.', 'err'); return; }
  let csv = 'Kitap Adı,Yazar Adı,Yıl,Kapak\n';
  for (const w of cleanerWorks) {
    csv += [cleanerCsvEscape(w.title), cleanerCsvEscape(w.author), cleanerCsvEscape(w.year || ''), cleanerCsvEscape(w.cover || '')].join(',') + '\n';
  }
  cleanerDownload('TEMIZ_liste_' + cleanerWorks.length + '.csv', csv);
});

document.getElementById('btn-cleaner-export-removed')?.addEventListener('click', () => {
  const rows = cleanerRemoved.filter(r => !r.restored);
  if (!rows.length) { notify('cleaner-notif', 'Elenen kayıt yok.', 'err'); return; }
  let csv = 'Kitap Adı,Yazar Adı,Neden\n';
  for (const r of rows) csv += [cleanerCsvEscape(r.title), cleanerCsvEscape(r.author), cleanerCsvEscape(r.reason || '')].join(',') + '\n';
  cleanerDownload('ELENENLER_' + rows.length + '.csv', csv);
});
