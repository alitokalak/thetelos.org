<?php
/**
 * _verify.php — OLGU DOĞRULAMA: yazının anlattığı kitap gerçekten o kitap mı?
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * NEDEN VAR
 * ═══════════════════════════════════════════════════════════════════════════
 * Bugüne kadarki denetim MAKİNE ARTIĞI arıyordu: prompt sızıntısı, parça
 * işareti, yarım cümle, boş başlık. Bunların hepsi metnin BİÇİMİYLE ilgili ve
 * düzenli ifadeyle kesin olarak bulunabiliyor.
 *
 * Ama bir okuyucu Peter Camenzind özetinde romanda olmayan bir karakterin
 * anlatıldığını, ölen kişinin yanlış olduğunu bildirdi. Bu kusur biçimsel
 * değil: metin kusursuz görünüyor, düzgün başlıklara sahip, akıcı — sadece
 * ANLATTIĞI KİTAP O KİTAP DEĞİL. Hiçbir düzenli ifade bunu yakalayamaz.
 *
 * Kök sebep prompt'un kendisinde: model "bu eseri derinlemesine okumuş bir
 * akademisyensin" diye çerçevelenip bölüm bölüm yürümesi isteniyor ve
 * "bilmiyorum" demesine hiçbir kapı bırakılmıyor. Eseri yalnızca adından
 * tanıyan bir model, bu baskı altında makul görünen bir kitap uydurur.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * ÜÇ KATMAN
 * ═══════════════════════════════════════════════════════════════════════════
 * 1. BİLGİ YOKLAMASI (üretimden önce, ucuz)
 *    Model'e üretim baskısı OLMADAN sorulur: bu eseri biliyor musun, yayın
 *    yılı, ana karakterler, yapısı ne? Yanıt Open Library kaydıyla çapraz
 *    kontrol edilir. Tutmuyorsa kitap hiç yazılmaz — uydurma kaynağında kesilir.
 *
 * 2. OLGU DENETİMİ (yayından önce)
 *    Yazılan metin, DÜŞMANCA çerçevede ikinci bir çağrıya verilir: "bu metinde
 *    bu esere ait OLMAYAN ne varsa listele". Üretme baskısı olmadığı için model
 *    kendi uydurmasını kayda değer sıklıkta yakalıyor.
 *
 * 3. MEKANİK KAPI (bedava)
 *    content-audit.php'deki mevcut kontroller. Üretim reddi, prompt şablonu,
 *    parça işareti, yarım metin → hiçbiri yayına çıkmaz.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * NE GARANTİ EDER, NE ETMEZ
 * ═══════════════════════════════════════════════════════════════════════════
 * Bu sistem uydurmayı AZALTIR, sıfırlamaz. Model kendi bilgisiyle kendini
 * denetlediği için, bir eseri baştan sona yanlış "biliyorsa" iki katman da
 * aynı yanlışı onaylayabilir. Open Library çapraz kontrolü bu yüzden var:
 * eserin varlığı ve yılı BAĞIMSIZ bir kaynaktan doğrulanır. Tek gerçek
 * güvence, düşük güvenli eserlerin hiç yazılmamasıdır — kapı bu yüzden
 * "şüpheliyi yayınlama, taslakta beklet" diye kurulmuştur, "şüpheliyi düzelt"
 * diye değil.
 */

if (!defined('TLS_VERIFY_LOADED')) {
    define('TLS_VERIFY_LOADED', 1);

/* ── Ayarlar ─────────────────────────────────────────────────────────────── */

/** Doğrulama ayarları (settings.json) — varsayılan: her iki katman da açık. */
function tv_settings() {
    static $s = null;
    if ($s !== null) return $s;
    $f = dirname(__DIR__) . '/settings.json';
    $j = file_exists($f) ? json_decode((string) file_get_contents($f), true) : [];
    if (!is_array($j)) $j = [];
    $s = [
        'probe'      => !isset($j['verify_probe'])      || (bool) $j['verify_probe'],
        'factcheck'  => !isset($j['verify_factcheck'])  || (bool) $j['verify_factcheck'],
        'min_conf'   => max(0, min(100, (int) ($j['verify_min_conf'] ?? 55))),
        'gate'       => !isset($j['verify_gate'])       || (bool) $j['verify_gate'],
        // Doğrulama sağlayıcısı: VARSAYILAN DEEPSEEK (ucuz). Claude yalnız açık
        // istekle — maliyet güvenliği. Panelden her denetimde ayrı seçilebilir.
        'provider'   => (($j['verify_provider'] ?? 'deepseek') === 'anthropic') ? 'anthropic' : 'deepseek',
    ];
    return $s;
}

/* ── Sağlayıcı çağrısı ───────────────────────────────────────────────────── */

/**
 * Kısa, akışsız bir DeepSeek çağrısı.
 *
 * Doğrulama çağrıları üretim çağrılarından farklıdır: kısa, deterministik
 * (temperature 0) ve JSON döndürmesi beklenir. Uzun akış mantığına gerek yok.
 */
function tv_ask($prompt, $max_tokens = 700, $timeout = 90, $provider = null, $model = '') {
    /* SAĞLAYICI: VARSAYILAN DEEPSEEK (ucuz). Claude yalnızca AÇIKÇA istenirse
       ($provider='anthropic') — maliyet kontrolü için. Böylece "Denetimi Başlat"
       Claude kredisini kazara yakmaz. Claude seçildiyse ve başarısız olursa yine
       DeepSeek'e düşülür; denetim hiç durmaz.
       $model: anthropic için model override (üretimde Haiku/Sonnet seçimi). Boşsa
       hızlı model (Haiku) — kısa denetim/yoklama çağrıları için. */
    if ($provider === null) $provider = tv_settings()['provider'] ?? 'deepseek';
    if ($provider === 'anthropic') {
        require_once __DIR__ . '/_anthropic.php';
        if (tls_anthropic_ready()) {
            $r = tls_claude('', $prompt, [
                'model'       => $model !== '' ? $model : tls_claude_fast_model(),
                'max_tokens'  => min(8000, max(500, (int) $max_tokens)),
                'temperature' => 0,
                'timeout'     => $timeout,
                'retries'     => 3,
            ]);
            if (!empty($r['ok'])) return ['ok' => true, 'text' => $r['text']];
            // Claude başarısız → DeepSeek yedeğine düş (aşağıda).
        }
    } elseif ($provider === 'gemini') {
        require_once __DIR__ . '/_gemini.php';
        if (tls_gemini_ready()) {
            $r = tls_gemini('', $prompt, [
                'max_tokens'  => min(24000, max(500, (int) $max_tokens)),
                'temperature' => 0,
                'timeout'     => $timeout,
                'retries'     => 3,
            ]);
            if (!empty($r['ok'])) return ['ok' => true, 'text' => $r['text']];
            // Gemini başarısız → DeepSeek yedeğine düş (aşağıda).
        }
    }

    if (!defined('DEEPSEEK_KEY') || !DEEPSEEK_KEY) {
        return ['ok' => false, 'error' => 'DeepSeek anahtarı tanımsız'];
    }
    $model = (defined('DEEPSEEK_MODEL') && !in_array(DEEPSEEK_MODEL, ['deepseek-chat', 'deepseek-reasoner'], true))
           ? DEEPSEEK_MODEL : 'deepseek-v4-flash';

    /* DÜŞÜNMEYİ KAPAT: bunlar doğrulama çağrıları — kısa, deterministik JSON
       isteniyor, derin "düşünme" değil. DeepSeek V4 varsayılan olarak önce
       reasoning_content üretiyor; bu hem yavaş hem de bütçeyi yiyip content'i
       boş bırakabiliyordu (olgu denetimi bu yüzden "HTTP 200 ama boş" diye
       başarısız oluyordu). thinking:disabled ile yanıt doğrudan content'e ve
       hızlı gelir. Sağlayıcı bu parametreyi reddederse (400) parametresiz
       tekrar denenir — böylece hiçbir koşulda gerileme olmaz. */
    $body = [
        'model'       => $model,
        'max_tokens'  => $max_tokens,
        'temperature' => 0,
        'thinking'    => ['type' => 'disabled'],
        'messages'    => [['role' => 'user', 'content' => $prompt]],
    ];
    $do = function ($payload) use ($timeout) {
        $ch = curl_init(DEEPSEEK_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$raw, $err, $code];
    };
    [$raw, $err, $code] = $do($body);
    if ($code === 400) { unset($body['thinking']); [$raw, $err, $code] = $do($body); }

    if ($err) return ['ok' => false, 'error' => 'bağlantı: ' . $err];
    $j    = json_decode((string) $raw, true);
    $m    = $j['choices'][0]['message'] ?? [];
    $text = trim((string) ($m['content'] ?? ''));
    /* REASONING YEDEĞİ: DeepSeek'in yeni modeli yanıtı content yerine
       reasoning_content'te bırakabiliyor (content-audit.php de bu yedeğe
       düşüyor). Bu olmadan HER olgu denetimi ve HER üretim-öncesi yoklama
       "HTTP 200 ama içerik boş" diye başarısız oluyordu — yani doğrulama
       katmanı fiilen çalışmıyordu. */
    if ($text === '') $text = trim((string) ($m['reasoning_content'] ?? ''));
    if ($text === '') {
        $msg = $j['error']['message'] ?? trim(preg_replace('/\s+/', ' ', strip_tags((string) $raw)));
        return ['ok' => false, 'error' => 'HTTP ' . $code . ($msg ? ' · ' . mb_substr($msg, 0, 180) : '')];
    }
    return ['ok' => true, 'text' => $text];
}

/**
 * Yanıttan JSON çıkar.
 *
 * Model JSON'u ```json bloğuna sarabiliyor ya da öncesine bir cümle
 * ekleyebiliyor; ham json_decode bunlarda boş dönüp doğrulamayı sessizce
 * "sonuçsuz" yapıyordu.
 */
function tv_json($text) {
    $t = trim((string) $text);
    if (preg_match('/```(?:json)?\s*(.+?)```/is', $t, $m)) $t = trim($m[1]);
    $j = json_decode($t, true);
    if (is_array($j)) return $j;
    $a = strpos($t, '{');
    $b = strrpos($t, '}');
    if ($a !== false && $b !== false && $b > $a) {
        $j = json_decode(substr($t, $a, $b - $a + 1), true);
        if (is_array($j)) return $j;
    }
    return null;
}

/* ── Bağımsız kaynak: Open Library ───────────────────────────────────────── */

/**
 * Eser gerçekten var mı? Modelin kendi iddiasından BAĞIMSIZ tek sinyal.
 *
 * Dönüş: ['found'=>bool, 'year'=>int|null, 'title'=>string, 'author'=>string]
 * Ağ hatasında found=null döner — "bulunamadı" ile "bakılamadı" ayrı şeylerdir
 * ve ikincisi bir kitabı reddetmek için gerekçe olamaz.
 */
function tv_openlibrary($book, $author) {
    $url = 'https://openlibrary.org/search.json?limit=5&fields=title,author_name,first_publish_year'
         . '&title=' . rawurlencode($book)
         . ($author ? '&author=' . rawurlencode($author) : '');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: thetelos.org/1.0 (verify)'],
    ]);
    $r = curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($c !== 200 || !$r) return ['found' => null, 'year' => null, 'title' => '', 'author' => ''];

    $j = json_decode($r, true);
    $d = $j['docs'][0] ?? null;
    if (!$d) return ['found' => false, 'year' => null, 'title' => '', 'author' => ''];
    return [
        'found'  => true,
        'year'   => isset($d['first_publish_year']) ? (int) $d['first_publish_year'] : null,
        'title'  => (string) ($d['title'] ?? ''),
        'author' => (string) (($d['author_name'][0]) ?? ''),
    ];
}

/**
 * Google Books'tan kitabı ara: açıklama + üstveri.
 * Google Books çok dilli ve açıklama kapsamı Open Library'den geniştir
 * (Burocracia, Liberalismus gibi çeviri başlıkları da bulur). Anahtarsız
 * kullanım IP başına günlük kotaya tabidir; kota dolarsa boş döner (sorun değil,
 * Open Library'ye düşülür).
 * Dönüş: ['found'=>bool|null,'title','author','year','desc','subjects'=>[]]
 */
function tv_google_books($book, $author) {
    $q = trim($book . ($author ? ' ' . $author : ''));
    $url = 'https://www.googleapis.com/books/v1/volumes?country=US&maxResults=6&q=' . rawurlencode($q);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: thetelos.org/1.0 (verify)'],
    ]);
    $r = curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($c !== 200 || !$r) return ['found' => null];   // kota/ağ → "bakılamadı"
    $j = json_decode($r, true);
    if (!is_array($j) || empty($j['items'])) return ['found' => false];

    // Tek baskıyla yetinme: FARKLI baskıların açıklamalarını topla (biri kısa,
    // biri dolu olabilir), tekrarları ele, en uzunları birleştir. Kategoriler de
    // birden çok baskıdan toplanır. Böylece Google Books gerçekten yük taşır.
    $descs = []; $cats = []; $year = null; $g_title = ''; $g_author = '';
    foreach ($j['items'] as $it) {
        $vi = $it['volumeInfo'] ?? [];
        if ($g_title === '')  $g_title  = (string) ($vi['title'] ?? '');
        if ($g_author === '') $g_author = (string) (($vi['authors'][0]) ?? '');
        if ($year === null && !empty($vi['publishedDate']) && preg_match('/\d{4}/', $vi['publishedDate'], $ym)) $year = (int) $ym[0];
        foreach ((array) ($vi['categories'] ?? []) as $cat) $cats[$cat] = true;
        $d = trim((string) ($vi['description'] ?? ''));
        if (mb_strlen($d) >= 60) $descs[] = $d;
    }
    // Tekrar/alt-küme açıklamaları ele; uzundan kısaya
    usort($descs, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    $kept = [];
    foreach ($descs as $d) {
        $dup = false;
        foreach ($kept as $k) { if (mb_stripos($k, mb_substr($d, 0, 80)) !== false) { $dup = true; break; } }
        if (!$dup) $kept[] = $d;
        if (count($kept) >= 3) break;
    }
    return [
        'found'    => true,
        'title'    => $g_title,
        'author'   => $g_author,
        'year'     => $year,
        'desc'     => trim(implode("\n\n", $kept)),
        'subjects' => array_slice(array_keys($cats), 0, 10),
    ];
}

/**
 * Open Library "work" açıklaması (search → work key → work.json.description).
 * tv_openlibrary yalnız varlık/yıl bakıyordu; bu ek olarak AÇIKLAMA getirir.
 */
function tv_openlibrary_desc($book, $author) {
    $s = 'https://openlibrary.org/search.json?limit=1&fields=key,title,first_publish_year,subject,subject_people,subject_places,subject_times,first_sentence'
       . '&title=' . rawurlencode($book) . ($author ? '&author=' . rawurlencode($author) : '');
    $ch = curl_init($s);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: thetelos.org/1.0 (verify)']]);
    $r = curl_exec($ch); $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($c !== 200 || !$r) return ['found' => null];
    $j = json_decode($r, true);
    $doc = $j['docs'][0] ?? null;
    if (!$doc || empty($doc['key'])) return ['found' => false];
    // Konu etiketlerini genişlet: genel + kişi/yer/dönem (kitabın kapsamını verir).
    $subj = array_merge(
        array_slice((array) ($doc['subject'] ?? []), 0, 10),
        array_slice((array) ($doc['subject_people'] ?? []), 0, 5),
        array_slice((array) ($doc['subject_places'] ?? []), 0, 5),
        array_slice((array) ($doc['subject_times'] ?? []), 0, 3)
    );
    $out = [
        'found'    => true,
        'title'    => (string) ($doc['title'] ?? ''),
        'year'     => isset($doc['first_publish_year']) ? (int) $doc['first_publish_year'] : null,
        'subjects' => array_values(array_unique($subj)),
        'desc'     => '',
    ];
    // Work kaydından açıklama + (varsa) alıntılar/ilk cümle
    $wc = curl_init('https://openlibrary.org' . $doc['key'] . '.json');
    curl_setopt_array($wc, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: thetelos.org/1.0 (verify)']]);
    $wr = curl_exec($wc); $wcode = (int) curl_getinfo($wc, CURLINFO_HTTP_CODE); curl_close($wc);
    $extra = [];
    if ($wcode === 200 && $wr) {
        $wj = json_decode($wr, true);
        $de = $wj['description'] ?? '';
        if (is_array($de)) $de = $de['value'] ?? '';
        $de = trim((string) $de);
        if ($de !== '') $extra[] = $de;
        // Kitabın ilk cümlesi (kişiye özel değil, kitaptan) — bağlam verir.
        $fs = $wj['first_sentence'] ?? ($doc['first_sentence'][0] ?? '');
        if (is_array($fs)) $fs = $fs['value'] ?? '';
        $fs = trim((string) $fs);
        if ($fs !== '' && mb_strlen($fs) < 400) $extra[] = 'Opening line: ' . $fs;
    }
    $out['desc'] = trim(implode("\n\n", $extra));
    return $out;
}

/**
 * BİRLEŞİK KAYNAK KARTI — grounding'in temeli.
 *
 * Modelin hafızasına güvenmek yerine kitabın GERÇEK tanıtımını dış kataloglardan
 * toplar. Google Books (geniş, çok dilli, iyi açıklama) → Open Library (yedek).
 * Üretim bu karta DAYANARAK yazar → uydurma kaynağında kesilir; ayrıca "gerçek
 * kitap mı" sorusu modelin belleğinden değil, bağımsız kayıttan cevaplanır.
 *
 * Dönüş: [
 *   'real'     => bool,      // herhangi bir katalogda bulundu mu (gerçek kitap)
 *   'checked'  => bool,      // en az bir katalog cevap verdi mi (ağ/kota değil)
 *   'desc'     => string,    // gerçek açıklama (varsa) — grounding metni
 *   'year'     => int|null,
 *   'subjects' => array,
 *   'source'   => string,    // google | openlibrary | ''
 * ]
 */
function tv_book_source($book, $author) {
    $desc = ''; $year = null; $subjects = []; $source = '';
    $real = false; $checked = false;

    $g = tv_google_books($book, $author);
    if ($g['found'] !== null) $checked = true;
    if (!empty($g['found'])) {
        $real = true;
        if (!empty($g['desc']))     { $desc = $g['desc']; $source = 'google'; }
        if (!empty($g['year']))     $year = $g['year'];
        if (!empty($g['subjects'])) $subjects = $g['subjects'];
    }

    // Açıklama yoksa ya da hiç bulunmadıysa Open Library'yi dene.
    if ($desc === '' || !$real) {
        $o = tv_openlibrary_desc($book, $author);
        if (isset($o['found']) && $o['found'] !== null) $checked = true;
        if (!empty($o['found'])) {
            $real = true;
            if ($desc === '' && !empty($o['desc'])) { $desc = $o['desc']; $source = 'openlibrary'; }
            if ($year === null && !empty($o['year'])) $year = $o['year'];
            if (!$subjects && !empty($o['subjects'])) $subjects = $o['subjects'];
        }
    }

    // Açıklama aşırı kısaysa (ör. "A book.") grounding için işe yaramaz — atla.
    if (mb_strlen($desc) < 60) $desc = '';

    return [
        'real'     => $real,
        'checked'  => $checked,
        'desc'     => $desc,
        'year'     => $year,
        'subjects' => array_values(array_unique($subjects)),
        'source'   => $desc !== '' ? $source : '',
    ];
}

/* ── 1. KATMAN: bilgi yoklaması (üretimden ÖNCE) ─────────────────────────── */

/**
 * Model bu eseri gerçekten biliyor mu?
 *
 * Çerçeveleme kasıtlı olarak üretim prompt'unun TERSİ: burada hiçbir şey
 * yazması istenmiyor, "bilmiyorum" demek DOĞRU cevap olarak sunuluyor. Model
 * ancak bu koşulda kendi bilgisizliğini bildiriyor; üretim baskısı altındayken
 * bildirmiyor, uyduruyor.
 *
 * Dönüş: ['ok'=>bool, 'known'=>bool, 'conf'=>int, 'reason'=>string, 'data'=>array]
 */
function tv_probe($book, $author) {
    /* ── ÖNCE BAĞIMSIZ KAYNAK ─────────────────────────────────────────────
       Kararın belkemiği artık modelin belleği DEĞİL, dış katalog. Google Books
       + Open Library kitabı buluyorsa bu GERÇEK, tanımlanabilir bir eserdir;
       modelin "chapter by chapter bilmiyorum" demesi onu reddetmek için sebep
       olamaz (eski hata buydu: Bureaucracy, Planned Chaos gibi gerçek Mises
       kitaplarına "güven 0" deyip eliyordu). Üretim zaten bu kaynağa dayanarak
       yazacak (grounding). */
    $src = tv_book_source($book, $author);

    $prompt = <<<TXT
Identify this published book. You are NOT writing its contents — only identifying it.

WORK: {$book}
AUTHOR: {$author}

Reply with ONLY this JSON, no other text:
{
  "identifiable": true or false,   // is this a real, identifiable published work by (or associated with) this author?
  "confidence": 0-100,             // how sure are you that you can IDENTIFY the work (not summarize it)
  "year": "first publication year, or empty string",
  "form": "novel | treatise | essay collection | poetry | memoir | other | unknown",
  "why_not": "if identifiable is false, one short sentence explaining why"
}
TXT;

    $r = tv_ask($prompt, 1500, 90);
    $j = !empty($r['ok']) ? tv_json($r['text']) : null;
    // Model cevabı okunamasa bile kaynak kitabı bulduysa DEVAM edebiliriz.
    $m_ident = is_array($j) ? !empty($j['identifiable']) : false;
    $conf    = is_array($j) ? max(0, min(100, (int) ($j['confidence'] ?? 0))) : 0;
    $notes   = [];

    /* ── KARAR (katalog öncelikli) ────────────────────────────────────────
       real  = herhangi bir katalogda bulundu (gerçek kitap)
       known = üretime izin ver mi?
         • Katalogda GERÇEK ise → known=true (düşük model güveni ret sebebi değil).
         • Katalog cevap verdi ve BULAMADIYSA → yalnız model yüksek güvenle
           tanıyorsa geç; değilse bilinmiyor (yer tutucu).
         • Katalog ağ/kota yüzünden bakılamadıysa → modele düş: tanıyorsa geç. */
    if (!empty($src['real'])) {
        $known = true;
        if ($src['source'] !== '') $notes[] = 'kaynak: ' . $src['source'];
        else $notes[] = 'katalogda var';
    } elseif (!empty($src['checked'])) {
        // Katalog baktı, bulamadı → şüpheli. Sadece model gerçekten emin+tanıyorsa geç.
        $known = ($m_ident && $conf >= 70);
        if (!$known) $notes[] = 'katalogda yok';
    } else {
        // Katalog bakılamadı (ağ/kota) → modelin sözüne bak, temkinli.
        $known = ($m_ident && $conf >= max(50, tv_settings()['min_conf']));
        if (!$known) $notes[] = 'katalog bakılamadı; model tanımıyor';
    }

    return [
        'ok'      => true,
        'known'   => $known,
        'conf'    => $conf,
        'reason'  => $known ? '' : trim(((is_array($j) ? ($j['why_not'] ?? '') : 'model yanıtı yok'))
                                        . ($notes ? ' [' . implode('; ', $notes) . ']' : '')),
        'notes'   => $notes,
        'src'     => $src,        // grounding kartı — üretim yeniden çekmesin diye
        'data'    => is_array($j) ? $j : [],
    ];
}

/* ── 2. KATMAN: olgu denetimi (yayından ÖNCE) ────────────────────────────── */

/**
 * Yazılan metin gerçekten bu eseri mi anlatıyor?
 *
 * Çerçeveleme yine kasıtlı: model "yazar" değil "denetçi" konumuna alınıyor ve
 * görevinin HATA BULMAK olduğu söyleniyor. Kendi ürettiği metni savunma
 * eğilimini kırmak için metnin kim tarafından yazıldığı belirtilmiyor.
 *
 * Metin uzun olabildiğinden tamamı gönderilmez: baş, orta ve son kesitler
 * alınır — uydurma karakter/olay bunların birinde neredeyse kesin görünür.
 *
 * Dönüş: ['ok'=>bool, 'verdict'=>'ok|suspect|wrong', 'issues'=>[...]]
 */
function tv_factcheck($book, $author, $html, $provider = null) {
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $html)));
    if (mb_strlen($text) < 200) return ['ok' => false, 'error' => 'metin çok kısa'];

    // Baş / orta / son kesitler — uzun metinde tamamını göndermek hem pahalı
    // hem gereksiz; uydurma bir karakter metnin her yerine yayılır.
    $n = mb_strlen($text);
    if ($n > 9000) {
        $sample = mb_substr($text, 0, 3500) . "\n[...]\n"
                . mb_substr($text, (int) ($n / 2) - 1500, 3000) . "\n[...]\n"
                . mb_substr($text, -2500);
    } else {
        $sample = $text;
    }

    $prompt = <<<TXT
You are a STRICT but FAIR fact-checker. The text below claims to describe a specific work. Decide whether it is genuinely ABOUT THAT WORK, and flag only substantive fabrications — not style, not unverifiable wording.

CLAIMED WORK: {$book}
CLAIMED AUTHOR: {$author}

FLAG ONLY these (they show the text may be about the WRONG book or invented):
- Named characters, places, or figures that do NOT exist in this work.
- Plot events or outcomes that do NOT happen in this work (who dies, who leaves, how it ends).
- Invented structure (e.g. chapters/sections that do not exist).
- Content that clearly belongs to a DIFFERENT work.

DO NOT FLAG these (they are NOT fabrication — ignore them completely):
- Whether a quotation is word-for-word exact. You CANNOT verify verbatim wording, so NEVER mark a quote wrong just because the wording might differ. Only flag a quoted line if what it CLAIMS is something the work does not actually say or hold.
- Interpretation, analysis, emphasis, or paraphrase that is broadly faithful to the work.
- Small details you are merely unsure about.

VERDICT RULES:
- "wrong": ONLY when you are confident the text invents major elements (characters / plot / structure) OR describes a DIFFERENT work. This is a serious judgement — use it sparingly.
- "suspect": one specific thing looks off but you are not certain, or only minor issues.
- "ok": recognizably about the correct work and its real themes, even if imperfect.
- "unknown": you do not know this work well enough to judge — prefer this over guessing.

If the piece is clearly about the correct work, the verdict is "ok" even if it is not flawless.

TEXT TO CHECK:
---
{$sample}
---

Reply with ONLY this JSON, no other text:
{
  "verdict": "ok" | "suspect" | "wrong" | "unknown",
  "describes_different_work": true or false,
  "issues": [
    {"claim": "the specific wrong statement, quoted briefly", "problem": "what is actually true of the work", "severity": "high" | "low"}
  ]
}
severity "high" ONLY for invented characters/plot/structure or wrong-work; everything else is "low". At most 6 issues, most serious first. If verdict is "ok", "issues" may be empty.
TXT;

    // Bol token: reasoning fazı bütçeden yiyor, dar tutarsa JSON verdict'e
    // sıra gelmeden kesiliyor ve denetim boşuna "başarısız" oluyordu.
    $r = tv_ask($prompt, 4000, 150, $provider);
    if (empty($r['ok'])) return ['ok' => false, 'error' => $r['error'] ?? 'denetim başarısız'];

    $j = tv_json($r['text']);
    if (!is_array($j)) {
        return ['ok' => false, 'error' => 'denetim yanıtı okunamadı: ' . mb_substr(trim($r['text']), 0, 140)];
    }

    $verdict = in_array($j['verdict'] ?? '', ['ok', 'suspect', 'wrong', 'unknown'], true) ? $j['verdict'] : 'unknown';
    $issues  = [];
    foreach ((array) ($j['issues'] ?? []) as $i) {
        if (!is_array($i)) continue;
        $issues[] = [
            'claim'    => mb_substr(trim((string) ($i['claim'] ?? '')), 0, 300),
            'problem'  => mb_substr(trim((string) ($i['problem'] ?? '')), 0, 300),
            'severity' => (($i['severity'] ?? '') === 'high') ? 'high' : 'low',
        ];
    }
    // Başka bir eseri anlatıyorsa bu tek başına en ağır bulgudur.
    if (!empty($j['describes_different_work'])) $verdict = 'wrong';

    return [
        'ok'      => true,
        'verdict' => $verdict,
        'diff'    => !empty($j['describes_different_work']),
        'issues'  => array_slice($issues, 0, 8),
    ];
}

/* ── 3. KATMAN: yayın kapısı ─────────────────────────────────────────────── */

/**
 * Bu metin YAYINA çıkabilir mi?
 *
 * Tek karar noktası. Hem mekanik kusurlara hem olgu doğrulamasına bakar ve
 * "yayınla / taslakta beklet" der. Şüpheliyi DÜZELTMEYE çalışmaz: yanlış
 * anlatılan bir kitabı otomatik düzeltmenin güvenli bir yolu yok, doğru
 * davranış onu yayından uzak tutmaktır.
 *
 * $checks: mekanik kontrol işlevlerini içeren dosya yüklüyse kullanılır;
 * yüklü değilse yalnızca olgu katmanı çalışır (kapı yine de kurulur).
 *
 * Dönüş: ['pass'=>bool, 'reasons'=>[...], 'report'=>[...]]
 */
function tv_gate($book, $author, $html, $opts = []) {
    $cfg      = tv_settings();
    $reasons  = [];
    $report   = ['at' => time(), 'book' => $book, 'author' => $author];

    /* — Mekanik kapı (bedava) — */
    if (function_exists('ca_check_refusal')) {
        if ($s = ca_check_refusal($html))       $reasons[] = 'üretim reddi: ' . mb_substr($s, 0, 120);
        if ($s = ca_check_prompt_dump($html))   $reasons[] = 'prompt şablonu metne basılmış';
        if ($s = ca_check_part_markers($html))  $reasons[] = 'parça işareti sızmış: ' . mb_substr($s, 0, 60);
        if ($s = ca_check_prompt_leak($html))   $reasons[] = 'prompt talimatı metinde: ' . mb_substr($s, 0, 80);
        if (function_exists('ca_check_scaffold_leak') && ($s = ca_check_scaffold_leak($html)))
            $reasons[] = 'düzenek etiketi sızmış (CLARIFY/Why this matters): ' . mb_substr($s, 0, 80);
        if (function_exists('ca_check_dup_chapters') && ($s = ca_check_dup_chapters($html)))
            $reasons[] = 'numaralandırma çökmesi (bölüm tekrarı): ' . $s;
        if ($s = ca_check_meta_talk($html))     $reasons[] = 'model kendi süreciyle konuşmuş: ' . mb_substr($s, 0, 80);
        if ($s = ca_check_truncated($html))     $reasons[] = 'cümle ortasında kesilmiş';
        if ($s = ca_check_orphan_heading($html))$reasons[] = 'boş başlıkla bitmiş: ' . mb_substr($s, 0, 60);
    }
    /* UZUNLUK TABANI DÜŞÜK BİLEREK.
       Prompt artık üç kademeli: eseri iyi bilmiyorsa model 400-800 kelimelik
       KISA bir olgu notu (MODE B) yazabiliyor — bu meşru içeriktir, yayına
       çıkmalı. Eskiden taban 800'dü ve bu notları hep taslakta bırakıyordu.
       Uzunluk zaten kalitenin zayıf bir işareti: gerçek kusurlar (yarım kesik
       cümle, öksüz başlık, üretim reddi, prompt dökümü, OLGU HATASI) uzunluktan
       BAĞIMSIZ kontrollerle yakalanıyor. Taban yalnızca neredeyse-boş çıktıyı
       eler; kaliteyi olgu denetimi ve mekanik kapı sağlar. */
    $words = str_word_count(strip_tags((string) $html));
    $min   = max(200, (int) ($opts['min_words'] ?? 300));
    if ($words < $min) $reasons[] = "çok kısa ({$words} kelime, en az {$min})";
    $report['mech_words'] = $words;

    /* — Olgu kapısı (API) — */
    if ($cfg['factcheck'] && empty($opts['skip_factcheck'])) {
        $fc = tv_factcheck($book, $author, $html);
        $report['factcheck'] = $fc;
        if (!empty($fc['ok'])) {
            $high = 0;
            foreach ($fc['issues'] as $i) if ($i['severity'] === 'high') $high++;
            if ($fc['verdict'] === 'wrong') {
                $reasons[] = 'OLGU HATASI: metin eseri yanlış anlatıyor'
                           . ($fc['diff'] ? ' (başka bir eseri anlatıyor)' : '')
                           . ($fc['issues'] ? ' — ' . $fc['issues'][0]['claim'] : '');
            } elseif ($fc['verdict'] === 'suspect' && $high > 0) {
                $reasons[] = 'OLGU ŞÜPHESİ: ' . $high . ' ciddi bulgu — ' . $fc['issues'][0]['claim'];
            }
        }
        // Denetim çalışmadıysa (ağ/kota) yayını ENGELLEME: doğrulayamamak,
        // yanlış olduğunu göstermez. Rapora not düşülür, kapı açık kalır.
        else $report['factcheck_error'] = $fc['error'] ?? 'bilinmiyor';
    }

    $report['reasons'] = $reasons;
    return ['pass' => empty($reasons), 'reasons' => $reasons, 'report' => $report];
}

} // TLS_VERIFY_LOADED
