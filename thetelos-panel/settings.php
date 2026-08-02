<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }

$prompts = ['summary'=>'','analysis'=>''];
if (file_exists(PROMPTS_FILE)) {
    $p = json_decode(file_get_contents(PROMPTS_FILE), true);
    if ($p) $prompts = $p;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ayarlar — Thetelos Content Panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<style>
.stab{padding:9px 20px;background:none;border:none;border-bottom:2px solid transparent;color:var(--muted);font-size:13px;font-weight:500;cursor:pointer;transition:.15s;margin-bottom:-1px}
.stab.active{color:var(--text);border-bottom-color:var(--gold)}
.stab-pane{display:none}.stab-pane.active{display:block}
.info-row{display:flex;align-items:center;gap:8px;font-size:13px;padding:4px 0}
.info-label{color:var(--muted);min-width:80px}
.info-val{color:var(--text);font-weight:500}
</style>
</head>
<body>
<div class="tls-shell">

  <aside class="tls-sidebar">
    <div class="tls-logo"><h1>Thetelos</h1><small>Content Panel</small></div>
    <nav class="tls-nav">
      <a href="panel.php"><span class="ico">✍</span> İçerik Üret</a>
      <a href="content-audit.php"><span class="ico">🩺</span> İçerik Denetimi</a>
      <a href="content-guard.php"><span class="ico">🛡️</span> İçerik Koruma</a>
      <a href="recategorize.php"><span class="ico">🗂️</span> Kategori Düzelt</a>
      <a href="category-cleanup.php"><span class="ico">🧹</span> Kategori Temizle</a>
      <a href="cover-backfill.php"><span class="ico">🖼</span> Kapak Bul</a>
      <a href="amazon-match.php"><span class="ico">🛒</span> Amazon</a>
      <a href="settings.php" class="active"><span class="ico">⚙</span> Ayarlar</a>
      <a href="<?= rtrim(WP_URL,'/') ?>/wp-admin/" target="_blank"><span class="ico">🔗</span> WP Admin</a>
      <a href="<?= rtrim(WP_URL,'/') ?>/" target="_blank"><span class="ico">↗</span> Siteyi Gör</a>
    </nav>
    <div class="tls-sidebar-footer"><a href="index.php?logout=1">Çıkış Yap</a></div>
  </aside>

  <main class="tls-main">
    <div class="tls-header">
      <div><h2>Ayarlar</h2><p>Prompt şablonları ve bağlantı bilgileri</p></div>
    </div>

    <div id="s-notif" class="notif"></div>

    <!-- Bağlantı Bilgileri -->
    <div class="card">
      <div class="card-title">WordPress Bağlantısı</div>
      <div class="info-row"><span class="info-label">Site</span><span class="info-val"><?= htmlspecialchars(WP_URL) ?></span></div>
      <div class="info-row"><span class="info-label">Kullanıcı</span><span class="info-val"><?= htmlspecialchars(WP_USER) ?></span></div>
      <div class="info-row"><span class="info-label">Model</span><span class="info-val"><?= htmlspecialchars(ANTHROPIC_MODEL) ?></span></div>
      <div class="info-row"><span class="info-label">Max Token</span><span class="info-val"><?= number_format(ANTHROPIC_MAX_TOKENS) ?></span></div>
      <div style="margin-top:16px">
        <button class="btn btn-ghost btn-sm" id="btn-test">🔌 WP Bağlantısını Test Et</button>
        <span id="test-result" style="margin-left:12px;font-size:13px"></span>
      </div>
    </div>

    <!-- Prompt Şablonları -->
    <div class="card">
      <div class="card-title">Prompt Şablonları</div>
      <p style="color:var(--muted);font-size:12px;margin-bottom:16px">
        Şablonlarda <code style="background:var(--surface2);padding:1px 6px;border-radius:3px;color:var(--gold)">{book_title}</code>
        ve <code style="background:var(--surface2);padding:1px 6px;border-radius:3px;color:var(--gold)">{author_name}</code> değişkenlerini kullanın.
      </p>

      <div style="display:flex;border-bottom:1px solid var(--border);margin-bottom:20px">
        <button class="stab active" onclick="switchTab(this,'pane-summary')">📄 Özet Promptu</button>
        <button class="stab"       onclick="switchTab(this,'pane-analysis')">🔍 Analiz Promptu</button>
      </div>

      <div id="pane-summary" class="stab-pane active">
        <label style="display:block;margin-bottom:8px;color:var(--muted);font-size:12px">Özet Prompt Şablonu</label>
        <textarea id="prompt_summary" rows="20"><?= htmlspecialchars($prompts['summary'] ?? '') ?></textarea>
      </div>
      <div id="pane-analysis" class="stab-pane">
        <label style="display:block;margin-bottom:8px;color:var(--muted);font-size:12px">Analiz Prompt Şablonu</label>
        <textarea id="prompt_analysis" rows="20"><?= htmlspecialchars($prompts['analysis'] ?? '') ?></textarea>
      </div>

      <div style="margin-top:16px;display:flex;align-items:center;gap:14px">
        <button class="btn btn-primary" id="btn-save">💾 Promptları Kaydet</button>
        <span id="save-result" style="font-size:13px"></span>
      </div>
    </div>

    <!-- Yayın Kapısı -->
    <div class="card">
      <div class="card-title">🚦 Yayın Kapısı</div>
      <p style="font-size:13px;color:var(--muted);line-height:1.7;margin-bottom:14px;max-width:820px">
        Bir okuyucu, yayındaki bir özette romanda hiç olmayan bir karakterin anlatıldığını bildirdi.
        Bu kusur biçimsel değil: metin kusursuz görünüyor ama <b>anlattığı kitap o kitap değil</b>.
        Düzenli ifadeyle yakalanamaz — bu yüzden yayın kararı artık bir kapıdan geçiyor.
        Kapı kusuru <b>düzeltmez</b>, yalnızca yayına çıkmasını engeller: içerik taslak olarak kaydedilir,
        sebebi yazılır, kararı sen verirsin.
      </p>
      <div style="display:grid;gap:12px;max-width:820px">
        <label style="display:flex;gap:10px;align-items:flex-start;font-size:13px">
          <input type="checkbox" id="v-gate" style="margin-top:3px">
          <span><b>Kapı açık</b> — kusurlu içerik yayınlanmaz, taslakta bekler.
            <span style="color:var(--muted)">Kapatırsan her şey doğrudan yayına gider.</span></span>
        </label>
        <label style="display:flex;gap:10px;align-items:flex-start;font-size:13px">
          <input type="checkbox" id="v-probe" style="margin-top:3px">
          <span><b>Üretim öncesi bilgi yoklaması</b> — yazmadan önce kısa bir çağrı: “bu eseri biliyor musun?”.
            Yanıt Open Library ile çapraz kontrol edilir; tutmazsa kitap hiç yazılmaz.
            <span style="color:var(--muted)">Ucuz (~1 kısa çağrı) ve uydurmayı kaynağında keser.</span></span>
        </label>
        <label style="display:flex;gap:10px;align-items:flex-start;font-size:13px">
          <input type="checkbox" id="v-fact" style="margin-top:3px">
          <span><b>Yayın öncesi olgu denetimi</b> — yazılan metin ayrı bir çağrıya düşmanca çerçevede verilir:
            “bu metinde bu esere ait olmayan ne varsa listele”.
            <span style="color:var(--muted)">Kitap başına ~1 çağrı. Üretme baskısı olmadığı için model kendi uydurmasını yakalayabiliyor.</span></span>
        </label>
        <label style="font-size:13px;display:flex;gap:10px;align-items:center">
          <span style="min-width:210px">Yoklamada en az güven eşiği</span>
          <input type="number" id="v-conf" min="0" max="100" step="5" style="width:80px;padding:5px 8px">
          <span style="color:var(--muted)">altındaysa kitap yazılmaz (varsayılan 55)</span>
        </label>
      </div>
      <div style="margin-top:14px;display:flex;gap:12px;align-items:center">
        <button class="btn btn-primary" id="btn-save-verify">💾 Kapı Ayarlarını Kaydet</button>
        <span id="verify-result" style="font-size:13px"></span>
      </div>
      <p style="font-size:12px;color:var(--muted);margin-top:14px;line-height:1.7;max-width:820px">
        <b>Dürüst sınır:</b> bu sistem uydurmayı azaltır, sıfırlamaz. Model kendi bilgisiyle kendini
        denetlediği için bir eseri baştan sona yanlış “biliyorsa” iki katman da aynı yanlışı onaylayabilir.
        Open Library çapraz kontrolü bu yüzden var — eserin varlığı ve yılı bağımsız bir kaynaktan doğrulanır.
        Tek gerçek güvence, düşük güvenli eserlerin <b>hiç yazılmaması</b>.
      </p>
    </div>

    <!-- Dosya İzni Durumu -->
    <div class="card">
      <div class="card-title">Sistem Durumu</div>
      <div class="info-row">
        <span class="info-label">prompts.json</span>
        <span class="info-val">
          <?php if (!file_exists(PROMPTS_FILE)): ?>
            <span style="color:var(--warn)">⚠ Dosya yok — kaydet butonuna basınca oluşturulacak</span>
          <?php elseif (!is_writable(PROMPTS_FILE)): ?>
            <span style="color:var(--danger)">✗ Yazma izni yok — FTP'den chmod 666 yapın</span>
          <?php else: ?>
            <span style="color:var(--green)">✓ Yazılabilir</span>
          <?php endif; ?>
        </span>
      </div>
      <div class="info-row">
        <span class="info-label">PHP cURL</span>
        <span class="info-val" style="color:<?= function_exists('curl_init') ? 'var(--green)' : 'var(--danger)' ?>">
          <?= function_exists('curl_init') ? '✓ Aktif' : '✗ Yok' ?>
        </span>
      </div>
      <div class="info-row">
        <span class="info-label">PHP ZipArchive</span>
        <span class="info-val" style="color:<?= class_exists('ZipArchive') ? 'var(--green)' : 'var(--warn)' ?>">
          <?= class_exists('ZipArchive') ? '✓ Aktif' : '⚠ Yok (XLSX okuma etkilenir)' ?>
        </span>
      </div>
    </div>

  </main>
</div>

<script>
function switchTab(btn, paneId) {
  document.querySelectorAll('.stab').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.stab-pane').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(paneId).classList.add('active');
}

// ── Kaydet ────────────────────────────────────────────────
document.getElementById('btn-save').addEventListener('click', async () => {
  const btn     = document.getElementById('btn-save');
  const result  = document.getElementById('save-result');
  const summary  = document.getElementById('prompt_summary').value;
  const analysis = document.getElementById('prompt_analysis').value;

  btn.disabled = true;
  btn.innerHTML = '<span class="loader"></span> Kaydediliyor...';
  result.textContent = '';

  const fd = new FormData();
  fd.append('action',   'save_prompts');
  fd.append('summary',  summary);
  fd.append('analysis', analysis);

  try {
    const res = await fetch('api/settings.php', {method:'POST', body:fd}).then(r => r.json());
    if (res.ok) {
      result.style.color = 'var(--green)';
      result.textContent = '✓ Kaydedildi';
    } else {
      result.style.color = 'var(--danger)';
      result.textContent = '✗ ' + res.error;
    }
  } catch(e) {
    result.style.color = 'var(--danger)';
    result.textContent = '✗ Bağlantı hatası: ' + e.message;
  }

  btn.disabled = false;
  btn.innerHTML = '💾 Promptları Kaydet';
});

// ── Bağlantı Testi ────────────────────────────────────────
document.getElementById('btn-test').addEventListener('click', async () => {
  const btn    = document.getElementById('btn-test');
  const result = document.getElementById('test-result');

  btn.disabled = true;
  btn.innerHTML = '<span class="loader"></span> Test ediliyor...';
  result.textContent = '';

  try {
    const res = await fetch('api/settings.php?action=test_connection').then(r => r.json());
    if (res.ok) {
      result.style.color = 'var(--green)';
      result.textContent = `✓ Bağlantı başarılı — ${res.user} (${res.role})`;
    } else {
      result.style.color = 'var(--danger)';
      result.textContent = '✗ ' + res.error;
    }
  } catch(e) {
    result.style.color = 'var(--danger)';
    result.textContent = '✗ Hata: ' + e.message;
  }

  btn.disabled = false;
  btn.innerHTML = '🔌 WP Bağlantısını Test Et';
});

/* ── Yayın kapısı ayarları ────────────────────────────────────────────────
   Varsayılan AÇIK: ayar dosyası hiç yoksa da kapı çalışır. Güvenli
   varsayılan, bir okuyucunun uydurma içerik bildirmesinden sonra budur. */
(async function loadVerify(){
  try {
    const r = await fetch('api/settings.php?action=get_verify').then(x=>x.json());
    if (!r.ok) return;
    document.getElementById('v-gate').checked  = !!r.data.gate;
    document.getElementById('v-probe').checked = !!r.data.probe;
    document.getElementById('v-fact').checked  = !!r.data.factcheck;
    document.getElementById('v-conf').value    = r.data.min_conf;
  } catch(e){}
})();

document.getElementById('btn-save-verify')?.addEventListener('click', async () => {
  const btn = document.getElementById('btn-save-verify');
  const out = document.getElementById('verify-result');
  btn.disabled = true; out.style.color = 'var(--muted)'; out.textContent = 'Kaydediliyor...';
  const fd = new FormData();
  fd.append('action', 'save_verify');
  if (document.getElementById('v-gate').checked)  fd.append('gate', '1');
  if (document.getElementById('v-probe').checked) fd.append('probe', '1');
  if (document.getElementById('v-fact').checked)  fd.append('factcheck', '1');
  fd.append('min_conf', document.getElementById('v-conf').value || '55');
  try {
    const res = await fetch('api/settings.php', {method:'POST', body:fd}).then(r=>r.json());
    if (res.ok) { out.style.color = 'var(--green)'; out.textContent = '✓ Kaydedildi'; }
    else        { out.style.color = 'var(--danger)'; out.textContent = '✗ ' + res.error; }
  } catch(e) { out.style.color = 'var(--danger)'; out.textContent = '✗ ' + e.message; }
  btn.disabled = false;
});
</script>

<style>
.loader{display:inline-block;width:13px;height:13px;border:2px solid #444;border-top-color:var(--gold);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:4px}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</body>
</html>
