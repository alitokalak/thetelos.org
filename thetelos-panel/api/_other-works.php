<?php
/**
 * _other-works.php — Özeti yazılamayan (ama GERÇEKTEN var olan) eserleri, ayrı
 * boş sayfa açmak yerine yazarın "Other Works" listesine ekler.
 *
 * Neden: binlerce tek-cümlelik yer-tutucu sayfa Google'a "düşük değerli içerik"
 * sinyali veriyordu. Okur yine yazarın tüm eserlerini görsün diye eser adı +
 * yılı yazar teriminin 'tls_other_works' meta'sında tutulur; tema yazar
 * sayfasında listeler (özeti sonradan yazılırsa liste kendiliğinden link olur).
 *
 * GÜVENLİK: Yalnız katalogda (Open Library) BU yazarla doğrulanan eser eklenir —
 * modelin uydurduğu ya da yanlış yazara atfedilmiş başlık listeye girmez.
 */
function tls_ow_req($method, $url, $body = null) {
    $ch = curl_init($url);
    $h  = ['Authorization: Basic ' . base64_encode(WP_USER . ':' . WP_APP_PASS)];
    $o  = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $method];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE); }
    $o[CURLOPT_HTTPHEADER] = $h;
    curl_setopt_array($ch, $o);
    $r = curl_exec($ch); $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [json_decode((string) $r, true), $c];
}

/* Döner: 'added' | 'exists' | 'unverified' | 'error' */
function tls_add_other_work($book, $author, $catalog = null) {
    $book = trim((string) $book); $author = trim((string) $author);
    if ($book === '' || $author === '') return 'error';
    if ($catalog === null && function_exists('bw_catalog_facts')) $catalog = bw_catalog_facts(preg_replace('/\s*\([^()]*\)\s*$/u', '', $book), $author);
    if (empty($catalog['ok']) || !empty($catalog['mismatch'])) return 'unverified';
    $year = (int) ($catalog['year'] ?? 0);

    $api = rtrim(WP_URL, '/') . '/wp-json/wp/v2';
    [$terms] = tls_ow_req('GET', "$api/authors?search=" . rawurlencode($author) . '&per_page=20&context=edit&_fields=id,name,meta');
    $term = null;
    foreach ((array) $terms as $t) if (mb_strtolower($t['name'] ?? '', 'UTF-8') === mb_strtolower($author, 'UTF-8')) { $term = $t; break; }
    if (!$term) {
        [$nt] = tls_ow_req('POST', "$api/authors", ['name' => $author]);
        if (empty($nt['id'])) return 'error';
        $term = $nt;
    }
    $list = (array) ($term['meta']['tls_other_works'] ?? []);
    $norm = fn($s) => trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(preg_replace('/\s*\([^()]*\)\s*$/u', '', (string) $s), 'UTF-8')));
    foreach ($list as $w) if ($norm($w['t'] ?? '') === $norm($book)) return 'exists';
    $list[] = ['t' => $book, 'y' => $year];
    [, $c] = tls_ow_req('POST', "$api/authors/" . (int) $term['id'], ['meta' => ['tls_other_works' => array_values($list)]]);
    return ($c >= 200 && $c < 300) ? 'added' : 'error';
}
