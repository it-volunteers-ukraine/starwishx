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
    const { actions } = store("launchpad");
    const url = new URL(window.location);
    url.searchParams.set("view", "profile");
    window.history.pushState({}, "", url);
    actions.syncStateFromUrl();
  },

  /**
   * Cancel edit mode, restore original values
   */
  cancelEdit() {
    const { state, actions } = store("launchpad");
    const p = ensurePanel(state, "profile");
    if (p._original) Object.assign(p, p._original);
    const url = new URL(window.location);
    url.searchParams.delete("view");
    window.history.pushState({}, "", url);
    actions.syncStateFromUrl();
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
    const { state, actions } = store("launchpad");
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
            organization: p.organization,
          },
        },
      );
      if (data) {
        Object.assign(p, data);
        // If profile was completed (role upgraded), invalidate opportunities panel
        // so it re-fetches on next visit and clears the onboarding lock screen
        if (data._roleUpgraded) {
          const opp = ensurePanel(state, "opportunities");
          opp._loaded = false;
          opp.isLocked = false;
        }
        if (data._roleDegraded) {
          const opp = ensurePanel(state, "opportunities");
          opp._loaded = false;
          opp.isLocked = true;
        }
      }
      // Return to card view via URL
      const url = new URL(window.location);
      url.searchParams.delete("view");
      window.history.replaceState({}, "", url);
      actions.syncStateFromUrl();
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
    const { state, actions } = store("launchpad");
    ensurePanel(state, "profile").error = null;
    const url = new URL(window.location);
    url.searchParams.set("view", "password");
    window.history.pushState({}, "", url);
    actions.syncStateFromUrl();
  },

  /**
   * Cancel password change mode
   */
  cancelPasswordChange() {
    const { state, actions } = store("launchpad");
    const p = ensurePanel(state, "profile");
    p.passwordData = { current: "", new: "", confirm: "" };
    const url = new URL(window.location);
    url.searchParams.delete("view");
    window.history.pushState({}, "", url);
    actions.syncStateFromUrl();
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
        },
      );

      alert("Password changed successfully. Please log in again.");
      window.location.href = state.launchpadSettings.loginUrl;
    } catch (error) {
      p.error = error.message;
      p.isSaving = false;
    }
  },
};
