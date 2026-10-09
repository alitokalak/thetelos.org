<?php
/**
 * _nav.php — TÜM panel sayfaları için ORTAK, açılır-kapanır (accordion) sol menü.
 * Her sayfa bunu include eder → menü her yerde AYNI. Aktif grup otomatik açılır;
 * kullanıcının açtığı/kapattığı gruplar localStorage'da hatırlanır.
 */
$tls_cur     = basename($_SERVER['SCRIPT_NAME'] ?? '');
$tls_curmode = $_GET['mode'] ?? '';

/* Bir menü öğesi aktif mi? ($mode: panel.php alt sekmeleri için) */
if (!function_exists('tls_nav_active')) {
    function tls_nav_active($file, $mode, $cur, $curmode) {
        if ($mode !== null) {
            if ($cur !== 'panel.php') return false;
            $eff = in_array($curmode, ['queue', 'cleaner'], true) ? $curmode : 'single';
            return $mode === $eff;
        }
        return $cur === $file;
    }
}

/* Gruplar: her biri açılır-kapanır. item = [dosya, etiket, mode?] */
$tls_groups = [
    ['key' => 'icerik', 'ico' => '✍', 'label' => 'İçerik', 'items' => [
        ['panel.php', 'İçerik Üret', 'single'],
        ['panel.php', 'Kuyruk', 'queue'],
        ['panel.php', 'Liste Temizle', 'cleaner'],
        ['placeholders.php', 'Yer Tutucular', null],
        ['sources.php', 'Kaynak Arşivi', null],
        ['social.php', 'Sosyal', null],
        ['authors-bio.php', 'Yazar Bio', null],
    ]],
    ['key' => 'seo', 'ico' => '🔍', 'label' => 'SEO', 'items' => [
        ['seo.php', 'İçerik SEO', null],
        ['seo-site.php', 'Site SEO', null],
    ]],
    ['key' => 'denetim', 'ico' => '🩺', 'label' => 'Denetim', 'items' => [
        ['content-audit.php', 'İçerik Denetimi', null],
        ['content-guard.php', 'İçerik Koruma', null],
    ]],
    ['key' => 'kategori', 'ico' => '🗂️', 'label' => 'Kategoriler', 'items' => [
        ['category-organize.php', 'Organize', null],
        ['recategorize.php', 'Düzelt', null],
        ['category-cleanup.php', 'Temizle', null],
    ]],
    ['key' => 'arac', 'ico' => '🧰', 'label' => 'Araçlar', 'items' => [
        ['cover-backfill.php', 'Kapak Bul', null],
        ['amazon-match.php', 'Amazon', null],
    ]],
];
$WP = defined('WP_URL') ? rtrim(WP_URL, '/') : '';
?>
<aside class="tls-sidebar">
  <div class="tls-logo"><h1>Thetelos</h1><small>Content Panel</small></div>
  <nav class="tls-nav">
    <?php foreach ($tls_groups as $g):
        // Grupta aktif öğe var mı? (varsa grup açık + başlık vurgulu)
        $has = false;
        foreach ($g['items'] as $it) { if (tls_nav_active($it[0], $it[2], $tls_cur, $tls_curmode)) { $has = true; break; } }
        $cls = 'tls-grp' . ($has ? ' open has-active' : '');
    ?>
    <div class="<?= $cls ?>" data-key="<?= $g['key'] ?>">
      <button type="button" class="tls-grp-h">
        <span class="ico"><?= $g['ico'] ?></span>
        <span class="tls-grp-lbl"><?= $g['label'] ?></span>
        <span class="tls-grp-chev">▸</span>
      </button>
      <div class="tls-grp-items">
        <?php foreach ($g['items'] as $it):
            [$file, $label, $mode] = $it;
            $href = ($mode !== null) ? 'panel.php?mode=' . $mode : $file;
            $act  = tls_nav_active($file, $mode, $tls_cur, $tls_curmode) ? ' class="active"' : '';
        ?>
        <a href="<?= $href ?>"<?= $act ?>><?= $label ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="tls-nav-div"></div>
    <div class="tls-nav-flat">
      <a href="settings.php"<?= $tls_cur === 'settings.php' ? ' class="active"' : '' ?>><span class="ico">⚙</span> Ayarlar</a>
      <a href="<?= $WP ?>/wp-admin/" target="_blank" rel="noopener"><span class="ico">🔗</span> WP Admin</a>
      <a href="<?= $WP ?>/" target="_blank" rel="noopener"><span class="ico">↗</span> Siteyi Gör</a>
    </div>
  </nav>
  <div class="tls-sidebar-footer"><a href="index.php?logout=1">Çıkış Yap</a></div>
</aside>
<script>
(function(){
  if (window.__tlsNavInit) return; window.__tlsNavInit = 1;
  var KEY='tlsNavOpen', open={};
  try { open = JSON.parse(localStorage.getItem(KEY)||'{}') || {}; } catch(e){}
  document.querySelectorAll('.tls-grp').forEach(function(g){
    var k=g.getAttribute('data-key');
    // Aktif grup her zaman açık; değilse hatırlanan tercihe göre
    if (!g.classList.contains('has-active') && open[k]) g.classList.add('open');
    var h=g.querySelector('.tls-grp-h');
    if(h) h.addEventListener('click', function(){
      g.classList.toggle('open');
      open[k]=g.classList.contains('open');
      try { localStorage.setItem(KEY, JSON.stringify(open)); } catch(e){}
    });
  });
})();
</script>
