/**
 * Launchpad Store — Security Actions
 *
 * All actions under the `actions.security` namespace.
 * Uses Store Locator pattern: store("launchpad") to access state.
 *
 * File: inc/launchpad/Assets/store/security/actions.js
 */

import { getElement, store } from "@wordpress/interactivity";
import { ensurePanel, fetchJson } from "../utils.js";

/**
 * Security actions - plain object.
 * We explicitly locate the store to avoid 'this' context issues.
 */
export const securityActions = {
  /**
   * Update security form field
   */
  updateField() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    if (ref.dataset.field) {
      ensurePanel(state, "security")[ref.dataset.field] = ref.value;
    }
  },

  /**
   * Change password action
   */
  async changePassword(event) {
    const { state } = store("launchpad");
    event.preventDefault();
    const p = ensurePanel(state, "security");
    if (p.newPassword !== p.confirmPassword) {
      p.error = "Passwords do not match.";
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
            currentPassword: p.currentPassword,
            newPassword: p.newPassword,
          },
        }
      );
      p.passwordChanged = true;
      setTimeout(() => {
        window.location.href = state.launchpadSettings.loginUrl;
      }, 3000);
    } catch (error) {
      p.error = error.message;
    } finally {
      p.isSaving = false;
    }
  },
};
