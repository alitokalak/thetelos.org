/**
 * thetelos book cover — başlık auto-fit
 *
 * Otomatik üretilen kitap kapaklarında (.thetelos-book-cover) başlık
 * kutuya sığmıyorsa font boyutunu, taşma bitene kadar kademeli küçültür.
 * Böylece uzun kitap adları kesilmeden kapağa sığar. Yalnızca küçültür,
 * asla büyütmez; her çalıştırmada CSS'teki başlangıç boyutundan başlar.
 */
(function () {
  function fit(cover) {
    var t = cover.querySelector('.cover-title');
    if (!t) return;

    // Başlangıç boyutuna dön (yeniden çalıştırmada birikmesin)
    t.style.fontSize = '';
    t.style.lineHeight = '';

    var size = parseFloat(getComputedStyle(t).fontSize) || 14;
    var min = 7;
    var guard = 0;

    // Kapağın içeriği kutusunu aşıyorsa (overflow:hidden ile kesiliyorsa) küçült
    while (cover.scrollHeight > cover.clientHeight + 1 && size > min && guard < 80) {
      size -= 0.5;
      guard++;
      t.style.fontSize = size + 'px';
      t.style.lineHeight = '1.25';
    }
  }

  function run() {
    var covers = document.querySelectorAll('.thetelos-book-cover');
    for (var i = 0; i < covers.length; i++) fit(covers[i]);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
  // Web fontları geç yüklenince ölçüler değişebilir — tekrar sığdır
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(run);
  window.addEventListener('load', run);
})();
