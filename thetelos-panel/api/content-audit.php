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
@ini_set('memory_limit', '512M');
// Uzun metinlerde geri izleme sınırına takılan bir kalıp isteği öldürmesin:
// preg_* false döner, tarama o kontrolü atlar ama devam eder.
@ini_set('pcre.backtrack_limit', '2000000');
ignore_user_abort(true);

ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();

/* ── Kontroller ───────────────────────────────────────────────────────────
   Her kontrol: kod, önem (3=ağır, 2=orta, 1=hafif), açıklama, bulunan örnek.
   Ağır bulgular okuyucunun doğrudan göreceği kazalardır.                    */

/**
 * Metni bloklara ayırır (paragraf, başlık, madde, alıntı) — düz metin olarak.
 *
 * NEDEN BLOK: "the end of the work" ya da "here the work ends: not with a
 * solution, but with a question" gibi ifadeler NORMAL metinde geçer. Bunları
 * gövdenin herhangi bir yerinde aramak yüzlerce yanlış alarm üretiyordu.
 * Sızıntı, cümlenin içinde değil, KENDİ BAŞINA duran bir satır olarak görünür.
 */
/**
 * Kitabın kendi sesi: alıntılar.
 *
 * TEMEL AYRIM: Bir metinde iki ses vardır — kitabın/yazarın sesi (alıntılar)
 * ve özeti yazan sistemin sesi. Süreç kalıntısı ancak İKİNCİSİNDE olabilir.
 * Irenaeus'un "We will now proceed to refute these heretics…" cümlesi kitabın
 * sesidir; kalıp benzese de sızıntı değildir. Bu yüzden alıntılar taramanın
 * dışında bırakılır — kalıp kalıp yama yapmak yerine kaynağı ayırıyoruz.
 */
function ca_strip_quotes($html) {
    return preg_replace('/<blockquote\b[^>]*>.*?<\/blockquote>/is', ' ', (string) $html);
}

/** Blok baştan sona tırnak içindeyse alıntıdır — sistemin sesi olamaz. */
function ca_is_quotation($t) {
    $t = trim($t, "*_ \t");
    return (bool) preg_match('/^["“«‘\'].{10,}["”»’\']\s*[.,;:]?$/us', $t);
}

function ca_blocks($html, $skip_quotes = true) {
    $h = preg_replace('/<br\s*\/?>/i', "\n", (string) $html);
    if ($skip_quotes) $h = ca_strip_quotes($h);
    $out = [];
    if (preg_match_all('/<(p|h[1-6]|li|blockquote)\b[^>]*>(.*?)<\/\1>/is', $h, $m)) {
        foreach ($m[2] as $b) {
            $t = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($b)));
            if ($t !== '') $out[] = $t;
        }
    }
    if (!$out) {
        foreach (preg_split('/\n+/', wp_strip_all_tags($h)) as $l) {
            $l = trim($l);
            if ($l !== '') $out[] = $l;
        }
    }
    return $out;
}

/**
 * Süreç cümleleri.
 *
 * ÖLÇÜT: cümle, KİTABIN KONUSU hakkında değil, METNİN ÜRETİLMESİ hakkında
 * olmalı. Bu ayrımı yapmayan kalıplar yanlış alarm üretiyor —
 * "We will now proceed to refute these heretics" (Irenaeus) ile
 * "I will now continue with Part 3" arasındaki fark, fiilin değil NESNENİN
 * ne olduğudur. Bu yüzden her kalıp, üretim sözlüğünden bir kelimeyi
 * (part, word count, summary, prompt, AI) zorunlu tutar; salt birinci tekil
 * gelecek zaman kalıpları listede YOKTUR — felsefe metinlerinin doğal dilidir.
 */
function ca_meta_patterns() {
    return [
        // Üretim düzeneğinin kendi kelimeleri
        '/no summary,?\s*no closing paragraph/i',
        '/\bas an? (?:ai|language model|assistant)\b/i',
        '/\b(?:apply|per) the closing rule\b/i',
        '/\bdo not write a (?:conclusion|summary)\b/i',
        '/\bword count\s*[:=]/i',
        '/\btarget length\b/i',
        '/\bapproximately \d{3,5} words\b/i',
        '/\bper your (?:request|instructions)\b/i',
        '/\bnote to (?:the )?(?:editor|reader|user)\b/i',

        // Sohbet artığı — kitap metninde karşılığı yok
        '/\bi hope this (?:helps|summary)\b/i',
        '/\blet me know if you\b/i',

        // Parça düzeneğine açık gönderme (nesne: part/section)
        '/\b(?:part|section)\s+\d+\s+of\s+\d+\b/i',
        '/\b(?:i|we) (?:will|shall) now (?:continue|proceed|begin)\b[^.]{0,40}\b(?:part|section)\s*\d/i',
        '/\bhere(?:\'s| is) the (?:continuation|next part|final part)\b/i',
        '/\bthe (?:next|remaining|final) part will (?:cover|continue)\b/i',
        '/\b(?:in|for) this part,? i (?:will|have)\b/i',
        '/\bcontinu(?:ing|ed) from (?:the )?(?:previous|last) (?:part|section)\b/i',
    ];
}

/**
 * ÜRETİM REDDİ: yazının tamamı, modelin "bunu yazamam" açıklaması.
 *
 * Bu bir satır kusuru değil, yazının tamamının geçersiz olmasıdır. Böyle bir
 * yazıda satır silmek onarım değil — çöpün bir kısmını temizleyip kalanını
 * yayında bırakmaktır. Bu yüzden ayrı bir bulgu türü: eylemi yayından
 * kaldırmak ve yeniden üretmektir.
 */
function ca_check_refusal($html) {
    $blocks = ca_blocks($html, false);
    $head   = implode(' ', array_slice($blocks, 0, 3));   // reddin yeri hep baştır
    $pats = [
        '/\bi cannot (?:produce|write|provide|generate|create)\b/i',
        '/\bi(?:\'m| am) (?:unable|not able) to (?:produce|write|provide|access)\b/i',
        '/\bi do not have access to the (?:actual|full) text\b/i',
        '/\bwithout the source material\b/i',
        '/\bi need one of the following\b/i',
        '/\bplease provide the (?:necessary|full) (?:material|text)\b/i',
        '/\bif you (?:can )?(?:provide|share) the (?:text|book|material)\b/i',
        '/\bi (?:don\'t|do not) have (?:the )?(?:book|text|access)\b/i',
    ];
    foreach ($pats as $p) if (preg_match($p, $head, $m)) return trim($m[0]);
    return '';
}

/** Parça üretiminin teknik işaretleri metne sızmış mı? */
function ca_check_part_markers($html) {
    // Teknik işaretler her yerde geçersiz — alıntı içinde bile bulunamaz.
    if (preg_match('/%%\s*PART|PART_END|=== MULTI-PART/i', $html, $m)) return $m[0];
    // "Part 2 of 4" ifadesi kitabın kendi metninde geçebilir (alıntı, künye);
    // bu yüzden alıntılar dışarıda bırakılarak aranır.
    $text = wp_strip_all_tags(ca_strip_quotes($html));
    return preg_match('/\bPart\s+\d+\s+of\s+\d+\b/i', $text, $m) ? $m[0] : '';
}

/**
 * Bir blok BAŞTAN SONA süreç cümlesi mi? (silinebilir olan tek durum)
 * Uzun bloklar elenir: gerçek paragrafın içine karışmış bir eşleşme,
 * paragrafın tamamını silmeyi haklı çıkarmaz.
 */
function ca_is_meta_block($block) {
    $b = trim($block, "*_ \t");
    if ($b === '' || mb_strlen($b, 'UTF-8') > 220) return false;
    if (preg_match('/^%%\s*PART/i', $b)) return true;   // teknik işaret: her koşulda
    if (ca_is_quotation($b)) return false;              // tırnak içindeyse kitabın sesi
    foreach (ca_meta_patterns() as $p) if (preg_match($p, $b)) return true;
    return false;
}

/** Prompt'un kuralı metne yazılmış mı? — sadece kendi başına duran bloklarda. */
function ca_check_prompt_leak($html) {
    foreach (ca_blocks($html) as $b) {
        if (ca_is_meta_block($b)) return mb_substr(trim($b, "*_ \t"), 0, 110, 'UTF-8');
    }
    return '';
}

/**
 * Model kendi süreciyle konuşmuş mu? — cümlenin içinde bile kabul edilemez
 * olanlar. Alıntılar hariç tutulur: bir mektup ya da diyalog alıntısında
 * "per your request" geçebilir, bu kitabın sesidir.
 */
function ca_check_meta_talk($html) {
    $text = wp_strip_all_tags(ca_strip_quotes($html));
    $hard = [
        '/\bas an? (?:ai|language model|assistant)\b/i',
        '/\bi hope this (?:helps|summary)\b/i',
        '/\blet me know if you\b/i',
        '/\bnote to (?:the )?(?:editor|reader|user)\b/i',
        '/\bword count\s*[:=]/i',
        '/\[(?:note|continued|to be continued)\b/i',
    ];
    foreach ($hard as $p) if (preg_match($p, $text, $m)) return trim($m[0]);
    return '';
}

/** Metin yarım mı bitmiş? (cümle ortasında ya da başlıkla) */
function ca_check_truncated($html) {
    $t = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($html)));
    if ($t === '') return 'içerik boş';
    // Son blok başlıksa: başlık atılmış ama altı yazılmamış.
    // Tüm gövdeye "$" çapalı .*? uygulanmaz — 60 KB'lık metinlerde PCRE geri
    // izlemesi isteği dakikalarca oyalıyor ve tarama yarıda kopuyordu. Son
    // 300 karaktere bakmak yeterli.
    $tail_html = mb_substr(rtrim($html), -300, 300, 'UTF-8');
    if (preg_match('/<\/h[1-6]>\s*$/i', $tail_html)) return 'son blok başlık — altı boş';
    // Sondaki markdown vurgu işaretleri (*, _) noktalamayı gizleyebiliyor
    $t    = rtrim($t, "*_ \t");
    $last = mb_substr($t, -1, 1, 'UTF-8');
    // Kapanış parantezi/köşeli parantez de geçerli bir bitiş: alıntı ya da
    // künye ile biten metinler ("…Soviet Union.]") yarım sanılıyordu.
    if (mb_strpos('.!?"\'»)]}”’…', $last, 0, 'UTF-8') === false) {
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
    $t = trim(wp_strip_all_tags(ca_strip_quotes($html)));
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

        // Üretim reddi yazının TAMAMINI geçersiz kılar; diğer kusurlar anlamsız
        // kalır, o yüzden tek başına raporlanır ve onarım denenmez.
        if ($s = ca_check_refusal($html)) {
            $findings[] = [
                'id' => (int) $r->ID, 'title' => $r->post_title,
                'date' => substr((string)$r->post_date, 0, 10), 'words' => $words, 'sev' => 3,
                'link' => get_permalink($r->ID),
                'edit' => admin_url('post.php?post=' . (int)$r->ID . '&action=edit'),
                'flags' => [[ 'code'=>'refusal', 'sev'=>3,
                              'label'=>'ÜRETİM REDDİ — yazı geçersiz, yeniden üretilmeli', 'sample'=>$s ]],
            ];
            continue;
        }

        if ($s = ca_check_part_markers($html)) $flags[] = ['code'=>'part_marker', 'sev'=>3, 'label'=>'Parça işareti sızmış',        'sample'=>$s];
        if ($s = ca_check_meta_talk($html))    $flags[] = ['code'=>'meta_talk',   'sev'=>3, 'label'=>'Model kendi süreciyle konuşmuş','sample'=>$s];
        if ($s = ca_check_prompt_leak($html))  $flags[] = ['code'=>'prompt_leak', 'sev'=>3, 'label'=>'Prompt talimatı metne yazılmış','sample'=>$s];
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

/* ── Onarım ──────────────────────────────────────────────────────────────
   Yeniden üretmeden düzeltilebilen kusurlar: prompt/süreç satırlarının
   silinmesi, HTML'e çevrilmemiş markdown kalıntısının çevrilmesi, parça
   işaretlerinin temizlenmesi. Yarım biten ya da kısa içerik BURADA
   düzeltilemez — o ancak yeniden üretimle olur, dokunulmaz.

   Kural: bir <p> bloğunun TAMAMI kalıntıysa dönüştürülür; içine karışmışsa
   elleme. Tahminle metin bölmek, bozuk bırakmaktan daha kötü.               */
/**
 * SİLME ölçütü, TESPİT ölçütünden dar olmak zorundadır.
 *
 * Tespit "burada bir tuhaflık var" diyebilir; silme ise geri dönüşsüzdür ve
 * yanılma payı taşımamalıdır. Somut kaza: "(or the relevant chapters for
 * Part 1 of 4)" ifadesi geçtiği için sağlam bir paragraf silinmişti — oysa
 * bir cümlede "Part 1 of 4" geçmesi, o cümlenin makine artığı olduğunu
 * KANITLAMAZ. Silme yalnızca hiçbir kitapta bulunamayacak kadar kesin
 * işaretlerde yapılır; şüpheli olan ekranda gösterilir, insan karar verir.
 */
function ca_delete_patterns() {
    return [
        '/^%%\s*PART/i',                                   // teknik işaret
        '/no summary,?\s*no closing paragraph/i',          // prompt kuralının aynısı
        '/\bas an? (?:ai|language model|assistant)\b/i',
        '/\b(?:apply|per) the closing rule\b/i',
        '/\bdo not write a (?:conclusion|summary)\b/i',
        '/\bword count\s*[:=]/i',
        '/\btarget length\b/i',
        '/\bi hope this (?:helps|summary)\b/i',
        '/\blet me know if you\b/i',
        '/\bnote to (?:the )?(?:editor|reader|user)\b/i',
    ];
}

function ca_leak_line($t) {
    $b = trim($t, "*_ \t");
    if ($b === '' || mb_strlen($b, 'UTF-8') > 200) return false;
    if (ca_is_quotation($b)) return false;                 // kitabın sesi
    foreach (ca_delete_patterns() as $p) if (preg_match($p, $b)) return true;
    return false;
}

/**
 * @param string $mode 'severe' = yalnız AĞIR kusurlar (süreç satırı, parça
 *                     işareti). Markdown kalıntısına dokunulmaz: onlar okuma
 *                     akışını bozmuyor, sayıları binlerce ve her dokunuş
 *                     sağlam metni bozma riski taşıyor.
 *                     'all' = markdown dönüşümü de yapılır.
 */
/**
 * Onarımdan ÖNCE eski gövdeyi saklar.
 *
 * Yayındaki metni değiştiren her işlemin geri alınabilir olması gerekir;
 * yoksa tek bir hatalı kural, düzeltilemez bir kayba dönüşür.
 */
function ca_backup_before($id, $old) {
    if (get_post_meta($id, '_tls_audit_backup', true) === '') {
        update_post_meta($id, '_tls_audit_backup', $old);
        update_post_meta($id, '_tls_audit_backup_at', time());
    }
}

function ca_repair($html, $mode = 'severe') {
    // Yazının tamamı üretim reddiyse satır silmek işe yaramaz, zarar verir:
    // saçmalığın bir kısmı temizlenip kalanı yayında kalır. Dokunma.
    if (ca_check_refusal($html) !== '') return $html;

    $all = ($mode === 'all');

    $out = preg_replace_callback('/<p[^>]*>(.*?)<\/p>\s*/is', function ($m) use ($all) {
        $inner = trim($m[1]);
        $text  = trim(wp_strip_all_tags($inner));
        // Vurgu yıldızlarını sıyırıp bak: "*Here the work ends…*" da yakalansın
        $bare  = trim($text, "*_ \t");

        if ($bare === '' ) return '';
        if (ca_leak_line($bare)) return '';                       // süreç satırı → sil

        if ($all) {
            if (preg_match('/^(#{1,6})\s+(.+)$/u', $bare, $h)) {  // başlık kalıntısı
                $lvl = min(6, max(2, strlen($h[1])));             // h1 tema başlığı, kullanma
                $t   = trim($h[2], "* \t");
                return "<h{$lvl}>{$t}</h{$lvl}>\n";
            }
            if (preg_match('/^(?:-{3,}|\*{3,}|_{3,})$/', $bare)) return "<hr>\n";
        }

        // Parça işaretleri her modda temizlenir (okuyucuya görünen teknik artık)
        $clean = preg_replace('/%%\s*PART[^%]*%%/i', '', $inner);
        if ($all) {
            $clean = preg_replace('/\*\*([^*\n]{1,200}?)\*\*/', '<strong>$1</strong>', $clean);
        }
        $clean = trim($clean);
        return $clean === '' ? '' : "<p>{$clean}</p>\n";
    }, $html);

    // Blok dışında kalmış çıplak parça işaretleri
    $out = preg_replace('/%%\s*PART[^%]*%%/i', '', $out);

    return trim($out);
}

/* ── GERİ AL ──────────────────────────────────────────────────────────────
   Onarımın sildiği metni geri getirir. İki kaynak:
   1. _tls_audit_backup meta'sı (bundan sonraki onarımlar için)
   2. WordPress revizyonu — meta yedeği yokken yapılmış onarımlar için tek
      kurtarma yolu. Yalnız SİLME'yi geri alır: içeriği mevcuttan uzun olan
      ve mevcut metnin tamamını kapsayan en yeni revizyon geri yüklenir.
      Böylece elle yapılmış düzenlemeler yanlışlıkla geri alınmaz.            */
if ($action === 'undo') {
    global $wpdb;
    $offset = max(0, (int)($_POST['offset'] ?? 0));
    $limit  = max(10, min(100, (int)($_POST['limit'] ?? 40)));
    $window = max(0, (int)($_POST['hours'] ?? 24)) * 3600;

    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts}
          WHERE post_type IN ('post','analysis') AND post_status IN ('publish','draft')"
    );
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT ID, post_content FROM {$wpdb->posts}
          WHERE post_type IN ('post','analysis') AND post_status IN ('publish','draft')
       ORDER BY post_date DESC LIMIT %d OFFSET %d", $limit, $offset
    ) );

    $restored = 0; $samples = [];
    foreach ($rows as $r) {
        $id  = (int) $r->ID;
        $cur = (string) $r->post_content;

        // 1) Meta yedeği
        $bak = get_post_meta($id, '_tls_audit_backup', true);
        if (is_string($bak) && $bak !== '' && $bak !== $cur) {
            wp_update_post(['ID' => $id, 'post_content' => $bak]);
            delete_post_meta($id, '_tls_audit_backup');
            delete_post_meta($id, '_tls_audit_backup_at');
            $restored++;
            if (count($samples) < 3) $samples[] = get_the_title($id);
            continue;
        }

        // 2) Revizyon
        $revs = wp_get_post_revisions($id, ['numberposts' => 6]);
        if (!$revs) continue;
        $cur_len  = mb_strlen(wp_strip_all_tags($cur));
        $cur_norm = preg_replace('/\s+/u', ' ', wp_strip_all_tags($cur));

        foreach ($revs as $rev) {
            if ($window && (time() - strtotime($rev->post_date_gmt . ' UTC')) > $window) continue;
            $rc = (string) $rev->post_content;
            if ($rc === '' || $rc === $cur) continue;
            if (mb_strlen(wp_strip_all_tags($rc)) <= $cur_len) continue;  // sadece silmeyi geri al

            // Mevcut metnin her paragrafı revizyonda da var mı? (yalnız silinmiş mi)
            $ok = true;
            foreach (ca_blocks($cur, false) as $b) {
                if (mb_strlen($b) < 40) continue;
                if (strpos(preg_replace('/\s+/u', ' ', wp_strip_all_tags($rc)), mb_substr($b, 0, 60)) === false) { $ok = false; break; }
            }
            if (!$ok) continue;

            wp_update_post(['ID' => $id, 'post_content' => $rc]);
            $restored++;
            if (count($samples) < 3) $samples[] = get_the_title($id);
            break;
        }
    }

    echo json_encode([
        'ok'       => true,
        'total'    => $total,
        'seen'     => count($rows),
        'restored' => $restored,
        'samples'  => $samples,
        'next'     => (count($rows) < $limit) ? -1 : $offset + count($rows),
    ]);
    exit;
}

/* Tüm siteyi gezip onarır: önce tarama yapılmasını beklemez, dilim dilim
   ilerler. İstemci next<0 gelene kadar çağırır. */
if ($action === 'autofix') {
    global $wpdb;
    $offset = max(0, (int)($_POST['offset'] ?? 0));
    $limit  = max(10, min(100, (int)($_POST['limit'] ?? 40)));
    $mode   = ($_POST['mode'] ?? 'severe') === 'all' ? 'all' : 'severe';

    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts}
          WHERE post_type IN ('post','analysis') AND post_status = 'publish'"
    );
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT ID, post_content FROM {$wpdb->posts}
          WHERE post_type IN ('post','analysis') AND post_status = 'publish'
       ORDER BY post_date DESC LIMIT %d OFFSET %d", $limit, $offset
    ) );

    $fixed = 0; $samples = [];
    foreach ($rows as $r) {
        $old = (string) $r->post_content;
        $new = ca_repair($old, $mode);
        if ($new === '' || $new === $old) continue;
        if (mb_strlen(wp_strip_all_tags($new)) < mb_strlen(wp_strip_all_tags($old)) * 0.9) continue;
        ca_backup_before((int)$r->ID, $old);
        wp_update_post(['ID' => (int)$r->ID, 'post_content' => $new]);
        $fixed++;
        if (count($samples) < 3) $samples[] = get_the_title($r->ID);
    }

    echo json_encode([
        'ok'      => true,
        'total'   => $total,
        'seen'    => count($rows),
        'fixed'   => $fixed,
        'samples' => $samples,
        'next'    => (count($rows) < $limit) ? -1 : $offset + count($rows),
    ]);
    exit;
}

if ($action === 'fix') {
    $ids   = array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? ''))));
    $mode  = ($_POST['mode'] ?? 'severe') === 'all' ? 'all' : 'severe';
    $fixed = 0; $skipped = 0;
    foreach ($ids as $id) {
        $p = get_post($id);
        if (!$p || $p->post_type === 'revision') { $skipped++; continue; }
        $new = ca_repair((string)$p->post_content, $mode);
        if ($new === '' || $new === $p->post_content) { $skipped++; continue; }
        // Güvenlik: onarım metnin belirgin bir kısmını yutuyorsa uygulama.
        if (mb_strlen(wp_strip_all_tags($new)) < mb_strlen(wp_strip_all_tags($p->post_content)) * 0.9) {
            $skipped++; continue;
        }
        ca_backup_before($id, $p->post_content);
        wp_update_post(['ID' => $id, 'post_content' => $new]);
        $fixed++;
    }
    echo json_encode(['ok' => true, 'fixed' => $fixed, 'skipped' => $skipped]);
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
