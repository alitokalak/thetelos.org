<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['tls_auth'])) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Thetelos Content Panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__.'/assets/style.css') ?: time() ?>">
</head>
<body>
<div class="tls-shell">

  <aside class="tls-sidebar">
    <div class="tls-logo"><h1>Thetelos</h1><small>Content Panel</small></div>
    <nav class="tls-nav">
      <a href="panel.php" class="active"><span class="ico">✍</span> İçerik Üret</a>
      <a href="panel.php?mode=queue"><span class="ico">📋</span> Kuyruk</a>
      <a href="seo.php"><span class="ico">🔍</span> İçerik SEO</a>
      <a href="seo-site.php"><span class="ico">🌐</span> Site SEO</a>
      <a href="settings.php"><span class="ico">⚙</span> Ayarlar</a>
      <a href="<?= rtrim(WP_URL,'/') ?>/wp-admin/" target="_blank"><span class="ico">🔗</span> WP Admin</a>
      <a href="<?= rtrim(WP_URL,'/') ?>/" target="_blank"><span class="ico">↗</span> Siteyi Gör</a>
    </nav>
    <div class="tls-sidebar-footer"><a href="index.php?logout=1">Çıkış Yap</a></div>
  </aside>

  <main class="tls-main">
    <div class="tls-header">
      <div>
        <h2 id="page-title">Tek Kitap</h2>
        <p id="page-desc">Kitap adı ve yazar girerek özet veya analiz üretin</p>
      </div>
      <div class="tabs-top">
        <button class="tab-top-btn active" data-mode="single">✍ Tek Kitap</button>
        <button class="tab-top-btn" data-mode="bulk">📋 Toplu Batch</button>
        <button class="tab-top-btn" data-mode="builder">🧱 Liste Oluştur</button>
      </div>
    </div>

    <!-- ── API Provider Toggle ── -->
    <div class="api-toggle-bar">
      <span class="api-toggle-label">API:</span>
      <div class="api-toggle-group">
        <button class="api-btn active" data-provider="anthropic">
          <span class="api-dot anthropic"></span> Anthropic
        </button>
        <button class="api-btn" data-provider="deepseek">
          <span class="api-dot deepseek"></span> DeepSeek
        </button>
      </div>
      <div class="api-sub-group" id="api-sub-anthropic">
        <button class="api-sub-btn active" data-model="claude-haiku-4-5-20251001" data-label="haiku">Haiku <span class="api-sub-hint">Hızlı</span></button>
        <button class="api-sub-btn" data-model="claude-sonnet-4-20250514" data-label="sonnet">Sonnet <span class="api-sub-hint">Kaliteli</span></button>
      </div>
      <span class="api-active-label" id="api-active-label">claude-haiku</span>
    </div>

    <div id="gen-notif"  class="notif"></div>
    <div id="bulk-notif" class="notif"></div>

    <!-- ══ TEK KİTAP ══════════════════════════════════════ -->
    <div id="mode-single">

      <div class="card">
        <div class="card-title">İçerik Tipi & Kitap Bilgisi</div>
        <div class="type-toggle">
          <input type="radio" name="type" id="t-summary"  value="summary"  checked>
          <label for="t-summary">📄 Özet</label>
          <input type="radio" name="type" id="t-analysis" value="analysis">
          <label for="t-analysis">🔍 Analiz</label>
        </div>
        <div class="form-row">
          <div>
            <label for="book_title">Kitap Adı</label>
            <input type="text" id="book_title" placeholder="Örn: Meditations">
          </div>
          <div>
            <label for="author_name">Yazar Adı</label>
            <input type="text" id="author_name" placeholder="Örn: Marcus Aurelius">
          </div>
        </div>

        <div class="token-control">
          <div class="token-header">
            <label>Kaç kelime yazılsın?</label>
            <span id="token-display" class="token-val">3.000 kelime</span>
          </div>
          <input type="range" id="token-slider" min="500" max="8000" step="500" value="3000"
            oninput="updateTokenDisplay(this.value)">
          <div class="token-marks">
            <span>500</span><span>2K</span><span>4K</span><span>6K</span><span>8K</span>
          </div>
        </div>

        <div class="form-row" style="margin-top:14px">
          <div>
            <label for="post_status">Yayın Durumu</label>
            <select id="post_status">
              <option value="draft">Taslak olarak kaydet</option>
              <option value="publish">Direkt yayınla</option>
            </select>
          </div>
          <div>
            <label for="parts-select">Parça Sayısı <span style="color:var(--muted);font-weight:400;font-size:11px">(DeepSeek)</span></label>
            <select id="parts-select">
              <option value="2" selected>2 parça</option>
              <option value="3">3 parça (daha uzun)</option>
              <option value="4">4 parça (en uzun)</option>
            </select>
          </div>
        </div>
        <button class="btn btn-primary" id="btn-generate">✦ İçerik Üret</button>
      </div>

      <!-- İşlem durumu -->
      <div id="job-status-wrap" style="display:none" class="card">
        <div class="card-title">İşlem Durumu</div>
        <div class="loading-row">
          <span class="loader"></span>
          <span id="job-status-text">Kuyrukta bekliyor...</span>
        </div>
        <div class="progress-wrap" style="margin-top:8px">
          <div class="progress-bar" id="job-progress-bar" style="width:30%;animation:pulse 1.5s ease-in-out infinite"></div>
        </div>
      </div>

      <!-- Sonuçlar -->
      <div id="single-result" style="display:none">
        <div id="gen-stats" class="card" style="padding:16px 24px"></div>
        <div class="card" id="cover-card">
          <div class="card-title">Kitap Kapağı <span style="color:var(--muted);font-weight:400;font-size:11px">— doğru olanı seçin</span></div>
          <div class="cover-grid" id="cover-grid"></div>
        </div>
        <div class="card">
          <div class="card-title">Otomatik Tespit Edilenler <span style="color:var(--muted);font-weight:400;font-size:11px">— düzenleyebilirsiniz</span></div>
          <div class="form-row one"><div>
            <label>Kategoriler</label>
            <div class="cat-tags" id="cat-tags"></div>
          </div></div>
          <div class="form-row one"><div>
            <label for="field_excerpt">Excerpt</label>
            <input type="text" id="field_excerpt" data-maxlen="155">
          </div></div>
          <div class="form-row one"><div>
            <label for="field_meta_desc">Yoast Meta Description</label>
            <input type="text" id="field_meta_desc" data-maxlen="155">
          </div></div>
        </div>
        <div class="card">
          <div class="card-title">İçerik Önizlemesi</div>
          <div class="preview-box" id="preview-content"></div>
        </div>
        <div style="display:flex;gap:12px;margin-bottom:24px">
          <button class="btn btn-green" id="btn-publish">🚀 WordPress'e Yayınla</button>
          <button class="btn btn-ghost" id="btn-reset">↺ Yeni Kitap</button>
        </div>
        <div id="publish-result"></div>
      </div>

    </div><!-- /mode-single -->

    <!-- ══ TOPLU BATCH ═══════════════════════════════════ -->
    <div id="mode-bulk" style="display:none">

      <div class="card">
        <div class="card-title">
          Dosya Yükle
          <span class="badge badge-gold" id="batch-total-badge" style="display:none;margin-left:8px">0 kitap</span>
        </div>
        <div class="upload-zone" id="upload-zone">
          <div class="icon">📂</div>
          <p>CSV veya XLSX dosyasını buraya sürükle ya da tıkla</p>
          <p style="font-size:11px;margin-top:6px;color:#555">Format: <strong>Kitap Adı | Yazar</strong> — birden fazla dosya yükleyebilirsiniz</p>
        </div>
        <input type="file" id="bulk-file" accept=".csv,.xlsx" multiple style="display:none">
        <div id="file-list" style="margin-top:10px;font-size:12px;color:var(--muted)"></div>
        <div style="display:flex;gap:8px;margin-top:10px" id="upload-actions" style="display:none">
          <button class="btn btn-ghost btn-sm" id="btn-add-more">+ Dosya Ekle</button>
          <button class="btn btn-ghost btn-sm" id="btn-clear-list" style="color:var(--red)">✕ Listeyi Temizle</button>
        </div>
      </div>

      <div class="card">
        <div class="card-title">Batch Ayarları</div>
        <div class="type-toggle">
          <input type="radio" name="bulk_type" id="bt-summary"  value="summary"  checked>
          <label for="bt-summary">📄 Özet</label>
          <input type="radio" name="bulk_type" id="bt-analysis" value="analysis">
          <label for="bt-analysis">🔍 Derin Analiz</label>
        </div>

        <div class="token-control" style="margin-bottom:16px">
          <div class="token-header">
            <label>Kaç kelime yazılsın? (tüm liste için)</label>
            <span id="bulk-token-display" class="token-val">3.000 kelime</span>
          </div>
          <input type="range" id="bulk-token-slider" min="500" max="8000" step="500" value="3000"
            oninput="updateBulkTokenDisplay(this.value)">
          <div class="token-marks">
            <span>500</span><span>2K</span><span>4K</span><span>6K</span><span>8K</span>
          </div>
        </div>

        <div class="form-row">
          <div>
            <label for="bulk_post_status">Yayın Durumu</label>
            <select id="bulk_post_status">
              <option value="draft">Taslak olarak kaydet</option>
              <option value="publish">Direkt yayınla</option>
            </select>
          </div>
          <div>
            <label>Paralel Worker Sayısı</label>
            <select id="bulk_workers">
              <option value="1">1 worker (yavaş, güvenli)</option>
              <option value="2">2 worker</option>
              <option value="3" selected>3 worker (önerilen)</option>
              <option value="5">5 worker (hızlı)</option>
            </select>
          </div>
        </div>

        <div class="form-row" style="margin-top:14px">
          <div>
            <label for="bulk-parts-select">Parça Sayısı <span style="color:var(--muted);font-weight:400;font-size:11px">(DeepSeek — uzun içerik için)</span></label>
            <select id="bulk-parts-select">
              <option value="2" selected>2 parça</option>
              <option value="3">3 parça (daha uzun)</option>
              <option value="4">4 parça (en uzun)</option>
            </select>
          </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
          <button class="btn btn-primary" id="btn-batch-start" disabled>▶ Batch İşlemi Başlat</button>
          <button class="btn btn-ghost" id="btn-batch-pause" style="display:none">⏸ Duraklat</button>
        </div>
        <p style="font-size:11px;color:var(--muted);margin-top:8px">
          ℹ Sunucu taraflı batch — tarayıcıyı kapatsan bile işleme devam eder.
          Sayfayı yeniden açarak devam edebilirsin.
        </p>
      </div>

      <!-- İlerleme -->
      <div id="batch-progress-wrap" style="display:none" class="card">
        <div class="card-title">İlerleme
          <span id="batch-status-badge" class="badge badge-gold" style="margin-left:8px">Çalışıyor</span>
        </div>
        <div class="progress-wrap"><div class="progress-bar" id="bulk-bar" style="width:0%"></div></div>
        <div class="progress-label" id="bulk-bar-label">Bekleniyor...</div>
        <div id="batch-worker-status" style="margin-top:8px;font-size:12px;color:var(--muted);display:flex;gap:12px;flex-wrap:wrap"></div>
        <div id="bulk-summary" style="margin-top:8px;font-size:13px;color:var(--muted)"></div>
      </div>

      <div id="bulk-preview"></div>
    </div>

    <!-- ══ LİSTE OLUŞTUR ═══════════════════════════════════ -->
    <div id="mode-builder" style="display:none">

      <div class="card">
        <div class="card-title">Liste Kaynağı</div>
        <div class="type-toggle">
          <input type="radio" name="builder_mode" id="bm-author" value="author" checked>
          <label for="bm-author">👤 Yazara Göre</label>
          <input type="radio" name="builder_mode" id="bm-category" value="category">
          <label for="bm-category">🗂️ Kategoriye Göre</label>
        </div>

        <!-- Yazara göre -->
        <div id="builder-author-box">
          <div class="form-row one"><div>
            <label for="builder-author">Yazar Adı</label>
            <input type="text" id="builder-author" placeholder="Örn: Augustine of Hippo">
          </div></div>
          <button class="btn btn-primary" id="btn-fetch-works">📚 Tüm Eserlerini Getir</button>
        </div>

        <!-- Kategoriye göre -->
        <div id="builder-category-box" style="display:none">
          <div class="form-row">
            <div>
              <label for="builder-category">Kategori</label>
              <input type="text" id="builder-category" placeholder="Örn: Philosophy">
            </div>
            <div>
              <label for="builder-author-count">Kaç yazar?</label>
              <input type="number" id="builder-author-count" value="40" min="5" max="150">
            </div>
          </div>
          <button class="btn btn-primary" id="btn-fetch-authors">👥 Önemli Yazarları Getir</button>
        </div>

        <label style="display:flex;align-items:center;gap:8px;margin-top:12px;font-size:13px;color:var(--muted)">
          <input type="checkbox" id="builder-verify" checked>
          OpenLibrary ile doğrula + kapak/yıl getir
        </label>
      </div>

      <div id="builder-notif" class="notif"></div>

      <!-- Yazar listesi (kategoriye göre modda) -->
      <div id="builder-authors-card" class="card" style="display:none">
        <div class="card-title">Yazarlar <span id="builder-authors-count" style="color:var(--muted);font-weight:400;font-size:11px"></span></div>
        <div style="margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-green btn-sm" id="btn-fetch-all-works">⚡ Tüm Yazarların Eserlerini Getir</button>
          <span style="font-size:11px;color:var(--muted);align-self:center">(tek tek de getirebilirsin)</span>
        </div>
        <div style="overflow-x:auto">
          <table class="bulk-table" id="builder-authors-table">
            <thead><tr><th>#</th><th>Yazar</th><th>Dönem</th><th>Not</th><th>İşlem</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <!-- Toplanan kitap listesi -->
      <div id="builder-list-card" class="card" style="display:none">
        <div class="card-title">
          Oluşturulan Liste
          <span id="builder-list-count" class="badge badge-gold" style="margin-left:8px">0 kitap</span>
          <span id="builder-list-verified" class="badge badge-green" style="margin-left:4px;display:none"></span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
          <button class="btn btn-primary btn-sm" id="btn-builder-to-batch">📋 Toplu Batch'e Aktar</button>
          <button class="btn btn-ghost btn-sm" id="btn-builder-csv">⬇ CSV İndir</button>
          <button class="btn btn-ghost btn-sm" id="btn-builder-clear" style="color:var(--red)">✕ Temizle</button>
          <button class="btn btn-ghost btn-sm" id="btn-toggle-existing" style="display:none">⊘ Sitede olanları göster</button>
        </div>
        <div style="overflow-x:auto">
          <table class="bulk-table" id="builder-list-table">
            <thead><tr><th>#</th><th>Kapak</th><th>Kitap</th><th>Yazar</th><th>Yıl</th><th>✓</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- ══ OTOMATİK KUYRUK ══════════════════════════════════ -->
    <div id="mode-queue" style="display:none">
      <div class="card">
        <div class="card-title">Kategori Kuyruğu</div>
        <p style="font-size:13px;color:var(--muted);margin-bottom:14px">
          Kategori adı gir → sistem yazarları ve eserlerini sunucuda otomatik üretir → tarayıcı kapansa da devam eder.
        </p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
          <div style="flex:1;min-width:200px">
            <label class="form-label">Kategori</label>
            <input type="text" class="form-input" id="queue-category" placeholder="ör: philosophy, ethics, medieval literature">
          </div>
          <div>
            <label class="form-label">Yazar sayısı (max 50)</label>
            <input type="number" class="form-input" id="queue-author-count" value="50" min="10" max="50" style="width:90px">
          </div>
          <input type="hidden" id="queue-offset" value="0">
          <button class="btn btn-primary" id="btn-queue-create">▶ Kuyruğa Ekle</button>
        </div>
        <div id="queue-create-notif" class="notif" style="margin-top:10px"></div>
      </div>

      <div class="card" id="queue-list-card">
        <div class="card-title">Kayıtlı Kuyruklar <button class="btn btn-ghost btn-sm" id="btn-queue-refresh" style="margin-left:8px">↺</button></div>
        <div id="queue-list-body">
          <p style="color:var(--muted);font-size:13px">Yükleniyor...</p>
        </div>
      </div>
    </div>

  </main>
</div>
<script src="assets/app.js?v=<?= @filemtime(__DIR__.'/assets/app.js') ?: time() ?>"></script>
<script>updateTokenDisplay(3000);updateBulkTokenDisplay(3000);</script>
</body>
</html>
