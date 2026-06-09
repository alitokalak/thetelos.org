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

// ── 1) Yazarın tüm eserleri ───────────────────────────────────────
$prompt = "List the COMPLETE works of the author \"{$author}\".\n"
    . "For EACH work provide:\n"
    . "- the English title\n"
    . "- the original-language title (if different), e.g. Latin/Greek/German/French\n"
    . "- the approximate first publication or composition year (integer)\n"
    . "Include all significant books, treatises and major works in roughly chronological order. "
    . "Exclude minor letters and fragments unless they are major standalone works.\n"
    . "Return ONLY a valid JSON array — no prose, no markdown fences:\n"
    . "[{\"title\":\"English Title\",\"original\":\"Original Title\",\"year\":1234}]";

list($raw, $err) = lw_call_llm($provider, $prompt, 6000);
if ($err) { echo json_encode(['ok'=>false,'error'=>$err]); exit; }

$items = lw_extract_json_array($raw);
if (!$items) { echo json_encode(['ok'=>false,'error'=>'Liste ayrıştırılamadı. Yanıt: '.mb_substr($raw,0,200)]); exit; }

// ── 2) OpenLibrary ile doğrula + zenginleştir ─────────────────────
function lw_ol_verify($title, $author) {
    $url = 'https://openlibrary.org/search.json?' . http_build_query([
        'title'  => $title,
        'author' => $author,
        'limit'  => 1,
        'fields' => 'key,title,first_publish_year,cover_i',
    ]);
    $ctx = stream_context_create(['http'=>['timeout'=>6,'user_agent'=>'ThetelosBot/1.0 (thetelos.org)']]);
    $r = @file_get_contents($url, false, $ctx);
    if (!$r) return null;
    $d = json_decode($r, true);
    if (empty($d['docs'][0])) return null;
    $doc = $d['docs'][0];
    return [
        'ol_key'      => $doc['key'] ?? '',
        'ol_year'     => $doc['first_publish_year'] ?? null,
        'cover'       => !empty($doc['cover_i']) ? "https://covers.openlibrary.org/b/id/{$doc['cover_i']}-M.jpg" : '',
        'ol_title'    => $doc['title'] ?? '',
    ];
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
