/**
 * Projects Block — Scroll, Drag & Auto-scroll
 *
 * Architecture:
 *  - One `ProjectsCarousel` instance per `.projects-section` on the page
 *  - Touch scroll is intentionally delegated to native browser behaviour
 *    via CSS `scroll-snap-type` + `overscroll-behavior-x` — no JS touch
 *    handlers needed and native momentum/snap is superior on mobile
 *  - Desktop drag uses synchronous coordinate capture before any rAF call
 *
 * @file inc/acf/blocks/projects/projects.js
 */

document.addEventListener("DOMContentLoaded", () => {
  // ─── Selectors ───────────────────────────────────────────────────────────────

  const SELECTORS = {
    section: '[class*="projects-section"]',
    wrapper: "[data-projects-container]",
    leftArrow: '[data-arrow="left"]',
    rightArrow: '[data-arrow="right"]',
  };

  // ─── Class ───────────────────────────────────────────────────────────────────

  class ProjectsCarousel {
    constructor(section) {
      // Guard against double-init if script is ever loaded more than once
      if (section.dataset.projectsInitialized) return;
      section.dataset.projectsInitialized = "true";

      this.section = section;
      this.container = section.querySelector(SELECTORS.wrapper);
      this.leftArrow = section.querySelector(SELECTORS.leftArrow);
      this.rightArrow = section.querySelector(SELECTORS.rightArrow);

      if (!this.container) return;

      // Drag state
      this.isDragging = false;
      this.dragStartX = 0;
      this.scrollLeftOnStart = 0;
      this.isTicking = false; // rAF gate for mousemove
      this.hasDragged = false; // Prevents "ghost click" navigation after drag

      // Arrow sync rAF handle — stored so it can be cancelled if needed
      this.syncRafId = null;

      this.init();
    }

    init() {
      this.initArrows();
      this.initDesktopDrag();
      this.initMobileHint();
    }

    // ── Arrows ──────────────────────────────────────────────────────────────────

    initArrows() {
      if (!this.leftArrow || !this.rightArrow) return;

      // ── Scroll amount ──
      // Dynamic: 80% of visible width. Falls back to 300px if container
      // is not yet laid out (e.g. inside a hidden tab) to avoid scrollBy(0).
      const getScrollAmount = () =>
        Math.max(this.container.clientWidth * 0.8, 300);

      this.leftArrow.addEventListener("click", () => {
        this.container.scrollBy({
          left: -getScrollAmount(),
          behavior: "smooth",
        });
      });

      this.rightArrow.addEventListener("click", () => {
        this.container.scrollBy({
          left: getScrollAmount(),
          behavior: "smooth",
        });
      });

      // ── Arrow disabled state ──
      // syncArrows() is called immediately (no rAF) for correct first render,
      // then throttled via rAF on every scroll event to avoid layout thrashing.
      const syncArrows = () => {
        const maxScroll =
          this.container.scrollWidth - this.container.clientWidth;
        const currentScroll = this.container.scrollLeft;
        const tolerance = 10;

        const isAtStart = currentScroll <= tolerance;
        const isAtEnd = currentScroll >= maxScroll - tolerance;

        // Class for CSS visual state
        this.leftArrow.classList.toggle("disabled", isAtStart);
        this.rightArrow.classList.toggle("disabled", isAtEnd);

        // Native disabled — gives keyboard/AT correct semantics for free
        this.leftArrow.disabled = isAtStart;
        this.rightArrow.disabled = isAtEnd;
      };

      this.container.addEventListener("scroll", () => {
        if (this.syncRafId) return; // already a frame pending
        this.syncRafId = requestAnimationFrame(() => {
          syncArrows();
          this.syncRafId = null;
        });
      });

      // FIX: Arrow State De-sync on Window Resize
      // Updates arrows if container width changes (e.g. orientation change)
      const resizeObserver = new ResizeObserver(() => {
        if (this.syncRafId) return;
        this.syncRafId = requestAnimationFrame(() => {
          syncArrows();
          this.syncRafId = null;
        });
      });
      resizeObserver.observe(this.container);

      // Run immediately so arrows are correct before any user interaction
      syncArrows();
    }

    // ── Desktop drag ────────────────────────────────────────────────────────────

    initDesktopDrag() {
      // FIX: Native Image Drag Interference
      // Prevents browser from showing ghost-image drag when clicking on thumbnails
      this.container.addEventListener("dragstart", (e) => e.preventDefault());

      const startDrag = (e) => {
        this.isDragging = true;
        this.isTicking = false; // reset gate from any previous session
        this.hasDragged = false; // reset click prevention
        this.dragStartX = e.pageX - this.container.offsetLeft;
        this.scrollLeftOnStart = this.container.scrollLeft;
        this.container.style.cursor = "grabbing";

        // Override CSS smooth scroll so drag tracks 1:1 with the pointer.
        // Restored on drag end by setting back to "" (lets CSS cascade win).
        this.container.style.scrollBehavior = "auto";
      };

      const stopDrag = () => {
        if (!this.isDragging) return;
        this.isDragging = false;
        this.isTicking = false; // clear any pending rAF gate
        this.container.style.cursor = "";
        this.container.style.scrollBehavior = ""; // restore CSS `scroll-behavior: smooth`
      };

      this.container.addEventListener("mousedown", startDrag);
      this.container.addEventListener("mouseleave", stopDrag);
      this.container.addEventListener("mouseup", stopDrag);

      this.container.addEventListener("mousemove", (e) => {
        if (!this.isDragging) return;
        e.preventDefault(); // guarded — only fires during active drag

        // Capture coordinates SYNCHRONOUSLY before the async rAF callback.
        // Accessing e.pageX inside rAF risks reading a recycled/pooled event.
        const x = e.pageX - this.container.offsetLeft;
        const walk = (x - this.dragStartX) * 1.5; // drag sensitivity

        // FIX: Detect if it's an actual drag vs an accidental pixel bump
        if (Math.abs(walk) > 5) {
          this.hasDragged = true;
        }

        if (!this.isTicking) {
          this.isTicking = true;
          requestAnimationFrame(() => {
            this.container.scrollLeft = this.scrollLeftOnStart - walk;
            this.isTicking = false;
          });
        }
      });

      // FIX: "Ghost Click" Navigational Bug
      // Intercepts the click bubble phase to stop anchor links from navigating if a drag happened
      this.container.addEventListener(
        "click",
        (e) => {
          if (this.hasDragged) {
            e.preventDefault();
            e.stopPropagation();
            this.hasDragged = false; // reset
          }
        },
        { capture: true },
      );
    }

    // ── Mobile scroll hint ───────────────────────────────────────────────────────

    initMobileHint() {
      // Honour user OS "reduce motion" preference — skip animation entirely
      if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

      // Use matchMedia (not window.innerWidth) — consistent with CSS breakpoints
      // and avoids scrollbar-width discrepancies across browsers
      const mql = window.matchMedia("(max-width: 420px)");

      const observer = new IntersectionObserver(
        (entries) => {
          const entry = entries[0];
          if (!entry.isIntersecting || entry.intersectionRatio < 0.4) return;

          observer.disconnect(); // one-shot — prevent re-triggering

          if (!mql.matches) return; // viewport may have changed since init
          if (this.container.scrollWidth <= this.container.clientWidth) return;

          // Delay to let any CSS entrance transitions on the section settle
          setTimeout(() => {
            this.container.scrollBy({ left: 120, behavior: "smooth" });

            // Use `scrollend` where available — more reliable than a fixed timeout.
            // Fixed timeout (600ms) is the fallback for browsers without scrollend.
            if ("onscrollend" in this.container) {
              this.container.addEventListener(
                "scrollend",
                () =>
                  this.container.scrollBy({ left: -120, behavior: "smooth" }),
                { once: true },
              );
            } else {
              setTimeout(() => {
                this.container.scrollBy({ left: -120, behavior: "smooth" });
              }, 600);
            }
          }, 300);
        },
        { threshold: 0.4 }, // single value — only 0.4 is ever acted on
      );

      observer.observe(this.section);
    }
  }

  // ─── Multi-instance bootstrap ────────────────────────────────────────────────

  document.querySelectorAll(SELECTORS.section).forEach((section) => {
    new ProjectsCarousel(section);
  });
});
