/**
 * Launchpad Dashboard Favorites Getters
 *
 * 'this' is the launchpad state proxy (ThisType below).
 *
 * File: inc/launchpad/Assets/favorites/getters.ts
 */
import { getContext } from "@wordpress/interactivity";
import { favoritesStore } from "../stores.ts";
import type {
  FavoriteItem,
  FavoritesGetters,
  ItemContext,
  LaunchpadState,
} from "../types.ts";
import { loadedPanel } from "../utils.ts";

export const favoritesPanelGetters = {
  /** Is the card in context among the user's favorites (favorites store)? */
  get isCurrentItemFavorite(): boolean {
    const { item } = getContext<Partial<ItemContext<FavoriteItem>>>();
    const id = Number(item?.id);
    if (!id) return false;
    // Number(): the ids may arrive as strings.
    const ids = favoritesStore().state.myFavoriteIds ?? [];
    return ids.some((favId) => Number(favId) === id);
  },

  /** Hide the empty state while loading or once there are items. */
  get shouldHideFavoritesEmpty(): boolean {
    const panel = this.panels.favorites;
    if (!panel) return false;
    if (panel.isLoading) return true;
    return !!loadedPanel(this, "favorites")?.items.length;
  },
} satisfies FavoritesGetters & ThisType<LaunchpadState>;
