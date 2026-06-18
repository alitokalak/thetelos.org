<?php
/**
 * Template Name: Support Us
 * Template Post Type: page
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* Polar — ana destek yöntemi — Customize → Support / Donations
   Global kart ödemesi, hesap gerektirmez, Türkiye'ye ödeme yapar. */
$polar_url = get_theme_mod( 'tls_polar_url', 'https://buy.polar.sh/polar_cl_lo5vNnnTnFOlDvluWfQn62s5zSQq1AdVjTZzr1LqyE6' );

/* Kripto adresleri — opsiyonel ikincil yöntem — Customize → Support / Donations */
$btc  = get_theme_mod( 'tls_crypto_btc',  '' );
$eth  = get_theme_mod( 'tls_crypto_eth',  '' );
$usdc = get_theme_mod( 'tls_crypto_usdc', '' );
$has_crypto = ( $btc || $eth || $usdc );

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
            If the work has given you something, please consider supporting it.
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

            <label class="tls-don-fee-label" id="tls-don-fee-label">
                <input type="checkbox" id="tls-don-fee-chk">
                <span>
                    Add <strong id="tls-don-fee-display">$0.78</strong>
                    to cover processing fees
                </span>
            </label>

            <!-- ② Pay — Polar embedded checkout (card, no account) -->
            <a class="tls-don-submit"
               id="tls-don-submit"
               href="<?php echo esc_url( $polar_url ); ?>"
               data-polar-checkout
               data-polar-checkout-theme="dark">
                Support thetelos
                <svg class="tls-don-submit-arrow" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" width="15" height="15" aria-hidden="true">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                </svg>
            </a>

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

            <?php if ( $has_crypto ) : ?>
            <!-- Optional · crypto -->
            <details class="tls-don-crypto-details">
                <summary>Prefer crypto?</summary>
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
            </details>
            <?php endif; ?>

        </div><!-- /.tls-don-card-body -->
    </div><!-- /.tls-don-card -->

</div><!-- /.tls-don-inner -->
</div><!-- /.tls-don-wrap -->
</main>

<script>
(function() {
    var BASE_URL  = <?php echo json_encode( esc_url( $polar_url ) ); ?>;
    var selectedAmt = 25;
    var FEE_RATE    = 0.029;
    var FEE_FIXED   = 0.30;

    var amtBtns     = document.querySelectorAll('.tls-don-amt');
    var customWrap  = document.getElementById('tls-don-custom-wrap');
    var customInput = document.getElementById('tls-don-custom-input');
    var feeChk      = document.getElementById('tls-don-fee-chk');
    var feeDisplay  = document.getElementById('tls-don-fee-display');
    var submitBtn   = document.getElementById('tls-don-submit');

    /* Effective donation amount in dollars (incl. optional fee) */
    function totalDollars() {
        var amt = selectedAmt === 'other'
            ? (parseFloat(customInput.value) || 0)
            : selectedAmt;
        if (amt <= 0) { return 0; }
        if (feeChk && feeChk.checked) {
            amt += amt * FEE_RATE + FEE_FIXED;
        }
        return amt;
    }

    function updateFee() {
        var base = selectedAmt === 'other'
            ? (parseFloat(customInput.value) || 0)
            : selectedAmt;
        var fee = Math.max(0, base * FEE_RATE + FEE_FIXED);
        if (feeDisplay) { feeDisplay.textContent = '$' + fee.toFixed(2); }
    }

    /* Point the Polar checkout button at the chosen amount (?amount= in cents) */
    function updateCheckoutLink() {
        if (!submitBtn) { return; }
        var dollars = totalDollars();
        var url = BASE_URL;
        if (dollars > 0) {
            var cents = Math.round(dollars * 100);
            url += (url.indexOf('?') === -1 ? '?' : '&') + 'amount=' + cents;
        }
        submitBtn.href = url;
    }

    function refresh() { updateFee(); updateCheckoutLink(); }

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
            refresh();
        });
    });

    if (customInput) { customInput.addEventListener('input', refresh); }
    if (feeChk)      { feeChk.addEventListener('change', refresh); }
    refresh();
})();
</script>

<!-- Polar embedded checkout (opens an overlay on this page) -->
<script defer data-auto-init
        src="https://cdn.jsdelivr.net/npm/@polar-sh/checkout@latest/dist/embed.global.js"></script>

<?php get_footer(); ?>
