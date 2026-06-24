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
        echo '</div>';
        exit;
    }

    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

/* ── Yetkili cron çağrısı: yarım kalan en eski batch'i ilerlet ── */
$jobs_dir = dirname(__DIR__) . '/jobs';
$target   = null;
$oldest   = PHP_INT_MAX;

if (is_dir($jobs_dir)) {
    foreach (glob("$jobs_dir/*.json") ?: [] as $f) {
        $b = json_decode(@file_get_contents($f), true);
        if (!$b || ($b['status'] ?? '') !== 'running') continue;

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
