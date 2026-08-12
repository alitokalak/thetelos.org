<?php
/**
 * _referee.php — Kaynağa-sadakat HAKEMİ (bağımsız uydurma denetimi).
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * NEDEN VAR
 * ═══════════════════════════════════════════════════════════════════════════
 * Metni DeepSeek yazıyor. DeepSeek'in kendi metnini denetlemesi anlamsız (aynı
 * kör noktalar). Bu yüzden BAĞIMSIZ bir hakem, üretilen makaleyi topladığımız
 * KAYNAKLARLA kıyaslar: "kaynakta olmayan / kaynağa çelişen, uydurulmuş özgül
 * iddia var mı?" Hakemin kitabı tanımasına gerek yok — yalnız iki metni kıyaslar.
 *
 * KADEMELİ (kullanıcının seçimi): önce GEMINI (ucuz) denetler. "temiz" derse
 * ona güvenilir. "uydurma / emin değilim" derse CLAUDE'a (güçlü, bağımsız)
 * yükseltilir; Claude'un kararı NİHAİDİR. Böylece hem ucuz hem yanlış-pozitif
 * riski düşük.
 *
 * DÖNÜŞ: ['verdict'=>'clean'|'fabricated'|'unsure'|'skip', 'problems'=>[...],
 *         'judge'=>'gemini'|'anthropic'|'']
 *   clean      → kaynağa sadık, yayınla.
 *   fabricated → uydurma özgül iddia(lar) var → YAYINLAMA.
 *   unsure     → hakem karar veremedi (kaynak çok ince) → çağıran taraf karar verir.
 *   skip       → hakem çalışmadı (anahtar yok / kaynak yok) → engelleme.
 */

if (!defined('TLS_REFEREE_LOADED')) {
define('TLS_REFEREE_LOADED', 1);

require_once __DIR__ . '/_verify.php';   // tv_ask, tv_json

/** Hakem istemi — yalnız UYDURULMUŞ özgül iddiaları işaretle; genel bilinen
    gerçekleri, yorumu, tema tartışmasını İŞARETLEME. */
function tls_referee_prompt($book, $author, $article, $dossier) {
    $article = mb_substr(trim((string) $article), 0, 9000);
    $dossier = mb_substr(trim((string) $dossier), 0, 12000);
    return <<<TXT
You are a strict fact-checking REFEREE. You are given (A) an ARTICLE about a book and (B) VERIFIED SOURCE MATERIAL about that same book.

Your ONLY job: decide whether the article FABRICATES. Fabrication = specific, checkable claims that CONTRADICT the sources, OR the kind of specific detail nobody could state without a source — exact quotations, invented chapter titles, specific plot events, character names, precise dates, or statistics — that are ABSENT from the sources.

Do NOT flag: general well-known facts, reasonable interpretation, thematic discussion, or an author's widely-known ideas, even if not literally in the sources. Only flag concrete INVENTED or CONTRADICTING specifics.

Return ONLY JSON, nothing else:
{"verdict":"clean|fabricated|unsure","problems":["short quote/description of each fabricated or contradicting claim (max 5)"]}

verdict meaning:
- "clean": no fabrication; the article is faithful to what can be verified.
- "fabricated": one or more invented/contradicting specific claims are present.
- "unsure": the sources are too thin to judge, or it is genuinely ambiguous.

BOOK: "{$book}" by {$author}

=== ARTICLE ===
{$article}
=== END ARTICLE ===

=== VERIFIED SOURCE MATERIAL ===
{$dossier}
=== END SOURCE MATERIAL ===
TXT;
}

/** Tek hakem çağrısı → ['verdict'=>..., 'problems'=>[...]] veya verdict 'error'. */
function tls_referee_ask($prompt, $provider) {
    $r = tv_ask($prompt, 800, 120, $provider);
    if (empty($r['ok'])) return ['verdict' => 'error', 'problems' => []];
    $j = tv_json($r['text']);
    if (!is_array($j) || empty($j['verdict'])) {
        // JSON çıkmadıysa metinden kaba çıkarım (yine de kilitlenme).
        $t = mb_strtolower((string) $r['text'], 'UTF-8');
        if (strpos($t, 'fabricat') !== false)      return ['verdict' => 'fabricated', 'problems' => []];
        if (strpos($t, 'clean') !== false)          return ['verdict' => 'clean', 'problems' => []];
        return ['verdict' => 'error', 'problems' => []];
    }
    $v = strtolower(trim((string) $j['verdict']));
    if (!in_array($v, ['clean', 'fabricated', 'unsure'], true)) $v = 'unsure';
    $probs = [];
    if (!empty($j['problems']) && is_array($j['problems'])) {
        foreach ($j['problems'] as $p) { $p = trim((string) $p); if ($p !== '') $probs[] = mb_substr($p, 0, 200); }
    }
    return ['verdict' => $v, 'problems' => array_slice($probs, 0, 5)];
}

/**
 * Kademeli hakem.
 * @param array $opts primary(='gemini'), escalate(='anthropic')
 */
function tls_referee($article, $dossier, $book, $author, $opts = []) {
    $article = trim((string) $article);
    $dossier = trim((string) $dossier);
    // Kaynak yoksa kaynağa-sadakat kıyası yapılamaz → hakem devre dışı.
    if ($article === '' || $dossier === '') return ['verdict' => 'skip', 'problems' => [], 'judge' => ''];

    // Hazır sağlayıcıları çöz.
    require_once __DIR__ . '/_gemini.php';
    require_once __DIR__ . '/_anthropic.php';
    $primary  = $opts['primary']  ?? 'gemini';
    $escalate = $opts['escalate'] ?? 'anthropic';
    $ready = function ($p) {
        if ($p === 'gemini')    return tls_gemini_ready();
        if ($p === 'anthropic') return tls_anthropic_ready();
        return false;   // deepseek hakem olmaz (bağımsız değil)
    };
    // Birincil hazır değilse yükseltmeyi birincil yap.
    if (!$ready($primary)) { $primary = $escalate; $escalate = ''; }
    if ($primary === '' || !$ready($primary)) return ['verdict' => 'skip', 'problems' => [], 'judge' => ''];
    if ($escalate === $primary || !$ready($escalate)) $escalate = '';

    $prompt = tls_referee_prompt($book, $author, $article, $dossier);

    // 1) Birincil (Gemini) denetler.
    $v = tls_referee_ask($prompt, $primary);
    if ($v['verdict'] === 'clean') return $v + ['judge' => $primary];

    // 2) Şüphe/uydurma/hata → güçlü hakeme (Claude) yükselt; onun kararı nihai.
    if ($escalate !== '') {
        $v2 = tls_referee_ask($prompt, $escalate);
        if ($v2['verdict'] !== 'error') return $v2 + ['judge' => $escalate];
    }
    // Yükseltme yoksa/başarısızsa birincilin kararı (error → unsure'a indir).
    if ($v['verdict'] === 'error') $v['verdict'] = 'unsure';
    return $v + ['judge' => $primary];
}

} // TLS_REFEREE_LOADED
