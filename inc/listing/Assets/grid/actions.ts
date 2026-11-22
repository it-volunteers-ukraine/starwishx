/**
 * Listing Store — Grid Actions
 *
 * Orchestrates the fetching of results, pagination, and layout management.
 * File: inc/listing/Assets/grid/actions.ts
 */

import { getElement } from "@wordpress/interactivity";
import { favoritesStore, listingStore, settingsStore } from "../stores.ts";
import type { ListingState, SearchResponse } from "../types.ts";
import { ApiError, isAbortError } from "../../../shared/Assets/store-utils.ts";
import { fetchJson, isSearchResponse, syncStateToUrl } from "../utils.ts";

/**
 * What a successful response writes to the URL: a new Back step (a filter
 * change), the current entry rewritten (see syncStateToUrl), or nothing.
 */
export type HistoryMode = "push" | "replace" | "none";

interface FetchRequest {
  /** Append the results (Load more) rather than replace them. */
  append: boolean;
  history: HistoryMode;
}

/**
 * Persist facet labels (and slugs for categories) into state._labelCache
 * before facets are replaced. Ensures orphaned filter chips still display
 * a human-readable label and correct category slug for CSS targeting.
 */
function cacheFacetLabels(state: ListingState): void {
  const cache = (state._labelCache ??= {});

  for (const [key, items] of Object.entries(state.facets)) {
    if (!Array.isArray(items)) continue;
    for (const item of items) {
      const slug = "slug" in item ? item.slug : "";
      if (item.id && item.label) {
        cache[`${key}:${item.id}`] = item.label;
        if (slug) cache[`${key}:${item.id}:slug`] = slug;
      }
      if (!("children" in item)) continue;
      for (const child of item.children) {
        if (child.id && child.label) {
          cache[`${key}:${child.id}`] = child.label;
          cache[`${key}:${child.id}:child`] = true;
          // Children inherit parent slug for CSS color targeting
          if (slug) cache[`${key}:${child.id}:slug`] = slug;
        }
      }
    }
  }
}

/** The request that failed last, for actions.retry. */
let failedRequest: FetchRequest | null = null;

/**
 * Set once the hydrated nonce is refused - the tab outlived it (12-24h) or
 * the user logged out elsewhere. Searches then run as a guest's, except with
 * "Favorites" on, which only the user's own request can answer.
 */
let nonceExpired = false;

/** Core's code for a nonce that no longer matches the session. */
const INVALID_NONCE = "rest_cookie_invalid_nonce";

/**
 * Internal orchestration to fetch data from the REST API.
 */
async function fetchResults({
  append = false,
  history = "push",
}: Partial<FetchRequest> = {}): Promise<void> {
  const { state } = listingStore();

  // Build Query String from state.query
  state.isLoading = true;
  state.error = "";
  failedRequest = null;
  // Emptied first so the status region re-announces even an unchanged count.
  if (!append) state.announcement = "";

  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(state.query)) {
    if (!value || (Array.isArray(value) && value.length === 0)) continue;
    //! Skip page=1 — it's the server default
    if (key === "page" && value === 1) continue;
    if (Array.isArray(value)) {
      for (const id of value) params.append(key, String(id));
    } else {
      params.set(key, value === true ? "1" : String(value));
    }
  }

  try {
    const url = `${settingsStore().state.config.restUrl}search?${params.toString()}`;
    const request = (auth: boolean): Promise<SearchResponse> =>
      fetchJson(url, isSearchResponse, { requestId: "main-search", auth });

    // Logged-in users search as themselves (see fetchJson): the server
    // filters by and counts their favorites. Once the nonce is refused,
    // carry on as a guest - unless "Favorites" is on: a guest's request
    // would ignore it and list everything under a ticked box.
    let data: SearchResponse;
    try {
      data = await request(
        state.isUserLoggedIn && (state.query.favorites || !nonceExpired),
      );
    } catch (e) {
      if (!(e instanceof ApiError) || e.code !== INVALID_NONCE) throw e;
      nonceExpired = true;
      if (state.query.favorites) throw e;
      data = await request(false);
    }

    // Whether a card is in the visitor's favorites comes from the
    // favorites store, which FavoritesCore hydrates for logged-in users
    // and every toggle keeps current (the server skips that query for
    // REST, see ListingService::preloadPostData). Guests have none.
    const favoriteIds = favoritesStore().state.myFavoriteIds ?? [];
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
    if (!append && state.i18n.found) {
      state.announcement = `${state.i18n.found}: ${data.total}`;
    }
    if (data.facets) {
      cacheFacetLabels(state);
      // A guest's response (expired nonce) has no "Favorites" count: keep
      // the last one rather than show none.
      if (state.isUserLoggedIn && data.facets.favorites === undefined) {
        data.facets.favorites = state.facets.favorites;
      }
      state.facets = data.facets;
    }
    if (history !== "none") {
      syncStateToUrl(state.query, { replace: history === "replace" });
    }
  } catch (e) {
    if (isAbortError(e)) return;
    console.error("Listing Search Error:", e);
    // An expired nonce: Try again would only repeat it, the page must be
    // reloaded. Otherwise the server's own message when it sent one (rate
    // limit, validation) - translated on its side - or the generic one.
    const apiError = e instanceof ApiError ? e : null;
    state.error =
      (apiError?.code === INVALID_NONCE ? state.i18n.sessionExpired : "") ||
      Object.values(apiError?.fieldErrors ?? {})[0] ||
      apiError?.serverMessage ||
      state.i18n.loadError ||
      (e instanceof Error ? e.message : String(e));
    failedRequest = { append, history };
  } finally {
    state.isLoading = false;
  }
}

export const gridActions = {
  /**
   * Main entry point for updating results (Resetting to page 1).
   */
  async updateResults({
    history = "push",
  }: { history?: HistoryMode } = {}): Promise<void> {
    await fetchResults({ append: false, history });
  },

  /**
   * Load the next page of results. ?page=N in the URL is rewritten in place,
   * not added as a Back step, so a reload or a shared link renders the same
   * list (StateAggregator renders pages 1..N).
   */
  async loadMore(): Promise<void> {
    const { state } = listingStore();
    if (state.query.page >= state.totalPages) return;

    const shown = state.results.length;
    state.query.page++;
    await fetchResults({ append: true, history: "replace" });

    // Focus the first new card: keyboard and screen-reader users go on
    // reading where the new items start, and focus isn't left on the button,
    // which hides once the last page is in. Next frame: the cards render then.
    if (state.results.length > shown) {
      requestAnimationFrame(() => {
        const card = document.querySelectorAll(
          ".listing-grid article.listing-card",
        )[shown];
        const link = card?.querySelector(".listing-card__title--link");
        if (link instanceof HTMLElement) link.focus();
      });
    }
  },

  /**
   * "Try again" in the error alert: re-runs the request that failed. A
   * failed Load more already advanced the page, so it fetches the same page.
   */
  async retry(): Promise<void> {
    if (failedRequest) await fetchResults(failedRequest);
  },

  /**
   * Toggles the UI layout (Grid vs List).
   */
  setLayout(): void {
    const { state } = listingStore();
    const { ref } = getElement();
    if (
      (ref instanceof HTMLSelectElement || ref instanceof HTMLInputElement) &&
      ref.value
    ) {
      state.layout = ref.value;
    }
  },
};
