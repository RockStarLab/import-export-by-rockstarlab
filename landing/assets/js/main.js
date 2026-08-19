(() => {
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const topbar = document.querySelector("[data-topbar]");
  const nav = document.querySelector("[data-nav]");
  const navToggle = document.querySelector("[data-nav-toggle]");

  const setYear = () => {
    const year = new Date().getFullYear();
    $$("[data-year]").forEach((n) => (n.textContent = String(year)));
  };

  const onScroll = () => {
    if (!topbar) return;
    topbar.classList.toggle("is-scrolled", window.scrollY > 8);
  };

  const setupNav = () => {
    if (!nav || !navToggle) return;

    const MOBILE_BP = 900;

    const close = () => {
      nav.classList.remove("is-open");
      navToggle.setAttribute("aria-expanded", "false");
    };

    // Reset nav state when crossing the mobile breakpoint
    const onResize = () => {
      if (window.innerWidth > MOBILE_BP) {
        close();
      }
    };

    if ("ResizeObserver" in window) {
      new ResizeObserver(onResize).observe(document.documentElement);
    } else {
      window.addEventListener("resize", onResize);
    }

    navToggle.addEventListener("click", () => {
      const next = !nav.classList.contains("is-open");
      nav.classList.toggle("is-open", next);
      navToggle.setAttribute("aria-expanded", next ? "true" : "false");
    });

    nav.addEventListener("click", (e) => {
      const a = e.target instanceof Element ? e.target.closest("a") : null;
      if (!a) return;
      close();
    });

    window.addEventListener("keydown", (e) => {
      if (e.key === "Escape") close();
    });
  };

  const setupReveal = () => {
    const nodes = $$(".reveal");
    if (!nodes.length) return;

    if (!("IntersectionObserver" in window)) {
      nodes.forEach((n) => { n.classList.add("is-visible"); n.classList.add("is-done"); });
      return;
    }

    const io = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (!entry.isIntersecting) continue;
          const el = entry.target;
          el.classList.add("is-visible");
          el.addEventListener("animationend", () => {
            el.classList.add("is-done");
          }, { once: true });
          io.unobserve(el);
        }
      },
      { rootMargin: "0px 0px -10% 0px", threshold: 0.12 },
    );

    nodes.forEach((n) => io.observe(n));
  };

  const setupTabs = () => {
    const tabsRoot = document.querySelector("[data-tabs]");
    if (!tabsRoot) return;

    const tabs = $$("[data-tab]", tabsRoot);
    const panels = $$("[data-panel]", tabsRoot);
    const show = (name) => {
      for (const t of tabs) {
        const selected = t.getAttribute("data-tab") === name;
        t.setAttribute("aria-selected", selected ? "true" : "false");
      }
      for (const p of panels) {
        const isActive = p.getAttribute("data-panel") === name;
        p.hidden = !isActive;
      }
    };

    tabs.forEach((t) => {
      t.addEventListener("click", () => show(t.getAttribute("data-tab")));
    });
  };

  const setupLightbox = () => {
    const root = document.querySelector("[data-lightbox-root]");
    if (!root) return;

    const img = document.querySelector("[data-lightbox-img]");
    const cap = document.querySelector("[data-lightbox-cap]");
    const closeBtn = document.querySelector("[data-lightbox-close]");
    const triggers = $$("[data-lightbox]");

    const open = (src, alt) => {
      if (img) img.setAttribute("src", src);
      if (img) img.setAttribute("alt", alt || "");
      if (cap) cap.textContent = alt || "";
      root.hidden = false;
      // trigger reflow so transition plays from initial state
      root.getBoundingClientRect();
      root.classList.add("is-open");
      closeBtn?.focus();
      document.body.style.overflow = "hidden";
    };

    const close = () => {
      root.classList.remove("is-open");
      root.addEventListener("transitionend", () => {
        root.hidden = true;
        if (img) img.removeAttribute("src");
      }, { once: true });
      document.body.style.overflow = "";
    };

    triggers.forEach((btn) => {
      btn.addEventListener("click", () => {
        const src = btn.getAttribute("data-src");
        const alt = btn.getAttribute("data-alt") || "";
        if (!src) return;
        open(src, alt);
      });
    });

    closeBtn?.addEventListener("click", close);
    root.addEventListener("click", (e) => {
      if (e.target === root) close();
    });
    window.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && !root.hidden) close();
    });
  };

  const setupFlipper = () => {
    const container = document.querySelector("[data-flipper]");
    if (!container) return;

    const words = Array.from(container.querySelectorAll(".flipper-word"));
    if (!words.length) return;

    // Measure height of a single word at current viewport width
    const measureWord = (w) => {
      const prev = w.style.cssText;
      w.style.cssText = "position:relative!important;opacity:1!important;transform:none!important;width:100%!important;transition:none!important;animation:none!important;";
      container.getBoundingClientRect(); // force reflow
      const h = w.getBoundingClientRect().height;
      w.style.cssText = prev;
      return h;
    };

    // Set container height to match a specific word's height
    const setHeight = (idx) => {
      const h = measureWord(words[idx]);
      if (h > 0) container.style.height = h + "px";
    };

    // On resize: re-measure current word and update height
    let rafId = null;
    const onResize = () => {
      if (rafId) cancelAnimationFrame(rafId);
      rafId = requestAnimationFrame(() => {
        rafId = null;
        setHeight(current);
      });
    };
    window.addEventListener("resize", onResize, { passive: true });

    const HOLD      = 2600;
    const ENTER_DUR = 500;
    const EXIT_DUR  = 380;

    let current = 0;
    let paused = false;
    let holdTimer = null;
    let enterTimer = null;
    let exitTimer = null;

    const clearClasses = (w) => w.classList.remove("is-enter", "is-active", "is-exit");

    const show = (idx) => {
      // Update container height to match this word before showing it
      setHeight(idx);
      const w = words[idx];
      clearClasses(w);
      w.classList.add("is-enter");
      enterTimer = setTimeout(() => {
        enterTimer = null;
        if (w.classList.contains("is-enter")) {
          w.classList.remove("is-enter");
          w.classList.add("is-active");
        }
      }, ENTER_DUR);
    };

    const hide = (idx, cb) => {
      const w = words[idx];
      clearClasses(w);
      w.classList.add("is-exit");
      exitTimer = setTimeout(() => {
        exitTimer = null;
        clearClasses(w);
        cb && cb();
      }, EXIT_DUR);
    };

    const advance = () => {
      if (paused) return;
      hide(current, () => {
        if (paused) {
          // paused mid-transition — snap current word back to active state
          clearClasses(words[current]);
          words[current].classList.add("is-active");
          return;
        }
        current = (current + 1) % words.length;
        show(current);
        holdTimer = setTimeout(advance, HOLD);
      });
    };

    const pause = () => {
      if (paused) return;
      paused = true;
      // Cancel any pending timers so no transition fires while hidden
      if (holdTimer)  { clearTimeout(holdTimer);  holdTimer  = null; }
      if (enterTimer) { clearTimeout(enterTimer); enterTimer = null; }
      if (exitTimer)  { clearTimeout(exitTimer);  exitTimer  = null; }
      // Snap current word to a clean visible state — no mid-animation freeze
      words.forEach(clearClasses);
      words[current].classList.add("is-active");
      setHeight(current);
    };

    const resume = () => {
      if (!paused) return;
      paused = false;
      // Start the cycle from the current word
      holdTimer = setTimeout(advance, HOLD);
    };

    // Watch hero section visibility
    const hero = container.closest(".hero") || container;
    if ("IntersectionObserver" in window) {
      const io = new IntersectionObserver(
        (entries) => {
          for (const entry of entries) {
            if (entry.isIntersecting) {
              resume();
            } else {
              pause();
            }
          }
        },
        { threshold: 0.1 }
      );
      io.observe(hero);
    }

    show(current);
    holdTimer = setTimeout(advance, HOLD);
  };

  const setupNavHighlight = () => {
    const links = Array.from(document.querySelectorAll(".nav-link[href^='#']"));
    if (!links.length) return;

    const sectionIds = links.map((a) => a.getAttribute("href").slice(1));
    const sections = sectionIds
      .map((id) => document.getElementById(id))
      .filter(Boolean);

    if (!sections.length) return;

    const setActive = (id) => {
      links.forEach((a) => {
        const isActive = a.getAttribute("href") === "#" + id;
        a.classList.toggle("is-active", isActive);
      });
    };

    const update = () => {
      // Trigger line = 40% down the viewport — section is "active" when its
      // top has crossed this line (i.e. rect.top <= triggerY) and no later
      // section has also crossed it yet.
      const triggerY = window.innerHeight * 0.4;
      let best = null;

      for (const s of sections) {
        const rect = s.getBoundingClientRect();
        if (rect.top <= triggerY) {
          best = s; // keep overwriting — last one wins = lowest section above trigger
        }
      }

      // Nothing past trigger yet — we're in the hero, clear all highlights
      if (!best) {
        links.forEach((a) => a.classList.remove("is-active"));
        return;
      }
      setActive(best.id);
    };

    window.addEventListener("scroll", update, { passive: true });
    window.addEventListener("resize", update, { passive: true });
    update();
  };

  setYear();
  setupNav();
  setupNavHighlight();
  setupReveal();
  setupTabs();
  setupLightbox();
  setupFlipper();
  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });
})();

