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
function bw_clear_hb($batch_file, $idx) {
    @unlink(bw_hb_path($batch_file, $idx));
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
function bw_wp($url, $method, $body, $auth, $timeout = 30) {
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
    return [json_decode($r, true), $c];
}

function bw_update_book($batch_file, $idx, $updates) {
    $lk = bw_lock($batch_file);
    if (!$lk) return;
    $batch = json_decode((string)@file_get_contents($batch_file), true);
    if ($batch) {
        foreach ($updates as $k => $v) $batch['books'][$idx][$k] = $v;
        $done = $ok = $failed = 0;
        foreach ($batch['books'] as $b) {
            if (in_array($b['status'], ['done','error'])) $done++;
            if ($b['status'] === 'done')  $ok++;
            if ($b['status'] === 'error') $failed++;
        }
        $batch['done']   = $done;
        $batch['ok']     = $ok;
        $batch['failed'] = $failed;
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

function bw_clean_content($text) {
    $text = preg_replace('/%%PART[12]_(?:END|START)%%/i', '', $text);
    $text = preg_replace('/%%PART_END%%/i', '', $text);
    $text = preg_replace('/\[Note:[^\]]*\]/i', '', $text);
    $text = preg_replace('/\[Already[^\]]*\]/i', '', $text);
    $text = preg_replace('/\[This was[^\]]*\]/i', '', $text);
    $text = preg_replace('/\[.*?already.*?\]/is', '', $text);
    $text = preg_replace('/\[.*?covered.*?\]/is', '', $text);
    $text = preg_replace('/\[.*?Part 1.*?\]/is', '', $text);
    $text = preg_replace('/\[.*?structure.*?\]/is', '', $text);
    $text = preg_replace('/\n{4,}/', "\n\n\n", $text);
    return trim($text);
}

function bw_md2html($text) {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace_callback('/\*\*(.+?)\*\*/s', function($m) {
        return strpos($m[1], "\n") === false ? '<strong>'.$m[1].'</strong>' : $m[1];
    }, $text);
    $text = preg_replace(
        ['/^#{1} \*\*(.+?)\*\*/m','/^#{2} \*\*(.+?)\*\*/m','/^#{3} \*\*(.+?)\*\*/m',
         '/^# (.+)/m','/^## (.+)/m','/^### (.+)/m'],
        ['<h1><strong>$1</strong></h1>','<h2><strong>$1</strong></h2>','<h3><strong>$1</strong></h3>',
         '<h1>$1</h1>','<h2>$1</h2>','<h3>$1</h3>'],
        $text
    );
    $lines = explode("\n", $text);
    $html = ''; $buf = []; $bqbuf = [];
    $fl  = function() use (&$buf,  &$html) { if ($buf)   { $html .= '<p>'  . implode(' ', $buf)   . "</p>\n"; $buf   = []; } };
    $fbq = function() use (&$bqbuf,&$html) { if ($bqbuf) { $html .= '<blockquote>' . implode(' ', $bqbuf) . "</blockquote>\n"; $bqbuf = []; } };
    foreach ($lines as $l) {
        $l = trim($l);
        if (!$l) { $fl(); $fbq(); continue; }
        if (preg_match('/^(?:&gt;|>)\s*(.*)$/', $l, $m)) { $fl(); $bqbuf[] = $m[1]; continue; }
        if (preg_match('/^<(h[1-6]|hr)/', $l)) { $fl(); $fbq(); $html .= $l . "\n"; continue; }
        $fbq(); $buf[] = $l;
    }
    $fl(); $fbq();
    return $html;
}

function bw_fraction($n) {
    switch ($n) { case 2: return 'half'; case 3: return 'third'; case 4: return 'quarter'; default: return "1/{$n}"; }
}
function bw_part_instruction($k, $n, $headings, $tail, $part_words) {
    if ($n <= 1) {
        return "\nTarget length: approximately {$part_words} words.";
    }
    $frac = bw_fraction($n);
    $covered = '';
    foreach ($headings as $h) $covered .= "   ✗ {$h}\n";

    if ($k === 1) {
        return "\n\n=== MULTI-PART GENERATION (PART 1 of {$n}) ===\n"
             . "You are writing PART 1 of {$n} of a single continuous piece.\n"
             . "• Begin with the H1 (# **Title — Author**) then the H2 (## **Subtitle**), then the first ### sections in order.\n"
             . "• Cover approximately the first {$frac} of the complete work (~{$part_words} words for this part).\n"
             . "• Develop every section fully per the format rules. Do NOT write any conclusion — more parts follow.\n"
             . "• End naturally at a ### section boundary. Your ABSOLUTE FINAL LINE must be exactly:\n%%PART_END%%";
    }
    if ($k < $n) {
        return "\n\n=== MULTI-PART GENERATION (PART {$k} of {$n}) ===\n"
             . "You are writing PART {$k} of {$n} — a direct, seamless continuation of the text already written.\n"
             . "STRICT RULES:\n"
             . "1. DO NOT rewrite the H1 or H2 heading.\n"
             . "2. The following sections are FULLY COMPLETE — do NOT revisit, repeat, summarize, or expand them:\n{$covered}"
             . "3. Continue with the NEXT new ### sections not listed above. Cover roughly the next {$frac} of the work (~{$part_words} words).\n"
             . "4. Do NOT write a conclusion — there are still more parts after this one.\n"
             . "5. Maintain the exact same voice, depth, and format as before.\n"
             . "6. End at a ### section boundary. Your ABSOLUTE FINAL LINE must be exactly:\n%%PART_END%%\n"
             . "\nThe text so far ended here (continue seamlessly from this exact point — do NOT repeat it):\n...{$tail}";
    }
    return "\n\n=== MULTI-PART GENERATION (FINAL PART {$n} of {$n}) ===\n"
         . "You are writing the FINAL PART ({$n} of {$n}) — a direct, seamless continuation.\n"
         . "STRICT RULES:\n"
         . "1. DO NOT rewrite the H1 or H2 heading.\n"
         . "2. The following sections are FULLY COMPLETE — do NOT revisit, repeat, or expand them:\n{$covered}"
         . "3. Continue with ALL remaining ### sections and COMPLETE the work fully.\n"
         . "4. Maintain the exact same voice, depth, and format as before.\n"
         . "5. Apply the closing rule: end with the final substantive point — no summary paragraph, no closing sentence.\n"
         . "\nThe text so far ended here (continue seamlessly from this exact point — do NOT repeat it):\n...{$tail}";
}

/* ════════════════ Bir kitabı baştan sona işle ════════════════ */
function bw_process_book($batch_file, $idx, $batch, $auth, $wp_api) {
    $book         = $batch['books'][$idx]['book_title'];
    $author       = $batch['books'][$idx]['author_name'];
    $pre_cover    = trim($batch['books'][$idx]['cover_url'] ?? '');
    $pre_year     = trim((string)($batch['books'][$idx]['pub_year'] ?? '')); // listeden gelen yayın yılı
    // Dış aramalar (Google Books / OpenLibrary / dedup) için başlığın
    // sonundaki "(Orijinal Ad)" parantezini at — yoksa eşleşme bulunamıyor.
    $search_book  = trim(preg_replace('/\s*\([^()]*\)\s*$/', '', $book));
    if ($search_book === '') $search_book = $book;
    $type         = $batch['type'];
    $target_words = max(500, min(8000, (int)$batch['max_tokens']));
    $post_status  = $batch['post_status'];
    $api_provider = $batch['api_provider'] ?? 'deepseek';
    $parts        = max(1, min(4, (int)($batch['parts'] ?? 2)));
    $ep           = $type === 'analysis' ? 'analysis' : 'posts';

    // ── Prompt ────────────────────────────────────────────────────
    $prompts  = file_exists(PROMPTS_FILE) ? json_decode(file_get_contents(PROMPTS_FILE), true) : [];
    $template = trim($prompts[$type] ?? '');
    if (!$template) {
        bw_update_book($batch_file, $idx, ['status'=>'error','error'=>'Prompt boş']);
        return;
    }

    // ── İçerik üretimi ────────────────────────────────────────────
    $content   = '';
    $gen_error = '';

    $accumulated = '';
    $part_words  = (int)ceil($target_words / max(1, $parts));

    for ($k = 1; $k <= $parts; $k++) {
        bw_touch_hb($batch_file, $idx);   // ucuz canlılık damgası (her parçadan önce)
        $headings = [];
        if ($accumulated !== '') {
            preg_match_all('/^### (.+)$/m', $accumulated, $mh);
            $headings = $mh[1] ?? [];
        }
        $tail = $accumulated !== '' ? mb_substr($accumulated, -700) : '';

        $pr = $template
            . "\n\nBook: {$book}\nAuthor: {$author}"
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
        $ch = curl_init(DEEPSEEK_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_TIMEOUT => 280,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
            CURLOPT_POSTFIELDS => json_encode(['model'=>(in_array(DEEPSEEK_MODEL,['deepseek-chat','deepseek-reasoner'],true)?'deepseek-v4-flash':DEEPSEEK_MODEL),'max_tokens'=>16000,'stream'=>true,'messages'=>[['role'=>'user','content'=>$pr]]]),
            CURLOPT_WRITEFUNCTION => $stream_cb,
        ]);
        curl_exec($ch); $cerr = curl_error($ch); curl_close($ch);

        if ($cerr) {
            if ($k === 1) $gen_error = "DeepSeek Part {$k} bağlantı hatası: {$cerr}";
            break;
        }
        $piece = trim(str_replace('%%PART_END%%', '', $piece));
        if ($piece === '') {
            // Hiç içerik gelmediyse gövdede SSE yerine düz JSON hata olabilir
            if ($k === 1) {
                $errj = json_decode($raw_tail, true);
                $gen_error = "DeepSeek Part {$k}: " . ($errj['error']['message'] ?? 'boş yanıt');
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

    if ($gen_error || !$content) {
        bw_update_book($batch_file, $idx, ['status'=>'error','error'=>$gen_error ?: 'Boş içerik']);
        return;
    }

    bw_touch_hb($batch_file, $idx);   // yayın aşaması başlıyor — canlılığı tazele

    // ── Meta (excerpt, meta_desc, kategoriler, alıntılar) ──────────
    // Kategori paleti = sitede VAR OLAN tüm kategoriler. Üretici yeni kategori
    // OLUŞTURMAZ, yalnızca mevcutlardan seçer → onaylı listeye kattığın (WP'de
    // tanımlı) her kategori otomatik kullanılır, ayrı liste bakımı gerekmez.
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
    $cats_fallback = 'philosophy,philosophy_of_religion,ethics,metaphysics,epistemology,logic,aesthetics,political_philosophy,history_of_philosophy,religion,theology,systematic_theology,christian_theology,islamic_theology,christianity,islam,judaism,buddhism,hinduism,atheism,agnosticism,history,world_history,ancient_history,medieval_history,modern_history,military_history,cultural_history,biography,autobiography,memoir,literature,classic_literature,world_literature,poetry,drama,novel,fiction,historical_fiction,science_fiction,dystopian_fiction,fantasy,horror,mystery,detective_fiction,romance,adventure,psychology,cognitive_psychology,social_psychology,psychoanalysis,sociology,anthropology,politics,political_science,economics,microeconomics,macroeconomics,education,law,international_law,science,physics,astronomy,chemistry,mathematics,statistics,biology,evolution,genetics,medicine,neuroscience,public_health,technology,computers,artificial_intelligence,programming,data_science,art,art_history,music,music_history,architecture,design,photography,film,theatre,geography,travel,culture,mythology,folklore,children,young_adult,self_help,personal_development,business,management,marketing,entrepreneurship';
    $cats_list = $tls_all_cat_slugs ? implode(',', $tls_all_cat_slugs) : $cats_fallback;

    $snippet = mb_substr(strip_tags($content), 0, 1500);
    $mp = "Return ONLY valid JSON (no extra text, no markdown fences):\n"
        . "{\"excerpt\":\"...\",\"meta_description\":\"...\",\"categories\":[\"slug1\",\"slug2\"],\"quotes\":[{\"text\":\"verbatim quote\",\"source\":\"section name\"}]}\n"
        . "CRITICAL: excerpt and meta_description must each be ONE COMPLETE sentence, fully finished (ending with a period), and MUST NOT exceed 150 characters. Never cut off mid-sentence. If needed, write shorter.\n"
        . "Pick 2-5 category slugs from: {$cats_list}\n"
        . "For quotes: only truly verbatim passages; 0-2 quotes max.\n"
        . "Book: \"{$book}\" by {$author}\n\n{$snippet}";

    $mch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($mch, [
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>30,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>(in_array(DEEPSEEK_MODEL,['deepseek-chat','deepseek-reasoner'],true)?'deepseek-v4-flash':DEEPSEEK_MODEL),'max_tokens'=>500,'messages'=>[['role'=>'user','content'=>$mp]]]),
    ]);
    $meta_raw = curl_exec($mch); curl_close($mch);
    $meta_text = json_decode($meta_raw,true)['choices'][0]['message']['content'] ?? '{}';
    $meta_text = preg_replace('/```json|```/', '', $meta_text);
    $meta = json_decode(trim($meta_text), true) ?? [];

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

    $cat_ids = [];
    foreach ($meta['categories'] ?? [] as $raw_slug) {
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
    [$slug_posts, $slug_code] = bw_wp("$wp_api/$ep?slug=" . urlencode($expected_slug) . '&status=any&per_page=1', 'GET', [], $auth, 15);
    if ($slug_code === 200 && !empty($slug_posts[0]['id'])) {
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

    $pb = [
        'title'   => $post_title,
        'content' => bw_md2html($clean),
        'excerpt' => $meta['excerpt'] ?? '',
        'status'  => $post_status,
    ];
    if ($ep !== 'analysis' && $cat_ids) $pb['categories'] = $cat_ids;

    [$post, $pc] = bw_wp("$wp_api/$ep", 'POST', $pb, $auth, 60);
    if ($pc < 200 || $pc >= 300) {
        $err = $post['message'] ?? "WP HTTP $pc";
        bw_update_book($batch_file, $idx, ['status'=>'error','error'=>$err]);
        return;
    }
    $pid = $post['id'];
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

    bw_update_book($batch_file, $idx, [
        'status'    => 'done',
        'post_id'   => $pid,
        'post_url'  => $post['link'] ?? '',
        'edit_url'  => rtrim(WP_URL,'/') . '/wp-admin/post.php?post=' . $pid . '&action=edit',
        'cover_set' => $cover_set,
    ]);
}

/* ════════════════ DRAIN DÖNGÜSÜ ════════════════
 * Tüm bekleyen kitaplar bitene (ya da iptal/duraklat) kadar işle.
 */
$processed = 0;
$start     = time();
$budget    = 70;          // saniye — sunucu uzun süreçleri öldürmeden önce kendini yenile
$reason    = 'no_more';
while (true) {
    [$idx, $batch] = bw_claim_next($batch_file);
    if ($idx === -1) { $reason = 'done';      break; }      // bekleyen yok
    if ($idx === -3) { $reason = 'cancelled'; break; }      // iptal
    if ($idx === -4) { $reason = 'paused';    break; }      // duraklat
    if ($idx === -2) { usleep(400000); continue; }          // başka worker kilitledi
    // Her kitap için süreyi sıfırla. Limit parça sayısına göre ölçeklenir:
    // her parça için DeepSeek timeout'u 280sn olabilir; 660sn sabiti 3-4 parçalı
    // kitaplarda yetmiyor ve PHP süreci kitabı ortada öldürüyordu → kitap
    // "processing"de asılı kalıyordu. parts*300 + 240sn (meta/bio/kapak/WP payı).
    $bk_parts = max(1, min(4, (int)($batch['parts'] ?? 2)));
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
