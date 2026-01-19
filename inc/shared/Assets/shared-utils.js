/**
 * Shared utilities for Interactivity API stores.
 * Imported by both gateway-store.js and launchpad launchpad-store.js
 *
 * File: inc/shared/Assets/shared-utils.js
 */

/**
 * Deep clone safe for Interactivity API proxies.
 */
export function deepClone(obj) {
  if (!obj) return obj;
  return JSON.parse(JSON.stringify(obj));
}

/**
 * Convert panel-id (e.g., 'opportunities') to PascalCase (e.g., 'Opportunities')
 * *deprecated
 */
function idToPascal(id) {
  return String(id)
    .split(/[-_]/)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join("");
}

/**
 * Centralized fetch with WP nonce and error handling.
 * @param {Object} settings - Must contain { nonce, restUrl }
 * @param {string} url - Full API URL
 * @param {Object} options - { method, body, signal }
 */
export async function fetchJson(settings, url, options = {}) {
  const { method = "GET", body = null, signal = null } = options;

  const headers = {
    "X-WP-Nonce": settings.nonce,
    "X-Requested-With": "XMLHttpRequest",
  };
  if (body) headers["Content-Type"] = "application/json";

  const response = await fetch(url, {
    method,
    credentials: "same-origin",
    signal,
    headers,
    body: body ? JSON.stringify(body) : null,
  });

  // == HANDLING ERRORS ==
  if (!response.ok) {
    // 1. Default fallback message (Technical)
    let message = `Error ${response.status}: ${response.statusText}`;

    try {
      // 2. Check if the response is actually JSON before parsing
      const contentType = response.headers.get("content-type") || "";

      if (contentType.includes("application/json")) {
        const errorData = await response.json();

        /**
         * Because we standardized PHP to use WP_Error,
         * we are GUARANTEED that the error message is in .message
         */
        if (errorData?.message) {
          message = errorData.message;
        }
      } else {
        /**
         * Fallback: If the server returned HTML (a crash),
         * we don't want to show the whole HTML page to the user.
         */
        message = "A server-side error occurred. Please try again later.";
      }
    } catch (_) {
      // Silent catch: use the default technical message defined above
    }

    const err = new Error(message);
    err.status = response.status;
    throw err; // This lands in the .catch() block of your stores JS actions
  }

  // == HANDLING SUCCESS ==
  if (response.status === 204) return {};

  const contentType = response.headers.get("content-type") || "";
  if (!contentType.includes("application/json")) return {};

  return await response.json();
}
