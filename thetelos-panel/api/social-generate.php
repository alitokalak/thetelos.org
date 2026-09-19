<?php
/**
 * social-generate.php — Sosyal medya paylaşımı için içerik ÜRETİR (Faz 1).
 *
 * Popüler (ya da seçilen) yazılardan: gerçek bir ALINTI + yazar + kitap + site
 * linki + hazır caption/hashtag döndürür ve kuyruğa (option) ekler. Görsel
 * kartı PANELDE tarayıcıda (Canvas) üretilir — burada yalnız veriyi hazırlarız.
 *
 * POST: count(1-30), source(popular|recent), category(term_id, ops.),
 *       post_id / title (ops. — elle tek üretim), queue(1 → kuyruğa da yaz)
 * → { ok, items:[{post_id,title,author,url,cover,quote,quote_kind,caption,hashtags}] }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');

ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();

$count    = max(1, min(30, (int) ($_POST['count'] ?? 10)));
$source   = ($_POST['source'] ?? 'popular') === 'recent' ? 'recent' : 'popular';
$cat      = (int) ($_POST['category'] ?? 0);
$one_pid  = (int) ($_POST['post_id'] ?? 0);
$one_title= trim((string) ($_POST['title'] ?? ''));

/* ── Yazıdan paylaşılabilir bir ALINTI çıkar ──────────────────────────────
   Öncelik: gövdedeki <blockquote> (proto'nun eklediği GERÇEK birebir alıntı).
   Yoksa: özetten güçlü, kendi başına duran bir cümle (40–200 karakter). */
function sg_pick_quote($html) {
    $html = (string) $html;
    // 1) Gerçek pull-quote (blockquote)
    if (preg_match('#<blockquote[^>]*>(.*?)</blockquote>#is', $html, $m)) {
        $q = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $q = trim($q, " \t\n\r\0\x0B\"“”‘’");
        if (mb_strlen($q) >= 30 && mb_strlen($q) <= 240) return ['text' => $q, 'kind' => 'quote'];
    }
    // 2) Özetten iyi bir cümle
    $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $text = preg_replace('/\s+/u', ' ', $text);
    $sentences = preg_split('/(?<=[.!?])\s+/u', $text);
    $best = '';
    foreach ((array) $sentences as $s) {
        $s = trim($s);
        $len = mb_strlen($s);
        if ($len < 50 || $len > 200) continue;
        if (preg_match('/^(##|the following|this summary|in this|here )/i', $s)) continue;
        if (strpos($s, 'http') !== false) continue;
        $best = $s; break;   // ilk uygun cümle yeterince iyi
    }
    if ($best === '') return ['text' => '', 'kind' => 'none'];
    return ['text' => $best, 'kind' => 'insight'];   // özet cümlesi (yazara ATFEDİLMEZ)
}

/* ── Yazı listesi seç ── */
$ids = [];
if ($one_pid > 0) {
    $ids = [$one_pid];
} elseif ($one_title !== '') {
    $p = get_page_by_title($one_title, OBJECT, 'post');
    if ($p) $ids = [$p->ID];
} else {
    $base = ['post_type' => 'post', 'post_status' => 'publish', 'fields' => 'ids',
             'posts_per_page' => $count, 'no_found_rows' => true, 'ignore_sticky_posts' => true];
    if ($cat > 0) $base['cat'] = $cat;
    if ($source === 'popular') {
        $ids = get_posts(array_merge($base, [
            'meta_key' => 'post_views_count', 'orderby' => 'meta_value_num', 'order' => 'DESC',
            'meta_query' => [['key' => 'post_views_count', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC']],
        ]));
    }
    if (count($ids) < $count) {   // az geldiyse tarihle tamamla
        $more = get_posts(array_merge($base, ['orderby' => 'date', 'order' => 'DESC',
            'post__not_in' => $ids, 'posts_per_page' => $count - count($ids)]));
        $ids = array_merge($ids, $more);
    }
}
$ids = array_values(array_unique(array_map('intval', $ids)));
if (!$ids) { echo json_encode(['ok' => false, 'error' => 'Uygun yazı bulunamadı.']); exit; }

/* ── Her yazı için sosyal içerik kur ── */
$brand   = defined('TLS_SOCIAL_HANDLE') ? TLS_SOCIAL_HANDLE : '@thetelos';
$site    = 'thetelos.org';
$items   = [];
foreach ($ids as $pid) {
    $post = get_post($pid);
    if (!$post || $post->post_status !== 'publish') continue;
    $title  = html_entity_decode(get_the_title($pid), ENT_QUOTES, 'UTF-8');
    $terms  = get_the_terms($pid, 'authors');
    $author = (!empty($terms) && !is_wp_error($terms)) ? $terms[0]->name : '';
    $cats   = get_the_category($pid);
    $catname= !empty($cats) ? $cats[0]->name : '';
    $url    = get_permalink($pid);
    $cover  = has_post_thumbnail($pid) ? get_the_post_thumbnail_url($pid, 'large') : '';
    $q      = sg_pick_quote($post->post_content);
    if ($q['text'] === '') continue;

    // Caption: alıntı → atıf → link → hashtag. quote ise yazara, insight ise
    // "The Telos özeti" olarak dürüst atıf.
    $attrib = ($q['kind'] === 'quote' && $author !== '')
        ? '— ' . $author . ($title ? ', ' . $title : '')
        : ($author !== '' ? $title . ' — ' . $author : $title);
    $tagbits = ['#books', '#reading', '#booksummary', '#quotes'];
    if ($catname !== '') $tagbits[] = '#' . preg_replace('/[^a-z0-9]/', '', strtolower($catname));
    $tagbits[] = '#thetelos';
    $hashtags = implode(' ', array_values(array_unique($tagbits)));
    $caption = '“' . $q['text'] . '”' . "\n\n" . $attrib . "\n\n"
             . 'Full summary → ' . $url . "\n\n" . $hashtags;

    // Twitter/X için derli toplu metin (≤280; link t.co'da 23 sayılır)
    $tw_tags = '#books #thetelos';
    $tw_fixed = mb_strlen($attrib) + mb_strlen($tw_tags) + 23 + 8;  // atıf + tag + link + \n & tırnak
    $tw_qbudget = 280 - $tw_fixed;
    $tw_q = $q['text'];
    if (mb_strlen($tw_q) > $tw_qbudget) $tw_q = mb_substr($tw_q, 0, max(20, $tw_qbudget - 1)) . '…';
    $tweet = '“' . $tw_q . '”' . "\n\n" . $attrib . "\n\n" . $url . ' ' . $tw_tags;

    $items[] = [
        'post_id' => $pid, 'title' => $title, 'author' => $author, 'book' => $title,
        'category' => $catname, 'url' => $url, 'cover' => $cover,
        'quote' => $q['text'], 'quote_kind' => $q['kind'],
        'handle' => $brand, 'site' => $site,
        'caption' => $caption, 'tweet' => $tweet, 'hashtags' => $hashtags,
    ];
}
if (!$items) { echo json_encode(['ok' => false, 'error' => 'Alıntı çıkarılamadı (içerik kısa olabilir).']); exit; }

/* ── Kuyruğa yaz (istenirse) ── */
if (($_POST['queue'] ?? '') === '1') {
    $queue = get_option('tls_social_queue', []);
    if (!is_array($queue)) $queue = [];
    $today = date('Y-m-d');
    foreach ($items as $it) {
        $queue[] = ['t' => time(), 'day' => $today, 'status' => 'pending'] + $it;
    }
    $queue = array_slice($queue, -300);   // en fazla son 300 kayıt
    update_option('tls_social_queue', $queue, false);
}

echo json_encode(['ok' => true, 'count' => count($items), 'items' => $items], JSON_UNESCAPED_UNICODE);
