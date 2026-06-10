<?php
/**
 * list-works.php — Yazarın eserlerini üret (LLM) veya OpenLibrary ile doğrula.
 *
 * İki mod (Cloudflare ~100s timeout'u aşmamak için ayrıldı):
 *   mode=list   : Sadece LLM ile eser listesi (hızlı, doğrulama yok).
 *                 POST: author, api_provider
 *                 Dönüş: { ok, author, count, works:[{title, original, year}] }
 *   mode=verify : Bir grup başlığı OpenLibrary/Google ile doğrula + kapak getir.
 *                 POST: author, titles (JSON array), api_provider
 *                 Dönüş: { ok, results:[{title, verified, cover, year, ol_key}] }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');
@ini_set('display_errors', 0);
set_time_limit(300);

$author = trim($_POST['author'] ?? '');
$provider = trim($_POST['api_provider'] ?? 'deepseek');
$mode = trim($_POST['mode'] ?? 'list');

if ($author === '') { echo json_encode(['ok'=>false,'error'=>'Yazar adı zorunlu.']); exit; }

// ── LLM çağrısı (JSON döndüren, stream değil) ─────────────────────
function lw_call_llm($provider, $prompt, $max_tokens = 4000) {
    if ($provider === 'anthropic') {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>120,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.ANTHROPIC_KEY,'anthropic-version: 2023-06-01'],
            CURLOPT_POSTFIELDS=>json_encode(['model'=>ANTHROPIC_MODEL,'max_tokens'=>$max_tokens,'messages'=>[['role'=>'user','content'=>$prompt]]]),
        ]);
        $r = curl_exec($ch); $e = curl_error($ch); curl_close($ch);
        if ($e || !$r) return ['', 'Anthropic: '.$e];
        $d = json_decode($r, true);
        if (isset($d['error'])) return ['', 'Anthropic: '.($d['error']['message']??'hata')];
        return [$d['content'][0]['text'] ?? '', ''];
    }
    // deepseek
    $ch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>120,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>DEEPSEEK_MODEL,'max_tokens'=>$max_tokens,'messages'=>[['role'=>'user','content'=>$prompt]]]),
    ]);
    $r = curl_exec($ch); $e = curl_error($ch); curl_close($ch);
    if ($e || !$r) return ['', 'DeepSeek: '.$e];
    $d = json_decode($r, true);
    if (isset($d['error'])) return ['', 'DeepSeek: '.($d['error']['message']??'hata')];
    return [$d['choices'][0]['message']['content'] ?? '', ''];
}

// JSON dizisini güvenli ayıkla (markdown fence / fazla metin / kesik JSON temizle)
function lw_extract_json_array($text) {
    $text = preg_replace('/```json|```/i', '', $text);
    $s = strpos($text, '[');
    if ($s === false) return null;
    $e = strrpos($text, ']');
    $json = ($e !== false && $e > $s) ? substr($text, $s, $e - $s + 1) : substr($text, $s);
    $arr = json_decode($json, true);
    if (is_array($arr)) return $arr;
    // Kesik JSON onarımı: son tam nesneye kadar kırp ve diziyi kapat
    if (($lastObj = strrpos($json, '}')) !== false) {
        $repaired = substr($json, 0, $lastObj + 1) . ']';
        $arr = json_decode($repaired, true);
        if (is_array($arr)) return $arr;
    }
    return null;
}

// ── cURL GET (allow_url_fopen kapalı olabilir) ────────────────────
function lw_http_get($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['User-Agent: ThetelosBot/1.0 (+https://thetelos.org)'],
    ]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($c >= 200 && $c < 300) ? $r : null;
}

function lw_ol_verify($title, $author) {
    // 1) OpenLibrary — başlık + yazar
    $r = lw_http_get('https://openlibrary.org/search.json?' . http_build_query([
        'title'  => $title,
        'author' => $author,
        'limit'  => 1,
        'fields' => 'key,title,first_publish_year,cover_i',
    ]));
    if ($r) {
        $d = json_decode($r, true);
        if (!empty($d['docs'][0])) {
            $doc = $d['docs'][0];
            return [
                'ol_key'   => $doc['key'] ?? '',
                'ol_year'  => $doc['first_publish_year'] ?? null,
                'cover'    => !empty($doc['cover_i']) ? "https://covers.openlibrary.org/b/id/{$doc['cover_i']}-M.jpg" : '',
                'ol_title' => $doc['title'] ?? '',
            ];
        }
    }

    // 2) Google Books — kapak için (intitle + inauthor)
    $r2 = lw_http_get('https://www.googleapis.com/books/v1/volumes?' . http_build_query([
        'q'          => 'intitle:' . $title . ' inauthor:' . $author,
        'maxResults' => 1,
        'printType'  => 'books',
        'fields'     => 'items(volumeInfo(imageLinks,publishedDate))',
    ]));
    if ($r2) {
        $d2 = json_decode($r2, true);
        $vi = $d2['items'][0]['volumeInfo'] ?? null;
        if ($vi) {
            $c = $vi['imageLinks']['thumbnail'] ?? ($vi['imageLinks']['smallThumbnail'] ?? '');
            if ($c) {
                $c = str_replace(['http://', '&edge=curl'], ['https://', ''], $c);
                $c = preg_replace('/zoom=\d/', 'zoom=1', $c);
                $yr = null;
                if (!empty($vi['publishedDate']) && preg_match('/(\d{4})/', $vi['publishedDate'], $ym)) $yr = (int)$ym[1];
                return ['ol_key'=>'', 'ol_year'=>$yr, 'cover'=>$c, 'ol_title'=>''];
            }
        }
    }

    return null;
}

/* ════════════════ MODE: verify ════════════════ */
if ($mode === 'verify') {
    $titles = json_decode($_POST['titles'] ?? '[]', true);
    if (!is_array($titles)) { echo json_encode(['ok'=>false,'error'=>'titles geçersiz.']); exit; }
    $titles = array_slice($titles, 0, 10); // güvenli üst sınır (10×~12s < 100s)
    $results = [];
    foreach ($titles as $t) {
        $t = trim((string)$t);
        if ($t === '') continue;
        $row = ['title'=>$t, 'verified'=>false, 'cover'=>'', 'year'=>null, 'ol_key'=>''];
        $v = lw_ol_verify($t, $author);
        if ($v) {
            $row['verified'] = true;
            $row['cover']    = $v['cover'];
            $row['ol_key']   = $v['ol_key'];
            $row['year']     = $v['ol_year'];
        }
        $results[] = $row;
    }
    echo json_encode(['ok'=>true, 'results'=>$results], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ════════════════ MODE: list ════════════════ */
$base_prompt = "List the COMPLETE works of the author \"{$author}\".\n"
    . "For EACH work provide:\n"
    . "- the standard English title by which the work is known in scholarship\n"
    . "- the original-language title (if different), e.g. Latin/Greek/German/French/Arabic/Hebrew\n"
    . "- the approximate first publication or composition year (integer)\n"
    . "Include all significant books, treatises and major works in roughly chronological order. "
    . "Exclude minor letters and fragments unless they are major standalone works.\n"
    . "CRITICAL RULES:\n"
    . "- Only list works that genuinely belong to this author. If you are not sure a work is really his, do NOT include it.\n"
    . "- Each distinct work must appear EXACTLY ONCE. Do NOT list the same work twice under different English renderings, "
    . "translations or labels such as '(alternative)', '(Hebrew version)', '(summary)'. Merge variants into one entry.\n"
    . "Return ONLY a valid JSON array — no prose, no markdown fences:\n"
    . "[{\"title\":\"English Title\",\"original\":\"Original Title\",\"year\":1234}]";

$items      = [];
$seen       = [];
$max_rounds = 5;
$first_err  = '';
$started    = microtime(true);

for ($round = 1; $round <= $max_rounds; $round++) {
    // Cloudflare ~100s sınırı: süreyi aşmamak için döngüyü erken bitir
    if ($round > 1 && (microtime(true) - $started) > 70) break;

    if ($round === 1) {
        $prompt = $base_prompt;
    } else {
        $already = implode("\n", array_map(fn($w) => '- ' . $w['title'], $items));
        $prompt  = "The author is \"{$author}\".\n"
            . "These works have ALREADY been listed:\n{$already}\n\n"
            . "Now list ONLY ADDITIONAL works by this author that are NOT in the list above. "
            . "Do not repeat any listed work, and do not list translation/variant duplicates of works already listed. "
            . "Only include works you are genuinely sure belong to this author. "
            . "If there are no more works, return an empty array [].\n"
            . "Same JSON format: [{\"title\":\"English Title\",\"original\":\"Original Title\",\"year\":1234}]";
    }

    list($raw, $err) = lw_call_llm($provider, $prompt, 4000);
    if ($err) { if ($round === 1) { $first_err = $err; } break; }

    $batch = lw_extract_json_array($raw);
    if (!is_array($batch)) { if ($round === 1) { $first_err = 'Liste ayrıştırılamadı.'; } break; }

    $added = 0;
    foreach ($batch as $it) {
        $t = trim($it['title'] ?? '');
        if ($t === '') continue;
        $k = mb_strtolower($t);
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $items[] = $it;
        $added++;
    }

    if ($added === 0) break;
    if (count($items) >= 250) break;
}

if (!$items) { echo json_encode(['ok'=>false,'error'=>$first_err ?: 'Liste alınamadı.']); exit; }

$works = [];
foreach ($items as $it) {
    $title = trim($it['title'] ?? '');
    if ($title === '') continue;
    $original = trim($it['original'] ?? '');
    $works[] = [
        'title'    => $title,
        'original' => ($original && stripos($original, $title) === false) ? $original : '',
        'year'     => isset($it['year']) ? (int)$it['year'] : null,
        'verified' => false,
        'cover'    => '',
        'ol_key'   => '',
    ];
}

echo json_encode([
    'ok'     => true,
    'author' => $author,
    'count'  => count($works),
    'works'  => $works,
], JSON_UNESCAPED_UNICODE);
