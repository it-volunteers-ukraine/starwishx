/**
 * Listing Store — Main Entry
 *
 * Orchestrates Faceted Search using the Interactivity API.
 * File: inc/listing/Assets/listing-store.js
 */

import {
  store,
  getContext,
  getElement,
  withSyncEvent,
} from "@wordpress/interactivity";
import { extendState } from "./utils.js"; // or ../../shared/Assets/shared-utils.js
import { filterActions } from "./filters/actions.js";
import { gridActions } from "./grid/actions.js";
import { gridGetters } from "./grid/getters.js";
import { filterGetters } from "./filters/getters.js";
import { deepClone, highlightTerms } from "./utils.js";
import "../../shared/Assets/popup-store.js";

/**
 * Base State Definition
 *
 * CRITICAL ARCHITECTURE NOTE:
 * Do NOT define properties here that are hydrated from the server via
 * wp_interactivity_state() in PHP (results, facets, totalFound, totalPages, query).
 * Defining them here would cause the Client Store to overwrite the Server Data
 * with default values (e.g., empty arrays), breaking SSR hydration.
 *
 * Only define client-only state (UI flags, spinners) and computed getters.
 */
const listingState = {
  // Client-only UI state
  isLoading: false,
  isSidebarOpen: false,
  error: "", // message of the last failed request (grid/actions.js)
  ui: {
    isLoadingLocation: false, // Loading state for location autocomplete
  },
};

/**
 * Mixin Getters
 * Preserves getter descriptors so 'this' remains reactive. The getters the
 * markup uses have PHP twins in inc/listing/Core/DerivedState.php, which
 * render the page on the server.
 */
extendState(listingState, gridGetters, filterGetters);

/**
 * Controls Tab can land on inside the filter drawer (actions.trapSidebarFocus).
 */
const SIDEBAR_FOCUSABLE = [
  "a[href]",
  "button:not([disabled])",
  "input:not([disabled])",
  "select:not([disabled])",
  "textarea:not([disabled])",
  "summary",
  '[tabindex]:not([tabindex="-1"])',
].join(",");

/** The drawer state callbacks.syncSidebarFocus last acted on. */
let sidebarWasOpen = false;

/**
 * STORE DEFINITION
 * Standardized registration pattern.
 */
store("listing", {
  state: listingState,
  actions: {
    ...gridActions,
    filters: filterActions,

    toggleSidebar() {
      const { state } = store("listing");
      state.isSidebarOpen = !state.isSidebarOpen;
      document.body.classList.toggle("sidebar-open", state.isSidebarOpen);
    },

    closeSidebar() {
      const { state } = store("listing");
      state.isSidebarOpen = false;
      document.body.classList.remove("sidebar-open");
    },

    /**
     * Keeps Tab inside the open drawer: the page behind it sits under the
     * backdrop, so focus that got there would be invisible. Bound with
     * data-wp-on--keydown on the sidebar; withSyncEvent because it has to
     * preventDefault() the keystroke it redirects.
     */
    trapSidebarFocus: withSyncEvent((event) => {
      const { state } = store("listing");
      if (event.key !== "Tab" || !state.isSidebarOpen) return;

      const { ref } = getElement();
      const focusable = [...ref.querySelectorAll(SIDEBAR_FOCUSABLE)].filter(
        (el) => el.checkVisibility(),
      );
      if (focusable.length === 0) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      const current = document.activeElement;
      const outside = !ref.contains(current);

      if (event.shiftKey && (outside || current === first)) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && (outside || current === last)) {
        event.preventDefault();
        first.focus();
      }
    }),

    init() {
      const { state, actions } = store("listing");

      // SSR hydration is authoritative for the initial URL state.
      // Both clean URLs and filtered URLs (?category=..., /slug/)
      // are correctly hydrated by StateAggregator.

      // Give the entry the page loaded into the same query snapshot that
      // syncStateToUrl() stores, so Back to it restores filters the same
      // way (filters.restoreFromHistory).
      window.history.replaceState({ query: deepClone(state.query) }, "");

      // Close offcanvas sidebar on Escape key
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && state.isSidebarOpen) {
          actions.closeSidebar();
        }
      });
    },

    /**
     * THE TRIGGER:
     * When user clicks the checkbox OR the label text.
     */
    async toggleFavorite(event) {
      // 1. Prevent Browser Defaults (optional based on your HTML)
      if (event && event.type === "click") {
        // event.preventDefault(); // Uncomment if using <a> tag
      }
      const ctx = getContext();
      const item = ctx?.item;
      if (item && item.id) {
        // Guest guard: show auth popup instead of toggling
        if (!store("listing").state.isUserLoggedIn) {
          store("popup").actions.open();
          return;
        }

        // 2. OPTIMISTIC UPDATE (Local Context)
        // We MUST flip this boolean so the Fallback logic in the getter
        // stays in sync with the user's intent.
        item.isFavorite = !item.isFavorite;

        // 3. Call Global Store
        // This updates the Global Array, which triggers the getter again.
        await store("favorites").actions.toggle(item.id);
      }
    },
  },
  callbacks: {
    /**
     * data-wp-watch on the sidebar. Focus follows the drawer: into it when it
     * opens (the close button, its first control) and back to the Filters
     * button when it closes, unless focus has already moved elsewhere. Watch
     * re-runs come after the next frame, so the open drawer is visible, and
     * therefore focusable, by the time this runs. The first run, at
     * hydration, sees no change and leaves focus alone.
     */
    syncSidebarFocus() {
      const { state } = store("listing");
      const { ref } = getElement();
      const isOpen = state.isSidebarOpen;
      if (isOpen === sidebarWasOpen) return;
      sidebarWasOpen = isOpen;

      if (isOpen) {
        ref.querySelector(".listing-sidebar__close")?.focus();
        return;
      }

      const current = document.activeElement;
      if (!current || current === document.body || ref.contains(current)) {
        document.querySelector(`[aria-controls="${ref.id}"]`)?.focus();
      }
    },

    /**
     * PROXY ACTION
     * When user clicks heart in grid, we call the Global Favorites store.
     */
    async toggleFavorite(event) {
      // Prevent default link clicks or form submissions
      if (event) event.preventDefault();

      const ctx = getContext();
      const item = ctx?.item;
      if (!item) return;

      // Delegate to the independent favorites store
      await store("favorites").actions.toggle({
        preventDefault: () => {}, // mock event
        target: { dataset: { id: item.id } }, // mock ref/target
      });

      // Update the local item boolean for immediate UI reactivity
      // (The grid getter below handles the "active" class, but this helps context)
      item.isFavorite = !item.isFavorite;
    },

    /**
     * Sets the visual "Dash" on the parent checkbox
     */
    setIndeterminate: () => {
      const { state } = store("listing");
      const { ref } = getElement();
      const context = getContext();
      const item = context?.item;
      if (
        !item ||
        !Array.isArray(item.children) ||
        item.children.length === 0
      ) {
        ref.indeterminate = false;
        return;
      }

      const selectedIds = state.query.category;

      // Count how many children are currently selected
      const childrenIds = item.children.map((c) => c.id);
      const selectedChildrenCount = childrenIds.filter((id) =>
        selectedIds.includes(id),
      ).length;

      // Logic: Indeterminate if SOME children are checked, but NOT ALL
      const isPartiallyChecked =
        selectedChildrenCount > 0 && selectedChildrenCount < childrenIds.length;

      ref.indeterminate = isPartiallyChecked;
    },

    // highlight card content
    renderCardHighlight() {
      // normalize getElement() output to real DOM node
      const elWrapper = getElement();
      const root =
        (elWrapper && (elWrapper.ref || elWrapper.element)) ||
        elWrapper ||
        null;
      if (!root || typeof root.querySelector !== "function") return;

      // get card-local context (context.item)
      const ctx = getContext();
      const item = ctx?.item;
      // also read store state to register dependency on query terms
      const { state } = store("listing");
      const terms = [state.query.s, state.query.location].filter(Boolean);

      // find our visible target nodes
      const titleOut = root.querySelector(".title-highlight");
      const excerptOut = root.querySelector(".excerpt-highlight");

      // Decide raw text source: prefer context.item (reactive), fallback to existing textContent
      const rawTitle =
        (item && (item.title ?? item.name ?? "")) ||
        (titleOut?.textContent ?? "").trim();
      const rawExcerpt =
        (item && (item.excerpt ?? "")) ||
        (excerptOut?.textContent ?? "").trim();

      try {
        if (titleOut) {
          // highlightTerms must escape non-matches and only inject <mark>.
          titleOut.innerHTML = highlightTerms(rawTitle, terms, "mark");
        }
        if (excerptOut) {
          excerptOut.innerHTML = highlightTerms(rawExcerpt, terms, "mark");
        }
      } catch {
        // swallow to avoid breaking iAPI runtime; optionally log in dev
        // console.error("renderCardHighlight error", err);
      }
    },
  },
});

// Access actions for the popstate listener
const { actions } = store("listing");

/**
 * Event Listeners for Browser Navigation
 */
window.addEventListener("popstate", (event) => {
  actions.filters.restoreFromHistory(event.state?.query);
});

// Register keyboard listeners after store is ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => actions.init());
} else {
  actions.init();
}
