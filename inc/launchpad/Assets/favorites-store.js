/**
 * Launchpad Favorites Domain Store
 * Namespace: launchpad/favorites
 *
 * Lightweight store for managing user favorites.
 * Can be loaded on Frontend (single/archive) AND Backend (dashboard).
 *
 * File: inc/launchpad/Assets/favorites-store.js
 */
import { store, getContext, getElement } from "@wordpress/interactivity";

// Helper to resolve ID
const resolveId = () => {
  const context = getContext();
  // 1. Try Context
  if (context?.item?.id) return Number(context.item.id);

  // 2. Try Data Attribute (Fallback)
  const { ref } = getElement();
  if (ref?.dataset?.id) return Number(ref.dataset.id);

  return null;
};

const { state } = store("launchpad/favorites", {
  state: {
    myFavoriteIds: [],

    config: {},

    /**
     * Check if current context item is favorited
     */
    get isFavorite() {
      const id = resolveId();

      // DEBUG: Uncomment to see exactly what JS sees
      // console.log('Checking ID:', id, 'In List:', JSON.parse(JSON.stringify(this.myFavoriteIds)));

      if (!id) return false;

      // FIX: Use 'this' instead of 'state'
      // 'this' refers to the reactive state proxy containing the hydrated data
      const list = this.myFavoriteIds;

      if (!Array.isArray(list)) return false;

      // Robust check: Unwrap proxy and force numbers
      return Array.from(list).map(Number).includes(Number(id));
    },

    get count() {
      // FIX: Use 'this'
      return Array.isArray(this.myFavoriteIds) ? this.myFavoriteIds.length : 0;
    },
  },

  actions: {
    async toggle(eventOrId) {
      let id = null;
      let event = null;

      if (typeof eventOrId === "number" || typeof eventOrId === "string") {
        // Called programmatically with ID
        id = Number(eventOrId);
      } else {
        // Called via Event handler
        event = eventOrId;
        if (event && event.preventDefault) event.preventDefault();
        id = resolveId(); // Uses getContext() or element ref
      }

      if (!id) return;

      // Access state via 'state' variable is fine in actions (async),
      // but for consistency/safety we can use store lookup or 'state' closure.
      // The getter fix above is the critical part for the initial render.

      const isFav = state.myFavoriteIds.includes(id); // Closure 'state' is safe in async actions

      // 1. Optimistic Update
      if (isFav) {
        state.myFavoriteIds = state.myFavoriteIds.filter(
          (favId) => Number(favId) !== Number(id),
        );
      } else {
        state.myFavoriteIds = [...state.myFavoriteIds, Number(id)];
      }

      // 2. Background API call
      try {
        // Robust Config Lookup
        // 1. Try hydrated state
        // 2. Try global fallback (footer script)
        let config = state.config;
        if (!config || !config.nonce) {
          config = window.launchpadGlobal || window.launchpadSettings || {};
        }

        if (!config.nonce) {
          console.error(
            "Favorites: API Config missing. State:",
            JSON.parse(JSON.stringify(state)),
          );
          throw new Error("Missing REST API configuration");
        }

        const response = await fetch(
          `${config.restUrl}favorites/toggle/${id}`,
          {
            method: "POST",
            headers: {
              "X-WP-Nonce": config.nonce,
              "Content-Type": "application/json",
            },
          },
        );

        if (!response.ok) throw new Error(`HTTP ${response.status}`);
      } catch (error) {
        console.error("Favorite toggle failed:", error);
        // Revert
        if (isFav) state.myFavoriteIds = [...state.myFavoriteIds, Number(id)];
        else
          state.myFavoriteIds = state.myFavoriteIds.filter(
            (favId) => Number(favId) !== Number(id),
          );
      }
    },
  },
});
