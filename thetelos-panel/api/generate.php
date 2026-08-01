<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }

@ini_set('max_execution_time', 300);
@ini_set('output_buffering', 'off');
set_time_limit(300);
ignore_user_abort(false);

header('Content-Type: application/json');

$book         = trim($_POST['book_title']  ?? '');
$author       = trim($_POST['author_name'] ?? '');
$type         = trim($_POST['type']        ?? 'summary');
$api_provider = trim($_POST['api_provider'] ?? 'deepseek'); // 'deepseek'
$api_model    = trim($_POST['api_model']    ?? '');          // model override
$target_words = max(300, min(8000, (int)($_POST['max_tokens'] ?? 3000)));
// İki parçalı üretimde her parça tam kapasitede yazılsın
$max_tokens   = 16000;

if (!$book || !$author) {
    echo json_encode(['ok'=>false,'error'=>'Kitap adı ve yazar adı zorunludur.']);
    exit;
}

$prompts  = file_exists(PROMPTS_FILE) ? json_decode(file_get_contents(PROMPTS_FILE), true) : [];
$template = trim($prompts[$type] ?? '');

if (!$template) {
    echo json_encode(['ok'=>false,'error'=>'Prompt boş! Lütfen Ayarlar sayfasından promptu girin.']);
    exit;
}

// ── Çok parçalı üretim parametreleri ─────────────────────────────
// part  = kaçıncı parça (1-based). 0/boş = tek seferde
// parts = toplam parça sayısı (1-6; uzun hedeflerde panel otomatik yükseltir)
// prev_content = önceki parçaların birleştirilmiş içeriği (bağlam için)
$part  = (int)($_POST['part']  ?? 0);
$parts = max(1, min(6, (int)($_POST['parts'] ?? 1)));
$prev_content = trim($_POST['prev_content'] ?? '');
// Geriye dönük uyumluluk: eski çağrılar part='1'/'2' ve part1_content gönderiyordu
if ($prev_content === '' && !empty($_POST['part1_content'])) {
    $prev_content = trim($_POST['part1_content']);
}
if ($part < 1 && $parts > 1) $part = 1;

// Her parça için hedef kelime sayısı (toplam / parça)
$part_words = $parts > 1 ? (int)ceil($target_words / $parts) : $target_words;

// 1'den N'e kesir adı (yarısı, üçte biri, dörtte biri)
function tls_fraction_name($n) {
    switch ($n) {
        case 2: return 'half';
        case 3: return 'third';
        case 4: return 'quarter';
        default: return "1/{$n}";
    }
}

// Belirli bir parça için dinamik talimat üret (bağlam + akış korunur)
function tls_part_instruction($k, $n, $headings, $tail, $part_words) {
    $frac    = tls_fraction_name($n);
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

    // Son parça
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

$prompt = str_replace(
    ['{book_title}','{author_name}','{BOOK_TITLE}','{AUTHOR_NAME}'],
    [$book,$author,$book,$author],
    $template
) . "\n\nBook: {$book}\nAuthor: {$author}";

if ($parts <= 1) {
    $prompt .= "\nTarget length: approximately {$target_words} words.";
}

// ── Web search ile kaynak metin bul ──────────────────────────────
function fetch_source_text($book, $author) {
    // Arama sorgusu: orijinal başlık + yazar + "full text" veya "translation"
    $queries = [
        '"' . $book . '" "' . $author . '" full text translation',
        '"' . $book . '" "' . $author . '" english translation',
        $book . ' ' . $author . ' full text',
    ];

    $api_key = GOOGLE_SEARCH_KEY;
    $cx      = GOOGLE_SEARCH_CX;

    $found_url  = '';
    $found_text = '';

    foreach ($queries as $q) {
        $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query([
            'key' => $api_key,
            'cx'  => $cx,
            'q'   => $q,
            'num' => 5,
        ]);

        $res  = @file_get_contents($url);
        if (!$res) continue;
        $data = json_decode($res, true);
        if (empty($data['items'])) continue;

        foreach ($data['items'] as $item) {
            $link  = $item['link']    ?? '';
            $title = strtolower($item['title']   ?? '');
            $snip  = strtolower($item['snippet'] ?? '');

            // Güvenlik: hem kitap adından hem yazar adından en az bir kelime geçmeli
            $book_words   = array_filter(explode(' ', strtolower($book)),   fn($w) => strlen($w) > 3);
            $author_words = array_filter(explode(' ', strtolower($author)), fn($w) => strlen($w) > 2);

            $book_match   = false;
            $author_match = false;

            foreach ($book_words as $w) {
                if (strpos($title.$snip, $w) !== false) { $book_match = true; break; }
            }
            foreach ($author_words as $w) {
                if (strpos($title.$snip, $w) !== false) { $author_match = true; break; }
            }

            if (!$book_match || !$author_match) continue;

            // Sayfayı çek — ilk 5000 karakter al
            $ctx = stream_context_create(['http' => [
                'timeout' => 8,
                'header'  => 'User-Agent: Mozilla/5.0',
            ]]);
            $html = @file_get_contents($link, false, $ctx);
            if (!$html || strlen($html) < 500) continue;

            // HTML'den düz metin çıkar
            $text = strip_tags($html);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

            // Kitap adı gerçekten metinde geçiyor mu?
            foreach ($book_words as $w) {
                if (stripos($text, $w) !== false) {
                    $found_url  = $link;
                    $found_text = mb_substr($text, 0, 6000);
                    break 3; // tüm döngülerden çık
                }
            }
        }
    }

    return ['url' => $found_url, 'text' => $found_text];
}

// Web search — ilk parçada (veya tek seferde) çalıştır
$source_text = '';
$source_url  = '';
if ($parts <= 1 || $part === 1) {
    $source = fetch_source_text($book, $author);
    $source_text = $source['text'];
    $source_url  = $source['url'];
}
if ($source_text) {
    $prompt .= "\n\n=== SOURCE TEXT (MANDATORY) ===\n"
             . "You MUST base your writing on the following original source text.\n"
             . "Select ALL blockquote passages VERBATIM from this text only — do not invent quotes.\n"
             . "Source: {$source_url}\n\n"
             . $source_text
             . "\n=== END SOURCE TEXT ===\n";
}

// ── Çok parçalı talimatı ekle ────────────────────────────────────
if ($parts > 1) {
    $headings = [];
    if ($prev_content !== '') {
        preg_match_all('/^### (.+)$/m', $prev_content, $matches);
        $headings = $matches[1] ?? [];
    }
    $tail = $prev_content !== '' ? mb_substr($prev_content, -700) : '';
    $prompt .= tls_part_instruction($part, $parts, $headings, $tail, $part_words);
}

// SSE başlat
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
while (ob_get_level()) ob_end_flush();
ob_implicit_flush(true);

function sse($event, $data) {
    echo "event: $event\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

sse('status', ['msg' => 'DeepSeek içerik üretiyor...']);

$full_content  = '';
$input_tokens  = 0;
$output_tokens = 0;
$stop_reason   = '';
$stream_buf    = '';
$raw_buf       = '';
$err_buf       = '';   // SSE olmayan gövde (hata JSON'u) — teşhis için saklanır
$last_ping     = time();

// ── DeepSeek SSE ──────────────────────────────────────────
$write_fn = function($ch, $chunk) use (&$full_content, &$input_tokens, &$output_tokens, &$stop_reason, &$stream_buf, &$raw_buf, &$err_buf, &$last_ping) {
    $raw_buf .= $chunk;
    // API 200 dönmediğinde gövde SSE değil, düz JSON hatasıdır ("data: " ile
    // başlamaz) ve aşağıdaki ayrıştırıcı onu sessizce yutuyordu. Sonuç, sebebi
    // hiç görünmeyen bir "İçerik üretilemedi." mesajıydı.
    if (strlen($err_buf) < 2000) $err_buf .= $chunk;

    if (time() - $last_ping >= 10) {
        echo ": ping\n\n";
        flush();
        $last_ping = time();
    }

    while (($pos = strpos($raw_buf, "\n")) !== false) {
        $line    = substr($raw_buf, 0, $pos);
        $raw_buf = substr($raw_buf, $pos + 1);
        $line    = trim($line);
        if (!$line) continue;
        if (strpos($line, 'data: ') === 0) {
            $json = substr($line, 6);
            if ($json === '[DONE]') {
                $stop_reason = 'end_turn';
                continue;
            }
            $ev = json_decode($json, true);
            if (!$ev) continue;
            // OpenAI-uyumlu format
            $text = $ev['choices'][0]['delta']['content'] ?? '';
            if ($text !== '') {
                $full_content .= $text;
                $stream_buf   .= $text;
                if (strlen($stream_buf) >= 200) {
                    sse('chunk', ['text' => $stream_buf]);
                    $stream_buf = '';
                }
            }
            // Token sayısı — genellikle son chunk'ta gelir
            if (!empty($ev['usage'])) {
                $input_tokens  = $ev['usage']['prompt_tokens']     ?? $input_tokens;
                $output_tokens = $ev['usage']['completion_tokens'] ?? $output_tokens;
            }
            $finish = $ev['choices'][0]['finish_reason'] ?? '';
            if ($finish) $stop_reason = $finish === 'stop' ? 'end_turn' : $finish;
        }
    }
    return strlen($chunk);
};

/**
 * Bağlantıyı canlı tutan nabız.
 *
 * NEDEN AYRI: yukarıdaki ping yalnızca $write_fn içinde atılıyordu, o da
 * ancak VERİ GELDİĞİNDE çalışır. Model ilk kelimeyi yazana kadar düşünüyorsa
 * (uzun promptlarda 1-2 dakika sürebiliyor) tarayıcıya tek bayt gitmez ve
 * Cloudflare 100 saniyede bağlantıyı keser — akış hiçbir hata olayı
 * gönderemeden kapanır, ekranda sebepsiz "İçerik üretilemedi." belirir.
 *
 * XFERINFOFUNCTION veri aksa da akmasa da libcurl tarafından düzenli çağrılır;
 * nabız artık isteğin yavaşlığından etkilenmiyor.
 */
$beat = function () use (&$last_ping) {
    if (time() - $last_ping >= 10) {
        echo ": ping\n\n";
        flush();
        $last_ping = time();
    }
    return 0;
};

$ch = curl_init(DEEPSEEK_API_URL);
curl_setopt_array($ch, [
    CURLOPT_POST          => true,
    CURLOPT_TIMEOUT       => 280,
    CURLOPT_NOPROGRESS       => false,
    CURLOPT_XFERINFOFUNCTION => $beat,
    CURLOPT_HTTPHEADER    => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . DEEPSEEK_KEY,
    ],
    CURLOPT_POSTFIELDS    => json_encode([
        'model'       => (in_array(DEEPSEEK_MODEL,['deepseek-chat','deepseek-reasoner'],true)?'deepseek-v4-flash':DEEPSEEK_MODEL),
        'max_tokens'  => $max_tokens,
        'stream'      => true,
        'messages'    => [['role'=>'user','content'=>$prompt]],
    ]),
    CURLOPT_WRITEFUNCTION => $write_fn,
]);

curl_exec($ch);
$curl_err  = curl_error($ch);
$http_code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($stream_buf !== '') {
    sse('chunk', ['text' => $stream_buf]);
}

if ($curl_err) { sse('error', ['error' => 'Bağlantı hatası: ' . $curl_err]); exit; }

/**
 * Boş yanıtın SEBEBİNİ söyle.
 *
 * "İçerik üretilemedi." teşhis için hiçbir işe yaramıyordu: istek gitti mi,
 * kota mı doldu, model mi reddetti, sunucu mu meşguldü — hiçbiri bilinmiyordu.
 * Artık HTTP kodu, sağlayıcının kendi mesajı ve gövdenin başı gösterilir.
 */
if (!$full_content) {
    $j   = json_decode($err_buf, true);
    $msg = $j['error']['message'] ?? ($j['message'] ?? '');
    $tip = '';
    if ($http_code === 429)                    $tip = ' — istek sınırı doldu, biraz bekleyip tekrar dene';
    elseif ($http_code === 402)                $tip = ' — DeepSeek bakiyesi bitmiş';
    elseif ($http_code === 401)                $tip = ' — API anahtarı geçersiz';
    elseif ($http_code >= 500)                 $tip = ' — sağlayıcı tarafında geçici arıza';
    elseif ($http_code === 0)                  $tip = ' — yanıt hiç gelmedi (zaman aşımı ya da ağ)';
    $snip = trim(preg_replace('/\s+/', ' ', strip_tags($err_buf)));
    sse('error', ['error' =>
        'İçerik üretilemedi (HTTP ' . $http_code . ')' . $tip .
        ($msg ? ' · ' . $msg : ($snip ? ' · ' . mb_substr($snip, 0, 200) : ''))
    ]);
    exit;
}

sse('done',[
    'word_count'    => str_word_count(strip_tags($full_content)),
    'input_tokens'  => $input_tokens,
    'output_tokens' => $output_tokens,
    'stop_reason'   => $stop_reason,
]);
