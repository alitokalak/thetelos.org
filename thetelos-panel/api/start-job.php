<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }

@ini_set('max_execution_time', 600);
set_time_limit(600);
ignore_user_abort(true);

header('Content-Type: application/json');

$book      = trim($_POST['book_title']  ?? '');
$author    = trim($_POST['author_name'] ?? '');
$type      = trim($_POST['type']        ?? 'summary');
// Kullanıcının seçtiği token = hedef kelime sayısı
// max_tokens = hedef × 1.5 (Claude doğal bitiş yapsın, sistem asla kesmesin)
$target    = max(500, min(8000, (int)($_POST['max_tokens'] ?? 4000)));
$max_tokens= min(12000, (int)($target * 1.5));

if (!$book || !$author) {
    echo json_encode(['ok'=>false,'error'=>'Kitap adı ve yazar zorunludur.']);
    exit;
}

$prompts  = file_exists(PROMPTS_FILE) ? json_decode(file_get_contents(PROMPTS_FILE), true) : [];
$template = trim($prompts[$type] ?? '');
if (!$template) {
    echo json_encode(['ok'=>false,'error'=>'Prompt boş! Lütfen Ayarlar → ' . ($type==='analysis'?'Analiz':'Özet') . ' promptunu girin.']);
    exit;
}

// Job dosyası oluştur
$job_id   = uniqid('job_', true);
$job_dir  = dirname(__DIR__) . '/jobs';
if (!is_dir($job_dir)) mkdir($job_dir, 0755, true);
$job_file = "$job_dir/$job_id.json";

$job = [
    'id'=>$job_id, 'status'=>'processing', 'created_at'=>time(),
    'book_title'=>$book, 'author_name'=>$author, 'type'=>$type,
    'max_tokens'=>$max_tokens, 'target_tokens'=>$target,
    'content'=>'', 'word_count'=>0, 'input_tokens'=>0, 'output_tokens'=>0,
    'cache_read'=>0, 'cache_write'=>0, 'stop_reason'=>'',
    'excerpt'=>'', 'meta_description'=>'', 'categories'=>[], 'covers'=>[], 'error'=>'',
];
file_put_contents($job_file, json_encode($job));

// ── Tarayıcıya hemen job_id dön ─────────────────────────
echo json_encode(['ok'=>true, 'job_id'=>$job_id]);

// Bağlantıyı kapat — tarayıcı aldı, biz çalışmaya devam ediyoruz
if (ob_get_level()) ob_end_flush();
flush();
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

// ── Artık arka planda çalışıyoruz ─────────────────────────
function save_job($file, &$job) {
    file_put_contents($file, json_encode($job));
}

// Hedef kelime sayısı — slider değerinin ~%75'i kelimeye karşılık gelir
$target_words = (int)($target * 0.75);

// Prompt + kitap bilgisi — kelime sayısını otomatik ekle
$book_line = "\n\nIMPORTANT: Write exactly around {$target_words} words. Do not stop early, do not exceed significantly.\n\nBook: {$book}\nAuthor: {$author}";

// ── DeepSeek API ─────────────────────────────────────────
$ch = curl_init(DEEPSEEK_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 570,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . DEEPSEEK_KEY,
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model'      => DEEPSEEK_MODEL,
        'max_tokens' => $max_tokens,
        'messages'   => [
            ['role'=>'system', 'content'=>$template],
            ['role'=>'user',   'content'=>$book_line],
        ],
    ]),
]);

$raw  = curl_exec($ch);
$cerr = curl_error($ch);
curl_close($ch);

if ($cerr || !$raw) {
    $job['status'] = 'error';
    $job['error']  = 'API bağlantı hatası: ' . $cerr;
    save_job($job_file, $job); exit;
}

$data = json_decode($raw, true);
if (isset($data['error'])) {
    $job['status'] = 'error';
    $job['error']  = $data['error']['message'] ?? 'API hatası';
    save_job($job_file, $job); exit;
}

$content = $data['choices'][0]['message']['content'] ?? '';
if (!$content) {
    $job['status'] = 'error';
    $job['error']  = 'Boş içerik döndü. stop_reason: ' . ($data['stop_reason']??'?');
    save_job($job_file, $job); exit;
}

$usage = $data['usage'] ?? [];

// ── Meta + Kategori ───────────────────────────────────────
$cats = 'philosophy,philosophy_of_religion,ethics,metaphysics,epistemology,logic,aesthetics,political_philosophy,history_of_philosophy,religion,theology,systematic_theology,christian_theology,islamic_theology,christianity,islam,judaism,buddhism,hinduism,atheism,agnosticism,history,world_history,ancient_history,medieval_history,modern_history,military_history,cultural_history,biography,autobiography,memoir,literature,classic_literature,world_literature,poetry,drama,novel,fiction,historical_fiction,science_fiction,dystopian_fiction,fantasy,horror,mystery,detective_fiction,romance,adventure,psychology,cognitive_psychology,social_psychology,psychoanalysis,sociology,anthropology,politics,political_science,economics,microeconomics,macroeconomics,education,law,international_law,science,physics,astronomy,chemistry,mathematics,statistics,biology,evolution,genetics,medicine,neuroscience,public_health,technology,computers,artificial_intelligence,programming,data_science,art,art_history,music,music_history,architecture,design,photography,film,theatre,geography,travel,culture,mythology,folklore,children,young_adult,self_help,personal_development,business,management,marketing,entrepreneurship';

$snippet = mb_substr(strip_tags($content), 0, 1500);
$mp = "Return ONLY valid JSON (no extra text):\n{\"excerpt\":\"max 155 chars\",\"meta_description\":\"max 155 chars\",\"categories\":[\"slug1\",\"slug2\"]}\nChoose 2-5 slugs from: {$cats}\n\nFor book \"{$book}\" by {$author}:\n{$snippet}";

$ch2 = curl_init(DEEPSEEK_API_URL);
curl_setopt_array($ch2,[
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>30,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
    CURLOPT_POSTFIELDS=>json_encode(['model'=>DEEPSEEK_MODEL,'max_tokens'=>400,'messages'=>[['role'=>'user','content'=>$mp]]]),
]);
$raw2 = curl_exec($ch2); curl_close($ch2);
$mt   = preg_replace('/```json|```/','', json_decode($raw2,true)['choices'][0]['message']['content'] ?? '{}');
$meta = json_decode(trim($mt), true) ?? [];

// ── Kapaklar ──────────────────────────────────────────────
$covers = [];
$gb = json_decode(@file_get_contents('https://www.googleapis.com/books/v1/volumes?'.http_build_query([
    'q'=>'intitle:'.$book.'+inauthor:'.$author,'maxResults'=>5,'printType'=>'books',
    'fields'=>'items(volumeInfo(title,authors,imageLinks))'])), true);
foreach($gb['items']??[] as $item){
    $lnk=$item['volumeInfo']['imageLinks']??[];
    $c=$lnk['thumbnail']??($lnk['smallThumbnail']??'');
    if(!$c) continue;
    $c=str_replace(['http://','&edge=curl'],['https://',''],$c);
    $covers[]=['url'=>preg_replace('/zoom=\d/','zoom=3',$c),
        'title'=>$item['volumeInfo']['title']??'',
        'author'=>implode(', ',array_slice($item['volumeInfo']['authors']??[],0,2)),
        'source'=>'google'];
    if(count($covers)>=4) break;
}
if(count($covers)<2){
    $ol=json_decode(@file_get_contents('https://openlibrary.org/search.json?title='.urlencode($book).'&author='.urlencode($author).'&limit=4&fields=title,author_name,cover_i'),true);
    foreach($ol['docs']??[] as $doc){
        if(empty($doc['cover_i'])) continue;
        $covers[]=['url'=>"https://covers.openlibrary.org/b/id/{$doc['cover_i']}-L.jpg",
            'title'=>$doc['title']??'','author'=>implode(', ',array_slice($doc['author_name']??[],0,2)),'source'=>'openlibrary'];
        if(count($covers)>=4) break;
    }
}

// ── Tamamlandı ────────────────────────────────────────────
$job['status']           = 'done';
$job['content']          = $content;
$job['word_count']       = str_word_count(strip_tags($content));
$job['input_tokens']     = $usage['input_tokens']  ?? 0;
$job['output_tokens']    = $usage['output_tokens'] ?? 0;
$job['cache_read']       = $usage['cache_read_input_tokens']       ?? 0;
$job['cache_write']      = $usage['cache_creation_input_tokens']   ?? 0;
$job['stop_reason']      = $data['stop_reason'] ?? '';
$job['excerpt']          = $meta['excerpt']          ?? '';
$job['meta_description'] = $meta['meta_description'] ?? '';
$job['categories']       = $meta['categories']       ?? [];
$job['covers']           = $covers;
save_job($job_file, $job);
