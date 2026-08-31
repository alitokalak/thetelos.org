<?php
/**
 * Template Name: Topic Guide
 *
 * TheTelos — Konu Rehberi (pilot: Existentialism).
 * Mevcut kategori/kitap listesini DEĞİŞTİRMEZ; onun üstüne özgün bir bilgi
 * katmanı koyar: What is X? · Key Thinkers · Key Concepts · Major Arguments ·
 * Debates · Essential Works (kategoriden OTOMATİK) · Essays (essay tipi eklenince).
 *
 * İçerik: şimdilik aşağıdaki $GUIDES dizisinde (düzenlemesi kolay). İleride
 * kategori term-meta'sına / panele taşınacak. Essential Works ve Essays
 * OTOMATİK büyür (sen kitap/essay ekledikçe listelenir).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$topic_slug = get_post_meta( get_the_ID(), 'tls_topic_slug', true ) ?: 'existentialism';

/* ── Rehber verisi (yerleşik, doğrulanmış ansiklopedik bilgi — uydurma yok) ── */
$GUIDES = [
  'existentialism' => [
    'label'   => 'Existentialism',
    'cat'     => 'existentialism',   // Essential Works için kategori slug
    'intro'   => 'Existentialism is a philosophical movement centred on individual existence, freedom, and the search for meaning in a world without given purpose. It holds that human beings first exist and only afterward define who they are through their choices and actions — there is no fixed human essence handed down in advance. Emerging in the 19th and 20th centuries, it confronts anxiety, mortality, absurdity, and responsibility not as problems to be solved but as basic conditions of being human.',
    'thinkers'=> [
      ['Søren Kierkegaard', 'Danish philosopher, often called the father of existentialism.', 'That truth is subjective and lived: authentic existence means a passionate, personal commitment rather than abstract system-building.', 'Against Hegel’s totalising system, he argued the single individual cannot be dissolved into the universal; faith is a personal “leap” beyond rational proof.'],
      ['Friedrich Nietzsche', 'German philosopher who diagnosed the collapse of inherited meaning.', 'That with the “death of God”, values are no longer given and must be created; the task is to affirm life and become who one is.', 'He held that traditional morality masked a “will to power”; in the absence of absolute values, one must overcome nihilism by creating one’s own.'],
      ['Martin Heidegger', 'German philosopher of being and time.', 'That the human being (Dasein) is defined by its thrown, finite existence and its awareness of death.', 'In Being and Time he argued that authentic existence means facing one’s own mortality rather than fleeing into the anonymous “they”.'],
      ['Jean-Paul Sartre', 'French philosopher and the movement’s most famous voice.', 'That “existence precedes essence” — we are radically free and wholly responsible for what we make of ourselves.', 'Because there is no God-given nature, humans are “condemned to be free”; denying this freedom is “bad faith”.'],
      ['Albert Camus', 'French writer associated with the philosophy of the absurd.', 'That life has no inherent meaning, yet one can live fully by revolting against the absurd rather than surrendering to it.', 'In The Myth of Sisyphus he argued that neither suicide nor false hope answers the absurd; lucid revolt and engagement do. (Camus himself rejected the “existentialist” label.)'],
    ],
    'concepts'=> [
      ['Freedom', 'Humans are not determined by a fixed nature; they choose, and so are responsible for who they become.'],
      ['The Absurd', 'The clash between our demand for meaning and a universe that offers none in itself.'],
      ['Authenticity', 'Living in accordance with one’s own freely chosen values instead of external roles or expectations.'],
      ['Bad Faith', 'Sartre’s term for self-deception — denying one’s freedom by pretending one “has to” act a certain way.'],
      ['Angst / Anxiety', 'The dizziness of freedom: the mood in which one confronts the openness and responsibility of existence.'],
      ['Nihilism', 'The view that life is without objective meaning or value — a challenge existentialism seeks to answer, not endorse.'],
    ],
    'arguments'=> [
      ['“Existence precedes essence”', 'Sartre’s central claim: there is no predetermined human nature. First we exist; then, through our choices, we define our essence.', 'If no God designed us with a purpose, then nothing fixes in advance what a human “should” be. We are therefore fully responsible for the selves we create — and cannot appeal to nature, God, or society to excuse our choices.', 'Critics ask whether human beings are truly so unconstrained — biology, culture, and circumstance shape us; and whether “radical freedom” can ground any shared ethics.'],
      ['The Absurd and revolt', 'Camus’ claim that the absurd is unavoidable but need not lead to despair.', 'Since meaning cannot be found in the world or guaranteed by hope, the honest response is to keep living and creating in full awareness of the absurd — “one must imagine Sisyphus happy”.', 'Some object that a purely defiant stance still smuggles in a value (revolt) it cannot justify; others find it more attitude than argument.'],
    ],
    'debates'=> [
      ['Existentialism vs Nihilism', 'Both start from the loss of given meaning, but nihilism concludes that nothing matters, while existentialism responds that meaning can be created through free, responsible action.'],
      ['Sartre vs Camus', 'Allies who split publicly: Sartre pursued systematic philosophy and political commitment; Camus resisted the “existentialist” label and rejected what he saw as ideological justifications of violence.'],
      ['Kierkegaard vs Nietzsche', 'Two roots of existentialism reaching opposite conclusions about faith: Kierkegaard’s “leap” toward God versus Nietzsche’s declaration of the “death of God”.'],
      ['Existentialism vs Christianity', 'Religious existentialists (Kierkegaard, Marcel) tie authentic existence to faith, while atheistic existentialists (Sartre, Camus) locate meaning wholly within human freedom.'],
    ],
  ],
];

$G = $GUIDES[ $topic_slug ] ?? null;
?>
<style>
.tg-wrap{max-width:900px;margin:0 auto;padding:0 20px}
.tg-hero{padding:48px 0 8px}
.tg-eyebrow{font-family:var(--tls-sans,sans-serif);text-transform:uppercase;letter-spacing:.12em;font-size:12px;color:var(--tls-muted,#888);margin:0 0 6px}
.tg-title{font-family:var(--tls-serif,Georgia,serif);font-size:clamp(30px,5vw,46px);line-height:1.1;margin:0 0 14px}
.tg-intro{font-family:var(--tls-sans,sans-serif);font-size:18px;line-height:1.7;color:var(--tls-text,#222)}
.tg-sec{margin:44px 0}
.tg-sec h2{font-family:var(--tls-serif,Georgia,serif);font-size:26px;margin:0 0 16px;padding-bottom:8px;border-bottom:1px solid var(--tls-border,#e6e6e6)}
.tg-card{border:1px solid var(--tls-border,#e6e6e6);border-radius:12px;padding:16px 18px;margin:0 0 14px;background:var(--tls-surface,#fff)}
.tg-card h3{font-family:var(--tls-serif,Georgia,serif);font-size:19px;margin:0 0 4px}
.tg-card .tg-role{font-family:var(--tls-sans,sans-serif);font-size:13px;color:var(--tls-muted,#888);margin:0 0 10px}
.tg-card .tg-line{font-family:var(--tls-sans,sans-serif);font-size:15px;line-height:1.6;margin:6px 0}
.tg-card .tg-line b{color:var(--tls-text,#222)}
.tg-grid2{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
.tg-toc{display:flex;flex-wrap:wrap;gap:8px;margin:18px 0 0}
.tg-toc a{font-family:var(--tls-sans,sans-serif);font-size:13px;text-decoration:none;border:1px solid var(--tls-border,#e6e6e6);border-radius:20px;padding:5px 12px;color:var(--tls-text,#333)}
.tg-empty{font-family:var(--tls-sans,sans-serif);color:var(--tls-muted,#888);font-size:15px;padding:10px 0}
.tg-back{font-family:var(--tls-sans,sans-serif);font-size:14px}
</style>

<main id="main" role="main">
<div class="tg-wrap">
<?php if ( ! $G ) : ?>
  <div class="tg-hero"><h1 class="tg-title">Topic Guide</h1>
  <p class="tg-intro">Bu konu için henüz rehber tanımlı değil.</p></div>
<?php else : ?>

  <div class="tg-hero">
    <p class="tg-eyebrow">Topic Guide</p>
    <h1 class="tg-title">What is <?php echo esc_html( $G['label'] ); ?>?</h1>
    <p class="tg-intro"><?php echo esc_html( $G['intro'] ); ?></p>
    <div class="tg-toc">
      <a href="#thinkers">Key Thinkers</a><a href="#concepts">Key Concepts</a>
      <a href="#arguments">Major Arguments</a><a href="#debates">Debates</a>
      <a href="#works">Essential Works</a><a href="#essays">Essays</a>
    </div>
  </div>

  <!-- KEY THINKERS -->
  <section class="tg-sec" id="thinkers"><h2>Key Thinkers</h2>
    <?php foreach ( $G['thinkers'] as $t ) : ?>
      <div class="tg-card">
        <h3><?php echo esc_html( $t[0] ); ?></h3>
        <p class="tg-role"><?php echo esc_html( $t[1] ); ?></p>
        <p class="tg-line"><b>Temel fikri:</b> <?php echo esc_html( $t[2] ); ?></p>
        <p class="tg-line"><b>Argümanı:</b> <?php echo esc_html( $t[3] ); ?></p>
      </div>
    <?php endforeach; ?>
  </section>

  <!-- KEY CONCEPTS -->
  <section class="tg-sec" id="concepts"><h2>Key Concepts</h2>
    <div class="tg-grid2">
      <?php foreach ( $G['concepts'] as $c ) : ?>
        <div class="tg-card"><h3><?php echo esc_html( $c[0] ); ?></h3>
          <p class="tg-line"><?php echo esc_html( $c[1] ); ?></p></div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- MAJOR ARGUMENTS -->
  <section class="tg-sec" id="arguments"><h2>Major Arguments</h2>
    <?php foreach ( $G['arguments'] as $a ) : ?>
      <div class="tg-card">
        <h3><?php echo esc_html( $a[0] ); ?></h3>
        <p class="tg-line"><b>İddia:</b> <?php echo esc_html( $a[1] ); ?></p>
        <p class="tg-line"><b>Nasıl kuruluyor:</b> <?php echo esc_html( $a[2] ); ?></p>
        <p class="tg-line"><b>Temel itirazlar:</b> <?php echo esc_html( $a[3] ); ?></p>
      </div>
    <?php endforeach; ?>
  </section>

  <!-- DEBATES / CONTRASTS -->
  <section class="tg-sec" id="debates"><h2>Debates &amp; Contrasts</h2>
    <?php foreach ( $G['debates'] as $d ) : ?>
      <div class="tg-card"><h3><?php echo esc_html( $d[0] ); ?></h3>
        <p class="tg-line"><?php echo esc_html( $d[1] ); ?></p></div>
    <?php endforeach; ?>
  </section>

  <!-- ESSENTIAL WORKS — kategoriden OTOMATİK -->
  <section class="tg-sec" id="works"><h2>Essential Works</h2>
    <?php
    $work_q = new WP_Query( [
      'post_type'      => 'post',
      'category_name'  => $G['cat'],
      'posts_per_page' => 12,
      'orderby'        => 'title',
      'order'          => 'ASC',
      'no_found_rows'  => true,
    ] );
    if ( $work_q->have_posts() ) : ?>
      <div class="tls-books-grid">
        <?php while ( $work_q->have_posts() ) : $work_q->the_post();
          echo function_exists( 'thetelos_book_card' ) ? thetelos_book_card( get_the_ID() )
             : '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>';
        endwhile; ?>
      </div>
      <p class="tg-back" style="margin-top:14px">
        <a href="<?php echo esc_url( get_category_link( get_cat_ID( $G['label'] ) ) ?: home_url( '/category/' . $G['cat'] . '/' ) ); ?>">Tüm <?php echo esc_html( $G['label'] ); ?> özetleri →</a>
      </p>
    <?php else : ?>
      <p class="tg-empty">Bu kategoride henüz kitap özeti yok — eklendikçe burada listelenecek.</p>
    <?php endif; wp_reset_postdata(); ?>
  </section>

  <!-- ESSAYS — essay tipi eklenince OTOMATİK listelenecek -->
  <section class="tg-sec" id="essays"><h2>Essays</h2>
    <?php
    $essays_shown = false;
    if ( post_type_exists( 'tls_essay' ) ) {
      $es_q = new WP_Query( [
        'post_type'      => 'tls_essay',
        'posts_per_page' => 10,
        'no_found_rows'  => true,
        'tax_query'      => [ [ 'taxonomy' => 'category', 'field' => 'slug', 'terms' => $G['cat'] ] ],
      ] );
      if ( $es_q->have_posts() ) {
        $essays_shown = true;
        echo '<div class="tg-grid2">';
        while ( $es_q->have_posts() ) { $es_q->the_post();
          echo '<div class="tg-card"><h3><a href="' . esc_url( get_permalink() ) . '" style="text-decoration:none;color:inherit">' . esc_html( get_the_title() ) . '</a></h3>';
          echo '<p class="tg-line">' . esc_html( wp_trim_words( get_the_excerpt(), 28 ) ) . '</p></div>';
        }
        echo '</div>';
      }
      wp_reset_postdata();
    }
    if ( ! $essays_shown ) : ?>
      <p class="tg-empty">Bu konuda özgün editoryal yazılar (Essays) eklendikçe burada listelenecek.</p>
    <?php endif; ?>
  </section>

  <p class="tg-back" style="margin:30px 0 60px">
    <a href="<?php echo esc_url( home_url( '/category/' . $G['cat'] . '/' ) ); ?>">← <?php echo esc_html( $G['label'] ); ?> book summaries</a>
  </p>

<?php endif; ?>
</div>
</main>

<?php get_footer(); ?>
