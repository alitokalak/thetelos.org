<?php
/**
 * The Telos — Password Reset Page
 * /reset-password/?key=KEY&login=LOGIN
 * membership.php'deki template_redirect ile yüklenir.
 *
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$rp_key   = isset( $_GET['key'] )   ? sanitize_text_field( wp_unslash( $_GET['key'] ) )   : '';
$rp_login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';

$state   = 'form';   // form | success | error
$error   = '';
$user    = null;

/* ── POST: şifre sıfırla ─────────────────────────────── */
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tls_rp_nonce'] ) ) {

    if ( ! wp_verify_nonce( $_POST['tls_rp_nonce'], 'tls_reset_password' ) ) {
        $state = 'error';
        $error = 'Security check failed. Please request a new reset link.';
    } else {
        $post_key   = sanitize_text_field( wp_unslash( $_POST['rp_key']   ?? '' ) );
        $post_login = sanitize_text_field( wp_unslash( $_POST['rp_login'] ?? '' ) );
        $pass1      = $_POST['pass1'] ?? '';
        $pass2      = $_POST['pass2'] ?? '';

        $user = check_password_reset_key( $post_key, $post_login );

        if ( is_wp_error( $user ) ) {
            $state = 'error';
            $error = 'This reset link has expired or is invalid. Please request a new one.';
        } elseif ( strlen( $pass1 ) < 6 ) {
            $state = 'form';
            $error = 'Password must be at least 6 characters.';
            $rp_key   = $post_key;
            $rp_login = $post_login;
        } elseif ( $pass1 !== $pass2 ) {
            $state = 'form';
            $error = 'Passwords do not match.';
            $rp_key   = $post_key;
            $rp_login = $post_login;
        } else {
            reset_password( $user, $pass1 );
            $state = 'success';
        }
    }

/* ── GET: anahtarı doğrula ───────────────────────────── */
} elseif ( $rp_key && $rp_login ) {
    $user = check_password_reset_key( $rp_key, $rp_login );
    if ( is_wp_error( $user ) ) {
        $state = 'error';
        $error = 'This reset link has expired or is invalid. Please request a new one.';
    }
} else {
    $state = 'error';
    $error = 'No reset key provided. Please request a new password reset link.';
}

get_header();
?>
<main id="main" role="main" class="tls-auth-page">
<div class="tls-auth-wrap">

    <!-- Brand -->
    <div class="tls-auth-brand">
        <a href="<?php echo esc_url( home_url('/') ); ?>" class="tls-auth-logo">
            <?php bloginfo('name'); ?>
        </a>
        <p class="tls-auth-tagline">The Digital Archive of Great Books</p>
    </div>

    <?php if ( $state === 'success' ) : ?>
    <!-- ── SUCCESS ──────────────────────────────────── -->
    <div class="tls-rp-success">
        <div class="tls-rp-success-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32" height="32" aria-hidden="true">
                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>
        <h2 class="tls-rp-success-title">Password updated</h2>
        <p class="tls-rp-success-desc">
            Your password has been reset successfully. You can now sign in with your new password.
        </p>
        <a class="tls-auth-submit" href="<?php echo esc_url( home_url('/login/') ); ?>" style="display:block;text-align:center;text-decoration:none;margin-top:24px;">
            Sign In &rarr;
        </a>
    </div>

    <?php elseif ( $state === 'error' ) : ?>
    <!-- ── ERROR ────────────────────────────────────── -->
    <div class="tls-rp-error">
        <div class="tls-rp-error-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32" height="32" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
        </div>
        <h2 class="tls-rp-error-title">Link expired</h2>
        <p class="tls-rp-error-desc"><?php echo esc_html( $error ); ?></p>
        <a class="tls-auth-submit" href="<?php echo esc_url( home_url('/login/') ); ?>" style="display:block;text-align:center;text-decoration:none;margin-top:24px;">
            Request a new link
        </a>
    </div>

    <?php else : ?>
    <!-- ── FORM ─────────────────────────────────────── -->
    <div class="tls-auth-tabs" style="margin-bottom:8px;">
        <span class="tls-auth-tab active" style="cursor:default;">Reset Password</span>
    </div>

    <?php if ( $error ) : ?>
    <div class="tls-auth-msg tls-auth-msg--error" style="display:block;margin-bottom:16px;">
        <?php echo esc_html( $error ); ?>
    </div>
    <?php endif; ?>

    <form method="post" action="" class="tls-auth-form" id="tls-rp-form">
        <?php wp_nonce_field( 'tls_reset_password', 'tls_rp_nonce' ); ?>
        <input type="hidden" name="rp_key"   value="<?php echo esc_attr( $rp_key ); ?>">
        <input type="hidden" name="rp_login" value="<?php echo esc_attr( $rp_login ); ?>">

        <div class="tls-auth-field">
            <label class="tls-auth-label" for="pass1">New Password
                <span style="color:#aaa;font-weight:400;">(min. 6 chars)</span>
            </label>
            <div class="tls-auth-input-wrap">
                <input class="tls-auth-input" type="password" id="pass1" name="pass1"
                       placeholder="••••••••" autocomplete="new-password" required>
                <button type="button" class="tls-pw-toggle" data-target="pass1" aria-label="Show password">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>

        <div class="tls-auth-field">
            <label class="tls-auth-label" for="pass2">Confirm New Password</label>
            <div class="tls-auth-input-wrap">
                <input class="tls-auth-input" type="password" id="pass2" name="pass2"
                       placeholder="••••••••" autocomplete="new-password" required>
                <button type="button" class="tls-pw-toggle" data-target="pass2" aria-label="Show password">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>

        <!-- Strength indicator -->
        <div id="tls-pw-strength" style="margin:-8px 0 16px;height:4px;background:#E2DECE;border-radius:2px;overflow:hidden;">
            <div id="tls-pw-strength-bar" style="height:100%;width:0;transition:width .25s,background .25s;border-radius:2px;"></div>
        </div>

        <button class="tls-auth-submit" type="submit" id="tls-rp-submit">
            Set New Password
        </button>

        <a href="<?php echo esc_url( home_url('/login/') ); ?>"
           class="tls-auth-link"
           style="display:block;margin-top:14px;text-align:center;">
            &larr; Back to Sign In
        </a>
    </form>
    <?php endif; ?>

</div>
</main>

<style>
/* ── Reset page extras ─────────────────────────── */
.tls-rp-success,
.tls-rp-error {
    text-align: center;
    padding: 8px 0 4px;
}
.tls-rp-success-icon svg { color: var(--tls-green); }
.tls-rp-error-icon   svg { color: var(--tls-gold);  }
.tls-rp-success-icon,
.tls-rp-error-icon {
    display: flex;
    justify-content: center;
    margin-bottom: 16px;
}
.tls-rp-success-title,
.tls-rp-error-title {
    font-family: var(--tls-serif);
    font-size: 22px;
    color: var(--tls-bg-dark);
    margin: 0 0 10px;
}
.tls-rp-success-desc,
.tls-rp-error-desc {
    font-family: var(--tls-sans);
    font-size: 14px;
    color: var(--tls-muted);
    line-height: 1.65;
    margin: 0;
}
</style>

<script>
(function () {
    /* Şifre göster/gizle */
    document.querySelectorAll('.tls-pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var inp = document.getElementById(btn.dataset.target);
            inp.type = inp.type === 'password' ? 'text' : 'password';
        });
    });

    /* Şifre güç göstergesi */
    var p1 = document.getElementById('pass1');
    var bar = document.getElementById('tls-pw-strength-bar');
    if (p1 && bar) {
        p1.addEventListener('input', function () {
            var v = p1.value, score = 0;
            if (v.length >= 6)  score++;
            if (v.length >= 10) score++;
            if (/[A-Z]/.test(v)) score++;
            if (/[0-9]/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            var pct   = [0, 25, 50, 75, 100][score] || 0;
            var color = ['#E2DECE','#e67e22','#f1c40f','#B8922A','#00ab6b'][score] || '#E2DECE';
            bar.style.width      = pct + '%';
            bar.style.background = color;
        });
    }

    /* Submit - basit doğrulama */
    var form = document.getElementById('tls-rp-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            var p1val = (document.getElementById('pass1') || {}).value || '';
            var p2val = (document.getElementById('pass2') || {}).value || '';
            var btn   = document.getElementById('tls-rp-submit');
            if (p1val.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters.');
                return;
            }
            if (p1val !== p2val) {
                e.preventDefault();
                alert('Passwords do not match.');
                return;
            }
            if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
        });
    }
})();
</script>

<?php get_footer(); ?>
