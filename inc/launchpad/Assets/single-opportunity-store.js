/**
 * Single Opportunity Frontend Store
 * Namespace: starwishx/opportunities
 *
 * Handles auth guard + delegates favorites to the independent favorites store.
 */
import { store, getContext } from "@wordpress/interactivity";
import "../../shared/Assets/popup-store.js";

const { state } = store("starwishx/opportunities", {
  // No state defaults here — isFavorite, isUserLoggedIn, canFavorite are
  // hydrated by the server via wp_interactivity_state(). Defining them
  // would overwrite the server values with empty defaults.

  actions: {
    async toggle(event) {
      const context = getContext();
      const id = context?.id;
      if (!id) return;

      // Published-only guard
      if (!state.canFavorite) {
        if (event) event.preventDefault();
        return;
      }

      // Guest guard: prevent checkbox visual flip, show auth popup
      if (!state.isUserLoggedIn) {
        if (event) event.preventDefault();
        store("popup").actions.open();
        return;
      }

      if (event) event.preventDefault();

      // Optimistic local update (both control instances read the same state)
      state.isFavorite = !state.isFavorite;

      // Sync with favorites store (API call + global myFavoriteIds)
      await store("favorites").actions.toggle(id);
    },
  },
});
