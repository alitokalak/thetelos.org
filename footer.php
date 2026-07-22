<?php
/**
 * The Telos — Footer Template
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>

</div><!-- /.site-content -->

<!-- ══════════════════════════════════
     THE TELOS FOOTER
══════════════════════════════════ -->
<footer class="tls-footer" role="contentinfo">
    <div class="container">
        <div class="tls-footer-grid">

            <!-- Brand column -->
            <div>
                <a class="tls-footer-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <?php bloginfo( 'name' ); ?>
                </a>
                <p class="tls-footer-tagline">
                    <?php echo esc_html( get_bloginfo( 'description' ) ?: 'Preserving human knowledge for the digital age. A nonprofit initiative for open scholarship.' ); ?>
                </p>
            </div>

            <!-- Institutional -->
            <div>
                <div class="tls-footer-col-label">Institutional</div>
                <ul class="tls-footer-links">
                    <li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>">Terms of Use</a></li>
                </ul>
            </div>

            <!-- Archive -->
            <div>
                <div class="tls-footer-col-label">Archive</div>
                <ul class="tls-footer-links">
                    <?php
                    $footer_cats = get_categories( [
                        'orderby'  => 'count',
                        'order'    => 'DESC',
                        'number'   => 5,
                        'hide_empty' => true,
                    ] );
                    foreach ( $footer_cats as $fc ) :
                    ?>
                        <li>
                            <a href="<?php echo esc_url( get_category_link( $fc->term_id ) ); ?>">
                                <?php echo esc_html( $fc->name ); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Newsletter -->
            <div>
                <div class="tls-footer-col-label">The Newsletter</div>
                <p style="font-family:var(--tls-sans);font-size:13px;color:rgba(255,255,255,.4);line-height:1.55;margin-bottom:16px;">
                    Weekly summaries from the archive, curated for the modern scholar.
                </p>
                <?php if ( shortcode_exists( 'mc4wp_form' ) ) : ?>
                    <?php echo do_shortcode( '[mc4wp_form]' ); ?>
                <?php else : ?>
                <div class="tls-footer-newsletter">
                    <input type="email" id="tls-nl-email" placeholder="your@email.com" aria-label="Email address">
                    <button type="button" id="tls-nl-btn">Subscribe &rarr;</button>
                </div>
                <div id="tls-nl-msg" style="font-size:12px;margin-top:6px;min-height:16px;"></div>
                <?php endif; ?>
            </div>

        </div><!-- /.tls-footer-grid -->

        <div class="tls-footer-bottom">
            <p class="tls-footer-copy">
                <?php echo wp_kses_data( get_theme_mod( 'footer_textleft', '&copy; ' . date( 'Y' ) . ' ' . get_bloginfo( 'name' ) . '. All rights reserved.' ) ); ?>
            </p>
            <a href="#" class="tls-back-top" aria-label="Back to top">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
                Back to top
            </a>
        </div>

    </div><!-- /.container -->
</footer>

<?php wp_footer(); ?>

<!-- ══════════════════════════════════
     QUOTE SHARE POPUP
══════════════════════════════════ -->
<div id="tls-qshare-overlay" style="display:none;" role="dialog" aria-modal="true">
    <div id="tls-qshare-modal">
        <button id="tls-qshare-close" aria-label="Close">&times;</button>

        <!-- Önizleme kartı -->
        <div id="tls-qshare-card">
            <div class="tls-qsc-mark">&ldquo;</div>
            <p class="tls-qsc-text" id="tls-qsc-text"></p>
            <div class="tls-qsc-meta">
                <span class="tls-qsc-author" id="tls-qsc-author"></span>
                <span class="tls-qsc-sep" id="tls-qsc-sep"> — </span>
                <span class="tls-qsc-book" id="tls-qsc-book"></span>
            </div>
            <div class="tls-qsc-brand">thetelos.org</div>
        </div>

        <!-- Paylaşım butonları -->
        <div id="tls-qshare-actions">
            <p class="tls-qshare-label">Share this quote</p>
            <div class="tls-qshare-btns">
                <a id="tls-qsh-tw" class="tls-qsh-btn tls-qsh-tw" href="#" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    Post on X
                </a>
                <a id="tls-qsh-wa" class="tls-qsh-btn tls-qsh-wa" href="#" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    WhatsApp
                </a>
                <button id="tls-qsh-copy" class="tls-qsh-btn tls-qsh-copy" type="button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                    <span id="tls-copy-label">Copy Text</span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* ── Quote Share Overlay ── */
#tls-qshare-overlay {
    position:fixed;inset:0;z-index:99998;
    background:rgba(20,18,14,.7);
    backdrop-filter:blur(4px);
    align-items:center;justify-content:center;padding:16px;
}
#tls-qshare-modal {
    background:#fff;border-radius:14px;
    width:100%;max-width:460px;
    padding:20px 28px 24px;
    position:relative;
    box-shadow:0 24px 64px rgba(0,0,0,.25);
}
#tls-qshare-close {
    display:block;
    margin-left:auto;
    margin-bottom:14px;
    background:#f4f4f4;border:none;
    width:32px;height:32px;
    border-radius:50%;
    font-size:18px;line-height:1;
    color:#666;cursor:pointer;
    transition:background .15s,color .15s;
}
#tls-qshare-close:hover{background:#e8e8e8;color:#333;}

/* ── Quote card preview ── */
#tls-qshare-card {
    background:var(--tls-bg-dark,#1a1714);
    border-radius:10px;
    padding:28px 24px 20px;
    margin-bottom:22px;
    position:relative;
    overflow:hidden;
}
#tls-qshare-card::before {
    content:'';position:absolute;inset:6px;
    border:1px solid rgba(200,161,101,.2);
    border-radius:6px;pointer-events:none;
}
.tls-qsc-mark {
    font-family:Georgia,serif;font-size:52px;line-height:.8;
    color:var(--tls-gold,#C8A165);opacity:.4;
    margin-bottom:6px;
}
.tls-qsc-text {
    font-family:Georgia,serif;font-style:italic;
    font-size:15px;line-height:1.7;color:#fff;
    margin:0 0 14px;
}
.tls-qsc-meta {
    font-family:sans-serif;font-size:12px;
    color:rgba(255,255,255,.45);
}
.tls-qsc-author{font-weight:600;color:rgba(255,255,255,.65);}
.tls-qsc-brand {
    margin-top:14px;
    font-family:Georgia,serif;font-size:11px;
    color:var(--tls-gold,#C8A165);opacity:.6;
    letter-spacing:.1em;
}

/* ── Share buttons ── */
.tls-qshare-label {
    font-family:sans-serif;font-size:11px;
    font-weight:700;letter-spacing:.1em;
    text-transform:uppercase;color:#aaa;
    margin:0 0 10px;
}
.tls-qshare-btns{display:flex;gap:8px;flex-wrap:wrap;}
.tls-qsh-btn {
    display:inline-flex;align-items:center;gap:6px;
    padding:10px 16px;border-radius:8px;
    font-family:sans-serif;font-size:12px;font-weight:600;
    cursor:pointer;border:none;text-decoration:none!important;
    transition:opacity .15s,transform .1s;
}
.tls-qsh-btn:hover{opacity:.85;transform:translateY(-1px);}
.tls-qsh-tw   {background:#000;color:#fff!important;}
.tls-qsh-wa   {background:#25D366;color:#fff!important;}
.tls-qsh-copy {background:#f4f1ec;color:#1a1714!important;}
</style>

<script>
function tlsOpenQuoteShare(btn) {
    var card   = btn.closest('.tls-quote-card');
    var qtext  = card.dataset.quote  || '';
    var author = card.dataset.author || '';
    var book   = card.dataset.book   || '';
    var url    = window.location.href;

    document.getElementById('tls-qsc-text').textContent   = qtext;
    document.getElementById('tls-qsc-author').textContent = author;
    document.getElementById('tls-qsc-book').textContent   = book;
    document.getElementById('tls-qsc-sep').style.display  = (author && book) ? '' : 'none';

    /* X / Twitter */
    var twText = '\u201c' + qtext.slice(0,200) + '\u201d';
    if (author) twText += ' \u2014 ' + author;
    twText += ' | thetelos.org';
    document.getElementById('tls-qsh-tw').href =
        'https://twitter.com/intent/tweet?text=' + encodeURIComponent(twText) + '&url=' + encodeURIComponent(url);

    /* WhatsApp */
    var waText = '\u201c' + qtext + '\u201d';
    if (author) waText += '\n\u2014 ' + author;
    if (book)   waText += ', ' + book;
    waText += '\n\n' + url;
    document.getElementById('tls-qsh-wa').href =
        'https://api.whatsapp.com/send?text=' + encodeURIComponent(waText);

    /* Copy */
    document.getElementById('tls-qsh-copy').onclick = function(){
        var copyText = '\u201c' + qtext + '\u201d';
        if (author) copyText += '\n\u2014 ' + author;
        if (book)   copyText += ', ' + book;
        copyText += '\n\n' + url;
        navigator.clipboard.writeText(copyText).then(function(){
            document.getElementById('tls-copy-label').textContent = 'Copied!';
            setTimeout(function(){
                document.getElementById('tls-copy-label').textContent = 'Copy Text';
            }, 2000);
        });
    };

    var overlay = document.getElementById('tls-qshare-overlay');
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

(function(){
    var overlay  = document.getElementById('tls-qshare-overlay');
    var closeBtn = document.getElementById('tls-qshare-close');
    if (!overlay) return;
    function closeQShare(){
        overlay.style.display='none';
        document.body.style.overflow='';
    }
    if (closeBtn) closeBtn.addEventListener('click', closeQShare);
    overlay.addEventListener('click', function(e){ if(e.target===overlay) closeQShare(); });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeQShare(); });
})();
</script>

<!-- ══════════════════════════════════
     SUMMARY REQUEST POPUP
══════════════════════════════════ -->
<?php
$req_nonce = wp_create_nonce('tls_req_nonce');
$req_ajax  = admin_url('admin-ajax.php');
?>
<div id="tls-req-overlay" style="display:none;" role="dialog" aria-modal="true" aria-label="Request a Summary">
    <div id="tls-req-modal">

        <button id="tls-req-close" aria-label="Close">&times;</button>

        <div id="tls-req-body">
            <div class="tls-req-head">
                <div class="tls-req-head-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="22" height="22"><path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                </div>
                <div>
                    <h2 class="tls-req-title">Request a Summary</h2>
                    <p class="tls-req-sub">Tell us which book you'd like added to the archive.</p>
                </div>
            </div>

            <div id="tls-req-error" class="tls-req-error" style="display:none;"></div>

            <div class="tls-req-fields">
                <div class="tls-req-row2">
                    <div class="tls-req-field">
                        <label class="tls-req-label">Name <span>*</span></label>
                        <input class="tls-req-input" type="text" id="tls-f-name" placeholder="Your name" autocomplete="name">
                    </div>
                    <div class="tls-req-field">
                        <label class="tls-req-label">Email <span>*</span></label>
                        <input class="tls-req-input" type="email" id="tls-f-email" placeholder="you@example.com" autocomplete="email">
                    </div>
                </div>
                <div class="tls-req-row2">
                    <div class="tls-req-field">
                        <label class="tls-req-label">Author <span>*</span></label>
                        <input class="tls-req-input" type="text" id="tls-f-author" placeholder="e.g. Marcus Aurelius">
                    </div>
                    <div class="tls-req-field">
                        <label class="tls-req-label">Book Title <span>*</span></label>
                        <input class="tls-req-input" type="text" id="tls-f-book" placeholder="e.g. Meditations">
                    </div>
                </div>
                <!-- Honeypot — botlar için tuzak -->
                <div style="position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden;" aria-hidden="true">
                    <input type="text" name="tls_hp" id="tls-f-hp" tabindex="-1" autocomplete="off">
                </div>
            </div>

            <div class="tls-req-actions">
                <p class="tls-req-privacy">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    Your info is private and used only for this request.
                </p>
                <button class="tls-req-submit" id="tls-req-submit" type="button">
                    <span class="tls-req-btn-txt">Submit Request</span>
                    <span class="tls-req-btn-spin" style="display:none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15" style="animation:tlsSpin .7s linear infinite;"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                        Sending…
                    </span>
                </button>
            </div>
        </div><!-- /#tls-req-body -->

        <div id="tls-req-success" style="display:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="52" height="52" style="color:var(--tls-green);"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10" stroke-width="2"/></svg>
            <h3>Request Received!</h3>
            <p>Thank you. We've added your request to our reading list and will notify you when the summary goes live.</p>
            <button class="tls-req-submit" id="tls-req-done" type="button" style="margin-top:8px;">Close</button>
        </div>

    </div><!-- /#tls-req-modal -->
</div><!-- /#tls-req-overlay -->

<style>
/* ── Overlay ── */
#tls-req-overlay {
    position: fixed; inset: 0; z-index: 99999;
    background: rgba(20,18,14,.65);
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
    padding: 16px;
}
/* JS display:none / display:flex ile kontrol eder — CSS override yok */

/* ── Modal ── */
#tls-req-modal {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 580px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 36px 36px 32px;
    position: relative;
    box-shadow: 0 24px 64px rgba(0,0,0,.22);
}
@media (max-width:600px) {
    #tls-req-modal { padding: 28px 20px 24px; }
}

/* ── Close ── */
#tls-req-close {
    position: absolute; top: 16px; right: 18px;
    background: none; border: none;
    font-size: 22px; line-height: 1;
    color: #aaa; cursor: pointer;
    padding: 4px 8px; border-radius: 6px;
    transition: color .15s, background .15s;
}
#tls-req-close:hover { color: #333; background: #f4f4f4; }

/* ── Head ── */
.tls-req-head {
    display: flex; gap: 14px; align-items: flex-start;
    margin-bottom: 28px;
}
.tls-req-head-icon {
    flex-shrink: 0; width: 44px; height: 44px;
    background: var(--tls-bg, #f5efe6);
    border: 1px solid var(--tls-border, #e8e0d4);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: var(--tls-gold, #C8A165);
}
.tls-req-title {
    font-family: var(--tls-serif, Georgia, serif);
    font-size: 22px; font-weight: 400;
    color: var(--tls-bg-dark, #1a1714);
    margin: 0 0 4px; line-height: 1.2;
}
.tls-req-sub {
    font-family: var(--tls-sans, sans-serif);
    font-size: 13px; color: #888;
    margin: 0; line-height: 1.5;
}

/* ── Fields ── */
.tls-req-fields { display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px; }
.tls-req-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 500px) { .tls-req-row2 { grid-template-columns: 1fr; } }
.tls-req-field { display: flex; flex-direction: column; gap: 5px; }
.tls-req-label {
    font-family: var(--tls-sans, sans-serif);
    font-size: 11px; font-weight: 700;
    letter-spacing: .08em; text-transform: uppercase;
    color: var(--tls-bg-dark, #1a1714);
}
.tls-req-label span { color: var(--tls-gold, #C8A165); }
.tls-req-input {
    font-family: var(--tls-sans, sans-serif);
    font-size: 14px; color: #1a1714;
    background: #faf9f7;
    border: 1.5px solid #e4ddd3;
    border-radius: 8px;
    padding: 10px 14px;
    width: 100%; box-sizing: border-box;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
    -webkit-appearance: none;
}
.tls-req-input::placeholder { color: #ccc; }
.tls-req-input:focus {
    border-color: var(--tls-gold, #C8A165);
    box-shadow: 0 0 0 3px rgba(200,161,101,.13);
    background: #fff;
}

/* ── Actions ── */
.tls-req-actions {
    display: flex; align-items: center;
    justify-content: space-between;
    flex-wrap: wrap; gap: 12px;
}
.tls-req-privacy {
    font-family: var(--tls-sans, sans-serif);
    font-size: 11px; color: #aaa;
    margin: 0; display: flex; align-items: center; gap: 4px;
}
.tls-req-submit {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 12px 28px;
    background: var(--tls-bg-dark, #1a1714);
    color: #fff; border: none; border-radius: 8px;
    font-family: var(--tls-sans, sans-serif);
    font-size: 12px; font-weight: 700;
    letter-spacing: .08em; text-transform: uppercase;
    cursor: pointer;
    transition: background .2s, transform .1s;
}
.tls-req-submit:hover:not(:disabled) { background: #2a2620; }
.tls-req-submit:active:not(:disabled) { transform: scale(.97); }
.tls-req-submit:disabled { opacity: .55; cursor: not-allowed; }

/* ── Error ── */
.tls-req-error {
    padding: 10px 14px;
    background: #fff5f5; border: 1px solid #fca5a5;
    border-radius: 8px; margin-bottom: 16px;
    font-family: var(--tls-sans, sans-serif);
    font-size: 13px; color: #b91c1c;
}

/* ── Success ── */
#tls-req-success {
    display: flex; flex-direction: column;
    align-items: center; text-align: center;
    padding: 32px 16px; gap: 10px;
}
#tls-req-success h3 {
    font-family: var(--tls-serif, Georgia, serif);
    font-size: 22px; font-weight: 400;
    color: var(--tls-bg-dark, #1a1714); margin: 0;
}
#tls-req-success p {
    font-family: var(--tls-sans, sans-serif);
    font-size: 14px; color: #888;
    margin: 0; line-height: 1.6; max-width: 360px;
}

@keyframes tlsSpin { to { transform: rotate(360deg); } }
</style>

<script>
(function(){
    var overlay  = document.getElementById('tls-req-overlay');
    var openBtn  = document.getElementById('tls-open-request');
    var closeBtn = document.getElementById('tls-req-close');
    var doneBtn  = document.getElementById('tls-req-done');
    var submitBtn= document.getElementById('tls-req-submit');
    var errBox   = document.getElementById('tls-req-error');
    var body     = document.getElementById('tls-req-body');
    var succ     = document.getElementById('tls-req-success');
    var loadTime = Math.floor(Date.now()/1000);

    if (!overlay) return;

    /* Aç */
    function openModal() {
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        /* Runtime'da auth bilgisini al — localize gecikmesini aşmak için */
        var auth = window.tlsAuth || {};
        var nameEl  = document.getElementById('tls-f-name');
        var emailEl = document.getElementById('tls-f-email');

        if (auth.isLoggedIn && auth.user && nameEl && emailEl) {
            nameEl.value     = auth.user.name  || '';
            emailEl.value    = auth.user.email || '';
            nameEl.readOnly  = true;
            emailEl.readOnly = true;
            nameEl.style.background  = '#f5f5f5';
            emailEl.style.background = '#f5f5f5';
            nameEl.style.color       = '#888';
            emailEl.style.color      = '#888';
            setTimeout(function(){ document.getElementById('tls-f-author').focus(); }, 100);
        } else {
            if (nameEl)  { nameEl.readOnly  = false; nameEl.style.background  = ''; nameEl.style.color  = ''; }
            if (emailEl) { emailEl.readOnly = false; emailEl.style.background = ''; emailEl.style.color = ''; }
            setTimeout(function(){ if(nameEl) nameEl.focus(); }, 100);
        }
    }
    /* Kapat */
    function closeModal() {
        overlay.style.display = 'none';
        document.body.style.overflow = '';
        /* Formu sıfırla */
        setTimeout(function(){
            body.style.display = '';
            succ.style.display = 'none';
            errBox.style.display = 'none';
            ['tls-f-name','tls-f-email','tls-f-author','tls-f-book'].forEach(function(id){
                var el = document.getElementById(id);
                if (el) { el.value = ''; el.readOnly = false; el.style.background = ''; }
            });
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.querySelector('.tls-req-btn-txt').style.display  = 'inline';
                submitBtn.querySelector('.tls-req-btn-spin').style.display = 'none';
            }
        }, 300);
    }

    if (openBtn)  openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (doneBtn)  doneBtn.addEventListener('click', closeModal);

    /* Overlay dışına tıklayınca kapat */
    overlay.addEventListener('click', function(e){
        if (e.target === overlay) closeModal();
    });
    /* ESC ile kapat */
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closeModal();
    });

    /* ── Gönder ── */
    if (submitBtn) submitBtn.addEventListener('click', function(){
        var name   = document.getElementById('tls-f-name').value.trim();
        var email  = document.getElementById('tls-f-email').value.trim();
        var author = document.getElementById('tls-f-author').value.trim();
        var book   = document.getElementById('tls-f-book').value.trim();
        var hp     = document.getElementById('tls-f-hp').value;

        errBox.style.display = 'none';

        if (!name || !email || !author || !book) {
            errBox.textContent = 'Please fill in all fields.';
            errBox.style.display = 'block';
            return;
        }
        var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRe.test(email)) {
            errBox.textContent = 'Please enter a valid email address.';
            errBox.style.display = 'block';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.querySelector('.tls-req-btn-txt').style.display  = 'none';
        submitBtn.querySelector('.tls-req-btn-spin').style.display = 'inline-flex';

        var fd = new FormData();
        fd.append('action',     'tls_submit_req');
        fd.append('nonce',      '<?php echo esc_js($req_nonce); ?>');
        fd.append('tls_name',   name);
        fd.append('tls_email',  email);
        fd.append('tls_author', author);
        fd.append('tls_book',   book);
        fd.append('tls_hp',     hp);
        fd.append('tls_time',   loadTime);

        fetch('<?php echo esc_js($req_ajax); ?>', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success) {
                body.style.display = 'none';
                succ.style.display = 'flex';
            } else {
                errBox.textContent = (res.data && res.data.message) ? res.data.message : 'Something went wrong.';
                errBox.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.querySelector('.tls-req-btn-txt').style.display  = 'inline';
                submitBtn.querySelector('.tls-req-btn-spin').style.display = 'none';
            }
        })
        .catch(function(){
            errBox.textContent = 'Network error. Please try again.';
            errBox.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.querySelector('.tls-req-btn-txt').style.display  = 'inline';
            submitBtn.querySelector('.tls-req-btn-spin').style.display = 'none';
        });
    });
})();
</script>


<?php
$is_logged_in = is_user_logged_in();
$current_user = $is_logged_in ? wp_get_current_user() : null;
$auth_nonce   = wp_create_nonce('tls_auth_nonce');
$status_nonce = wp_create_nonce('tls_status_nonce');
$ajax_url     = admin_url('admin-ajax.php');
?>

<!-- ══════════════════════════════════
     AUTH POPUP (Login / Register)
══════════════════════════════════ -->
<?php if ( ! $is_logged_in ) : ?>
<div id="tls-auth-overlay" style="display:none;" role="dialog" aria-modal="true" aria-label="Sign In">
    <div id="tls-auth-modal">
        <button id="tls-auth-close" aria-label="Close">&times;</button>

        <div class="tls-auth-brand">
            <p class="tls-auth-logo"><?php bloginfo('name'); ?></p>
            <p class="tls-auth-tagline">Join the archive. Track your reading.</p>
        </div>

        <div class="tls-auth-tabs">
            <button class="tls-auth-tab tls-auth-tab--active" data-tab="login">Sign In</button>
            <button class="tls-auth-tab" data-tab="register">Create Account</button>
        </div>

        <div id="tls-auth-msg" style="display:none;"></div>

        <!-- LOGIN -->
        <div class="tls-auth-form" id="tls-tab-login">
            <div class="tls-auth-field">
                <label class="tls-auth-label">Email</label>
                <input class="tls-auth-input" type="email" id="tls-login-email" placeholder="you@example.com" autocomplete="email">
            </div>
            <div class="tls-auth-field">
                <label class="tls-auth-label">Password</label>
                <input class="tls-auth-input" type="password" id="tls-login-pass" placeholder="••••••••" autocomplete="current-password">
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:-4px;">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--tls-muted);cursor:pointer;">
                    <input type="checkbox" id="tls-login-rem"> Remember me
                </label>
                <button type="button" class="tls-auth-textbtn" id="tls-show-forgot">Forgot password?</button>
            </div>
            <button class="tls-auth-submit" id="tls-btn-login" type="button">Sign In</button>
        </div>

        <!-- REGISTER -->
        <div class="tls-auth-form" id="tls-tab-register" style="display:none;">
            <div class="tls-auth-field">
                <label class="tls-auth-label">Your Name</label>
                <input class="tls-auth-input" type="text" id="tls-reg-name" placeholder="Marcus Aurelius" autocomplete="name">
            </div>
            <div class="tls-auth-field">
                <label class="tls-auth-label">Email</label>
                <input class="tls-auth-input" type="email" id="tls-reg-email" placeholder="you@example.com" autocomplete="email">
            </div>
            <div class="tls-auth-field">
                <label class="tls-auth-label">Password <span style="color:#aaa;font-weight:400;">(min. 6 chars)</span></label>
                <input class="tls-auth-input" type="password" id="tls-reg-pass" placeholder="••••••••" autocomplete="new-password">
            </div>
            <div style="position:absolute;left:-9999px;opacity:0;height:0;" aria-hidden="true">
                <input type="text" id="tls-reg-hp" tabindex="-1" autocomplete="off">
            </div>
            <button class="tls-auth-submit" id="tls-btn-register" type="button">Create Account</button>
            <p style="font-size:11px;color:var(--tls-muted);text-align:center;margin:0;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11" style="vertical-align:middle;"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                Your data is private and never shared.
            </p>
        </div>

        <!-- FORGOT -->
        <div class="tls-auth-form" id="tls-tab-forgot" style="display:none;">
            <p style="font-size:14px;color:var(--tls-muted);margin:0 0 16px;line-height:1.6;">Enter your email to receive a password reset link.</p>
            <div class="tls-auth-field">
                <label class="tls-auth-label">Email</label>
                <input class="tls-auth-input" type="email" id="tls-forgot-email" placeholder="you@example.com">
            </div>
            <button class="tls-auth-submit" id="tls-btn-forgot" type="button">Send Reset Link</button>
            <button type="button" class="tls-auth-textbtn" id="tls-back-login" style="display:block;text-align:center;margin-top:10px;">← Back to Sign In</button>
        </div>

        <!-- INTERESTS (kayıt sonrası adım) -->
        <div class="tls-auth-form" id="tls-tab-interests" style="display:none;">
            <p style="font-size:15px;font-weight:600;color:var(--tls-bg-dark);margin:0 0 4px;text-align:center;">Welcome aboard! 🎉</p>
            <p class="tls-int-help" style="text-align:center;margin-bottom:14px;">Pick a few areas you love — we'll tune your recommendations and weekly digest.</p>
            <div style="max-height:44vh;overflow-y:auto;margin:0 -4px;padding:2px 4px;">
                <?php echo tls_render_interest_grid( [] ); ?>
            </div>
            <button class="tls-auth-submit" id="tls-btn-interests" type="button" style="margin-top:16px;">Continue &rarr;</button>
            <button type="button" class="tls-auth-textbtn" id="tls-skip-interests" style="display:block;text-align:center;margin-top:10px;">Skip for now</button>
        </div>

    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════
     PROFILE PANEL (Giriş yapmışsa)
══════════════════════════════════ -->
<?php if ( $is_logged_in ) :
    $avatar = get_avatar_url($current_user->ID, ['size'=>80]);
    $initials = strtoupper(substr($current_user->display_name,0,1));
    $rating_count = 0; // istatistik için sonra hesaplanır
    global $wpdb;
    $status_counts = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_value, COUNT(*) as cnt FROM {$wpdb->usermeta}
         WHERE user_id=%d AND meta_key LIKE '_tls_reading_status_%%'
         GROUP BY meta_value", $current_user->ID
    ), OBJECT_K);
    $cnt_want    = $status_counts['want']->cnt    ?? 0;
    $cnt_reading = $status_counts['reading']->cnt ?? 0;
    $cnt_read    = $status_counts['read']->cnt    ?? 0;
    $cnt_total   = $cnt_want + $cnt_reading + $cnt_read;
?>
<div id="tls-lib-overlay" style="display:none;" role="dialog" aria-modal="true">
    <div id="tls-lib-modal">

        <!-- ── Header ── -->
        <div id="tls-lib-header">
            <div class="tls-lib-profile-info">
                <div class="tls-lib-avatar-wrap">
                    <?php if ($avatar) : ?>
                        <img src="<?php echo esc_url($avatar); ?>" alt="" class="tls-lib-avatar-img" id="tls-lib-avatar-img">
                    <?php else : ?>
                        <div class="tls-lib-avatar"><?php echo esc_html($initials); ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="tls-lib-name"><?php echo esc_html($current_user->display_name); ?></div>
                    <div class="tls-lib-email"><?php echo esc_html($current_user->user_email); ?></div>
                    <div class="tls-lib-stats">
                        <span class="tls-lib-stat"><strong><?php echo $cnt_total; ?></strong> books</span>
                        <span class="tls-lib-stat"><strong><?php echo $cnt_read; ?></strong> finished</span>
                        <span class="tls-lib-stat"><strong><?php echo $cnt_reading; ?></strong> reading</span>
                    </div>
                </div>
            </div>
            <div class="tls-lib-header-actions">
                <button class="tls-lib-action-btn" id="tls-lib-settings-btn">⚙ Settings</button>
                <button class="tls-lib-action-btn" id="tls-lib-logout">Sign Out</button>
                <button id="tls-lib-close" aria-label="Close">&times;</button>
            </div>
        </div>

        <!-- ── Nav tabs ── -->
        <div id="tls-lib-tabs">
            <button class="tls-lib-tab tls-lib-tab--active" data-view="library" data-filter="all">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                All Books
            </button>
            <button class="tls-lib-tab" data-view="library" data-filter="want">❤ Want to Read</button>
            <button class="tls-lib-tab" data-view="library" data-filter="reading">📖 Reading</button>
            <button class="tls-lib-tab" data-view="library" data-filter="read">✓ Finished</button>
            <button class="tls-lib-tab" data-view="settings" data-filter="">⚙ Settings</button>
        </div>

        <!-- ── Library view ── -->
        <div id="tls-lib-content">
            <div id="tls-lib-loading">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                Loading your library…
            </div>
            <div id="tls-lib-grid"></div>
            <div id="tls-lib-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40"><path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                <p>No books here yet.<br>Start reading and mark books!</p>
            </div>
        </div>

        <!-- ── Settings view ── -->
        <div id="tls-settings-content" style="display:none;">
            <div id="tls-settings-msg"></div>

            <div class="tls-settings-section">
                <h3 class="tls-settings-title">Profile</h3>
                <div class="tls-settings-field">
                    <label class="tls-settings-label">Display Name</label>
                    <input class="tls-settings-input" type="text" id="s-name" value="<?php echo esc_attr($current_user->display_name); ?>">
                </div>
                <div class="tls-settings-field">
                    <label class="tls-settings-label">Email Address</label>
                    <input class="tls-settings-input" type="email" id="s-email" value="<?php echo esc_attr($current_user->user_email); ?>">
                </div>
                <button class="tls-settings-btn" id="s-save-profile">Save Profile</button>
            </div>

            <div class="tls-settings-section">
                <h3 class="tls-settings-title">Change Password</h3>
                <div class="tls-settings-field">
                    <label class="tls-settings-label">Current Password</label>
                    <input class="tls-settings-input" type="password" id="s-pass-old" placeholder="••••••••">
                </div>
                <div class="tls-settings-field">
                    <label class="tls-settings-label">New Password <span style="color:rgba(255,255,255,.3);font-weight:400;">(min. 6 chars)</span></label>
                    <input class="tls-settings-input" type="password" id="s-pass-new" placeholder="••••••••">
                </div>
                <button class="tls-settings-btn" id="s-save-pass">Change Password</button>
            </div>

            <div class="tls-settings-section tls-settings-danger">
                <button class="tls-settings-btn tls-settings-btn--outline" id="tls-settings-logout">Sign Out of Account</button>
            </div>
        </div>

    </div><!-- /#tls-lib-modal -->
</div><!-- /#tls-lib-overlay -->

<style>
/* ── PROFILE PANEL ── */
#tls-lib-overlay {
    position:fixed;inset:0;z-index:99997;
    background:rgba(10,9,8,.8);backdrop-filter:blur(8px);
    align-items:flex-end;justify-content:center;
}
#tls-lib-modal {
    background:#1a1714;
    width:100%;max-width:720px;
    height:92vh;border-radius:20px 20px 0 0;
    display:flex;flex-direction:column;overflow:hidden;
    box-shadow:0 -8px 48px rgba(0,0,0,.4);
}
/* Header */
#tls-lib-header {
    display:flex;align-items:center;justify-content:space-between;
    padding:20px 24px 16px;
    border-bottom:1px solid rgba(255,255,255,.07);
    flex-shrink:0;gap:12px;flex-wrap:wrap;
}
.tls-lib-profile-info{display:flex;align-items:center;gap:14px;}
.tls-lib-avatar-wrap{flex-shrink:0;}
.tls-lib-avatar-img{width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid rgba(200,161,101,.4);}
.tls-lib-avatar{width:48px;height:48px;background:var(--tls-gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:var(--tls-serif);font-size:20px;color:#fff;flex-shrink:0;}
.tls-lib-name{font-family:var(--tls-serif);font-size:17px;color:#fff;margin-bottom:1px;}
.tls-lib-email{font-size:12px;color:rgba(255,255,255,.35);margin-bottom:6px;}
.tls-lib-stats{display:flex;gap:12px;}
.tls-lib-stat{font-family:var(--tls-sans);font-size:11px;color:rgba(255,255,255,.4);}
.tls-lib-stat strong{color:rgba(255,255,255,.75);font-weight:600;}
.tls-lib-header-actions{display:flex;gap:8px;align-items:center;flex-shrink:0;}
.tls-lib-action-btn{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.55);font-family:var(--tls-sans);font-size:11px;font-weight:600;padding:6px 12px;border-radius:20px;cursor:pointer;transition:all .15s;letter-spacing:.04em;}
.tls-lib-action-btn:hover{background:rgba(255,255,255,.12);color:#fff;}
#tls-lib-close{background:none;border:none;font-size:24px;color:rgba(255,255,255,.3);cursor:pointer;padding:4px 8px;border-radius:6px;line-height:1;transition:all .15s;}
#tls-lib-close:hover{color:#fff;background:rgba(255,255,255,.08);}

/* Tabs */
#tls-lib-tabs{display:flex;border-bottom:1px solid rgba(255,255,255,.07);padding:0 16px;flex-shrink:0;overflow-x:auto;}
.tls-lib-tab{padding:11px 14px;background:none;border:none;font-family:var(--tls-sans);font-size:11px;font-weight:600;color:rgba(255,255,255,.3);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;transition:all .15s;text-transform:uppercase;letter-spacing:.06em;white-space:nowrap;display:flex;align-items:center;gap:5px;}
.tls-lib-tab:hover{color:rgba(255,255,255,.6);}
.tls-lib-tab--active{color:var(--tls-gold);border-bottom-color:var(--tls-gold);}

/* Library content */
#tls-lib-content{flex:1;overflow-y:auto;padding:16px 24px;}
#tls-lib-loading{display:flex;flex-direction:column;align-items:center;gap:10px;padding:48px;color:rgba(255,255,255,.3);font-size:13px;animation:tlsAuthSpin 0s;}
#tls-lib-loading svg{animation:tlsAuthSpin .8s linear infinite;color:var(--tls-gold);}
#tls-lib-grid{display:flex;flex-direction:column;gap:8px;}
#tls-lib-empty{display:none;flex-direction:column;align-items:center;text-align:center;padding:48px 24px;gap:10px;color:rgba(255,255,255,.25);}
#tls-lib-empty p{color:rgba(255,255,255,.35);font-size:13px;margin:0;line-height:1.6;}
.tls-lib-item{display:flex;align-items:center;gap:12px;padding:12px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:10px;transition:background .15s;}
.tls-lib-item:hover{background:rgba(255,255,255,.06);}
.tls-lib-item-cover{flex-shrink:0;}
.tls-lib-item-cover img{width:36px;height:50px;object-fit:cover;border-radius:3px;display:block;}
.tls-lib-cover-ph{width:36px;height:50px;background:rgba(255,255,255,.07);border-radius:3px;display:flex;align-items:center;justify-content:center;font-family:var(--tls-serif);font-size:16px;color:rgba(255,255,255,.2);}
.tls-lib-item-body{flex:1;min-width:0;}
.tls-lib-badge{display:inline-block;padding:2px 7px;border-radius:8px;font-size:9px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;}
.tls-lib-item-title{display:block;font-family:var(--tls-serif);font-size:14px;color:rgba(255,255,255,.85);text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3;}
.tls-lib-item-title:hover{color:var(--tls-gold);}
.tls-lib-item-author{font-size:11px;color:rgba(255,255,255,.3);margin:2px 0 0;font-style:italic;}

/* Settings */
#tls-settings-content{flex:1;overflow-y:auto;padding:20px 24px;display:flex;flex-direction:column;gap:0;}
#tls-settings-msg{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;display:none;}
.tls-settings-section{padding:20px 0;border-bottom:1px solid rgba(255,255,255,.07);}
.tls-settings-section:last-child{border-bottom:none;}
.tls-settings-title{font-family:var(--tls-serif);font-size:16px;font-weight:400;color:rgba(255,255,255,.7);margin:0 0 16px;}
.tls-settings-field{display:flex;flex-direction:column;gap:5px;margin-bottom:12px;}
.tls-settings-label{font-family:var(--tls-sans);font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);}
.tls-settings-input{font-family:var(--tls-sans);font-size:14px;color:#fff;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.1);border-radius:8px;padding:10px 14px;width:100%;box-sizing:border-box;outline:none;transition:border-color .2s;-webkit-appearance:none;}
.tls-settings-input:focus{border-color:var(--tls-gold);}
.tls-settings-input::placeholder{color:rgba(255,255,255,.2);}
.tls-settings-btn{display:inline-flex;align-items:center;padding:10px 20px;background:var(--tls-gold);color:#1a1714;border:none;border-radius:8px;font-family:var(--tls-sans);font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;cursor:pointer;transition:opacity .2s;margin-top:4px;}
.tls-settings-btn:hover{opacity:.85;}
.tls-settings-btn--outline{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.4);padding:9px 20px;}
.tls-settings-btn--outline:hover{background:rgba(255,255,255,.1);color:rgba(255,255,255,.7);opacity:1;}
.tls-settings-danger{margin-top:8px;}

@media(max-width:600px){
    #tls-lib-header{flex-direction:column;align-items:flex-start;}
    #tls-lib-content,#tls-settings-content{padding:14px 16px;}
}
</style>
<?php endif; ?>

<style>
/* ── AUTH OVERLAY ── */
#tls-auth-overlay {
    position:fixed;inset:0;z-index:99997;
    background:rgba(20,18,14,.7);backdrop-filter:blur(4px);
    align-items:center;justify-content:center;padding:16px;
}
#tls-auth-modal {
    background:#fff;border-radius:16px;
    width:100%;max-width:400px;
    padding:32px 28px;position:relative;
    box-shadow:0 24px 64px rgba(0,0,0,.25);
}
#tls-auth-close {
    position:absolute;top:14px;right:16px;
    background:none;border:none;font-size:22px;color:#aaa;
    cursor:pointer;padding:4px 8px;border-radius:6px;line-height:1;
}
#tls-auth-close:hover{color:#333;background:#f4f4f4;}
.tls-auth-brand{text-align:center;margin-bottom:24px;}
.tls-auth-logo{font-family:var(--tls-serif);font-size:22px;color:var(--tls-bg-dark);margin:0 0 4px;font-weight:400;}
.tls-auth-tagline{font-family:var(--tls-sans);font-size:12px;color:var(--tls-muted);margin:0;}
.tls-auth-tabs{display:flex;border-bottom:1.5px solid var(--tls-border);margin-bottom:22px;}
.tls-auth-tab{flex:1;padding:9px 0;background:none;border:none;font-family:var(--tls-sans);font-size:13px;font-weight:600;color:var(--tls-muted);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1.5px;transition:color .15s,border-color .15s;}
.tls-auth-tab--active{color:var(--tls-bg-dark);border-bottom-color:var(--tls-gold);}
#tls-auth-msg{padding:10px 12px;border-radius:8px;font-family:var(--tls-sans);font-size:13px;margin-bottom:14px;}
.tls-auth-msg--error{background:#fff5f5;border:1px solid #fca5a5;color:#b91c1c;}
.tls-auth-msg--success{background:#f0fdf4;border:1px solid #86efac;color:#15803d;}
.tls-auth-form{display:flex;flex-direction:column;gap:14px;}
.tls-auth-field{display:flex;flex-direction:column;gap:5px;}
.tls-auth-label{font-family:var(--tls-sans);font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--tls-bg-dark);}
.tls-auth-input{font-family:var(--tls-sans);font-size:16px;color:var(--tls-bg-dark);background:#faf9f7;border:1.5px solid var(--tls-border);border-radius:8px;padding:11px 14px;width:100%;box-sizing:border-box;outline:none;transition:border-color .2s,box-shadow .2s;-webkit-appearance:none;}
.tls-auth-input::placeholder{color:#ccc;}
.tls-auth-input:focus{border-color:var(--tls-gold);box-shadow:0 0 0 3px rgba(200,161,101,.12);background:#fff;}
.tls-auth-submit{display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;background:var(--tls-bg-dark);color:#fff;border:none;border-radius:8px;font-family:var(--tls-sans);font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;cursor:pointer;transition:background .2s;margin-top:2px;}
.tls-auth-submit:hover:not(:disabled){background:#2a2620;}
.tls-auth-submit:disabled{opacity:.55;cursor:not-allowed;}
.tls-auth-textbtn{background:none;border:none;font-family:var(--tls-sans);font-size:13px;color:var(--tls-gold);cursor:pointer;padding:0;text-decoration:underline;text-underline-offset:2px;}
@keyframes tlsAuthSpin{to{transform:rotate(360deg);}}

/* ── LIBRARY OVERLAY ── */
#tls-lib-overlay{
    position:fixed;inset:0;z-index:99997;
    background:rgba(20,18,14,.85);backdrop-filter:blur(6px);
    align-items:flex-end;justify-content:center;
}
#tls-lib-modal{
    background:var(--tls-bg-dark);
    width:100%;max-width:680px;
    height:90vh;
    border-radius:20px 20px 0 0;
    display:flex;flex-direction:column;
    overflow:hidden;
}
#tls-lib-header{
    display:flex;align-items:center;justify-content:space-between;
    padding:20px 24px;
    border-bottom:1px solid rgba(255,255,255,.08);
    flex-shrink:0;
}
.tls-lib-avatar{width:40px;height:40px;background:var(--tls-gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:var(--tls-serif);font-size:18px;color:#fff;flex-shrink:0;}
.tls-lib-action-btn{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.6);font-family:var(--tls-sans);font-size:12px;padding:6px 14px;border-radius:20px;cursor:pointer;transition:all .15s;}
.tls-lib-action-btn:hover{background:rgba(255,255,255,.14);color:#fff;}
#tls-lib-close{background:none;border:none;font-size:24px;color:rgba(255,255,255,.4);cursor:pointer;padding:4px 8px;border-radius:6px;line-height:1;}
#tls-lib-close:hover{color:#fff;background:rgba(255,255,255,.08);}
#tls-lib-tabs{display:flex;border-bottom:1px solid rgba(255,255,255,.08);padding:0 24px;flex-shrink:0;}
.tls-lib-tab{padding:12px 16px;background:none;border:none;font-family:var(--tls-sans);font-size:12px;font-weight:600;color:rgba(255,255,255,.35);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;transition:all .15s;text-transform:uppercase;letter-spacing:.06em;}
.tls-lib-tab--active{color:var(--tls-gold);border-bottom-color:var(--tls-gold);}
#tls-lib-content{flex:1;overflow-y:auto;padding:20px 24px;}
#tls-lib-grid{display:flex;flex-direction:column;gap:10px;}
.tls-lib-item{display:flex;align-items:center;gap:12px;padding:12px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:10px;transition:background .15s;}
.tls-lib-item:hover{background:rgba(255,255,255,.07);}
.tls-lib-item-cover{flex-shrink:0;}
.tls-lib-item-cover img{width:40px;height:56px;object-fit:cover;border-radius:3px;display:block;}
.tls-lib-cover-ph{width:40px;height:56px;background:rgba(255,255,255,.08);border-radius:3px;display:flex;align-items:center;justify-content:center;font-family:var(--tls-serif);font-size:18px;color:rgba(255,255,255,.3);}
.tls-lib-item-body{flex:1;min-width:0;}
.tls-lib-badge{display:inline-block;padding:2px 7px;border-radius:8px;font-size:9px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;}
.tls-lib-item-title{display:block;font-family:var(--tls-serif);font-size:14px;color:#fff;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.tls-lib-item-title:hover{color:var(--tls-gold);}
.tls-lib-item-author{font-size:12px;color:rgba(255,255,255,.4);margin:2px 0 0;font-style:italic;}
</style>

<script>
(function(){
    var ajax      = '<?php echo esc_js($ajax_url); ?>';
    var authNonce = '<?php echo esc_js($auth_nonce); ?>';
    var postId    = (function(){
        var w = document.querySelector('.tls-read-status[data-post-id]');
        return w ? w.dataset.postId : '';
    })();

    /* Global state — AJAX'tan doldurulacak */
    var gState = { loggedIn: false, user: null, status: '', nonces: {} };

    /* ── OVERLAY YARDIMCILARI ── */
    function openOverlay(id){
        var el=document.getElementById(id);
        if(el){el.style.display='flex';document.body.style.overflow='hidden';}
    }
    function closeOverlay(id){
        var el=document.getElementById(id);
        if(el){el.style.display='none';document.body.style.overflow='';}
    }
    window.closeTlsLib = function(){ closeOverlay('tls-lib-overlay'); };

    /* ══════════════════════════════════════════
       MERKEZI INIT — sayfa yüklenince çalışır
       AJAX ile gerçek login durumunu çeker
       Cache'den etkilenmez
    ══════════════════════════════════════════ */
    function initAll() {
        var fd = new FormData();
        fd.append('action', 'tls_get_user_state');
        if (postId) fd.append('post_id', postId);

        fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (!res.success) return;
            gState = {
                loggedIn : res.data.logged_in,
                user     : res.data.user,
                status   : res.data.status,
                nonces   : res.data.nonces || {}
            };
            authNonce = gState.nonces.auth || authNonce;

            updateHeader();
            updateStatusButtons();
            applySessionPending();
            initStatusButtons();  /* guard sayesinde sadece UI günceller, yeniden bind etmez */
            /* initDropdown buradan çağrılmıyor — DOMContentLoaded'da bir kez çağrılıyor */
        })
        .catch(function(){
            gState.loggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
            initStatusButtons();
        });
    }

    /* ══════════════════════════════════════════
       HEADER GÜNCELLEMESİ
    ══════════════════════════════════════════ */
    function updateHeader() {
        var menuWrap = document.getElementById('tls-user-menu');
        var iconWrap = document.getElementById('tls-user-icon');

        if (gState.loggedIn && gState.user) {
            if (iconWrap && !menuWrap) {
                /* Sadece ikon varsa (cache'li sayfa) — dropdown inşa et */
                iconWrap.outerHTML = buildDropdownHTML(gState.user);
                /* initDropdown() initAll'ın sonunda zaten çağrılacak */
            }
            /* Dropdown PHP'den geldiyse dokunma */
        } else if (!gState.loggedIn && menuWrap) {
            menuWrap.outerHTML = '<a class="tls-icon-btn" href="#" id="tls-user-icon" aria-label="Sign In"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="20" height="20"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></a>';
            document.getElementById('tls-user-icon').addEventListener('click', function(e){
                e.preventDefault(); openOverlay('tls-auth-overlay');
            });
        }

        window._tlsState = gState;
    }

    function buildDropdownHTML(user) {
        var fullName = user.name;
        var init     = user.initial;
        var profileUrl = user.profile;
        return '<div class="tls-user-menu" id="tls-user-menu">'
            + '<button class="tls-user-menu-trigger" id="tls-user-trigger" type="button">'
            + '<span class="tls-user-avatar">'+init+'</span>'
            + '<span class="tls-user-name">'+fullName+'</span>'
            + '<svg class="tls-user-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M6 9l6 6 6-6"/></svg>'
            + '</button>'
            + '<div class="tls-user-dropdown" id="tls-user-dropdown">'
            + '<div class="tls-user-dropdown-header">'
            + '<span class="tls-user-dropdown-name">'+fullName+'</span>'
            + '<span class="tls-user-dropdown-email">'+user.email+'</span>'
            + '</div>'
            + '<div class="tls-user-dropdown-divider"></div>'
            + '<a class="tls-user-dropdown-item" href="'+profileUrl+'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg> My Library</a>'
            + '<a class="tls-user-dropdown-item" href="'+profileUrl+'?tab=settings"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><circle cx="12" cy="12" r="3"/></svg> Settings</a>'
            + '<div class="tls-user-dropdown-divider"></div>'
            + '<button class="tls-user-dropdown-item tls-user-dropdown-item--danger" id="tls-header-signout" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> Sign Out</button>'
            + '</div></div>';
    }

    /* ══════════════════════════════════════════
       STATUS BUTON GÜNCELLEMESİ
    ══════════════════════════════════════════ */
    function updateStatusButtons() {
        var wrap = document.querySelector('.tls-read-status[data-post-id]');
        if (!wrap || !gState.status) return;
        wrap.querySelectorAll('.tls-status-btn').forEach(function(b){
            b.classList.toggle('active', b.dataset.status === gState.status);
        });
        document.dispatchEvent(new Event('tls:statusChanged'));
    }

    /* ══════════════════════════════════════════
       SESSION STORAGE — login sonrası pending
    ══════════════════════════════════════════ */
    function applySessionPending() {
        var raw = sessionStorage.getItem('tls_pending');
        if (!raw) return;
        sessionStorage.removeItem('tls_pending');
        var p; try { p = JSON.parse(raw); } catch(e){ return; }
        if (!p || !p.postId || !p.status) return;

        var sNonce = gState.nonces.status || '';
        var fd = new FormData();
        fd.append('action',  'tls_set_status');
        fd.append('nonce',   sNonce);
        fd.append('post_id', p.postId);
        fd.append('status',  p.status);

        fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success) {
                gState.status = p.status;
                var wrap = document.querySelector('.tls-read-status[data-post-id="'+p.postId+'"]');
                if (wrap) {
                    wrap.querySelectorAll('.tls-status-btn').forEach(function(b){
                        b.classList.toggle('active', b.dataset.status === p.status);
                    });
                    document.dispatchEvent(new Event('tls:statusChanged'));
                }
            }
        }).catch(function(){});
    }

    /* ══════════════════════════════════════════
       STATUS BUTON CLICK HANDLER
       — Tek seferlik bağlama (guard ile)
    ══════════════════════════════════════════ */
    var _statusBound = false;   /* guard: birden fazla bind'ı önler */

    function initStatusButtons() {
        if (_statusBound) {
            /* Zaten bağlı — sadece UI'yi güncelle */
            updateStatusButtons();
            return;
        }
        var wrap = document.querySelector('.tls-read-status[data-post-id]');
        if (!wrap) return;
        _statusBound = true;

        var sPid = wrap.dataset.postId;

        wrap.addEventListener('click', function(e){
            var btn = e.target.closest('.tls-status-btn[data-status]');
            if (!btn) return;

            var curActive = wrap.querySelector('.tls-status-btn.active');
            var curSt = curActive ? curActive.dataset.status : '';
            var newSt = curSt === btn.dataset.status ? '' : btn.dataset.status;

            /* Login değilse — pending kaydet, popup aç */
            if (!gState.loggedIn) {
                window._tlsPendingStatus = {postId: sPid, status: newSt};
                sessionStorage.setItem('tls_pending', JSON.stringify(window._tlsPendingStatus));
                openOverlay('tls-auth-overlay');
                return;
            }

            /* Optimistic UI */
            wrap.querySelectorAll('.tls-status-btn').forEach(function(b){
                b.classList.toggle('active', b.dataset.status === newSt && newSt !== '');
            });
            document.dispatchEvent(new Event('tls:statusChanged'));

            /* AJAX */
            var sNonce = (gState.nonces && gState.nonces.status) || '';
            var fd = new FormData();
            fd.append('action',  'tls_set_status');
            fd.append('nonce',   sNonce);
            fd.append('post_id', sPid);
            fd.append('status',  newSt);

            fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.success) {
                    /* Geri al */
                    wrap.querySelectorAll('.tls-status-btn').forEach(function(b){
                        b.classList.toggle('active', b.dataset.status === curSt);
                    });
                    document.dispatchEvent(new Event('tls:statusChanged'));
                    /* Nonce hatası ise login overlay aç */
                    window._tlsPendingStatus = {postId: sPid, status: newSt};
                    sessionStorage.setItem('tls_pending', JSON.stringify(window._tlsPendingStatus));
                    openOverlay('tls-auth-overlay');
                } else {
                    gState.status = newSt;
                }
            }).catch(function(){});
        });
    }

    /* ══════════════════════════════════════════
       DROPDOWN MENU
    ══════════════════════════════════════════ */
    /* Event delegation ile bağlanır: dropdown PHP'den mi geldi yoksa login
       sonrası updateHeader() ile sonradan mı basıldı fark etmez — buton her
       durumda çalışır. (Eskiden trigger'a doğrudan bağlanıyordu; JS ile
       sonradan basılan menü bağlanamadan kalıyordu → "yenileyince düzeliyor".) */
    var _dropdownDelegated = false;
    function initDropdown() {
        if (_dropdownDelegated) return;
        _dropdownDelegated = true;

        document.addEventListener('click', function(e){
            var t = e.target;
            var closest = function(sel){ return t.closest ? t.closest(sel) : null; };

            /* Kullanıcı menüsü aç/kapa */
            var trigger = closest('#tls-user-trigger');
            if (trigger) {
                e.preventDefault();
                e.stopPropagation();
                var menu = document.getElementById('tls-user-menu');
                if (menu) trigger.setAttribute('aria-expanded', menu.classList.toggle('open'));
                return;
            }

            /* Çıkış yap */
            if (closest('#tls-header-signout')) {
                var fd = new FormData();
                fd.append('action','tls_logout');
                fd.append('nonce', authNonce);
                fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
                    .then(function(r){return r.json();})
                    .then(function(res){ if(res.success) window.location.href=res.data.redirect; });
                return;
            }

            /* Giriş ikonu → auth popup */
            var icon = closest('#tls-user-icon');
            if (icon) { e.preventDefault(); openOverlay('tls-auth-overlay'); return; }

            /* Dışarı tıklama → menüyü kapat */
            var openMenu = document.getElementById('tls-user-menu');
            if (openMenu && !openMenu.contains(t)) openMenu.classList.remove('open');
        });
    }

    /* ── AUTH POPUP ── */
    var authOverlay = document.getElementById('tls-auth-overlay');
    if(authOverlay){
        document.getElementById('tls-auth-close').addEventListener('click', function(){ closeOverlay('tls-auth-overlay'); });
        authOverlay.addEventListener('click', function(e){ if(e.target===authOverlay) closeOverlay('tls-auth-overlay'); });
        document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeOverlay('tls-auth-overlay'); });

        /* Tabs */
        document.querySelectorAll('.tls-auth-tab').forEach(function(b){
            b.addEventListener('click', function(){
                document.querySelectorAll('.tls-auth-tab').forEach(function(x){x.classList.remove('tls-auth-tab--active');});
                b.classList.add('tls-auth-tab--active');
                showAuthForm(b.dataset.tab==='login'?'tls-tab-login':'tls-tab-register');
                clearAuthMsg();
            });
        });
        document.getElementById('tls-show-forgot').addEventListener('click', function(){ showAuthForm('tls-tab-forgot'); clearAuthMsg(); });
        document.getElementById('tls-back-login').addEventListener('click', function(){ showAuthForm('tls-tab-login'); clearAuthMsg(); });

        function showAuthForm(id){
            ['tls-tab-login','tls-tab-register','tls-tab-forgot','tls-tab-interests'].forEach(function(f){
                var el=document.getElementById(f);
                if(el) el.style.display=f===id?'':'none';
            });
            /* İlgi adımında üstteki sekme çubuğunu gizle (ayrı bir ekran hissi) */
            var tabs=document.querySelector('.tls-auth-tabs');
            if(tabs) tabs.style.display = (id==='tls-tab-interests') ? 'none' : '';
        }
        function authMsg(text,type){
            var el=document.getElementById('tls-auth-msg');
            if(!text){el.style.display='none';return;}
            el.textContent=text; el.className='tls-auth-msg--'+type; el.style.display='block';
        }
        function clearAuthMsg(){ authMsg('',''); }

        /* Login/Register sonrası bekleyen status'ü uygula */
        function applyPendingStatus(cb) {
            var pending = window._tlsPendingStatus;
            if (!pending || !pending.postId || !pending.status) { cb && cb(); return; }
            var sAjax = ajax;
            /* Taze nonce al */
            getAuthNonce(function(freshNonce){
                var fd = new FormData();
                fd.append('action',  'tls_set_status');
                fd.append('nonce',   freshNonce);
                fd.append('post_id', pending.postId);
                fd.append('status',  pending.status);
                fetch(sAjax, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(){
                    /* UI'da da göster */
                    var wrap = document.querySelector('.tls-read-status[data-post-id]');
                    if (wrap) {
                        wrap.querySelectorAll('.tls-status-btn').forEach(function(b){
                            b.classList.toggle('active', b.dataset.status === pending.status);
                        });
                        document.dispatchEvent(new Event('tls:statusChanged'));
                    }
                    window._tlsPendingStatus = null;
                    cb && cb();
                })
                .catch(function(){ cb && cb(); });
            });
        }

    /* ── AUTH AJAX — taze nonce ile gönder ── */
    function getAuthNonce(cb) {
        /* Her istekte taze nonce al — önbellek sorununun önüne geçer */
        var fd = new FormData();
        fd.append('action', 'tls_get_nonce');
        fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res){ cb(res.success ? res.data.nonce : authNonce); })
        .catch(function(){ cb(authNonce); });
    }

    function authPost(action, data, cb, btnId){
        var btn=document.getElementById(btnId);
        var orig=btn.textContent;
        btn.disabled=true; btn.textContent='…';
        clearAuthMsg();

        getAuthNonce(function(freshNonce) {
            var fd=new FormData();
            fd.append('action',action); fd.append('nonce',freshNonce);
            for(var k in data) fd.append(k,data[k]);
            fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(res){
                btn.disabled=false; btn.textContent=orig;
                if(res.success) cb(res.data);
                else authMsg(res.data&&res.data.message?res.data.message:'Something went wrong.','error');
            })
            .catch(function(){ btn.disabled=false; btn.textContent=orig; authMsg('Network error.','error'); });
        });
    }

        /* Login */
        document.getElementById('tls-btn-login').addEventListener('click', function(){
            var email=document.getElementById('tls-login-email').value.trim();
            var pass=document.getElementById('tls-login-pass').value;
            var rem=document.getElementById('tls-login-rem').checked?'1':'';
            if(!email||!pass){authMsg('Please enter your email and password.','error');return;}
            authPost('tls_login',{tls_email:email,tls_pass:pass,tls_remember:rem},function(){
                if (window._tlsPendingStatus) {
                    sessionStorage.setItem('tls_pending', JSON.stringify(window._tlsPendingStatus));
                }
                if (window._tlsPendingListId) {
                    sessionStorage.setItem('tls_pending_list', window._tlsPendingListId);
                }
                if (window._tlsAuthRedirect) { window.location.href = window._tlsAuthRedirect; return; }
                window.location.reload();
            },'tls-btn-login');
        });

        /* Register */
        document.getElementById('tls-btn-register').addEventListener('click', function(){
            var name=document.getElementById('tls-reg-name').value.trim();
            var email=document.getElementById('tls-reg-email').value.trim();
            var pass=document.getElementById('tls-reg-pass').value;
            var hp=document.getElementById('tls-reg-hp').value;
            if(!name||!email||!pass){authMsg('Please fill in all fields.','error');return;}
            if(pass.length<6){authMsg('Password must be at least 6 characters.','error');return;}
            authPost('tls_register',{tls_name:name,tls_email:email,tls_pass:pass,tls_hp:hp},function(d){
                if (window._tlsPendingStatus) {
                    sessionStorage.setItem('tls_pending', JSON.stringify(window._tlsPendingStatus));
                }
                if (window._tlsPendingListId) {
                    sessionStorage.setItem('tls_pending_list', window._tlsPendingListId);
                }
                /* Kayıt başarılı → çıkış yolunu sakla, pop-up içinde ilgi adımını göster */
                window._tlsPostRegRedirect = window._tlsAuthRedirect || '/profile/';
                clearAuthMsg();
                showAuthForm('tls-tab-interests');
            },'tls-btn-register');
        });

        /* Forgot */
        document.getElementById('tls-btn-forgot').addEventListener('click', function(){
            var email=document.getElementById('tls-forgot-email').value.trim();
            if(!email){authMsg('Please enter your email.','error');return;}
            authPost('tls_forgot',{tls_email:email},function(d){
                authMsg(d.message,'success');
                // Mail gitmediyse direkt link varsa göster
                if(d.link){
                    var linkEl = document.createElement('p');
                    linkEl.style.cssText='margin:8px 0 0;font-size:12px;word-break:break-all;';
                    linkEl.innerHTML='<a href="'+d.link+'" style="color:var(--tls-gold);">Click here to reset →</a>';
                    document.getElementById('tls-auth-msg').appendChild(linkEl);
                }
            },'tls-btn-forgot');
        });

        /* ── İlgi alanı adımı (kayıt sonrası pop-up) ── */
        function tlsFinishRegistration(){
            var url = window._tlsPostRegRedirect || '/profile/';
            window.location.href = url;
        }
        /* Chip aç/kapa — yalnızca overlay içindeki grid (profil kendi handler'ını kullanır) */
        document.addEventListener('click', function(e){
            var chip = e.target.closest ? e.target.closest('#tls-tab-interests .tls-int-chip') : null;
            if(!chip) return;
            var on = chip.classList.toggle('tls-int-chip--on');
            chip.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        var biBtn = document.getElementById('tls-btn-interests');
        if(biBtn) biBtn.addEventListener('click', function(){
            var slugs = [];
            document.querySelectorAll('#tls-tab-interests .tls-int-chip--on').forEach(function(c){ slugs.push(c.dataset.slug); });
            biBtn.disabled = true; biBtn.textContent = '…';
            var fd = new FormData();
            fd.append('action','tls_save_interests');
            fd.append('nonce', authNonce);
            fd.append('interests', slugs.join(','));
            fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(){ tlsFinishRegistration(); })
            .catch(function(){ tlsFinishRegistration(); });
        });
        var skBtn = document.getElementById('tls-skip-interests');
        if(skBtn) skBtn.addEventListener('click', tlsFinishRegistration);

        /* Enter key */
        document.addEventListener('keydown',function(e){
            if(e.key!=='Enter') return;
            var active=document.querySelector('#tls-auth-modal .tls-auth-form:not([style*="display:none"])');
            if(active){var b=active.querySelector('.tls-auth-submit');if(b)b.click();}
        });
    }

    var libOverlay = document.getElementById('tls-lib-overlay');
    if(libOverlay){
        document.getElementById('tls-lib-close').addEventListener('click', function(){ closeOverlay('tls-lib-overlay'); });
        libOverlay.addEventListener('click', function(e){ if(e.target===libOverlay) closeOverlay('tls-lib-overlay'); });
        document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeOverlay('tls-lib-overlay'); });

        /* ── Tab yönetimi: library vs settings ── */
        document.querySelectorAll('.tls-lib-tab').forEach(function(b){
            b.addEventListener('click', function(){
                document.querySelectorAll('.tls-lib-tab').forEach(function(x){x.classList.remove('tls-lib-tab--active');});
                b.classList.add('tls-lib-tab--active');
                var view = b.dataset.view;
                var filter = b.dataset.filter;
                if(view === 'settings'){
                    document.getElementById('tls-lib-content').style.display    = 'none';
                    document.getElementById('tls-settings-content').style.display = 'flex';
                } else {
                    document.getElementById('tls-lib-content').style.display    = 'block';
                    document.getElementById('tls-settings-content').style.display = 'none';
                    loadLibrary(filter);
                }
            });
        });

        /* Settings butonu da tab'a yönlendir */
        var settingsBtn = document.getElementById('tls-lib-settings-btn');
        if(settingsBtn){
            settingsBtn.addEventListener('click', function(){
                document.querySelectorAll('.tls-lib-tab').forEach(function(x){x.classList.remove('tls-lib-tab--active');});
                var settingsTab = document.querySelector('.tls-lib-tab[data-view="settings"]');
                if(settingsTab) settingsTab.classList.add('tls-lib-tab--active');
                document.getElementById('tls-lib-content').style.display     = 'none';
                document.getElementById('tls-settings-content').style.display = 'flex';
            });
        }

        /* ── Logout ── */
        function doLogout(){
            var fd=new FormData(); fd.append('action','tls_logout'); fd.append('nonce',authNonce);
            fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(res){ if(res.success) window.location.href = res.data.redirect; });
        }
        var logoutBtn = document.getElementById('tls-lib-logout');
        var logoutBtn2 = document.getElementById('tls-settings-logout');
        if(logoutBtn)  logoutBtn.addEventListener('click', doLogout);
        if(logoutBtn2) logoutBtn2.addEventListener('click', doLogout);

        /* ── Settings: profil kaydet ── */
        var saveProfileBtn = document.getElementById('s-save-profile');
        if(saveProfileBtn){
            saveProfileBtn.addEventListener('click', function(){
                var name  = document.getElementById('s-name').value.trim();
                var email = document.getElementById('s-email').value.trim();
                settingsMsg('','');
                var fd=new FormData();
                fd.append('action','tls_update_profile'); fd.append('nonce',authNonce);
                fd.append('tls_name',name); fd.append('tls_email',email);
                fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
                .then(function(r){return r.json();})
                .then(function(res){
                    settingsMsg(res.success?res.data.message:res.data.message, res.success?'success':'error');
                });
            });
        }

        /* ── Settings: şifre değiştir ── */
        var savePassBtn = document.getElementById('s-save-pass');
        if(savePassBtn){
            savePassBtn.addEventListener('click', function(){
                var old = document.getElementById('s-pass-old').value;
                var nw  = document.getElementById('s-pass-new').value;
                if(!old || !nw){ settingsMsg('Please fill both password fields.','error'); return; }
                if(nw.length < 6){ settingsMsg('Password must be at least 6 characters.','error'); return; }
                settingsMsg('','');
                var fd=new FormData();
                fd.append('action','tls_update_profile'); fd.append('nonce',authNonce);
                fd.append('tls_pass_old',old); fd.append('tls_pass',nw);
                fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
                .then(function(r){return r.json();})
                .then(function(res){
                    settingsMsg(res.success?res.data.message:res.data.message, res.success?'success':'error');
                    if(res.success){ document.getElementById('s-pass-old').value=''; document.getElementById('s-pass-new').value=''; }
                });
            });
        }

        function settingsMsg(text, type){
            var el = document.getElementById('tls-settings-msg');
            if(!text){ el.style.display='none'; return; }
            el.textContent = text;
            el.style.display = 'block';
            el.style.background = type==='success' ? 'rgba(139,175,124,.15)' : 'rgba(185,28,28,.15)';
            el.style.color      = type==='success' ? '#8baf7c'              : '#fca5a5';
            el.style.border     = '1px solid ' + (type==='success' ? 'rgba(139,175,124,.3)' : 'rgba(252,165,165,.3)');
        }
    }

    /* ── Kütüphane yükleme ── */
    var statusColors={want:'#b84c6e',reading:'var(--tls-gold)',read:'var(--tls-green)'};
    var statusLabels={want:'Want to Read',reading:'Currently Reading',read:'Finished'};

    window.loadLibrary = function(filter){
        var loading=document.getElementById('tls-lib-loading');
        var grid=document.getElementById('tls-lib-grid');
        var empty=document.getElementById('tls-lib-empty');
        if(!loading) return;
        loading.style.display='flex'; grid.innerHTML=''; grid.style.display='none'; empty.style.display='none';
        var fd=new FormData(); fd.append('action','tls_get_library'); fd.append('filter',filter);
        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(res){
            loading.style.display='none';
            if(!res.success||!res.data.books.length){empty.style.display='flex';return;}
            grid.style.display='flex';
            grid.innerHTML=res.data.books.map(function(b){
                return '<div class="tls-lib-item">'+
                    '<a href="'+b.url+'" class="tls-lib-item-cover">'+
                    (b.cover?'<img src="'+b.cover+'" alt="">':'<div class="tls-lib-cover-ph">'+b.title.charAt(0)+'</div>')+
                    '</a><div class="tls-lib-item-body">'+
                    '<span class="tls-lib-badge" style="background:'+statusColors[b.status]+'">'+statusLabels[b.status]+'</span>'+
                    '<a href="'+b.url+'" class="tls-lib-item-title">'+b.title+'</a>'+
                    (b.author?'<p class="tls-lib-item-author">'+b.author+'</p>':'')+
                    '</div></div>';
            }).join('');
        });
    };

    /* ── Newsletter subscribe ── */
    var nlBtn = document.getElementById('tls-nl-btn');
    var nlInput = document.getElementById('tls-nl-email');
    var nlMsg = document.getElementById('tls-nl-msg');
    if (nlBtn && nlInput) {
        function doSubscribe() {
            var email = nlInput.value.trim();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                nlMsg.textContent = 'Please enter a valid email.';
                nlMsg.style.color = '#e57373';
                return;
            }
            nlBtn.disabled = true;
            nlBtn.textContent = '…';
            var fd = new FormData();
            fd.append('action', 'tls_newsletter_subscribe');
            fd.append('email', email);
            fd.append('nonce', '<?php echo esc_js(wp_create_nonce("tls_newsletter")); ?>');
            fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(res){
                nlBtn.disabled = false;
                nlBtn.textContent = 'Subscribe →';
                if (res.success) {
                    nlMsg.textContent = '✓ You\'re subscribed!';
                    nlMsg.style.color = 'var(--tls-gold)';
                    nlInput.value = '';
                } else {
                    nlMsg.textContent = res.data.message || 'Something went wrong.';
                    nlMsg.style.color = '#e57373';
                }
            });
        }
        nlBtn.addEventListener('click', doSubscribe);
        nlInput.addEventListener('keydown', function(e){ if(e.key==='Enter') doSubscribe(); });
    }

    /* ── Add to Library ── */
    function setListBtnSaved(btn) {
        btn.innerHTML = '✓ Reading Paths’e eklendi →';
        btn.classList.add('added');
        btn.disabled = false;
        btn.dataset.saved = '1';
    }

    document.addEventListener('click', function(e){
        var btn = e.target.closest('.tls-rl-add-btn');
        if (!btn) return;
        var listId = btn.dataset.listId;
        if (!listId) return;

        /* Zaten kaydedilmişse → profile git, toggle yapma */
        if (btn.dataset.saved === '1') {
            var goUrl = (window.tlsAuth && window.tlsAuth.profileUrl)
                ? window.tlsAuth.profileUrl + '?tab=readingpaths'
                : '/profile/?tab=readingpaths';
            window.location.href = goUrl;
            return;
        }

        var origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳';

        var fd = new FormData();
        fd.append('action',  'tls_add_list_to_library');
        fd.append('list_id', listId);

        fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success && res.data.action === 'added') {
                setListBtnSaved(btn);
            } else if (res.data && res.data.message === 'login_required') {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                window._tlsPendingListId = listId;
                var authOv = document.getElementById('tls-auth-overlay');
                if (authOv) openOverlay('tls-auth-overlay');
            } else {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        })
        .catch(function(){ btn.disabled = false; btn.innerHTML = origHtml; });
    });

    /* ── Reload sonrası pending list uygula ── */
    (function(){
        var pendingList = sessionStorage.getItem('tls_pending_list');
        if (!pendingList) return;
        sessionStorage.removeItem('tls_pending_list');
        var fd = new FormData();
        fd.append('action',  'tls_add_list_to_library');
        fd.append('list_id', pendingList);
        fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success && res.data.action === 'added') {
                var btn = document.querySelector('.tls-rl-add-btn[data-list-id="'+pendingList+'"]');
                if (btn) setListBtnSaved(btn);
            }
        });
    })();

        /* ── Başlat ── */
    document.addEventListener('DOMContentLoaded', function(){
        initDropdown();      /* Önce dropdown */
        initStatusButtons(); /* Status butonları da hemen bağla */
        initAll();           /* Sonra AJAX state */
    });

})();
</script>

</body>
</html>
