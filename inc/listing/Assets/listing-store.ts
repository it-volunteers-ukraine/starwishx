/**
 * Listing Store — Main Entry
 *
 * Orchestrates Faceted Search using the Interactivity API.
 * File: inc/listing/Assets/listing-store.ts
 */

import {
  getContext,
  getElement,
  store,
  withSyncEvent,
} from "@wordpress/interactivity";
import { keepTabInside } from "../../shared/Assets/shared-utils.js";
import "../../shared/Assets/popup-store.js";
import { filterActions } from "./filters/actions.ts";
import { filterGetters } from "./filters/getters.ts";
import { gridActions } from "./grid/actions.ts";
import { gridGetters } from "./grid/getters.ts";
import { favoritesStore, listingStore, popupStore } from "./stores.ts";
import type { CardContext, ClientState, ListingActions } from "./types.ts";
import {
  extendState,
  highlightTerms,
  historyEntry,
  historyQuery,
} from "./utils.ts";

/**
 * Base State Definition
 *
 * Only client-only state: typed ClientState, so a key hydrated from the
 * server via wp_interactivity_state() in PHP (results, facets, totalFound,
 * totalPages, query - see ServerState) is a compile error here. Declaring
 * one would make the Client Store overwrite the Server Data with its
 * default, breaking SSR hydration.
 */
const clientState: ClientState = {
  isLoading: false,
  isSidebarOpen: false,
  error: "", // message of the last failed request (grid/actions.ts)
  announcement: "", // "Found: N" for the results' role=status (grid/actions.ts)
};

/**
 * Mixin Getters
 * Preserves getter descriptors so 'this' remains reactive. The getters the
 * markup uses have PHP twins in inc/listing/Core/DerivedState.php, which
 * render the page on the server.
 */
const listingState = extendState(clientState, gridGetters, filterGetters);

/** The drawer state callbacks.syncSidebarFocus last acted on. */
let sidebarWasOpen = false;

const listingActions = {
  ...gridActions,
  filters: filterActions,

  toggleSidebar(): void {
    const { state } = listingStore();
    state.isSidebarOpen = !state.isSidebarOpen;
    document.body.classList.toggle("sidebar-open", state.isSidebarOpen);
  },

  closeSidebar(): void {
    listingStore().state.isSidebarOpen = false;
    document.body.classList.remove("sidebar-open");
  },

  /**
   * Keeps Tab inside the open drawer (keepTabInside, shared with the
   * popup). Bound with data-wp-on--keydown on the sidebar; withSyncEvent
   * because it has to preventDefault() the keystroke it redirects.
   */
  trapSidebarFocus: withSyncEvent((event: KeyboardEvent): void => {
    if (event.key !== "Tab" || !listingStore().state.isSidebarOpen) return;
    const { ref } = getElement();
    if (ref) keepTabInside(event, ref);
  }),

  init(): void {
    const { state, actions } = listingStore();

    // SSR hydration is authoritative for the initial URL state.
    // Both clean URLs and filtered URLs (?category=..., /slug/)
    // are correctly hydrated by StateAggregator.

    // Give the entry the page loaded into the same query snapshot that
    // syncStateToUrl() stores, so Back to it restores filters the same
    // way (filters.restoreFromHistory).
    window.history.replaceState(historyEntry(state.query), "");

    // Close offcanvas sidebar on Escape key
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && state.isSidebarOpen) {
        actions.closeSidebar();
      }
    });
  },

  /**
   * THE TRIGGER:
   * A click on the card's favorites checkbox - directly, via its label
   * text, or Space. withSyncEvent because a guest's click is cancelled:
   * the box must not tick (nor be announced as checked) when nothing was
   * saved - same as single-opportunity-store.js.
   */
  toggleFavorite: withSyncEvent(async (event: Event): Promise<void> => {
    const { item } = getContext<Partial<CardContext>>();
    if (!item?.id) return;

    const { state } = listingStore();

    // Guest guard: show auth popup instead of toggling
    if (!state.isUserLoggedIn) {
      event.preventDefault();
      popupStore().actions.open();
      return;
    }

    // The "Favorites" filter's count follows the heart. It counts the
    // user's favorites among the posts the other filters leave, and
    // every card shown is one of those, so one heart is exactly one.
    const shiftCount = (delta: number): void => {
      if (typeof state.facets.favorites === "number") {
        state.facets.favorites += delta;
      }
    };

    // OPTIMISTIC UPDATE (Local Context): the browser has ticked the box;
    // flip the item so state.isFavorited agrees with it.
    item.isFavorite = !item.isFavorite;
    shiftCount(item.isFavorite ? 1 : -1);

    // Call Global Store
    // This updates the Global Array, which triggers the getter again.
    await favoritesStore().actions.toggle(item.id);

    // The favorites store puts its list back when saving fails: follow
    // it, or the heart and the count would keep the unsaved change.
    const saved = (favoritesStore().state.myFavoriteIds ?? []).some(
      (id) => Number(id) === Number(item.id),
    );
    if (saved !== item.isFavorite) {
      item.isFavorite = saved;
      shiftCount(saved ? 1 : -1);
    }
  }),
} satisfies ListingActions;

/**
 * STORE DEFINITION
 * Standardized registration pattern.
 */
const { actions } = store("listing", {
  state: listingState,
  actions: listingActions,
  callbacks: {
    /**
     * data-wp-watch on the sidebar. Focus follows the drawer: into it when it
     * opens (the close button, its first control) and back to the Filters
     * button when it closes, unless focus has already moved elsewhere. Watch
     * re-runs come after the next frame, so the open drawer is visible, and
     * therefore focusable, by the time this runs. The first run, at
     * hydration, sees no change and leaves focus alone.
     */
    syncSidebarFocus(): void {
      const { ref } = getElement();
      const isOpen = listingStore().state.isSidebarOpen;
      if (!ref || isOpen === sidebarWasOpen) return;
      sidebarWasOpen = isOpen;

      if (isOpen) {
        const close = ref.querySelector(".listing-sidebar__close");
        if (close instanceof HTMLElement) close.focus();
        return;
      }

      const current = document.activeElement;
      if (!current || current === document.body || ref.contains(current)) {
        const toggle = document.querySelector(`[aria-controls="${ref.id}"]`);
        if (toggle instanceof HTMLElement) toggle.focus();
      }
    },

    /**
     * data-wp-watch on a parent category checkbox: mirrors the partial
     * selection into its indeterminate property, which is what screen readers
     * announce as "mixed" (the .is-indeterminate class only draws the dash).
     * A watch rather than data-wp-bind--indeterminate: indeterminate is a
     * property without an attribute, and the server render would write it
     * into the HTML as an invalid one.
     */
    syncIndeterminate(): void {
      const { ref } = getElement();
      if (ref instanceof HTMLInputElement) {
        ref.indeterminate = listingStore().state.isPartiallySelected;
      }
    },

    /**
     * Highlights the search terms in a card's .title-highlight and
     * .excerpt-highlight. Not bound by the current card markup.
     */
    renderCardHighlight(): void {
      const { ref: root } = getElement();
      if (!root) return;

      // Card-local context, and the query terms (a reactive dependency)
      const { item } = getContext<Partial<CardContext>>();
      const { query } = listingStore().state;
      const terms = [query.s, query.location].filter((term) => term !== "");

      const titleOut = root.querySelector(".title-highlight");
      const excerptOut = root.querySelector(".excerpt-highlight");

      // Raw text: context.item (reactive), else the node's own text
      const rawTitle = item?.title || (titleOut?.textContent ?? "").trim();
      const rawExcerpt =
        item?.excerpt || (excerptOut?.textContent ?? "").trim();

      // highlightTerms escapes everything but the <mark>s it adds.
      if (titleOut) titleOut.innerHTML = highlightTerms(rawTitle, terms);
      if (excerptOut) excerptOut.innerHTML = highlightTerms(rawExcerpt, terms);
    },
  },
});

/**
 * Event Listeners for Browser Navigation
 */
window.addEventListener("popstate", (event) => {
  actions.filters.restoreFromHistory(historyQuery(event.state));
});

// Register keyboard listeners after store is ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => actions.init());
} else {
  actions.init();
}
