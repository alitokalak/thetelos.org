<?php
// Kaynak zinciri canlı testi (GitHub Actions'ta çalışır).
require __DIR__ . '/../thetelos-panel/api/_proto.php';
$books = [
  ['Candide (Candide, ou l\'Optimisme)', 'Voltaire'],
  ['Meditations on First Philosophy (Meditationes de prima philosophia)', 'René Descartes'],
  ['The Social Contract (Du contrat social)', 'Jean-Jacques Rousseau'],
  ['Sermons on the Song of Songs (Sermones super Cantica Canticorum)', 'Bernard of Clairvaux'],
  ['On Women', 'Arthur Schopenhauer'],
  ['Essays (Essais)', 'Michel de Montaigne'],
  ['The Spirit of the Laws (De l\'esprit des lois)', 'Montesquieu'],
  ['Pensées', 'Blaise Pascal'],
];
foreach ($books as [$b, $a]) {
  echo "\n=== $b — $a\n";
  foreach (['openlibrary_ia' => 'proto_openlibrary_ia', 'gallica' => 'proto_gallica'] as $n => $fn) {
    $t0 = microtime(true); $r = $fn($b, $a);
    $ok = !empty($r['found']); $txt = $r['text'] ?? '';
    printf("  %-15s found=%s src=%s len=%d auth_ok=%s %.1fs url=%s\n", $n, $ok?'Y':'n', $r['source'] ?? '-', mb_strlen($txt),
      $ok ? (proto_author_in_text($a, $txt, $b) ? 'Y' : 'n') : '-', microtime(true)-$t0, $r['url'] ?? '');
    if ($ok) echo "    sample: " . str_replace("\n", ' ', mb_substr(trim(proto_clean($r['source'], $txt)), 2000, 220)) . "\n";
    else echo "    debug: " . mb_substr(json_encode($r['debug'] ?? null, JSON_UNESCAPED_UNICODE), 0, 600) . "\n";
  }
}
