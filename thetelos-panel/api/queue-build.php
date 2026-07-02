<?php
/**
 * queue-build.php — Batch dosyasındaki yazarların eserlerini adım adım ekle
 * POST: batch_id, chunk (kaç yazarı işle, varsayılan 5)
 * Her çağrıda 5 yazarın eserlerini Firebase→OL→LLM ile çeker, batch'e ekler
 * Dönüş: { ok, done:bool, authors_built, authors_total, books_added }
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
// Yetki: panel session'ı, ya da cron'un kullandığı dahili token / CLI.
$qb_itok     = hash('sha256', WP_APP_PASS . '|tls-batch-worker');
$qb_internal = isset($_POST['_itok']) && hash_equals($qb_itok, (string)$_POST['_itok']);
$qb_is_cli   = (PHP_SAPI === 'cli');
if (empty($_SESSION['tls_auth']) && !$qb_internal && !$qb_is_cli) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
set_time_limit(150);
@ini_set('memory_limit', '512M');   // dev list_only dosyasını işlerken/taşırken OOM olmasın

$batch_id = preg_replace('/[^a-z0-9_.]/', '', trim($_POST['batch_id'] ?? ''));
$chunk    = max(1, min(10, (int)($_POST['chunk'] ?? 3)));
if (!$batch_id) { echo json_encode(['ok'=>false,'error'=>'batch_id gerekli']); exit; }

$jobs_dir   = dirname(__DIR__) . '/jobs';
$batch_file = "$jobs_dir/{$batch_id}.json";
if (!file_exists($batch_file)) { echo json_encode(['ok'=>false,'error'=>'Kuyruk bulunamadı']); exit; }

// Dosyayı kilitle
$fp = fopen($batch_file, 'r+');
if (!flock($fp, LOCK_EX)) { fclose($fp); echo json_encode(['ok'=>false,'error'=>'Kilit alınamadı']); exit; }
fseek($fp, 0); $raw=''; while(!feof($fp)) $raw .= fread($fp, 65536);
$batch = json_decode($raw, true);
flock($fp, LOCK_UN); fclose($fp);

if (!$batch) { echo json_encode(['ok'=>false,'error'=>'Batch okunamadı']); exit; }

$authors      = $batch['authors']      ?? [];
$authors_built = (int)($batch['authors_built'] ?? 0);
$authors_total = count($authors);

// list_only (dev liste) kuyruklarında eserleri iş dosyasına gömmek yerine ayrı
// bir EKLEME dosyasına (.jsonl) yazarız → her tik koca dosyayı baştan yazmaz,
// bellek/zaman sınırını aşmaz. İçerik kuyrukları (≤50 yazar) eski modelde kalır.
$is_list_only = !empty($batch['list_only']);
$books_file   = preg_replace('/\.json$/', '.books.jsonl', $batch_file);
// Nesil damgası: queue-reset her sıfırlamada bunu artırır. Bu tik yazmadan önce
// dosyadaki epoch hâlâ aynı mı bakar; değiştiyse (reset olduysa) yazmaz → sıfırlama ezilmez.
$my_epoch     = (int)($batch['epoch'] ?? 0);

if ($authors_built >= $authors_total) {
    $batch['status']    = !empty($batch['list_only']) ? 'list_ready' : 'running';
    $batch['build_msg'] = 'Kuyruk hazır.';
    file_put_contents($batch_file, json_encode($batch, JSON_UNESCAPED_UNICODE));
    echo json_encode(['ok'=>true,'done'=>true,'authors_built'=>$authors_built,'authors_total'=>$authors_total,'books_added'=>0]);
    exit;
}

define('FIREBASE_URL','https://thetelos-db-default-rtdb.europe-west1.firebasedatabase.app');

function qb_fb_norm($n){ return strtr(mb_strtolower(trim($n)),['.'=>'_','#'=>'_','$'=>'_','['=>'_',']'=>'_','/'=>'_']); }

function qb_firebase($author){
    $url = FIREBASE_URL.'/yazarlar/'.rawurlencode(qb_fb_norm($author)).'.json?limitToFirst=100';
    $ch  = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8]);
    $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($code!==200||!$r||$r==='null') return [];
    $d=json_decode($r,true); if(!is_array($d)) return [];
    $out=[];
    foreach($d as $t){ $t=trim($t); if($t) $out[]=['title'=>$t,'cover'=>'','year'=>'']; }
    return $out;
}

/**
 * DoH ile (Cloudflare 1.1.1.1, IP üzerinden — yerel DNS GEREKMEZ) bir host'un
 * IPv4 adresini çöz. Cron/CLI'ın bozuk resolv.conf'unu tamamen baypas eder.
 * Sonuç süreç boyunca önbelleğe alınır.
 */
function qb_resolve_ip($host){
    static $cache = [];
    if (isset($cache[$host])) return $cache[$host];
    $ip = '';
    foreach (['https://1.1.1.1/dns-query', 'https://8.8.8.8/resolve'] as $doh) {
        $ch = curl_init($doh . '?name=' . urlencode($host) . '&type=A');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8, CURLOPT_CONNECTTIMEOUT=>6,
            CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_HTTPHEADER=>['Accept: application/dns-json']]);
        $r = curl_exec($ch); curl_close($ch);
        $d = json_decode((string)$r, true);
        foreach ($d['Answer'] ?? [] as $a) {
            if ((int)($a['type'] ?? 0) === 1 && filter_var($a['data'] ?? '', FILTER_VALIDATE_IP)) { $ip = $a['data']; break; }
        }
        if ($ip) break;
    }
    return $cache[$host] = $ip;
}

function qb_http_get($url){
    // Cron (PHP CLI) bağlamında yerel DNS "Could not resolve host" verebiliyor
    // (web/litespeed'de sorun yok). Çözüm: önce normal dene; DNS patlarsa host'u
    // DoH ile kendimiz çözüp CURLOPT_RESOLVE ile curl'e pinleyerek tekrar dene.
    $host = parse_url($url, PHP_URL_HOST) ?: '';
    $last_c = 0; $last_err = ''; $pinned_ip = '';
    for ($attempt = 1; $attempt <= 4; $attempt++) {
        $ch=curl_init($url);
        $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>18,CURLOPT_CONNECTTIMEOUT=>10,
            CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,   // IPv6 resolver bozuksa "could not resolve" olur
            // OpenLibrary politikası: tanımlayıcı UA + iletişim e-postası ister; aksi halde 403/429.
            CURLOPT_HTTPHEADER=>['Accept: application/json',
                'User-Agent: ThetelosBot/1.0 (https://thetelos.org; mailto:alitokalak@gmail.com)']];
        // DNS patladıysa: DoH ile bulduğumuz IP'yi 80/443 portlarına pinle.
        if ($pinned_ip && $host) {
            $opts[CURLOPT_RESOLVE] = ["$host:443:$pinned_ip", "$host:80:$pinned_ip"];
        }
        curl_setopt_array($ch,$opts);
        $r=curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); $errno=curl_errno($ch);
        $last_c=$c; $last_err=($r===false)?curl_error($ch):'';
        curl_close($ch);
        if ($c===200 && $r) { $GLOBALS['qb_last_http']=$c; $GLOBALS['qb_last_err']=''; return json_decode($r,true); }
        if ($c!==0) break;   // gerçek HTTP yanıtı (403/404/429 vs.) → tekrar denemenin anlamı yok
        // CURLE_COULDNT_RESOLVE_HOST (6) → DoH ile çöz, sonraki turda pinle.
        if ($errno === 6 && !$pinned_ip && $host) { $pinned_ip = qb_resolve_ip($host); }
        usleep($attempt*300000);
    }
    $GLOBALS['qb_last_http']=$last_c;   // teşhis: son OL/GB HTTP kodu
    $GLOBALS['qb_last_err']=$last_err . ($pinned_ip ? " (DoH IP: $pinned_ip)" : ' (DoH çözemedi)');
    return null;
}

function qb_tok($s){
    $stop=array_flip(explode(' ','the a an of on to and by with in for from about into that this his her their complete works collected selected early late major minor new revised critical introduction guide study reader companion handbook anthology essays'));
    $s=mb_strtolower($s,'UTF-8');
    $t=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s); if($t!==false&&$t!=='') $s=$t;
    $s=preg_replace('/[^a-z0-9]+',' ',$s); $out=[];
    foreach(explode(' ',trim($s)) as $w){ if(strlen($w)>=4&&!isset($stop[$w])) $out[]=$w; }
    return $out;
}
function qb_tok_match($a,$b){ $n=min(strlen($a),strlen($b)); if($n<5) return $a===$b; $k=0; while($k<$n&&$a[$k]===$b[$k]) $k++; return $k>=5; }
function qb_titles_same($ta,$tb){ if(!$ta||!$tb) return false; $small=count($ta)<=count($tb)?$ta:$tb; $big=count($ta)<=count($tb)?$tb:$ta; $m=0; foreach($small as $x){ foreach($big as $y){ if(qb_tok_match($x,$y)){$m++;break;} } } $min=count($small); return $m>=2||($m>=1&&$m===$min); }

/**
 * Yazar için 1 Google Books isteği → title(lower) → ['cover'=>url,'year'=>yyyy] map.
 * OpenLibrary yazar-eserler ucu kapak/yıl konusunda cimri; GB bunları doldurur.
 */
function qb_gb_meta_for_author($author) {
    $url = 'https://www.googleapis.com/books/v1/volumes?' . http_build_query([
        'q'          => 'inauthor:"' . $author . '"',
        'maxResults' => 40,
        'printType'  => 'books',
        'fields'     => 'items(volumeInfo(title,imageLinks,publishedDate))',
        'key'        => GOOGLE_BOOKS_KEY,
    ]);
    $data = qb_http_get($url);
    $map  = [];
    foreach ($data['items'] ?? [] as $item) {
        $vi = $item['volumeInfo'] ?? [];
        $t  = mb_strtolower(trim($vi['title'] ?? ''));
        if (!$t) continue;
        $lnk = $vi['imageLinks'] ?? [];
        $c   = $lnk['thumbnail'] ?? ($lnk['smallThumbnail'] ?? '');
        if ($c) { $c = str_replace(['http://','&edge=curl'], ['https://',''], $c); $c = preg_replace('/zoom=\d/', 'zoom=3', $c); }
        $y = '';
        if (!empty($vi['publishedDate']) && preg_match('/\d{4}/', $vi['publishedDate'], $m)) $y = $m[0];
        if (!isset($map[$t])) $map[$t] = ['cover'=>$c, 'year'=>$y];
        else { if ($c && !$map[$t]['cover']) $map[$t]['cover']=$c; if ($y && !$map[$t]['year']) $map[$t]['year']=$y; }
    }
    return $map;
}

/* Başlığı GB map'inde eşleştir → ['cover','year'] (yoksa boş). */
function qb_meta_from_map($title, $map) {
    $empty = ['cover'=>'', 'year'=>''];
    if (empty($map)) return $empty;
    $t = mb_strtolower(trim($title));
    if (isset($map[$t])) return $map[$t];
    if (strlen($t) < 5) return $empty;
    foreach ($map as $gt => $meta) {
        if (str_contains($gt, $t) || str_contains($t, $gt)) return $meta;
    }
    return $empty;
}

function qb_openlibrary($author){
    // OpenLibrary iki adım: yazar key → eserler
    $ad = qb_http_get('https://openlibrary.org/search/authors.json?q='.urlencode($author).'&limit=5');
    $author_key = null;
    $q = mb_strtolower($author);
    foreach($ad['docs']??[] as $a){
        $name = mb_strtolower(trim($a['name']??''));
        if($name===$q||str_contains($name,$q)||str_contains($q,$name)){$author_key=$a['key']??null;break;}
    }
    if(!$author_key&&!empty($ad['docs'][0]['key'])) $author_key=$ad['docs'][0]['key'];
    if(!$author_key) return [];

    // search/authors.json 'key'i çıplak OLID döner ("OL3175986A"); diğer uçlar
    // "/authors/OL3175986A" bekler. İkisini de çıplak OLID'e indir, URL'yi doğru kur.
    $olid = preg_replace('~^/?(authors/)?~', '', trim($author_key));  // → "OL3175986A"
    if($olid==='') return [];

    $data = qb_http_get('https://openlibrary.org/authors/'.$olid.'/works.json?limit=50');
    if(!$data) return [];

    $author_parts = array_filter(explode(' ', mb_strtolower($author)));
    $author_last  = end($author_parts);
    $out = []; $seen = [];
    foreach($data['entries']??[] as $entry){
        $t = trim($entry['title']??''); if(!$t) continue;
        $k = mb_strtolower($t); if(isset($seen[$k])) continue; $seen[$k]=true;

        // Sadece bu yazara ait eserler
        $was = $entry['authors']??[];
        if(!empty($was)){
            $ok = false;
            foreach($was as $wa){
                $wk = preg_replace('~^/?(authors/)?~', '', (string)($wa['author']['key']??($wa['key']??'')));
                if($wk===$olid){$ok=true;break;}
            }
            if(!$ok) continue;
        }

        // "X of/on Kant" gibi ikincil literatürü at
        if(strlen($author_last)>=4){
            if(preg_match('/\b(of|on|to|about|and|by)\s+'.preg_quote($author_last,'/').'\\b/i',$t)) continue;
            if(trim(mb_strtolower($t))===$author_last) continue;
        }

        $cover = '';
        if(!empty($entry['covers'][0])&&$entry['covers'][0]>0)
            $cover = 'https://covers.openlibrary.org/b/id/'.$entry['covers'][0].'-M.jpg';
        $year = '';
        if(!empty($entry['first_publish_date'])){preg_match('/\d{4}/',$entry['first_publish_date'],$m);$year=$m[0]??'';}

        $out[] = ['title'=>$t,'original'=>'','cover'=>$cover,'year'=>$year];
    }
    return $out;
}

function qb_llm($author){
    $ch=curl_init(DEEPSEEK_API_URL);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>30,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>DEEPSEEK_MODEL,'max_tokens'=>1500,
            'messages'=>[['role'=>'user','content'=>"List the most important works by {$author}. Return ONLY JSON: [{\"title\":\"...\",\"year\":\"...\"}]"]]])]);
    $r=curl_exec($ch); curl_close($ch);
    $d=json_decode($r,true); $txt=$d['choices'][0]['message']['content']??'';
    $txt=preg_replace('/```json|```/i','',$txt);
    $s=strpos($txt,'['); $e=strrpos($txt,']');
    $arr=($s!==false&&$e>$s)?json_decode(substr($txt,$s,$e-$s+1),true):[];
    if(!is_array($arr)) return [];
    $out=[];
    foreach($arr as $it){ $t=trim($it['title']??''); if($t) $out[]=['title'=>$t,'cover'=>'','year'=>(string)($it['year']??'')]; }
    return $out;
}


// Sonraki chunk kadar yazarı işle
$new_books   = [];
$chunk_end   = min($authors_built + $chunk, $authors_total);
$end         = $authors_built;   // SADECE gerçekten tamamlanan yazara kadar ilerlet
$dbg         = ['ol'=>0,'fb'=>0,'llm'=>0,'none'=>0];  // son chunk kaynak teşhisi
$infra_fail  = false;            // DNS/bağlantı hatası (HTTP 0) → ilerleme

for ($i = $authors_built; $i < $chunk_end; $i++) {
    $author_name = trim($authors[$i]['author'] ?? '');
    if (!$author_name) { $end = $i + 1; continue; }

    // Eser kaynağı: SADECE OpenLibrary (+ kendi Firebase DB'miz). AI YOK.
    $works   = qb_openlibrary($author_name);            $src = $works ? 'ol' : '';
    $ol_http = $GLOBALS['qb_last_http'] ?? 0;
    if ($i === $authors_built) {  // ilk yazarın OL kodu + curl hatası
        $dbg['ol_http'] = $ol_http;
        $dbg['ol_err']  = $GLOBALS['qb_last_err'] ?? '';
    }
    // HTTP 0 = bağlantı/DNS başarısız (gerçek "eseri yok" DEĞİL). Bu bağlamda
    // (ör. DNS'i bozuk cron) yazarı atlama — chunk'ı durdur, ilerletme. Böylece
    // DNS'in çalıştığı bağlam (tarayıcı) bu yazarları sonra gerçekten çeker.
    if (empty($works) && $ol_http === 0) { $infra_fail = true; break; }

    if (empty($works)) { $works = qb_firebase($author_name); if ($works) $src = 'fb'; }
    $dbg[$src ?: 'none']++;
    $end = $i + 1;   // bu yazar gerçekten işlendi (200 yanıt alındı)

    // Kapak/yıl zenginleştirmesi: yazar başına 1 Google Books isteği.
    // OL'de eksik olan kapak ve yılları buradan doldur.
    $gbmeta = !empty($works) ? qb_gb_meta_for_author($author_name) : [];

    $author_last_main = preg_replace('/^.+\s/', '', $author_name);
    $seen_norm = [];   // bu yazar için normalize edilmiş başlık tekrar kontrolü
    $al = preg_quote($author_last_main, '/');
    foreach ($works as $w) {
        $t    = trim($w['title']   ?? ''); if (!$t) continue;
        $orig = trim($w['original'] ?? '');

        // Sahte başlıkları filtrele (Socrates vs.)
        if (preg_match('/^(none|no\s+(\w+\s+)?works|no\s+known|no\s+extant|not\s+applicable)/i', $t)) continue;

        // BLANKET ikincil literatür: neredeyse hiçbir zaman birincil eser adı değildir.
        if (preg_match('/\b(portable|reader|anthology|omnibus|companion|festschrift|selected works|selected writings|selected essays|collected works|collected writings|complete works|compendium|handbook|encyclop|dictionary|readings in|essential texts|primary texts|key texts|complete texts|a biography|biography of|the life of|study guide|critical study|casebook|reconsidered|revisited|in context)\b/i', $t)) continue;

        // YAZAR HAKKINDA yazılmış kitaplar (yazar adıyla BİRLİKTE) — kendi eseri sanılmasın.
        // "The Philosophy of Right" gibi gerçek eserler adı içermediği için elenmez.
        if (strlen($author_last_main) >= 4) {
            // "... {of|on|about|...} Einstein", "philosophy/life/thought/world of Einstein"
            if (preg_match('/\b(of|on|to|about|after|against|beyond|from|with|reading|understanding|interpreting)\s+(the\s+\w+\s+of\s+)?'.$al.'\b/i', $t)) continue;
            if (preg_match('/\b(philosophy|life|thought|world|legacy|letters|correspondence|biography)\s+of\s+'.$al.'\b/i', $t)) continue;
            // "Einstein: a biography / his life / and his ... / reader / companion"
            if (preg_match('/\b'.$al.'\b\s*[:,\-–].*\b(biography|life|study|reader|companion|thought|philosophy|legacy|letters|reconsidered|revisited|in context|and his|and her|the man)\b/i', $t)) continue;
            if (trim(mb_strtolower($t)) === mb_strtolower($author_last_main)) continue;
        }

        // Normalize et → aynı eserin ufak varyantlarını (The/A öneki, noktalama) tekrar ekleme.
        $norm = mb_strtolower(trim($t));
        $norm = preg_replace('/^(the|a|an)\s+/', '', $norm);
        $norm = preg_replace('/[\s\.,:;!\?"\']+/', ' ', $norm);
        $norm = trim($norm);
        if ($norm === '' || isset($seen_norm[$norm])) continue;
        $seen_norm[$norm] = true;

        // "English Title (Original Title)" formatı — Wikidata origLabel varsa direkt kullan
        if ($orig && mb_strtolower($orig) !== mb_strtolower($t) && !preg_match('/\([^)]+\)/', $t)) {
            $t = "$t ($orig)";
        }

        // Kapak/yıl: OL'den geldiyse onu kullan, boşsa Google Books'tan doldur.
        $cover = $w['cover'] ?? '';
        $year  = $w['year']  ?? '';
        if (!$cover || !$year) {
            $gm = qb_meta_from_map($t, $gbmeta);
            if (!$cover) $cover = $gm['cover'];
            if (!$year)  $year  = $gm['year'];
        }

        $new_books[] = [
            'book_title'  => $t,
            'author_name' => $author_name,
            'cover_url'   => $cover,
            'year'        => $year,
            'status'      => 'pending',
            'post_id'=>null,'post_url'=>null,'edit_url'=>null,'cover_set'=>false,'error'=>'',
        ];
    }
}

// .lock ile karşılıklı dışlama; iş dosyası her zaman atomik (temp+rename) yazılır.
$lock = fopen($batch_file . '.lock', 'c');
if ($lock) flock($lock, LOCK_EX);

if ($is_list_only) {
    /* ── ÖLÇEKLENİR MODEL (list_only) ──
       Eserler .jsonl'a EKLENİR; iş JSON'u küçük kalır. Yazmadan ÖNCE dosyayı
       kilit altında yeniden oku ve doğrula: epoch (reset) değiştiyse ya da
       authors_built beklediğimden farklıysa (başka tik ilerletti) bu tik'in
       sonucunu ATLA → reset ezilmez, çift ekleme olmaz. */
    $b2 = json_decode((string)@file_get_contents($batch_file), true);
    if (!is_array($b2)) $b2 = $batch;
    $cur_epoch = (int)($b2['epoch'] ?? 0);
    $cur_built = (int)($b2['authors_built'] ?? 0);
    if ($cur_epoch !== $my_epoch || $cur_built !== $authors_built) {
        // Sıfırlama olmuş ya da başka bir tik araya girmiş → yazma, sonucu at.
        if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
        echo json_encode([
            'ok'=>true, 'done'=>false, 'skipped'=>true,
            'authors_built'=>$cur_built, 'authors_total'=>$authors_total,
            'books_added'=>0, 'total_books'=>(int)($b2['total'] ?? 0),
            'build_msg'=>($b2['build_msg'] ?? ''),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Güvenli: yeni eserleri (+ varsa eski gömülü books[]) .jsonl'a EKLE.
    $lines = '';
    if (!empty($b2['books'])) {
        foreach ($b2['books'] as $bk) { $lines .= json_encode($bk, JSON_UNESCAPED_UNICODE) . "\n"; }
    }
    foreach ($new_books as $bk) { $lines .= json_encode($bk, JSON_UNESCAPED_UNICODE) . "\n"; }
    if ($lines !== '') @file_put_contents($books_file, $lines, FILE_APPEND | LOCK_EX);

    $prev_total  = (int)($b2['total'] ?? count($b2['books'] ?? []));
    $total_books = $prev_total + count($new_books);

    $b2['books']          = [];
    $b2['books_migrated'] = true;
    $b2['total']          = $total_books;
    $b2['authors_built']  = $end;
} else {
    /* ── Eski gömülü model (küçük içerik kuyrukları) ── */
    $raw2 = @file_get_contents($batch_file);
    $b2   = json_decode((string)$raw2, true);
    if (!is_array($b2)) $b2 = $batch;   // canlı dosya bozuk/boşsa bellekteki kopyadan kurtar
    $b2['books']         = array_merge($b2['books'] ?? [], $new_books);
    $b2['total']         = count($b2['books']);
    $b2['authors_built'] = $end;
    $total_books         = count($b2['books']);
}

$b2['build_msg'] = $infra_fail
    ? ("⚠ Bu bağlamda OpenLibrary'ye erişilemiyor (OL HTTP " . ($dbg['ol_http'] ?? '0')
       . (!empty($dbg['ol_err']) ? ' — ' . $dbg['ol_err'] : '') . "). İlerleme durduruldu — "
       . "tarayıcıdan \"Oluşturmayı Devam Ettir\" ile çek. {$end}/{$authors_total} yazar, {$total_books} eser.")
    : ("{$end}/{$authors_total} yazar işlendi, {$total_books} eser hazır"
       . " · son {$chunk}: OL {$dbg['ol']}·FB {$dbg['fb']}·boş {$dbg['none']} (OL HTTP " . ($dbg['ol_http'] ?? '?')
       . (!empty($dbg['ol_err']) ? ' — ' . $dbg['ol_err'] : '') . ")");
if ($end >= $authors_total) {
    $b2['status']    = $is_list_only ? 'list_ready' : 'running';
    $b2['build_msg'] = 'Kuyruk hazır — ' . $total_books . ' eser.';
}

$json = json_encode($b2, JSON_UNESCAPED_UNICODE);
if ($json !== false && $json !== '') {          // boş/başarısız encode'u ASLA yazma
    $tmp = $batch_file . '.tmp.' . getmypid() . '.' . mt_rand();
    if (@file_put_contents($tmp, $json) === strlen($json)) { @rename($tmp, $batch_file); }
    else { @unlink($tmp); }
}
if ($lock) { flock($lock, LOCK_UN); fclose($lock); }

echo json_encode([
    'ok'            => true,
    'done'          => ($end >= $authors_total),
    'infra_fail'    => $infra_fail,   // bu bağlamda OpenLibrary'ye erişilemedi
    'authors_built' => $end,
    'authors_total' => $authors_total,
    'books_added'   => count($new_books),
    'total_books'   => $total_books,
    'build_msg'     => $b2['build_msg'],
], JSON_UNESCAPED_UNICODE);
