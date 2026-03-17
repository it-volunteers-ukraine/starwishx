/**
 * Listing Store — Utilities
 *
 * Handles URL synchronization, debounced API calls, and standardized fetching.
 * File: inc/listing/Assets/utils.js
 */
import { store } from "@wordpress/interactivity";
/**
 * AbortController registry to cancel pending search requests.
 */
export const searchControllers = new Map();

/**
 * Utility: Deep clone objects, safe for Interactivity API Proxies.
 */
export function deepClone(obj) {
  if (!obj) return obj;
  return JSON.parse(JSON.stringify(obj));
}

/**
 * Utility: Debounce function to limit API calls during typing.
 */
export function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

/**
 * Standardized Fetch Helper for the Listing Module.
 *
 * @param {Object} state - The 'listing' store state.
 * @param {string} url - API endpoint.
 * @param {Object} options - Fetch options (method, body, requestId).
 */
export async function fetchJson(
  state,
  url,
  { method = "GET", body = null, requestId = "main" } = {},
) {
  // Cancel previous request with the same ID
  if (searchControllers.has(requestId)) {
    searchControllers.get(requestId).abort();
  }

  const controller = new AbortController();
  searchControllers.set(requestId, controller);

  const { state: settingsState } = store("listingSettings");
  const config = settingsState.config || {};

  try {
    const headers = {
      "X-WP-Nonce": config.nonce,
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

    return await response.json();
  } finally {
    searchControllers.delete(requestId);
  }
}

/**
 * Synchronize the Store State to the Browser URL.
 * Converts the 'query' object into URL Search Parameters.
 *
 * @param {Object} query - The state.query object.
 */
// Around line ~107 - Update the URL sync function
export function syncStateToUrl(query) {
  const url = new URL(window.location);
  const params = new URLSearchParams();

  // Category facets tree for slug serialization
  const { state } = store("listing");
  const categoryTree = state.facets?.["category-oportunities"] || [];

  Object.entries(query).forEach(([key, value]) => {
    if (!value || (Array.isArray(value) && value.length === 0)) {
      params.delete(key);
      return;
    }

    //! avoid write default page=1 to URL — it's the implicit default
    if (key === "page" && value === 1) return;

    // Smart category: parent selected → slug, children only → numeric IDs
    if (key === "category" && Array.isArray(value) && categoryTree.length > 0) {
      for (const node of categoryTree) {
        if (value.includes(node.id)) {
          // Parent fully selected → write slug, skip children
          params.append(key, node.slug);
        } else if (node.children?.length) {
          for (const child of node.children) {
            if (value.includes(child.id)) {
              params.append(key, child.id);
            }
          }
        }
      }
    } else if (Array.isArray(value)) {
      // Clean URL: ?country=75 (no brackets)
      value.forEach((val) => params.append(key, val));
    } else {
      params.set(key, value);
    }
  });

  const newUrl = params.toString()
    ? `${url.pathname}?${params.toString()}`
    : url.pathname;

  window.history.pushState({ query: deepClone(query) }, "", newUrl);
}

/**
 * Synchronize the Browser URL to the Store State.
 * Used on initial load and 'popstate' (back/forward buttons).
 *
 * @param {Object} queryState - The reactive state.query object to update.
 */
export function syncUrlToState(queryState) {
  const params = new URLSearchParams(window.location.search);

  // Category facets tree for slug resolution
  const { state } = store("listing");
  const categoryTree = state.facets?.["category-oportunities"] || [];

  // Reset existing state keys to default before filling from URL
  Object.keys(queryState).forEach((key) => {
    if (Array.isArray(queryState[key])) {
      queryState[key] = [];
    } else {
      queryState[key] = key === "page" ? 1 : "";
    }
  });

  // Resolve category first: slugs → parent + children IDs, numeric → as-is
  const categoryRaw = params.getAll("category");
  if (categoryRaw.length > 0) {
    const ids = [];
    for (const val of categoryRaw) {
      if (isNaN(val)) {
        // Slug → find parent in tree, expand to parent + all children
        const parent = categoryTree.find((n) => n.slug === val);
        if (parent) {
          ids.push(parent.id);
          if (parent.children) {
            ids.push(...parent.children.map((c) => c.id));
          }
        }
      } else {
        ids.push(Number(val));
      }
    }
    queryState.category = [...new Set(ids)];
  }

  // Handle remaining params (skip category — already resolved)
  for (const [key, value] of params.entries()) {
    const cleanKey = key.replace("[]", "");

    if (cleanKey === "category") continue;

    if (Array.isArray(queryState[cleanKey])) {
      // It's a taxonomy array
      const allValues = params.getAll(key);
      queryState[cleanKey] = allValues.map((v) => (isNaN(v) ? v : Number(v)));
    } else if (cleanKey === "page" || cleanKey === "country") {
      // Forced numeric types
      queryState[cleanKey] = Number(value);
    } else {
      // Standard strings (city, search)
      queryState[cleanKey] = value;
    }
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

/**
 * Get the REST URL from the listingSettings store.
 */
export function getRestUrl() {
  const { state } = store("listingSettings");
  return state.config?.restUrl || rest_url("listing/v1/");
}

// Highlight part

export function escapeHtml(str = "") {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

export function escapeRegex(string) {
  return String(string).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

export function highlightTerms(text = "", terms = [], tag = "mark") {
  if (!text || !terms?.length) return escapeHtml(text);

  const sortedTerms = [...terms]
    .filter(Boolean)
    .sort((a, b) => b.length - a.length);
  if (!sortedTerms.length) return escapeHtml(text);

  const regex = new RegExp(`(${sortedTerms.map(escapeRegex).join("|")})`, "gi");

  let result = "";
  let lastIndex = 0;
  let m;
  while ((m = regex.exec(text)) !== null) {
    const i = m.index;
    result += escapeHtml(text.slice(lastIndex, i));
    result += `<${tag}>${escapeHtml(m[0])}</${tag}>`;
    lastIndex = regex.lastIndex;
    if (regex.lastIndex === i) regex.lastIndex++; // guard
  }
  result += escapeHtml(text.slice(lastIndex));
  return result;
}

/**
 * Safe highlighting: returns HTML with <mark> wrappers while escaping all other text.
 * Uses tokenization to avoid messing with already-escaped text.
 */
export function highlightTermsHtml(text = "", terms = [], tag = "mark") {
  if (!text || !terms || !terms.length) {
    return escapeHtml(text);
  }

  const sortedTerms = [...terms]
    .filter(Boolean)
    .sort((a, b) => b.length - a.length);
  if (!sortedTerms.length) return escapeHtml(text);

  const escapedTerms = sortedTerms.map((t) => escapeRegex(t));
  const regex = new RegExp(`(${escapedTerms.join("|")})`, "gi");

  let result = "";
  let lastIndex = 0;
  let m;

  while ((m = regex.exec(text)) !== null) {
    const matchIndex = m.index;
    result += escapeHtml(text.slice(lastIndex, matchIndex));
    result += `<${tag}>${escapeHtml(m[0])}</${tag}>`;
    lastIndex = regex.lastIndex;
    // guard against infinite loop for zero-length matches
    if (regex.lastIndex === matchIndex) regex.lastIndex++;
  }

  result += escapeHtml(text.slice(lastIndex));
  return result;
}
