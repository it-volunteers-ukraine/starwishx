/**
 * Comments Store — Utilities
 * File: inc/comments/Assets/utils.js
 *
 * Standalone fetch helper for the Comments module.
 * Reads nonce from state.config (hydrated by CommentsCore).
 */

/**
 * Centralized Fetch Helper with WP nonce support.
 *
 * @param {Object} state - The Interactivity API state object (contains config.nonce)
 * @param {string} url - Full API URL
 * @param {Object} options
 * @param {string} [options.method='GET'] - HTTP method
 * @param {Object|null} [options.body=null] - Request body (will be JSON.stringify'd)
 * @returns {Promise<Object>} Parsed JSON response
 * @throws {Error} With message and status properties on failure
 */
export async function fetchJson(
  state,
  url,
  { method = "GET", body = null } = {},
) {
  const config = state.config || {};

  const headers = {
    "X-WP-Nonce": config.nonce,
    "X-Requested-With": "XMLHttpRequest",
  };

  if (body) {
    headers["Content-Type"] = "application/json";
  }

  const response = await fetch(url, {
    method,
    credentials: "same-origin",
    headers,
    body: body ? JSON.stringify(body) : null,
  });

  // Nonce refresh — update for subsequent calls
  const refreshedNonce = response.headers.get("X-WP-Nonce");
  if (refreshedNonce && config.nonce !== refreshedNonce) {
    config.nonce = refreshedNonce;
  }

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
