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

$key = (string)($_GET['key'] ?? '');

/* ── Yetkisiz: giriş yapmış admin'e kurulum adresini göster ── */
if (!hash_equals($cron_key, $key)) {
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

/* Yarım kalan en eski batch'i bul. Sadece 'paused'/'cancelled' atlanır;
   'running' ya da (ölü worker yüzünden) belirsiz durumda kalmış ama hâlâ
   bekleyen kitabı olan batch'ler ele alınır — böylece donmuş batch'ler de
   cron tarafından kurtarılır. */
$target = null;
$oldest = PHP_INT_MAX;

if (is_dir($jobs_dir)) {
    foreach (glob("$jobs_dir/*.json") ?: [] as $f) {
        $b = json_decode(@file_get_contents($f), true);
        if (!$b) continue;
        $st = $b['status'] ?? '';
        if ($st === 'paused' || $st === 'cancelled' || $st === 'done') continue;

        // Bekleyen ya da (ölü worker'dan kalma) işlenmekte takılı kitap var mı?
        $has_work = false;
        foreach ($b['books'] ?? [] as $bk) {
            $s = $bk['status'] ?? '';
            if ($s === 'pending' || ($s === 'processing' && empty($bk['post_id']))) { $has_work = true; break; }
        }
        if (!$has_work) continue;

        $ct = (int)($b['created_at'] ?? 0);
        if ($ct < $oldest) { $oldest = $ct; $target = $b['id'] ?? basename($f, '.json'); }
    }
}

if (!$target) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'msg' => 'no running batch with pending work']);
    exit;
}

// batch-worker'ı bu istek içinde çalıştır (Cloudflare self-call YOK).
// ignore_user_abort sayesinde cron servisi 30sn'de bağlantıyı kesse bile
// drain döngüsü sunucuda 70sn boyunca çalışmaya devam eder.
$_POST['batch_id'] = $target;
$_POST['_itok']    = $worker_itok;
include __DIR__ . '/batch-worker.php';
