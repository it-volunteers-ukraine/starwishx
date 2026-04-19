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
  isUploading: false,
  error: null,
  fieldErrors: {},
  _loaded: false,
  items: [],
  currentView: "list",
  formData: {
    seekers: [],
    subcategory: [],
    // Shared list of selected chips
    locations: [],

    // --- SPLIT SEARCH STATES ---
    // 1. Oblast
    searchOblast: "",
    resultsOblast: [],

    // 2. Raion
    searchRaion: "",
    resultsRaion: [],

    // 3. City/Settlement
    searchCity: "",
    resultsCity: [],
  },
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
  { method = "GET", body = null, panelId = null } = {},
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

    let payload = body;

    // DETECT FORM DATA
    if (body instanceof FormData) {
      // Do NOT set Content-Type; browser sets it with boundary
      // payload remains FormData
    } else if (body) {
      headers["Content-Type"] = "application/json";
      payload = JSON.stringify(body);
    }

    const response = await fetch(url, {
      method,
      credentials: "same-origin",
      signal: controller.signal,
      headers,
      body: payload, // Use processed payload
    });

    if (!response.ok) {
      let message = `${response.status} ${response.statusText}`;
      let fieldErrors = null;
      try {
        const error = await response.json();
        if (error?.message) message = error.message;
        if (error?.data?.field_errors) fieldErrors = error.data.field_errors;
      } catch (_) {}
      const err = new Error(message);
      err.status = response.status;
      err.fieldErrors = fieldErrors;
      throw err;
    }

    if (response.status === 204) return {};

    const contentType = response.headers.get("content-type") || "";
    if (!contentType.includes("application/json")) {
      return {};
    }

    return await response.json();
  } catch (error) {
    if (error.name === "AbortError") {
      // Aborted intentionally — do not throw or log as an error
      return; // Or throw a custom signal if needed
    }
    throw error; // Re-throw other errors
  } finally {
    if (panelId) panelControllers.delete(panelId);
  }
}

/**
 * Normalize a URL field value (mirrors backend InputSanitizer::sanitizeUrl).
 *
 * - Auto-prepends https:// when no scheme is present
 * - Rejects non-http(s) schemes (ftp, mailto, javascript, data, file…)
 * - Returns "" for invalid input so required-field checks catch it
 *
 * @param {string|null|undefined} value Raw field value
 * @returns {string} Normalized URL or ""
 */
export function normalizeUrl(value) {
  if (!value || !value.trim()) return "";
  let url = value.trim();

  // No scheme at all → assume https
  if (!/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(url)) {
    url = "https://" + url;
  }

  // Only allow http/https — reject everything else
  if (!/^https?:\/\//i.test(url)) {
    return "";
  }

  return url;
}

/**
 * Validate a name field (mirrors backend ProfileService::validateNameField).
 *
 * Allowed: Unicode letters, spaces, hyphens, apostrophes (straight + curly), periods.
 * Returns null if valid, or an error-message-key if invalid.
 * Empty values are valid (fields are optional).
 *
 * @param {string|null|undefined} value
 * @param {{ min?: number, max?: number }} limits
 * @returns {string|null} Error key or null
 */
export function validateName(value, limits = {}) {
  if (!value || !value.trim()) return null;
  const t = value.trim();
  const min = limits.min || 2;
  const max = limits.max || 80;

  if (t.length < min) return "nameMinLength";
  if (t.length > max) return "nameMaxLength";
  // Must contain at least one letter
  if (!/\p{L}/u.test(t)) return "nameInvalid";
  // Only allowed characters
  if (!/^[\p{L}\s\-\u2019'.]+$/u.test(t)) return "nameInvalid";
  return null;
}

/**
 * Validate a username/nickname against the hydrated UsernamePolicy rules.
 * Returns the translated error message on failure, or null on success.
 * Policy i18n messages are hydrated from PHP via wp_interactivity_state().
 *
 * Callers decide whether empty values are allowed — this only checks
 * the character-set / length constraints when a value is present.
 *
 * @param {string|null|undefined} value
 * @param {{pattern?: string, minLength?: number, maxLength?: number, messages?: Object}} policy
 * @returns {string|null}
 */
export function validateUsername(value, policy) {
  if (!policy) return null;
  const { pattern, minLength, maxLength, messages = {} } = policy;
  const str = String(value ?? "");

  if (typeof minLength === "number" && str.length < minLength) {
    return messages.tooShort ?? "";
  }
  if (typeof maxLength === "number" && str.length > maxLength) {
    return messages.tooLong ?? "";
  }
  if (pattern && !new RegExp(pattern).test(str)) {
    return messages.invalid ?? "";
  }
  return null;
}

/**
 * Validate a messenger handle against the hydrated MessengerPolicy rules.
 * Returns the translated error message on failure, or null on success.
 *
 * The regex already encodes length + character-set + first-char rules, so
 * a single `messages.invalid` string covers every failure mode.
 *
 * @param {string|null|undefined} value
 * @param {{pattern?: string, messages?: Object}} policy
 * @returns {string|null}
 */
export function validateMessenger(value, policy) {
  if (!policy) return null;
  const { pattern, messages = {} } = policy;
  const str = String(value ?? "");

  if (pattern && !new RegExp(pattern).test(str)) {
    return messages.invalid ?? "";
  }
  return null;
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

/**
 * Scroll the first visible error into view.
 *
 * Targets any `.exclamation-circle__error` element that is currently not
 * hidden — covers both the panel-header banner (when p.error is set) and
 * inline field errors (when p.fieldErrors is populated). The double-rAF
 * waits for the Interactivity API to commit the pending state update so
 * the target element's `hidden` attribute reflects the new state before
 * we query the DOM.
 *
 * @param {Element|Document} [root=document] Scope to search within.
 */
export function scrollToFirstError(root = document) {
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      const target = root.querySelector(
        ".exclamation-circle__error:not([hidden])",
      );
      target?.scrollIntoView({ behavior: "smooth", block: "center" });
    });
  });
}
