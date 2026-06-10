<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }

@ini_set('max_execution_time', 660);
set_time_limit(660);
ignore_user_abort(true);
header('Content-Type: application/json');

$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_POST['batch_id'] ?? ''));
if (!$batch_id) { echo json_encode(['status'=>'error','error'=>'batch_id gerekli']); exit; }

$jobs_dir   = dirname(__DIR__) . '/jobs';
$batch_file = "$jobs_dir/{$batch_id}.json";
if (!file_exists($batch_file)) { echo json_encode(['status'=>'no_more']); exit; }

// ── Bir sonraki kitabı atomik olarak klaym et ─────────────────────
$fp = fopen($batch_file, 'r+');
if (!$fp) { echo json_encode(['status'=>'error','error'=>'Dosya açılamadı']); exit; }

if (!flock($fp, LOCK_EX | LOCK_NB)) {
    fclose($fp);
    echo json_encode(['status'=>'retry']);
    exit;
}

fseek($fp, 0);
$raw   = '';
while (!feof($fp)) $raw .= fread($fp, 65536);
$batch = json_decode($raw, true);

if (!$batch) {
    flock($fp, LOCK_UN); fclose($fp);
    echo json_encode(['status'=>'error','error'=>'Batch JSON bozuk']);
    exit;
}

if ($batch['status'] === 'cancelled') {
    flock($fp, LOCK_UN); fclose($fp);
    echo json_encode(['status'=>'cancelled']);
    exit;
}

$idx = -1;
foreach ($batch['books'] as $i => $b) {
    if ($b['status'] === 'pending') { $idx = $i; break; }
}

if ($idx === -1) {
    // Kalan pending yok — tamamlandı
    if ($batch['status'] !== 'done') {
        $batch['status'] = 'done';
        fseek($fp, 0); ftruncate($fp, 0);
        fwrite($fp, json_encode($batch, JSON_UNESCAPED_UNICODE));
    }
    flock($fp, LOCK_UN); fclose($fp);
    echo json_encode(['status'=>'no_more']);
    exit;
}

// Kitabı "processing" yap
$batch['books'][$idx]['status'] = 'processing';
fseek($fp, 0); ftruncate($fp, 0);
fwrite($fp, json_encode($batch, JSON_UNESCAPED_UNICODE));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

// ── Tarayıcıya HEMEN dön, ağır işi arka planda yap ────────────────
// (Cloudflare ~100sn bağlantı limitini aşmamak için kritik)
echo json_encode(['status'=>'started','idx'=>$idx]);
while (ob_get_level() > 0) { @ob_end_flush(); }
@flush();
if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }

// ── İşlenecek kitap bilgileri ─────────────────────────────────────
$book         = $batch['books'][$idx]['book_title'];
$author       = $batch['books'][$idx]['author_name'];
$type         = $batch['type'];
$target_words = max(500, min(8000, (int)$batch['max_tokens']));
$post_status  = $batch['post_status'];
$api_provider = $batch['api_provider'] ?? 'deepseek';
$parts        = max(1, min(4, (int)($batch['parts'] ?? 2)));

$auth   = 'Basic ' . base64_encode(WP_USER . ':' . WP_APP_PASS);
$wp_api = rtrim(WP_URL, '/') . '/wp-json/wp/v2';
$ep     = $type === 'analysis' ? 'analysis' : 'posts';

// ── Yardımcılar ───────────────────────────────────────────────────
function bw_wp($url, $method, $body, $auth, $timeout = 30) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: ' . $auth],
    ]);
    if ($method !== 'GET') curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [json_decode($r, true), $c];
}

function bw_update_book($batch_file, $idx, $updates) {
    $fp = fopen($batch_file, 'r+');
    if (!$fp) return;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return; }
    fseek($fp, 0);
    $raw = '';
    while (!feof($fp)) $raw .= fread($fp, 65536);
    $batch = json_decode($raw, true);
    if ($batch) {
        foreach ($updates as $k => $v) $batch['books'][$idx][$k] = $v;
        // Sayaçları yeniden hesapla
        $done = $ok = $failed = 0;
        foreach ($batch['books'] as $b) {
            if (in_array($b['status'], ['done','error','duplicate'])) $done++;
            if ($b['status'] === 'done')      $ok++;
            if ($b['status'] === 'error')     $failed++;
        }
        $batch['done']   = $done;
        $batch['ok']     = $ok;
        $batch['failed'] = $failed;
        if ($done >= $batch['total']) $batch['status'] = 'done';
        fseek($fp, 0); ftruncate($fp, 0);
        fwrite($fp, json_encode($batch, JSON_UNESCAPED_UNICODE));
        fflush($fp);
    }
    flock($fp, LOCK_UN); fclose($fp);
}

function bw_clean_content($text) {
    $text = preg_replace('/%%PART[12]_(?:END|START)%%/i', '', $text);
    $text = preg_replace('/\[Note:[^\]]*\]/i', '', $text);
    $text = preg_replace('/\[Already[^\]]*\]/i', '', $text);
    $text = preg_replace('/\[This was[^\]]*\]/i', '', $text);
    $text = preg_replace('/\[.*?already.*?\]/is', '', $text);
    $text = preg_replace('/\[.*?covered.*?\]/is', '', $text);
    $text = preg_replace('/\[.*?Part 1.*?\]/is', '', $text);
    $text = preg_replace('/\[.*?structure.*?\]/is', '', $text);
    $text = preg_replace('/\n{4,}/', "\n\n\n", $text);
    return trim($text);
}

function bw_md2html($text) {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace_callback('/\*\*(.+?)\*\*/s', function($m) {
        return strpos($m[1], "\n") === false ? '<strong>'.$m[1].'</strong>' : $m[1];
    }, $text);
    $text = preg_replace(
        ['/^#{1} \*\*(.+?)\*\*/m','/^#{2} \*\*(.+?)\*\*/m','/^#{3} \*\*(.+?)\*\*/m',
         '/^# (.+)/m','/^## (.+)/m','/^### (.+)/m'],
        ['<h1><strong>$1</strong></h1>','<h2><strong>$1</strong></h2>','<h3><strong>$1</strong></h3>',
         '<h1>$1</h1>','<h2>$1</h2>','<h3>$1</h3>'],
        $text
    );
    $lines = explode("\n", $text);
    $html = ''; $buf = []; $bqbuf = [];
    $fl  = function() use (&$buf,  &$html) { if ($buf)   { $html .= '<p>'  . implode(' ', $buf)   . "</p>\n"; $buf   = []; } };
    $fbq = function() use (&$bqbuf,&$html) { if ($bqbuf) { $html .= '<blockquote>' . implode(' ', $bqbuf) . "</blockquote>\n"; $bqbuf = []; } };
    foreach ($lines as $l) {
        $l = trim($l);
        if (!$l) { $fl(); $fbq(); continue; }
        if (preg_match('/^(?:&gt;|>)\s*(.*)$/', $l, $m)) { $fl(); $bqbuf[] = $m[1]; continue; }
        if (preg_match('/^<(h[1-6]|hr)/', $l)) { $fl(); $fbq(); $html .= $l . "\n"; continue; }
        $fbq(); $buf[] = $l;
    }
    $fl(); $fbq();
    return $html;
}

// ── Dedup kontrolü ────────────────────────────────────────────────
[$existing] = bw_wp("$wp_api/$ep?search=" . urlencode($book) . '&per_page=5', 'GET', [], $auth);
if (!empty($existing) && is_array($existing)) {
    foreach ($existing as $p) {
        $title = html_entity_decode(strip_tags($p['title']['rendered'] ?? ''));
        if (stripos($title, $book) !== false && (!$author || stripos($title, $author) !== false)) {
            bw_update_book($batch_file, $idx, [
                'status'   => 'duplicate',
                'post_id'  => $p['id'],
                'edit_url' => rtrim(WP_URL,'/') . '/wp-admin/post.php?post=' . $p['id'] . '&action=edit',
                'error'    => 'Zaten mevcut',
            ]);
            echo json_encode(['status'=>'done','idx'=>$idx,'ok'=>false,'error'=>'duplicate','post_id'=>$p['id']]);
            exit;
        }
    }
}

// ── Prompt ────────────────────────────────────────────────────────
$prompts  = file_exists(PROMPTS_FILE) ? json_decode(file_get_contents(PROMPTS_FILE), true) : [];
$template = trim($prompts[$type] ?? '');
if (!$template) {
    bw_update_book($batch_file, $idx, ['status'=>'error','error'=>'Prompt boş']);
    echo json_encode(['status'=>'done','idx'=>$idx,'ok'=>false,'error'=>'Prompt boş']);
    exit;
}

// ── Çok parçalı talimat yardımcıları ─────────────────────────────
function bw_fraction($n) {
    switch ($n) { case 2: return 'half'; case 3: return 'third'; case 4: return 'quarter'; default: return "1/{$n}"; }
}
function bw_part_instruction($k, $n, $headings, $tail, $part_words) {
    if ($n <= 1) {
        return "\nTarget length: approximately {$part_words} words.";
    }
    $frac = bw_fraction($n);
    $covered = '';
    foreach ($headings as $h) $covered .= "   ✗ {$h}\n";

    if ($k === 1) {
        return "\n\n=== MULTI-PART GENERATION (PART 1 of {$n}) ===\n"
             . "You are writing PART 1 of {$n} of a single continuous piece.\n"
             . "• Begin with the H1 (# **Title — Author**) then the H2 (## **Subtitle**), then the first ### sections in order.\n"
             . "• Cover approximately the first {$frac} of the complete work (~{$part_words} words for this part).\n"
             . "• Develop every section fully per the format rules. Do NOT write any conclusion — more parts follow.\n"
             . "• End naturally at a ### section boundary. Your ABSOLUTE FINAL LINE must be exactly:\n%%PART_END%%";
    }
    if ($k < $n) {
        return "\n\n=== MULTI-PART GENERATION (PART {$k} of {$n}) ===\n"
             . "You are writing PART {$k} of {$n} — a direct, seamless continuation of the text already written.\n"
             . "STRICT RULES:\n"
             . "1. DO NOT rewrite the H1 or H2 heading.\n"
             . "2. The following sections are FULLY COMPLETE — do NOT revisit, repeat, summarize, or expand them:\n{$covered}"
             . "3. Continue with the NEXT new ### sections not listed above. Cover roughly the next {$frac} of the work (~{$part_words} words).\n"
             . "4. Do NOT write a conclusion — there are still more parts after this one.\n"
             . "5. Maintain the exact same voice, depth, and format as before.\n"
             . "6. End at a ### section boundary. Your ABSOLUTE FINAL LINE must be exactly:\n%%PART_END%%\n"
             . "\nThe text so far ended here (continue seamlessly from this exact point — do NOT repeat it):\n...{$tail}";
    }
    return "\n\n=== MULTI-PART GENERATION (FINAL PART {$n} of {$n}) ===\n"
         . "You are writing the FINAL PART ({$n} of {$n}) — a direct, seamless continuation.\n"
         . "STRICT RULES:\n"
         . "1. DO NOT rewrite the H1 or H2 heading.\n"
         . "2. The following sections are FULLY COMPLETE — do NOT revisit, repeat, or expand them:\n{$covered}"
         . "3. Continue with ALL remaining ### sections and COMPLETE the work fully.\n"
         . "4. Maintain the exact same voice, depth, and format as before.\n"
         . "5. Apply the closing rule: end with the final substantive point — no summary paragraph, no closing sentence.\n"
         . "\nThe text so far ended here (continue seamlessly from this exact point — do NOT repeat it):\n...{$tail}";
}

// ── İçerik üretimi ───────────────────────────────────────────────
$content   = '';
$gen_error = '';

if ($api_provider === 'deepseek') {

    // N parçalı üretim — her parça öncekinin bağlamıyla devam eder
    $accumulated = '';
    $part_words  = (int)ceil($target_words / max(1, $parts));

    for ($k = 1; $k <= $parts; $k++) {
        $headings = [];
        if ($accumulated !== '') {
            preg_match_all('/^### (.+)$/m', $accumulated, $mh);
            $headings = $mh[1] ?? [];
        }
        $tail = $accumulated !== '' ? mb_substr($accumulated, -700) : '';

        $pr = $template
            . "\n\nBook: {$book}\nAuthor: {$author}"
            . bw_part_instruction($k, $parts, $headings, $tail, $part_words);

        $ch = curl_init(DEEPSEEK_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 280,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
            CURLOPT_POSTFIELDS => json_encode(['model'=>DEEPSEEK_MODEL,'max_tokens'=>16000,'messages'=>[['role'=>'user','content'=>$pr]]]),
        ]);
        $raw  = curl_exec($ch); $cerr = curl_error($ch); curl_close($ch);

        if ($cerr || !$raw) {
            if ($k === 1) $gen_error = "DeepSeek Part {$k} bağlantı hatası: {$cerr}";
            break; // sonraki parçalar denenmez; eldeki içerikle devam edilir
        }
        $dd = json_decode($raw, true);
        if (isset($dd['error'])) {
            if ($k === 1) $gen_error = "DeepSeek Part {$k}: " . ($dd['error']['message'] ?? 'API hatası');
            break;
        }
        $piece = trim($dd['choices'][0]['message']['content'] ?? '');
        $piece = str_replace('%%PART_END%%', '', $piece);
        if ($piece === '') break;

        if ($k > 1) {
            // Sonraki parçalarda kazara yazılan H1/H2 başlıklarını temizle
            $piece = preg_replace('/^# [^\n]+\n+/m',  '', $piece, 1);
            $piece = preg_replace('/^## [^\n]+\n+/m', '', $piece, 1);
            $piece = ltrim($piece);
        }
        $accumulated = $accumulated === '' ? $piece : ($accumulated . "\n\n" . $piece);
    }

    if ($accumulated !== '') {
        $content = bw_clean_content($accumulated);
    }

} else {
    // Anthropic — tek parça
    $prompt = $template
        . "\n\nBook: {$book}\nAuthor: {$author}\nTarget length: approximately {$target_words} words.";
    $model = $type === 'analysis' ? ANTHROPIC_MODEL : 'claude-haiku-4-5-20251001';

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 580,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json','x-api-key: '.ANTHROPIC_KEY,'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS => json_encode(['model'=>$model,'max_tokens'=>min(16000,(int)($target_words*2.5)),'messages'=>[['role'=>'user','content'=>$prompt]]]),
    ]);
    $raw = curl_exec($ch); $err = curl_error($ch); curl_close($ch);

    if ($err || !$raw) {
        $gen_error = 'Anthropic bağlantı hatası: ' . $err;
    } else {
        $d = json_decode($raw, true);
        if (isset($d['error'])) {
            $gen_error = 'Anthropic: ' . ($d['error']['message'] ?? 'API hatası');
        } else {
            $content = bw_clean_content($d['content'][0]['text'] ?? '');
            if (!$content) $gen_error = 'Boş içerik döndü';
        }
    }
}

if ($gen_error || !$content) {
    bw_update_book($batch_file, $idx, ['status'=>'error','error'=>$gen_error ?: 'Boş içerik']);
    echo json_encode(['status'=>'done','idx'=>$idx,'ok'=>false,'error'=>$gen_error]);
    exit;
}

// ── Meta (excerpt, meta_desc, kategoriler) ────────────────────────
$cats_list = 'philosophy,philosophy_of_religion,ethics,metaphysics,epistemology,logic,aesthetics,political_philosophy,history_of_philosophy,religion,theology,systematic_theology,christian_theology,islamic_theology,christianity,islam,judaism,buddhism,hinduism,atheism,agnosticism,history,world_history,ancient_history,medieval_history,modern_history,military_history,cultural_history,biography,autobiography,memoir,literature,classic_literature,world_literature,poetry,drama,novel,fiction,historical_fiction,science_fiction,dystopian_fiction,fantasy,horror,mystery,detective_fiction,romance,adventure,psychology,cognitive_psychology,social_psychology,psychoanalysis,sociology,anthropology,politics,political_science,economics,microeconomics,macroeconomics,education,law,international_law,science,physics,astronomy,chemistry,mathematics,statistics,biology,evolution,genetics,medicine,neuroscience,public_health,technology,computers,artificial_intelligence,programming,data_science,art,art_history,music,music_history,architecture,design,photography,film,theatre,geography,travel,culture,mythology,folklore,children,young_adult,self_help,personal_development,business,management,marketing,entrepreneurship';

$snippet  = mb_substr(strip_tags($content), 0, 1500);
$mp       = "Return ONLY valid JSON (no extra text, no markdown fences):\n"
           . "{\"excerpt\":\"max 155 chars\",\"meta_description\":\"max 155 chars\",\"categories\":[\"slug1\",\"slug2\"],\"quotes\":[{\"text\":\"verbatim quote\",\"source\":\"section name\"}]}\n"
           . "Pick 2-5 category slugs from: {$cats_list}\n"
           . "For quotes: only truly verbatim passages; 0-2 quotes max.\n"
           . "Book: \"{$book}\" by {$author}\n\n{$snippet}";

$meta_provider = $api_provider === 'deepseek' ? 'deepseek' : 'anthropic';
$meta_raw = '';

if ($meta_provider === 'deepseek') {
    $mch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($mch, [
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>30,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>DEEPSEEK_MODEL,'max_tokens'=>500,'messages'=>[['role'=>'user','content'=>$mp]]]),
    ]);
    $meta_raw = curl_exec($mch); curl_close($mch);
    $meta_text = json_decode($meta_raw,true)['choices'][0]['message']['content'] ?? '{}';
} else {
    $mch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($mch, [
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>30,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.ANTHROPIC_KEY,'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>'claude-haiku-4-5-20251001','max_tokens'=>500,'messages'=>[['role'=>'user','content'=>$mp]]]),
    ]);
    $meta_raw = curl_exec($mch); curl_close($mch);
    $meta_text = json_decode($meta_raw,true)['content'][0]['text'] ?? '{}';
}

$meta_text = preg_replace('/```json|```/', '', $meta_text);
$meta = json_decode(trim($meta_text), true) ?? [];

// ── Kapak bul ─────────────────────────────────────────────────────
$cover_url = '';

// cURL ile dış GET (allow_url_fopen kapalı olabilir)
$bw_http_get = function($url, $binary = false) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['User-Agent: Mozilla/5.0 (ThetelosBot)'],
    ]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($c >= 200 && $c < 300) ? $r : null;
};

// Google Books — intitle + inauthor (daha spesifik)
$gb_url = 'https://www.googleapis.com/books/v1/volumes?' . http_build_query([
    'q'          => 'intitle:"' . $book . '" inauthor:"' . $author . '"',
    'maxResults' => 5,
    'printType'  => 'books',
    'fields'     => 'items(volumeInfo(title,authors,imageLinks,industryIdentifiers))',
]);
$gb = json_decode((string)$bw_http_get($gb_url), true);
foreach ($gb['items'] ?? [] as $item) {
    $lnk = $item['volumeInfo']['imageLinks'] ?? [];
    $c   = $lnk['thumbnail'] ?? ($lnk['smallThumbnail'] ?? '');
    if (!$c) continue;
    // Yazar kontrolü
    $authors = array_map('strtolower', $item['volumeInfo']['authors'] ?? []);
    $author_match = false;
    $author_words = array_filter(explode(' ', strtolower($author)), fn($w) => strlen($w) > 2);
    foreach ($authors as $a) {
        foreach ($author_words as $w) { if (strpos($a, $w) !== false) { $author_match = true; break 2; } }
    }
    if (!$author_match && !empty($authors)) continue; // yazar eşleşmedi
    $c = str_replace(['http://','&edge=curl'], ['https://',''], $c);
    $cover_url = preg_replace('/zoom=\d/', 'zoom=3', $c);
    break;
}

// Fallback: geniş Google Books sorgusu
if (!$cover_url) {
    $gb2_url = 'https://www.googleapis.com/books/v1/volumes?' . http_build_query([
        'q'          => $book . ' ' . $author,
        'maxResults' => 3,
        'printType'  => 'books',
        'fields'     => 'items(volumeInfo(imageLinks))',
    ]);
    $gb2 = json_decode((string)$bw_http_get($gb2_url), true);
    foreach ($gb2['items'] ?? [] as $item) {
        $lnk = $item['volumeInfo']['imageLinks'] ?? [];
        $c   = $lnk['thumbnail'] ?? ($lnk['smallThumbnail'] ?? '');
        if (!$c) continue;
        $c = str_replace(['http://','&edge=curl'], ['https://',''], $c);
        $cover_url = preg_replace('/zoom=\d/', 'zoom=3', $c);
        break;
    }
}

// Fallback: OpenLibrary
if (!$cover_url) {
    $ol = json_decode((string)$bw_http_get(
        'https://openlibrary.org/search.json?title=' . urlencode($book)
        . '&author=' . urlencode($author) . '&limit=4&fields=cover_i,title,author_name'
    ), true);
    foreach ($ol['docs'] ?? [] as $doc) {
        if (empty($doc['cover_i'])) continue;
        $cover_url = "https://covers.openlibrary.org/b/id/{$doc['cover_i']}-L.jpg";
        break;
    }
}

// ── WordPress'e yayınla ───────────────────────────────────────────
$cat_ids = [];
foreach ($meta['categories'] ?? [] as $raw_slug) {
    $slug = strtolower(trim(preg_replace('/[\s_]+/', '-', $raw_slug)));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    if (!$slug) continue;
    [$cats] = bw_wp("$wp_api/categories?slug=" . urlencode($slug) . '&per_page=1', 'GET', [], $auth);
    if (!empty($cats[0]['id'])) { $cat_ids[] = $cats[0]['id']; continue; }
    $slug_u = str_replace('-', '_', $slug);
    [$cats2] = bw_wp("$wp_api/categories?slug=" . urlencode($slug_u) . '&per_page=1', 'GET', [], $auth);
    if (!empty($cats2[0]['id'])) { $cat_ids[] = $cats2[0]['id']; continue; }
    $name = ucwords(str_replace('-', ' ', $slug));
    [$nc] = bw_wp("$wp_api/categories", 'POST', ['name'=>$name,'slug'=>$slug], $auth);
    if (!empty($nc['id'])) $cat_ids[] = $nc['id'];
}

// İçeriği temizle
$clean = $content;
$clean = preg_replace('/^# \*\*[^\n]+\*\*\n*/m', '', $clean, 1);
$clean = preg_replace('/^# [^\n]+\n*/m', '', $clean, 1);
$clean = ltrim($clean);

$post_title = $author ? "$book - $author" : $book;
$pb = [
    'title'   => $post_title,
    'content' => bw_md2html($clean),
    'excerpt' => $meta['excerpt'] ?? '',
    'status'  => $post_status,
];
if ($ep !== 'analysis' && $cat_ids) $pb['categories'] = $cat_ids;

[$post, $pc] = bw_wp("$wp_api/$ep", 'POST', $pb, $auth, 60);
if ($pc < 200 || $pc >= 300) {
    $err = $post['message'] ?? "WP HTTP $pc";
    bw_update_book($batch_file, $idx, ['status'=>'error','error'=>$err]);
    echo json_encode(['status'=>'done','idx'=>$idx,'ok'=>false,'error'=>$err]);
    exit;
}
$pid = $post['id'];

// Yazar
if ($author) {
    [$terms] = bw_wp("$wp_api/authors?search=" . urlencode($author) . '&per_page=10', 'GET', [], $auth);
    $tid = null; $existing_desc = '';
    foreach ($terms ?? [] as $t) {
        if (strtolower($t['name']) === strtolower($author)) {
            $tid = $t['id']; $existing_desc = $t['description'] ?? ''; break;
        }
    }
    if (!$tid || !$existing_desc) {
        $bio_prompt = "Write a concise 2-3 sentence biography of \"{$author}\" for a philosophy/literature website. Focus on main works and intellectual contributions. English, encyclopedic.";
        if ($api_provider === 'deepseek') {
            $bch = curl_init(DEEPSEEK_API_URL);
            curl_setopt_array($bch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>20,
                CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
                CURLOPT_POSTFIELDS=>json_encode(['model'=>DEEPSEEK_MODEL,'max_tokens'=>200,'messages'=>[['role'=>'user','content'=>$bio_prompt]]]),
            ]);
            $bio_raw = curl_exec($bch); curl_close($bch);
            $bio = json_decode($bio_raw,true)['choices'][0]['message']['content'] ?? '';
        } else {
            $bch = curl_init('https://api.anthropic.com/v1/messages');
            curl_setopt_array($bch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>20,
                CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.ANTHROPIC_KEY,'anthropic-version: 2023-06-01'],
                CURLOPT_POSTFIELDS=>json_encode(['model'=>'claude-haiku-4-5-20251001','max_tokens'=>200,'messages'=>[['role'=>'user','content'=>$bio_prompt]]]),
            ]);
            $bio_raw = curl_exec($bch); curl_close($bch);
            $bio = json_decode($bio_raw,true)['content'][0]['text'] ?? '';
        }
        if (!$tid) {
            [$nt] = bw_wp("$wp_api/authors", 'POST', ['name'=>$author,'description'=>$bio], $auth);
            $tid = $nt['id'] ?? null;
        } elseif ($bio) {
            bw_wp("$wp_api/authors/$tid", 'POST', ['description'=>$bio], $auth);
        }
    }
    if ($tid) bw_wp("$wp_api/$ep/$pid", 'POST', ['authors'=>[$tid]], $auth);
}

// Yoast meta
if (!empty($meta['meta_description'])) {
    bw_wp("$wp_api/$ep/$pid", 'POST', ['meta'=>['_yoast_wpseo_metadesc'=>$meta['meta_description']]], $auth);
}

// Alıntılar
bw_wp("$wp_api/$ep/$pid", 'POST', ['meta'=>['_tls_disable_quotes'=>'1']], $auth);
$clean_quotes = [];
foreach ($meta['quotes'] ?? [] as $q) {
    $t = trim($q['text'] ?? ''); $s = trim($q['source'] ?? '');
    if ($t) $clean_quotes[] = ['text'=>$t,'source'=>$s];
}
if ($clean_quotes) bw_wp("$wp_api/$ep/$pid", 'POST', ['meta'=>['_tls_quotes'=>$clean_quotes]], $auth);

// Kitap kapağı
$cover_set = false;
if ($cover_url) {
    $allowed = ['books.google.com','covers.openlibrary.org',
                'lh3.googleusercontent.com','lh4.googleusercontent.com','lh5.googleusercontent.com',
                'lh6.googleusercontent.com'];
    $host = parse_url($cover_url, PHP_URL_HOST);
    if (in_array($host, $allowed)) {
        $img = $bw_http_get($cover_url, true);
        if ($img && strlen($img) > 2000) {
            $fn = preg_replace('/[^a-z0-9]/', '-', strtolower($book)) . '.jpg';
            $cm = curl_init("$wp_api/media");
            curl_setopt_array($cm, [
                CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>30,
                CURLOPT_HTTPHEADER=>['Authorization: '.$auth,"Content-Disposition: attachment; filename=\"$fn\"",'Content-Type: image/jpeg'],
                CURLOPT_POSTFIELDS=>$img,
            ]);
            $mr  = curl_exec($cm); curl_close($cm);
            $mid = json_decode($mr, true)['id'] ?? null;
            if ($mid) {
                bw_wp("$wp_api/$ep/$pid", 'POST', ['featured_media'=>$mid], $auth);
                $cover_set = true;
            }
        }
    }
}

// ── Batch güncelle ────────────────────────────────────────────────
bw_update_book($batch_file, $idx, [
    'status'    => 'done',
    'post_id'   => $pid,
    'post_url'  => $post['link'] ?? '',
    'edit_url'  => rtrim(WP_URL,'/') . '/wp-admin/post.php?post=' . $pid . '&action=edit',
    'cover_set' => $cover_set,
]);

echo json_encode(['status'=>'done','idx'=>$idx,'ok'=>true,'post_id'=>$pid,'cover_set'=>$cover_set]);
