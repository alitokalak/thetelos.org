<?php
/**
 * category-organize.php — 220+ düz kategoriyi 14 KALICI ANA KATEGORİ altında grupla.
 *
 * AMAÇ: Kategori URL'lerine DOKUNMADAN (SEO güvenli) her kategoriyi 14 ana
 * başlıktan birine bağlamak. Bağlama "sunum katmanında" bir seçenek olarak
 * saklanır (tls_cat_groups); tema bunu okuyup categories sayfası / menü /
 * arama kenar çubuğunu gruplu gösterir. WP parent'ı DEĞİŞTİRİLMEZ → hiçbir
 * kategori URL'si değişmez, 404/301 riski yok.
 *
 * 14 ana kategori kalıcıdır (kitapçı/BISAC/kütüphane mantığı) — dünyadaki tüm
 * kitapları kapsayacak kadar geniş. Yeni konular geldikçe yalnız ALT kategori
 * eklenir; ana başlıklar sabit kalır.
 *
 * POST action=list          → tüm kategoriler + sayı + önerilen ana + kayıtlı ana
 * POST action=apply {map}   → {term_id: main_slug} eşlemesini kaydet
 * POST action=mains         → 14 ana kategori listesi
 *
 * NOT: Bu araç yalnız GRUPLAR (yıkıcı değil). İnce/çöp kategorileri gerçekten
 * BİRLEŞTİRMEK (ör. "Peru Literature" → Literature, +301) için mevcut
 * "Kategori Temizle" (category-cleanup.php) aracı kullanılır.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-LiteSpeed-Cache-Control: no-cache');
@set_time_limit(300);

ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();

/** 14 KALICI ANA KATEGORİ (slug => görünen ad). Sıra = menüde görünecek sıra. */
function co_mains() {
    return [
        'literature-fiction'     => 'Literature & Fiction',
        'philosophy'             => 'Philosophy',
        'religion-spirituality'  => 'Religion & Spirituality',
        'history'                => 'History',
        'biography-memoir'       => 'Biography & Memoir',
        'psychology'             => 'Psychology',
        'social-sciences'        => 'Social Sciences & Politics',
        'science-nature'         => 'Science & Nature',
        'technology-engineering' => 'Technology & Engineering',
        'arts-culture'           => 'Arts & Culture',
        'business-economics'     => 'Business & Economics',
        'health-lifestyle'       => 'Health & Lifestyle',
        'self-help'              => 'Self-Help & Personal Growth',
        'children-ya'            => 'Children & Young Adult',
    ];
}

/** WP slug'ları tire; normalize (küçük harf + tire). */
function co_norm($s) { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string)$s)), '-'); }

/** Çekirdek slug → ana kategori (birebir eşleme). */
function co_explicit_map() {
    return [
        // Philosophy
        'philosophy'=>'philosophy','history-of-philosophy'=>'philosophy','ethics'=>'philosophy',
        'metaphysics'=>'philosophy','epistemology'=>'philosophy','logic'=>'philosophy',
        'aesthetics'=>'philosophy','political-philosophy'=>'philosophy','philosophy-of-religion'=>'philosophy',
        'philosophy-of-science'=>'philosophy','philosophy-of-mind'=>'philosophy','philosophy-of-language'=>'philosophy',
        'critical-theory'=>'philosophy',
        // Religion & Spirituality
        'religion'=>'religion-spirituality','theology'=>'religion-spirituality','systematic-theology'=>'religion-spirituality',
        'christian-theology'=>'religion-spirituality','islamic-theology'=>'religion-spirituality','christianity'=>'religion-spirituality',
        'islam'=>'religion-spirituality','judaism'=>'religion-spirituality','buddhism'=>'religion-spirituality',
        'hinduism'=>'religion-spirituality','atheism'=>'religion-spirituality','agnosticism'=>'religion-spirituality',
        'mythology'=>'religion-spirituality','folklore'=>'religion-spirituality',
        // History
        'history'=>'history','world-history'=>'history','ancient-history'=>'history','medieval-history'=>'history',
        'modern-history'=>'history','military-history'=>'history','cultural-history'=>'history','history-of-science'=>'history',
        // Biography & Memoir
        'biography'=>'biography-memoir','autobiography'=>'biography-memoir','memoir'=>'biography-memoir',
        // Literature & Fiction
        'literature'=>'literature-fiction','classic-literature'=>'literature-fiction','world-literature'=>'literature-fiction',
        'poetry'=>'literature-fiction','drama'=>'literature-fiction','novel'=>'literature-fiction','fiction'=>'literature-fiction',
        'historical-fiction'=>'literature-fiction','science-fiction'=>'literature-fiction','dystopian-fiction'=>'literature-fiction',
        'fantasy'=>'literature-fiction','horror'=>'literature-fiction','mystery'=>'literature-fiction',
        'detective-fiction'=>'literature-fiction','romance'=>'literature-fiction','adventure'=>'literature-fiction',
        // Psychology
        'psychology'=>'psychology','cognitive-psychology'=>'psychology','social-psychology'=>'psychology',
        'psychoanalysis'=>'psychology','neuroscience'=>'psychology',
        // Social Sciences & Politics
        'sociology'=>'social-sciences','anthropology'=>'social-sciences','politics'=>'social-sciences',
        'political-science'=>'social-sciences','law'=>'social-sciences','international-law'=>'social-sciences',
        'education'=>'social-sciences','geography'=>'social-sciences','cultural-studies'=>'social-sciences','culture'=>'social-sciences',
        // Science & Nature
        'science'=>'science-nature','physics'=>'science-nature','astronomy'=>'science-nature','chemistry'=>'science-nature',
        'mathematics'=>'science-nature','statistics'=>'science-nature','biology'=>'science-nature','evolution'=>'science-nature',
        'genetics'=>'science-nature',
        // Technology & Engineering
        'technology'=>'technology-engineering','computers'=>'technology-engineering','artificial-intelligence'=>'technology-engineering',
        'programming'=>'technology-engineering','data-science'=>'technology-engineering',
        // Arts & Culture
        'art'=>'arts-culture','art-history'=>'arts-culture','music'=>'arts-culture','music-history'=>'arts-culture',
        'architecture'=>'arts-culture','design'=>'arts-culture','photography'=>'arts-culture','film'=>'arts-culture','theatre'=>'arts-culture',
        // Business & Economics
        'economics'=>'business-economics','microeconomics'=>'business-economics','macroeconomics'=>'business-economics',
        'business'=>'business-economics','management'=>'business-economics','marketing'=>'business-economics','entrepreneurship'=>'business-economics',
        // Health & Lifestyle
        'medicine'=>'health-lifestyle','public-health'=>'health-lifestyle','travel'=>'health-lifestyle',
        // Self-Help
        'self-help'=>'self-help','personal-development'=>'self-help',
        // Children & YA
        'children'=>'children-ya','young-adult'=>'children-ya',
    ];
}

/**
 * Rastgele bir kategori adı/slug'ı için ana kategori tahmin et.
 * Önce birebir çekirdek eşlemesi; sonra baş-kelime + aile anahtar kelimeleri.
 * Emin değilse '' döner (kullanıcı ekrandan seçer).
 */
function co_guess_main($name, $slug) {
    $explicit = co_explicit_map();
    $ns = co_norm($slug);
    if (isset($explicit[$ns])) return $explicit[$ns];

    $words = array_values(array_filter(preg_split('/-+/', co_norm($name . '-' . $slug))));
    if (!$words) return '';

    // Baş kelime: "X of Y" → of'tan önceki; değilse son kelime (ör. "german literature" → literature)
    $of = array_search('of', $words, true);
    $head = ($of !== false && $of > 0) ? $words[$of - 1] : end($words);

    // Aile anahtar kelimeleri → ana kategori. Tekil çekirdek eşlemesi de baş kelimeyle denenir.
    $explicit_singular = $explicit[$head] ?? '';
    if ($explicit_singular) return $explicit_singular;

    $fam = [
        'literature-fiction'     => ['literature','literatures','edebiyat','literary','fiction','novel','novels','poetry','poem','poems','poet','drama','play','plays','story','stories','tale','tales','prose','satire','essay','essays','verse','saga','epic'],
        'philosophy'             => ['philosophy','philosophical','philosopher','ethics','ethical','metaphysics','epistemology','logic','aesthetics','existential','phenomenology'],
        'religion-spirituality'  => ['religion','religious','theology','theological','church','bible','biblical','quran','koran','islam','islamic','christian','christianity','catholic','protestant','judaism','jewish','buddhism','buddhist','hindu','hinduism','spiritual','spirituality','mysticism','mystical','saint','saints','prayer','scripture','gnostic','sufi','taoism','confucian'],
        'history'                => ['history','historical','historiography','century','empire','war','wars','revolution','civilization','civilisation','antiquity','ancient','medieval','renaissance','reformation','dynasty','colonial','archaeology'],
        'biography-memoir'       => ['biography','biographies','biographical','autobiography','memoir','memoirs','diary','diaries','letters','correspondence'],
        'psychology'             => ['psychology','psychological','psycho','cognitive','psychoanalysis','psychiatry','mind','mental','behaviour','behavior','emotion','emotions','consciousness','neuroscience'],
        'social-sciences'        => ['sociology','sociological','social','society','anthropology','anthropological','politics','political','government','governance','democracy','law','legal','jurisprudence','education','pedagogy','teaching','geography','gender','feminism','feminist','race','ethnic','migration','media','communication','journalism','criminology'],
        'science-nature'         => ['science','scientific','physics','chemistry','biology','biological','mathematics','math','maths','statistics','astronomy','cosmology','geology','ecology','ecological','environment','environmental','nature','zoology','botany','evolution','genetics','climate','earth'],
        'technology-engineering' => ['technology','technological','computer','computers','computing','software','programming','coding','artificial','ai','machine','data','robotics','internet','cyber','digital','engineering','electronics','network'],
        'arts-culture'           => ['art','arts','artistic','painting','sculpture','music','musical','film','films','cinema','movie','architecture','architectural','design','photography','photographic','theatre','theater','dance','opera','craft','crafts','fashion'],
        'business-economics'     => ['business','economics','economic','economy','finance','financial','accounting','trade','industry','industrial','commerce','management','marketing','entrepreneur','entrepreneurship','startup','leadership','money','investment','banking'],
        'health-lifestyle'       => ['health','healthy','medicine','medical','disease','anatomy','surgery','clinical','nutrition','diet','fitness','wellness','cooking','cookbook','food','recipe','recipes','home','garden','gardening','travel','sport','sports','hobby','hobbies','lifestyle'],
        'self-help'              => ['self-help','self','improvement','development','productivity','motivation','motivational','habit','habits','mindfulness','relationship','relationships','success'],
        'children-ya'            => ['children','childrens','kids','juvenile','young-adult','ya','picture-book','nursery','fairy'],
    ];
    foreach ($words as $w) {
        foreach ($fam as $main => $keys) {
            if (in_array($w, $keys, true)) return $main;
        }
    }
    return '';
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

if ($action === 'mains') {
    echo json_encode(['ok'=>true, 'mains'=>co_mains()], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Listele: tüm kategoriler + sayı + önerilen ana + kayıtlı ana ── */
if ($action === 'list') {
    $mains  = co_mains();
    $saved  = get_option('tls_cat_group_of', []);            // [term_id => main_slug]
    if (!is_array($saved)) $saved = [];
    $all    = get_categories(['hide_empty' => false]);
    $rows   = [];
    $sys    = ['general','uncategorized'];
    foreach ($all as $c) {
        if (in_array(co_norm($c->slug), $sys, true)) continue;
        $cur = isset($saved[$c->term_id]) ? (string)$saved[$c->term_id] : '';
        $sug = co_guess_main($c->name, $c->slug);
        $rows[] = [
            'id'         => (int)$c->term_id,
            'name'       => $c->name,
            'slug'       => $c->slug,
            'count'      => (int)$c->count,
            'current'    => ($cur !== '' && isset($mains[$cur])) ? $cur : '',
            'suggested'  => $sug,
            'thin'       => ((int)$c->count < 10),   // ince → belki "Kategori Temizle" ile birleştir
        ];
    }
    // Atanmamış + kalabalık önce (dikkat gereken): önce ataması olmayanlar, sonra çok yazılı
    usort($rows, function ($a, $b) {
        $au = ($a['current'] === '') ? 0 : 1;
        $bu = ($b['current'] === '') ? 0 : 1;
        if ($au !== $bu) return $au - $bu;              // atanmamışlar üstte
        return $b['count'] <=> $a['count'];             // çok yazılı üstte
    });
    $assigned = count(array_filter($rows, fn($r) => $r['current'] !== ''));
    echo json_encode([
        'ok'=>true, 'mains'=>$mains, 'rows'=>$rows,
        'total'=>count($rows), 'assigned'=>$assigned, 'unassigned'=>count($rows)-$assigned,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Uygula: {term_id: main_slug} eşlemesini kaydet (URL değişmez) ── */
if ($action === 'apply') {
    $mains = co_mains();
    $in = json_decode((string)($_POST['map'] ?? '[]'), true);
    if (!is_array($in)) $in = [];

    // Mevcut kaydı al, gelen satırlarla güncelle (boş main = atamayı KALDIR).
    $group_of = get_option('tls_cat_group_of', []);
    if (!is_array($group_of)) $group_of = [];

    $set = 0; $cleared = 0;
    foreach ($in as $row) {
        $tid  = (int)($row['id'] ?? 0);
        $main = (string)($row['main'] ?? '');
        if ($tid <= 0) continue;
        if ($main === '') { if (isset($group_of[$tid])) { unset($group_of[$tid]); $cleared++; } continue; }
        if (!isset($mains[$main])) continue;                 // geçersiz ana → atla
        $group_of[$tid] = $main;
        $set++;
    }
    update_option('tls_cat_group_of', $group_of, false);

    // Tema kolaylığı için tersini de kaydet: [main_slug => [term_id,...]] (menü sırasıyla)
    $groups = [];
    foreach (array_keys($mains) as $ms) $groups[$ms] = [];
    foreach ($group_of as $tid => $ms) { if (isset($groups[$ms])) $groups[$ms][] = (int)$tid; }
    update_option('tls_cat_groups', $groups, false);
    update_option('tls_cat_main_labels', $mains, false);

    // KANIT: seçeneği DB'den TAZE oku — gerçekten yazıldı mı?
    wp_cache_delete('tls_cat_group_of', 'options');
    $verify = get_option('tls_cat_group_of', []);
    echo json_encode([
        'ok'=>true, 'set'=>$set, 'cleared'=>$cleared,
        'total_assigned'=>count($group_of),
        'stored'=> is_array($verify) ? count($verify) : 0,
    ]);
    exit;
}

/* ── AI ile boşları öner: motorun tahmin edemediği kategorileri DeepSeek 14
      ana başlıktan birine maplar. Girdi: [{id,name,slug}] → çıktı: [{id,main}] ── */
if ($action === 'ai_suggest') {
    $items = json_decode((string)($_POST['items'] ?? '[]'), true);
    if (!is_array($items) || !$items) { echo json_encode(['ok'=>true,'map'=>[]]); exit; }
    $items = array_slice(array_values($items), 0, 120);   // tek çağrıda en çok 120
    $mains = co_mains();
    $mainlist = '';
    foreach ($mains as $slug => $label) $mainlist .= "$slug = $label\n";

    $lines = [];
    foreach ($items as $i => $it) {
        $lines[] = ($i+1).'. '.trim((string)($it['name'] ?? '').' ['.($it['slug'] ?? '').']');
    }
    $prompt = "Map each book CATEGORY to the SINGLE best fitting main category.\n"
        . "Return ONLY JSON: {\"map\":{\"<number>\":\"<main-slug>\"}} using the item numbers.\n"
        . "Choose main-slug EXACTLY from this list (left side), never invent:\n"
        . $mainlist . "\n"
        . "If a category truly fits none, omit its number.\n\nCATEGORIES:\n" . implode("\n", $lines);

    // ÖNEMLİ: doğrudan DeepSeek çağırma — tv_ask kullan. tv_ask "thinking"i
    // kapatıp reasoning_content yedeğini de okur ve sunucunun ERİŞEBİLDİĞİ
    // sağlayıcı ayarını (OpenRouter dahil) kullanır. Doğrudan çağrı V4'te çoğu
    // kez BOŞ content döndürüyordu → "öneri gelmiyor" sorununun sebebi buydu.
    require_once __DIR__ . '/_verify.php';
    $r = tv_ask($prompt, 3000, 90, 'deepseek');
    if (empty($r['ok'])) {
        echo json_encode(['ok'=>false, 'map'=>[], 'error'=>($r['error'] ?? 'AI hata')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $txt = trim(preg_replace('/```json|```/', '', (string)($r['text'] ?? '')));
    $j = json_decode($txt, true) ?: [];
    if (!$j && $txt !== '' && preg_match('/\{.*\}/s', $txt, $m)) $j = json_decode($m[0], true) ?: [];
    $out = [];
    foreach (($j['map'] ?? []) as $num => $slug) {
        $idx  = (int)$num - 1;
        $slug = (string)$slug;
        if (isset($items[$idx]) && isset($mains[$slug])) {
            $out[] = ['id' => (int)$items[$idx]['id'], 'main' => $slug];
        }
    }
    echo json_encode([
        'ok'=>true, 'map'=>$out, 'asked'=>count($items),
        'debug'=> $out ? '' : ('AI yanıtı ayrıştırılamadı: ' . mb_substr($txt, 0, 200)),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok'=>false, 'error'=>'bad action']);
