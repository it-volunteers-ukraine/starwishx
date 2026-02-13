/**
 * Listing Store — Filter Actions
 *
 * Handles interaction logic for checkboxes, radios, and sub-filter searching.
 * File: inc/listing/Assets/filters/actions.js
 */

import { getElement, store, getContext } from "@wordpress/interactivity";
import { fetchJson, debounce, getRestUrl } from "../utils.js";

/**
 * Internal helper to update results.
 * Resets page to 1 whenever a filter is changed.
 */
const refreshResults = () => {
  const { state, actions } = store("listing");
  state.query.page = 1;
  actions.updateResults();
};

export const filterActions = {
  /**
   * Generic toggle for checkboxes and radios.
   * Uses data-field and value from the element.
   */
  toggle() {
    const { state } = store("listing");
    const { ref } = getElement();
    const field = ref.dataset.field;
    const value = ref.type === "checkbox" ? Number(ref.value) : ref.value;

    if (!field || !state.query.hasOwnProperty(field)) return;

    if (Array.isArray(state.query[field])) {
      const index = state.query[field].indexOf(value);
      if (index === -1) {
        state.query[field].push(value);
      } else {
        state.query[field].splice(index, 1);
      }
    } else {
      state.query[field] = state.query[field] === value ? "" : value;
    }

    refreshResults();
  },

  /**
   * Global search input handler.
   */
  updateSearch: debounce((event) => {
    const { state, actions } = store("listing");
    state.query.s = event.target.value;
    refreshResults();
  }, 400),

  /**
   * Specialized Search inside a filter group (e.g., Location).
   * Fetches matching options from the REST API without refreshing the grid.
   */
  searchSubFilter: debounce(async (event) => {
    const { state } = store("listing");
    const target = event.target;
    const filterId = target.dataset.filterId;
    const query = target.value;

    if (query.length < 2 && query.length !== 0) return;

    // UI Loading state specific to this filter
    if (!state.ui) state.ui = {};
    state.ui[
      `isLoading${filterId.charAt(0).toUpperCase() + filterId.slice(1)}`
    ] = true;

    try {
      const data = await fetchJson(
        state,
        `${getRestUrl()}sub-filter/${filterId}?q=${encodeURIComponent(query)}`,
        { requestId: `subfilter-${filterId}` },
      );

      if (data) {
        state.facets[filterId] = data;
      }
    } catch (e) {
      if (e.name !== "AbortError") console.error("Sub-filter search failed", e);
    } finally {
      state.ui[
        `isLoading${filterId.charAt(0).toUpperCase() + filterId.slice(1)}`
      ] = false;
    }
  }, 300),

  /**
   * Select a location from the autocomplete dropdown.
   */
  selectLocation(event) {
    const { state } = store("listing");
    const target = event.target; // Use event.target
    const value = target.value;

    state.query.location = state.query.location === value ? "" : value;
    refreshResults();
  },

  /**
   * Reset the location filter.
   */
  clearLocation() {
    const { state } = store("listing");
    state.query.location = "";
    refreshResults();
  },

  /**
   * Reset all filters to initial state.
   */
  clearAll() {
    const { state } = store("listing");
    state.query.s = "";
    state.query.category = [];
    state.query.country = [];
    state.query.location = "";
    state.query.seekers = [];
    refreshResults();
  },

  /**
   * Action for the PARENT Checkbox
   * Logic: Select All OR Deselect All
   */
  toggleParent() {
    const { state, actions } = store("listing");
    const { ref } = getElement();
    const context = getContext();
    const item = context?.item;

    if (!item) return;

    const parentId = Number(ref.value);
    const children = item.children || [];
    const childIds = children.map((c) => c.id);

    // Get current state
    let current = [...state.query.category];

    if (ref.checked) {
      // Add Parent
      if (!current.includes(parentId)) current.push(parentId);
      // Add ALL Children
      childIds.forEach((id) => {
        if (!current.includes(id)) current.push(id);
      });
    } else {
      // Remove Parent
      current = current.filter((id) => id !== parentId);
      // Remove ALL Children
      current = current.filter((id) => !childIds.includes(id));
    }

    state.query.category = [...new Set(current)];
    state.query.page = 1;
    actions.updateResults();
  },

  /**
   * Action for the CHILD Checkbox
   * Logic: Toggle self. Then check if Parent should be added or removed.
   */
  toggleChild() {
    const { state, actions } = store("listing");
    const { ref } = getElement();
    const context = getContext();
    // In the inner loop, context.item is the CHILD.
    // But we need access to the PARENT context to know siblings.
    // Interactivity API exposes parent context automatically via prototype chain
    // if we access the right property, but here the easiest way is
    // to rely on the fact that we can filter the state directly.

    // Note: To get the full parent object here is tricky in pure API.
    // simpler strategy:
    // 1. Toggle the specific child.
    // 2. "cleanUpParents" logic.

    const childId = Number(ref.value);
    let current = [...state.query.category];

    // 1. Toggle this child
    if (ref.checked) {
      if (!current.includes(childId)) current.push(childId);
    } else {
      current = current.filter((id) => id !== childId);
    }

    // 2. PARENT SYNC LOGIC
    // We need to find the parent of this child.
    // Since we don't have the parent object easily in this context scope,
    // we iterate the Facets Tree to find the relationship.
    const tree = state.facets["category-oportunities"] || [];

    // Helper to find parent in the tree
    let parentItem = null;
    for (const p of tree) {
      if (p.children && p.children.some((c) => c.id === childId)) {
        parentItem = p;
        break;
      }
    }

    if (parentItem) {
      const parentId = parentItem.id;
      const allSiblingIds = parentItem.children.map((c) => c.id);

      // Are ALL siblings now selected?
      const allSelected = allSiblingIds.every((id) => current.includes(id));

      if (allSelected) {
        // If ALL selected, ensure Parent is selected
        if (!current.includes(parentId)) current.push(parentId);
      } else {
        // If NOT ALL selected (indeterminate), ensure Parent is REMOVED
        // This ensures backend only filters by the specific children
        current = current.filter((id) => id !== parentId);
      }
    }

    state.query.category = [...new Set(current)];
    state.query.page = 1;
    actions.updateResults();
  },

  /**
   * Immediate location search (no autocomplete dropdown).
   */
  updateLocationSearch: debounce((event) => {
    const { state, actions } = store("listing");
    state.query.location = event.target.value;
    state.query.page = 1;
    actions.updateResults();
  }, 300),
};
