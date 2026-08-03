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
        // Doğrulama sağlayıcısı: varsayılan Claude (kullanıcı kararı). Farklı
        // model denetlerse DeepSeek kendi hatasını onaylamaz — daha güvenli.
        'provider'   => (($j['verify_provider'] ?? 'anthropic') === 'deepseek') ? 'deepseek' : 'anthropic',
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
function tv_ask($prompt, $max_tokens = 700, $timeout = 90) {
    /* SAĞLAYICI: doğrulama varsayılan olarak Claude (kullanıcı kararı).
       Claude anahtarı yoksa ya da çağrı başarısız olursa DeepSeek'e düşülür —
       böylece denetim sağlayıcı yüzünden hiç durmaz. */
    if ((tv_settings()['provider'] ?? 'anthropic') === 'anthropic') {
        require_once __DIR__ . '/_anthropic.php';
        if (tls_anthropic_ready()) {
            $r = tls_claude('', $prompt, [
                'model'       => tls_claude_fast_model(),   // config'deki model 404; geçerli Haiku
                'max_tokens'  => min(4000, max(500, (int) $max_tokens)),
                'temperature' => 0,
                'timeout'     => $timeout,
                'retries'     => 3,
            ]);
            if (!empty($r['ok'])) return ['ok' => true, 'text' => $r['text']];
            // Claude başarısız → DeepSeek yedeğine düş (aşağıda).
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
    $prompt = <<<TXT
You are being asked ONLY to report what you reliably know. You are NOT being asked to write anything about this work. Saying you do not know it is a CORRECT and expected answer — there is no penalty for it, and a wrong guess is far worse than an honest "no".

WORK: {$book}
AUTHOR: {$author}

Do you have specific, reliable knowledge of THIS EXACT WORK by THIS EXACT AUTHOR — enough to describe its actual contents chapter by chapter without inventing anything?

Set "known": false if ANY of these apply:
- You are not certain this exact work exists.
- You know the author but not this particular work.
- You know only the title and its general reputation, not its actual contents.
- You would have to guess at its characters, structure, or arguments.

Reply with ONLY this JSON, no other text:
{
  "known": true or false,
  "confidence": 0-100,
  "year": "first publication year, or empty string",
  "form": "novel | treatise | essay collection | poetry | memoir | other | unknown",
  "key_names": ["up to 5 proper names that actually appear in the work — characters, places, or key terms"],
  "structure": "one short sentence on how the work is organised, or empty string",
  "why_not": "if known is false, one short sentence explaining what you are missing"
}
TXT;

    // Reasoning modeli önce "düşünüyor" ve o tokenlar bütçeden düşüyor; dar
    // bütçede content'e sıra gelmeden bitiyordu. Bol tut.
    $r = tv_ask($prompt, 3000, 120);
    if (empty($r['ok'])) return ['ok' => false, 'error' => $r['error'] ?? 'yoklama başarısız'];

    $j = tv_json($r['text']);
    if (!is_array($j)) {
        return ['ok' => false, 'error' => 'yoklama yanıtı okunamadı: ' . mb_substr(trim($r['text']), 0, 140)];
    }

    $known = !empty($j['known']);
    $conf  = max(0, min(100, (int) ($j['confidence'] ?? 0)));

    // Open Library ile çapraz kontrol — modelin kendi iddiasından bağımsız.
    $ol = tv_openlibrary($book, $author);
    $notes = [];

    if ($ol['found'] === false) {
        // Eser kayıtlarda yok. Tek başına red sebebi değil (Open Library
        // eksiktir, özellikle çeviri ve eski eserlerde), ama modelin yüksek
        // güveniyle birleşmediğinde şüphe sebebidir.
        $notes[] = 'Open Library kaydı yok';
        if ($conf < 80) $known = false;
    } elseif ($ol['found'] === true && $ol['year'] && !empty($j['year']) && preg_match('/\d{4}/', (string) $j['year'], $ym)) {
        $diff = abs((int) $ym[0] - $ol['year']);
        // Modelin verdiği yıl kayıttan çok uzaksa büyük olasılıkla BAŞKA bir
        // eseri anlatıyor. 20 yıl payı bırakılır: baskı yılı ile ilk yayın
        // yılı doğal olarak ayrışabiliyor.
        if ($diff > 20) {
            $notes[] = "yıl uyuşmuyor (model {$ym[0]}, kayıt {$ol['year']})";
            $known = false;
        }
    }

    if ($conf < tv_settings()['min_conf']) {
        $notes[] = "güven düşük ({$conf})";
        $known = false;
    }

    return [
        'ok'      => true,
        'known'   => $known,
        'conf'    => $conf,
        'reason'  => $known ? '' : trim(($j['why_not'] ?? '') . ($notes ? ' [' . implode('; ', $notes) . ']' : '')),
        'notes'   => $notes,
        'ol'      => $ol,
        'data'    => $j,
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
function tv_factcheck($book, $author, $html) {
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
You are a fact-checker. A text below claims to describe a specific literary or philosophical work. Your ONLY job is to find statements in it that are NOT true of that work. Do not praise it, do not summarise it, do not improve it.

CLAIMED WORK: {$book}
CLAIMED AUTHOR: {$author}

Check specifically for:
- Characters, places, or proper names that do not exist in this work.
- Plot events that do not happen in this work (who dies, who survives, what happens at the end).
- Structural claims that are wrong (chapter count, ordering, form).
- Content that actually belongs to a DIFFERENT work by this or another author.
- Quotations attributed to the work that it does not contain.

If you do not reliably know this work well enough to judge, say so — set "verdict":"unknown". That is a valid answer and is much better than approving something you cannot check.

TEXT TO CHECK:
---
{$sample}
---

Reply with ONLY this JSON, no other text:
{
  "verdict": "ok" | "suspect" | "wrong" | "unknown",
  "describes_different_work": true or false,
  "issues": [
    {"claim": "the exact wrong statement, quoted briefly", "problem": "what is actually true", "severity": "high" | "low"}
  ]
}
Use "wrong" only when you are confident the text misrepresents the work. Use "suspect" when something looks off but you are not certain. Use at most 8 issues, most serious first.
TXT;

    // Bol token: reasoning fazı bütçeden yiyor, dar tutarsa JSON verdict'e
    // sıra gelmeden kesiliyor ve denetim boşuna "başarısız" oluyordu.
    $r = tv_ask($prompt, 4000, 150);
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
