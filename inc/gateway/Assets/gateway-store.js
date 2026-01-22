/**
 * Gateway Interactivity API Store
 * File: inc/gateway/Assets/gateway-store.js
 */

import { store, getElement } from "@wordpress/interactivity";

import { loginActions } from "./auth/actions.js";
import { registerActions } from "./register/actions.js";
import {
  lostPasswordActions,
  resetPasswordActions,
} from "./recovery/actions.js";

const gatewayState = {
  activeView: "login",

  // Helper to convert kebab to camel 'lost-password' to 'lostPassword'
  // todo: check we still need id since have similar in backend now
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

    login: loginActions,

    lostPassword: lostPasswordActions,

    resetPassword: resetPasswordActions,

    register: registerActions,
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
