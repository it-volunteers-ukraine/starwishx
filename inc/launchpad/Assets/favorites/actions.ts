/**
 * Favorites Actions for Main Launchpad Store
 *
 * This only handles pagination in the Favorites Panel.
 * The toggle action lives in inc/favorites/Assets/favorites-store.js.
 *
 * File: inc/launchpad/Assets/favorites/actions.ts
 */
import { isRecord } from "../../../shared/Assets/store-utils.ts";
import { favoritesStore, launchpadStore } from "../stores.ts";
import type { FavoriteItem } from "../types.ts";
import { apiErrorMessage, fetchJson, loadedPanel } from "../utils.ts";

/** The favorites panel's page size (FavoritesPanel). */
const PER_PAGE = 20;

/** GET favorites - FavoritesController (inc/favorites). */
interface FavoritesPage {
  items: FavoriteItem[];
  total: number;
  total_pages: number;
}

function isFavoritesPage(value: unknown): value is FavoritesPage {
  return (
    isRecord(value) &&
    Array.isArray(value.items) &&
    typeof value.total === "number" &&
    typeof value.total_pages === "number"
  );
}

export const favoritesActions = {
  /**
   * Load more favorites (pagination in Favorites panel)
   */
  async loadMore(): Promise<void> {
    const p = loadedPanel(launchpadStore().state, "favorites");
    const restUrl = favoritesStore().state.config?.restUrl;
    if (!p || p.isLoading || !restUrl) return;

    p.isLoading = true;
    try {
      const nextPage = p.page + 1;
      const data = await fetchJson(
        `${restUrl}favorites?page=${nextPage}&per_page=${PER_PAGE}`,
        isFavoritesPage,
      );
      if (data) {
        p.items = [...p.items, ...data.items];
        p.page = nextPage;
        p.total = data.total;
        p.totalPages = data.total_pages;
        p.hasMore = nextPage < data.total_pages;
      }
    } catch (error) {
      p.error = apiErrorMessage(error);
    } finally {
      p.isLoading = false;
    }
  },
};
