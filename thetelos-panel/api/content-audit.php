<?php
/**
 * content-audit.php — Yayındaki yazılarda yapay zekâ artığı / bozuk içerik taraması.
 *
 * Çok parçalı üretimde metin, modelden parça parça geliyor ve birleştiriliyor.
 * Bu dikişlerde tipik kazalar oluyor: "Part 2 of 4" gibi teknik işaretler, "işte
 * devamı", "bir sonraki bölümde" gibi model konuşmaları, yarım kalan cümleler,
 * tekrarlanan başlıklar, HTML'e dönüşmemiş markdown kalıntıları.
 *
 * Bu araç YAYINDAKİ metni tarar ve bulguları önem sırasına göre listeler.
 * Hiçbir şeyi kendiliğinden değiştirmez — ne bulduğunu söyler, kararı sen ver.
 *
 * POST action=scan  (offset, limit, min_words) → o dilimin bulguları + sonraki offset
 * POST action=draft (ids)                      → seçilenleri yayından kaldır (taslak)
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

/* ── Kontroller ───────────────────────────────────────────────────────────
   Her kontrol: kod, önem (3=ağır, 2=orta, 1=hafif), açıklama, bulunan örnek.
   Ağır bulgular okuyucunun doğrudan göreceği kazalardır.                    */

/** Parça üretiminin teknik işaretleri metne sızmış mı? */
function ca_check_part_markers($text) {
    $pat = '/%%\s*PART|PART_END|\bPart\s+\d+\s+of\s+\d+\b|\bPART\s+\d+\/\d+|=== MULTI-PART/i';
    return preg_match($pat, $text, $m) ? $m[0] : '';
}

/** Model kendi süreci hakkında konuşmuş mu? ("işte devamı", "şimdi yazacağım") */
function ca_check_meta_talk($text) {
    $pats = [
        '/\bas an? (ai|language model|assistant)\b/i',
        '/\bi (?:will|shall|can) now (?:continue|proceed|begin|write|cover)\b/i',
        '/\blet me (?:continue|proceed|know if|explain that)\b/i',
        '/\bcontinuing (?:from|where) (?:the|we|it)\b/i',
        '/\bhere(?:\'s| is) (?:the|a|your) (?:summary|continuation|next part|final part)\b/i',
        '/\b(?:in|for) this part,? i\b/i',
        '/\bthe (?:next|remaining|final) part will\b/i',
        '/\b(?:word count|target length|approximately \d{3,5} words)\b/i',
        '/\bi hope this (?:helps|summary)\b/i',
        '/\blet me know if you\b/i',
        '/\bnote to (?:the )?(?:editor|reader|user)\b/i',
        '/\[(?:note|continued|already|to be continued)\b/i',
        '/\bper your (?:request|instructions)\b/i',
        '/\bas (?:requested|instructed)\b/i',
    ];
    foreach ($pats as $p) if (preg_match($p, $text, $m)) return trim($m[0]);
    return '';
}

/** Metin yarım mı bitmiş? (cümle ortasında ya da başlıkla) */
function ca_check_truncated($html) {
    $t = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($html)));
    if ($t === '') return 'içerik boş';
    // Son blok başlıksa: başlık atılmış ama altı yazılmamış
    if (preg_match('/<h[1-6][^>]*>.*?<\/h[1-6]>\s*$/is', trim($html))) return 'son blok başlık — altı boş';
    $last = mb_substr($t, -1, 1, 'UTF-8');
    if (mb_strpos('.!?"\'»)”’…', $last, 0, 'UTF-8') === false) {
        return '…' . mb_substr($t, -60, 60, 'UTF-8');
    }
    return '';
}

/** Aynı başlık iki kez yazılmış mı? (parça sınırında tekrar) */
function ca_check_dup_heading($html) {
    if (!preg_match_all('/<h([1-3])[^>]*>(.*?)<\/h\1>/is', $html, $m)) return '';
    $seen = [];
    foreach ($m[2] as $h) {
        $k = mb_strtolower(trim(wp_strip_all_tags($h)), 'UTF-8');
        if ($k === '') continue;
        if (isset($seen[$k])) return wp_strip_all_tags($h);
        $seen[$k] = 1;
    }
    return '';
}

/** Aynı paragraf iki kez geçmiş mi? (parça örtüşmesi) */
function ca_check_dup_para($html) {
    if (!preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $m)) return '';
    $seen = [];
    foreach ($m[1] as $p) {
        $t = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($p)));
        if (mb_strlen($t, 'UTF-8') < 160) continue;   // kısa paragraflar doğal olarak benzeşebilir
        $k = mb_substr($t, 0, 160, 'UTF-8');
        if (isset($seen[$k])) return mb_substr($t, 0, 90, 'UTF-8') . '…';
        $seen[$k] = 1;
    }
    return '';
}

/** HTML'e dönüşmemiş markdown kalıntısı var mı? */
function ca_check_md_leak($html) {
    $t = wp_strip_all_tags($html);
    if (preg_match('/(^|\n)\s*#{1,4}\s+\S/', $t, $m))   return trim($m[0]);
    if (preg_match('/\*\*[^*\n]{3,80}\*\*/', $t, $m))    return trim($m[0]);
    if (preg_match('/(^|\n)\s*---\s*(\n|$)/', $t))       return '--- (yatay çizgi)';
    return '';
}

/** Yasak kapanış: prompt "özet paragrafıyla bitirme" diyor. */
function ca_check_wrapup($html) {
    $t = trim(wp_strip_all_tags($html));
    $tail = mb_substr($t, -400, 400, 'UTF-8');
    if (preg_match('/\b(in conclusion|to conclude|in summary|to sum up|overall,? this book)\b/i', $tail, $m)) {
        return trim($m[0]);
    }
    return '';
}

function ca_word_count($html) {
    return str_word_count(wp_strip_all_tags($html), 0);
}

/* ── Tara ── */
$action = $_POST['action'] ?? '';

if ($action === 'scan') {
    global $wpdb;
    $offset    = max(0, (int)($_POST['offset'] ?? 0));
    $limit     = max(10, min(200, (int)($_POST['limit'] ?? 100)));
    $min_words = max(0, (int)($_POST['min_words'] ?? 1500));

    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts}
          WHERE post_type IN ('post','analysis') AND post_status = 'publish'"
    );

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT ID, post_title, post_content, post_date
           FROM {$wpdb->posts}
          WHERE post_type IN ('post','analysis') AND post_status = 'publish'
       ORDER BY post_date DESC
          LIMIT %d OFFSET %d", $limit, $offset
    ) );

    $findings = [];
    foreach ($rows as $r) {
        $html  = (string) $r->post_content;
        $words = ca_word_count($html);
        $flags = [];

        if ($s = ca_check_part_markers($html)) $flags[] = ['code'=>'part_marker', 'sev'=>3, 'label'=>'Parça işareti sızmış',        'sample'=>$s];
        if ($s = ca_check_meta_talk($html))    $flags[] = ['code'=>'meta_talk',   'sev'=>3, 'label'=>'Model kendi süreciyle konuşmuş','sample'=>$s];
        if ($s = ca_check_truncated($html))    $flags[] = ['code'=>'truncated',   'sev'=>3, 'label'=>'Yarım bitmiş',                 'sample'=>$s];
        if ($s = ca_check_dup_para($html))     $flags[] = ['code'=>'dup_para',    'sev'=>2, 'label'=>'Paragraf tekrarı',             'sample'=>$s];
        if ($s = ca_check_dup_heading($html))  $flags[] = ['code'=>'dup_heading', 'sev'=>2, 'label'=>'Başlık tekrarı',               'sample'=>$s];
        if ($s = ca_check_md_leak($html))      $flags[] = ['code'=>'md_leak',     'sev'=>2, 'label'=>'Markdown kalıntısı',           'sample'=>$s];
        if ($min_words && $words < $min_words) $flags[] = ['code'=>'short',       'sev'=>2, 'label'=>'Hedeften kısa',                'sample'=>$words . ' kelime'];
        if ($s = ca_check_wrapup($html))       $flags[] = ['code'=>'wrapup',      'sev'=>1, 'label'=>'Özet paragrafıyla bitmiş',     'sample'=>$s];

        $h3 = preg_match_all('/<h3[^>]*>/i', $html);
        if ($h3 < 3) $flags[] = ['code'=>'few_sections', 'sev'=>1, 'label'=>'Bölüm sayısı az', 'sample'=>$h3 . ' bölüm'];

        if (!$flags) continue;

        usort($flags, function($a, $b) { return $b['sev'] <=> $a['sev']; });
        $findings[] = [
            'id'    => (int) $r->ID,
            'title' => $r->post_title,
            'date'  => substr((string)$r->post_date, 0, 10),
            'words' => $words,
            'sev'   => $flags[0]['sev'],
            'link'  => get_permalink($r->ID),
            'edit'  => admin_url('post.php?post=' . (int)$r->ID . '&action=edit'),
            'flags' => $flags,
        ];
    }

    echo json_encode([
        'ok'       => true,
        'total'    => $total,
        'scanned'  => count($rows),
        'next'     => (count($rows) < $limit) ? -1 : $offset + count($rows),
        'findings' => $findings,
    ]);
    exit;
}

/* ── Seçilenleri yayından kaldır ── */
if ($action === 'draft') {
    $ids  = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
    $done = 0;
    foreach ($ids as $id) {
        if (get_post_status($id) !== 'publish') continue;
        wp_update_post(['ID' => $id, 'post_status' => 'draft']);
        $done++;
    }
    echo json_encode(['ok' => true, 'drafted' => $done]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'unknown_action']);
