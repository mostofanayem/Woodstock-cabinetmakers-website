/* Woodstock Cabinet Makers — site behaviour (no build step, vanilla JS) */
(function () {
  "use strict";

  var root = document.documentElement;
  root.classList.add("js");

  /* ---- Mobile nav ---- */
  var toggle = document.querySelector(".nav-toggle");
  var navLinks = document.querySelector(".site-nav__links");
  if (toggle && navLinks) {
    toggle.addEventListener("click", function () {
      var open = navLinks.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      document.body.style.overflow = open ? "hidden" : "";
    });

    document.querySelectorAll(".has-dropdown > .nav-link").forEach(function (link) {
      link.addEventListener("click", function (e) {
        if (window.innerWidth > 860) return;
        var parent = link.closest(".has-dropdown");
        var alreadyOpen = parent.classList.contains("is-open");
        if (!alreadyOpen) {
          e.preventDefault();
          document.querySelectorAll(".has-dropdown.is-open").forEach(function (el) { el.classList.remove("is-open"); });
          parent.classList.add("is-open");
        }
      });
    });
  }

  /* ---- Chat launcher ---- */
  var launcher = document.querySelector(".chat-launcher");
  var chatPanel = document.querySelector(".chat-panel");
  if (launcher && chatPanel) {
    launcher.addEventListener("click", function () {
      var open = chatPanel.classList.toggle("is-open");
      launcher.setAttribute("aria-expanded", open ? "true" : "false");
      if (open) {
        var firstField = chatPanel.querySelector("input, textarea");
        if (firstField) firstField.focus();
      }
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && chatPanel.classList.contains("is-open")) {
        chatPanel.classList.remove("is-open");
        launcher.setAttribute("aria-expanded", "false");
        launcher.focus();
      }
    });
    document.addEventListener("click", function (e) {
      if (!chatPanel.classList.contains("is-open")) return;
      if (chatPanel.contains(e.target) || launcher.contains(e.target)) return;
      chatPanel.classList.remove("is-open");
      launcher.setAttribute("aria-expanded", "false");
    });
  }

  /* ---- Forms: progressive-enhancement AJAX submit ---- */
  function wireForm(form) {
    if (!form) return;
    var status = form.querySelector(".form-status");
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (status) {
        status.className = "form-status is-visible";
        status.textContent = "Sending…";
      }
      var submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      fetch(form.getAttribute("action"), {
        method: "POST",
        headers: { Accept: "application/json" },
        body: new FormData(form),
      })
        .then(function (res) { return res.json().catch(function () { return { ok: res.ok }; }); })
        .then(function (data) {
          if (data && data.ok) {
            form.reset();
            if (status) {
              status.className = "form-status is-visible form-status--ok";
              status.textContent = "Thanks — your message is on its way to Jack. We'll be in touch soon.";
            }
          } else {
            throw new Error((data && data.error) || "Something went wrong");
          }
        })
        .catch(function () {
          if (status) {
            status.className = "form-status is-visible form-status--error";
            status.textContent = "Sorry, that didn't send. Please call 07 3277 9797 or email jack@woodcab.com.au directly.";
          }
        })
        .finally(function () {
          if (submitBtn) submitBtn.disabled = false;
        });
    });
  }
  document.querySelectorAll("form[data-ajax-form]").forEach(wireForm);

  /* ---- Horizontal track scroll buttons ---- */
  document.querySelectorAll("[data-track]").forEach(function (wrapper) {
    var track = wrapper.querySelector(".review-track, .project-track");
    var prev = wrapper.querySelector('[data-track-prev]');
    var next = wrapper.querySelector('[data-track-next]');
    if (!track) return;
    function amount() { return Math.min(track.clientWidth * 0.85, 420); }
    if (prev) prev.addEventListener("click", function () { track.scrollBy({ left: -amount(), behavior: "smooth" }); });
    if (next) next.addEventListener("click", function () { track.scrollBy({ left: amount(), behavior: "smooth" }); });
  });

  /* ---- Live Google reviews ----
     Fetches /api/google-reviews-proxy.php (server-side Places API proxy).
     On any failure, the curated fallback markup already in the page (server-rendered) is left untouched. */
  var reviewsRoot = document.querySelector("[data-reviews-root]");
  if (reviewsRoot) {
    var endpoint = reviewsRoot.getAttribute("data-reviews-endpoint") || "/api/google-reviews-proxy.php";
    fetch(endpoint, { headers: { Accept: "application/json" } })
      .then(function (res) { if (!res.ok) throw new Error("bad status"); return res.json(); })
      .then(function (data) {
        if (!data || !data.ok || !Array.isArray(data.reviews) || !data.reviews.length) throw new Error("no reviews");
        renderReviews(data);
      })
      .catch(function () {
        var statusEl = reviewsRoot.querySelector(".reviews__status");
        if (statusEl) statusEl.textContent = "Showing recent reviews — see every review on Google below.";
      });

    function starString(rating) {
      var rounded = Math.round(rating || 5);
      return "★★★★★".slice(0, rounded) + "☆☆☆☆☆".slice(0, 5 - rounded);
    }

    function renderReviews(data) {
      var track = reviewsRoot.querySelector(".review-track");
      var countEl = reviewsRoot.querySelector(".reviews__count");
      var starsEl = reviewsRoot.querySelector(".reviews__stars");
      var statusEl = reviewsRoot.querySelector(".reviews__status");

      if (starsEl && data.rating) starsEl.textContent = starString(data.rating);
      if (countEl && data.total) countEl.textContent = "Based on " + data.total + " reviews";
      if (statusEl) statusEl.textContent = "Live from Google";

      if (track) {
        track.innerHTML = "";
        data.reviews.slice(0, 8).forEach(function (review) {
          var author = review.author || "Google user";
          var card = document.createElement("article");
          card.className = "review-card";
          var stars = document.createElement("div");
          stars.className = "review-card__stars";
          stars.textContent = starString(review.rating);
          var text = document.createElement("p");
          text.textContent = review.text || "";
          var who = document.createElement("div");
          who.className = "review-card__who";
          var avatar = document.createElement("span");
          avatar.className = "review-card__avatar";
          avatar.setAttribute("aria-hidden", "true");
          avatar.textContent = author.trim().charAt(0).toUpperCase() || "G";
          var whoText = document.createElement("span");
          whoText.className = "review-card__who-text";
          var strong = document.createElement("strong");
          strong.textContent = author;
          whoText.appendChild(strong);
          whoText.appendChild(document.createTextNode(review.relativeTime || ""));
          who.appendChild(avatar);
          who.appendChild(whoText);
          card.appendChild(stars);
          card.appendChild(text);
          card.appendChild(who);
          track.appendChild(card);
        });
      }
    }
  }

  /* ---- Scroll reveal (progressive enhancement — no JS/reduced-motion = always visible) ---- */
  var prefersReducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  try {
  if (!prefersReducedMotion && "IntersectionObserver" in window) {
    var revealTargets = document.querySelectorAll(
      ".section-head, .project-card, .service-card, .review-card, .fitted-card, .two-col > div, .cta-band h2, .cta-band .lede, .cta-band__actions, .project-meta"
    );
    var revealObserver = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("reveal-visible");
            revealObserver.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15, rootMargin: "0px 0px -40px 0px" }
    );
    revealTargets.forEach(function (el, i) {
      el.classList.add("reveal-init");
      var group = el.parentElement;
      var siblingsBefore = group ? Array.prototype.indexOf.call(group.children, el) : i;
      el.style.transitionDelay = Math.min(siblingsBefore * 70, 280) + "ms";
      revealObserver.observe(el);
    });

    // Safety net: force everything visible after a few seconds regardless, so a slow
    // observer, a missed intersection, or a script error downstream can never leave
    // real content permanently invisible.
    window.setTimeout(function () {
      document.querySelectorAll(".reveal-init:not(.reveal-visible)").forEach(function (el) {
        el.classList.add("reveal-visible");
      });
    }, 2500);
  }
  } catch (e) {
    // Never let a reveal-animation failure leave content invisible.
    document.querySelectorAll(".reveal-init").forEach(function (el) { el.classList.add("reveal-visible"); });
  }

  /* ---- Nav shadow on scroll ---- */
  var siteNav = document.querySelector(".site-nav");
  if (siteNav) {
    var onScroll = function () {
      siteNav.classList.toggle("is-scrolled", window.scrollY > 8);
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }

  /* ---- Gallery lightbox (works for any .gallery-grid on the page) ---- */
  var galleries = document.querySelectorAll(".gallery-grid");
  if (galleries.length) {
    var lightbox = document.createElement("div");
    lightbox.className = "lightbox";
    lightbox.setAttribute("role", "dialog");
    lightbox.setAttribute("aria-modal", "true");
    lightbox.innerHTML =
      '<button type="button" class="lightbox__prev" aria-label="Previous photo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 5l-7 7 7 7"/></svg></button>' +
      '<img class="lightbox__img" src="" alt="">' +
      '<button type="button" class="lightbox__next" aria-label="Next photo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 5l7 7-7 7"/></svg></button>' +
      '<button type="button" class="lightbox__close" aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 5l14 14M19 5L5 19"/></svg></button>' +
      '<div class="lightbox__counter"></div>';
    document.body.appendChild(lightbox);

    var lbImg = lightbox.querySelector(".lightbox__img");
    var lbCounter = lightbox.querySelector(".lightbox__counter");
    var lbPrev = lightbox.querySelector(".lightbox__prev");
    var lbNext = lightbox.querySelector(".lightbox__next");
    var lbClose = lightbox.querySelector(".lightbox__close");
    var currentItems = [];
    var currentIndex = 0;

    function openLightbox(items, index) {
      currentItems = items;
      currentIndex = index;
      renderLightbox();
      lightbox.classList.add("is-open");
      document.body.style.overflow = "hidden";
    }
    function renderLightbox() {
      var item = currentItems[currentIndex];
      lbImg.src = item.getAttribute("data-full");
      lbImg.alt = item.querySelector("img") ? item.querySelector("img").alt : "";
      lbCounter.textContent = currentItems.length > 1 ? (currentIndex + 1) + " / " + currentItems.length : "";
      lbPrev.style.display = currentItems.length > 1 ? "flex" : "none";
      lbNext.style.display = currentItems.length > 1 ? "flex" : "none";
    }
    function closeLightbox() {
      lightbox.classList.remove("is-open");
      document.body.style.overflow = "";
    }
    function showNext(delta) {
      currentIndex = (currentIndex + delta + currentItems.length) % currentItems.length;
      renderLightbox();
    }

    galleries.forEach(function (grid) {
      var items = Array.prototype.slice.call(grid.querySelectorAll(".gallery-grid__item"));
      items.forEach(function (item, i) {
        item.addEventListener("click", function () {
          openLightbox(items, i);
        });
      });
    });

    lbClose.addEventListener("click", closeLightbox);
    lbPrev.addEventListener("click", function () { showNext(-1); });
    lbNext.addEventListener("click", function () { showNext(1); });
    lightbox.addEventListener("click", function (e) {
      if (e.target === lightbox) closeLightbox();
    });
    document.addEventListener("keydown", function (e) {
      if (!lightbox.classList.contains("is-open")) return;
      if (e.key === "Escape") closeLightbox();
      if (e.key === "ArrowLeft") showNext(-1);
      if (e.key === "ArrowRight") showNext(1);
    });
  }

  /* ---- Mark the active nav link ---- */
  var here = window.location.pathname.replace(/index\.html$/, "");
  document.querySelectorAll(".nav-link[href]").forEach(function (link) {
    var href = link.getAttribute("href").replace(/index\.html$/, "");
    if (href && href !== "/" && here.indexOf(href) === 0) {
      link.setAttribute("aria-current", "page");
    }
  });
})();
