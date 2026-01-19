/**
 * Launchpad Store — Profile Actions
 *
 * All actions under the `actions.profile` namespace.
 * Uses Store Locator pattern: store("launchpad") to access state/actions.
 *
 * File: inc/launchpad/Assets/profile/actions.js
 */

import { getElement, store } from "@wordpress/interactivity";
import { ensurePanel, fetchJson } from "../utils.js";

/**
 * Profile actions - plain object using Store Locator pattern.
 */
export const profileActions = {
  /**
   * Enter edit mode for profile
   */
  startEdit() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
    p.isEditing = true;
    // Store original values for cancel
    p._original = {
      firstName: p.firstName,
      lastName: p.lastName,
      email: p.email,
      phone: p.phone,
      telegram: p.telegram,
    };
  },

  /**
   * Cancel edit mode, restore original values
   */
  cancelEdit() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
    if (p._original) Object.assign(p, p._original);
    p.isEditing = false;
  },

  /**
   * Update a field value from input element
   */
  updateField() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    if (ref.dataset.field) {
      ensurePanel(state, "profile")[ref.dataset.field] = ref.value;
    }
  },

  /**
   * Save profile changes to server
   */
  async save(event) {
    const { state } = store("launchpad");
    event.preventDefault();
    const p = ensurePanel(state, "profile");
    p.isSaving = true;

    try {
      const data = await fetchJson(
        state,
        `${state.launchpadSettings.restUrl}profile`,
        {
          method: "POST",
          body: {
            firstName: p.firstName,
            lastName: p.lastName,
            email: p.email,
            phone: p.phone,
            telegram: p.telegram,
          },
        }
      );
      if (data) Object.assign(p, data);
      p.isEditing = false;
    } catch (error) {
      p.error = error.message;
    } finally {
      p.isSaving = false;
    }
  },

  /**
   * Enter password change mode
   */
  startChangePassword() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
    p.isChangingPassword = true;
    p.error = null;
  },

  /**
   * Cancel password change mode
   */
  cancelPasswordChange() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
    p.isChangingPassword = false;
    p.passwordData = { current: "", new: "", confirm: "" };
  },

  /**
   * Update password form field
   */
  updatePasswordField() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    const p = ensurePanel(state, "profile");
    p.passwordData[ref.dataset.field] = ref.value;
  },

  /**
   * Submit password change to server
   */
  async submitPasswordChange(event) {
    const { state } = store("launchpad");
    event.preventDefault();
    const p = ensurePanel(state, "profile");

    if (p.passwordData.new !== p.passwordData.confirm) {
      p.error = "New passwords do not match.";
      return;
    }

    p.isSaving = true;
    try {
      await fetchJson(
        state,
        `${state.launchpadSettings.restUrl}security/password`,
        {
          method: "POST",
          body: {
            currentPassword: p.passwordData.current,
            newPassword: p.passwordData.new,
          },
        }
      );

      alert("Password changed successfully. Please log in again.");
      window.location.href = state.launchpadSettings.loginUrl;
    } catch (error) {
      p.error = error.message;
      p.isSaving = false;
    }
  },
};
