<?php
/**
 * author-split.php — AYNI İSİMDEKİ FARKLI YAZARLARI AYIR.
 *
 * SORUN: WordPress yazarı yalnız İSİM METNİNE göre grupluyor. "William Gilbert"
 * adıyla 5 ayrı gerçek kişi (fizikçi, Victorian romancı, denizci yazar…) tek
 * terime düşüyor → biri hakkındaki bilgi diğerine atfediliyor.
 *
 * ÇÖZÜM: Kitabın kendisi yazarı söyler. Bir yazar teriminin TÜM kitap
 * başlıklarını Claude'a (web aramalı) verip "bunları GERÇEK yazarına göre
 * grupla" dedirtiyoruz. Kullanıcı önizlemeyi onaylayınca her grup AYRI yazar
 * terimine bölünür, kitaplar doğru kişiye taşınır. Her şey geri alınabilir.
 *
 * POST action=authors  (q)                  → isim arayıp yazar terimlerini listele
 * POST action=analyze  (term_id)            → kitapları Claude+web ile grupla (önizleme)
 * POST action=apply     (term_id, groups)   → grupları ayrı terimlere böl + yeniden ata
 * POST action=restore   (term_id)           → son ayırmayı geri al (yedekten)
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
require_once __DIR__ . '/_anthropic.php';
require_once __DIR__ . '/_verify.php';   // tv_json

define('AS_TAX', 'authors');
define('AS_MAX_BOOKS', 150);   // tek analizde en çok kaç kitap

/* Bir yazar teriminin kitaplarını (ID + başlık) getir. */
function as_term_books($term_id, $max = AS_MAX_BOOKS) {
    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => $max,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => false,
        'fields'         => 'ids',
        'tax_query'      => [[
            'taxonomy' => AS_TAX, 'field' => 'term_id', 'terms' => (int) $term_id,
        ]],
    ]);
    $books = [];
    foreach ($q->posts as $pid) {
        $books[] = ['id' => (int) $pid, 'title' => (string) get_the_title($pid)];
    }
    $total = (int) $q->found_posts;
    wp_reset_postdata();
    return ['books' => $books, 'total' => $total];
}

/* Kitap başlık listesini kümelet (Claude + web araması). */
function as_cluster($name, $books) {
    if (!tls_anthropic_ready()) return ['ok' => false, 'error' => 'ANTHROPIC_KEY yok'];
    $lines = '';
    foreach ($books as $i => $b) { $lines .= ($i + 1) . '. ' . $b['title'] . "\n"; }

    $system =
        "You are disambiguating authors for a books website. Many books have been filed under a SINGLE "
      . "author name, but a name is often shared by several DIFFERENT real people. Using the book titles, "
      . "your own knowledge, and web search, group the books by their ACTUAL author.\n\n"
      . "Use the web_search tool to verify who actually wrote each title — do not rely on the shared name. "
      . "Different real people usually write about clearly different subjects (e.g. a physicist's books are "
      . "about magnetism; a novelist's are fiction; a maritime writer's are about ships).\n\n"
      . "For EACH distinct real person, produce a group with:\n"
      . "- \"label\": the author's name followed by a SHORT disambiguator in parentheses — life dates if you "
      . "know them, else a brief role/field. Examples: \"William Gilbert (1544-1603, physicist)\", "
      . "\"William Gilbert (Victorian novelist)\". Keep the base name EXACTLY as given.\n"
      . "- \"who\": one short factual sentence identifying this person.\n"
      . "- \"books\": the list of book NUMBERS (from the numbered list) that belong to this person.\n\n"
      . "Put any book you cannot confidently assign into a single group with label \"Uncertain\" and who \"\". "
      . "Every book number must appear in exactly one group. Return ONLY JSON:\n"
      . "{\"groups\":[{\"label\":\"...\",\"who\":\"...\",\"books\":[1,3,5]}, ...]}";

    $user = "Author name as filed: \"{$name}\"\n\nBooks:\n{$lines}\nReturn the grouping JSON.";

    $r = tls_claude($system, $user, [
        'model'      => tls_claude_quality_model(),
        'max_tokens' => 4000,
        'timeout'    => 300,
        'retries'    => 2,
        'tools'      => [['type' => (defined('ANTHROPIC_WEBSEARCH_TOOL') ? ANTHROPIC_WEBSEARCH_TOOL : 'web_search_20250305'),
                          'name' => 'web_search', 'max_uses' => 8]],
        'max_turns'  => 10,
        'batch'      => false,
    ]);
    if (empty($r['ok'])) return ['ok' => false, 'error' => $r['error'] ?? 'claude hata'];
    $j = tv_json($r['text']);
    if (!is_array($j) || empty($j['groups']) || !is_array($j['groups'])) {
        return ['ok' => false, 'error' => 'kümeleme yanıtı okunamadı: ' . mb_substr(trim((string) $r['text']), 0, 160)];
    }
    // Grupları temizle: index → book id eşlemesi.
    $n = count($books);
    $groups = [];
    $seen = [];
    foreach ($j['groups'] as $g) {
        if (!is_array($g)) continue;
        $label = trim((string) ($g['label'] ?? ''));
        if ($label === '') continue;
        $ids = [];
        foreach ((array) ($g['books'] ?? []) as $num) {
            $idx = (int) $num - 1;
            if ($idx < 0 || $idx >= $n || isset($seen[$idx])) continue;
            $seen[$idx] = true;
            $ids[] = $books[$idx]['id'];
        }
        if (!$ids) continue;
        $groups[] = [
            'label' => mb_substr($label, 0, 180),
            'who'   => mb_substr(trim((string) ($g['who'] ?? '')), 0, 300),
            'ids'   => $ids,
        ];
    }
    // Atanmayan kitaplar → "Belirsiz"e düşür (kaybolmasın).
    $left = [];
    foreach ($books as $i => $b) { if (!isset($seen[$i])) $left[] = $b['id']; }
    if ($left) $groups[] = ['label' => 'Uncertain', 'who' => '', 'ids' => $left];
    return ['ok' => true, 'groups' => $groups];
}

$action = $_POST['action'] ?? '';

/* ── Yazar ara ── */
if ($action === 'authors') {
    $q = trim((string) ($_POST['q'] ?? ''));
    $args = ['taxonomy' => AS_TAX, 'hide_empty' => false, 'number' => 40, 'orderby' => 'count', 'order' => 'DESC'];
    if ($q !== '') { $args['search'] = $q; }
    $terms = get_terms($args);
    if (is_wp_error($terms)) { echo json_encode(['ok' => false, 'error' => $terms->get_error_message()]); exit; }
    $rows = [];
    foreach ($terms as $t) {
        $rows[] = ['id' => (int) $t->term_id, 'name' => $t->name, 'count' => (int) $t->count];
    }
    echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Analiz (kümele) ── */
if ($action === 'analyze') {
    $id = (int) ($_POST['term_id'] ?? 0);
    $t  = $id ? get_term($id, AS_TAX) : null;
    if (!$t || is_wp_error($t)) { echo json_encode(['ok' => false, 'error' => 'yazar bulunamadı']); exit; }
    $bk = as_term_books($id);
    if (!$bk['books']) { echo json_encode(['ok' => false, 'error' => 'bu yazarda kitap yok']); exit; }
    $cl = as_cluster($t->name, $bk['books']);
    if (empty($cl['ok'])) { echo json_encode(['ok' => false, 'error' => $cl['error']]); exit; }
    // Başlıkları da döndür (önizlemede göstermek için).
    $titles = [];
    foreach ($bk['books'] as $b) { $titles[$b['id']] = $b['title']; }
    echo json_encode([
        'ok'       => true,
        'name'     => $t->name,
        'analyzed' => count($bk['books']),
        'total'    => $bk['total'],
        'groups'   => $cl['groups'],
        'titles'   => $titles,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Uygula (böl + yeniden ata) ──
   groups: JSON [{label, ids:[postId,...]}, ...]. Bir grup ESKİ terimde kalır
   (en çok kitaplısı ya da base-isimle birebir eşleşen); diğerleri yeni terime
   taşınır. "Uncertain" grubu eski terimde bırakılır (dokunulmaz). */
if ($action === 'apply') {
    $id = (int) ($_POST['term_id'] ?? 0);
    $t  = $id ? get_term($id, AS_TAX) : null;
    if (!$t || is_wp_error($t)) { echo json_encode(['ok' => false, 'error' => 'yazar bulunamadı']); exit; }
    $groups = json_decode((string) ($_POST['groups'] ?? ''), true);
    if (!is_array($groups) || !$groups) { echo json_encode(['ok' => false, 'error' => 'grup verisi yok']); exit; }

    $base = $t->name;

    // Hangi grup eski terimde kalsın? "Uncertain" + tam base-isimli grup kalır;
    // yoksa EN ÇOK kitaplı grup kalır. Diğerleri yeni terim.
    $keep_idx = -1; $max_c = -1;
    foreach ($groups as $i => $g) {
        $lbl = trim((string) ($g['label'] ?? ''));
        $c   = count((array) ($g['ids'] ?? []));
        if (strcasecmp($lbl, $base) === 0) { $keep_idx = $i; break; }
        if ($c > $max_c) { $max_c = $c; $keep_idx = $i; }
    }

    $backup = [];   // post_id => [eski term_id,...]  (geri alma için)
    $created = [];   // yeni terim özetleri
    $moved = 0;
    $debug = ['recv_groups' => count($groups), 'keep_idx' => $keep_idx, 'base' => $base, 'tax' => AS_TAX, 'groups' => []];

    foreach ($groups as $i => $g) {
        $lbl = trim((string) ($g['label'] ?? ''));
        $ids = array_values(array_filter(array_map('intval', (array) ($g['ids'] ?? []))));
        $dg  = ['i' => $i, 'label' => $lbl, 'ids' => count($ids)];
        if ($lbl === '' || !$ids) { $dg['skip'] = 'bos-label-veya-id'; $debug['groups'][] = $dg; continue; }
        if ($i === $keep_idx) { $dg['skip'] = 'keep'; $debug['groups'][] = $dg; continue; }
        if (strcasecmp($lbl, 'Uncertain') === 0 || strcasecmp($lbl, 'Belirsiz') === 0) { $dg['skip'] = 'uncertain'; $debug['groups'][] = $dg; continue; }

        // Hedef terim: varsa bul, yoksa oluştur.
        $existing = get_term_by('name', $lbl, AS_TAX);
        if ($existing && !is_wp_error($existing)) {
            $new_tid = (int) $existing->term_id; $dg['term'] = 'bulundu';
        } else {
            $res = wp_insert_term($lbl, AS_TAX);
            if (is_wp_error($res)) { $dg['err'] = 'insert_term: ' . $res->get_error_message(); $debug['groups'][] = $dg; continue; }
            $new_tid = (int) $res['term_id']; $dg['term'] = 'olusturuldu';
        }
        $dg['term_id'] = $new_tid;
        if ($new_tid === (int) $id) { $dg['skip'] = 'ayni-terim'; $debug['groups'][] = $dg; continue; }

        $gmoved = 0; $gerr = '';
        foreach ($ids as $pid) {
            $cur = wp_get_object_terms($pid, AS_TAX, ['fields' => 'ids']);
            if (is_wp_error($cur)) { $gerr = 'get_obj_terms: ' . $cur->get_error_message(); continue; }
            $backup[$pid] = array_map('intval', (array) $cur);
            // Bu yazar terimini çıkar, yeni terimi ekle (diğer yazarlara dokunma).
            $want = array_values(array_diff(array_map('intval', (array) $cur), [(int) $id]));
            $want[] = $new_tid;
            $sr = wp_set_object_terms($pid, array_values(array_unique($want)), AS_TAX, false);
            if (is_wp_error($sr)) { $gerr = 'set_obj_terms: ' . $sr->get_error_message(); continue; }
            $moved++; $gmoved++;
        }
        $dg['moved'] = $gmoved; if ($gerr) $dg['err'] = $gerr;
        $debug['groups'][] = $dg;
        $created[] = ['label' => $lbl, 'term_id' => $new_tid, 'count' => count($ids)];
    }

    // Yedeği sakla (son işlemi geri almak için).
    if ($backup) {
        update_option('tls_authsplit_bak_' . $id, ['at' => time(), 'base' => $base, 'map' => $backup], false);
    }

    echo json_encode([
        'ok'      => true,
        'moved'   => $moved,
        'created' => $created,
        'kept'    => $base,
        'can_restore' => !empty($backup),
        'debug'   => $debug,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Geri al (son ayırmayı) ── */
if ($action === 'restore') {
    $id  = (int) ($_POST['term_id'] ?? 0);
    $bak = get_option('tls_authsplit_bak_' . $id);
    if (!is_array($bak) || empty($bak['map'])) { echo json_encode(['ok' => false, 'error' => 'geri alınacak yedek yok']); exit; }
    $restored = 0;
    foreach ($bak['map'] as $pid => $terms) {
        $terms = array_values(array_filter(array_map('intval', (array) $terms)));
        if (!$terms) continue;
        wp_set_object_terms((int) $pid, $terms, AS_TAX, false);
        $restored++;
    }
    delete_option('tls_authsplit_bak_' . $id);
    echo json_encode(['ok' => true, 'restored' => $restored], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'bad action']);
