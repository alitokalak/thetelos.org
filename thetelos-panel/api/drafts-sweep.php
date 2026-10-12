<?php
/**
 * drafts-sweep.php — TASLAK (draft) kitap postlarını topluca denetle & temizle.
 *
 * NEDEN: Toplu üretimde yayın kapısı, şüpheli/kusurlu içeriği YAYINLAMAYIP
 * taslakta bekletiyor (koruma). Zamanla çok taslak birikiyor. Bu araç onları
 * tek listede gösterir, Claude ile "bu içerik doğru kişi/eser hakkında mı,
 * yayınlanabilir mi?" diye denetler ve toplu karar verdirir:
 *   - good  → Yayınla
 *   - fix   → Claude küçük hataları düzeltti → Düzelt & Yayınla
 *   - wrong → farklı kişi/eser (yanlış kaynak) → Sil
 *   - unsure→ emin değil → elle bak
 *
 * POST action=scan    (offset,limit)        → taslakları sayfa sayfa + sebep
 * POST action=check   (id)                  → Claude denetimi (good/fix/wrong/unsure)
 * POST action=publish (id, apply_fix)       → (ops. düzeltmeyi uygula) + yayınla
 * POST action=delete  (id)                  → çöpe at
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-LiteSpeed-Cache-Control: no-cache');
@set_time_limit(180);

ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();
require_once __DIR__ . '/_anthropic.php';

/* pid → en son işaretlenen sorun sebebi (jsonl'den). */
function ds_reason_map() {
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    $f = dirname(__DIR__) . '/jobs/rewrite-problems.jsonl';
    if (is_file($f) && ($h = @fopen($f, 'r'))) {
        while (($line = fgets($h)) !== false) {
            $r = json_decode(trim($line), true);
            if (!is_array($r) || empty($r['pid'])) continue;
            $map[(int) $r['pid']] = trim(((string) ($r['reason'] ?? '')) . (empty($r['detail']) ? '' : ' · ' . $r['detail']));
        }
        fclose($h);
    }
    return $map;
}

/* Postun kitap adı + yazarı (başlıktan "– Yazar" ekini temizle). */
function ds_book_author($pid) {
    $title = (string) get_the_title($pid);
    $terms = wp_get_object_terms($pid, 'authors', ['fields' => 'names']);
    $author = (!is_wp_error($terms) && $terms) ? (string) $terms[0] : '';
    $book = $title;
    if ($author !== '') {
        // "Kitap – Yazar" / "Kitap - Yazar" sonekini at.
        $book = preg_replace('/\s*[–-]\s*' . preg_quote($author, '/') . '\s*$/u', '', $title);
    }
    return [trim($book) ?: $title, $author];
}

$action = $_POST['action'] ?? '';

/* ── Tara ── */
if ($action === 'scan') {
    $offset = max(0, (int) ($_POST['offset'] ?? 0));
    $limit  = min(60, max(10, (int) ($_POST['limit'] ?? 30)));
    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'draft',
        'posts_per_page' => $limit,
        'offset'         => $offset,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
    ]);
    $reasons = ds_reason_map();
    $rows = [];
    foreach ($q->posts as $pid) {
        [$book, $author] = ds_book_author($pid);
        $words = str_word_count(wp_strip_all_tags((string) get_post_field('post_content', $pid)));
        $rows[] = [
            'id'      => (int) $pid,
            'title'   => (string) get_the_title($pid),
            'author'  => $author,
            'words'   => (int) $words,
            'reason'  => $reasons[(int) $pid] ?? '',
            'verdict' => (string) get_post_meta($pid, '_tls_sweep_verdict', true),   // kayıtlı denetim (varsa)
            'vnote'   => (string) get_post_meta($pid, '_tls_sweep_note', true),
            'link'    => get_permalink($pid),
            'edit'    => rtrim(WP_URL, '/') . '/wp-admin/post.php?post=' . (int) $pid . '&action=edit',
        ];
    }
    echo json_encode([
        'ok' => true, 'rows' => $rows,
        'scanned' => count($q->posts),
        'next_offset' => $offset + count($q->posts),
        'done' => count($q->posts) < $limit,
        'total' => (int) $q->found_posts,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Denetle (Claude) ──
   Verdict + not KALICI olarak post meta'ya yazılır → sayfa yenilense/oturum
   dolsa bile denetim KAYBOLMAZ, tekrar para harcanmaz. */
if ($action === 'check') {
    $id = (int) ($_POST['id'] ?? 0);
    $p  = $id ? get_post($id) : null;
    if (!$p) { echo json_encode(['ok' => false, 'error' => 'post bulunamadı']); exit; }
    $save = function ($verdict, $note) use ($id) {
        update_post_meta($id, '_tls_sweep_verdict', $verdict);
        update_post_meta($id, '_tls_sweep_note', mb_substr((string) $note, 0, 200));
        echo json_encode(['ok' => true, 'verdict' => $verdict, 'note' => $note], JSON_UNESCAPED_UNICODE); exit;
    };
    [$book, $author] = ds_book_author($id);
    $html = (string) $p->post_content;
    if (str_word_count(wp_strip_all_tags($html)) < 40) { $save('unsure', 'çok kısa/boş → elle bak'); }
    // Kaynaksız bilgi-denetimi: eseri/ kişiyi Claude bilir mi, doğru mu?
    $acc = tls_claude_accuracy($book, $author, $html, ['trust_source' => false]);
    if (empty($acc['ok'])) { echo json_encode(['ok' => false, 'error' => $acc['error'] ?? 'denetim hatası']); exit; }
    $act = $acc['action'] ?? '';
    if ($act === 'block') {
        $save(!empty($acc['wrongsubject']) ? 'wrong' : 'unsure', $acc['error'] ?? '');
    }
    if ($act === 'fixed' && trim((string) $acc['text']) !== '') {
        update_post_meta($id, '_tls_sweep_fixed', $acc['text']);   // düzeltilmiş hali sakla
        $save('fix', 'Claude küçük hataları düzeltti');
    }
    // ok / unknown → yayınlanabilir.
    delete_post_meta($id, '_tls_sweep_fixed');
    $save('good', '');
}

/* ── Yayınla (ops. düzeltmeyi uygula) ── */
if ($action === 'publish') {
    $id = (int) ($_POST['id'] ?? 0);
    $p  = $id ? get_post($id) : null;
    if (!$p) { echo json_encode(['ok' => false, 'error' => 'post bulunamadı']); exit; }
    if (!empty($_POST['apply_fix'])) {
        $fixed = get_post_meta($id, '_tls_sweep_fixed', true);
        if (is_string($fixed) && trim($fixed) !== '') {
            // Markdown gelebilir → bw_md2html ile HTML'e çevir (varsa).
            require_once __DIR__ . '/_content-format.php';
            $html = function_exists('bw_md2html') ? bw_md2html($fixed) : $fixed;
            wp_update_post(['ID' => $id, 'post_content' => $html]);
        }
    }
    delete_post_meta($id, '_tls_sweep_fixed');
    delete_post_meta($id, '_tls_sweep_verdict'); delete_post_meta($id, '_tls_sweep_note');
    $u = wp_update_post(['ID' => $id, 'post_status' => 'publish'], true);
    if (is_wp_error($u)) { echo json_encode(['ok' => false, 'error' => $u->get_error_message()]); exit; }
    echo json_encode(['ok' => true, 'link' => get_permalink($id)]); exit;
}

/* ── Sil (çöpe at) ── */
if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id || !get_post($id)) { echo json_encode(['ok' => false, 'error' => 'post bulunamadı']); exit; }
    delete_post_meta($id, '_tls_sweep_fixed');
    delete_post_meta($id, '_tls_sweep_verdict'); delete_post_meta($id, '_tls_sweep_note');
    $r = wp_trash_post($id);   // kalıcı silme değil → çöpten geri alınabilir
    if (!$r) { echo json_encode(['ok' => false, 'error' => 'silinemedi']); exit; }
    echo json_encode(['ok' => true]); exit;
}

echo json_encode(['ok' => false, 'error' => 'bad action']);
