<?php
/**
 * _dsweb.php — DeepSeek Search API ile kitap hakkında web kaynağı topla.
 *
 * POST https://api.deepseek.com/v1/tools/search  (1.000 başarılı istek = $0.75)
 * Kitap başına TEK arama (30 gün önbellek). Sonuçlar SIKI süzülür: sayfada
 * kitabın adı BİREBİR (ifade olarak) ve yazarın soyadı geçmiyorsa kullanılmaz →
 * benzer başlıklı / başka yazara ait / atıf karışıklığı sayfalar elenir.
 * Satış, korsan PDF ve kitap-listesi siteleri de elenir.
 *
 * @return array ['text' => string (model için kaynak bloğu), 'sources' => string[], 'count' => int]
 */

if (!function_exists('tls_ds_web_sources')) {

function tls_ds_web_norm($s) {
    $s = mb_strtolower(trim((string) $s), 'UTF-8');
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false && $t !== '') $s = $t;
    $s = preg_replace('/[^a-z0-9]+/', ' ', strtolower($s));
    return ' ' . trim(preg_replace('/\s+/', ' ', $s)) . ' ';
}

function tls_ds_web_sources($book, $author) {
    $empty = ['text' => '', 'sources' => [], 'count' => 0];
    $book = trim((string) $book); $author = trim((string) $author);
    if ($book === '' || !defined('DEEPSEEK_KEY') || !DEEPSEEK_KEY) return $empty;

    // Eşleşme anahtarları: İngilizce ana ad VE (varsa) parantezdeki ORİJİNAL ad + yazar soyadı.
    // Az bilinen eserler hakkındaki sayfalar çoğu zaman yalnız orijinal adı kullanır
    // ("Traité de la suppuration") → yalnız İngilizceyle aramak onları kaçırıyordu.
    $main = trim(preg_replace('/\s*[\(\（].*$/u', '', $book));
    $main = trim(preg_replace('/\s*[:;].*$/u', '', $main));
    if ($main === '') $main = $book;
    $orig = ''; if (preg_match('/[\(\（]([^\)\）]+)[\)\）]\s*$/u', $book, $om)) $orig = trim(preg_replace('/\s*[:;].*$/u', '', $om[1]));
    $cores = [];
    foreach (array_filter([$main, $orig]) as $tt) {
        $c = preg_replace('/^ (the|a|an|le|la|les|l|der|die|das|el|los|las|il|lo) /', ' ', tls_ds_web_norm($tt));
        if (strlen(trim($c)) >= 4) $cores[] = $c;              // "Me", "It" gibi tek kelime: güvenilir eşleşme yok
    }
    if (!$cores) return $empty;
    $parts = preg_split('/\s+/', trim($author));
    $surname = tls_ds_web_norm((string) end($parts));
    if (strlen(trim($surname)) < 3) $surname = '';

    $dir  = __DIR__ . '/cache/dsweb';
    $file = $dir . '/' . md5('dsw2|' . mb_strtolower($book . '|' . $author, 'UTF-8')) . '.json';
    if (is_file($file) && filemtime($file) > time() - 30 * 86400) {
        $hit = json_decode((string) file_get_contents($file), true);
        if (is_array($hit)) return $hit;
    }

    $results = [];
    foreach (array_values(array_unique(array_filter([$main, $orig]))) as $qt) {
        $q = '"' . mb_substr($qt, 0, 90) . '"' . ($author !== '' ? ' ' . mb_substr($author, 0, 30) : '')
           . ' -site:amazon.com -site:ebay.com -site:abebooks.com';
        $ch = curl_init('https://api.deepseek.com/v1/tools/search');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
            CURLOPT_POSTFIELDS => json_encode(['query' => mb_substr($q, 0, 128), 'count' => 10, 'need_content' => true], JSON_UNESCAPED_UNICODE),
        ]);
        $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        if ($code !== 200 || !$r) continue;                     // hata/ödeme sorunu: sessizce kaynaksız devam
        $jj = json_decode((string) $r, true);
        foreach ((array) ($jj['results'] ?? []) as $res) $results[(string) ($res['url'] ?? '')] = $res;
    }
    if (!$results) return $empty;
    $j = ['results' => array_values($results)];

    $bad = '/(amazon\.|ebay\.|abebooks|alibris|biblio\.com|thriftbooks|walmart|etsy|bookdepository|barnesandnoble|z-lib|zlibrary|libgen|pdfdrive|oceanofpdf|annas-archive|dokumen\.pub|ebin\.pub|vdoc\.pub|scribd|pinterest|facebook|instagram|tiktok|youtube|reddit|quora)/i';
    $out = []; $srcs = [];
    foreach ((array) ($j['results'] ?? []) as $res) {
        $url = (string) ($res['url'] ?? '');
        if ($url === '' || preg_match($bad, $url)) continue;
        if ((float) ($res['rerank_score'] ?? 0) < 1.0) continue;
        $body = trim((string) ($res['content'] ?? '')) ?: trim((string) ($res['summary'] ?? ''));
        if (mb_strlen($body) < 300) continue;
        $hay = tls_ds_web_norm(($res['title'] ?? '') . ' ' . mb_substr($body, 0, 20000));
        // BİREBİR: ana ad ifade olarak geçmeli + yazar soyadı geçmeli.
        $title_hit = false; foreach ($cores as $c) { if (strpos($hay, $c) !== false) { $title_hit = true; break; } }
        if (!$title_hit) continue;
        if ($surname !== '' && strpos($hay, $surname) === false) continue;
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        $out[] = "[Web — " . ($res['site_name'] ?: $host) . " — " . mb_substr((string) ($res['title'] ?? ''), 0, 120) . "]\n" . mb_substr($body, 0, 3500);
        $srcs[] = $host;
        if (count($out) >= 4) break;
    }
    $res = ['text' => implode("\n\n", $out), 'sources' => array_values(array_unique($srcs)), 'count' => count($out)];
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($file, json_encode($res, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $res;
}

}
