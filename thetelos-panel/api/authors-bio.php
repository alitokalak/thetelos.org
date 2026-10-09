<?php
/**
 * authors-bio.php — Yazar biyografilerini denetle & düzelt.
 *
 * Yazar bilgisi 'authors' taksonomisinin TERİM AÇIKLAMASIDIR. Bu araç:
 *   - Tüm yazarları tarar, bio durumunu işaretler (eksik / yarıda kesik /
 *     markdown kalıntısı / çok kısa / iyi).
 *   - Sorunluları DeepSeek ile yeniden ürettirir (düz metin, cümle tam, 2-3
 *     cümle) → önizleme → kaydet. Elle düzenleyip kaydetmek de mümkün.
 *
 * POST action=scan  (offset,limit,only_issues) → sayfa sayfa yazar + durum
 * POST action=regen (id)        → yeni bio metni üret (KAYDETMEZ, önizleme)
 * POST action=save  (id,bio)    → bio'yu terim açıklamasına yaz
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-LiteSpeed-Cache-Control: no-cache');
@set_time_limit(120);

ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();

/* Bio durumu: missing / markdown / truncated / short / ok */
function ab_status($bio) {
    $b = trim((string) $bio);
    if ($b === '') return 'missing';
    if (preg_match('/[*`#]/u', $b)) return 'markdown';
    $last = mb_substr($b, -1, 1, 'UTF-8');
    if (!preg_match('/[.!?"”\'’\)]/u', $last)) return 'truncated';   // cümle bitmemiş
    if (str_word_count(strip_tags($b)) < 12) return 'short';
    return 'ok';
}

/* DeepSeek ile 2-3 cümle, düz metin, tam bio üret (publish.php ile aynı kural). */
function ab_generate_bio($author) {
    if (!defined('DEEPSEEK_API_URL') || !defined('DEEPSEEK_KEY') || !DEEPSEEK_KEY) return '';
    $model  = (defined('DEEPSEEK_MODEL') && !in_array(DEEPSEEK_MODEL, ['deepseek-chat', 'deepseek-reasoner'], true))
            ? DEEPSEEK_MODEL : 'deepseek-v4-flash';
    $prompt = "Write a concise 2-3 sentence biography of the author \"{$author}\" for a philosophy "
            . "and literature website. Focus on their main works, philosophical contributions, and "
            . "historical context. Write in English, factual and encyclopedic. Plain prose only — NO "
            . "markdown, asterisks, or formatting. Finish every sentence; do not cut off mid-sentence.";
    $ch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 40,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model, 'max_tokens' => 420, 'temperature' => 0.3,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]),
    ]);
    $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200 || !$r) return '';
    $bio = json_decode($r, true)['choices'][0]['message']['content'] ?? '';
    return trim(preg_replace('/[*`#]+/u', '', (string) $bio));
}

$action = $_POST['action'] ?? '';

/* ── Tara: yazarları sayfa sayfa getir + durum işaretle ── */
if ($action === 'scan') {
    $offset = max(0, (int) ($_POST['offset'] ?? 0));
    $limit  = min(100, max(10, (int) ($_POST['limit'] ?? 60)));
    $only   = !empty($_POST['only_issues']);

    $terms = get_terms([
        'taxonomy'   => 'authors',
        'hide_empty' => false,
        'number'     => $limit,
        'offset'     => $offset,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);
    if (is_wp_error($terms)) { echo json_encode(['ok' => false, 'error' => $terms->get_error_message()]); exit; }

    $rows = [];
    foreach ($terms as $t) {
        $st = ab_status($t->description);
        if ($only && $st === 'ok') continue;
        $rows[] = [
            'id'     => (int) $t->term_id,
            'name'   => $t->name,
            'count'  => (int) $t->count,
            'bio'    => (string) $t->description,
            'status' => $st,
            'link'   => get_term_link((int) $t->term_id, 'authors'),
        ];
    }

    $total = (int) wp_count_terms(['taxonomy' => 'authors', 'hide_empty' => false]);
    echo json_encode([
        'ok'          => true,
        'rows'        => $rows,
        'scanned'     => count($terms),
        'next_offset' => $offset + count($terms),
        'done'        => count($terms) < $limit,
        'total'       => $total,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Yeniden üret (önizleme — kaydetmez) ── */
if ($action === 'regen') {
    $id = (int) ($_POST['id'] ?? 0);
    $t  = $id ? get_term($id, 'authors') : null;
    if (!$t || is_wp_error($t)) { echo json_encode(['ok' => false, 'error' => 'yazar bulunamadı']); exit; }
    $bio = ab_generate_bio($t->name);
    if ($bio === '') { echo json_encode(['ok' => false, 'error' => 'bio üretilemedi (DeepSeek)']); exit; }
    echo json_encode(['ok' => true, 'bio' => $bio, 'status' => ab_status($bio)], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Kaydet ── */
if ($action === 'save') {
    $id  = (int) ($_POST['id'] ?? 0);
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $bio = trim(preg_replace('/[*`#]+/u', '', $bio));            // güvenlik: markdown temizle
    $bio = sanitize_textarea_field($bio);
    $t   = $id ? get_term($id, 'authors') : null;
    if (!$t || is_wp_error($t)) { echo json_encode(['ok' => false, 'error' => 'yazar bulunamadı']); exit; }
    $u = wp_update_term($id, 'authors', ['description' => $bio]);
    if (is_wp_error($u)) { echo json_encode(['ok' => false, 'error' => $u->get_error_message()]); exit; }
    echo json_encode(['ok' => true, 'bio' => $bio, 'status' => ab_status($bio)], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'bad action']);
