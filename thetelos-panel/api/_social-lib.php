<?php
/**
 * _social-lib.php — Sosyal içerik paylaşılan yardımcıları (yazar portresi).
 * Tek başına endpoint DEĞİLDİR; yalnızca fonksiyon tanımlar.
 * Gereksinim: tls_wd_http (_wikidata-authors.php) + get_option (wp-load).
 */
if (!function_exists('sg_commons_thumb')) {
    /* Commons dosya adı → görsel URL. Special:FilePath doğru thumb'a yönlendirir. */
    function sg_commons_thumb($file, $w = 800) {
        $fn = str_replace(' ', '_', (string) $file);
        if (preg_match('/\.(pdf|tif|tiff)$/i', $fn)) return '';
        return 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($fn) . '?width=' . (int) $w;
    }

    /* Yazarın Wikidata portresi (P18). $debug=true → [url,qid,file] döndürür. */
    function sg_author_image($name, $debug = false) {
        $name = trim((string) $name);
        $blank = $debug ? ['url' => '', 'qid' => '', 'file' => ''] : '';
        if ($name === '' || !function_exists('tls_wd_http')) return $blank;

        $cache = function_exists('get_option') ? get_option('tls_author_img2', []) : [];
        if (!is_array($cache)) $cache = [];
        $key = mb_strtolower($name);
        if (!$debug && array_key_exists($key, $cache)) return $cache[$key];

        $qid = ''; $file = ''; $img = '';
        $s = json_decode(tls_wd_http('https://www.wikidata.org/w/api.php?action=wbsearchentities&format=json&language=en&type=item&limit=1&search=' . rawurlencode($name)), true);
        $qid = $s['search'][0]['id'] ?? '';
        if ($qid) {
            $c = json_decode(tls_wd_http('https://www.wikidata.org/w/api.php?action=wbgetclaims&format=json&property=P18&entity=' . $qid), true);
            $file = $c['claims']['P18'][0]['mainsnak']['datavalue']['value'] ?? '';
            if ($file) $img = sg_commons_thumb($file, 800);
        }
        if (function_exists('update_option')) { $cache[$key] = $img; update_option('tls_author_img2', $cache, false); }
        return $debug ? ['url' => $img, 'qid' => $qid, 'file' => $file] : $img;
    }
}
