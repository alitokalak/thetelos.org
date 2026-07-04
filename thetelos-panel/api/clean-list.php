<?php
/**
 * clean-list.php — TEK YAZARIN eser listesini temizle.
 *
 * Katman 1 (kural, ücretsiz): normalize tekrarları birleştir.
 * Katman 2 (AI hakem, DeepSeek): aynı eserin farklı dil/çeviri baskılarını tek
 *   kanonik girişte birleştir ("İngilizce ad (Orijinal ad)"), yazara ait
 *   olmayanları gerekçesiyle ele. AI liste ÜRETMEZ — yalnız verilen başlıkları
 *   yargılar; listede olmayan hiçbir eser eklenmez.
 *
 * POST: author, works (JSON [{title,year,cover}]), use_ai (0/1)
 * Dönüş: { ok, works:[{title,author,year,cover,merged}], removed:[{title,reason}], ai_used, error? }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
@ini_set('display_errors', 0);
set_time_limit(150);

$author = trim($_POST['author'] ?? '');
$works  = json_decode($_POST['works'] ?? '', true);
$use_ai = !empty($_POST['use_ai']);
if ($author === '' || !is_array($works) || empty($works)) {
    echo json_encode(['ok'=>false,'error'=>'author ve works gerekli']); exit;
}

/* ── Normalizasyon (kural katmanı) ── */
function cl_norm($s) {
    $s = mb_strtolower(trim((string)$s));
    $s = preg_replace('/\s*[\(\（].*?[\)\）]\s*/u', ' ', $s);       // parantez içlerini at
    $s = preg_replace('/^(the|a|an|le|la|les|der|die|das|el|il)\s+/u', '', $s);
    $s = preg_replace('/\s*[:;].*$/u', '', $s);                     // alt başlık
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s); if ($t !== false && $t !== '') $s = $t;
    $s = preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s));
    return trim(preg_replace('/\s+/', ' ', $s));
}

/* Kural katmanı: normalize aynı olanları birleştir (en bilgili satırı tut) */
$groups = [];   // norm → ['rows'=>[...], 'title'=>best]
$order  = [];
foreach ($works as $w) {
    $t = trim((string)($w['title'] ?? '')); if ($t === '') continue;
    $n = cl_norm($t); if ($n === '') $n = mb_strtolower($t);
    if (!isset($groups[$n])) { $groups[$n] = ['rows'=>[], 'title'=>$t]; $order[] = $n; }
    $groups[$n]['rows'][] = [
        'title' => $t,
        'year'  => trim((string)($w['year']  ?? '')),
        'cover' => trim((string)($w['cover'] ?? '')),
    ];
    // Tercih: Latin alfabeli ve daha kısa ana başlığı temsilci yap
    $cur = $groups[$n]['title'];
    $t_latin  = (bool)preg_match('/^[\x20-\x7E\p{Latin}\p{P}\p{Zs}\d]+$/u', $t);
    $c_latin  = (bool)preg_match('/^[\x20-\x7E\p{Latin}\p{P}\p{Zs}\d]+$/u', $cur);
    if (($t_latin && !$c_latin) || ($t_latin === $c_latin && mb_strlen($t) < mb_strlen($cur))) {
        $groups[$n]['title'] = $t;
    }
}

/* Grubun en iyi yıl/kapak değeri (ilk dolu; yıl için en küçük 4 haneli = ilk basım) */
function cl_best($rows) {
    $year = ''; $cover = '';
    foreach ($rows as $r) {
        if ($r['cover'] !== '' && $cover === '') $cover = $r['cover'];
        if (preg_match('/^\d{3,4}$/', $r['year'])) {
            if ($year === '' || (int)$r['year'] < (int)$year) $year = $r['year'];
        }
    }
    return [$year, $cover];
}

$items = [];   // kural sonrası aday liste
foreach ($order as $n) {
    [$year, $cover] = cl_best($groups[$n]['rows']);
    $items[] = [
        'title'  => $groups[$n]['title'],
        'year'   => $year,
        'cover'  => $cover,
        'merged' => count($groups[$n]['rows']),
        'all'    => array_map(fn($r) => $r['title'], $groups[$n]['rows']),
    ];
}

$removed = [];
$ai_used = false;
$ai_err  = '';

/* ── AI hakem katmanı ── */
if ($use_ai && count($items) >= 2 && defined('DEEPSEEK_KEY') && DEEPSEEK_KEY !== '') {
    $cap = 120;                                    // token güvenliği (her giriş çıktıda yer alacak)
    $slice = array_slice($items, 0, $cap);
    $lines = '';
    foreach ($slice as $i => $it) {
        $lines .= ($i+1) . '. ' . mb_substr($it['title'], 0, 160) . "\n";
    }
    $prompt = "You are a strict bibliographic judge. Below is a numbered list of titles catalogued under the author \"{$author}\".\n"
        . "Your job: produce the author's clean canonical bibliography from these entries.\n"
        . "OUTPUT CONTRACT — every entry number MUST appear in exactly one place: either in some group's members, or in not_by_author.\n"
        . "1) GROUP entries that are the SAME WORK (translations, different-language/script editions, transliterations, spelling variants) into ONE group. "
        . "A single-member group is normal for works appearing once.\n"
        . "2) For EVERY group give:\n"
        . "   en   = the work's standard title as used in ENGLISH literature (e.g. \"Tao Te Ching\", \"Critique of Pure Reason\", \"The Evolution of Physics\")\n"
        . "   orig = the title in the language the work was ORIGINALLY WRITTEN in by {$author} (e.g. \"道德经\" for Laozi, \"Kritik der reinen Vernunft\" for Kant).\n"
        . "   CRITICAL: think about which language(s) {$author} actually wrote in. A TRANSLATION'S title is NEVER orig — "
        . "e.g. a Japanese or Hebrew edition title of an Einstein work is NOT the original (Einstein wrote in German/English). "
        . "orig must be empty if the work was originally written in English, and ALSO empty if you don't know the true original title. "
        . "Never copy a listed foreign edition title into orig unless it IS the language the author wrote in.\n"
        . "3) not_by_author: entries that are NOT a book written by {$author} — books ABOUT the author, secondary literature, "
        . "quote/aphorism collections (\"Quotes\", \"Words of Wisdom\"), titles that are just the author's name or a slogan, or entries you cannot identify at all. Short reason each.\n"
        . "Rules: judge ONLY the given entries; do NOT invent extra works; when unsure about a plausible entry, keep it as its own group.\n"
        . "Return ONLY JSON:\n"
        . "{\"groups\":[{\"en\":\"English title\",\"orig\":\"Original title or empty\",\"members\":[1,4]}],\"not_by_author\":[{\"n\":3,\"reason\":\"short reason\"}]}\n\n"
        . $lines;

    $ch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 90,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => DEEPSEEK_MODEL, 'max_tokens' => 6000, 'temperature' => 0,
            'messages' => [['role'=>'user','content'=>$prompt]],
        ]),
    ]);
    $r = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);

    $parsed = null;
    if ($http === 200 && $r) {
        $d   = json_decode($r, true);
        $txt = $d['choices'][0]['message']['content'] ?? '';
        $txt = preg_replace('/```json|```/i', '', $txt);
        $s = strpos($txt, '{'); $e = strrpos($txt, '}');
        if ($s !== false && $e > $s) $parsed = json_decode(substr($txt, $s, $e - $s + 1), true);
    } else {
        $ai_err = "AI HTTP $http";
    }

    if (is_array($parsed)) {
        $ai_used  = true;
        $flagged  = [];   // index(0-based) → reason
        foreach ($parsed['not_by_author'] ?? [] as $f) {
            $ix = (int)($f['n'] ?? 0) - 1;
            if ($ix >= 0 && $ix < count($slice)) $flagged[$ix] = trim((string)($f['reason'] ?? 'yazara ait değil'));
        }

        $used = [];      // gruba giren indexler
        $out  = [];
        foreach ($parsed['groups'] ?? [] as $g) {
            $members = array_values(array_filter(array_map(fn($m) => (int)$m - 1, (array)($g['members'] ?? [])),
                        fn($ix) => $ix >= 0 && $ix < count($slice) && !isset($flagged[$ix]) && !isset($used[$ix])));
            if (empty($members)) continue;
            foreach ($members as $ix) $used[$ix] = true;

            $en   = trim((string)($g['en']   ?? ''));
            $orig = trim((string)($g['orig'] ?? ''));
            // GÜVENLİK: AI başlık uyduramaz — en az bir üyeyle kaba benzerlik yoksa üyenin başlığını kullan
            if ($en === '') $en = $slice[$members[0]]['title'];
            $final = $en;
            if ($orig !== '' && mb_strtolower($orig) !== mb_strtolower($en)) $final = "$en ($orig)";

            // Üyelerin en iyi yıl/kapak değeri
            $year = ''; $cover = ''; $mergedN = 0;
            foreach ($members as $ix) {
                $it = $slice[$ix]; $mergedN += $it['merged'];
                if ($cover === '' && $it['cover'] !== '') $cover = $it['cover'];
                if (preg_match('/^\d{3,4}$/', $it['year']) && ($year === '' || (int)$it['year'] < (int)$year)) $year = $it['year'];
            }
            $out[] = ['title'=>$final, 'author'=>$author, 'year'=>$year, 'cover'=>$cover, 'merged'=>$mergedN];
        }
        // Gruba girmeyen + işaretlenmemiş girişler aynen kalır (AI kararsızsa dokunma)
        foreach ($slice as $ix => $it) {
            if (isset($used[$ix])) continue;
            if (isset($flagged[$ix])) {
                $removed[] = ['title'=>$it['title'], 'author'=>$author, 'year'=>$it['year'], 'cover'=>$it['cover'], 'reason'=>$flagged[$ix]];
                continue;
            }
            $out[] = ['title'=>$it['title'], 'author'=>$author, 'year'=>$it['year'], 'cover'=>$it['cover'], 'merged'=>$it['merged']];
        }
        // Cap dışında kalanlar (>150) kural sonucuyla eklenir
        foreach (array_slice($items, $cap) as $it) {
            $out[] = ['title'=>$it['title'], 'author'=>$author, 'year'=>$it['year'], 'cover'=>$it['cover'], 'merged'=>$it['merged']];
        }
        $items_final = $out;
    } else {
        if ($ai_err === '') $ai_err = 'AI yanıtı çözümlenemedi';
    }
}

if (!isset($items_final)) {
    // AI yok/başarısız → kural sonucu
    $items_final = array_map(fn($it) => [
        'title'=>$it['title'], 'author'=>$author, 'year'=>$it['year'], 'cover'=>$it['cover'], 'merged'=>$it['merged'],
    ], $items);
}

/* ── SERT GÜVENLİK KATMANI (AI'dan bağımsız, çıkışta ZORUNLU) ──
   Format kuralı: her başlığın ANA kısmı İngilizce literatür adı olmalı; orijinal
   ad parantezdedir ("Tao Te Ching (道德经)" GEÇERLİDİR). Şunlar temiz listeye
   giremez (Elenenler'e düşer, geri alınabilir):
   1) Ana kısmı hâlâ Latin harfsiz kalan başlık = AI eseri İngilizce adıyla çözememiş
   2) Başlık == yazar adı ("Albert Einstein")
   3) Alıntı/özlü söz derlemeleri ("Quotes", "Words of Wisdom") */
$guarded = [];
$auth_norm = cl_norm($author);
foreach ($items_final as $it) {
    $main = trim(preg_replace('/\s*[\(\（].*$/u', '', $it['title']));   // parantez öncesi ana başlık
    if (!preg_match('/\p{Latin}/u', $main)) {
        $removed[] = ['title'=>$it['title'], 'author'=>$author, 'year'=>$it['year'], 'cover'=>$it['cover'],
                      'reason'=>'İngilizce literatür adı çözülemedi — geri alıp elle "İngilizce Ad ('.$it['title'].')" yazabilirsin'];
        continue;
    }
    if ($auth_norm !== '' && cl_norm($main) === $auth_norm) {
        $removed[] = ['title'=>$it['title'], 'author'=>$author, 'year'=>$it['year'], 'cover'=>$it['cover'],
                      'reason'=>'başlık = yazar adı'];
        continue;
    }
    if (preg_match('/\b(quotes?|quotations?|words of wisdom|sayings|aphorisms?)\b/i', $main)) {
        $removed[] = ['title'=>$it['title'], 'author'=>$author, 'year'=>$it['year'], 'cover'=>$it['cover'],
                      'reason'=>'alıntı/özlü söz derlemesi'];
        continue;
    }
    $guarded[] = $it;
}
$items_final = $guarded;

echo json_encode([
    'ok'      => true,
    'author'  => $author,
    'works'   => $items_final,
    'removed' => $removed,
    'ai_used' => $ai_used,
    'ai_err'  => $ai_err,
    'in'      => count($works),
    'out'     => count($items_final),
], JSON_UNESCAPED_UNICODE);
