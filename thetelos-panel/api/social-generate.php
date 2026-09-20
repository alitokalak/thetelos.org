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

/* ── AI carousel slaytları (DeepSeek) — tutarlı, sıralı, tek-fikirli ── */
function sg_slide_prompt($book, $author, $content) {
    $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $content), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $plain = mb_substr($plain, 0, 4000);
    return "You are writing an Instagram carousel about the book \"$book\"" . ($author ? " by $author" : "") . ".\n"
        . "Write between 3 and 5 slides based on the summary below — use ONLY as many as the material genuinely supports; do NOT pad with filler just to reach a number.\n"
        . "Rules:\n"
        . "- One clear idea per slide, 8-22 words, a plain declarative sentence.\n"
        . "- In order: the first slide states the core premise; the middle slides give the key ideas; the last is the main takeaway.\n"
        . "- No numbering, no quotes, no emojis, no hashtags, do not write 'this book' or 'the summary'.\n"
        . "- Output ONLY the slide lines, one slide per line, nothing else.\n\nSUMMARY:\n" . $plain;
}
function sg_parse_slides($txt) {
    $lines = preg_split('/\r?\n/', (string) $txt); $out = [];
    foreach ($lines as $l) {
        $l = trim($l);
        $l = preg_replace('/^\s*(\d+[\).\-:]|[-*•])\s*/u', '', $l);   // numara/madde işaretini at
        $l = trim($l, " \t\"“”'’-–—");
        if (mb_strlen($l) >= 20 && mb_strlen($l) <= 200) $out[] = $l;
    }
    return array_slice($out, 0, 5);
}

/* Carousel için özetten kısa noktalar çıkar (kapak alıntısı hariç). */
function sg_pick_points($html, $exclude, $n = 4) {
    $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $text = preg_replace('/\s+/u', ' ', $text);
    $sent = preg_split('/(?<=[.!?])\s+/u', $text);
    $ex = mb_strtolower(trim((string) $exclude)); $out = [];
    foreach ((array) $sent as $s) {
        $s = trim($s); $len = mb_strlen($s);
        if ($len < 50 || $len > 190) continue;
        if (preg_match('/^(##|the following|this summary|in this|here )/i', $s)) continue;
        if (strpos($s, 'http') !== false) continue;
        if ($ex !== '' && mb_strtolower($s) === $ex) continue;
        $out[] = $s;
        if (count($out) >= $n) break;
    }
    return $out;
}

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

/* ── Daha önce X'e paylaşılanlar (tekrar üretme/gösterme) ── */
$shared = get_option('tls_social_shared', []);
if (!is_array($shared)) $shared = [];
$shared_ids  = array_map('intval', array_keys($shared));
$exclude_shared = (($_POST['exclude_shared'] ?? '1') === '1');
$skip = ($exclude_shared && $shared_ids) ? $shared_ids : [];

/* ── Yazı listesi seç ──
   Aynı içeriğin tekrar tekrar gelmemesi için: geniş bir HAVUZ çekilir
   (popüler ya da yeni), paylaşılanlar çıkarılır, KARIŞTIRILIR, count kadar
   alınır. Böylece her "Üret" farklı bir set verir. */
$ids = [];
if ($one_pid > 0) {
    $ids = [$one_pid];
} elseif ($one_title !== '') {
    $p = get_page_by_title($one_title, OBJECT, 'post');
    if ($p) $ids = [$p->ID];
} else {
    $pool_size = max($count * 6, 60);
    $base = ['post_type' => 'post', 'post_status' => 'publish', 'fields' => 'ids',
             'posts_per_page' => $pool_size, 'no_found_rows' => true, 'ignore_sticky_posts' => true];
    if ($cat > 0) $base['cat'] = $cat;
    if ($skip) $base['post__not_in'] = $skip;   // paylaşılanları hariç tut
    if ($source === 'popular') {
        $pool = get_posts(array_merge($base, [
            'meta_key' => 'post_views_count', 'orderby' => 'meta_value_num', 'order' => 'DESC',
            'meta_query' => [['key' => 'post_views_count', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC']],
        ]));
    } else {
        $pool = get_posts(array_merge($base, ['orderby' => 'date', 'order' => 'DESC']));
    }
    shuffle($pool);                                  // her Üret farklı olsun
    $ids = array_slice($pool, 0, $count);
    if (count($ids) < $count) {                      // havuz az geldiyse tarihle tamamla
        $more = get_posts(array_merge($base, ['orderby' => 'date', 'order' => 'DESC',
            'post__not_in' => array_merge($ids, $skip), 'posts_per_page' => $count - count($ids)]));
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
        'slides'     => sg_pick_points($post->post_content, $q['text'], 4),  // carousel detay noktaları
        'shared'    => isset($shared[(string) $pid]),
        'shared_at' => isset($shared[(string) $pid]['t']) ? date('Y-m-d', (int) $shared[(string) $pid]['t']) : '',
    ];
}
if (!$items) { echo json_encode(['ok' => false, 'error' => 'Alıntı çıkarılamadı (içerik kısa olabilir).']); exit; }

/* ── AI carousel slaytları (istenirse) — DeepSeek ile tutarlı slayt metinleri.
   Post başına WP option'da önbelleklenir → tekrar üretimde bedava/anında. ── */
if (($_POST['ai_slides'] ?? '') === '1') {
    require_once __DIR__ . '/_proto.php';
    $sc = get_option('tls_carousel_slides', []); if (!is_array($sc)) $sc = [];
    $need_i = []; $prompts = [];
    foreach ($items as $i => $it) {
        $pid = (string) $it['post_id'];
        if (isset($sc[$pid]) && is_array($sc[$pid]) && count($sc[$pid]) >= 3) {
            $items[$i]['slides'] = $sc[$pid];
        } else {
            $p = get_post($it['post_id']);
            $need_i[] = $i; $prompts[] = sg_slide_prompt($it['book'], $it['author'], $p ? $p->post_content : '');
        }
    }
    if ($prompts) {
        $res = function_exists('proto_deepseek_multi') ? proto_deepseek_multi($prompts, 380, null, 6) : [];
        foreach ($need_i as $k => $i) {
            $txt = is_array($res) && isset($res[$k]) ? $res[$k] : '';
            if ($txt === '' && function_exists('proto_ds')) $txt = proto_ds($prompts[$k], 380);
            $sl = sg_parse_slides($txt);   // 3-5 nokta → toplam 5-7 slayt; içerik azsa daha az
            if (count($sl) >= 2) { $items[$i]['slides'] = $sl; $sc[(string) $items[$i]['post_id']] = $sl; }
        }
        update_option('tls_carousel_slides', $sc, false);
    }
}

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
