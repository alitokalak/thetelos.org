<?php
/**
 * fix-dupes-1127.php — TEK SEFERLİK temizlik (27.11.2026).
 * 24–27 Kasım'da çift kayıt filtresinden kaçan sayfalar: aynı eserin çeviri baskıları,
 * külliyat/mektup ciltleri, seçkiler ve BAŞKA yazarların kitapları.
 *   ?            → yalnız liste (hiçbir şey değişmez)
 *   ?apply=1     → listedekiler TASLAĞA alınır (silinmez, geri alınabilir), 4 başlık İngilizce adına çevrilir
 *   ?revert=1    → taslağa alınanlar yeniden yayınlanır
 * Panel oturumu gerekir.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('Önce panele giriş yapın.'); }
session_write_close();
ob_start(); require_once '/home/thetelos/public_html/wp-load.php'; ob_end_clean();
header('Content-Type: text/html; charset=utf-8');

$hide = [
    // Sextus Empiricus — aynı 3 eserin baskıları/çevirileri, külliyat, seçki
    48271 => 'İtalyanca seçki (Outlines + Against the Mathematicians parçaları)',
    48267 => 'Against the Logicians — 2. kopya (Loeb baskısı)',
    48265 => 'Opera, cilt 2 (külliyat cildi)',
    48263 => 'başlık = yazar adı',
    48260 => 'Outlines of Pyrrhonism — Türkçe baskı',
    48257 => 'Adversus dogmaticos = Against the Logicians + Physicists + Ethicists (zaten var)',
    48254 => 'Outlines of Pyrrhonism — Türkçe baskı (Kuşkuculuk)',
    48252 => 'seçki (Scepticism, Man and God)',
    48250 => 'Opera (külliyat)',
    48245 => 'Complete (külliyat)',
    48243 => 'Against the Logicians — İtalyanca baskı',
    // John Dee — başkasının kitabı
    48281 => 'Robert Recorde\'un kitabı (Dee yalnız düzenledi)',
    48279 => 'Robert Recorde\'un kitabı (Dee yalnız düzenledi)',
    48277 => 'Henry Perri\'nin kitabı (Dosparth ar retoreg)',
    48273 => 'Öklid\'in Elementler\'i (Dee\'nin önsözü ayrı sayfada var)',
    // Turgot
    48237 => 'Oeuvres (külliyat)',
    48232 => 'seçki (Meek derlemesi)',
    48226 => 'seçki (Groenewegen derlemesi)',
    // Agrippa
    48216 => 'Occult Philosophy 1. kitabı — Philosophy of Natural Magic ile aynı',
    // Wolff
    48195 => 'Jus gentium — 2. kopya',
    // Burckhardt
    48178 => 'Greek Culture — Almanca baskı (İngilizcesi var)',
    48153 => 'Greek Culture — Almanca baskı (İngilizcesi var)',
    48156 => 'Reflections on History — Almanca baskı (İngilizcesi var)',
    48167 => 'Briefe (mektuplar cildi)',
    48162 => 'Vorträge (konuşmalar derlemesi)',
    // Gassendi
    48134 => 'Galileo\'nun kitabı (Sidereus nuncius)',
    48122 => 'Opera omnia (külliyat)',
    48118 => 'Correspondance (mektuplar)',
    48148 => 'Gassendi–Peiresc mektupları',
    48130 => 'Syntagma philosophiae Epicuri — 2. kopya',
    // Pinker
    48097 => 'Pinker\'in kitabı değil (Van Gogh biyografisi)',
    48089 => 'The Better Angels of Our Nature — Almanca baskı (İngilizcesi var)',
    // bell hooks
    48050 => 'bell hooks\'un kitabı değil (Roni Horn kataloğu)',
    // John Muir — John Muir Laws (başka kişi)
    48059 => 'John Muir Laws\'un kitabı (başka kişi)',
    48057 => 'John Muir Laws\'un kitabı (başka kişi)',
];
$rename = [
    48240 => 'Outlines of Pyrrhonism – Sextus Empiricus',
    48269 => 'Against the Logicians – Sextus Empiricus',
    48092 => 'The Stuff of Thought – Steven Pinker',
    48047 => 'All About Love – bell hooks',
    48159 => 'The Cicerone – Jacob Burckhardt',
];

$apply  = !empty($_GET['apply']);
$revert = !empty($_GET['revert']);
echo '<pre style="font:14px/1.5 monospace">';
echo $apply ? "UYGULANIYOR…\n\n" : ($revert ? "GERİ ALINIYOR…\n\n" : "ÖNİZLEME (değişiklik yok). Uygulamak için adrese ?apply=1 ekleyin.\n\n");
$n = 0;
foreach ($hide as $id => $why) {
    $p = get_post($id);
    if (!$p) { echo "#$id yok\n"; continue; }
    $t = html_entity_decode($p->post_title, ENT_QUOTES, 'UTF-8');
    if ($apply && $p->post_status === 'publish') {
        wp_update_post(['ID' => $id, 'post_status' => 'draft']);
        update_post_meta($id, '_tls_fix1127', 'draft');
        $n++;
    } elseif ($revert && get_post_meta($id, '_tls_fix1127', true) === 'draft') {
        wp_update_post(['ID' => $id, 'post_status' => 'publish']);
        delete_post_meta($id, '_tls_fix1127');
        $n++;
    }
    echo "TASLAK  #$id  [{$p->post_status}]  " . esc_html($t) . "\n        → $why\n";
}
echo "\n";
foreach ($rename as $id => $nt) {
    $p = get_post($id);
    if (!$p) { echo "#$id yok\n"; continue; }
    $old = html_entity_decode($p->post_title, ENT_QUOTES, 'UTF-8');
    if ($apply && $old !== $nt) {
        update_post_meta($id, '_tls_fix1127_title', $old);
        wp_update_post(['ID' => $id, 'post_title' => $nt]);   // adres (slug) değişmez
        $n++;
    } elseif ($revert && ($o = get_post_meta($id, '_tls_fix1127_title', true))) {
        wp_update_post(['ID' => $id, 'post_title' => $o]);
        delete_post_meta($id, '_tls_fix1127_title');
        $n++;
    }
    echo "AD      #$id  " . esc_html($old) . "  →  " . esc_html($nt) . "\n";
}
if (function_exists('litespeed_purge_all')) litespeed_purge_all();
do_action('litespeed_purge_all');
echo "\n" . ($apply || $revert ? "Bitti: $n değişiklik." : count($hide) . ' sayfa taslağa alınacak, ' . count($rename) . ' başlık düzeltilecek.') . "</pre>";
