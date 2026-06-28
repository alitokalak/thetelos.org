<?php
/**
 * _wikidata-authors.php — Paylaşılan Wikidata yazar-listeleme yardımcısı.
 * list-authors.php ve queue-create.php bunu kullanır. Tek başına bir endpoint
 * DEĞİLDİR; yalnızca fonksiyon tanımlar.
 *
 * tls_wikidata_authors($category, $count, $offset) → ['ok'=>bool,'authors'=>[{author,era,note}],'error'=>str]
 */
if (!defined('ABSPATH') && !defined('TLS_PANEL')) { /* doğrudan erişimde sessiz kal */ }

if (!function_exists('tls_wd_http')) {
    /* UA zorunlu: Wikidata/WDQS UA'sız istekleri 403'lüyor. */
    function tls_wd_http($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: ThetelosBot/1.0 (https://thetelos.org; content builder)',
                'Accept: application/sparql-results+json, application/json',
            ],
        ]);
        $r = curl_exec($ch);
        $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($c >= 200 && $c < 300) ? (string)$r : '';
    }

    function tls_wd_year($iso) {
        if (preg_match('/^(-?)0*(\d{1,4})-/', (string)$iso, $m)) {
            return $m[1] === '-' ? ($m[2] . ' BC') : $m[2];
        }
        return '';
    }

    /* Kategori → Wikidata [property, QID]. P106 = occupation, P101 = field of work. */
    function tls_wd_map_category($cat) {
        $c = strtolower(trim(preg_replace('/[\s_]+/', ' ', $cat)));
        $map = [
            'philosophy'             => ['P106','Q4964182'],
            'history of philosophy'  => ['P106','Q4964182'],
            'ethics'                 => ['P101','Q9465'],
            'metaphysics'            => ['P101','Q9035'],
            'epistemology'           => ['P101','Q9471'],
            'logic'                  => ['P106','Q19350898'],
            'aesthetics'             => ['P101','Q35986'],
            'political philosophy'   => ['P101','Q1064441'],
            'philosophy of religion' => ['P101','Q1417149'],
            'theology'               => ['P106','Q1234713'],
            'religion'               => ['P106','Q1234713'],
            'literature'             => ['P106','Q36180'],
            'classic literature'     => ['P106','Q36180'],
            'world literature'       => ['P106','Q36180'],
            'fiction'                => ['P106','Q36180'],
            'novel'                  => ['P106','Q6625963'],
            'poetry'                 => ['P106','Q49757'],
            'drama'                  => ['P106','Q214917'],
            'psychology'             => ['P106','Q212980'],
            'sociology'              => ['P106','Q2306091'],
            'anthropology'           => ['P106','Q4773904'],
            'economics'              => ['P106','Q188094'],
            'political science'      => ['P106','Q3400985'],
            'politics'               => ['P106','Q82955'],
            'history'                => ['P106','Q201788'],
            'world history'          => ['P106','Q201788'],
            'ancient history'        => ['P106','Q201788'],
            'medieval history'       => ['P106','Q201788'],
            'modern history'         => ['P106','Q201788'],
            'science'                => ['P106','Q901'],
            'physics'                => ['P106','Q169470'],
            'mathematics'            => ['P106','Q170790'],
            'biology'                => ['P106','Q864503'],
            'chemistry'              => ['P106','Q593644'],
            'astronomy'              => ['P106','Q11063'],
            'medicine'               => ['P106','Q39631'],
        ];
        return $map[$c] ?? null;
    }

    function tls_wikidata_authors($category, $count, $offset) {
        $category = trim($category);
        $count    = max(5, min(50, (int)$count));
        $offset   = max(0, (int)$offset);
        if ($category === '') return ['ok'=>false, 'authors'=>[], 'error'=>'Kategori zorunlu.'];

        [$prop, $qid] = tls_wd_map_category($category) ?? [null, null];
        if (!$qid) {
            $sr   = json_decode(tls_wd_http(
                'https://www.wikidata.org/w/api.php?action=wbsearchentities&format=json&language=en&type=item&limit=1&search='
                . urlencode($category)
            ), true);
            $qid  = $sr['search'][0]['id'] ?? null;
            $prop = 'P101';
            if (!$qid) return ['ok'=>false, 'authors'=>[], 'error'=>"Kategori Wikidata'da eşlenemedi: $category"];
        }

        $sparql = "SELECT ?person ?personLabel ?personDescription ?sitelinks ?birth ?death WHERE {
  { SELECT ?person ?sitelinks WHERE {
      ?person wdt:P31 wd:Q5 .
      ?person wdt:$prop wd:$qid .
      ?person wikibase:sitelinks ?sitelinks .
      FILTER(?sitelinks >= 2)
    } ORDER BY DESC(?sitelinks) ?person LIMIT $count OFFSET $offset }
  OPTIONAL { ?person wdt:P569 ?birth. }
  OPTIONAL { ?person wdt:P570 ?death. }
  SERVICE wikibase:label { bd:serviceParam wikibase:language \"en,mul\". }
} ORDER BY DESC(?sitelinks) ?person";

        $resp = tls_wd_http('https://query.wikidata.org/sparql?format=json&query=' . urlencode($sparql));
        $data = json_decode($resp, true);
        $rows = $data['results']['bindings'] ?? null;
        if ($rows === null) return ['ok'=>false, 'authors'=>[], 'error'=>'Wikidata sorgusu başarısız (zaman aşımı/limit).'];

        $authors = []; $seen = [];
        foreach ($rows as $r) {
            $uri = $r['person']['value'] ?? '';
            if ($uri === '' || isset($seen[$uri])) continue;
            $seen[$uri] = true;
            $name = trim($r['personLabel']['value'] ?? '');
            if ($name === '' || preg_match('/^Q\d+$/', $name)) continue;
            $by  = isset($r['birth']['value']) ? tls_wd_year($r['birth']['value']) : '';
            $dy  = isset($r['death']['value']) ? tls_wd_year($r['death']['value']) : '';
            $era = $by !== '' ? ($by . '–' . $dy) : ($dy !== '' ? ('–' . $dy) : '');
            $authors[] = ['author'=>$name, 'era'=>$era, 'note'=>trim($r['personDescription']['value'] ?? '')];
        }
        return ['ok'=>true, 'authors'=>$authors, 'error'=>''];
    }
}
