/**
 * Gateway Store — Recovery Actions
 * File: inc/gateway/Assets/recovery/actions.js
 */
import { getElement, store } from "@wordpress/interactivity";
import { fetchJson, validators, RestApiError } from "../utils.js";

// ─────────────────────────────────────────────────────────────────────────────
// Phase 1: Lost Password — request reset link
// ─────────────────────────────────────────────────────────────────────────────

export const lostPasswordActions = {
  /**
   * Update field and clear its error (consistent with other forms).
   */
  updateField() {
    const { state } = store("gateway");
    const { ref } = getElement();
    const field = ref?.dataset?.field;

    if (field && state.forms?.lostPassword) {
      state.forms.lostPassword[field] = ref.value;

      if (state.forms.lostPassword.fieldErrors) {
        state.forms.lostPassword.fieldErrors[field] = null;
      }
    }
  },

  async submit(event) {
    event.preventDefault();

    const { state } = store("gateway");
    const form = state.forms?.lostPassword;

    if (!form || form.isSubmitting) return;

    // ── Client-side validation ──────────────────────────────────────────────
    if (!validators.required(form.userLogin)) {
      form.fieldErrors = { userLogin: "Please enter your username or email" };
      return;
    }
    // ──────────────────────────────────────────────────────────────────────

    form.isSubmitting = true;
    form.error = null;

    try {
      await fetchJson(state, `${state.gatewaySettings.restUrl}password/lost`, {
        method: "POST",
        body: { user_login: form.userLogin },
      });

      // Always show success regardless of whether the email exists —
      // prevents user enumeration via timing or response differences.
      form.success = true;
    } catch (error) {
      form.error = error.message;
    } finally {
      form.isSubmitting = false;
    }
  },
};

// ─────────────────────────────────────────────────────────────────────────────
// Phase 2: Reset Password — set the new password using key + login from URL
// ─────────────────────────────────────────────────────────────────────────────

export const resetPasswordActions = {
  updateField() {
    const { state } = store("gateway");
    const { ref } = getElement();
    const field = ref?.dataset?.field;

    if (field && state.forms?.resetPassword) {
      state.forms.resetPassword[field] = ref.value;
    }
  },

  toggleVisibility() {
    const { state } = store("gateway");

    if (state.forms?.resetPassword) {
      state.forms.resetPassword.isPasswordVisible =
        !state.forms.resetPassword.isPasswordVisible;
    }
  },

  /**
   * Fetch a generated strong password suggestion from the server.
   */
  async generate() {
    const { state } = store("gateway");
    const form = state.forms?.resetPassword;

    if (!form || form.isGenerating) return;

    form.isGenerating = true;
    form.error = null;

    try {
      const data = await fetchJson(
        state,
        `${state.gatewaySettings.restUrl}password/generate`,
      );

      if (data.success && data.password) {
        form.newPassword = data.password;
        form.isPasswordVisible = true; // show what was generated
      }
    } catch (error) {
      form.error = error.message;
    } finally {
      form.isGenerating = false;
    }
  },

  async submit(event) {
    event.preventDefault();

    const { state } = store("gateway");
    const form = state.forms?.resetPassword;

    if (!form || form.isSubmitting) return;

    // ── Client-side validation (UX guard only — server re-validates) ────────
    const password = form.newPassword;
    const hasLength = validators.minLength(12)(password);
    const hasUpper = /[A-Z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[^A-Za-z0-9]/.test(password);

    if (!hasLength) {
      form.error = "Password must be at least 12 characters long";
      return;
    }
    if (!hasUpper || !hasNumber || !hasSpecial) {
      form.error =
        "Password must include uppercase letters, numbers, and symbols";
      return;
    }
    // ──────────────────────────────────────────────────────────────────────

    form.isSubmitting = true;
    form.error = null;

    try {
      const url = new URL(window.location.href);
      const data = await fetchJson(
        state,
        `${state.gatewaySettings.restUrl}password/reset`,
        {
          method: "POST",
          body: {
            login: url.searchParams.get("login"),
            key: url.searchParams.get("key"),
            password: form.newPassword,
          },
        },
      );

      if (data.success) {
        form.success = true;
        form.successMessage =
          data.message ||
          "Password reset successfully! Redirecting to login...";
        form.newPassword = ""; // clear sensitive field immediately

        // baseUrl is always set via wp_interactivity_state() in GatewayCore.
        // No fallback — if it's undefined that failure should be loud.
        setTimeout(() => {
          window.location.href = state.gatewaySettings.baseUrl;
        }, 2000);
      }
    } catch (error) {
      form.error = error.message;
    } finally {
      form.isSubmitting = false;
    }
  },
};
