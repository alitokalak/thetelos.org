<?php
/**
 * Template Name: Login & Register
 * Template Post Type: page
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Zaten giriş yapmışsa kütüphaneye yönlendir
if ( is_user_logged_in() ) {
    wp_redirect( home_url('/library/') );
    exit;
}

get_header();
$nonce = wp_create_nonce('tls_auth_nonce');
?>
<main id="main" role="main" class="tls-auth-page">
<div class="tls-auth-wrap">

    <!-- Logo & başlık -->
    <div class="tls-auth-brand">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="tls-auth-logo">
            <?php bloginfo('name'); ?>
        </a>
        <p class="tls-auth-tagline">The Digital Archive of Great Books</p>
    </div>

    <!-- Tab switch -->
    <div class="tls-auth-tabs">
        <button class="tls-auth-tab active" data-tab="login">Sign In</button>
        <button class="tls-auth-tab" data-tab="register">Create Account</button>
    </div>

    <!-- Error / Success -->
    <div id="tls-auth-msg" class="tls-auth-msg" style="display:none;"></div>

    <!-- LOGIN FORMU -->
    <div class="tls-auth-form" id="tls-tab-login">
        <div class="tls-auth-field">
            <label class="tls-auth-label">Email</label>
            <input class="tls-auth-input" type="email" id="login-email" placeholder="you@example.com" autocomplete="email">
        </div>
        <div class="tls-auth-field">
            <label class="tls-auth-label">Password</label>
            <div class="tls-auth-input-wrap">
                <input class="tls-auth-input" type="password" id="login-pass" placeholder="••••••••" autocomplete="current-password">
                <button type="button" class="tls-pw-toggle" data-target="login-pass" tabindex="-1" aria-label="Show password">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>
        <div class="tls-auth-row">
            <label class="tls-auth-check"><input type="checkbox" id="login-remember"> Remember me</label>
            <button type="button" class="tls-auth-link" id="tls-show-forgot">Forgot password?</button>
        </div>
        <button class="tls-auth-submit" id="tls-login-btn" type="button">
            <span class="tls-auth-btn-txt">Sign In</span>
            <span class="tls-auth-btn-spin" style="display:none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16" style="animation:tlsAuthSpin .7s linear infinite;"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
            </span>
        </button>
    </div>

    <!-- KAYIT FORMU -->
    <div class="tls-auth-form" id="tls-tab-register" style="display:none;">
        <div class="tls-auth-field">
            <label class="tls-auth-label">Your Name</label>
            <input class="tls-auth-input" type="text" id="reg-name" placeholder="Marcus Aurelius" autocomplete="name">
        </div>
        <div class="tls-auth-field">
            <label class="tls-auth-label">Email</label>
            <input class="tls-auth-input" type="email" id="reg-email" placeholder="you@example.com" autocomplete="email">
        </div>
        <div class="tls-auth-field">
            <label class="tls-auth-label">Password <span style="color:#aaa;font-weight:400;">(min. 6 characters)</span></label>
            <div class="tls-auth-input-wrap">
                <input class="tls-auth-input" type="password" id="reg-pass" placeholder="••••••••" autocomplete="new-password">
                <button type="button" class="tls-pw-toggle" data-target="reg-pass" tabindex="-1" aria-label="Show password">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>
        <p class="tls-auth-privacy">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            Your data is private and never shared with third parties.
        </p>
        <!-- Honeypot -->
        <div style="position:absolute;left:-9999px;opacity:0;height:0;" aria-hidden="true">
            <input type="text" name="tls_hp" id="reg-hp" tabindex="-1" autocomplete="off">
        </div>
        <button class="tls-auth-submit" id="tls-reg-btn" type="button">
            <span class="tls-auth-btn-txt">Create Account</span>
            <span class="tls-auth-btn-spin" style="display:none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16" style="animation:tlsAuthSpin .7s linear infinite;"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
            </span>
        </button>
    </div>

    <!-- ŞİFRE SIFIRLAMA -->
    <div class="tls-auth-form" id="tls-tab-forgot" style="display:none;">
        <p style="font-size:14px;color:#666;margin:0 0 20px;line-height:1.6;">Enter your email and we'll send you a link to reset your password.</p>
        <div class="tls-auth-field">
            <label class="tls-auth-label">Email</label>
            <input class="tls-auth-input" type="email" id="forgot-email" placeholder="you@example.com">
        </div>
        <button class="tls-auth-submit" id="tls-forgot-btn" type="button">
            <span class="tls-auth-btn-txt">Send Reset Link</span>
            <span class="tls-auth-btn-spin" style="display:none;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16" style="animation:tlsAuthSpin .7s linear infinite;"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg></span>
        </button>
        <button type="button" class="tls-auth-link" id="tls-back-login" style="display:block;margin-top:12px;text-align:center;">← Back to Sign In</button>
    </div>

</div><!-- /.tls-auth-wrap -->
</main>

<script>
(function(){
    var ajax  = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
    var nonce = '<?php echo esc_js($nonce); ?>';

    /* ── Tab geçişi ── */
    document.querySelectorAll('.tls-auth-tab').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('.tls-auth-tab').forEach(function(b){ b.classList.remove('active'); });
            btn.classList.add('active');
            var tab = btn.dataset.tab;
            showForm(tab === 'login' ? 'tls-tab-login' : 'tls-tab-register');
            clearMsg();
        });
    });
    document.getElementById('tls-show-forgot').addEventListener('click', function(){ showForm('tls-tab-forgot'); clearMsg(); });
    document.getElementById('tls-back-login').addEventListener('click', function(){ showForm('tls-tab-login'); clearMsg(); });

    function showForm(id) {
        ['tls-tab-login','tls-tab-register','tls-tab-forgot'].forEach(function(f){
            var el = document.getElementById(f);
            if (el) el.style.display = f === id ? '' : 'none';
        });
    }

    /* ── Mesaj ── */
    function showMsg(msg, type) {
        var el = document.getElementById('tls-auth-msg');
        el.textContent = msg;
        el.className = 'tls-auth-msg tls-auth-msg--' + type;
        el.style.display = 'block';
    }
    function clearMsg() {
        var el = document.getElementById('tls-auth-msg');
        el.style.display = 'none';
    }

    /* ── Loading state ── */
    function setLoading(btnId, loading) {
        var btn = document.getElementById(btnId);
        btn.disabled = loading;
        btn.querySelector('.tls-auth-btn-txt').style.display  = loading ? 'none'         : 'inline';
        btn.querySelector('.tls-auth-btn-spin').style.display = loading ? 'inline-flex'  : 'none';
    }

    /* ── Şifre göster/gizle ── */
    document.querySelectorAll('.tls-pw-toggle').forEach(function(btn){
        btn.addEventListener('click', function(){
            var inp = document.getElementById(btn.dataset.target);
            inp.type = inp.type === 'password' ? 'text' : 'password';
        });
    });

    /* ── AJAX yardımcı ── */
    function doPost(action, data, onSuccess, btnId) {
        setLoading(btnId, true);
        clearMsg();
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        for (var k in data) fd.append(k, data[k]);
        fetch(ajax, { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){
            setLoading(btnId, false);
            if (res.success) { onSuccess(res.data); }
            else { showMsg(res.data.message || 'Something went wrong.', 'error'); }
        })
        .catch(function(){
            setLoading(btnId, false);
            showMsg('Network error. Please try again.', 'error');
        });
    }

    /* ── Giriş ── */
    document.getElementById('tls-login-btn').addEventListener('click', function(){
        var email = document.getElementById('login-email').value.trim();
        var pass  = document.getElementById('login-pass').value;
        var rem   = document.getElementById('login-remember').checked ? '1' : '';
        if (!email || !pass) { showMsg('Please enter your email and password.', 'error'); return; }
        doPost('tls_login', {tls_email:email, tls_pass:pass, tls_remember:rem}, function(data){
            showMsg('Welcome back! Redirecting…', 'success');
            setTimeout(function(){ window.location.href = data.redirect; }, 800);
        }, 'tls-login-btn');
    });

    /* ── Kayıt ── */
    document.getElementById('tls-reg-btn').addEventListener('click', function(){
        var name  = document.getElementById('reg-name').value.trim();
        var email = document.getElementById('reg-email').value.trim();
        var pass  = document.getElementById('reg-pass').value;
        var hp    = document.getElementById('reg-hp').value;
        if (!name || !email || !pass) { showMsg('Please fill in all fields.', 'error'); return; }
        if (pass.length < 6) { showMsg('Password must be at least 6 characters.', 'error'); return; }
        doPost('tls_register', {tls_name:name, tls_email:email, tls_pass:pass, tls_hp:hp}, function(data){
            showMsg('Account created! Setting up your library…', 'success');
            setTimeout(function(){ window.location.href = data.redirect; }, 1000);
        }, 'tls-reg-btn');
    });

    /* ── Şifre sıfırla ── */
    document.getElementById('tls-forgot-btn').addEventListener('click', function(){
        var email = document.getElementById('forgot-email').value.trim();
        if (!email) { showMsg('Please enter your email.', 'error'); return; }
        doPost('tls_forgot', {tls_email:email}, function(data){
            showMsg(data.message, 'success');
        }, 'tls-forgot-btn');
    });

    /* ── Enter tuşu ── */
    document.addEventListener('keydown', function(e){
        if (e.key !== 'Enter') return;
        var active = document.querySelector('.tls-auth-form:not([style*="display:none"])');
        if (!active) return;
        var btn = active.querySelector('.tls-auth-submit');
        if (btn) btn.click();
    });
})();
</script>

<?php get_footer(); ?>
