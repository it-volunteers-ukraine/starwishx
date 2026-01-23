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

    /**
     * Reset a form to its initial state.
     * Called when navigating away from a form view.
     *
     * @param {string} formKey - Camel-cased form key (e.g., 'lostPassword', 'register')
     */
    resetFormState(formKey) {
      const form = state.forms?.[formKey];
      if (!form) return;

      Object.keys(form).forEach((key) => {
        // We don't want to reset 'fieldErrors' structure, just the values
        if (key === "fieldErrors" && typeof form[key] === "object") {
          Object.keys(form[key]).forEach((errorKey) => {
            form[key][errorKey] = null;
          });
          return;
        }

        const value = form[key];
        if (typeof value === "string") form[key] = "";
        if (typeof value === "boolean") form[key] = false;
        if (typeof value === "number") form[key] = 0;
        // successMessage or error are usually strings, so they are caught above
      });
    },

    switchView(event) {
      event.preventDefault();
      const { ref } = getElement();
      const url = new URL(ref.href, window.location);

      // Reset the current form before switching views
      const currentView = state.activeView;
      const currentFormKey = state.toCamel(currentView);
      if (state.forms?.[currentFormKey]) {
        actions.resetFormState(currentFormKey);
      }

      // Navigate to new view
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
