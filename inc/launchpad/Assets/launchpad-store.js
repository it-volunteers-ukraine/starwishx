/**
 * Launchpad Interactivity API Store
 *
 * File: inc/launchpad/Assets/launchpad-store.js
 *
 */

import { store } from "@wordpress/interactivity";

// Import local utilities
import { extendState } from "./utils.js";

// Core Modules
import { coreGetters } from "./core/getters.js";
import { coreActions } from "./core/actions.js";

// Domain modules - plain objects
import { opportunitiesActions } from "./opportunities/actions.js";
import { opportunitiesGetters } from "./opportunities/getters.js";
import { favoritesActions } from "./favorites/actions.js";
import { securityActions } from "./security/actions.js";
import { profileActions } from "./profile/actions.js";
import { profileGetters } from "./profile/getters.js";

/**
 * Define Base State
 * Define the static properties and local getters here.
 */
const launchpadState = {
  activePanel: "opportunities",
  panels: {},
};

/**
 * Extend State with Domain Getters
 * This copies the 'getter logic' without executing it.
 * 'this' inside opportunitiesGetters refer to launchpadState
 */
extendState(launchpadState, opportunitiesGetters, profileGetters, coreGetters);

/**
 * STORE DEFINITION
 */
const { actions } = store("launchpad", {
  state: launchpadState,

  actions: {
    ...coreActions,

    // Opportunities domain (Factory Pattern)
    // Just assign the plain object.
    // The Store Locator pattern inside the methods handles the context.
    opportunities: opportunitiesActions,

    // Profile domain
    profile: profileActions,

    // Favorites domain
    favorites: favoritesActions,

    // Security domain
    security: securityActions,
  },
});

window.addEventListener("popstate", () => actions.syncStateFromUrl());
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    actions.syncStateFromUrl()
  );
} else {
  actions.syncStateFromUrl();
}
