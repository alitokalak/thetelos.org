<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$book         = trim($_POST['book_title']   ?? '');
$author       = trim($_POST['author_name'] ?? '');
$type         = trim($_POST['type']        ?? 'summary');
$content      = trim($_POST['content']     ?? '');
$api_provider = trim($_POST['api_provider'] ?? 'anthropic');
$api_model    = trim($_POST['api_model']    ?? '');

if (!$book || !$content) { echo json_encode(['ok'=>false,'error'=>'Eksik veri.']); exit; }

$cats = 'philosophy,philosophy_of_religion,ethics,metaphysics,epistemology,logic,aesthetics,political_philosophy,history_of_philosophy,religion,theology,systematic_theology,christian_theology,islamic_theology,christianity,islam,judaism,buddhism,hinduism,atheism,agnosticism,history,world_history,ancient_history,medieval_history,modern_history,military_history,cultural_history,biography,autobiography,memoir,literature,classic_literature,world_literature,poetry,drama,novel,fiction,historical_fiction,science_fiction,dystopian_fiction,fantasy,horror,mystery,detective_fiction,romance,adventure,psychology,cognitive_psychology,social_psychology,psychoanalysis,sociology,anthropology,politics,political_science,economics,microeconomics,macroeconomics,education,law,international_law,science,physics,astronomy,chemistry,mathematics,statistics,biology,evolution,genetics,medicine,neuroscience,public_health,technology,computers,artificial_intelligence,programming,data_science,art,art_history,music,music_history,architecture,design,photography,film,theatre,geography,travel,culture,mythology,folklore,children,young_adult,self_help,personal_development,business,management,marketing,entrepreneurship';

$snippet = mb_substr(strip_tags($content), 0, 1500);
$type_label = $type === 'analysis' ? 'analysis' : 'summary';

$system_prompt = 'You are a JSON generator. You MUST respond with ONLY a valid JSON object. No explanations, no markdown, no backticks, no extra text. Just the raw JSON object starting with { and ending with }.';

$mp = "Generate SEO metadata for \"{$book}\" by {$author}.\n\n"
    . "Respond with ONLY this JSON structure:\n"
    . "{\n"
    . "  \"excerpt\": \"1-2 complete sentences describing the work. Must end with a period. Maximum 120 characters.\",\n"
    . "  \"meta_description\": \"SEO description. Must end with a period. Maximum 120 characters. Different from excerpt.\"\n"
    . "}\n\n"
    . "STRICT RULES:\n"
    . "- excerpt: complete sentence(s) ending with a period. Max 120 chars.\n"
    . "- meta_description: complete sentence ending with a period. Max 120 chars.\n"
    . "- Both must be different from each other.\n"
    . "- categories: pick 2-5 slugs ONLY from: {$cats}\n"
    . "- quotes: leave as empty array []\n"
    . "- Output ONLY the JSON object\n\n"
    . "CONTENT (for context):\n" . $snippet;

if ($api_provider === 'deepseek') {
    $ch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>30,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS=>json_encode([
            'model'      => DEEPSEEK_MODEL,
            'max_tokens' => 700,
            'messages'   => [
                ['role'=>'system','content'=>$system_prompt],
                ['role'=>'user',  'content'=>$mp],
            ],
        ]),
    ]);
    $raw = curl_exec($ch); curl_close($ch);
    $raw_text = json_decode($raw,true)['choices'][0]['message']['content'] ?? '{}';
} else {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>30,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.ANTHROPIC_KEY,'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS=>json_encode([
            'model'      => $api_model ?: 'claude-haiku-4-5-20251001',
            'max_tokens' => 700,
            'system'     => $system_prompt,
            'messages'   => [['role'=>'user','content'=>$mp]],
        ]),
    ]);
    $raw = curl_exec($ch); curl_close($ch);
    $raw_text = json_decode($raw,true)['content'][0]['text'] ?? '{}';
}

// JSON extraction — backtick blokları, açıklamalar, vs. temizle
$mt = $raw_text;
// ```json ... ``` veya ``` ... ``` bloklarını çıkar
if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $mt, $m)) {
    $mt = $m[1];
} else {
    // Sadece { } arasındaki kısmı al
    if (preg_match('/\{.*\}/s', $mt, $m)) {
        $mt = $m[1] ?? $m[0];
    }
}
$mt = trim($mt);
$meta = json_decode($mt, true) ?? [];

// Karakter limiti uygula — tam cümle sınırında kes
function trim_to_sentence($text, $max = 150) {
    $text = trim($text);
    if (!$text) return '';
    // Nokta ile bitmiyorsa nokta ekle — model tam cümle yazdıysa bu yeterli
    if (mb_strlen($text) <= $max) {
        if (!preg_match('/[.!?]$/', $text)) $text .= '.';
        return $text;
    }
    // Çok uzunsa: son tam cümlede kes (son nokta/ünlem/soru işaretine kadar)
    $cut  = mb_substr($text, 0, $max);
    $last = max(
        mb_strrpos($cut, '. ') ?: 0,
        mb_strrpos($cut, '! ') ?: 0,
        mb_strrpos($cut, '? ') ?: 0,
        mb_strrpos($cut, '.') ?: 0,
        mb_strrpos($cut, '!') ?: 0,
        mb_strrpos($cut, '?') ?: 0
    );
    if ($last > 20) return mb_substr($cut, 0, $last + 1);
    // Son cümle bulunamazsa son kelimede kes ve nokta ekle
    $ls = mb_strrpos($cut, ' ');
    return ($ls > 20 ? mb_substr($cut, 0, $ls) : $cut) . '.';
}

if (!empty($meta['excerpt']))
    $meta['excerpt'] = trim_to_sentence($meta['excerpt'], 150);
if (!empty($meta['meta_description']))
    $meta['meta_description'] = trim_to_sentence($meta['meta_description'], 150);
$bad_phrases = [
    'please provide',
    'i need actual',
    "i don't have",
    'i cannot',
    'i am unable',
    "i'm unable",
    'auto draft',
    'no content',
    'not enough information',
    'no book',
    'no author',
];
foreach (['excerpt', 'meta_description'] as $field) {
    $val = strtolower($meta[$field] ?? '');
    foreach ($bad_phrases as $phrase) {
        if (strpos($val, $phrase) !== false) {
            $meta[$field] = '';
            break;
        }
    }
}

// ── Kapak arama ───────────────────────────────────────────
// Kitap adını temizle: parantez içleri, tire, özel karakter, Latin dışı harfleri at
function clean_title($raw) {
    // Parantez ve içini sil: (القانون في الطب – Al-Qanun fi al-Tibb) gibi
    $t = preg_replace('/\(.*?\)/u', '', $raw);
    // Köşeli parantez içini sil
    $t = preg_replace('/\[.*?\]/u', '', $t);
    // Tire ile ayrılmış yazar kısmını at: "Kitap Adı -Yazar Adı-" → "Kitap Adı"
    $t = preg_replace('/-[^-]+-\s*$/', '', $t);
    // Latin dışı karakterleri at (Arapça, Çince vb.)
    $t = preg_replace('/[^\x20-\x7E]/u', ' ', $t);
    // Noktalama temizle
    $t = preg_replace('/[–—:;,\.!?\/\\\\]+/', ' ', $t);
    // Fazla boşluk
    $t = trim(preg_replace('/\s+/', ' ', $t));
    return $t;
}

// Yazar adını temizle: sadece son kelimeyi al (soyadı genelde daha spesifik)
function clean_author($raw) {
    $t = preg_replace('/[^\x20-\x7E]/u', ' ', $raw);
    $t = trim(preg_replace('/\s+/', ' ', $t));
    $parts = explode(' ', $t);
    // Soyadı + ilk isim kombinasyonu (en fazla 2 kelime)
    return implode(' ', array_slice($parts, 0, 2));
}

// Sonucu skorla: başlıkta kitap kelimesi VE yazar kelimesi geçiyorsa yüksek skor
function score_cover($result_title, $result_authors, $clean_book, $clean_author_str) {
    $score = 0;
    $rt = strtolower($result_title);
    $ra = strtolower(implode(' ', (array)$result_authors));

    // Kitap adının ilk anlamlı kelimesi başlıkta geçiyor mu?
    $book_words = array_filter(explode(' ', strtolower($clean_book)), fn($w) => strlen($w) > 3);
    foreach ($book_words as $w) {
        if (strpos($rt, $w) !== false) { $score += 2; break; }
    }

    // Yazar adı sonuçta geçiyor mu? (başlıkta veya yazar alanında)
    $author_words = array_filter(explode(' ', strtolower($clean_author_str)), fn($w) => strlen($w) > 2);
    foreach ($author_words as $w) {
        if (strpos($rt, $w) !== false || strpos($ra, $w) !== false) { $score += 3; break; }
    }

    return $score;
}

$clean_book   = clean_title($book);
$clean_auth   = clean_author($author);

// Sorgu sıralaması: en spesifikten en genele
$queries = array_filter([
    $clean_book && $clean_auth ? $clean_book . ' ' . $clean_auth : '',  // temiz kitap + yazar
    $clean_book,                                                          // sadece temiz kitap adı
    $clean_auth && $clean_book ? 'intitle:' . $clean_book : '',          // Google Books intitle operatörü
]);

$raw_covers = [];
foreach ($queries as $q) {
    if (!$q) continue;
    $gb = json_decode(@file_get_contents('https://www.googleapis.com/books/v1/volumes?' . http_build_query([
        'q' => $q, 'maxResults' => 5, 'printType' => 'books',
        'fields' => 'items(volumeInfo(title,authors,imageLinks))'
    ])), true);
    foreach ($gb['items'] ?? [] as $item) {
        $lnk = $item['volumeInfo']['imageLinks'] ?? [];
        $c   = $lnk['thumbnail'] ?? ($lnk['smallThumbnail'] ?? '');
        if (!$c) continue;
        $c   = str_replace(['http://', '&edge=curl'], ['https://', ''], $c);
        $url = preg_replace('/zoom=\d/', 'zoom=3', $c);
        // Duplicate URL kontrolü
        if (array_filter($raw_covers, fn($x) => $x['url'] === $url)) continue;
        $result_title   = $item['volumeInfo']['title']   ?? '';
        $result_authors = $item['volumeInfo']['authors'] ?? [];
        $raw_covers[] = [
            'url'    => $url,
            'title'  => $result_title,
            'author' => implode(', ', array_slice($result_authors, 0, 2)),
            'source' => 'google',
            'score'  => score_cover($result_title, $result_authors, $clean_book, $clean_auth),
        ];
    }
    if (count($raw_covers) >= 6) break;
}

// Skora göre sırala (yüksek skor önce)
usort($raw_covers, fn($a, $b) => $b['score'] - $a['score']);

// Skoru çıkar, ilk 4'ü al
$covers = array_map(fn($c) => ['url'=>$c['url'],'title'=>$c['title'],'author'=>$c['author'],'source'=>$c['source']],
    array_slice($raw_covers, 0, 4));

// Google'dan yeterli sonuç gelemediyse Open Library'ye de bak
if (count($covers) < 2) {
    $ol_q = $clean_book . ($clean_auth ? ' ' . $clean_auth : '');
    $ol = json_decode(@file_get_contents(
        'https://openlibrary.org/search.json?q=' . urlencode($ol_q) . '&limit=5&fields=title,author_name,cover_i'
    ), true);
    foreach ($ol['docs'] ?? [] as $doc) {
        if (empty($doc['cover_i'])) continue;
        $ol_title   = $doc['title'] ?? '';
        $ol_authors = $doc['author_name'] ?? [];
        $covers[] = [
            'url'    => "https://covers.openlibrary.org/b/id/{$doc['cover_i']}-L.jpg",
            'title'  => $ol_title,
            'author' => implode(', ', array_slice($ol_authors, 0, 2)),
            'source' => 'openlibrary',
        ];
        if (count($covers) >= 4) break;
    }
}

echo json_encode([
    'ok'               => true,
    'excerpt'          => $meta['excerpt']          ?? '',
    'meta_description' => $meta['meta_description'] ?? '',
    'categories'       => $meta['categories']       ?? [],
    'quotes'           => $meta['quotes']           ?? [],
    'covers'           => $covers,
]);
