<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');
@ini_set('display_errors', 0);
error_reporting(0);
set_time_limit(600);
ignore_user_abort(true);

$book         = trim($_POST['book_title']  ?? '');
$author       = trim($_POST['author_name'] ?? '');
$type         = trim($_POST['type']        ?? 'summary');
$post_status  = trim($_POST['post_status'] ?? 'draft');
$target_words = max(300, min(8000, (int)($_POST['max_tokens'] ?? 3000)));
$max_tokens   = min(16000, $target_words * 3);

if (!$book || !$author) { echo json_encode(array('ok'=>false,'error'=>'Eksik veri.','book'=>$book)); exit; }

$prompts  = file_exists(PROMPTS_FILE) ? json_decode(file_get_contents(PROMPTS_FILE), true) : array();
$template = trim(isset($prompts[$type]) ? $prompts[$type] : '');
if (!$template) { echo json_encode(array('ok'=>false,'error'=>'Prompt ayarlanmamis.','book'=>$book)); exit; }

$prompt = str_replace(
    array('{book_title}','{author_name}','{BOOK_TITLE}','{AUTHOR_NAME}'),
    array($book,$author,$book,$author), $template
) . "\n\nBook: {$book}\nAuthor: {$author}\nTarget length: approximately {$target_words} words.";

function bp_ac($payload, $timeout=580) {
    $ch = curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>$timeout,
        CURLOPT_HTTPHEADER=>array('Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY),
        CURLOPT_POSTFIELDS=>json_encode($payload),
    ));
    $r=curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return array($r,$c);
}

function bp_wr($url,$method,$body,$auth,$timeout=30){
    $ch=curl_init($url);
    curl_setopt_array($ch,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,
        CURLOPT_TIMEOUT=>$timeout,
        CURLOPT_HTTPHEADER=>array('Content-Type: application/json','Authorization: '.$auth),
        CURLOPT_POSTFIELDS=>json_encode($body)));
    $r=curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return array(json_decode($r,true),$c);
}

function bp_md2h($text){
    $text=str_replace(array("\r\n","\r"),"\n",$text);
    $text=preg_replace(array('/^#{1} \*\*(.+?)\*\*/m','/^#{2} \*\*(.+?)\*\*/m','/^#{3} \*\*(.+?)\*\*/m',
        '/^# (.+)/m','/^## (.+)/m','/^### (.+)/m','/\*\*(.+?)\*\*/'),
        array('<h1><strong>$1</strong></h1>','<h2><strong>$1</strong></h2>','<h3><strong>$1</strong></h3>',
         '<h1>$1</h1>','<h2>$1</h2>','<h3>$1</h3>','<strong>$1</strong>'),$text);
    $lines=explode("\n",$text); $html=''; $buf=array();
    $fl=function() use (&$buf,&$html){ if($buf){$html.='<p>'.implode(' ',$buf)."</p>\n"; $buf=array();} };
    foreach($lines as $l){
        $l=trim($l); if(!$l){$fl();continue;}
        if(preg_match('/^<(h[1-6]|hr)/',$l)){$fl();$html.=$l."\n";continue;}
        $buf[]=$l;
    }
    $fl(); return $html;
}

// İçerik üret
list($raw,$code) = bp_ac(array('model'=>(in_array(DEEPSEEK_MODEL,['deepseek-chat','deepseek-reasoner'],true)?'deepseek-v4-pro':DEEPSEEK_MODEL),'max_tokens'=>$max_tokens,'messages'=>array(array('role'=>'user','content'=>$prompt))));
if (!$raw||$code!==200) {
    $d2=json_decode($raw,true);
    $err=isset($d2['error']['message'])?$d2['error']['message']:"API Hatasi $code";
    echo json_encode(array('ok'=>false,'error'=>$err,'book'=>$book)); exit;
}
$d=json_decode($raw,true);
$content=isset($d['choices'][0]['message']['content'])?$d['choices'][0]['message']['content']:'';
if (!$content) { echo json_encode(array('ok'=>false,'error'=>'Bos icerik.','book'=>$book)); exit; }

// Meta + Kategori
$cats='philosophy,philosophy_of_religion,ethics,metaphysics,epistemology,logic,aesthetics,political_philosophy,history_of_philosophy,religion,theology,systematic_theology,christian_theology,islamic_theology,christianity,islam,judaism,buddhism,hinduism,atheism,agnosticism,history,world_history,ancient_history,medieval_history,modern_history,military_history,cultural_history,biography,autobiography,memoir,literature,classic_literature,world_literature,poetry,drama,novel,fiction,historical_fiction,science_fiction,dystopian_fiction,fantasy,horror,mystery,detective_fiction,romance,adventure,psychology,cognitive_psychology,social_psychology,psychoanalysis,sociology,anthropology,politics,political_science,economics,microeconomics,macroeconomics,education,law,international_law,science,physics,astronomy,chemistry,mathematics,statistics,biology,evolution,genetics,medicine,neuroscience,public_health,technology,computers,artificial_intelligence,programming,data_science,art,art_history,music,music_history,architecture,design,photography,film,theatre,geography,travel,culture,mythology,folklore,children,young_adult,self_help,personal_development,business,management,marketing,entrepreneurship';
$snippet=mb_substr(strip_tags($content),0,1500);
$mp="Return ONLY JSON for \"{$book}\" by {$author}:\n{\"excerpt\":\"max 155 chars\",\"meta_description\":\"max 155 chars\",\"categories\":[\"slug\"],\"quotes\":[{\"text\":\"quote\",\"source\":\"section\"}]}\nPick 2-5 from: {$cats}\n\n{$snippet}";
list($rm)=bp_ac(array('model'=>(in_array(DEEPSEEK_MODEL,['deepseek-chat','deepseek-reasoner'],true)?'deepseek-v4-pro':DEEPSEEK_MODEL),'max_tokens'=>600,'messages'=>array(array('role'=>'user','content'=>$mp))),30);
$mt=preg_replace('/```json|```/','',isset(json_decode($rm,true)['choices'][0]['message']['content'])?json_decode($rm,true)['choices'][0]['message']['content']:'{}');
$meta=json_decode(trim($mt),true);
if (!$meta) $meta=array();

// Kapak
$cover_url='';
$gb_raw=@file_get_contents('https://www.googleapis.com/books/v1/volumes?'.http_build_query(array('q'=>$book,'maxResults'=>3,'printType'=>'books','fields'=>'items(volumeInfo(imageLinks))')));
$gb=json_decode($gb_raw,true);
foreach((isset($gb['items'])?$gb['items']:array()) as $item){
    $lnk=isset($item['volumeInfo']['imageLinks'])?$item['volumeInfo']['imageLinks']:array();
    $c=isset($lnk['thumbnail'])?$lnk['thumbnail']:(isset($lnk['smallThumbnail'])?$lnk['smallThumbnail']:'');
    if(!$c)continue;
    $c=str_replace(array('http://','&edge=curl'),array('https://',''),$c);
    $cover_url=preg_replace('/zoom=\d/','zoom=3',$c); break;
}
if(!$cover_url){
    $ol_raw=@file_get_contents('https://openlibrary.org/search.json?q='.urlencode($book).'&limit=3&fields=cover_i');
    $ol=json_decode($ol_raw,true);
    foreach((isset($ol['docs'])?$ol['docs']:array()) as $doc){
        if(!empty($doc['cover_i'])){$cover_url="https://covers.openlibrary.org/b/id/{$doc['cover_i']}-L.jpg";break;}
    }
}

// WP
$auth   = 'Basic '.base64_encode(WP_USER.':'.WP_APP_PASS);
$wp_api = rtrim(WP_URL,'/').'/wp-json/wp/v2';
$ep     = $type==='analysis'?'analysis':'posts';

$cat_ids=array();
$allowed=array_fill_keys(array_map('trim',explode(',',$cats)),true); // onaylı ~100 kategori
$meta_cats=isset($meta['categories'])?$meta['categories']:array();
foreach($meta_cats as $slug){
    $slug=preg_replace('/[^a-z0-9_-]/','',strtolower($slug));
    if($slug==='')continue;
    $slug_u=str_replace('-','_',$slug);
    if(empty($allowed[$slug])&&empty($allowed[$slug_u]))continue; // liste dışı → OLUŞTURMA, atla
    list($cats_r)=bp_wr("$wp_api/categories?slug=".urlencode($slug).'&per_page=1','GET',array(),$auth);
    if(!empty($cats_r[0]['id'])){$cat_ids[]=$cats_r[0]['id'];continue;}
    list($cats_r2)=bp_wr("$wp_api/categories?slug=".urlencode($slug_u).'&per_page=1','GET',array(),$auth);
    if(!empty($cats_r2[0]['id']))$cat_ids[]=$cats_r2[0]['id'];
}

$pb=array('title'=>$author?"$book -{$author}-":$book,'content'=>bp_md2h($content),
    'excerpt'=>isset($meta['excerpt'])?$meta['excerpt']:'','status'=>$post_status);
if($ep==='posts'&&$cat_ids)$pb['categories']=$cat_ids;
list($post,$pc)=bp_wr("$wp_api/$ep",'POST',$pb,$auth);
if($pc<200||$pc>=300){echo json_encode(array('ok'=>false,'error'=>isset($post['message'])?$post['message']:"WP $pc",'book'=>$book));exit;}
$pid=$post['id'];

if($author){
    list($terms)=bp_wr("$wp_api/authors?search=".urlencode($author).'&per_page=5','GET',array(),$auth);
    $tid=null; $edesc='';
    foreach((isset($terms)?$terms:array()) as $t){if(strtolower($t['name'])===strtolower($author)){$tid=$t['id'];$edesc=isset($t['description'])?$t['description']:'';break;}}
    if(!$tid){
        list($bio_r)=bp_ac(array('model'=>(in_array(DEEPSEEK_MODEL,['deepseek-chat','deepseek-reasoner'],true)?'deepseek-v4-pro':DEEPSEEK_MODEL),'max_tokens'=>200,'messages'=>array(array('role'=>'user','content'=>"Write a 2-3 sentence biography of \"{$author}\". English, encyclopedic."))),20);
        $bio=isset(json_decode($bio_r,true)['choices'][0]['message']['content'])?json_decode($bio_r,true)['choices'][0]['message']['content']:'';
        list($nt)=bp_wr("$wp_api/authors",'POST',array('name'=>$author,'description'=>$bio),$auth);
        $tid=isset($nt['id'])?$nt['id']:null;
    } elseif(empty($edesc)){
        list($bio_r)=bp_ac(array('model'=>(in_array(DEEPSEEK_MODEL,['deepseek-chat','deepseek-reasoner'],true)?'deepseek-v4-pro':DEEPSEEK_MODEL),'max_tokens'=>200,'messages'=>array(array('role'=>'user','content'=>"Write a 2-3 sentence biography of \"{$author}\". English, encyclopedic."))),20);
        $bio=isset(json_decode($bio_r,true)['choices'][0]['message']['content'])?json_decode($bio_r,true)['choices'][0]['message']['content']:'';
        if($bio)bp_wr("$wp_api/authors/$tid",'POST',array('description'=>$bio),$auth);
    }
    if($tid)bp_wr("$wp_api/$ep/$pid",'POST',array('authors'=>array($tid)),$auth);
}

$meta_desc=isset($meta['meta_description'])?$meta['meta_description']:'';
if($meta_desc)bp_wr("$wp_api/$ep/$pid",'POST',array('meta'=>array('_yoast_wpseo_metadesc'=>$meta_desc)),$auth);

bp_wr("$wp_api/$ep/$pid",'POST',array('meta'=>array('_tls_disable_quotes'=>'1')),$auth);
$raw_quotes=isset($meta['quotes'])?$meta['quotes']:array();
$clean_quotes=array();
foreach($raw_quotes as $q){
    $t=trim(isset($q['text'])?$q['text']:''); $s=trim(isset($q['source'])?$q['source']:'');
    if($t)$clean_quotes[]=array('text'=>$t,'source'=>$s);
}
if($clean_quotes)bp_wr("$wp_api/$ep/$pid",'POST',array('meta'=>array('_tls_quotes'=>$clean_quotes)),$auth);

$cover_set=false;
if($cover_url){
    $allowed=array('books.google.com','covers.openlibrary.org','lh3.googleusercontent.com','lh4.googleusercontent.com','lh5.googleusercontent.com');
    $host=parse_url($cover_url,PHP_URL_HOST);
    if(in_array($host,$allowed)){
        $img=@file_get_contents($cover_url);
        if($img&&strlen($img)>1000){
            $cm=curl_init("$wp_api/media");
            $fn=preg_replace('/[^a-z0-9]/','',strtolower($book)).'.jpg';
            curl_setopt_array($cm,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>30,
                CURLOPT_HTTPHEADER=>array('Authorization: '.$auth,"Content-Disposition: attachment; filename=\"$fn\"",'Content-Type: image/jpeg'),
                CURLOPT_POSTFIELDS=>$img));
            $mr=curl_exec($cm);curl_close($cm);
            $mid_d=json_decode($mr,true);$mid=isset($mid_d['id'])?$mid_d['id']:null;
            if($mid){bp_wr("$wp_api/$ep/$pid",'POST',array('featured_media'=>$mid),$auth);$cover_set=true;}
        }
    }
}

echo json_encode(array('ok'=>true,'book'=>$book,'author'=>$author,'post_id'=>$pid,
    'post_url'=>$post['link'],'edit_url'=>rtrim(WP_URL,'/')."/wp-admin/post.php?post=$pid&action=edit",
    'cover_set'=>$cover_set,'categories'=>$cat_ids,
    'word_count'=>str_word_count(strip_tags($content))));
