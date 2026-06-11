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
$offset   = max(0, (int)($_POST['offset'] ?? 0));   // sayfalama: kaçıncı sıradan başla
$provider = 'deepseek';

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

// 100+ yazar tek seferde DeepSeek'i 85sn sınırını zorlayabilir; 50'şerli turlarla ilerle.
$batch_size = 50;
$items = [];
$seen_names = [];
$first_err = '';

$total_fetched = 0;
while ($total_fetched < $count) {
    set_time_limit(110);
    $batch_count = min($batch_size, $count - $total_fetched);
    $rank_start  = $offset + $total_fetched + 1;
    $rank_end    = $offset + $total_fetched + $batch_count;

    $already = $total_fetched > 0 || $offset > 0
        ? "IMPORTANT: Do NOT include any of the top " . ($offset + $total_fetched) . " already listed. Start from rank {$rank_start}.\n"
        : "";

    $prompt = "List ranks {$rank_start} through {$rank_end} of the most important and influential authors "
        . "in the field of \"{$category}\" across all of history, "
        . "whose body of work merits scholarly publication and study.\n"
        . $already
        . "Order strictly by overall importance/influence.\n"
        . "Return ONLY a valid JSON array — no prose, no markdown fences:\n"
        . "[{\"author\":\"Full Name\",\"era\":\"period or century\",\"note\":\"one short phrase on their significance\"}]";

    [$raw, $err] = la_call_llm($provider, $prompt, 4000);
    if ($err) { $first_err = $err; break; }

    $batch = la_extract_json_array($raw);
    if (!$batch) { $first_err = 'Liste ayrıştırılamadı.'; break; }

    $added = 0;
    foreach ($batch as $it) {
        $name = trim($it['author'] ?? '');
        if ($name === '' || isset($seen_names[strtolower($name)])) continue;
        $seen_names[strtolower($name)] = true;
        $items[] = $it;
        $added++;
    }
    $total_fetched += $batch_count;
    if ($added === 0) break; // boş tur, dur
}

if (!$items && $first_err) { echo json_encode(['ok'=>false,'error'=>$first_err]); exit; }
if (!$items) { echo json_encode(['ok'=>false,'error'=>'Liste alınamadı.']); exit; }

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
    'offset'   => $offset,
    'count'    => count($authors),
    'authors'  => $authors,
], JSON_UNESCAPED_UNICODE);
