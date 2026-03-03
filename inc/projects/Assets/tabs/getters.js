/**
 * Projects Store — Tab Getters
 *
 * Computed properties for tab visibility and counts.
 * 'this' refers to the state proxy.
 *
 * File: inc/projects/Assets/tabs/getters.js
 */

import { getContext } from "@wordpress/interactivity";

export const tabGetters = {
  get isAboutActive() {
    return this.activeTab === "about";
  },

  get isOpportunitiesActive() {
    return this.activeTab === "opportunities";
  },

  get isNgosActive() {
    return this.activeTab === "ngos";
  },

  get hasOpportunities() {
    return Array.isArray(this.opportunities) && this.opportunities.length > 0;
  },

  get hasNgos() {
    return Array.isArray(this.ngos) && this.ngos.length > 0;
  },

  get opportunitiesCount() {
    return this.counts?.opportunities ?? 0;
  },

  get ngosCount() {
    return this.counts?.ngos ?? 0;
  },

  /**
   * Per-card favorite state.
   *
   * Uses ONLY the local context property (item.isFavorite) — no cross-store
   * reactive dependencies. The toggleFavorite action flips this property
   * optimistically, keeping each card an independent reactive island
   * within the data-wp-each loop.
   */
  get isFavorited() {
    const ctx = getContext();
    const item = ctx?.item;
    if (!item || !item.id) return false;
    return !!item.isFavorite;
  },
};
