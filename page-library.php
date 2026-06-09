<?php
/**
 * Template Name: My Library
 * Template Post Type: page
 * @package Mediumish / TheTelos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! is_user_logged_in() ) {
    wp_redirect( home_url('/login/') );
    exit;
}

get_header();
$user     = wp_get_current_user();
$initials = strtoupper( substr($user->display_name, 0, 1) );
?>
<main id="main" role="main">

<!-- ── Library Hero ── -->
<div class="tls-lib-hero">
    <div class="container">
        <div class="tls-lib-hero-inner">
            <div class="tls-lib-avatar"><?php echo esc_html($initials); ?></div>
            <div>
                <h1 class="tls-lib-name"><?php echo esc_html($user->display_name); ?>'s Library</h1>
                <p class="tls-lib-email"><?php echo esc_html($user->user_email); ?></p>
            </div>
            <div class="tls-lib-hero-actions">
                <button class="tls-status-btn" id="tls-edit-profile-btn" type="button">Edit Profile</button>
                <button class="tls-status-btn" id="tls-logout-btn" type="button">Sign Out</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Filter tabs ── -->
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

<!-- ── Book list ── -->
<div class="tls-lib-body">
    <div class="container">
        <div id="tls-lib-loading" class="tls-lib-loading">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28" style="animation:tlsAuthSpin .8s linear infinite;color:var(--tls-gold);"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
            Loading your library…
        </div>
        <div id="tls-lib-grid" class="tls-lib-grid" style="display:none;"></div>
        <div id="tls-lib-empty" class="tls-lib-empty" style="display:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48" style="color:var(--tls-border);"><path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
            <h3>No books here yet</h3>
            <p>Start reading summaries and mark books as "Want to Read", "Reading", or "Finished".</p>
            <a class="tls-botw-cta" href="<?php echo esc_url(home_url('/archive/')); ?>" style="margin-top:8px;">Browse the Archive →</a>
        </div>
    </div>
</div>

<!-- ── Edit Profile Modal ── -->
<div id="tls-profile-overlay" style="display:none;" role="dialog" aria-modal="true">
    <div id="tls-profile-modal">
        <button id="tls-profile-close" aria-label="Close">&times;</button>
        <h2 class="tls-req-title" style="margin-bottom:20px;">Edit Profile</h2>
        <div id="tls-profile-msg" style="display:none;margin-bottom:16px;"></div>
        <div class="tls-req-fields">
            <div class="tls-req-field">
                <label class="tls-req-label">Display Name</label>
                <input class="tls-req-input" type="text" id="prof-name" value="<?php echo esc_attr($user->display_name); ?>">
            </div>
            <div class="tls-req-field">
                <label class="tls-req-label">Current Password <span class="tls-req-optional">(required to change password)</span></label>
                <input class="tls-req-input" type="password" id="prof-pass-old" placeholder="••••••••">
            </div>
            <div class="tls-req-field">
                <label class="tls-req-label">New Password <span class="tls-req-optional">(leave blank to keep current)</span></label>
                <input class="tls-req-input" type="password" id="prof-pass-new" placeholder="••••••••">
            </div>
        </div>
        <div class="tls-req-actions" style="margin-top:20px;">
            <button class="tls-req-submit" id="tls-save-profile" type="button">Save Changes</button>
        </div>
    </div>
</div>

<script>
(function(){
    var ajax      = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
    var authNonce = '<?php echo esc_js(wp_create_nonce("tls_auth_nonce")); ?>';
    var statusMap = { want:'Want to Read', reading:'Currently Reading', read:'Finished' };
    var badgeColor= { want:'#b84c6e', reading:'var(--tls-gold)', read:'var(--tls-green)' };
    var currentFilter = 'all';

    /* ── Kütüphaneyi yükle ── */
    function loadLibrary(filter) {
        currentFilter = filter;
        document.getElementById('tls-lib-loading').style.display = 'flex';
        document.getElementById('tls-lib-grid').style.display    = 'none';
        document.getElementById('tls-lib-empty').style.display   = 'none';

        var fd = new FormData();
        fd.append('action', 'tls_get_library');
        fd.append('filter', filter);
        fetch(ajax, {method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(res){
            document.getElementById('tls-lib-loading').style.display = 'none';
            if (!res.success || !res.data.books.length) {
                document.getElementById('tls-lib-empty').style.display = 'flex';
                return;
            }
            var grid = document.getElementById('tls-lib-grid');
            grid.innerHTML = res.data.books.map(function(book){
                var badge = badgeColor[book.status] || '#888';
                var label = statusMap[book.status]  || '';
                return '<div class="tls-lib-item">' +
                    '<a href="'+ book.url +'" class="tls-lib-item-cover">' +
                    ( book.cover
                        ? '<img src="'+ book.cover +'" alt="'+ book.title +'">'
                        : '<div class="tls-lib-cover-placeholder">'+ book.title.charAt(0) +'</div>'
                    ) + '</a>' +
                    '<div class="tls-lib-item-body">' +
                        '<span class="tls-lib-status-badge" style="background:'+ badge +'">'+ label +'</span>' +
                        '<a href="'+ book.url +'" class="tls-lib-item-title">'+ book.title +'</a>' +
                        ( book.author ? '<p class="tls-lib-item-author">'+ book.author +'</p>' : '' ) +
                        ( book.category ? '<span class="tls-lib-item-cat">'+ book.category +'</span>' : '' ) +
                    '</div>' +
                '</div>';
            }).join('');
            grid.style.display = 'grid';
        });
    }

    /* ── Filter tabları ── */
    document.querySelectorAll('.tls-lib-tab').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('.tls-lib-tab').forEach(function(b){b.classList.remove('active');});
            btn.classList.add('active');
            loadLibrary(btn.dataset.filter);
        });
    });

    /* ── İlk yükleme ── */
    loadLibrary('all');

    /* ── Çıkış ── */
    document.getElementById('tls-logout-btn').addEventListener('click', function(){
        var fd = new FormData();
        fd.append('action','tls_logout');
        fd.append('nonce', authNonce);
        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(res){
            if(res.success) window.location.href = res.data.redirect;
        });
    });

    /* ── Profil modal ── */
    var profOverlay = document.getElementById('tls-profile-overlay');
    document.getElementById('tls-edit-profile-btn').addEventListener('click', function(){
        profOverlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    });
    document.getElementById('tls-profile-close').addEventListener('click', closeProfile);
    profOverlay.addEventListener('click', function(e){ if(e.target===profOverlay) closeProfile(); });
    function closeProfile(){ profOverlay.style.display='none'; document.body.style.overflow=''; }

    document.getElementById('tls-save-profile').addEventListener('click', function(){
        var name    = document.getElementById('prof-name').value.trim();
        var passOld = document.getElementById('prof-pass-old').value;
        var passNew = document.getElementById('prof-pass-new').value;
        var msgEl   = document.getElementById('tls-profile-msg');
        msgEl.style.display='none';

        var fd = new FormData();
        fd.append('action','tls_update_profile');
        fd.append('nonce', authNonce);
        fd.append('tls_name', name);
        fd.append('tls_pass_old', passOld);
        fd.append('tls_pass', passNew);
        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(res){
            msgEl.style.display='block';
            msgEl.style.color = res.success ? '#15803d' : '#b91c1c';
            msgEl.style.background = res.success ? '#f0fdf4' : '#fff5f5';
            msgEl.style.padding = '8px 12px';
            msgEl.style.borderRadius = '6px';
            msgEl.style.fontSize = '13px';
            msgEl.textContent = res.success ? res.data.message : res.data.message;
        });
    });
})();
</script>

<?php get_footer(); ?>
