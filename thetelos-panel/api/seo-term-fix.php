<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$term_id = (int)($_POST['term_id'] ?? 0);
$type    = trim($_POST['type'] ?? 'category');
$name    = trim($_POST['name'] ?? '');

if (!$term_id || !$name) {
    echo json_encode(['ok'=>false,'error'=>'Eksik veri.']); exit;
}

ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();

$term = get_term($term_id);
$desc = $term ? trim($term->description) : '';

$taxonomy = $type === 'category' ? 'category' : 'author';
$context  = $type === 'category'
    ? "This is a category page on thetelos.org, a classical philosophy archive."
    : "This is an author page on thetelos.org, a classical philosophy archive.";

$content_hint = $desc
    ? "Description of this {$taxonomy}: {$desc}"
    : "Name: {$name}. {$context}";

$prompt = "Write an SEO meta description for the {$taxonomy} page \"{$name}\" on thetelos.org.\n\n"
    . "{$context}\n\n"
    . "{$content_hint}\n\n"
    . "Requirements:\n"
    . "- 1 complete, compelling sentence\n"
    . "- Must end with a period\n"
    . "- Maximum 120 characters\n"
    . "- Informative and relevant to the topic\n"
    . "- Respond with ONLY the meta description text, nothing else";

$ch = curl_init(DEEPSEEK_API_URL);
curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>20,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
    CURLOPT_POSTFIELDS=>json_encode([
        'model'     =>(in_array(DEEPSEEK_MODEL,['deepseek-chat','deepseek-reasoner'],true)?'deepseek-v4-flash':DEEPSEEK_MODEL),
        'max_tokens'=>150,
        'messages'  =>[['role'=>'user','content'=>$prompt]],
    ]),
]);
$raw  = curl_exec($ch); curl_close($ch);
$meta = trim(json_decode($raw,true)['choices'][0]['message']['content'] ?? '');

// Nokta ile bitmeyen metne nokta ekle
if ($meta && !preg_match('/[.!?]$/', $meta)) $meta .= '.';
// 150 karakteri geçerse son cümlede kes
if (mb_strlen($meta) > 150) {
    $cut  = mb_substr($meta, 0, 150);
    $last = max(mb_strrpos($cut,'.')?:0, mb_strrpos($cut,'!')?:0, mb_strrpos($cut,'?')?:0);
    $meta = $last > 20 ? mb_substr($cut, 0, $last+1) : mb_substr($cut, 0, mb_strrpos($cut,' ')?:150).'.';
}

if (!$meta) {
    echo json_encode(['ok'=>false,'error'=>'Meta oluşturulamadı.']); exit;
}

update_term_meta($term_id, 'wpseo_metadesc', $meta);
echo json_encode(['ok'=>true,'meta'=>$meta]);
