/**
 * Gateway Interactivity API Store
 * Set of user auth forms
 *
 * File: inc/gateway/Assets/gateway-store.js
 */

import { store, getElement } from "@wordpress/interactivity";

// Import modules
import { loginActions } from "./auth/actions.js";
import {
  forgotPasswordActions,
  resetPasswordActions,
} from "./recovery/actions.js";

// Define State
const gatewayState = {
  activeView: "login",

  // Getter uses 'this' to refer to state proxy
  get currentForm() {
    return this.forms?.[this.activeView] || {};
  },
};

// Store definition
const { state, actions } = store("gateway", {
  state: gatewayState,

  actions: {
    /**
     * Sync state from URL on load/popstate.
     */
    syncStateFromUrl() {
      const url = new URL(window.location);
      const view = url.searchParams.get("view") || "login";
      state.activeView = view;

      // Use the formMap from PHP to toggle boolean flags
      if (state.formMap) {
        Object.entries(state.formMap).forEach(([id, stateKey]) => {
          state[stateKey] = id === view;
        });
      }
    },

    /**
     * Switch view via link click.
     */
    switchView(event) {
      event.preventDefault();
      const { ref } = getElement();
      const url = new URL(ref.href, window.location);
      window.history.pushState({}, "", url);
      actions.syncStateFromUrl();
    },

    // Auth Modules (Plain objects)

    login: loginActions,

    forgotPassword: forgotPasswordActions,

    resetPassword: resetPasswordActions,
  },
});

// Init
window.addEventListener("popstate", () => actions.syncStateFromUrl());
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    actions.syncStateFromUrl()
  );
} else {
  actions.syncStateFromUrl();
}
