<?php
/**
 * _version.php — Sunucuda ÇALIŞAN sürümü söyler.
 *
 * NEDEN VAR: FTP deploy'u ara sıra zaman aşımına uğruyor ve sessizce
 * düşüyordu. Sonuç: panelde eski kod çalışmaya devam ediyor, aynı hata tekrar
 * görülüyor, "düzelttim" denen şey düzelmemiş görünüyordu. "Deploy oldu mu?"
 * sorusu ancak tahminle cevaplanabiliyordu — ve tahmin yanlış olduğunda
 * günler kaybediliyordu.
 *
 * .version dosyasını deploy iş akışı yazar (commit kısa SHA + zaman + başlık).
 * Panelin başlığında görünür: ekrandaki sürüm ile beklenen sürüm aynı değilse
 * sorun kodda değil, deploy'dadır.
 */

/** ['sha'=>..,'at'=>..,'subject'=>..] ya da dosya yoksa null. */
function tls_version() {
    static $v = null;
    if ($v !== null) return $v ?: null;
    $f = __DIR__ . '/.version';
    if (!file_exists($f)) { $v = false; return null; }
    $l = array_map('trim', explode("\n", (string) file_get_contents($f)));
    $v = [
        'sha'     => $l[0] ?? '',
        'at'      => $l[1] ?? '',
        'subject' => $l[2] ?? '',
    ];
    return $v;
}

/** Başlıklarda gösterilecek kısa damga. */
function tls_version_badge() {
    $v = tls_version();
    if (!$v || $v['sha'] === '') {
        return '<span style="opacity:.55;font-size:11px" title="Sürüm dosyası yok — deploy bu sürümü yazmamış olabilir">· sürüm bilinmiyor</span>';
    }
    return '<span style="opacity:.55;font-size:11px" title="' . htmlspecialchars($v['subject']) . '">'
         . '· sürüm <code>' . htmlspecialchars($v['sha']) . '</code> · ' . htmlspecialchars($v['at']) . '</span>';
}
