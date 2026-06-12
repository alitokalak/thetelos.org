<?php
/**
 * queue-create.php — Kategori bazlı otomatik kuyruk oluştur
 * POST: category, author_count (max 50), type, post_status, max_tokens, api_provider
 *
 * Akış (tarayıcı bağlantısı kapandıktan sonra da devam eder):
 *   1) LLM → kategori için yazar listesi
 *   2) Her yazar için Firebase → OpenLibrary → LLM eser listesi
 *   3) Batch dosyasına yaz (mevcut batch sistemi ile uyumlu)
 *   4) batch_id döner → batch-worker.php ile işlenir
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
ignore_user_abort(true);
@ini_set('max_execution_time', '0');
set_time_limit(0);

$category     = trim($_POST['category']     ?? '');
$author_count = max(10, min(50, (int)($_POST['author_count'] ?? 50)));
$type         = trim($_POST['type']         ?? 'summary');
$post_status  = trim($_POST['post_status']  ?? 'draft');
$max_tokens   = max(500, min(8000, (int)($_POST['max_tokens'] ?? 3000)));
$api_provider = 'deepseek';
$parts        = max(1, min(4, (int)($_POST['parts'] ?? 2)));

if ($category === '') { echo json_encode(['ok'=>false,'error'=>'Kategori zorunlu.']); exit; }

$jobs_dir = dirname(__DIR__) . '/jobs';
if (!is_dir($jobs_dir)) mkdir($jobs_dir, 0755, true);

$batch_id   = 'queue_' . preg_replace('/[^a-z0-9]/', '_', strtolower($category)) . '_' . time();
$batch_file = "$jobs_dir/{$batch_id}.json";

// Başlangıç dosyasını hemen yaz — JS polling yapabilsin
$batch = [
    'id'           => $batch_id,
    'category'     => $category,
    'status'       => 'building',   // building → running → done
    'created_at'   => time(),
    'type'         => $type,
    'post_status'  => $post_status,
    'max_tokens'   => $max_tokens,
    'api_provider' => $api_provider,
    'parts'        => $parts,
    'total'        => 0,
    'done'         => 0,
    'ok'           => 0,
    'failed'       => 0,
    'build_msg'    => 'Yazarlar getiriliyor...',
    'books'        => [],
];
file_put_contents($batch_file, json_encode($batch, JSON_UNESCAPED_UNICODE));

// Tarayıcıya hemen batch_id dön
echo json_encode(['ok'=>true, 'batch_id'=>$batch_id]);
if (ob_get_level()) ob_end_flush();
flush();
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

// ─── Artık arka planda çalışıyoruz ────────────────────────────────

function qc_save(&$batch, $file) {
    file_put_contents($file, json_encode($batch, JSON_UNESCAPED_UNICODE));
}

function qc_call_llm($prompt, $max_tokens = 4000) {
    $ch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 85,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => DEEPSEEK_MODEL, 'max_tokens' => $max_tokens,
            'messages' => [['role'=>'user','content'=>$prompt]],
        ]),
    ]);
    $r = curl_exec($ch); curl_close($ch);
    $d = json_decode($r, true);
    return $d['choices'][0]['message']['content'] ?? '';
}

function qc_extract_json_array($text) {
    $text = preg_replace('/```json|```/i', '', $text);
    $s = strpos($text, '['); if ($s === false) return [];
    $e = strrpos($text, ']');
    $json = ($e !== false && $e > $s) ? substr($text, $s, $e-$s+1) : substr($text, $s);
    $arr = json_decode($json, true);
    if (is_array($arr)) return $arr;
    if (($last = strrpos($json, '}')) !== false) {
        $arr = json_decode(substr($json, 0, $last+1).']', true);
        if (is_array($arr)) return $arr;
    }
    return [];
}

// ── 1. Yazar listesi (LLM) ────────────────────────────────────────
$author_prompt = "List the {$author_count} most important and influential authors in the field of \"{$category}\" across all of history. "
    . "Order strictly by overall importance/influence.\n"
    . "Return ONLY a valid JSON array:\n"
    . '[{"author":"Full Name","era":"period or century","note":"one short phrase"}]';

$raw     = qc_call_llm($author_prompt, 4000);
$authors = qc_extract_json_array($raw);

if (empty($authors)) {
    $batch['status']    = 'error';
    $batch['build_msg'] = 'Yazar listesi alınamadı.';
    qc_save($batch, $batch_file);
    exit;
}

$batch['build_msg'] = count($authors) . ' yazar bulundu, eserler getiriliyor...';
qc_save($batch, $batch_file);

define('FIREBASE_URL', 'https://thetelos-db-default-rtdb.europe-west1.firebasedatabase.app');

function qc_fb_norm($name) {
    return strtr(mb_strtolower(trim($name)), ['.'=>'_','#'=>'_','$'=>'_','['=>'_',']'=>'_','/'=>'_']);
}

function qc_fetch_works_firebase($author) {
    $norm = qc_fb_norm($author);
    $url  = FIREBASE_URL . '/yazarlar/' . rawurlencode($norm) . '.json?limitToFirst=100';
    $ch   = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
    $raw  = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200 || !$raw || $raw === 'null') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) return [];
    $works = [];
    foreach ($data as $title) { if (trim($title)) $works[] = ['title'=>trim($title),'cover'=>'']; }
    return $works;
}

function qc_fetch_works_openlibrary($author) {
    $url = 'https://openlibrary.org/search.json?author=' . urlencode($author)
         . '&limit=50&fields=title,first_publish_year,cover_i&lang=eng';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15,
        CURLOPT_USERAGENT=>'thetelos.org/1.0']);
    $raw  = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200 || !$raw) return [];
    $data = json_decode($raw, true);
    $works = [];
    $seen  = [];
    foreach ($data['docs'] ?? [] as $doc) {
        $title = trim($doc['title'] ?? ''); if (!$title) continue;
        $key   = mb_strtolower($title); if (isset($seen[$key])) continue; $seen[$key] = true;
        $cover = !empty($doc['cover_i']) ? 'https://covers.openlibrary.org/b/id/'.$doc['cover_i'].'-M.jpg' : '';
        $works[] = ['title'=>$title,'cover'=>$cover,'year'=>(string)($doc['first_publish_year']??'')];
    }
    return $works;
}

function qc_fetch_works_llm($author) {
    $prompt = "List the most important works by {$author}. "
        . "Return ONLY a valid JSON array:\n"
        . '[{"title":"Work Title","year":"publication year"}]';
    $raw    = qc_call_llm($prompt, 2000);
    $items  = qc_extract_json_array($raw);
    $works  = [];
    foreach ($items as $it) {
        $t = trim($it['title'] ?? ''); if (!$t) continue;
        $works[] = ['title'=>$t,'cover'=>'','year'=>(string)($it['year']??'')];
    }
    return $works;
}

// ── 2. Her yazar için eser listesi ───────────────────────────────
$all_books = [];
foreach ($authors as $idx => $a) {
    $author_name = trim($a['author'] ?? '');
    if (!$author_name) continue;

    $batch['build_msg'] = "({$idx}/".count($authors).") {$author_name} eserleri getiriliyor...";
    qc_save($batch, $batch_file);

    // Öncelik: Firebase → OpenLibrary → LLM
    $works = qc_fetch_works_firebase($author_name);
    if (empty($works)) $works = qc_fetch_works_openlibrary($author_name);
    if (empty($works)) $works = qc_fetch_works_llm($author_name);

    foreach ($works as $w) {
        $title = trim($w['title'] ?? ''); if (!$title) continue;
        $all_books[] = [
            'book_title'  => $title,
            'author_name' => $author_name,
            'cover_url'   => $w['cover']  ?? '',
            'year'        => $w['year']   ?? '',
            'status'      => 'pending',
            'post_id'     => null, 'post_url'=>null, 'edit_url'=>null,
            'cover_set'   => false, 'error'=>'',
        ];
    }
}

if (empty($all_books)) {
    $batch['status']    = 'error';
    $batch['build_msg'] = 'Hiç eser bulunamadı.';
    qc_save($batch, $batch_file);
    exit;
}

// ── 3. Batch dosyasını güncelle — işleme hazır ────────────────────
$batch['status']    = 'running';
$batch['total']     = count($all_books);
$batch['build_msg'] = 'Kuyruk hazır, işleme başlandı.';
$batch['books']     = $all_books;
qc_save($batch, $batch_file);
