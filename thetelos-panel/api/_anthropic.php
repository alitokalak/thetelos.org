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

    $payload = [
        'model'       => $model,
        'max_tokens'  => $maxtok,
        'temperature' => $temp,
        'messages'    => [['role' => 'user', 'content' => (string) $user]],
    ];
    if (trim((string) $system) !== '') $payload['system'] = (string) $system;
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $lastErr = ''; $lastCode = 0;
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

        // Ağ/bağlantı hatası → geçici say, yeniden dene.
        if ($err) {
            $lastErr = 'bağlantı: ' . $err;
            if ($try < $retries) { sleep(min(20, 3 * $try)); continue; }
            return ['ok' => false, 'http' => 0, 'error' => $lastErr];
        }

        $j = json_decode((string) $raw, true);

        if ($code >= 200 && $code < 300 && is_array($j)) {
            $text = '';
            foreach (($j['content'] ?? []) as $blk) {
                if (($blk['type'] ?? '') === 'text') $text .= (string) ($blk['text'] ?? '');
            }
            $text = trim($text);
            if ($text !== '') {
                return ['ok' => true, 'text' => $text,
                        'stop_reason' => (string) ($j['stop_reason'] ?? ''),
                        'usage' => $j['usage'] ?? [], 'http' => $code];
            }
            $lastErr = 'boş içerik (stop_reason: ' . ($j['stop_reason'] ?? '?') . ')';
            if ($try < $retries) { sleep(2); continue; }
            return ['ok' => false, 'http' => $code, 'error' => $lastErr];
        }

        // Hata gövdesi.
        $emsg    = $j['error']['message'] ?? trim(preg_replace('/\s+/', ' ', strip_tags((string) $raw)));
        $lastErr = 'HTTP ' . $code . ($emsg ? ' · ' . mb_substr($emsg, 0, 200) : '');

        // 429 (rate) / 529 (overloaded) / 5xx → geçici, geri çekilip dene.
        // 4xx (400/401/403/404) → kalıcı, denemenin anlamı yok.
        if ($code === 429 || $code === 529 || $code >= 500) {
            if ($try < $retries) { sleep(min(40, 5 * $try * ($code === 529 ? 2 : 1))); continue; }
        }
        return ['ok' => false, 'http' => $code, 'error' => $lastErr];
    }
    return ['ok' => false, 'http' => $lastCode, 'error' => $lastErr ?: 'bilinmeyen hata'];
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
      . "2. Be honest about the grain of your knowledge: if you know the author, "
      . "type, and context but not the exact contents, write about those and say so "
      . "naturally, rather than inventing a detailed summary.\n"
      . "3. Output EXACTLY the single word UNKNOWN — nothing else — ONLY when you "
      . "genuinely cannot identify this work at all: you can't tell what it is, you "
      . "can't distinguish it from a different work with a similar name, or you "
      . "would have to invent essentially everything. A merely obscure work you can "
      . "still place (author + kind + context) is NOT UNKNOWN — write what you know.\n"
      . "4. Do NOT pad with generic filler. Your length must follow how much you "
      . "TRULY know: write as much as you can while every single sentence stays "
      . "true. For a work you know well, that can be long (1000+ words); for one "
      . "you know only in outline, keep it short. Never add a sentence you cannot "
      . "vouch for just to reach a length. A short true text beats a long padded "
      . "one.";

    $user =
        "Write a factual overview of the book $who — as thorough as your genuine "
      . "knowledge allows, and no longer. Do NOT aim for a fixed length: if you "
      . "reliably know this work and its author in depth, write a full, rich "
      . "overview (this may run 1000–1500 words); if you know it only in outline, "
      . "write a short honest note. Length must track certainty, never a target.\n\n"
      . "Cover what you actually know — as much as applies and you are sure of: "
      . "what kind of work it is, its author and their historical/intellectual "
      . "context, its central subject or argument or the author's characteristic "
      . "themes, how it fits the author's body of work, and its significance, "
      . "reception, or influence. Use Markdown with 2–5 short section headings "
      . "(##). Write in the same language as the book's title/audience where "
      . "natural, otherwise clear neutral prose.\n\n"
      . "ANTI-FABRICATION (absolute): do NOT reconstruct or invent a plot, "
      . "characters, quotes, dates, chapter lists, or any specific you are not "
      . "certain of — omit what you don't know rather than guess. Every sentence "
      . "must be something you actually know to be true. Output exactly UNKNOWN "
      . "only if you genuinely cannot identify this work at all (not merely because "
      . "you lack its detailed contents).";

    $r = tls_claude($system, $user, [
        'model'       => $opts['model'] ?? tls_claude_fast_model(),
        'max_tokens'  => (int) ($opts['max_tokens'] ?? 4000),
        'temperature' => 0.2,
        'timeout'     => (int) ($opts['timeout'] ?? 180),
        'on_beat'     => $opts['on_beat'] ?? null,
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
    return ['ok' => true, 'unknown' => false, 'md' => $md, 'usage' => $r['usage'] ?? []];
}

} // TLS_ANTHROPIC_LOADED
