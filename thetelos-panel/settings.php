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
</script>

<style>
.loader{display:inline-block;width:13px;height:13px;border:2px solid #444;border-top-color:var(--gold);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:4px}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</body>
</html>
