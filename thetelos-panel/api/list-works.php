<?php
/**
 * list-works.php — Yazarın eserlerini üret (LLM) veya OpenLibrary ile doğrula.
 *
 * İki mod (Cloudflare ~100s timeout'u aşmamak için ayrıldı):
 *   mode=list   : Sadece LLM ile eser listesi (hızlı, doğrulama yok).
 *                 POST: author, api_provider
 *                 Dönüş: { ok, author, count, works:[{title, original, year}] }
 *   mode=verify : Bir grup başlığı OpenLibrary/Google ile doğrula + kapak getir.
 *                 POST: author, titles (JSON array), api_provider
 *                 Dönüş: { ok, results:[{title, verified, cover, year, ol_key}] }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();  // Session kilidini hemen bırak — uzun LLM çağrıları boyunca kilide gerek yok
header('Content-Type: application/json');
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
set_time_limit(120);

$author = trim($_POST['author'] ?? '');
$provider = trim($_POST['api_provider'] ?? 'deepseek');
$mode = trim($_POST['mode'] ?? 'list');

if ($author === '') { echo json_encode(['ok'=>false,'error'=>'Yazar adı zorunlu.']); exit; }

// ── LLM çağrısı (JSON döndüren, stream değil) ─────────────────────
// Dönüş: [text, error, truncated]  (truncated=true → token limiti nedeniyle kesildi)
// TIMEOUT 85sn: Cloudflare ~100sn'de bağlantıyı kesip HTML hata sayfası döndürüyor;
// ondan önce cURL'i keserek temiz JSON hata mesajı döndürmüş oluyoruz.
function lw_call_llm($provider, $prompt, $max_tokens = 4000) {
    $attempt = function($provider, $prompt, $max_tokens) {
        if ($provider === 'anthropic') {
            // Haiku: kanon bilgisi yeterli, Sonnet'ten 3-4 kat hızlı (sunucu
            // timeout'una takılmaz) ve çok daha ucuz.
            $ch = curl_init('https://api.anthropic.com/v1/messages');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
                CURLOPT_TIMEOUT=>85, CURLOPT_CONNECTTIMEOUT=>10,
                CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.ANTHROPIC_KEY,'anthropic-version: 2023-06-01'],
                CURLOPT_POSTFIELDS=>json_encode(['model'=>'claude-haiku-4-5-20251001','max_tokens'=>$max_tokens,'temperature'=>0,'messages'=>[['role'=>'user','content'=>$prompt]]]),
            ]);
            $r = curl_exec($ch); $e = curl_error($ch); curl_close($ch);
            if ($e || !$r) return ['', 'Anthropic: '.($e ?: 'boş yanıt'), false];
            $d = json_decode($r, true);
            if (isset($d['error'])) return ['', 'Anthropic: '.($d['error']['message']??'hata'), false];
            $trunc = ($d['stop_reason'] ?? '') === 'max_tokens';
            return [$d['content'][0]['text'] ?? '', '', $trunc];
        }
        // deepseek
        $ch = curl_init(DEEPSEEK_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
            CURLOPT_TIMEOUT=>85, CURLOPT_CONNECTTIMEOUT=>10,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
            CURLOPT_POSTFIELDS=>json_encode(['model'=>DEEPSEEK_MODEL,'max_tokens'=>$max_tokens,'temperature'=>0,'messages'=>[['role'=>'user','content'=>$prompt]]]),
        ]);
        $r = curl_exec($ch); $e = curl_error($ch); curl_close($ch);
        if ($e || !$r) return ['', 'DeepSeek: '.($e ?: 'boş yanıt'), false];
        $d = json_decode($r, true);
        if (isset($d['error'])) return ['', 'DeepSeek: '.($d['error']['message']??'hata'), false];
        $trunc = ($d['choices'][0]['finish_reason'] ?? '') === 'length';
        return [$d['choices'][0]['message']['content'] ?? '', '', $trunc];
    };

    $t0 = microtime(true);
    [$text, $err, $trunc] = $attempt($provider, $prompt, $max_tokens);
    $elapsed = microtime(true) - $t0;
    // Sadece HIZLI başarısızlıkta (geçici bağlantı kopması) bir kez yeniden dene.
    // İlk deneme timeout'a kadar sürdüyse tekrar deneme — Cloudflare sınırını aşar.
    if ($err && stripos($err, 'hata') === false && $elapsed < 25) {
        [$text, $err, $trunc] = $attempt($provider, $prompt, $max_tokens);
    }
    return [$text, $err, $trunc];
}

// JSON dizisini güvenli ayıkla (markdown fence / fazla metin / kesik JSON temizle)
function lw_extract_json_array($text) {
    $text = preg_replace('/```json|```/i', '', $text);
    $s = strpos($text, '[');
    if ($s === false) return null;
    $e = strrpos($text, ']');
    $json = ($e !== false && $e > $s) ? substr($text, $s, $e - $s + 1) : substr($text, $s);
    $arr = json_decode($json, true);
    if (is_array($arr)) return $arr;
    // Kesik JSON onarımı: son tam nesneye kadar kırp ve diziyi kapat
    if (($lastObj = strrpos($json, '}')) !== false) {
        $repaired = substr($json, 0, $lastObj + 1) . ']';
        $arr = json_decode($repaired, true);
        if (is_array($arr)) return $arr;
    }
    return null;
}

// Dedup için başlık normalleştir: aksanları kaldır, parantez içini at, yalnız harf/rakam bırak
function lw_norm($s) {
    $s = (string)$s;
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false && $t !== '') $s = $t;
    $s = strtolower($s);
    $s = preg_replace('/\([^)]*\)/', ' ', $s);   // parantez içi varyantları at
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);  // sadece harf/rakam
    return trim(preg_replace('/\s+/', ' ', $s));
}

// ── cURL GET (allow_url_fopen kapalı olabilir) ────────────────────
function lw_http_get($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['User-Agent: ThetelosBot/1.0 (+https://thetelos.org)'],
    ]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($c >= 200 && $c < 300) ? $r : null;
}

function lw_ol_verify($title, $author) {
    // 1) OpenLibrary — başlık + yazar
    $r = lw_http_get('https://openlibrary.org/search.json?' . http_build_query([
        'title'  => $title,
        'author' => $author,
        'limit'  => 1,
        'fields' => 'key,title,first_publish_year,cover_i',
    ]));
    if ($r) {
        $d = json_decode($r, true);
        if (!empty($d['docs'][0])) {
            $doc = $d['docs'][0];
            return [
                'ol_key'   => $doc['key'] ?? '',
                'ol_year'  => $doc['first_publish_year'] ?? null,
                'cover'    => !empty($doc['cover_i']) ? "https://covers.openlibrary.org/b/id/{$doc['cover_i']}-M.jpg" : '',
                'ol_title' => $doc['title'] ?? '',
            ];
        }
    }

    // 2) Google Books — kapak için (intitle + inauthor)
    $r2 = lw_http_get('https://www.googleapis.com/books/v1/volumes?' . http_build_query([
        'q'          => 'intitle:' . $title . ' inauthor:' . $author,
        'maxResults' => 1,
        'printType'  => 'books',
        'fields'     => 'items(volumeInfo(imageLinks,publishedDate))',
    ]));
    if ($r2) {
        $d2 = json_decode($r2, true);
        $vi = $d2['items'][0]['volumeInfo'] ?? null;
        if ($vi) {
            $c = $vi['imageLinks']['thumbnail'] ?? ($vi['imageLinks']['smallThumbnail'] ?? '');
            if ($c) {
                $c = str_replace(['http://', '&edge=curl'], ['https://', ''], $c);
                $c = preg_replace('/zoom=\d/', 'zoom=1', $c);
                $yr = null;
                if (!empty($vi['publishedDate']) && preg_match('/(\d{4})/', $vi['publishedDate'], $ym)) $yr = (int)$ym[1];
                return ['ol_key'=>'', 'ol_year'=>$yr, 'cover'=>$c, 'ol_title'=>''];
            }
        }
    }

    return null;
}

/* ════════════════ MODE: exists (yazarın sitedeki TÜM eserlerini tek seferde getir) ════════════════
 * Dönüş: { ok, existing:[ {norm, title, post_id, post_url} ] }
 * İstemci normalleştirilmiş başlıkla yerel eşleştirme yapar (tek istek = hızlı).
 */
if ($mode === 'exists') {
    $auth   = 'Basic ' . base64_encode(WP_USER . ':' . WP_APP_PASS);
    $wp_api = rtrim(WP_URL, '/') . '/wp-json/wp/v2';

    $wp_get = function($url) use ($auth) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20, CURLOPT_CONNECTTIMEOUT=>10,
            CURLOPT_HTTPHEADER=>['Authorization: '.$auth],
        ]);
        $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return ($c>=200 && $c<300) ? json_decode($r, true) : null;
    };

    // Yazar terimini bul
    $tid = null;
    $terms = $wp_get("$wp_api/authors?search=" . urlencode($author) . '&per_page=10');
    foreach ($terms ?? [] as $t) {
        if (strtolower($t['name'] ?? '') === strtolower($author)) { $tid = $t['id']; break; }
    }
    if ($tid === null && !empty($terms[0]['id'])) $tid = $terms[0]['id'];

    $existing = [];
    $collect = function($posts) use (&$existing, $author) {
        foreach ($posts ?? [] as $p) {
            $ptitle = html_entity_decode(strip_tags($p['title']['rendered'] ?? ''));
            // " - Yazar" sonekini at
            $clean = preg_replace('/\s*[-–]\s*' . preg_quote($author, '/') . '\s*$/i', '', $ptitle);
            $norm = lw_norm($clean);
            if ($norm === '') continue;
            $existing[] = ['norm'=>$norm, 'title'=>$ptitle, 'post_id'=>$p['id'], 'post_url'=>$p['link'] ?? ''];
        }
    };

    foreach (['posts','analysis'] as $ep) {
        if ($tid !== null) {
            $collect($wp_get("$wp_api/$ep?authors=$tid&per_page=100&status=publish,draft,pending,private,future"));
        }
        // Yazar terimi atanmamış olabilir → ada göre arama ile de tara
        $collect($wp_get("$wp_api/$ep?search=" . urlencode($author) . '&per_page=100&status=publish,draft,pending,private,future'));
    }

    echo json_encode(['ok'=>true, 'existing'=>$existing], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ════════════════ MODE: verify ════════════════ */
if ($mode === 'verify') {
    $titles = json_decode($_POST['titles'] ?? '[]', true);
    if (!is_array($titles)) { echo json_encode(['ok'=>false,'error'=>'titles geçersiz.']); exit; }
    $titles = array_slice($titles, 0, 10); // güvenli üst sınır (10×~12s < 100s)
    $results = [];
    foreach ($titles as $t) {
        $t = trim((string)$t);
        if ($t === '') continue;
        $row = ['title'=>$t, 'verified'=>false, 'cover'=>'', 'year'=>null, 'ol_key'=>''];
        $v = lw_ol_verify($t, $author);
        if ($v) {
            $row['verified'] = true;
            $row['cover']    = $v['cover'];
            $row['ol_key']   = $v['ol_key'];
            $row['year']     = $v['ol_year'];
        }
        $results[] = $row;
    }
    echo json_encode(['ok'=>true, 'results'=>$results], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ════════════════ MODE: list ════════════════ */
// Eser listesi DOĞRULUK ister (kanon bilgisi, şüpheli/tekrar eseri ayıklama).
// DeepSeek kanonu zayıf hatırlayıp dubia/spuria ve tekrar çeviri ekliyordu;
// bu yüzden liste üretimini DAİMA Claude (Anthropic) ile yapıyoruz — model
// kanonu çok daha doğru biliyor. İçerik üretimi (pahalı kısım) DeepSeek'te kalır.
// Maliyet ihmal edilebilir: yazar başına tek çağrı, ~5K token.
$provider = 'anthropic';
$prompt = "List the COMPLETE works of the author \"{$author}\".\n"
    . "BE EXHAUSTIVE. Include every distinct standalone work the author produced across ALL the fields they wrote in "
    . "(for example: philosophical, religious/legal, scientific, medical, and major letters/epistles). "
    . "A prolific author typically has between 15 and 40 distinct works — do not stop at only the few most famous ones.\n"
    . "For EACH work provide:\n"
    . "- \"title\": the standard English title by which the work is known in scholarship\n"
    . "- \"original\": the original-language title, romanized into Latin letters "
    . "(e.g. Arabic/Hebrew/Greek transliterated). ALWAYS provide this; if the work was originally written in English, repeat the English title.\n"
    . "- \"year\": the approximate first publication or composition year (integer)\n"
    . "List them in roughly chronological order.\n"
    . "CRITICAL RULES:\n"
    . "- AUTHENTICITY: List ONLY works whose attribution to THIS exact author is securely established in standard scholarship "
    . "(e.g. for Thomas Aquinas the Torrell / Leonine catalogue). EXCLUDE all dubia (doubtful attributions) and spuria "
    . "(works falsely or traditionally but wrongly attributed). If a work's authenticity is debated or you are not certain it is genuine, OMIT it entirely.\n"
    . "- NO DUPLICATES: Each distinct text appears EXACTLY ONCE. If the same work is known by multiple English titles, transliterations, "
    . "or alternative names, pick ONE and never list it again under a variant name.\n"
    . "- The \"title\" field must contain ONLY the work's name — never add '(alternative)', '(variant)', '(also known as)' etc.\n"
    . "- A multi-volume work may be listed per volume (e.g. \"Work Title (Volume 1)\"), BUT never list BOTH the whole work AND its individual volumes/chapters.\n"
    . "- Do NOT split a single book into its internal chapters, sections, or laws (e.g. a legal code is ONE entry, not its 30 subsections).\n"
    . "- Prefer a tighter, fully-authentic list over a longer one padded with doubtful items.\n"
    . "Return ONLY a valid JSON array — no prose, no markdown fences:\n"
    . "[{\"title\":\"English Title\",\"original\":\"Romanized Original Title\",\"year\":1234}]";

$items     = [];
$seen      = [];   // normalize edilmiş İngilizce başlık
$seen_orig = [];   // normalize edilmiş orijinal başlık
$first_err = '';

// Tek çağrı yap (temperature=0 → tutarlı sonuç).
// Yanıt token limitiyle kesilmişse bir kez daha devam isteği gönder.
for ($round = 1; $round <= 2; $round++) {
    set_time_limit(110);   // her tur için süreyi sıfırla — iki tur üst üste 120sn sınırını aşıp 500 veriyordu
    [$raw, $err, $truncated] = lw_call_llm($provider, $prompt, 6000);
    if ($err) { $first_err = $err; break; }

    $batch = lw_extract_json_array($raw);
    if (!is_array($batch)) { $first_err = 'Liste ayrıştırılamadı.'; break; }

    $added = 0;
    foreach ($batch as $it) {
        $t = trim($it['title'] ?? '');
        if ($t === '') continue;
        $ne = lw_norm($t);                          // İngilizce başlık
        $no = lw_norm($it['original'] ?? '');       // orijinal başlık
        if ($ne !== '' && isset($seen[$ne])) continue;          // aynı İngilizce ad
        if ($no !== '' && isset($seen_orig[$no])) continue;     // aynı orijinal ad = aynı eser
        if ($ne !== '') $seen[$ne] = true;
        if ($no !== '') $seen_orig[$no] = true;
        $items[] = $it;
        $added++;
    }

    if (count($items) >= 250) break;
    // Sadece yanıt token limitiyle kesilmişse devam et; yoksa tek turda kal
    if (!$truncated) break;
    // Devam turu: kesildiği yerden JSON'u tamamlamasını iste
    $prompt = "Continue the JSON array from where you stopped. Do NOT restart from the beginning. "
        . "Output ONLY the remaining items as valid JSON array entries, starting with [ if needed, ending with ].\n"
        . "Same format: [{\"title\":\"...\",\"original\":\"...\",\"year\":0}]";
}

if (!$items) { echo json_encode(['ok'=>false,'error'=>$first_err ?: 'Liste alınamadı.']); exit; }

$works = [];
foreach ($items as $it) {
    $title = trim($it['title'] ?? '');
    if ($title === '') continue;
    $original = trim($it['original'] ?? '');
    $works[] = [
        'title'    => $title,
        'original' => ($original && stripos($original, $title) === false) ? $original : '',
        'year'     => isset($it['year']) ? (int)$it['year'] : null,
        'verified' => false,
        'cover'    => '',
        'ol_key'   => '',
    ];
}

echo json_encode([
    'ok'     => true,
    'author' => $author,
    'count'  => count($works),
    'works'  => $works,
], JSON_UNESCAPED_UNICODE);
