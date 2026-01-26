/**
 * Launchpad Dashboard Favorites Getters
 * File: inc/launchpad/Assets/favorites/getters.js
 */
import { store, getContext } from "@wordpress/interactivity";

export const favoritesPanelGetters = {
  get isCurrentItemFavorite() {
    const context = getContext();
    const id = context?.item?.id;

    if (!id) return false;

    // 1. Access the separate store state
    const favState = store("launchpad/favorites").state;

    // 2. Safety Check
    if (!favState || !favState.myFavoriteIds) return false;

    // 3. ROBUST CHECK:
    // - Array.from() unwraps the Proxy so we get a clean array
    // - .map(Number) ensures we verify integers (fixes string/int mismatch)
    // - .includes(Number(id)) ensures the target is also an integer
    const ids = Array.from(favState.myFavoriteIds).map(Number);

    // Uncomment this line if you still have issues to see exactly what is happening
    // console.log(`[FavCheck] Item: ${id}`, ids, ids.includes(Number(id)));

    return ids.includes(Number(id));
  },
  
  /**
   * Safe check to hide the empty state.
   * 'this' refers to the global launchpad state.
   */
  get shouldHideFavoritesEmpty() {
    // 1. Safety Check: Does the panel exist?
    const panel = this.panels?.favorites;
    if (!panel) return false;

    // 2. Logic: Hide if loading OR if items exist
    const hasItems = Array.isArray(panel.items) && panel.items.length > 0;
    return hasItems || panel.isLoading;
  },
};
