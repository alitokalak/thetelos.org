<?php
/**
 * _checks.php — İçerik kusuru TESPİT kuralları (tek kaynak).
 *
 * NEDEN AYRI DOSYA: bu kurallar iki ayrı yerde gerekiyor.
 *   1. content-audit.php — YAYINDAKİ yazıları tarar (geriye dönük).
 *   2. _verify.php / publish.php / batch-worker.php — YAYIN ÖNCESİ kapı
 *      (ileriye dönük): kusurlu metin hiç yayına çıkmasın.
 *
 * Aynı kural iki dosyada ayrı ayrı yazıldığında kaçınılmaz olarak ayrışıyor;
 * bunu daha önce tespit/silme listelerinde yaşadık. Kural tek yerde durur.
 *
 * BAĞIMLILIK: yalnızca wp_strip_all_tags. WordPress yüklü değilse (publish.php
 * ve batch-worker.php REST API üzerinden çalışır, wp-load.php yüklemez)
 * aşağıdaki yedek tanım devreye girer.
 */

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($s) {
        $s = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $s);
        return trim(strip_tags($s));
    }
}

/* ── Kontroller ───────────────────────────────────────────────────────────
   Her kontrol: kod, önem (3=ağır, 2=orta, 1=hafif), açıklama, bulunan örnek.
   Ağır bulgular okuyucunun doğrudan göreceği kazalardır.                    */

/**
 * Metni bloklara ayırır (paragraf, başlık, madde, alıntı) — düz metin olarak.
 *
 * NEDEN BLOK: "the end of the work" ya da "here the work ends: not with a
 * solution, but with a question" gibi ifadeler NORMAL metinde geçer. Bunları
 * gövdenin herhangi bir yerinde aramak yüzlerce yanlış alarm üretiyordu.
 * Sızıntı, cümlenin içinde değil, KENDİ BAŞINA duran bir satır olarak görünür.
 */
/**
 * Kitabın kendi sesi: alıntılar.
 *
 * TEMEL AYRIM: Bir metinde iki ses vardır — kitabın/yazarın sesi (alıntılar)
 * ve özeti yazan sistemin sesi. Süreç kalıntısı ancak İKİNCİSİNDE olabilir.
 * Irenaeus'un "We will now proceed to refute these heretics…" cümlesi kitabın
 * sesidir; kalıp benzese de sızıntı değildir. Bu yüzden alıntılar taramanın
 * dışında bırakılır — kalıp kalıp yama yapmak yerine kaynağı ayırıyoruz.
 */
function ca_strip_quotes($html) {
    return preg_replace('/<blockquote\b[^>]*>.*?<\/blockquote>/is', ' ', (string) $html);
}

/** Blok baştan sona tırnak içindeyse alıntıdır — sistemin sesi olamaz. */
function ca_is_quotation($t) {
    $t = trim($t, "*_ \t");
    return (bool) preg_match('/^["“«‘\'].{10,}["”»’\']\s*[.,;:]?$/us', $t);
}

function ca_blocks($html, $skip_quotes = true) {
    $h = preg_replace('/<br\s*\/?>/i', "\n", (string) $html);
    if ($skip_quotes) $h = ca_strip_quotes($h);
    $out = [];
    if (preg_match_all('/<(p|h[1-6]|li|blockquote)\b[^>]*>(.*?)<\/\1>/is', $h, $m)) {
        foreach ($m[2] as $b) {
            $t = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($b)));
            if ($t !== '') $out[] = $t;
        }
    }
    if (!$out) {
        foreach (preg_split('/\n+/', wp_strip_all_tags($h)) as $l) {
            $l = trim($l);
            if ($l !== '') $out[] = $l;
        }
    }
    return $out;
}

/**
 * Süreç cümleleri.
 *
 * ÖLÇÜT: cümle, KİTABIN KONUSU hakkında değil, METNİN ÜRETİLMESİ hakkında
 * olmalı. Bu ayrımı yapmayan kalıplar yanlış alarm üretiyor —
 * "We will now proceed to refute these heretics" (Irenaeus) ile
 * "I will now continue with Part 3" arasındaki fark, fiilin değil NESNENİN
 * ne olduğudur. Bu yüzden her kalıp, üretim sözlüğünden bir kelimeyi
 * (part, word count, summary, prompt, AI) zorunlu tutar; salt birinci tekil
 * gelecek zaman kalıpları listede YOKTUR — felsefe metinlerinin doğal dilidir.
 */
function ca_meta_patterns() {
    return [
        // Üretim düzeneğinin kendi kelimeleri
        '/no summary,?\s*no closing paragraph/i',
        '/\bas an? (?:ai|language model|assistant)\b/i',
        '/\b(?:apply|per) the closing rule\b/i',
        '/\bdo not write a (?:conclusion|summary)\b/i',
        '/\bword count\s*[:=]/i',
        '/\btarget length\b/i',
        '/\bapproximately \d{3,5} words\b/i',
        '/\bper your (?:request|instructions)\b/i',
        '/\bnote to (?:the )?(?:editor|reader|user)\b/i',

        // Sohbet artığı — kitap metninde karşılığı yok
        '/\bi hope this (?:helps|summary)\b/i',
        '/\blet me know if you\b/i',

        // Parça düzeneğine açık gönderme (nesne: part/section)
        '/\b(?:part|section)\s+\d+\s+of\s+\d+\b/i',
        '/\b(?:i|we) (?:will|shall) now (?:continue|proceed|begin)\b[^.]{0,40}\b(?:part|section)\s*\d/i',
        '/\bhere(?:\'s| is) the (?:continuation|next part|final part)\b/i',
        '/\bthe (?:next|remaining|final) part will (?:cover|continue)\b/i',
        '/\b(?:in|for) this part,? i (?:will|have)\b/i',
        '/\bcontinu(?:ing|ed) from (?:the )?(?:previous|last) (?:part|section)\b/i',
    ];
}

/**
 * ÜRETİM REDDİ: yazının tamamı, modelin "bunu yazamam" açıklaması.
 *
 * Bu bir satır kusuru değil, yazının tamamının geçersiz olmasıdır. Böyle bir
 * yazıda satır silmek onarım değil — çöpün bir kısmını temizleyip kalanını
 * yayında bırakmaktır. Bu yüzden ayrı bir bulgu türü: eylemi yayından
 * kaldırmak ve yeniden üretmektir.
 */
/**
 * Bulgunun ÇEVRESİNİ döndürür.
 *
 * Örnek olarak yalnızca eşleşen ifadeyi ("cannot verify") göstermek, ekranda
 * doğrulanamaz bir bulgu üretiyordu: kullanıcı yazıyı açıp o ifadeyi arıyor,
 * bağlamı göremediği için hatanın gerçek mi yanlış alarm mı olduğunu
 * anlayamıyordu. Bulgu, tek bakışta doğrulanabilir olmalı.
 */
function ca_context($haystack, $needle, $len = 150) {
    $h = trim(preg_replace('/\s+/u', ' ', (string) $haystack));
    $p = mb_stripos($h, trim((string) $needle), 0, 'UTF-8');
    if ($p === false) return mb_substr($h, 0, $len, 'UTF-8');
    $start = max(0, $p - 45);
    $out   = mb_substr($h, $start, $len, 'UTF-8');
    return ($start > 0 ? '…' : '') . trim($out) . (mb_strlen($h, 'UTF-8') > $start + $len ? '…' : '');
}

function ca_check_refusal($html) {
    $blocks = ca_blocks($html, false);
    $head   = implode(' ', array_slice($blocks, 0, 3));   // reddin olağan yeri baştır
    $pats = [
        '/\bi cannot (?:produce|write|provide|generate|create)\b/i',
        '/\bi(?:\'m| am) (?:unable|not able) to (?:produce|write|provide|access)\b/i',
        '/\bi do not have access to the (?:actual|full) text\b/i',
        '/\bwithout the source material\b/i',
        '/\bi need one of the following\b/i',
        '/\bplease provide the (?:necessary|full) (?:material|text)\b/i',
        '/\bif you (?:can )?(?:provide|share) the (?:text|book|material)\b/i',
        '/\bi (?:don\'t|do not) have (?:the )?(?:book|text|access)\b/i',
    ];

    /* PROMPT'UN DÜRÜSTLÜK KAPISININ ÜRETTİĞİ AÇIK RET.
       Ölçüt ifadenin metinde GEÇMESİ DEĞİL, bloğun onunla BAŞLAMASIDIR.

       İlk halinde kalıp "/\bcannot verify\b/i" idi ve gövdenin herhangi bir
       yerinde eşleşiyordu. Sonuç, tam da kaçınmak istediğimiz şey oldu:
       Popper'ın "Bilgi Kuramının İki Temel Sorunu" özeti reddedildi — oysa o
       kitabın KONUSU doğrulanabilirlik, "cannot verify" onun ana sözcüğü.
       Clausewitz'in sefer tarihinde de "kaynaklardan doğrulayamıyoruz"
       cümlesi olağan. Sağlam yazıları uydurma sanmak, uydurmayı kaçırmak
       kadar zararlı.

       Gerçek ret, modelin metin yerine yazdığı TEK SATIRDIR: bloğun başında
       durur ve bloğun tamamı kısadır. */
    $decl = [
        '/^[\s*_>#\-]*cannot verify\b/i',
        '/^[\s*_>#\-]*(?:i am|i\'m) not familiar with (?:this|that) (?:work|book|text)\b/i',
        '/^[\s*_>#\-]*i (?:do not|don\'t) (?:reliably )?know (?:this|that) (?:exact )?(?:work|book)\b/i',
    ];
    foreach (ca_blocks($html, true) as $b) {
        if (mb_strlen($b, 'UTF-8') > 300) continue;   // uzun blok = gerçek metin
        foreach ($decl as $p) if (preg_match($p, trim($b))) return ca_context($b, trim($b));
    }

    foreach ($pats as $p) if (preg_match($p, $head, $m)) return ca_context($head, $m[0]);

    /* ÇOK PARÇALI ÜRETİMDE RET ORTADA KALIR.
       Parçalar birleştirildiği için 1. parça düzgün yazılıp 3. parça
       reddettiğinde ret metnin ortasına ya da sonuna düşer; yalnızca başa
       bakmak onu kaçırıyordu — yazı yayına yarısı ret metniyle çıkabilirdi.

       Gövdenin geri kalanında iki koruma birlikte çalışır:
       - ALINTILAR HARİÇ (kitabın kendi sesi ret sayılmaz),
       - yalnızca KISA bloklar. Gerçek ret tek başına duran 1-3 cümledir;
         "I cannot write" ifadesini içeren edebî bir paragraf uzundur ve
         bu eşiğin üstünde kalır. Ölçüt kelimenin varlığı değil, bloğun
         yapısı — kalıp kalıp yama yapmadan yanlış alarm önlenir. */
    foreach (ca_blocks($html, true) as $i => $b) {
        if ($i < 3) continue;                       // baş zaten tarandı
        if (mb_strlen($b, 'UTF-8') > 400) continue; // uzun blok = gerçek metin
        foreach ($pats as $p) if (preg_match($p, $b, $m)) return ca_context($b, $m[0]);
    }
    return '';
}

/**
 * PROMPT DÖKÜMÜ: şablonun kendisi yazının içine basılmış.
 *
 * En ağır kaza türü — okuyucu kitap özeti yerine sistemin talimatlarını
 * okuyor ("You are producing a GUIDED WALKTHROUGH…", "{book_title}",
 * "CORE TASK"). Metin genelde düzgün başlar, bir yerden sonra şablona döner;
 * yani kusur bir satır değil, o noktadan SONRASININ tamamıdır.
 *
 * Kalıplar bilerek tartışmasız: şablon değişkenleri ve talimat cümleleri
 * hiçbir kitap metninde bulunamaz.
 */
function ca_prompt_dump_patterns() {
    return [
        '/\{(?:book_title|author_name|book|author)\}/i',
        '/\byou are producing a\b/i',
        '/\bIMPORTANT:\s*Write the ENTIRE text in English\b/i',
        '/\bdo not use turkish\b/i',
        '/^CORE TASK$/im',
        '/\bfor thetelos\.org\b/i',
        '/\bwalk the reader through the actual content of this work\b/i',
        '/\byour primary job is to\b/i',
    ];
}

function ca_check_prompt_dump($html) {
    foreach (ca_blocks($html, false) as $b) {
        foreach (ca_prompt_dump_patterns() as $p) {
            if (preg_match($p, $b, $m)) return mb_substr(trim($b), 0, 110, 'UTF-8');
        }
    }
    return '';
}

/**
 * Şablonun başladığı bloktan itibaren HER ŞEYİ atar.
 *
 * Model bir kez şablona döndükten sonra geri dönmüyor; o noktadan sonrası
 * bütünüyle çöp. Kalan metin cümle ortasında biterse "tamamlanabilir" olur
 * ve API ile sürdürülür — yani yazı kurtarılır, sıfırdan yazılmaz.
 */
function ca_cut_prompt_dump($html) {
    if (!preg_match_all('/<(p|h[1-6]|li|blockquote|hr)\b[^>]*>(?:.*?<\/\1>)?/is', $html, $m, PREG_OFFSET_CAPTURE)) {
        return $html;
    }
    foreach ($m[0] as $i => $blk) {
        $txt = trim(wp_strip_all_tags($blk[0]));
        if ($txt === '') continue;
        foreach (ca_prompt_dump_patterns() as $p) {
            if (preg_match($p, $txt)) {
                $kept = rtrim(substr($html, 0, $blk[1]));
                // Kesince geriye yayınlanabilir bir metin kalmıyorsa dokunma:
                // o yazı baştan geçersizdir, onarılmaz — yeniden üretilir.
                if (mb_strlen(wp_strip_all_tags($kept), 'UTF-8') < 400) return $html;
                return $kept;
            }
        }
    }
    return $html;
}

/**
 * Üretim düzeneğinin teknik işareti.
 *
 * TEK KAYNAK: hem tespit hem silme bu kalıbı kullanır. Ayrı yazıldıklarında
 * ayrışıyorlar — nitekim yayına "%%PART_END%%" değil, yüzde işaretleri
 * düşmüş ÇIPLAK "PART_END" sızmıştı: tespit yakalıyor, silme yakalamıyordu
 * ve yazı gereksiz yere "yeniden üretilecek" görünüyordu.
 *
 * Bu diziler hiçbir kitap metninde bulunamaz, bu yüzden her yerde silinir.
 */
function ca_marker_regex() {
    return '/(?:%%\s*)?\bPART[_ ]?\d*[_ ]?(?:END|START)\b(?:\s*%%)?|%%\s*PART[^%]*%%|===\s*MULTI-PART[^\n]*/i';
}

/** Parça üretiminin teknik işaretleri metne sızmış mı? */
function ca_check_part_markers($html) {
    // Teknik işaretler her yerde geçersiz — alıntı içinde bile bulunamaz.
    if (preg_match(ca_marker_regex(), $html, $m)) return trim($m[0]);
    // "Part 2 of 4" ifadesi kitabın kendi metninde geçebilir (alıntı, künye);
    // bu yüzden alıntılar dışarıda bırakılarak aranır.
    $text = wp_strip_all_tags(ca_strip_quotes($html));
    return preg_match('/\bPart\s+\d+\s+of\s+\d+\b/i', $text, $m) ? $m[0] : '';
}

/**
 * Bir blok BAŞTAN SONA süreç cümlesi mi? (silinebilir olan tek durum)
 * Uzun bloklar elenir: gerçek paragrafın içine karışmış bir eşleşme,
 * paragrafın tamamını silmeyi haklı çıkarmaz.
 */
function ca_is_meta_block($block) {
    $b = trim($block, "*_ \t");
    if ($b === '' || mb_strlen($b, 'UTF-8') > 220) return false;
    if (preg_match(ca_marker_regex(), $b)) return true;   // teknik işaret: her koşulda
    if (ca_is_quotation($b)) return false;              // tırnak içindeyse kitabın sesi
    foreach (ca_meta_patterns() as $p) if (preg_match($p, $b)) return true;
    return false;
}

/** Prompt'un kuralı metne yazılmış mı? — sadece kendi başına duran bloklarda. */
function ca_check_prompt_leak($html) {
    foreach (ca_blocks($html) as $b) {
        if (ca_is_meta_block($b)) return mb_substr(trim($b, "*_ \t"), 0, 110, 'UTF-8');
    }
    return '';
}

/**
 * Model kendi süreciyle konuşmuş mu? — cümlenin içinde bile kabul edilemez
 * olanlar. Alıntılar hariç tutulur: bir mektup ya da diyalog alıntısında
 * "per your request" geçebilir, bu kitabın sesidir.
 */
function ca_check_meta_talk($html) {
    $text = wp_strip_all_tags(ca_strip_quotes($html));
    $hard = [
        '/\bas an? (?:ai|language model|assistant)\b/i',
        '/\bi hope this (?:helps|summary)\b/i',
        '/\blet me know if you\b/i',
        '/\bnote to (?:the )?(?:editor|reader|user)\b/i',
        '/\bword count\s*[:=]/i',
        '/\[(?:note|continued|to be continued)\b/i',
    ];
    foreach ($hard as $p) if (preg_match($p, $text, $m)) return ca_context($text, $m[0]);
    return '';
}

/**
 * ÖKSÜZ BAŞLIK: yazı bir başlıkla bitiyor, altında metin yok.
 *
 * Son parça alt başlığı atıp içeriğini yazmadan bitirdiğinde oluşur. Yarım
 * biten cümleden AYRI bir kusurdur: burada eksik olan bir cümle değil, boş
 * duran bir başlıktır — ve başlığı silmek metne hiçbir şey kaybettirmez,
 * dolayısıyla otomatik onarılabilir. Cümle ortasında kesilen metin ise
 * onarılamaz, oraya cümle uydurulamaz.
 *
 * Tüm gövdeye "$" çapalı .*? uygulanmaz — 60 KB'lık metinlerde PCRE geri
 * izlemesi isteği dakikalarca oyalıyordu. Son 300 karakter yeterli.
 */
function ca_check_orphan_heading($html) {
    $tail = mb_substr(rtrim((string) $html), -300, 300, 'UTF-8');
    if (preg_match('/<h([1-6])[^>]*>(.*?)<\/h\1>\s*$/is', $tail, $m)) {
        return mb_substr(trim(wp_strip_all_tags($m[2])), 0, 80, 'UTF-8');
    }
    return '';
}

/** Metin cümle ortasında mı kesilmiş? (onarılamaz — yeniden üretim ister) */
function ca_check_truncated($html) {
    $t = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($html)));
    if ($t === '') return 'içerik boş';
    // Öksüz başlık ayrı bulgu; burada tekrar raporlanmaz.
    if (ca_check_orphan_heading($html) !== '') return '';
    // Sondaki markdown vurgu işaretleri (*, _) noktalamayı gizleyebiliyor
    $t    = rtrim($t, "*_ \t");
    $last = mb_substr($t, -1, 1, 'UTF-8');
    // Kapanış parantezi/köşeli parantez de geçerli bir bitiş: alıntı ya da
    // künye ile biten metinler ("…Soviet Union.]") yarım sanılıyordu.
    if (mb_strpos('.!?"\'»)]}”’…', $last, 0, 'UTF-8') === false) {
        return '…' . mb_substr($t, -60, 60, 'UTF-8');
    }
    return '';
}

/** Aynı başlık iki kez yazılmış mı? (parça sınırında tekrar) */
function ca_check_dup_heading($html) {
    if (!preg_match_all('/<h([1-3])[^>]*>(.*?)<\/h\1>/is', $html, $m)) return '';
    $seen = [];
    foreach ($m[2] as $h) {
        $k = mb_strtolower(trim(wp_strip_all_tags($h)), 'UTF-8');
        // Kısa/genel başlıklar ("Conclusion", "Introduction") bir metinde
        // doğal olarak tekrarlanabilir; kusur sayılan şey uzun ve birebir
        // aynı olan başlıktır — o parça sınırında yeniden yazılmış demektir.
        if (mb_strlen($k, 'UTF-8') < 20) continue;
        if (isset($seen[$k])) return wp_strip_all_tags($h);
        $seen[$k] = 1;
    }
    return '';
}

/** Aynı paragraf iki kez geçmiş mi? (parça örtüşmesi) */
function ca_check_dup_para($html) {
    if (!preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $m)) return '';
    $seen = [];
    foreach ($m[1] as $p) {
        $t = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($p)));
        if (mb_strlen($t, 'UTF-8') < 160) continue;   // kısa paragraflar doğal olarak benzeşebilir
        $k = mb_substr($t, 0, 160, 'UTF-8');
        if (isset($seen[$k])) return mb_substr($t, 0, 90, 'UTF-8') . '…';
        $seen[$k] = 1;
    }
    return '';
}

/** HTML'e dönüşmemiş markdown kalıntısı var mı? */
function ca_check_md_leak($html) {
    $t = wp_strip_all_tags($html);
    if (preg_match('/(^|\n)\s*#{1,4}\s+\S/', $t, $m))   return trim($m[0]);
    if (preg_match('/\*\*[^*\n]{3,80}\*\*/', $t, $m))    return trim($m[0]);
    if (preg_match('/(^|\n)\s*---\s*(\n|$)/', $t))       return '--- (yatay çizgi)';
    return '';
}

/** Yasak kapanış: prompt "özet paragrafıyla bitirme" diyor. */
function ca_check_wrapup($html) {
    // Aranan şey, metnin SON PARAGRAFININ bir özet paragrafı olarak BAŞLAMASI.
    // İfadeyi metnin herhangi bir yerinde aramak yanlış alarm üretiyordu:
    // "The diary's power lies in its refusal TO CONCLUDE: it ends not with a
    // verdict…" cümlesi kusur değil, iyi bir cümledir.
    $blocks = ca_blocks($html);
    if (!$blocks) return '';
    $last = trim(end($blocks), "*_ \t");
    if (preg_match('/^(in conclusion|to conclude|in summary|to sum up|in closing|overall,? this book)\b[^.]{0,60}/i', $last, $m)) {
        return trim($m[0]);
    }
    return '';
}

function ca_word_count($html) {
    return str_word_count(wp_strip_all_tags($html), 0);
}
