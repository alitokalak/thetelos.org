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
    initReadingStatus();
    initBackToTop();
    initStickyNav();
    setContentOffset();
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
     MOBILE NAV toggle
  ──────────────────────────── */
  function initNav() {
    var toggle = document.querySelector('.tls-mobile-toggle');
    var drawer = document.querySelector('.tls-mobile-nav');
    if (!toggle || !drawer) return;
    toggle.addEventListener('click', function () {
      drawer.classList.toggle('open');
      toggle.setAttribute('aria-expanded', drawer.classList.contains('open'));
    });
    document.addEventListener('click', function (e) {
      if (!toggle.contains(e.target) && !drawer.contains(e.target)) {
        drawer.classList.remove('open');
      }
    });
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
  function initRating() {
    var container = document.querySelector('.tls-stars[data-post-id]');
    if (!container) return;

    var postId   = container.dataset.postId;
    var stars    = container.querySelectorAll('.tls-star');
    var msgEl    = document.querySelector('.tls-rating-msg');
    var countEl  = document.querySelector('.tls-rating-count');
    var storageKey = 'tls_rated_' + postId;
    var userRating = parseInt(localStorage.getItem(storageKey) || '0', 10);

    /* Render current filled state */
    renderStars(userRating, stars);

    /* Hover */
    stars.forEach(function (star, i) {
      star.addEventListener('mouseenter', function () {
        highlightStars(i + 1, stars);
      });
    });
    container.addEventListener('mouseleave', function () {
      renderStars(userRating, stars);
    });

    /* Click to rate */
    stars.forEach(function (star, i) {
      star.addEventListener('click', function () {
        var newRating = i + 1;
        if (userRating === newRating) {
          /* Toggle off — cancel rating */
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
        if (msgEl) msgEl.textContent = 'Thanks for rating!';
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
  ──────────────────────────── */
  function initReadingStatus() {
    var btns = document.querySelectorAll('.tls-status-btn[data-status]');
    if (!btns.length) return;
    var postId = document.querySelector('[data-tls-post-id]');
    if (!postId) return;
    var pid = postId.dataset.tlsPostId;
    var key = 'tls_status_' + pid;
    var current = localStorage.getItem(key);

    btns.forEach(function (btn) {
      if (btn.dataset.status === current) btn.classList.add('active');
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var status = btn.dataset.status;
        if (current === status) {
          /* Toggle off */
          current = null;
          localStorage.removeItem(key);
          btns.forEach(function (b) { b.classList.remove('active'); });
        } else {
          current = status;
          localStorage.setItem(key, status);
          btns.forEach(function (b) {
            b.classList.toggle('active', b.dataset.status === status);
          });
        }
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

})();
