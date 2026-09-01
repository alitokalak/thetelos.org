<?php
/**
 * pdf-extract.php — Yüklenen bir PDF'ten DÜZ METİN çıkarır ve panelde
 * "Manuel kaynak" metin kutusuna doldurulmak üzere döner.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * NEDEN VAR
 * ═══════════════════════════════════════════════════════════════════════════
 * Kaynaklar çoğunlukla PDF olarak geliyor. PDF iki tür olabilir:
 *   1) METİN KATMANLI  — içinde gerçek metin var (Gutenberg, çoğu Internet
 *      Archive PDF'i kendi OCR metin katmanını GÖMER). Bunu saf PHP ile,
 *      LLM'siz, anında ve ücretsiz çıkarırız (FlateDecode + metin operatörleri).
 *   2) SALT GÖRSEL (tarama) — sayfalar fotoğraf; metin katmanı yok/az. Bunu
 *      Gemini'nin görsel (multimodal) yeteneğiyle OCR ederiz — PDF'i File API
 *      ile yükler, sayfa sayfa değil, tamamını okutup MAX_TOKENS'a takılırsa
 *      "kaldığın yerden devam" döngüsüyle birleştiririz.
 *
 * Dönen metin, mevcut kaynak-temelli boru hattına (proto_generate) aynen
 * yapıştırılmış metin gibi girer — downstream'de hiçbir değişiklik gerekmez.
 *
 * SÖZLEŞME: her zaman JSON döner:
 *   ['ok'=>bool, 'text'=>string, 'chars'=>int, 'pages'=>int,
 *    'method'=>'text-layer'|'gemini-ocr', 'truncated'=>bool, 'error'=>string]
 */

session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Yetki yok.']); exit; }
require_once __DIR__ . '/_gemini.php';

header('Content-Type: application/json; charset=utf-8');
@set_time_limit(0);
@ignore_user_abort(true);
@ini_set('memory_limit', '512M');

/* ── PDF baytlarını al ─────────────────────────────────────────────────────
   İki yol: (1) HAM gövde (php://input) — istemci öntanımlı olarak bunu kullanır;
   `upload_max_filesize`'ı (çoğu paylaşımlı sunucuda 2MB) BAŞTAN aşar, yalnız
   `post_max_size`'a tabidir. (2) multipart $_FILES — yedek. */
$bytes = '';
$name  = basename((string)($_GET['name'] ?? 'source.pdf'));

if (!empty($_FILES['pdf']) && is_uploaded_file($_FILES['pdf']['tmp_name'] ?? '')) {
    $bytes = (string) file_get_contents($_FILES['pdf']['tmp_name']);
    $name  = basename((string)($_FILES['pdf']['name'] ?? $name));
} elseif (!empty($_FILES['pdf']) && (int)($_FILES['pdf']['error'] ?? 0) === UPLOAD_ERR_INI_SIZE) {
    echo json_encode(['ok'=>false,'error'=>'PDF çok büyük — sunucu yükleme sınırını aşıyor. Daha küçük bir dosya deneyin ya da metni .txt olarak verin.']); exit;
} else {
    $bytes = (string) file_get_contents('php://input');
}

if ($bytes === '') {
    // Gövde boşsa ve $_POST/$_FILES de boşsa: büyük olasılıkla post_max_size aşıldı.
    if (empty($_POST) && empty($_FILES)) {
        echo json_encode(['ok'=>false,'error'=>'PDF sunucuya ulaşmadı — dosya sunucu sınırını (post_max_size) aşıyor olabilir. Bir-iki dakika sonra tekrar deneyin (sunucu ayarı güncelleniyor) ya da metni .txt olarak verin.']); exit;
    }
    echo json_encode(['ok'=>false,'error'=>'PDF bulunamadı.']); exit;
}

if (strncmp($bytes, '%PDF', 4) !== 0) {
    echo json_encode(['ok'=>false,'error'=>'Bu bir PDF dosyası değil (%PDF imzası yok).']); exit;
}

$pages = pex_page_count($bytes);

/* ── 1) METİN KATMANI (saf PHP, LLM'siz) ──────────────────────────────────── */
$text = pex_text_layer($bytes);
$text = pex_tidy($text);

// "Yeterli metin var mı?" — sayfa başına makul karakter. Metin katmanlı PDF'te
// sayfa başına en az ~120 karakter beklenir; altındaysa tarama say → OCR'a düş.
$per_page = $pages > 0 ? strlen($text) / $pages : strlen($text);
$has_layer = (strlen($text) >= 400 && $per_page >= 80 && pex_looks_like_text($text));

if ($has_layer) {
    echo json_encode([
        'ok'=>true, 'method'=>'text-layer', 'text'=>$text,
        'chars'=>mb_strlen($text), 'pages'=>$pages, 'truncated'=>false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── 2) CLAUDE OCR (salt görsel / CID-font tarama PDF) ─────────────────────────
   Gemini bu projede kapalı ("denied access") — hem File API hem generateContent.
   Anthropic (Claude) bu projede ÇALIŞIYOR (son-çare yazar + referee onu kullanır)
   ve PDF'i NATIF (görsel) okur: sayfaları render edip OCR eder, custom/CID font
   kodlaması onu ETKİLEMEZ. PDF'i "document" içerik bloğu (base64) olarak yollarız. */
require_once __DIR__ . '/_anthropic.php';
if (!tls_anthropic_ready()) {
    echo json_encode(['ok'=>false,
        'error'=>'Bu bir tarama/CID-font PDF ve okunabilir düz metin katmanı yok. OCR için Anthropic (Claude) anahtarı gerekli ama config.php\'de tanımlı değil.',
        'pages'=>$pages]);
    exit;
}

// Claude PDF sınırı: istek başına ~32MB ve ~100 sayfa. Aşımda net uyarı.
if (strlen($bytes) > 30 * 1024 * 1024) {
    echo json_encode(['ok'=>false,
        'error'=>'Bu tarama PDF çok büyük ('.round(strlen($bytes)/1048576,1).'MB) — Claude PDF sınırı ~32MB. PDF\'i bölün ya da metni .txt olarak verin.',
        'pages'=>$pages]);
    exit;
}
if ($pages > 100) {
    echo json_encode(['ok'=>false,
        'error'=>'Bu PDF '.$pages.' sayfa — Claude tek istekte en çok ~100 sayfa OCR eder. PDF\'i bölüp parça parça yükleyin.',
        'pages'=>$pages]);
    exit;
}

$key   = tls_anthropic_key();
$model = defined('ANTHROPIC_OCR_MODEL') ? ANTHROPIC_OCR_MODEL : tls_claude_quality_model();
$b64   = base64_encode($bytes);
$sys   = 'You are a precise OCR transcription engine. Transcribe the document VERBATIM into plain text, preserving reading order and paragraph breaks. Do NOT summarize, translate, comment, or add anything. Output only the transcribed text.';

$acc = '';
$truncated = false;
$MAX_ROUNDS = 10;                 // güvenlik tavanı
for ($round = 1; $round <= $MAX_ROUNDS; $round++) {
    if ($round === 1) {
        $prompt = 'Transcribe the ENTIRE document verbatim, from the first page to the last. Output only the raw text.';
    } else {
        $tail = mb_substr($acc, max(0, mb_strlen($acc) - 600));
        $prompt = "You are continuing a verbatim transcription of the SAME document. "
                . "Here are the LAST characters already transcribed:\n\n\"".$tail."\"\n\n"
                . "Resume the transcription IMMEDIATELY after that text and continue to the end of the document. "
                . "Do NOT repeat any text you already produced. Output only the new raw text.";
    }
    $r = pex_claude_ocr($key, $model, $sys, $b64, $prompt, 16000);
    if (!$r['ok']) {
        if ($round === 1) { echo json_encode(['ok'=>false,'error'=>'Claude OCR hatası: '.$r['error'],'pages'=>$pages]); exit; }
        $truncated = true; break;
    }
    $chunk = $r['text'];
    if ($round > 1) $chunk = pex_dedupe_join($acc, $chunk);
    $acc .= ($acc === '' ? '' : "\n") . $chunk;

    if (($r['stop'] ?? '') !== 'max_tokens') break;      // bitti
    if ($round === $MAX_ROUNDS) $truncated = true;        // tavana takıldı
}

$acc = pex_tidy($acc);
if ($acc === '') {
    echo json_encode(['ok'=>false,'error'=>'OCR sonucu boş döndü.','pages'=>$pages]); exit;
}
echo json_encode([
    'ok'=>true, 'method'=>'claude-ocr', 'text'=>$acc,
    'chars'=>mb_strlen($acc), 'pages'=>$pages, 'truncated'=>$truncated,
], JSON_UNESCAPED_UNICODE);
exit;


/* ════════════════════════════════════════════════════════════════════════════
   YARDIMCILAR
   ════════════════════════════════════════════════════════════════════════════ */

/** Kaba sayfa sayısı — /Type /Page (ama /Pages değil) sayımı. */
function pex_page_count($raw) {
    if (preg_match('#/Type\s*/Pages\b[^>]*?/Count\s+(\d+)#s', $raw, $m)) return (int)$m[1];
    $n = preg_match_all('#/Type\s*/Page\b#', $raw);
    return $n ?: 0;
}

/**
 * Saf PHP metin katmanı çıkarımı. FlateDecode akışlarını açar, içlerinden
 * metin gösteren operatörlerin ( Tj, TJ, ', " ) dizgilerini toplar.
 * LZW/JPX gibi nadir filtreleri atlar (metinsiz döner → OCR'a düşer).
 */
function pex_text_layer($raw) {
    $out = '';
    // stream ... endstream bloklarını yakala.
    if (!preg_match_all('#stream\r?\n(.*?)\r?\nendstream#s', $raw, $mm)) {
        // Bazı üreticiler CRLF farklı → daha gevşek dene.
        preg_match_all('#stream\r?\n?(.*?)endstream#s', $raw, $mm);
    }
    foreach ($mm[1] as $s) {
        $dec = pex_inflate($s);
        if ($dec === null) continue;
        // İçerik akışı mı? (metin operatörü içeriyor mu)
        if (strpos($dec, 'Tj') === false && strpos($dec, 'TJ') === false && strpos($dec, 'BT') === false) continue;
        $out .= pex_ops_to_text($dec) . "\n";
        if (strlen($out) > 8000000) break;   // ~8MB metin tavanı (bellek koruması)
    }
    return $out;
}

/** Bir akışı Flate ile açmayı dener; olmazsa ham dener. */
function pex_inflate($s) {
    // Baş/son boşlukları PDF'te anlamlı olabilir; ama zlib başlığı için trim güvenli.
    $t = $s;
    $z = @gzuncompress($t);
    if ($z !== false && $z !== '') return $z;
    $z = @gzinflate($t);
    if ($z !== false && $z !== '') return $z;
    // Bazı akışlar başında fazladan bayt taşır → küçük kaydırmalarla dene.
    for ($off = 1; $off <= 2; $off++) {
        $z = @gzinflate(substr($t, $off));
        if ($z !== false && $z !== '') return $z;
    }
    // Zaten düz metin operatörleri içeriyorsa ham döndür (filtresiz akış).
    if (strpos($t, 'Tj') !== false || strpos($t, 'TJ') !== false) return $t;
    return null;
}

/**
 * Açılmış içerik akışından metin dizgilerini ayıklar. Konum operatörleri
 * (Td, TD, T-star, tırnak) satır sonu ipucu olur; ( ) literal ve < > hex
 * dizgileri desteklenir, TJ dizileri birleştirilir.
 */
function pex_ops_to_text($c) {
    $res = '';
    $len = strlen($c);
    $i = 0;
    // Basit tarayıcı: dizgileri ve ilgili operatörleri sırayla oku.
    while ($i < $len) {
        $ch = $c[$i];
        if ($ch === '(') {
            // literal string — kaçışlara dikkat.
            $depth = 1; $i++; $buf = '';
            while ($i < $len && $depth > 0) {
                $d = $c[$i];
                if ($d === '\\') {
                    $n = $c[$i+1] ?? '';
                    $map = ['n'=>"\n",'r'=>"\r",'t'=>"\t",'b'=>"\x08",'f'=>"\x0C",'('=>'(',')'=>')','\\'=>'\\'];
                    if (isset($map[$n])) { $buf .= $map[$n]; $i += 2; continue; }
                    if ($n >= '0' && $n <= '7') { // octal \ddd
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
            $res .= $buf;
            continue;
        }
        if ($ch === '<' && ($c[$i+1] ?? '') !== '<') {
            // hex string
            $i++; $hex = '';
            while ($i < $len && $c[$i] !== '>') { $hex .= $c[$i]; $i++; }
            $i++; // skip >
            $hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);
            if (strlen($hex) % 2) $hex .= '0';
            for ($k=0; $k<strlen($hex); $k+=2) $res .= chr(hexdec(substr($hex,$k,2)));
            continue;
        }
        // Satır kırıcı operatörler: Td TD T* ' " → yeni satır ipucu.
        if ($ch === 'T' && (($c[$i+1] ?? '') === 'd' || ($c[$i+1] ?? '') === 'D' || ($c[$i+1] ?? '') === '*')) {
            $res .= "\n"; $i += 2; continue;
        }
        if ($ch === "'" || $ch === '"') { $res .= "\n"; $i++; continue; }
        $i++;
    }
    return $res;
}

/**
 * Çıkan metin GERÇEK okunabilir metin mi, yoksa CID-font çöpü mü?
 * Çok PDF, 2-baytlık CID font kodları kullanır; ham operatör çıkarımı bu
 * kodları çöp bayt olarak verir ("6>???:W…q8?_u*?…"). Bu çöp; harf ve boşluk
 * ORANI DÜŞÜK, buna karşın '?' ve sembol oranı yüksektir. İki güçlü sinyal:
 *   1) harf+boşluk oranı yeterince yüksek (gerçek düzyazı ~%75+),
 *   2) yaygın kısa kelimeler (the/and/of/de/la…) makul sayıda geçiyor.
 * İkisi de yoksa → çöp say, Gemini OCR'a düş.
 */
function pex_looks_like_text($t) {
    $sample = substr($t, 0, 20000);
    $n = strlen($sample);
    if ($n < 200) return false;
    $letters = preg_match_all('/[A-Za-zÀ-ÿ]/u', $sample);
    $spaces  = substr_count($sample, ' ');
    $ratio   = ($letters + $spaces) / max(1, $n);
    // Gerçek metinde bu yaygın kelimeler bolca geçer; çöpte neredeyse hiç yok.
    $words   = preg_match_all('/(?<![A-Za-z])(the|and|of|to|in|that|is|was|for|with|de|la|le|el|und|der|die|il|et|en)(?![A-Za-z])/i', $sample);
    return ($ratio >= 0.62 && $words >= 6);
}

/** Metni derle-topla: kontrol karakterleri, aşırı boşluk, satır normalize. */
function pex_tidy($t) {
    if ($t === '') return '';
    // Geçersiz UTF-8'i temizle.
    $t = @mb_convert_encoding($t, 'UTF-8', 'UTF-8');
    $t = str_replace(["\x00","\r"], ['', "\n"], $t);
    // Yazdırılamayan kontrol karakterlerini (tab/newline hariç) at.
    $t = preg_replace('/[^\P{C}\n\t]+/u', '', $t);
    // Çoklu boşlukları tekle, satır içi trim.
    $t = preg_replace('/[ \t]{2,}/', ' ', $t);
    $t = preg_replace('/\n{3,}/', "\n\n", $t);
    $lines = array_map('trim', explode("\n", $t));
    $t = trim(implode("\n", $lines));
    return $t;
}

/** Devam turunda örtüşen başlangıcı kırparak birleştir. */
function pex_dedupe_join($acc, $chunk) {
    $tail = mb_substr($acc, max(0, mb_strlen($acc) - 200));
    // chunk, tail'in bir son-ekiyle başlıyorsa o kadarını at.
    for ($n = min(mb_strlen($tail), mb_strlen($chunk)); $n >= 20; $n--) {
        if (mb_substr($chunk, 0, $n) === mb_substr($tail, mb_strlen($tail) - $n)) {
            return mb_substr($chunk, $n);
        }
    }
    return $chunk;
}

/**
 * Claude OCR — PDF'i "document" (base64) içerik bloğu olarak yollar, verbatim
 * transkripsiyon ister. Claude PDF'i natif (görsel) okur; CID/custom font
 * kodlaması sonucu etkilemez. Dönüş ['ok','text','stop','error'].
 */
function pex_claude_ocr($key, $model, $system, $b64, $prompt, $maxtok) {
    $payload = [
        'model'      => $model,
        'max_tokens' => (int) $maxtok,
        'system'     => $system,
        'messages'   => [[
            'role' => 'user',
            'content' => [
                ['type'=>'document', 'source'=>['type'=>'base64','media_type'=>'application/pdf','data'=>$b64]],
                ['type'=>'text', 'text'=>$prompt],
            ],
        ]],
    ];
    $do = function($body) use ($key) {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>20, CURLOPT_TIMEOUT=>600,
            CURLOPT_HTTPHEADER=>[
                'content-type: application/json',
                'x-api-key: ' . $key,
                'anthropic-version: 2023-06-01',
                'anthropic-beta: pdfs-2024-09-25',
            ],
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
            return ['ok'=>true, 'text'=>trim($text), 'stop'=>(string)($j['stop_reason'] ?? '')];
        }
        if ($code===429 || $code>=500) { if ($try<3){ sleep(5*$try); continue; } }
        $m = $j['error']['message'] ?? ('HTTP '.$code);
        return ['ok'=>false,'error'=>$m];
    }
    return ['ok'=>false,'error'=>'bilinmeyen hata'];
}

/* ── Gemini File API (kullanılmıyor — anahtar erişimi kapalı; ileride açılırsa) ── */

/** PDF'i Gemini File API'ye yükle (tek istekli raw resumable). */
function pex_gemini_upload($key, $bytes, $mime, $display) {
    $num = strlen($bytes);
    $url = 'https://generativelanguage.googleapis.com/upload/v1beta/files?key=' . rawurlencode($key);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_HTTPHEADER     => [
            'X-Goog-Upload-Protocol: raw',
            'X-Goog-Upload-Header-Content-Length: ' . $num,
            'X-Goog-Upload-Header-Content-Type: ' . $mime,
            'Content-Type: ' . $mime,
        ],
        CURLOPT_POSTFIELDS     => $bytes,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($err) return ['ok'=>false,'error'=>'bağlantı: '.$err];
    $j = json_decode((string)$raw, true);
    if ($code < 200 || $code >= 300 || empty($j['file']['uri'])) {
        $m = $j['error']['message'] ?? ('HTTP '.$code);
        return ['ok'=>false,'error'=>$m];
    }
    return ['ok'=>true, 'uri'=>$j['file']['uri'], 'name'=>$j['file']['name'],
            'mime'=>$j['file']['mimeType'] ?? $mime, 'state'=>$j['file']['state'] ?? ''];
}

/** Dosya ACTIVE olana dek yokla (Gemini PDF'i sayfalara ayırıyor). */
function pex_gemini_wait_active($key, $name) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/' . $name . '?key=' . rawurlencode($key);
    for ($i = 0; $i < 60; $i++) {   // ~2 dk tavan
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>15, CURLOPT_TIMEOUT=>30]);
        $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        $j = json_decode((string)$raw, true);
        $state = $j['state'] ?? '';
        if ($state === 'ACTIVE') return ['ok'=>true];
        if ($state === 'FAILED') return ['ok'=>false,'error'=>'dosya işleme FAILED'];
        sleep(2);
    }
    return ['ok'=>false,'error'=>'zaman aşımı (dosya ACTIVE olmadı)'];
}

/** generateContent — çok-parçalı (file_data + text) istek. */
function pex_gemini_generate($key, $model, $system, $parts, $maxtok) {
    // Anahtarı ÇALIŞAN referee gibi header'da gönder (?key= yerine) — bazı
    // kısıtlı anahtarlar sorgu-parametreli erişimi reddediyor.
    $base = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $payload = [
        'contents' => [['role'=>'user', 'parts'=>$parts]],
        'generationConfig' => ['temperature'=>0.0, 'maxOutputTokens'=>(int)$maxtok, 'thinkingConfig'=>['thinkingBudget'=>0]],
    ];
    if (trim((string)$system) !== '') $payload['systemInstruction'] = ['parts'=>[['text'=>$system]]];

    $do = function($body) use ($base, $key) {
        $ch = curl_init($base);
        curl_setopt_array($ch, [
            CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>20, CURLOPT_TIMEOUT=>600,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json', 'x-goog-api-key: ' . $key],
            CURLOPT_POSTFIELDS=>json_encode($body, JSON_UNESCAPED_UNICODE),
        ]);
        $raw=curl_exec($ch); $err=curl_error($ch); $code=(int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        return [$raw,$err,$code];
    };

    for ($try=1; $try<=3; $try++) {
        [$raw,$err,$code] = $do($payload);
        if ($code===400 && isset($payload['generationConfig']['thinkingConfig'])) {
            unset($payload['generationConfig']['thinkingConfig']);
            [$raw,$err,$code] = $do($payload);
        }
        if ($err) { if ($try<3){ sleep(3*$try); continue; } return ['ok'=>false,'error'=>$err]; }
        $j = json_decode((string)$raw, true);
        if ($code>=200 && $code<300 && is_array($j)) {
            $cand = $j['candidates'][0] ?? null;
            $text = '';
            foreach (($cand['content']['parts'] ?? []) as $p) if (isset($p['text'])) $text .= $p['text'];
            return ['ok'=>true, 'text'=>trim($text), 'finish'=>(string)($cand['finishReason'] ?? '')];
        }
        if ($code===429 || $code>=500) { if ($try<3){ sleep(5*$try); continue; } }
        $m = $j['error']['message'] ?? ('HTTP '.$code);
        return ['ok'=>false,'error'=>$m];
    }
    return ['ok'=>false,'error'=>'bilinmeyen hata'];
}

/** File API'den dosyayı sil (best-effort temizlik). */
function pex_gemini_delete($key, $name) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/' . $name . '?key=' . rawurlencode($key);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST=>'DELETE', CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30]);
    curl_exec($ch); curl_close($ch);
}
