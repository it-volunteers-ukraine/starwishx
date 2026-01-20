/**
 * Launchpad Store — Favorites Actions
 *
 * All actions under the `actions.favorites` namespace.
 * Uses Store Locator pattern: store("launchpad") to access state.
 *
 * File: inc/launchpad/Assets/store/favorites/actions.js
 */

import { getElement, store } from "@wordpress/interactivity";
import { ensurePanel, fetchJson } from "../utils.js";

/**
 * Favorites actions - plain object.
 * We explicitly locate the store to avoid 'this' context issues.
 */
export const favoritesActions = {
  /**
   * Remove item from favorites
   */
  async remove() {
    const { state } = store("launchpad");
    const postId = getElement().ref?.dataset?.postId;
    const p = ensurePanel(state, "favorites");
    try {
      await fetchJson(
        state,
        `${state.launchpadSettings.restUrl}favorites/${postId}`,
        { method: "DELETE" }
      );
      p.items = (p.items || []).filter((item) => item.id != postId);
    } catch (error) {
      p.error = error.message;
    }
  },
};
