<?php
/**
 * fix-claviez.php — "Throwing the Moral Dice" sayfasını düzeltir (tek seferlik).
 * Kitap Žižek'in değil; Thomas Claviez ve Viola Marchi'nin editörlüğünde bir derleme
 * (Fordham University Press, 2021). Bilgiler yayınevi ve Project MUSE kaydından.
 *
 * Panel oturumu açıkken:
 *   /thetelos-panel/api/fix-claviez.php          → ne değişeceğini gösterir
 *   /thetelos-panel/api/fix-claviez.php?apply=1  → uygular
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
if (empty($_SESSION['tls_auth'])) { http_response_code(401); exit('Önce panele giriş yapın.'); }
ob_start(); require_once '/home/thetelos/public_html/wp-load.php'; ob_end_clean();
header('Content-Type: text/plain; charset=utf-8');

$old_slug = 'throwing-the-moral-dice-slavoj-zizek';
$new_slug = 'throwing-the-moral-dice-thomas-claviez-viola-marchi';
$post = get_page_by_path($old_slug, OBJECT, 'post') ?: get_page_by_path($new_slug, OBJECT, 'post');
if (!$post) exit("Yazı bulunamadı ($old_slug).");
$pid = $post->ID;

$author_name = 'Thomas Claviez & Viola Marchi';
$title = 'Throwing the Moral Dice – Thomas Claviez & Viola Marchi';
$meta  = 'Throwing the Moral Dice, edited by Thomas Claviez and Viola Marchi, gathers nine thinkers on how ethics can respond to contingency.';
$excerpt = 'A 2021 Fordham University Press collection, edited by Thomas Claviez and Viola Marchi, on ethics and the problem of contingency.';

$content = <<<HTML
<h2>About the Book</h2>
<p><em>Throwing the Moral Dice: Ethics and the Problem of Contingency</em> is a collection of essays edited by <strong>Thomas Claviez</strong> and <strong>Viola Marchi</strong>, both of the University of Bern. It was published by Fordham University Press in December 2021 in the <em>Just Ideas</em> series (272 pages), with a foreword by Alain Badiou.</p>
<p>The book brings together nine philosophers and theorists: Étienne Balibar, Rosi Braidotti, Thomas Claviez, Drucilla Cornell, Hans Ulrich Gumbrecht, Viola Marchi, Michael Naas, Cary Wolfe and Slavoj Žižek. Each contributor writes a separate chapter.</p>

<h2>What the Volume Asks</h2>
<p>According to the publisher, contingency today is often treated as a threat: the precariousness of work, the unknown risks of terrorism, the uncertain future of the planet. It is something to be calculated and controlled. The volume asks whether contingency can instead be thought of in its creative aspects, outside the language of risk and exposure.</p>
<p>Its central questions are what place contingency, as what is unnecessary and law-defying, has in ethics, and what an "ethics of contingency" would look like if it did not try to remove contingency altogether. The contributors approach these questions from deconstruction, feminism and ecological thought, and revisit concepts such as difference, agency, community and life.</p>

<h2>Contents</h2>
<ul>
<li>Foreword: Ethics and Contingency — Alain Badiou</li>
<li>Introduction: Throwing the Moral Dice: Ethics 2.0, Contingency, and Dialectics — Thomas Claviez and Viola Marchi</li>
</ul>
<p><strong>Part I: Throwing the Moral Dice: Ethics and/of Contingency</strong></p>
<ol>
<li>Three Notes on Contingency Today: Stress, Science — and Consolation from the Past? — Hans Ulrich Gumbrecht</li>
<li>Cosmopolitan Ethics as an Ethics of Contingency: Toward a Metonymic Community — Thomas Claviez</li>
<li>Dumb Luck: Jacques Derrida and the Problem of Contingency — Michael Naas</li>
<li>The Apophatic Community: Ethics, Contingency, Negation — Viola Marchi</li>
</ol>
<p><strong>Part II: Other Others: Ethics 2.0 and the Problem of the "Unsynthesizable"</strong></p>
<ol start="5">
<li>Commonality versus Individuality: An Ethical Dilemma? — Étienne Balibar</li>
<li>Critique, Power, and the Ethics of Affirmation — Rosi Braidotti</li>
<li>The Promise of Practical Philosophy and Institutional Innovation — Drucilla Cornell</li>
<li>Ethics of Circular Time — Slavoj Žižek</li>
<li>The Road Not Taken: Environmental Ethics, Reciprocity, and Non-Negative Nonagency — Thomas Claviez</li>
<li>"There Is No World": Living Life in Deconstruction and Theoretical Biology — Cary Wolfe</li>
</ol>

<h2>The Editors</h2>
<p><strong>Thomas Claviez</strong> is Professor for Literary Theory at the University of Bern, where he is responsible for the MA program in World Literature. His books include <em>Aesthetics and Ethics: Otherness and Moral Imagination from Aristotle to Levinas and from Uncle Tom's Cabin to House Made of Dawn</em> (2008).</p>
<p><strong>Viola Marchi</strong> is a postdoctoral researcher at the University of Bern. She studied English and Italian literatures at the University of Pisa and the University of Bern, where she received her PhD in English in 2019.</p>

<p><em>Correction: An earlier version of this page wrongly presented this book as a work by Slavoj Žižek, who contributed one chapter. We thank Professor Thomas Claviez for pointing out the error.</em></p>
<p><small>Sources: Fordham University Press; Project MUSE.</small></p>
HTML;

$term = term_exists($author_name, 'authors');
$cur  = wp_get_post_terms($pid, 'authors', ['fields' => 'names']);
echo "Yazı #$pid: " . get_the_title($pid) . "\n";
echo "Şimdiki yazar: " . implode(', ', (array) $cur) . "\n";
echo "Yeni başlık : $title\nYeni yazar  : $author_name\nYeni adres  : /$new_slug/ (eski adres otomatik yönlenir)\n";
echo "Meta (" . mb_strlen($meta) . " karakter): $meta\n";

if (empty($_GET['apply'])) exit("\nUygulamak için adresin sonuna ?apply=1 ekleyin.\n");

if (!$term) $term = wp_insert_term($author_name, 'authors');
if (is_wp_error($term)) exit('Yazar oluşturulamadı: ' . $term->get_error_message());
wp_set_object_terms($pid, [(int) $term['term_id']], 'authors', false);

$r = wp_update_post(['ID' => $pid, 'post_title' => $title, 'post_name' => $new_slug, 'post_content' => $content, 'post_excerpt' => $excerpt], true);
if (is_wp_error($r)) exit('Güncellenemedi: ' . $r->get_error_message());
update_post_meta($pid, '_yoast_wpseo_metadesc', $meta);
update_post_meta($pid, '_tls_pub_year', '2021');
delete_post_meta($pid, '_tls_audio_url');
if (function_exists('litespeed_purge_single_post')) litespeed_purge_single_post($pid);
do_action('litespeed_purge_post', $pid);
do_action('litespeed_purge_all');

echo "\nTAMAM ✓  Yeni sayfa: " . get_permalink($pid) . "\n";
