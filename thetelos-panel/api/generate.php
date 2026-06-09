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
$api_provider = trim($_POST['api_provider'] ?? 'anthropic'); // 'anthropic' veya 'deepseek'
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

$part         = trim($_POST['part']         ?? '');   // '1', '2', veya boş
$part1_content = trim($_POST['part1_content'] ?? '');  // Part 2 için bağlam

$prompt = str_replace(
    ['{book_title}','{author_name}','{BOOK_TITLE}','{AUTHOR_NAME}'],
    [$book,$author,$book,$author],
    $template
) . "\n\nBook: {$book}\nAuthor: {$author}\nTarget length: approximately {$target_words} words.";

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

// Web search — sadece DeepSeek için çalıştır (Anthropic kendi bilgisini kullanır)
$source_text = '';
$source_url  = '';
if ($api_provider === 'deepseek' && !$part || $part === '1') {
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

if ($part === '1') {
    $prompt .= "\n\nIMPORTANT: You are writing PART 1 of 2. Write the first half of the book only. Cover roughly half the book's content. End your response naturally at a section boundary — stop mid-book, not at the end. Do NOT write a conclusion or closing paragraph.";
} elseif ($part === '2') {
    $prompt .= "\n\nIMPORTANT: You are writing PART 2 of 2 — the direct continuation of a summary already written. STRICT RULES:\n"
             . "1. DO NOT write the H1 or H2 heading.\n"
             . "2. DO NOT introduce or re-summarize topics from Part 1. Each topic listed below is FULLY AND COMPLETELY FINISHED — do not revisit, expand, or repeat any of them.\n"
             . "3. Start immediately with the NEXT new ### section that was NOT covered in Part 1.\n"
             . "4. This is a seamless continuation — write as if you are picking up exactly where Part 1 ended.\n";

    if ($part1_content) {
        preg_match_all('/^### (.+)$/m', $part1_content, $matches);
        $headings = $matches[1] ?? [];
        if ($headings) {
            $prompt .= "\nThe following sections are FULLY COMPLETE — DO NOT touch them again:\n";
            foreach ($headings as $h) {
                $prompt .= "✗ {$h}\n";
            }
        }
        $prompt .= "\nPart 1 ended mid-text here (continue from this exact point):\n..." . mb_substr($part1_content, -500);
    }
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

$provider_label = $api_provider === 'deepseek' ? 'DeepSeek' : 'Claude';
sse('status', ['msg' => $provider_label . ' içerik üretiyor...']);

$full_content  = '';
$input_tokens  = 0;
$output_tokens = 0;
$stop_reason   = '';
$stream_buf    = '';
$raw_buf       = '';
$last_ping     = time();

if ($api_provider === 'deepseek') {

    // ── DeepSeek SSE ──────────────────────────────────────────
    $write_fn = function($ch, $chunk) use (&$full_content, &$input_tokens, &$output_tokens, &$stop_reason, &$stream_buf, &$raw_buf, &$last_ping) {
        $raw_buf .= $chunk;

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

    $ch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_TIMEOUT       => 280,
        CURLOPT_HTTPHEADER    => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . DEEPSEEK_KEY,
        ],
        CURLOPT_POSTFIELDS    => json_encode([
            'model'       => DEEPSEEK_MODEL,
            'max_tokens'  => $max_tokens,
            'stream'      => true,
            'messages'    => [['role'=>'user','content'=>$prompt]],
        ]),
        CURLOPT_WRITEFUNCTION => $write_fn,
    ]);

} else {

    // ── Anthropic SSE ─────────────────────────────────────────
    $write_fn = function($ch, $chunk) use (&$full_content, &$input_tokens, &$output_tokens, &$stop_reason, &$stream_buf, &$raw_buf, &$last_ping) {
        $raw_buf .= $chunk;

        if (time() - $last_ping >= 10) {
            echo ": ping\n\n";
            flush();
            $last_ping = time();
        }

        while (($pos = strpos($raw_buf, "\n")) !== false) {
            $line    = substr($raw_buf, 0, $pos);
            $raw_buf = substr($raw_buf, $pos + 1);
            $line    = trim($line);
            if (!$line || $line === 'event: ping') continue;
            if (strpos($line, 'data: ') === 0) {
                $json = substr($line, 6);
                if ($json === '[DONE]') continue;
                $ev = json_decode($json, true);
                if (!$ev) continue;
                $ev_type = $ev['type'] ?? '';
                if ($ev_type === 'content_block_delta') {
                    $text = $ev['delta']['text'] ?? '';
                    if ($text !== '') {
                        $full_content .= $text;
                        $stream_buf   .= $text;
                        if (strlen($stream_buf) >= 200) {
                            sse('chunk', ['text' => $stream_buf]);
                            $stream_buf = '';
                        }
                    }
                } elseif ($ev_type === 'message_delta') {
                    $stop_reason   = $ev['delta']['stop_reason'] ?? '';
                    $output_tokens = $ev['usage']['output_tokens'] ?? 0;
                } elseif ($ev_type === 'message_start') {
                    $input_tokens  = $ev['message']['usage']['input_tokens'] ?? 0;
                }
            }
        }
        return strlen($chunk);
    };

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_TIMEOUT       => 280,
        CURLOPT_HTTPHEADER    => [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS    => json_encode([
            'model'      => $api_model ?: 'claude-haiku-4-5-20251001',
            'max_tokens' => $max_tokens,
            'stream'     => true,
            'messages'   => [['role'=>'user','content'=>$prompt]],
        ]),
        CURLOPT_WRITEFUNCTION => $write_fn,
    ]);
}

curl_exec($ch);
$curl_err = curl_error($ch);
curl_close($ch);

if ($stream_buf !== '') {
    sse('chunk', ['text' => $stream_buf]);
}

if ($curl_err) { sse('error', ['error' => 'cURL: ' . $curl_err]); exit; }
if (!$full_content) { sse('error', ['error' => 'İçerik üretilemedi.']); exit; }

sse('done',[
    'word_count'    => str_word_count(strip_tags($full_content)),
    'input_tokens'  => $input_tokens,
    'output_tokens' => $output_tokens,
    'stop_reason'   => $stop_reason,
]);
