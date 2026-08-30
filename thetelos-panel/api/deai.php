<?php
/**
 * deai.php — Claude yazılarındaki "AI itirafı/hitabı" cümlelerini AI editörle
 * ayıklar. AYRI DOSYA: api/content-audit.php'nin OPcache'te eski kalması
 * ihtimaline karşı temiz bir uç (yeni dosya → taze derlenir).
 *
 * POST action=run  items=JSON[id|url,…]  model=(''|opus)  → her öğe için sonuç
 *      action=undo  ids=CSV                                → yedekten geri yükle
 *
 * Editör-AI YALNIZ kendinden/AI'dan bahseden veya okuyucuya hitap eden
 * cümleleri siler; gerisini AYNEN korur. Yeniden yazmaz, kaynak aramaz.
 * Doğrulama geçmezse (boş/red/hâlâ-meta/uzunluk şüpheli) YAZMAZ. Yedek meta
 * '_tls_audit_backup' → content-audit.php'deki 'undo' ile de geri alınır.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
@set_time_limit(300);
@ini_set('memory_limit', '512M');
@ini_set('pcre.backtrack_limit', '2000000');
ignore_user_abort(true);

ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();

require_once __DIR__ . '/_checks.php';      // ca_check_meta_talk / ca_check_refusal
require_once __DIR__ . '/_anthropic.php';   // tls_claude* / tls_strip_ai_meta_html

/* Yedek (content-audit.php ile aynı meta anahtarı → oradaki 'undo' geri alır). */
function deai_backup($id, $old) {
    if (get_post_meta($id, '_tls_audit_backup', true) === '') {
        update_post_meta($id, '_tls_audit_backup', $old);
        update_post_meta($id, '_tls_audit_backup_at', time());
    }
}

/* id (sayı) VEYA url/slug → post id. */
function deai_resolve($item) {
    $item = trim((string) $item);
    if ($item === '') return 0;
    if (ctype_digit($item)) return (int) $item;
    if (preg_match('/[?&]p=(\d+)/', $item, $m)) return (int) $m[1];
    $url = (stripos($item, 'http') === 0) ? $item : (rtrim(WP_URL, '/') . '/' . ltrim($item, '/'));
    $pid = (int) url_to_postid($url);
    if ($pid) return $pid;
    $path = trim(parse_url($url, PHP_URL_PATH) ?: '', '/');
    $slug = substr(strrchr('/' . $path, '/'), 1);
    if ($slug !== '') {
        $p = get_page_by_path($slug, OBJECT, ['post', 'analysis']);
        if ($p) return (int) $p->ID;
    }
    return 0;
}

/* Tek yazıyı AI editörle temizle. */
function deai_edit($id, $model = '') {
    $p = get_post($id);
    if (!$p || $p->post_type === 'revision') return ['error' => 'yazı yok'];
    $old = (string) $p->post_content;
    if (trim($old) === '')                return ['skip' => true, 'error' => 'boş'];
    if (ca_check_meta_talk($old) === '')  return ['clean' => true];      // zaten temiz
    if (!tls_anthropic_ready())           return ['error' => 'ANTHROPIC_KEY yok'];

    $sys = "You are a meticulous copy editor for a published book-reference website. "
         . "You are given the HTML body of one article. Your ONLY task: DELETE every "
         . "sentence or passage where the writer talks about THEMSELVES, their own "
         . "knowledge, confidence or limits, or ADDRESSES the reader as an author/AI — "
         . "for example: 'A note on the limits of what I can say', 'I can reliably "
         . "identify…', 'I do not have secure knowledge…', 'I have deliberately not "
         . "supplied…', 'as an AI', 'I cannot…', 'to the best of my knowledge'. Also "
         . "delete a heading or paragraph that exists ONLY to host such text.\n\n"
         . "STRICT RULES:\n"
         . "1. Keep ALL other text EXACTLY as written — do NOT reword, summarize, "
         . "translate, reorder, add, or 'improve' anything.\n"
         . "2. Preserve the HTML tags and structure of the kept text verbatim.\n"
         . "3. If a deletion leaves a rough seam, make the SMALLEST possible change to "
         . "the surrounding words so it still reads naturally — nothing more.\n"
         . "4. Never add new facts, opinions, or notes of your own.\n"
         . "Output ONLY the cleaned HTML body, nothing else (no code fences, no "
         . "commentary).";
    $user = "Clean this article body:\n\n" . $old;

    $otok = (int) round(mb_strlen($old) / 3);
    $mtok = min(16000, max(2000, (int) round($otok * 1.3)));
    $r = tls_claude($sys, $user, [
        'model'       => $model ?: tls_claude_quality_model(),
        'max_tokens'  => $mtok,
        'temperature' => 0.0,
        'timeout'     => 300,
        'retries'     => 2,
    ]);
    if (empty($r['ok'])) return ['error' => $r['error'] ?? 'AI hata'];

    $new = trim((string) $r['text']);
    $new = preg_replace('/^```[a-z]*\s*/i', '', $new);
    $new = preg_replace('/\s*```$/', '', $new);
    $new = trim($new);
    if (function_exists('tls_strip_ai_meta_html')) $new = tls_strip_ai_meta_html($new);

    if ($new === '')                        return ['error' => 'AI boş döndü'];
    if (ca_check_refusal($new) !== '')      return ['error' => 'AI reddetti'];
    if (ca_check_meta_talk($new) !== '')    return ['error' => 'meta hâlâ var (yazılmadı)'];
    $ol = mb_strlen(wp_strip_all_tags($old));
    $nl = mb_strlen(wp_strip_all_tags($new));
    if ($nl < $ol * 0.5 || $nl > $ol * 1.1) return ['error' => "uzunluk şüpheli ($ol→$nl)"];
    if ($new === $old)                      return ['clean' => true];

    deai_backup($id, $old);
    wp_update_post(['ID' => $id, 'post_content' => $new]);
    do_action('litespeed_purge_post', $id);
    return ['changed' => true, 'words' => str_word_count(wp_strip_all_tags($new))];
}

$action = $_POST['action'] ?? 'run';

if ($action === 'undo') {
    $ids = array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? ''))));
    $restored = 0; $samples = [];
    foreach ($ids as $id) {
        $bak = get_post_meta($id, '_tls_audit_backup', true);
        $cur = get_post_field('post_content', $id);
        if (is_string($bak) && $bak !== '' && $bak !== $cur) {
            wp_update_post(['ID' => $id, 'post_content' => $bak]);
            do_action('litespeed_purge_post', $id);
            delete_post_meta($id, '_tls_audit_backup');
            delete_post_meta($id, '_tls_audit_backup_at');
            $restored++;
            if (count($samples) < 3) $samples[] = get_the_title($id);
        }
    }
    echo json_encode(['ok' => true, 'restored' => $restored, 'samples' => $samples]);
    exit;
}

// action=run
$items = json_decode((string)($_POST['items'] ?? ''), true);
if (!is_array($items)) $items = array_filter(array_map('trim', explode(',', (string)($_POST['ids'] ?? ''))));
$items = array_slice(array_values($items), 0, 25);
$mreq  = trim((string)($_POST['model'] ?? ''));
$model = ($mreq === 'opus') ? tls_claude_best_model() : ($mreq !== '' ? $mreq : '');

$results = []; $changed = 0; $clean = 0; $failed = 0; $seen = [];
foreach ($items as $it) {
    $ref = is_string($it) ? $it : (string) $it;
    $id  = deai_resolve($it);
    if (!$id) { $failed++; $results[] = ['ref'=>$ref, 'status'=>'atlandı', 'error'=>'yazı bulunamadı']; continue; }
    if (isset($seen[$id])) continue;
    $seen[$id] = 1;
    $res = deai_edit($id, $model);
    $row = ['id'=>$id, 'title'=>get_the_title($id) ?: ('#'.$id)];
    if (!empty($res['changed']))   { $changed++; $row['status']='temizlendi'; $row['words']=$res['words']??0; }
    elseif (!empty($res['clean'])) { $clean++;   $row['status']='zaten temiz'; }
    else                           { $failed++;  $row['status']='atlandı'; $row['error']=$res['error']??'?'; }
    $results[] = $row;
}
echo json_encode(['ok'=>true, 'v'=>3, 'got'=>count($items), 'results'=>$results,
                  'changed'=>$changed, 'clean'=>$clean, 'failed'=>$failed]);
