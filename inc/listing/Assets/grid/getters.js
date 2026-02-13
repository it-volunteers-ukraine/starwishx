/**
 * Listing Store — Grid Getters
 *
 * Computed properties for the results display.
 * 'this' refers to the state proxy.
 *
 * File: inc/listing/Assets/grid/getters.js
 */
import { getContext } from "@wordpress/interactivity";

export const gridGetters = {
  /**
   * Boolean check for results presence.
   */
  get hasResults() {
    return Array.isArray(this.results) && this.results.length > 0;
  },

  /**
   * Logic for the pagination button visibility.
   */
  get hasMore() {
    return this.query.page < this.totalPages;
  },

  /**
   * Generates the CSS class for the grid container.
   */
  get layoutClass() {
    return `listing-grid--${this.layout || "grid"}`;
  },

  /**
   * Formatted string for the results count.
   */
  get resultsFoundLabel() {
    if (this.isLoading && this.results.length === 0) return "";
    return `${this.totalFound} opportunities found`;
  },

  /**
   * Count of active filters (excluding search string and page).
   * Useful for showing a "Clear All (3)" button.
   */
  get activeFiltersCount() {
    let count = 0;
    const skipKeys = ["s", "page"];

    Object.entries(this.query).forEach(([key, value]) => {
      if (skipKeys.includes(key)) return;
      if (Array.isArray(value)) count += value.length;
      else if (value) count += 1;
    });

    return count;
  },

  /**
   * Is the current filter checkbox checked?
   * Used in data-wp-each loops for checkboxes.
   * Uses getContext() which works during binding phase.
   */
  get isFilterChecked() {
    const ctx = getContext();
    // Standardize on 'item'
    const item = ctx?.item;
    if (!item) return false;

    const field = ctx?.filterField;
    if (!field) return false;

    const queryValue = this.query[field];

    // Case 1: Array (Taxonomies: Category, Country, Seekers)
    if (Array.isArray(queryValue)) {
      // Use == for loose comparison because SSR sends IDs as Strings,
      // but JS updates them as Numbers.
      return queryValue.some((v) => v == item.id);
    }

    // Case 2: Single Value (City)
    return queryValue == item.id;
  },

  /**
   * Is the current location option selected?
   * Used for radio button checked state in location filter.
   */
  get isLocationSelected() {
    const ctx = getContext();
    const item = ctx?.item;
    if (!item) return false;

    // Location uses code as identifier
    return this.query.location === item.id;
  },

  /**
   * we pass category slug
   * so this is for use it as сlassname
   * */
  get categoryClass() {
    //! we need to call getContext()
    const ctx = getContext();
    if (!ctx?.cat) return "category-tag";
    return `category-tag ${ctx.cat.slug}`;
  },

  /**
   * Get comma-separated seekers list.
   */
  get seekersList() {
    const ctx = getContext();
    const seekers = ctx?.item?.seekers || [];
    if (!Array.isArray(seekers) || seekers.length === 0) {
      return "";
    }
    return seekers.map((s) => s.name).join(", ");
  },

  get isCurrentItemFavorite() {
    const ctx = getContext();
    const id = ctx.item.id;

    // Read directly from the OTHER store
    const userFavorites = store("launchpad/favorites").state.myFavoriteIds;

    return userFavorites.includes(id);
  },

  /**
   * THE BRIDGE:
   * Robustly checks Global Store vs Local SSR Context
   */
  get isFavorited() {
    const ctx = getContext();
    const item = ctx?.item;

    // 1. Safety Check: If we are not in a valid context, stop.
    if (!item || !item.id) return false;

    // 2. Access Global Store
    // We use try/catch to safely handle if the other store isn't registered yet
    let globalIds = [];
    try {
      const favState = store("launchpad/favorites").state;
      if (favState && Array.isArray(favState.myFavoriteIds)) {
        globalIds = favState.myFavoriteIds;
      }
    } catch (e) {
      console.warn("Favorites store not ready");
    }

    // 3. LOGIC: If we have GLOBAL data, we trust it 100%.
    // We check length > 0 to distinguish "Active List" from "Initial Empty State".
    if (globalIds.length > 0) {
      return globalIds.map(Number).includes(Number(item.id));
    }

    // 4. FALLBACK (SSR Data):
    // If global list is empty, we fall back to the SSR/Local state.
    // This prevents the "Flicker to Empty" on page load.
    // Also handles the case where the user genuinely has 0 favorites
    // (item.isFavorite will be false from SSR).
    return !!item.isFavorite;
  },
};
