/**
 * Launchpad Store — Profile Getters
 *
 * Computed state getters for the profile panel.
 * Uses 'this' to access state (refers to the State Proxy after extendState).
 *
 * File: inc/launchpad/Assets/profile/getters.js
 */

import { getContext } from "@wordpress/interactivity";

/**
 * Profile getters - plain object to be mixed into state via extendState.
 */
export const profileGetters = {
  /**
   * Is the profile card (read-only view) visible?
   * Hidden when editing profile or changing password.
   */
  get isProfileCardVisible() {
    const p = this.panels.profile;
    return !p?.isEditing && !p?.isChangingPassword;
  },

  get currentPasswordInputType() {
    return this.panels.profile?.isCurrentPasswordVisible ? "text" : "password";
  },

  get newPasswordInputType() {
    return this.panels.profile?.isNewPasswordVisible ? "text" : "password";
  },

  get deletePasswordInputType() {
    return this.panels.profile?.deletePopup?.isPasswordVisible
      ? "text"
      : "password";
  },

  get emailPasswordInputType() {
    return this.panels.profile?.emailPopup?.isPasswordVisible
      ? "text"
      : "password";
  },

  /**
   * Is the current sensitive field blurred? (context-aware via data-field)
   * Reads data-field from the element to check against revealedFields array.
   */
  get isFieldBlurred() {
    const field = getContext()?.field;
    const revealed = this.panels.profile?.revealedFields;
    return !field || !Array.isArray(revealed) || !revealed.includes(field);
  },

  /**
   * Is the current display-name option the selected one? (context-aware)
   */
  get isDisplayNameItemSelected() {
    const val = getContext()?.item;
    return val === this.panels.profile?.displayName;
  },
};
