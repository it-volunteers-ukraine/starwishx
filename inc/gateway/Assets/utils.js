/**
 * Gateway Store — Utilities
 * File: inc/gateway/Assets/utils.js
 */

/**
 * Typed error for REST API failures.
 */
export class RestApiError extends Error {
  /**
   * @param {string} message - Human-readable message (from WP error or HTTP status)
   * @param {string} code    - WordPress error code e.g. 'rest_cookie_invalid_nonce'
   * @param {number} status  - HTTP status code
   * @param {Object|null} fieldErrors - `{field => message}` map from `data.field_errors`
   */
  constructor(message, code = "", status = 0, fieldErrors = null) {
    super(message);
    this.name = "RestApiError";
    this.code = code;
    this.status = status;
    this.fieldErrors = fieldErrors;
  }
}

/**
 * Shared WordPress error codes.
 * Source of truth: inc/shared/Core/AbstractApiController.php
 */
export const WP_ALREADY_AUTHENTICATED = "already_authenticated";

/**
 * Nonce mismatch — browser sent a stale nonce against a changed session.
 * Defined here so action modules can react to it without magic strings.
 */
export const WP_NONCE_INVALID = "rest_cookie_invalid_nonce";

/**
 * Convert kebab-case or snake_case to camelCase.
 * e.g. 'lost-password' → 'lostPassword'
 */
export function toCamelCase(str) {
  return str.replace(/([-_][a-z])/g, (g) => g[1].toUpperCase());
}

/**
 * Centralized fetch with WP nonce management.
 *
 * Implements the same contract as @wordpress/api-fetch nonce middleware:
 * WordPress core registers rest_send_refreshed_nonce() on rest_post_dispatch
 * unconditionally — X-WP-Nonce is present on every REST response including errors.
 *
 * Retry contract:
 *   On rest_cookie_invalid_nonce the 403 response carries the correct nonce for
 *   the new session in X-WP-Nonce. We pass it EXPLICITLY into the retry rather
 *   than relying on Interactivity API Proxy mutation being visible across the
 *   recursive async call — Proxy side-effect visibility is not guaranteed here.
 *   State is still updated for all subsequent calls after the retry completes.
 *
 * @param {Object}  state      - Interactivity API state (contains gatewaySettings)
 * @param {string}  url        - REST endpoint URL
 * @param {Object}  [options]  - fetch options
 * @param {boolean} [_isRetry] - internal: prevents infinite loops
 * @param {string|null} [_retryNonce] - internal: explicit nonce for retry, bypasses Proxy read
 * @returns {Promise<Object>}
 * @throws {RestApiError}
 */
export async function fetchJson(
  state,
  url,
  options = {},
  _isRetry = false,
  _retryNonce = null,
) {
  const { method = "GET", body = null } = options;
  const settings = state?.gatewaySettings ?? {};

  // Use the explicitly passed nonce on retry so we bypass Proxy mutation uncertainty.
  // On initial calls _retryNonce is null and we read from state normally.
  const nonce = _retryNonce ?? settings.nonce ?? "";

  const headers = {
    "X-WP-Nonce": nonce,
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

  // ── WordPress nonce refresh contract ──────────────────────────────────────
  //
  // Capture X-WP-Nonce on every response (success or failure).
  // Best-effort state update for all future calls after this one resolves.
  // The retry path does NOT rely on this mutation — it receives the nonce
  // via _retryNonce instead.
  //
  const refreshedNonce = response.headers.get("X-WP-Nonce");
  if (refreshedNonce && settings.nonce !== refreshedNonce) {
    settings.nonce = refreshedNonce;
  }
  // ──────────────────────────────────────────────────────────────────────────

  if (!response.ok) {
    let errorData = {};
    let message = `${response.status} ${response.statusText}`;
    let fieldErrors = null;

    try {
      errorData = await response.json();
      if (errorData?.message) message = errorData.message;
      // Honors the shared error-shape contract (AbstractApiController):
      // `data.field_errors` routes to inline slots, top-level `message` to banner.
      if (errorData?.data?.field_errors)
        fieldErrors = errorData.data.field_errors;
    } catch (_) {
      // Non-JSON body — keep the HTTP status message
    }

    // ── Nonce race-condition recovery ─────────────────────────────────────
    //
    // Another tab logged in: browser sends new auth cookie but stale nonce.
    // The refreshed nonce from X-WP-Nonce is passed EXPLICITLY to the retry —
    // not read back from state — so mutation visibility is not a factor.
    //
    if (
      !_isRetry &&
      response.status === 403 &&
      errorData?.code === WP_NONCE_INVALID
    ) {
      return fetchJson(state, url, options, true, refreshedNonce);
    }
    // ──────────────────────────────────────────────────────────────────────

    throw new RestApiError(
      message,
      errorData?.code ?? "",
      response.status,
      fieldErrors,
    );
  }

  if (response.status === 204) return {};

  return response.json();
}

/**
 * Client-side validators (UX only — server always re-validates).
 */
export const validators = {
  required: (value) => !!value?.trim(),
  email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
  minLength: (min) => (value) => value?.length >= min,
};

/**
 * Validate a username/nickname against the hydrated UsernamePolicy rules.
 * Returns the translated error message on failure, or null on success.
 * Policy i18n messages come from PHP — no hardcoded strings here.
 *
 * @param {string} value
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
