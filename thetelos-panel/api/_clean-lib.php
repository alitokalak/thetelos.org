<?php
/**
 * _clean-lib.php — Yeniden kullanılabilir "yazar-yazar liste temizleme" çekirdeği.
 *
 * clean-list.php'deki (tek yazarlık) denetim mantığının SAF, çağrılabilir hâli.
 * Amaç: bir yazarın altında katalogtaki başlıkları alıp Claude'a denetletmek ve
 *   - aynı eserin çeviri/kopyalarını TEK gruba indirmek,
 *   - yazara ait olmayan / ikincil eserleri ayıklamak,
 *   - her grup için İngilizce kanonik adı + (varsa) orijinal adı + yılı vermek.
 *
 * KURAL ÖZÜ (clean-list.php ile birebir aynı prompt): "kelimeleri KİM yazdı?"
 *   KALIR: yazarın kendi mektup/konuşma/deneme/şiir/otobiyografi ve bunların
 *          bir editör/üniversite tarafından SONRADAN derlenmiş NAMED derlemeleri.
 *   ELENİR: yazar HAKKINDA ikincil eserler, alıntı derlemeleri, yayıncı külliyatı,
 *          başka birinin yazdığı/atfı yanlış eserler, tanınamayanlar.
 *
 * Toplu içerik üretiminde (batch) anthropic motoru seçiliyken, yazma başlamadan
 * ÖNCE bu fonksiyon çağrılır (batch-worker.php'nin temizleme fazı).
 */

if (!function_exists('cll_norm')) {
    function cll_norm($s) {
        $s = mb_strtolower(trim((string) $s), 'UTF-8');
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false && $t !== '') $s = $t;
        $s = preg_replace('/\([^()]*\)/', ' ', $s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', (string) $s));
    }
}

if (!function_exists('cll_looks_foreign')) {
    function cll_looks_foreign($s) {
        return (bool) (preg_match('/[àâäéèêëîïôöùûüçñáíóúãõœæ]/iu', $s)
            || preg_match('/(^|\s)(de la|de las|de los|de l\'|del|della|delle|degli|dei|di|le|les|la|el|il|un|une|des|du|von|vom|und|der|das|zur|zum|sur|aux|dans|nella|nel|för|van het|van de)(\s|$)/iu', mb_strtolower($s)));
    }
}

/**
 * Bir yazarın başlık listesini Claude ile denetle.
 *
 * @param string   $author  Yazar adı
 * @param string[] $titles  0-tabanlı başlık dizisi (yalnız başlık metni)
 * @param array    $opts    ['batch'=>bool, 'on_beat'=>callable, 'engine'=>'claude']
 * @return array {
 *   ok:bool,
 *   groups: [ ['en'=>str,'orig'=>str,'display'=>str,'year'=>str,'members'=>int[](0-tabanlı)] ],
 *   not_by_author: [ ['n'=>int(0-tabanlı),'reason'=>str] ],
 *   wrote_in: string[],
 *   error:str
 * }
 */
function cll_clean_author_ai($author, array $titles, array $opts = []) {
    require_once __DIR__ . '/_anthropic.php';
    $author = trim((string) $author);
    $titles = array_values($titles);
    $n = count($titles);
    if ($n === 0)  return ['ok' => true,  'groups' => [], 'not_by_author' => [], 'wrote_in' => [], 'error' => ''];
    if (!tls_anthropic_ready()) return ['ok' => false, 'groups' => [], 'not_by_author' => [], 'wrote_in' => [], 'error' => 'anthropic hazır değil'];

    $cap   = 120;                          // token güvenliği
    $slice = array_slice($titles, 0, $cap);
    $lines = '';
    foreach ($slice as $i => $t) {
        $lines .= ($i + 1) . '. ' . mb_substr((string) $t, 0, 160) . "\n";
    }

    // clean-list.php ile BİREBİR aynı sistem talimatı (prompt-cache'lenir).
    $system_rules = "You are a strict bibliographic judge and a meticulous cataloguer. "
        . "You are given a numbered list of titles all catalogued under ONE author (named in the user message as AUTHOR).\n"
        . "Your job: produce that author's clean canonical bibliography from these entries. Output ONLY valid JSON, nothing else.\n"
        . "STEP 0 — first determine which language(s) the author actually WROTE their works in, and return them in \"wrote_in\" "
        . "(e.g. Einstein → [\"German\",\"English\"]; Martin Buber → [\"German\",\"Hebrew\"]; Laozi → [\"Classical Chinese\"]). Every 'orig' you output must be in one of these languages.\n"
        . "REASON FROM THIS: a listed title that is NOT in one of the author's writing-languages and NOT in English is a TRANSLATION into some third language — it is NEVER the original. Example: Martin Buber wrote in German/Hebrew, so a Turkish title ('Yahudi yazarlar antolojisi'), a Spanish title ('Eclipse de Dios'), or an Italian title ('I racconti dei Chassidim') is a translated edition. You MUST resolve such a title to its English name (as 'en') with the German/Hebrew original in 'orig' — or, if you cannot identify the work, put it in not_by_author. It is a hard error to output a Turkish, Spanish, Italian, Czech, Portuguese, or any non-English title as the 'en' name.\n"
        . "OUTPUT CONTRACT — every entry number MUST appear in exactly one place: either in some group's members, or in not_by_author.\n"
        . "1) GROUP entries that are the SAME WORK (translations, different-language/script editions, transliterations, spelling variants, reprints) into ONE group. "
        . "A single-member group is normal for works appearing once. Be AGGRESSIVE: this list is full of duplicate editions and translations of a few real works — the number of groups you output should be MUCH SMALLER than the number of entries. Merge every edition/translation of the same work; never list the same work twice.\n"
        . "1b) POSTHUMOUS entries: a title published after the author's death may be EITHER a genuine posthumous original OR merely a later translation/edition of an existing work. Decide by the work's identity: if it is the same work as another entry (in any language), GROUP it as a translation — do NOT create a separate work for it. Only keep it separate if it is genuinely a distinct work the author wrote.\n"
        . "2) For EVERY group give:\n"
        . "   en   = MANDATORY. The work's title in ENGLISH — the established English-literature name if one exists (e.g. \"Tao Te Ching\", \"Critique of Pure Reason\", \"The Soul's Journey into God\" for Itinerarium Mentis in Deum). If NO established English name exists, give a faithful, natural English TRANSLATION of the title. NEVER leave 'en' blank and NEVER put a French/Latin/Italian/German/other non-English title in 'en'. The foreign form belongs ONLY in 'orig'.\n"
        . "   orig = the title in the language the work was ORIGINALLY WRITTEN in by the author. If the work was ORIGINALLY WRITTEN IN ENGLISH, leave orig EMPTY. Never copy a listed foreign EDITION title into orig unless it IS the language the author wrote in.\n"
        . "3) not_by_author — THE ONE TEST IS: WHO WROTE THE WORDS INSIDE THE BOOK?\n"
        . "   KEEP (put in a group — the AUTHOR wrote the words) — these are PRIMARY works and belong in the bibliography even when an editor, university, or publisher compiled, edited, selected, or published them AFTER the author's death: the author's own letters/correspondence, speeches/orations, essays, dialogues, treatises, sermons, poems, diaries, notebooks, autobiography/memoir, and any NAMED collection of the author's own such writings. A collection of the author's OWN letters or speeches is a genuine work — NEVER treat it as mere 'compilation' and NEVER eliminate it.\n"
        . "   ELIMINATE (words written by SOMEONE ELSE, or not a real single work): books ABOUT the author / secondary literature by others — biographies, critical studies, 'A Companion to X', 'The Thought/Politics/Ethics of X', 'X and the Y', 'X: A Life'; quote/aphorism collections; generic publisher OMNIBUS containers ('Complete Works', 'Collected Works', 'Selected Writings'); titles that are just the author's name; works PROVEN to be by someone else; entries you cannot identify at all. Short reason each.\n"
        . "   DISTINGUISH CAREFULLY by authorship, not keywords: 'Cicero's Letters to his Friends' (Cicero's OWN letters → KEEP) vs 'Cicero and his Friends' by Gaston Boissier (a study ABOUT him → ELIMINATE). A memoir the author wrote is KEEP; a biography others wrote is ELIMINATE.\n"
        . "4) ERA CHECK — entries chronologically or thematically IMPOSSIBLE for this author (likely a different person with the same name) MUST be flagged with reason \"implausible for this author\".\n"
        . "5) For each group also give year = the work's ORIGINAL first-publication/composition year as an integer if you know it — NOT a modern reprint year; empty string if unknown.\n"
        . "Rules: judge ONLY the given entries; do NOT invent extra works; when unsure about a plausible entry, keep it as its own group.\n"
        . "Return ONLY JSON:\n"
        . "{\"wrote_in\":[\"German\",\"English\"],\"groups\":[{\"en\":\"English title\",\"orig\":\"Original title or empty\",\"year\":\"1687\",\"members\":[1,4]}],\"not_by_author\":[{\"n\":3,\"reason\":\"short reason\"}]}";

    $user_msg = "AUTHOR: {$author}\n\nEntries catalogued under this author:\n" . $lines;

    // Model: zor liste (uzun / farklı alfabe / yabancı-Latin başlık) → Opus, aksi Sonnet.
    $titles_txt = ' ' . implode(' ', $slice);
    $foreign_latin = preg_match('/[àâäéèêëîïôöùûüçñáíóúãõœæ]/iu', $titles_txt)
        || preg_match('/(^|\s)(de la|de l\'|del|della|delle|di|le|les|la|el|il|une|des|du|von|vom|und|der|das|sur|aux|dans)(\s|$)/iu', mb_strtolower($titles_txt));
    $hard = (count($slice) > 8) || $foreign_latin
        || preg_match('/[\x{0370}-\x{03FF}\x{0400}-\x{04FF}\x{0590}-\x{05FF}\x{0600}-\x{06FF}\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}]/u', $titles_txt);
    $model = $hard ? tls_claude_best_model() : tls_claude_quality_model();

    $cr = tls_claude($system_rules, $user_msg, [
        'model'       => $model,
        'max_tokens'  => 8000,
        'temperature' => 0,
        'timeout'     => 180,
        'retries'     => 2,
        'cache'       => true,
        'batch'       => !empty($opts['batch']),
        'on_beat'     => (isset($opts['on_beat']) && is_callable($opts['on_beat'])) ? $opts['on_beat'] : null,
    ]);
    if (empty($cr['ok'])) {
        return ['ok' => false, 'groups' => [], 'not_by_author' => [], 'wrote_in' => [],
                'error' => 'Claude: ' . mb_substr((string) ($cr['error'] ?? '?'), 0, 120)];
    }

    $txt = (string) $cr['text'];
    $txt = preg_replace('/```json|```/i', '', $txt);
    $s = strpos($txt, '{'); $e = strrpos($txt, '}');
    $parsed = ($s !== false && $e > $s) ? json_decode(substr($txt, $s, $e - $s + 1), true) : null;
    if (!is_array($parsed)) {
        return ['ok' => false, 'groups' => [], 'not_by_author' => [], 'wrote_in' => [], 'error' => 'JSON çözümlenemedi'];
    }

    // not_by_author (1-tabanlı → 0-tabanlı)
    $flagged = [];
    foreach ($parsed['not_by_author'] ?? [] as $f) {
        $ix = (int) ($f['n'] ?? 0) - 1;
        if ($ix >= 0 && $ix < count($slice)) $flagged[$ix] = trim((string) ($f['reason'] ?? 'yazara ait değil'));
    }

    $used   = [];
    $groups = [];
    foreach ($parsed['groups'] ?? [] as $g) {
        $members = array_values(array_filter(
            array_map(fn($m) => (int) $m - 1, (array) ($g['members'] ?? [])),
            fn($ix) => $ix >= 0 && $ix < count($slice) && !isset($flagged[$ix]) && !isset($used[$ix])
        ));
        if (empty($members)) continue;
        foreach ($members as $ix) $used[$ix] = true;

        $en   = trim((string) ($g['en']   ?? ''));
        $orig = trim((string) ($g['orig'] ?? ''));
        if ($en === '' || !preg_match('/\p{Latin}/u', $en) || cll_looks_foreign($en)) {
            // İngilizce ad çözülemedi → eseri KAYBETME: ilk üyenin ham başlığını kullan.
            $en = (string) $slice[$members[0]];
        }
        $display = ($orig !== '' && mb_strtolower($orig) !== mb_strtolower($en)) ? "$en ($orig)" : $en;
        $ai_year = trim((string) ($g['year'] ?? ''));
        $year = preg_match('/^\d{1,4}$/', $ai_year) ? $ai_year : '';

        $groups[] = ['en' => $en, 'orig' => $orig, 'display' => $display, 'year' => $year, 'members' => $members];
    }

    // Gruba girmemiş ve işaretlenmemiş girişler → kendi başına tek üyeli grup (dokunma).
    foreach ($slice as $ix => $t) {
        if (isset($used[$ix]) || isset($flagged[$ix])) continue;
        $groups[] = ['en' => (string) $t, 'orig' => '', 'display' => (string) $t, 'year' => '', 'members' => [$ix]];
    }

    // Cap dışında kalan (>120) başlıklar: dokunma, tek üyeli grup.
    for ($ix = $cap; $ix < $n; $ix++) {
        $groups[] = ['en' => (string) $titles[$ix], 'orig' => '', 'display' => (string) $titles[$ix], 'year' => '', 'members' => [$ix]];
    }

    $flags = [];
    foreach ($flagged as $ix => $reason) $flags[] = ['n' => $ix, 'reason' => $reason];

    return [
        'ok'            => true,
        'groups'        => $groups,
        'not_by_author' => $flags,
        'wrote_in'      => array_values((array) ($parsed['wrote_in'] ?? [])),
        'error'         => '',
    ];
}
