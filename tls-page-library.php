<?php
/**
 * Library page — /library/
 * Custom rewrite ile yüklenir.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$user     = wp_get_current_user();
$initials = strtoupper(substr($user->display_name, 0, 1));
$auth_nonce   = wp_create_nonce('tls_auth_nonce');
$status_nonce = wp_create_nonce('tls_status_nonce');
get_header();
?>
<main id="main" role="main">

<div class="tls-lib-hero">
    <div class="container">
        <div class="tls-lib-hero-inner">
            <div class="tls-lib-avatar"><?php echo esc_html($initials); ?></div>
            <div>
                <h1 class="tls-lib-name"><?php echo esc_html($user->display_name); ?>'s Library</h1>
                <p class="tls-lib-email"><?php echo esc_html($user->user_email); ?></p>
            </div>
            <div class="tls-lib-hero-actions">
                <button class="tls-status-btn" id="tls-edit-btn" type="button">Edit Profile</button>
                <button class="tls-status-btn" id="tls-logout-btn" type="button">Sign Out</button>
            </div>
        </div>
    </div>
</div>

<div class="tls-lib-tabs-wrap">
    <div class="container">
        <div class="tls-lib-tabs">
            <button class="tls-lib-tab active" data-filter="all">All Books</button>
            <button class="tls-lib-tab" data-filter="want">Want to Read</button>
            <button class="tls-lib-tab" data-filter="reading">Reading</button>
            <button class="tls-lib-tab" data-filter="read">Finished</button>
        </div>
    </div>
</div>

<div class="tls-lib-body">
    <div class="container">
        <div id="tls-lib-loading" class="tls-lib-loading">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28"
                 style="animation:tlsAuthSpin .8s linear infinite;color:var(--tls-gold);">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
            </svg>
            Loading your library…
        </div>
        <div id="tls-lib-grid" class="tls-lib-grid" style="display:none;"></div>
        <div id="tls-lib-empty" class="tls-lib-empty" style="display:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48" style="color:var(--tls-border);"><path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
            <h3>Your library is empty</h3>
            <p>Browse the archive and mark books as "Want to Read", "Reading", or "Finished".</p>
            <a class="tls-botw-cta" href="<?php echo esc_url(home_url('/archive/')); ?>" style="margin-top:8px;">Browse the Archive →</a>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div id="tls-prof-overlay" style="display:none;" role="dialog">
    <div id="tls-prof-modal">
        <button id="tls-prof-close">&times;</button>
        <h2 style="font-family:var(--tls-serif);font-size:22px;font-weight:400;margin:0 0 20px;">Edit Profile</h2>
        <div id="tls-prof-msg" style="display:none;padding:8px 12px;border-radius:6px;font-size:13px;margin-bottom:14px;"></div>
        <div class="tls-req-fields">
            <div class="tls-req-field">
                <label class="tls-req-label">Display Name</label>
                <input class="tls-req-input" type="text" id="prof-name" value="<?php echo esc_attr($user->display_name); ?>">
            </div>
            <div class="tls-req-field">
                <label class="tls-req-label">Current Password <span class="tls-req-optional">(required to change)</span></label>
                <input class="tls-req-input" type="password" id="prof-old" placeholder="••••••••">
            </div>
            <div class="tls-req-field">
                <label class="tls-req-label">New Password <span class="tls-req-optional">(leave blank to keep)</span></label>
                <input class="tls-req-input" type="password" id="prof-new" placeholder="••••••••">
            </div>
        </div>
        <button class="tls-req-submit" id="btn-save-prof" type="button" style="margin-top:20px;width:100%;">Save Changes</button>
    </div>
</div>

<style>
#tls-prof-overlay {
    position:fixed;inset:0;z-index:99999;
    background:rgba(20,18,14,.65);backdrop-filter:blur(4px);
    align-items:center;justify-content:center;padding:16px;
}
#tls-prof-modal {
    background:#fff;border-radius:14px;width:100%;max-width:440px;
    padding:32px 28px 24px;position:relative;
    box-shadow:0 24px 64px rgba(0,0,0,.22);
}
#tls-prof-close {
    position:absolute;top:14px;right:16px;
    background:none;border:none;font-size:22px;color:#aaa;cursor:pointer;
    padding:4px 8px;border-radius:6px;
}
#tls-prof-close:hover{color:#333;background:#f4f4f4;}
</style>

<script>
(function(){
    var ajax       = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
    var authNonce  = '<?php echo esc_js($auth_nonce); ?>';
    var statusColors = {want:'#b84c6e', reading:'var(--tls-gold)', read:'var(--tls-green)'};
    var statusLabels = {want:'Want to Read', reading:'Currently Reading', read:'Finished'};

    function scaledCover(html, w, h) {
        var s = w / 160;
        return '<div style="width:'+w+'px;height:'+h+'px;position:relative;overflow:hidden;border-radius:4px;flex-shrink:0;box-shadow:0 4px 12px rgba(0,0,0,.2);">'
            + '<div style="position:absolute;top:0;left:0;transform:scale('+s+');transform-origin:top left;width:160px;height:220px;pointer-events:none;">'
            + html + '</div></div>';
    }

    function loadLibrary(filter){
        document.getElementById('tls-lib-loading').style.display = 'flex';
        document.getElementById('tls-lib-grid').style.display    = 'none';
        document.getElementById('tls-lib-empty').style.display   = 'none';
        var fd=new FormData(); fd.append('action','tls_get_library'); fd.append('filter',filter);
        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(res){
            document.getElementById('tls-lib-loading').style.display='none';
            if(!res.success||!res.data.books.length){
                document.getElementById('tls-lib-empty').style.display='flex'; return;
            }
            var grid=document.getElementById('tls-lib-grid');
            grid.innerHTML=res.data.books.map(function(b){
                return '<div class="tls-lib-item">'+
                    '<a href="'+b.url+'" class="tls-lib-item-cover">'+
                    (b.cover
                        ? '<img src="'+b.cover+'" alt="'+b.title+'">'
                        : b.cover_html ? scaledCover(b.cover_html, 50, 70) : '')+
                    '</a>'+
                    '<div class="tls-lib-item-body">'+
                    '<span class="tls-lib-status-badge" style="background:'+statusColors[b.status]+'">'+statusLabels[b.status]+'</span>'+
                    '<a href="'+b.url+'" class="tls-lib-item-title">'+b.title+'</a>'+
                    (b.author?'<p class="tls-lib-item-author">'+b.author+'</p>':'')+
                    (b.category?'<span class="tls-lib-item-cat">'+b.category+'</span>':'')+
                    '</div></div>';
            }).join('');
            grid.style.display='grid';
        });
    }

    document.querySelectorAll('.tls-lib-tab').forEach(function(btn){
        btn.addEventListener('click',function(){
            document.querySelectorAll('.tls-lib-tab').forEach(function(b){b.classList.remove('active');});
            btn.classList.add('active');
            loadLibrary(btn.dataset.filter);
        });
    });
    loadLibrary('all');

    /* Logout */
    document.getElementById('tls-logout-btn').addEventListener('click',function(){
        var fd=new FormData(); fd.append('action','tls_logout'); fd.append('nonce',authNonce);
        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(res){ if(res.success) window.location.href=res.data.redirect; });
    });

    /* Profile modal */
    var ov=document.getElementById('tls-prof-overlay');
    document.getElementById('tls-edit-btn').addEventListener('click',function(){
        ov.style.display='flex'; document.body.style.overflow='hidden';
    });
    document.getElementById('tls-prof-close').addEventListener('click',closeProf);
    ov.addEventListener('click',function(e){if(e.target===ov)closeProf();});
    function closeProf(){ ov.style.display='none'; document.body.style.overflow=''; }

    document.getElementById('btn-save-prof').addEventListener('click',function(){
        var name=document.getElementById('prof-name').value.trim();
        var old=document.getElementById('prof-old').value;
        var nw=document.getElementById('prof-new').value;
        var msgEl=document.getElementById('tls-prof-msg');
        msgEl.style.display='none';
        var fd=new FormData();
        fd.append('action','tls_update_profile'); fd.append('nonce',authNonce);
        fd.append('tls_name',name); fd.append('tls_pass_old',old); fd.append('tls_pass',nw);
        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(res){
            msgEl.style.display='block';
            msgEl.style.background=res.success?'#f0fdf4':'#fff5f5';
            msgEl.style.color=res.success?'#15803d':'#b91c1c';
            msgEl.textContent=res.success?res.data.message:res.data.message;
        });
    });
})();
</script>
<?php get_footer(); ?>
