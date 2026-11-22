/**
 * Listing Store — Grid Getters
 *
 * Computed properties for the results display.
 * 'this' refers to the state proxy (ThisType below).
 *
 * The getters archive-opportunity.php binds have PHP twins in
 * inc/listing/Core/DerivedState.php, which render the page on the server -
 * keep the two in step.
 *
 * File: inc/listing/Assets/grid/getters.ts
 */
import { getContext } from "@wordpress/interactivity";
import type {
  CardContext,
  CategoryNode,
  Chip,
  FacetOption,
  FilterListContext,
  GridGetters,
  ItemContext,
  ListingState,
} from "../types.ts";

/** Longest chip label before it is cut with "…" (DerivedState::CHIP_LABEL_MAX). */
const CHIP_LABEL_MAX = 33;

const chipLabel = (label: string): string =>
  label.length > CHIP_LABEL_MAX ? label.slice(0, CHIP_LABEL_MAX) + "…" : label;

/** The selected parent category node when exactly one is selected. */
function selectedParent(state: ListingState): CategoryNode | undefined {
  const selected = state.query.category;
  if (selected.length === 0) return undefined;

  const parents = state.facets["category-oportunities"].filter((node) =>
    selected.includes(node.id),
  );
  return parents.length === 1 ? parents[0] : undefined;
}

/** A label cached from earlier facets (grid/actions.ts cacheFacetLabels). */
function cachedLabel(state: ListingState, key: string): string | undefined {
  const label = state._labelCache?.[key];
  return typeof label === "string" ? label : undefined;
}

export const gridGetters: GridGetters & ThisType<ListingState> = {
  /**
   * Boolean check for results presence.
   */
  get hasResults() {
    return this.results.length > 0;
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
   * The results count; empty while the first results load.
   */
  get resultsFoundLabel() {
    if (this.isLoading && this.results.length === 0) return "";
    return this.totalFound;
  },

  /**
   * Count of active filters (excluding search string and page).
   * Useful for showing a "Clear All (3)" button.
   */
  get activeFiltersCount() {
    let count = 0;

    for (const [key, value] of Object.entries(this.query)) {
      if (key === "s" || key === "page") continue;

      // Smart count for hierarchical category: parent = 1, ignore its children
      if (key === "category" && Array.isArray(value) && value.length > 0) {
        for (const node of this.facets["category-oportunities"]) {
          if (value.includes(node.id)) {
            count++;
            continue;
          }
          count += node.children.filter((c) => value.includes(c.id)).length;
        }
      } else if (Array.isArray(value)) {
        count += value.length;
      } else if (value) {
        count += 1;
      }
    }

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
   */
  get isFilterChecked() {
    const { item, filterField } =
      getContext<Partial<FilterListContext & ItemContext<FacetOption>>>();
    if (!item || !filterField) return false;

    // Loose, as the PHP twin is: ids from the markup may arrive as strings.
    return this.query[filterField].some((id) => id == item.id);
  },

  /**
   * A card's category tag classes: "category-tag {slug}" (context.cat).
   */
  get categoryClass() {
    const { cat } = getContext<Partial<CardContext>>();
    return cat ? `category-tag ${cat.slug}` : "category-tag";
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
    const { item } = getContext<Partial<CardContext>>();
    if (!item?.id) return false;
    return !!item.isFavorite;
  },

  /**
   * When exactly one parent category is selected, return its label.
   * Returns empty string otherwise.
   */
  get selectedCategoryName() {
    return selectedParent(this)?.label ?? "";
  },

  /**
   * Boolean: is exactly one parent category selected?
   */
  get hasSelectedCategory() {
    return this.selectedCategoryName !== "";
  },

  /**
   * Image URL for the selected single parent category (from ACF term field).
   * null when there is none, so data-wp-bind--src drops the attribute rather
   * than writing src="".
   */
  get selectedCategoryImageUrl() {
    return selectedParent(this)?.imageUrl || null;
  },

  /**
   * WP taxonomy description for the selected single parent category.
   */
  get selectedCategoryDescription() {
    return selectedParent(this)?.description || "";
  },

  /**
   * Builds an array of chip objects for all active filters.
   * Label is truncated to CHIP_LABEL_MAX characters.
   *
   * Smart category handling:
   *  - parent selected → single chip with parent label (children hidden)
   *  - only children selected → individual chips per child
   */
  get activeFilterChips() {
    const chips: Chip[] = [];

    // --- Category (hierarchical) ---
    const catIds = this.query.category;
    if (catIds.length > 0) {
      const handled = new Set<number>();

      for (const node of this.facets["category-oportunities"]) {
        if (catIds.includes(node.id)) {
          chips.push({
            key: `category-${node.id}`,
            field: "category",
            value: node.id,
            label: chipLabel(node.label),
            slug: node.slug,
            isChild: false,
          });
          handled.add(node.id);
          // Children are visually contained under the parent chip
          for (const child of node.children) handled.add(child.id);
          continue;
        }
        for (const child of node.children) {
          if (catIds.includes(child.id)) {
            chips.push({
              key: `category-${child.id}`,
              field: "category",
              value: child.id,
              label: chipLabel(child.label),
              slug: node.slug,
              isChild: true,
            });
            handled.add(child.id);
          }
        }
      }

      // Orphaned: selected but disappeared from facets after cross-filter refinement
      for (const id of catIds) {
        if (handled.has(id)) continue;
        const label = cachedLabel(this, `category-oportunities:${id}`);
        if (label) {
          chips.push({
            key: `category-${id}`,
            field: "category",
            value: id,
            label: chipLabel(label),
            slug: cachedLabel(this, `category-oportunities:${id}:slug`) ?? "",
            isChild: !!this._labelCache?.[`category-oportunities:${id}:child`],
          });
        }
      }
    }

    // --- Flat lists: country, beneficiaries ---
    const flat: readonly {
      field: "country" | "beneficiaries";
      facetKey: "country" | "category-beneficiaries";
    }[] = [
      { field: "country", facetKey: "country" },
      { field: "beneficiaries", facetKey: "category-beneficiaries" },
    ];
    for (const { field, facetKey } of flat) {
      const options: readonly FacetOption[] = this.facets[facetKey];
      for (const id of this.query[field]) {
        const label =
          options.find((option) => option.id == id)?.label ??
          cachedLabel(this, `${facetKey}:${id}`);
        if (!label) continue;
        chips.push({
          key: `${field}-${id}`,
          field,
          value: id,
          label: chipLabel(label),
        });
      }
    }

    // --- Location (string value) ---
    if (this.query.location) {
      chips.push({
        key: "location",
        field: "location",
        value: this.query.location,
        label: chipLabel(this.query.location),
      });
    }

    // --- Favorites (boolean, logged-in users only) ---
    if (this.query.favorites) {
      chips.push({
        key: "favorites",
        field: "favorites",
        value: true,
        label: chipLabel(this.i18n.favoritesChip),
      });
    }

    return chips;
  },

  /**
   * "Remove filter: {chip label}" for a chip's button (context.item is the
   * chip), so each button says which filter it removes.
   */
  get chipRemoveLabel() {
    const removeFilter = this.i18n.removeFilter;
    const { item } = getContext<Partial<ItemContext<Chip>>>();
    return item?.label ? `${removeFilter}: ${item.label}` : removeFilter;
  },
};
