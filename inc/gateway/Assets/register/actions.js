/**
 * Gateway Store — Register Actions
 * Updated for email-first registration flow.
 *
 * File: inc/gateway/Assets/register/actions.js
 */
import { getElement, store } from "@wordpress/interactivity";
import { fetchJson, validators } from "../utils.js";
export const registerActions = {
  /**
   * Update form field value in state.
   */
  updateField() {
    const { state } = store("gateway");
    const { ref } = getElement();
    const field = ref?.dataset?.field;

    if (field && state.forms?.register) {
      state.forms.register[field] = ref.value;

      // Clear field error when user starts typing
      if (state.forms.register.fieldErrors) {
        state.forms.register.fieldErrors[field] = null;
      }
    }
  },
  /**
   * Handle registration form submission.
   * Only sends username and email (no password).
   */
  async submit(event) {
    event.preventDefault();
    const { state } = store("gateway");
    const form = state.forms?.register;

    if (!form || form.isSubmitting) return;
    // Client-side validation
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
    // Submit registration
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
        // Show success message
        form.success = true;

        // Clear form fields
        form.username = "";
        form.email = "";
        form.fieldErrors = { username: null, email: null };
      }
    } catch (error) {
      form.error = error.message || "Registration failed. Please try again.";
    } finally {
      form.isSubmitting = false;
    }
  },
};
