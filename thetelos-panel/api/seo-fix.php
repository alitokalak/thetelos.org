<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$post_id = (int)($_POST['post_id'] ?? 0);
$field   = trim($_POST['field']   ?? 'both');
$title   = trim($_POST['title']   ?? '');
$author  = trim($_POST['author']  ?? '');

if (!$post_id || !$title) {
    echo json_encode(['ok'=>false,'error'=>'Eksik veri.']); exit;
}

@set_time_limit(120);

// WordPress'i yükle
ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();

$post = get_post($post_id);
if (!$post) { echo json_encode(['ok'=>false,'error'=>'Post bulunamadı.']); exit; }

$content_text = trim(strip_tags($post->post_content));
$snippet      = mb_substr($content_text, 0, 1500);

$system_prompt = 'You are a JSON generator. Respond with ONLY a valid JSON object. No explanations, no markdown, no backticks.';
$prompt = "Generate SEO metadata for the book \"{$title}\"" . ($author ? " by {$author}" : '') . ".\n\n"
    . "Respond with ONLY this JSON:\n"
    . "{\n"
    . "  \"excerpt\": \"1-2 compelling complete sentences. Must end with period. Max 120 chars.\",\n"
    . "  \"meta_description\": \"SEO description. Must end with period. Max 120 chars. Different from excerpt.\"\n"
    . "}\n\n"
    . "RULES: Complete sentences ending with period. Max 120 chars each. Based on:\n\n" . $snippet;

$ch = curl_init(DEEPSEEK_API_URL);
curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>30,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
    CURLOPT_POSTFIELDS=>json_encode([
        'model'     =>DEEPSEEK_MODEL,
        'max_tokens'=>400,
        'messages'  =>[
            ['role'=>'system','content'=>$system_prompt],
            ['role'=>'user',  'content'=>$prompt],
        ],
    ]),
]);
$raw = curl_exec($ch); curl_close($ch);
$raw_text = json_decode($raw,true)['choices'][0]['message']['content'] ?? '{}';

if(preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s',$raw_text,$m)) $raw_text=$m[1];
elseif(preg_match('/\{.*\}/s',$raw_text,$m)) $raw_text=$m[0];
$meta = json_decode(trim($raw_text),true) ?? [];

function trim_to_sentence($text,$max=150){
    $text=trim($text);
    if(!$text) return '';
    if(mb_strlen($text)<=$max){
        if(!preg_match('/[.!?]$/',$text)) $text.='.';
        return $text;
    }
    $cut=mb_substr($text,0,$max);
    $last=max(mb_strrpos($cut,'.')?:0,mb_strrpos($cut,'!')?:0,mb_strrpos($cut,'?')?:0);
    if($last>20) return mb_substr($cut,0,$last+1);
    $ls=mb_strrpos($cut,' ');
    return ($ls>20?mb_substr($cut,0,$ls):$cut).'.';
}

$new_excerpt = !empty($meta['excerpt'])          ? trim_to_sentence($meta['excerpt'])          : '';
$new_meta    = !empty($meta['meta_description']) ? trim_to_sentence($meta['meta_description']) : '';

if(!$new_excerpt && !$new_meta){
    echo json_encode(['ok'=>false,'error'=>'AI yanıt üretemedi.']); exit;
}

if(($field==='excerpt'||$field==='both') && $new_excerpt) wp_update_post(['ID'=>$post_id,'post_excerpt'=>$new_excerpt]);
if(($field==='meta'||$field==='both') && $new_meta) update_post_meta($post_id,'_yoast_wpseo_metadesc',$new_meta);

$response=['ok'=>true];
if($new_excerpt && ($field==='excerpt'||$field==='both')) $response['excerpt']=$new_excerpt;
if($new_meta    && ($field==='meta'   ||$field==='both')) $response['meta_description']=$new_meta;
echo json_encode($response);
