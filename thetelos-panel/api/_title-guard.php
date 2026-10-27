<?php
/**
 * _title-guard.php — Yazar eser listesinde başlığa sızan "not"ları yakalar.
 *
 * Eser listesini üreten model (DeepSeek), kuşkulu eserleri listeden çıkarmak
 * yerine bazen başlığın içine not düşüyor: "Live from Death Row (by Mumia
 * Abu-Jamal — misattribution)". Bu not başlıkla birlikte yayına giriyor ve
 * "yanlış yazara atfedilmiş" sayfalar oluşuyordu. Böyle başlıklar artık
 * listeye alınmaz ve batch'te üretilmez.
 */
function tls_title_has_attribution_note($title) {
    return (bool) preg_match(
        '/\b(misattribut\w*|spurious|dubia|dubious(\s+attribution)?|wrongly\s+attributed|falsely\s+attributed|'
        . 'attribution\s+(?:doubtful|uncertain|disputed)|not\s+by\s+\w+|actually\s+by\s+\w+|pseudo-?epigraph\w*)\b'
        . '|\(\s*by\s+[^()]+\)/iu',
        (string) $title
    );
}

/**
 * Başlık İngilizce karşılığı OLMADAN tamamen yabancı dilde mi? (çeviri baskısı şüphesi)
 * Site kuralı: yabancı dilde yazılmış eser "İngilizce Ad (Orijinal Ad)" biçiminde
 * girer. Parantezli orijinal ad yoksa ve başlık yabancı görünüyorsa bu büyük
 * ihtimalle bir ÇEVİRİ baskısıdır (ör. "Kazari – 13. židovsko pleme?" = Koestler'in
 * The Thirteenth Tribe'ının Hırvatçası) → ayrı kitap olarak yazılmamalı.
 */
function tls_title_untranslated_foreign($title) {
    $t = trim((string) $title);
    if ($t === '' || preg_match('/\([^()]+\)\s*$/u', $t)) return false;     // "English (Original)" biçimi → sorun yok
    // Latin dışı yazı (Kiril, Yunan, Arap, İbrani, Hint, Çin/Japon…) veya İngilizcede olmayan Latin harfleri
    if (preg_match('/[^\x{0000}-\x{024F}\x{2000}-\x{206F}\x{2010}-\x{2027}]/u', $t)) return true;
    if (preg_match('/[ščžćđłńśźżőűřěůťďľĺŕąęėįųūāēīōşğıøåæœßþð]/iu', $t)) return true;
    if (preg_match('/\S\s+de\s+\S/iu', $t)) return true;   // "X de Y" (baştaki "De Profundis" gibi Latince kalıplar hariç)
    // Yaygın yabancı tanımlık/edatlar (Fransızca, İspanyolca, İtalyanca, Almanca, Hollandaca…)
    return (bool) preg_match('/(^|\s)(de la|del|della|delle|degli|dei|di|les|la|el|il|une|des|du|von|vom|und|der|die|das|zur|zum|sur|aux|dans|nella|nel|och|för|het|een|und|oder|ein|eine|o|os|as|do|da|dos|das|em|y|en el|lo|gli|pour|avec|sobre|para|como)(\s|$)/iu', ' ' . mb_strtolower($t) . ' ');
}

