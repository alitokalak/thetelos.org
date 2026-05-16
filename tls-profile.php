<?php
/**
 * The Telos — Profile Page
 * /profile/ URL'sinden membership.php tarafından yüklenir
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user        = wp_get_current_user();
$uid         = $user->ID;
$initials    = strtoupper( substr($user->display_name, 0, 1) );
$avatar      = get_avatar_url($uid, ['size' => 120]);
$auth_nonce  = wp_create_nonce('tls_auth_nonce');
$ajax        = admin_url('admin-ajax.php');

global $wpdb;
$status_rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT meta_value, COUNT(*) as cnt FROM {$wpdb->usermeta}
     WHERE user_id=%d AND meta_key LIKE '_tls_reading_status_%%'
     GROUP BY meta_value", $uid
), OBJECT_K);
$cnt_want    = $status_rows['want']->cnt    ?? 0;
$cnt_reading = $status_rows['reading']->cnt ?? 0;
$cnt_read    = $status_rows['read']->cnt    ?? 0;
$cnt_total   = $cnt_want + $cnt_reading + $cnt_read;

get_header();
?>

<main id="main" role="main" class="tls-profile-page">

<!-- ── Hero banner ── -->
<div class="tls-profile-hero">
    <div class="container">
        <div class="tls-profile-hero-inner">
            <div class="tls-ph-avatar-wrap">
                <?php if ( $avatar ) : ?>
                    <img src="<?php echo esc_url($avatar); ?>" alt="" class="tls-ph-avatar">
                <?php else : ?>
                    <div class="tls-ph-avatar tls-ph-avatar--initials"><?php echo esc_html($initials); ?></div>
                <?php endif; ?>
            </div>
            <div class="tls-ph-info">
                <h1 class="tls-ph-name"><?php echo esc_html($user->display_name); ?></h1>
                <p class="tls-ph-email"><?php echo esc_html($user->user_email); ?></p>
                <div class="tls-ph-stats">
                    <div class="tls-ph-stat">
                        <strong><?php echo $cnt_total; ?></strong>
                        <span>Books</span>
                    </div>
                    <div class="tls-ph-stat">
                        <strong><?php echo $cnt_read; ?></strong>
                        <span>Finished</span>
                    </div>
                    <div class="tls-ph-stat">
                        <strong><?php echo $cnt_reading; ?></strong>
                        <span>Reading</span>
                    </div>
                    <div class="tls-ph-stat">
                        <strong><?php echo $cnt_want; ?></strong>
                        <span>Want to Read</span>
                    </div>
                </div>
            </div>
            <div class="tls-ph-actions">
                <button class="tls-ph-action-btn tls-ph-signout" id="tls-ph-signout" type="button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    Sign Out
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Tab nav ── -->
<div class="tls-profile-nav">
    <div class="container">
        <nav class="tls-profile-tabs">
            <button class="tls-profile-tab tls-profile-tab--active" data-tab="library">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                My Library
            </button>
            <button class="tls-profile-tab" data-tab="settings">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                Settings
            </button>
        </nav>
    </div>
</div>

<!-- ── Library tab ── -->
<div class="tls-profile-content" id="tab-library">
    <div class="container">

        <!-- Filter -->
        <div class="tls-lib-filter-wrap">
            <button class="tls-lib-filter tls-lib-filter--active" data-filter="all">All <span><?php echo $cnt_total; ?></span></button>
            <button class="tls-lib-filter" data-filter="want">Want to Read <span><?php echo $cnt_want; ?></span></button>
            <button class="tls-lib-filter" data-filter="reading">Reading <span><?php echo $cnt_reading; ?></span></button>
            <button class="tls-lib-filter" data-filter="read">Finished <span><?php echo $cnt_read; ?></span></button>
        </div>

        <!-- Book grid -->
        <div id="tls-lib-loading" class="tls-lib-page-loading">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
            Loading your library…
        </div>

        <div id="tls-lib-grid" class="tls-lib-page-grid" style="display:none;"></div>

        <div id="tls-lib-empty" class="tls-lib-page-empty" style="display:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="56" height="56"><path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
            <h3>No books here yet</h3>
            <p>Browse the archive and mark books as "Want to Read", "Reading", or "Finished".</p>
            <a class="tls-botw-cta" href="<?php echo esc_url(home_url('/archive/')); ?>">Browse the Archive →</a>
        </div>

    </div>
</div>

<!-- ── Settings tab ── -->
<div class="tls-profile-content" id="tab-settings" style="display:none;">
    <div class="container">
        <div class="tls-settings-grid">

            <!-- Profile info -->
            <div class="tls-settings-card">
                <h2 class="tls-settings-card-title">Profile Information</h2>
                <div id="tls-prof-msg" class="tls-settings-msg" style="display:none;"></div>
                <div class="tls-settings-field">
                    <label class="tls-settings-label">Display Name</label>
                    <input class="tls-settings-input" type="text" id="s-name" value="<?php echo esc_attr($user->display_name); ?>">
                </div>
                <div class="tls-settings-field">
                    <label class="tls-settings-label">Email Address</label>
                    <input class="tls-settings-input" type="email" id="s-email" value="<?php echo esc_attr($user->user_email); ?>">
                </div>
                <button class="tls-settings-save-btn" id="s-save-profile">Save Changes</button>
            </div>

            <!-- Password -->
            <div class="tls-settings-card">
                <h2 class="tls-settings-card-title">Change Password</h2>
                <div id="tls-pass-msg" class="tls-settings-msg" style="display:none;"></div>
                <div class="tls-settings-field">
                    <label class="tls-settings-label">Current Password</label>
                    <input class="tls-settings-input" type="password" id="s-pass-old" placeholder="••••••••">
                </div>
                <div class="tls-settings-field">
                    <label class="tls-settings-label">New Password <span style="color:var(--tls-muted);font-weight:400;">(min. 6 characters)</span></label>
                    <input class="tls-settings-input" type="password" id="s-pass-new" placeholder="••••••••">
                </div>
                <button class="tls-settings-save-btn" id="s-save-pass">Update Password</button>
            </div>

            <!-- Reading stats -->
            <div class="tls-settings-card">
                <h2 class="tls-settings-card-title">Reading Statistics</h2>
                <div class="tls-reading-stats">
                    <div class="tls-reading-stat-row">
                        <span>Books in library</span><strong><?php echo $cnt_total; ?></strong>
                    </div>
                    <div class="tls-reading-stat-row">
                        <span>Finished</span><strong style="color:var(--tls-green);"><?php echo $cnt_read; ?></strong>
                    </div>
                    <div class="tls-reading-stat-row">
                        <span>Currently reading</span><strong style="color:var(--tls-gold);"><?php echo $cnt_reading; ?></strong>
                    </div>
                    <div class="tls-reading-stat-row">
                        <span>Want to read</span><strong style="color:#b84c6e;"><?php echo $cnt_want; ?></strong>
                    </div>
                </div>
            </div>

            <!-- Danger zone -->
            <div class="tls-settings-card tls-settings-card--danger">
                <h2 class="tls-settings-card-title">Account</h2>
                <p style="font-family:var(--tls-sans);font-size:14px;color:var(--tls-muted);margin:0 0 16px;">Sign out from this device.</p>
                <button class="tls-settings-save-btn tls-settings-save-btn--outline" id="tls-ph-signout-2" type="button">Sign Out</button>
            </div>

        </div>
    </div>
</div>

</main>

<style>
/* ── PROFILE PAGE ── */
.tls-profile-page { min-height: 80vh; background: var(--tls-bg); }

/* Hero */
.tls-profile-hero {
  background: var(--tls-bg-dark);
  padding: 52px 0 44px;
}
.tls-profile-hero-inner {
  display: flex;
  align-items: flex-start;
  gap: 28px;
  flex-wrap: wrap;
}
.tls-ph-avatar-wrap { flex-shrink: 0; }
.tls-ph-avatar {
  width: 96px; height: 96px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid rgba(200,161,101,.3);
  display: block;
}
.tls-ph-avatar--initials {
  background: var(--tls-gold);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--tls-serif); font-size: 38px; color: #fff;
}
.tls-ph-info { flex: 1; min-width: 0; }
.tls-ph-name {
  font-family: var(--tls-serif);
  font-size: clamp(24px,4vw,36px);
  font-weight: 400; color: #fff;
  margin: 0 0 4px;
}
.tls-ph-email {
  font-family: var(--tls-sans);
  font-size: 14px; color: rgba(255,255,255,.4);
  margin: 0 0 20px;
}
.tls-ph-stats {
  display: flex; gap: 28px; flex-wrap: wrap;
}
.tls-ph-stat { text-align: center; }
.tls-ph-stat strong {
  display: block;
  font-family: var(--tls-serif);
  font-size: 26px; font-weight: 400;
  color: #fff; line-height: 1;
  margin-bottom: 3px;
}
.tls-ph-stat span {
  font-family: var(--tls-sans);
  font-size: 11px; color: rgba(255,255,255,.35);
  text-transform: uppercase; letter-spacing: .08em;
}
.tls-ph-actions { margin-left: auto; }
.tls-ph-action-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 18px;
  border-radius: 24px;
  font-family: var(--tls-sans); font-size: 12px; font-weight: 600;
  cursor: pointer; transition: all .15s;
  letter-spacing: .04em;
}
.tls-ph-signout {
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.12);
  color: rgba(255,255,255,.5);
}
.tls-ph-signout:hover {
  background: rgba(255,255,255,.12);
  color: #fff;
}

/* Tab nav */
.tls-profile-nav {
  background: #fff;
  border-bottom: 1px solid var(--tls-border);
  position: sticky;
  top: var(--tls-nav-h);
  z-index: 90;
}
.tls-profile-tabs { display: flex; gap: 0; }
.tls-profile-tab {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 16px 20px;
  background: none; border: none;
  font-family: var(--tls-sans); font-size: 13px; font-weight: 600;
  color: var(--tls-muted); cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: color .15s, border-color .15s;
}
.tls-profile-tab:hover { color: var(--tls-bg-dark); }
.tls-profile-tab--active { color: var(--tls-bg-dark); border-bottom-color: var(--tls-gold); }

/* Library */
.tls-profile-content { padding: 36px 0 64px; }
.tls-lib-filter-wrap { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 28px; }
.tls-lib-filter {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px;
  background: #fff; border: 1px solid var(--tls-border);
  border-radius: 24px;
  font-family: var(--tls-sans); font-size: 13px; font-weight: 500;
  color: var(--tls-muted); cursor: pointer;
  transition: all .15s;
}
.tls-lib-filter span {
  background: var(--tls-bg); border-radius: 10px;
  padding: 1px 7px; font-size: 11px;
}
.tls-lib-filter--active, .tls-lib-filter:hover {
  border-color: var(--tls-bg-dark);
  color: var(--tls-bg-dark);
  background: var(--tls-bg-dark);
  color: #fff;
}
.tls-lib-filter--active span, .tls-lib-filter:hover span {
  background: rgba(255,255,255,.15);
}

.tls-lib-page-loading {
  display: flex; flex-direction: column; align-items: center; gap: 12px;
  padding: 64px; color: var(--tls-muted);
  font-family: var(--tls-sans); font-size: 13px;
}
.tls-lib-page-loading svg { animation: tlsProfSpin .9s linear infinite; color: var(--tls-gold); }
@keyframes tlsProfSpin { to { transform: rotate(360deg); } }

.tls-lib-page-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
}
.tls-lib-page-item {
  background: #fff;
  border: 1px solid var(--tls-border);
  border-radius: 12px;
  display: flex; align-items: center; gap: 14px;
  padding: 14px;
  transition: box-shadow .2s, transform .2s;
  text-decoration: none;
}
.tls-lib-page-item:hover {
  box-shadow: 0 6px 24px rgba(0,0,0,.08);
  transform: translateY(-1px);
}
.tls-lib-page-item-cover { flex-shrink: 0; }
.tls-lib-page-item-cover img {
  width: 52px; height: 72px;
  object-fit: cover; border-radius: 4px;
  display: block;
  box-shadow: 0 4px 12px rgba(0,0,0,.2);
}
.tls-lib-cover-ph {
  width: 52px; height: 72px;
  background: var(--tls-bg-dark);
  border-radius: 4px;
  display: flex; align-items: center; justify-content: center;
  font-family: var(--tls-serif); font-size: 22px;
  color: rgba(255,255,255,.3);
}
.tls-lib-page-item-body { flex: 1; min-width: 0; }
.tls-lib-page-badge {
  display: inline-block;
  padding: 2px 8px; border-radius: 10px;
  font-family: var(--tls-sans); font-size: 9px; font-weight: 700;
  color: #fff; text-transform: uppercase; letter-spacing: .06em;
  margin-bottom: 5px;
}
.tls-lib-page-title {
  display: block;
  font-family: var(--tls-serif); font-size: 15px;
  color: var(--tls-bg-dark);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  margin-bottom: 3px; line-height: 1.3;
}
.tls-lib-page-author {
  font-family: var(--tls-sans); font-style: italic;
  font-size: 12px; color: var(--tls-muted);
}

.tls-lib-page-empty {
  display: flex; flex-direction: column; align-items: center;
  text-align: center; gap: 12px; padding: 80px 24px;
  color: var(--tls-muted);
}
.tls-lib-page-empty h3 {
  font-family: var(--tls-serif); font-size: 22px; font-weight: 400;
  color: var(--tls-bg-dark); margin: 0;
}
.tls-lib-page-empty p {
  font-family: var(--tls-sans); font-size: 14px;
  color: var(--tls-muted); margin: 0; max-width: 380px; line-height: 1.6;
}

/* Settings */
.tls-settings-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  max-width: 900px;
  margin: 0 auto;
}
@media (max-width: 680px) { .tls-settings-grid { grid-template-columns: 1fr; } }
.tls-settings-card {
  background: #fff;
  border: 1px solid var(--tls-border);
  border-radius: 12px;
  padding: 24px;
}
.tls-settings-card-title {
  font-family: var(--tls-serif);
  font-size: 18px; font-weight: 400;
  color: var(--tls-bg-dark);
  margin: 0 0 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--tls-border);
}
.tls-settings-msg {
  padding: 10px 14px; border-radius: 8px;
  font-family: var(--tls-sans); font-size: 13px;
  margin-bottom: 14px;
}
.tls-settings-field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
.tls-settings-label {
  font-family: var(--tls-sans); font-size: 11px; font-weight: 700;
  letter-spacing: .08em; text-transform: uppercase;
  color: var(--tls-bg-dark);
}
.tls-settings-input {
  font-family: var(--tls-sans); font-size: 14px;
  color: var(--tls-bg-dark); background: var(--tls-bg);
  border: 1.5px solid var(--tls-border);
  border-radius: 8px; padding: 10px 14px;
  width: 100%; box-sizing: border-box;
  outline: none; transition: border-color .2s;
  -webkit-appearance: none;
}
.tls-settings-input:focus { border-color: var(--tls-gold); }
.tls-settings-save-btn {
  display: inline-flex; align-items: center;
  padding: 11px 24px;
  background: var(--tls-bg-dark); color: #fff;
  border: 1px solid transparent;
  border-radius: 8px;
  font-family: var(--tls-sans); font-size: 12px; font-weight: 700;
  letter-spacing: .06em; text-transform: uppercase;
  cursor: pointer; transition: background .2s;
  margin-top: 4px;
}
.tls-settings-save-btn:hover { background: #2a2620; }
.tls-settings-save-btn--outline {
  background: transparent;
  border-color: var(--tls-border);
  color: var(--tls-muted);
}
.tls-settings-save-btn--outline:hover { border-color: #b91c1c; color: #b91c1c; background: transparent; }

.tls-reading-stats { display: flex; flex-direction: column; gap: 12px; }
.tls-reading-stat-row {
  display: flex; align-items: center; justify-content: space-between;
  font-family: var(--tls-sans); font-size: 14px; color: var(--tls-muted);
  padding-bottom: 12px; border-bottom: 1px solid var(--tls-border);
}
.tls-reading-stat-row:last-child { border-bottom: none; padding-bottom: 0; }
.tls-reading-stat-row strong { font-size: 18px; color: var(--tls-bg-dark); }

@media (max-width: 768px) {
  .tls-profile-hero-inner { flex-direction: column; }
  .tls-ph-actions { margin-left: 0; }
  .tls-ph-stats { gap: 20px; }
  .tls-lib-page-grid { grid-template-columns: 1fr; }
}
</style>

<script>
(function(){
    var ajax      = '<?php echo esc_js($ajax); ?>';
    var authNonce = '<?php echo esc_js($auth_nonce); ?>';
    var statusColors = {want:'#b84c6e', reading:'<?php echo addslashes(get_theme_mod("tls_gold","#C8A165")); ?>', read:'#6a9f5e'};
    var statusLabels = {want:'Want to Read', reading:'Currently Reading', read:'Finished'};

    /* ── Tab geçişi ── */
    document.querySelectorAll('.tls-profile-tab').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('.tls-profile-tab').forEach(function(b){ b.classList.remove('tls-profile-tab--active'); });
            btn.classList.add('tls-profile-tab--active');
            var tab = btn.dataset.tab;
            document.querySelectorAll('.tls-profile-content').forEach(function(c){ c.style.display='none'; });
            document.getElementById('tab-' + tab).style.display = 'block';
        });
    });

    /* ── Kütüphane yükleme ── */
    function loadLibrary(filter){
        var loading = document.getElementById('tls-lib-loading');
        var grid    = document.getElementById('tls-lib-grid');
        var empty   = document.getElementById('tls-lib-empty');
        loading.style.display = 'flex';
        grid.style.display    = 'none';
        empty.style.display   = 'none';
        grid.innerHTML        = '';

        var fd = new FormData();
        fd.append('action', 'tls_get_library');
        fd.append('filter', filter);
        fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res){
            loading.style.display = 'none';
            if (!res.success || !res.data.books.length) {
                empty.style.display = 'flex'; return;
            }
            grid.style.display = 'grid';
            grid.innerHTML = res.data.books.map(function(b){
                return '<a href="'+b.url+'" class="tls-lib-page-item">'+
                    '<div class="tls-lib-page-item-cover">'+
                    (b.cover ? '<img src="'+b.cover+'" alt="'+b.title+'">'
                              : '<div class="tls-lib-cover-ph">'+b.title.charAt(0)+'</div>')+
                    '</div>'+
                    '<div class="tls-lib-page-item-body">'+
                    '<span class="tls-lib-page-badge" style="background:'+statusColors[b.status]+'">'+statusLabels[b.status]+'</span>'+
                    '<span class="tls-lib-page-title">'+b.title+'</span>'+
                    (b.author ? '<div class="tls-lib-page-author">'+b.author+'</div>' : '')+
                    '</div></a>';
            }).join('');
        });
    }

    loadLibrary('all');

    /* Filter butonları */
    document.querySelectorAll('.tls-lib-filter').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('.tls-lib-filter').forEach(function(b){ b.classList.remove('tls-lib-filter--active'); });
            btn.classList.add('tls-lib-filter--active');
            loadLibrary(btn.dataset.filter);
        });
    });

    /* ── Çıkış ── */
    function doSignout(){
        var fd = new FormData();
        fd.append('action', 'tls_logout');
        fd.append('nonce',  authNonce);
        fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res){ if(res.success) window.location.href = res.data.redirect; });
    }
    var so1 = document.getElementById('tls-ph-signout');
    var so2 = document.getElementById('tls-ph-signout-2');
    if(so1) so1.addEventListener('click', doSignout);
    if(so2) so2.addEventListener('click', doSignout);

    /* ── Profil kaydet ── */
    function settingsMsg(elId, text, type){
        var el = document.getElementById(elId);
        if(!el) return;
        el.textContent = text;
        el.style.display = text ? 'block' : 'none';
        el.style.background = type==='success' ? '#f0fdf4' : '#fff5f5';
        el.style.color      = type==='success' ? '#15803d' : '#b91c1c';
        el.style.border     = '1px solid ' + (type==='success' ? '#86efac' : '#fca5a5');
    }
    function ajaxPost(data, cb){
        data.nonce = authNonce;
        var fd = new FormData();
        for(var k in data) fd.append(k, data[k]);
        fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(cb);
    }

    var saveProfile = document.getElementById('s-save-profile');
    if(saveProfile){
        saveProfile.addEventListener('click', function(){
            ajaxPost({
                action:'tls_update_profile',
                tls_name:  document.getElementById('s-name').value.trim(),
                tls_email: document.getElementById('s-email').value.trim(),
            }, function(res){
                settingsMsg('tls-prof-msg', res.success?res.data.message:res.data.message, res.success?'success':'error');
            });
        });
    }
    var savePass = document.getElementById('s-save-pass');
    if(savePass){
        savePass.addEventListener('click', function(){
            var old = document.getElementById('s-pass-old').value;
            var nw  = document.getElementById('s-pass-new').value;
            if(!old || !nw){ settingsMsg('tls-pass-msg','Please fill both password fields.','error'); return; }
            ajaxPost({
                action:'tls_update_profile',
                tls_pass_old: old,
                tls_pass: nw,
            }, function(res){
                settingsMsg('tls-pass-msg', res.success?res.data.message:res.data.message, res.success?'success':'error');
                if(res.success){ document.getElementById('s-pass-old').value=''; document.getElementById('s-pass-new').value=''; }
            });
        });
    }
})();
</script>

<?php get_footer(); ?>
