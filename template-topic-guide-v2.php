<?php
/**
 * Template Name: Topic Guide v2
 *
 * TheTelos — Konu Rehberi (pilot: Existentialism). Editoryal, tarayıcı-dostu
 * referans katmanı. Mevcut kitap listesini DEĞİŞTİRMEZ. Essential Works ve
 * Essays OTOMATİK büyür. İçerik $GUIDES dizisinde (düzenlemesi kolay; ileride
 * panele taşınır). Tasarım: 2. tur redesign (numaralı argümanlar, tartışma
 * çiftleri, kavram kartları, meta satırı).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$topic_slug = get_post_meta( get_the_ID(), 'tls_topic_slug', true ) ?: 'existentialism';

$GUIDES = [
  'existentialism' => [
    'label'   => 'Existentialism',
    'cat'     => 'existentialism',
    'updated' => 'August 2026',
    'read'    => 14,
    'intro'   => 'Existentialism is a philosophy of individual existence, freedom, and the search for meaning in a world that offers none in advance. It holds that we first exist and only afterward define who we are, through our choices and actions — there is no fixed human essence given beforehand. It treats anxiety, mortality, absurdity, and responsibility not as problems to be solved but as the basic conditions of being human.',
    'thinkers'=> [
      ['Søren Kierkegaard', '1813–1855 · often called the father of existentialism', 'Truth is subjective and lived: authentic existence is a passionate, personal commitment, not an abstract system.', 'Against Hegel’s total system, the single individual cannot be dissolved into the universal; faith is a personal leap beyond proof.'],
      ['Friedrich Nietzsche', '1844–1900 · diagnosed the collapse of inherited meaning', 'With the “death of God”, values are no longer given and must be created; the task is to affirm life and become who one is.', 'Traditional morality masked a will to power; in the absence of absolute values one must overcome nihilism by creating one’s own.'],
      ['Martin Heidegger', '1889–1976 · philosopher of being and time', 'The human being (Dasein) is defined by its thrown, finite existence and its awareness of death.', 'Authentic existence means facing one’s own mortality rather than fleeing into the anonymous “they”.'],
      ['Jean-Paul Sartre', '1905–1980 · the movement’s most famous voice', '“Existence precedes essence” — we are radically free and wholly responsible for what we make of ourselves.', 'With no God-given nature, humans are “condemned to be free”; denying this freedom is bad faith.'],
      ['Albert Camus', '1913–1960 · philosopher of the absurd', 'Life has no inherent meaning, yet one can live fully by revolting against the absurd rather than surrendering to it.', 'Neither suicide nor false hope answers the absurd; lucid revolt does. (Camus rejected the existentialist label.)'],
      ['Simone de Beauvoir', '1908–1986 · ethics of situated freedom', 'Freedom is always situated: real conditions — above all oppression — narrow the horizon of choice.', 'An existentialist ethics must work to widen that horizon, not merely assert an abstract, universal freedom.'],
    ],
    'concepts'=> [
      ['Authenticity', '', 'Owning your own choices instead of hiding in ready-made roles and others’ expectations.'],
      ['Bad Faith', 'mauvaise foi', 'Self-deception that denies one’s freedom: “I had no choice but to act this way.”'],
      ['Anxiety', 'angst', 'Not fear of a particular object, but the dizziness that arises from unlimited possibility itself.'],
      ['The Absurd', '', 'The unbridgeable gap between our demand for meaning and a universe that gives no answer.'],
      ['Thrownness', 'Geworfenheit', 'Being born into a time, body, and history we did not choose — where every freedom begins.'],
      ['Being-toward-death', 'Sein-zum-Tode', 'Grasping mortality not as an end but as the horizon that makes taking life seriously possible.'],
    ],
    'arguments'=> [
      ['Existence precedes essence.', 'A knife’s function is fixed before it is made; a human’s is not. With no human nature given in advance, what we are emerges only from what we do. Essence is the sum seen in hindsight, at the end of a life.', 'Biology, evolution, and culture give us strong tendencies; the “zero essence” claim treats our given limits too lightly.'],
      ['Freedom is not an option but a sentence.', 'Not choosing is itself a choice; circumstances do not determine action, they only offer a situation to be interpreted. Responsibility is therefore non-transferable, and anxiety is the direct experience of freedom.', 'Poverty, oppression, and trauma are real constraints. Beauvoir answers partly from within: freedom is situated, and conditions narrow it.'],
      ['Meaning is not found but made.', 'If the universe offers no ready purpose, meaning arises through commitment — to a work, a person, a project. The source of value is the choice itself.', 'A value grounded only in choice is open to arbitrariness: does committing to a project suffice to make it good?'],
    ],
    'debates'=> [
      ['Is existentialism a humanism?', ['Sartre — yes', 'In choosing, a person chooses not only themselves but an image of how a human ought to be; existentialism is therefore a humanism grounded in responsibility.'], ['Heidegger — no', 'In the “Letter on Humanism”, he argues that any approach centring the human re-imprisons the question of being within metaphysics.']],
      ['Facing the absurd', ['Revolt — Camus', 'The absurd must be neither denied nor closed off by a leap to transcendence; it must be lived with, lucidly.'], ['Leap of faith — Kierkegaard', 'Where reason runs out, the individual can entrust themselves to the transcendent through a commitment without proof.']],
      ['How situated is freedom?', ['Radical freedom — early Sartre', 'In every circumstance there is a choice; even a person in chains chooses the meaning they give the chains.'], ['Situated freedom — Beauvoir & Fanon', 'Oppression materially narrows the horizon of choice; ethics must also work to widen that horizon.']],
      ['Existentialism vs Nihilism', ['Nihilism', 'Concludes that with no given meaning, nothing matters.'], ['Existentialism', 'Responds that meaning can be created through free, responsible action.']],
    ],
  ],
];

$G = $GUIDES[ $topic_slug ] ?? null;

/* Meta: bölüm sayısı (dolu bölümler) + okuma süresi + güncelleme */
$sections = 0;
if ( $G ) { foreach ( ['thinkers','concepts','arguments','debates'] as $k ) if ( ! empty( $G[$k] ) ) $sections++; $sections += 2; /* works + essays */ }
?>
<style>
:root{ --tg-accent: var(--tls-accent, #c9a34e); --tg-ink: var(--tls-text,#1d1d1f); --tg-mut: var(--tls-muted,#8a8a8e); --tg-line: var(--tls-border,#e7e7e9); --tg-card: var(--tls-surface,#fff); }
.tg-wrap{max-width:820px;margin:0 auto;padding:0 20px 72px}
.tg-hero{padding:44px 0 6px;border-bottom:1px solid var(--tg-line);margin-bottom:8px}
.tg-eyebrow{font-family:var(--tls-sans,system-ui);text-transform:uppercase;letter-spacing:.16em;font-size:11px;font-weight:600;color:var(--tg-accent);margin:0 0 10px}
.tg-title{font-family:var(--tls-serif,Georgia,serif);font-size:clamp(34px,6vw,52px);line-height:1.05;margin:0 0 12px;color:var(--tg-ink)}
.tg-meta{font-family:var(--tls-sans,system-ui);font-size:12.5px;color:var(--tg-mut);margin:0 0 18px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.tg-meta span::after{content:"·";margin-left:10px;color:var(--tg-line)}
.tg-meta span:last-child::after{content:""}
.tg-intro{font-family:var(--tls-sans,system-ui);font-size:18px;line-height:1.7;color:var(--tg-ink);max-width:70ch}
.tg-toc{position:sticky;top:0;z-index:5;display:flex;gap:6px;flex-wrap:wrap;padding:12px 0;margin:0 0 8px;background:var(--tls-bg,#fff);border-bottom:1px solid var(--tg-line)}
.tg-toc a{font-family:var(--tls-sans,system-ui);font-size:12.5px;text-decoration:none;border:1px solid var(--tg-line);border-radius:20px;padding:5px 12px;color:var(--tg-ink);white-space:nowrap}
.tg-toc a:hover{border-color:var(--tg-accent);color:var(--tg-accent)}
.tg-sec{margin:40px 0}
.tg-sec>h2{font-family:var(--tls-serif,Georgia,serif);font-size:27px;margin:0 0 4px;color:var(--tg-ink)}
.tg-sec>.tg-sub{font-family:var(--tls-sans,system-ui);font-size:14px;color:var(--tg-mut);margin:0 0 18px}
.tg-card{border:1px solid var(--tg-line);border-radius:14px;padding:18px 20px;margin:0 0 14px;background:var(--tg-card);transition:box-shadow .2s,border-color .2s}
.tg-card:hover{border-color:#d9d2bf;box-shadow:0 6px 22px rgba(0,0,0,.05)}
.tg-card h3{font-family:var(--tls-serif,Georgia,serif);font-size:20px;margin:0 0 3px;color:var(--tg-ink)}
.tg-card .tg-role{font-family:var(--tls-sans,system-ui);font-size:12.5px;color:var(--tg-mut);margin:0 0 12px}
.tg-lbl{display:block;font-family:var(--tls-sans,system-ui);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--tg-accent);margin:12px 0 2px}
.tg-txt{font-family:var(--tls-sans,system-ui);font-size:15px;line-height:1.62;color:var(--tg-ink);margin:0}
.tg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px}
.tg-concept h3{font-size:17px}.tg-concept .tg-orig{font-style:italic;color:var(--tg-mut);font-weight:400;font-size:14px}
.tg-arg{display:flex;gap:16px;align-items:flex-start}
.tg-num{flex:0 0 auto;font-family:var(--tls-serif,Georgia,serif);font-size:34px;line-height:1;color:var(--tg-line);width:52px}
.tg-arg .tg-body{flex:1}
.tg-deb{display:grid;grid-template-columns:1fr 1fr;gap:0;border:1px solid var(--tg-line);border-radius:14px;overflow:hidden;margin:0 0 14px}
.tg-deb .tg-q{grid-column:1/-1;font-family:var(--tls-sans,system-ui);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--tg-mut);padding:12px 18px;border-bottom:1px solid var(--tg-line);background:rgba(0,0,0,.015)}
.tg-deb .tg-side{padding:16px 18px}
.tg-deb .tg-side:first-of-type{border-right:1px solid var(--tg-line)}
.tg-deb .tg-side h4{font-family:var(--tls-serif,Georgia,serif);font-size:16px;margin:0 0 6px;color:var(--tg-ink)}
.tg-empty{font-family:var(--tls-sans,system-ui);color:var(--tg-mut);font-size:15px;padding:8px 0}
.tg-back{font-family:var(--tls-sans,system-ui);font-size:14px}
.tg-foot{margin-top:56px;padding-top:18px;border-top:1px solid var(--tg-line);font-family:var(--tls-sans,system-ui);font-size:12px;color:var(--tg-mut)}
@media(max-width:560px){.tg-deb{grid-template-columns:1fr}.tg-deb .tg-side:first-of-type{border-right:0;border-bottom:1px solid var(--tg-line)}.tg-arg{gap:10px}.tg-num{width:34px;font-size:26px}}
</style>

<main id="main" role="main">
<div class="tg-wrap">
<?php if ( ! $G ) : ?>
  <div class="tg-hero"><h1 class="tg-title">Topic Guide</h1></div>
  <p class="tg-intro">Bu konu için henüz rehber tanımlı değil.</p>
<?php else : ?>

  <header class="tg-hero">
    <p class="tg-eyebrow">Topic Guide</p>
    <h1 class="tg-title"><?php echo esc_html( $G['label'] ); ?></h1>
    <p class="tg-meta">
      <span><?php echo (int) $sections; ?> sections</span>
      <span>~<?php echo (int) $G['read']; ?> min read</span>
      <span>Updated <?php echo esc_html( $G['updated'] ); ?></span>
    </p>
    <p class="tg-intro"><?php echo esc_html( $G['intro'] ); ?></p>
  </header>

  <nav class="tg-toc" aria-label="Sections">
    <a href="#thinkers">Thinkers</a><a href="#concepts">Concepts</a>
    <a href="#arguments">Arguments</a><a href="#debates">Debates</a>
    <a href="#works">Essential Works</a><a href="#essays">Essays</a>
  </nav>

  <!-- KEY THINKERS -->
  <section class="tg-sec" id="thinkers">
    <h2>Key Thinkers</h2>
    <p class="tg-sub">The figures the movement keeps returning to — what each argued, and how.</p>
    <?php foreach ( $G['thinkers'] as $t ) : ?>
      <div class="tg-card">
        <h3><?php echo esc_html( $t[0] ); ?></h3>
        <p class="tg-role"><?php echo esc_html( $t[1] ); ?></p>
        <span class="tg-lbl">Core idea</span><p class="tg-txt"><?php echo esc_html( $t[2] ); ?></p>
        <span class="tg-lbl">Argument</span><p class="tg-txt"><?php echo esc_html( $t[3] ); ?></p>
      </div>
    <?php endforeach; ?>
  </section>

  <!-- KEY CONCEPTS -->
  <section class="tg-sec" id="concepts">
    <h2>Key Concepts</h2>
    <p class="tg-sub">Short definitions of the terms that recur across the texts.</p>
    <div class="tg-grid">
      <?php foreach ( $G['concepts'] as $c ) : ?>
        <div class="tg-card tg-concept">
          <h3><?php echo esc_html( $c[0] ); ?><?php if ( $c[1] ) : ?> <span class="tg-orig">(<?php echo esc_html( $c[1] ); ?>)</span><?php endif; ?></h3>
          <p class="tg-txt"><?php echo esc_html( $c[2] ); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- MAJOR ARGUMENTS -->
  <section class="tg-sec" id="arguments">
    <h2>Major Arguments</h2>
    <p class="tg-sub">Each argument in three steps: the claim, how it is built, and the objections to it.</p>
    <?php foreach ( $G['arguments'] as $i => $a ) : ?>
      <div class="tg-card tg-arg">
        <div class="tg-num"><?php echo sprintf( '%02d', $i + 1 ); ?></div>
        <div class="tg-body">
          <h3><?php echo esc_html( $a[0] ); ?></h3>
          <span class="tg-lbl">How it is built</span><p class="tg-txt"><?php echo esc_html( $a[1] ); ?></p>
          <span class="tg-lbl">Objections</span><p class="tg-txt"><?php echo esc_html( $a[2] ); ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

  <!-- DEBATES / CONTRASTS -->
  <section class="tg-sec" id="debates">
    <h2>Debates &amp; Contrasts</h2>
    <p class="tg-sub">Open tensions — within the movement and against it.</p>
    <?php foreach ( $G['debates'] as $d ) : ?>
      <div class="tg-deb">
        <div class="tg-q"><?php echo esc_html( $d[0] ); ?></div>
        <div class="tg-side"><h4><?php echo esc_html( $d[1][0] ); ?></h4><p class="tg-txt"><?php echo esc_html( $d[1][1] ); ?></p></div>
        <div class="tg-side"><h4><?php echo esc_html( $d[2][0] ); ?></h4><p class="tg-txt"><?php echo esc_html( $d[2][1] ); ?></p></div>
      </div>
    <?php endforeach; ?>
  </section>

  <!-- ESSENTIAL WORKS — kategoriden OTOMATİK -->
  <section class="tg-sec" id="works">
    <h2>Essential Works</h2>
    <p class="tg-sub">Start here — every book’s summary is on the site.</p>
    <?php
    $work_q = new WP_Query( [
      'post_type' => 'post', 'category_name' => $G['cat'], 'posts_per_page' => 12,
      'orderby' => 'date', 'order' => 'ASC', 'no_found_rows' => true,
    ] );
    if ( $work_q->have_posts() ) : ?>
      <div class="tls-books-grid">
        <?php while ( $work_q->have_posts() ) : $work_q->the_post();
          echo function_exists( 'thetelos_book_card' ) ? thetelos_book_card( get_the_ID() )
             : '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>';
        endwhile; ?>
      </div>
      <p class="tg-back" style="margin-top:14px"><a href="<?php echo esc_url( home_url( '/category/' . $G['cat'] . '/' ) ); ?>">All <?php echo esc_html( $G['label'] ); ?> summaries →</a></p>
    <?php else : ?>
      <p class="tg-empty">Book summaries in this category will appear here as they are added.</p>
    <?php endif; wp_reset_postdata(); ?>
  </section>

  <!-- ESSAYS — essay tipi eklenince OTOMATİK -->
  <section class="tg-sec" id="essays">
    <h2>Essays</h2>
    <p class="tg-sub">Long-form editorial pieces by TheTelos writers on this topic.</p>
    <?php
    $shown = false;
    if ( post_type_exists( 'tls_essay' ) ) {
      $es = new WP_Query( [ 'post_type'=>'tls_essay', 'posts_per_page'=>10, 'no_found_rows'=>true,
        'tax_query'=>[ ['taxonomy'=>'category','field'=>'slug','terms'=>$G['cat']] ] ] );
      if ( $es->have_posts() ) { $shown = true; echo '<div class="tg-grid">';
        while ( $es->have_posts() ) { $es->the_post();
          echo '<div class="tg-card"><h3><a href="'.esc_url(get_permalink()).'" style="color:inherit;text-decoration:none">'.esc_html(get_the_title()).'</a></h3><p class="tg-txt">'.esc_html(wp_trim_words(get_the_excerpt(),26)).'</p></div>'; }
        echo '</div>'; }
      wp_reset_postdata();
    }
    if ( ! $shown ) : ?><p class="tg-empty">Original essays on this topic will be listed here as they are published.</p><?php endif; ?>
  </section>

  <p class="tg-back" style="margin-top:30px"><a href="<?php echo esc_url( home_url( '/category/' . $G['cat'] . '/' ) ); ?>">← <?php echo esc_html( $G['label'] ); ?> book summaries</a></p>
  <p class="tg-foot">thetelos · Topic Guides · <?php echo date( 'Y' ); ?></p>

<?php endif; ?>
</div>
</main>

<?php get_footer(); ?>
