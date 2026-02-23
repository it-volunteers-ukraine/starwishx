/**
 * Gateway Store — Register Actions
 * File: inc/gateway/Assets/register/actions.js
 */
import { getElement, store } from "@wordpress/interactivity";
import {
  fetchJson,
  validators,
  RestApiError,
  WP_ALREADY_AUTHENTICATED,
  WP_NONCE_INVALID,
} from "../utils.js";

export const registerActions = {
  updateField() {
    const { state } = store("gateway");
    const { ref } = getElement();
    const field = ref?.dataset?.field;

    if (field && state.forms?.register) {
      state.forms.register[field] = ref.value;

      if (state.forms.register.fieldErrors) {
        state.forms.register.fieldErrors[field] = null;
      }
    }
  },

  async submit(event) {
    event.preventDefault();

    const { state } = store("gateway");
    const form = state.forms?.register;

    if (!form || form.isSubmitting) return;

    // ── Client-side validation (UX guard only) ──────────────────────────────
    const errors = {};

    if (!validators.required(form.username)) {
      errors.username = "Username is required";
    } else if (!validators.minLength(3)(form.username)) {
      errors.username = "Username must be at least 3 characters";
    }

    if (!validators.required(form.email)) {
      errors.email = "Email is required";
    } else if (!validators.email(form.email)) {
      errors.email = "Please enter a valid email address";
    }

    if (Object.keys(errors).length > 0) {
      form.fieldErrors = errors;
      return;
    }
    // ──────────────────────────────────────────────────────────────────────

    form.isSubmitting = true;
    form.error = null;

    try {
      const data = await fetchJson(
        state,
        `${state.gatewaySettings.restUrl}register`,
        {
          method: "POST",
          body: {
            username: form.username,
            email: form.email,
          },
        },
      );

      if (data.success) {
        form.success = true;
        form.username = "";
        form.email = "";
        form.fieldErrors = { username: null, email: null };
      }
    } catch (error) {
      // Same race condition as login — /register uses checkLoggedOut() too.
      if (
        error instanceof RestApiError &&
        (error.code === WP_ALREADY_AUTHENTICATED ||
          error.code === WP_NONCE_INVALID)
      ) {
        window.location.reload();
        return;
      }

      form.error = error.message;
    } finally {
      form.isSubmitting = false;
    }
  },
};
