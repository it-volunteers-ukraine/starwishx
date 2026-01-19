/**
 * Launchpad Store — Profile Getters
 *
 * Computed state getters for the profile panel.
 * Uses 'this' to access state (refers to the State Proxy after extendState).
 *
 * File: inc/launchpad/Assets/profile/getters.js
 */

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
};
