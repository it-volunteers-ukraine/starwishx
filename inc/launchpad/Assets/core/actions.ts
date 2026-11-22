/**
 * Launchpad Store — Core Actions
 *
 * Root-level actions for panel navigation and state management.
 * Uses Store Locator pattern: launchpadStore() to access state/actions.
 *
 * File: inc/launchpad/Assets/core/actions.ts
 */

import { getElement } from "@wordpress/interactivity";
import { launchpadStore } from "../stores.ts";
import type { LaunchpadState, OpportunityView } from "../types.ts";
import {
  apiErrorMessage,
  currentUrl,
  ensurePanel,
  fetchJson,
  isPanelState,
  loadedPanel,
  writeUrl,
} from "../utils.ts";

const VIEWS: readonly OpportunityView[] = ["list", "add", "edit"];

/** ?view for the opportunities panel; anything unknown is the list. */
const opportunityView = (view: string | null): OpportunityView =>
  VIEWS.find((known) => known === view) ?? "list";

/**
 * The panel the URL asks for when it is registered (panelMap), else the one
 * the server rendered - it falls back the same way for an unknown ?panel.
 */
function requestedPanel(state: LaunchpadState, requested: string | null) {
  return requested !== null && Object.keys(state.panelMap).includes(requested)
    ? requested
    : state.activePanel;
}

/** Apply ?view / ?id to a loaded panel. */
function routePanel(panelId: string, params: URLSearchParams): void {
  const { state, actions } = launchpadStore();

  // Opportunities: the list, or the form (add / edit ?id)
  if (panelId === "opportunities") {
    const p = loadedPanel(state, "opportunities");
    if (!p) return;
    const view = opportunityView(params.get("view"));
    const id = Number(params.get("id"));
    if (p.currentView !== view) {
      p.currentView = view;
      if (view === "edit" && id > 0) void actions.opportunities.loadSingle(id);
      else if (view === "add") actions.opportunities.resetForm();
    }
  }

  // Profile: view=profile | view=password | default = card
  if (panelId === "profile") {
    const p = loadedPanel(state, "profile");
    if (!p) return;
    p.revealedFields = [];
    const view = params.get("view");
    const wasEditing = p.isEditing;
    p.isEditing = view === "profile";
    p.isChangingPassword = view === "password";
    // Capture original values when entering edit mode (for cancel restore)
    if (p.isEditing && !wasEditing) {
      p._original = {
        firstName: p.firstName,
        lastName: p.lastName,
        email: p.email,
        phone: p.phone,
        telegram: p.telegram,
        organization: p.organization,
      };
    }
  }
}

export const coreActions = {
  /**
   * Synchronize state from URL parameters: the active panel, its flags, its
   * view (list / add / edit, profile / password), and its first load.
   */
  syncStateFromUrl(): void {
    const { state, actions } = launchpadStore();
    const url = currentUrl();
    const params = url.searchParams;
    const panelId = requestedPanel(state, params.get("panel"));

    const panel = ensurePanel(state, panelId);
    state.activePanel = panelId;

    // Visibility flags (e.g. isOpportunitiesActive)
    for (const [id, flag] of Object.entries(state.panelMap)) {
      state[flag] = id === panelId;
    }

    // Ensure URL reflects state (no ?panel, or one that isn't registered)
    if (params.get("panel") !== panelId) {
      url.searchParams.set("panel", panelId);
      writeUrl(url, { replace: true });
    }

    if (panel._loaded) {
      routePanel(panelId, params);
      return;
    }

    // First visit: load, then route - the loaded state brings its own
    // view, which routing must come after, not race.
    if (!panel.isLoading) {
      void actions.loadPanelState(panelId).then(() => {
        const loaded = state.panels[panelId]?._loaded === true;
        if (loaded && state.activePanel === panelId) actions.syncStateFromUrl();
      });
    }
  },

  /**
   * Set the active panel and optionally push to history.
   */
  setActivePanel(panelId: string, { pushHistory = false } = {}): void {
    const { state, actions } = launchpadStore();
    if (!panelId) return;
    const url = currentUrl();
    url.searchParams.set("panel", panelId);

    // Clear view/id params when switching panels
    if (panelId !== state.activePanel) {
      url.searchParams.delete("view");
      url.searchParams.delete("id");
    }

    writeUrl(url, { replace: !pushHistory });
    actions.syncStateFromUrl();
  },

  /**
   * Switch panel from click event (e.g., Sidebar tabs).
   */
  switchPanel(): void {
    const panelId = getElement().ref?.dataset.panelId;
    if (panelId) {
      launchpadStore().actions.setActivePanel(panelId, { pushHistory: true });
    }
  },

  /**
   * Load panel state from API (lazy loading of the panels the page didn't
   * render active). Its own abort key: the panel's other requests (a single
   * opportunity, the next page) don't cancel it.
   */
  async loadPanelState(panelId: string): Promise<void> {
    const { state } = launchpadStore();
    const panel = ensurePanel(state, panelId);
    if (panel.isLoading) return;

    panel.isLoading = true;
    try {
      const data = await fetchJson(
        `${state.launchpadSettings.restUrl}panel/${panelId}/state`,
        isPanelState,
        { abortKey: `panel:${panelId}` },
      );
      if (data) Object.assign(panel, data, { _loaded: true });
    } catch (error) {
      panel.error = apiErrorMessage(error);
    } finally {
      panel.isLoading = false;
    }
  },
};
