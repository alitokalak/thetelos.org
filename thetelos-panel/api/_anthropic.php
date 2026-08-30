<?php
/**
 * _anthropic.php — Claude (Anthropic) API istemcisi. TEK giriş: tls_claude().
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * NEDEN VAR
 * ═══════════════════════════════════════════════════════════════════════════
 * Panelde "Anthropic" düğmesi ve config'de ANTHROPIC_MODEL/MAX_TOKENS vardı ama
 * gerçek bir Claude çağrısı HİÇBİR yerde yoktu; "Anthropic" seçilse bile kod
 * DeepSeek'e gidiyordu. Uydurma denetimini ve düzeltmeyi bağımsız, daha güçlü
 * bir modelle (Claude) yapabilmek için gerçek istemci burada.
 *
 * TASARIM İLKESİ: hiçbir çağrı sessizce donup kalmamalı. Bu yüzden:
 *   - CONNECTTIMEOUT + TIMEOUT her zaman var (sonsuz bekleme yok),
 *   - 429 / 529 (overloaded) / 5xx / ağ hatası → üstel geri çekilmeyle yeniden
 *     denenir; 4xx (400/401/403) kalıcıdır, denenmez,
 *   - uzun işler için on_beat geri-çağrısı (job heartbeat'ini tazeler),
 *   - dönüş HER ZAMAN yapılandırılmış: ['ok'=>bool, ...] — çağıran taraf asla
 *     ham/eksik veriyle kalmaz.
 */

if (!defined('TLS_ANTHROPIC_LOADED')) {
define('TLS_ANTHROPIC_LOADED', 1);

/** API anahtarını bul. İsim config.php'de değişebilir diye birkaç aday denenir. */
function tls_anthropic_key() {
    foreach (['ANTHROPIC_KEY', 'ANTHROPIC_API_KEY', 'CLAUDE_KEY', 'CLAUDE_API_KEY'] as $c) {
        if (defined($c) && constant($c)) return (string) constant($c);
    }
    return '';
}

/** Claude kullanılabilir mi? (anahtar tanımlı mı) */
function tls_anthropic_ready() {
    return tls_anthropic_key() !== '';
}

/** Kullanılacak model: override > config > makul varsayılan. */
function tls_anthropic_model($override = '') {
    if ($override !== '') return $override;
    if (defined('ANTHROPIC_MODEL') && ANTHROPIC_MODEL) return (string) ANTHROPIC_MODEL;
    return 'claude-haiku-4-5-20251001';
}

/** HIZLI/UCUZ model — olgu denetimi, yoklama, meta gibi kısa çağrılar için.
   NEDEN AYRI: config'deki ANTHROPIC_MODEL güncelliğini yitirmiş olabiliyor
   (sonnet-4-20250514 artık 404 dönüyordu). Bu kısa işler için güncel Haiku
   sabit; istenirse config'den ANTHROPIC_FAST_MODEL ile değiştirilebilir. */
function tls_claude_fast_model() {
    return (defined('ANTHROPIC_FAST_MODEL') && ANTHROPIC_FAST_MODEL)
        ? (string) ANTHROPIC_FAST_MODEL : 'claude-haiku-4-5-20251001';
}

/** KALİTELİ model — yeniden yazma/özet gibi uzun, önemli üretim için. */
function tls_claude_quality_model() {
    return (defined('ANTHROPIC_QUALITY_MODEL') && ANTHROPIC_QUALITY_MODEL)
        ? (string) ANTHROPIC_QUALITY_MODEL : 'claude-sonnet-4-5-20250929';
}

/** EN GÜÇLÜ model — son-çare "bu eseri biliyor musun" için. Opus, geniş
   bilgi kapsamı + düşünme ile nadir eserleri Sonnet'ten çok daha iyi hatırlar
   (sohbette Opus'un bilip Sonnet'in UNKNOWN demesinin sebebi buydu). Config'de
   ANTHROPIC_BEST_MODEL ile değiştirilebilir. */
function tls_claude_best_model() {
    return (defined('ANTHROPIC_BEST_MODEL') && ANTHROPIC_BEST_MODEL)
        ? (string) ANTHROPIC_BEST_MODEL : 'claude-opus-4-8';
}

/**
 * Claude'a bir istek. Tek kullanıcı mesajı + isteğe bağlı system.
 *
 * @param string $system  Sistem yönergesi ('' ise gönderilmez).
 * @param string $user    Kullanıcı mesajı (asıl istem).
 * @param array  $opts    model, max_tokens, temperature, timeout, retries, on_beat(callable)
 * @return array ['ok'=>bool, 'text'=>string, 'stop_reason'=>string,
 *                'usage'=>array, 'error'=>string, 'http'=>int]
 */
function tls_claude($system, $user, $opts = []) {
    $key = tls_anthropic_key();
    if ($key === '') {
        return ['ok' => false, 'http' => 0,
                'error' => 'ANTHROPIC_KEY config.php’de tanımlı değil — Claude kullanılamıyor.'];
    }

    $model   = tls_anthropic_model($opts['model'] ?? '');
    $maxtok  = (int) ($opts['max_tokens'] ?? (defined('ANTHROPIC_MAX_TOKENS') ? ANTHROPIC_MAX_TOKENS : 4096));
    $maxtok  = max(256, min(16000, $maxtok));
    $temp    = array_key_exists('temperature', $opts) ? (float) $opts['temperature'] : 0.4;
    $timeout = max(15, (int) ($opts['timeout'] ?? 180));
    $retries = max(1, (int) ($opts['retries'] ?? 3));
    $beat    = $opts['on_beat'] ?? null;
    // SUNUCU ARAÇLARI (ör. web araması): verilirse payload'a eklenir. Web araması
    // Anthropic tarafında çalışır — panelden ekstra dış bağlantı GEREKTİRMEZ.
    // Uzun aramalarda API 'pause_turn' dönebilir; o zaman asistan turu geri
    // eklenip istek yinelenir (aşağıdaki tur döngüsü). max_turns bunu sınırlar.
    $tools     = (isset($opts['tools']) && is_array($opts['tools'])) ? $opts['tools'] : null;
    $max_turns = max(1, (int) ($opts['max_turns'] ?? ($tools ? 5 : 1)));
    // DÜŞÜNME (adaptive): yeni modeller (Opus 4.6+/5, Sonnet 5, Fable) nadir
    // eserleri düşünerek çok daha iyi hatırlar. Düşünme açıkken bu modeller
    // 'temperature' parametresini REDDEDER (400) → o yüzden düşünme varsa
    // temperature göndermeyiz. ['type'=>'adaptive'] gibi bir dizi beklenir.
    $thinking  = (isset($opts['thinking']) && is_array($opts['thinking'])) ? $opts['thinking'] : null;

    $messages = [['role' => 'user', 'content' => (string) $user]];

    /* Tek bir HTTP denemesi (geçici hatalarda kendi içinde yeniden dener).
       @return array ['j'=>decoded|null, 'code'=>int, 'err'=>string] */
    $do_request = function (array $payload) use ($key, $timeout, $retries, $beat) {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $lastErr = ''; $lastCode = 0;
        $stripped_temp = false;   // temperature 400'ünü bir kez temizle
        for ($try = 1; $try <= $retries; $try++) {
            $ch = curl_init('https://api.anthropic.com/v1/messages');
            $copts = [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_HTTPHEADER     => [
                    'content-type: application/json',
                    'x-api-key: ' . $key,
                    'anthropic-version: 2023-06-01',
                ],
                CURLOPT_POSTFIELDS     => $body,
            ];
            if (is_callable($beat)) {
                $copts[CURLOPT_NOPROGRESS]       = false;
                $copts[CURLOPT_XFERINFOFUNCTION] = function () use ($beat) { $beat(); return 0; };
            }
            curl_setopt_array($ch, $copts);
            $raw  = curl_exec($ch);
            $err  = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            $lastCode = $code;

            if ($err) {                       // ağ hatası → geçici
                $lastErr = 'bağlantı: ' . $err;
                if ($try < $retries) { sleep(min(20, 3 * $try)); continue; }
                return ['j' => null, 'code' => 0, 'err' => $lastErr];
            }
            $j = json_decode((string) $raw, true);
            if ($code >= 200 && $code < 300 && is_array($j)) {
                return ['j' => $j, 'code' => $code, 'err' => ''];
            }
            $emsg    = $j['error']['message'] ?? trim(preg_replace('/\s+/', ' ', strip_tags((string) $raw)));
            $lastErr = 'HTTP ' . $code . ($emsg ? ' · ' . mb_substr($emsg, 0, 200) : '');
            // Yeni modeller (Opus 4.6+/5, Sonnet 5, Fable) 'temperature'ı reddeder
            // (400). Bir kez temizleyip tekrar dene — model değişse de kod çalışsın.
            if ($code === 400 && !$stripped_temp && isset($payload['temperature'])
                && stripos($emsg, 'temperature') !== false) {
                unset($payload['temperature']);
                $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
                $stripped_temp = true;
                continue;   // bu denemeyi harcamadan yeniden gönder
            }
            // 429 / 529 / 5xx → geçici; 4xx → kalıcı.
            if ($code === 429 || $code === 529 || $code >= 500) {
                if ($try < $retries) { sleep(min(40, 5 * $try * ($code === 529 ? 2 : 1))); continue; }
            }
            return ['j' => null, 'code' => $code, 'err' => $lastErr];
        }
        return ['j' => null, 'code' => $lastCode, 'err' => $lastErr ?: 'bilinmeyen hata'];
    };

    $collected  = '';   // nihai yazılan metin (en son metinli tur kazanır)
    $last_stop  = '';
    $last_usage = [];
    $last_err   = ''; $last_code = 0;

    for ($turn = 1; $turn <= $max_turns; $turn++) {
        $payload = [
            'model'      => $model,
            'max_tokens' => $maxtok,
            'messages'   => $messages,
        ];
        // Düşünme açıksa temperature GÖNDERME (yeni modeller reddeder); yoksa gönder.
        if ($thinking) $payload['thinking'] = $thinking;
        else           $payload['temperature'] = $temp;
        if ($tools) $payload['tools'] = $tools;
        if (trim((string) $system) !== '') $payload['system'] = (string) $system;

        $res  = $do_request($payload);
        $j    = $res['j'];
        if (!is_array($j)) {
            // Hata: elimizde önceki turdan metin varsa onu döndür, yoksa hata.
            if ($collected !== '') break;
            return ['ok' => false, 'http' => $res['code'], 'error' => $res['err']];
        }
        $last_code  = $res['code'];
        $last_stop  = (string) ($j['stop_reason'] ?? '');
        $last_usage = $j['usage'] ?? [];

        $turn_text = '';
        foreach (($j['content'] ?? []) as $blk) {
            if (($blk['type'] ?? '') === 'text') $turn_text .= (string) ($blk['text'] ?? '');
        }
        $turn_text = trim($turn_text);
        if ($turn_text !== '') $collected = $turn_text;   // nihai özet son turda gelir

        // Sunucu aracı (web araması) turu duraklattıysa → asistan turunu geri
        // ekleyip devam et; model arama sonuçlarıyla metni yazsın.
        if ($last_stop === 'pause_turn' && $tools && $turn < $max_turns) {
            $messages[] = ['role' => 'assistant', 'content' => $j['content'] ?? []];
            continue;
        }
        break;
    }

    if ($collected !== '') {
        return ['ok' => true, 'text' => $collected,
                'stop_reason' => $last_stop, 'usage' => $last_usage, 'http' => $last_code ?: 200];
    }
    return ['ok' => false, 'http' => $last_code,
            'error' => $last_err ?: ('boş içerik (stop_reason: ' . ($last_stop ?: '?') . ')')];
}

/**
 * SON ÇARE — Claude'un KENDİ bilgisinden kitap tanıtım metni.
 *
 * NE ZAMAN: Ne tam metin (Gutenberg/Archive) ne de Wikipedia-temelli Bilgi
 * Metni bulunabildiğinde, yer tutucu koymadan ÖNCE denenir. Amaç: Claude eseri
 * GERÇEKTEN ve GÜVENİLİR biliyorsa en azından 1 sayfalık olgusal bir tanıtım
 * yazsın; bilmiyorsa UYDURMASIN.
 *
 * UYDURMA KORUMASI (kritik):
 *   - System yönergesi: emin değilsen TEK KELİME "UNKNOWN" yaz, uydurma.
 *   - Model UNKNOWN dönerse ['unknown'=>true] → çağıran yer tutucuya düşer.
 *   - Boş/çok kısa çıktı da unknown sayılır.
 * Bu bir "son çare"dir: kaynak bulunamayan eserlerin bir kısmını (Claude'un
 * sağlam bildiği ünlü eserler) kurtarır, gerisini olduğu gibi bırakır.
 *
 * @return array ['ok'=>bool,'md'=>string,'unknown'=>bool,'error'=>string,'usage'=>array]
 */
function tls_claude_overview($book, $author, $opts = []) {
    if (!tls_anthropic_ready()) {
        return ['ok' => false, 'unknown' => true, 'error' => 'ANTHROPIC_KEY yok'];
    }
    $book   = trim((string) $book);
    $author = trim((string) $author);
    if ($book === '') return ['ok' => false, 'unknown' => true, 'error' => 'kitap adı boş'];

    $who = $author !== '' ? "\"$book\" by $author" : "\"$book\"";

    // HEDEF KELİME (TAVAN): batch'te seçilen kriter. Claude eseri iyi biliyorsa
    // bu civarda yazar ama GEÇMEZ; az biliyorsa daha kısa. 0/geçersizse 1200.
    $cap = (int) ($opts['target_words'] ?? 0);
    if ($cap < 200)  $cap = 1200;
    if ($cap > 8000) $cap = 8000;
    $cap_lo = max(120, (int) round($cap * 0.7));   // iyi bilinen eserde alt-hedef

    $system =
        "You are a careful literary reference writer. This is a strict "
      . "anti-fabrication task: everything you write must be TRUE, but you do NOT "
      . "need to have the work's full text memorized to write about it.\n\n"
      . "WHAT COUNTS AS 'KNOWING' THE WORK — you may write an overview if you can "
      . "reliably identify this specific work and state true things about it, EVEN "
      . "IF you do not remember its detailed contents. It is enough to reliably "
      . "know, for example: who the author is, what KIND of work this is (novel, "
      . "essay collection, compiled newspaper columns, treatise, poetry, etc.), the "
      . "period and context it comes from, and its general subject or the author's "
      . "characteristic themes. Write about exactly those things you are sure of.\n\n"
      . "ABSOLUTE RULES:\n"
      . "1. NEVER invent plot points, characters, quotes, specific dates, chapter "
      . "lists, or any concrete claim you are not sure of. If you don't know a "
      . "specific, OMIT it — do not guess. It is fine to write at the level you "
      . "actually know (e.g. 'a collection of Chesterton's essays from this period, "
      . "characteristically concerned with X') without fabricating specifics.\n"
      . "2. Write the overview as a clean published encyclopedia/reference entry "
      . "about the BOOK ONLY. NEVER write about yourself, your knowledge, your "
      . "confidence, your limits, or what you can/cannot say/identify/supply. Do "
      . "NOT address the reader and do NOT add any note, caveat, or disclaimer "
      . "about the grain of your knowledge (e.g. 'A note on the limits of what I "
      . "can say', 'I can reliably identify…', 'I do not have secure knowledge of…', "
      . "'I have deliberately not supplied…'). If you don't know a specific, just "
      . "OMIT it SILENTLY — no meta-commentary about the omission whatsoever. The "
      . "text must read as if written by a reference work, never by an AI.\n"
      . "3. Output EXACTLY the single word UNKNOWN — nothing else — ONLY when you "
      . "genuinely cannot identify this work at all: you can't tell what it is, you "
      . "can't distinguish it from a different work with a similar name, or you "
      . "would have to invent essentially everything. A merely obscure work you can "
      . "still place (author + kind + context) is NOT UNKNOWN — write what you know.\n"
      . "4. Do NOT pad with generic filler. Your length must follow how much you "
      . "TRULY know: write as much as you can while every single sentence stays "
      . "true. If you know the work well, aim for roughly $cap_lo–$cap words — but "
      . "treat $cap words as a HARD CEILING you must not exceed. If you know it only "
      . "in outline, write a short honest note well under that. Never add a sentence "
      . "you cannot vouch for just to reach a length. A short true text beats a long "
      . "padded one.\n"
      . "5. Work ONLY from your own knowledge. You have no web access and must not "
      . "claim to look anything up. If your own knowledge is not enough to identify "
      . "the work, that is an UNKNOWN — do not fill the gap with guesses.";

    $user =
        "Write a factual overview of the book $who — from your own knowledge only, "
      . "as thorough as that knowledge genuinely allows and no longer.\n\n"
      . "Target length: if you reliably know this work and its author in depth, "
      . "write a full overview of about $cap_lo–$cap words, and do NOT exceed $cap "
      . "words under any circumstances. If you know it only a little (e.g. the "
      . "author and roughly what kind of work it is), write a short factual entry "
      . "about just that much. Length must track how much you actually know, capped "
      . "at $cap words. Do NOT add any note about your own knowledge or its limits, "
      . "and do NOT talk about yourself or what you can/cannot say — write only "
      . "about the book, like an encyclopedia entry.\n\n"
      . "Cover what you actually know — as much as applies and you are sure of: "
      . "what kind of work it is, its author and their historical/intellectual "
      . "context, its central subject or argument or the author's characteristic "
      . "themes, how it fits the author's body of work, and its significance, "
      . "reception, or influence. Use Markdown with 2–5 short section headings "
      . "(##). Write in the same language as the book's title/audience where "
      . "natural, otherwise clear neutral prose.\n\n"
      . "ANTI-FABRICATION (absolute): do NOT reconstruct or invent a plot, "
      . "characters, quotes, dates, chapter lists, or any specific you are not "
      . "sure of — omit what you don't know rather than guess. Every sentence must "
      . "be something you actually know to be true. Output exactly UNKNOWN only if "
      . "you genuinely cannot identify this work at all (not merely because you "
      . "lack its detailed contents).";

    // max_tokens'i tavana göre boyutlandır (~1 kelime ≈ 1.6 token + pay); tavan
    // yükseldiğinde uzun ama gerçek metin yarıda kesilmesin.
    $mtok = (int) ($opts['max_tokens'] ?? min(16000, max(1500, (int) round($cap * 1.9))));

    $r = tls_claude($system, $user, [
        'model'       => $opts['model'] ?? tls_claude_fast_model(),
        'max_tokens'  => $mtok,
        'temperature' => 0.2,
        'timeout'     => (int) ($opts['timeout'] ?? 180),
        'on_beat'     => $opts['on_beat'] ?? null,
        // Düşünme (adaptive) verilirse geç: nadir eserleri daha iyi hatırlar.
        'thinking'    => (isset($opts['thinking']) && is_array($opts['thinking'])) ? $opts['thinking'] : null,
    ]);

    if (empty($r['ok'])) {
        return ['ok' => false, 'unknown' => false, 'error' => $r['error'] ?? 'claude hata', 'http' => $r['http'] ?? 0];
    }

    $md = trim((string) $r['text']);
    // UNKNOWN kaçışı: tek başına ya da metnin en başında geçiyorsa → bilmiyor.
    $probe = mb_strtoupper(preg_replace('/[^A-Za-z]/', '', mb_substr($md, 0, 40)));
    if (strpos($probe, 'UNKNOWN') === 0) {
        return ['ok' => false, 'unknown' => true, 'usage' => $r['usage'] ?? []];
    }
    // Çok kısa çıktı da güvenilmez → bilmiyor say. Eşik düşürüldü: Claude bir
    // eseri (yazar+tür+bağlam) sağlam bilip de içeriğini ezbere bilmiyorsa kısa
    // ama gerçek bir tanıtım yazabilir; bunu yer tutucuya düşürme. Yalnızca tek
    // cümlelik/işe yaramaz çıktıları ele.
    if (str_word_count(strip_tags($md)) < 70) {
        return ['ok' => false, 'unknown' => true, 'usage' => $r['usage'] ?? []];
    }
    // SAVUNMA: prompt'a rağmen model bazen kendi bilgi sınırları hakkında bir
    // "not"/itiraf paragrafı ekleyebiliyor (ör. "A note on the limits of what I
    // can say…", "I can reliably identify…", "I do not have secure knowledge…").
    // Bu meta-konuşma YAYINA ÇIKMAMALI — burada ayıklanır.
    $md = tls_strip_ai_meta($md);
    if (str_word_count(strip_tags($md)) < 70) {
        return ['ok' => false, 'unknown' => true, 'usage' => $r['usage'] ?? []];
    }
    return ['ok' => true, 'unknown' => false, 'md' => $md, 'usage' => $r['usage'] ?? []];
}

/** AI meta-konuşmasını / kendi bilgi sınırı itiraflarını metinden ayıkla.
   Yalnız bu KALIPLARI içeren PARAGRAFLARI (ya da o başlık altındaki bölümü)
   siler; gerçek içeriğe dokunmaz. Yayına AI itirafı sızmasın diye son kalkan. */
function tls_strip_ai_meta($md) {
    $md = (string) $md;
    if (trim($md) === '') return '';
    // Birinci tekil şahıs + bilgi/sınır/söyleme kalıpları (İng. + biraz TR).
    $pat = '/\b('
         . 'a note on (the )?limits|note on what i can|'
         . 'i can(not|\'t)? (reliably )?(identify|say|confirm|verify|provide|supply|tell)|'
         . 'i do not have (secure |reliable |access to )?(knowledge|information)|'
         . 'i don\'?t have (secure |reliable |access to )?(knowledge|information)|'
         . 'i have (deliberately|intentionally) (not |omitted|left out)|'
         . 'i am not (able|certain|confident)|i\'?m not (able|certain|confident)|'
         . 'as an ai|language model|my (training|knowledge) (data|cutoff)|'
         . 'bilgim(in)? (yok|sınırlı)|kesin (olarak )?bil(e|mi)|emin değilim|'
         . 'yapay zeka olarak'
         . ')\b/i';
    // Paragraflara böl (boş satırla), meta içerenleri at.
    $parts = preg_split('/\n{2,}/', $md);
    $keep  = [];
    foreach ($parts as $p) {
        $t = trim($p);
        if ($t === '') continue;
        // Yalnız başlık satırıysa (##) koru.
        $body = preg_replace('/^#{1,6}\s+.*$/m', '', $t);
        if (preg_match($pat, $body)) continue;   // meta paragrafı → at
        $keep[] = $t;
    }
    $out = trim(implode("\n\n", $keep));
    // Öksüz kalan son başlık(lar)ı (altındaki tek içeriği atılmışsa) temizle.
    $out = preg_replace('/\n{2,}#{1,6}\s+[^\n]+\s*$/', '', $out);
    return trim($out);
}

} // TLS_ANTHROPIC_LOADED
