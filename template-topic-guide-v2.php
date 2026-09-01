<?php
/**
 * Template Name: Topic Guide v2
 *
 * TheTelos — Konu Rehberi (pilot: Existentialism). Tasarım: kullanıcının
 * onayladığı redesign taslağı (site fontları: --tls-serif / --tls-sans, krem zemin,
 * altın accent, sticky scroll-spy çip nav, numaralı argümanlar, VS tartışma
 * kartları, eser ızgarası, deneme listesi, "sıradaki rehber" CTA).
 * İçerik $GUIDES'ta (İngilizce). Essential Works + Essays OTOMATİK büyür.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$topic_slug = get_post_meta( get_the_ID(), 'tls_topic_slug', true ) ?: 'existentialism';

$GUIDES = [
  'existentialism' => [
    'label'   => 'Existentialism',
    'cat'     => 'existentialism',
    'next'    => 'Stoicism',
    'updated' => 'August 2026',
    'read'    => 14,
    'intro'   => 'Existentialism is a philosophy of individual existence, freedom, and the search for meaning in a world that offers none in advance. It holds that we first exist and only afterward define who we are, through our choices and actions — there is no fixed human essence given beforehand. It treats anxiety, mortality, absurdity, and responsibility not as problems to be solved but as the basic conditions of being human.',
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
      ['Is existentialism a humanism?', 'Sartre: yes', 'In choosing, a person chooses not only themselves but an image of how a human ought to be; existentialism is therefore a humanism grounded in responsibility.', 'Heidegger: no', 'In the “Letter on Humanism”, he argues that any approach centring the human re-imprisons the question of being within metaphysics.'],
      ['Facing the absurd', 'Revolt (Camus)', 'The absurd must be neither denied nor closed off by a leap to transcendence; it must be lived with, lucidly.', 'Leap of faith (Kierkegaard)', 'Where reason runs out, the individual can entrust themselves to the transcendent through a commitment without proof.'],
      ['How situated is freedom?', 'Radical freedom', 'Early Sartre: in every circumstance there is a choice; even a person in chains chooses the meaning they give the chains.', 'Situated freedom', 'Beauvoir & Fanon: oppression materially narrows the horizon of choice; ethics must also work to widen it.'],
    ],
  ],
];
$G = $GUIDES[ $topic_slug ] ?? null;

$AC = '#8A6A12';   // accent (altın)
?>
<style>
#tg{ --ac:<?php echo $AC; ?>; --bg:#FBFAF7; --ink:#16150F; --mut:#8A8467; --tx:#56523F; --tx2:#33301F; --ln:#EAE6DC; --ln2:#E4E0D4; background:var(--bg); color:var(--ink); font-family:var(--tls-sans); -webkit-font-smoothing:antialiased; }
#tg *{ box-sizing:border-box; }
#tg .pad{ max-width:1180px; margin:0 auto; padding-left:32px; padding-right:32px; }
#tg .serif{ font-family:var(--tls-serif); font-weight:400; }
#tg a{ color:inherit; text-decoration:none; }
#tg a:hover{ color:var(--ac); }
#tg .hero{ padding:64px 0 34px; }
#tg .eyebrow{ margin:0 0 18px; font-size:12px; letter-spacing:.16em; text-transform:uppercase; color:var(--ac); font-weight:600; }
#tg .h1{ margin:0 0 16px; font-family:var(--tls-serif); font-weight:400; font-size:52px; line-height:1.04; letter-spacing:-.01em; max-width:900px; }
#tg .intro{ max-width:780px; margin:0 0 22px; font-size:18px; line-height:1.7; color:var(--tx); }
#tg .meta{ display:flex; flex-wrap:wrap; gap:10px 20px; font-size:13px; color:var(--mut); }
#tg .navbar{ position:sticky; top:0; z-index:20; background:rgba(251,250,247,.92); backdrop-filter:blur(10px); border-top:1px solid var(--ln); border-bottom:1px solid var(--ln); }
#tg .navwrap{ display:flex; gap:8px; overflow-x:auto; scrollbar-width:none; padding-top:12px; padding-bottom:12px; }
#tg .navwrap::-webkit-scrollbar{ display:none; }
#tg .chip{ flex:0 0 auto; font-size:14px; padding:8px 16px; border-radius:999px; white-space:nowrap; transition:all .18s ease; border:1px solid var(--ln2); background:#fff; color:#45412F; }
#tg .chip.on{ border-color:var(--ac); background:var(--ac); color:#fff; }
#tg main{ padding-bottom:120px; }
#tg section{ padding-top:96px; }
#tg .sechead{ border-bottom:1px solid var(--ln2); padding-bottom:14px; }
#tg .h2{ margin:0; font-family:var(--tls-serif); font-weight:400; font-size:36px; letter-spacing:-.01em; }
#tg .sub{ max-width:720px; margin:22px 0 34px; font-size:17px; line-height:1.7; color:var(--tx); }
#tg .grid3{ display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
#tg .card{ background:#fff; border:1px solid var(--ln); border-radius:10px; padding:24px 24px 26px; transition:transform .18s ease, box-shadow .18s ease; }
#tg .card:hover{ transform:translateY(-2px); box-shadow:0 10px 28px rgba(22,21,15,.07); }
#tg .card h3{ margin:0 0 12px; font-family:var(--tls-serif); font-weight:400; font-size:21px; }
#tg .card h3 .orig{ font-style:italic; color:var(--mut); font-size:16px; }
#tg .card p{ margin:0; font-size:15.5px; line-height:1.66; color:var(--tx); }
#tg .args{ display:flex; flex-direction:column; gap:40px; max-width:860px; }
#tg .arg{ display:grid; grid-template-columns:52px 1fr; gap:24px; }
#tg .arg .num{ font-family:var(--tls-serif); font-size:34px; color:#C9C2AC; line-height:1; }
#tg .arg .body{ border-left:1px solid var(--ln2); padding-left:28px; }
#tg .arg .body h3{ margin:0 0 20px; font-family:var(--tls-serif); font-weight:400; font-size:26px; line-height:1.25; letter-spacing:-.01em; }
#tg .lbl{ margin:0 0 6px; font-size:12px; letter-spacing:.14em; text-transform:uppercase; font-weight:600; }
#tg .lbl.b{ color:var(--ac); } #tg .lbl.g{ color:var(--mut); }
#tg .arg .body p.t{ margin:0 0 22px; font-size:16.5px; line-height:1.72; color:var(--tx2); }
#tg .arg .body p.o{ margin:0; font-size:16.5px; line-height:1.72; color:var(--tx); }
#tg .debates{ display:flex; flex-direction:column; gap:16px; }
#tg .debate{ background:#fff; border:1px solid var(--ln); border-radius:10px; padding:28px 30px; }
#tg .debate .top{ margin:0 0 22px; font-size:12px; letter-spacing:.14em; text-transform:uppercase; color:var(--mut); font-weight:600; }
#tg .drow{ display:grid; grid-template-columns:1fr 56px 1fr; gap:8px; align-items:start; }
#tg .drow h3{ margin:0 0 10px; font-family:var(--tls-serif); font-weight:400; font-size:21px; }
#tg .drow p{ margin:0; font-size:16px; line-height:1.68; color:var(--tx); }
#tg .vs{ justify-self:center; align-self:center; font-size:12px; letter-spacing:.12em; color:var(--ac); border:1px solid #E4DCC2; background:#FAF5E8; border-radius:999px; padding:5px 11px; }
#tg .work{ display:block; background:#fff; border:1px solid var(--ln); border-radius:10px; padding:18px; transition:transform .18s ease, box-shadow .18s ease; }
#tg .work:hover{ transform:translateY(-2px); box-shadow:0 10px 28px rgba(22,21,15,.07); color:inherit; }
#tg .work .cov{ aspect-ratio:3/4; border-radius:6px; border:1px solid var(--ln2); background-size:cover; background-position:center; }
#tg .work .cov.ph{ background:repeating-linear-gradient(135deg,#F5F2E9 0 8px,#F0ECE0 8px 16px); }
#tg .work h3{ margin:16px 0 4px; font-family:var(--tls-serif); font-weight:400; font-size:20px; line-height:1.25; }
#tg .work .by{ margin:0 0 12px; font-size:14px; color:var(--mut); }
#tg .work .nt{ margin:0; font-size:15px; line-height:1.6; color:var(--tx); }
#tg .elist{ border-top:1px solid var(--ln); max-width:900px; }
#tg .erow{ display:grid; grid-template-columns:1fr auto; gap:24px; align-items:baseline; padding:22px 4px; border-bottom:1px solid var(--ln); transition:padding-left .18s ease; }
#tg .erow:hover{ padding-left:12px; color:inherit; }
#tg .erow h3{ margin:0 0 6px; font-family:var(--tls-serif); font-weight:400; font-size:22px; line-height:1.3; }
#tg .erow p{ margin:0; font-size:15px; color:var(--tx); }
#tg .erow .len{ font-size:13px; color:var(--mut); white-space:nowrap; }
#tg .empty{ border:1px dashed #DCD6C6; border-radius:10px; padding:48px 32px; text-align:center; background:#fff; }
#tg .empty h3{ margin:0 0 6px; font-family:var(--tls-serif); font-weight:400; font-size:21px; }
#tg .empty p{ margin:0; font-size:15.5px; color:var(--mut); }
#tg .cta{ margin-top:16px; background:#F5F2E9; border:1px solid var(--ln2); border-radius:12px; padding:44px; display:flex; flex-wrap:wrap; gap:28px; align-items:center; justify-content:space-between; }
#tg .cta h2{ margin:0 0 10px; font-family:var(--tls-serif); font-weight:400; font-size:28px; letter-spacing:-.01em; }
#tg .cta p{ margin:0; max-width:560px; font-size:16.5px; line-height:1.68; color:var(--tx); }
#tg .cta a{ background:var(--ink); color:var(--bg); font-size:15px; font-weight:500; padding:14px 26px; border-radius:999px; white-space:nowrap; }
#tg .cta a:hover{ background:var(--ac); color:var(--bg); }
#tg .foot{ border-top:1px solid var(--ln); background:#fff; }
#tg .foot .in{ display:flex; flex-wrap:wrap; gap:16px; justify-content:space-between; padding:36px 32px; font-size:14px; color:var(--mut); }
html{ scroll-behavior:smooth; scroll-padding-top:80px; }
@media(max-width:760px){
  #tg .pad{ padding-left:20px; padding-right:20px; }
  #tg .h1{ font-size:38px; } #tg .h2{ font-size:28px; }
  #tg .grid3{ grid-template-columns:1fr; }
  #tg .drow{ grid-template-columns:1fr; } #tg .vs{ justify-self:start; }
  #tg .arg{ grid-template-columns:34px 1fr; gap:12px; } #tg .arg .num{ font-size:26px; } #tg .arg .body{ padding-left:16px; }
}
</style>

<div id="tg">
<?php if ( ! $G ) : ?>
  <div class="pad hero"><h1 class="h1 serif">Topic Guide</h1></div>
<?php else :
  $secs = [ ['concepts','Key Concepts'], ['arguments','Major Arguments'], ['debates','Debates'], ['works','Essential Works'], ['essays','Essays'] ];
?>

  <!-- HERO -->
  <section class="pad hero" id="tg-top">
    <p class="eyebrow">Topic Guide</p>
    <h1 class="h1"><?php echo esc_html( $G['label'] ); ?></h1>
    <p class="intro"><?php echo esc_html( $G['intro'] ); ?></p>
    <div class="meta">
      <span><?php echo count( $secs ); ?> sections</span><span>·</span>
      <span>~<?php echo (int) $G['read']; ?> min read</span><span>·</span>
      <span>Updated <?php echo esc_html( $G['updated'] ); ?></span>
    </div>
  </section>

  <!-- STICKY SCROLL-SPY NAV -->
  <nav class="navbar" aria-label="Sections">
    <div class="pad navwrap" id="tg-nav">
      <?php foreach ( $secs as $i => $s ) : ?>
        <a class="chip<?php echo $i === 0 ? ' on' : ''; ?>" href="#<?php echo $s[0]; ?>" data-chip="<?php echo $s[0]; ?>"><?php echo esc_html( $s[1] ); ?></a>
      <?php endforeach; ?>
    </div>
  </nav>

  <main class="pad">

    <!-- KEY CONCEPTS -->
    <section id="concepts">
      <div class="sechead"><h2 class="h2">Key Concepts</h2></div>
      <p class="sub">Short definitions of the terms that recur across the texts.</p>
      <div class="grid3">
        <?php foreach ( $G['concepts'] as $c ) : ?>
          <div class="card">
            <h3><?php echo esc_html( $c[0] ); ?><?php if ( $c[1] ) : ?> <span class="orig">(<?php echo esc_html( $c[1] ); ?>)</span><?php endif; ?></h3>
            <p><?php echo esc_html( $c[2] ); ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- MAJOR ARGUMENTS -->
    <section id="arguments">
      <div class="sechead"><h2 class="h2">Major Arguments</h2></div>
      <p class="sub">Each argument in three steps: the claim, how it is built, and the objections to it.</p>
      <div class="args">
        <?php foreach ( $G['arguments'] as $i => $a ) : ?>
          <article class="arg">
            <div class="num"><?php echo sprintf( '%02d', $i + 1 ); ?></div>
            <div class="body">
              <h3><?php echo esc_html( $a[0] ); ?></h3>
              <p class="lbl b">How it is built</p>
              <p class="t"><?php echo esc_html( $a[1] ); ?></p>
              <p class="lbl g">Objections</p>
              <p class="o"><?php echo esc_html( $a[2] ); ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- DEBATES -->
    <section id="debates">
      <div class="sechead"><h2 class="h2">Debates</h2></div>
      <p class="sub">Open tensions — within the movement and against it.</p>
      <div class="debates">
        <?php foreach ( $G['debates'] as $d ) : ?>
          <div class="debate">
            <p class="top"><?php echo esc_html( $d[0] ); ?></p>
            <div class="drow">
              <div><h3><?php echo esc_html( $d[1] ); ?></h3><p><?php echo esc_html( $d[2] ); ?></p></div>
              <div class="vs">VS</div>
              <div><h3><?php echo esc_html( $d[3] ); ?></h3><p><?php echo esc_html( $d[4] ); ?></p></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ESSENTIAL WORKS — kategoriden OTOMATİK -->
    <section id="works">
      <div class="sechead"><h2 class="h2">Essential Works</h2></div>
      <p class="sub">Start here — every book’s summary is on the site.</p>
      <?php
      $wq = new WP_Query( [ 'post_type'=>'post', 'category_name'=>$G['cat'], 'posts_per_page'=>9, 'orderby'=>'date', 'order'=>'ASC', 'no_found_rows'=>true ] );
      if ( $wq->have_posts() ) : ?>
        <div class="grid3">
          <?php while ( $wq->have_posts() ) : $wq->the_post();
            $cov = get_the_post_thumbnail_url( get_the_ID(), 'medium' );
            $au  = wp_get_post_terms( get_the_ID(), 'authors', ['fields'=>'names'] );
            $auname = ( $au && ! is_wp_error( $au ) ) ? $au[0] : '';
            ?>
            <a class="work" href="<?php the_permalink(); ?>">
              <div class="cov<?php echo $cov ? '' : ' ph'; ?>"<?php echo $cov ? ' style="background-image:url(\'' . esc_url( $cov ) . '\')"' : ''; ?>></div>
              <h3><?php echo esc_html( get_the_title() ); ?></h3>
              <?php if ( $auname ) : ?><p class="by"><?php echo esc_html( $auname ); ?></p><?php endif; ?>
              <p class="nt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
            </a>
          <?php endwhile; ?>
        </div>
        <p style="margin-top:16px"><a href="<?php echo esc_url( home_url( '/category/' . $G['cat'] . '/' ) ); ?>" style="color:var(--ac);font-size:14px">All <?php echo esc_html( $G['label'] ); ?> summaries →</a></p>
      <?php else : ?>
        <div class="empty"><h3>No summaries yet</h3><p>Book summaries in this category will appear here as they are added.</p></div>
      <?php endif; wp_reset_postdata(); ?>
    </section>

    <!-- ESSAYS — essay tipi eklenince OTOMATİK -->
    <section id="essays">
      <div class="sechead"><h2 class="h2">Essays</h2></div>
      <p class="sub">Long-form editorial pieces by TheTelos writers on this topic.</p>
      <?php
      $rows = [];
      if ( post_type_exists( 'tls_essay' ) ) {
        $eq = new WP_Query( [ 'post_type'=>'tls_essay', 'posts_per_page'=>10, 'no_found_rows'=>true,
          'tax_query'=>[ ['taxonomy'=>'category','field'=>'slug','terms'=>$G['cat']] ] ] );
        while ( $eq->have_posts() ) { $eq->the_post();
          $mins = max( 1, (int) round( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 220 ) );
          $rows[] = [ get_permalink(), get_the_title(), get_the_author(), $mins . ' min' ];
        }
        wp_reset_postdata();
      }
      if ( $rows ) : ?>
        <div class="elist">
          <?php foreach ( $rows as $e ) : ?>
            <a class="erow" href="<?php echo esc_url( $e[0] ); ?>">
              <div><h3><?php echo esc_html( $e[1] ); ?></h3><p><?php echo esc_html( $e[2] ); ?></p></div>
              <span class="len"><?php echo esc_html( $e[3] ); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else : ?>
        <div class="empty"><h3>No essays yet</h3><p>Original essays on this topic will be listed here as they are published.</p></div>
      <?php endif; ?>
    </section>

    <!-- NEXT GUIDE CTA -->
    <section style="padding-top:112px">
      <div class="cta">
        <div>
          <h2>Next guide: <?php echo esc_html( $G['next'] ); ?></h2>
          <p>Same skeleton, new topic. Be notified when new guides are published.</p>
        </div>
        <a href="<?php echo esc_url( home_url( '/category/' . $G['cat'] . '/' ) ); ?>">← Back to <?php echo esc_html( $G['label'] ); ?></a>
      </div>
    </section>

  </main>

  <footer class="foot">
    <div class="pad in">
      <span class="serif" style="font-size:20px;color:var(--ink)">thetelos</span>
      <span>Topic Guides · <?php echo date( 'Y' ); ?></span>
    </div>
  </footer>

  <script>
  (function(){
    var chips = document.querySelectorAll('#tg-nav .chip');
    var ids = ['concepts','arguments','debates','works','essays'];
    if(!('IntersectionObserver' in window)) return;
    var obs = new IntersectionObserver(function(entries){
      var vis = entries.filter(function(e){return e.isIntersecting;})
        .sort(function(a,b){return a.boundingClientRect.top - b.boundingClientRect.top;});
      if(vis.length){
        var id = vis[0].target.id;
        chips.forEach(function(c){ c.classList.toggle('on', c.getAttribute('data-chip')===id); });
      }
    }, { rootMargin:'-140px 0px -60% 0px', threshold:0 });
    ids.forEach(function(id){ var el=document.getElementById(id); if(el) obs.observe(el); });
  })();
  </script>

<?php endif; ?>
</div>

<?php get_footer(); ?>
