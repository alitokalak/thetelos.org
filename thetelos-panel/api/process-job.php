<?php
ignore_user_abort(true);
@ini_set('max_execution_time', 600);
set_time_limit(600);

require_once dirname(__DIR__) . '/config.php';

$job_id = trim($_POST['job_id'] ?? '');
$secret = trim($_POST['secret'] ?? '');
$expected = defined('BRIDGE_SECRET') ? BRIDGE_SECRET : 'tls-internal-secret';
if (!$job_id || $secret !== $expected) { http_response_code(403); exit; }

$job_file = dirname(__DIR__) . '/jobs/' . preg_replace('/[^a-z0-9_.]/', '', $job_id) . '.json';
if (!file_exists($job_file)) exit;

$job = json_decode(file_get_contents($job_file), true);
if (!$job || $job['status'] !== 'pending') exit;

$job['status'] = 'processing';
file_put_contents($job_file, json_encode($job));

if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

$book      = $job['book_title'];
$author    = $job['author_name'];
$type      = $job['type'];
$max_tok   = (int)($job['max_tokens'] ?? 4000);

$prompts  = file_exists(PROMPTS_FILE) ? json_decode(file_get_contents(PROMPTS_FILE), true) : [];
$template = trim($prompts[$type] ?? '');
if (!$template) {
    $job['status']='error'; $job['error']='Prompt boş.';
    file_put_contents($job_file, json_encode($job)); exit;
}

$book_line = "\n\nBook: {$book}\nAuthor: {$author}";
$prompt    = $template . $book_line;

// ── DeepSeek API ─────────────────────────────────────────
$messages_payload = [
    'model'      => (in_array(DEEPSEEK_MODEL,['deepseek-chat','deepseek-reasoner'],true)?'deepseek-v4-pro':DEEPSEEK_MODEL),
    'max_tokens' => $max_tok,
    'messages'   => [
        ['role'=>'system', 'content'=>$template],
        ['role'=>'user',   'content'=>"Book: {$book}\nAuthor: {$author}"],
    ],
];

$ch = curl_init(DEEPSEEK_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 580,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . DEEPSEEK_KEY,
    ],
    CURLOPT_POSTFIELDS => json_encode($messages_payload),
]);
$raw = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err || !$raw) {
    $job['status']='error'; $job['error']='API hatası: '.$err;
    file_put_contents($job_file, json_encode($job)); exit;
}

$data = json_decode($raw, true);
if (isset($data['error'])) {
    $job['status']='error'; $job['error']=$data['error']['message']??'API hatası';
    file_put_contents($job_file, json_encode($job)); exit;
}

$content = $data['choices'][0]['message']['content'] ?? '';
if (!$content) {
    $job['status']='error'; $job['error']='Boş içerik döndü.';
    file_put_contents($job_file, json_encode($job)); exit;
}

$usage = $data['usage'] ?? [];

// ── Meta + Kategori ───────────────────────────────────────
$cats_list = 'philosophy,philosophy_of_religion,ethics,metaphysics,epistemology,logic,aesthetics,political_philosophy,history_of_philosophy,religion,theology,systematic_theology,christian_theology,islamic_theology,christianity,islam,judaism,buddhism,hinduism,atheism,agnosticism,history,world_history,ancient_history,medieval_history,modern_history,military_history,cultural_history,biography,autobiography,memoir,literature,classic_literature,world_literature,poetry,drama,novel,fiction,historical_fiction,science_fiction,dystopian_fiction,fantasy,horror,mystery,detective_fiction,romance,adventure,psychology,cognitive_psychology,social_psychology,psychoanalysis,sociology,anthropology,politics,political_science,economics,microeconomics,macroeconomics,education,law,international_law,science,physics,astronomy,chemistry,mathematics,statistics,biology,evolution,genetics,medicine,neuroscience,public_health,technology,computers,artificial_intelligence,programming,data_science,art,art_history,music,music_history,architecture,design,photography,film,theatre,geography,travel,culture,mythology,folklore,children,young_adult,self_help,personal_development,business,management,marketing,entrepreneurship';

$snippet = mb_substr(strip_tags($content), 0, 1500);
$mp = "Return ONLY valid JSON for \"{$book}\" by {$author}:\n{\"excerpt\":\"max 155 chars\",\"meta_description\":\"max 155 chars\",\"categories\":[\"slug\"]}\nPick 2-5 from: {$cats_list}\n\n{$snippet}";

$ch2 = curl_init(DEEPSEEK_API_URL);
curl_setopt_array($ch2,[
    CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>30,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
    CURLOPT_POSTFIELDS=>json_encode(['model'=>(in_array(DEEPSEEK_MODEL,['deepseek-chat','deepseek-reasoner'],true)?'deepseek-v4-pro':DEEPSEEK_MODEL),'max_tokens'=>400,'messages'=>[['role'=>'user','content'=>$mp]]]),
]);
$raw2=curl_exec($ch2); curl_close($ch2);
$mt=preg_replace('/```json|```/','',json_decode($raw2,true)['choices'][0]['message']['content']??'{}');
$meta=json_decode(trim($mt),true)??[];

// ── Kapaklar ──────────────────────────────────────────────
$covers=[];
$gb=json_decode(@file_get_contents('https://www.googleapis.com/books/v1/volumes?'.http_build_query(['q'=>'intitle:'.$book.'+inauthor:'.$author,'maxResults'=>5,'printType'=>'books','fields'=>'items(volumeInfo(title,authors,imageLinks))'])),true);
foreach($gb['items']??[] as $item){
    $lnk=$item['volumeInfo']['imageLinks']??[];
    $c=$lnk['thumbnail']??($lnk['smallThumbnail']??'');
    if(!$c)continue;
    $c=str_replace(['http://','&edge=curl'],['https://',''],$c);
    $covers[]=['url'=>preg_replace('/zoom=\d/','zoom=3',$c),'title'=>$item['volumeInfo']['title']??'','author'=>implode(', ',array_slice($item['volumeInfo']['authors']??[],0,2)),'source'=>'google'];
    if(count($covers)>=4)break;
}
if(count($covers)<2){
    $ol=json_decode(@file_get_contents('https://openlibrary.org/search.json?title='.urlencode($book).'&author='.urlencode($author).'&limit=4&fields=title,author_name,cover_i'),true);
    foreach($ol['docs']??[] as $doc){
        if(empty($doc['cover_i']))continue;
        $covers[]=['url'=>"https://covers.openlibrary.org/b/id/{$doc['cover_i']}-L.jpg",'title'=>$doc['title']??'','author'=>implode(', ',array_slice($doc['author_name']??[],0,2)),'source'=>'openlibrary'];
        if(count($covers)>=4)break;
    }
}

// ── Job tamamlandı ────────────────────────────────────────
$job['status']          = 'done';
$job['content']         = $content;
$job['word_count']      = str_word_count(strip_tags($content));
$job['input_tokens']    = $usage['input_tokens']         ?? 0;
$job['output_tokens']   = $usage['output_tokens']        ?? 0;
$job['cache_read']      = $usage['cache_read_input_tokens']  ?? 0;
$job['cache_write']     = $usage['cache_creation_input_tokens'] ?? 0;
$job['stop_reason']     = $data['stop_reason']           ?? '';
$job['excerpt']         = $meta['excerpt']               ?? '';
$job['meta_description']= $meta['meta_description']      ?? '';
$job['categories']      = $meta['categories']            ?? [];
$job['covers']          = $covers;
file_put_contents($job_file, json_encode($job));
