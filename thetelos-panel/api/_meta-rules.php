<?php
/**
 * _meta-rules.php — Meta açıklama ve özet (excerpt) için TEK KURAL.
 *
 * KURAL: en fazla 150 karakter, TEK ve TAM bir cümle, noktayla biter.
 * Model kurala uymayan bir şey döndürürse o cevap KULLANILMAZ — model aynı işi
 * tekrar yapar. Kod hiçbir zaman metni kırpıp sonuna nokta EKLEMEZ.
 *
 * Eski yarım açıklamaların asıl sebebi: DeepSeek V4'te "düşünme" varsayılan açık;
 * düşünme cevap bütçesini (max_tokens) yiyip cümleyi yarıda bırakıyordu. Burada
 * düşünme KAPALI, bütçe geniş, ve kesilen cevap (finish_reason=length) reddedilir.
 */

if (!function_exists('tls_meta_valid')) {

/* Kurala uyuyor mu? */
function tls_meta_valid($t, $max = 150) {
    $t = trim((string) $t);
    if (mb_strlen($t) < 40 || mb_strlen($t) > $max) return false;
    if (!preg_match('/[.!?]["”’)\']?$/u', $t)) return false;                      // noktayla bitmeli
    if (preg_match('/\b(the|a|an|of|and|or|but|to|with|for|in|on|at|by|its|their|his|her|as|that|which|from|into|than|this|these|whose|between|about)[.!?]["”’)\']?$/iu', $t)) return false;   // "…of the." yarım
    if (preg_match('/[.!?]\s+\S/u', rtrim($t, '.!?"”’)\''))) return false;        // tek cümle olmalı
    if (preg_match('/\.\.\.|…/u', $t)) return false;                                // üç nokta = kesik
    return true;
}

/* Kurala uyan cümle üret (en fazla 5 deneme). Uyan yoksa '' döner — asla bozuk metin değil. */
function tls_meta_generate($book, $author, $text, $kind = 'meta', $avoid = '') {
    if (!defined('DEEPSEEK_KEY') || !DEEPSEEK_KEY) return '';
    $snip = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text))), 0, 1600);
    if ($snip === '') return '';
    $label = ($kind === 'excerpt') ? 'excerpt (a short teaser shown on listing pages)' : 'meta description (shown in Google results)';
    $model = (defined('DEEPSEEK_MODEL') && DEEPSEEK_MODEL && !in_array(DEEPSEEK_MODEL, ['deepseek-chat', 'deepseek-reasoner'], true)) ? DEEPSEEK_MODEL : 'deepseek-flash';
    $prev = '';
    for ($try = 0; $try < 5; $try++) {
        $prompt = "Write the {$label} for an article about the book \"{$book}\"" . ($author !== '' ? " by {$author}" : '') . ".\n"
            . "STRICT RULES:\n- Exactly ONE complete English sentence.\n- At most 150 characters including spaces (aim for 110-145).\n"
            . "- It must end with a period and must not be cut off.\n- Use only facts from the text below. No quotes around it.\n"
            . ($avoid !== '' ? "- It must be different from this sentence: \"{$avoid}\"\n" : '')
            . ($prev !== '' ? "Your previous answer broke the rules (" . mb_strlen($prev) . " characters or unfinished): \"{$prev}\". Write a new one that follows them.\n" : '')
            . "Output ONLY the sentence.\n\n=== TEXT ===\n{$snip}";
        $ch = curl_init(DEEPSEEK_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model, 'max_tokens' => 600, 'temperature' => 0.3,
                'thinking' => ['type' => 'disabled'],
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ], JSON_UNESCAPED_UNICODE),
        ]);
        $r = curl_exec($ch); curl_close($ch);
        $j = json_decode((string) $r, true);
        $c = $j['choices'][0] ?? [];
        $out = trim(preg_replace('/\s+/u', ' ', (string) ($c['message']['content'] ?? '')), " \t\n\"'“”");
        if (($c['finish_reason'] ?? '') === 'length') { $prev = $out; continue; }   // kesilmiş cevap → reddet
        if (tls_meta_valid($out) && ($avoid === '' || $out !== $avoid)) return $out;
        $prev = $out;
    }
    return '';
}

}
