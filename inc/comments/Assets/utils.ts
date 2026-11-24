/**
 * Comments Store — Utilities
 *
 * Fetching with the WP nonce contract, the response guards and the error
 * text. The nonce lives in state.config (CommentsCore::getConfig()).
 *
 * File: inc/comments/Assets/utils.ts
 */
import { ApiError, isRecord } from "../../shared/Assets/store-utils.ts";
import { commentsStore } from "./stores.ts";
import type {
  Aggregates,
  CommentListResponse,
  CommentNode,
  CommentWriteResponse,
} from "./types.ts";

/** checkRestNonce() / core: the nonce doesn't match the session. */
const NONCE_INVALID = "rest_cookie_invalid_nonce";

interface FetchOptions {
  method?: "GET" | "POST" | "PUT";
  body?: Record<string, unknown>;
}

/**
 * Fetch helper for the comments REST calls, with the same nonce contract
 * as @wordpress/api-fetch's nonce middleware (and gatewayFetch()).
 *
 * Every response's X-WP-Nonce replaces the stored nonce for later calls. A
 * 403 rest_cookie_invalid_nonce (the user logged in or out in another tab)
 * is retried once with the refreshed nonce, when the response carried one.
 *
 * The JSON is checked with `isValid`: a success without the expected body
 * (204, HTML from a proxy) throws instead of reaching the list. A failed
 * response throws ApiError.
 */
export async function commentsFetch<T>(
  url: string,
  isValid: (data: unknown) => data is T,
  options: FetchOptions = {},
  retryNonce?: string,
): Promise<T> {
  const { method = "GET", body } = options;
  const config = commentsStore().state.config;

  const headers: Record<string, string> = {
    "X-WP-Nonce": retryNonce ?? config.nonce,
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
  if (refreshedNonce && config.nonce !== refreshedNonce) {
    config.nonce = refreshedNonce;
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
      error.code === NONCE_INVALID
    ) {
      return commentsFetch(url, isValid, options, refreshedNonce);
    }
    throw error;
  }

  let data: unknown = null;
  try {
    data = await response.json();
  } catch {
    // Not JSON: rejected by isValid below.
  }
  if (!isValid(data)) throw new Error(`Unexpected response from ${url}`);
  return data;
}

/** The text to show for a failed call: the server's message, else `fallback`. */
export function errorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) return error.serverMessage || fallback;
  return fallback;
}

// --- Response guards ----------------------------------------------------

const isAggregates = (data: unknown): data is Aggregates =>
  isRecord(data) &&
  typeof data.count === "number" &&
  typeof data.avg === "number";

/**
 * Enough of a CommentNode for the list to render it: the id the loops key
 * on, the text fields and the replies array. The flags only hide controls.
 */
export const isCommentNode = (data: unknown): data is CommentNode =>
  isRecord(data) &&
  typeof data.id === "number" &&
  typeof data.author === "string" &&
  typeof data.content === "string" &&
  typeof data.date === "string" &&
  (data.rating === null || typeof data.rating === "number") &&
  Array.isArray(data.replies);

export const isListResponse = (data: unknown): data is CommentListResponse =>
  isRecord(data) &&
  Array.isArray(data.items) &&
  data.items.every(isCommentNode) &&
  typeof data.total_pages === "number";

export const isWriteResponse = (data: unknown): data is CommentWriteResponse =>
  isRecord(data) &&
  isCommentNode(data.comment) &&
  isAggregates(data.aggregates);
