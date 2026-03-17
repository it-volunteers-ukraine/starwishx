/**
 * Listing Store — Grid Getters
 *
 * Computed properties for the results display.
 * 'this' refers to the state proxy.
 *
 * File: inc/listing/Assets/grid/getters.js
 */
import { getContext, store } from "@wordpress/interactivity";
import { __, _n, sprintf } from "@wordpress/i18n";

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
    // return `${this.totalFound} opportunities found`;
    // return sprintf(
    //   _n(
    //     "%d opportunity found",
    //     "%d opportunities found",
    //     this.totalFound,
    //     "launchpad",
    //   ),
    //   this.totalFound,
    // );
    return this.totalFound;
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

      // Smart count for hierarchical category: parent = 1, ignore its children
      if (key === "category" && Array.isArray(value) && value.length > 0) {
        const tree = this.facets["category-oportunities"] || [];
        for (const node of tree) {
          if (value.includes(node.id)) {
            count++;
            continue;
          }
          if (node.children && node.children.length > 0) {
            count += node.children.filter((c) => value.includes(c.id)).length;
          }
        }
      } else if (Array.isArray(value)) {
        count += value.length;
      } else if (value) {
        count += 1;
      }
    });

    return count;
  },

  /**
   * Boolean: are any filters active?
   */
  get hasActiveFilters() {
    return this.activeFiltersCount > 0;
  },

  /**
   * Formatted label for the filter count badge on mobile button.
   * Returns "(3)" or empty string.
   */
  get activeFiltersCountLabel() {
    const count = this.activeFiltersCount;
    return count > 0 ? `(${count})` : "";
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
    const userFavorites = store("favorites").state.myFavoriteIds;

    return userFavorites.includes(id);
  },

  /**
   * Per-card favorite state.
   *
   * Uses ONLY the local context property (item.isFavorite) — no cross-store
   * reactive dependencies. The toggleFavorite action flips this property
   * optimistically, and the global myFavoriteIds array is updated in the
   * background for server persistence. This keeps each card an independent
   * reactive island within the data-wp-each loop.
   */
  get isFavorited() {
    const ctx = getContext();
    const item = ctx?.item;
    if (!item || !item.id) return false;
    return !!item.isFavorite;
  },

  /**
   * When exactly one parent category is selected, return its label.
   * Returns empty string otherwise.
   */
  get selectedCategoryName() {
    const selected = this.query.category;
    if (!Array.isArray(selected) || selected.length === 0) return "";

    const tree = this.facets["category-oportunities"] || [];
    const selectedParents = tree.filter((node) => selected.includes(node.id));

    return selectedParents.length === 1 ? selectedParents[0].label : "";
  },

  /**
   * Boolean: is exactly one parent category selected?
   */
  get hasSelectedCategory() {
    return this.selectedCategoryName !== "";
  },

  /**
   * Builds an array of chip objects for all active filters.
   * Each chip: { key, field, value, label }
   * Label is truncated to 33 characters.
   *
   * Smart category handling:
   *  - parent selected → single chip with parent label (children hidden)
   *  - only children selected → individual chips per child
   */
  get activeFilterChips() {
    const chips = [];
    const MAX = 33;
    const tr = (s) => (s.length > MAX ? s.slice(0, MAX) + "\u2026" : s);
    const cache = this._labelCache || {};

    // --- Category (hierarchical) ---
    const catIds = this.query.category;
    if (Array.isArray(catIds) && catIds.length > 0) {
      const tree = this.facets["category-oportunities"] || [];
      const handled = new Set();

      for (const node of tree) {
        if (catIds.includes(node.id)) {
          chips.push({
            key: `category-${node.id}`,
            field: "category",
            value: node.id,
            label: tr(node.label),
            slug: node.slug || "",
            isChild: false,
          });
          handled.add(node.id);
          // Children are visually contained under the parent chip
          if (node.children) node.children.forEach((c) => handled.add(c.id));
          continue;
        }
        if (node.children) {
          for (const child of node.children) {
            if (catIds.includes(child.id)) {
              chips.push({
                key: `category-${child.id}`,
                field: "category",
                value: child.id,
                label: tr(child.label),
                slug: node.slug || "",
                isChild: true,
              });
              handled.add(child.id);
            }
          }
        }
      }

      // Orphaned: selected but disappeared from facets after cross-filter refinement
      for (const id of catIds) {
        if (handled.has(id)) continue;
        const label = cache[`category-oportunities:${id}`];
        if (label) {
          chips.push({
            key: `category-${id}`,
            field: "category",
            value: id,
            label: tr(label),
            slug: cache[`category-oportunities:${id}:slug`] || "",
            isChild: !!cache[`category-oportunities:${id}:child`],
          });
        }
      }
    }

    // --- Flat arrays: country, seekers ---
    const flat = [
      { field: "country", facetKey: "country" },
      { field: "seekers", facetKey: "category-seekers" },
    ];
    for (const { field, facetKey } of flat) {
      const ids = this.query[field];
      if (!Array.isArray(ids) || ids.length === 0) continue;
      const list = this.facets[facetKey] || [];
      for (const id of ids) {
        const f = list.find((item) => item.id == id);
        const label = f ? f.label : cache[`${facetKey}:${id}`];
        if (!label) continue;
        chips.push({
          key: `${field}-${id}`,
          field,
          value: id,
          label: tr(label),
        });
      }
    }

    // --- Location (string value) ---
    if (this.query.location) {
      chips.push({
        key: "location",
        field: "location",
        value: this.query.location,
        label: tr(this.query.location),
      });
    }

    return chips;
  },
};
