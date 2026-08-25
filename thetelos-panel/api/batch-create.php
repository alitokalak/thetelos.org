<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$type         = trim($_POST['type']         ?? 'summary');
$post_status  = trim($_POST['post_status']  ?? 'draft');
$max_tokens   = max(500, min(8000, (int)($_POST['max_tokens'] ?? 3000)));
$api_provider = trim($_POST['api_provider'] ?? 'deepseek');
// Claude seçildiğinde ana içerik modeli: sonnet (kaliteli, varsayılan) | haiku (ucuz).
$claude_model = (trim($_POST['claude_model'] ?? 'sonnet') === 'haiku') ? 'haiku' : 'sonnet';
$parts        = max(1, min(4, (int)($_POST['parts'] ?? 2)));
$workers      = max(1, min(5, (int)($_POST['workers'] ?? 1)));
$books_json   = trim($_POST['books']        ?? '[]');
// YENİDEN YAZ MODU: mevcut yazıyı bulup ÜSTÜNE yaz (yeni oluşturma). Listedeki
// eserlerin sitede zaten olanları, yeni dürüstlük kurallarıyla güncellenir.
$rewrite      = !empty($_POST['rewrite']) && $_POST['rewrite'] !== '0';

$books = json_decode($books_json, true);
if (!$books || !is_array($books) || count($books) === 0) {
    echo json_encode(['ok'=>false,'error'=>'Kitap listesi boş.']);
    exit;
}

// Boş kitapları filtrele
$books = array_values(array_filter($books, fn($b) => !empty(trim($b['book_title'] ?? ''))));
if (count($books) === 0) {
    echo json_encode(['ok'=>false,'error'=>'Geçerli kitap bulunamadı.']);
    exit;
}

// ── Aynı eseri bir batch'te BİRDEN ÇOK kez işleme (duplicate önleme) ──
// Liste 0-50/50-100 örtüşmesi veya parantez varyantları yüzünden aynı kitabı
// birden çok içerebiliyor. Başlık+yazarı normalize edip (parantez, diakritik,
// noktalama atılır) tekrarları burada eler → aynı işten 2 post oluşmaz.
function bc_norm_key($title, $author) {
    $n = function ($s) {
        $s = mb_strtolower(trim((string)$s), 'UTF-8');
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false && $t !== '') $s = $t;
        $s = preg_replace('/\([^()]*\)/', ' ', $s);   // parantez içini at
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);   // noktalama/diakritik → boşluk
        return trim(preg_replace('/\s+/', ' ', $s));
    };
    return $n($title) . '||' . $n($author);
}
$seen = [];
$deduped = [];
$removed_dupes = 0;
foreach ($books as $b) {
    $k = bc_norm_key($b['book_title'] ?? '', $b['author_name'] ?? '');
    if ($k === '||' || isset($seen[$k])) { if (isset($seen[$k])) $removed_dupes++; continue; }
    $seen[$k] = true;
    $deduped[] = $b;
}
$books = $deduped;
if (count($books) === 0) {
    echo json_encode(['ok'=>false,'error'=>'Geçerli kitap bulunamadı.']);
    exit;
}

$batch_id   = 'batch_' . uniqid('', true);
$jobs_dir   = dirname(__DIR__) . '/jobs';
if (!is_dir($jobs_dir)) mkdir($jobs_dir, 0755, true);
$batch_file = "$jobs_dir/{$batch_id}.json";

$batch = [
    'id'           => $batch_id,
    'status'       => 'running',
    'created_at'   => time(),
    'type'         => $type,
    'post_status'  => $post_status,
    'max_tokens'   => $max_tokens,
    'api_provider' => $api_provider,
    'claude_model' => $claude_model,
    'parts'        => $parts,
    'workers'      => $workers,
    'rewrite'      => $rewrite,   // true: mevcut yazıyı güncelle, yoksa atla
    // Yeniden yaz modunda yeni gövdeden taze excerpt+meta description üret
    // (gövdeye çıpalı, uydurma değil). '0' → dokunma (eski davranış). Vars. açık.
    'rewrite_meta' => (($_POST['rewrite_meta'] ?? '1') !== '0') ? '1' : '0',
    // Kademeli hakem (Gemini denetler, şüphede Claude): üretilen bilgi metnini
    // kaynaklarla kıyaslar; uydurma bulursa yayınlamaz. '0' → kapat. Vars. açık.
    'referee'      => (($_POST['referee'] ?? '1') !== '0') ? '1' : '0',
    // Kaynak-temelli özet (source) uzunluğu: kisa | standart | kapsamli (geriye dönük)
    'length'       => in_array($_POST['length'] ?? 'standart', ['kisa', 'standart', 'kapsamli'], true) ? $_POST['length'] : 'standart',
    // Serbest hedef kelime (kaydırıcı) — verilirse 'length' ön-ayarını geçersiz kılar.
    'source_words' => max(1000, min(8000, (int) ($_POST['source_words'] ?? 0))),
    // MANUEL KAYNAK (tekli): kullanıcı URL verir ya da metin yapıştırır → otomatik
    // edinim atlanır, doğrudan bu kaynaktan özet çıkarılır. (Tek kitaplık işler için.)
    'source_url'   => trim((string) ($_POST['source_url'] ?? '')),
    'source_text'  => (string) ($_POST['source_text'] ?? ''),
    'total'        => count($books),
    'done'         => 0,
    'ok'           => 0,
    'failed'       => 0,
    'books'        => array_map(fn($b) => [
        'book_title'  => trim($b['book_title']  ?? ''),
        'author_name' => trim($b['author_name'] ?? ''),
        'cover_url'   => trim($b['cover']        ?? ''),
        'pub_year'    => trim((string)($b['year'] ?? '')),
        'status'      => 'pending',
        'post_id'     => null,
        'post_url'    => null,
        'edit_url'    => null,
        'cover_set'   => false,
        'error'       => '',
    ], $books),
];

file_put_contents($batch_file, json_encode($batch, JSON_UNESCAPED_UNICODE));

echo json_encode(['ok'=>true, 'batch_id'=>$batch_id, 'total'=>count($books), 'removed_dupes'=>$removed_dupes]);
