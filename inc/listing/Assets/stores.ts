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
import type {
  FavoritesStore,
  ListingSettingsStore,
  ListingStore,
  PopupStore,
} from "./types.ts";

export const listingStore = () => store<ListingStore>("listing");

export const settingsStore = () =>
  store<ListingSettingsStore>("listingSettings");

export const favoritesStore = () => store<FavoritesStore>("favorites");

export const popupStore = () => store<PopupStore>("popup");
