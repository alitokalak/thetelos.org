/* ═══════════════════════════════════════
   THE TELOS — Main JS
   Rating, Reading Status, Nav, Search
═══════════════════════════════════════ */
(function () {
  'use strict';

  /* ── DOM ready ── */
  document.addEventListener('DOMContentLoaded', init);

  function init() {
    initNav();
    initSearch();
    initRating();
    initBackToTop();
    initStickyNav();
    setContentOffset();
    initReadProgress();
    initReadingPaths();
    initSingleParallax();
  }

  /* ────────────────────────────
     NAV — hide on scroll down
  ──────────────────────────── */
  function initStickyNav() {
    var header = document.querySelector('.tls-header');
    if (!header) return;
    var lastY = 0, ticking = false;
    window.addEventListener('scroll', function () {
      if (!ticking) {
        requestAnimationFrame(function () {
          var y = window.scrollY;
          if (y > lastY && y > 120) {
            header.classList.add('nav-hidden');
          } else {
            header.classList.remove('nav-hidden');
          }
          lastY = y;
          ticking = false;
        });
        ticking = true;
      }
    });
  }

  function setContentOffset() {
    var header = document.querySelector('.tls-header');
    var content = document.querySelector('.site-content');
    if (header && content) {
      content.style.marginTop = header.offsetHeight + 'px';
    }
  }

  /* ────────────────────────────
     MOBILE NAV toggle (hamburger)
  ──────────────────────────── */
  function initNav() {
    var hamburger = document.getElementById('tls-hamburger');
    var drawer    = document.getElementById('tls-mobile-menu');
    if (!hamburger || !drawer) return;

    /* Create backdrop */
    var backdrop = document.createElement('div');
    backdrop.className = 'tls-mobile-menu-backdrop';
    document.body.appendChild(backdrop);

    function openMenu() {
      hamburger.classList.add('open');
      drawer.classList.add('open');
      backdrop.classList.add('open');
      hamburger.setAttribute('aria-expanded', 'true');
      drawer.setAttribute('aria-hidden', 'false');
    }

    function closeMenu() {
      hamburger.classList.remove('open');
      drawer.classList.remove('open');
      backdrop.classList.remove('open');
      hamburger.setAttribute('aria-expanded', 'false');
      drawer.setAttribute('aria-hidden', 'true');
    }

    hamburger.addEventListener('click', function () {
      if (hamburger.classList.contains('open')) { closeMenu(); } else { openMenu(); }
    });

    backdrop.addEventListener('click', closeMenu);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeMenu();
    });

    /* Close when a menu link is tapped */
    drawer.addEventListener('click', function (e) {
      if (e.target.closest('a')) closeMenu();
    });

    /* Wire up mobile signin to the same auth overlay as the desktop user icon */
    var mobileSignin = document.getElementById('tls-mobile-signin');
    if (mobileSignin) {
      mobileSignin.addEventListener('click', function (e) {
        e.preventDefault();
        closeMenu();
        /* After login/register from the hamburger, go to the account page */
        window._tlsAuthRedirect = (window.tlsAuth && window.tlsAuth.profileUrl)
          ? window.tlsAuth.profileUrl
          : '/profile/';
        var authOverlay = document.getElementById('tls-auth-overlay');
        if (authOverlay) {
          authOverlay.style.display = 'flex';
          document.body.style.overflow = 'hidden';
        } else {
          /* Fallback: trigger desktop user icon click */
          var desktopUser = document.getElementById('tls-user-icon');
          if (desktopUser) desktopUser.click();
        }
      });
    }

    /* Legacy: old mobile toggle (no-op if element absent) */
    var toggle = document.querySelector('.tls-mobile-toggle');
    var legacyDrawer = document.querySelector('.tls-mobile-nav');
    if (toggle && legacyDrawer) {
      toggle.addEventListener('click', function () {
        legacyDrawer.classList.toggle('open');
      });
    }
  }

  /* ────────────────────────────
     SEARCH OVERLAY
  ──────────────────────────── */
  function initSearch() {
    var openBtns = document.querySelectorAll('[data-tls-search]');
    var overlay  = document.querySelector('.tls-search-overlay');
    var closeBtn = overlay && overlay.querySelector('.tls-search-close');
    var input    = overlay && overlay.querySelector('input[type="search"]');
    if (!overlay) return;

    openBtns.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        overlay.classList.add('open');
        if (input) setTimeout(function () { input.focus(); }, 50);
      });
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        overlay.classList.remove('open');
      });
    }

    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) overlay.classList.remove('open');
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') overlay.classList.remove('open');
    });
  }

  /* ────────────────────────────
     STAR RATING
  ──────────────────────────── */
  /* ────────────────────────────
     RATING
     Sadece "want to read" seçenlerinde KAPALI
     "reading" veya "read" seçenlerinde AKTİF
  ──────────────────────────── */
  function initRating() {
    var container = document.querySelector('.tls-stars[data-post-id]');
    if (!container) return;

    var postId     = container.dataset.postId;
    var stars      = container.querySelectorAll('.tls-star');
    var msgEl      = document.querySelector('.tls-rating-msg');
    var countEl    = document.querySelector('.tls-rating-count');
    var storageKey = 'tls_rated_' + postId;
    var userRating = parseInt(localStorage.getItem(storageKey) || '0', 10);

    renderStars(userRating, stars);

    /* Runtime'da auth ve status kontrol et */
    function getAuth() { return window.tlsAuth || {}; }

    function getUserStatus() {
      var wrap = document.querySelector('.tls-read-status[data-post-id]');
      if (!wrap) return '';
      var active = wrap.querySelector('.tls-status-btn.active');
      return active ? active.dataset.status : '';
    }

    function canRate() {
      /* DOM'daki active butona bak — güvenilir */
      var s = getUserStatus();
      return s === 'reading' || s === 'read';
    }

    function isLoggedInNow() {
      /* window._tlsState — footer.php'nin AJAX'tan doldurduğu state */
      if (window._tlsState && typeof window._tlsState.loggedIn !== 'undefined') {
        return window._tlsState.loggedIn;
      }
      /* Fallback: dropdown var mı? */
      return !!document.getElementById('tls-user-menu');
    }

    function updateRatingState() {
      var allowed = canRate();
      stars.forEach(function(star) {
        star.style.opacity  = allowed ? '1' : '0.4';
        star.style.cursor   = allowed ? 'pointer' : 'not-allowed';
        star.style.transition = 'opacity .2s';
      });
      if (msgEl) {
        if (!isLoggedInNow()) {
          msgEl.textContent = 'Sign in to rate this book.';
          msgEl.style.color = 'var(--tls-muted)';
        } else if (!allowed) {
          msgEl.textContent = 'Mark as "Reading" or "Finished" to rate.';
          msgEl.style.color = 'var(--tls-muted)';
        } else {
          if (!userRating) msgEl.textContent = '';
        }
      }
    }

    /* İlk render - _tlsState hazır olunca güncelle */
    setTimeout(updateRatingState, 100);
    /* _tlsState AJAX'tan dolunca tekrar güncelle */
    var ratingStateInterval = setInterval(function(){
      if (window._tlsState) {
        updateRatingState();
        clearInterval(ratingStateInterval);
      }
    }, 200);

    /* Status değişince güncelle */
    document.addEventListener('tls:statusChanged', updateRatingState);

    /* Hover */
    stars.forEach(function (star, i) {
      star.addEventListener('mouseenter', function () {
        if (!canRate()) return;
        highlightStars(i + 1, stars);
      });
    });
    container.addEventListener('mouseleave', function () {
      renderStars(userRating, stars);
    });

    /* Click */
    stars.forEach(function (star, i) {
      star.addEventListener('click', function () {
        if (!isLoggedInNow()) {
          var overlay = document.getElementById('tls-auth-overlay');
          if (overlay) { overlay.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
          return;
        }
        if (!canRate()) {
          if (msgEl) {
            msgEl.textContent = 'Please mark as "Reading" or "Finished" first.';
            msgEl.style.color = 'var(--tls-gold)';
            setTimeout(function(){ updateRatingState(); }, 2500);
          }
          return;
        }
        var newRating = i + 1;
        if (userRating === newRating) {
          userRating = 0;
          localStorage.removeItem(storageKey);
          renderStars(0, stars);
          if (msgEl) msgEl.textContent = '';
          submitRating(postId, 0, countEl);
          return;
        }
        userRating = newRating;
        localStorage.setItem(storageKey, String(newRating));
        renderStars(newRating, stars);
        if (msgEl) { msgEl.textContent = 'Thanks for rating!'; msgEl.style.color = 'var(--tls-green)'; }
        submitRating(postId, newRating, countEl);
      });
    });
  }

  function renderStars(rating, stars) {
    stars.forEach(function (star, i) {
      star.classList.toggle('filled', i < rating);
      star.classList.remove('hovered');
    });
  }

  function highlightStars(upTo, stars) {
    stars.forEach(function (star, i) {
      star.classList.toggle('hovered', i < upTo);
    });
  }

  function submitRating(postId, rating, countEl) {
    if (typeof thelosData === 'undefined') return;
    var body = 'action=thetelos_rate_book&nonce=' + thelosData.nonce +
               '&post_id=' + encodeURIComponent(postId) +
               '&rating=' + encodeURIComponent(rating);
    fetch(thelosData.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success && countEl && data.data) {
        var avg   = parseFloat(data.data.avg).toFixed(1);
        var count = data.data.count;
        var avgEl = countEl.querySelector('.tls-rating-avg');
        if (avgEl) avgEl.textContent = avg;
        var cntText = countEl.childNodes[countEl.childNodes.length - 1];
        if (cntText) cntText.textContent = ' (' + count + ' rating' + (count !== 1 ? 's' : '') + ')';
      }
    })
    .catch(function () {});
  }

  /* ────────────────────────────
     READING STATUS
     Giriş yaptıysa → user meta (sunucu)
     Giriş yapmadıysa → auth popup aç
  ──────────────────────────── */
  function initReadingStatus() {
    var btns = document.querySelectorAll('.tls-status-btn[data-status]');
    if (!btns.length) return;

    var wrap = document.querySelector('.tls-read-status[data-post-id]');
    if (!wrap) return;
    var pid = wrap.dataset.postId;

    btns.forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();

        var ajaxUrl = (window.tlsAuth && window.tlsAuth.ajaxUrl)
                   || (window.thelosData && window.thelosData.ajaxUrl)
                   || '';
        var nonce   = (window.tlsAuth && window.tlsAuth.statusNonce) || '';

        var status = btn.dataset.status;
        var current = wrap.querySelector('.tls-status-btn.active');
        var currentStatus = current ? current.dataset.status : '';
        var newStatus = (currentStatus === status) ? '' : status;

        /* Optimistik UI — hemen güncelle */
        btns.forEach(function(b) {
          b.classList.toggle('active', b.dataset.status === newStatus && newStatus !== '');
        });
        document.dispatchEvent(new Event('tls:statusChanged'));

        if (!ajaxUrl) return;

        var fd = new FormData();
        fd.append('action',  'tls_set_status');
        fd.append('nonce',   nonce);
        fd.append('post_id', pid);
        fd.append('status',  newStatus);

        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res){
          if (!res.success) {
            /* login_required → auth popup göster */
            var msg = res.data && res.data.message ? res.data.message : '';
            if (msg === 'login_required') {
              /* Geri al */
              btns.forEach(function(b) {
                b.classList.toggle('active', b.dataset.status === currentStatus);
              });
              document.dispatchEvent(new Event('tls:statusChanged'));
              /* Auth popup aç */
              var authOverlay = document.getElementById('tls-auth-overlay');
              if (authOverlay) {
                authOverlay.style.display = 'flex';
                document.body.style.overflow = 'hidden';
              }
            }
          }
        })
        .catch(function(){});
      });
    });
  }

  /* ────────────────────────────
     BACK TO TOP
  ──────────────────────────── */
  function initBackToTop() {
    var btn = document.querySelector('.tls-back-top');
    if (!btn) return;
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    window.addEventListener('scroll', function () {
      btn.style.opacity = window.scrollY > 600 ? '1' : '0';
      btn.style.pointerEvents = window.scrollY > 600 ? 'auto' : 'none';
    });
  }

  /* ────────────────────────────
     SCROLL PROGRESS BAR
     Sadece single post sayfasında aktif.
     Makale içeriğini referans alır —
     header veya footer scroll'u saymaz.
  ──────────────────────────── */
  function initReadProgress() {
    // Sadece post sayfasında çalış
    var article = document.querySelector('.tls-article-content');
    if (!article) return;

    // Bar elementini oluştur
    var bar = document.createElement('div');
    bar.id = 'tls-read-progress';
    document.body.appendChild(bar);

    var ticking = false;

    function updateBar() {
      var articleTop    = article.getBoundingClientRect().top + window.scrollY;
      var articleBottom = articleTop + article.offsetHeight;
      var winH          = window.innerHeight;

      // Makale başlangıcından sonuna kadar 0→100%
      var scrolled  = window.scrollY + winH - articleTop;
      var total     = articleBottom - articleTop;
      var pct       = Math.min( 100, Math.max( 0, (scrolled / total) * 100 ) );

      bar.style.width = pct + '%';

      // %100'e ulaşınca ince bir "tamamlandı" animasyonu
      if (pct >= 100) {
        bar.classList.add('completed');
      } else {
        bar.classList.remove('completed');
      }

      ticking = false;
    }

    window.addEventListener('scroll', function () {
      if (!ticking) {
        requestAnimationFrame(updateBar);
        ticking = true;
      }
    }, { passive: true });

    // İlk render
    updateBar();
  }

})();



/* ─────────────────────────────────────────
   SINGLE POST — Mobil Parallax Kart Efekti
───────────────────────────────────────── */
function initSingleParallax() {
  if (window.innerWidth > 768) return;

  var sidebar = document.querySelector('.tls-single-sidebar');
  var card    = document.querySelector('.tls-single-content');
  var trigger = document.querySelector('[data-status="reading"]');

  if (!sidebar || !card || !trigger) return;

  var winH = window.innerHeight;

  /* Pozisyonları scroll=0'da hesapla */
  var sRect               = sidebar.getBoundingClientRect();
  var tRect               = trigger.getBoundingClientRect();
  var triggerInSidebar    = (tRect.top + tRect.height / 2) - sRect.top;
  var stickyTop           = winH / 2 - triggerInSidebar;
  var sidebarDocTop       = sRect.top + window.scrollY;
  var parallaxStartScroll = sidebarDocTop - stickyTop;

  /* Sidebar sticky yap */
  sidebar.style.position = 'sticky';
  sidebar.style.top      = Math.round(stickyTop) + 'px';
  sidebar.style.zIndex   = '0';

  function onScroll() {
    var delta = window.scrollY - parallaxStartScroll;

    if (delta > 0) {
      /* Sidebar %40 hızda yukarı kayar (sticky pozisyonundan yukarı drift) */
      sidebar.style.transform = 'translateY(' + Math.round(-delta * 0.4) + 'px)';

      /* Blur: kart sidebar'ı kapattıkça artar, max 5px */
      var blur = Math.min(5, delta * 0.012);
      sidebar.style.filter = 'blur(' + blur.toFixed(1) + 'px)';

      /* Kart normal hızda — sadece full-width transform */
      card.style.transform = 'translateX(-50%)';
    } else {
      /* Trigger öncesi veya geri kaydırma — sıfırla */
      sidebar.style.transform = 'translateY(0)';
      sidebar.style.filter    = 'blur(0px)';
      card.style.transform    = 'translateX(-50%)';
    }
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
}


/* ────────────────────────────────────────────
   READING PATHS — Panel switcher + Save button
──────────────────────────────────────────── */
function initReadingPaths() {
  var panels  = Array.from(document.querySelectorAll('.tls-rp-panel'));
  var prevBtn = document.getElementById('tls-rp-prev');
  var nextBtn = document.getElementById('tls-rp-next');
  var counter = document.getElementById('tls-rp-counter');

  if (!panels.length) return;

  var total   = panels.length;
  var current = 0;
  var ajaxUrl = (window.ajaxurl || '/wp-admin/admin-ajax.php');

  function updateArrows() {
    if (prevBtn) prevBtn.disabled = (current === 0);
    if (nextBtn) nextBtn.disabled = (current === total - 1);
  }

  function updateCounter() {
    if (!counter) return;
    var pad = function(n) { return String(n).padStart(2, '0'); };
    counter.textContent = 'Path ' + pad(current + 1) + ' / ' + pad(total);
  }

  function goTo(i) {
    if (i < 0 || i >= total || i === current) return;
    panels[current].hidden = true;
    panels[current].classList.remove('is-active');
    current = i;
    panels[current].hidden = false;
    panels[current].classList.add('is-active');
    updateArrows();
    updateCounter();
  }

  if (prevBtn) prevBtn.addEventListener('click', function() { goTo(current - 1); });
  if (nextBtn) nextBtn.addEventListener('click', function() { goTo(current + 1); });
  updateArrows();

  /* ── "Add to Reading List" button — event delegation ── */
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-rp-save]');
    if (!btn) return;

    /* Already saved → go to profile */
    if (btn.classList.contains('is-saved')) {
      var profUrl = (window.tlsAuth && window.tlsAuth.profileUrl)
        ? window.tlsAuth.profileUrl + '?tab=readingpaths'
        : '/profile/?tab=readingpaths';
      window.location.href = profUrl;
      return;
    }

    if (btn.classList.contains('is-loading')) return;

    var listId = btn.dataset.listId;
    if (!listId) return;

    btn.classList.add('is-loading');

    var fd = new FormData();
    fd.append('action',  'tls_add_list_to_library');
    fd.append('list_id', listId);

    fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        btn.classList.remove('is-loading');
        if (res.success && res.data && res.data.action === 'added') {
          markSaved(btn);
        } else if (res.data && res.data.message === 'login_required') {
          window._tlsPendingListId = listId;
          var overlay = document.getElementById('tls-auth-overlay');
          if (overlay && typeof openOverlay === 'function') {
            openOverlay('tls-auth-overlay');
          } else if (overlay) {
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
          }
        }
      })
      .catch(function() { btn.classList.remove('is-loading'); });
  });

  /* ── Fetch saved state on load (logged-in users) ── */
  var fd = new FormData();
  fd.append('action', 'tls_get_lists');
  fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (!res.success || !res.data.lists) return;
      res.data.lists.forEach(function(list) {
        if (list.saved) {
          var btn = document.querySelector('[data-rp-save][data-list-id="' + list.id + '"]');
          if (btn) markSaved(btn);
        }
      });
    })
    .catch(function() {});

  function markSaved(btn) {
    btn.classList.add('is-saved');
    var label = btn.querySelector('.tls-rp-save-label');
    if (label) label.textContent = 'Added to Reading List';
  }
}
