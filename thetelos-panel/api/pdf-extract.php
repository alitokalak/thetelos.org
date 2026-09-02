<?php
/**
 * pdf-extract.php — Yüklenen bir PDF'ten DÜZ METİN çıkarır (panelde "Manuel
 * kaynak" kutusuna doldurulur). Cloudflare senkron isteği ~100 sn'de keser
 * (524), OCR ise dakikalarca sürer → bu yüzden batch-worker ile AYNI kalıba
 * oturur: kısa `start`, ARKA PLAN `work`, hafif `status` yoklaması.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * İKİ TÜR PDF
 *   1) METİN KATMANLI (Gutenberg, çoğu Archive PDF'i): saf PHP ile ANINDA ve
 *      ücretsiz çıkarılır (FlateDecode + metin operatörleri) — `start` biter.
 *   2) SALT GÖRSEL / CID-font (z-library taramaları): düz metin çöp döner →
 *      CLAUDE ile OCR. Claude PDF'i natif (görsel) okur; font kodlaması sonucu
 *      etkilemez. Gemini bu projede kapalı ("denied access"), onun için Claude.
 *
 * AKSİYONLAR (?action=):
 *   start  — POST ham PDF gövdesi (?name=...). Metin katmanı iyiyse HEMEN
 *            {status:'done', text} döner. Değilse işi kaydeder,
 *            {status:'processing', job_id, pages} döner.
 *   work   — POST job_id (+ session ya da _itok). ateşle-unut; ignore_user_abort
 *            ile Cloudflare 524'ten sonra da sürer. OCR turlarını çalıştırır,
 *            her turda ilerlemeyi iş dosyasına yazar.
 *   status — GET ?job=ID. {status, pages, chars, round, truncated, text?(done)}.
 */

session_start();
require_once dirname(__DIR__) . '/config.php';

$internal_token = hash('sha256', WP_APP_PASS . '|tls-pdf-ocr');
$is_internal    = isset($_POST['_itok']) && hash_equals($internal_token, (string) $_POST['_itok']);
if (empty($_SESSION['tls_auth']) && !$is_internal) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Yetki yok.']); exit; }
session_write_close();

@set_time_limit(0);
@ignore_user_abort(true);
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '256M');

$JOBDIR = dirname(__DIR__) . '/jobs';
$action  = $_GET['action'] ?? 'start';

function pex_job_path($dir, $id) { return $dir . '/pdfocr-' . preg_replace('/[^a-f0-9]/', '', $id); }
function pex_job_read($dir, $id) { $f = pex_job_path($dir, $id) . '.json'; return is_file($f) ? json_decode((string) file_get_contents($f), true) : null; }
function pex_job_write($dir, $id, $data) { file_put_contents(pex_job_path($dir, $id) . '.json', json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX); }

/* ════════════════════════════════════════════════════════════════════════════
   STATUS — hafif yoklama
   ════════════════════════════════════════════════════════════════════════════ */
if ($action === 'status') {
    header('Content-Type: application/json; charset=utf-8');
    $id  = (string) ($_GET['job'] ?? '');
    $job = pex_job_read($JOBDIR, $id);
    if (!$job) { echo json_encode(['ok'=>false,'error'=>'iş bulunamadı']); exit; }
    $out = [
        'ok'=>true, 'status'=>$job['status'] ?? '?', 'pages'=>$job['pages'] ?? 0,
        'chars'=>$job['chars'] ?? 0, 'round'=>$job['round'] ?? 0,
        'truncated'=>!empty($job['truncated']), 'method'=>$job['method'] ?? '',
        'age'=>isset($job['ts']) ? max(0, time() - (int)$job['ts']) : null,
    ];
    if (($job['status'] ?? '') === 'done')  $out['text']  = $job['text']  ?? '';
    if (($job['status'] ?? '') === 'error') $out['error'] = $job['error'] ?? 'hata';
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ════════════════════════════════════════════════════════════════════════════
   WORK — arka plan OCR (ateşle-unut; 524 sonrası da sürer)
   ════════════════════════════════════════════════════════════════════════════ */
if ($action === 'work') {
    header('Content-Type: application/json; charset=utf-8');
    $id  = (string) ($_POST['job'] ?? $_GET['job'] ?? '');
    $job = pex_job_read($JOBDIR, $id);
    if (!$job) { echo json_encode(['ok'=>false,'error'=>'iş bulunamadı']); exit; }
    // Zaten biten ya da CANLI çalışan işi tekrar başlatma (çift ateşleme koruması).
    $age = isset($job['ts']) ? (time() - (int)$job['ts']) : 9999;
    if (in_array($job['status'] ?? '', ['done','error'], true)) { echo json_encode(['ok'=>true,'status'=>$job['status']]); exit; }
    if (($job['status'] ?? '') === 'working' && $age < 150) { echo json_encode(['ok'=>true,'status'=>'working']); exit; }

    $pdf = pex_job_path($JOBDIR, $id) . '.pdf';
    if (!is_file($pdf)) {
        $job['status']='error'; $job['error']='PDF geçici dosyası kayıp'; pex_job_write($JOBDIR, $id, $job); echo json_encode(['ok'=>false]); exit;
    }
    $bytes = (string) file_get_contents($pdf);
    $pages = (int) ($job['pages'] ?? pex_page_count($bytes));

    require_once __DIR__ . '/_anthropic.php';
    if (!tls_anthropic_ready()) {
        $job['status']='error'; $job['error']='OCR için Anthropic (Claude) anahtarı config.php\'de yok.'; pex_job_write($JOBDIR, $id, $job); @unlink($pdf); echo json_encode(['ok'=>false]); exit;
    }
    // Claude PDF sınırı: ~32MB / ~100 sayfa.
    if (strlen($bytes) > 30*1024*1024) { $job['status']='error'; $job['error']='PDF çok büyük (~32MB üstü) — bölün ya da .txt verin.'; pex_job_write($JOBDIR,$id,$job); @unlink($pdf); echo json_encode(['ok'=>false]); exit; }
    if ($pages > 100)                  { $job['status']='error'; $job['error']='PDF '.$pages.' sayfa — Claude tek istekte ~100 sayfa OCR eder; bölün.'; pex_job_write($JOBDIR,$id,$job); @unlink($pdf); echo json_encode(['ok'=>false]); exit; }

    $model = defined('ANTHROPIC_OCR_MODEL') ? ANTHROPIC_OCR_MODEL : tls_claude_quality_model();
    $b64   = base64_encode($bytes);
    // DÖNÜŞTÜRÜCÜ DİGEST — telif korumalı kitabı KELİMESİ KELİMESİNE kopyalamak
    // (reproduction) Claude tarafından reddediliyor; zaten thetelos'un işi de
    // özet. Onun için Claude kitabı okuyup KENDİ SÖZLERİYLE kapsamlı, sadık bir
    // digest üretir (bölüm bölüm ana tezler, argümanlar, örnekler). Bu hem
    // meşru/dönüştürücü, hem tek geçiş → daha ucuz ve sunucuyu az yorar.
    $sys   = 'You are a scholarly book-digest engine for a book-summary website. From the provided PDF, produce a COMPREHENSIVE, faithful digest IN ENGLISH that a summariser can rely on as source grounding. Work through the book in order (chapter by chapter or section by section), capturing in YOUR OWN WORDS: the central theses, the key arguments and how they are built, important examples and evidence, and the conclusions. Be detailed and specific to THIS book (names, concepts, structure) — not generic. Do NOT reproduce the text verbatim; this is a transformative digest, not a copy. Write ONLY the digest itself: no preface, no meta commentary, no notes about yourself or about copyright, no headings like "Digest:".';

    $acc = (string) ($job['acc'] ?? '');
    $truncated = false;
    $job['status']='working'; $job['ts']=time(); pex_job_write($JOBDIR, $id, $job);

    // SERT KAYNAK TAVANI: sunucuyu (shared hosting) uzun süre tutmasın.
    $MAX_ROUNDS = 4;              // digest yoğunlaştırılmış → az tur yeter
    $deadline   = time() + 600;   // ~10 dk toplam; sonra worker'ı serbest bırak
    for ($round = ((int)($job['round'] ?? 0)) + 1; $round <= $MAX_ROUNDS; $round++) {
        if (time() > $deadline) { $truncated = true; break; }
        if ($acc === '') {
            $prompt = 'Produce the comprehensive English digest of this document now, covering the whole book in order.';
        } else {
            $tail = mb_substr($acc, max(0, mb_strlen($acc) - 600));
            $prompt = "You are continuing the SAME comprehensive digest of this book. "
                    . "Here is how the digest so far ends:\n\n\"".$tail."\"\n\n"
                    . "Continue the digest from that point, covering the remaining parts of the book to the end. "
                    . "Do NOT repeat what you already wrote. Output only the continuation.";
        }
        $r = pex_claude_ocr($key = tls_anthropic_key(), $model, $sys, $b64, $prompt, 16000);
        if (!$r['ok']) {
            if ($acc === '') { $job['status']='error'; $job['error']='Claude digest hatası: '.$r['error']; pex_job_write($JOBDIR,$id,$job); @unlink($pdf); echo json_encode(['ok'=>false]); exit; }
            $truncated = true; break;   // eldekiyle bitir
        }
        $chunk = $r['text'];
        if ($acc !== '') $chunk = pex_dedupe_join($acc, $chunk);
        $acc .= ($acc === '' ? '' : "\n") . $chunk;

        $job['acc']=$acc; $job['round']=$round; $job['chars']=mb_strlen($acc); $job['ts']=time(); $job['status']='working';
        pex_job_write($JOBDIR, $id, $job);

        if (($r['stop'] ?? '') !== 'max_tokens') break;    // bitti
        if ($round === $MAX_ROUNDS) $truncated = true;
    }

    $acc = pex_tidy($acc);
    if ($acc === '') { $job['status']='error'; $job['error']='Digest boş döndü.'; }
    else { $job = ['status'=>'done','method'=>'claude-digest','text'=>$acc,'chars'=>mb_strlen($acc),'pages'=>$pages,'truncated'=>$truncated,'round'=>$job['round'] ?? 0,'ts'=>time()]; }
    pex_job_write($JOBDIR, $id, $job);
    @unlink($pdf);
    echo json_encode(['ok'=>true,'status'=>$job['status']]);
    exit;
}

/* ════════════════════════════════════════════════════════════════════════════
   START — PDF'i al, metin katmanını dene, gerekirse OCR işini kur
   ════════════════════════════════════════════════════════════════════════════ */
header('Content-Type: application/json; charset=utf-8');

// PDF baytları: HAM gövde (upload_max_filesize'ı aşar) ya da yedek multipart.
$bytes = '';
if (!empty($_FILES['pdf']) && is_uploaded_file($_FILES['pdf']['tmp_name'] ?? '')) {
    $bytes = (string) file_get_contents($_FILES['pdf']['tmp_name']);
} else {
    $bytes = (string) file_get_contents('php://input');
}
if ($bytes === '') {
    if (empty($_POST) && empty($_FILES)) { echo json_encode(['ok'=>false,'error'=>'PDF sunucuya ulaşmadı — dosya sunucu sınırını (post_max_size) aşıyor olabilir. Bir-iki dakika sonra tekrar deneyin ya da metni .txt olarak verin.']); exit; }
    echo json_encode(['ok'=>false,'error'=>'PDF bulunamadı.']); exit;
}
if (strncmp($bytes, '%PDF', 4) !== 0) { echo json_encode(['ok'=>false,'error'=>'Bu bir PDF dosyası değil (%PDF imzası yok).']); exit; }

$pages = pex_page_count($bytes);

// 1) METİN KATMANI (saf PHP) — hızlı, ücretsiz.
$text = pex_tidy(pex_text_layer($bytes));
$per_page = $pages > 0 ? strlen($text) / $pages : strlen($text);
if (strlen($text) >= 400 && $per_page >= 80 && pex_looks_like_text($text)) {
    echo json_encode(['ok'=>true,'status'=>'done','method'=>'text-layer','text'=>$text,'chars'=>mb_strlen($text),'pages'=>$pages,'truncated'=>false], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2) OCR gerekli — işi kur, PDF'i sakla, {processing} dön. Asıl OCR'ı `work` yapar.
$id = bin2hex(random_bytes(8));
file_put_contents(pex_job_path($JOBDIR, $id) . '.pdf', $bytes, LOCK_EX);
pex_job_write($JOBDIR, $id, ['status'=>'queued','pages'=>$pages,'chars'=>0,'round'=>0,'ts'=>time()]);
echo json_encode(['ok'=>true,'status'=>'processing','job_id'=>$id,'pages'=>$pages], JSON_UNESCAPED_UNICODE);
exit;


/* ════════════════════════════════════════════════════════════════════════════
   YARDIMCILAR
   ════════════════════════════════════════════════════════════════════════════ */

/** Kaba sayfa sayısı — /Type /Pages/Count, yoksa /Type /Page sayımı. */
function pex_page_count($raw) {
    if (preg_match('#/Type\s*/Pages\b[^>]*?/Count\s+(\d+)#s', $raw, $m)) return (int)$m[1];
    $n = preg_match_all('#/Type\s*/Page\b#', $raw);
    return $n ?: 0;
}

/** Saf PHP metin katmanı çıkarımı (FlateDecode akışları + metin operatörleri). */
function pex_text_layer($raw) {
    $out = '';
    if (!preg_match_all('#stream\r?\n(.*?)\r?\nendstream#s', $raw, $mm)) {
        preg_match_all('#stream\r?\n?(.*?)endstream#s', $raw, $mm);
    }
    foreach ($mm[1] as $s) {
        $dec = pex_inflate($s);
        if ($dec === null) continue;
        if (strpos($dec, 'Tj') === false && strpos($dec, 'TJ') === false && strpos($dec, 'BT') === false) continue;
        $out .= pex_ops_to_text($dec) . "\n";
        if (strlen($out) > 8000000) break;
    }
    return $out;
}

/** Bir akışı Flate ile açmayı dener; olmazsa ham dener. */
function pex_inflate($s) {
    $z = @gzuncompress($s); if ($z !== false && $z !== '') return $z;
    $z = @gzinflate($s);    if ($z !== false && $z !== '') return $z;
    for ($off = 1; $off <= 2; $off++) { $z = @gzinflate(substr($s, $off)); if ($z !== false && $z !== '') return $z; }
    if (strpos($s, 'Tj') !== false || strpos($s, 'TJ') !== false) return $s;
    return null;
}

/**
 * Açılmış içerik akışından metin dizgilerini ayıklar. Konum operatörleri
 * (Td, TD, T-star, tırnak) satır sonu ipucu olur; ( ) literal ve < > hex
 * dizgileri desteklenir.
 */
function pex_ops_to_text($c) {
    $res = ''; $len = strlen($c); $i = 0;
    while ($i < $len) {
        $ch = $c[$i];
        if ($ch === '(') {
            $depth = 1; $i++; $buf = '';
            while ($i < $len && $depth > 0) {
                $d = $c[$i];
                if ($d === '\\') {
                    $n = $c[$i+1] ?? '';
                    $map = ['n'=>"\n",'r'=>"\r",'t'=>"\t",'b'=>"\x08",'f'=>"\x0C",'('=>'(',')'=>')','\\'=>'\\'];
                    if (isset($map[$n])) { $buf .= $map[$n]; $i += 2; continue; }
                    if ($n >= '0' && $n <= '7') {
                        $oct = $n; $i += 2;
                        for ($k=0; $k<2 && $i<$len && $c[$i]>='0' && $c[$i]<='7'; $k++,$i++) $oct .= $c[$i];
                        $buf .= chr(octdec($oct)); continue;
                    }
                    $buf .= $n; $i += 2; continue;
                }
                if ($d === '(') { $depth++; $buf .= $d; $i++; continue; }
                if ($d === ')') { $depth--; if ($depth>0) $buf .= $d; $i++; continue; }
                $buf .= $d; $i++;
            }
            $res .= $buf; continue;
        }
        if ($ch === '<' && ($c[$i+1] ?? '') !== '<') {
            $i++; $hex = '';
            while ($i < $len && $c[$i] !== '>') { $hex .= $c[$i]; $i++; }
            $i++;
            $hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);
            if (strlen($hex) % 2) $hex .= '0';
            for ($k=0; $k<strlen($hex); $k+=2) $res .= chr(hexdec(substr($hex,$k,2)));
            continue;
        }
        if ($ch === 'T' && (($c[$i+1] ?? '') === 'd' || ($c[$i+1] ?? '') === 'D' || ($c[$i+1] ?? '') === '*')) { $res .= "\n"; $i += 2; continue; }
        if ($ch === "'" || $ch === '"') { $res .= "\n"; $i++; continue; }
        $i++;
    }
    return $res;
}

/**
 * Çıkan metin GERÇEK okunabilir metin mi, yoksa CID-font çöpü mü? Harf+boşluk
 * oranı ve yaygın kelime sinyaline bakar. İkisi de yoksa → çöp, OCR'a düş.
 */
function pex_looks_like_text($t) {
    $sample = substr($t, 0, 20000);
    $n = strlen($sample);
    if ($n < 200) return false;
    $letters = preg_match_all('/[A-Za-zÀ-ÿ]/u', $sample);
    $spaces  = substr_count($sample, ' ');
    $ratio   = ($letters + $spaces) / max(1, $n);
    $words   = preg_match_all('/(?<![A-Za-z])(the|and|of|to|in|that|is|was|for|with|de|la|le|el|und|der|die|il|et|en)(?![A-Za-z])/i', $sample);
    return ($ratio >= 0.62 && $words >= 6);
}

/** Metni derle-topla: kontrol karakterleri, aşırı boşluk, satır normalize. */
function pex_tidy($t) {
    if ($t === '') return '';
    $t = @mb_convert_encoding($t, 'UTF-8', 'UTF-8');
    $t = str_replace(["\x00","\r"], ['', "\n"], $t);
    $t = preg_replace('/[^\P{C}\n\t]+/u', '', $t);
    $t = preg_replace('/[ \t]{2,}/', ' ', $t);
    $t = preg_replace('/\n{3,}/', "\n\n", $t);
    $lines = array_map('trim', explode("\n", $t));
    return trim(implode("\n", $lines));
}

/** Devam turunda örtüşen başlangıcı kırparak birleştir. */
function pex_dedupe_join($acc, $chunk) {
    $tail = mb_substr($acc, max(0, mb_strlen($acc) - 200));
    for ($n = min(mb_strlen($tail), mb_strlen($chunk)); $n >= 20; $n--) {
        if (mb_substr($chunk, 0, $n) === mb_substr($tail, mb_strlen($tail) - $n)) return mb_substr($chunk, $n);
    }
    return $chunk;
}

/**
 * Claude OCR — PDF'i "document" (base64) bloğu olarak yollar, verbatim metin
 * ister. Claude PDF'i natif (görsel) okur; CID/özel font kodlaması etkilemez.
 */
function pex_claude_ocr($key, $model, $system, $b64, $prompt, $maxtok) {
    $payload = [
        'model'=>$model, 'max_tokens'=>(int)$maxtok, 'system'=>$system,
        // PROMPT CACHE: PDF belgesi devam turlarında tekrar gönderiliyor →
        // cache_control ile 2-4. turlarda PDF input maliyeti ~%90 düşer.
        'messages'=>[[ 'role'=>'user', 'content'=>[
            ['type'=>'document','source'=>['type'=>'base64','media_type'=>'application/pdf','data'=>$b64],'cache_control'=>['type'=>'ephemeral']],
            ['type'=>'text','text'=>$prompt],
        ]]],
    ];
    $do = function($body) use ($key) {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>20, CURLOPT_TIMEOUT=>240,
            CURLOPT_HTTPHEADER=>['content-type: application/json','x-api-key: '.$key,'anthropic-version: 2023-06-01','anthropic-beta: pdfs-2024-09-25'],
            CURLOPT_POSTFIELDS=>json_encode($body, JSON_UNESCAPED_UNICODE),
        ]);
        $raw=curl_exec($ch); $err=curl_error($ch); $code=(int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        return [$raw,$err,$code];
    };
    for ($try=1; $try<=3; $try++) {
        [$raw,$err,$code] = $do($payload);
        if ($err) { if ($try<3){ sleep(3*$try); continue; } return ['ok'=>false,'error'=>'bağlantı: '.$err]; }
        $j = json_decode((string)$raw, true);
        if ($code>=200 && $code<300 && is_array($j)) {
            $text=''; foreach (($j['content'] ?? []) as $blk) if (($blk['type'] ?? '')==='text') $text .= $blk['text'];
            return ['ok'=>true,'text'=>trim($text),'stop'=>(string)($j['stop_reason'] ?? '')];
        }
        if ($code===429 || $code>=500) { if ($try<3){ sleep(5*$try); continue; } }
        $m = $j['error']['message'] ?? ('HTTP '.$code);
        return ['ok'=>false,'error'=>$m];
    }
    return ['ok'=>false,'error'=>'bilinmeyen hata'];
}
