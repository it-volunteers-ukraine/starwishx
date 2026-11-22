/**
 * Launchpad Store — Local Utilities
 *
 * Fetching, panel access, validation and scrolling for the Launchpad store.
 * The helpers every store shares are in inc/shared/Assets/store-utils.ts.
 *
 * File: inc/launchpad/Assets/utils.ts
 */
import {
  ApiError,
  historyState,
  isAbortError,
  isRecord,
} from "../../shared/Assets/store-utils.ts";
import { launchpadStore } from "./stores.ts";
import type {
  GeneratedPassword,
  KatottgPlace,
  KnownPanelId,
  LaunchpadState,
  Lazy,
  LoadedPanel,
  MediaResponse,
  NameError,
  OpportunitiesData,
  OpportunityForm,
  OpportunityListResponse,
  OpportunityStatus,
  PanelData,
  PatternPolicy,
  ProfileUpdateResponse,
  SelectionField,
} from "./types.ts";

// --- Fetching -----------------------------------------------------------

/** AbortController per key: a newer request with the key cancels the older. */
const controllers = new Map<string, AbortController>();

interface FetchOptions {
  method?: "GET" | "POST" | "PUT";
  /** FormData goes as is (the browser sets the boundary); else as JSON. */
  body?: FormData | object;
  /** A newer request with the same key cancels this one. */
  abortKey?: string;
}

/** Cancel the request in flight under `abortKey`, if any (it resolves to undefined). */
export function cancelRequest(abortKey: string): void {
  controllers.get(abortKey)?.abort();
  controllers.delete(abortKey);
}

/**
 * Fetch helper for the Launchpad's REST calls: the WP nonce, JSON bodies,
 * per-key cancellation.
 *
 * Resolves to `undefined` when a newer request with the same `abortKey`
 * cancelled it - callers must leave the state alone then. The JSON is
 * checked with `isValid` (the response's shape is the one thing the compiler
 * can't see). A failed response throws ApiError, whose `serverMessage` is
 * the server's translated message.
 */
export async function fetchJson<T>(
  url: string,
  isValid: (data: unknown) => data is T,
  { method = "GET", body, abortKey }: FetchOptions = {},
): Promise<T | undefined> {
  const controller = new AbortController();
  if (abortKey) {
    controllers.get(abortKey)?.abort();
    controllers.set(abortKey, controller);
  }

  const headers: Record<string, string> = {
    "X-WP-Nonce": launchpadStore().state.launchpadSettings.nonce,
    "X-Requested-With": "XMLHttpRequest",
  };
  let payload: BodyInit | undefined;
  if (body instanceof FormData) {
    payload = body;
  } else if (body) {
    headers["Content-Type"] = "application/json";
    payload = JSON.stringify(body);
  }

  try {
    const response = await fetch(url, {
      method,
      credentials: "same-origin",
      signal: controller.signal,
      headers,
      body: payload,
    });

    if (!response.ok) {
      let error: unknown = null;
      try {
        error = await response.json();
      } catch {}
      throw new ApiError(response.status, response.statusText, error);
    }

    const contentType = response.headers.get("content-type") ?? "";
    const data: unknown =
      response.status !== 204 && contentType.includes("application/json")
        ? await response.json()
        : {};
    if (!isValid(data)) {
      throw new Error(`Unexpected response from ${url}`);
    }
    return data;
  } catch (error) {
    // Cancelled on purpose by a newer request: not an error.
    if (isAbortError(error)) return undefined;
    throw error;
  } finally {
    // An aborted request settles after its replacement has registered, so
    // only remove this request's own controller.
    if (abortKey && controllers.get(abortKey) === controller) {
      controllers.delete(abortKey);
    }
  }
}

/** The text to show for a failed call: the server's message when it sent one. */
export function apiErrorMessage(error: unknown): string {
  if (error instanceof ApiError) return error.serverMessage || error.message;
  return error instanceof Error ? error.message : String(error);
}

// The responses, checked at the edge before the store trusts them.

/** Any JSON object (status changes, deletions: nothing is read back). */
export const isObject = (value: unknown): value is Record<string, unknown> =>
  isRecord(value);

/** GET panel/{id}/state - Panels/*::getInitialState(). */
export const isPanelState = isObject;

export function isListResponse(
  value: unknown,
): value is OpportunityListResponse {
  return (
    isRecord(value) &&
    Array.isArray(value.items) &&
    typeof value.total === "number" &&
    typeof value.total_pages === "number"
  );
}

/** GET opportunities/{id} - OpportunitiesService::getSingleOpportunity(). */
export function isOpportunityForm(value: unknown): value is OpportunityForm {
  return (
    isRecord(value) &&
    typeof value.id === "number" &&
    typeof value.title === "string" &&
    Array.isArray(value.category) &&
    Array.isArray(value.beneficiaries)
  );
}

export function isPlaceList(value: unknown): value is KatottgPlace[] {
  return (
    Array.isArray(value) &&
    value.every(
      (place) =>
        isRecord(place) &&
        typeof place.code === "string" &&
        typeof place.name === "string",
    )
  );
}

export function isMediaResponse(value: unknown): value is MediaResponse {
  return isRecord(value) && typeof value.id === "number";
}

/** POST profile / profile/email - the saved profile (ProfileService). */
export const isProfileUpdate = (
  value: unknown,
): value is ProfileUpdateResponse => isRecord(value);

export function isGeneratedPassword(
  value: unknown,
): value is GeneratedPassword {
  return isRecord(value) && typeof value.password === "string";
}

// --- Panels -------------------------------------------------------------

/**
 * A known panel's state once loaded, else undefined. Actions start here and
 * return when the panel isn't loaded: its controls aren't on screen then.
 */
export function loadedPanel<K extends KnownPanelId>(
  state: LaunchpadState,
  id: K,
): LoadedPanel<PanelData[K]> | undefined {
  const panel: Lazy<PanelData[K]> | undefined = state.panels[id];
  return panel?._loaded ? panel : undefined;
}

/** Every status, in the list filter's order (Launchpad\Enums\OpportunityStatus). */
export const STATUSES: readonly OpportunityStatus[] = [
  "draft",
  "pending",
  "publish",
];

/**
 * Does the list's status filter leave a status out? The boxes start all
 * ticked, so this is the user having unticked at least one.
 */
export const isStatusFilterActive = (p: OpportunitiesData): boolean =>
  (p.filters?.statuses.length ?? STATUSES.length) < STATUSES.length;

/** The opportunities panel once loaded (the getters' shorthand). */
export const opportunities = (
  state: LaunchpadState,
): LoadedPanel<OpportunitiesData> | undefined =>
  loadedPanel(state, "opportunities");

/**
 * A panel's state, created when the id isn't hydrated yet (a panel
 * registered after the page was built).
 */
export function ensurePanel(
  state: LaunchpadState,
  panelId: string,
): Lazy<object> {
  const panel = state.panels[panelId];
  if (panel) return panel;
  const created: Lazy<object> = {
    _loaded: false,
    isLoading: false,
    isSaving: false,
    error: null,
  };
  state.panels[panelId] = created;
  return created;
}

/** The pending clear of each flashError() target. */
const errorTimers = new WeakMap<object, ReturnType<typeof setTimeout>>();

/** Show `message` in `target.error` for five seconds. */
export function flashError(
  target: { error: string | null },
  message: string,
): void {
  // One timer per target: a newer message restarts the five seconds rather
  // than being cleared early by the older message's timer. (The store hands
  // out one proxy per object, so the proxy is a stable key.)
  clearTimeout(errorTimers.get(target));
  target.error = message;
  errorTimers.set(
    target,
    setTimeout(() => {
      target.error = null;
      errorTimers.delete(target);
    }, 5000),
  );
}

/**
 * Drop a panel's loaded state: its next visit loads it afresh (after a role
 * change, say). Replaces the state instead of flipping `_loaded`, so the
 * types follow - the stale data goes with it.
 */
export function unloadPanel(
  state: LaunchpadState,
  panelId: KnownPanelId,
): void {
  state.panels[panelId] = {
    _loaded: false,
    isLoading: false,
    isSaving: false,
    error: null,
  };
}

// --- History ------------------------------------------------------------

/**
 * Write the URL as a new Back step (or rewrite this one), keeping core's
 * `wpInteractivityId` in the entry - see historyState(). Entries written as
 * `{}` / `{ panel }` made every Back reload the whole page.
 */
export function writeUrl(url: URL, { replace = false } = {}): void {
  const entry = historyState({ panel: url.searchParams.get("panel") });
  if (replace) window.history.replaceState(entry, "", url);
  else window.history.pushState(entry, "", url);
}

/** The current URL, to edit and hand to writeUrl(). */
export const currentUrl = (): URL => new URL(window.location.href);

// --- Forms --------------------------------------------------------------

/**
 * Normalize a URL field value (mirrors backend InputSanitizer::sanitizeUrl).
 *
 * - Auto-prepends https:// when no scheme is present
 * - Rejects non-http(s) schemes (ftp, mailto, javascript, data, file…)
 * - Returns "" for invalid input so required-field checks catch it
 */
export function normalizeUrl(value: string | null | undefined): string {
  const url = value?.trim() ?? "";
  if (!url) return "";

  // No scheme at all → assume https
  const withScheme = /^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(url)
    ? url
    : `https://${url}`;

  // Only allow http/https — reject everything else
  return /^https?:\/\//i.test(withScheme) ? withScheme : "";
}

/**
 * Limit of a multi-select form field (category, beneficiaries), hydrated
 * from OpportunitiesService via selectionLimits — the same numbers the REST
 * validators enforce. 0 when no limit is known.
 */
export function selectionLimit(
  p: OpportunitiesData | undefined,
  field: SelectionField,
): number {
  return p?.selectionLimits?.[field] ?? 0;
}

/**
 * How many items a multi-select form field holds — the whole array, which is
 * what the REST validator counts (it can include ids without a checkbox).
 */
export function selectionSize(
  p: OpportunitiesData | undefined,
  field: SelectionField,
): number {
  return p?.formData[field].length ?? 0;
}

/**
 * Validate a name field (mirrors backend ProfileService::validateNameField).
 *
 * Allowed: Unicode letters, spaces, hyphens, apostrophes (straight + curly),
 * periods. Empty values are valid (fields are optional).
 */
export function validateName(
  value: string | null | undefined,
  { min = 2, max = 80 }: { min?: number; max?: number } = {},
): NameError | null {
  const t = value?.trim() ?? "";
  if (!t) return null;

  if (t.length < min) return "nameMinLength";
  if (t.length > max) return "nameMaxLength";
  // Must contain at least one letter
  if (!/\p{L}/u.test(t)) return "nameInvalid";
  // Only allowed characters
  if (!/^[\p{L}\s\-’'.]+$/u.test(t)) return "nameInvalid";
  return null;
}

/**
 * Validate a username/nickname against the hydrated UsernamePolicy rules.
 * Returns the translated error message on failure, or null on success.
 * Callers decide whether empty values are allowed — this only checks the
 * character-set / length constraints when a value is present.
 */
export function validateUsername(
  value: string | null | undefined,
  policy: PatternPolicy | undefined,
): string | null {
  if (!policy) return null;
  const { pattern, minLength, maxLength, messages = {} } = policy;
  const str = value ?? "";

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
 * The regex already encodes length + character-set + first-char rules, so
 * a single `messages.invalid` string covers every failure mode.
 */
export function validateMessenger(
  value: string | null | undefined,
  policy: PatternPolicy | undefined,
): string | null {
  if (!policy?.pattern) return null;
  return new RegExp(policy.pattern).test(value ?? "")
    ? null
    : (policy.messages?.invalid ?? "");
}

// --- DOM ----------------------------------------------------------------

/**
 * Scroll the first visible error into view.
 *
 * Targets any `.exclamation-circle__error` element that is currently not
 * hidden — covers both the panel-header banner (when p.error is set) and
 * inline field errors (when p.fieldErrors is populated). When the match
 * sits inside a `.form-field`, we scroll that container instead so the
 * input and its error are both in frame; otherwise (banner case) we scroll
 * the error element itself. The double-rAF waits for the Interactivity API
 * to commit the pending state update so the target element's `hidden`
 * attribute reflects the new state before we query the DOM.
 */
export function scrollToFirstError(root: ParentNode = document): void {
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      const err = root.querySelector(
        ".exclamation-circle__error:not([hidden])",
      );
      if (!err) return;
      const target = err.closest(".form-field") ?? err;
      target.scrollIntoView({ behavior: "smooth", block: "center" });
    });
  });
}

/**
 * Scroll the window to the very top.
 *
 * Called at the success path of form-save actions so the user lands at
 * the top of the page — above the panel, where the full cabinet chrome
 * (header, nav, mobile tab switcher) is visible — instead of sitting at
 * the footer where the submit button was. Honors prefers-reduced-motion.
 */
export function scrollToPageTop(): void {
  const reduced = matchMedia("(prefers-reduced-motion: reduce)").matches;
  window.scrollTo({ top: 0, behavior: reduced ? "auto" : "smooth" });
}

/** The element an event handler is bound to, when it is an HTMLElement. */
export function boundElement(event: Event): HTMLElement | null {
  return event.currentTarget instanceof HTMLElement
    ? event.currentTarget
    : null;
}

/** The element an event started on, when it is an Element. */
export function targetElement(event: Event): Element | null {
  return event.target instanceof Element ? event.target : null;
}

/** Focus `el` when it is focusable (an HTMLElement). */
export function focus(el: Element | null | undefined): void {
  if (el instanceof HTMLElement) el.focus();
}
