/**
 * Single Opportunity Frontend Store
 * Namespace: starwishx/opportunities
 *
 * Handles auth guard + delegates favorites to the independent favorites store.
 */
import { store, getContext } from "@wordpress/interactivity";
import "../../shared/Assets/popup-store.js";

const { state } = store("starwishx/opportunities", {
  state: {
    get isFavorite() {
      const context = getContext();
      const id = context?.id;
      if (!id) return false;

      // Delegate to the independent favorites store
      const favState = store("favorites").state;
      if (!favState || !Array.isArray(favState.myFavoriteIds)) return false;

      return Array.from(favState.myFavoriteIds)
        .map(Number)
        .includes(Number(id));
    },
  },

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

      // Delegate to the independent favorites store
      await store("favorites").actions.toggle(id);
    },
  },
});
