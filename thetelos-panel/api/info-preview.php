<?php
/**
 * info-preview.php — KAYNAK-TEMELLİ BİLGİ METNİ prototipini canlıda dene.
 *
 * Yayına HİÇBİR ŞEY yazmaz. Bir kitap+yazar girersin; sistem Wikipedia+Google
 * Books+Open Library'den veriyi toplar, DeepSeek (varsayılan) ile bilgi metni
 * yazar ve sana gösterir: hangi kaynaklar bulundu, üretilen metin, kelime
 * sayısı ve ham kaynak dosyası. Amaç: siteye yaymadan önce çıktı kalitesini görmek.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); echo 'Yetkisiz.'; exit; }

$book     = trim($_GET['book']     ?? '');
$author   = trim($_GET['author']   ?? '');
$provider = ($_GET['provider'] ?? 'deepseek') === 'anthropic' ? 'anthropic' : 'deepseek';

$result = null; $err = '';
if ($book !== '') {
    @set_time_limit(180);
    require_once __DIR__ . '/_info.php';
    $result = tls_info_generate($book, $author, ['provider' => $provider]);
}

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="tr"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bilgi Metni — Önizleme</title>
<style>
  body{font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:900px;margin:0 auto;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.6}
  h1{font-size:20px} h2{font-size:16px;color:#9ab}
  form{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;background:#171a21;padding:16px;border-radius:12px;border:1px solid #262b36}
  label{display:block;font-size:12px;color:#9aa;margin-bottom:4px}
  input,select{padding:9px 10px;border-radius:8px;border:1px solid #333;background:#0f1115;color:#eee;font-size:14px}
  input[name=book]{min-width:240px} input[name=author]{min-width:200px}
  button{padding:9px 16px;border-radius:8px;border:0;background:#3b82f6;color:#fff;font-weight:600;cursor:pointer;font-size:14px}
  .card{background:#171a21;border:1px solid #262b36;border-radius:12px;padding:18px 20px;margin-top:18px}
  .badge{display:inline-block;background:#22303f;color:#9cf;border-radius:20px;padding:2px 10px;font-size:12px;margin:0 4px 4px 0}
  .warn{color:#e0a800} .muted{color:#8a93a2;font-size:13px}
  .article h3{font-size:15px;color:#cbd5e1;margin:18px 0 6px}
  .article p{margin:8px 0}
  details{margin-top:14px} details pre{white-space:pre-wrap;background:#0b0d11;padding:12px;border-radius:8px;font-size:12px;color:#9aa;max-height:360px;overflow:auto}
</style>
</head><body>
<h1>📚 Kaynak-Temelli Bilgi Metni — Önizleme</h1>
<p class="muted">Yayına hiçbir şey yazmaz. Kitabı gir → sistem Wikipedia + Google Books + Open Library'den derler, DeepSeek ile bilgi metni üretir. Kalitesini gör, sonra siteye yaymaya karar veririz.</p>

<form method="get">
  <div><label>Kitap adı</label><input name="book" value="<?= h($book) ?>" placeholder="Bureaucracy" required></div>
  <div><label>Yazar</label><input name="author" value="<?= h($author) ?>" placeholder="Ludwig von Mises"></div>
  <div><label>Model</label>
    <select name="provider">
      <option value="deepseek" <?= $provider==='deepseek'?'selected':'' ?>>DeepSeek (ucuz)</option>
      <option value="anthropic" <?= $provider==='anthropic'?'selected':'' ?>>Claude</option>
    </select>
  </div>
  <button type="submit">Üret</button>
</form>

<?php if ($result !== null): ?>
  <?php if (!empty($result['insufficient'])): ?>
    <div class="card">
      <b class="warn">Kaynak yetersiz.</b>
      <p class="muted">Bu kitap için Wikipedia/Google Books/Open Library'de yazılacak güvenilir bir açıklama bulunamadı.
      Gerçek sistemde bu durumda <b>kısa not / yer tutucu</b> konur (uydurma yazılmaz).</p>
      <p class="muted">Bulunan kaynaklar: <?= $result['sources'] ? h(implode(', ', $result['sources'])) : '—' ?></p>
    </div>
  <?php elseif (empty($result['ok'])): ?>
    <div class="card"><b class="warn">Üretilemedi:</b> <?= h($result['error']) ?></div>
  <?php else: ?>
    <div class="card">
      <div style="margin-bottom:10px">
        <span class="muted">Kaynaklar:</span>
        <?php foreach ($result['sources'] as $s): ?><span class="badge"><?= h($s) ?></span><?php endforeach; ?>
        <span class="badge"><?= (int) $result['words'] ?> kelime</span>
      </div>
      <div class="article"><?= $result['html'] ?></div>
    </div>
    <details>
      <summary class="muted">Ham kaynak dosyası (modele verilen — kontrol için)</summary>
      <pre><?= h($result['dossier']) ?></pre>
    </details>
  <?php endif; ?>
<?php endif; ?>

<p class="muted" style="margin-top:24px">İpucu: dene → Bureaucracy / Ludwig von Mises · Liberalism / Ludwig von Mises · The Ultimate Foundation of Economic Science / Ludwig von Mises</p>
</body></html>
