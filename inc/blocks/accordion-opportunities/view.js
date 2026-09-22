/**
 * starwishx/accordion-opportunities — Interactivity API store (front end).
 *
 * Compiled by gulp (nativeBlockScripts) into build/view.js and loaded through
 * block.json "viewScriptModule" only on pages where the block renders.
 *
 * State lives in the block's context (render.php): `activeIds` on the
 * <section>, `id` on each <li>. Context writes to an inherited key land on the
 * element that owns it, so every item reads and updates the same `activeIds`.
 *
 * Mouse / keyboard: opening is pure CSS (:hover, :focus-within). This store
 * covers the two touch behaviours and the number button:
 *   - actions.toggle   — the "01" button; exclusive open/close (aria-expanded).
 *   - callbacks.initList — "click for touch" off: on coarse pointers, items
 *     open as they cross a line 25% above the viewport bottom and stay open
 *     once passed (IntersectionObserver), like the ACF block did.
 *
 * File: inc/blocks/accordion-opportunities/view.js
 */

import { store, getContext, getElement } from "@wordpress/interactivity";

const isFinePointer = () => window.matchMedia("(pointer: fine)").matches;

store("starwishx/accordion-opportunities", {
  state: {
    get isActive() {
      const ctx = getContext();
      return ctx.activeIds.includes(ctx.id);
    },
  },

  actions: {
    toggle() {
      const ctx = getContext();
      ctx.activeIds = ctx.activeIds.includes(ctx.id) ? [] : [ctx.id];
    },
  },

  callbacks: {
    initList() {
      const ctx = getContext();
      const { ref } = getElement();

      if (
        ctx.clickForTouch ||
        isFinePointer() ||
        !("IntersectionObserver" in window)
      ) {
        return undefined;
      }

      const observer = new IntersectionObserver(
        (entries) => {
          for (const entry of entries) {
            const id = entry.target.dataset.id;
            if (!id) continue;

            if (entry.isIntersecting) {
              if (!ctx.activeIds.includes(id)) {
                ctx.activeIds = [...ctx.activeIds, id];
              }
            } else if (entry.boundingClientRect.top >= 0) {
              // Still below the line: collapse. Above it: already passed, stays open.
              ctx.activeIds = ctx.activeIds.filter((activeId) => activeId !== id);
            }
          }
        },
        { rootMargin: "0px 0px -25% 0px" },
      );

      ref.querySelectorAll(":scope > li[data-id]").forEach((item) => {
        observer.observe(item);
      });

      return () => observer.disconnect();
    },
  },
});
