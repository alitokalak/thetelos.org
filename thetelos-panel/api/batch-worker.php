<?php
/**
 * batch-worker.php — Sunucu tarafı "drain" worker.
 *
 * Tarayıcı bu isteği ATEŞLER ve BEKLEMEZ (fire-and-forget). Bu betik,
 * ignore_user_abort sayesinde Cloudflare bağlantıyı ~100sn'de kesse bile
 * arka planda çalışmaya devam eder ve kuyruktaki TÜM bekleyen kitapları
 * sırayla işler. Tarayıcı yalnızca batch-status.php ile durumu sorgular.
 *
 * Eskiden tek istek hem kitabı alıp hem üretiyordu; fastcgi_finish_request()
 * yalnız PHP-FPM'de bulunduğundan mod_php/CGI sunucularda bağlantı kapanmıyor,
 * istek uzun sürüyor ve Cloudflare "bağlantı hatası" veriyordu. Drain modeli
 * bu sorunu tüm sunucu tiplerinde çözer.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/_content-format.php';   // bw_clean_content / bw_md2html

/* Yetki: ya panel session'ı ya da worker'ın kendini zincirlerken kullandığı
   dahili token. Self-spawn isteklerinde tarayıcı çerezi/session bulunmadığından
   config tabanlı bir token ile doğrularız. */
$internal_token = hash('sha256', WP_APP_PASS . '|tls-batch-worker');
$is_internal    = isset($_POST['_itok']) && hash_equals($internal_token, (string)$_POST['_itok']);
if (empty($_SESSION['tls_auth']) && !$is_internal) { http_response_code(401); exit; }
session_write_close();   // KRİTİK: session kilidini hemen bırak — yoksa uzun süren
                         // drain döngüsü boyunca batch-status.php gibi istekler bloke olur.

ignore_user_abort(true);
@ini_set('max_execution_time', '0');
set_time_limit(0);
header('Content-Type: application/json');

$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_POST['batch_id'] ?? ''));
if (!$batch_id) { echo json_encode(['status'=>'error','error'=>'batch_id gerekli']); exit; }

$jobs_dir   = dirname(__DIR__) . '/jobs';
$batch_file = "$jobs_dir/{$batch_id}.json";
if (!file_exists($batch_file)) { echo json_encode(['status'=>'no_more']); exit; }

// Per-worker alive dosyası: batch JSON'un yanına .wk.{id} oluştur.
// server-status.php bu dosyaları sayarak canlı worker sayısını öğrenir.
// Zincirleme sırasında eski worker'ın dosyası hâlâ taze olduğundan
// proc sayısı düşmez ve gereksiz worker açılmaz.
$_wk_id  = uniqid('', true);
$g_worker_hb = preg_replace('/\.json$/', '', $batch_file) . ".wk.$_wk_id";
@touch($g_worker_hb);

$auth   = 'Basic ' . base64_encode(WP_USER . ':' . WP_APP_PASS);
$wp_api = rtrim(WP_URL, '/') . '/wp-json/wp/v2';

/* ── UCUZ HEARTBEAT ────────────────────────────────────────────────
 * İki katmanlı canlılık sistemi:
 *
 * 1) PER-WORKER dosyası (.wk.{id}): worker başladığında oluşturulur,
 *    her chunk'ta yenilenir, zincirlenince silinmez (180 sn sonra
 *    kendiliğinden bayatlar). server-status.php bu dosyaları sayarak kaç
 *    worker'ın canlı olduğunu öğrenir. Zincirleme sırasında (eski worker
 *    çıkıyor, yeni worker başlıyor) ÖRTÜŞME olduğu için proc sayısı
 *    düşmez → checkActiveJobs gereksiz worker açmaz.
 *
 * 2) PER-KITAP dosyası (.hb.{idx}): hangi kitabın işlendiğini ve o
 *    kitabın worker'ının ölü olup olmadığını izler. bw_claim_next
 *    kurtarma ve UI zamanlayıcısı için kullanılır.
 *
 * Eşik: 180 sn — üretim streaming olduğu için heartbeat sürekli tazelenir. */

function bw_hb_path($batch_file, $idx) {
    return preg_replace('/\.json$/', '', $batch_file) . ".hb.$idx";
}
function bw_touch_hb($batch_file, $idx) {
    global $g_worker_hb;
    @touch(bw_hb_path($batch_file, $idx));
    if ($g_worker_hb) @touch($g_worker_hb);   // worker'ı da canlı tut
}
function bw_stage_path($batch_file, $idx) {
    return preg_replace('/\.json$/', '', $batch_file) . ".stage.$idx";
}
function bw_set_stage($batch_file, $idx, $msg) {
    @file_put_contents(bw_stage_path($batch_file, $idx), (string) $msg);
}
function bw_clear_hb($batch_file, $idx) {
    @unlink(bw_hb_path($batch_file, $idx));
    @unlink(bw_stage_path($batch_file, $idx));
}
/* Bir "processing" kitabın worker'ı ölmüş mü? hb dosyası yoksa ya da
   mtime'ı eşikten eskiyse ölü say. Eşik: 180sn. Üretim artık streaming
   olduğundan worker her birkaç saniyede heartbeat'i tazeler; bu yüzden eşik
   güvenle düşürülebilir → takılan kitap ~3 dk yerine ~180sn'de kurtarılır. */
function bw_hb_dead($batch_file, $idx, $thr = 180) {
    $p = bw_hb_path($batch_file, $idx);
    if (!file_exists($p)) return true;
    return (time() - filemtime($p)) > $thr;
}

/* ── Sıradaki bekleyen kitabı atomik klaym et ─────────────────────
 * Dönüş: [idx, batch]  | [-1,null]=bitti  [-2,null]=kilitli(tekrar dene)
 *        [-3,null]=iptal  [-4,null]=duraklatıldı
 *
 * STALE RECOVERY: Kilit içinde çalışır — ölen worker'ların "processing" bıraktığı
 * kitapları (hb dosyası bayatlamış) otomatik "pending"e döndürür.
 */
/* ATOMİK YAZ: geçici dosyaya yaz + rename. Yarıda kesilse bile canlı dosya
 * ASLA 0 byte/bozuk kalmaz (eski ftruncate+fwrite deseni tam bunu yapabiliyordu
 * ve kuyruk dosyasını bir kez böyle kaybettik). Kilit ayrı .lock dosyasında
 * tutulur (rename ile inode değiştiği için kilidi ana dosyada tutmak yanlış). */
function bw_write_atomic($batch_file, $batch) {
    $json = json_encode($batch, JSON_UNESCAPED_UNICODE);
    if ($json === false || $json === '') return false;
    $tmp = $batch_file . '.tmp.' . getmypid() . '.' . mt_rand();
    if (@file_put_contents($tmp, $json) !== strlen($json)) { @unlink($tmp); return false; }
    return @rename($tmp, $batch_file);
}
function bw_lock($batch_file) {
    $lk = @fopen($batch_file . '.lock', 'c');
    if (!$lk) return null;
    if (!flock($lk, LOCK_EX)) { fclose($lk); return null; }
    return $lk;
}
function bw_unlock($lk) { if ($lk) { flock($lk, LOCK_UN); fclose($lk); } }

function bw_claim_next($batch_file) {
    $lk = bw_lock($batch_file);
    if (!$lk) return [-2, null];

    $batch = json_decode((string)@file_get_contents($batch_file), true);
    if (!$batch) { bw_unlock($lk); return [-1, null]; }

    $st = $batch['status'] ?? '';
    if ($st === 'cancelled') { bw_unlock($lk); return [-3, null]; }
    if ($st === 'paused')    { bw_unlock($lk); return [-4, null]; }

    // Bayat (ölü worker'dan kalan) processing kitapları kurtar.
    // Canlılık ucuz heartbeat dosyasından okunur (hb mtime). Worker canlıysa
    // her parçadan önce dosyaya dokunur; ölmüşse dosya bayatlar → kitap pending'e
    // döner. 180 sn eşik streaming heartbeat ile her parça sayısı için doğru çalışır.
    $changed = false;
    foreach ($batch['books'] as $i => $b) {
        if (($b['status'] ?? '') !== 'processing') continue;
        if (!empty($b['post_id'])) {
            // WP post oluşturulmuş ama status güncellenmemiş → done say
            $batch['books'][$i]['status'] = 'done';
            bw_clear_hb($batch_file, $i);
            $changed = true;
        } elseif (bw_hb_dead($batch_file, $i)) {
            $batch['books'][$i]['status'] = 'pending';
            bw_clear_hb($batch_file, $i);
            $changed = true;
        }
    }

    $idx = -1;
    foreach ($batch['books'] as $i => $b) {
        if (($b['status'] ?? '') === 'pending') { $idx = $i; break; }
    }
    if ($idx === -1) {
        // Hâlâ "processing" olan kitap varsa done yazma — başka worker çalışıyor olabilir
        $has_processing = false;
        foreach ($batch['books'] as $b) {
            if (($b['status'] ?? '') === 'processing') { $has_processing = true; break; }
        }
        if (!$has_processing && $st !== 'done') {
            $batch['status'] = 'done';
            $changed = true;
        }
        if ($changed) bw_write_atomic($batch_file, $batch);
        bw_unlock($lk);
        return [-1, null];
    }

    $batch['books'][$idx]['status'] = 'processing';
    $batch['books'][$idx]['processing_since'] = time();
    bw_write_atomic($batch_file, $batch);
    bw_unlock($lk);
    bw_touch_hb($batch_file, $idx);   // canlılık damgası — anında "ölü" sanılmasın
    return [$idx, $batch];
}

/* ── Halef worker ateşle (fire-and-forget) ─────────────────────────
 * Bu süreç ölmeden önce kendi yerine yeni bir worker başlatır; böylece
 * sunucu uzun süren süreçleri öldürse bile batch sunucu tarafında
 * kendiliğinden devam eder. Tarayıcı açık olmasa bile çalışır. */
function bw_spawn_successor($batch_id) {
    $token  = hash('sha256', WP_APP_PASS . '|tls-batch-worker');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = strtok($_SERVER['REQUEST_URI'] ?? '/thetelos-panel/api/batch-worker.php', '?');
    $url    = $scheme . '://' . $host . $path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_POSTFIELDS      => http_build_query(['batch_id' => $batch_id, '_itok' => $token]),
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_CONNECTTIMEOUT  => 5,
        CURLOPT_TIMEOUT         => 5,    // sadece isteği gönder; yeni worker arka planda sürer
        CURLOPT_NOSIGNAL        => 1,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_SSL_VERIFYHOST  => 0,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ── WP REST yardımcısı ────────────────────────────────────────────
/* WP REST çağrısı. Bağlantı kopması (HTTP 0 = yanıt hiç gelmedi) ya da
   geçici sunucu hatası (502/503/504) GEÇİCİdir → 3 kez denenir, aralarda
   kısa bekleme. Toplu üretimdeki "WP HTTP 0" hatalarının başlıca sebebi
   buydu: kitap üretiliyor ama yayınlanırken bağlantı düşünce boşa gidiyordu. */
function bw_wp($url, $method, $body, $auth, $timeout = 30) {
    $r = null; $c = 0;
    for ($try = 1; $try <= 3; $try++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: ' . $auth],
        ]);
        if ($method !== 'GET') curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        $r = curl_exec($ch);
        $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Başarılı ya da kalıcı hata (4xx) → tekrar deneme, olduğu gibi dön.
        if ($c !== 0 && !in_array($c, [502, 503, 504], true)) break;
        if ($try < 3) sleep(2);
    }
    return [json_decode($r, true), $c];
}

function bw_update_book($batch_file, $idx, $updates) {
    $lk = bw_lock($batch_file);
    if (!$lk) return;
    $batch = json_decode((string)@file_get_contents($batch_file), true);
    if ($batch) {
        foreach ($updates as $k => $v) $batch['books'][$idx][$k] = $v;
        // Terminal duruma (done/error) geçişte bayrakları NORMALİZE et: güncelleme
        // açıkça 'kept'/'placeholder' vermiyorsa eski turdan kalanı SIFIRLA. Yoksa
        // önce kapıdan geçemeyip 'kept' işaretlenen bir kitap, sonra gerçekten
        // yeniden yazılıp yeşile döndüğünde bayat 'kept=1' üstünde kalıp panelde
        // yanlış (amber) görünüyordu. Böylece renk HER ZAMAN son gerçeği yansıtır.
        if (isset($updates['status']) && in_array($updates['status'], ['done', 'error'], true)) {
            if (!array_key_exists('placeholder', $updates)) $batch['books'][$idx]['placeholder'] = 0;
            if (!array_key_exists('kept', $updates))        $batch['books'][$idx]['kept'] = 0;
        }
        // Yer tutucu KONDUYSA (içerik değişti) arşiv kaydını güncelle → eski
        // (Wikisource) kayıt "şüpheli" listesinde bayat kalmasın. Merkezi nokta:
        // tüm yer-tutucu yolları buradan geçer.
        if (!empty($updates['placeholder']) && !empty($batch['books'][$idx]['post_id'])) {
            bw_source_mark((int) $batch['books'][$idx]['post_id'],
                (string) ($batch['books'][$idx]['book_title'] ?? ''),
                (string) ($batch['books'][$idx]['author_name'] ?? ''), 'yer-tutucu');
        }
        $done = $ok = $failed = $placeholder = $kept = 0;
        foreach ($batch['books'] as $b) {
            if (in_array($b['status'], ['done','error'])) $done++;
            if ($b['status'] === 'done' && !empty($b['placeholder'])) $placeholder++;   // yayında ama içerik yok
            elseif ($b['status'] === 'done' && !empty($b['kept'])) $kept++;             // yeni içerik yazılmadı, eski korundu
            elseif ($b['status'] === 'done')  $ok++;                                     // taze iyi içerik yazıldı
            if ($b['status'] === 'error') $failed++;
        }
        $batch['done']        = $done;
        $batch['ok']          = $ok;
        $batch['placeholder'] = $placeholder;
        $batch['kept']        = $kept;
        $batch['failed']      = $failed;
        $batch['last_activity'] = time();   // görünürlük: "en son ne zaman ilerledi"
        if ($done >= $batch['total'] && ($batch['status'] ?? '') !== 'cancelled') $batch['status'] = 'done';
        bw_write_atomic($batch_file, $batch);
    }
    bw_unlock($lk);
    // Kitap tamamlandıysa/hata aldıysa heartbeat dosyasını temizle.
    if (isset($updates['status']) && in_array($updates['status'], ['done','error'], true)) {
        bw_clear_hb($batch_file, $idx);
    }
}

/* ── SORUNLU ESER GÜNLÜĞÜ ───────────────────────────────────────────────
   Yazılamayan / hata alan / yayından kaldırılan / kapıdan geçemeyen her eseri
   KALICI bir loga yazar. Amaç: kullanıcı bu listeyi sonradan indirip (aynı CSV
   formatında) FARKLI bir modelle yeniden yazdırabilsin. Dosya jobs/ altında
   olduğundan FTP deploy'u ASLA üzerine yazmaz (deploy jobs/** hariç tutar) →
   sürümler arası kaybolmaz. JSONL: her satır bir olay; okuma tarafı
   normalize-anahtarla tekilleştirir (aynı eserin son durumu geçerli sayılır). */
function bw_flag_problem($book, $author, $cover, $year, $reason, $detail = '', $pid = 0, $mode = 'rewrite') {
    $file = dirname(__DIR__) . '/jobs/rewrite-problems.jsonl';
    $rec  = [
        't'      => time(),
        'book'   => (string) $book,
        'author' => (string) $author,
        'cover'  => (string) $cover,
        'year'   => (string) $year,
        'reason' => (string) $reason,          // kod: not_found / unpublished / gate_draft / wp_error / gen_error / refused
        'detail' => mb_substr((string) $detail, 0, 200),
        'pid'    => (int) $pid,
        'mode'   => (string) $mode,            // rewrite | create
    ];
    @file_put_contents($file, json_encode($rec, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

/* ── KAYNAK ARŞİVİ ───────────────────────────────────────────────────────
   Kaynak-temelli özet YAZILDIĞINDA, kitabın (thetelos postu) hangi kaynaktan/
   hangi LİNKTEN yazıldığını kalıcı kaydeder. İki yere:
   (1) POST META (_tls_source_url / _tls_source_name) → ileride tema "Download
       source" butonunu OTOMATİK ekleyebilsin (veri postta durur);
   (2) merkezi indeks (jobs/source-index.jsonl) → panelde liste + CSV/JSON.
   URL'siz (manuel yapıştırılan) metinlerde ham metin jobs/sources/{pid}.txt'e
   kaydedilir, url alanı 'local:sources/{pid}.txt' olur (kaybolmasın). */
function bw_source_archive($post_id, $book, $author, $name, $url, $chars, $wp_api, $ep, $auth, $manual_text = '') {
    $post_id = (int) $post_id;
    // URL'siz manuel metin → server'da sakla (kalıcı; deploy jobs/'u silmez).
    if ($url === '' && $manual_text !== '' && $post_id > 0) {
        $dir = dirname(__DIR__) . '/jobs/sources';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents("$dir/{$post_id}.txt", $manual_text);
        $url = 'local:sources/' . $post_id . '.txt';
    }
    if ($url === '') return;   // kaydedilecek link yok
    // (1) Post meta — tema ileride doğrudan okur.
    if ($post_id > 0) {
        @bw_wp("$wp_api/$ep/$post_id", 'POST', ['meta' => ['_tls_source_url' => $url, '_tls_source_name' => (string) $name]], $auth, 30);
    }
    // (2) Merkezi indeks (kitap ↔ post ↔ kaynak).
    $rec = ['t' => time(), 'pid' => $post_id, 'book' => (string) $book, 'author' => (string) $author,
            'source' => (string) $name, 'url' => (string) $url, 'chars' => (int) $chars];
    @file_put_contents(dirname(__DIR__) . '/jobs/source-index.jsonl', json_encode($rec, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

/* ── KAYNAK KAYDINI GÜNCELLE (link'siz sonuçlar) ────────────────────────────
   Bir yazı Wikisource kaydıyla arşivdeyken YENİDEN yazılıp içeriği DEĞİŞTİYSE
   (bilgi metni / Claude / yer tutucu) ama yeni gerçek kaynak linki yoksa,
   arşivdeki ESKİ (Wikisource) kayıt bayat kalıyor ve "şüpheli" listesinde
   yanlış görünüyordu. Bu, post başına SON kaydı non-Wikisource yapar → düzelen
   yazı şüpheli listesinden düşer. (İçerik DEĞİŞMEYEN 'eski korundu'da çağrılmaz:
   orada eski Wikisource içeriği hâlâ yayında → haklı olarak şüpheli kalır.) */
function bw_source_mark($post_id, $book, $author, $source) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) return;
    $rec = ['t' => time(), 'pid' => $post_id, 'book' => (string) $book, 'author' => (string) $author,
            'source' => (string) $source, 'url' => '', 'chars' => 0];
    @file_put_contents(dirname(__DIR__) . '/jobs/source-index.jsonl', json_encode($rec, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

/* ── YER-TUTUCU GÖVDE ────────────────────────────────────────────────────
   Model eseri güvenilir tanımıyorsa yazıyı YAYINDAN ALMAYIZ (link ölmesin,
   trafik/Google keşfi sürsün). Bunun yerine gövdeye nazik, dürüst bir
   yer-tutucu koyar, yayında bırakırız. Eski (muhtemelen uydurma) gövde de
   böylece temizlenmiş olur; eser sorunlu listeye düşer → sonra başka modelle
   gerçek içerik yazılır. */
function bw_placeholder_html($book, $author) {
    $b = htmlspecialchars(trim((string) $book),   ENT_QUOTES, 'UTF-8');
    $a = htmlspecialchars(trim((string) $author), ENT_QUOTES, 'UTF-8');
    // Kısa, profesyonel, itirafsız. (Site İngilizce.)
    return "<p>A detailed overview of <strong>" . $b . "</strong>"
         . ($a !== '' ? " by " . $a : "")
         . " is being prepared and will be published here soon.</p>";
}

/* ── SON ÇARE: Claude'un kendi bilgisinden tanıtım metni ────────────────────
   Ne tam metin ne Wikipedia bulunduğunda, yer tutucu koymadan ÖNCE çağrılır.
   Claude eseri GÜVENİLİR biliyorsa olgusal bir tanıtım döner; bilmiyorsa ''.
   UYDURMA YOK: model emin değilse UNKNOWN der, biz de boş döneriz.
   MODEL: KALİTELİ (Sonnet) — Haiku obscure eserleri "bilmiyorum" (UNKNOWN)
   diyordu, oysa Sonnet çoğunu biliyor; son çare az sayıda kitapta çalıştığı
   için maliyet sınırlı, kazanç büyük. config'de ANTHROPIC_QUALITY_MODEL ile
   değiştirilebilir.
   $why (by-ref): neden boş döndüğünü GÖRÜNÜR yapar (anahtar yok / UNKNOWN / hata)
   @return string  HTML içerik (bulundu) ya da '' (bilmiyor/kapalı/hata). */
function bw_claude_last_resort($book, $author, $batch_file, $idx, &$why = '', $target_words = 0) {
    require_once __DIR__ . '/_anthropic.php';
    if (!tls_anthropic_ready()) { $why = 'Claude anahtarı config.php\'de yok'; return ''; }
    $hb = function () use ($batch_file, $idx) { bw_touch_hb($batch_file, $idx); };

    // 1) EN GÜÇLÜ model (Opus) + DÜŞÜNME: nadir eserleri Sonnet'ten çok daha iyi
    //    hatırlar (sohbette Opus'un bilip Sonnet'in UNKNOWN demesinin sebebi).
    //    Batch'te seçilen kelime hedefi TAVAN: iyi biliyorsa o civarı yazar ama
    //    geçmez; az biliyorsa daha kısa; hiç bilmiyorsa UNKNOWN.
    $r = tls_claude_overview($book, $author, [
        'model'        => tls_claude_best_model(),
        'thinking'     => ['type' => 'adaptive'],
        'target_words' => (int) $target_words,
        'timeout'      => 300,
        'on_beat'      => $hb,
    ]);
    // Güçlü model AÇIKÇA bilmiyorsa (UNKNOWN) → Sonnet de bilmez; yer tutucu.
    if (!empty($r['unknown'])) { $why = 'Claude bu eseri kesin bilmediğini bildirdi (UNKNOWN)'; return ''; }
    if (!empty($r['ok']) && trim((string) ($r['md'] ?? '')) !== '') { $why = ''; return bw_clean_content($r['md']); }

    // 2) GÜVENLİK AĞI: güçlü model HATA verdi (model erişilemez/400 vb.) — UNKNOWN
    //    değil. Kaliteli modelle (Sonnet, düşünmesiz) yeniden dene ki toplu hata
    //    olmasın. Böylece Opus yapılandırması bozuksa bile üretim durmaz.
    $r2 = tls_claude_overview($book, $author, [
        'model'        => tls_claude_quality_model(),
        'target_words' => (int) $target_words,
        'timeout'      => 240,
        'on_beat'      => $hb,
    ]);
    if (!empty($r2['unknown'])) { $why = 'Claude bu eseri kesin bilmediğini bildirdi (UNKNOWN)'; return ''; }
    if (!empty($r2['ok']) && trim((string) ($r2['md'] ?? '')) !== '') { $why = ''; return bw_clean_content($r2['md']); }

    $why = 'Claude hata/boş: ' . mb_substr((string) ($r['error'] ?? $r2['error'] ?? 'bilinmiyor'), 0, 80);
    return '';
}

/* ── MEKANİK KUSUR TARAMASI (BEDAVA) ────────────────────────────────────────
   Bir HTML gövdede yayına ASLA çıkmaması gereken makine-artığı kusurları arar:
   üretim reddi, prompt/şablon dökümü, parça işareti, DÜZENEK ETİKETİ (LOCATE/
   PRESENT/CLARIFY — kullanıcının bulduğu tam bu), bölüm tekrarı, meta-konuşma,
   kesik cümle, öksüz başlık. Hem YENİ hem ESKİ gövdeye uygulanır.
   NEDEN KRİTİK: "eski korundu" kararı eskiden ESKİ gövdeyi HİÇ denetlemeden
   yayında tutuyordu — oysa eski gövde çoğu zaman tam da temizlemeye çalıştığımız
   uydurma/scaffold'lu eski nesil içerikti. Artık eski gövde de bu kapıdan geçmek
   zorunda; geçemezse korunmaz, yer tutucu konur.
   @return array kusur açıklamaları (boşsa mekanik olarak temiz). */
function bw_mech_flaws($html) {
    require_once __DIR__ . '/_checks.php';
    $html = (string) $html;
    if (trim($html) === '') return [];
    $r = [];
    if (ca_check_refusal($html))       $r[] = 'üretim reddi';
    if (ca_check_prompt_dump($html))   $r[] = 'prompt şablonu';
    if (($s = ca_check_part_markers($html)) !== '')  $r[] = 'parça işareti (' . mb_substr($s, 0, 30) . ')';
    if (function_exists('ca_check_prompt_leak')   && ca_check_prompt_leak($html))   $r[] = 'prompt talimatı';
    if (function_exists('ca_check_scaffold_leak') && ($s = ca_check_scaffold_leak($html)) !== '') $r[] = 'düzenek etiketi (LOCATE/PRESENT/CLARIFY: ' . mb_substr($s, 0, 40) . ')';
    if (function_exists('ca_check_dup_chapters')  && ($s = ca_check_dup_chapters($html)) !== '')  $r[] = 'bölüm tekrarı (' . $s . ')';
    if (ca_check_meta_talk($html))     $r[] = 'meta-konuşma';
    if (ca_check_truncated($html))     $r[] = 'cümle ortasında kesik';
    if (ca_check_orphan_heading($html))$r[] = 'öksüz başlık';
    return $r;
}

/* ── Başlık kök eşleştirme (JS titleTokens/titlesSame ile aynı mantık) ── */
function bw_title_tokens($s) {
    static $stop = null;
    if ($stop === null) {
        $stop = array_flip(explode(' ',
            'against those attack book books gospel epistle epistles letter letters saint part parts four '
          . 'commentary commentaries exposition expositions expositio commentaria commentarium '
          . 'compendium treatise office feast officium rule introduction '
          . 'sentencia sententia sentencie super libri liber librum libros '
          . 'quaestiones quaestio questiones questio disputatae disputata disputatio quaestione '
          . 'questions question disputed disputation '
          . 'litteram litera evangelium evangelii evangelio epistola epistolas epistolam festo '
          . 'aristotle aristotles aristotelis'));
    }
    $s = mb_strtolower($s, 'UTF-8');
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false && $t !== '') $s = $t;
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    $out = [];
    foreach (explode(' ', $s) as $w) {
        if (strlen($w) >= 4 && !isset($stop[$w])) $out[] = $w;
    }
    return $out;
}
function bw_tok_match($a, $b) {
    if ($a === $b) return true;
    $n = min(strlen($a), strlen($b));
    if ($n < 5) return false;
    $k = 0; while ($k < $n && $a[$k] === $b[$k]) $k++;
    return $k >= 5;
}
function bw_titles_same($ta, $tb) {
    if (!$ta || !$tb) return false;
    $small = count($ta) <= count($tb) ? $ta : $tb;
    $big   = count($ta) <= count($tb) ? $tb : $ta;
    $m = 0;
    foreach ($small as $x) { foreach ($big as $y) { if (bw_tok_match($x, $y)) { $m++; break; } } }
    $min = count($small);
    return $m >= 2 || ($m >= 1 && $m === $min);
}

/**
 * Metni $max karakter sınırı içinde TAM cümlede bitir.
 * 155 altındaki son cümle sonu (. ! ?) noktasında keser — cümle ortasında kesmez.
 * Hiç cümle sonu yoksa son kelime sınırında keser (… eklemeden).
 */
function bw_trim_sentence($text, $max = 155) {
    $text = trim($text);
    if (mb_strlen($text) <= $max) return $text;
    $slice = mb_substr($text, 0, $max);
    // Sınır içindeki son cümle bitişini bul
    if (preg_match('/^.*[.!?](?=\s|$)/su', $slice, $m)) {
        return trim($m[0]);
    }
    // Cümle sonu yoksa son tam kelimede kes
    $sp = mb_strrpos($slice, ' ');
    return trim($sp !== false ? mb_substr($slice, 0, $sp) : $slice);
}

/**
 * bw_norm_sentence — Bir excerpt/meta metnini TAM CÜMLE + sınır içinde garantiler.
 *   1) Fazla boşlukları sadeleştirir.
 *   2) $max'ı aşıyorsa sınır içinde son cümle bitişine (yoksa son tam kelimeye) kırpar.
 *   3) Sonda nokta/!/? yoksa: önceki tam cümleye geri döner; o da yoksa nokta ekler.
 * Böylece sonuç asla "Çok Uzun" ya da "Kesilmiş" olmaz.
 */
function bw_norm_sentence($text, $max = 155) {
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
    if ($text === '') return '';

    if (mb_strlen($text) > $max) {
        $text = bw_trim_sentence($text, $max);
    }
    // Sonda cümle bitişi var mı? (kapanış tırnağı/parantez tolere edilir)
    if (!preg_match('/[.!?][")\']?$/u', $text)) {
        if (preg_match('/^.*[.!?](?=\s|$)/su', $text, $m) && mb_strlen(trim($m[0])) >= 20) {
            $text = trim($m[0]);                       // son tam cümleye kırp
        } else {
            $text = rtrim($text, " ,;:—–-") . '.';     // kurtarılamıyorsa nokta ekle
        }
    }
    return $text;
}




/* ════════════════ Bir kitabı baştan sona işle ════════════════ */
/* ── YETİŞKİN İÇERİK ENGELİ ────────────────────────────────────────
 * Site bu tür içerik yayınlamaz. Kitap adı/yazar bu listeyle eşleşirse
 * ÜRETİM HİÇ BAŞLAMAZ (API çağrısı yapılmaz → maliyet de doğmaz) ve kitap
 * 'blocked_adult' işaretiyle atlanır. Panelde neden görünür. */
function bw_adult_terms() {
    // YALNIZ pornografik/erotik-özgü terimler. "sex", "sexual", "sexuality",
    // "nude", "naked", "lust" gibi ÇIPLAK kelimeler bilerek YOK — akademik/bilim
    // eserlerinde masum geçiyor (Darwin "…in Relation to Sex", Foucault "History
    // of Sexuality", sanat tarihinde "The Nude") ve boşuna blokluyordu.
    return ['erotica','erotic','erotique','pornography','pornographic','porn',
            'kama sutra','kamasutra','sexology','fetish','bdsm','sadomasochism',
            'aphrodisiac','obscene','obscenity','brothel','courtesan',
            'perfumed garden','ananga ranga','fanny hill','venus in furs',
            '120 days of sodom','delta of venus','lady chatterley'];
}
function bw_is_adult($text) {
    $t = ' ' . mb_strtolower((string)$text, 'UTF-8') . ' ';
    $t = preg_replace('/[^a-z0-9 ]+/u', ' ', $t);
    $t = preg_replace('/\s+/', ' ', $t);
    foreach (bw_adult_terms() as $kw) {
        // TAM KELİME/İFADE eşleşmesi (kelime-parçası değil): boşlukla çevrele.
        if (strpos($t, ' ' . trim($kw) . ' ') !== false) return true;
    }
    return false;
}

function bw_process_book($batch_file, $idx, $batch, $auth, $wp_api) {
    $book         = $batch['books'][$idx]['book_title'];
    $author       = $batch['books'][$idx]['author_name'];

    // Yetişkin içerik → üretme, yayınlama; hiç API çağrısı yapma.
    if (bw_is_adult($book . ' ' . $author)) {
        bw_update_book($batch_file, $idx, ['status'=>'error','error'=>'blocked_adult (yetişkin içerik — üretilmedi)']);
        return;
    }
    $pre_cover    = trim($batch['books'][$idx]['cover_url'] ?? '');
    $pre_year     = trim((string)($batch['books'][$idx]['pub_year'] ?? '')); // listeden gelen yayın yılı
    // Dış aramalar (Google Books / OpenLibrary / dedup) için başlığın
    // sonundaki "(Orijinal Ad)" parantezini at — yoksa eşleşme bulunamıyor.
    $search_book  = trim(preg_replace('/\s*\([^()]*\)\s*$/', '', $book));
    if ($search_book === '') $search_book = $book;
    $type         = $batch['type'];
    $target_words = max(500, min(8000, (int)$batch['max_tokens']));
    // CLAUDE SON-ÇARE için hedef kelime (TAVAN): batch'te seçilen kritere göre.
    // source: kaydırıcı (source_words) > uzunluk ön-ayarı (kisa/standart/kapsamli).
    // diğer tipler: max_tokens (kelime hedefi). Claude iyi biliyorsa bu civarı
    // yazar ama geçmez; az biliyorsa daha kısa.
    $cl_target_words = (int) ($batch['source_words'] ?? 0);
    if ($cl_target_words <= 0) {
        if ($type === 'source') {
            $len_pre = in_array($batch['length'] ?? 'standart', ['kisa','standart','kapsamli'], true) ? $batch['length'] : 'standart';
            $cl_target_words = ['kisa'=>1500, 'standart'=>3000, 'kapsamli'=>5200][$len_pre];
        } else {
            $cl_target_words = $target_words;
        }
    }
    $post_status  = $batch['post_status'];
    $api_provider = $batch['api_provider'] ?? 'deepseek';
    // Claude seçiliyse ANA İÇERİK modeli: kalite için varsayılan Sonnet, ucuz
    // isteyene Haiku. (Yoklama + meta yine DeepSeek — maliyet bölünür.)
    $claude_model = (($batch['claude_model'] ?? 'sonnet') === 'haiku') ? 'haiku' : 'sonnet';
    $parts        = bw_effective_parts($batch);
    $ep           = $type === 'analysis' ? 'analysis' : 'posts';

    /* ── YENİDEN YAZ MODU ────────────────────────────────────────────
       Bu modda amaç yeni post OLUŞTURMAK değil, sitedeki MEVCUT yazıyı yeni
       (dürüstlük kurallı) içerikle GÜNCELLEMEK. Mevcut yazıyı ŞİMDİ (üretimden
       ÖNCE) buluyoruz: yoksa satır atlanır ve boşuna üretim yapılmaz. */
    $rewrite    = !empty($batch['rewrite']);
    $update_pid = 0;
    $ep = 'posts';
    // ── DOĞRUDAN HEDEF (target_pid) ─────────────────────────────────────────
    // Yeniden yazma listesi POST ID taşıyorsa (Kaynak Arşivi / sonuç / denetim
    // CSV'si), bulanık başlık aramasına HİÇ girme: o yazıyı doğrudan güncelle.
    // "bulunamadı" hatasının en büyük kaynağı buydu — artık id ile birebir.
    $target_pid = (int) ($batch['books'][$idx]['target_pid'] ?? 0);
    if ($rewrite && $target_pid > 0) {
        foreach (['posts', 'analysis'] as $try_ep) {
            [$tp, $tc] = bw_wp("$wp_api/$try_ep/$target_pid?_fields=id", 'GET', [], $auth, 15);
            if ($tc === 200 && !empty($tp['id'])) { $update_pid = (int) $tp['id']; $ep = $try_ep; break; }
        }
        if (!$update_pid) {
            // Verilen id sitede yok (silinmiş/yanlış) → dürüstçe bildir, uydurma yapma.
            bw_flag_problem($book, $author, $pre_cover, $pre_year, 'not_found', 'hedef post id sitede yok: ' . $target_pid);
            bw_update_book($batch_file, $idx, ['status' => 'error', 'error' => 'yeniden yaz: verilen post id (' . $target_pid . ') sitede bulunamadı']);
            return;
        }
    }
    if ($rewrite && !$update_pid) {
        $rt_title = $author ? "$book - $author" : $book;
        // Normalize: küçük harf, diakritik→ascii, parantez içi at, noktalama→boşluk.
        $nrm = function ($s) {
            // WP REST 'title.rendered' HTML-ENTITY kodlu döner: kıvrık apostrof →
            // "&#8217;", en-dash → "&#8211;", "&" → "&amp;". Bunları çözMEZsek
            // normalize "&#8217;" → "8217" rakamına dönüp eşleşmeyi bozuyordu
            // ("masnavi i ma 8217 navi..."). ÖNCE entity'leri gerçek karaktere çevir.
            $s = html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $s = mb_strtolower(trim($s), 'UTF-8');
            // KESME İŞARETLERİNİ (düz ' ve kıvrık ' dahil) TAMAMEN SİL — boşluğa
            // ÇEVİRME. Yoksa "Ma'navi" → "ma navi" (bölünür) olurken sitedeki kıvrık
            // apostrof farklı işlenip "manavi" oluyor ve karşılaştırma tutmuyordu.
            // ÖNCE iconv translit'ten (aksan→ascii) önce yapılmalı ki her iki
            // apostrof türü de aynı şekilde ('' olarak) elensin.
            $s = preg_replace('/[\x{2018}\x{2019}\x{02BC}\x{0027}\x{0060}]/u', '', $s);
            $x = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($x !== false && $x !== '') $s = $x;
            $s = preg_replace('/\([^()]*\)/', ' ', $s);
            $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
            return trim(preg_replace('/\s+/', ' ', $s));
        };
        $tgt     = $nrm($rt_title);
        $tgt_b   = $nrm($book);

        /* WordPress slug'ını OLUŞTURMA YOLUYLA BİREBİR kur: küçük harf →
           diakritik translit → [^a-z0-9]+ '-'. ÖNEMLİ: parantez içi ATILMAZ
           (post oluşturulurken de atılmamıştı). Örn:
           "Relativity ... (Über die ...) - Albert Einstein" →
           "relativity-...-uber-die-...-albert-einstein" — sitedeki slug bu.
           Eski eşleştirici parantezi attığı için ("...-albert-einstein") hiç
           tutmuyordu; "bulunamadı"nın sebebi buydu. */
        $mkslug = function ($s) {
            $s = mb_strtolower(trim((string) $s), 'UTF-8');
            // ÖNEMLİ: kesme işaretlerini ASCII translit'ten ÖNCE at (WP böyle yapıyor).
            // "l'homme" → WP slug'ı "lhomme"; oysa eski akış önce translit edip sonra
            // [^a-z0-9]→'-' yaptığı için "l-homme" üretiyor, sitedeki "lhomme" ile
            // tutmuyordu. Tüm Fransızca/İtalyanca/… (aksan+kesme) başlıklar bu yüzden
            // "bulunamadı" düşüyordu (The Rebel, The Stranger, Suicide, …).
            $s = preg_replace('/[\x{2018}\x{2019}\x{02BC}\x{0027}\x{0060}]/u', '', $s);
            $x = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($x !== false && $x !== '') $s = $x;
            $s = preg_replace('/[^a-z0-9]+/', '-', $s);
            return trim($s, '-');
        };
        // WP, LATİN-DIŞI harfleri (Çince/Kiril/Arapça…) slug'da KORUR:
        // "...-art-在延安文艺座谈会上的讲话-mao-zedong". ASCII translit bunları atıyordu →
        // "bulunamadı". Bu aday Unicode harfleri korur (apostrof WP gibi silinir).
        $mkslug_u = function ($s) {
            $s = mb_strtolower(trim((string) $s), 'UTF-8');
            $s = preg_replace('/[\x{2018}\x{2019}\x{02BC}\x{0027}]/u', '', $s);   // apostrof at (WP)
            $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $s);                       // harf/rakam dışı → '-'
            return trim($s, '-');
        };
        // Çok sayıda slug adayı: parantezli/parantezsiz, yazarlı/yazarsız, ASCII+Unicode.
        $slug_cands = array_values(array_filter(array_unique([
            $mkslug($rt_title),                                  // book(paren) - author  ← oluşturma yolu (ASCII)
            $mkslug($book),                                      // book(paren) only (ASCII)
            $mkslug_u($rt_title),                                // book(paren) - author  ← Unicode korur (CJK/Kiril…)
            $mkslug_u($book),                                    // book(paren) only (Unicode)
            $mkslug($search_book . ($author ? " - $author" : '')), // paren-stripped + author
            $mkslug($search_book),                               // paren-stripped only
            trim(preg_replace('/\s+/', '-', $tgt), '-'),         // normalize(book-author) paren-stripped
            trim(preg_replace('/\s+/', '-', $tgt_b), '-'),       // normalize(book) paren-stripped
        ])));

        /* HER İKİ TİPTE DE ARA: özet (posts) VE analiz (analysis). */
        $atoks = $author ? array_values(array_filter(explode(' ', $nrm($author)))) : [];
        $alast = $atoks ? end($atoks) : '';
        preg_match_all('/\b\d{4}\b/', $tgt_b, $ym); $years = $ym[0];
        $dbg_slug_http = []; $dbg_search_n = []; // TEŞHİS: neden bulunamadığını görünür kıl
        foreach (['posts', 'analysis'] as $try_ep) {
            // 1) Slug adaylarını sırayla dene (en güvenilir yol)
            foreach ($slug_cands as $cand_slug) {
                if ($cand_slug === '') continue;
                [$sp, $sc] = bw_wp("$wp_api/$try_ep?slug=" . rawurlencode($cand_slug) . '&status=any&per_page=1', 'GET', [], $auth, 15);
                $dbg_slug_http[$try_ep] = $sc;
                if ($sc === 200 && !empty($sp[0]['id'])) { $update_pid = (int) $sp[0]['id']; $ep = $try_ep; break 2; }
            }
            // 2) Başlık araması (yedek). ÖNCE tam parantezsiz terim; 0 sonuç dönerse
            //    ilk 4 anlamlı kelimeyle tekrar dene (uzun/yabancı başlıklarda WP
            //    araması boş dönebiliyor). per_page=100.
            $do_search = function ($q) use ($wp_api, $try_ep, $auth) {
                // ÖNEMLİ: WP araması KESME İŞARETLİ terimleri bulamıyor ("Ma'navi" → 0
                // sonuç; "Manavi" → bulur). Sorgudan tüm kesme işaretlerini AT.
                $q = preg_replace('/[\x{2018}\x{2019}\x{02BC}\x{0027}\x{0060}]/u', '', (string) $q);
                [$r, $c] = bw_wp("$wp_api/$try_ep?search=" . rawurlencode($q) . '&status=any&per_page=100&_fields=id,title', 'GET', [], $auth, 20);
                return [$c, (is_array($r) ? $r : [])];
            };
            [$scc, $srch] = $do_search($search_book);
            if ($scc === 200 && !$srch) {
                $words4 = implode(' ', array_slice(array_filter(explode(' ', $nrm($search_book))), 0, 4));
                if ($words4 !== '' && $words4 !== $nrm($search_book)) [$scc, $srch] = $do_search($words4);
            }
            $dbg_search_n[$try_ep] = ($scc === 200) ? count($srch) : "http$scc";
            if ($scc === 200 && is_array($srch)) {
                foreach ($srch as $cand) {
                    $ct = $nrm($cand['title']['rendered'] ?? ($cand['title'] ?? ''));
                    if ($ct !== '' && ($ct === $tgt || $ct === $tgt_b)) { $update_pid = (int) $cand['id']; $ep = $try_ep; break 2; }
                }
                // 3) GÜVENLİ BULANIK EŞLEŞME: birebir tutmayan ama AYNI eser olan
                //    format farklarını (başlık/parantez/çeviri sapması) yakala.
                //    Yanlış yazının üstüne yazma riskine karşı SIKI korumalar:
                //    (a) yazar verildiyse aday başlıkta yazar SOYADI geçmeli,
                //    (b) hedefteki 4-hane yıl(lar) adayda AYNEN bulunmalı,
                //    (c) benzerlik (similar_text) ≥ %86.
                foreach ($srch as $cand) {
                    $ct = $nrm($cand['title']['rendered'] ?? ($cand['title'] ?? ''));
                    if ($ct === '') continue;
                    if ($alast !== '' && strpos(' ' . $ct . ' ', ' ' . $alast . ' ') === false) continue;
                    $ok_years = true;
                    foreach ($years as $y) if (strpos($ct, $y) === false) { $ok_years = false; break; }
                    if (!$ok_years) continue;
                    similar_text($tgt,   $ct, $p1);
                    similar_text($tgt_b, $ct, $p2);
                    if (max($p1, $p2) >= 86) { $update_pid = (int) $cand['id']; $ep = $try_ep; break 2; }
                }
            }
            // 4) YAZARLA ARA (EN SAĞLAM): yazar adı ASCII (ör. "Mahatma Gandhi"),
            //    yabancı/non-Latin başlık slug'ından tamamen bağımsız. Yazarın tüm
            //    yazıları (az sayıda) gelir; normalize başlıkla eşleştiririz. Devanagari/
            //    Çince/Arapça başlıklı eserler bu yüzden "bulunamıyordu".
            if ($author) {
                [$acc, $asr] = $do_search($author);
                $dbg_search_n[$try_ep . '/yazar'] = ($acc === 200) ? count($asr) : "http$acc";
                if ($acc === 200 && is_array($asr)) {
                    foreach ($asr as $cand) {
                        $ct = $nrm($cand['title']['rendered'] ?? ($cand['title'] ?? ''));
                        if ($ct === '') continue;
                        // Tam (normalize) eşleşme: başlık+yazar VEYA sadece başlık.
                        if ($ct === $tgt || $ct === $tgt_b) { $update_pid = (int) $cand['id']; $ep = $try_ep; break 2; }
                        // Güvenli bulanık: yıl(lar) tutmalı + benzerlik ≥ %88 (yazar zaten kesin).
                        $ok_years = true; foreach ($years ?? [] as $y) if (strpos($ct, $y) === false) { $ok_years = false; break; }
                        if ($ok_years) {
                            similar_text($tgt, $ct, $q1); similar_text($tgt_b, $ct, $q2);
                            if (max($q1, $q2) >= 88) { $update_pid = (int) $cand['id']; $ep = $try_ep; break 2; }
                        }
                    }
                }
            }
        }
        if (!$update_pid) {
            // TEŞHİS: hangi slug denendi, aramada kaç sonuç döndü, HTTP durumu.
            $c0 = $slug_cands[0] ?? '';
            $diag = 'slug[' . mb_substr($c0, 0, 60) . '] http=' . json_encode($dbg_slug_http)
                  . ' · arama=' . json_encode($dbg_search_n, JSON_UNESCAPED_UNICODE);
            bw_flag_problem($book, $author, $pre_cover, $pre_year, 'not_found', 'bulunamadı · ' . $diag);
            bw_update_book($batch_file, $idx, ['status' => 'error', 'error' => 'yeniden yaz: bulunamadı · ' . $diag]);
            return;
        }
        // Bulunan tipe göre prompt tipini düzelt: mevcut yazı özetse özet, analizse analiz.
        // AMA 'info' ve 'source' tiplerini KORU — bunlar prompts.json kullanmaz, kendi
        // motorlarıyla üretir (info → _info.php, source → proto_generate). Aksi halde
        // rewrite'ta 'source' 'summary'ye dönüşüp "Prompt boş" hatası veriyordu.
        if ($type !== 'info' && $type !== 'source') $type = ($ep === 'analysis') ? 'analysis' : 'summary';
    }

    // ── Prompt ────────────────────────────────────────────────────
    // Bilgi metni (info) tipi prompts.json kullanmaz — kendi motoru var (_info.php).
    $prompts  = file_exists(PROMPTS_FILE) ? json_decode(file_get_contents(PROMPTS_FILE), true) : [];
    $template = trim($prompts[$type] ?? '');
    if (!$template && $type !== 'info' && $type !== 'source') {   // info/source kendi motoru; prompt gerekmez
        bw_update_book($batch_file, $idx, ['status'=>'error','error'=>'Prompt boş']);
        return;
    }

    /* ── ÜRETİM ÖNCESİ BİLGİ YOKLAMASI ────────────────────────────
       Uydurmanın en ucuz durduğu yer burasıdır: metin hiç yazılmaz.
       Model'e üretim baskısı OLMADAN "bu eseri biliyor musun" sorulur ve
       yanıtı Open Library kaydıyla çapraz kontrol edilir. Tutmazsa kitap
       hata olarak işaretlenir — API'ye 4 parça yazdırıp sonra atmaktan
       hem çok daha ucuz hem de tek güvenilir koruma budur. */
    require_once __DIR__ . '/_verify.php';
    /* ── ÜRETİM ÖNCESİ YOKLAMA (artık KATALOG öncelikli + GROUNDING kaynağı) ──
       Yoklama yeniden yazıldı: kararın belkemiği modelin belleği değil, dış
       katalog (Google Books + Open Library). Gerçek/bulunabilir bir kitap,
       model "bilmiyorum" dese bile GEÇER — ve kataloğun açıklaması aşağıda
       üretime KAYNAK olarak verilir (grounding: model ezberden değil, gerçek
       tanıtımdan yazar). Artık hem sıfırdan üretimde hem yeniden yaz modunda
       çalışır; yeniden yaz modunda "bilinmiyor" sonucu YAYINDAN ALMAZ, gövdeye
       yer tutucu koyar (yazı yayında kalır). */
    $src = null;                       // grounding kaynağı (varsa)
    $skip_generation = false;          // SON ÇARE (Claude) içeriği ürettiyse üretimi atla
    // KAYNAK-TEMELLİ tipte probe'u ATLA: asıl kapı "tam metin bulunuyor mu"dur.
    // Probe (model eseri tanıyor mu) obscure ama GERÇEK/metni-olan eserleri —
    // ve kullanıcının MANUEL verdiği kaynağı — kaynak dalına hiç varmadan yer-tutucuya
    // düşürüyordu. Kaynak tipinde uydurma riski yok (özet gerçek metne bağlı), o yüzden
    // gereksiz. Manuel kaynak verildiyse her koşulda atla.
    // MANUEL KAYNAK — batch geneli VEYA kitaba özel (toplu metin yükleme).
    // Kitaba özel varsa o kullanılır (her kitabın kendi metni/linki).
    $bk_src_text = (string) ($batch['books'][$idx]['source_text'] ?? '');
    $bk_src_url  = (string) ($batch['books'][$idx]['source_url']  ?? '');
    $eff_src_text = $bk_src_text !== '' ? $bk_src_text : (string) ($batch['source_text'] ?? '');
    $eff_src_url  = $bk_src_url  !== '' ? $bk_src_url  : (string) ($batch['source_url'] ?? '');
    $has_manual_source = (trim($eff_src_url) !== '' || trim($eff_src_text) !== '');
    if (tv_settings()['probe'] && ($post_status === 'publish' || $rewrite) && $type !== 'source' && !$has_manual_source) {
        bw_touch_hb($batch_file, $idx);
        $pr = tv_probe($search_book, $author);
        if (!empty($pr['ok'])) {
            $src = $pr['src'] ?? null;
            if (empty($pr['known'])) {
                // SON ÇARE: probe kitabı doğrulayamadı — ama Claude eseri GÜVENİLİR
                // biliyorsa (kendi kaçışıyla: bilmiyorsa UNKNOWN) tanıtım yazsın.
                if ($api_provider !== 'anthropic') {
                    $cl_why0 = '';
                    $cl = bw_claude_last_resort($book, $author, $batch_file, $idx, $cl_why0, $cl_target_words);
                    if ($cl !== '') {
                        $content = $cl;
                        $gen_method = 'claude-bilgi';
                        $skip_generation = true;   // içerik hazır → normal üretimi atla, yayına geç
                        bw_flag_problem($book, $author, $pre_cover, $pre_year, 'claude-bilgi', 'probe bilmiyor → Claude bilgi metni', $update_pid, $rewrite ? 'rewrite' : 'create');
                    }
                }
                if (empty($skip_generation) && $rewrite && $update_pid) {
                    // Yeniden yaz + bilinmiyor: YAYINDAN ALMA — yer tutucu koy, yayında kalsın.
                    $ph = bw_placeholder_html($book, $author);
                    [$rp] = bw_wp("$wp_api/$ep/$update_pid", 'POST', ['content' => $ph, 'status' => 'publish'], $auth, 60);
                    bw_flag_problem($book, $author, $pre_cover, $pre_year, 'placeholder', (string) $pr['reason'], $update_pid, 'rewrite');
                    bw_update_book($batch_file, $idx, [
                        'status'   => 'done',
                        'post_id'  => $update_pid,
                        'post_url' => $rp['link'] ?? '',
                        'edit_url' => rtrim(WP_URL, '/') . '/wp-admin/post.php?post=' . $update_pid . '&action=edit',
                        'error'    => 'model tanımadı → yer tutucu kondu (yayında)',
                        'placeholder' => 1, 'method' => 'yer-tutucu',
                    ]);
                    return;
                }
                if (empty($skip_generation)) {
                // Sıfırdan üretim + bilinmiyor: post oluşturma, atla.
                bw_flag_problem($book, $author, $pre_cover, $pre_year, 'unknown', (string) $pr['reason'], 0, 'create');
                bw_update_book($batch_file, $idx, [
                    'status' => 'error',
                    'error'  => 'DOĞRULANAMADI: eser kataloglarda yok, model de tanımıyor — ' .
                                mb_substr((string) $pr['reason'], 0, 180),
                ]);
                return;
                }
            }
        }
    }
    // Yoklama kapalıysa da grounding kaynağını yine de dene (ucuz, uydurmayı azaltır).
    if ($src === null) {
        $src = tv_book_source($search_book, $author);
    }

    // ── İçerik üretimi ────────────────────────────────────────────
    // SON ÇARE (Claude) probe aşamasında içeriği ürettiyse üretimi tümden atla.
    if (empty($skip_generation)) {
    $content   = '';
    $gen_error = '';
    $part_warn = '';      // parça eksik kaldıysa uyarı (yayınlanır ama işaretlenir)
    // YÖNTEM damgası: her kitabın nasıl yazıldığı (bitiş CSV'sinde görünür).
    // kaynak-temelli = gerçek tam metinden · bilgi-metni = Wikipedia/katalog ·
    // kaynaksız = prompt'tan (summary/analysis). Yer-tutucu/eski-korundu ayrı işaretlenir.
    $gen_method = ($type === 'source' || $type === 'info') ? 'bilgi-metni' : 'kaynaksız';
    $gen_source = '';     // bulunan kaynağın adı (Gutenberg/Archive) — kaynak-temelliyse
    $gen_source_url = ''; // bulunan kaynağın linki (kaynak arşivi + post meta)
    $gen_book_words = 0;  // kaynak metnin kelime sayısı (indeks için)

    /* ── KAYNAK-TEMELLİ ÖZET TİPİ (source) ───────────────────────────────
       Kitabın GERÇEK TAM METNİNİ bul (Project Gutenberg / Internet Archive),
       parçala, her parçayı yalnız o metinden özetle, kapsamlı özete birleştir.
       AKILLI KADEME (uydurma YOK, kapsamı en üst düzeyde tut):
         1) tam metin var  → kapsamlı, bölüm-bölüm özet
         2) tam metin yok  → Wikipedia-temelli Bilgi Metni'ne düş (tls_info_generate)
         3) o da yok        → yer tutucu (rewrite) / atla (create). */
    if ($type === 'source') {
        require_once __DIR__ . '/_proto.php';
        require_once __DIR__ . '/_info.php';
        bw_touch_hb($batch_file, $idx);
        $sr = proto_generate($search_book, $author, [
            'length'  => in_array($batch['length'] ?? 'standart', ['kisa','standart','kapsamli'], true) ? $batch['length'] : 'standart',
            'words'   => (int) ($batch['source_words'] ?? 0),   // serbest hedef kelime (kaydırıcı); 0 → 'length' ön-ayarı
            'url'     => $eff_src_url,    // MANUEL kaynak: URL (kitaba özel > batch geneli)
            'text'    => $eff_src_text,   // MANUEL kaynak: yapıştırılan/yüklenen metin (kitaba özel > batch geneli)
            'provider'=> 'auto',
            'on_beat' => function () use ($batch_file, $idx) { bw_touch_hb($batch_file, $idx); },
            'on_stage'=> function ($m) use ($batch_file, $idx) { bw_set_stage($batch_file, $idx, $m); bw_touch_hb($batch_file, $idx); },
        ]);
        $sr_trace = (string) ($sr['trace'] ?? '');
        if (!empty($sr['found']) && empty($sr['insufficient']) && trim((string)($sr['md'] ?? '')) !== '') {
            $content = bw_clean_content($sr['md']);   // tam metinden kapsamlı özet
            $gen_method = 'kaynak-temelli';           // GERÇEK tam metinden yazıldı
            $gen_source = (string) ($sr['source'] ?? '');
            $gen_source_url = (string) ($sr['url'] ?? '');   // KAYNAK ARŞİVİ: kitap↔kaynak linki
            $gen_book_words = (int) ($sr['book_words'] ?? 0);
        } else {
            // Tam metin yok / yetersiz → Wikipedia-temelli Bilgi Metni'ne düş.
            // NEDENİNİ sorunlu listeye yaz (Relativity'nin neden 2 dk çıktığını böyle görürüz).
            bw_flag_problem($book, $author, $pre_cover, $pre_year, 'source_fallback', ($sr_trace ?: 'tam metin yok') . ' → Bilgi Metni', $update_pid, $rewrite ? 'rewrite' : 'create');
            $info_prov = (proto_deepseek_reachable()) ? 'deepseek' : 'gemini';
            $ir = tls_info_generate($search_book, $author, [
                'provider' => $info_prov,
                'referee'  => (($batch['referee'] ?? '1') !== '0'),
                'on_beat'  => function () use ($batch_file, $idx) { bw_touch_hb($batch_file, $idx); },
            ]);
            if (!empty($ir['insufficient'])) {
                // SON ÇARE: Claude eseri güvenilir biliyorsa tanıtım metni yazsın.
                $cl_why = '';
                $cl = bw_claude_last_resort($book, $author, $batch_file, $idx, $cl_why, $cl_target_words);
                if ($cl !== '') {
                    $content = $cl;
                    $gen_method = 'claude-bilgi';   // Claude'un bilgisinden (kaynak yok) — CSV'de ayrı görünür
                    bw_flag_problem($book, $author, $pre_cover, $pre_year, 'claude-bilgi', 'kaynak yok → Claude bilgi metni', $update_pid, $rewrite ? 'rewrite' : 'create');
                } else {
                    // Ne tam metin ne Wikipedia ne de Claude → UYDURMA YOK. Sebebi GÖRÜNÜR yaz.
                    $ph_reason = 'kaynak yok · ' . ($cl_why ?: $sr_trace);
                    if ($rewrite && $update_pid) {
                        $ph = bw_placeholder_html($book, $author);
                        [$rp] = bw_wp("$wp_api/$ep/$update_pid", 'POST', ['content' => $ph, 'status' => 'publish'], $auth, 60);
                        bw_flag_problem($book, $author, $pre_cover, $pre_year, 'placeholder', $ph_reason, $update_pid, 'rewrite');
                        bw_update_book($batch_file, $idx, ['status'=>'done','post_id'=>$update_pid,'post_url'=>$rp['link']??'','edit_url'=>rtrim(WP_URL,'/').'/wp-admin/post.php?post='.$update_pid.'&action=edit','error'=>'kaynak yok → yer tutucu · '.($cl_why?:'—'),'placeholder'=>1,'method'=>'yer-tutucu']);
                        return;
                    }
                    bw_flag_problem($book, $author, $pre_cover, $pre_year, 'unknown', $ph_reason, 0, 'create');
                    bw_update_book($batch_file, $idx, ['status'=>'error','error'=>'kaynak yok: tam metin/Wikipedia yok · '.($cl_why?:'—')]);
                    return;
                }
            } else
            if (empty($ir['ok']) || trim((string)$ir['md']) === '') { $gen_error = 'kaynak-temelli özet üretilemedi: ' . ($ir['error'] ?? 'boş'); }
            else {
                $content = bw_clean_content($ir['md']);
                if (!empty($ir['shortnote'])) bw_flag_problem($book, $author, $pre_cover, $pre_year, 'shortnote', 'kaynaksız kısa not (tam metin yok, Wikipedia zayıf)', $update_pid, $rewrite ? 'rewrite' : 'create');
            }
        }
    } elseif ($type === 'info') {
        require_once __DIR__ . '/_info.php';
        require_once __DIR__ . '/_anthropic.php';   // model id yardımcıları
        bw_touch_hb($batch_file, $idx);
        // Anthropic seçiliyse ana makaleyi yazacak Claude modeli (haiku/sonnet).
        $info_cmodel = ($api_provider === 'anthropic')
            ? (($claude_model === 'haiku') ? tls_claude_fast_model() : tls_claude_quality_model())
            : '';
        $ir = tls_info_generate($search_book, $author, [
            'provider' => in_array($api_provider, ['anthropic', 'gemini'], true) ? $api_provider : 'deepseek',
            'model'    => $info_cmodel,
            'referee'  => (($batch['referee'] ?? '1') !== '0'),   // kademeli hakem (Gemini→Claude)
            'on_beat'  => function () use ($batch_file, $idx) { bw_touch_hb($batch_file, $idx); },
        ]);
        if (!empty($ir['insufficient'])) {
            // İki neden olabilir: (a) güvenilir kaynak yok, (b) HAKEM metni
            // uydurma bulup reddetti. İkisinde de UYDURMA yayına çıkmaz.
            $ref_fab = (($ir['referee']['verdict'] ?? '') === 'fabricated');
            $pr_probs = $ref_fab ? implode(' | ', array_slice($ir['referee']['problems'] ?? [], 0, 3)) : '';
            $pr_reason = $ref_fab
                ? ('hakem uydurma buldu (' . ($ir['referee']['judge'] ?? '?') . ')' . ($pr_probs ? ': ' . $pr_probs : ''))
                : 'kaynak yetersiz (bilgi metni)';
            // SON ÇARE: Claude eseri güvenilir biliyorsa tanıtım metni yazsın.
            // (Provider zaten anthropic ise Claude denenmişti → tekrar deneme.)
            $cl_why2 = '';
            $cl = ($api_provider !== 'anthropic')
                ? bw_claude_last_resort($book, $author, $batch_file, $idx, $cl_why2, $cl_target_words) : '';
            if ($cl !== '') {
                $content = $cl;
                $gen_method = 'claude-bilgi';   // Claude'un bilgisinden (kaynak yok) — CSV'de ayrı görünür
                bw_flag_problem($book, $author, $pre_cover, $pre_year, 'claude-bilgi', 'kaynak yok → Claude bilgi metni', $update_pid, $rewrite ? 'rewrite' : 'create');
            } else {
                // Rewrite'ta yer tutucu (yayında kalsın), create'te atla.
                if ($rewrite && $update_pid) {
                    $ph = bw_placeholder_html($book, $author);
                    [$rp] = bw_wp("$wp_api/$ep/$update_pid", 'POST', ['content' => $ph, 'status' => 'publish'], $auth, 60);
                    bw_flag_problem($book, $author, $pre_cover, $pre_year, ($ref_fab ? 'referee' : 'placeholder'), $pr_reason, $update_pid, 'rewrite');
                    bw_update_book($batch_file, $idx, [
                        'status' => 'done', 'post_id' => $update_pid, 'post_url' => $rp['link'] ?? '',
                        'edit_url' => rtrim(WP_URL, '/') . '/wp-admin/post.php?post=' . $update_pid . '&action=edit',
                        'error' => ($ref_fab ? 'hakem uydurma buldu → yer tutucu (yayında)' : 'kaynak yetersiz → yer tutucu (yayında)'),
                        'placeholder' => 1, 'method' => 'yer-tutucu',
                    ]);
                    return;
                }
                bw_flag_problem($book, $author, $pre_cover, $pre_year, ($ref_fab ? 'referee' : 'unknown'), $pr_reason, 0, 'create');
                bw_update_book($batch_file, $idx, ['status' => 'error', 'error' => ($ref_fab ? ('hakem uydurma buldu: ' . $pr_probs) : 'kaynak yetersiz: güvenilir bilgi bulunamadı (bilgi metni)')]);
                return;
            }
        } else
        if (empty($ir['ok']) || trim((string) $ir['md']) === '') {
            $gen_error = 'bilgi metni üretilemedi: ' . ($ir['error'] ?? 'boş');
        } else {
            $content = bw_clean_content($ir['md']);
            // Kaynaksız KISA NOT (modelin bilgisinden) → yayınlanır ama sorunlu
            // listeye 'shortnote' olarak düşer: sonra daha iyi kaynak/modelle
            // zenginleştirmek istersen elinde liste olur.
            if (!empty($ir['shortnote'])) {
                bw_flag_problem($book, $author, $pre_cover, $pre_year, 'shortnote', 'kaynaksız kısa not (modelin bilgisi)', $update_pid, $rewrite ? 'rewrite' : 'create');
            }
        }
    } else {

    $accumulated = '';
    $part_words  = (int)ceil($target_words / max(1, $parts));

    /* ── GROUNDING: gerçek katalog açıklamasını üretime KAYNAK ver ───────────
       Model ezberden değil, dış kaynağın DOĞRULANMIŞ tanıtımından yazsın →
       uydurma büyük ölçüde kesilir. Kaynak yoksa blok boş kalır (model eski
       davranışına döner, dürüstlük prompt'u yine geçerli). */
    $ground = '';
    if (is_array($src)) {
        $sd = trim((string) ($src['desc'] ?? ''));
        $sy = $src['year'] ?? null;
        $ssub = implode(', ', array_slice((array) ($src['subjects'] ?? []), 0, 6));
        if ($sd !== '') {
            $ground = "\n\n=== VERIFIED SOURCE MATERIAL (from a book catalog) ===\n"
                . "Use ONLY this verified description plus well-established facts as your basis. "
                . "Do NOT invent characters, plot points, chapters, or arguments that are not supported by this material or by widely-known facts about the work. "
                . "If the material is thin, write a shorter, factual overview rather than filling gaps with invention.\n"
                . ($sy ? "First published: {$sy}\n" : '')
                . ($ssub !== '' ? "Subjects: {$ssub}\n" : '')
                . "Description:\n" . mb_substr($sd, 0, 2000) . "\n=== END SOURCE MATERIAL ===";
        } elseif (!empty($src['real'])) {
            // Açıklama yok ama kitap gerçek: en azından yıl/konu bağla + uydurma yasağı.
            $ground = "\n\nNOTE: This is a real, catalogued work"
                . ($sy ? " (first published {$sy})" : '')
                . ($ssub !== '' ? ", subjects: {$ssub}" : '')
                . ". If you do not reliably know its actual contents, write a SHORT factual overview based on what is genuinely established — do NOT invent characters, plot, or chapter details.";
        }
    }

    for ($k = 1; $k <= $parts; $k++) {
        bw_touch_hb($batch_file, $idx);   // ucuz canlılık damgası (her parçadan önce)
        $headings = [];
        if ($accumulated !== '') {
            preg_match_all('/^### (.+)$/m', $accumulated, $mh);
            $headings = $mh[1] ?? [];
        }
        $tail = $accumulated !== '' ? mb_substr($accumulated, -700) : '';

        // ZORUNLU ANTI-META KURALI (stored prompt'ta olsa da olmasa da her zaman
        // eklenir): metin ansiklopedi/eleştiri yazısı gibi olmalı; yazar KENDİNDEN,
        // AI'dan, bilgisinden/sınırlarından ASLA bahsetmez, okuyucuya hitap etmez.
        // "As an AI / I cannot / I do not have knowledge / A note on the limits /
        // to the best of my knowledge" gibi ifadeler KESİNLİKLE yasak. Bilmediğin
        // bir şeyi SESSİZCE atla; not/uyarı/itiraf yazma.
        $anti_meta =
            "\n\nABSOLUTE OUTPUT RULE — write as a published encyclopedia/critical "
          . "article about the WORK ONLY. NEVER write about yourself, an AI, your "
          . "knowledge, confidence, or limits, and NEVER address the reader. Do NOT "
          . "output any note/caveat/disclaimer such as 'As an AI', 'I cannot', 'I do "
          . "not have (secure) knowledge', 'A note on the limits of what I can say', "
          . "'to the best of my knowledge', 'I have deliberately not…'. If you don't "
          . "know a specific, OMIT it silently — no meta-commentary. The text must "
          . "read as if written by a human reference author, never by an AI.";

        $pr = $template
            . "\n\nBook: {$book}\nAuthor: {$author}"
            . $ground
            . $anti_meta
            . bw_part_instruction($k, $parts, $headings, $tail, $part_words);

        // Streaming üretim: yanıt parça parça gelirken HER chunk'ta heartbeat'i
        // tazele. Böylece worker, 280sn'ye varan uzun üretim sırasında "ölü"
        // sanılmaz; canlılık eşiği düşük tutulabilir → takılan batch hızla
        // kurtarılır. (Eskiden bloklu istek heartbeat'i tazeleyemediğinden eşik
        // 7 dk olmak zorundaydı ve takılma penceresi bu yüzden çok uzundu.)
        $piece    = '';
        $sbuf     = '';
        $raw_tail = '';
        $stream_cb = function($c, $chunk) use (&$piece, &$sbuf, &$raw_tail, $batch_file, $idx) {
            $raw_tail = substr($raw_tail . $chunk, -2000);   // hata teşhisi için son gövde
            $sbuf .= $chunk;
            while (($p = strpos($sbuf, "\n")) !== false) {
                $line = trim(substr($sbuf, 0, $p));
                $sbuf = substr($sbuf, $p + 1);
                if (strpos($line, 'data:') !== 0) continue;
                $j = trim(substr($line, 5));
                if ($j === '' || $j === '[DONE]') continue;
                $ev = json_decode($j, true);
                if (isset($ev['choices'][0]['delta']['content'])) {
                    $piece .= $ev['choices'][0]['delta']['content'];
                }
            }
            bw_touch_hb($batch_file, $idx);   // her chunk = canlılık damgası
            return strlen($chunk);
        };
        // Hem bağlantı hatası (timeout/SSL) hem BOŞ YANIT geçicidir → 3 kez dene.
        // ÖNEMLİ: Eskiden yalnız bağlantı hatası tekrarlanıyordu; bir ara parça
        // boş dönerse döngü SESSİZCE kırılıp yalnız önceki parçalar yayınlanıyordu
        // → istenen 8000 kelime yerine ~3000 kelimelik "başarılı" yazı çıkıyordu.
        // Parça TAM mı geldi? Akış ortada koparsa gövde dolu ama cümle yarım
        // kalıyordu ve bu "başarılı" sayılıp yayınlanıyordu. Ara parçalarda
        // prompt son satır olarak %%PART_END%% istiyor — yoksa yanıt kesilmiş
        // demektir. Son parçada böyle bir işaret yok, orada cümlenin düzgün
        // bitip bitmediğine bakılır.
        $piece_complete = function($raw) use ($k, $parts) {
            if (trim($raw) === '') return false;
            if ($k < $parts) return strpos($raw, '%%PART_END%%') !== false;
            $t = rtrim(preg_replace('/[*_\s]+$/u', '', $raw));
            return $t !== '' && mb_strpos('.!?"\'»)”’…', mb_substr($t, -1, 1, 'UTF-8'), 0, 'UTF-8') !== false;
        };

        $cerr = ''; $piece_ok = false; $piece_partial = '';
        for ($try = 1; $try <= 3; $try++) {
            $piece = ''; $sbuf = ''; $raw_tail = '';   // her denemede buffer sıfırla
            if ($api_provider === 'anthropic') {
                /* ── CLAUDE ile ÜRETİM (kaliteli içerik) ──────────────────
                   DeepSeek'in kalitesi yetmediğinde ana metni Claude yazar.
                   Bloklu çağrı; canlılık için önce/sonra + on_beat ile heartbeat
                   tazelenir. Yoklama ve meta yine DeepSeek (maliyet böl). */
                require_once __DIR__ . '/_anthropic.php';
                bw_touch_hb($batch_file, $idx);
                $cm = ($claude_model === 'haiku') ? tls_claude_fast_model() : tls_claude_quality_model();
                $cres = tls_claude('', $pr, [
                    'model'       => $cm,
                    'max_tokens'  => 8000,
                    'temperature' => 0.4,
                    'timeout'     => 240,
                    'retries'     => 1,            // dış döngü zaten 3 kez deniyor
                    'on_beat'     => function () use ($batch_file, $idx) { bw_touch_hb($batch_file, $idx); },
                ]);
                bw_touch_hb($batch_file, $idx);
                if (!empty($cres['ok'])) { $piece = (string) $cres['text']; $cerr = ''; }
                else { $cerr = 'Claude: ' . ($cres['error'] ?? 'boş yanıt'); $raw_tail = $cerr; }
            } elseif ($api_provider === 'gemini') {
                /* ── GEMINI ile ÜRETİM (ucuz + hızlı alternatif) ───────────
                   Bloklu çağrı; canlılık için on_beat ile heartbeat tazelenir.
                   2.5-flash düşünen model → istemci varsayılan thinkingBudget=0
                   ile düşünmeyi kapatır (maliyet). Yoklama/meta yine DeepSeek. */
                require_once __DIR__ . '/_gemini.php';
                bw_touch_hb($batch_file, $idx);
                $gres = tls_gemini('', $pr, [
                    'max_tokens'  => 16000,
                    'temperature' => 0.4,
                    'timeout'     => 240,
                    'retries'     => 1,            // dış döngü zaten 3 kez deniyor
                    'on_beat'     => function () use ($batch_file, $idx) { bw_touch_hb($batch_file, $idx); },
                ]);
                bw_touch_hb($batch_file, $idx);
                if (!empty($gres['ok'])) { $piece = (string) $gres['text']; $cerr = ''; }
                else { $cerr = 'Gemini: ' . ($gres['error'] ?? 'boş yanıt'); $raw_tail = $cerr; }
            } else {
            $ch = curl_init(DEEPSEEK_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_TIMEOUT => 280,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
                CURLOPT_POSTFIELDS => json_encode(['model'=>(in_array(DEEPSEEK_MODEL,['deepseek-chat','deepseek-reasoner'],true)?'deepseek-v4-flash':DEEPSEEK_MODEL),'max_tokens'=>16000,'stream'=>true,'messages'=>[['role'=>'user','content'=>$pr]]]),
                CURLOPT_WRITEFUNCTION => $stream_cb,
            ]);
            curl_exec($ch); $cerr = curl_error($ch); curl_close($ch);
            }

            if (!$cerr) {
                if ($piece_complete($piece)) {
                    $piece = trim(str_replace('%%PART_END%%', '', $piece));
                    $piece_ok = true;
                    break;                                  // parça eksiksiz geldi
                }
                // Gövde var ama yarım (akış koptu / model kesildi): en dolusunu
                // sakla, son çare olarak onu kullanacağız.
                $cand = trim(str_replace('%%PART_END%%', '', $piece));
                if (mb_strlen($cand, 'UTF-8') > mb_strlen($piece_partial, 'UTF-8')) $piece_partial = $cand;
            }
            if ($try < 3) { bw_touch_hb($batch_file, $idx); sleep(3); }
        }

        if (!$piece_ok) {
            if ($piece_partial !== '') {
                // 3 denemede de tam gelmedi ama elimizde gövde var: metni
                // çöpe atmak yerine yarım cümleyi kırpıp yayınla ve İŞARETLE.
                $piece_partial = preg_replace('/(?<=[.!?"\'”’)])[^.!?]*$/u', '', $piece_partial);
                $piece_partial = rtrim($piece_partial);
                if ($piece_partial !== '') {
                    $piece      = $piece_partial;
                    $piece_ok   = true;
                    $part_warn  = "eksik: Part {$k}/{$parts} yarım geldi (3 deneme) — yarım cümle kırpıldı";
                }
            }
        }

        if (!$piece_ok) {
            if ($k === 1) {
                // İlk parça hiç gelmedi → kitap üretilemedi. Sağlayıcı adını
                // DOĞRU yaz (Claude seçiliyken "DeepSeek" demek yanıltıyordu).
                $prov = ($api_provider === 'anthropic') ? 'Claude' : (($api_provider === 'gemini') ? 'Gemini' : 'DeepSeek');
                $errj = json_decode($raw_tail, true);
                $gen_error = $cerr
                    ? "$prov Part {$k} bağlantı hatası (3 deneme): {$cerr}"
                    : "$prov Part {$k} (3 deneme): " . ($errj['error']['message'] ?? 'boş yanıt');
            } else {
                // Ara/son parça gelmedi → elimizdeki kısmı yayınla AMA işaretle,
                // yoksa kısa içerik "tam" sanılıp fark edilmiyor.
                $part_warn = "eksik: Part {$k}/{$parts} boş döndü (3 deneme) — içerik hedeflenenden kısa";
            }
            break;
        }

        if ($k > 1) {
            $piece = preg_replace('/^# [^\n]+\n+/m',  '', $piece, 1);
            $piece = preg_replace('/^## [^\n]+\n+/m', '', $piece, 1);
            $piece = ltrim($piece);
        }
        $accumulated = $accumulated === '' ? $piece : ($accumulated . "\n\n" . $piece);
    }

    if ($accumulated !== '') $content = bw_clean_content($accumulated);

    /* ── HAKEM (özet + ANALİZ): kaynağa-sadakat uydurma denetimi ─────────────
       AYNI DİSİPLİN: source/info tipinde hakem tls_info_generate içinde vardı;
       artık özet/analiz de aynı denetimden geçer. Üretilen metni, elimizdeki
       DOĞRULANMIŞ katalog malzemesiyle (dossier) kıyaslar; hakem "uydurma özgül
       iddia" bulursa YAYINLAMAYIZ → rewrite'ta dürüst yer tutucu, create'te atla.
       Kaynak (dossier) yoksa hakem 'skip' → engellemez (dürüstlük prompt'u +
       mekanik kapı yine geçerli). Batch 'referee' kapalıysa çalışmaz. */
    $ref_dossier = '';
    if (is_array($src)) {
        $rsd = trim((string) ($src['desc'] ?? ''));
        $rsy = $src['year'] ?? null;
        $rsub = implode(', ', array_slice((array) ($src['subjects'] ?? []), 0, 8));
        if ($rsd !== '' || $rsub !== '' || $rsy) {
            $ref_dossier = trim(
                ($rsy ? "First published: {$rsy}\n" : '') .
                ($rsub !== '' ? "Subjects: {$rsub}\n" : '') .
                ($rsd !== '' ? "Description:\n" . mb_substr($rsd, 0, 4000) : '')
            );
        }
    }
    if (($batch['referee'] ?? '1') !== '0' && $ref_dossier !== '' && trim((string) $content) !== '') {
        require_once __DIR__ . '/_referee.php';
        bw_touch_hb($batch_file, $idx);
        $rf = tls_referee($content, $ref_dossier, $book, $author, ['primary' => 'gemini', 'escalate' => 'anthropic']);
        bw_touch_hb($batch_file, $idx);
        if (($rf['verdict'] ?? '') === 'fabricated') {
            $rf_probs = implode(' | ', array_slice($rf['problems'] ?? [], 0, 3));
            if ($rewrite && $update_pid) {
                $ph = bw_placeholder_html($book, $author);
                [$rp] = bw_wp("$wp_api/$ep/$update_pid", 'POST', ['content' => $ph, 'status' => 'publish'], $auth, 60);
                bw_flag_problem($book, $author, $pre_cover, $pre_year, 'referee', 'hakem uydurma buldu (' . ($rf['judge'] ?? '?') . ')' . ($rf_probs ? ': ' . $rf_probs : ''), $update_pid, 'rewrite');
                bw_update_book($batch_file, $idx, [
                    'status' => 'done', 'post_id' => $update_pid, 'post_url' => $rp['link'] ?? '',
                    'edit_url' => rtrim(WP_URL, '/') . '/wp-admin/post.php?post=' . $update_pid . '&action=edit',
                    'error' => 'hakem uydurma buldu → yer tutucu (yayında)', 'placeholder' => 1, 'method' => 'yer-tutucu',
                ]);
                return;
            }
            bw_flag_problem($book, $author, $pre_cover, $pre_year, 'referee', 'hakem uydurma buldu: ' . $rf_probs, 0, 'create');
            bw_update_book($batch_file, $idx, ['status' => 'error', 'error' => 'hakem uydurma buldu: ' . $rf_probs]);
            return;
        }
    }
    }   // ── /walkthrough (type !== 'info') ──
    }   // ── /if (empty($skip_generation)) — Claude son-çare içeriği hazırsa üretim atlandı ──

    if ($gen_error || !$content) {
        bw_flag_problem($book, $author, $pre_cover, $pre_year, 'gen_error', $gen_error ?: 'Boş içerik', $update_pid, $rewrite ? 'rewrite' : 'create');
        bw_update_book($batch_file, $idx, ['status'=>'error','error'=>$gen_error ?: 'Boş içerik']);
        return;
    }

    /* ── ÜRETİM REDDİ → KİTABI ATLA (post OLUŞTURMA) ──────────────────
       Model üretim baskısı altında bile eseri güvenilir tanımadığını
       bildirebiliyor: tek satır "CANNOT VERIFY: …" ya da "I cannot produce
       this…". Bu bir İÇERİK DEĞİL, reddin kendisidir.

       Eskiden bu tek satır boş sayılmıyor (yukarıdaki !$content tutmuyor),
       meta/kapak için boşa API harcanıyor ve yayın kapısı onu TASLAK yapıyordu
       — yani WP'de "CANNOT VERIFY…" gövdeli çöp bir taslak birikiyordu.
       Kullanıcının istediği açık: bilgi yoksa kitabı SİTEYE HİÇ EKLEME, atla.
       Reddi burada, meta/kapak/yayından ÖNCE yakalayıp kitabı 'error'
       (atlandı) işaretliyoruz; hiçbir post oluşmuyor, API da boşa gitmiyor. */
    require_once __DIR__ . '/_checks.php';
    if (($refusal = ca_check_refusal($content)) !== '') {
        if ($rewrite && $update_pid) {
            // Yeniden yaz modu: model eseri tanımıyor. YAYINDAN ALMA — yazı
            // yayında kalsın (link/trafik ölmesin). Gövdeye yer-tutucu koy
            // (eski uydurma içerik de temizlenir), sorunlu listeye ekle.
            $ph = bw_placeholder_html($book, $author);
            [$rp, $rc] = bw_wp("$wp_api/$ep/$update_pid", 'POST', ['content' => $ph, 'status' => 'publish'], $auth, 60);
            bw_flag_problem($book, $author, $pre_cover, $pre_year, 'placeholder', $refusal, $update_pid, 'rewrite');
            bw_update_book($batch_file, $idx, [
                'status'   => 'done',
                'post_id'  => $update_pid,
                'post_url' => $rp['link'] ?? '',
                'edit_url' => rtrim(WP_URL, '/') . '/wp-admin/post.php?post=' . $update_pid . '&action=edit',
                'error'    => 'model tanımadı → yer tutucu kondu (yayında)',
                'placeholder' => 1, 'method' => 'yer-tutucu',
            ]);
            return;
        }
        bw_flag_problem($book, $author, $pre_cover, $pre_year, 'refused', $refusal, 0, 'create');
        bw_update_book($batch_file, $idx, [
            'status' => 'error',
            'error'  => 'DOĞRULANAMADI: model eseri güvenilir tanımadı, atlandı — ' .
                        mb_substr($refusal, 0, 160),
        ]);
        return;
    }

    /* ── YENİDEN YAZ: YALNIZ GÖVDEYİ GÜNCELLE ────────────────────────────────
       Kullanıcı isteği: başlık (H1), kapak, açıklama (excerpt/meta), kategori,
       yazar AYNEN kalsın — hem hız hem gereksiz API yok. Sadece `content`
       güncellenir. Bu blok döndüğü için aşağıdaki meta/kapak/yazar/oluşturma
       kodunun hiçbiri rewrite modunda ÇALIŞMAZ. */
    if ($rewrite && $update_pid) {
        // İçerikteki H1'i at (tema post başlığını zaten H1 gösterir — orijinalde de böyle).
        $rw = preg_replace('/^# \*\*[^\n]+\*\*\n*/m', '', $content, 1);
        $rw = preg_replace('/^# [^\n]+\n*/m', '', $rw, 1);
        $rw_html = bw_md2html(ltrim($rw));

        // Mekanik kapı (BEDAVA, hızlı): yarım/kesik/prompt-dökümü yeni içeriği
        // yayına salma. Olgu denetimi (API) burada YOK — hız için; dürüstlük
        // prompt'u + ret kontrolü uyduruğu zaten büyük ölçüde eliyor.
        require_once __DIR__ . '/_verify.php';
        // Mekanik kapı (BEDAVA): içerik yarım/kesik/prompt-dökümü mü? ARTIK
        // TASLAĞA ÇEKMİYORUZ (hiçbir yazı yayından alınmaz). Kusurlu geldiyse
        // MEVCUT yazının üstüne YAZMA — eski (iyi olabilecek) gövdeyi koru,
        // sorunlu listeye ekle, sonra yeniden denenir. Böylece ne link ölür
        // ne de yerine bozuk metin geçer.
        if (tv_settings()['gate']) {
            $g = tv_gate($book, $author, $rw_html, ['min_words' => ($type === 'info' ? 120 : 300), 'skip_factcheck' => true]);
            if (!$g['pass']) {
                $why = implode(' | ', $g['reasons'] ?? []);   // HANGİ kontrol tetiklendi → görünür yap
                /* ── ESKİ GÖVDEYİ DE DENETLE (kritik düzeltme) ──────────────────
                   "Koru" kararı eskiden ESKİ gövdeyi hiç denetlemeden yayında
                   tutuyordu. Ama eski gövde çoğu zaman tam da temizlemeye
                   çalıştığımız eski-nesil içerik: scaffold etiketleri (LOCATE/
                   PRESENT/CLARIFY), uydurma, parça işaretleri. Onu yayında tutmak
                   yer tutucudan BETERDİR (okuyucu uydurmayı gerçek sanır).
                   Kural: eski gövde bu bedava mekanik kapıdan da geçemiyorsa
                   KORUMA — dürüst yer tutucu koy. Geçiyorsa (mekanik temizse)
                   koru; yine de sorunlu listede kalır, sonra yeniden denenir. */
                $og = bw_wp("$wp_api/$ep/$update_pid?_fields=content&context=view", 'GET', [], $auth, 30);
                $old_html  = is_array($og[0]) ? (string) ($og[0]['content']['rendered'] ?? '') : '';
                $old_flaws = bw_mech_flaws($old_html);
                if ($old_flaws) {
                    // Eski gövde de kusurlu → koruma, yer tutucu koy (dürüst).
                    $ph = bw_placeholder_html($book, $author);
                    [$rp] = bw_wp("$wp_api/$ep/$update_pid", 'POST', ['content' => $ph, 'status' => 'publish'], $auth, 60);
                    bw_flag_problem($book, $author, $pre_cover, $pre_year, 'placeholder', 'yeni kusurlu + ESKİ de kusurlu (' . implode(', ', array_slice($old_flaws, 0, 3)) . ') → yer tutucu', $update_pid, 'rewrite');
                    bw_update_book($batch_file, $idx, [
                        'status'   => 'done',
                        'post_id'  => $update_pid,
                        'post_url' => $rp['link'] ?? '',
                        'edit_url' => rtrim(WP_URL, '/') . '/wp-admin/post.php?post=' . $update_pid . '&action=edit',
                        'error'    => 'yeni kusurlu, eski gövde de kusurlu (' . implode(', ', array_slice($old_flaws, 0, 2)) . ') → yer tutucu (yayında)',
                        'placeholder' => 1, 'method' => 'yer-tutucu',
                    ]);
                    return;
                }
                /* ── ŞÜPHELİ REDO: ESKİYİ KORUMA (no_keep) ───────────────────────
                   Kullanıcı ŞÜPHELİ (yanlış-kaynak) yazıları REPLACE etmek için
                   yeniden yazdırıyorsa, eski gövde GÜVENİLMEZDİR — mekanik temiz
                   olsa bile (yanlış kaynaktan yazılmış olabilir). Onu "koru"
                   demek redo'nun amacını çöpe atar. Bu modda: yeni içerik
                   olmadıysa eskiyi tutma, DÜRÜST yer tutucu koy. */
                if (!empty($batch['no_keep'])) {
                    $ph = bw_placeholder_html($book, $author);
                    [$rp] = bw_wp("$wp_api/$ep/$update_pid", 'POST', ['content' => $ph, 'status' => 'publish'], $auth, 60);
                    bw_flag_problem($book, $author, $pre_cover, $pre_year, 'placeholder', 'şüpheli redo · yeni kusurlu (' . $why . ') → eski korunmadı, yer tutucu', $update_pid, 'rewrite');
                    bw_update_book($batch_file, $idx, [
                        'status'   => 'done', 'post_id' => $update_pid, 'post_url' => $rp['link'] ?? '',
                        'edit_url' => rtrim(WP_URL, '/') . '/wp-admin/post.php?post=' . $update_pid . '&action=edit',
                        'error'    => 'şüpheli redo: yeni içerik üretilemedi → yer tutucu (eski güvenilmez, korunmadı)',
                        'placeholder' => 1, 'method' => 'yer-tutucu',
                    ]);
                    return;
                }
                bw_flag_problem($book, $author, $pre_cover, $pre_year, 'gen_error', 'içerik kusurlu (kapı): ' . $why, $update_pid, 'rewrite');
                bw_update_book($batch_file, $idx, [
                    'status'   => 'done',
                    'post_id'  => $update_pid,
                    'edit_url' => rtrim(WP_URL, '/') . '/wp-admin/post.php?post=' . $update_pid . '&action=edit',
                    'error'    => 'içerik kusurlu → mevcut korundu (eski gövde mekanik temiz): ' . ($why ?: 'bilinmiyor') . ' (yeniden dene)',
                    'kept'     => 1, 'method' => 'eski-korundu',   // yeni içerik yazılMADI, eski gövde korundu → yeşil "başarılı" sayma
                ]);
                return;
            }
        }
        // Temiz: SADECE gövde + status=publish. status=publish KENDİNİ ONARIR —
        // eser daha önce (eski sürümde) yanlışlıkla taslağa çekildiyse yayına
        // döner. Başlık/kapak/kategori/yazar/meta'ya DOKUNULMAZ.
        [$rw_post, $rw_pc] = bw_wp("$wp_api/$ep/$update_pid", 'POST', ['content' => $rw_html, 'status' => 'publish'], $auth, 60);
        if ($rw_pc < 200 || $rw_pc >= 300) {
            bw_flag_problem($book, $author, $pre_cover, $pre_year, 'wp_error', 'WP ' . $rw_pc, $update_pid, 'rewrite');
            bw_update_book($batch_file, $idx, ['status' => 'error', 'error' => 'güncellenemedi: WP ' . $rw_pc]);
            return;
        }

        /* ── META TAZELE (rewrite) ───────────────────────────────────────────
           Eski meta description bazen gövdeyle uyumsuz/yanıltıcıydı (rewrite
           eskiden meta'ya hiç dokunmuyordu). Yeni gövde zaten DOĞRU ve kaynağa
           dayalı olduğundan, excerpt + meta description'ı ONDAN türetiriz:
           yanıltıcı değil, UYDURMA DEĞİL (yalnız yayınlanan metni özetler).
           Başlık/H1, kapak, kategori, yazar YİNE değişmez. Kısa çıktı → ucuz.
           ($batch['rewrite_meta']='0' ile kapatılır.) */
        if (($batch['rewrite_meta'] ?? '1') !== '0') {
            bw_touch_hb($batch_file, $idx);
            $body_snip = mb_substr(trim(strip_tags($rw_html)), 0, 1400);
            if ($body_snip !== '') {
                $mprov = in_array($api_provider, ['anthropic', 'gemini'], true) ? $api_provider : 'deepseek';
                $mprompt = "Based ONLY on the article text below (about the book \"{$book}\" by {$author}), return a JSON object:\n"
                    . "{\"excerpt\":\"...\",\"meta_description\":\"...\"}\n"
                    . "RULES: Each value is ONE complete sentence, factual and FAITHFUL to the article text, ending with a period, at most 150 characters. Do NOT introduce any fact, claim, or angle not present in the text. The excerpt and meta_description must differ from each other. Return ONLY the JSON, no extra text.\n\n"
                    . "=== ARTICLE TEXT ===\n{$body_snip}\n=== END ARTICLE TEXT ===";
                $mr = tv_ask($mprompt, 400, 90, $mprov);
                if (!empty($mr['ok']) && preg_match('/\{.*\}/s', (string) $mr['text'], $mm)) {
                    $mj = json_decode($mm[0], true);
                    if (is_array($mj)) {
                        $new_ex = !empty($mj['excerpt'])          ? bw_norm_sentence($mj['excerpt'], 155)          : '';
                        $new_md = !empty($mj['meta_description']) ? bw_norm_sentence($mj['meta_description'], 155) : '';
                        if ($new_ex !== '') bw_wp("$wp_api/$ep/$update_pid", 'POST', ['excerpt' => $new_ex], $auth, 30);
                        if ($new_md !== '') bw_wp("$wp_api/$ep/$update_pid", 'POST', ['meta' => ['_yoast_wpseo_metadesc' => $new_md]], $auth, 30);
                    }
                }
            }
        }

        // Temiz yayınlandı: varsa önceki 'sorunlu' kaydını GEÇERSİZ kıl (liste kendini onarır).
        bw_flag_problem($book, $author, $pre_cover, $pre_year, 'ok', 'yeniden yazıldı (' . $gen_method . ($gen_source ? " · $gen_source" : '') . ')', $update_pid, 'rewrite');
        bw_update_book($batch_file, $idx, [
            'status'   => 'done',
            'post_id'  => $update_pid,
            'post_url' => $rw_post['link'] ?? '',
            'edit_url' => rtrim(WP_URL, '/') . '/wp-admin/post.php?post=' . $update_pid . '&action=edit',
            'error'    => '',
            'method'   => $gen_method,
            'source'   => $gen_source,
            'words'    => str_word_count(strip_tags($rw_html)),   // yazılan özetin kelime sayısı (okuma süresi)
        ]);
        // KAYNAK ARŞİVİ: kaynak-temelli yazıldıysa kitap↔kaynak linkini kalıcı kaydet.
        if ($gen_method === 'kaynak-temelli') {
            bw_source_archive($update_pid, $book, $author, $gen_source, $gen_source_url, $gen_book_words, $wp_api, $ep, $auth, $eff_src_text);
        } else {
            // Kaynak-temelli DEĞİL ama içerik DEĞİŞTİ (bilgi/Claude/kaynaksız): arşivdeki
            // eski (ör. Wikisource) kaydı GÜNCELLE ki "şüpheli" listesinde bayat kalmasın.
            bw_source_mark($update_pid, $book, $author, $gen_method);
        }
        return;
    }

    bw_touch_hb($batch_file, $idx);   // yayın aşaması başlıyor — canlılığı tazele

    // ── Meta (excerpt, meta_desc, kategoriler, alıntılar) ──────────
    // Kategori paleti = sitedeki kategorilerin ÇEKİRDEK LİSTEYLE KESİŞİMİ.
    // Üretici yeni kategori OLUŞTURMAZ ve çekirdek dışına da çıkamaz; böylece
    // zamanla "Veterinary/Construction" gibi tekil kategoriler birikip kategori
    // enflasyonu yaratmaz (temizlik sonrası liste sabit kalır).
    // Bir worker çalışmasında bir kez çekilir, sonraki kitaplarda tekrar kullanılır.
    static $tls_all_cat_slugs = null;
    if ($tls_all_cat_slugs === null) {
        $tls_all_cat_slugs = [];
        for ($pg = 1; $pg <= 6; $pg++) {
            [$cc, $ccode] = bw_wp("$wp_api/categories?per_page=100&page=$pg&_fields=slug", 'GET', [], $auth, 15);
            if ($ccode !== 200 || empty($cc) || !is_array($cc)) break;
            foreach ($cc as $c) { if (!empty($c['slug'])) $tls_all_cat_slugs[] = $c['slug']; }
            if (count($cc) < 100) break;
        }
    }
    // REST başarısız olursa güvenli temel liste (base ~100).
    $cats_fallback = 'philosophy,philosophy_of_religion,philosophy_of_science,philosophy_of_mind,philosophy_of_language,history_of_science,critical_theory,cultural_studies,ethics,metaphysics,epistemology,logic,aesthetics,political_philosophy,history_of_philosophy,religion,theology,systematic_theology,christian_theology,islamic_theology,christianity,islam,judaism,buddhism,hinduism,atheism,agnosticism,history,world_history,ancient_history,medieval_history,modern_history,military_history,cultural_history,biography,autobiography,memoir,literature,classic_literature,world_literature,poetry,drama,novel,fiction,historical_fiction,science_fiction,dystopian_fiction,fantasy,horror,mystery,detective_fiction,romance,adventure,psychology,cognitive_psychology,social_psychology,psychoanalysis,sociology,anthropology,politics,political_science,economics,microeconomics,macroeconomics,education,law,international_law,science,physics,astronomy,chemistry,mathematics,statistics,biology,evolution,genetics,medicine,neuroscience,public_health,technology,computers,artificial_intelligence,programming,data_science,art,art_history,music,music_history,architecture,design,photography,film,theatre,geography,travel,culture,mythology,folklore,children,young_adult,self_help,personal_development,business,management,marketing,entrepreneurship';
    // Paleti çekirdekle sınırla: sitede olsa bile çekirdek dışı slug sunulmaz.
    // (Slug'lar WP'de tire, çekirdek listede alt çizgi olabilir → normalize et.)
    $core_norm = [];
    foreach (explode(',', $cats_fallback) as $cs) {
        $core_norm[str_replace('_', '-', trim($cs))] = trim($cs);
    }
    $palette = [];
    foreach ($tls_all_cat_slugs ?: [] as $s) {
        if (isset($core_norm[str_replace('_', '-', strtolower($s))])) $palette[] = $s;
    }
    $cats_list = $palette ? implode(',', $palette) : $cats_fallback;

    $snippet = mb_substr(strip_tags($content), 0, 1500);
    $mp = "Return ONLY valid JSON (no extra text, no markdown fences):\n"
        . "{\"excerpt\":\"...\",\"meta_description\":\"...\",\"categories\":[\"slug1\",\"slug2\"],\"quotes\":[{\"text\":\"verbatim quote\",\"source\":\"section name\"}]}\n"
        . "CRITICAL: excerpt and meta_description must each be ONE COMPLETE sentence, fully finished (ending with a period), and MUST NOT exceed 150 characters. Never cut off mid-sentence. If needed, write shorter.\n"
        . "Pick 2-5 category slugs from: {$cats_list}\n"
        . "For quotes: only truly verbatim passages; 0-2 quotes max.\n"
        . "Book: \"{$book}\" by {$author}\n\n{$snippet}";

    // Meta/kategori isteği. max_tokens dar tutulursa JSON yarıda kesilir →
    // json_decode boş döner → kategori bulunamaz → her kitap "General"e düşer.
    // Bu yüzden bütçe geniş, JSON çıkarımı toleranslı ve 2 denemeli.
    $meta = [];
    for ($mtry = 1; $mtry <= 2; $mtry++) {
        $mch = curl_init(DEEPSEEK_API_URL);
        curl_setopt_array($mch, [
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>60,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
            CURLOPT_POSTFIELDS=>json_encode([
                'model'           => (in_array(DEEPSEEK_MODEL,['deepseek-chat','deepseek-reasoner'],true)?'deepseek-v4-flash':DEEPSEEK_MODEL),
                'max_tokens'      => 3000,
                'response_format' => ['type' => 'json_object'],
                'thinking'        => ['type' => 'disabled'],   // meta = kısa JSON, düşünme gereksiz + yavaş
                'messages'        => [['role'=>'user','content'=>$mp]],
            ]),
        ]);
        $meta_raw = curl_exec($mch); $merr = curl_error($mch); curl_close($mch);

        $meta_text = json_decode((string)$meta_raw, true)['choices'][0]['message']['content'] ?? '';
        $meta_text = trim(preg_replace('/```json|```/', '', (string)$meta_text));
        $meta = json_decode($meta_text, true) ?: [];
        // Model JSON'un başına/sonuna laf eklediyse gövdedeki ilk {...} bloğunu çek
        if (!$meta && $meta_text !== '' && preg_match('/\{.*\}/s', $meta_text, $mm)) {
            $meta = json_decode($mm[0], true) ?: [];
        }
        if ($meta) break;                      // başarılı
        if ($mtry < 2) { bw_touch_hb($batch_file, $idx); sleep(2); }
    }
    if (!is_array($meta)) $meta = [];

    // excerpt & meta_description'ı DAİMA normalize et: 155 karaktere sığdır VE
    // mutlaka tam bir cümle (nokta/!/? ile biten) olsun. AI kısa ama yarıda
    // kesik bir cümle döndürse bile ("...the role of the") burada düzeltilir —
    // böylece panelde "Kesilmiş / Çok Uzun" olarak işaretlenmez.
    foreach (['excerpt', 'meta_description'] as $mf) {
        if (!empty($meta[$mf])) {
            $meta[$mf] = bw_norm_sentence($meta[$mf], 155);
        }
    }

    // ── Kapak bul (cURL) ───────────────────────────────────────────
    // Builder'da zaten bulunmuş/doğrulanmış kapak varsa onu kullan; yoksa ara.
    $cover_url = $pre_cover;
    $bw_http_get = function($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0 (ThetelosBot)'],
        ]);
        $r = curl_exec($ch);
        $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($c >= 200 && $c < 300) ? $r : null;
    };

    // Kapak: queue-build'den gelen OpenLibrary kapağı yoksa OL'dan ara
    if (!$cover_url) {
        $ol = json_decode((string)$bw_http_get(
            'https://openlibrary.org/search.json?title=' . urlencode($search_book)
            . '&author=' . urlencode($author) . '&limit=5&fields=cover_i,title,author_name'
        ), true);
        foreach ($ol['docs'] ?? [] as $doc) {
            if (empty($doc['cover_i'])) continue;
            $cover_url = 'https://covers.openlibrary.org/b/id/' . $doc['cover_i'] . '-L.jpg';
            break;
        }
    }

    // Kitabın yayın yılı: ÖNCE listeden gelen değer (zaten OpenLibrary kaynaklı),
    // yoksa OpenLibrary'den ara. Postta "Published <yıl>" göstermek için.
    $pub_year = preg_match('/^\d{3,4}$/', $pre_year) ? $pre_year : '';
    if ($pub_year === '') {
        $oly = json_decode((string)$bw_http_get(
            'https://openlibrary.org/search.json?title=' . urlencode($search_book)
            . '&author=' . urlencode($author) . '&limit=1&fields=first_publish_year'
        ), true);
        if (!empty($oly['docs'][0]['first_publish_year'])) {
            $pub_year = (string)(int)$oly['docs'][0]['first_publish_year'];
        }
    }

    // ── WordPress'e yayınla ────────────────────────────────────────
    bw_touch_hb($batch_file, $idx);   // meta+kapak bitti — yayın öncesi tazele
    // Onaylı kategori kümesi ($cats_list). YENİ KATEGORİ ASLA OLUŞTURULMAZ —
    // AI listede olmayan bir slug uydurursa (ör. "confucianism") atlanır.
    $allowed = array_fill_keys( array_map('trim', explode(',', $cats_list)), true );

    /* ── KAYNAK 1: OpenLibrary konuları (kütüphane sınıflandırması) ──────
       Kategoriyi AI'ın yorumuna bırakmak yerine önce kitabın OpenLibrary'deki
       KONU etiketlerine bakarız: bunlar kütüphaneciler tarafından atanmış
       gerçek sınıflandırma verisidir. Konular yalnızca ONAYLI listeye
       eşlenir; eşleşmeyen konu atılır, yeni kategori açılmaz.
       Yeterli eşleşme çıkarsa AI'ın önerisine hiç gerek kalmaz. */
    $ol_slugs = [];
    $ol_raw = json_decode((string) $bw_http_get(
        'https://openlibrary.org/search.json?title=' . urlencode($search_book)
        . ($author !== '' ? '&author=' . urlencode($author) : '')
        . '&limit=1&fields=subject'
    ), true);
    $subjects = $ol_raw['docs'][0]['subject'] ?? [];
    if (is_array($subjects) && $subjects) {
        // Konu metnini tek gövdede birleştir → onaylı slug'ları içinde ara
        $hay = ' ' . strtolower(preg_replace('/[^a-zA-Z0-9]+/', ' ', implode(' ', array_slice($subjects, 0, 60)))) . ' ';
        foreach (array_keys($allowed) as $cslug) {
            $words = trim(str_replace(['_', '-'], ' ', $cslug));
            if ($words === '' || strlen($words) < 4) continue;
            if (strpos($hay, ' ' . $words . ' ') !== false) $ol_slugs[] = $cslug;
        }
        $ol_slugs = array_slice(array_values(array_unique($ol_slugs)), 0, 5);
    }

    /* Kaynak sırası: OpenLibrary konuları → (yetersizse) AI önerisi. */
    $picked_slugs = count($ol_slugs) >= 2 ? $ol_slugs
                  : array_merge($ol_slugs, (array)($meta['categories'] ?? []));

    $cat_ids = [];
    foreach ($picked_slugs as $raw_slug) {
        $slug = strtolower(trim(preg_replace('/[\s_]+/', '-', $raw_slug)));
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        if (!$slug) continue;
        $slug_u = str_replace('-', '_', $slug);
        // Onaylı listede değilse: atla, OLUŞTURMA.
        if (empty($allowed[$slug]) && empty($allowed[$slug_u])) continue;
        // Var olan kategoriyi bul ('-' ve '_' varyantı); yoksa oluşturmadan geç.
        [$cats] = bw_wp("$wp_api/categories?slug=" . urlencode($slug) . '&per_page=1', 'GET', [], $auth);
        if (!empty($cats[0]['id'])) { $cat_ids[] = $cats[0]['id']; continue; }
        [$cats2] = bw_wp("$wp_api/categories?slug=" . urlencode($slug_u) . '&per_page=1', 'GET', [], $auth);
        if (!empty($cats2[0]['id'])) { $cat_ids[] = $cats2[0]['id']; continue; }
    }
    $cat_ids = array_values(array_unique($cat_ids));

    // Son çare: hiç uygun kategori çıkmazsa "General" kovasına ata (nadir).
    // Bu tek sistem kategorisi yoksa bir kez oluşturulur; AI slug'ları YİNE
    // oluşturulmaz — "General" AI'a seçenek olarak sunulmaz, yalnız buraya düşer.
    if (!$cat_ids) {
        [$gen] = bw_wp("$wp_api/categories?slug=general&per_page=1", 'GET', [], $auth);
        if (!empty($gen[0]['id'])) {
            $cat_ids[] = (int) $gen[0]['id'];
        } else {
            [$ngen] = bw_wp("$wp_api/categories", 'POST', ['name' => 'General', 'slug' => 'general'], $auth);
            if (!empty($ngen['id'])) $cat_ids[] = (int) $ngen['id'];
        }
    }
    bw_touch_hb($batch_file, $idx);   // kategori çözümü bitti — canlılığı tazele

    $clean = $content;
    $clean = preg_replace('/^# \*\*[^\n]+\*\*\n*/m', '', $clean, 1);
    $clean = preg_replace('/^# [^\n]+\n*/m', '', $clean, 1);
    $clean = ltrim($clean);

    $post_title = $author ? "$book - $author" : $book;

    // Aynı başlıkla zaten yayınlanmış bir post varsa tekrar oluşturma.
    // Slug bazlı kontrol: title karşılaştırmasından daha güvenilir.
    $expected_slug = mb_strtolower($post_title, 'UTF-8');
    $expected_slug = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $expected_slug) ?: $expected_slug;
    $expected_slug = preg_replace('/[^a-z0-9]+/', '-', $expected_slug);
    $expected_slug = trim($expected_slug, '-');
    // YENİDEN YAZ modunda bu "zaten var → atla" bloğu ÇALIŞMAZ: mevcut yazıyı
    // zaten en başta bulduk ($update_pid) ve amaç onu GÜNCELLEMEK.
    [$slug_posts, $slug_code] = $rewrite ? [null, 0]
        : bw_wp("$wp_api/$ep?slug=" . urlencode($expected_slug) . '&status=any&per_page=1', 'GET', [], $auth, 15);
    if (!$rewrite && $slug_code === 200 && !empty($slug_posts[0]['id'])) {
        $pid = $slug_posts[0]['id'];
        // Mevcut post yazara bağlı değilse bağla — aksi halde yazar sayfasında görünmez.
        $existing_authors = $slug_posts[0]['authors'] ?? [];
        if ($author && (!is_array($existing_authors) || count($existing_authors) === 0)) {
            [$terms] = bw_wp("$wp_api/authors?search=" . urlencode($author) . '&per_page=10', 'GET', [], $auth);
            $tid = null;
            foreach ($terms ?? [] as $t) {
                if (strtolower($t['name']) === strtolower($author)) { $tid = $t['id']; break; }
            }
            if (!$tid) {
                [$nt] = bw_wp("$wp_api/authors", 'POST', ['name'=>$author], $auth);
                $tid = $nt['id'] ?? null;
            }
            if ($tid) bw_wp("$wp_api/$ep/$pid", 'POST', ['authors'=>[$tid]], $auth);
        }
        bw_update_book($batch_file, $idx, [
            'status'   => 'done',
            'post_id'  => $pid,
            'post_url' => $slug_posts[0]['link'] ?? '',
            'edit_url' => rtrim(WP_URL,'/') . '/wp-admin/post.php?post=' . $pid . '&action=edit',
            'cover_set'=> false,
            'error'    => 'duplicate_skipped',
        ]);
        return;
    }

    $body_html = bw_md2html($clean);

    /* ── YAYIN KAPISI ─────────────────────────────────────────────────────
       Hacmin tamamı buradan geçiyor; kapı asıl burada gerekli. Kusurlu ya da
       eseri yanlış anlatan metin YAYINA ÇIKMAZ, taslak olarak kaydedilir ve
       sebebi hem batch kaydına hem yazının meta'sına yazılır.

       Kapı yalnızca "publish" isteniyorsa çalışır: zaten taslak kaydedilecek
       bir metni doğrulamak için API harcamanın anlamı yok. */
    $gate_report = null;
    if ($post_status === 'publish') {
        require_once __DIR__ . '/_checks.php';
        require_once __DIR__ . '/_verify.php';
        if (tv_settings()['gate']) {
            bw_touch_hb($batch_file, $idx);   // doğrulama sürerken worker ölü sanılmasın
            $g = tv_gate($book, $author, $body_html, ['min_words' => ($type === 'info' ? 120 : 300)]);
            $gate_report = $g['report'];
            if (!$g['pass']) $post_status = 'draft';
        }
    }

    $pb = [
        'title'   => $post_title,
        'content' => $body_html,
        'excerpt' => $meta['excerpt'] ?? '',
        'status'  => $post_status,
    ];
    if ($ep !== 'analysis' && $cat_ids) $pb['categories'] = $cat_ids;

    // YENİDEN YAZ: mevcut yazının ÜSTÜNE yaz (POST /$ep/$id). Aksi halde yeni oluştur.
    if ($rewrite && $update_pid) {
        [$post, $pc] = bw_wp("$wp_api/$ep/$update_pid", 'POST', $pb, $auth, 60);
    } else {
        [$post, $pc] = bw_wp("$wp_api/$ep", 'POST', $pb, $auth, 60);
    }
    if ($pc < 200 || $pc >= 300) {
        $err = $post['message'] ?? "WP HTTP $pc";
        bw_update_book($batch_file, $idx, ['status'=>'error','error'=>$err]);
        return;
    }
    $pid = $post['id'] ?? ($update_pid ?: 0);
    // post_id'yi hemen kaydet — crash sonrası recovery bu kitabı tekrar işlemesin
    bw_update_book($batch_file, $idx, ['post_id' => $pid]);

    // Yazar
    if ($author) {
        [$terms] = bw_wp("$wp_api/authors?search=" . urlencode($author) . '&per_page=10', 'GET', [], $auth);
        $tid = null; $existing_desc = '';
        foreach ($terms ?? [] as $t) {
            if (strtolower($t['name']) === strtolower($author)) { $tid = $t['id']; $existing_desc = $t['description'] ?? ''; break; }
        }
        if (!$tid || !$existing_desc) {
            $bio_prompt = "Write a concise 2-3 sentence biography of \"{$author}\" for a philosophy/literature website. Focus on main works and intellectual contributions. English, encyclopedic.";
            $bch = curl_init(DEEPSEEK_API_URL);
            curl_setopt_array($bch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>20,
                CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
                CURLOPT_POSTFIELDS=>json_encode(['model'=>(in_array(DEEPSEEK_MODEL,['deepseek-chat','deepseek-reasoner'],true)?'deepseek-v4-flash':DEEPSEEK_MODEL),'max_tokens'=>200,'messages'=>[['role'=>'user','content'=>$bio_prompt]]]),
            ]);
            $bio_raw = curl_exec($bch); curl_close($bch);
            $bio = json_decode($bio_raw,true)['choices'][0]['message']['content'] ?? '';
            if (!$tid) {
                [$nt] = bw_wp("$wp_api/authors", 'POST', ['name'=>$author,'description'=>$bio], $auth);
                $tid = $nt['id'] ?? null;
                // Race condition: another worker may have just created this term — retry GET
                if (!$tid) {
                    usleep(300000);
                    [$retry] = bw_wp("$wp_api/authors?search=" . urlencode($author) . '&per_page=10', 'GET', [], $auth);
                    foreach ($retry ?? [] as $t) {
                        if (strtolower($t['name']) === strtolower($author)) { $tid = $t['id']; break; }
                    }
                }
            } elseif ($bio) {
                bw_wp("$wp_api/authors/$tid", 'POST', ['description'=>$bio], $auth);
            }
        }
        if ($tid) bw_wp("$wp_api/$ep/$pid", 'POST', ['authors'=>[$tid]], $auth);
    }

    if (!empty($meta['meta_description'])) {
        bw_wp("$wp_api/$ep/$pid", 'POST', ['meta'=>['_yoast_wpseo_metadesc'=>$meta['meta_description']]], $auth);
    }

    // Yoast odak anahtar kelimesi — SEO analiz skoru (kırmızı/yeşil) bunun
    // üzerinden hesaplanır; ayarlanmazsa Yoast otomatik "kırmızı" gösterir.
    // Temiz, parantezsiz kitap adını kullan.
    $focus_kw = trim($search_book !== '' ? $search_book : $book);
    if ($focus_kw !== '') {
        bw_wp("$wp_api/$ep/$pid", 'POST', ['meta'=>['_yoast_wpseo_focuskw'=>$focus_kw]], $auth);
    }

    // Yıl bulunduysa onu, bulunamadıysa "(–)" işareti olarak '-' kaydet.
    bw_wp("$wp_api/$ep/$pid", 'POST', ['meta'=>['_tls_pub_year'=>($pub_year !== '' ? $pub_year : '-')]], $auth);

    bw_wp("$wp_api/$ep/$pid", 'POST', ['meta'=>['_tls_disable_quotes'=>'1']], $auth);
    $clean_quotes = [];
    foreach ($meta['quotes'] ?? [] as $q) {
        $t = trim($q['text'] ?? ''); $s = trim($q['source'] ?? '');
        if ($t) $clean_quotes[] = ['text'=>$t,'source'=>$s];
    }
    if ($clean_quotes) bw_wp("$wp_api/$ep/$pid", 'POST', ['meta'=>['_tls_quotes'=>$clean_quotes]], $auth);

    // Kapak yükle
    $cover_set = false;
    if ($cover_url) {
        $allowed = ['books.google.com','covers.openlibrary.org',
                    'lh3.googleusercontent.com','lh4.googleusercontent.com','lh5.googleusercontent.com','lh6.googleusercontent.com'];
        $host = parse_url($cover_url, PHP_URL_HOST);
        if (in_array($host, $allowed)) {
            $img = $bw_http_get($cover_url);
            if ($img && strlen($img) > 2000) {
                $fn = preg_replace('/[^a-z0-9]/', '-', strtolower($book)) . '.jpg';
                $cm = curl_init("$wp_api/media");
                curl_setopt_array($cm, [
                    CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>30,
                    CURLOPT_HTTPHEADER=>['Authorization: '.$auth,"Content-Disposition: attachment; filename=\"$fn\"",'Content-Type: image/jpeg'],
                    CURLOPT_POSTFIELDS=>$img,
                ]);
                $mr  = curl_exec($cm); curl_close($cm);
                $mid = json_decode($mr, true)['id'] ?? null;
                if ($mid) {
                    bw_wp("$wp_api/$ep/$pid", 'POST', ['featured_media'=>$mid], $auth);
                    $cover_set = true;
                }
            }
        }
    }

    // Kapı raporu yazıya iliştirilir: neden taslakta kaldığı sonradan görünsün.
    if ($gate_report) {
        bw_wp("$wp_api/$ep/$pid", 'POST',
              ['meta' => ['_tls_gate' => json_encode($gate_report, JSON_UNESCAPED_UNICODE)]], $auth);
    }

    $gate_reasons = $gate_report['reasons'] ?? [];

    // KAYNAK ARŞİVİ (sıfırdan üretim yolu): kitap↔kaynak linkini kalıcı kaydet.
    if ($gen_method === 'kaynak-temelli') {
        bw_source_archive($pid, $book, $author, $gen_source, $gen_source_url, $gen_book_words, $wp_api, $ep, $auth, (string) ($batch['source_text'] ?? ''));
    }

    bw_update_book($batch_file, $idx, [
        'status'    => 'done',
        'post_id'   => $pid,
        'post_url'  => $post['link'] ?? '',
        'edit_url'  => rtrim(WP_URL,'/') . '/wp-admin/post.php?post=' . $pid . '&action=edit',
        'cover_set' => $cover_set,
        'method'    => $gen_method,
        'source'    => $gen_source,
        'words'     => str_word_count(strip_tags($body_html)),   // yazılan özetin kelime sayısı

        // Kapı blokladıysa bu kitap YAYINDA DEĞİL. Sessizce "done" yazmak
        // "yayınlandı" sanmaya yol açardı; sebep kuyrukta görünür.
        'gated'     => $gate_reasons ? 1 : 0,
        'gate'      => $gate_reasons,
        // Parça eksik kaldıysa kayda düş: panelde ⚠ ile görünür, hangi kitapların
        // kısa kaldığı fark edilir ve istenirse yeniden üretilir.
        'error'     => $gate_reasons
                     ? ('KAPI: ' . implode(' · ', array_slice($gate_reasons, 0, 2)))
                     : $part_warn,
    ]);
}

/* ════════════════ DRAIN DÖNGÜSÜ ════════════════
 * Tüm bekleyen kitaplar bitene (ya da iptal/duraklat) kadar işle.
 */
/* DeepSeek yoğun saati mi? (UTC 01-04 ve 06-10 → 2× fiyat)
   Panelden kapatılabilir: jobs/.peak-skip = "0". Dosya yoksa koruma AÇIK.
   NOT: Bu kontrol cron-tick'te de var; ama worker kendini zincirlediği için
   (bw_spawn_successor) cron'u baypas ediyordu → koruma burada da şart. */
/**
 * Bir batch'in GERÇEK parça sayısı.
 *
 * Model, tek bir istekte ne istenirse istensin ~1800 kelimeden sonra kendini
 * toparlayıp bitiriyor. "8000 kelime / 2 parça" seçildiğinde her parçadan 4000
 * kelime isteniyor, gelen ~2000 oluyordu; yani kelime kaydırağı en üstteyken
 * bile yazılar hedefin yarısı uzunlukta çıkıyordu. Bu yüzden parça sayısı
 * hedefe göre otomatik yükseltilir — kullanıcının seçtiği değer taban sayılır,
 * parça başına istenen kelime hiçbir zaman ~1800'ü geçmez.
 */
function bw_effective_parts($batch) {
    $target = max(500, min(8000, (int)($batch['max_tokens'] ?? 3000)));
    $picked = max(1, min(6, (int)($batch['parts'] ?? 2)));
    return max($picked, min(6, (int)ceil($target / 1800)));
}

function bw_peak_now() {
    if (defined('TLS_DEEPSEEK_SKIP_PEAK') && !TLS_DEEPSEEK_SKIP_PEAK) return false;
    $flag = dirname(__DIR__) . '/jobs/.peak-skip';
    if (file_exists($flag) && trim((string)@file_get_contents($flag)) === '0') return false;
    // 23.08.2026'dan beri HAFTA SONU (Cmt/Paz, Pekin/UTC+8) TÜM GÜN off-peak → duraklatma yok.
    $bj_dow = (int) gmdate('N', time() + 8 * 3600);   // 6=Cmt, 7=Paz (Pekin)
    if ($bj_dow >= 6) return false;
    $h = (int) gmdate('G');
    return ($h >= 1 && $h < 4) || ($h >= 6 && $h < 10);
}

$processed = 0;
$start     = time();
$budget    = 70;          // saniye — sunucu uzun süreçleri öldürmeden önce kendini yenile
$reason    = 'no_more';
while (true) {
    // Yoğun saat başladıysa üretimi burada bırak: zincirleme yapılmaz, wk dosyası
    // temizlenir; saat normale dönünce cron-tick kaldığı yerden devam ettirir.
    if (bw_peak_now()) { $reason = 'peak'; break; }
    [$idx, $batch] = bw_claim_next($batch_file);
    if ($idx === -1) { $reason = 'done';      break; }      // bekleyen yok
    if ($idx === -3) { $reason = 'cancelled'; break; }      // iptal
    if ($idx === -4) { $reason = 'paused';    break; }      // duraklat
    if ($idx === -2) { usleep(400000); continue; }          // başka worker kilitledi
    // Her kitap için süreyi sıfırla. Limit parça sayısına göre ölçeklenir:
    // her parça için DeepSeek timeout'u 280sn olabilir; 660sn sabiti 3-4 parçalı
    // kitaplarda yetmiyor ve PHP süreci kitabı ortada öldürüyordu → kitap
    // "processing"de asılı kalıyordu. parts*300 + 240sn (meta/bio/kapak/WP payı).
    $bk_parts = bw_effective_parts($batch);
    set_time_limit($bk_parts * 300 + 240);
    bw_process_book($batch_file, $idx, $batch, $auth, $wp_api);
    $processed++;
    if ($processed >= 5000)            { $reason = 'limit';  break; }   // güvenlik üst sınırı
    if (time() - $start >= $budget)    { $reason = 'budget'; break; }   // süre doldu → zincirle
    sleep(2);                                               // kitaplar arası kısa bekleme
}

/* Süre/limit nedeniyle çıktıysak ve hâlâ bekleyen iş varsa kendi yerimize
   yeni bir worker başlat; böylece sunucu bizi öldürse bile batch durmaz.
   Zincirlemede wk dosyasını SİLMİYORUZ: eski worker'ın dosyası 180 sn daha
   taze kalır, bu sürede halefi de kendi dosyasını oluşturur → proc sayısı
   düşmez → checkActiveJobs gereksiz worker açmaz. */
if ($reason === 'budget' || $reason === 'limit') {
    if ($g_worker_hb) @touch($g_worker_hb);   // zincirlemeden önce tazele
    bw_spawn_successor($batch_id);
} else {
    // Temiz çıkış (done / cancelled / paused): wk dosyasını temizle
    if ($g_worker_hb) @unlink($g_worker_hb);
}

echo json_encode(['status'=>'no_more','processed'=>$processed,'reason'=>$reason]);
