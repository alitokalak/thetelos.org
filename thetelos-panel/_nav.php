<?php
/**
 * _nav.php — TÜM panel sayfaları için ORTAK sol menü.
 * Her sayfa kendi kopyası yerine bunu include eder → menü her yerde AYNI.
 * Aktif sayfa otomatik işaretlenir (script adı + panel.php için ?mode).
 */
if (!function_exists('tls_navlink')) {
    function tls_navlink($file, $ico, $label, $mode = null, $ext = false) {
        $cur     = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $curmode = $_GET['mode'] ?? '';
        $active  = '';
        $target  = '';
        if ($ext) {
            $href = $file; $target = ' target="_blank" rel="noopener"';
        } elseif ($mode !== null) {                       // panel.php alt sekmeleri
            $href = 'panel.php?mode=' . $mode;
            if ($cur === 'panel.php') {
                $effective = in_array($curmode, ['queue', 'cleaner'], true) ? $curmode : 'single';
                if ($mode === $effective) $active = ' class="active"';
            }
        } else {
            $href = $file;
            if ($cur === $file) $active = ' class="active"';
        }
        echo '<a href="' . $href . '"' . $active . $target . '><span class="ico">' . $ico . '</span> ' . $label . '</a>';
    }
}
$WP = defined('WP_URL') ? rtrim(WP_URL, '/') : '';
?>
<aside class="tls-sidebar">
  <div class="tls-logo"><h1>Thetelos</h1><small>Content Panel</small></div>
  <nav class="tls-nav">

    <div class="tls-nav-sec">İçerik</div>
    <?php tls_navlink('panel.php', '✍', 'İçerik Üret', 'single'); ?>
    <?php tls_navlink('panel.php', '📋', 'Kuyruk', 'queue'); ?>
    <?php tls_navlink('panel.php', '🧹', 'Liste Temizle', 'cleaner'); ?>
    <?php tls_navlink('placeholders.php', '⏳', 'Yer Tutucular'); ?>
    <?php tls_navlink('sources.php', '📚', 'Kaynak Arşivi'); ?>
    <?php tls_navlink('social.php', '📣', 'Sosyal'); ?>
    <?php tls_navlink('authors-bio.php', '👤', 'Yazar Bio'); ?>

    <div class="tls-nav-sec">SEO</div>
    <?php tls_navlink('seo.php', '🔍', 'İçerik SEO'); ?>
    <?php tls_navlink('seo-site.php', '🌐', 'Site SEO'); ?>

    <div class="tls-nav-sec">Denetim</div>
    <?php tls_navlink('content-audit.php', '🩺', 'İçerik Denetimi'); ?>
    <?php tls_navlink('content-guard.php', '🛡️', 'İçerik Koruma'); ?>

    <div class="tls-nav-sec">Kategoriler</div>
    <?php tls_navlink('category-organize.php', '🗄️', 'Organize'); ?>
    <?php tls_navlink('recategorize.php', '🗂️', 'Düzelt'); ?>
    <?php tls_navlink('category-cleanup.php', '🧽', 'Temizle'); ?>

    <div class="tls-nav-sec">Araçlar</div>
    <?php tls_navlink('cover-backfill.php', '🖼', 'Kapak Bul'); ?>
    <?php tls_navlink('amazon-match.php', '🛒', 'Amazon'); ?>

    <div class="tls-nav-sec">Sistem</div>
    <?php tls_navlink('settings.php', '⚙', 'Ayarlar'); ?>
    <?php tls_navlink($WP . '/wp-admin/', '🔗', 'WP Admin', null, true); ?>
    <?php tls_navlink($WP . '/', '↗', 'Siteyi Gör', null, true); ?>

  </nav>
  <div class="tls-sidebar-footer"><a href="index.php?logout=1">Çıkış Yap</a></div>
</aside>
