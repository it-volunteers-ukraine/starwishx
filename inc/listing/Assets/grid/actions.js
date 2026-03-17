/**
 * Listing Store — Grid Actions
 *
 * Orchestrates the fetching of results, pagination, and layout management.
 * File: inc/listing/Assets/grid/actions.js
 */

import { getElement, store } from "@wordpress/interactivity";
import { fetchJson, syncStateToUrl } from "../utils.js";

/**
 * Persist facet labels (and slugs for categories) into state._labelCache
 * before facets are replaced. Ensures orphaned filter chips still display
 * a human-readable label and correct category slug for CSS targeting.
 */
function cacheFacetLabels(state) {
  if (!state._labelCache) state._labelCache = {};
  const facets = state.facets;
  if (!facets) return;

  for (const [key, items] of Object.entries(facets)) {
    if (!Array.isArray(items)) continue;
    for (const item of items) {
      if (item.id && item.label) {
        state._labelCache[`${key}:${item.id}`] = item.label;
        if (item.slug) state._labelCache[`${key}:${item.id}:slug`] = item.slug;
      }
      if (Array.isArray(item.children)) {
        for (const child of item.children) {
          if (child.id && child.label) {
            state._labelCache[`${key}:${child.id}`] = child.label;
            state._labelCache[`${key}:${child.id}:child`] = true;
            // Children inherit parent slug for CSS color targeting
            if (item.slug)
              state._labelCache[`${key}:${child.id}:slug`] = item.slug;
          }
        }
      }
    }
  }
}

/**
 * Internal orchestration to fetch data from the REST API.
 *
 * @param {Object} options
 * @param {boolean} options.append - If true, appends results (pagination). If false, replaces them.
 */
async function fetchResults({ append = false } = {}) {
  const { state } = store("listing");

  // Build Query String from state.query
  state.isLoading = true;

  const params = new URLSearchParams();
  Object.entries(state.query).forEach(([key, value]) => {
    if (!value || (Array.isArray(value) && value.length === 0)) return;
    //! Skip page=1 — it's the server default
    if (key === "page" && value === 1) return;
    if (Array.isArray(value)) {
      value.forEach((val) => params.append(key, val));
    } else {
      params.set(key, value);
    }
  });

  try {
    const { state: settingsState } = store("listingSettings");
    const baseUrl = settingsState.config.restUrl;

    const data = await fetchJson(
      state,
      `${baseUrl}search?${params.toString()}`,
      { requestId: "main-search" },
    );

    if (data) {
      if (append) {
        state.results.push(...data.items);
      } else {
        state.results = data.items;
      }
      state.totalFound = data.total;
      state.totalPages = data.total_pages;
      if (data.facets) {
        cacheFacetLabels(state);
        state.facets = data.facets;
      }
      syncStateToUrl(state.query);
    }
  } catch (e) {
    if (e.name !== "AbortError") console.error("Listing Search Error:", e);
  } finally {
    state.isLoading = false;
  }
}

export const gridActions = {
  /**
   * Main entry point for updating results (Resetting to page 1).
   */
  async updateResults() {
    const { state } = store("listing");
    await fetchResults({ append: false });
  },

  /**
   * Load the next page of results.
   */
  async loadMore() {
    const { state } = store("listing");
    if (state.query.page >= state.totalPages) return;

    state.query.page++;
    await fetchResults({ append: true });
  },

  /**
   * Toggles the UI layout (Grid vs List).
   */
  setLayout() {
    const { state } = store("listing");
    const { ref } = getElement();
    if (ref.value) {
      state.layout = ref.value;
    }
  },
};
