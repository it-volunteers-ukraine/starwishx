/**
 * Launchpad Store — Core Actions
 *
 * Root-level actions for panel navigation and state management.
 * Uses Store Locator pattern: store("launchpad") to access state/actions.
 *
 * File: inc/launchpad/Assets/core/actions.js
 */

import { getElement, store } from "@wordpress/interactivity";
import { deepClone, ensurePanel, fetchJson } from "../utils.js";

export const coreActions = {
  /**
   * Synchronize state from URL parameters.
   * Handles panel switching, view modes (list/edit), and hydration.
   */
  syncStateFromUrl() {
    const { state, actions } = store("launchpad");
    const url = new URL(window.location);
    const params = url.searchParams;
    let panelId = params.get("panel") || state.activePanel || "opportunities";

    ensurePanel(state, panelId);
    state.activePanel = panelId;

    // Update panelMap flags (e.g., isOpportunitiesActive)
    if (state.panelMap) {
      Object.entries(state.panelMap).forEach(([id, stateKey]) => {
        state[stateKey] = id === panelId;
      });
    }

    // Ensure URL reflects state
    if (!params.get("panel")) {
      url.searchParams.set("panel", panelId);
      window.history.replaceState({ panel: panelId }, "", url);
    }

    // Specific logic for Opportunities routing (View/ID)
    if (panelId === "opportunities") {
      const oppPanel = state.panels.opportunities;
      const view = params.get("view") || "list";
      const id = params.get("id");
      if (oppPanel.currentView !== view) {
        oppPanel.currentView = view;
        if (view === "edit" && id) actions.opportunities.loadSingle(id);
        else if (view === "add") actions.opportunities.resetForm();
      }
    }

    // Hydrate panel if needed
    if (!state.panels[panelId]._loaded && !state.panels[panelId].isLoading) {
      actions.loadPanelState(panelId);
    }
  },

  /**
   * Set the active panel and optionally push to history.
   */
  async setActivePanel(panelId, { pushHistory = false } = {}) {
    const { state, actions } = store("launchpad");
    if (!panelId) return;
    const url = new URL(window.location);
    url.searchParams.set("panel", panelId);

    // Clear view/id params when switching panels
    if (panelId !== state.activePanel) {
      url.searchParams.delete("view");
      url.searchParams.delete("id");
    }

    if (pushHistory) window.history.pushState({ panel: panelId }, "", url);
    else window.history.replaceState({ panel: panelId }, "", url);

    actions.syncStateFromUrl();
  },

  /**
   * Switch panel from click event (e.g., Sidebar tabs).
   */
  async switchPanel() {
    const { actions } = store("launchpad");
    const panelId = getElement().ref?.dataset?.panelId;
    if (panelId) await actions.setActivePanel(panelId, { pushHistory: true });
  },

  /**
   * Load panel state from API (SSR Hydration / Lazy Loading).
   */
  async loadPanelState(panelId) {
    const { state } = store("launchpad");
    const panel = ensurePanel(state, panelId);
    if (panel.isLoading) return;

    panel.isLoading = true;
    try {
      const data = await fetchJson(
        state,
        `${state.launchpadSettings.restUrl}panel/${panelId}/state`,
        { panelId }
      );
      if (data) {
        Object.assign(panel, data, { _loaded: true, isLoading: false });
        if (data.emptyForm) panel.emptyForm = deepClone(data.emptyForm);
      }
    } catch (error) {
      panel.error = error.message;
      panel.isLoading = false;
    }
  },
};
