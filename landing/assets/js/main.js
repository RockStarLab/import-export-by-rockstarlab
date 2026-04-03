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

    const close = () => {
      nav.classList.remove("is-open");
      navToggle.setAttribute("aria-expanded", "false");
    };

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
      nodes.forEach((n) => n.classList.add("is-visible"));
      return;
    }

    const io = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (!entry.isIntersecting) continue;
          entry.target.classList.add("is-visible");
          io.unobserve(entry.target);
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
      closeBtn?.focus();
      document.body.style.overflow = "hidden";
    };

    const close = () => {
      root.hidden = true;
      if (img) img.removeAttribute("src");
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

  setYear();
  setupNav();
  setupReveal();
  setupTabs();
  setupLightbox();
  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });
})();

