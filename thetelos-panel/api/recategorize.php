<?php
/**
 * recategorize.php — "General" kovasında kalan yazıları doğru kategorilere taşır.
 *
 * NEDEN: Meta isteği dar token bütçesi yüzünden yarıda kesildiğinde kategori
 * listesi boş dönüyordu ve kod yazıyı "General"e atıyordu. Bu araç o yazıları
 * içeriğinden yeniden sınıflandırır.
 *
 * NASIL: Yazının başlığı + yazarı + gövde başlangıcı DeepSeek'e verilir; SADECE
 * sitede HÂLİHAZIRDA VAR OLAN kategori slug'ları arasından 2-5 tanesini seçmesi
 * istenir. Yeni kategori ASLA oluşturulmaz. Seçilenler atanır, "General" çıkarılır.
 *
 * POST action=stats → sayaçlar
 * POST action=run   → sıradaki N yazıyı işler (varsayılan 5)
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-LiteSpeed-Cache-Control: no-cache');
@set_time_limit(300);

ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();

/** Etkin DeepSeek modeli (eski adlar kullanımdan kalktı). */
function rc_model() {
    return in_array(DEEPSEEK_MODEL, ['deepseek-chat','deepseek-reasoner'], true)
        ? 'deepseek-v4-flash' : DEEPSEEK_MODEL;
}

/** "General" kategori terimi (yoksa null). */
function rc_general_id() {
    $t = get_category_by_slug('general');
    return $t ? (int) $t->term_id : 0;
}

function rc_stats() {
    $gid = rc_general_id();
    if (!$gid) return ['general' => 0, 'fixed' => 0, 'skipped' => 0];
    $in_general = (int) (get_category($gid)->count ?? 0);
    $fixed   = count(get_posts(['post_type'=>'post','post_status'=>'publish','numberposts'=>-1,
                'fields'=>'ids','meta_key'=>'_tls_recat_done','meta_value'=>'1']));
    $skipped = count(get_posts(['post_type'=>'post','post_status'=>'publish','numberposts'=>-1,
                'fields'=>'ids','meta_key'=>'_tls_recat_skip','meta_value'=>'1']));
    return ['general' => $in_general, 'fixed' => $fixed, 'skipped' => $skipped];
}

$action = $_POST['action'] ?? '';

if ($action === 'stats') {
    echo json_encode(['ok' => true, 'stats' => rc_stats()]);
    exit;
}

if ($action !== 'run') { echo json_encode(['ok'=>false,'error'=>'bad action']); exit; }

$gid = rc_general_id();
if (!$gid) { echo json_encode(['ok'=>true,'done'=>true,'rows'=>[],'stats'=>rc_stats()]); exit; }

$chunk = max(1, min(10, (int)($_POST['chunk'] ?? 5)));

// Sitedeki TÜM kategoriler — AI yalnız bunlardan seçebilir (yeni kategori yok).
$all = get_categories(['hide_empty' => false]);
$slug2id = []; $palette = [];
foreach ($all as $c) {
    if ($c->slug === 'general' || $c->slug === 'uncategorized') continue;
    $slug2id[$c->slug] = (int) $c->term_id;
    $palette[] = $c->slug;
}

$q = new WP_Query([
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => $chunk,
    'cat'            => $gid,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'fields'         => 'ids',
    'no_found_rows'  => true,
    'meta_query'     => [[ 'key' => '_tls_recat_skip', 'compare' => 'NOT EXISTS' ]],
]);

$rows = [];
foreach ($q->posts as $pid) {
    $title   = html_entity_decode(get_the_title($pid), ENT_QUOTES, 'UTF-8');
    $authors = get_the_terms($pid, 'authors');
    $author  = (!empty($authors) && !is_wp_error($authors)) ? $authors[0]->name : '';
    $snippet = mb_substr(trim(strip_tags(get_post_field('post_content', $pid))), 0, 1800);

    $prompt = "Return ONLY valid JSON: {\"categories\":[\"slug1\",\"slug2\"]}\n"
        . "Pick 2-5 category slugs that best fit this book, chosen STRICTLY from this list:\n"
        . implode(',', $palette) . "\n"
        . "Use only slugs from the list, exactly as written. Never invent new ones.\n"
        . "Book: \"{$title}\" by {$author}\n\n{$snippet}";

    $picked = [];
    for ($try = 1; $try <= 2; $try++) {
        $ch = curl_init(DEEPSEEK_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
            CURLOPT_POSTFIELDS => json_encode([
                'model'           => rc_model(),
                'max_tokens'      => 800,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [['role'=>'user','content'=>$prompt]],
            ]),
        ]);
        $raw = curl_exec($ch); curl_close($ch);
        $txt = json_decode((string)$raw, true)['choices'][0]['message']['content'] ?? '';
        $txt = trim(preg_replace('/```json|```/', '', (string)$txt));
        $j   = json_decode($txt, true) ?: [];
        if (!$j && $txt !== '' && preg_match('/\{.*\}/s', $txt, $m)) $j = json_decode($m[0], true) ?: [];
        foreach (($j['categories'] ?? []) as $s) {
            $s = sanitize_title((string)$s);
            if (isset($slug2id[$s]) && !in_array($s, $picked, true)) $picked[] = $s;
        }
        if ($picked) break;
        sleep(1);
    }

    if (!$picked) {
        // Bulunamadı → bir daha sorma, General'de kalsın
        update_post_meta($pid, '_tls_recat_skip', '1');
        $rows[] = ['post_id'=>$pid, 'title'=>$title, 'cats'=>[], 'ok'=>false];
        continue;
    }

    $ids = array_values(array_map(fn($s) => $slug2id[$s], array_slice($picked, 0, 5)));
    wp_set_post_categories($pid, $ids, false);   // General dahil eskiler değiştirilir
    update_post_meta($pid, '_tls_recat_done', '1');
    delete_post_meta($pid, '_tls_recat_skip');
    $rows[] = ['post_id'=>$pid, 'title'=>$title, 'cats'=>array_slice($picked,0,5), 'ok'=>true,
               'url'=>get_permalink($pid)];
}

echo json_encode([
    'ok'    => true,
    'rows'  => $rows,
    'done'  => count($q->posts) === 0,
    'stats' => rc_stats(),
], JSON_UNESCAPED_UNICODE);
