/**
 * Gateway Store — Auth Actions (Login)
 * File: inc/gateway/Assets/auth/actions.js
 */

import { getElement, store } from "@wordpress/interactivity";
import { fetchJson, validators } from "../utils.js";

export const loginActions = {
  updateField() {
    const { state } = store("gateway");
    const { ref } = getElement();
    const field = ref?.dataset?.field;
    if (field && state.forms?.login) {
      state.forms.login[field] = ref.value;
      // Clear field error on edit
      if (state.forms.login.fieldErrors) {
        state.forms.login.fieldErrors[field] = null;
      }
    }
  },

  toggleRemember() {
    const { state } = store("gateway");
    const { ref } = getElement();
    if (state.forms?.login) {
      state.forms.login.rememberMe = ref.checked;
    }
  },

  async submit(event) {
    event.preventDefault();
    const { state } = store("gateway");
    const form = state.forms?.login;
    if (!form || form.isSubmitting) return;

    // Client validation
    const errors = {};
    if (!validators.required(form.username)) {
      errors.username = "Username is required";
    }
    if (!validators.required(form.password)) {
      errors.password = "Password is required";
    }
    if (Object.keys(errors).length > 0) {
      form.fieldErrors = errors;
      return;
    }

    form.isSubmitting = true;
    form.error = null;

    try {
      const data = await fetchJson(
        state,
        `${state.gatewaySettings.restUrl}login`,
        {
          method: "POST",
          body: {
            username: form.username,
            password: form.password,
            remember: form.rememberMe,
          },
        }
      );

      if (data.success && data.redirectTo) {
        window.location.href = data.redirectTo;
      }
    } catch (error) {
      form.error = error.message || "Login failed. Please try again.";
    } finally {
      form.isSubmitting = false;
    }
  },
};
