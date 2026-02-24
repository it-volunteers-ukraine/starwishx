/**
 * Gateway Interactivity API Store
 * File: inc/gateway/Assets/gateway-store.js
 */
import { store, getElement } from "@wordpress/interactivity";
import { toCamelCase } from "./utils.js";

import { loginActions } from "./auth/actions.js";
import { registerActions } from "./register/actions.js";
import {
  lostPasswordActions,
  resetPasswordActions,
} from "./recovery/actions.js";

const gatewayState = {
  activeView: "login",

  get currentForm() {
    return this.forms?.[toCamelCase(this.activeView)] || {};
  },

  get resetPasswordInputType() {
    return this.forms?.resetPassword?.isPasswordVisible ? "text" : "password";
  },
  
  get loginPasswordInputType() {
    return this.forms?.login?.isPasswordVisible ? "text" : "password";
  },
};

const { state, actions } = store("gateway", {
  state: gatewayState,

  actions: {
    syncStateFromUrl() {
      const url = new URL(window.location.href);
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
     * Called when navigating away from a view so stale data never bleeds through.
     *
     * @param {string} formKey - camelCase form key e.g. 'lostPassword'
     */
    resetFormState(formKey) {
      const form = state.forms?.[formKey];
      if (!form) return;

      Object.keys(form).forEach((key) => {
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
      });
    },

    switchView(event) {
      event.preventDefault();

      const { ref } = getElement();
      const url = new URL(ref.href, window.location.href);

      // Reset current form before leaving so stale errors/values don't persist
      const currentFormKey = toCamelCase(state.activeView);
      if (state.forms?.[currentFormKey]) {
        actions.resetFormState(currentFormKey);
      }

      window.history.pushState({}, "", url);
      actions.syncStateFromUrl();
    },

    login: loginActions,
    register: registerActions,
    lostPassword: lostPasswordActions,
    resetPassword: resetPasswordActions,
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
