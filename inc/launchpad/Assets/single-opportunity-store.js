/**
 * Single Opportunity Frontend Store
 * Namespace: starwishx/opportunities
 */
import { store, getContext } from "@wordpress/interactivity";
import "../../shared/Assets/popup-store.js";

const { state } = store("starwishx/opportunities", {
  state: {
    // Map of ID -> Boolean
    // Example: { 624: true }
    statusMap: {},
    config: {},

    get isFavorite() {
      const context = getContext();
      // Standard Int Api context access
      const id = context?.id;
      if (!id) return false;

      return !!state.statusMap[id];
    },
  },

  actions: {
    async toggle(event) {
      const context = getContext();
      const id = context?.id;
      if (!id) return;

      // Guest guard: prevent checkbox visual flip, show auth popup
      if (!state.isUserLoggedIn) {
        if (event) event.preventDefault();
        store("popup").actions.open();
        return;
      }

      if (event) event.preventDefault();

      // 1. Optimistic Update (Boolean flip)
      const wasFav = !!state.statusMap[id];
      state.statusMap[id] = !wasFav;

      // 2. API Request
      try {
        const { nonce, restUrl } = state.config;

        await fetch(`${restUrl}favorites/toggle/${id}`, {
          method: "POST",
          headers: { "X-WP-Nonce": nonce, "Content-Type": "application/json" },
        });
      } catch (e) {
        console.error("Toggle error", e);
        // Revert
        state.statusMap[id] = wasFav;
      }
    },
  },
});
