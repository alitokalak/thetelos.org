<?php
/**
 * seo-fix-sentences.php — Mevcut yazıların excerpt & meta description'larını
 * AI KULLANMADAN, anlamlı tam cümlelere düzeltir.
 *
 * Strateji (her alan için):
 *   1) Zaten iyi (<=160 krk ve nokta/!/? ile bitiyor) → dokunma.
 *   2) Metni 155 içinde SON TAM CÜMLEYE kırpabiliyorsa → onu kullan (yarım
 *      parçayı atar). Anlamlıdır.
 *   3) Kırpılamıyorsa (tek parça, hiç cümle sonu yok) → yazının GÖVDESİNİN
 *      ilk tam cümlesinden anlamlı bir özet üretir. Gövde düzgün cümlelerden
 *      oluştuğu için sonuç her zaman anlamlıdır.
 *   4) Hiçbiri mümkün değilse → dokunmaz (nadir).
 *
 * Token harcamaz, anında çalışır. Panele girişli admin çalıştırır.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('yetkisiz'); }

ob_start();
require_once '/home/thetelos/public_html/wp-load.php';
ob_end_clean();

/**
 * Bir alanı (excerpt/meta) anlamlı tam cümleye getir.
 * @return array [yeni_deger|null, method]  method: kept|trimmed|frombody|skip
 */
function tls_fix_field($text, $post_content, $max = 155) {
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

    // 1) Zaten iyi mi?
    if ($text !== '' && mb_strlen($text) <= 160 && preg_match('/[.!?]["\')\]]?$/u', $text)) {
        return [null, 'kept'];
    }

    // 2) Mevcut metni 155 içinde son tam cümleye kırp.
    if ($text !== '') {
        $within = mb_substr($text, 0, $max);
        if (preg_match('/^.*[.!?](?=\s|$)/su', $within, $m) && mb_strlen(trim($m[0])) >= 25) {
            return [trim($m[0]), 'trimmed'];
        }
    }

    // 3) Gövdenin ilk tam cümlesinden üret.
    $body = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $post_content)));
    if ($body !== '') {
        $within = mb_substr($body, 0, $max);
        if (preg_match('/^.*[.!?](?=\s|$)/su', $within, $m) && mb_strlen(trim($m[0])) >= 25) {
            return [trim($m[0]), 'frombody'];
        }
        // 155 içinde tam cümle yoksa ilk cümlenin tamamını al (biraz aşabilir ama tam olsun).
        if (preg_match('/^.*?[.!?](?=\s|$)/su', $body, $m)) {
            $s = trim($m[0]);
            if (mb_strlen($s) >= 25 && mb_strlen($s) <= 220) return [$s, 'frombody'];
        }
    }

    // 4) Kurtarılamadı.
    return [null, 'skip'];
}

/* ── AJAX çalışma adımı (POST — cache'lenmesin diye) ── */
if (isset($_REQUEST['run'])) {
    header('Content-Type: application/json');
    // LiteSpeed/tarayıcı bu dinamik endpoint'i CACHE'LEMESİN — yoksa her
    // çağrıda eski "0 düzeltildi" yanıtı döner ve iş hiç çalışmaz.
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('X-LiteSpeed-Cache-Control: no-cache');
    header('Pragma: no-cache');

    $offset = max(0, (int) ($_REQUEST['offset'] ?? 0));
    $chunk  = 30;
    $dbg = ['kept' => 0, 'trimmed' => 0, 'frombody' => 0, 'skip' => 0, 'borrow' => 0];

    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $chunk,
        'offset'         => $offset,
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'ignore_sticky_posts' => 1,
    ]);

    $total = (int) $q->found_posts;
    $fixed = 0;
    $seen  = 0;

    foreach ($q->posts as $pid) {
        $seen++;
        $post = get_post($pid);
        if (!$post) continue;
        $content = $post->post_content;

        $cur_ex = trim((string) $post->post_excerpt);
        $meta   = get_post_meta($pid, '_yoast_wpseo_metadesc', true);
        $cur_mt = trim((string) $meta);

        [$new_ex, $mx] = tls_fix_field($cur_ex, $content);
        [$new_mt, $mm] = tls_fix_field($cur_mt, $content);

        // Bir alan kurtarılamadıysa (skip), diğeri temiz bir cümleyse ondan ödünç al.
        $ex_clean = ($mx === 'kept') ? $cur_ex : $new_ex;   // temiz excerpt adayı
        $mt_clean = ($mm === 'kept') ? $cur_mt : $new_mt;   // temiz meta adayı
        if ($mx === 'skip' && is_string($mt_clean) && $mt_clean !== '') { $new_ex = $mt_clean; $mx = 'borrow'; }
        if ($mm === 'skip' && is_string($ex_clean) && $ex_clean !== '') { $new_mt = $ex_clean; $mm = 'borrow'; }

        $dbg[$mx]++; $dbg[$mm]++;

        if ($new_ex !== null && $new_ex !== $cur_ex) {
            wp_update_post(['ID' => $pid, 'post_excerpt' => $new_ex]);
            $fixed++;
        }
        if ($new_mt !== null && $new_mt !== $cur_mt) {
            update_post_meta($pid, '_yoast_wpseo_metadesc', $new_mt);
            $fixed++;
        }
    }

    $next = $offset + $seen;
    echo json_encode([
        'ok'        => true,
        'total'     => $total,
        'processed' => $next,
        'fixed'     => $fixed,
        'done'      => ($seen === 0 || $next >= $total),
        'next'      => $next,
        'dbg'       => $dbg,
    ]);
    exit;
}

/* ── Basit HTML arayüz (panel butonu bunu ?run ile çağırır; bu sayfa yedek) ── */
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;max-width:640px;margin:40px auto">'
   . '<h2>Excerpt & Meta — Akıllı Düzeltme (AI\'sız)</h2>'
   . '<p>Bu araç panelde <b>İçerik SEO</b> ekranındaki “⚡ Hızlı Düzelt (AI\'sız)” butonundan çalıştırılır.</p>'
   . '</body>';
