<?php
/**
 * cron-tick.php — Dışarıdan (cron-job.org gibi) tetiklenen batch ilerletici.
 *
 * NEDEN: Sunucu taraflı worker'lar kendilerini curl ile zincirliyor
 * (bw_spawn_successor). Site Cloudflare arkasında olduğundan bu "kendi
 * kendine istek" sık sık engellenip zincir kopuyor; tarayıcı sekmesi de
 * kapalıysa batch olduğu yerde donuyor.
 *
 * ÇÖZÜM: Dış bir cron servisi bu adresi dakikada bir çağırır. Bu betik,
 * yarım kalan (running + bekleyen kitabı olan) en eski batch'i bulur ve
 * batch-worker'ı AYNI İSTEK İÇİNDE (in-process) çalıştırır — yeni bir
 * Cloudflare self-call'a gerek kalmaz. Böylece tarayıcı kapalı olsa da,
 * Cloudflare ne yaparsa yapsın batch sonuna kadar ilerler.
 *
 * KURULUM: Panele giriş yapmış admin bu adresi tarayıcıda açarsa, cron
 * servisine yapıştıracağı tam URL (gizli anahtar dahil) ekrana yazılır.
 */

require_once dirname(__DIR__) . '/config.php';

// batch-worker'ın beklediği dahili token (in-process include için)
$worker_itok = hash('sha256', WP_APP_PASS . '|tls-batch-worker');
// cron URL'sinde kullanılan ayrı, tahmin edilemez anahtar
$cron_key    = hash('sha256', WP_APP_PASS . '|tls-cron-key');

$key    = (string)($_GET['key'] ?? '');
$is_cli = (PHP_SAPI === 'cli');   // cPanel cron'u "php cron-tick.php" ile çağırınca:
                                  // web isteği zaman aşımı YOK → kitaplar yarıda kesilmez.

/* ── Yetkisiz web isteği: giriş yapmış admin'e kurulum adresini göster ──
   CLI (sunucu cron'u) yerel ve güvenli sayılır; anahtar gerekmez. */
if (!$is_cli && !hash_equals($cron_key, $key)) {
    session_start();
    $is_admin = !empty($_SESSION['tls_auth']);
    session_write_close();

    if ($is_admin) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'thetelos.org';
        $path   = strtok($_SERVER['REQUEST_URI'] ?? '/thetelos-panel/api/cron-tick.php', '?');
        $url    = $scheme . '://' . $host . $path . '?key=' . $cron_key;
        header('Content-Type: text/html; charset=utf-8');
        echo '<div style="font-family:sans-serif;max-width:760px;margin:40px auto;line-height:1.6">';
        echo '<h2>Batch Cron — Kurulum Adresi</h2>';
        echo '<p>Aşağıdaki adresi <b>cron-job.org</b> (ücretsiz) üzerinde <b>dakikada bir</b> çağrılacak şekilde ekle. '
           . 'Bu kurulduktan sonra toplu üretim, tarayıcı kapalı olsa bile kendiliğinden sonuna kadar devam eder:</p>';
        echo '<p><code style="display:block;padding:14px;background:#111;color:#0f0;border-radius:6px;word-break:break-all;font-size:15px">'
           . htmlspecialchars($url) . '</code></p>';
        echo '<p style="color:#888">Bu anahtar yalnızca batch işlemeyi tetikler; veriye erişim vermez.</p>';

        // ── ÖNERİLEN: cPanel Cron Job (PHP CLI) — web zaman aşımına takılmaz ──
        echo '<hr style="margin:24px 0;border:none;border-top:1px solid #ddd">';
        echo '<h3>✅ Önerilen: cPanel Cron Job (en güvenilir)</h3>';
        echo '<p>cURL yöntemi web isteği zaman aşımına takılıp kitabı yarıda kesebiliyor. '
           . 'cPanel’in varsa <b>en sağlam yol</b> bu: <b>cPanel → Cron Jobs</b>, zamanlama '
           . '<b>Once Per Minute (* * * * *)</b>, komut olarak:</p>';
        echo '<p><code style="display:block;padding:14px;background:#111;color:#0f0;border-radius:6px;word-break:break-all;font-size:15px">'
           . '/usr/local/bin/php ' . htmlspecialchars(__FILE__) . '</code></p>';
        echo '<p style="color:#888">Hata verirse PHP yolunu cPanel <b>MultiPHP Manager</b>’daki '
           . 'sürümle değiştir (ör. <code>/opt/cpanel/ea-php82/root/usr/bin/php</code>). '
           . 'Mevcut cURL cron’unu silip bunu eklemen yeterli.</p>';

        // ── Teşhis: cron son ne zaman çalıştı + bekleyen iş var mı ──
        $jd   = dirname(__DIR__) . '/jobs';
        $beat = $jd . '/.cron-last';
        $last = file_exists($beat) ? (int) @file_get_contents($beat) : 0;
        $ago  = $last ? (time() - $last) : null;
        $pending_total = 0; $running_batches = 0;
        foreach (glob("$jd/*.json") ?: [] as $f) {
            $b = json_decode(@file_get_contents($f), true);
            if (!$b) continue;
            $st = $b['status'] ?? '';
            if ($st === 'paused' || $st === 'cancelled' || $st === 'done') continue;
            $p = 0;
            foreach ($b['books'] ?? [] as $bk) {
                $s = $bk['status'] ?? '';
                if ($s === 'pending' || ($s === 'processing' && empty($bk['post_id']))) $p++;
            }
            if ($p > 0) { $running_batches++; $pending_total += $p; }
        }
        echo '<hr style="margin:24px 0;border:none;border-top:1px solid #ddd">';
        echo '<h3>Teşhis</h3><ul>';
        echo '<li>Cron son çalışma: <b>' . ($ago === null ? 'HENÜZ HİÇ ÇALIŞMADI' : $ago . ' saniye önce') . '</b>'
           . ($ago !== null && $ago > 180 ? ' <span style="color:#c00">(⚠ 3 dk+ — cron çalışmıyor olabilir)</span>'
              : ($ago !== null ? ' <span style="color:#0a0">(✓ cron çalışıyor)</span>' : '')) . '</li>';
        echo '<li>İşlenecek batch: <b>' . $running_batches . '</b> — toplam bekleyen kitap: <b>' . $pending_total . '</b></li>';
        echo '</ul>';
        echo '<p style="color:#888">Bu sayfayı 1-2 dakika arayla yenile: "son çalışma" hep küçük bir sayı (ör. &lt;70sn) gösteriyorsa cron çalışıyor demektir.</p>';
        echo '</div>';
        exit;
    }

    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

/* ── Yetkili cron çağrısı ── */
$jobs_dir = dirname(__DIR__) . '/jobs';
if (!is_dir($jobs_dir)) @mkdir($jobs_dir, 0755, true);

// Teşhis: her yetkili çalışmada zaman damgası bırak (cron gerçekten geliyor mu?)
@file_put_contents($jobs_dir . '/.cron-last', time());

/* cron-job.org gibi dış servisler yanıtı ~30sn bekler; bir tik 8 yazarı
   OpenLibrary'den çekerken bunu aşabilir → servis "başarısız" sayıp job'u
   DEVRE DIŞI bırakabiliyor. Çözüm: yetkili web çağrısına HEMEN 200 dön,
   asıl işi bağlantı kapansa da arka planda sürdür (fastcgi_finish_request +
   ignore_user_abort). Böylece cron her zaman "başarılı" görünür. */
if (!$is_cli) {
    @ignore_user_abort(true);
    @set_time_limit(0);
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'msg' => 'tick started']);
    // Bağlantıyı HEMEN kapat ki dış cron (cron-job.org) 30sn'de timeout'a
    // düşüp job'u "başarısız/devre dışı" saymasın. Sunucu tipine göre:
    //   fastcgi_finish_request  → PHP-FPM
    //   litespeed_finish_request → LiteSpeed/LSAPI (bu sitenin sunucusu)
    // İş, .htaccess'teki noabort sayesinde bağlantı kapansa da arka planda sürer.
    if (function_exists('fastcgi_finish_request'))       { @fastcgi_finish_request(); }
    elseif (function_exists('litespeed_finish_request')) { @litespeed_finish_request(); }
    else { @ob_end_flush(); @flush(); }
}

/* ── ÖNCELİK 1: yarım kalan "building" kuyruğunu ilerlet ──
   queue-create ile oluşan kuyruklar yazarların eserlerini sunucuda parça
   parça çeker (status='building'). Tarayıcı/oturum kapalı olsa da cron bunu
   sürdürür → liste arka planda sonuna kadar oluşur. */
$build_target = null; $b_oldest = PHP_INT_MAX;
foreach (glob("$jobs_dir/*.json") ?: [] as $f) {
    $b = json_decode(@file_get_contents($f), true);
    if (!$b || ($b['status'] ?? '') !== 'building') continue;
    if ((int)($b['authors_built'] ?? 0) >= (int)($b['authors_total'] ?? 0)) continue;
    $ct = (int)($b['created_at'] ?? 0);
    if ($ct < $b_oldest) { $b_oldest = $ct; $build_target = $b['id'] ?? basename($f, '.json'); }
}
if ($build_target) {
    $_POST['batch_id'] = $build_target;
    // Her tik 30sn ALTINDA bitmeli: cron-job.org 30sn'de kesip "timeout/başarısız"
    // sayıyor (iş sunucuda bitse de). 4 yazar ≈ 15-20sn → cron yeşil görür,
    // çok-başarısızlıktan job'u devre dışı bırakmaz.
    $_POST['chunk']    = 4;
    $_POST['_itok']    = $worker_itok;
    include __DIR__ . '/queue-build.php';
    exit;
}

/* ── DeepSeek YOĞUN-SAAT KORUMASI ──────────────────────────────────
   DeepSeek yoğun saatlerde (UTC 01:00–04:00 ve 06:00–10:00 → TR 04–07 ve
   09–13) tüm kalemleri 2× fiyatlandırıyor. İçerik üretimi ücretli DeepSeek
   çağrısı yaptığı için bu saatlerde ATLANIR; liste oluşturma (ücretsiz,
   yukarıda ÖNCELİK 1) etkilenmez → kuyruk boşalmaya devam eder ama pahalı
   üretim normal-fiyat saatlere kayar. Aç/kapa PANELDEN yapılır
   (jobs/.peak-skip bayrağı; dosya yoksa varsayılan AÇIK). Zorla üretmek için
   cron URL'ine &peak=off ekle. config.php'de define('TLS_DEEPSEEK_SKIP_PEAK', …)
   tanımlıysa o her şeyi ezer. */
$peak_flag = $jobs_dir . '/.peak-skip';
$peak_skip = !file_exists($peak_flag) || trim((string) @file_get_contents($peak_flag)) !== '0';
if (defined('TLS_DEEPSEEK_SKIP_PEAK')) $peak_skip = (bool) TLS_DEEPSEEK_SKIP_PEAK;
if ($peak_skip && (($_GET['peak'] ?? '') !== 'off')) {
    $h = (int) gmdate('G'); // UTC saati 0–23
    // HAFTA SONU (Cmt/Paz, Pekin/UTC+8) tüm gün off-peak → duraklatma yok.
    $bj_dow = (int) gmdate('N', time() + 8 * 3600);
    if ($bj_dow < 6 && (($h >= 1 && $h < 4) || ($h >= 6 && $h < 10))) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'msg' => 'skipped: DeepSeek peak hour (2x price)', 'utc_hour' => $h]);
        exit;
    }
}

/* ── ÖNCELİK 2: içerik üretimi ──
   Yarım kalan en eski batch'i bul. Sadece 'paused'/'cancelled' atlanır;
   'running' ya da (ölü worker yüzünden) belirsiz durumda kalmış ama hâlâ
   bekleyen kitabı olan batch'ler ele alınır — böylece donmuş batch'ler de
   cron tarafından kurtarılır. */
$target = null;
$newest = -1;

if (is_dir($jobs_dir)) {
    foreach (glob("$jobs_dir/*.json") ?: [] as $f) {
        $b = json_decode(@file_get_contents($f), true);
        if (!$b) continue;
        $st = $b['status'] ?? '';
        // İçerik üretimi YALNIZ gerçek 'running' batch'lerde. 'building'/'list_ready'
        // ve list_only (yalnız-liste) kuyruklar burada işlenmez → istenmeyen post üretilmez.
        if ($st !== 'running' || !empty($b['list_only'])) continue;

        // Bekleyen ya da (ölü worker'dan kalma) işlenmekte takılı kitap var mı?
        $has_work = false;
        foreach ($b['books'] ?? [] as $bk) {
            $s = $bk['status'] ?? '';
            if ($s === 'pending' || ($s === 'processing' && empty($bk['post_id']))) { $has_work = true; break; }
        }
        if (!$has_work) continue;

        // EN YENİ batch öncelikli: kullanıcının son başlattığı iş asıl istenen iştir.
        // (Eskiden en eski seçiliyordu; unutulmuş eski bir kopya tüm cron tiklerini
        // çalıp yeni batch'i "tarayıcı kapalıyken durdu" hissine sokuyordu.)
        $ct = (int)($b['created_at'] ?? 0);
        if ($ct > $newest) { $newest = $ct; $target = $b['id'] ?? basename($f, '.json'); }
    }
}

if (!$target) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'msg' => 'no running batch with pending work']);
    exit;
}

/* ── ÇAKIŞMA KİLİDİ ──────────────────────────────────────────────────
   Bu batch'te hâlâ CANLI bir worker varsa ikincisini BAŞLATMA. İki worker
   aynı listede aynı anda çalışınca, "bu kitap zaten var mı?" kontrolü ikisinde
   de "yok" görüp aynı kitabı iki kez yayınlıyordu (duplicate). batch-worker
   her chunk'ta {batch}.wk.{id} heartbeat dosyasını tazeler; 90sn içinde
   dokunulmuş bir dosya varsa bir worker aktif demektir → yeni worker açma.
   Worker ölürse dosyası bayatlar (90sn+) ve sonraki tik normal devam eder. */
foreach (glob("$jobs_dir/{$target}.wk.*") ?: [] as $wk) {
    if (time() - (int) @filemtime($wk) < 90) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'msg' => 'worker already active', 'batch' => $target]);
        exit;
    }
}

// batch-worker'ı bu istek içinde çalıştır (Cloudflare self-call YOK).
// ignore_user_abort sayesinde cron servisi 30sn'de bağlantıyı kesse bile
// drain döngüsü sunucuda 70sn boyunca çalışmaya devam eder.
$_POST['batch_id'] = $target;
$_POST['_itok']    = $worker_itok;
include __DIR__ . '/batch-worker.php';
