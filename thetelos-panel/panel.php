<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/_version.php';
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
      <a href="panel.php" <?= !in_array($_GET['mode']??'', ['queue','cleaner'], true) ? 'class="active"' : '' ?>><span class="ico">✍</span> İçerik Üret</a>
      <a href="panel.php?mode=queue" <?= ($_GET['mode']??'') === 'queue' ? 'class="active"' : '' ?>><span class="ico">📋</span> Kuyruk</a>
      <a href="panel.php?mode=cleaner" <?= ($_GET['mode']??'') === 'cleaner' ? 'class="active"' : '' ?>><span class="ico">🧹</span> Liste Temizle</a>
      <a href="placeholders.php"><span class="ico">⏳</span> Yer Tutucular</a>
      <a href="seo.php"><span class="ico">🔍</span> İçerik SEO</a>
      <a href="seo-site.php"><span class="ico">🌐</span> Site SEO</a>
      <a href="content-audit.php"><span class="ico">🩺</span> İçerik Denetimi</a>
      <a href="content-guard.php"><span class="ico">🛡️</span> İçerik Koruma</a>
      <a href="recategorize.php"><span class="ico">🗂️</span> Kategori Düzelt</a>
      <a href="category-cleanup.php"><span class="ico">🧹</span> Kategori Temizle</a>
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
        <h2 id="page-title">Tek Kitap</h2>
        <p id="page-desc">Kitap adı ve yazar girerek özet veya analiz üretin</p>
      </div>
      <div class="tabs-top">
        <button class="tab-top-btn active" data-mode="single">✍ Tek Kitap</button>
        <button class="tab-top-btn" data-mode="bulk">📋 Toplu Batch</button>
        <button class="tab-top-btn" data-mode="builder">🧱 Liste Oluştur</button>
        <button class="tab-top-btn" data-mode="cleaner">🧹 Liste Temizle</button>
        <button class="tab-top-btn" data-mode="queue">⚙ Kuyruk</button>
      </div>
    </div>

    <!-- ── Aktif İşlem Göstergesi ── -->
    <div id="active-jobs-bar" style="display:none;background:#1a1a1a;border:1px solid #333;border-radius:8px;padding:10px 16px;margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <div style="display:flex;align-items:center;gap:8px">
          <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--gold);animation:pulse 1s infinite"></span>
          <strong style="font-size:13px">Arka planda çalışan işlem var</strong>
        </div>
        <button class="btn btn-ghost btn-sm" onclick="stopAllJobs()" style="color:var(--red)">⛔ Tümünü Durdur</button>
      </div>
      <div id="active-jobs-list" style="margin-top:8px;font-size:12px;color:var(--muted)"></div>
    </div>

    <!-- ── API Provider Toggle ── -->
    <div class="api-toggle-bar">
      <span class="api-toggle-label">API:</span>
      <div class="api-toggle-group">
        <button class="api-btn" data-provider="anthropic">
          <span class="api-dot anthropic"></span> Anthropic
        </button>
        <button class="api-btn" data-provider="gemini">
          <span class="api-dot gemini"></span> Gemini
        </button>
        <button class="api-btn active" data-provider="deepseek">
          <span class="api-dot deepseek"></span> DeepSeek
        </button>
      </div>
      <div class="api-sub-group" id="api-sub-anthropic" style="display:none">
        <button class="api-sub-btn active" data-model="claude-haiku-4-5-20251001" data-label="haiku">Haiku <span class="api-sub-hint">Hızlı</span></button>
        <button class="api-sub-btn" data-model="claude-sonnet-4-20250514" data-label="sonnet">Sonnet <span class="api-sub-hint">Kaliteli</span></button>
      </div>
      <span class="api-active-label" id="api-active-label"><?= defined('DEEPSEEK_MODEL') ? htmlspecialchars(in_array(DEEPSEEK_MODEL,['deepseek-chat','deepseek-reasoner'],true)?'deepseek-v4-flash':DEEPSEEK_MODEL) : 'deepseek' ?></span>
    </div>

    <div id="gen-notif"  class="notif"></div>
    <div id="bulk-notif" class="notif"></div>

    <!-- ══ TEK KİTAP ══════════════════════════════════════ -->
    <div id="mode-single">

      <div class="card">
        <div class="card-title">İçerik Tipi & Kitap Bilgisi</div>
        <div class="type-toggle">
          <input type="radio" name="type" id="t-source" value="source" checked>
          <label for="t-source">📖 Kaynak-Temelli</label>
          <input type="radio" name="type" id="t-summary"  value="summary">
          <label for="t-summary">📄 Kaynaksız Özet</label>
          <input type="radio" name="type" id="t-analysis" value="analysis">
          <label for="t-analysis">🔍 Analiz</label>
        </div>
        <div id="single-source-note" style="font-size:12px;color:#7fb37f;line-height:1.5;margin:0 0 12px;border:1px solid rgba(90,170,90,.35);border-radius:10px;padding:10px 12px;background:rgba(90,170,90,.06)">
          📖 <b>Kaynak-Temelli:</b> kitabın <b>gerçek tam metnini</b> (Project Gutenberg / Internet Archive)
          bulup ondan kapsamlı özet yazar. <b>Uydurma yok.</b> Bu bir arka-plan işidir; canlı önizleme yerine
          ilerleme gösterilir, bitince yazı doğrudan yayınlanır. Tam metin yoksa Bilgi Metni'ne düşer / atlar.
        </div>
        <div id="single-length-row" class="token-control" style="margin:0 0 16px">
          <div class="token-header">
            <label>Özet uzunluğu — istediğin kelimeyi seç</label>
            <span id="single-source-words-display" class="token-val">3.500 kelime · ~18 dk</span>
          </div>
          <input type="range" id="single-source-words" min="1500" max="8000" step="250" value="3500"
            oninput="updateSingleSourceWords(this.value)">
          <div class="token-marks"><span>1.5K</span><span>3K</span><span>4.5K</span><span>6K</span><span>8K</span></div>
          <p style="font-size:11px;color:var(--muted);margin:6px 0 0;line-height:1.5">
            Bu hedef yalnız <b>kaynak-temelli</b> özette (tam metin bulunursa) geçerlidir.
            Tam metin yoksa Bilgi Metni'ne düşer ve kısa olur — sonuç kutusunda hangisi olduğu yazar.
          </p>
        </div>
        <div id="single-rewrite-row" style="margin:0 0 12px">
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
            <input type="checkbox" id="single_rewrite" style="width:auto">
            <span>Kitap sitede zaten varsa <b>mevcut yazıyı yeniden yaz</b> (kopya oluşturma; başlık/kapak/kategori korunur, gövde yenilenir). İşaretsizse yeni yazı oluşturulur.</span>
          </label>
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

        <div class="token-control" id="single-token-control">
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
          <div id="single-parts-col">
            <label for="parts-select">Parça Sayısı <span style="color:var(--muted);font-weight:400;font-size:11px">(DeepSeek — en az)</span></label>
            <select id="parts-select" onchange="updateTokenDisplay(document.getElementById('token-slider').value)">
              <option value="2" selected>2 parça</option>
              <option value="3">3 parça (daha uzun)</option>
              <option value="4">4 parça (en uzun)</option>
            </select>
            <span id="parts-actual" style="display:block;margin-top:4px;font-size:11px;color:var(--muted)"></span>
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
        <div style="margin-top:10px">
          <label style="font-size:13px;cursor:pointer;display:flex;align-items:center;gap:8px">
            <input type="checkbox" id="bulk-skip-onsite" checked>
            <span id="skip-onsite-lbl">Sitede zaten olan yazarları atla</span>
          </label>
        </div>
        <label style="display:flex;align-items:flex-start;gap:8px;margin-top:10px;font-size:13px;color:var(--muted);cursor:pointer;line-height:1.5;border:1px solid rgba(190,120,220,.4);border-radius:10px;padding:12px 14px;background:rgba(190,120,220,.06)">
          <input type="checkbox" id="bulk_rewrite" style="margin-top:2px">
          <span>♻️ <b style="color:#c58af0">Yeniden yaz modu (mevcut yazıları güncelle)</b> — Aynı CSV listeni ver; sistem sitedeki MEVCUT yazıyı bulup <b>yalnız gövdesini</b> yeni dürüstlük kurallarıyla yeniden yazar (başlık, kapak, kategori, yazar AYNEN kalır). <b>Hiçbir yazı yayından kaldırılmaz:</b> model eseri tanımıyorsa gövdeye "içerik henüz hazırlanmadı" yer tutucusu konur, yazı yayında kalır ve <b>sorunlu listeye</b> düşer (sonra başka modelle yazılır).<br><b style="color:#e6963c">Not:</b> bu modda yukarıdaki "sitede olanları atla" kutusunu <b>KAPAT</b> — açık kalırsa liste elenir.</span>
        </label>
        <div style="display:flex;gap:8px;margin-top:10px" id="upload-actions" style="display:none">
          <button class="btn btn-ghost btn-sm" id="btn-add-more">+ Dosya Ekle</button>
          <button class="btn btn-ghost btn-sm" id="btn-clear-list" style="color:var(--red)">✕ Listeyi Temizle</button>
        </div>
      </div>

      <div class="card">
        <div class="card-title">Batch Ayarları</div>
        <div class="type-toggle">
          <input type="radio" name="bulk_type" id="bt-source" value="source" checked>
          <label for="bt-source">📖 Kaynak-Temelli Özet</label>
          <input type="radio" name="bulk_type" id="bt-info" value="info">
          <label for="bt-info">📚 Bilgi Metni</label>
          <input type="radio" name="bulk_type" id="bt-summary"  value="summary">
          <label for="bt-summary">📄 Kaynaksız Özet</label>
          <input type="radio" name="bulk_type" id="bt-analysis" value="analysis">
          <label for="bt-analysis">🔍 Analiz</label>
        </div>
        <div id="bulk-source-note" style="font-size:12px;color:#7fb37f;line-height:1.5;margin:-4px 0 12px;border:1px solid rgba(90,170,90,.35);border-radius:10px;padding:10px 12px;background:rgba(90,170,90,.06)">
          📖 <b>Kaynak-Temelli Özet (önerilen):</b> kitabın <b>GERÇEK tam metnini</b> (Project Gutenberg /
          Internet Archive) bulur, parça parça okuyup <b>yalnız metne dayalı</b> kapsamlı özet yazar.
          Tam metin yoksa → Wikipedia-temelli Bilgi Metni'ne düşer; o da yoksa yer tutucu. <b>Hiçbir aşamada
          uydurma yok.</b> DeepSeek erişilebilirse onu (ucuz), değilse Gemini kullanır.
        </div>
        <div id="bulk-info-note" style="display:none;font-size:12px;color:#7fb37f;line-height:1.5;margin:-4px 0 12px;border:1px solid rgba(90,170,90,.35);border-radius:10px;padding:10px 12px;background:rgba(90,170,90,.06)">
          📚 <b>Bilgi Metni:</b> kitap hakkında Wikipedia + Google Books + Open Library'den GERÇEK veri
          toplanır, model yalnız buna dayanarak yazar. <b>Uydurma yok.</b> (Kelime/parça ayarları kullanılmaz.)
        </div>
        <div id="bulk-length-row" class="token-control" style="margin:0 0 16px">
          <div class="token-header">
            <label>Özet uzunluğu (kaynak-temelli) — istediğin kelimeyi seç</label>
            <span id="bulk-source-words-display" class="token-val">3.500 kelime · ~18 dk</span>
          </div>
          <input type="range" id="bulk-source-words" min="1500" max="8000" step="250" value="3500"
            oninput="updateBulkSourceWords(this.value)">
          <div class="token-marks">
            <span>1.5K</span><span>3K</span><span>4.5K</span><span>6K</span><span>8K</span>
          </div>
          <p style="font-size:11px;color:var(--muted);margin:6px 0 0;line-height:1.5">
            Hedef kelime arttıkça kitap daha derin okunur ve özet uzar. Yaklaşık okuma süresi
            ~200 kelime/dk üzerinden gösterilir. Not: kaynak-temelli motor bütçeyi tüm özete
            paylaştırır — 3.500–4.500 arası çoğu roman için dengeli sonuç verir.
          </p>
        </div>

        <div class="token-control" id="bulk-token-control" style="margin-bottom:16px">
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

        <div class="form-row" id="bulk-parts-row" style="margin-top:14px">
          <div>
            <label for="bulk-parts-select">Parça Sayısı <span style="color:var(--muted);font-weight:400;font-size:11px">(DeepSeek — uzun içerik için)</span></label>
            <select id="bulk-parts-select">
              <option value="2" selected>2 parça</option>
              <option value="3">3 parça (daha uzun)</option>
              <option value="4">4 parça (en uzun)</option>
            </select>
            <span style="display:block;margin-top:6px;font-size:11px;color:var(--muted);line-height:1.5">
              Bu bir <b>alt sınır</b>: model tek istekte ~1800 kelimeden fazlasını yazmadığı için
              parça sayısı hedef kelimeye göre otomatik yükseltilir (ör. 8.000 kelime → 5 parça).
            </span>
          </div>
        </div>

        <!-- Claude modeli — yalnız üstte "Anthropic" seçiliyken geçerli.
             Ana içeriği Claude yazar; yoklama+meta yine DeepSeek (maliyet böl). -->
        <div class="form-row" id="claude-model-row" style="margin-top:14px;display:none">
          <div>
            <label for="bulk_claude_model">Claude Modeli <span style="color:#c58af0;font-weight:400;font-size:11px">(Anthropic seçili)</span></label>
            <select id="bulk_claude_model">
              <option value="sonnet" selected>Sonnet 4.5 — kaliteli (önerilen)</option>
              <option value="haiku">Haiku 4.5 — ucuz/hızlı</option>
            </select>
            <span style="display:block;margin-top:6px;font-size:11px;color:#e6963c;line-height:1.5">
              ⚠️ Claude ücretlidir. Ana metni Claude yazar; yoklama ve meta yine DeepSeek kalır
              (maliyet düşük tutulur). Önce <b>küçük bir listeyle (5–10)</b> deneyip maliyeti gör.
            </span>
          </div>
        </div>

        <?php
          $tls_peak_flag = __DIR__ . '/jobs/.peak-skip';
          $tls_peak_on   = !file_exists($tls_peak_flag) || trim((string) @file_get_contents($tls_peak_flag)) !== '0';
        ?>
        <label style="display:flex;align-items:flex-start;gap:8px;margin-top:16px;font-size:13px;color:var(--muted);cursor:pointer;line-height:1.5">
          <input type="checkbox" id="cb-peak-skip" style="margin-top:2px" <?= $tls_peak_on ? 'checked' : '' ?>>
          <span>💤 <b style="color:var(--text)">Yoğun saatlerde tasarruf et</b> — DeepSeek'in 2× fiyatlı saatlerinde (TR 04:00–07:00 &amp; 09:00–13:00) üretimi otomatik duraklatır, uygun saat gelince kaldığı yerden devam eder. Liste oluşturma (ücretsiz) etkilenmez. <span style="font-size:11px">(önerilen: açık)</span></span>
        </label>
        <span id="peak-skip-msg" style="display:block;margin-top:6px;font-size:12px;color:var(--gold)"></span>

        <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
          <button class="btn btn-primary" id="btn-batch-start" disabled>▶ Batch İşlemi Başlat</button>
          <button class="btn btn-ghost" id="btn-batch-pause" style="display:none">⏸ Duraklat</button>
        </div>
        <script>
        (function(){
          var cb = document.getElementById('cb-peak-skip'), msg = document.getElementById('peak-skip-msg');
          if(!cb) return;
          cb.addEventListener('change', function(){
            var fd = new FormData(); fd.append('on', cb.checked ? '1' : '0');
            fetch('api/peak-skip.php', {method:'POST', body:fd, credentials:'same-origin'})
              .then(function(r){return r.json();})
              .then(function(d){ msg.textContent = d && d.ok ? (cb.checked ? '✓ Tasarruf açık — yoğun saatlerde duraklatılacak.' : '✓ Tasarruf kapalı — her saat üretir.') : 'Kaydedilemedi.'; })
              .catch(function(){ msg.textContent = 'Bağlantı hatası.'; });
          });
        })();
        </script>
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

      <!-- ══ SORUNLU / YAZDIRILAMAYAN ESERLER ═══════════════════════
           Yeniden-yaz veya üretim sırasında temiz yayınlanamayan eserler
           (bulunamadı, model tanımadı, taslağa çekildi, hata) burada birikir.
           Bu listeyi indirip AYNI CSV formatıyla FARKLI bir modelle yeniden
           yazdırabilirsin. Sayfa açılışında yüklenmez — "Yenile" ile gelir. -->
      <div class="card" style="margin-top:14px">
        <div class="card-title" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          ⚠️ Sorunlu / Yazdırılamayan Eserler
          <span id="problems-count" class="badge badge-gray">—</span>
          <span style="flex:1"></span>
          <button class="btn btn-ghost btn-sm" id="btn-problems-refresh" type="button">🔄 Yenile</button>
          <button class="btn btn-green btn-sm" id="btn-problems-csv" type="button">⬇ CSV indir</button>
          <button class="btn btn-ghost btn-sm" id="btn-problems-clear" type="button" style="color:var(--muted)">🗑 Temizle</button>
        </div>
        <div style="font-size:12px;color:var(--muted);margin:2px 0 8px">
          Sistem bu listeyi kalıcı tutar (sürümler arası kaybolmaz). İndirdiğin CSV,
          Toplu Batch'e tekrar yükleyip başka bir modelle yazdırabileceğin formattadır.
        </div>
        <div id="problems-list" style="font-size:13px"></div>
      </div>

      <!-- ══ TASLAKTA KALAN YAZILAR (kurtarma) ═══════════════════════
           Eski turlarda yanlışlıkla taslağa çekilmiş gerçek yazıları bulur;
           tek tıkla (veya toptan) yayına alır. Ön yüzde 404 veren yazıların
           kaynağı budur. -->
      <div class="card" style="margin-top:14px">
        <div class="card-title" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          🗂 Taslakta Kalan Yazılar
          <span id="drafts-count" class="badge badge-gray">—</span>
          <span style="flex:1"></span>
          <button class="btn btn-ghost btn-sm" id="btn-drafts-refresh" type="button">🔄 Yenile</button>
          <button class="btn btn-green btn-sm" id="btn-drafts-puball" type="button">✅ Tümünü yayına al</button>
        </div>
        <div style="font-size:12px;color:var(--muted);margin:2px 0 8px">
          Ön yüzde görünmeyen (taslakta kalmış) yazılar burada listelenir. Tek tek
          "Yayınla" ya da toptan "Tümünü yayına al". Sayfa açılışında yüklenmez.
        </div>
        <div id="drafts-list" style="font-size:13px"></div>
      </div>
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

          <!-- Toplu yazar ekle (CSV) -->
          <div style="margin-top:16px;padding-top:14px;border-top:1px dashed var(--border)">
            <div class="card-title" style="margin-bottom:6px">📋 Toplu Yazar Ekle (CSV)</div>
            <p style="font-size:12px;color:var(--muted);margin:0 0 12px">
              İçinde <b>Yazar</b> sütunu olan bir CSV yükle. Yazarlar aşağıya dolar; sonra
              <b>⚡ tarayıcıda</b> (hızlı, küçük listeler) ya da <b>🌐 sunucuda</b> (arka plan, binlerce yazar) çek.
            </p>
            <div class="form-row one" style="margin-bottom:12px"><div>
              <label for="bulk-authors-name">Liste adı (opsiyonel)</label>
              <input type="text" id="bulk-authors-name" placeholder="Örn: Felsefe seçkisi">
            </div></div>

            <input type="file" id="bulk-authors-file" accept=".csv,text/csv" hidden>
            <div id="bulk-dropzone" class="tls-dropzone" tabindex="0" role="button" aria-label="CSV yükle">
              <div class="tls-dz-emoji">📄</div>
              <div class="tls-dz-main">CSV'yi buraya <b>sürükle</b> <span style="color:var(--muted)">ya da</span> <span class="tls-dz-link">bilgisayardan seç</span></div>
              <div class="tls-dz-hint">.csv · "Yazar" sütunu · UTF-8</div>
              <div class="tls-dz-file" id="bulk-dz-filename"></div>
            </div>
            <button class="btn btn-green" id="btn-bulk-authors-upload" style="margin-top:12px" disabled>📥 Listeyi Yükle</button>
          </div>

          <style>
            .tls-dropzone{
              border:2px dashed var(--border,#3a3a3a); border-radius:12px;
              padding:26px 18px; text-align:center; cursor:pointer;
              background:rgba(255,255,255,0.02); transition:all .18s ease; outline:none;
            }
            .tls-dropzone:hover,.tls-dropzone:focus{ border-color:var(--gold,#c9a24b); background:rgba(201,162,75,0.06); }
            .tls-dropzone.dragover{ border-color:var(--gold,#c9a24b); background:rgba(201,162,75,0.12); transform:scale(1.01); }
            .tls-dz-emoji{ font-size:34px; line-height:1; margin-bottom:8px; opacity:.9; }
            .tls-dz-main{ font-size:14px; }
            .tls-dz-link{ color:var(--gold,#c9a24b); text-decoration:underline; }
            .tls-dz-hint{ font-size:11px; color:var(--muted,#888); margin-top:6px; }
            .tls-dz-file{ font-size:13px; color:var(--green,#3fae6a); font-weight:600; margin-top:10px; }
          </style>
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
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn btn-primary" id="btn-fetch-authors">👥 Önemli Yazarları Getir</button>
            <button class="btn btn-ghost" id="btn-fetch-all-authors" title="Bu kategorideki TÜM önemli yazarları otomatik sayfalayarak getir">⬇️ Tümünü Getir</button>
          </div>
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
          <button class="btn btn-ghost btn-sm" id="btn-remove-onsite" title="Sitede zaten olan yazarları bu listeden çıkar">⊘ Sitede olanları çıkar</button>
          <button class="btn btn-primary btn-sm" id="btn-builder-to-queue" title="Bu yazar listesini sunucuya gönder; eserleri arka planda (tarayıcı/oturum kapalı olsa da) çekilsin. Sonra Kuyruk sayfasından 100'erli ZIP indir.">🌐 Sunucuda Eserleri Çek (arka plan)</button>
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

    <!-- ══ LİSTE TEMİZLE ═══════════════════════════════════ -->
    <div id="mode-cleaner" style="display:none">

      <div class="card">
        <div class="card-title">🧹 Eser Listesi Temizleme</div>
        <p style="font-size:13px;color:var(--muted);line-height:1.6;margin:0 0 14px">
          Panelden indirdiğin eser CSV'sini yükle. Sistem iki katmanda temizler:
          <b>1) Kurallar</b> — birebir/normalize tekrarları anında birleştirir (ücretsiz).
          <b>2) AI hakem</b> — aynı eserin farklı dil/çeviri baskılarını tek kanonik girişte
          (<i>"İngilizce ad (Orijinal ad)"</i> formatında) birleştirir, yazara ait olmayanları
          gerekçesiyle işaretler. AI liste <u>üretmez</u>, yalnız eldeki veriyi yargılar.
          Sonucu önizler, istediğini geri alır, temiz CSV indirirsin.
        </p>

        <input type="file" id="cleaner-file" accept=".csv,text/csv" hidden>
        <div id="cleaner-dropzone" class="tls-dropzone" tabindex="0" role="button" aria-label="CSV yükle">
          <div class="tls-dz-emoji">🧹</div>
          <div class="tls-dz-main">Eser CSV'sini buraya <b>sürükle</b> <span style="color:var(--muted)">ya da</span> <span class="tls-dz-link">bilgisayardan seç</span></div>
          <div class="tls-dz-hint">.csv · sütunlar: Kitap Adı, Yazar Adı, Yıl, Kapak</div>
          <div class="tls-dz-file" id="cleaner-dz-filename"></div>
        </div>

        <label style="display:flex;align-items:center;gap:8px;margin-top:12px;font-size:13px;color:var(--muted)">
          <input type="checkbox" id="cleaner-use-ai" checked>
          AI hakem kullan (çeviri birleştirme + yazara aidiyet kontrolü — DeepSeek)
        </label>
        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;font-size:13px;color:var(--muted)">
          <input type="checkbox" id="cleaner-drop-onsite" checked>
          Sitede zaten olan yazarların eserlerini ele (varyant adları da tanır: "Avicenna (İbn Sina)" vb.)
        </label>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
          <button class="btn btn-green" id="btn-cleaner-start" disabled>🧹 Temizlemeyi Başlat</button>
          <button class="btn btn-ghost" id="btn-cleaner-cancel" style="display:none;color:var(--red)">⛔ Durdur</button>
        </div>
        <p style="font-size:11px;color:var(--muted);margin-top:8px">
          Not: AI hakem yazar başına 1 istek yapar (aynı anda 4 yazar işlenir); 100 yazarlık dosya
          ~20-40 dk sürer. Sekmeyi kapatsan da ilerleme kaydedilir — sonra "Kaldığı Yerden Devam Et" ile sürdürürsün.
        </p>
      </div>

      <div id="cleaner-notif" class="notif"></div>

      <!-- İlerleme -->
      <div id="cleaner-progress-card" class="card" style="display:none">
        <div class="card-title">İlerleme <span id="cleaner-progress-text" style="color:var(--muted);font-weight:400;font-size:11px"></span></div>
        <div style="background:#2a2a2a;border-radius:4px;height:8px;overflow:hidden">
          <div id="cleaner-progress-bar" style="background:var(--green);height:100%;width:0%;transition:width .3s"></div>
        </div>
        <div id="cleaner-stats" style="margin-top:10px;font-size:13px;color:var(--muted)"></div>
      </div>

      <!-- Sonuç -->
      <div id="cleaner-result-card" class="card" style="display:none">
        <div class="card-title">Sonuç
          <span id="cleaner-result-summary" style="color:var(--muted);font-weight:400;font-size:11px"></span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
          <button class="btn btn-green btn-sm" id="btn-cleaner-export">⬇ Temiz CSV indir</button>
          <button class="btn btn-ghost btn-sm" id="btn-cleaner-export-removed">⬇ Elenenler raporu (CSV)</button>
        </div>
        <div style="overflow-x:auto">
          <table class="bulk-table" id="cleaner-table">
            <thead><tr><th>#</th><th>Eser (temiz)</th><th>Yazar</th><th>Yıl</th><th>Birleşen</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div id="cleaner-removed-wrap" style="margin-top:16px;display:none">
          <div class="card-title" style="font-size:13px">Elenenler <span id="cleaner-removed-count" style="color:var(--muted);font-weight:400;font-size:11px"></span></div>
          <div style="overflow-x:auto">
            <table class="bulk-table" id="cleaner-removed-table">
              <thead><tr><th>Eser</th><th>Yazar</th><th>Neden</th><th></th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>

    </div>

    <!-- ══ OTOMATİK KUYRUK ══════════════════════════════════ -->
    <div id="mode-queue" style="display:none">

      <?php
      /* ── Yarım kalan toplu batchler — kitapların GERÇEK durumlarını say ── */
      $jobs_dir = __DIR__ . '/jobs';
      if (!is_dir($jobs_dir)) $jobs_dir = dirname(__DIR__) . '/jobs';
      $all_files = glob("$jobs_dir/batch_*.json") ?: [];
      $stuck_batches = [];
      foreach ($all_files as $f) {
          $d = json_decode(file_get_contents($f), true);
          if (!$d || empty($d['books']) || !is_array($d['books'])) continue;
          $cnt = ['pending'=>0,'processing'=>0,'error'=>0,'done'=>0];
          foreach ($d['books'] as $bk) {
              $s = $bk['status'] ?? 'pending';
              if (!isset($cnt[$s])) $cnt[$s] = 0;
              $cnt[$s]++;
          }
          $remaining = $cnt['pending'] + $cnt['processing'];
          // Bekleyen/işlenen veya hatalı kitabı olan her batch'i göster (status fark etmez)
          if ($remaining <= 0 && $cnt['error'] <= 0) continue;
          $d['_cnt']       = $cnt;
          $d['_remaining'] = $remaining;
          $d['_total']     = count($d['books']);
          $stuck_batches[] = $d;
      }
      // En yeni önce
      usort($stuck_batches, fn($a,$b) => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0));
      ?>
      <?php
      /* Yoğun saat durumu: batch "durmuş" görünüyorsa sebebi burada yazsın —
         arıza mı, tasarruf molası mı ayırt edilebilsin. */
      $tls_peak_flag = __DIR__ . '/jobs/.peak-skip';
      $tls_peak_on   = !file_exists($tls_peak_flag) || trim((string) @file_get_contents($tls_peak_flag)) !== '0';
      $tls_h_utc     = (int) gmdate('G');
      $tls_in_peak   = ($tls_h_utc >= 1 && $tls_h_utc < 4) || ($tls_h_utc >= 6 && $tls_h_utc < 10);
      if ($tls_peak_on && $tls_in_peak):
          $tls_end_utc = ($tls_h_utc < 4) ? 4 : 10;                 // yoğunluğun biteceği UTC saat
          $tls_end_tr  = ($tls_end_utc + 3) % 24;                    // TR = UTC+3
      ?>
      <div style="margin-bottom:10px;padding:10px 14px;background:rgba(212,180,131,.12);border:1px solid var(--gold);border-radius:6px;font-size:13px;color:var(--gold)">
        &#9208; <b>Üretim duraklatıldı — yoğun saat tasarrufu</b> (arıza değil).
        DeepSeek şu an <b>2× fiyat</b> uyguluyor.
        <b><?= sprintf('%02d:00', $tls_end_tr) ?></b>'te kendiliğinden devam edecek.
        <span style="color:var(--muted)">Beklemek istemezsen "Yoğun saatlerde tasarruf et" kutusunu kaldır.</span>
      </div>
      <?php endif; ?>
      <div style="font-size:11px;color:#555;margin-bottom:8px;padding:6px 10px;background:#111;border-radius:4px">
        panel v6 &middot; <?= strip_tags(tls_version_badge()) ?> &middot; jobs: <?= is_dir($jobs_dir) ? 'var' : 'YOK' ?> &middot; dosya: <?= count($all_files) ?> &middot; yarim kalan: <?= count($stuck_batches) ?>
        &middot; tasarruf: <?= $tls_peak_on ? 'açık' : 'kapalı' ?><?= $tls_in_peak ? ' (şu an yoğun saat)' : '' ?>
      </div>
      <?php if ($stuck_batches): ?>
      <div class="card" style="border-left:3px solid var(--gold)">
        <div class="card-title" style="color:var(--gold)">&#9888; Yarım Kalan Toplu Batchler</div>
        <?php
        /* Tek kart render eden yardımcı */
        $newest_total = $stuck_batches[0]['_total'] ?? 0;
        $render_batch = function($b, $is_active = false) use ($newest_total) {
          $tot     = $b['_total'];
          $cnt     = $b['_cnt'];
          $pending = $b['_remaining'];
          $errs    = $cnt['error'];
          $okc     = $cnt['done'];
          $pct     = $tot > 0 ? round($okc / $tot * 100) : 0;
          $bid     = htmlspecialchars($b['id'] ?? '');
          $when    = !empty($b['created_at']) ? date('d.m.Y H:i', (int)$b['created_at']) : '';
          $wkrs    = max(1, min(5, (int)($b['workers'] ?? 1)));
          // Canlılık: en son ne zaman ilerledi
          $la  = (int)($b['last_activity'] ?? 0);
          $ago = $la ? (time() - $la) : null;
          $agoTxt = $ago === null ? '' : ($ago < 90 ? $ago . ' sn önce' : ($ago < 5400 ? round($ago/60) . ' dk önce' : round($ago/3600) . ' sa önce'));
          $alive  = $ago !== null && $ago < 300;
          // Eski kopya şüphesi: aktif olmayan + hiç ilerlememiş + aynı boyut
          $dup_hint = (!$is_active && $okc === 0 && $errs === 0 && $tot === $newest_total);
          ob_start(); ?>
        <div style="background:#1a1a1a;border-radius:6px;padding:12px;margin-bottom:8px;<?= $is_active ? 'border:1px solid var(--gold);' : 'opacity:.85;' ?>" data-batch-card="<?= $bid ?>" data-pending="<?= $pending ?>" data-workers="<?= $wkrs ?>">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;flex-wrap:wrap;gap:6px">
            <span style="font-size:13px;font-weight:600">
              <?php if ($is_active): ?><span style="background:var(--gold);color:#111;font-size:10px;padding:2px 7px;border-radius:10px;margin-right:6px;vertical-align:1px">AKTİF</span>
              <?php else: ?><span style="background:#333;color:#999;font-size:10px;padding:2px 7px;border-radius:10px;margin-right:6px;vertical-align:1px">ESKİ</span><?php endif; ?>
              <?= $b['type'] === 'analysis' ? 'Derin Analiz' : 'Özet' ?> &middot; <?= $tot ?> kitap
              <span style="color:#555;font-weight:400;font-size:11px"> &middot; başlatıldı <?= $when ?></span>
              <?php if ($agoTxt): ?>
                <span style="font-weight:400;font-size:11px;color:<?= $alive ? 'var(--green)' : '#cc6b00' ?>"> &middot; <?= $alive ? '● çalışıyor' : '⏸ duraklamış olabilir' ?> &middot; <?= $agoTxt ?> ilerledi</span>
              <?php endif; ?>
            </span>
            <span style="font-size:12px;color:var(--muted)" data-bc-meta><?= $okc ?> &#10003; &middot; <?= $errs ?> hata &middot; <?= $pending ?> bekliyor / <?= $tot ?></span>
          </div>
          <?php if ($dup_hint): ?>
          <div style="font-size:12px;color:#cc6b00;margin-bottom:8px">⚠ Bu, aynı listenin hiç ilerlememiş eski kopyası görünüyor — güvenle silebilirsin.</div>
          <?php endif; ?>
          <div style="background:#2a2a2a;border-radius:4px;height:5px;overflow:hidden;margin-bottom:10px">
            <div style="background:var(--gold);height:100%;width:<?= $pct ?>%" data-bc-bar></div>
          </div>
          <?php
          // "Nerede kaldık" işaretçisi: şu an işlenen (yoksa sıradaki) kitap
          $cur_i = -1; $cur_t = ''; $cur_mode = '';
          foreach ($b['books'] as $ci => $cb) {
              if (($cb['status'] ?? '') === 'processing' && empty($cb['post_id'])) { $cur_i = $ci; $cur_t = trim($cb['book_title'] ?? ''); $cur_mode = 'işleniyor'; break; }
          }
          if ($cur_i < 0) foreach ($b['books'] as $ci => $cb) {
              if (($cb['status'] ?? '') === 'pending') { $cur_i = $ci; $cur_t = trim($cb['book_title'] ?? ''); $cur_mode = 'sırada'; break; }
          }
          if ($cur_i >= 0): ?>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:12px">
            <span style="color:var(--gold)">📍 <?= $cur_mode === 'işleniyor' ? 'Şu an işleniyor' : 'Sırada' ?> (<?= $cur_i + 1 ?>/<?= $tot ?>):</span>
            <span style="color:#ddd;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:50%"><?= htmlspecialchars(mb_substr($cur_t, 0, 70)) ?></span>
            <button class="btn btn-ghost btn-sm" style="padding:2px 8px;font-size:11px" onclick="tlsJumpToCurrent('<?= $bid ?>',<?= $cur_i ?>)">↓ listede göster</button>
          </div>
          <?php endif; ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php if ($pending > 0): ?>
            <button class="btn btn-primary btn-sm" onclick="tlsResumeBatch('<?= $bid ?>',this)">&#9654; Kaldigi Yerden Devam Et (<?= $pending ?>)</button>
            <?php endif; ?>
            <?php $bstat = $b['status'] ?? ''; ?>
            <?php if ($pending > 0 && $bstat !== 'paused' && $bstat !== 'cancelled'): ?>
            <button class="btn btn-ghost btn-sm" style="color:var(--gold)" onclick="tlsPauseBatch('<?= $bid ?>',this)">&#9208; Duraklat</button>
            <?php endif; ?>
            <?php if ($errs > 0): ?>
            <button class="btn btn-ghost btn-sm" style="color:var(--gold)" onclick="tlsRetryErrors('<?= $bid ?>',this)">&#8634; <?= $errs ?> hatayi tekrar dene</button>
            <a class="btn btn-ghost btn-sm" style="color:var(--green);text-decoration:none" href="api/export-errors.php?batch_id=<?= urlencode($bid) ?>">&#8595; Hataları CSV indir</a>
            <a class="btn btn-ghost btn-sm" style="color:var(--red);text-decoration:none" href="api/error-reasons.php?batch_id=<?= urlencode($bid) ?>" target="_blank">&#9888; Hata sebepleri</a>
            <?php endif; ?>
            <a class="btn btn-ghost btn-sm" style="color:var(--green);text-decoration:none" href="api/batch-results.php?batch_id=<?= urlencode($bid) ?>" title="Her kitabın yöntemi: kaynak-temelli / bilgi-metni / yer-tutucu / eski-korundu / hata — 'Sorunlu?' sütunuyla süz">&#8595; Tüm sonuçlar (yöntemli CSV)</a>
            <button class="btn btn-ghost btn-sm" style="color:var(--red);margin-left:auto" onclick="tlsDeleteBatch('<?= $bid ?>',this)">&#10005; Sil</button>
          </div>
          <div data-bc-hint style="font-size:12px;color:var(--muted);margin-top:8px;<?= ($bstat ?? '') === 'paused' ? '' : 'display:none' ?>">
            ⏸ Duraklatıldı — yeni kitap alınmıyor. Devam ettirdiğinde kaldığı yerden sürer.
          </div>

          <?php
          /* ── Kitap listesi — açılır, canlı güncellenen ──────────────── */
          $st_map = [
            'done'       => ['#1f7a3d', '✓'],
            'processing' => ['#b8860b', '⚙'],
            'stale'      => ['#cc4400', '!'],
            'error'      => ['#a33',    '✕'],
            'pending'    => ['#333',    '…'],
          ];
          // Stale eşiği parça sayısına göre (heartbeat yok; klaym anından ölçülür)
          // Parça sayısı hedef kelimeye göre otomatik yükseliyor (bkz.
          // bw_effective_parts); eşik seçilen değere göre hesaplanırsa uzun
          // kitaplar boş yere "takıldı" görünür.
          $bparts = max(
              max(1, min(6, (int)($b['parts'] ?? 2))),
              min(6, (int)ceil(max(500, min(8000, (int)($b['max_tokens'] ?? 3000))) / 1800))
          );
          $stale_secs = $bparts * 300 + 300;
          $now_ts = time();
          ?>
          <details style="margin-top:8px" open>
            <summary style="cursor:pointer;color:var(--muted);font-size:12px;padding:4px 0">&#9656; Kitap listesi (<?= $tot ?>)</summary>
            <div data-bc-list style="max-height:340px;overflow-y:auto;margin-top:6px;border:1px solid #2a2a2a;border-radius:4px">
              <?php foreach ($b['books'] as $i => $bk):
                $bs    = $bk['status'] ?? 'pending';
                $psince= (int)($bk['processing_since'] ?? 0);
                $elapsed = $psince > 0 ? ($now_ts - $psince) : 0;
                // 7 dk+ processing ve post_id yoksa stale göster
                $effective = ($bs === 'processing' && empty($bk['post_id']) && $elapsed > $stale_secs) ? 'stale' : $bs;
                [$bg, $ico] = $st_map[$effective] ?? $st_map['pending'];
                $bt   = htmlspecialchars(trim($bk['book_title'] ?? ''));
                $bauth= htmlspecialchars(trim($bk['author_name'] ?? ''));
                $purl = htmlspecialchars($bk['post_url'] ?? '');
                $time_label = '';
                if ($bs === 'processing' && $elapsed > 0) {
                  $m = floor($elapsed / 60); $s = $elapsed % 60;
                  $time_label = $m > 0 ? " {$m}dk" : " {$s}sn";
                }
              ?>
              <div data-bc-row="<?= $i ?>" data-bc-since="<?= $psince ?>" style="display:flex;align-items:center;gap:8px;padding:5px 8px;font-size:12px;border-bottom:1px solid #222">
                <span data-bc-ico style="flex:0 0 18px;text-align:center;color:#fff;background:<?= $bg ?>;border-radius:3px;font-size:11px;line-height:18px"><?= $ico ?></span>
                <span style="flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                  <?php if ($purl): ?><a href="<?= $purl ?>" target="_blank" style="color:#ddd;text-decoration:none"><?= $bt ?></a><?php else: ?><span style="color:#ddd"><?= $bt ?></span><?php endif; ?>
                  <?php if ($bauth): ?><span style="color:#666"> — <?= $bauth ?></span><?php endif; ?>
                </span>
                <?php if ($time_label): ?><span data-bc-timer style="flex:0 0 auto;color:<?= $effective==='stale'?'#cc4400':'#888' ?>;font-size:11px"><?= $time_label ?></span><?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </details>
        </div>
          <?php return ob_get_clean();
        };

        // İlk (en yeni) batch — ana listen, her zaman açık
        echo $render_batch($stuck_batches[0], true);

        // Geri kalan eski batch'ler — katlanır bölümde gizli
        $rest = array_slice($stuck_batches, 1);
        if ($rest): ?>
        <details style="margin-top:6px">
          <summary style="cursor:pointer;color:var(--muted);font-size:12px;padding:6px 0">
            &#9656; Diğer <?= count($rest) ?> eski yarım batch (göster / temizle)
          </summary>
          <div style="margin-top:8px">
            <?php foreach ($rest as $b) echo $render_batch($b); ?>
          </div>
        </details>
        <?php endif; ?>
      </div>
      <script>
      var _tlsPanelBase = (function(){ var p=window.location.pathname; return p.substring(0,p.lastIndexOf('/')+1); })();

      /* "Nerede kaldık": listeyi ilgili satıra kaydır + vurgula */
      function tlsJumpToCurrent(bid, idx) {
        var card = document.querySelector('[data-batch-card="'+bid+'"]');
        if (!card) return;
        var det = card.querySelector('details'); if (det) det.open = true;
        var row = card.querySelector('[data-bc-row="'+idx+'"]');
        if (!row) return;
        row.scrollIntoView({ block: 'center', behavior: 'smooth' });
        row.style.transition = 'background .3s';
        row.style.background = 'rgba(200,161,101,.25)';
        setTimeout(function(){ row.style.background = ''; }, 2500);
      }
      /* Sayfa açılınca her listeyi kaldığı yere (ilk işlenen/bekleyen satıra) kaydır */
      document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-batch-card]').forEach(function (card) {
          var list = card.querySelector('[data-bc-list]'); if (!list) return;
          var target = null;
          card.querySelectorAll('[data-bc-row]').forEach(function (row) {
            if (target) return;
            var ico = row.querySelector('[data-bc-ico]');
            var t = ico ? ico.textContent.trim() : '';
            if (t === '⚙' || t === '!' || t === '…') target = row;
          });
          if (target) list.scrollTop = Math.max(0, target.offsetTop - list.clientHeight / 2);
        });
      });
      function _tlsFireWorkers(id, count) {
        for (var i = 0; i < count; i++) {
          fetch(_tlsPanelBase+'api/batch-worker.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'batch_id='+id}).catch(()=>{});
        }
      }

      /* ── Kart canlı ilerleme güncelleyici ────────────────────────────
       * Bu liste sayfa yenilense / tarayıcı kapanıp açılsa bile sunucudaki
       * gerçek durumu gösterir. _tlsWatch yalnızca KARTI canlı günceller;
       * takılan batch'leri yeniden ATEŞLEME işini küresel nöbetçi
       * (app.js → checkActiveJobs, her 10sn, her sekmede) üstlenir. */
      var _tlsWatchTimers = {};
      var _tlsStaleKick   = {};  // batch_id -> son stale kick zamanı (ms)

      function _fmtElapsed(secs) {
        if (secs <= 0) return '';
        var m = Math.floor(secs / 60), s = secs % 60;
        return m > 0 ? m+'dk' : s+'sn';
      }

      /* Kart izleyicisi de sekme görünmüyorken YOKLAMA YAPMAZ ve sunucu
         cevap vermezse aralığını açar. Beş açık sekme × 8 saniye, hiç kimse
         hiçbir şey yapmasa bile sunucuyu meşgul ediyordu; sunucu zorlanırken
         aynı hızda devam etmek durumu ağırlaştırıyordu. */
      var _tlsWatchFails = {};
      function _tlsWatch(id) {
        if (_tlsWatchTimers[id]) return;
        _tlsWatchFails[id] = 0;
        _tlsWatchTimers[id] = setInterval(async function () {
          if (document.hidden) return;
          // Arka arkaya hata: turların bir kısmını atlayarak yavaşla.
          if (_tlsWatchFails[id] > 0 &&
              (Date.now() % (8000 * Math.min(8, _tlsWatchFails[id] + 1))) > 8000) return;
          try {
            var r = await fetch(_tlsPanelBase+'api/batch-status.php?batch_id='+encodeURIComponent(id));
            var j = await r.json();
            if (!j.ok || !j.batch) { _tlsWatchFails[id]++; return; }
            _tlsWatchFails[id] = 0;
            var b = j.batch, st = b.status || '', books = b.books || [];
            var pending = 0, processing = 0, done = 0, errs = 0;
            var nowSec = Math.floor(Date.now() / 1000);
            var _EFF_PARTS = Math.max(
                  Math.max(1, Math.min(6, b.parts || 2)),
                  Math.min(6, Math.ceil(Math.max(500, Math.min(8000, b.max_tokens || 3000)) / 1800)));
            var _STALE_SECS = (_EFF_PARTS * 300) + 300;
            var hasStale = false;

            for (var i = 0; i < books.length; i++) {
              var s = books[i].status;
              if (s === 'pending') pending++;
              else if (s === 'processing') processing++;
              else if (s === 'done') done++;
              else if (s === 'error') errs++;
              if (s === 'processing' && !books[i].post_id) {
                var elapsed = books[i].processing_since > 0 ? (nowSec - books[i].processing_since) : 0;
                if (elapsed > _STALE_SECS) hasStale = true;
              }
            }
            var tot = books.length;
            var card = document.querySelector('[data-batch-card="'+id+'"]');
            if (card) {
              card.setAttribute('data-pending', pending + processing);
              var meta = card.querySelector('[data-bc-meta]');
              if (meta) meta.innerHTML = done+' &#10003; &middot; '+errs+' hata &middot; '+(pending+processing)+' bekliyor / '+tot;
              var bar = card.querySelector('[data-bc-bar]');
              if (bar) bar.style.width = (tot>0 ? Math.round(done/tot*100) : 0)+'%';

              // Her kitap satırını güncelle: renk, ikon, süre
              for (var k = 0; k < books.length; k++) {
                var bk = books[k];
                var row = card.querySelector('[data-bc-row="'+k+'"]');
                if (!row) continue;
                var ico = row.querySelector('[data-bc-ico]');
                var tim = row.querySelector('[data-bc-timer]');
                var isStale = (bk.status === 'processing' && !bk.post_id &&
                               bk.processing_since > 0 && (nowSec - bk.processing_since) > _STALE_SECS);
                var bg, symbol;
                if      (bk.status === 'done')                    { bg='#1f7a3d'; symbol='✓'; }
                else if (bk.status === 'error')                   { bg='#a33';    symbol='✕'; }
                else if (isStale)                                  { bg='#cc4400'; symbol='!'; }
                else if (bk.status === 'processing')              { bg='#b8860b'; symbol='⚙'; }
                else                                               { bg='#333';    symbol='…'; }
                if (ico) { ico.style.background = bg; ico.textContent = symbol; }
                // Süre göstergesi (sadece processing için)
                if (bk.status === 'processing' && bk.processing_since > 0) {
                  var el = _fmtElapsed(nowSec - bk.processing_since);
                  if (!tim) {
                    tim = document.createElement('span');
                    tim.setAttribute('data-bc-timer', '');
                    tim.style.cssText = 'flex:0 0 auto;font-size:11px;margin-left:4px';
                    row.appendChild(tim);
                  }
                  tim.textContent = el;
                  tim.style.color = isStale ? '#cc4400' : '#888';
                } else if (tim) {
                  tim.textContent = '';
                }
              }
            }

            // NOT: takılan worker'ı yeniden ateşleme işini ARTIK tek motor
            // (app.js → checkActiveJobs) yapıyor; o, seçilen worker sayısını
            // korur ve asla aşmaz. Burada ayrıca ateşlemek flood'a yol açardı.

            if (st === 'cancelled' || (pending === 0 && processing === 0)) {
              clearInterval(_tlsWatchTimers[id]); delete _tlsWatchTimers[id];
            }
          } catch (e) { _tlsWatchFails[id]++; }
        }, 8000);
      }
      // Sayfa açılışında bekleyen işi olan tüm batch kartlarını canlı izlemeye al.
      document.querySelectorAll('[data-batch-card]').forEach(function (c) {
        if (parseInt(c.getAttribute('data-pending') || '0', 10) > 0) _tlsWatch(c.getAttribute('data-batch-card'));
      });
      /* Yoğun saat bilgisi (sunucu saatine göre) — devam butonunda uyarı için */
      window._tlsInPeak    = <?= ( ! empty($tls_peak_on) && ! empty($tls_in_peak) ) ? 'true' : 'false' ?>;
      window._tlsPeakEndTr = <?= isset($tls_end_tr) ? (int) $tls_end_tr : 13 ?>;
      function _tlsCardWorkers(id) {
        var c = document.querySelector('[data-batch-card="'+id+'"]');
        return Math.max(1, Math.min(5, parseInt((c && c.getAttribute('data-workers')) || '1', 10)));
      }
      async function tlsResumeBatch(id,btn) {
        // Yoğun saatte devam ettirmek 2× fiyat demek DEĞİL: worker saati kontrol
        // edip üretmeden çıkar. Kullanıcı boşuna beklemesin diye açıkça söyle.
        if (window._tlsInPeak) {
          if (!confirm('Şu an DeepSeek yoğun saati (2× fiyat). Tasarruf açık olduğu için worker üretim yapmadan duracak — yani şimdi başlatmak bir şey değiştirmez, saat '+window._tlsPeakEndTr+':00\'te zaten kendiliğinden devam edecek.\n\nYine de denemek istiyor musun?')) return;
        }
        btn.disabled=true; btn.textContent='Baslatiliyor...';
        await fetch(_tlsPanelBase+'api/batch-control.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'batch_id='+id+'&action=resume'}).catch(()=>{});
        var w = _tlsCardWorkers(id);
        _tlsFireWorkers(id, w);
        _tlsWatch(id);
        btn.textContent='✓ '+w+' worker basladi! (arka planda devam ediyor)'; btn.style.background='var(--green)';
      }
      /* Duraklat: batch'i "paused" yapar. Worker'lar SIRADAKİ kitabı almadan
         önce bu durumu görüp temiz çıkar; şu an işlenmekte olan kitap yarıda
         kesilmez. Küresel nöbetçi (app.js) duraklatılmış batch'i atlar, yani
         kendiliğinden yeniden başlamaz. Devam Et, yarım kalan "processing"
         kitapları pending'e çevirdiği için hiçbir kitap kaybolmaz. */
      async function tlsPauseBatch(id,btn) {
        var old = btn.innerHTML;
        btn.disabled = true; btn.textContent = 'Duraklatiliyor...';
        var ok = false;
        try {
          var r = await fetch(_tlsPanelBase+'api/batch-control.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'batch_id='+id+'&action=pause'});
          var j = await r.json();
          ok = !!j.ok;
        } catch(e) {}
        if (!ok) {
          btn.disabled = false; btn.innerHTML = old;
          alert('Duraklatilamadi — tekrar dene. (Oturum dusmus olabilir, sayfayi yenile.)');
          return;
        }
        btn.innerHTML = '⏸ Duraklatildi'; btn.style.color = 'var(--green)';
        if (_tlsWatchTimers[id]) { clearInterval(_tlsWatchTimers[id]); delete _tlsWatchTimers[id]; }
        var card = document.querySelector('[data-batch-card="'+id+'"]');
        var hint = card && card.querySelector('[data-bc-hint]');
        if (hint) {
          hint.style.display = '';
          hint.textContent = '⏸ Duraklatildi — su an isteme alinmis kitap bitince (birkac dakika) tamamen duracak. Devam ettirdiginde kaldigi yerden surer, hicbir kitap kaybolmaz.';
        }
      }
      async function tlsRetryErrors(id,btn) {
        btn.disabled=true; btn.textContent='Kuyruğa aliniyor...';
        await fetch(_tlsPanelBase+'api/batch-control.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'batch_id='+id+'&action=retry_errors'}).catch(()=>{});
        var w = _tlsCardWorkers(id);
        _tlsFireWorkers(id, w);
        _tlsWatch(id);
        btn.textContent='✓ '+w+' worker yeniden deneniyor! (arka planda)'; btn.style.color='var(--green)';
      }
      async function tlsDeleteBatch(id,btn) {
        if (!confirm('Bu batch kaydı silinsin mi? (Yayınlanmış içerikler silinmez, sadece bu işlem kaydı silinir)')) return;
        btn.disabled=true; btn.textContent='Siliniyor...';
        await fetch(_tlsPanelBase+'api/batch-control.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'batch_id='+id+'&action=delete'}).catch(()=>{});
        var card = document.querySelector('[data-batch-card="'+id+'"]');
        if (card) card.remove();
      }
      </script>
      <?php endif; ?>

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
