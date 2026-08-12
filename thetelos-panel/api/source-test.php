<?php
/**
 * source-test.php — KAYNAK MOTORU TEŞHİSİ (canlı sunucuda).
 *
 * "Ünlü kitaplarda bile kaynak bulunamıyor → içerik kısa" sorununun kökü
 * dossier'ın boş dönmesi. Bu boşluk (a) sunucunun dış API'lere erişememesi
 * mi, yoksa (b) eşleşme/ayrıştırma hatası mı — ONU ölçer.
 *
 * KULLANIM (tarayıcıda, panele giriş yaptıktan sonra):
 *   /thetelos-panel/api/source-test.php?book=On the Origin of Species&author=Charles Darwin
 *
 * Çıktı: her dış servise HAM bağlantı (HTTP kodu + bayt) + tls_info_dossier
 * sonucunun (have/kaç karakter/kaynaklar) özeti. Hiçbir şey yayınlamaz.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); echo 'auth'; exit; }
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/_verify.php';
require_once __DIR__ . '/_info.php';

$book   = trim($_GET['book']   ?? 'On the Origin of Species');
$author = trim($_GET['author'] ?? 'Charles Darwin');

echo "KAYNAK TEŞHİSİ\n";
echo "Kitap : {$book}\n";
echo "Yazar : {$author}\n";
echo str_repeat('─', 60) . "\n\n";

/* 1) HAM BAĞLANTI: sunucu bu hostlara ulaşabiliyor mu? */
function st_probe($label, $url) {
    $t0 = microtime(true);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['User-Agent: thetelos.org/1.0 (diagnostic)'],
    ]);
    $r = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    $dt = round((microtime(true) - $t0) * 1000);
    printf("%-14s HTTP=%-3d bytes=%-7d %-5s %s\n",
        $label, $code, strlen((string) $r), $dt . 'ms',
        $err ? ('ERR: ' . $err) : '');
    return [$code, strlen((string) $r)];
}

echo "1) HAM BAĞLANTI (sunucu → dış API):\n";
st_probe('Wikipedia', 'https://en.wikipedia.org/w/api.php?format=json&action=query&list=search&srlimit=1&srsearch=' . rawurlencode($book));
st_probe('GoogleBooks', 'https://www.googleapis.com/books/v1/volumes?maxResults=1&q=' . rawurlencode($book));
st_probe('OpenLibrary', 'https://openlibrary.org/search.json?limit=1&q=' . rawurlencode($book));
st_probe('Wikidata', 'https://www.wikidata.org/w/api.php?format=json&action=wbsearchentities&language=en&limit=1&search=' . rawurlencode($book));
echo "\n";

/* 2) DOSSIER: gerçek motor ne topluyor? */
echo "2) DOSSIER (tls_info_dossier):\n";
$t0 = microtime(true);
$d  = tls_info_dossier($book, $author);
$dt = round((microtime(true) - $t0) * 1000);
printf("have=%s  chars=%d  süre=%dms\n", $d['have'] ? 'YES' : 'NO', mb_strlen($d['text']), $dt);
echo "sources: " . (implode(', ', $d['sources']) ?: '(hiç)') . "\n";
echo "yıl: " . ($d['year'] ?? '-') . "\n\n";

echo "3) DOSSIER İLK 800 KARAKTER:\n";
echo str_repeat('─', 60) . "\n";
echo mb_substr($d['text'], 0, 800) . "\n";
echo str_repeat('─', 60) . "\n";
