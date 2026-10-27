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

/** The request that failed last, for actions.retry. */
let failedRequest = null;

/**
 * Internal orchestration to fetch data from the REST API.
 *
 * @param {Object} options
 * @param {boolean} options.append - If true, appends results (pagination). If false, replaces them.
 * @param {"push"|"replace"|"none"} options.history - What a successful
 *   response writes to the URL: a new Back step (a filter change), the
 *   current entry rewritten (see syncStateToUrl), or nothing.
 */
async function fetchResults({ append = false, history = "push" } = {}) {
  const { state } = store("listing");

  // Build Query String from state.query
  state.isLoading = true;
  state.error = "";
  failedRequest = null;
  // Emptied first so the status region re-announces even an unchanged count.
  if (!append) state.announcement = "";

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
      // The request ran as a guest's (see fetchJson), so whether a card is
      // in the visitor's favorites comes from the favorites store, which
      // FavoritesCore hydrates for logged-in users and every toggle keeps
      // current. Guests have none.
      const favoriteIds = store("favorites").state.myFavoriteIds ?? [];
      for (const item of data.items) {
        item.isFavorite = favoriteIds.includes(item.id);
      }

      if (append) {
        state.results.push(...data.items);
      } else {
        state.results = data.items;
      }
      state.totalFound = data.total;
      state.totalPages = data.total_pages;
      if (!append && state.i18n?.found) {
        state.announcement = `${state.i18n.found}: ${data.total}`;
      }
      if (data.facets) {
        cacheFacetLabels(state);
        state.facets = data.facets;
      }
      if (history !== "none") {
        syncStateToUrl(state.query, { replace: history === "replace" });
      }
    }
  } catch (e) {
    if (e.name === "AbortError") return;
    console.error("Listing Search Error:", e);
    // The server's own message when it sent one (rate limit, validation) -
    // translated on its side - otherwise the generic one PHP hydrated.
    state.error =
      Object.values(e.fieldErrors ?? {})[0] ||
      e.serverMessage ||
      state.i18n?.loadError ||
      e.message;
    failedRequest = { append, history };
  } finally {
    state.isLoading = false;
  }
}

export const gridActions = {
  /**
   * Main entry point for updating results (Resetting to page 1).
   *
   * @param {Object} [options]
   * @param {"push"|"replace"|"none"} [options.history="push"] - See fetchResults().
   */
  async updateResults({ history = "push" } = {}) {
    await fetchResults({ append: false, history });
  },

  /**
   * Load the next page of results. ?page=N in the URL is rewritten in place,
   * not added as a Back step, so a reload or a shared link renders the same
   * list (StateAggregator renders pages 1..N).
   */
  async loadMore() {
    const { state } = store("listing");
    if (state.query.page >= state.totalPages) return;

    const shown = state.results.length;
    state.query.page++;
    await fetchResults({ append: true, history: "replace" });

    // Focus the first new card: keyboard and screen-reader users go on
    // reading where the new items start, and focus isn't left on the button,
    // which hides once the last page is in. Next frame: the cards render then.
    if (state.results.length > shown) {
      requestAnimationFrame(() => {
        const cards = document.querySelectorAll(
          ".listing-grid article.listing-card",
        );
        cards[shown]?.querySelector(".listing-card__title--link")?.focus();
      });
    }
  },

  /**
   * "Try again" in the error alert: re-runs the request that failed. A
   * failed Load more already advanced the page, so it fetches the same page.
   */
  async retry() {
    if (failedRequest) await fetchResults(failedRequest);
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
