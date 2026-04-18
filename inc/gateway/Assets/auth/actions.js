/**
 * Gateway Store — Auth Actions (Login)
 * File: inc/gateway/Assets/auth/actions.js
 */
import { getElement, store } from "@wordpress/interactivity";
import {
  fetchJson,
  validators,
  RestApiError,
  WP_ALREADY_AUTHENTICATED,
  WP_NONCE_INVALID,
} from "../utils.js";

export const loginActions = {
  updateField() {
    const { state } = store("gateway");
    const { ref } = getElement();
    const field = ref?.dataset?.field;

    if (field && state.forms?.login) {
      state.forms.login[field] = ref.value;

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

  toggleVisibility() {
    const { state } = store("gateway");
    if (state.forms?.login) {
      state.forms.login.isPasswordVisible =
        !state.forms.login.isPasswordVisible;
    }
  },

  async submit(event) {
    event.preventDefault();

    const { state } = store("gateway");
    const form = state.forms?.login;

    if (!form || form.isSubmitting) return;

    // ── Client-side validation (UX guard only) ──────────────────────────────
    const strings = state.validationStrings ?? {};
    const errors = {};
    if (!validators.required(form.username))
      errors.username = strings.usernameRequired ?? "";
    if (!validators.required(form.password))
      errors.password = strings.passwordRequired ?? "";

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
        `${state.gatewaySettings.restUrl}login`,
        {
          method: "POST",
          body: {
            username: form.username,
            password: form.password,
            remember: form.rememberMe,
          },
        },
      );

      if (data.success && data.redirectTo) {
        window.location.href = data.redirectTo;
      }
    } catch (error) {
      // ── Race condition resolution ──────────────────────────────────────
      //
      // Primary path: fetchJson retried with fresh nonce, checkLoggedOut()
      // blocked it → already_authenticated. Another tab completed login.
      //
      // Safety net: if the retry itself failed (exhausted _isRetry), the code
      // is WP_NONCE_INVALID. Either way the session has changed under us —
      // reload so page-gateway.php::is_user_logged_in() redirects correctly.
      //
      if (
        error instanceof RestApiError &&
        (error.code === WP_ALREADY_AUTHENTICATED ||
          error.code === WP_NONCE_INVALID)
      ) {
        window.location.reload();
        return;
      }
      // ──────────────────────────────────────────────────────────────────

      // Route backend field_errors to inline slots (shared contract).
      const fieldErrors =
        (error instanceof RestApiError && error.fieldErrors) || null;
      if (fieldErrors && Object.keys(fieldErrors).length) {
        form.fieldErrors = { ...form.fieldErrors, ...fieldErrors };
      } else {
        form.error = error.message;
      }
    } finally {
      form.isSubmitting = false;
    }
  },
};
