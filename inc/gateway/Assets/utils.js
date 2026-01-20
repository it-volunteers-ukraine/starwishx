/**
 * Gateway Store — Utilities
 * File: inc/gateway/Assets/utils.js
 */

/**
 * Centralized fetch with WP nonce.
 *
 * @param {Object} state - The Interactivity API state object (containing gatewaySettings)
 * @param {string} url - The URL to fetch
 * @param {Object} options - Fetch options
 */
export async function fetchJson(state, url, options = {}) {
  const { method = "GET", body = null } = options;
  // Access settings from the passed state, not global/this
  const settings = state.gatewaySettings || {};

  const headers = {
    "X-WP-Nonce": settings.nonce,
    "X-Requested-With": "XMLHttpRequest",
  };
  if (body) headers["Content-Type"] = "application/json";

  const response = await fetch(url, {
    method,
    credentials: "same-origin",
    headers,
    body: body ? JSON.stringify(body) : null,
  });

  if (!response.ok) {
    let message = `${response.status} ${response.statusText}`;
    try {
      const error = await response.json();
      if (error?.message) message = error.message;
    } catch (_) {}
    throw new Error(message);
  }

  if (response.status === 204) return {};
  return await response.json();
}

/**
 * Client-side validators (UX only, server re-validates).
 */
export const validators = {
  required: (value) => !!value?.trim(),
  email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
  minLength: (min) => (value) => value?.length >= min,
};
