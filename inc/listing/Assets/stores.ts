/**
 * Listing Store — Typed store accessors
 *
 * store(namespace) with no store part returns the store already registered
 * under that namespace; these give it its type, so no file calls an untyped
 * store("listing").
 *
 * File: inc/listing/Assets/stores.ts
 */
import { store } from "@wordpress/interactivity";
import type { ListingSettingsStore, ListingStore } from "./types.ts";

// The other modules' stores the listing uses.
export { favoritesStore, popupStore } from "../../shared/Assets/stores.ts";

export const listingStore = () => store<ListingStore>("listing");

export const settingsStore = () =>
  store<ListingSettingsStore>("listingSettings");
