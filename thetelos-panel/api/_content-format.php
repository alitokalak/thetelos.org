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

    $text = preg_replace('/\n{4,}/', "\n\n\n", $text);
    return trim($text);
}

function bw_md2html($text) {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace_callback('/\*\*(.+?)\*\*/s', function($m) {
        return strpos($m[1], "\n") === false ? '<strong>'.$m[1].'</strong>' : $m[1];
    }, $text);
    // ÖNEMLİ: h4/h5 ve yatay çizgi de dönüştürülür. Eskiden yalnız h1-h3 vardı;
    // model "#### Alt Başlık" veya "---" yazdığında bunlar HTML'e çevrilmeden
    // sayfada düz metin olarak görünüyordu ("#### T", "---").
    $text = preg_replace(
        ['/^#{1} \*\*(.+?)\*\*/m','/^#{2} \*\*(.+?)\*\*/m','/^#{3} \*\*(.+?)\*\*/m',
         '/^#{4} \*\*(.+?)\*\*/m','/^#{5,6} \*\*(.+?)\*\*/m',
         '/^# (.+)/m','/^## (.+)/m','/^### (.+)/m','/^#### (.+)/m','/^#{5,6} (.+)/m',
         '/^\s*(?:-{3,}|\*{3,}|_{3,})\s*$/m'],
        ['<h1><strong>$1</strong></h1>','<h2><strong>$1</strong></h2>','<h3><strong>$1</strong></h3>',
         '<h4><strong>$1</strong></h4>','<h5><strong>$1</strong></h5>',
         '<h1>$1</h1>','<h2>$1</h2>','<h3>$1</h3>','<h4>$1</h4>','<h5>$1</h5>',
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
