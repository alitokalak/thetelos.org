<?php
/**
 * list-works.php — Bir yazarın TÜM eserlerini üret (LLM) + OpenLibrary ile doğrula/zenginleştir.
 * POST: author, api_provider (deepseek|anthropic), verify (1|0)
 * Dönüş: { ok, author, works:[{title, original, year, verified, cover, ol_key}] }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');
@ini_set('display_errors', 0);
set_time_limit(300);

$author   = trim($_POST['author'] ?? '');
$provider = trim($_POST['api_provider'] ?? 'deepseek');
$verify   = ($_POST['verify'] ?? '1') === '1';

if ($author === '') { echo json_encode(['ok'=>false,'error'=>'Yazar adı zorunlu.']); exit; }

// ── LLM çağrısı (JSON döndüren, stream değil) ─────────────────────
function lw_call_llm($provider, $prompt, $max_tokens = 4000) {
    if ($provider === 'anthropic') {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>180,
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
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>180,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>DEEPSEEK_MODEL,'max_tokens'=>$max_tokens,'messages'=>[['role'=>'user','content'=>$prompt]]]),
    ]);
    $r = curl_exec($ch); $e = curl_error($ch); curl_close($ch);
    if ($e || !$r) return ['', 'DeepSeek: '.$e];
    $d = json_decode($r, true);
    if (isset($d['error'])) return ['', 'DeepSeek: '.($d['error']['message']??'hata')];
    return [$d['choices'][0]['message']['content'] ?? '', ''];
}

// JSON dizisini güvenli ayıkla (markdown fence / fazla metin temizle)
function lw_extract_json_array($text) {
    $text = preg_replace('/```json|```/i', '', $text);
    $s = strpos($text, '[');
    $e = strrpos($text, ']');
    if ($s === false || $e === false || $e <= $s) return null;
    $json = substr($text, $s, $e - $s + 1);
    $arr = json_decode($json, true);
    return is_array($arr) ? $arr : null;
}

// ── 1) Yazarın tüm eserleri — model "başka yok" diyene kadar döngü ──
$base_prompt = "List the COMPLETE works of the author \"{$author}\".\n"
    . "For EACH work provide:\n"
    . "- the English title\n"
    . "- the original-language title (if different), e.g. Latin/Greek/German/French\n"
    . "- the approximate first publication or composition year (integer)\n"
    . "Include all significant books, treatises and major works in roughly chronological order. "
    . "Exclude minor letters and fragments unless they are major standalone works.\n"
    . "Return ONLY a valid JSON array — no prose, no markdown fences:\n"
    . "[{\"title\":\"English Title\",\"original\":\"Original Title\",\"year\":1234}]";

$items      = [];
$seen       = [];
$max_rounds = 6;
$first_err  = '';

for ($round = 1; $round <= $max_rounds; $round++) {
    if ($round === 1) {
        $prompt = $base_prompt;
    } else {
        // Şimdiye kadar listelenenleri ver, SADECE eksik kalanları iste
        $already = implode("\n", array_map(fn($w) => '- ' . $w['title'], $items));
        $prompt  = "The author is \"{$author}\".\n"
            . "These works have ALREADY been listed:\n{$already}\n\n"
            . "Now list ONLY ADDITIONAL works by this author that are NOT in the list above. "
            . "Do not repeat any listed work. If there are no more works, return an empty array [].\n"
            . "Same JSON format: [{\"title\":\"English Title\",\"original\":\"Original Title\",\"year\":1234}]";
    }

    list($raw, $err) = lw_call_llm($provider, $prompt, 8000);
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

    if ($added === 0) break;          // model'in ekleyecek yeni eseri kalmadı
    if (count($items) >= 250) break;  // güvenlik üst sınırı
}

if (!$items) { echo json_encode(['ok'=>false,'error'=>$first_err ?: 'Liste alınamadı.']); exit; }

// ── 2) Doğrula + kapak getir (cURL — allow_url_fopen kapalı olabilir) ──
function lw_http_get($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
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

$works = [];
$max_items = 120; // güvenlik üst sınırı
$n = 0;
foreach ($items as $it) {
    if ($n >= $max_items) break;
    $title    = trim($it['title'] ?? '');
    $original = trim($it['original'] ?? '');
    $year     = isset($it['year']) ? (int)$it['year'] : null;
    if ($title === '') continue;

    $row = [
        'title'    => $title,
        'original' => ($original && stripos($original, $title) === false) ? $original : '',
        'year'     => $year,
        'verified' => false,
        'cover'    => '',
        'ol_key'   => '',
    ];

    if ($verify) {
        $v = lw_ol_verify($title, $author);
        if ($v) {
            $row['verified'] = true;
            $row['cover']    = $v['cover'];
            $row['ol_key']   = $v['ol_key'];
            if (!$row['year'] && $v['ol_year']) $row['year'] = $v['ol_year'];
        }
    }
    $works[] = $row;
    $n++;
}

echo json_encode([
    'ok'       => true,
    'author'   => $author,
    'count'    => count($works),
    'verified' => count(array_filter($works, fn($w)=>$w['verified'])),
    'works'    => $works,
], JSON_UNESCAPED_UNICODE);
