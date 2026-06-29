<?php
/**
 * list-authors.php — Bir kategorinin önemli yazarlarını WIKIDATA'dan üret.
 * Veri/sorgu mantığı _wikidata-authors.php içinde (queue-create ile paylaşılır).
 *
 * POST: category, count (vars. 40, max 50), offset
 * Dönüş: { ok, category, offset, count, authors:[{author, era, note}], source }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
@ini_set('display_errors', 0);
set_time_limit(120);

require_once __DIR__ . '/_wikidata-authors.php';

$category = trim($_POST['category'] ?? '');
$count    = max(5, min(500, (int)($_POST['count'] ?? 40)));
$offset   = max(0, (int)($_POST['offset'] ?? 0));

$res = tls_wikidata_authors($category, $count, $offset);
if (!$res['ok']) { echo json_encode(['ok'=>false,'error'=>$res['error']]); exit; }

echo json_encode([
    'ok'       => true,
    'category' => $category,
    'offset'   => $offset,
    'count'    => count($res['authors']),
    'raw'      => $res['raw'] ?? count($res['authors']),  // ham QID sayısı (tükendi kararı için)
    'authors'  => $res['authors'],
    'source'   => 'wikidata',
], JSON_UNESCAPED_UNICODE);
