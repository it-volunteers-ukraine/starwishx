/**
 * Launchpad Store — Core Getters
 *
 * Computed state for core panel functionality:
 * - currentPanel
 *
 * File: inc/launchpad/Assets/core/getters.js
 */

export const coreGetters = {
  /**
   * Retrieve the state object of the currently active panel.
   * Returns an empty object if the panel doesn't exist yet to prevent errors.
   */
  get currentPanel() {
    return this.panels[this.activePanel] || {};
  },
};
