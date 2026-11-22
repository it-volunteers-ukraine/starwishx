/**
 * Launchpad Interactivity API Store
 *
 * State is hydrated by PHP: launchpadSettings (LaunchpadCore::enqueueAssets)
 * and the panels, their flags and the active panel (StateAggregator, from
 * templates/page-launchpad.php). None of those keys has a client default -
 * store() merges client state over the server's, so a default would replace
 * it (`activePanel: "opportunities"` used to override the server's fallback
 * for an unknown ?panel). The client adds only the getters.
 *
 * File: inc/launchpad/Assets/launchpad-store.ts
 */

import { store } from "@wordpress/interactivity";
import { extendState } from "../../shared/Assets/store-utils.ts";
import { coreActions } from "./core/actions.ts";
import { favoritesActions } from "./favorites/actions.ts";
import { favoritesPanelGetters } from "./favorites/getters.ts";
import { opportunitiesActions } from "./opportunities/actions.ts";
import { opportunitiesCallbacks } from "./opportunities/callbacks.ts";
import { opportunitiesGetters } from "./opportunities/getters.ts";
import { profileActions } from "./profile/actions.ts";
import { profileGetters } from "./profile/getters.ts";
import type { LaunchpadActions, LaunchpadStore } from "./types.ts";

/**
 * The domain getters, copied as accessors (not evaluated): their `this` is
 * the state proxy.
 */
const getters = extendState(
  {},
  opportunitiesGetters,
  profileGetters,
  favoritesPanelGetters,
);

const { actions } = store<LaunchpadStore>("launchpad", {
  state: getters,

  actions: {
    ...coreActions,
    // Domains: plain objects; their methods reach the store through
    // launchpadStore(), so `this` doesn't matter.
    opportunities: opportunitiesActions,
    profile: profileActions,
    favorites: favoritesActions,
  } satisfies LaunchpadActions,

  // Side effects for data-wp-watch / data-wp-init, by domain.
  callbacks: {
    opportunities: opportunitiesCallbacks,
  },
});

window.addEventListener("popstate", () => actions.syncStateFromUrl());
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    actions.syncStateFromUrl(),
  );
} else {
  actions.syncStateFromUrl();
}
