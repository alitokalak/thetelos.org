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
