/**
 * Shared Store Accessors (TypeScript)
 *
 * Typed handles on the stores other modules own, for the parts the
 * TypeScript stores use. store(namespace) with no store part returns the
 * store already registered under that namespace; the accessor gives it its
 * type. Grow an interface when a caller needs more of its store.
 *
 * File: inc/shared/Assets/stores.ts
 */
import { store } from "@wordpress/interactivity";

/** inc/favorites/Assets/favorites-store.js, hydrated by FavoritesCore. */
export interface FavoritesStore {
  // Guests don't load the favorites module, so for them store("favorites")
  // is an empty store: everything optional.
  state: {
    myFavoriteIds?: number[];
    config?: { nonce?: string; restUrl?: string };
  };
  actions: {
    toggle(id: number): Promise<void>;
  };
}

/** The shared login popup (inc/shared/Assets/popup-store.js). */
export interface PopupStore {
  state: { isOpen: boolean };
  actions: {
    open(): void;
  };
}

export const favoritesStore = () => store<FavoritesStore>("favorites");

export const popupStore = () => store<PopupStore>("popup");
