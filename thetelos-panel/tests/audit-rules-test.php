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
foreach (['ca_strip_quotes','ca_is_quotation','ca_blocks','ca_meta_patterns','ca_check_part_markers',
          'ca_is_meta_block','ca_check_prompt_leak','ca_check_meta_talk'] as $fn) {
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

echo "\nSonuç: {$ok} geçti, {$bad} hata\n";
exit($bad ? 1 : 0);
