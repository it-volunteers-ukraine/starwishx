/**
 * Launchpad Interactivity API Store
 *
 * File: inc\launchpad\Assets\store.js
 *
 */

import { store, getElement, getContext } from "@wordpress/interactivity";

/**
 * UTILITIES
 */

const panelControllers = new Map();

/**
 * Utility: Deep clone objects.
 * We use the JSON method here because structuredClone throws errors
 * when encountering Interactivity API Proxies.
 */
function deepClone(obj) {
  if (!obj) return obj;
  return JSON.parse(JSON.stringify(obj));
}

/**
 * Convert panel-id (e.g., 'opportunities') to PascalCase (e.g., 'Opportunities')
 */
function idToPascal(id) {
  return String(id)
    .split(/[-_]/)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join("");
}

/**
 * Ensure a specific panel exists in the store state with reactive defaults
 */
function ensurePanel(state, panelId) {
  if (!state.panels[panelId]) {
    state.panels[panelId] = {
      isLoading: false,
      isSaving: false,
      error: null,
      _loaded: false,
      items: [],
      currentView: "list",
      formData: { seekers: [], subcategory: [] },
    };
  }
  return state.panels[panelId];
}

/**
 * Centralized Fetch Helper
 */
async function fetchJson(
  state,
  url,
  { method = "GET", body = null, panelId = null } = {}
) {
  if (panelId) {
    const prev = panelControllers.get(panelId);
    if (prev) prev.abort();
  }

  const controller = new AbortController();
  if (panelId) panelControllers.set(panelId, controller);

  try {
    const headers = {
      "X-WP-Nonce": state.launchpadSettings.nonce,
      "X-Requested-With": "XMLHttpRequest",
    };
    if (body) headers["Content-Type"] = "application/json";

    const response = await fetch(url, {
      method,
      credentials: "same-origin",
      signal: controller.signal,
      headers,
      body: body ? JSON.stringify(body) : null,
    });

    if (!response.ok) {
      let message = `${response.status} ${response.statusText}`;
      try {
        const error = await response.json();
        if (error?.message) message = error.message;
      } catch (_) {}
      const err = new Error(message);
      err.status = response.status;
      throw err;
    }

    if (response.status === 204) return {};

    const contentType = response.headers.get("content-type") || "";
    if (!contentType.includes("application/json")) {
      return {};
    }

    return await response.json();
  } finally {
    if (panelId) panelControllers.delete(panelId);
  }
}

/**
 * STORE DEFINITION
 */
const { state, actions } = store("launchpad", {
  state: {
    get currentPanel() {
      return state.panels[state.activePanel] || {};
    },

    // --- OPPORTUNITIES GETTERS ---
    get isOppListVisible() {
      return (state.panels.opportunities?.currentView || "list") === "list";
    },
    get isOppFormVisible() {
      const view = state.panels.opportunities?.currentView;
      return view === "add" || view === "edit";
    },
    get hasOppItems() {
      return !!state.panels.opportunities?.items?.length;
    },
    get showOppEmpty() {
      return !state.panels.opportunities?.isLoading && !state.hasOppItems;
    },
    get showOppGrid() {
      return state.hasOppItems;
    },
    get isSeekerChecked() {
      const ctx = getContext();
      const id = ctx?.item?.id;
      const selected = state.panels.opportunities?.formData?.seekers;
      return Array.isArray(selected) && selected.some((s) => s == id);
    },
  },

  actions: {
    /**
     * THE BRAIN: Syncs UI state with URL.
     */
    syncStateFromUrl() {
      const url = new URL(window.location);
      const params = url.searchParams;

      let panelId = params.get("panel") || state.activePanel || "profile";
      if (panelId === "user-stats") panelId = "stats";

      ensurePanel(state, panelId);
      state.activePanel = panelId;

      // Toggle active flags for Sidebar CSS
      Object.keys(state).forEach((key) => {
        if (key.startsWith("is") && key.endsWith("Active")) state[key] = false;
      });
      state[`is${idToPascal(panelId)}Active`] = true;

      // Ensure URL is not empty
      if (!params.get("panel")) {
        url.searchParams.set("panel", panelId);
        window.history.replaceState({ panel: panelId }, "", url);
      }

      // Handle sub-view logic
      if (panelId === "opportunities") {
        const oppPanel = state.panels.opportunities;
        const view = params.get("view") || "list";
        const id = params.get("id");

        if (oppPanel.currentView !== view) {
          oppPanel.currentView = view;
          if (view === "edit" && id && oppPanel.formData?.id != id) {
            actions.opportunities.loadSingle(id);
          } else if (view === "add") {
            actions.opportunities.resetForm();
          }
        }
      }

      if (!state.panels[panelId]._loaded && !state.panels[panelId].isLoading) {
        actions.loadPanelState(panelId);
      }
    },

    async setActivePanel(panelId, { pushHistory = false } = {}) {
      if (!panelId) return;
      const url = new URL(window.location);
      url.searchParams.set("panel", panelId);
      if (panelId !== state.activePanel) {
        url.searchParams.delete("view");
        url.searchParams.delete("id");
      }
      if (pushHistory) {
        window.history.pushState({ panel: panelId }, "", url);
      } else {
        window.history.replaceState({ panel: panelId }, "", url);
      }
      actions.syncStateFromUrl();
    },

    async switchPanel() {
      const { ref } = getElement();
      const panelId = ref?.dataset?.panelId;
      if (panelId) await actions.setActivePanel(panelId, { pushHistory: true });
    },

    async loadPanelState(panelId) {
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
          // Hydrate the form template if provided by the server
          if (data.emptyForm) panel.emptyForm = deepClone(data.emptyForm);
        }
      } catch (error) {
        panel.error = error.message;
        panel.isLoading = false;
      }
    },

    /**
     * OPPORTUNITIES MODULE
     */
    opportunities: {
      resetForm() {
        const panel = state.panels.opportunities;
        // Logic: use emptyForm template if available, otherwise manual defaults
        panel.formData = panel.emptyForm
          ? deepClone(panel.emptyForm)
          : { id: null, title: "", seekers: [], subcategory: [] };
      },

      async loadSingle(id) {
        const panel = ensurePanel(state, "opportunities");
        panel.isLoading = true;
        try {
          const data = await fetchJson(
            state,
            `${state.launchpadSettings.restUrl}opportunities/${id}`,
            { panelId: "opportunities" }
          );
          if (data) panel.formData = data;
        } catch (e) {
          panel.error = e.message;
        } finally {
          panel.isLoading = false;
        }
      },

      openAdd() {
        actions.opportunities.resetForm();
        const url = new URL(window.location);
        url.searchParams.set("view", "add");
        url.searchParams.delete("id");
        window.history.pushState({}, "", url);
        actions.syncStateFromUrl();
      },

      async openEdit(event) {
        const btn = event.target.closest("button");
        const id = Number(btn?.dataset?.id || event.target.dataset.id);
        if (!Number.isFinite(id) || id <= 0) return;

        const url = new URL(window.location);
        url.searchParams.set("view", "edit");
        url.searchParams.set("id", id);
        window.history.pushState({}, "", url);
        actions.syncStateFromUrl();
      },

      async save(event) {
        event.preventDefault();
        const panel = state.panels.opportunities;
        if (panel.isSaving) return;
        panel.isSaving = true;
        try {
          const id = panel.formData.id;
          const method = id ? "PUT" : "POST";
          const endpoint = id
            ? `${state.launchpadSettings.restUrl}opportunities/${id}`
            : `${state.launchpadSettings.restUrl}opportunities`;

          await fetchJson(state, endpoint, {
            method,
            body: panel.formData,
            panelId: "opportunities",
          });

          actions.opportunities.cancel();
          await actions.loadPanelState("opportunities");
        } catch (error) {
          panel.error = error.message;
        } finally {
          panel.isSaving = false;
        }
      },

      cancel() {
        const url = new URL(window.location);
        url.searchParams.delete("view");
        url.searchParams.delete("id");
        window.history.pushState({}, "", url);
        actions.syncStateFromUrl();
      },

      updateForm() {
        const { ref } = getElement();
        const field = ref?.dataset?.field;
        const panel = state.panels.opportunities;
        if (panel.formData && field) panel.formData[field] = ref.value;
      },

      toggleSeeker(event) {
        const { ref } = getElement();
        const panel = state.panels.opportunities;
        const value = Number(ref.value);
        let seekers = Array.isArray(panel.formData.seekers)
          ? [...panel.formData.seekers]
          : [];
        if (ref.checked) {
          if (!seekers.includes(value)) seekers.push(value);
        } else {
          seekers = seekers.filter((id) => id !== value);
        }
        panel.formData.seekers = seekers;
      },

      async loadMore() {
        const panel = ensurePanel(state, "opportunities");
        if (panel.isLoading) return;
        panel.isLoading = true;
        try {
          const nextPage = (panel.page || 0) + 1;
          const data = await fetchJson(
            state,
            `${state.launchpadSettings.restUrl}opportunities?page=${nextPage}`,
            { panelId: "opportunities" }
          );
          if (data) {
            panel.items = [...(panel.items || []), ...data.items];
            panel.page = nextPage;
            panel.hasMore = nextPage < data.total_pages;
          }
        } catch (error) {
          panel.error = error.message;
        } finally {
          panel.isLoading = false;
        }
      },
    },

    /**
     * PROFILE MODULE
     */
    profile: {
      startEdit() {
        const panel = ensurePanel(state, "profile");
        panel.isEditing = true;
        // Cache original values to support "Cancel"
        panel._original = {
          firstName: panel.firstName,
          lastName: panel.lastName,
          email: panel.email,
          phone: panel.phone, // NEW
          telegram: panel.telegram, // NEW
        };
      },
      cancelEdit() {
        const panel = ensurePanel(state, "profile");
        if (panel._original) Object.assign(panel, panel._original);
        panel.isEditing = false;
      },
      updateField() {
        const { ref } = getElement();
        const field = ref?.dataset?.field;
        // Generic update works for all fields (firstName, phone, etc.)
        if (field) ensurePanel(state, "profile")[field] = ref.value;
      },
      async save(event) {
        event.preventDefault();
        const panel = ensurePanel(state, "profile");
        if (panel.isSaving) return;
        panel.isSaving = true;
        try {
          const data = await fetchJson(
            state,
            `${state.launchpadSettings.restUrl}profile`,
            {
              method: "POST",
              body: {
                firstName: panel.firstName,
                lastName: panel.lastName,
                email: panel.email,
                phone: panel.phone, // NEW
                telegram: panel.telegram, // NEW
              },
            }
          );
          if (data) Object.assign(panel, data);
          panel.isEditing = false;
        } catch (error) {
          panel.error = error.message;
        } finally {
          panel.isSaving = false;
        }
      },
    },

    /**
     * FAVORITES MODULE
     */
    favorites: {
      async remove() {
        const { ref } = getElement();
        const postId = Number(ref?.dataset?.postId);
        const panel = ensurePanel(state, "favorites");
        try {
          await fetchJson(
            state,
            `${state.launchpadSettings.restUrl}favorites/${postId}`,
            { method: "DELETE" }
          );
          panel.items = (panel.items || []).filter(
            (item) => item.id !== postId
          );
        } catch (error) {
          panel.error = error.message;
        }
      },
    },

    /**
     * SECURITY MODULE
     */
    security: {
      updateField() {
        const { ref } = getElement();
        const field = ref?.dataset?.field;
        if (field) ensurePanel(state, "security")[field] = ref.value;
      },
      async changePassword(event) {
        event.preventDefault();
        const panel = ensurePanel(state, "security");
        if (panel.isSaving) return;
        if (panel.newPassword !== panel.confirmPassword) {
          panel.error = "Passwords do not match.";
          return;
        }
        panel.isSaving = true;
        try {
          await fetchJson(
            state,
            `${state.launchpadSettings.restUrl}security/password`,
            {
              method: "POST",
              body: {
                currentPassword: panel.currentPassword,
                newPassword: panel.newPassword,
              },
            }
          );
          panel.passwordChanged = true;
          setTimeout(() => {
            window.location.href = state.launchpadSettings.loginUrl;
          }, 3000);
        } catch (error) {
          panel.error = error.message;
        } finally {
          panel.isSaving = false;
        }
      },
    },
  },
});

/**
 * INITIALIZATION
 */

window.addEventListener("popstate", () => actions.syncStateFromUrl());

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    actions.syncStateFromUrl()
  );
} else {
  actions.syncStateFromUrl();
}
