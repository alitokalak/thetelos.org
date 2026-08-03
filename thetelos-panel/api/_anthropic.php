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

} // TLS_ANTHROPIC_LOADED
