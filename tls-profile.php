<?php
/**
 * The Telos — Profile Page v2
 * Okuma istatistikleri · Yıllık hedef · Streak · Kitaplık · Ayarlar
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

/* ── Okuma durumu sayıları ── */
$status_rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT meta_value, COUNT(*) as cnt FROM {$wpdb->usermeta}
     WHERE user_id=%d AND meta_key LIKE '_tls_reading_status_%%'
     GROUP BY meta_value", $uid
), OBJECT_K);
$cnt_want    = (int)($status_rows['want']->cnt    ?? 0);
$cnt_reading = (int)($status_rows['reading']->cnt ?? 0);
$cnt_read    = (int)($status_rows['read']->cnt    ?? 0);
$cnt_total   = $cnt_want + $cnt_reading + $cnt_read;

/* ── Yıllık okuma hedefi ── */
$current_year = (int) date('Y');
$reading_goal = (int) get_user_meta($uid, '_tls_reading_goal_' . $current_year, true);
if ( ! $reading_goal ) $reading_goal = 12; /* Varsayılan: 12 kitap */

/* ── Bu yıl okunan kitap sayısı ── */
$year_read = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->usermeta}
     WHERE user_id = %d
       AND meta_key LIKE '_tls_status_date_%'
       AND LEFT(meta_value, 4) = %s",
    $uid, $current_year
) );
/* Fallback: toplam read */
if ( $year_read === 0 ) $year_read = $cnt_read;

/* ── Streak hesaplama ── */
/* Son 365 günün okuma tarihlerini al, ardışık gün sayısını hesapla */
$streak_dates = $wpdb->get_col( $wpdb->prepare(
    "SELECT DISTINCT DATE(meta_value) as read_date
     FROM {$wpdb->usermeta}
     WHERE user_id = %d
       AND meta_key LIKE '_tls_status_date_%%'
       AND meta_value != ''
     ORDER BY read_date DESC
     LIMIT 365", $uid
) );

$streak = 0;
if ( ! empty($streak_dates) ) {
    $today = new DateTime('today');
    $yesterday = new DateTime('yesterday');
    $first_date = new DateTime($streak_dates[0]);

    if ( $first_date >= $yesterday ) {
        $streak = 1;
        for ( $i = 1; $i < count($streak_dates); $i++ ) {
            $curr = new DateTime($streak_dates[$i]);
            $prev = new DateTime($streak_dates[$i-1]);
            $diff = $prev->diff($curr)->days;
            if ( $diff === 1 ) {
                $streak++;
            } else {
                break;
            }
        }
    }
}

/* ── Aktif tab (URL parametresinden) ── */
$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'library';

get_header();
?>

<main id="main" role="main" class="tls-profile-page">

<!-- ════════════════════════════════
     HERO
════════════════════════════════ -->
<div class="tls-profile-hero">
    <div class="container">
        <div class="tls-profile-hero-inner">

            <!-- Avatar -->
            <div class="tls-ph-avatar-wrap">
                <?php if ( $avatar ) : ?>
                    <img src="<?php echo esc_url($avatar); ?>" alt="" class="tls-ph-avatar">
                <?php else : ?>
                    <div class="tls-ph-avatar tls-ph-avatar--initials"><?php echo esc_html($initials); ?></div>
                <?php endif; ?>
            </div>

            <!-- Bilgiler -->
            <div class="tls-ph-info">
                <h1 class="tls-ph-name"><?php echo esc_html($user->display_name); ?></h1>
                <p class="tls-ph-email"><?php echo esc_html($user->user_email); ?></p>
                <div class="tls-ph-stats">
                    <div class="tls-ph-stat">
                        <strong><?php echo $cnt_total; ?></strong>
                        <span>In Library</span>
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

            <!-- Streak & Hedef (sağ köşe) -->
            <div class="tls-ph-badges">
                <?php if ( $streak > 0 ) : ?>
                <div class="tls-ph-badge tls-ph-badge--streak">
                    <span class="tls-ph-badge-icon">🔥</span>
                    <div>
                        <strong><?php echo $streak; ?></strong>
                        <span>day streak</span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ( $reading_goal > 0 ) : ?>
                <div class="tls-ph-badge tls-ph-badge--goal">
                    <span class="tls-ph-badge-icon">📚</span>
                    <div>
                        <strong><?php echo $year_read; ?>/<?php echo $reading_goal; ?></strong>
                        <span><?php echo $current_year; ?> goal</span>
                    </div>
                </div>
                <?php endif; ?>
                <a class="tls-ph-action-btn tls-ph-signout" id="tls-ph-signout" href="#" role="button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    Sign Out
                </a>
            </div>

        </div>
    </div>
</div>

<!-- ════════════════════════════════
     TAB NAV
════════════════════════════════ -->
<div class="tls-profile-nav">
    <div class="container">
        <nav class="tls-profile-tabs">
            <a class="tls-profile-tab<?php echo $active_tab === 'library'  ? ' tls-profile-tab--active' : ''; ?>"
               href="<?php echo esc_url(home_url('/profile/?tab=library')); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                My Library
            </a>
            <a class="tls-profile-tab<?php echo $active_tab === 'stats'    ? ' tls-profile-tab--active' : ''; ?>"
               href="<?php echo esc_url(home_url('/profile/?tab=stats')); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Statistics
            </a>
            <a class="tls-profile-tab<?php echo $active_tab === 'settings' ? ' tls-profile-tab--active' : ''; ?>"
               href="<?php echo esc_url(home_url('/profile/?tab=settings')); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                Settings
            </a>
        </nav>
    </div>
</div>

<!-- ════════════════════════════════
     KİTAPLIK TAB
════════════════════════════════ -->
<div class="tls-profile-content<?php echo $active_tab !== 'library' ? ' tls-tab-hidden' : ''; ?>" id="tab-library">
    <div class="container">

        <div class="tls-lib-filter-wrap">
            <button class="tls-lib-filter tls-lib-filter--active" data-filter="all">
                All <span><?php echo $cnt_total; ?></span>
            </button>
            <button class="tls-lib-filter" data-filter="want">
                Want to Read <span><?php echo $cnt_want; ?></span>
            </button>
            <button class="tls-lib-filter" data-filter="reading">
                Reading <span><?php echo $cnt_reading; ?></span>
            </button>
            <button class="tls-lib-filter" data-filter="read">
                Finished <span><?php echo $cnt_read; ?></span>
            </button>
        </div>

        <div id="tls-lib-loading" class="tls-lib-page-loading">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="26" height="26"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
            Loading your library…
        </div>

        <div id="tls-lib-grid" class="tls-lib-page-grid" style="display:none;"></div>

        <div id="tls-lib-empty" class="tls-lib-page-empty" style="display:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="52" height="52"><path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0118 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
            <h3>No books here yet</h3>
            <p>Browse the archive and mark books to build your library.</p>
            <a class="tls-botw-cta" href="<?php echo esc_url(home_url('/')); ?>">Browse the Archive →</a>
        </div>

    </div>
</div>

<!-- ════════════════════════════════
     İSTATİSTİKLER TAB
════════════════════════════════ -->
<div class="tls-profile-content<?php echo $active_tab !== 'stats' ? ' tls-tab-hidden' : ''; ?>" id="tab-stats">
    <div class="container">
        <div class="tls-stats-layout">

            <!-- Sol: Donut grafik -->
            <div class="tls-stats-card tls-stats-card--donut">
                <h2 class="tls-stats-card-title">Reading breakdown</h2>

                <?php
                $total_for_chart = max(1, $cnt_total);
                $pct_read    = round($cnt_read    / $total_for_chart * 100);
                $pct_reading = round($cnt_reading / $total_for_chart * 100);
                $pct_want    = 100 - $pct_read - $pct_reading;

                /* SVG donut değerleri */
                $r       = 70; /* yarıçap */
                $cx      = 100;
                $cy      = 100;
                $circ    = 2 * M_PI * $r; /* çevre */

                function donut_arc($pct, $offset, $r, $circ) {
                    $len = ($pct / 100) * $circ;
                    $gap = $circ - $len;
                    return 'stroke-dasharray="' . round($len,2) . ' ' . round($gap,2) . '" stroke-dashoffset="' . (-$offset) . '"';
                }

                $offset_read    = 0;
                $offset_reading = ($pct_read / 100) * $circ;
                $offset_want    = $offset_reading + ($pct_reading / 100) * $circ;
                ?>

                <div class="tls-donut-wrap">
                    <svg viewBox="0 0 200 200" width="200" height="200" aria-hidden="true">
                        <!-- Background ring -->
                        <circle cx="<?php echo $cx; ?>" cy="<?php echo $cy; ?>" r="<?php echo $r; ?>"
                                fill="none" stroke="var(--tls-border)" stroke-width="18"/>
                        <!-- Read (yeşil) -->
                        <?php if ($cnt_read > 0) : ?>
                        <circle cx="<?php echo $cx; ?>" cy="<?php echo $cy; ?>" r="<?php echo $r; ?>"
                                fill="none" stroke="#00ab6b" stroke-width="18"
                                stroke-linecap="butt"
                                <?php echo donut_arc($pct_read, $offset_read, $r, $circ); ?>
                                transform="rotate(-90 <?php echo $cx; ?> <?php echo $cy; ?>)"/>
                        <?php endif; ?>
                        <!-- Reading (altın) -->
                        <?php if ($cnt_reading > 0) : ?>
                        <circle cx="<?php echo $cx; ?>" cy="<?php echo $cy; ?>" r="<?php echo $r; ?>"
                                fill="none" stroke="var(--tls-gold)" stroke-width="18"
                                stroke-linecap="butt"
                                <?php echo donut_arc($pct_reading, $offset_reading, $r, $circ); ?>
                                transform="rotate(-90 <?php echo $cx; ?> <?php echo $cy; ?>)"/>
                        <?php endif; ?>
                        <!-- Want (pembe) -->
                        <?php if ($cnt_want > 0) : ?>
                        <circle cx="<?php echo $cx; ?>" cy="<?php echo $cy; ?>" r="<?php echo $r; ?>"
                                fill="none" stroke="#b84c6e" stroke-width="18"
                                stroke-linecap="butt"
                                <?php echo donut_arc($pct_want, $offset_want, $r, $circ); ?>
                                transform="rotate(-90 <?php echo $cx; ?> <?php echo $cy; ?>)"/>
                        <?php endif; ?>
                        <!-- Merkez sayı -->
                        <text x="<?php echo $cx; ?>" y="<?php echo $cy - 8; ?>"
                              text-anchor="middle"
                              font-family="var(--tls-serif)"
                              font-size="32"
                              fill="var(--tls-bg-dark)"><?php echo $cnt_total; ?></text>
                        <text x="<?php echo $cx; ?>" y="<?php echo $cy + 14; ?>"
                              text-anchor="middle"
                              font-family="var(--tls-sans)"
                              font-size="11"
                              fill="var(--tls-muted)"
                              letter-spacing="1">BOOKS</text>
                    </svg>

                    <div class="tls-donut-legend">
                        <div class="tls-donut-legend-item">
                            <span class="tls-donut-dot" style="background:#00ab6b;"></span>
                            <span class="tls-donut-label">Finished</span>
                            <strong><?php echo $cnt_read; ?></strong>
                        </div>
                        <div class="tls-donut-legend-item">
                            <span class="tls-donut-dot" style="background:var(--tls-gold);"></span>
                            <span class="tls-donut-label">Currently reading</span>
                            <strong><?php echo $cnt_reading; ?></strong>
                        </div>
                        <div class="tls-donut-legend-item">
                            <span class="tls-donut-dot" style="background:#b84c6e;"></span>
                            <span class="tls-donut-label">Want to read</span>
                            <strong><?php echo $cnt_want; ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sağ: Yıllık hedef + streak -->
            <div class="tls-stats-right">

                <!-- Yıllık hedef kartı -->
                <div class="tls-stats-card">
                    <div class="tls-goal-header">
                        <h2 class="tls-stats-card-title"><?php echo $current_year; ?> Reading Goal</h2>
                        <button class="tls-goal-edit-btn" id="tls-goal-edit-btn" type="button" title="Edit goal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                    </div>

                    <?php
                    $goal_pct = $reading_goal > 0 ? min(100, round($year_read / $reading_goal * 100)) : 0;
                    $books_left = max(0, $reading_goal - $year_read);
                    ?>

                    <div class="tls-goal-numbers">
                        <span class="tls-goal-current"><?php echo $year_read; ?></span>
                        <span class="tls-goal-sep">/</span>
                        <span class="tls-goal-target" id="tls-goal-display"><?php echo $reading_goal; ?></span>
                        <span class="tls-goal-unit">books</span>
                    </div>

                    <!-- İlerleme çubuğu -->
                    <div class="tls-goal-bar-wrap">
                        <div class="tls-goal-bar">
                            <div class="tls-goal-bar-fill" style="width:<?php echo $goal_pct; ?>%;"></div>
                        </div>
                        <span class="tls-goal-pct"><?php echo $goal_pct; ?>%</span>
                    </div>

                    <p class="tls-goal-hint">
                        <?php if ( $goal_pct >= 100 ) : ?>
                            🎉 Goal achieved! Great job!
                        <?php elseif ( $books_left === 1 ) : ?>
                            One more book to reach your goal!
                        <?php else : ?>
                            <?php echo $books_left; ?> books left to reach your <?php echo $current_year; ?> goal.
                        <?php endif; ?>
                    </p>

                    <!-- Hedef düzenleme formu (gizli) -->
                    <div class="tls-goal-form" id="tls-goal-form" style="display:none;">
                        <label class="tls-goal-form-label">Set your <?php echo $current_year; ?> goal</label>
                        <div class="tls-goal-form-row">
                            <input type="number" id="tls-goal-input" class="tls-goal-input"
                                   min="1" max="365"
                                   value="<?php echo $reading_goal; ?>"
                                   placeholder="e.g. 24">
                            <button class="tls-goal-save-btn" id="tls-goal-save" type="button">Save</button>
                        </div>
                    </div>
                </div>

                <!-- Streak kartı -->
                <div class="tls-stats-card tls-stats-card--streak">
                    <h2 class="tls-stats-card-title">Reading Streak</h2>
                    <div class="tls-streak-display">
                        <div class="tls-streak-flame">🔥</div>
                        <div class="tls-streak-info">
                            <strong class="tls-streak-num"><?php echo $streak; ?></strong>
                            <span class="tls-streak-label">consecutive day<?php echo $streak !== 1 ? 's' : ''; ?></span>
                        </div>
                    </div>
                    <?php if ($streak === 0) : ?>
                    <p class="tls-streak-tip">Mark a book as "Reading" or "Finished" today to start a streak.</p>
                    <?php elseif ($streak < 7) : ?>
                    <p class="tls-streak-tip">Keep going! <?php echo 7 - $streak; ?> more day<?php echo (7-$streak)!==1?'s':''; ?> to reach a week streak.</p>
                    <?php else : ?>
                    <p class="tls-streak-tip">Impressive! You've been reading for <?php echo $streak; ?> days in a row.</p>
                    <?php endif; ?>
                </div>

                <!-- Kategorilere göre dağılım -->
                <?php
                /* Kullanıcının okuduğu kitapların kategorileri */
                $read_metas = $wpdb->get_col( $wpdb->prepare(
                    "SELECT meta_key FROM {$wpdb->usermeta}
                     WHERE user_id = %d AND meta_key LIKE '_tls_reading_status_%%'",
                    $uid
                ) );
                $cat_counts = [];
                foreach ( $read_metas as $mk ) {
                    $pid  = (int) str_replace('_tls_reading_status_', '', $mk);
                    $cats = get_the_category($pid);
                    foreach ( $cats as $c ) {
                        $cat_counts[$c->name] = ($cat_counts[$c->name] ?? 0) + 1;
                    }
                }
                arsort($cat_counts);
                $top_cats = array_slice($cat_counts, 0, 5, true);
                $max_count = $top_cats ? max($top_cats) : 1;
                if ( ! empty($top_cats) ) :
                ?>
                <div class="tls-stats-card">
                    <h2 class="tls-stats-card-title">Top categories</h2>
                    <div class="tls-top-cats">
                        <?php foreach ( $top_cats as $cat_name => $count ) :
                            $bar_pct = round($count / $max_count * 100);
                        ?>
                        <div class="tls-top-cat-row">
                            <span class="tls-top-cat-name"><?php echo esc_html($cat_name); ?></span>
                            <div class="tls-top-cat-bar-wrap">
                                <div class="tls-top-cat-bar" style="width:<?php echo $bar_pct; ?>%;"></div>
                            </div>
                            <span class="tls-top-cat-count"><?php echo $count; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════
     AYARLAR TAB
════════════════════════════════ -->
<div class="tls-profile-content<?php echo $active_tab !== 'settings' ? ' tls-tab-hidden' : ''; ?>" id="tab-settings">
    <div class="container">
        <div class="tls-settings-grid">

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

            <div class="tls-settings-card">
                <h2 class="tls-settings-card-title">Change Password</h2>
                <div id="tls-pass-msg" class="tls-settings-msg" style="display:none;"></div>
                <div class="tls-settings-field">
                    <label class="tls-settings-label">Current Password</label>
                    <input class="tls-settings-input" type="password" id="s-pass-old" placeholder="••••••••">
                </div>
                <div class="tls-settings-field">
                    <label class="tls-settings-label">New Password <span style="color:var(--tls-muted);font-weight:400;">(min. 6 chars)</span></label>
                    <input class="tls-settings-input" type="password" id="s-pass-new" placeholder="••••••••">
                </div>
                <button class="tls-settings-save-btn" id="s-save-pass">Update Password</button>
            </div>

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
                    <div class="tls-reading-stat-row">
                        <span><?php echo $current_year; ?> goal progress</span>
                        <strong><?php echo $year_read; ?>/<?php echo $reading_goal; ?></strong>
                    </div>
                    <div class="tls-reading-stat-row">
                        <span>Current streak</span>
                        <strong><?php echo $streak; ?> day<?php echo $streak!==1?'s':''; ?></strong>
                    </div>
                </div>
            </div>

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
/* ════════ PROFILE PAGE v2 ════════ */
.tls-profile-page { min-height: 80vh; background: var(--tls-bg); }
.tls-tab-hidden { display: none !important; }

/* Hero */
.tls-profile-hero { background: var(--tls-bg-dark); padding: 52px 0 40px; }
.tls-profile-hero-inner {
  display: flex; align-items: flex-start; gap: 24px; flex-wrap: wrap;
}
.tls-ph-avatar-wrap { flex-shrink: 0; }
.tls-ph-avatar {
  width: 88px; height: 88px;
  border-radius: 50%; object-fit: cover; display: block;
  border: 3px solid rgba(200,161,101,.3);
}
.tls-ph-avatar--initials {
  background: var(--tls-gold);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--tls-serif); font-size: 36px; color: #fff;
}
.tls-ph-info { flex: 1; min-width: 0; }
.tls-ph-name {
  font-family: var(--tls-serif);
  font-size: clamp(22px,4vw,34px); font-weight: 400;
  color: #fff; margin: 0 0 4px;
}
.tls-ph-email {
  font-family: var(--tls-sans); font-size: 13px;
  color: rgba(255,255,255,.35); margin: 0 0 18px;
}
.tls-ph-stats { display: flex; gap: 24px; flex-wrap: wrap; }
.tls-ph-stat { text-align: center; }
.tls-ph-stat strong {
  display: block; font-family: var(--tls-serif);
  font-size: 24px; font-weight: 400; color: #fff;
  line-height: 1; margin-bottom: 3px;
}
.tls-ph-stat span {
  font-family: var(--tls-sans); font-size: 10px;
  color: rgba(255,255,255,.3); text-transform: uppercase; letter-spacing: .08em;
}

/* Badges & sign out */
.tls-ph-badges {
  display: flex; flex-direction: column;
  align-items: flex-end; gap: 10px; margin-left: auto;
}
.tls-ph-badge {
  display: flex; align-items: center; gap: 8px;
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 10px; padding: 8px 12px;
}
.tls-ph-badge-icon { font-size: 18px; line-height: 1; }
.tls-ph-badge div { display: flex; flex-direction: column; }
.tls-ph-badge strong {
  font-family: var(--tls-sans); font-size: 15px;
  font-weight: 700; color: #fff; line-height: 1;
}
.tls-ph-badge span {
  font-family: var(--tls-sans); font-size: 10px;
  color: rgba(255,255,255,.35); text-transform: uppercase; letter-spacing: .06em;
}
.tls-ph-action-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: 20px;
  font-family: var(--tls-sans); font-size: 11px; font-weight: 600;
  cursor: pointer; transition: all .15s;
  letter-spacing: .04em; text-decoration: none !important;
}
.tls-ph-signout {
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.1);
  color: rgba(255,255,255,.45) !important;
}
.tls-ph-signout:hover { background: rgba(255,255,255,.12); color: #fff !important; }

/* Tab nav */
.tls-profile-nav {
  background: #fff; border-bottom: 1px solid var(--tls-border);
  position: sticky; top: var(--tls-nav-h); z-index: 90;
}
.tls-profile-tabs { display: flex; }
.tls-profile-tab {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 16px 20px; background: none; border: none;
  font-family: var(--tls-sans); font-size: 13px; font-weight: 600;
  color: var(--tls-muted); cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: color .15s, border-color .15s;
  text-decoration: none !important;
}
.tls-profile-tab:hover { color: var(--tls-bg-dark) !important; }
.tls-profile-tab--active { color: var(--tls-bg-dark) !important; border-bottom-color: var(--tls-gold); }

/* Library */
.tls-profile-content { padding: 36px 0 64px; }
.tls-lib-filter-wrap { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 28px; }
.tls-lib-filter {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; background: #fff; border: 1px solid var(--tls-border);
  border-radius: 24px; font-family: var(--tls-sans); font-size: 13px; font-weight: 500;
  color: var(--tls-muted); cursor: pointer; transition: all .15s;
}
.tls-lib-filter span {
  background: var(--tls-bg); border-radius: 10px; padding: 1px 7px; font-size: 11px;
}
.tls-lib-filter--active, .tls-lib-filter:hover {
  border-color: var(--tls-bg-dark); background: var(--tls-bg-dark); color: #fff;
}
.tls-lib-filter--active span, .tls-lib-filter:hover span {
  background: rgba(255,255,255,.15);
}
.tls-lib-page-loading {
  display: flex; flex-direction: column; align-items: center; gap: 12px;
  padding: 64px; color: var(--tls-muted); font-family: var(--tls-sans); font-size: 13px;
}
.tls-lib-page-loading svg { animation: tlsProfSpin .9s linear infinite; color: var(--tls-gold); }
@keyframes tlsProfSpin { to { transform: rotate(360deg); } }
.tls-lib-page-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(300px,1fr)); gap: 14px;
}
.tls-lib-page-item {
  background: #fff; border: 1px solid var(--tls-border); border-radius: 12px;
  display: flex; align-items: center; gap: 14px; padding: 14px;
  transition: box-shadow .2s, transform .2s; text-decoration: none;
}
.tls-lib-page-item:hover { box-shadow: 0 6px 24px rgba(0,0,0,.08); transform: translateY(-1px); }
.tls-lib-page-item-cover img {
  width: 50px; height: 70px; object-fit: cover; border-radius: 4px;
  display: block; box-shadow: 0 4px 12px rgba(0,0,0,.2);
}
.tls-lib-cover-ph {
  width: 50px; height: 70px; background: var(--tls-bg-dark); border-radius: 4px;
  display: flex; align-items: center; justify-content: center;
  font-family: var(--tls-serif); font-size: 20px; color: rgba(255,255,255,.3);
}
.tls-lib-page-item-body { flex: 1; min-width: 0; }
.tls-lib-page-badge {
  display: inline-block; padding: 2px 8px; border-radius: 10px;
  font-family: var(--tls-sans); font-size: 9px; font-weight: 700;
  color: #fff; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 4px;
}
.tls-lib-page-title {
  display: block; font-family: var(--tls-serif); font-size: 14px;
  color: var(--tls-bg-dark); white-space: nowrap; overflow: hidden;
  text-overflow: ellipsis; margin-bottom: 2px; line-height: 1.3;
}
.tls-lib-page-author {
  font-family: var(--tls-sans); font-style: italic;
  font-size: 12px; color: var(--tls-muted);
}
.tls-lib-page-empty {
  display: flex; flex-direction: column; align-items: center;
  text-align: center; gap: 12px; padding: 80px 24px; color: var(--tls-muted);
}
.tls-lib-page-empty h3 {
  font-family: var(--tls-serif); font-size: 22px; font-weight: 400;
  color: var(--tls-bg-dark); margin: 0;
}
.tls-lib-page-empty p {
  font-family: var(--tls-sans); font-size: 14px; color: var(--tls-muted);
  margin: 0; max-width: 340px; line-height: 1.6;
}

/* Stats layout */
.tls-stats-layout {
  display: grid; grid-template-columns: 300px 1fr; gap: 24px; align-items: start;
}
@media (max-width: 768px) { .tls-stats-layout { grid-template-columns: 1fr; } }
.tls-stats-right { display: flex; flex-direction: column; gap: 20px; }

/* Stats card */
.tls-stats-card {
  background: #fff; border: 1px solid var(--tls-border);
  border-radius: 14px; padding: 24px;
}
.tls-stats-card-title {
  font-family: var(--tls-serif); font-size: 17px; font-weight: 400;
  color: var(--tls-bg-dark); margin: 0 0 20px;
  padding-bottom: 14px; border-bottom: 1px solid var(--tls-border);
}

/* Donut */
.tls-donut-wrap { display: flex; flex-direction: column; align-items: center; gap: 20px; }
.tls-donut-legend { display: flex; flex-direction: column; gap: 10px; width: 100%; }
.tls-donut-legend-item {
  display: flex; align-items: center; gap: 8px;
  font-family: var(--tls-sans); font-size: 13px;
}
.tls-donut-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.tls-donut-label { flex: 1; color: var(--tls-muted); }
.tls-donut-legend-item strong { color: var(--tls-bg-dark); font-size: 15px; }

/* Goal */
.tls-goal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid var(--tls-border); }
.tls-goal-header .tls-stats-card-title { margin: 0; padding: 0; border: none; }
.tls-goal-edit-btn {
  background: none; border: 1px solid var(--tls-border); border-radius: 6px;
  padding: 5px 8px; cursor: pointer; color: var(--tls-muted);
  transition: all .15s;
}
.tls-goal-edit-btn:hover { border-color: var(--tls-gold); color: var(--tls-gold); }
.tls-goal-numbers {
  display: flex; align-items: baseline; gap: 4px; margin-bottom: 14px;
}
.tls-goal-current {
  font-family: var(--tls-serif); font-size: 42px; font-weight: 400;
  color: var(--tls-bg-dark); line-height: 1;
}
.tls-goal-sep, .tls-goal-target {
  font-family: var(--tls-serif); font-size: 24px;
  color: var(--tls-muted); line-height: 1;
}
.tls-goal-unit {
  font-family: var(--tls-sans); font-size: 13px;
  color: var(--tls-muted); margin-left: 6px;
}
.tls-goal-bar-wrap { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.tls-goal-bar { flex: 1; height: 8px; background: var(--tls-border); border-radius: 4px; overflow: hidden; }
.tls-goal-bar-fill {
  height: 100%; background: var(--tls-gold); border-radius: 4px;
  transition: width .6s ease;
}
.tls-goal-pct { font-family: var(--tls-sans); font-size: 12px; color: var(--tls-muted); flex-shrink: 0; }
.tls-goal-hint { font-family: var(--tls-sans); font-size: 13px; color: var(--tls-muted); margin: 0; }
.tls-goal-form { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--tls-border); }
.tls-goal-form-label {
  display: block; font-family: var(--tls-sans); font-size: 11px; font-weight: 700;
  letter-spacing: .08em; text-transform: uppercase; color: var(--tls-muted);
  margin-bottom: 8px;
}
.tls-goal-form-row { display: flex; gap: 8px; }
.tls-goal-input {
  flex: 1; padding: 9px 12px;
  background: var(--tls-bg); border: 1.5px solid var(--tls-border);
  border-radius: 8px; font-family: var(--tls-sans); font-size: 14px;
  color: var(--tls-bg-dark); outline: none; transition: border-color .2s;
}
.tls-goal-input:focus { border-color: var(--tls-gold); }
.tls-goal-save-btn {
  padding: 9px 18px; background: var(--tls-bg-dark); color: #fff;
  border: none; border-radius: 8px; font-family: var(--tls-sans); font-size: 12px;
  font-weight: 700; cursor: pointer; transition: background .2s;
}
.tls-goal-save-btn:hover { background: var(--tls-gold); }

/* Streak */
.tls-streak-display { display: flex; align-items: center; gap: 14px; margin-bottom: 10px; }
.tls-streak-flame { font-size: 36px; line-height: 1; }
.tls-streak-info { display: flex; flex-direction: column; }
.tls-streak-num {
  font-family: var(--tls-serif); font-size: 38px; font-weight: 400;
  color: var(--tls-bg-dark); line-height: 1;
}
.tls-streak-label {
  font-family: var(--tls-sans); font-size: 12px; color: var(--tls-muted);
  text-transform: uppercase; letter-spacing: .06em;
}
.tls-streak-tip { font-family: var(--tls-sans); font-size: 13px; color: var(--tls-muted); margin: 0; }

/* Top cats */
.tls-top-cats { display: flex; flex-direction: column; gap: 10px; }
.tls-top-cat-row { display: flex; align-items: center; gap: 10px; }
.tls-top-cat-name { font-family: var(--tls-sans); font-size: 13px; color: var(--tls-bg-dark); min-width: 100px; }
.tls-top-cat-bar-wrap { flex: 1; height: 6px; background: var(--tls-border); border-radius: 3px; overflow: hidden; }
.tls-top-cat-bar { height: 100%; background: var(--tls-gold); border-radius: 3px; transition: width .5s ease; }
.tls-top-cat-count { font-family: var(--tls-sans); font-size: 12px; color: var(--tls-muted); min-width: 20px; text-align: right; }

/* Settings */
.tls-settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-width: 900px; }
@media (max-width: 680px) { .tls-settings-grid { grid-template-columns: 1fr; } }
.tls-settings-card {
  background: #fff; border: 1px solid var(--tls-border); border-radius: 12px; padding: 24px;
}
.tls-settings-card-title {
  font-family: var(--tls-serif); font-size: 18px; font-weight: 400;
  color: var(--tls-bg-dark); margin: 0 0 20px;
  padding-bottom: 16px; border-bottom: 1px solid var(--tls-border);
}
.tls-settings-msg {
  padding: 10px 14px; border-radius: 8px;
  font-family: var(--tls-sans); font-size: 13px; margin-bottom: 14px;
}
.tls-settings-field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
.tls-settings-label {
  font-family: var(--tls-sans); font-size: 11px; font-weight: 700;
  letter-spacing: .08em; text-transform: uppercase; color: var(--tls-bg-dark);
}
.tls-settings-input {
  font-family: var(--tls-sans); font-size: 14px; color: var(--tls-bg-dark);
  background: var(--tls-bg); border: 1.5px solid var(--tls-border);
  border-radius: 8px; padding: 10px 14px; width: 100%; box-sizing: border-box;
  outline: none; transition: border-color .2s; -webkit-appearance: none;
}
.tls-settings-input:focus { border-color: var(--tls-gold); }
.tls-settings-save-btn {
  display: inline-flex; align-items: center; padding: 11px 24px;
  background: var(--tls-bg-dark); color: #fff;
  border: 1px solid transparent; border-radius: 8px;
  font-family: var(--tls-sans); font-size: 12px; font-weight: 700;
  letter-spacing: .06em; text-transform: uppercase;
  cursor: pointer; transition: background .2s; margin-top: 4px;
}
.tls-settings-save-btn:hover { background: #2a2620; }
.tls-settings-save-btn--outline {
  background: transparent; border-color: var(--tls-border); color: var(--tls-muted);
}
.tls-settings-save-btn--outline:hover { border-color: #b91c1c; color: #b91c1c; background: transparent; }
.tls-reading-stats { display: flex; flex-direction: column; gap: 12px; }
.tls-reading-stat-row {
  display: flex; align-items: center; justify-content: space-between;
  font-family: var(--tls-sans); font-size: 14px; color: var(--tls-muted);
  padding-bottom: 12px; border-bottom: 1px solid var(--tls-border);
}
.tls-reading-stat-row:last-child { border-bottom: none; padding-bottom: 0; }
.tls-reading-stat-row strong { font-size: 17px; color: var(--tls-bg-dark); }

@media (max-width: 768px) {
  .tls-profile-hero-inner { flex-direction: column; }
  .tls-ph-badges { margin-left: 0; flex-direction: row; flex-wrap: wrap; align-items: flex-start; }
}
</style>

<script>
(function(){
    var ajax      = '<?php echo esc_js($ajax); ?>';
    var authNonce = '<?php echo esc_js($auth_nonce); ?>';
    var statusColors  = { want:'#b84c6e', reading:'#B8922A', read:'#00ab6b' };
    var statusLabels  = { want:'Want to Read', reading:'Currently Reading', read:'Finished' };

    /* ── Kitaplık yükleme ── */
    function loadLibrary(filter) {
        var loading = document.getElementById('tls-lib-loading');
        var grid    = document.getElementById('tls-lib-grid');
        var empty   = document.getElementById('tls-lib-empty');
        if (!loading) return;
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

    if (document.getElementById('tab-library') &&
        !document.getElementById('tab-library').classList.contains('tls-tab-hidden')) {
        loadLibrary('all');
    }

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
    ['tls-ph-signout','tls-ph-signout-2'].forEach(function(id){
        var el = document.getElementById(id);
        if (el) el.addEventListener('click', function(e){ e.preventDefault(); doSignout(); });
    });

    /* ── Yıllık hedef düzenleme ── */
    var editBtn  = document.getElementById('tls-goal-edit-btn');
    var goalForm = document.getElementById('tls-goal-form');
    var goalSave = document.getElementById('tls-goal-save');
    var goalDisp = document.getElementById('tls-goal-display');

    if (editBtn && goalForm) {
        editBtn.addEventListener('click', function(){
            goalForm.style.display = goalForm.style.display === 'none' ? '' : 'none';
        });
    }
    if (goalSave) {
        goalSave.addEventListener('click', function(){
            var val = parseInt(document.getElementById('tls-goal-input').value, 10);
            if (!val || val < 1 || val > 365) return;

            var fd = new FormData();
            fd.append('action',   'tls_save_goal');
            fd.append('nonce',    authNonce);
            fd.append('goal',     val);
            fd.append('year',     '<?php echo $current_year; ?>');
            fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success) {
                    if (goalDisp) goalDisp.textContent = val;
                    if (goalForm) goalForm.style.display = 'none';
                }
            });
        });
    }

    /* ── Profil ayarlar ── */
    function settingsMsg(elId, text, type){
        var el = document.getElementById(elId);
        if (!el) return;
        el.textContent   = text;
        el.style.display = text ? 'block' : 'none';
        el.style.background = type==='success' ? '#f0fdf4' : '#fff5f5';
        el.style.color      = type==='success' ? '#15803d' : '#b91c1c';
        el.style.border     = '1px solid ' + (type==='success' ? '#86efac' : '#fca5a5');
    }
    function ajaxPost(data, cb){
        data.nonce = authNonce;
        var fd = new FormData();
        for (var k in data) fd.append(k, data[k]);
        fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(cb);
    }
    var saveProfile = document.getElementById('s-save-profile');
    if (saveProfile) {
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
    if (savePass) {
        savePass.addEventListener('click', function(){
            var old = document.getElementById('s-pass-old').value;
            var nw  = document.getElementById('s-pass-new').value;
            if (!old || !nw) { settingsMsg('tls-pass-msg','Please fill both password fields.','error'); return; }
            ajaxPost({
                action:'tls_update_profile', tls_pass_old:old, tls_pass:nw,
            }, function(res){
                settingsMsg('tls-pass-msg', res.success?res.data.message:res.data.message, res.success?'success':'error');
                if (res.success) {
                    document.getElementById('s-pass-old').value='';
                    document.getElementById('s-pass-new').value='';
                }
            });
        });
    }
})();
</script>

<?php get_footer(); ?>
