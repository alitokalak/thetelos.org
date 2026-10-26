<?php
/**
 * misattribution.php — Başlığına model notu sızmış ("(by X — misattribution)")
 * yazıları bulur ve düzeltir.
 *
 *   ?action=list  → JSON: her yazı için önerilen düzeltme (yeni başlık + gerçek yazar)
 *   POST action=apply&ids=1,2,3 → yalnız 'ready' olanları uygular:
 *       başlıktaki not silinir, yazı gerçek yazara taşınır (yazar terimi yoksa
 *       oluşturulur), slug yeni başlıktan yeniden üretilir. WordPress eski
 *       adresi (_wp_old_slug) otomatik olarak yeni adrese yönlendirir.
 *
 * Gerçek yazar nottan okunamıyorsa (ör. "(modern poet — misattribution)")
 * yazı 'manual' işaretlenir, DOKUNULMAZ.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit; }
session_write_close();
@set_time_limit(180);
header('Content-Type: application/json; charset=utf-8');

$auth   = 'Basic ' . base64_encode(WP_USER . ':' . WP_APP_PASS);
$wp_api = rtrim(WP_URL, '/') . '/wp-json/wp/v2';

function ma_req($method, $url, $auth, $body = null) {
    $ch = curl_init($url);
    $h  = ['Authorization: ' . $auth];
    $o  = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45, CURLOPT_CUSTOMREQUEST => $method];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h;
    curl_setopt_array($ch, $o);
    $r = curl_exec($ch); $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [json_decode((string) $r, true), $c];
}

/* Başlığı çöz: "Kitap (orijinal) (by Gerçek Yazar — misattribution) – Eski Yazar" */
function ma_parse($title) {
    $t = trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $old_author = '';
    if (preg_match('/^(.*\S)\s+[–—-]\s+([^–—()]+)$/u', $t, $m)) { $t = trim($m[1]); $old_author = trim($m[2]); }
    if (!preg_match('/\s*\(([^()]*misattribut[^()]*)\)/iu', $t, $nm)) return null;
    $note  = $nm[1];
    $clean = trim(preg_replace('/\s*\([^()]*misattribut[^()]*\)/iu', '', $t));
    $clean = trim(preg_replace('/\s*[—–-]+\s*$/u', '', $clean));
    $real  = '';
    if (preg_match('/\bby\s+(?:the\s+)?(?:modern\s+|hindi\s+)?(?:poet\s+|philosopher\s+|author\s+|writer\s+)?(.+?)\s*(?:[—–,;]|-\s|$)/iu', $note, $am)) {
        $real = trim($am[1]);
    }
    // Bilinen karışıklık: Budist filozof Nagarjuna ↔ modern Hintçe şair Nagarjun.
    // Not "modern poet" deyip ad vermiyorsa gerçek yazar Nagarjun'dur.
    if ($real === '' && preg_match('/modern\s+poet/i', $note) && preg_match('/^nagarjuna$/i', $old_author)) $real = 'Nagarjun';
    return ['clean' => $clean, 'old_author' => $old_author, 'real_author' => $real, 'note' => $note];
}

/* Yazar terimini bul: tam ad eşleşmesi; yalnız soyad verildiyse tek eşleşen terim. */
function ma_find_author($name, $wp_api, $auth) {
    [$terms] = ma_req('GET', "$wp_api/authors?search=" . rawurlencode($name) . '&per_page=20&_fields=id,name,count', $auth);
    $terms = is_array($terms) ? $terms : [];
    foreach ($terms as $t) if (mb_strtolower($t['name'] ?? '', 'UTF-8') === mb_strtolower($name, 'UTF-8')) return [$t['id'], $t['name']];
    if (strcasecmp($name, 'Nagarjun') === 0) return [0, 'Nagarjun'];   // ayrı kişi; terim yoksa oluştur
    if (strpos(trim($name), ' ') === false) {          // tek kelime (soyad) → tek aday varsa onu al
        $c = array_values(array_filter($terms, fn($t) => preg_match('/\b' . preg_quote($name, '/') . '$/iu', $t['name'] ?? '')));
        if (count($c) === 1) return [$c[0]['id'], $c[0]['name']];
        return [null, null];
    }
    return [0, $name];                                  // tam ad, terim yok → oluşturulacak
}

function ma_scan($wp_api, $auth) {
    $items = []; $seen = [];
    for ($pg = 1; $pg <= 10; $pg++) {
        [$rows, $c] = ma_req('GET', "$wp_api/posts?search=misattribution&per_page=100&page=$pg&status=publish,draft,pending&_fields=id,title,link,status", $auth);
        if ($c !== 200 || !is_array($rows) || !$rows) break;
        foreach ($rows as $p) {
            $id = (int) ($p['id'] ?? 0); if (!$id || isset($seen[$id])) continue; $seen[$id] = 1;
            $title = html_entity_decode($p['title']['rendered'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $pr = ma_parse($title);
            if (!$pr) continue;                                    // başlıkta not yok (yalnız içerikte geçiyor)
            $it = ['id' => $id, 'title' => $title, 'link' => $p['link'] ?? '', 'clean' => $pr['clean'],
                   'old_author' => $pr['old_author'], 'note' => $pr['note'], 'real_author' => '', 'term_id' => null, 'state' => 'manual', 'why' => ''];
            if ($pr['real_author'] === '' || $pr['clean'] === '') { $it['why'] = 'gerçek yazar nottan okunamadı'; $items[] = $it; continue; }
            [$tid, $tname] = ma_find_author($pr['real_author'], $wp_api, $auth);
            if ($tid === null) { $it['why'] = 'yazar "' . $pr['real_author'] . '" kesin bulunamadı'; $it['real_author'] = $pr['real_author']; $items[] = $it; continue; }
            $it['real_author'] = $tname; $it['term_id'] = $tid; $it['state'] = 'ready';
            $it['new_title'] = $pr['clean'] . ' – ' . $tname;
            $items[] = $it;
        }
        if (count($rows) < 100) break;
    }
    return $items;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'apply') {
    $want  = array_filter(array_map('intval', explode(',', (string) ($_POST['ids'] ?? ''))));
    $done  = []; $fail = [];
    foreach (ma_scan($wp_api, $auth) as $it) {
        if ($it['state'] !== 'ready' || ($want && !in_array($it['id'], $want, true))) continue;
        $tid = (int) $it['term_id'];
        if ($tid === 0) {                                          // yazar terimi yok → oluştur
            [$nt] = ma_req('POST', "$wp_api/authors", $auth, ['name' => $it['real_author']]);
            $tid = (int) ($nt['id'] ?? 0);
            if (!$tid) { [$tid] = ma_find_author($it['real_author'], $wp_api, $auth); $tid = (int) $tid; }
        }
        if (!$tid) { $fail[] = ['id' => $it['id'], 'why' => 'yazar terimi oluşturulamadı']; continue; }
        [$r, $c] = ma_req('POST', "$wp_api/posts/{$it['id']}", $auth, [
            'title'   => $it['new_title'],
            'slug'    => $it['new_title'],                         // WP sanitize eder; eski adres otomatik yönlenir
            'authors' => [$tid],
        ]);
        if ($c >= 200 && $c < 300) $done[] = ['id' => $it['id'], 'title' => $it['new_title'], 'link' => $r['link'] ?? ''];
        else $fail[] = ['id' => $it['id'], 'why' => 'HTTP ' . $c];
    }
    echo json_encode(['ok' => true, 'done' => $done, 'fail' => $fail], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = ma_scan($wp_api, $auth);
echo json_encode(['ok' => true, 'count' => count($items),
    'ready' => count(array_filter($items, fn($i) => $i['state'] === 'ready')), 'items' => $items], JSON_UNESCAPED_UNICODE);
