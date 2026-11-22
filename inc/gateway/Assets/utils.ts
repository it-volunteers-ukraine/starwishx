/**
 * Gateway Store — Utilities
 *
 * Fetching with the WP nonce contract, client-side validation (UX only: the
 * server always re-validates) and the response guards.
 *
 * File: inc/gateway/Assets/utils.ts
 */
import { ApiError, isRecord } from "../../shared/Assets/store-utils.ts";
import { gatewayStore } from "./stores.ts";
import type {
  GeneratedPasswordResponse,
  LoginResponse,
  MessageResponse,
  PasswordPolicy,
  UsernamePolicy,
} from "./types.ts";

/**
 * The WordPress error codes the Gateway reacts to.
 * Source of truth: inc/shared/Core/AbstractApiController.php
 */
export const WP_ERROR = {
  /** checkLoggedOut(): another tab logged in. */
  alreadyAuthenticated: "already_authenticated",
  /** checkRestNonce() / core: the nonce doesn't match the session. */
  nonceInvalid: "rest_cookie_invalid_nonce",
} as const;

// --- Fetching -------------------------------------------------------------

interface FetchOptions {
  method?: "GET" | "POST";
  body?: Record<string, unknown>;
}

/**
 * Fetch helper for the Gateway's REST calls, with the same nonce contract
 * as @wordpress/api-fetch's nonce middleware.
 *
 * Every response's X-WP-Nonce (WordPress sends a refreshed one with cookie
 * auth) replaces the stored nonce for later calls. A 403
 * rest_cookie_invalid_nonce (another tab logged in or out: new cookie,
 * stale nonce) is retried once - with the refreshed nonce passed
 * explicitly rather than read back from the state proxy, and only when the
 * response carried one: re-sending the stale nonce (and, for login, the
 * password) can't succeed.
 *
 * The JSON is checked with `isValid`. A failed response throws ApiError.
 */
export async function gatewayFetch<T>(
  url: string,
  isValid: (data: unknown) => data is T,
  options: FetchOptions = {},
  retryNonce?: string,
): Promise<T> {
  const { method = "GET", body } = options;
  const settings = gatewayStore().state.gatewaySettings;

  const headers: Record<string, string> = {
    "X-WP-Nonce": retryNonce ?? settings.nonce,
    "X-Requested-With": "XMLHttpRequest",
  };
  if (body) headers["Content-Type"] = "application/json";

  const response = await fetch(url, {
    method,
    credentials: "same-origin",
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  const refreshedNonce = response.headers.get("X-WP-Nonce");
  if (refreshedNonce && settings.nonce !== refreshedNonce) {
    settings.nonce = refreshedNonce;
  }

  if (!response.ok) {
    let errorBody: unknown = null;
    try {
      errorBody = await response.json();
    } catch {
      // Not JSON: the status text is the message.
    }
    const error = new ApiError(response.status, response.statusText, errorBody);

    if (
      retryNonce === undefined &&
      refreshedNonce &&
      response.status === 403 &&
      error.code === WP_ERROR.nonceInvalid
    ) {
      return gatewayFetch(url, isValid, options, refreshedNonce);
    }
    throw error;
  }

  const data: unknown = response.status === 204 ? {} : await response.json();
  if (!isValid(data)) throw new Error(`Unexpected response from ${url}`);
  return data;
}

/** The text to show for a failed call: the server's message when it sent one. */
export function errorMessage(error: unknown): string {
  if (error instanceof ApiError) return error.serverMessage || error.message;
  return error instanceof Error ? error.message : String(error);
}

// --- Response guards ----------------------------------------------------

const isSuccess = (
  data: unknown,
): data is { success: true } & Record<string, unknown> =>
  isRecord(data) && data.success === true;

export const isLoginResponse = (data: unknown): data is LoginResponse =>
  isSuccess(data) && typeof data.redirectTo === "string";

export const isMessageResponse = (data: unknown): data is MessageResponse =>
  isSuccess(data) && typeof data.message === "string";

export const isGeneratedPassword = (
  data: unknown,
): data is GeneratedPasswordResponse =>
  isSuccess(data) && typeof data.password === "string" && data.password !== "";

/**
 * `url` as a URL when it is http(s) on this origin, else null. Where the
 * store navigates to a URL from a response: the server only sends
 * home_url() / admin_url() today, this keeps it that way.
 */
export function sameOriginUrl(url: string): URL | null {
  try {
    const parsed = new URL(url, window.location.href);
    return parsed.origin === window.location.origin &&
      (parsed.protocol === "https:" || parsed.protocol === "http:")
      ? parsed
      : null;
  } catch {
    return null;
  }
}

// --- Validation (UX only) ---------------------------------------------

export const validators = {
  required: (value: string): boolean => value.trim() !== "",
  email: (value: string): boolean => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
};

/**
 * The UsernamePolicy message `value` breaks, or null. The messages are
 * translated in PHP.
 */
export function validateUsername(
  value: string,
  policy: UsernamePolicy | undefined,
): string | null {
  if (!policy) return null;
  const { pattern, minLength, maxLength, messages } = policy;
  if (value.length < minLength) return messages.tooShort;
  if (value.length > maxLength) return messages.tooLong;
  if (pattern && !new RegExp(pattern).test(value)) return messages.invalid;
  return null;
}

/**
 * The PasswordPolicy message `value` breaks, or null. The character classes
 * are PasswordPolicy::validate()'s: change them together.
 */
export function validatePassword(
  value: string,
  policy: PasswordPolicy | undefined,
): string | null {
  const minLength = policy?.minLength ?? 12;
  if (value.length < minLength) return policy?.messages.tooShort ?? "";
  if (
    !/[A-Z]/.test(value) ||
    !/[0-9]/.test(value) ||
    !/[^A-Za-z0-9]/.test(value)
  ) {
    return policy?.messages.tooWeak ?? "";
  }
  return null;
}
