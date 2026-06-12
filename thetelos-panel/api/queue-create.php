<?php
/**
 * queue-create.php — Sadece yazar listesini alır, batch dosyası oluşturur (hızlı)
 * POST: category, author_count, type, post_status, max_tokens
 * Dönüş: { ok, batch_id, authors:[...] }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
@ini_set('display_errors', 0);
set_time_limit(90);

$category     = trim($_POST['category']     ?? '');
$author_count = max(10, min(50, (int)($_POST['author_count'] ?? 50)));
$offset       = max(0, (int)($_POST['offset'] ?? 0));
$type         = 'summary';
$post_status  = 'draft';
$max_tokens   = 3000;
$parts        = 2;

if ($category === '') { echo json_encode(['ok'=>false,'error'=>'Kategori zorunlu.']); exit; }

// LLM → yazar listesi
$ch = curl_init(DEEPSEEK_API_URL);
$rank_start = $offset + 1;
$rank_end   = $offset + $author_count;
$prompt = "List ranks {$rank_start} through {$rank_end} of the most important authors in \"{$category}\" across all of history, ordered by importance.\n"
        . ($offset > 0 ? "Do NOT include the top {$offset} most important authors — they are already listed.\n" : '')
        . 'Return ONLY a valid JSON array: [{"author":"Full Name","era":"century","note":"brief"}]';
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>80,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
    CURLOPT_POSTFIELDS=>json_encode(['model'=>DEEPSEEK_MODEL,'max_tokens'=>4000,
        'messages'=>[['role'=>'user','content'=>$prompt]]]),
]);
$raw = curl_exec($ch); curl_close($ch);
$d   = json_decode($raw, true);
$txt = $d['choices'][0]['message']['content'] ?? '';

// JSON dizi ayrıştır
$txt   = preg_replace('/```json|```/i', '', $txt);
$s     = strpos($txt, '['); $e = strrpos($txt, ']');
$arr   = ($s!==false && $e>$s) ? json_decode(substr($txt,$s,$e-$s+1), true) : [];
if (!is_array($arr) || empty($arr)) {
    echo json_encode(['ok'=>false,'error'=>'Yazar listesi alınamadı. Tekrar dene.']); exit;
}

$authors = [];
foreach ($arr as $a) {
    $name = trim($a['author'] ?? ''); if (!$name) continue;
    $authors[] = ['author'=>$name,'era'=>trim($a['era']??''),'note'=>trim($a['note']??'')];
}

// Batch dosyası oluştur (books henüz boş, status=building)
$jobs_dir = dirname(__DIR__) . '/jobs';
if (!is_dir($jobs_dir)) mkdir($jobs_dir, 0755, true);
$batch_id   = 'queue_' . preg_replace('/[^a-z0-9]/', '_', strtolower($category)) . '_' . time();
$batch_file = "$jobs_dir/{$batch_id}.json";

$batch = [
    'id'           => $batch_id,
    'category'     => $category,
    'status'       => 'building',
    'created_at'   => time(),
    'type'         => $type,
    'post_status'  => $post_status,
    'max_tokens'   => $max_tokens,
    'parts'        => $parts,
    'api_provider' => 'deepseek',
    'total'        => 0,
    'done'         => 0, 'ok'=>0, 'failed'=>0,
    'build_msg'     => 'Yazarlar alındı, eserler getiriliyor...',
    'author_offset' => $offset,
    'authors_total' => count($authors),
    'authors_built' => 0,
    'authors'       => $authors,
    'books'        => [],
];
file_put_contents($batch_file, json_encode($batch, JSON_UNESCAPED_UNICODE));

echo json_encode(['ok'=>true,'batch_id'=>$batch_id,'authors'=>$authors], JSON_UNESCAPED_UNICODE);
