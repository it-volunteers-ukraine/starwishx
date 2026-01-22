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
    // Check password match
    if (password !== form.confirmPassword) {
      form.error = "Passwords do not match";
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

        // Clear form fields
        form.newPassword = "";
        form.confirmPassword = "";

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
