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

    // Eşleşme anahtarları: parantez/alt başlık öncesi ana ad + yazar soyadı.
    $main = trim(preg_replace('/\s*[\(\（].*$/u', '', $book));
    $main = trim(preg_replace('/\s*[:;].*$/u', '', $main));
    if ($main === '') $main = $book;
    $nmain = tls_ds_web_norm($main);
    $nmain_core = preg_replace('/^ (the|a|an) /', ' ', $nmain);
    if (strlen(trim($nmain_core)) < 4) return $empty;          // "Me", "It" gibi tek kelime: güvenilir eşleşme yok
    $parts = preg_split('/\s+/', trim($author));
    $surname = tls_ds_web_norm((string) end($parts));
    if (strlen(trim($surname)) < 3) $surname = '';

    $dir  = __DIR__ . '/cache/dsweb';
    $file = $dir . '/' . md5('dsw1|' . mb_strtolower($book . '|' . $author, 'UTF-8')) . '.json';
    if (is_file($file) && filemtime($file) > time() - 30 * 86400) {
        $hit = json_decode((string) file_get_contents($file), true);
        if (is_array($hit)) return $hit;
    }

    $q = '"' . mb_substr($main, 0, 90) . '"' . ($author !== '' ? ' ' . mb_substr($author, 0, 30) : '')
       . ' -site:amazon.com -site:ebay.com -site:abebooks.com';
    $ch = curl_init('https://api.deepseek.com/v1/tools/search');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS => json_encode(['query' => mb_substr($q, 0, 128), 'count' => 10, 'need_content' => true], JSON_UNESCAPED_UNICODE),
    ]);
    $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    if ($code !== 200 || !$r) return $empty;                    // hata/ödeme sorunu: sessizce kaynaksız devam
    $j = json_decode((string) $r, true);

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
        if (strpos($hay, $nmain_core) === false) continue;
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
