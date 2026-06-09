<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
header('Content-Type: application/json');

$book        = trim($_POST['book_title']       ?? '');
$author      = trim($_POST['author_name']      ?? '');
$content     = trim($_POST['content']          ?? '');
$type        = trim($_POST['type']             ?? 'summary');
$post_status = trim($_POST['post_status']      ?? 'draft');
$excerpt     = trim($_POST['excerpt']          ?? '');
$meta_desc   = trim($_POST['meta_description'] ?? '');
$categories  = json_decode($_POST['categories'] ?? '[]', true) ?: [];
$quotes      = json_decode($_POST['quotes']    ?? '[]', true) ?: [];
$cover_url   = trim($_POST['cover_url']        ?? '');

if (!$book || !$content) {
    echo json_encode(['ok'=>false,'error'=>'Kitap adı ve içerik zorunludur.']);
    exit;
}

$auth   = 'Basic ' . base64_encode(WP_USER . ':' . WP_APP_PASS);
$wp_api = rtrim(WP_URL,'/') . '/wp-json/wp/v2';

function wp_req($url,$method,$body,$auth,$timeout=30){
    $ch=curl_init($url);
    $is_get = ($method === 'GET');
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CUSTOMREQUEST=>$method,
        CURLOPT_TIMEOUT=>$timeout,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: '.$auth],
    ]);
    if (!$is_get) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    return[json_decode($r,true),$c];
}

function md2html($text){
    $text=str_replace(["\r\n","\r"],"\n",$text);
    // Çok satırlı ** ** bloklarını temizle — DeepSeek bazen tüm bölümü bold yapıyor
    // Birden fazla satır kapsayan ** ... ** bloklarını bold'dan çıkar
    $text=preg_replace_callback('/\*\*(.+?)\*\*/s', function($m){
        // Sadece tek satırlı ise bold yap
        if (strpos($m[1], "\n") === false) return '<strong>'.$m[1].'</strong>';
        // Çok satırlıysa bold olmadan bırak
        return $m[1];
    }, $text);
    $text=preg_replace(['/^#{1} \*\*(.+?)\*\*/m','/^#{2} \*\*(.+?)\*\*/m','/^#{3} \*\*(.+?)\*\*/m','/^#{4} \*\*(.+?)\*\*/m',
        '/^# (.+)/m','/^## (.+)/m','/^### (.+)/m','/^#### (.+)/m'],
        ['<h1><strong>$1</strong></h1>','<h2><strong>$1</strong></h2>','<h3><strong>$1</strong></h3>','<h3><strong>$1</strong></h3>',
         '<h1>$1</h1>','<h2>$1</h2>','<h3>$1</h3>','<h3>$1</h3>'],$text);
    $lines=explode("\n",$text);$html='';$buf=[];$bqbuf=[];
    $fl=function()use(&$buf,&$html){if($buf){$html.='<p>'.implode(' ',$buf)."</p>\n";$buf=[];}};
    $fbq=function()use(&$bqbuf,&$html){if($bqbuf){$html.='<blockquote>'.implode(' ',$bqbuf)."</blockquote>\n";$bqbuf=[];}};
    foreach($lines as $l){
        $l=trim($l);
        if(!$l){$fl();$fbq();continue;}
        // Blockquote satırı: > ile başlıyor
        if(preg_match('/^&gt;\s*(.*)$/',$l,$m)||preg_match('/^>\s*(.*)$/',$l,$m)){
            $fl();$bqbuf[]=$m[1];continue;
        }
        if(preg_match('/^<(h[1-6]|hr)/',$l)){$fl();$fbq();$html.=$l."\n";continue;}
        $fbq();$buf[]=$l;
    }
    $fl();$fbq();return $html;
}

// ── Kategorileri bul/oluştur ──────────────────────────────
$cat_ids=[];
foreach($categories as $raw_slug){
    /* Her türlü formatı normalize et: boşluk, alt çizgi, tire → tire */
    $slug = strtolower(trim(preg_replace('/[\s_]+/', '-', $raw_slug)));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug); // sadece güvenli karakterler

    /* Önce normalize slug ile ara */
    [$cats]=wp_req("$wp_api/categories?slug=".urlencode($slug).'&per_page=1','GET',[],$auth);
    if(!empty($cats[0]['id'])){
        $cat_ids[]=$cats[0]['id'];
        continue;
    }

    /* Bulunamazsa alt çizgili versiyonu da dene */
    $slug_underscore = str_replace('-', '_', $slug);
    [$cats2]=wp_req("$wp_api/categories?slug=".urlencode($slug_underscore).'&per_page=1','GET',[],$auth);
    if(!empty($cats2[0]['id'])){
        $cat_ids[]=$cats2[0]['id'];
        continue;
    }

    /* Hâlâ yoksa isimle arama yap */
    $search_name = ucwords(str_replace('-', ' ', $slug));
    [$cats3]=wp_req("$wp_api/categories?search=".urlencode($search_name).'&per_page=5','GET',[],$auth);
    if(!empty($cats3)){
        foreach($cats3 as $c){
            $existing_norm = strtolower(preg_replace('/[\s_]+/', '-', $c['slug']));
            if($existing_norm === $slug){ $cat_ids[]=$c['id']; continue 2; }
        }
    }

    /* Gerçekten yoksa oluştur — artık her zaman tireli slug ile */
    $name = ucwords(str_replace('-', ' ', $slug));
    [$nc,$ncode]=wp_req("$wp_api/categories",'POST',['name'=>$name,'slug'=>$slug],$auth);
    if(!empty($nc['id'])) $cat_ids[]=$nc['id'];
}

// ── Post oluştur ──────────────────────────────────────────
$ep = $type==='analysis' ? 'analysis' : 'posts';
$post_title = $author ? "$book - $author" : $book;

// İçerikten sadece H1 başlığını sil — tema zaten post title'ı H1 olarak gösteriyor
// H2 korunuyor
$clean_content = preg_replace('/^# \*\*[^\n]+\*\*\n*/m', '', $content, 1);
$clean_content = preg_replace('/^# [^\n]+\n*/m', '', $clean_content, 1);
$clean_content = ltrim($clean_content);

// PART işaretlerini temizle
$clean_content = preg_replace('/%%PART[12]_END%%/i', '', $clean_content);
$clean_content = preg_replace('/%%PART[12]_START%%/i', '', $clean_content);

// DeepSeek'in bıraktığı meta notları temizle
// [Note: ...], [Already covered...], [This was already...] gibi köşeli parantez notları
$clean_content = preg_replace('/\[Note:[^\]]*\]/i', '', $clean_content);
$clean_content = preg_replace('/\[Already[^\]]*\]/i', '', $clean_content);
$clean_content = preg_replace('/\[This[^\]]*\]/i', '', $clean_content);
$clean_content = preg_replace('/\[.*?already.*?\]/is', '', $clean_content);
$clean_content = preg_replace('/\[.*?covered.*?\]/is', '', $clean_content);
$clean_content = preg_replace('/\[.*?Part 1.*?\]/is', '', $clean_content);
$clean_content = preg_replace('/\[.*?structure.*?\]/is', '', $clean_content);

// Boş H3 başlıklarını temizle (not silindikten sonra içeriksiz kalanlar)
// Örnek: "### Başlık\n\n### Diğer Başlık" — aralarında içerik yoksa ilkini sil
$clean_content = preg_replace('/(### [^\n]+\n+)(### )/m', '$2', $clean_content);

// Fazla boş satırları temizle (3+ boş satır → 2 boş satır)
$clean_content = preg_replace('/\n{4,}/', "\n\n\n", $clean_content);
$clean_content = trim($clean_content);

$pb = [
    'title'   => $post_title,
    'content' => md2html($clean_content),
    'excerpt' => $excerpt,
    'status'  => $post_status,
];
if($type!=='analysis' && $cat_ids) $pb['categories']=$cat_ids;

[$post,$pc]=wp_req("$wp_api/$ep",'POST',$pb,$auth);
if($pc<200||$pc>=300){
    echo json_encode(['ok'=>false,'error'=>$post['message']??"WP HTTP $pc"]);
    exit;
}
$pid=$post['id'];

// ── Author taxonomy ───────────────────────────────────────
if($author){
    [$terms]=wp_req("$wp_api/authors?search=".urlencode($author).'&per_page=10','GET',[],$auth);
    $tid=null;
    $existing_desc = '';
    foreach(($terms??[])as $t){
        if(strtolower($t['name'])===strtolower($author)){
            $tid=$t['id'];
            $existing_desc = $t['description'] ?? '';
            break;
        }
    }

    if(!$tid){
        // Yeni yazar — kısa biyografi üret
        $bio = '';
        $bio_prompt = "Write a concise 2-3 sentence biography of the author \"{$author}\" for a philosophy and literature website. Focus on their main works, philosophical contributions, and historical context. Write in English. Be factual and encyclopedic.";
        $bio_ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($bio_ch,[
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>20,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.ANTHROPIC_KEY,'anthropic-version: 2023-06-01'],
            CURLOPT_POSTFIELDS=>json_encode(['model'=>'claude-haiku-4-5-20251001','max_tokens'=>200,'messages'=>[['role'=>'user','content'=>$bio_prompt]]]),
        ]);
        $bio_raw = curl_exec($bio_ch); curl_close($bio_ch);
        $bio = json_decode($bio_raw,true)['content'][0]['text'] ?? '';

        [$nt]=wp_req("$wp_api/authors",'POST',['name'=>$author,'description'=>$bio],$auth);
        $tid=$nt['id']??null;
    } elseif($tid && !$existing_desc) {
        // Yazar var ama biyografisi yok — ekle
        $bio_prompt = "Write a concise 2-3 sentence biography of the author \"{$author}\" for a philosophy and literature website. Focus on their main works, philosophical contributions, and historical context. Write in English. Be factual and encyclopedic.";
        $bio_ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($bio_ch,[
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>20,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.ANTHROPIC_KEY,'anthropic-version: 2023-06-01'],
            CURLOPT_POSTFIELDS=>json_encode(['model'=>'claude-haiku-4-5-20251001','max_tokens'=>200,'messages'=>[['role'=>'user','content'=>$bio_prompt]]]),
        ]);
        $bio_raw = curl_exec($bio_ch); curl_close($bio_ch);
        $bio = json_decode($bio_raw,true)['content'][0]['text'] ?? '';
        if($bio) wp_req("$wp_api/authors/$tid",'POST',['description'=>$bio],$auth);
    }

    if($tid) wp_req("$wp_api/$ep/$pid",'POST',['authors'=>[$tid]],$auth);
}

// ── Yoast SEO meta ────────────────────────────────────────
if($meta_desc){
    wp_req("$wp_api/$ep/$pid",'POST',['meta'=>['_yoast_wpseo_metadesc'=>$meta_desc]],$auth);
}

// ── Key Quotes (_tls_quotes) ──────────────────────────────
// WP'nin kendi quote sistemi devreye girmesin
wp_req("$wp_api/$ep/$pid",'POST',['meta'=>['_tls_disable_quotes'=>'1']],$auth);

if(!empty($quotes)){
    $clean = [];
    foreach($quotes as $q){
        $t = trim($q['text']??''); $s = trim($q['source']??'');
        if($t) $clean[] = ['text'=>$t,'source'=>$s];
    }
    if($clean){
        wp_req("$wp_api/$ep/$pid",'POST',['meta'=>['_tls_quotes'=>$clean]],$auth);
    }
}

// ── Kitap kapağı ──────────────────────────────────────────
$cover_set=false;
if($cover_url){
    $allowed=['books.google.com','covers.openlibrary.org',
              'lh3.googleusercontent.com','lh4.googleusercontent.com','lh5.googleusercontent.com',
              'lh6.googleusercontent.com','encrypted-tbn0.gstatic.com'];
    $host=parse_url($cover_url,PHP_URL_HOST);
    if(in_array($host,$allowed)){
        $ctx=stream_context_create(['http'=>['timeout'=>15,'user_agent'=>'Mozilla/5.0']]);
        $img=@file_get_contents($cover_url,false,$ctx);
        if($img && strlen($img)>1000){
            $cm=curl_init("$wp_api/media");
            $fn=preg_replace('/[^a-z0-9]/','-',strtolower($book)).'.jpg';
            curl_setopt_array($cm,[
                CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>30,
                CURLOPT_HTTPHEADER=>['Authorization: '.$auth,
                    "Content-Disposition: attachment; filename=\"$fn\"",
                    'Content-Type: image/jpeg'],
                CURLOPT_POSTFIELDS=>$img,
            ]);
            $mr=curl_exec($cm);curl_close($cm);
            $mid=json_decode($mr,true)['id']??null;
            if($mid){
                wp_req("$wp_api/$ep/$pid",'POST',['featured_media'=>$mid],$auth);
                $cover_set=true;
            }
        }
    }
}

function sanitize_key($key){
    return preg_replace('/[^a-z0-9_-]/','',strtolower($key));
}

echo json_encode([
    'ok'        =>true,
    'post_id'   =>$pid,
    'post_url'  =>$post['link'],
    'edit_url'  =>rtrim(WP_URL,'/')."/wp-admin/post.php?post=$pid&action=edit",
    'cover_set' =>$cover_set,
    'categories'=>$cat_ids,
]);
