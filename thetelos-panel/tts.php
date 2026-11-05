<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
$tts_key = hash('sha256', WP_APP_PASS . '|tls-tts');
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sesli Özet — Thetelos Panel</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.stats-row{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.stat-box{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 16px}
.stat-val{font-size:22px;font-weight:700}
.stat-lbl{font-size:11px;color:var(--muted);margin-top:2px}
.steps li{margin:0 0 10px;line-height:1.6}
code.k{background:#111;padding:3px 8px;border-radius:4px;font-size:12px;word-break:break-all;user-select:all}
.lv-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;align-items:end}
.lv-grid label{display:flex;flex-direction:column;gap:5px;font-size:12px;color:var(--muted)}
.lv-grid input,.lv-grid select{background:var(--surface2);border:1px solid var(--border);color:inherit;border-radius:6px;padding:8px}
.mask{font-size:11px;color:#00ab6b}
.lv-tbl{width:100%;border-collapse:collapse;font-size:13px}.lv-tbl th,.lv-tbl td{text-align:left;padding:6px 8px;border-bottom:1px solid var(--border)}
</style>
</head>
<body>
<div class="tls-shell">
  <?php require __DIR__ . "/_nav.php"; ?>
  <main class="tls-main">
    <div class="tls-header"><div>
      <h2>Sesli Özet</h2>
      <p>Her kitap sayfasında doğal sesli dinleme (Kokoro). Ses bir kez üretilir, Cloudflare R2'de saklanır.</p>
    </div></div>

    <div class="stats-row">
      <div class="stat-box"><div class="stat-val" id="st-with" style="color:#00ab6b">–</div><div class="stat-lbl">Sesi olan kitap</div></div>
      <div class="stat-box"><div class="stat-val" id="st-today">–</div><div class="stat-lbl">Bugün seslendirilen karakter</div></div>
      <div class="stat-box"><div class="stat-val" id="st-cost">–</div><div class="stat-lbl">Toplam tahmini harcama</div></div>
      <div class="stat-box"><div class="stat-val" id="st-ready">–</div><div class="stat-lbl">Anlık dinleme</div></div>
    </div>

    <div class="card">
      <div class="card-title">Anlık dinleme ayarları</div>
      <p style="color:var(--muted);font-size:13px;margin:0 0 12px">Her kitap sayfasında ▶ çıkar. Bir kitap <b>ilk kez</b> dinlendiğinde DeepInfra seslendirir ve ses R2'ye kaydedilir; sonraki dinlemeler <b>ücretsizdir</b>. Anahtarlar sunucuda saklanır. Boş bıraktığınız alan eski değerini korur.</p>
      <form id="lv-form" class="lv-grid">
        <label>DeepInfra API anahtarı <span id="m-di" class="mask"></span><input name="deepinfra_key" type="password" autocomplete="off" placeholder="deepinfra.com → Dashboard → API Keys"></label>
        <label>R2 Access Key ID <span id="m-ak" class="mask"></span><input name="r2_key" type="password" autocomplete="off"></label>
        <label>R2 Secret Access Key <span id="m-sk" class="mask"></span><input name="r2_secret" type="password" autocomplete="off"></label>
        <label>Ses<select name="voice"><option value="am_michael">Michael (erkek, ABD)</option><option value="af_heart">Heart (kadın, ABD)</option><option value="bm_george">George (erkek, İngiliz)</option><option value="bf_emma">Emma (kadın, İngiliz)</option></select></label>
        <label>Günlük üst sınır (karakter)<input name="daily_chars" type="number" min="10000" step="10000"></label>
        <label style="flex-direction:row;align-items:center;gap:8px;font-size:13px;color:inherit"><input type="checkbox" name="enabled" value="1" style="width:auto"> Anlık dinleme açık (DeepInfra — ücretli)</label>
        <div><button class="btn btn-primary" type="submit">Kaydet</button> <span id="lv-msg" style="font-size:13px"></span></div>
      </form>
      <div id="lv-err" style="display:none;margin-top:10px;font-size:12px;color:#e07070"></div>
    </div>

    <div class="card">
      <div class="card-title">Sesi olan kitaplar</div>
      <table class="lv-tbl"><thead><tr><th>Kitap</th><th>Tür</th><th>Süre</th><th>Parça</th></tr></thead><tbody id="lv-books"><tr><td colspan="4">Yükleniyor…</td></tr></tbody></table>
      <p style="color:var(--muted);font-size:12px;margin:8px 0 0"><b>hazır</b> = Colab'da baştan sona üretildi · <b>anlık</b> = okuyucu dinledikçe üretildi (parça: hazır olan / toplam)</p>
    </div>

    <details class="card">
      <summary class="card-title" style="cursor:pointer">İsteğe bağlı: Colab ile toplu ücretsiz seslendirme</summary>
      <ol class="steps">
        <li><a href="tts/kokoro_colab.ipynb" download style="color:var(--gold)">⬇ kokoro_colab.ipynb</a> dosyasını <a href="https://colab.research.google.com/" target="_blank" style="color:var(--gold)">Colab</a>'da açın.</li>
        <li>🔑 paneline: <code class="k">TLS_KEY</code> = <code class="k"><?= htmlspecialchars($tts_key) ?></code>, ve <code class="k">R2_ACCOUNT_ID</code>, <code class="k">R2_ACCESS_KEY</code>, <code class="k">R2_SECRET</code>, <code class="k">R2_BUCKET</code> (= thetelos-audio).</li>
        <li>T4 GPU seçip hücreleri sırayla ▶ çalıştırın.</li>
      </ol>
    </details>
  </main>
</div>
<script>
const PRICE_PER_M = 1.0; // $ / 1M karakter (DeepInfra Kokoro, yaklaşık — kesin tutar DeepInfra → Billing)
function fmtT(s){s=+s||0;const m=Math.floor(s/60);return m+':'+String(Math.floor(s%60)).padStart(2,'0');}
function esc(t){const d=document.createElement('div');d.textContent=t;return d.innerHTML;}
function load(){
  fetch('api/tts-live.php?action=status').then(r=>r.json()).then(d=>{
    if(!d.ok) return;
    document.getElementById('st-with').textContent=d.books.length.toLocaleString('tr');
    document.getElementById('st-today').textContent=d.today_chars.toLocaleString('tr');
    document.getElementById('st-cost').textContent='$'+(d.total_chars/1e6*PRICE_PER_M).toFixed(2);
    const rd=document.getElementById('st-ready'); rd.textContent=d.ready?'Açık':'Kapalı'; rd.style.color=d.ready?'#00ab6b':'#e07070';
    document.getElementById('m-di').textContent=d.deepinfra_key?'(kayıtlı: '+d.deepinfra_key+')':'';
    document.getElementById('m-ak').textContent=d.r2_key?'(kayıtlı: '+d.r2_key+')':'';
    document.getElementById('m-sk').textContent=d.r2_secret?'(kayıtlı: '+d.r2_secret+')':'';
    const f=document.getElementById('lv-form'); f.enabled.checked=!!d.enabled; f.voice.value=d.voice; f.daily_chars.value=d.daily_chars;
    if(d.last_error){const e=document.getElementById('lv-err');e.style.display='block';e.textContent='Son hata: '+d.last_error;}
    document.getElementById('lv-books').innerHTML=d.books.length?d.books.map(b=>
      '<tr><td><a href="'+esc(b.url)+'" target="_blank" style="color:var(--gold)">'+esc(b.title)+'</a></td><td>'+b.kind+'</td><td>'+fmtT(b.seconds)+'</td><td>'+(b.parts||'–')+'</td></tr>').join('')
      :'<tr><td colspan="4">Henüz yok.</td></tr>';
  });
}
document.getElementById('lv-form').onsubmit=e=>{
  e.preventDefault(); const fd=new FormData(e.target); fd.append('action','save');
  const m=document.getElementById('lv-msg'); m.textContent='Kaydediliyor…';
  fetch('api/tts-live.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    m.textContent=d.ok?'Kaydedildi ✓':'Hata'; e.target.querySelectorAll('input[type=password]').forEach(i=>i.value=''); load();
  }).catch(()=>m.textContent='Hata');
};
load();
</script>
</body>
</html>
