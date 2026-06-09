<?php
/**
 * Auth page — /login/
 * Custom rewrite ile yüklenir, WordPress sayfası gerektirmez.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$nonce = wp_create_nonce('tls_auth_nonce');
get_header();
?>
<main id="main" role="main" class="tls-auth-page">
<div class="tls-auth-wrap">

    <div class="tls-auth-brand">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="tls-auth-logo"><?php bloginfo('name'); ?></a>
        <p class="tls-auth-tagline">The Digital Archive of Great Books</p>
    </div>

    <div class="tls-auth-tabs">
        <button class="tls-auth-tab active" data-tab="login">Sign In</button>
        <button class="tls-auth-tab" data-tab="register">Create Account</button>
    </div>

    <div id="tls-auth-msg" class="tls-auth-msg" style="display:none;"></div>

    <!-- LOGIN -->
    <div class="tls-auth-form" id="tls-tab-login">
        <div class="tls-auth-field">
            <label class="tls-auth-label">Email</label>
            <input class="tls-auth-input" type="email" id="login-email" placeholder="you@example.com" autocomplete="email">
        </div>
        <div class="tls-auth-field">
            <label class="tls-auth-label">Password</label>
            <div class="tls-auth-input-wrap">
                <input class="tls-auth-input" type="password" id="login-pass" placeholder="••••••••" autocomplete="current-password">
                <button type="button" class="tls-pw-toggle" data-target="login-pass" aria-label="Show password">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>
        <div class="tls-auth-row">
            <label class="tls-auth-check"><input type="checkbox" id="login-rem"> Remember me</label>
            <button type="button" class="tls-auth-link" id="show-forgot">Forgot password?</button>
        </div>
        <button class="tls-auth-submit" id="btn-login" type="button">Sign In</button>
    </div>

    <!-- REGISTER -->
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
            <label class="tls-auth-label">Password <span style="color:#aaa;font-weight:400;">(min. 6 chars)</span></label>
            <div class="tls-auth-input-wrap">
                <input class="tls-auth-input" type="password" id="reg-pass" placeholder="••••••••" autocomplete="new-password">
                <button type="button" class="tls-pw-toggle" data-target="reg-pass" aria-label="Show password">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>
        <p class="tls-auth-privacy">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            Your data is private and never shared.
        </p>
        <div style="position:absolute;left:-9999px;opacity:0;height:0;" aria-hidden="true">
            <input type="text" id="reg-hp" tabindex="-1" autocomplete="off">
        </div>
        <button class="tls-auth-submit" id="btn-register" type="button">Create Account</button>
    </div>

    <!-- FORGOT -->
    <div class="tls-auth-form" id="tls-tab-forgot" style="display:none;">
        <p style="font-size:14px;color:#666;margin:0 0 18px;line-height:1.6;">Enter your email to receive a password reset link.</p>
        <div class="tls-auth-field">
            <label class="tls-auth-label">Email</label>
            <input class="tls-auth-input" type="email" id="forgot-email" placeholder="you@example.com">
        </div>
        <button class="tls-auth-submit" id="btn-forgot" type="button">Send Reset Link</button>
        <button type="button" class="tls-auth-link" id="back-login" style="display:block;margin-top:12px;text-align:center;">← Back to Sign In</button>
    </div>

</div>
</main>

<script>
(function(){
    var ajax  = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
    var nonce = '<?php echo esc_js($nonce); ?>';

    /* Tab */
    document.querySelectorAll('.tls-auth-tab').forEach(function(b){
        b.addEventListener('click', function(){
            document.querySelectorAll('.tls-auth-tab').forEach(function(x){x.classList.remove('active');});
            b.classList.add('active');
            show(b.dataset.tab === 'login' ? 'tls-tab-login' : 'tls-tab-register');
            msg('','');
        });
    });
    document.getElementById('show-forgot').addEventListener('click', function(){ show('tls-tab-forgot'); msg('',''); });
    document.getElementById('back-login').addEventListener('click', function(){ show('tls-tab-login'); msg('',''); });

    function show(id){
        ['tls-tab-login','tls-tab-register','tls-tab-forgot'].forEach(function(f){
            document.getElementById(f).style.display = f===id?'':'none';
        });
    }
    function msg(text, type){
        var el = document.getElementById('tls-auth-msg');
        if(!text){el.style.display='none';return;}
        el.textContent = text;
        el.className = 'tls-auth-msg tls-auth-msg--'+type;
        el.style.display = 'block';
    }
    function setBtn(id, loading){
        var b = document.getElementById(id);
        b.disabled = loading;
        b.textContent = loading ? '…' : b.dataset.orig || b.textContent;
        if(!b.dataset.orig && !loading) b.dataset.orig = b.textContent;
    }
    function post(action, data, cb, btnId){
        if(btnId){ var b=document.getElementById(btnId); b.dataset.orig=b.textContent; setBtn(btnId,true); }
        msg('','');
        var fd = new FormData();
        fd.append('action',action); fd.append('nonce',nonce);
        for(var k in data) fd.append(k,data[k]);
        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(res){
            if(btnId) setBtn(btnId,false);
            if(res.success) cb(res.data);
            else msg(res.data.message||'Something went wrong.','error');
        })
        .catch(function(){ if(btnId) setBtn(btnId,false); msg('Network error.','error'); });
    }

    /* Şifre göster */
    document.querySelectorAll('.tls-pw-toggle').forEach(function(b){
        b.addEventListener('click', function(){
            var i=document.getElementById(b.dataset.target);
            i.type=i.type==='password'?'text':'password';
        });
    });

    /* Login */
    document.getElementById('btn-login').addEventListener('click', function(){
        var email=document.getElementById('login-email').value.trim();
        var pass=document.getElementById('login-pass').value;
        var rem=document.getElementById('login-rem').checked?'1':'';
        if(!email||!pass){msg('Please enter your email and password.','error');return;}
        post('tls_login',{tls_email:email,tls_pass:pass,tls_remember:rem},function(d){
            msg('Welcome back! Redirecting…','success');
            setTimeout(function(){window.location.href=d.redirect;},700);
        },'btn-login');
    });

    /* Register */
    document.getElementById('btn-register').addEventListener('click', function(){
        var name=document.getElementById('reg-name').value.trim();
        var email=document.getElementById('reg-email').value.trim();
        var pass=document.getElementById('reg-pass').value;
        var hp=document.getElementById('reg-hp').value;
        if(!name||!email||!pass){msg('Please fill in all fields.','error');return;}
        if(pass.length<6){msg('Password must be at least 6 characters.','error');return;}
        post('tls_register',{tls_name:name,tls_email:email,tls_pass:pass,tls_hp:hp},function(d){
            msg('Account created! Redirecting…','success');
            setTimeout(function(){window.location.href=d.redirect;},700);
        },'btn-register');
    });

    /* Forgot */
    document.getElementById('btn-forgot').addEventListener('click', function(){
        var email=document.getElementById('forgot-email').value.trim();
        if(!email){msg('Please enter your email.','error');return;}
        post('tls_forgot',{tls_email:email},function(d){ msg(d.message,'success'); },'btn-forgot');
    });

    /* Enter */
    document.addEventListener('keydown',function(e){
        if(e.key!=='Enter')return;
        var active=document.querySelector('.tls-auth-form:not([style*="display:none"])');
        if(active){var b=active.querySelector('.tls-auth-submit');if(b)b.click();}
    });
})();
</script>
<?php get_footer(); ?>
