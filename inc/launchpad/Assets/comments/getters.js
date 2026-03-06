/**
 * Launchpad Comments — Getters
 * File: inc/launchpad/Assets/comments/getters.js
 *
 * Computed state logic.
 * 'this' refers to the State Proxy after being mixed in via extendState.
 */

import { getContext } from "@wordpress/interactivity";

export const commentsGetters = {
  /**
   * Check if there are any comments in the list.
   * Defensive check ensures we don't crash if list is undefined during hydration.
   *
   * @returns {boolean}
   */
  get hasComments() {
    return Array.isArray(this.list) && this.list.length > 0;
  },

  get hasRatings() {
    return this.aggregates && this.aggregates.count > 0;
  },

  /**
   * "Add review" button visible only on published posts and when form is closed.
   */
  get isAddReviewVisible() {
    return this.canComment && !this.showForm;
  },
};
