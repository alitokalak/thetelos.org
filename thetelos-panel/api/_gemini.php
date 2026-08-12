<?php
/**
 * _gemini.php — Google Gemini API istemcisi. TEK giriş: tls_gemini().
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * NEDEN VAR
 * ═══════════════════════════════════════════════════════════════════════════
 * Panelde üçüncü bir sağlayıcı denemek için (DeepSeek=ucuz varsayılan,
 * Claude=kaliteli/doğrulama, Gemini=ucuz+hızlı alternatif). Aynı sözleşme:
 * dönüş HER ZAMAN ['ok'=>bool, 'text'=>..., ...] — çağıran taraf ham/eksik
 * veriyle kalmaz; tv_ask() bu istemciyi diğerleriyle aynı şekilde kullanır.
 *
 * TASARIM İLKESİ (_anthropic.php ile aynı): hiçbir çağrı sessizce donmaz.
 *   - CONNECTTIMEOUT + TIMEOUT her zaman var,
 *   - 429 / 5xx / ağ hatası → üstel geri çekilmeyle yeniden denenir;
 *     4xx (400/401/403) kalıcıdır, denenmez,
 *   - uzun işler için on_beat geri-çağrısı job heartbeat'ini tazeler,
 *   - Gemini 2.5 "düşünen" modeldir: varsayılan thinkingBudget=0 ile
 *     düşünme kapatılır (hız + maliyet; kaynak-çıpalı yazımda gerek yok).
 *     İstenirse opts['thinking_budget'] ile açılır.
 */

if (!defined('TLS_GEMINI_LOADED')) {
define('TLS_GEMINI_LOADED', 1);

/** API anahtarını bul. İsim config.php'de değişebilir diye birkaç aday denenir. */
function tls_gemini_key() {
    foreach (['GEMINI_KEY', 'GEMINI_API_KEY', 'GOOGLE_API_KEY', 'GOOGLE_GEMINI_KEY'] as $c) {
        if (defined($c) && constant($c)) return (string) constant($c);
    }
    return '';
}

/** Gemini kullanılabilir mi? (anahtar tanımlı mı) */
function tls_gemini_ready() {
    return tls_gemini_key() !== '';
}

/** Kullanılacak model: override > config > makul varsayılan. */
function tls_gemini_model($override = '') {
    if ($override !== '') return $override;
    if (defined('GEMINI_MODEL') && GEMINI_MODEL) return (string) GEMINI_MODEL;
    return 'gemini-2.5-flash';
}

/**
 * Gemini'ye bir istek. Tek kullanıcı mesajı + isteğe bağlı system.
 *
 * @param string $system  Sistem yönergesi ('' ise gönderilmez).
 * @param string $user    Kullanıcı mesajı (asıl istem).
 * @param array  $opts    model, max_tokens, temperature, timeout, retries,
 *                        thinking_budget (varsayılan 0 = düşünme kapalı),
 *                        on_beat(callable)
 * @return array ['ok'=>bool, 'text'=>string, 'stop_reason'=>string,
 *                'usage'=>array, 'error'=>string, 'http'=>int]
 */
function tls_gemini($system, $user, $opts = []) {
    $key = tls_gemini_key();
    if ($key === '') {
        return ['ok' => false, 'http' => 0,
                'error' => 'GEMINI_KEY config.php’de tanımlı değil — Gemini kullanılamıyor.'];
    }

    $model   = tls_gemini_model($opts['model'] ?? '');
    $maxtok  = (int) ($opts['max_tokens'] ?? (defined('GEMINI_MAX_TOKENS') ? GEMINI_MAX_TOKENS : 4096));
    $maxtok  = max(256, min(24000, $maxtok));
    $temp    = array_key_exists('temperature', $opts) ? (float) $opts['temperature'] : 0.4;
    $timeout = max(15, (int) ($opts['timeout'] ?? 180));
    $retries = max(1, (int) ($opts['retries'] ?? 3));
    $beat    = $opts['on_beat'] ?? null;
    // Gemini 2.5 varsayılan olarak "düşünür" (gizli reasoning token'ı yakar).
    // Kaynak-çıpalı yazımda buna gerek yok → 0 ile kapat (hız + maliyet).
    $think   = array_key_exists('thinking_budget', $opts) ? (int) $opts['thinking_budget'] : 0;

    $gencfg = [
        'temperature'     => $temp,
        'maxOutputTokens' => $maxtok,
    ];
    // thinkingBudget yalnız destekleyen modellerde geçerli; -1 (dinamik) hariç
    // 0 ve pozitif değerleri gönder. Model reddederse (400) parametresiz retry.
    if ($think >= 0) $gencfg['thinkingConfig'] = ['thinkingBudget' => $think];

    $payload = [
        'contents'         => [['role' => 'user', 'parts' => [['text' => (string) $user]]]],
        'generationConfig' => $gencfg,
    ];
    if (trim((string) $system) !== '') {
        $payload['systemInstruction'] = ['parts' => [['text' => (string) $system]]];
    }

    $base = 'https://generativelanguage.googleapis.com/v1beta/models/'
          . rawurlencode($model) . ':generateContent';

    $do = function ($body) use ($base, $key, $timeout, $beat) {
        $ch = curl_init($base);
        $copts = [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'x-goog-api-key: ' . $key,
            ],
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
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
        return [$raw, $err, $code];
    };

    $lastErr = ''; $lastCode = 0;
    for ($try = 1; $try <= $retries; $try++) {
        [$raw, $err, $code] = $do($payload);

        // thinkingBudget desteklenmiyorsa (bazı modeller 400) → parametresiz retry.
        if ($code === 400 && isset($payload['generationConfig']['thinkingConfig'])) {
            unset($payload['generationConfig']['thinkingConfig']);
            [$raw, $err, $code] = $do($payload);
        }
        $lastCode = $code;

        // Ağ/bağlantı hatası → geçici say, yeniden dene.
        if ($err) {
            $lastErr = 'bağlantı: ' . $err;
            if ($try < $retries) { sleep(min(20, 3 * $try)); continue; }
            return ['ok' => false, 'http' => 0, 'error' => $lastErr];
        }

        $j = json_decode((string) $raw, true);

        if ($code >= 200 && $code < 300 && is_array($j)) {
            $cand = $j['candidates'][0] ?? null;
            $text = '';
            if ($cand) {
                foreach (($cand['content']['parts'] ?? []) as $p) {
                    if (isset($p['text'])) $text .= (string) $p['text'];
                }
            }
            $text = trim($text);
            if ($text !== '') {
                return ['ok' => true, 'text' => $text,
                        'stop_reason' => (string) ($cand['finishReason'] ?? ''),
                        'usage' => $j['usageMetadata'] ?? [], 'http' => $code];
            }
            // Boş: güvenlik bloğu ya da yalnız düşünmeyle token bitmiş olabilir.
            $fin = (string) ($cand['finishReason'] ?? '');
            $blk = (string) ($j['promptFeedback']['blockReason'] ?? '');
            $lastErr = 'boş içerik' . ($fin ? " (finish: $fin)" : '') . ($blk ? " (block: $blk)" : '');
            // MAX_TOKENS düşünmeye gitmiş olabilir → düşünmeyi kapatıp bir kez daha.
            if ($fin === 'MAX_TOKENS' && isset($payload['generationConfig']['thinkingConfig'])) {
                $payload['generationConfig']['thinkingConfig']['thinkingBudget'] = 0;
                if ($try < $retries) { continue; }
            }
            if ($try < $retries) { sleep(2); continue; }
            return ['ok' => false, 'http' => $code, 'error' => $lastErr];
        }

        // Hata gövdesi.
        $emsg    = $j['error']['message'] ?? trim(preg_replace('/\s+/', ' ', strip_tags((string) $raw)));
        $lastErr = 'HTTP ' . $code . ($emsg ? ' · ' . mb_substr($emsg, 0, 200) : '');

        // 429 (rate) / 5xx → geçici, geri çekilip dene. 4xx kalıcı.
        if ($code === 429 || $code >= 500) {
            if ($try < $retries) { sleep(min(40, 5 * $try)); continue; }
        }
        return ['ok' => false, 'http' => $code, 'error' => $lastErr];
    }
    return ['ok' => false, 'http' => $lastCode, 'error' => $lastErr ?: 'bilinmeyen hata'];
}

} // TLS_GEMINI_LOADED
