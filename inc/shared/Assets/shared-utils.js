/**
 * Shared utilities for Interactivity API stores.
 * Imported by the contact, listing and popup stores.
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
    } catch {
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

/**
 * Controls Tab can land on (keepTabInside).
 */
export const FOCUSABLE = [
  "a[href]",
  "button:not([disabled])",
  "input:not([disabled])",
  "select:not([disabled])",
  "textarea:not([disabled])",
  "summary",
  '[tabindex]:not([tabindex="-1"])',
].join(",");

/**
 * Keeps Tab inside an open drawer or modal: past its last visible control
 * Tab wraps to the first, Shift+Tab the other way round, and focus that is
 * outside comes back in. The page behind sits under a backdrop, so focus that
 * got there would be invisible. Call it from a withSyncEvent action - it
 * preventDefault()s the keystroke it redirects.
 *
 * @param {KeyboardEvent} event - A Tab keydown.
 * @param {Element} container - The drawer or dialog.
 */
export function keepTabInside(event, container) {
  const candidates = [...container.querySelectorAll(FOCUSABLE)];
  const focusable = candidates.filter((el) => el.checkVisibility());
  if (focusable.length === 0) return;

  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  const current = document.activeElement;
  const outside = !container.contains(current);

  if (event.shiftKey && (outside || current === first)) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && (outside || current === last)) {
    event.preventDefault();
    first.focus();
  }
}
