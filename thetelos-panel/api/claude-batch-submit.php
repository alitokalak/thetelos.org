<?php
/**
 * claude-batch-submit.php — Anthropic Batch API ile TOPLU özet üretimi başlat.
 *
 * "Yavaş ama ucuz" (−%50, 24 saate kadar async) yol. Her kitap için kendi
 * bilgisinden tek-çağrılık tam özet istenir (batch'e uygun bağımsız istekler).
 * Çalışan senkron worker'a DOKUNMAZ — tamamen ayrı, güvenli araç.
 *
 * POST: books=JSON [{book_title, author_name, target_words?}], claude_model=sonnet|haiku,
 *       target_words (genel, kitap kendi vermezse)
 * → jobs/cbatch_{id}.json yazar, batch_id döner.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
require_once __DIR__ . '/_anthropic.php';

if (!tls_anthropic_ready()) {
    echo json_encode(['ok' => false, 'error' => 'ANTHROPIC_KEY config.php’de yok — önce kredi/anahtar tanımla.']);
    exit;
}

$books = json_decode(trim($_POST['books'] ?? '[]'), true);
if (!is_array($books) || !$books) { echo json_encode(['ok' => false, 'error' => 'Kitap listesi boş.']); exit; }

$model_pick   = (trim($_POST['claude_model'] ?? 'sonnet') === 'haiku') ? 'haiku' : 'sonnet';
$model        = ($model_pick === 'haiku') ? tls_claude_fast_model() : tls_claude_quality_model();
$gen_words    = max(400, min(6000, (int) ($_POST['target_words'] ?? 1200)));

// Tür-uyarlı, uydurma-karşıtı sistem promptu (her istekte AYNI → cache'lenir).
$system =
    "You are writing a full book summary FROM YOUR OWN KNOWLEDGE for a books website, "
  . "in the voice of a thoughtful human essayist, entirely in English.\n"
  . "FORM: decide if the work is NARRATIVE (novel/story/play/poetry) or EXPOSITORY "
  . "(philosophy/history/science/essay). Open with a SHORT unlabeled overview (2–4 "
  . "sentences), then use a FEW natural ## headings that fit the form — for narrative "
  . "e.g. The Story / Characters / Themes / Why It Endures; for expository e.g. The "
  . "Core Argument / Key Ideas / Themes / Why It Matters. Omit any you can't fill. "
  . "Never force argument headings onto a novel.\n"
  . "ANTI-FABRICATION (hard limit): recount only what you TRULY know; never invent "
  . "character names, quotations, dates, or chapter titles. If you genuinely cannot "
  . "identify the work, output EXACTLY the single word UNKNOWN. Never add any note about "
  . "your own knowledge or its limits.";

$items = [];
$map   = [];
foreach (array_values($books) as $i => $b) {
    $title  = trim((string) ($b['book_title'] ?? ''));
    if ($title === '') continue;
    $author = trim((string) ($b['author_name'] ?? ''));
    $words  = max(400, min(6000, (int) ($b['target_words'] ?? $gen_words)));
    $who    = $author !== '' ? "\"$title\" by $author" : "\"$title\"";
    $cid    = 'bk_' . $i;
    $items[] = [
        'custom_id'   => $cid,
        'system'      => $system,
        'user'        => "Write a full, detailed summary of the book $who from your own knowledge only. "
                       . "Aim for about $words words (hard ceiling), using real known content — never padding with "
                       . "invented specifics. Markdown, English only. Output exactly UNKNOWN if you cannot identify it.",
        'model'       => $model,
        'max_tokens'  => min(16000, max(1200, (int) round($words * 1.9))),
        'temperature' => 0.3,
        'cache'       => true,   // sabit system ön-eki batch içinde önbellekten okunur
    ];
    $map[$cid] = ['book_title' => $title, 'author_name' => $author];
}
if (!$items) { echo json_encode(['ok' => false, 'error' => 'Geçerli kitap yok (başlık boş?).']); exit; }

$res = tls_claude_batch_submit($items);
if (empty($res['ok'])) { echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'gönderim hatası', 'http' => $res['http'] ?? 0]); exit; }

$jobs_dir = dirname(__DIR__) . '/jobs';
if (!is_dir($jobs_dir)) mkdir($jobs_dir, 0755, true);
$job = [
    'batch_id'    => $res['id'],
    'created_at'  => time(),
    'model'       => $model,
    'count'       => $res['count'],
    'status'      => $res['status'] ?: 'in_progress',
    'written'     => 0,      // taslak yazıldı mı (idempotent collect)
    'map'         => $map,   // custom_id → {book_title, author_name}
];
@file_put_contents("$jobs_dir/cbatch_" . preg_replace('/[^A-Za-z0-9_]/', '', $res['id']) . ".json",
    json_encode($job, JSON_UNESCAPED_UNICODE));

echo json_encode(['ok' => true, 'batch_id' => $res['id'], 'count' => $res['count'], 'status' => $job['status']]);
