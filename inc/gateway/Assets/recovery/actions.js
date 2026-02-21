/**
 * Gateway Store — Recovery Actions
 * File: inc/gateway/Assets/recovery/actions.js
 */

import { getElement, store } from "@wordpress/interactivity";
import { fetchJson, validators } from "../utils.js";

// Phase 1: Lost Password Actions (Requesting the link)
export const lostPasswordActions = {
  updateField() {
    const { state } = store("gateway");
    const { ref } = getElement();
    const field = ref?.dataset?.field;
    if (field && state.forms?.lostPassword) {
      state.forms.lostPassword[field] = ref.value;
    }
  },

  async submit(event) {
    event.preventDefault();
    const { state } = store("gateway");
    const form = state.forms?.lostPassword; // This now exists!
    if (!form || form.isSubmitting) return;

    form.isSubmitting = true;
    form.error = null;

    try {
      await fetchJson(state, `${state.gatewaySettings.restUrl}password/lost`, {
        method: "POST",
        body: { user_login: form.userLogin },
      });
      form.success = true;
    } catch (error) {
      form.error = error.message;
    } finally {
      form.isSubmitting = false;
    }
  },
};

// Phase 2: Reset Password Actions (Setting the new password)
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
        // Native UX: Show the password again if they generated a new one
        form.isPasswordVisible = true;
      }
    } catch (error) {
      form.error = error.message || "Failed to generate password.";
    } finally {
      form.isGenerating = false;
    }
  },

  async submit(event) {
    event.preventDefault();
    const { state } = store("gateway");
    const form = state.forms?.resetPassword;
    if (!form || form.isSubmitting) return;

    // Client validation
    const password = form.newPassword;
    if (!validators.minLength(12)(password)) {
      form.error = "Password must be at least 12 characters long";
      return;
    }

    // Client-side strength validation (server will validate too)
    const hasUpper = /[A-Z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[^A-Za-z0-9]/.test(password);

    if (!hasUpper || !hasNumber || !hasSpecial) {
      form.error = "Password must include uppercase, numbers, and symbols";
      return;
    }

    form.isSubmitting = true;
    form.error = null;

    try {
      const url = new URL(window.location);
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

        // Clear sensitive form fields
        form.newPassword = "";

        // Redirect after 2 seconds
        setTimeout(() => {
          window.location.href = state.gatewaySettings.baseUrl || "/gateway/";
        }, 2000);
      }
    } catch (error) {
      form.error = error.message || "Password reset failed. Please try again.";
    } finally {
      form.isSubmitting = false;
    }
  },
};
