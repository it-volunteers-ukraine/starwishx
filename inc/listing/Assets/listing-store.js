/**
 * Listing Store — Main Entry
 *
 * Orchestrates Faceted Search using the Interactivity API.
 * File: inc/listing/Assets/listing-store.js
 */

import { store, getContext, getElement } from "@wordpress/interactivity";
import { extendState } from "./utils.js"; // or ../../shared/Assets/shared-utils.js
import { filterActions } from "./filters/actions.js";
import { gridActions } from "./grid/actions.js";
import { gridGetters } from "./grid/getters.js";
import { syncUrlToState, highlightTerms } from "./utils.js";

/**
 * Base State Definition
 * Properties defined here are the "Schema" of the app.
 */
const listingState = {
  query: {
    s: "",
    category: [],
    country: [],
    location: "",
    seekers: [],
    page: 1,
  },
  facets: {},
  results: [],
  totalFound: 0,
  totalPages: 0,
  isLoading: false,
  isSidebarOpen: true,
  layout: "grid",
  ui: {
    isLoadingLocation: false, // Loading state for location autocomplete
  },
};

/**
 * Mixin Getters
 * Preserves getter descriptors so 'this' remains reactive.
 */
extendState(listingState, gridGetters);

/**
 * STORE DEFINITION
 * Standardized registration pattern.
 */
store("listing", {
  state: listingState,
  actions: {
    ...gridActions,
    filters: filterActions,

    init() {
      const { state, actions } = store("listing");
      syncUrlToState(state.query);
      // Optional: Initial fetch if results are empty (non-SSR fallback)
      if (state.results.length === 0) {
        actions.updateResults();
      }
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
        // 2. OPTIMISTIC UPDATE (Local Context)
        // We MUST flip this boolean so the Fallback logic in the getter
        // stays in sync with the user's intent.
        item.isFavorite = !item.isFavorite;

        // 3. Call Global Store
        // This updates the Global Array, which triggers the getter again.
        await store("launchpad/favorites").actions.toggle(item.id);
      }
    },
  },
  callbacks: {
    isParentExpanded: () => {
      const { state } = store("listing");
      const context = getContext();
      const item = context?.item;

      // Expansion logic: Expand if Parent OR any child is selected
      if (!item) return false;

      const field = context?.filterField || "category";
      const selected = state.query[field] || [];
      const hasSelectedChild = item.children?.some((c) =>
        selected.includes(c.id),
      );
      const isParentSelected = selected.includes(item.id);
      return isParentSelected || hasSelectedChild;
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

      // Delegate to the Launchpad store
      // We manually construct a context or pass the ID if the store supports it
      // Assuming launchpad/favorites store reads context.item.id or dataset

      // Trick: We access the other store's actions directly
      await store("launchpad/favorites").actions.toggle({
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
    /**
     * Pure logic calculation. No DOM manipulation.
     * Returns true if some, but not all, children are selected.
     */
    isIndeterminate: () => {
      const { state } = store("listing");
      const context = getContext();
      const item = context?.item;

      // Safety check
      if (
        !item ||
        !Array.isArray(item.children) ||
        item.children.length === 0
      ) {
        return false;
      }

      const selectedIds = state.query.category;
      const childrenIds = item.children.map((c) => c.id);

      // Count matches
      const selectedCount = childrenIds.filter((id) =>
        selectedIds.includes(id),
      ).length;

      // Logic: Indeterminate if SOME children checked, but NOT ALL
      return selectedCount > 0 && selectedCount < childrenIds.length;
    },

    /**
     * Calculates the "Human Readable" count of active filters.
     * Usage: data-wp-text="callbacks.getActiveFilterCount"
     */
    getActiveFilterCount: () => {
      const { state } = store("listing");
      const context = getContext();
      const filterId = context?.filterId;

      if (!filterId) return "";

      const value = state.query[filterId];

      // 1. Handle Empty States
      if (!value || (Array.isArray(value) && value.length === 0)) {
        return "";
      }

      // 2. Handle Text/String Filters (like City or Search)
      if (typeof value === "string") {
        return "(1)";
      }

      // 3. Handle Hierarchical Filters (Category) - The "Smart Count"
      // We assume 'category' is the ID for the hierarchical filter
      if (filterId === "category") {
        const tree = state.facets["category-oportunities"] || [];
        let count = 0;
        const selectedIds = value; // Array of IDs

        // Iterate Top-Level Nodes only
        for (const node of tree) {
          // Scenario A: Parent is explicitly selected (User checked Parent)
          if (selectedIds.includes(node.id)) {
            count++;
            // Architecturally: We ignore children because visually
            // the Parent "contains" them.
            continue;
          }

          // Scenario B: Parent NOT selected, but Children might be (Indeterminate)
          if (node.children && node.children.length > 0) {
            const activeChildren = node.children.filter((c) =>
              selectedIds.includes(c.id),
            );
            count += activeChildren.length;
          }
        }

        // Safety: If for some reason we have IDs not in the tree (ghosts), ignore them
        // or fallback to simple length? For precise UI, tree logic is safer.
        return count > 0 ? `(${count})` : "";
      }

      // 4. Handle Flat Arrays (Country, Seekers)
      return `(${value.length})`;
    },

    /**
     * Returns true if the current filter group has ANY selection.
     * Usage: data-wp-class--has-selection="callbacks.hasActiveFilter"
     */
    hasActiveFilter: () => {
      const { state } = store("listing");
      const context = getContext();
      const filterId = context?.filterId;

      if (!filterId) return false;
      const value = state.query[filterId];

      if (Array.isArray(value)) return value.length > 0;
      return !!value;
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
      } catch (err) {
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
window.addEventListener("popstate", () => {
  const { state } = store("listing");
  syncUrlToState(state.query);
  actions.updateResults();
});

// Optional patch: Initial sync if not handled by SSR hydration
// When SSR hydration will done - get rid off this
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => actions.init());
} else {
  actions.init();
}
