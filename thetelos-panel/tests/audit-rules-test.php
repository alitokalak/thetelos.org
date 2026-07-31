<?php
/**
 * audit-rules-test.php — İçerik denetimi kurallarının regresyon testi.
 *
 * Denetimin en büyük riski YANLIŞ ALARM: sistem olmayan sorunu gösterirse
 * hem gerçek sorunlar gürültüde kaybolur hem de onarım sağlam metni bozar.
 * Bu test, kuralları hem gerçek kitap metniyle (işaretlenmemeli) hem gerçek
 * sızıntılarla (işaretlenmeli) sınar.
 *
 * Çalıştırma:  php thetelos-panel/tests/audit-rules-test.php
 * Yeni bir kalıp eklerken önce buraya bir vaka ekle.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // web'den erişilemez

function wp_strip_all_tags($s){ return trim(strip_tags($s)); }
$src = file_get_contents(__DIR__ . '/../api/content-audit.php');
foreach (['ca_strip_quotes','ca_is_quotation','ca_blocks','ca_meta_patterns','ca_check_refusal',
          'ca_check_part_markers','ca_is_meta_block','ca_check_prompt_leak','ca_check_meta_talk',
          'ca_delete_patterns','ca_leak_line','ca_check_orphan_heading','ca_check_truncated',
          'ca_check_wrapup','ca_check_dup_heading','ca_repair_too_lossy','ca_repair'] as $fn) {
    preg_match('/function '.$fn.'\(.*?\n}\n/s', $src, $m); eval($m[0]);
}

$ok = 0; $bad = 0;
function t($label, $html, $expect) {
    global $ok, $bad;
    $leak = ca_check_prompt_leak($html);
    $meta = ca_check_meta_talk($html);
    $part = ca_check_part_markers($html);
    $flagged = ($leak || $meta || $part);
    $pass = ($flagged === $expect);
    $pass ? $ok++ : $bad++;
    printf("%s  %-42s %s\n", $pass ? '✓' : '✗ HATA', $label,
        $flagged ? ('→ ' . trim($leak ?: ($meta ?: $part))) : '→ temiz');
}

echo "── GERÇEK İÇERİK (işaretlenmemeli) ──\n";
t('Irenaeus alıntısı (blockquote)', '<blockquote>“We will now proceed to refute these heretics, and to show that the God who made the world is the only God.”</blockquote>', false);
t('Aynı alıntı düz paragrafta',      '<p>“We will now proceed to refute these heretics, and to show that the God who made the world is the only God.”</p>', false);
t('Felsefi gelecek zaman',           '<p>We will now proceed to the question of being itself, which Heidegger raises in the opening pages.</p>', false);
t('"the end of the work"',           '<p>Toward the end of the work, Kristeva returns to the maternal body.</p>', false);
t('Edebî kapanış',                   '<p>Here the work ends: not with a solution, but with a question.</p>', false);
t('Kitap bölüm başlığı',             '<h3>Part Four: The Discipline of Tomorrow</h3>', false);
t('Mektup alıntısı',                 '<blockquote>“Per your request, I have enclosed the manuscript.”</blockquote>', false);

echo "\n── GERÇEK SIZINTI (işaretlenmeli) ──\n";
t('Kapanış kuralı',                  '<p>*Here the work ends. No summary, no closing paragraph.*</p>', true);
t('Parça işareti',                   '<p>%%PART_END%%</p>', true);
t('Alıntı içinde parça işareti',     '<blockquote>“%%PART_END%%”</blockquote>', true);
t('Kelime sayısı notu',              '<p>Word count: approximately 2000 words</p>', true);
t('Sohbet artığı',                   '<p>I hope this helps! Let me know if you need anything else.</p>', true);
t('Parça devamı',                    '<p>I will now continue with Part 3 of the summary.</p>', true);
t('Parça numarası',                  '<p>This is Part 2 of 4 of the analysis.</p>', true);


/* ── ONARIM YOLU ──────────────────────────────────────────────────────────
   Tespit etmek yetmez: ekran "onarılabilir" diyorsa onarım GERÇEKTEN
   değiştirmeli. Bu iki liste bir zamanlar ayrışmıştı ve buton hiçbir şey
   yapmadan "onardım" diyordu.                                              */
function repairs($html) {
    $new = ca_repair($html, 'severe');
    return ($new !== $html) && !ca_repair_too_lossy($html, $new);
}
function r($label, $html, $expect) {
    global $ok, $bad;
    $got = repairs($html);
    $pass = ($got === $expect);
    $pass ? $ok++ : $bad++;
    printf("%s  %-42s %s\n", $pass ? '✓' : '✗ HATA', $label, $got ? '→ onarıldı' : '→ dokunulmadı');
}

echo "\n── ONARIM ÇALIŞIYOR MU (değişmeli) ──\n";
$body = '<p>Real body text that must stay in place.</p>';
r('Parça işareti silinir',  "$body\n<p>%%PART_END%%</p>", true);
r('Prompt kuralı silinir',  "$body\n<p>*Here the work ends. No summary, no closing paragraph.*</p>", true);
r('Sohbet artığı silinir',  "$body\n<p>I hope this helps! Let me know if you need anything else.</p>", true);
r('Devam cümlesi silinir',  "$body\n<p>Here is the continuation of the summary.</p>", true);
r('Öksüz başlık silinir',   "$body\n<h2>A Compendium in Thirteen Propositions</h2>", true);
r('Kelime notu silinir',    "$body\n<p>Word count: approximately 2000 words</p>", true);

echo "\n── ONARIM DOKUNMAMALI ──\n";
r('Alıntı korunur',        '<blockquote>“We will now proceed to refute these heretics.”</blockquote>', false);
r('"Part 1 of 4" cümlesi', '<p>The full text (or the relevant chapters for Part 1 of 4) is discussed here.</p>', false);
r('Normal kapanış',        '<p>The argument closes on this note, complete and whole.</p>', false);
r('Üretim reddi (elleme)', '<p>I cannot produce this summary because I do not have access to the actual text.</p><p>Word count: 0</p>', false);
/* Güvenlik eşiği doğrudan sınanır: içeriğe bakmaksızın, "ne kadar metin
   gitmiş" sorusuna doğru cevabı vermeli. İleride bir kalıp yanlışlıkla
   genişlerse yayındaki metnin toplu silinmesini bu eşik durdurur. */
function g($label, $old_len, $new_len, $expect) {
    global $ok, $bad;
    $got  = ca_repair_too_lossy(str_repeat('a', $old_len), str_repeat('a', $new_len));
    $pass = ($got === $expect);
    $pass ? $ok++ : $bad++;
    printf("%s  %-42s %s\n", $pass ? '✓' : '✗ HATA', $label, $got ? '→ engellendi' : '→ izin verildi');
}
echo "\n── GÜVENLİK EŞİĞİ ──\n";
g('7000 karakterden 20 gitmiş',   7000, 6980, false);   // normal onarım
g('300 karakterden 40 gitmiş',     300,  260, false);   // kısa yazı, küçük artık
g('7000 karakterden 5000 gitmiş', 7000, 2000, true);    // felaket → durdur
g('1000 karakterden 900 gitmiş',  1000,  100, true);    // felaket → durdur

/* Kapanış ve başlık kontrolleri: kusur sayılan şey ifadenin metinde GEÇMESİ
   değil, son paragrafın bir ÖZET PARAGRAFI olarak başlamasıdır. */
function w($label, $html, $expect) {
    global $ok, $bad;
    $got  = (ca_check_wrapup($html) !== '');
    $pass = ($got === $expect);
    $pass ? $ok++ : $bad++;
    printf("%s  %-42s %s\n", $pass ? '✓' : '✗ HATA', $label, $got ? '→ işaretlendi' : '→ temiz');
}
echo "\n── KAPANIŞ KONTROLÜ ──\n";
w('"refusal to conclude" (gerçek cümle)',
  '<p>The diary’s power lies in its refusal to conclude: it ends not with a verdict, but with a burned library.</p>', false);
w('"in summary" cümle içinde',
  '<p>What he offers in summary form here is expanded in the later chapters of the work.</p>', false);
w('Gerçek özet paragrafı',
  '<p>In conclusion, this book shows that the argument holds across every domain.</p>', true);

function d($label, $html, $expect) {
    global $ok, $bad;
    $got  = (ca_check_dup_heading($html) !== '');
    $pass = ($got === $expect);
    $pass ? $ok++ : $bad++;
    printf("%s  %-42s %s\n", $pass ? '✓' : '✗ HATA', $label, $got ? '→ işaretlendi' : '→ temiz');
}
echo "\n── BAŞLIK TEKRARI ──\n";
d('Kısa genel başlık iki kez', '<h3>Conclusion</h3><p>x</p><h3>Conclusion</h3>', false);
d('Uzun başlık birebir iki kez',
  '<h3>Hypothesis V: If the One Is Not (The Knowable Non-Being)</h3><p>x</p><h3>Hypothesis V: If the One Is Not (The Knowable Non-Being)</h3>', true);

echo "\nSonuç: {$ok} geçti, {$bad} hata\n";
exit($bad ? 1 : 0);
