/**
 * Favorites Actions for Main Launchpad Store
 *
 * This only handles pagination in the Favorites Panel.
 * The toggle action lives in favorites-store.js
 *
 * File: inc/launchpad/Assets/favorites/actions.js
 */
import { store } from "@wordpress/interactivity";
import { ensurePanel, fetchJson } from "../utils.js";
export const favoritesActions = {
  /**
   * Load more favorites (pagination in Favorites panel)
   */
  async loadMore() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "favorites");

    if (p.isLoading) return;

    p.isLoading = true;
    try {
      const nextPage = (p.page || 1) + 1;
      const data = await fetchJson(
        state,
        `${state.launchpadSettings.restUrl}favorites?page=${nextPage}&per_page=20`,
      );
      if (data?.items) {
        p.items = [...(p.items || []), ...data.items];
        p.page = nextPage;
        p.total = data.total;
        p.totalPages = data.total_pages;
        p.hasMore = nextPage < data.total_pages;
      }
    } catch (error) {
      p.error = error.message;
    } finally {
      p.isLoading = false;
    }
  },
};
