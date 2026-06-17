<?php
/**
 * Template Name: Support Us
 * Template Post Type: page
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* Patreon — ana destek yöntemi — Customize → Support / Donations */
$patreon_url = get_theme_mod( 'tls_patreon_url', 'https://www.patreon.com/c/Thetelos' );

/* Shopier ürün URL'leri — Customize → Support / Donations */
$url_5   = get_theme_mod( 'tls_support_url_5',   '' );
$url_10  = get_theme_mod( 'tls_support_url_10',  '' );
$url_25  = get_theme_mod( 'tls_support_url_25',  '' );
$url_50  = get_theme_mod( 'tls_support_url_50',  '' );
$url_100 = get_theme_mod( 'tls_support_url_100', '' );

/* Kripto adresleri — Customize → Support / Donations */
$btc  = get_theme_mod( 'tls_crypto_btc',  '' );
$eth  = get_theme_mod( 'tls_crypto_eth',  '' );
$usdc = get_theme_mod( 'tls_crypto_usdc', '' );

/* Tutar → Shopier URL eşleşmesi */
$shopier_urls = [
    5   => $url_5,
    10  => $url_10,
    25  => $url_25,
    50  => $url_50,
    100 => $url_100,
];
$shopier_map_json = json_encode( array_map( 'esc_url', $shopier_urls ) );

get_header();
?>

<main id="main" role="main">
<div class="tls-don-wrap">
<div class="tls-don-inner">

    <!-- ══ LEFT · Letter ══ -->
    <div class="tls-don-letter">

        <p class="tls-don-eyebrow">The Archive &middot; A Letter</p>

        <h1 class="tls-don-headline">
            Keep the world's<br>
            <em>great books</em> free to read.
        </h1>

        <p class="tls-don-body">
            thetelos exists to publish careful summaries and analyses of
            humanity's most important works — beginning with philosophy —
            and to give them away, to everyone, for nothing.
        </p>

        <p class="tls-don-body">
            Every analysis on this site was read closely, written slowly,
            and edited by hand. There are no ads, no paywalls, and no
            investors deciding what gets read. That independence is the
            whole point — and it is paid for by readers like you.
        </p>

        <p class="tls-don-body">
            A single gift keeps the servers running, funds the next book in
            the archive, and ensures a student anywhere in the world can sit
            with Aristotle, Augustine, or Arendt without paying a cent.
            If the work has given you something, consider becoming a member —
            or leaving a one-time gift.
        </p>

        <p class="tls-don-sig">The Telos Editorial</p>

    </div><!-- /.tls-don-letter -->


    <!-- ══ RIGHT · Donation card ══ -->
    <div class="tls-don-card" role="region" aria-label="Donation form">

        <!-- Card header -->
        <div class="tls-don-card-head">
            <span class="tls-don-onetime">Support thetelos</span>
            <span class="tls-don-secure">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     width="12" height="12" aria-hidden="true">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                Secure
            </span>
        </div>

        <div class="tls-don-card-body">

            <!-- ★ PRIMARY · Patreon membership -->
            <div class="tls-don-patreon">
                <div class="tls-don-patreon-head">
                    <span class="tls-don-patreon-logo" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor">
                            <path d="M0 .48h4.22v23.04H0zM15.385.48c-4.764 0-8.641 3.88-8.641 8.65 0 4.755 3.877 8.623 8.641 8.623 4.75 0 8.615-3.868 8.615-8.623C24 4.36 20.136.48 15.385.48z"/>
                        </svg>
                    </span>
                    <span class="tls-don-patreon-titles">
                        <span class="tls-don-patreon-title">Become a member</span>
                        <span class="tls-don-patreon-sub">Ongoing support · from&nbsp;$5/month</span>
                    </span>
                </div>
                <p class="tls-don-patreon-text">
                    Join on Patreon to keep the archive free for everyone — the most
                    direct way to fund the next book, essay, and analysis.
                </p>
                <a class="tls-don-patreon-btn" href="<?php echo esc_url( $patreon_url ); ?>"
                   target="_blank" rel="noopener">
                    Join on Patreon
                    <svg class="tls-don-submit-arrow" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.5" width="15" height="15" aria-hidden="true">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </a>
            </div>

            <!-- Secondary divider -->
            <div class="tls-don-or"><span>or make a one-time gift</span></div>

            <!-- ① Amount -->
            <div class="tls-don-step-label">
                <span class="tls-don-step-num" aria-hidden="true">1</span>
                Choose an amount <span class="tls-don-currency">(USD)</span>
            </div>

            <div class="tls-don-amounts" role="group" aria-label="Donation amounts">
                <?php
                $presets = [ 5, 10, 25, 50, 100 ];
                foreach ( $presets as $amt ) :
                    $active = ( $amt === 25 ) ? ' active' : '';
                ?>
                <button class="tls-don-amt<?php echo $active; ?>"
                        type="button"
                        data-amount="<?php echo $amt; ?>"
                        aria-pressed="<?php echo $amt === 25 ? 'true' : 'false'; ?>">
                    $<?php echo $amt; ?>
                </button>
                <?php endforeach; ?>
                <button class="tls-don-amt tls-don-amt-other"
                        type="button"
                        data-amount="other"
                        aria-pressed="false">
                    $ Other
                </button>
            </div>

            <div class="tls-don-custom-wrap" id="tls-don-custom-wrap">
                <input class="tls-don-custom-input"
                       id="tls-don-custom-input"
                       type="number"
                       min="1"
                       placeholder="Enter amount"
                       aria-label="Custom donation amount in USD">
            </div>

            <label class="tls-don-fee-label">
                <input type="checkbox" id="tls-don-fee-chk">
                <span>
                    Add <strong id="tls-don-fee-display">$0.78</strong>
                    to cover processing fees
                </span>
            </label>

            <!-- ② Payment method -->
            <hr class="tls-don-divider">

            <div class="tls-don-step-label">
                <span class="tls-don-step-num" aria-hidden="true">2</span>
                Payment method
            </div>

            <div class="tls-don-tabs" role="tablist">
                <button class="tls-don-tab active"
                        type="button"
                        role="tab"
                        aria-selected="true"
                        aria-controls="tls-don-panel-shopier"
                        id="tls-tab-shopier">
                    <span class="tls-don-tab-name">Shopier</span>
                    <span class="tls-don-tab-sub">Local checkout</span>
                </button>
                <button class="tls-don-tab"
                        type="button"
                        role="tab"
                        aria-selected="false"
                        aria-controls="tls-don-panel-crypto"
                        id="tls-tab-crypto">
                    <span class="tls-don-tab-name">Crypto</span>
                    <span class="tls-don-tab-sub">BTC · ETH · USDC</span>
                </button>
            </div>

            <!-- Shopier panel -->
            <div class="tls-don-panel active"
                 id="tls-don-panel-shopier"
                 role="tabpanel"
                 aria-labelledby="tls-tab-shopier">
                <div class="tls-don-shopier-note">
                    <strong>Pay with card via Shopier.</strong><br>
                    You'll be taken to Shopier's secure checkout to complete your gift
                    with Visa, Mastercard, or Amex. No account required.
                </div>
            </div>

            <!-- Crypto panel -->
            <div class="tls-don-panel"
                 id="tls-don-panel-crypto"
                 role="tabpanel"
                 aria-labelledby="tls-tab-crypto">
                <?php if ( $btc || $eth || $usdc ) : ?>
                <div class="tls-don-crypto-grid">
                    <?php if ( $btc ) : ?>
                    <div class="tls-don-crypto-row">
                        <span class="tls-don-crypto-coin">Bitcoin (BTC)</span>
                        <span class="tls-don-crypto-addr"><?php echo esc_html( $btc ); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $eth ) : ?>
                    <div class="tls-don-crypto-row">
                        <span class="tls-don-crypto-coin">Ethereum (ETH)</span>
                        <span class="tls-don-crypto-addr"><?php echo esc_html( $eth ); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $usdc ) : ?>
                    <div class="tls-don-crypto-row">
                        <span class="tls-don-crypto-coin">USD Coin (USDC)</span>
                        <span class="tls-don-crypto-addr"><?php echo esc_html( $usdc ); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else : ?>
                <div class="tls-don-shopier-note">
                    Crypto addresses coming soon. Add your wallet addresses in
                    <strong>Customize → Support / Donations</strong>.
                </div>
                <?php endif; ?>
            </div>

            <!-- CTA -->
            <button class="tls-don-submit" type="button" id="tls-don-submit">
                Donate
                <svg class="tls-don-submit-arrow" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" width="15" height="15" aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                </svg>
            </button>

            <!-- Trust line -->
            <div class="tls-don-trust" aria-label="Security assurances">
                <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         width="12" height="12" aria-hidden="true">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    Secure payment
                </span>
                <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         width="12" height="12" aria-hidden="true">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                    Visa &amp; Mastercard
                </span>
                <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         width="12" height="12" aria-hidden="true">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    No account needed
                </span>
            </div>

        </div><!-- /.tls-don-card-body -->
    </div><!-- /.tls-don-card -->

</div><!-- /.tls-don-inner -->
</div><!-- /.tls-don-wrap -->
</main>

<script>
(function() {
    var shopierUrls = <?php echo $shopier_map_json; ?>;
    var selectedAmt = 25;
    var activeTab   = 'shopier';
    var FEE_RATE    = 0.029;
    var FEE_FIXED   = 0.30;

    var amtBtns     = document.querySelectorAll('.tls-don-amt');
    var customWrap  = document.getElementById('tls-don-custom-wrap');
    var customInput = document.getElementById('tls-don-custom-input');
    var feeChk      = document.getElementById('tls-don-fee-chk');
    var feeDisplay  = document.getElementById('tls-don-fee-display');
    var tabs        = document.querySelectorAll('.tls-don-tab');
    var panels      = document.querySelectorAll('.tls-don-panel');
    var submitBtn   = document.getElementById('tls-don-submit');

    function updateFee() {
        var amt = selectedAmt === 'other'
            ? (parseFloat(customInput.value) || 0)
            : selectedAmt;
        var fee = Math.max(0, amt * FEE_RATE + FEE_FIXED);
        if (feeDisplay) feeDisplay.textContent = '$' + fee.toFixed(2);
    }

    /* Amount buttons */
    amtBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            amtBtns.forEach(function(b) {
                b.classList.remove('active');
                b.setAttribute('aria-pressed', 'false');
            });
            btn.classList.add('active');
            btn.setAttribute('aria-pressed', 'true');
            selectedAmt = btn.dataset.amount === 'other' ? 'other' : parseInt(btn.dataset.amount);
            var isOther = (selectedAmt === 'other');
            customWrap.classList.toggle('visible', isOther);
            if (isOther) { customInput.focus(); }
            updateFee();
        });
    });

    if (customInput) {
        customInput.addEventListener('input', updateFee);
    }
    if (feeChk) { feeChk.addEventListener('change', updateFee); }
    updateFee();

    /* Payment tabs */
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            tabs.forEach(function(t) {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            panels.forEach(function(p) { p.classList.remove('active'); });
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');
            var panelId = tab.getAttribute('aria-controls');
            var panel   = document.getElementById(panelId);
            if (panel) { panel.classList.add('active'); }
            activeTab = panelId.includes('crypto') ? 'crypto' : 'shopier';
        });
    });

    /* Donate button */
    if (submitBtn) {
        submitBtn.addEventListener('click', function() {
            if (activeTab === 'crypto') {
                /* Scroll to / highlight wallet addresses — no redirect needed */
                var panel = document.getElementById('tls-don-panel-crypto');
                if (panel) { panel.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                return;
            }
            /* Shopier flow */
            var amt = selectedAmt === 'other'
                ? (parseInt(customInput.value) || 0)
                : selectedAmt;
            var url = shopierUrls[amt] || '';
            if (url && url !== '#' && url !== '') {
                window.location.href = url;
            } else {
                /* URL henüz eklenmemiş — Customize'da ekle */
                submitBtn.textContent = 'Coming soon — check back shortly!';
                submitBtn.disabled = true;
                setTimeout(function() {
                    submitBtn.innerHTML = 'Donate <svg class="tls-don-submit-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
                    submitBtn.disabled = false;
                }, 3000);
            }
        });
    }
})();
</script>

<?php get_footer(); ?>
