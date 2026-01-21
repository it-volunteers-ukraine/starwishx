/**
 * Gateway Interactivity API Store
 * File: inc/gateway/Assets/gateway-store.js
 */

import { store, getElement } from "@wordpress/interactivity";

// Import modules
import { loginActions } from "./auth/actions.js";
import {
  lostPasswordActions, // Renamed from forgotPasswordActions
  resetPasswordActions,
} from "./recovery/actions.js";

// Define State
const gatewayState = {
  activeView: "login",

  // Helper to convert 'lost-password' to 'lostPassword'
  toCamel(str) {
    return str.replace(/([-_][a-z])/g, (group) =>
      group.toUpperCase().replace("-", "").replace("_", ""),
    );
  },

  get currentForm() {
    const key = this.toCamel(this.activeView);
    return this.forms?.[key] || {};
  },
};

// Store definition
const { state, actions } = store("gateway", {
  state: gatewayState,

  actions: {
    syncStateFromUrl() {
      const url = new URL(window.location);
      const view = url.searchParams.get("view") || "login";
      state.activeView = view;

      if (state.formMap) {
        Object.entries(state.formMap).forEach(([id, stateKey]) => {
          state[stateKey] = id === view;
        });
      }
    },

    switchView(event) {
      event.preventDefault();
      const { ref } = getElement();
      const url = new URL(ref.href, window.location);
      window.history.pushState({}, "", url);
      actions.syncStateFromUrl();
    },

    // Auth Modules
    login: loginActions,

    lostPassword: lostPasswordActions, // Name must match the data-wp-on attribute in PHP

    resetPassword: resetPasswordActions,
  },
});

// Init logic (popstate/DOMContentLoaded omitted for brevity)
window.addEventListener("popstate", () => actions.syncStateFromUrl());
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    actions.syncStateFromUrl(),
  );
} else {
  actions.syncStateFromUrl();
}
