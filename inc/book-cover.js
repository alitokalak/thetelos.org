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
    t.style.removeProperty('font-size');
    t.style.removeProperty('line-height');

    var size = parseFloat(getComputedStyle(t).fontSize) || 12;
    var min = 7;
    var guard = 0;

    // .cover-title flex:1 + overflow:hidden olduğundan metin KENDİ kutusu içinde
    // kesilir; kapak (cover) taşmaz. Bu yüzden başlığın kendi yüksekliğine bakarız:
    // metnin gerçek boyu (scrollHeight) görünür kutuyu (clientHeight) aşıyorsa küçült.
    // ÖNEMLİ: bazı sayfalarda (ör. tekil/post) CSS `.cover-title{font-size:...!important}`
    // ile geliyor; inline stili de `!important` ile yazmazsak küçültme ezilir.
    while (t.scrollHeight > t.clientHeight + 1 && size > min && guard < 120) {
      size -= 0.5;
      guard++;
      t.style.setProperty('font-size', size + 'px', 'important');
      t.style.setProperty('line-height', '1.25', 'important');
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
