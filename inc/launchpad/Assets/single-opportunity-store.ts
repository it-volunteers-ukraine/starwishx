/**
 * Single Opportunity Frontend Store
 * Namespace: starwishx/opportunities
 *
 * Handles auth guard + delegates favorites to the independent favorites store.
 * Markup: template-parts/control-favorites.php.
 *
 * File: inc/launchpad/Assets/single-opportunity-store.ts
 */
import { getContext, store, withSyncEvent } from "@wordpress/interactivity";
import "../../shared/Assets/popup-store.js";
import "../../shared/Assets/collapsible-store.js";
import type { SyncAwareAction } from "../../shared/Assets/store-utils.ts";
import { favoritesStore, popupStore } from "../../shared/Assets/stores.ts";

/** LaunchpadCore::enqueueFrontendStore(). */
interface SingleOpportunityState {
  isUserLoggedIn: boolean;
  /** Only published posts can be favorited. */
  canFavorite: boolean;
  isFavorite: boolean;
}

/** data-wp-context on the control: the post id. */
interface ControlContext {
  id: number;
}

interface SingleOpportunityStore {
  state: SingleOpportunityState;
  actions: { toggle: SyncAwareAction };
}

const { state } = store<SingleOpportunityStore>("starwishx/opportunities", {
  // No state defaults here — isFavorite, isUserLoggedIn, canFavorite are
  // hydrated by the server via wp_interactivity_state(). Defining them
  // would overwrite the server values with empty defaults.

  actions: {
    /**
     * The heart's change. withSyncEvent: the guards cancel the event.
     */
    toggle: withSyncEvent(async (event?: Event): Promise<void> => {
      const { id } = getContext<Partial<ControlContext>>();
      if (!id) return;

      event?.preventDefault();

      // Published-only guard
      if (!state.canFavorite) return;

      // Guest guard: prevent checkbox visual flip, show auth popup
      if (!state.isUserLoggedIn) {
        popupStore().actions.open();
        return;
      }

      // Optimistic local update (both control instances read the same state)
      state.isFavorite = !state.isFavorite;

      // Sync with favorites store (API call + global myFavoriteIds)
      await favoritesStore().actions.toggle(id);

      // The favorites store puts its list back when saving fails: follow it,
      // or the heart would keep the unsaved change.
      const ids = favoritesStore().state.myFavoriteIds ?? [];
      state.isFavorite = ids.some((saved) => Number(saved) === id);
    }),
  },
});
