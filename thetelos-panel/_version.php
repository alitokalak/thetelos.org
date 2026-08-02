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
 * İKİ KAYNAK, ÇÜNKÜ BİRİNCİSİ GARANTİ DEĞİL:
 *
 *   1. version.txt — deploy iş akışının yazdığı commit SHA + zaman. Kesin
 *      bilgidir ama dosyanın sunucuya ulaşmasına bağlıdır. (İlk sürümü
 *      ".version" adıyla yazıyordu; noktayla başlayan dosyalar FTP senkron
 *      araçlarında ve exclude kalıplarında sık sık atlanıyor, bu yüzden
 *      ekranda hiçbir şey görünmedi.)
 *
 *   2. DOSYA ZAMANI — bu dosyanın sunucudaki değiştirilme tarihi. Hiçbir şeye
 *      bağlı değildir: deploy dosyayı yüklediyse zaman değişir, yüklemediyse
 *      değişmez. version.txt hiç gelmese bile "sunucudaki kod ne zamandan
 *      kalma" sorusu bununla cevaplanır.
 */

/** ['sha'=>..,'at'=>..,'subject'=>..] ya da dosya yoksa null. */
function tls_version() {
    static $v = null;
    if ($v !== null) return $v ?: null;
    foreach (['/version.txt', '/.version'] as $name) {
        $f = __DIR__ . $name;
        if (!file_exists($f)) continue;
        $l = array_map('trim', explode("\n", (string) file_get_contents($f)));
        if (($l[0] ?? '') === '') continue;
        $v = ['sha' => $l[0], 'at' => $l[1] ?? '', 'subject' => $l[2] ?? ''];
        return $v;
    }
    $v = false;
    return null;
}

/**
 * Sunucudaki panel kodunun en son ne zaman değiştiği.
 *
 * En yeni mtime alınır: deploy yalnızca değişen dosyaları yüklediği için tek
 * bir dosyaya bakmak yanıltır.
 */
function tls_code_mtime() {
    $newest = 0;
    foreach (['/_version.php', '/content-audit.php', '/panel.php', '/assets/app.js', '/api/generate.php'] as $f) {
        $t = @filemtime(__DIR__ . $f);
        if ($t && $t > $newest) $newest = $t;
    }
    return $newest;
}

/** Başlıklarda gösterilecek damga. Her koşulda BİR ŞEY gösterir. */
function tls_version_badge() {
    $v  = tls_version();
    $mt = tls_code_mtime();
    $ts = $mt ? date('d.m.Y H:i', $mt) : '';

    if ($v && $v['sha'] !== '') {
        return '<span style="opacity:.6;font-size:11px" title="' . htmlspecialchars($v['subject']) . '">'
             . '· sürüm <code>' . htmlspecialchars($v['sha']) . '</code>'
             . ($ts ? ' · sunucudaki kod: ' . $ts : '') . '</span>';
    }
    // version.txt gelmemiş: dosya zamanı yine de doğruyu söyler.
    return '<span style="opacity:.6;font-size:11px" title="Sürüm dosyası sunucuya ulaşmamış; gösterilen, panel kodunun sunucudaki son değişiklik zamanı">'
         . '· sunucudaki kod: ' . ($ts ?: 'bilinmiyor') . '</span>';
}
