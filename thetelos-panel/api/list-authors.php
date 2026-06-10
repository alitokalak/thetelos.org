<?php
/**
 * list-authors.php — Bir kategorinin en önemli yazarlarını üret (LLM).
 * POST: category, count (varsayılan 40), api_provider
 * Dönüş: { ok, category, authors:[{author, era, note}] }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();  // KRİTİK: kilidi hemen bırak — yoksa LLM çağrısı boyunca tüm panel bloke olur
header('Content-Type: application/json');
@ini_set('display_errors', 0);
set_time_limit(120);

$category = trim($_POST['category'] ?? '');
$count    = max(5, min(150, (int)($_POST['count'] ?? 40)));
$provider = trim($_POST['api_provider'] ?? 'deepseek');

if ($category === '') { echo json_encode(['ok'=>false,'error'=>'Kategori zorunlu.']); exit; }

function la_call_llm($provider, $prompt, $max_tokens = 4000) {
    if ($provider === 'anthropic') {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>85,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.ANTHROPIC_KEY,'anthropic-version: 2023-06-01'],
            CURLOPT_POSTFIELDS=>json_encode(['model'=>ANTHROPIC_MODEL,'max_tokens'=>$max_tokens,'messages'=>[['role'=>'user','content'=>$prompt]]]),
        ]);
        $r = curl_exec($ch); $e = curl_error($ch); curl_close($ch);
        if ($e || !$r) return ['', 'Anthropic: '.$e];
        $d = json_decode($r, true);
        if (isset($d['error'])) return ['', 'Anthropic: '.($d['error']['message']??'hata')];
        return [$d['content'][0]['text'] ?? '', ''];
    }
    $ch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>85,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>DEEPSEEK_MODEL,'max_tokens'=>$max_tokens,'messages'=>[['role'=>'user','content'=>$prompt]]]),
    ]);
    $r = curl_exec($ch); $e = curl_error($ch); curl_close($ch);
    if ($e || !$r) return ['', 'DeepSeek: '.$e];
    $d = json_decode($r, true);
    if (isset($d['error'])) return ['', 'DeepSeek: '.($d['error']['message']??'hata')];
    return [$d['choices'][0]['message']['content'] ?? '', ''];
}

function la_extract_json_array($text) {
    $text = preg_replace('/```json|```/i', '', $text);
    $s = strpos($text, '[');
    if ($s === false) return null;
    $e = strrpos($text, ']');
    $json = ($e !== false && $e > $s) ? substr($text, $s, $e - $s + 1) : substr($text, $s);
    $arr = json_decode($json, true);
    if (is_array($arr)) return $arr;
    // Kesik JSON onarımı: son tam nesneye kadar kırp ve diziyi kapat
    if (($lastObj = strrpos($json, '}')) !== false) {
        $arr = json_decode(substr($json, 0, $lastObj + 1) . ']', true);
        if (is_array($arr)) return $arr;
    }
    return null;
}

$prompt = "List the {$count} most important and influential authors in the field of \"{$category}\" across all of history, "
    . "whose body of work merits scholarly publication and study.\n"
    . "Order them by overall importance/influence.\n"
    . "Return ONLY a valid JSON array — no prose, no markdown fences:\n"
    . "[{\"author\":\"Full Name\",\"era\":\"period or century\",\"note\":\"one short phrase on their significance\"}]";

list($raw, $err) = la_call_llm($provider, $prompt, 6000);
if ($err) { echo json_encode(['ok'=>false,'error'=>$err]); exit; }

$items = la_extract_json_array($raw);
if (!$items) { echo json_encode(['ok'=>false,'error'=>'Liste ayrıştırılamadı. Yanıt: '.mb_substr($raw,0,200)]); exit; }

$authors = [];
foreach ($items as $it) {
    $name = trim($it['author'] ?? '');
    if ($name === '') continue;
    $authors[] = [
        'author' => $name,
        'era'    => trim($it['era'] ?? ''),
        'note'   => trim($it['note'] ?? ''),
    ];
}

echo json_encode([
    'ok'       => true,
    'category' => $category,
    'count'    => count($authors),
    'authors'  => $authors,
], JSON_UNESCAPED_UNICODE);
