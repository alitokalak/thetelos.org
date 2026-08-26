<?php
/**
 * _content-format.php — Model çıktısını yayına hazır HTML'e çeviren ortak kod.
 *
 * NEDEN AYRI DOSYA: Bu iki fonksiyon hem toplu üretimde (batch-worker) hem tek
 * kitap yayınında hem de denetim/tamamlama aracında gerekiyor. Kopyalanırsa
 * biri düzeltilip diğeri unutuluyor — nitekim "#### başlık HTML'e çevrilmiyor"
 * hatası tam olarak böyle iki ayrı kopyadan doğmuştu.
 */

if (!defined('ABSPATH') && !defined('TLS_PANEL')) { /* panelden de doğrudan çağrılabilir */ }

if (!function_exists('bw_clean_content')) {

/* Tekrarlanan bölümleri temizle.
   Çok kademeli üretim/genişletme, "tekrar etme" talimatına rağmen bazen aynı
   #/##/### başlıklı bölümü (ve gövdesini) İKİNCİ kez yazıyor (Origin of Species:
   "The Problem of Instinct", "The Book's Structure" vb. dört bölüm iki kez).
   Bu, SEO'yu bozan gerçek bir içerik hatası. Başlık metnini normalize edip İLK
   görülen bölümü tutar, sonraki AYNI başlıklı bloğu gövdesiyle birlikte atar.
   Farklı başlıklı özgün içerik korunur. Model talimata uymasa bile mekanik
   olarak temizlenir. */
function bw_dedup_sections($text) {
    $text = (string) $text;
    if (strpos($text, '#') === false) return $text;   // başlık yoksa iş yok
    $norm = function ($h) {
        $h = preg_replace('/^#{1,6}\s*/', '', trim($h));
        $h = preg_replace('/[*_`>#]+/', '', $h);
        $a = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $h);
        if ($a !== false && $a !== '') $h = $a;
        $h = mb_strtolower(trim($h), 'UTF-8');
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $h));
    };
    // Bir bloğun gövdesini (başlık hariç) karşılaştırma için normalize et.
    $bodynorm = function ($lines) {
        $body = implode(' ', array_slice($lines, 1));           // başlık satırını atla
        $body = mb_strtolower(strip_tags($body), 'UTF-8');
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $body));
    };
    $lines  = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
    $blocks = [];
    $cur    = ['key' => null, 'lines' => []];
    foreach ($lines as $ln) {
        if (preg_match('/^#{1,3}\s+\S/', $ln)) {   // yeni bölüm başlığı
            $blocks[] = $cur;
            $cur = ['key' => $norm($ln), 'lines' => [$ln]];
        } else {
            $cur['lines'][] = $ln;
        }
    }
    $blocks[] = $cur;
    $seen = []; $out = []; $removed = 0;
    foreach ($blocks as $b) {
        if ($b['key'] !== null && $b['key'] !== '') {
            $body = $bodynorm($b['lines']);
            if (isset($seen[$b['key']])) {
                // Aynı başlık. Yalnız GÖVDE de büyük ölçüde örtüşüyorsa gerçek kopya
                // → at (çok kademeli üretimin aynı bölümü ikinci kez yazması). Gövde
                // farklıysa özgün genişletme → başlığı 'X (continued)' yapıp KORU;
                // böylece hem içerik kaybolmaz hem iki aynı başlık yan yana durmaz.
                $prev = $seen[$b['key']];
                $pct = 0.0;
                if ($body === '' || $prev === '') { $pct = 100.0; }
                else { similar_text(mb_substr($prev, 0, 1500), mb_substr($body, 0, 1500), $pct); }
                if ($pct >= 55) { $removed++; continue; }          // gerçek tekrar → at
                $b['lines'][0] = rtrim($b['lines'][0]) . ' (continued)';   // özgün → koru
            } else {
                $seen[$b['key']] = $body;
            }
        }
        $out[] = implode("\n", $b['lines']);
    }
    if ($removed === 0) return $text;   // gerçek tekrar yok → dokunma
    return preg_replace('/\n{4,}/', "\n\n\n", implode("\n", $out));
}

function bw_clean_content($text) {
    $text = preg_replace('/%%PART[12]_(?:END|START)%%/i', '', $text);
    $text = preg_replace('/%%PART_END%%/i', '', $text);
    $text = preg_replace('/\[Note:[^\]]*\]/i', '', $text);
    $text = preg_replace('/\[Already[^\]]*\]/i', '', $text);
    $text = preg_replace('/\[This was[^\]]*\]/i', '', $text);
    $text = preg_replace('/\[.*?already.*?\]/is', '', $text);
    $text = preg_replace('/\[.*?covered.*?\]/is', '', $text);
    $text = preg_replace('/\[.*?Part 1.*?\]/is', '', $text);
    $text = preg_replace('/\[.*?structure.*?\]/is', '', $text);

    // Model bazen prompt'taki kapanış KURALINI metnin sonuna yazıyor
    // ("*Here the work ends. No summary, no closing paragraph.*"). Bu satır
    // okuyucuya görünmemeli; kendi başına duran satırlar olarak silinir.
    $text = preg_replace(
        '/^\s*[*_]{0,2}\s*(?:here (?:the|this) (?:work|piece|summary) ends|no summary,? no closing|'
        . 'end of (?:part|the work|summary)|%%\s*PART[^%]*%%)[^\n]*$/im',
        '', $text);
    // Modelin kendi süreciyle konuştuğu tek satırlık notlar
    $text = preg_replace(
        '/^\s*[*_(\[]{0,2}\s*(?:as (?:requested|instructed)|per your (?:request|instructions)|'
        . 'let me know if you|i hope this (?:helps|summary)|word count:?)[^\n]*$/im',
        '', $text);

    // "Part N" BAŞLIK ARTIĞI: model, iç işleme segmentlerimizi ([Part 1]…[Part N])
    // ya da kafasına göre "Part 1 / Part Two" diye BAŞLIĞA basıyor — kullanıcı bunu
    // istemiyor (kitabın gerçek akışı izlenmeli). Başlıklardan bu ön-eki mekanik at:
    //  "### Part 3"            → başlığı tamamen sil (altındaki metin akışta kalır)
    //  "### Part 3: The Absurd"→ "### The Absurd" (gerçek başlığı koru)
    // Gövde cümlelerine ("In Part 1…") DOKUNMAYIZ — yalnız başlık satırları.
    $strip_part = fn($s) => trim(preg_replace(
        '/^part\s+(?:\d+|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|[ivxlcdm]+)\b\s*[:.\-–—)]*\s*/i',
        '', $s));
    // (a) Markdown başlık: "### Part 3: The Absurd" → "### The Absurd"; "### Part 3" → sil.
    $text = preg_replace_callback('/^(#{2,6})[ \t]+(.+?)[ \t]*$/m', function ($m) use ($strip_part) {
        $t = $strip_part($m[2]);
        return $t === '' ? '' : $m[1] . ' ' . $t;
    }, $text);
    // (b) KALIN SATIR başlık: "**Part 2: The Manchester Years**" → "**The Manchester
    //     Years**"; tek başına "**Part 2**" → sil. Model bölüm başlıklarını çoğu zaman
    //     ### yerine kalın satır olarak yazıyor; bunlar da temizlenmeli.
    $text = preg_replace_callback('/^[ \t]*\*\*[ \t]*(.+?)[ \t]*\*\*[ \t]*$/m', function ($m) use ($strip_part) {
        $orig = $m[1];
        $t = $strip_part($orig);
        if ($t === $orig) return $m[0];        // "Part" ile başlamıyorsa dokunma
        return $t === '' ? '' : '**' . $t . '**';
    }, $text);

    // Başıboş/boş başlık satırlarını at (yalnız "##" olup metni olmayan).
    $text = preg_replace('/^[ \t]*#{1,6}[ \t]*$/m', '', $text);

    // Tekrarlanan bölümleri at (çok kademeli üretim kopyaları).
    $text = bw_dedup_sections($text);

    $text = preg_replace('/\n{4,}/', "\n\n\n", $text);
    return trim($text);
}

function bw_md2html($text) {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace_callback('/\*\*(.+?)\*\*/s', function($m) {
        return strpos($m[1], "\n") === false ? '<strong>'.$m[1].'</strong>' : $m[1];
    }, $text);
    // Tek yıldız italik: **kalın** yukarıda tüketildi; kalan *...* italiğe çevrilir
    // (yoksa "*Peter Camenzind*" sayfada yıldızlarıyla düz görünüyordu).
    $text = preg_replace('/(?<!\*)\*(?!\s)([^*\n]+?)(?<!\s)\*(?!\*)/', '<em>$1</em>', $text);
    // ÖNEMLİ: h4/h5 ve yatay çizgi de dönüştürülür. Eskiden yalnız h1-h3 vardı;
    // model "#### Alt Başlık" veya "---" yazdığında bunlar HTML'e çevrilmeden
    // sayfada düz metin olarak görünüyordu ("#### T", "---").
    $text = preg_replace(
        ['/^#{1} \*\*(.+?)\*\*/m','/^#{2} \*\*(.+?)\*\*/m','/^#{3} \*\*(.+?)\*\*/m',
         '/^#{4} \*\*(.+?)\*\*/m','/^#{5,6} \*\*(.+?)\*\*/m',
         '/^# (.+)/m','/^## (.+)/m','/^### (.+)/m','/^#### (.+)/m','/^#{5,6} (.+)/m',
         '/^\s*(?:-{3,}|\*{3,}|_{3,})\s*$/m'],
        // NOT: Gövdede H1 YOK — sayfa başlığı (post title) zaten tek H1. Model tek '#'
        // yazınca dev H1 çıkıyordu; içerik başlıklarını en fazla H2'den başlat.
        ['<h2><strong>$1</strong></h2>','<h2><strong>$1</strong></h2>','<h3><strong>$1</strong></h3>',
         '<h4><strong>$1</strong></h4>','<h5><strong>$1</strong></h5>',
         '<h2>$1</h2>','<h2>$1</h2>','<h3>$1</h3>','<h4>$1</h4>','<h5>$1</h5>',
         '<hr>'],
        $text
    );
    $lines = explode("\n", $text);
    $html = ''; $buf = []; $bqbuf = []; $list = []; $list_tag = 'ul';
    $fl  = function() use (&$buf,  &$html) { if ($buf)   { $html .= '<p>'  . implode(' ', $buf)   . "</p>\n"; $buf   = []; } };
    $fbq = function() use (&$bqbuf,&$html) { if ($bqbuf) { $html .= '<blockquote>' . implode(' ', $bqbuf) . "</blockquote>\n"; $bqbuf = []; } };
    $fls = function() use (&$list, &$list_tag, &$html) {
        if (!$list) return;
        $html .= "<{$list_tag}>\n";
        foreach ($list as $li) $html .= '<li>' . $li . "</li>\n";
        $html .= "</{$list_tag}>\n";
        $list = [];
    };
    foreach ($lines as $l) {
        $l = trim($l);
        if (!$l) { $fl(); $fbq(); $fls(); continue; }
        if (preg_match('/^(?:&gt;|>)\s*(.*)$/', $l, $m)) { $fl(); $fls(); $bqbuf[] = $m[1]; continue; }
        if (preg_match('/^<(h[1-6]|hr)/', $l)) { $fl(); $fbq(); $fls(); $html .= $l . "\n"; continue; }
        // Madde işaretli / numaralı liste — eskiden "- madde" olarak düz metne düşüyordu
        if (preg_match('/^[-*•]\s+(.+)$/u', $l, $m))    { $fl(); $fbq(); if ($list && $list_tag !== 'ul') $fls(); $list_tag = 'ul'; $list[] = $m[1]; continue; }
        if (preg_match('/^\d+[.)]\s+(.+)$/u', $l, $m))  { $fl(); $fbq(); if ($list && $list_tag !== 'ol') $fls(); $list_tag = 'ol'; $list[] = $m[1]; continue; }
        $fbq(); $fls(); $buf[] = $l;
    }
    $fl(); $fbq(); $fls();
    return $html;
}
}

/* Çok parçalı üretimin parça talimatları — üretim ve onarım aynı
   yönergeyi kullanmalı ki yeniden yazılan metin diğerleriyle aynı biçimde olsun. */
if (!function_exists('bw_fraction')) {
function bw_fraction($n) {
    switch ($n) { case 2: return 'half'; case 3: return 'third'; case 4: return 'quarter'; default: return "1/{$n}"; }
}

function bw_part_instruction($k, $n, $headings, $tail, $part_words) {
    if ($n <= 1) {
        return "\nTarget length: approximately {$part_words} words.";
    }
    $frac = bw_fraction($n);
    $covered = '';
    foreach ($headings as $h) $covered .= "   ✗ {$h}\n";

    if ($k === 1) {
        return "\n\n=== MULTI-PART GENERATION (PART 1 of {$n}) ===\n"
             . "You are writing PART 1 of {$n} of a single continuous piece.\n"
             . "• Begin with the H1 (# **Title — Author**) then the H2 (## **Subtitle**), then the first ### sections in order.\n"
             . "• Cover approximately the first {$frac} of the complete work (~{$part_words} words for this part).\n"
             . "• Develop every section fully per the format rules. Do NOT write any conclusion — more parts follow.\n"
             . "• End naturally at a ### section boundary. Your ABSOLUTE FINAL LINE must be exactly:\n%%PART_END%%";
    }
    if ($k < $n) {
        return "\n\n=== MULTI-PART GENERATION (PART {$k} of {$n}) ===\n"
             . "You are writing PART {$k} of {$n} — a direct, seamless continuation of the text already written.\n"
             . "STRICT RULES:\n"
             . "1. DO NOT rewrite the H1 or H2 heading.\n"
             . "2. The following sections are FULLY COMPLETE — do NOT revisit, repeat, summarize, or expand them:\n{$covered}"
             . "3. Continue with the NEXT new ### sections not listed above. Cover roughly the next {$frac} of the work (~{$part_words} words).\n"
             . "4. Do NOT write a conclusion — there are still more parts after this one.\n"
             . "5. Maintain the exact same voice, depth, and format as before.\n"
             . "6. End at a ### section boundary. Your ABSOLUTE FINAL LINE must be exactly:\n%%PART_END%%\n"
             . "\nThe text so far ended here (continue seamlessly from this exact point — do NOT repeat it):\n...{$tail}";
    }
    return "\n\n=== MULTI-PART GENERATION (FINAL PART {$n} of {$n}) ===\n"
         . "You are writing the FINAL PART ({$n} of {$n}) — a direct, seamless continuation.\n"
         . "STRICT RULES:\n"
         . "1. DO NOT rewrite the H1 or H2 heading.\n"
         . "2. The following sections are FULLY COMPLETE — do NOT revisit, repeat, or expand them:\n{$covered}"
         . "3. Continue with ALL remaining ### sections and COMPLETE the work fully.\n"
         . "4. Maintain the exact same voice, depth, and format as before.\n"
         . "5. Apply the closing rule: end with the final substantive point — no summary paragraph, no closing sentence.\n"
         . "\nThe text so far ended here (continue seamlessly from this exact point — do NOT repeat it):\n...{$tail}";
}
}
