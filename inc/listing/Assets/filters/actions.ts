/**
 * Listing Store — Filter Actions
 *
 * Handles interaction logic for checkboxes and the text filters.
 * File: inc/listing/Assets/filters/actions.ts
 */

import { getContext, getElement } from "@wordpress/interactivity";
import { listingStore } from "../stores.ts";
import type {
  CategoryNode,
  Chip,
  ItemContext,
  ListingQuery,
} from "../types.ts";
import { debounce, isListKey, syncUrlToState } from "../utils.ts";

/** The text filters, whose typing runs share one Back step. */
type TextField = "s" | "location";

/** The text field of the typing run in progress, if any. */
let typingField: TextField | null = null;

/**
 * Internal helper to update results.
 * Resets page to 1 whenever a filter is changed. A change is a new Back
 * step, except while typing (refreshFromTyping) and on Back/Forward itself.
 */
const refreshResults = ({
  history = "push",
  typing = null,
}: {
  history?: "push" | "replace";
  typing?: TextField | null;
} = {}): void => {
  const { state, actions } = listingStore();
  typingField = typing;
  state.query.page = 1;
  void actions.updateResults({ history });
};

/**
 * Debounced keyword / location input. The first request of a typing run
 * adds a Back step and the following ones in the same field rewrite it, so
 * Back returns to the filters from before the search rather than to every
 * half-typed word.
 */
const refreshFromTyping = (field: TextField): void =>
  refreshResults({
    history: typingField === field ? "replace" : "push",
    typing: field,
  });

/** The text a debounced input handler was called for. */
const inputValue = (event: Event): string | null =>
  event.target instanceof HTMLInputElement ? event.target.value : null;

/** The `<input>` the action runs on, when it is one. */
function inputElement(): HTMLInputElement | null {
  const { ref } = getElement();
  return ref instanceof HTMLInputElement ? ref : null;
}

export const filterActions = {
  /**
   * Toggle for the id-list checkboxes (category, country, beneficiaries)
   * and the location value. Uses data-field and value from the element.
   */
  toggle(): void {
    const { state } = listingStore();
    const ref = inputElement();
    if (!ref) return;
    const field = ref.dataset.field;

    if (isListKey(field)) {
      const ids = state.query[field];
      const value = Number(ref.value);
      const index = ids.indexOf(value);
      if (index === -1) {
        ids.push(value);
      } else {
        ids.splice(index, 1);
      }
    } else if (field === "location") {
      state.query.location =
        state.query.location === ref.value ? "" : ref.value;
    } else {
      return;
    }

    refreshResults();
  },

  /**
   * Global search input handler.
   */
  updateSearch: debounce((event: Event): void => {
    const value = inputValue(event);
    if (value === null) return;
    listingStore().state.query.s = value;
    refreshFromTyping("s");
  }, 400),

  /**
   * Reset the location filter.
   */
  clearLocation(): void {
    listingStore().state.query.location = "";
    refreshResults();
  },

  /**
   * Remove a single filter chip.
   * Reads field + value from the data-wp-each context item.
   */
  removeChip(): void {
    const { state } = listingStore();
    const { item: chip } = getContext<Partial<ItemContext<Chip>>>();
    if (!chip) return;

    const { field, value } = chip;

    if (field === "location") {
      state.query.location = "";
    } else if (field === "favorites") {
      state.query.favorites = false;
    } else if (field === "category") {
      // If removing a parent, also remove all its children
      const parent = state.facets["category-oportunities"].find(
        (node) => node.id === value,
      );
      const removed = new Set<unknown>([
        value,
        ...(parent?.children.map((c) => c.id) ?? []),
      ]);
      state.query.category = state.query.category.filter(
        (id) => !removed.has(id),
      );
    } else if (isListKey(field)) {
      // Loose, as the ids the chips carry may be strings.
      state.query[field] = state.query[field].filter((id) => id != value);
    }

    refreshResults();
  },

  /**
   * Reset all filters to initial state.
   */
  clearAll(): void {
    const { query } = listingStore().state;
    query.s = "";
    query.category = [];
    query.country = [];
    query.location = "";
    query.beneficiaries = [];
    query.favorites = false;
    refreshResults();
  },

  /**
   * "Favorites" (logged-in users only). A boolean, so not toggle(),
   * which reads the input's value as an id.
   */
  toggleFavorites(): void {
    const ref = inputElement();
    if (!ref) return;
    listingStore().state.query.favorites = ref.checked;
    refreshResults();
  },

  /**
   * Action for the PARENT Checkbox
   * Logic: Select All OR Deselect All
   */
  toggleParent(): void {
    const { state } = listingStore();
    const ref = inputElement();
    const { item } = getContext<Partial<ItemContext<CategoryNode>>>();
    if (!ref || !item) return;

    const parentId = Number(ref.value);
    const family = [parentId, ...item.children.map((c) => c.id)];

    state.query.category = ref.checked
      ? [...new Set([...state.query.category, ...family])]
      : state.query.category.filter((id) => !family.includes(id));
    refreshResults();
  },

  /**
   * Action for the CHILD Checkbox
   * Logic: Toggle self, then tick the parent when all its children are
   * ticked and untick it otherwise - the server then filters by the parent
   * only when it stands for all of them.
   */
  toggleChild(): void {
    const { state } = listingStore();
    const ref = inputElement();
    if (!ref) return;

    const childId = Number(ref.value);
    let current = ref.checked
      ? [...state.query.category, childId]
      : state.query.category.filter((id) => id !== childId);

    // The child's context doesn't reach its parent node: find it in the tree.
    const parent = state.facets["category-oportunities"].find((node) =>
      node.children.some((c) => c.id === childId),
    );

    if (parent) {
      const allSelected = parent.children.every((c) => current.includes(c.id));
      current = allSelected
        ? [...current, parent.id]
        : current.filter((id) => id !== parent.id);
    }

    state.query.category = [...new Set(current)];
    refreshResults();
  },

  /**
   * Immediate location search (no autocomplete dropdown).
   */
  updateLocationSearch: debounce((event: Event): void => {
    const value = inputValue(event);
    if (value === null) return;
    listingStore().state.query.location = value;
    refreshFromTyping("location");
  }, 300),

  /**
   * Back/Forward (popstate). Puts the entry's filters back and reloads the
   * results without adding a Back step - pushing one here is what used to
   * keep Back from ever leaving the page. Entries written by
   * syncStateToUrl() carry the query; any other entry is read from its URL.
   * Only the first page comes back (the API serves one page per call), so
   * the entry's ?page is dropped.
   *
   * @param saved - The entry's query (utils.ts historyQuery()).
   */
  restoreFromHistory(saved?: Partial<ListingQuery>): void {
    const { state } = listingStore();
    if (saved) {
      // Entries written before a filter existed lack its key: default it.
      Object.assign(state.query, { favorites: false }, saved);
    } else {
      syncUrlToState(state.query);
    }
    refreshResults({ history: "replace" });
  },
};
