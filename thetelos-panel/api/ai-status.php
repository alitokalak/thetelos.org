<?php
/**
 * ai-status.php — Hangi AI motorları BATCH'te kullanılabilir durumda?
 * Anahtar DEĞERİNİ göstermez; yalnız "tanımlı mı" (boolean) ve model kimliklerini
 * bildirir. Amaç: "Claude batch'te neden çalışmıyor?" sorusunu saniyede yanıtlamak.
 *
 * Kullanım: panelde giriş yaptıktan sonra tarayıcıda /thetelos-panel/api/ai-status.php
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('yetki yok'); }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_anthropic.php';

// Hangi Anthropic anahtar sabiti tanımlı? (değer gösterilmez)
$anthropic_key_const = '';
foreach (['ANTHROPIC_KEY', 'ANTHROPIC_API_KEY', 'CLAUDE_KEY', 'CLAUDE_API_KEY'] as $c) {
    if (defined($c) && constant($c)) { $anthropic_key_const = $c; break; }
}
$anthropic_ready = function_exists('tls_anthropic_ready') ? tls_anthropic_ready() : false;

$deepseek_ready = defined('DEEPSEEK_KEY') && DEEPSEEK_KEY && defined('DEEPSEEK_API_URL') && DEEPSEEK_API_URL;
$gbooks_ready   = defined('GOOGLE_BOOKS_KEY') && GOOGLE_BOOKS_KEY;

$q_model = function_exists('tls_claude_quality_model') ? tls_claude_quality_model() : '?';
$b_model = function_exists('tls_claude_best_model')    ? tls_claude_best_model()    : '?';

// ── CANLI TEST ÇAĞRISI (?probe=1) ──
// Anahtar "tanımlı" olsa bile çağrı 401 (yanlış anahtar) / 404 (yanlış model)
// dönebilir. Batch'te "hata" veya "temizlik 0" görünce buraya bak: HER model
// için gerçek HTTP kodu + hata metni. Küçük istek (max_tokens=8), ~kuruş.
$probe = null;
if (($_GET['probe'] ?? '') === '1' && $anthropic_ready && function_exists('tls_claude')) {
    $probe = [];
    foreach (['quality' => $q_model, 'best' => $b_model] as $label => $mdl) {
        $r = tls_claude('You are a test. Reply with exactly: OK', 'ping', [
            'model' => $mdl, 'max_tokens' => 8, 'temperature' => 0, 'timeout' => 40, 'retries' => 1,
        ]);
        $probe[$label] = [
            'model' => $mdl,
            'ok'    => !empty($r['ok']),
            'http'  => (int) ($r['http'] ?? 0),
            'text'  => !empty($r['ok']) ? mb_substr(trim((string) ($r['text'] ?? '')), 0, 40) : '',
            'error' => !empty($r['ok']) ? '' : mb_substr((string) ($r['error'] ?? '?'), 0, 300),
        ];
    }
}

echo json_encode([
    'ok' => true,
    'anthropic' => [
        'ready'        => (bool) $anthropic_ready,
        'key_constant' => $anthropic_key_const ?: '(tanımlı değil)',
        'models'       => [
            'fast'    => function_exists('tls_claude_fast_model') ? tls_claude_fast_model() : '?',
            'quality' => $q_model,
            'best'    => $b_model,
        ],
        'live_test' => $probe ?: '(çalıştırmak için sonuna ?probe=1 ekle)',
        'note' => $anthropic_ready
            ? 'Anahtar tanımlı. Batch\'te hata/temizlik-0 görüyorsan ?probe=1 ile canlı testi çalıştır: 401=yanlış anahtar, 404=yanlış model kimliği.'
            : 'DİKKAT: Anthropic anahtarı config.php\'de YOK/boş → Claude batch\'te HİÇ çalışmaz. Çözüm: config.php\'ye ANTHROPIC_KEY ekle.',
    ],
    'deepseek' => ['ready' => (bool) $deepseek_ready],
    'google_books' => ['ready' => (bool) $gbooks_ready],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
