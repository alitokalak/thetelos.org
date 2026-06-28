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

// Yazar kaynağı:
//  1) Builder doğrudan listeyi gönderdiyse (POST authors) → onu kullan (Wikidata + "sitede olanları çıkar" sonrası).
//  2) Yoksa Wikidata'dan çek (AI YOK — deterministik, kanonik adlar).
$authors = [];
$posted = json_decode($_POST['authors'] ?? '', true);
if (is_array($posted) && !empty($posted)) {
    foreach ($posted as $a) {
        if (is_array($a)) {
            $name = trim($a['author'] ?? '');
            $authors[] = ['author'=>$name,'era'=>trim($a['era']??''),'note'=>trim($a['note']??'')];
        } else {
            $name = trim((string)$a);
            $authors[] = ['author'=>$name,'era'=>'','note'=>''];
        }
    }
    $authors = array_values(array_filter($authors, fn($x) => $x['author'] !== ''));
} else {
    require_once __DIR__ . '/_wikidata-authors.php';
    $wd = tls_wikidata_authors($category, $author_count, $offset);
    if (!$wd['ok']) { echo json_encode(['ok'=>false,'error'=>$wd['error']]); exit; }
    $authors = $wd['authors'];
}

if (empty($authors)) {
    echo json_encode(['ok'=>false,'error'=>'Yazar listesi boş.']); exit;
}

// Batch dosyası oluştur (books henüz boş, status=building)
$jobs_dir = dirname(__DIR__) . '/jobs';
if (!is_dir($jobs_dir)) mkdir($jobs_dir, 0755, true);
$batch_id   = 'queue_' . preg_replace('/[^a-z0-9]/', '_', strtolower($category)) . '_' . time();
$batch_file = "$jobs_dir/{$batch_id}.json";

$batch = [
    'id'           => $batch_id,
    'category'     => $category,
    'list_only'    => !empty($_POST['list_only']),  // true → yalnız liste; içerik ÜRETİLMEZ
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
