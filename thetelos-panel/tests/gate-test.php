<?php
/**
 * gate-test.php — Yayın kapısının API'siz sınanabilen kısmı.
 *
 * Kapının en tehlikeli iki hatası:
 *   1. YANLIŞ GEÇİRMEK — kusurlu metni yayına salmak (asıl kaza).
 *   2. YANLIŞ DURDURMAK — sağlam metni taslakta hapsetmek (üretim durur).
 *
 * API çağrısı gerektiren katman (tv_probe / tv_factcheck) burada sınanamaz;
 * onun yerine JSON ayrıştırma, Open Library çapraz kontrol mantığı ve
 * mekanik kapı kararları sınanır. Bunlar kapının kararını veren kısımlardır.
 *
 * Çalıştırma:  php thetelos-panel/tests/gate-test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../api/_checks.php';

/* _verify.php config sabitlerini ister; yalnızca sınanan işlevler alınır. */
$src = file_get_contents(__DIR__ . '/../api/_verify.php');
foreach (['tv_json'] as $fn) {
    preg_match('/function ' . $fn . '\(.*?\n}\n/s', $src, $m);
    eval($m[0]);
}

/* tv_gate'in mekanik yarısı: ayar dosyası ve API olmadan çalışsın diye
   factcheck kapalı varsayılır. Karar mantığı birebir aynı. */
function tv_settings() { return ['probe'=>false,'factcheck'=>false,'min_conf'=>55,'gate'=>true]; }
preg_match('/function tv_gate\(.*?\n}\n/s', $src, $m);
eval($m[0]);

$ok = 0; $bad = 0;
function chk($label, $got, $expect, $detail = '') {
    global $ok, $bad;
    $pass = ($got === $expect);
    $pass ? $ok++ : $bad++;
    printf("%s  %-46s %s\n", $pass ? '✓' : '✗ HATA', $label, $detail);
}

/* ── JSON ayrıştırma ──────────────────────────────────────────────────────
   Model JSON'u ```json bloğuna sarabiliyor ya da önüne bir cümle koyabiliyor.
   Ham json_decode bunlarda null döner ve doğrulama sessizce "sonuçsuz"
   sayılırdı — yani kapı fiilen kapanırdı. */
echo "── JSON AYRIŞTIRMA ──\n";
chk('düz JSON',        tv_json('{"verdict":"ok"}')['verdict'] ?? '', 'ok');
chk('```json bloğu',   tv_json("```json\n{\"verdict\":\"wrong\"}\n```")['verdict'] ?? '', 'wrong');
chk('``` (dilsiz)',    tv_json("```\n{\"verdict\":\"suspect\"}\n```")['verdict'] ?? '', 'suspect');
chk('önünde cümle',    tv_json("Here is the result:\n{\"verdict\":\"ok\"}")['verdict'] ?? '', 'ok');
chk('sonunda cümle',   tv_json("{\"verdict\":\"ok\"}\nLet me know if you need more.")['verdict'] ?? '', 'ok');
chk('JSON yok',        tv_json('sorry, I cannot answer'), null);

/* ── Mekanik kapı ─────────────────────────────────────────────────────── */
echo "\n── KAPI: KUSURLUYU DURDUR ──\n";
$saglam = '<h2>The Argument</h2>' . str_repeat(
    '<p>Peter grows up in the Swiss village of Nimikon, among mountains that shape his sense of scale and solitude. ' .
    'The narrative follows his departure, his years in the city, and his return to the land he came from.</p>', 45);

function gated($html, $book = 'Peter Camenzind', $author = 'Hermann Hesse') {
    $g = tv_gate($book, $author, $html, ['min_words' => 800, 'skip_factcheck' => true]);
    return !$g['pass'];
}

chk('üretim reddi (baş)',  gated('<p>I cannot produce this summary because I do not have access to the actual text.</p>' . $saglam), true);
// ÇOK PARÇALI ÜRETİM: 1. parça yazılmış, 3. parça reddetmiş → ret ORTADA.
chk('üretim reddi (ortada)',gated($saglam . '<p>I cannot produce this summary because I do not have access to the actual text.</p>' . $saglam), true);
chk('CANNOT VERIFY (yeni)',gated('<p>CANNOT VERIFY: I do not know this specific work by this author.</p>'), true);
chk('parça işareti',       gated($saglam . '<p>%%PART_END%%</p>'), true);
chk('prompt talimatı',     gated($saglam . '<p>*Here the work ends. No summary, no closing paragraph.*</p>'), true);
chk('model süreç konuşması',gated($saglam . '<p>I will now continue with Part 3 of the summary.</p>'), true);
chk('boş başlıkla bitmiş', gated($saglam . '<h3>Chapter Nine: The Return</h3>'), true);
chk('cümle ortasında kesik',gated($saglam . '<p>And then Peter turned toward the</p>'), true);
chk('çok kısa',            gated('<p>' . str_repeat('word ', 120) . '</p>'), true);

echo "\n── KAPI: SAĞLAMI GEÇİR ──\n";
chk('düzgün uzun metin',   gated($saglam), false);
chk('alıntıyla biten',     gated($saglam . '<blockquote>“We will now proceed to refute these heretics.”</blockquote><p>The chapter closes there.</p>'), false);
chk('bölüm başlığı + metin',gated($saglam . '<h3>Part Four: The Discipline of Tomorrow</h3><p>The final movement returns to the village, and the book ends where it began.</p>'), false);
$edebi = '<p>' . str_repeat('In the final letter the narrator confesses that i cannot write any longer, and the sentence hangs there unfinished while the correspondence continues around it. ', 6) . '</p>';
chk('uzun edebî paragraf (ret değil)', gated($saglam . $edebi), false);
chk('alıntıdaki ret ifadesi',          gated($saglam . '<blockquote>“I cannot write this. I do not have the text before me.”</blockquote><p>The refusal is the point of the chapter.</p>'), false);

/* ── Kapı SEBEBİ söylemeli ────────────────────────────────────────────────
   "Yayınlanmadı" demek yetmez: hangi kusur yüzünden durduğu yazılmazsa
   kullanıcı taslaklar arasında kör kalır. */
echo "\n── KAPI SEBEBİ ──\n";
$g = tv_gate('X', 'Y', $saglam . '<p>%%PART_END%%</p>', ['min_words' => 800, 'skip_factcheck' => true]);
chk('sebep listesi dolu',  count($g['reasons']) > 0, true, $g['reasons'] ? $g['reasons'][0] : '');
chk('rapor sebebi taşıyor', isset($g['report']['reasons']), true);

echo "\nSonuç: {$ok} geçti, {$bad} hata\n";
exit($bad ? 1 : 0);
