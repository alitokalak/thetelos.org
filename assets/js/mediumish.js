jQuery(document).ready(function ($) {

  // Hide share when scrolled past .hideshare
  if ($(".hideshare").length) {
    var topOfOthDiv = $(".hideshare").offset().top;
    $(window).on("scroll", function () {
      if ($(window).scrollTop() > topOfOthDiv) {
        $(".share").hide();
      } else {
        $(".share").show();
      }
    });
  }

  // Back to top
  var offset = 1250;
  var duration = 800;

  $(window).on("scroll", function () {
    if ($(this).scrollTop() > offset) {
      $(".back-to-top").fadeIn(duration);
    } else {
      $(".back-to-top").fadeOut(duration);
    }
  });

  $(document).on("click", ".back-to-top", function (event) {
    event.preventDefault();
    $("html, body").animate({ scrollTop: 0 }, duration);
    return false;
  });

  // Alert bar on scroll
  $(document).on("scroll", function () {
    var y = $(this).scrollTop();
    if (y > 280) {
      $(".alertbar").fadeIn();
    } else {
      $(".alertbar").fadeOut();
    }
  });

  // Masonry
  if ($(".masonrygrid").length && $.fn.masonry) {
    var $grid = $(".masonrygrid").masonry({
      itemSelector: ".grid-item",
    });
    if ($.fn.imagesLoaded) {
      $grid.imagesLoaded().progress(function () {
        $grid.masonry();
      });
    }
  }

  // Minutes to read
  if ($(".post-read").length && $(".article-post").length) {
    var txt = $(".article-post")[0].textContent || "";
    var wordCount = txt.replace(/[^\w ]/g, "").split(/\s+/).filter(Boolean).length;
    var readingTimeInMinutes = Math.floor(wordCount / 250) + 1;
    $(".post-read").html(readingTimeInMinutes + " min");
  }

  // Smooth scroll to an anchor
  $(document).on("click", 'a.smoothscroll[href*="#"]:not([href="#"]):not([href="#0"])', function (event) {
    var samePath = location.pathname.replace(/^\//, "") === this.pathname.replace(/^\//, "");
    var sameHost = location.hostname === this.hostname;
    if (!samePath || !sameHost) return;

    var target = $(this.hash);
    target = target.length ? target : $("[name=" + this.hash.slice(1) + "]");
    if (!target.length) return;

    event.preventDefault();
    $("html, body").animate({ scrollTop: target.offset().top }, 1000, function () {
      var $target = $(target);
      $target.attr("tabindex", "-1").focus();
    });
  });

  // Hide Header on scroll down
  var didScroll = false;
  var lastScrollTop = 0;
  var delta = 5;
  var navbarHeight = $("header").outerHeight() || 0;

  $(window).on("scroll", function () {
    didScroll = true;
  });

  setInterval(function () {
    if (didScroll) {
      hasScrolled();
      didScroll = false;
    }
  }, 250);

  function hasScrolled() {
    var st = $(window).scrollTop();
    var brandrow = $(".brandrow").css("height") || "0px";

    if (Math.abs(lastScrollTop - st) <= delta) return;

    if (st > lastScrollTop && st > navbarHeight) {
      // Scroll Down
      $("header").removeClass("nav-down").addClass("nav-up");
      $(".nav-up").css("top", "-" + brandrow);
    } else {
      // Scroll Up
      if (st + $(window).height() < $(document).height()) {
        $("header").removeClass("nav-up").addClass("nav-down");
        $(".nav-up, .nav-down").css("top", "0px");
      }
    }

    lastScrollTop = st;
  }

  // Push content below header height (once on load)
  $(".site-content").css("margin-top", ($("header").outerHeight() || 0) + "px");

});