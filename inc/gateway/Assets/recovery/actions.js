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
    if (!validators.minLength(8)(form.newPassword)) {
      form.error = "Password must be at least 8 characters";
      return;
    }
    if (form.newPassword !== form.confirmPassword) {
      form.error = "Passwords do not match";
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
          "Password reset successfully! Redirecting to login...";
        setTimeout(() => {
          // Switch view back to login
          window.location.href = state.gatewaySettings.baseUrl || "/gateway/";
        }, 2000);
      }
    } catch (error) {
      form.error = error.message;
    } finally {
      form.isSubmitting = false;
    }
  },
};
