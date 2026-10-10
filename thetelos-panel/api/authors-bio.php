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
 * POST action=fix   (id)        → Claude ile üret + KAYDET (toplu düzeltme için)
 * POST action=save  (id,bio)    → bio'yu terim açıklamasına yaz
 *
 * BİYOGRAFİYİ CLAUDE YAZAR (yanlış yazar karışmasını önlemek için):
 *   - Yazarın KENDİ kitapları modele bağlam olarak verilir → doğru kişi tespiti.
 *   - Claude kişiyi GÜVENLE tanıyamazsa tek kelime UNKNOWN döner → o yazar
 *     ATLANIR (yanlış/uydurma bio yazmaktansa mevcut hali korunur).
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

/* Yazarın KENDİ kitaplarından birkaç başlık topla — modele "bu yazar kim"
   bağlamı için. Doğru kişiyi ayırt etmenin en güçlü ipucu budur. */
function ab_author_titles($term_id, $max = 8) {
    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $max,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
        'fields'         => 'ids',
        'tax_query'      => [[
            'taxonomy' => 'authors',
            'field'    => 'term_id',
            'terms'    => (int) $term_id,
        ]],
    ]);
    $titles = [];
    foreach ($q->posts as $pid) {
        $t = trim(get_the_title($pid));
        if ($t !== '') $titles[] = $t;
    }
    wp_reset_postdata();
    return $titles;
}

/* Claude ile kısa, OLGUSAL, DOĞRU KİŞİYE ait bio üret.
   @return ['ok'=>bool,'bio'=>string,'unknown'=>bool,'error'=>string]
   unknown=true → Claude kişiyi güvenle tanıyamadı; ÜSTÜNE YAZMA, atla. */
function ab_generate_bio($author, $term_id = 0) {
    if (!tls_anthropic_ready()) {
        return ['ok' => false, 'unknown' => false, 'bio' => '', 'error' => 'ANTHROPIC_KEY config.php’de yok'];
    }
    $author = trim((string) $author);
    if ($author === '') return ['ok' => false, 'unknown' => false, 'bio' => '', 'error' => 'yazar adı boş'];

    // Yazarın kendi kitapları → doğru kişiyi tespit için bağlam.
    $titles = $term_id ? ab_author_titles($term_id) : [];
    $books_ctx = $titles
        ? "On our site, books credited to this author include:\n- " . implode("\n- ", array_slice($titles, 0, 8)) . "\n\n"
          . "Use these titles to make sure you identify the CORRECT person who wrote them. "
        : "";

    $system =
        "You write short, factual, encyclopedic author biographies for a philosophy and "
      . "literature website. Everything you write must be TRUE and about the EXACT person "
      . "named — the real author of the listed works.\n\n"
      . "HARD RULES:\n"
      . "1. Identify the RIGHT person. If several people share this name, or you cannot "
      . "confidently tell which real person this is, output EXACTLY the single word UNKNOWN "
      . "and nothing else. Never guess, never blend two different people.\n"
      . "2. Never invent facts. Do not make up birth/death dates, nationalities, works, or "
      . "titles you are not sure of. If you are not certain of a specific, leave it out. If "
      . "you can identify the person but know little for certain, write only the few facts "
      . "you are sure of (even one sentence) rather than padding with guesses.\n"
      . "3. BE CONSERVATIVE AND PRECISE — this is where subtle errors creep in:\n"
      . "   a. Do NOT label the GENRE/FORM of a specific work (novel, memoir, treatise, essay, "
      . "poem collection…) unless you are certain of it. When in doubt, just name the work "
      . "without classifying it (e.g. 'his book X' not 'his novel X'). Misclassifying a work's "
      . "genre is a factual error.\n"
      . "   b. Do NOT attach the author to a philosophical/literary MOVEMENT or SCHOOL "
      . "(existentialism, romanticism, stoicism…) unless it is textbook-standard and you are "
      . "certain. Prefer neutral, verifiable description over interpretive labels.\n"
      . "   c. Avoid flowery, sweeping, or interpretive claims ('fuses existential philosophy "
      . "with…'). Stick to concrete, checkable facts: who they were, their era/nationality, "
      . "their field, and their notable works BY NAME.\n"
      . "4. Write 2-4 complete sentences. Finish every sentence — never cut off mid-sentence.\n"
      . "5. Plain prose only. English. NO markdown, asterisks, hashes, or headings. Write "
      . "only the biography text — no preamble, no quotes around it, no meta commentary about "
      . "yourself or your knowledge.";

    $user =
        $books_ctx
      . "Write a concise, factual, CONSERVATIVE biography (2-4 sentences, plain prose, English) "
      . "of the author: \"{$author}\".\n\n"
      . "Prefer safe, checkable facts over interpretation. Do not classify a work's genre or "
      . "assign the author to a movement unless you are certain.\n\n"
      . "If you cannot confidently identify which real person this is, output exactly: UNKNOWN";

    $r = tls_claude($system, $user, [
        'model'      => tls_claude_quality_model(),   // doğruluk için kaliteli model
        'max_tokens' => 500,
        'timeout'    => 90,
        'retries'    => 3,
    ]);
    if (empty($r['ok'])) {
        return ['ok' => false, 'unknown' => false, 'bio' => '', 'error' => $r['error'] ?? 'claude hata'];
    }
    $bio = trim((string) $r['text']);
    // UNKNOWN kaçışı: tek başına ya da en başta → tanıyamadı.
    $probe = mb_strtoupper(preg_replace('/[^A-Za-z]/', '', mb_substr($bio, 0, 20)));
    if ($probe === 'UNKNOWN' || strpos($probe, 'UNKNOWN') === 0) {
        return ['ok' => false, 'unknown' => true, 'bio' => '', 'error' => 'Claude bu yazarı güvenle tanıyamadı'];
    }
    $bio = trim(preg_replace('/[*`#]+/u', '', $bio));           // güvenlik: markdown temizle
    if (str_word_count($bio) < 6) {
        return ['ok' => false, 'unknown' => true, 'bio' => '', 'error' => 'çok kısa/boş çıktı'];
    }

    // ── FACT-CHECK (ikinci geçiş): taslağı denetle ve gerekiyorsa düzelt. ──
    // Senin elle yaptığın kontrolün aynısı: yanlış tarih/tür/atıf/abartı ayıklanır.
    $verified = ab_verify_bio($author, $bio, $titles);
    if ($verified !== null) $bio = $verified;
    if (str_word_count($bio) < 6) {
        return ['ok' => false, 'unknown' => true, 'bio' => '', 'error' => 'denetim sonrası boş'];
    }
    return ['ok' => true, 'unknown' => false, 'bio' => $bio, 'error' => ''];
}

/* FACT-CHECK: üretilen bio'yu bağımsız bir geçişte denetle, hataları düzelt.
   Yanlış tarih, yanlış eser TÜRÜ, yanlış akım/okul atfı ve abartılı/temelsiz
   iddiaları ayıklar. Düzeltilmiş (ya da zaten doğruysa aynı) bio'yu döner.
   Hata olursa (API vb.) null → çağıran orijinal taslağı kullanır. */
function ab_verify_bio($author, $draft, $titles = []) {
    $draft = trim((string) $draft);
    if ($draft === '') return null;
    $books_ctx = $titles
        ? "Known works by this author (from our site): " . implode('; ', array_slice($titles, 0, 8)) . ".\n\n"
        : "";

    $system =
        "You are a careful fact-checker for short author biographies. You are given a DRAFT "
      . "biography. Verify every factual claim and return a corrected version.\n\n"
      . "Check specifically for:\n"
      . "- Wrong birth/death dates, nationalities, or names.\n"
      . "- MISCLASSIFIED works: a work called a 'novel' that is actually a memoir/essay/"
      . "treatise/poem, etc. If unsure of a work's genre, remove the genre label and just "
      . "name the work.\n"
      . "- Incorrect attribution to a philosophical/literary MOVEMENT or SCHOOL. Remove such a "
      . "label unless it is textbook-standard and clearly correct.\n"
      . "- OVERSTATED or interpretive claims not solidly grounded in fact. Replace with neutral, "
      . "verifiable statements.\n"
      . "- Any invented specific you cannot confirm → remove it.\n\n"
      . "Keep it 2-4 complete sentences, plain prose, English, NO markdown. Output ONLY the final "
      . "corrected biography text — nothing else. If the draft is already fully accurate, output "
      . "it unchanged. If after removing everything unverifiable there is essentially nothing "
      . "reliable left, or you cannot identify the person at all, output exactly UNKNOWN.";

    $user =
        $books_ctx
      . "Author: \"{$author}\"\n\n"
      . "DRAFT biography to verify and correct:\n\"\"\"\n{$draft}\n\"\"\"\n\n"
      . "Return the corrected biography (or the draft unchanged if already accurate), or UNKNOWN.";

    $r = tls_claude($system, $user, [
        'model'      => tls_claude_quality_model(),
        'max_tokens' => 500,
        'timeout'    => 90,
        'retries'    => 2,
    ]);
    if (empty($r['ok'])) return null;   // denetim yapılamadı → taslağı kullan
    $out = trim((string) $r['text']);
    if ($out === '') return null;
    $probe = mb_strtoupper(preg_replace('/[^A-Za-z]/', '', mb_substr($out, 0, 20)));
    if ($probe === 'UNKNOWN' || strpos($probe, 'UNKNOWN') === 0) return '';   // güvenilir bilgi kalmadı
    return trim(preg_replace('/[*`#]+/u', '', $out));
}

/* GÜVENLİK AĞI: üstüne yazmadan önce mevcut bio'yu yedekle (term meta).
   Böylece her değişiklik 'restore' ile geri alınabilir. Boşsa yedeklemez. */
function ab_backup_bio($id, $old) {
    $old = trim((string) $old);
    if ($old === '') return;
    update_term_meta($id, '_tls_bio_backup', $old);
    update_term_meta($id, '_tls_bio_backup_at', current_time('mysql'));
}

/* Sadece markdown işaretlerini temizle (AI YOK) — kelimelere dokunmadan
   yıldız, backtick ve kare karakterlerini at, boşlukları toparla. */
function ab_strip_markdown($s) {
    $s = preg_replace('/[*`#]+/u', ' ', (string) $s);
    $s = preg_replace('/[ \t]{2,}/', ' ', $s);
    $s = preg_replace('/\n{3,}/', "\n\n", $s);
    return trim($s);
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
    $g = ab_generate_bio($t->name, $id);
    if (empty($g['ok'])) {
        echo json_encode(['ok' => false, 'unknown' => !empty($g['unknown']),
                          'error' => $g['error'] ?: 'bio üretilemedi']);
        exit;
    }
    echo json_encode(['ok' => true, 'bio' => $g['bio'], 'status' => ab_status($g['bio'])], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Düzelt: en güvenli yolu seç + KAYDET (toplu düzeltme için tek çağrı) ──
   1) Bio sadece MARKDOWN'lıysa → işaretleri temizle, metni AYNEN bırak (AI YOK).
      Doğru içeriği riske atmaz, para harcamaz. Temizleyince tamsa biter.
   2) Eksik/yarıda kesik/kısa (ya da temizleyince hâlâ bozuk) → Claude yeniden yazar.
   Her iki halde de ÜSTÜNE YAZMADAN ÖNCE eski bio yedeklenir (geri alınabilir).
   Claude yazarı tanıyamazsa (unknown) KAYDETMEZ; mevcut bio korunur, atlanır. */
if ($action === 'fix') {
    $id = (int) ($_POST['id'] ?? 0);
    $t  = $id ? get_term($id, 'authors') : null;
    if (!$t || is_wp_error($t)) { echo json_encode(['ok' => false, 'error' => 'yazar bulunamadı']); exit; }

    $old = (string) $t->description;

    // 1) SADECE markdown sorunu mu? → AI'sız temizle (en güvenli, içerik korunur).
    if (ab_status($old) === 'markdown') {
        $clean = ab_strip_markdown($old);
        if (ab_status($clean) === 'ok') {
            ab_backup_bio($id, $old);
            $u = wp_update_term($id, 'authors', ['description' => sanitize_textarea_field($clean)]);
            if (is_wp_error($u)) { echo json_encode(['ok' => false, 'error' => $u->get_error_message()]); exit; }
            echo json_encode(['ok' => true, 'bio' => $clean, 'status' => ab_status($clean),
                              'method' => 'cleaned', 'had_old' => trim($old) !== ''], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // temizlenince hâlâ bozuk (yarıda kesik/çok kısa) → aşağıda Claude yazsın.
    }

    // 2) Claude ile yeniden yaz.
    $g = ab_generate_bio($t->name, $id);
    if (empty($g['ok'])) {
        echo json_encode([
            'ok'      => false,
            'skipped' => !empty($g['unknown']),   // unknown → bilinçli atlama (hata değil)
            'error'   => $g['error'] ?: 'bio üretilemedi',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    ab_backup_bio($id, $old);
    $u = wp_update_term($id, 'authors', ['description' => $g['bio']]);
    if (is_wp_error($u)) { echo json_encode(['ok' => false, 'error' => $u->get_error_message()]); exit; }
    echo json_encode(['ok' => true, 'bio' => $g['bio'], 'status' => ab_status($g['bio']),
                      'method' => 'ai', 'had_old' => trim($old) !== ''], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Geri Al: en son üstüne yazmadan önceki bio'yu yedekten geri yükle ── */
if ($action === 'restore') {
    $id = (int) ($_POST['id'] ?? 0);
    $t  = $id ? get_term($id, 'authors') : null;
    if (!$t || is_wp_error($t)) { echo json_encode(['ok' => false, 'error' => 'yazar bulunamadı']); exit; }
    $bak = get_term_meta($id, '_tls_bio_backup', true);
    if (!is_string($bak) || trim($bak) === '') { echo json_encode(['ok' => false, 'error' => 'yedek yok']); exit; }
    $u = wp_update_term($id, 'authors', ['description' => $bak]);
    if (is_wp_error($u)) { echo json_encode(['ok' => false, 'error' => $u->get_error_message()]); exit; }
    echo json_encode(['ok' => true, 'bio' => $bak, 'status' => ab_status($bak)], JSON_UNESCAPED_UNICODE);
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
    $had_old = trim((string) $t->description) !== '';
    ab_backup_bio($id, $t->description);   // elle kaydetmede de eskiyi yedekle
    $u = wp_update_term($id, 'authors', ['description' => $bio]);
    if (is_wp_error($u)) { echo json_encode(['ok' => false, 'error' => $u->get_error_message()]); exit; }
    echo json_encode(['ok' => true, 'bio' => $bio, 'status' => ab_status($bio), 'had_old' => $had_old], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'bad action']);
