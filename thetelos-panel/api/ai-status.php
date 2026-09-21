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

echo json_encode([
    'ok' => true,
    'anthropic' => [
        'ready'        => (bool) $anthropic_ready,
        'key_constant' => $anthropic_key_const ?: '(tanımlı değil)',
        'models'       => [
            'fast'    => function_exists('tls_claude_fast_model')    ? tls_claude_fast_model()    : '?',
            'quality' => function_exists('tls_claude_quality_model') ? tls_claude_quality_model() : '?',
            'best'    => function_exists('tls_claude_best_model')    ? tls_claude_best_model()    : '?',
        ],
        'note' => $anthropic_ready
            ? 'Claude BATCH\'te çalışır: kaynak yoksa kendi bilgisinden yazar (Sonnet→Opus), bilmezse Haiku ile dürüst not, o da olmazsa yer tutucu.'
            : 'DİKKAT: Anthropic anahtarı config.php\'de YOK/boş → Claude batch\'te HİÇ çalışmaz; kaynaksız kitaplar DOĞRUDAN yer tutucuya düşer. Çözüm: config.php\'ye ANTHROPIC_KEY ekle.',
    ],
    'deepseek' => ['ready' => (bool) $deepseek_ready],
    'google_books' => ['ready' => (bool) $gbooks_ready],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
