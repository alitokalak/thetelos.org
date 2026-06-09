<?php
/**
 * Template Name: TLS Profile
 * The Telos — Profile Page
 */
if ( ! defined('ABSPATH') ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( home_url('/') ); exit; }

$user        = wp_get_current_user();
$uid         = $user->ID;
$initials    = strtoupper( substr($user->display_name, 0, 1) );
$avatar_url  = get_user_meta($uid, '_tls_avatar_url', true) ?: '';
$auth_nonce  = wp_create_nonce('tls_auth_nonce');
$upload_nonce= wp_create_nonce('tls_upload_avatar');
$ajax        = admin_url('admin-ajax.php');

global $wpdb;
$rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT meta_value, COUNT(*) as cnt FROM {$wpdb->usermeta}
     WHERE user_id=%d AND meta_key LIKE '_tls_reading_status_%%'
     GROUP BY meta_value", $uid
), OBJECT_K );
$cnt_want    = $rows['want']->cnt    ?? 0;
$cnt_reading = $rows['reading']->cnt ?? 0;
$cnt_read    = $rows['read']->cnt    ?? 0;
$cnt_total   = $cnt_want + $cnt_reading + $cnt_read;

get_header();
?>
<main id="main" role="main" class="tls-prof-page">
<div class="container">

  <!-- ══ TAB NAV ══ -->
  <div class="tls-prof-topnav">
    <button class="tls-prof-topbtn tls-prof-topbtn--active" data-tab="library">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
      My Library
    </button>
    <button class="tls-prof-topbtn" data-tab="readingpaths">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
      Reading Paths
    </button>
    <button class="tls-prof-topbtn" data-tab="settings">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
      Settings
    </button>
  </div>

  <!-- ══ ANA LAYOUT: Sol sidebar sabit ══ -->
  <div class="tls-prof-outer">

    <!-- SOL: Sabit sidebar — tüm tablarda görünür -->
    <aside class="tls-prof-sidebar">
      <div class="tls-prof-avatar-wrap">
        <label for="tls-avatar-input" class="tls-prof-avatar-label" title="Change photo">
          <?php if ($avatar_url) : ?>
            <img src="<?php echo esc_url($avatar_url); ?>" alt="" class="tls-prof-avatar-img" id="tls-avatar-img">
          <?php else : ?>
            <div class="tls-prof-avatar-ph"><?php echo esc_html($initials); ?></div>
          <?php endif; ?>
          <div class="tls-prof-avatar-hover">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
            <span>Change Photo</span>
          </div>
        </label>
        <input type="file" id="tls-avatar-input" accept="image/*" style="display:none;">
        <div class="tls-prof-avatar-loading" id="tls-avatar-loading" style="display:none;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
        </div>
      </div>
      <h1 class="tls-prof-name"><?php echo esc_html($user->display_name); ?></h1>
      <p class="tls-prof-email"><?php echo esc_html($user->user_email); ?></p>
      <p class="tls-prof-since">Member since <?php echo date('F Y', strtotime($user->user_registered)); ?></p>
      <div class="tls-prof-divider"></div>
      <div class="tls-prof-stats">
        <div class="tls-prof-stat-row"><span class="tls-prof-stat-label">In Library</span><span class="tls-prof-stat-val"><?php echo $cnt_total; ?></span></div>
        <div class="tls-prof-stat-row"><span class="tls-prof-stat-label">Finished</span><span class="tls-prof-stat-val" style="color:var(--tls-green);"><?php echo $cnt_read; ?></span></div>
        <div class="tls-prof-stat-row"><span class="tls-prof-stat-label">Reading</span><span class="tls-prof-stat-val" style="color:var(--tls-gold);"><?php echo $cnt_reading; ?></span></div>
        <div class="tls-prof-stat-row"><span class="tls-prof-stat-label">Want to Read</span><span class="tls-prof-stat-val" style="color:#b84c6e;"><?php echo $cnt_want; ?></span></div>
      </div>
      <div class="tls-prof-divider"></div>
      <button class="tls-prof-signout-btn" id="tls-ph-signout">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        Sign Out
      </button>
    </aside>

    <!-- SAĞ: Tab içerikleri -->
    <div class="tls-prof-content">

      <!-- LIBRARY TAB -->
      <div class="tls-prof-tab" id="tab-library">
        <div class="tls-prof-filters">
          <button class="tls-prof-filter tls-prof-filter--active" data-filter="all">All <span><?php echo $cnt_total; ?></span></button>
          <button class="tls-prof-filter" data-filter="want">❤ Want to Read <span><?php echo $cnt_want; ?></span></button>
          <button class="tls-prof-filter" data-filter="reading">📖 Reading <span><?php echo $cnt_reading; ?></span></button>
          <button class="tls-prof-filter" data-filter="read">✓ Finished <span><?php echo $cnt_read; ?></span></button>
        </div>
        <div id="tls-lib-loading" class="tls-prof-loading">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
          Loading…
        </div>
        <div id="tls-lib-grid" class="tls-prof-books" style="display:none;"></div>
        <div id="tls-lib-empty" class="tls-prof-empty" style="display:none;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
          <h3>No books yet</h3>
          <p>Browse the archive and mark books to build your library.</p>
          <a class="tls-botw-cta" href="<?php echo esc_url(home_url('/')); ?>">Browse Archive →</a>
        </div>
      </div>

      <!-- READING PATHS TAB -->
      <div class="tls-prof-tab" id="tab-readingpaths" style="display:none;">
        <div id="tls-rl-loading" class="tls-prof-loading">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
          Loading…
        </div>
        <div id="tls-rl-grid" style="display:none;"></div>
        <div id="tls-rl-empty" class="tls-prof-empty" style="display:none;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
          <h3>No saved reading paths</h3>
          <p>Browse the homepage and save reading paths to your profile.</p>
          <a class="tls-botw-cta" href="<?php echo esc_url(home_url('/')); ?>">Browse Reading Paths →</a>
        </div>
      </div>

      <!-- SETTINGS TAB -->
      <div class="tls-prof-tab" id="tab-settings" style="display:none;">
        <div class="tls-sett-card">
          <h2 class="tls-sett-title">Profile Information</h2>
          <div id="tls-prof-msg" class="tls-sett-notice" style="display:none;"></div>
          <div class="tls-sett-field"><label>Display Name</label><input type="text" id="s-name" value="<?php echo esc_attr($user->display_name); ?>"></div>
          <div class="tls-sett-field"><label>Email Address</label><input type="email" id="s-email" value="<?php echo esc_attr($user->user_email); ?>"></div>
          <button class="tls-sett-btn" id="s-save-profile">Save Changes</button>
        </div>
        <div class="tls-sett-card">
          <h2 class="tls-sett-title">Change Password</h2>
          <div id="tls-pass-msg" class="tls-sett-notice" style="display:none;"></div>
          <div class="tls-sett-field"><label>Current Password</label><input type="password" id="s-pass-old" placeholder="••••••••"></div>
          <div class="tls-sett-field"><label>New Password <span>(min. 6 characters)</span></label><input type="password" id="s-pass-new" placeholder="••••••••"></div>
          <button class="tls-sett-btn" id="s-save-pass">Update Password</button>
        </div>
        <div class="tls-sett-card tls-sett-card--flat">
          <h2 class="tls-sett-title">Account</h2>
          <button class="tls-sett-btn tls-sett-btn--outline" id="tls-ph-signout-2">Sign Out</button>
        </div>
      </div>

    </div><!-- /.tls-prof-content -->
  </div><!-- /.tls-prof-outer -->

</div><!-- /.container -->
</main>

<style>
@keyframes tlsProfSpin { to { transform:rotate(360deg); } }
.tls-prof-page { padding: 40px 0 80px; background: var(--tls-bg); min-height: 80vh; }

/* Tab nav */
.tls-prof-topnav { display:flex; gap:0; border-bottom:1px solid var(--tls-border); margin-bottom:32px; }
.tls-prof-topbtn { display:inline-flex; align-items:center; gap:7px; padding:13px 20px; background:none; border:none; font-family:var(--tls-sans); font-size:13px; font-weight:600; color:var(--tls-muted); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; transition:color .15s,border-color .15s; }
.tls-prof-topbtn:hover { color:var(--tls-bg-dark); }
.tls-prof-topbtn--active { color:var(--tls-bg-dark); border-bottom-color:var(--tls-gold); }

/* Outer layout — sidebar + content */
.tls-prof-outer { display:grid; grid-template-columns:240px 1fr; gap:32px; align-items:start; }
.tls-prof-content { min-width:0; }

/* Sidebar */
.tls-prof-sidebar { background:#fff; border:1px solid var(--tls-border); border-radius:16px; padding:28px 20px; text-align:center; position:sticky; top:calc(var(--tls-nav-h,80px) + 16px); }
.tls-prof-avatar-wrap { position:relative; display:inline-block; margin-bottom:16px; }
.tls-prof-avatar-label { display:block; cursor:pointer; border-radius:50%; }
.tls-prof-avatar-img { width:100px; height:100px; border-radius:50%; object-fit:cover; display:block; border:3px solid var(--tls-border); transition:opacity .2s; }
.tls-prof-avatar-ph { width:100px; height:100px; border-radius:50%; background:var(--tls-bg-dark); display:flex; align-items:center; justify-content:center; font-family:var(--tls-serif); font-size:38px; color:#fff; }
.tls-prof-avatar-hover { position:absolute; inset:0; border-radius:50%; background:rgba(0,0,0,.5); display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; color:#fff; opacity:0; transition:opacity .2s; }
.tls-prof-avatar-hover span { font-family:var(--tls-sans); font-size:9px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; }
.tls-prof-avatar-label:hover .tls-prof-avatar-hover { opacity:1; }
.tls-prof-avatar-label:hover .tls-prof-avatar-img { opacity:.6; }
.tls-prof-avatar-loading { position:absolute; inset:0; border-radius:50%; background:rgba(0,0,0,.55); display:flex; align-items:center; justify-content:center; color:var(--tls-gold); }
.tls-prof-avatar-loading svg { animation:tlsProfSpin .8s linear infinite; }
.tls-prof-name { font-family:var(--tls-serif); font-size:20px; font-weight:400; color:var(--tls-bg-dark); margin:0 0 4px; }
.tls-prof-email { font-family:var(--tls-sans); font-size:12px; color:var(--tls-muted); margin:0 0 2px; word-break:break-all; }
.tls-prof-since { font-family:var(--tls-sans); font-size:11px; color:rgba(0,0,0,.3); font-style:italic; margin:0; }
.tls-prof-divider { height:1px; background:var(--tls-border); margin:18px 0; }
.tls-prof-stats { text-align:left; }
.tls-prof-stat-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; font-family:var(--tls-sans); font-size:13px; border-bottom:1px solid var(--tls-border); }
.tls-prof-stat-row:last-child { border-bottom:none; }
.tls-prof-stat-label { color:var(--tls-muted); }
.tls-prof-stat-val { font-weight:700; font-size:16px; color:var(--tls-bg-dark); }
.tls-prof-signout-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; width:100%; justify-content:center; background:none; border:1px solid var(--tls-border); border-radius:8px; font-family:var(--tls-sans); font-size:12px; font-weight:600; color:var(--tls-muted); cursor:pointer; transition:all .15s; }
.tls-prof-signout-btn:hover { border-color:#b91c1c; color:#b91c1c; }

/* Filters */
.tls-prof-filters { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
.tls-prof-filter { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:24px; font-family:var(--tls-sans); font-size:12px; font-weight:500; color:var(--tls-muted); background:#fff; border:1px solid var(--tls-border); cursor:pointer; transition:all .15s; }
.tls-prof-filter span { background:var(--tls-bg); border-radius:10px; padding:1px 6px; font-size:10px; }
.tls-prof-filter--active,.tls-prof-filter:hover { background:var(--tls-bg-dark); border-color:var(--tls-bg-dark); color:#fff; }
.tls-prof-filter--active span,.tls-prof-filter:hover span { background:rgba(255,255,255,.15); }

/* Loading / empty */
.tls-prof-loading { display:flex; flex-direction:column; align-items:center; gap:10px; padding:64px; color:var(--tls-muted); font-family:var(--tls-sans); font-size:13px; }
.tls-prof-loading svg { animation:tlsProfSpin .9s linear infinite; color:var(--tls-gold); }
.tls-prof-empty { display:flex; flex-direction:column; align-items:center; text-align:center; padding:64px 24px; gap:12px; color:var(--tls-muted); }
.tls-prof-empty svg { color:var(--tls-border); }
.tls-prof-empty h3 { font-family:var(--tls-serif); font-size:20px; font-weight:400; color:var(--tls-bg-dark); margin:0; }
.tls-prof-empty p { font-family:var(--tls-sans); font-size:13px; margin:0; max-width:300px; line-height:1.6; }

/* Book cards */
.tls-prof-books { display:flex; flex-direction:column; gap:10px; }
.tls-prof-book { display:flex; align-items:center; gap:14px; background:#fff; border:1px solid var(--tls-border); border-radius:10px; padding:12px 16px; text-decoration:none; transition:box-shadow .2s,transform .15s; }
.tls-prof-book:hover { box-shadow:0 4px 16px rgba(0,0,0,.07); transform:translateY(-1px); }
.tls-prof-book-cover { flex-shrink:0; }
.tls-prof-book-cover img { width:44px; height:62px; object-fit:cover; border-radius:4px; display:block; box-shadow:0 4px 12px rgba(0,0,0,.15); }
.tls-prof-book-ph { width:44px; height:62px; border-radius:4px; display:flex; align-items:center; justify-content:center; font-family:var(--tls-serif); font-size:20px; color:rgba(255,255,255,.3); }
.tls-prof-book-body { flex:1; min-width:0; }
.tls-prof-book-badge { display:inline-block; padding:2px 8px; border-radius:8px; font-size:9px; font-weight:700; color:#fff; text-transform:uppercase; letter-spacing:.06em; margin-bottom:5px; }
.tls-prof-book-title { display:block; font-family:var(--tls-serif); font-size:14px; color:var(--tls-bg-dark); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.3; }
.tls-prof-book-author { font-family:var(--tls-sans); font-style:italic; font-size:12px; color:var(--tls-muted); margin-top:2px; }

/* Settings */
.tls-sett-card { background:#fff; border:1px solid var(--tls-border); border-radius:12px; padding:24px; margin-bottom:16px; }
.tls-sett-card--flat { background:var(--tls-bg); }
.tls-sett-title { font-family:var(--tls-serif); font-size:17px; font-weight:400; color:var(--tls-bg-dark); margin:0 0 18px; padding-bottom:14px; border-bottom:1px solid var(--tls-border); }
.tls-sett-notice { padding:10px 14px; border-radius:8px; font-family:var(--tls-sans); font-size:13px; margin-bottom:14px; }
.tls-sett-field { display:flex; flex-direction:column; gap:5px; margin-bottom:14px; }
.tls-sett-field label { font-family:var(--tls-sans); font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--tls-bg-dark); }
.tls-sett-field label span { font-weight:400; text-transform:none; letter-spacing:0; color:var(--tls-muted); }
.tls-sett-field input { font-family:var(--tls-sans); font-size:14px; color:var(--tls-bg-dark); background:var(--tls-bg); border:1.5px solid var(--tls-border); border-radius:8px; padding:11px 14px; width:100%; box-sizing:border-box; outline:none; -webkit-appearance:none; transition:border-color .2s; }
.tls-sett-field input:focus { border-color:var(--tls-gold); background:#fff; }
.tls-sett-btn { display:inline-flex; align-items:center; gap:6px; padding:11px 22px; background:var(--tls-bg-dark); color:#fff; border:1px solid transparent; border-radius:8px; font-family:var(--tls-sans); font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; cursor:pointer; transition:background .2s; }
.tls-sett-btn:hover { background:#2a2620; }
.tls-sett-btn--outline { background:transparent; border-color:var(--tls-border); color:var(--tls-muted); }
.tls-sett-btn--outline:hover { border-color:#b91c1c; color:#b91c1c; background:transparent; }

/* Reading path cards in profile */
.tls-rl-prof-card { background:#fff; border:1px solid var(--tls-border); border-radius:12px; padding:20px; margin-bottom:16px; }
.tls-rl-prof-header { display:flex; align-items:center; gap:10px; margin-bottom:6px; }
.tls-rl-prof-title { font-family:var(--tls-serif); font-size:17px; font-weight:400; color:var(--tls-bg-dark); margin:0; flex:1; }
.tls-rl-prof-count { font-family:var(--tls-sans); font-size:11px; color:var(--tls-muted); white-space:nowrap; }
.tls-rl-prof-desc { font-family:var(--tls-sans); font-size:13px; color:var(--tls-muted); margin:0 0 14px; line-height:1.5; }
.tls-rl-prof-book { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--tls-border); text-decoration:none; }
.tls-rl-prof-book:last-child { border-bottom:none; }
.tls-rl-prof-num { font-size:11px; color:var(--tls-muted); min-width:18px; font-family:var(--tls-sans); flex-shrink:0; }
.tls-rl-prof-book img { width:36px; height:50px; object-fit:cover; border-radius:3px; flex-shrink:0; }
.tls-rl-prof-book-ph { width:36px; height:50px; border-radius:3px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-family:var(--tls-serif); font-size:14px; color:rgba(255,255,255,.5); }
.tls-rl-prof-book-title { font-family:var(--tls-serif); font-size:13px; color:var(--tls-bg-dark); display:block; line-height:1.3; }
.tls-rl-prof-book-author { font-family:var(--tls-sans); font-size:11px; color:var(--tls-muted); font-style:italic; }
.tls-rl-remove-btn { display:inline-flex; align-items:center; gap:6px; margin-top:14px; padding:7px 14px; background:none; border:1px solid var(--tls-border); border-radius:8px; font-family:var(--tls-sans); font-size:12px; color:var(--tls-muted); cursor:pointer; transition:all .15s; }
.tls-rl-remove-btn:hover { border-color:#b91c1c; color:#b91c1c; }

@media(max-width:768px){
  .tls-prof-outer{grid-template-columns:1fr;gap:16px;}
  .tls-prof-sidebar{position:static;border-radius:12px;}
  .tls-prof-topbtn{padding:10px 12px;font-size:11px;}
}
</style>

<script>
(function(){
    var ajax      = '<?php echo esc_js($ajax); ?>';
    var authNonce = '<?php echo esc_js($auth_nonce); ?>';
    var upNonce   = '<?php echo esc_js($upload_nonce); ?>';
    var colors    = {want:'#b84c6e', reading:'#C8A165', read:'#6a9f5e'};
    var labels    = {want:'Want to Read', reading:'Currently Reading', read:'Finished'};
    var rlLoaded  = false;

    /* ── Tab geçişi ── */
    document.querySelectorAll('.tls-prof-topbtn').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('.tls-prof-topbtn').forEach(function(b){ b.classList.remove('tls-prof-topbtn--active'); });
            btn.classList.add('tls-prof-topbtn--active');
            document.querySelectorAll('.tls-prof-tab').forEach(function(t){ t.style.display='none'; });
            var tab = document.getElementById('tab-'+btn.dataset.tab);
            if (tab) tab.style.display='block';
            if (btn.dataset.tab === 'readingpaths' && !rlLoaded) {
                rlLoaded = true;
                loadReadingPaths();
            }
        });
    });
    if (location.search.indexOf('tab=settings')>-1) document.querySelector('[data-tab="settings"]').click();
    if (location.search.indexOf('tab=readingpaths')>-1) { rlLoaded=true; document.querySelector('[data-tab="readingpaths"]').click(); loadReadingPaths(); }

    /* ── Kütüphane yükle ── */
    function scaledCover(html, w, h) {
        var s = w / 160;
        return '<div style="width:'+w+'px;height:'+h+'px;position:relative;overflow:hidden;border-radius:4px;flex-shrink:0;box-shadow:0 4px 12px rgba(0,0,0,.2);">'
            + '<div style="position:absolute;top:0;left:0;transform:scale('+s+');transform-origin:top left;width:160px;height:220px;pointer-events:none;">'
            + html + '</div></div>';
    }

    function loadLib(filter) {
        var loading=document.getElementById('tls-lib-loading');
        var grid=document.getElementById('tls-lib-grid');
        var empty=document.getElementById('tls-lib-empty');
        loading.style.display='flex'; grid.style.display='none'; empty.style.display='none'; grid.innerHTML='';
        var fd=new FormData(); fd.append('action','tls_get_library'); fd.append('filter',filter);
        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(res){
            loading.style.display='none';
            if(!res.success||!res.data.books.length){empty.style.display='flex';return;}
            grid.style.display='flex';
            grid.innerHTML=res.data.books.map(function(b){
                var coverHtml;
                if(b.cover){
                    coverHtml='<img src="'+b.cover+'" alt="" loading="lazy" style="width:44px;height:62px;object-fit:cover;border-radius:3px;flex-shrink:0;">';
                } else {
                    coverHtml = b.cover_html ? scaledCover(b.cover_html, 44, 62) : '';
                }
                return '<a href="'+b.url+'" class="tls-prof-book">'
                    +'<div class="tls-prof-book-cover">'+coverHtml+'</div>'
                    +'<div class="tls-prof-book-body">'
                    +'<span class="tls-prof-book-badge" style="background:'+colors[b.status]+'">'+labels[b.status]+'</span>'
                    +'<span class="tls-prof-book-title">'+b.title+'</span>'
                    +(b.author?'<div class="tls-prof-book-author">'+b.author+'</div>':'')
                    +'</div></a>';
            }).join('');
        });
    }
    loadLib('all');

    document.querySelectorAll('.tls-prof-filter').forEach(function(btn){
        btn.addEventListener('click',function(){
            document.querySelectorAll('.tls-prof-filter').forEach(function(b){b.classList.remove('tls-prof-filter--active');});
            btn.classList.add('tls-prof-filter--active');
            loadLib(btn.dataset.filter);
        });
    });

    /* ── Reading Paths yükle ── */
    function loadReadingPaths() {
        var loading=document.getElementById('tls-rl-loading');
        var grid=document.getElementById('tls-rl-grid');
        var empty=document.getElementById('tls-rl-empty');
        if(!loading) return;
        loading.style.display='flex'; grid.style.display='none'; empty.style.display='none';

        var fd=new FormData(); fd.append('action','tls_get_my_lists');
        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(res){
            loading.style.display='none';
            if(!res.success||!res.data.lists.length){empty.style.display='block';return;}
            grid.style.display='block';
            grid.innerHTML=res.data.lists.map(function(rl){
                var booksHtml = rl.book_details && rl.book_details.length
                    ? rl.book_details.map(function(b,i){
                        var cover = b.cover
                            ? '<img src="'+b.cover+'" alt="" style="width:36px;height:50px;object-fit:cover;border-radius:3px;flex-shrink:0;">'
                            : b.cover_html ? scaledCover(b.cover_html, 36, 50) : '';
                        return '<a href="'+b.url+'" class="tls-rl-prof-book">'
                            +'<span class="tls-rl-prof-num">'+(i+1)+'.</span>'
                            +cover
                            +'<div><span class="tls-rl-prof-book-title">'+b.title+'</span>'
                            +(b.author?'<span class="tls-rl-prof-book-author">'+b.author+'</span>':'')
                            +'</div></a>';
                    }).join('')
                    : '<p style="color:var(--tls-muted);font-size:13px;padding:8px 0;">No books in this list.</p>';

                return '<div class="tls-rl-prof-card" data-list-id="'+rl.id+'">'
                    +'<div class="tls-rl-prof-header">'
                    +'<span style="font-size:22px;">'+rl.emoji+'</span>'
                    +'<h3 class="tls-rl-prof-title">'+rl.title+'</h3>'
                    +'<span class="tls-rl-prof-count">'+rl.count+' books</span>'
                    +'</div>'
                    +'<p class="tls-rl-prof-desc">'+rl.desc+'</p>'
                    +'<div>'+booksHtml+'</div>'
                    +'<button class="tls-rl-remove-btn" data-list-id="'+rl.id+'">× Remove from profile</button>'
                    +'</div>';
            }).join('');
        });
    }

    /* Remove from profile — anında DOM'dan kaldır */
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.tls-rl-remove-btn');
        if (!btn) return;
        var listId = btn.dataset.listId;
        var card   = document.querySelector('.tls-rl-prof-card[data-list-id="'+listId+'"]');
        btn.disabled = true;
        btn.textContent = '⏳';

        var fd = new FormData();
        fd.append('action',  'tls_add_list_to_library');
        fd.append('list_id', listId);
        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(res){
            if(res.success && card) {
                card.style.transition = 'opacity .3s';
                card.style.opacity = '0';
                setTimeout(function(){ card.remove(); }, 300);
            } else {
                btn.disabled = false;
                btn.textContent = '× Remove from profile';
            }
        });
    });

    /* ── Avatar upload ── */
    document.getElementById('tls-avatar-input').addEventListener('change',function(){
        var file=this.files[0];
        if(!file||!file.type.startsWith('image/'))return;
        if(file.size>5*1024*1024){alert('Image must be under 5MB.');return;}
        document.getElementById('tls-avatar-loading').style.display='flex';
        var fd=new FormData(); fd.append('action','tls_upload_avatar'); fd.append('nonce',upNonce); fd.append('avatar',file);
        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(res){
            document.getElementById('tls-avatar-loading').style.display='none';
            if(res.success&&res.data.url){
                var img=document.getElementById('tls-avatar-img');
                if(img){img.src=res.data.url;}
            }
        });
    });

    /* ── Sign out ── */
    function doSignout(){
        var fd=new FormData(); fd.append('action','tls_logout'); fd.append('nonce',authNonce);
        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(res){if(res.success)window.location.href=res.data.redirect;});
    }
    var so1=document.getElementById('tls-ph-signout');
    var so2=document.getElementById('tls-ph-signout-2');
    if(so1)so1.addEventListener('click',doSignout);
    if(so2)so2.addEventListener('click',doSignout);

    /* ── Settings kaydet ── */
    function notice(id,text,type){
        var el=document.getElementById(id); if(!el)return;
        el.textContent=text; el.style.display=text?'block':'none';
        el.style.background=type==='success'?'#f0fdf4':'#fff5f5';
        el.style.color=type==='success'?'#15803d':'#b91c1c';
        el.style.border='1px solid '+(type==='success'?'#86efac':'#fca5a5');
    }
    function settPost(data,cb){
        data.nonce=authNonce; var fd=new FormData();
        Object.keys(data).forEach(function(k){fd.append(k,data[k]);});
        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(cb);
    }
    var sp=document.getElementById('s-save-profile');
    if(sp)sp.addEventListener('click',function(){
        settPost({action:'tls_update_profile',tls_name:document.getElementById('s-name').value.trim(),tls_email:document.getElementById('s-email').value.trim()},function(res){
            notice('tls-prof-msg',res.success?res.data.message:res.data.message,res.success?'success':'error');
        });
    });
    var spass=document.getElementById('s-save-pass');
    if(spass)spass.addEventListener('click',function(){
        var old=document.getElementById('s-pass-old').value;
        var nw=document.getElementById('s-pass-new').value;
        if(!old||!nw){notice('tls-pass-msg','Please fill both fields.','error');return;}
        settPost({action:'tls_update_profile',tls_pass_old:old,tls_pass:nw},function(res){
            notice('tls-pass-msg',res.success?res.data.message:res.data.message,res.success?'success':'error');
            if(res.success){document.getElementById('s-pass-old').value='';document.getElementById('s-pass-new').value='';}
        });
    });
})();
</script>

<?php get_footer(); ?>
