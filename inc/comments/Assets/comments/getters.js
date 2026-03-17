/**
 * Comments — Getters
 * File: inc/comments/Assets/comments/getters.js
 *
 * Computed state logic.
 * 'this' refers to the State Proxy after being mixed in via extendState.
 */

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

  get roundedAvg() {
    return this.aggregates ? Math.round(this.aggregates.avg) : 0;
  },

  get ratingBadgeLabel() {
    if (!this.aggregates) return "";
    return (this.config?.messages?.ratingBadgeLabel || "")
      .replace("%avg%", this.aggregates.avg)
      .replace("%count%", this.aggregates.count);
  },

  /**
   * "Add review" button visible only on published posts and when form is closed.
   */
  get isAddReviewVisible() {
    return this.canComment && !this.showForm;
  },
};
