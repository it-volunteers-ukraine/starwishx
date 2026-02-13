/**
 * Launchpad Store — Opportunities Getters
 *
 * Computed state getters for the opportunities panel.
 * Uses 'this' to access state (refers to the State Proxy).
 *
 * File: inc/launchpad/Assets/opportunities/getters.js
 */

import { getContext } from "@wordpress/interactivity";

/**
 * Opportunity getters - plain object to be spread into state.
 * 'this' refers to the Interactivity API State Proxy.
 */
export const opportunitiesGetters = {
  /**
   * Is the opportunities list view currently visible?
   */
  get isOppListVisible() {
    return (this.panels.opportunities?.currentView || "list") === "list";
  },

  /**
   * Is the opportunities form (add or edit) currently visible?
   */
  get isOppFormVisible() {
    const view = this.panels.opportunities?.currentView;
    return view === "add" || view === "edit";
  },

  /**
   * Does the opportunities panel have any items?
   */
  get hasOppItems() {
    return !!this.panels.opportunities?.items?.length;
  },

  /**
   * Should the empty state message be shown?
   */
  get showOppEmpty() {
    return !this.panels.opportunities?.isLoading && !this.hasOppItems;
  },

  /**
   * Should the grid/list be shown?
   */
  get showOppGrid() {
    return this.hasOppItems;
  },

  /**
   * Get appropriate form headers based on current view
   */
  get opportunityFormHeaders() {
    const view = this.panels.opportunities?.currentView || "list";
    return view === "add"
      ? this.panels.opportunities?.formHeaders?.newOpportunity
      : this.panels.opportunities?.formHeaders?.editOpportunity;
  },

  // ──────────────────────────────────────────────────────────────
  // CONTEXT-AWARE GETTERS (getContext works fine in external files)
  // ──────────────────────────────────────────────────────────────

  /**
   * Is the current seeker checkbox checked?
   * Used in: data-wp-bind--checked="state.isSeekerChecked"
   */
  get isSeekerChecked() {
    const id = getContext()?.item?.id;
    const selected = this.panels.opportunities?.formData?.seekers;
    return Array.isArray(selected) && selected.some((s) => s == id);
  },

  /**
   * Is the current category checkbox checked?
   * Used in: data-wp-bind--checked="state.isCategoryChecked"
   */
  get isCategoryChecked() {
    // We expect context.child.id from the inner loop
    const id = getContext()?.child?.id;
    const selected = this.panels.opportunities?.formData?.category;
    return Array.isArray(selected) && selected.some((s) => s == id);
  },

  /**
   * Can the current item/form be edited?
   */
  get canEdit() {
    const item = getContext()?.item;
    if (item?.id) {
      return item.status === "draft";
    }

    const p = this.panels.opportunities;
    if (p?.currentView === "add") {
      return true;
    }
    if (p?.currentView === "edit" && p?.formData) {
      return p.formData.status === "draft";
    }

    return false;
  },

  /**
   * Is the current item/form published?
   */
  get isPublish() {
    const item = getContext()?.item;
    if (item?.id) {
      return item.status === "publish";
    }

    const p = this.panels.opportunities;
    if (p?.currentView === "add") {
      return false;
    }
    if (p?.formData?.status) {
      return p.formData.status === "publish";
    }

    return false;
  },

  /**
   * Is the current item/form pending review?
   */
  get isPending() {
    const item = getContext()?.item;
    if (item?.id) {
      return item.status === "pending";
    }

    const p = this.panels.opportunities;
    if (p?.currentView === "add") {
      return false;
    }
    return p?.formData?.status === "pending";
  },

  /**
   * Is the status filter checkbox checked?
   */
  get isStatusChecked() {
    const { status } = getContext();
    const active = this.panels.opportunities?.filters?.statuses || [];
    return active.includes(status);
  },

  /**
   * Get the expiration label text based on item context
   */
  get expirationLabel() {
    const item = getContext()?.item;
    if (!item?.isExpired) return "";
    return "Outdated"; // Or use a translation from state.settings if available
  },

  /**
   * Check if selected country is Ukraine.
   * We scan the 'options.countries' list to find the ID for "Ukraine".
   */
  get isUkraineSelected() {
    const p = this.panels.opportunities;
    if (!p || !p.formData || !p.formData.country) return false;

    const selectedId = parseInt(p.formData.country);

    // Find the country object in options to check name
    // Assuming options.countries is loaded
    if (p.options && p.options.countries) {
      const country = p.options.countries.find((c) => c.id === selectedId);
      // Check against localized name or slug if available.
      // For stability, checking "Ukraine" string or specific ID is common.
      // Adjust "Ukraine" string based on your exact term name in DB.
      return (
        country && (country.name === "Ukraine" || country.name === "Україна")
      );
    }
    return false;
  },
};
