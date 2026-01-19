/**
 * Launchpad Store — Local Utilities
 *
 * Contains utility functions specific to the Launchpad store.
 * These are extracted from the main launchpad-store.js for modularity.
 *
 * File: inc/launchpad/Assets/utils.js
 */

/**
 * AbortController registry for canceling in-flight requests per panel.
 * @type {Map<string, AbortController>}
 */
export const panelControllers = new Map();

/**
 * Utility: Deep clone objects.
 * We use the JSON method here because structuredClone throws errors
 * when encountering Interactivity API Proxies.
 *
 * @param {Object} obj - Object to clone
 * @returns {Object} Deep cloned object
 */
export function deepClone(obj) {
  if (!obj) return obj;
  return JSON.parse(JSON.stringify(obj));
}

/**
 * Default panel state structure.
 * Extracted as constant for consistency and documentation.
 */
const DEFAULT_PANEL_STATE = {
  isLoading: false,
  isSaving: false,
  error: null,
  _loaded: false,
  items: [],
  currentView: "list",
  formData: { seekers: [], subcategory: [] },
  layout: "compact",
  isLayoutCompact: true,
  isLayoutCard: false,
  isLayoutGrid: false,
};

/**
 * Ensure a specific panel exists in the store state with reactive defaults.
 *
 * @param {Object} state - The Interactivity API state object
 * @param {string} panelId - Panel identifier (e.g., 'opportunities', 'profile')
 * @returns {Object} The panel state object (created if didn't exist)
 */
export function ensurePanel(state, panelId) {
  if (!state.panels[panelId]) {
    // Use spread to create a fresh copy of defaults
    state.panels[panelId] = { ...DEFAULT_PANEL_STATE };
  }
  return state.panels[panelId];
}

/**
 * Centralized Fetch Helper with WP nonce and AbortController support.
 *
 * Features:
 * - Automatic WP nonce injection from state.launchpadSettings
 * - Per-panel request cancellation (panelId option)
 * - JSON body serialization
 * - Standardized error handling
 *
 * @param {Object} state - The Interactivity API state object
 * @param {string} url - Full API URL
 * @param {Object} options
 * @param {string} [options.method='GET'] - HTTP method
 * @param {Object|null} [options.body=null] - Request body (will be JSON.stringify'd)
 * @param {string|null} [options.panelId=null] - Panel ID for request cancellation
 * @returns {Promise<Object>} Parsed JSON response
 * @throws {Error} With message and status properties on failure
 */
export async function fetchJson(
  state,
  url,
  { method = "GET", body = null, panelId = null } = {}
) {
  // Cancel any previous request for this panel
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
 * Utility: Safely merges source objects into a target object,
 * preserving Getters/Setters instead of evaluating them.
 *
 * @param {Object} target - The destination state object
 * @param {...Object} sources - Objects containing getters/properties to mix in
 * @returns {Object} The modified target
 */
export function extendState(target, ...sources) {
  sources.forEach((source) => {
    Object.defineProperties(target, Object.getOwnPropertyDescriptors(source));
  });
  return target;
}
