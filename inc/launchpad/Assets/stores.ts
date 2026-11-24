/**
 * Launchpad Store — Typed store accessors
 *
 * store(namespace) with no store part returns the store already registered
 * under that namespace; these give it its type, so no file calls an untyped
 * store("launchpad").
 *
 * File: inc/launchpad/Assets/stores.ts
 */
import { store } from "@wordpress/interactivity";
import type { LaunchpadStore } from "./types.ts";

// The other modules' stores the Launchpad uses.
export { favoritesStore, popupStore } from "../../shared/Assets/stores.ts";

export const launchpadStore = () => store<LaunchpadStore>("launchpad");

/** inc/tour/Assets/tour-store.js - the part the profile save uses. */
interface TourStore {
  actions: { refreshScenarios(): Promise<void> };
}

export const tourStore = () => store<TourStore>("tour");
