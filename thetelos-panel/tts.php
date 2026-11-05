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
</style>
</head>
<body>
<div class="tls-shell">
  <?php require __DIR__ . "/_nav.php"; ?>
  <main class="tls-main">
    <div class="tls-header"><div>
      <h2>Sesli Özet</h2>
      <p>Özetler Kokoro (açık kaynak, doğal ses) ile Google Colab'ın ücretsiz GPU'sunda seslendirilir, Cloudflare R2'ye yüklenir ve kitap sayfasında oynatıcı olarak görünür. En çok okunan kitaptan başlar.</p>
    </div></div>

    <div class="stats-row">
      <div class="stat-box"><div class="stat-val" id="st-with" style="color:#00ab6b">–</div><div class="stat-lbl">Sesi hazır kitap</div></div>
      <div class="stat-box"><div class="stat-val" id="st-total">–</div><div class="stat-lbl">Toplam kitap</div></div>
    </div>

    <div class="card">
      <div class="card-title">Kurulum (bir kez)</div>
      <ol class="steps">
        <li><b>Cloudflare R2:</b> Cloudflare → R2 → <i>Create bucket</i> → adı <code class="k">thetelos-audio</code>. Kovada <i>Settings → Custom Domains</i> → <code class="k">audio.thetelos.org</code> ekleyin.</li>
        <li><b>R2 API anahtarı:</b> R2 → <i>Manage API Tokens</i> → <i>Create API Token</i> → izin: <i>Object Read &amp; Write</i>. Çıkan <i>Access Key ID</i>, <i>Secret Access Key</i> ve hesap kimliğini (Account ID) bir yere not edin. <b>Bu anahtarları kimseyle (bana da) paylaşmayın</b>; yalnız Colab'ın gizli anahtar paneline yazacaksınız.</li>
        <li><b>Colab defteri:</b> <a href="tts/kokoro_colab.ipynb" download style="color:var(--gold)">⬇ kokoro_colab.ipynb dosyasını indirin</a> → <a href="https://colab.research.google.com/" target="_blank" style="color:var(--gold)">colab.research.google.com</a> → <i>Upload</i> ile açın.</li>
        <li><b>Gizli anahtarlar:</b> Colab'da soldaki 🔑 paneline ekleyin: <code class="k">TLS_KEY</code> = <code class="k"><?= htmlspecialchars($tts_key) ?></code>, ve <code class="k">R2_ACCOUNT_ID</code>, <code class="k">R2_ACCESS_KEY</code>, <code class="k">R2_SECRET</code>, <code class="k">R2_BUCKET</code> (= thetelos-audio).</li>
        <li><b>Çalıştırın:</b> Çalışma zamanını <i>T4 GPU</i> yapıp hücreleri sırayla ▶ çalıştırın. Her biten kitap anında sitede oynatıcıyla görünür. Colab kapanırsa tekrar çalıştırın; kaldığı yerden devam eder.</li>
      </ol>
    </div>
  </main>
</div>
<script>
fetch('api/tts.php?action=stats&key=<?= rawurlencode($tts_key) ?>').then(r => r.json()).then(d => {
  if (!d.ok) return;
  document.getElementById('st-with').textContent = d.with_audio.toLocaleString('tr');
  document.getElementById('st-total').textContent = d.total.toLocaleString('tr');
});
</script>
</body>
</html>
