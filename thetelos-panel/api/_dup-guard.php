<?php
/**
 * _dup-guard.php — YENİ yazı açmadan önce "bu kitap sitede zaten var mı?" denetimi.
 *
 * Eski denetim yalnız birebir slug'a bakıyordu ("Kitap - Yazar"). Çeviri baskısı,
 * farklı ad, alt başlıklı/alt başlıksız yazım, başka dilde derleme ("Kuinai jing ji
 * zhu zuo xuan ji" = Quesnay'ın eserleri) bu yüzden ikinci sayfa olarak açılıyordu.
 *
 * Yeni sıra:
 *  1) Sitede AYNI YAZARIN tüm yazıları toplanır (başlıktaki " – Yazar" kısmından).
 *  2) Kodla kesin eşleşme: normalize başlık / parantezdeki orijinal ad / alt başlık
 *     farkı / %90+ benzerlik → aynı kitap.
 *  3) Kod karar veremezse DeepSeek'e (ucuz, düşünmesiz) liste verilir: "yeni başlık
 *     bunlardan birinin çevirisi, farklı adı, baskısı ya da aynı derlemesi mi?"
 *     Aynıysa yeni yazı AÇILMAZ.
 */

require_once __DIR__ . '/_work-rules.php';
require_once __DIR__ . '/_verify.php';

/** Başlık/yazar karşılaştırma anahtarı (küçük harf, ASCII, yalnız harf-rakam). */
function dg_key($s) {
    $s = html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = preg_replace('/[\x{2018}\x{2019}\x{02BC}\x{0027}\x{0060}]/u', '', $s);
    $x = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($x !== false && $x !== '') $s = $x;
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

/** Yazarın soyadı anahtarı (son anlamlı kelime). */
function dg_last_name($author) {
    $t = array_values(array_filter(explode(' ', dg_key($author)), fn($w) => strlen($w) >= 2));
    if (!$t) return '';
    $last = end($t);
    // "Jr", "II" gibi ekler soyadı değildir
    if (in_array($last, ['jr', 'sr', 'ii', 'iii', 'iv'], true) && count($t) > 1) $last = $t[count($t) - 2];
    return strlen($last) >= 3 ? $last : '';
}

/** Site başlığını [kitap, yazar] olarak ayır: "Kitap (Orijinal) – Yazar". */
function dg_split_title($title) {
    $t = trim(html_entity_decode((string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (preg_match('/^(.*)\s[–—-]\s([^–—]+)$/u', $t, $m)) return [trim($m[1]), trim($m[2])];
    return [$t, ''];
}

/** Bir kitap başlığının karşılaştırılacak biçimleri: parantezsiz ad + parantezdeki orijinal ad. */
function dg_variants($book) {
    $book = trim(html_entity_decode((string) $book, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $out = [];
    $base = trim(preg_replace('/\s*\([^()]*\)\s*/u', ' ', $book));
    if ($base !== '') $out[] = $base;
    if (preg_match_all('/\(([^()]+)\)/u', $book, $m)) foreach ($m[1] as $in) { $in = trim($in); if (mb_strlen($in) >= 3) $out[] = $in; }
    if (!$out) $out[] = $book;
    return array_values(array_unique($out));
}

/** Başlık İngilizce dışı mı? (Latin dışı alfabe ya da başka dilin işaret/durak kelimesi) */
function dg_foreign($t) {
    $t = trim(preg_replace('/\s*\([^()]*\)\s*/u', ' ', html_entity_decode((string) $t, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($t === '') return false;
    if (wr_script_langs($t) !== null || (bool) wr_guess_langs($t)) return true;
    // İngilizce dışı Latin harfi (ė, ō, ē, é…) → transliterasyon/yabancı dil
    if (preg_match('/[^\x00-\x7F]/u', preg_replace('/[\x{2018}\x{2019}\x{201C}\x{201D}\x{2013}\x{2014}\x{2026}]/u', '', $t))) return true;
    $words = array_values(array_filter(explode(' ', dg_key($t))));
    if (array_intersect($words, ['avec','ses','sur','und','oder','sobre','sulla','nella','dello','och','och','ob','pro','kak','ili','zur'])) return true;
    // Pinyin (Çince transliterasyon): kelimelerin çoğu pinyin hecesi
    if (count($words) >= 3) {
        $py = 0;
        foreach ($words as $w) if (preg_match('/^(?:zh|ch|sh|[bpmfdtnlgkhjqxrzcsyw])?(?:a|o|e|i|u|v|ai|ei|ui|ao|ou|iu|ie|ue|er|an|en|in|un|ang|eng|ing|ong|ia|iao|ian|iang|iong|ua|uo|uai|uan|uang)$/', $w)) $py++;
        if ($py / count($words) >= 0.7) return true;
    }
    return false;
}

/** İki İngilizce başlığın anlamlı kelime örtüşmesi (kısa olanın kelimelerine oranla). */
function dg_overlap($a, $b) {
    $stop = ['the','and','of','in','on','to','for','with','from','an','a','is','its','his','her','their','by','at','as','or','into','what','how','why','about','vol','volume','part','book'];
    $w = function ($s) use ($stop) {
        $k = dg_key(preg_replace('/\s*\([^()]*\)\s*/u', ' ', (string) $s));
        return array_values(array_unique(array_filter(explode(' ', $k), fn($x) => strlen($x) >= 3 && !in_array($x, $stop, true))));
    };
    $A = $w($a); $B = $w($b);
    if (!$A || !$B) return 0.0;
    return count(array_intersect($A, $B)) / min(count($A), count($B));
}

/** Kodla kesin "aynı eser" kararı. */
function dg_same_by_rules($new_book, $old_book) {
    foreach (dg_variants($new_book) as $a) {
        foreach (dg_variants($old_book) as $b) {
            $ka = dg_key($a); $kb = dg_key($b);
            if ($ka === '' || $kb === '') continue;
            if (str_replace(' ', '', $ka) === str_replace(' ', '', $kb)) return true;
            if (wr_same_work($a, $b)) return true;
            $na = wr_norm($a); $nb = wr_norm($b);
            if (min(strlen($na), strlen($nb)) >= 8) {
                similar_text($na, $nb, $p);
                if ($p >= 90) return true;
            }
        }
    }
    return false;
}

/**
 * Sitede aynı kitap var mı?
 * Dönüş: null (yok) | ['id'=>int, 'title'=>string, 'link'=>string, 'why'=>string]
 * $wp_get: function(string $path_and_query): [array|null $json, int $http]
 */
function dg_find_duplicate($book, $author, callable $wp_get, $ep = 'posts', &$diag = '') {
    $book = trim((string) $book); $author = trim((string) $author);
    $diag = '';
    if ($book === '') return null;
    $last = dg_last_name($author);
    if ($last === '') { $diag = 'yazar soyadı yok → yalnız slug denetimi'; return null; }

    // 1) Aynı yazarın sitedeki yazıları (taslak + zamanlanmış dahil).
    $cands = [];
    $seen_q = [];
    foreach (array_unique([$author, $last, implode(' ', array_slice(explode(' ', dg_key($book)), 0, 4))]) as $q) {
        $q = trim(preg_replace('/[\x{2018}\x{2019}\x{02BC}\x{0027}\x{0060}]/u', '', (string) $q));
        if ($q === '' || isset($seen_q[$q])) continue;
        $seen_q[$q] = 1;
        for ($page = 1; $page <= 3; $page++) {
            [$r, $c] = $wp_get("$ep?search=" . rawurlencode($q) . "&status=any&per_page=100&page=$page&_fields=id,title,link");
            if ($c !== 200 || !is_array($r) || !$r) break;
            foreach ($r as $p) {
                $id = (int) ($p['id'] ?? 0);
                if (!$id || isset($cands[$id])) continue;
                [$bk, $au] = dg_split_title($p['title']['rendered'] ?? ($p['title'] ?? ''));
                if ($au === '' || strpos(' ' . dg_key($au) . ' ', ' ' . $last . ' ') === false) continue;
                $cands[$id] = ['id' => $id, 'book' => $bk, 'author' => $au, 'link' => (string) ($p['link'] ?? '')];
            }
            if (count($r) < 100) break;
        }
    }
    if (!$cands) { $diag = 'yazarın sitede yazısı yok'; return null; }

    // 2) Kodla kesin eşleşme.
    foreach ($cands as $c) {
        if (dg_same_by_rules($book, $c['book'])) {
            return ['id' => $c['id'], 'title' => $c['book'] . ' – ' . $c['author'], 'link' => $c['link'], 'why' => 'aynı başlık (yazım farkı)'];
        }
    }

    // 3) DeepSeek: çeviri / farklı ad / aynı derleme mi?
    // YANLIŞ POZİTİF KORUMASI (19.11.2026: Mayr'ın "One Long Argument", "What Evolution Is"
    // gibi FARKLI kitapları "aynı" sayılıp atlanmıştı): DeepSeek'e yalnız GERÇEKTEN aynı
    // olabilecek adaylar sorulur — başlıklardan biri yabancı dilde (çeviri ihtimali) ya da
    // iki İngilizce başlık anlamlı kelimelerin çoğunu paylaşıyor. İki farklı İngilizce
    // başlık ("Animal Species and Evolution" / "Systematics and the Origin of Species")
    // DeepSeek'e hiç sorulmaz.
    $new_foreign = dg_foreign($book);
    $list = array_values(array_filter($cands, function ($c) use ($book, $new_foreign) {
        return $new_foreign || dg_foreign($c['book']) || dg_overlap($book, $c['book']) >= 0.6;
    }));
    if (!$list) { $diag = count($cands) . ' aday, hiçbiri benzer/çeviri değil'; return null; }
    if (count($list) > 120) $list = array_slice($list, 0, 120);
    $lines = '';
    foreach ($list as $i => $c) $lines .= ($i + 1) . '. ' . $c['book'] . "\n";
    $prompt = "A book site already has these works by $author:\n$lines\n"
        . "NEW candidate: \"$book\" by $author.\n\n"
        . "Is the NEW candidate the SAME work as one of the listed items? Count as SAME: a translation of it "
        . "(title in another language or transliterated), an alternate/English/original-language title, a "
        . "different edition, an abridged or unabridged version, a subtitle variant, or a collection with "
        . "essentially the same contents (e.g. 'Complete Economic Writings' vs 'Economic Works'). Count as "
        . "DIFFERENT: a genuinely different book, a different volume/part of a multi-volume work, a sequel, "
        . "or a different work on a similar subject. Sharing a topic, words or the same author does NOT make "
        . "two books the same. Answer SAME only if you are certain; when in doubt answer 0. Answer with JSON "
        . "only: {\"match\": <item number or 0>, \"reason\": \"<max 12 words>\"}";
    $prev_think = $GLOBALS['ds_think'] ?? null; $GLOBALS['ds_think'] = false;
    // Yalnız Claude modunda soru da Claude Haiku'ya gider (kısa, tek istek); aksi halde DeepSeek.
    $use_claude = !empty(tv_settings()['claude_only']);
    $prev_nc = $GLOBALS['tls_no_claude'] ?? null;
    if ($use_claude) $GLOBALS['tls_no_claude'] = false;
    $a = tv_ask($prompt, 200, 60, $use_claude ? 'anthropic' : 'deepseek');
    $GLOBALS['tls_no_claude'] = $prev_nc;
    $GLOBALS['ds_think'] = $prev_think;
    $j = !empty($a['ok']) ? tv_json($a['text'] ?? '') : null;
    if (!is_array($j)) { $diag = 'DeepSeek yanıt vermedi → yalnız kod denetimi'; return null; }
    $n = (int) ($j['match'] ?? 0);
    if ($n >= 1 && $n <= count($list)) {
        $c = $list[$n - 1];
        return ['id' => $c['id'], 'title' => $c['book'] . ' – ' . $c['author'], 'link' => $c['link'],
                'why' => ($use_claude ? 'Claude: ' : 'DeepSeek: ') . mb_substr(trim((string) ($j['reason'] ?? 'aynı eser')), 0, 90)];
    }
    $diag = count($list) . ' aday, eşleşme yok';
    return null;
}
