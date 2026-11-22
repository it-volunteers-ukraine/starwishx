/**
 * Listing Store — Utilities
 *
 * Handles URL synchronization, debounced API calls, and standardized fetching.
 * File: inc/listing/Assets/utils.ts
 */
import { favoritesStore, listingStore, settingsStore } from "./stores.ts";
import type {
  HistoryEntry,
  ListFilterKey,
  ListingQuery,
  SearchResponse,
} from "./types.ts";

// --- Narrowing helpers --------------------------------------------------

/** A non-null object whose properties can be read as unknown. */
function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

function isNumberArray(value: unknown): value is number[] {
  return Array.isArray(value) && value.every((v) => typeof v === "number");
}

/** The query's id-list keys, in the order the URL writes them. */
const LIST_KEYS: readonly ListFilterKey[] = [
  "category",
  "country",
  "beneficiaries",
];

export function isListKey(key: unknown): key is ListFilterKey {
  return LIST_KEYS.some((listKey) => listKey === key);
}

/** A fetch cancelled by a newer one (AbortController.abort()). */
export function isAbortError(error: unknown): boolean {
  return error instanceof DOMException && error.name === "AbortError";
}

// --- Timing -------------------------------------------------------------

/**
 * Utility: Debounce function to limit API calls during typing.
 */
export function debounce<A extends unknown[]>(
  func: (...args: A) => void,
  wait: number,
): (...args: A) => void {
  let timeout: ReturnType<typeof setTimeout> | undefined;
  return (...args: A): void => {
    clearTimeout(timeout);
    timeout = setTimeout(() => func(...args), wait);
  };
}

// --- Fetching -----------------------------------------------------------

/**
 * A failed response: its HTTP status, the WP error `code`, the server's own
 * (translated) `serverMessage` and its `fieldErrors` (`data.field_errors`,
 * see AbstractApiController).
 */
export class ListingApiError extends Error {
  override readonly name = "ListingApiError";
  readonly status: number;
  readonly code: string;
  readonly serverMessage: string;
  readonly fieldErrors: Record<string, string> | null;

  constructor(status: number, statusText: string, body: unknown) {
    super(`${status} ${statusText}`);
    const error = isRecord(body) ? body : {};
    const data = isRecord(error.data) ? error.data : {};
    this.status = status;
    this.code = typeof error.code === "string" ? error.code : "";
    this.serverMessage = typeof error.message === "string" ? error.message : "";
    this.fieldErrors = isRecord(data.field_errors)
      ? Object.fromEntries(
          Object.entries(data.field_errors).filter(
            (entry): entry is [string, string] => typeof entry[1] === "string",
          ),
        )
      : null;
  }
}

/** The /search response, checked at the edge before the store trusts it. */
export function isSearchResponse(value: unknown): value is SearchResponse {
  return (
    isRecord(value) &&
    Array.isArray(value.items) &&
    typeof value.total === "number" &&
    typeof value.total_pages === "number"
  );
}

/** AbortController per request id: a newer request cancels the older one. */
const searchControllers = new Map<string, AbortController>();

interface FetchOptions {
  /** A newer request with the same id cancels this one. */
  requestId?: string;
  /** Send the favorites store's nonce (logged-in users). */
  auth?: boolean;
}

/**
 * Standardized Fetch Helper for the Listing Module: GET, the only method the
 * listing's REST API has.
 *
 * Without `auth` the request is anonymous: no X-WP-Nonce, so it runs as a
 * guest's - every guest request does. Core checks a nonce whenever one is
 * sent and answers a stale one (a tab left open overnight, a cached page)
 * with a 403, which used to freeze the filters.
 *
 * With `auth` it carries the nonce the favorites store hydrates
 * (FavoritesCore): fetchResults in grid/actions.ts sets it for logged-in
 * users, whose searches the "Favorites" filter and its count depend on
 * (FavoritesFilter, ListingService::getFacets), and core marks the response
 * private / no-store. A stale nonce comes back as code
 * `rest_cookie_invalid_nonce`; fetchResults then carries on as a guest.
 *
 * The JSON is checked with `isValid` - the response's shape is the one
 * thing the compiler can't see. A failed response throws ListingApiError.
 */
export async function fetchJson<T>(
  url: string,
  isValid: (data: unknown) => data is T,
  { requestId = "main", auth = false }: FetchOptions = {},
): Promise<T> {
  // Cancel previous request with the same ID
  searchControllers.get(requestId)?.abort();

  const controller = new AbortController();
  searchControllers.set(requestId, controller);

  try {
    const headers: Record<string, string> = {
      "X-Requested-With": "XMLHttpRequest",
    };
    if (auth) {
      const nonce = favoritesStore().state.config?.nonce;
      if (nonce) headers["X-WP-Nonce"] = nonce;
    }

    const response = await fetch(url, {
      // Cookies still go along (Polylang's language cookie); with no nonce
      // core treats the request as a guest's.
      credentials: "same-origin",
      signal: controller.signal,
      headers,
    });

    if (!response.ok) {
      let error: unknown = null;
      try {
        error = await response.json();
      } catch {}
      throw new ListingApiError(response.status, response.statusText, error);
    }

    const data: unknown = await response.json();
    if (!isValid(data)) {
      throw new Error(`Unexpected response from ${url}`);
    }
    return data;
  } finally {
    // An aborted request settles after its replacement has registered, so
    // only remove this request's own controller.
    if (searchControllers.get(requestId) === controller) {
      searchControllers.delete(requestId);
    }
  }
}

// --- History ------------------------------------------------------------

/**
 * A plain copy of the query for history.state, which must be structured-
 * cloneable - the store's proxies aren't.
 */
function snapshotQuery(query: ListingQuery): ListingQuery {
  return {
    ...query,
    category: [...query.category],
    country: [...query.country],
    beneficiaries: [...query.beneficiaries],
  };
}

/**
 * history.state for an entry the listing writes: the query, next to what the
 * current entry already holds. Core's Interactivity API runtime stamps the
 * page's entry with `wpInteractivityId` and reloads the page on Back/Forward
 * to any entry without it (it takes it for another session's) - entries
 * written as just `{ query }` made every Back a full page load.
 */
export function historyEntry(query: ListingQuery): HistoryEntry {
  const current: unknown = window.history.state;
  return {
    ...(isRecord(current) ? current : {}),
    query: snapshotQuery(query),
  };
}

/**
 * The query a history entry carries, taking only well-typed keys: an entry
 * may predate a filter, or not be the listing's at all.
 */
export function historyQuery(
  state: unknown,
): Partial<ListingQuery> | undefined {
  if (!isRecord(state) || !isRecord(state.query)) return undefined;
  const saved = state.query;
  const query: Partial<ListingQuery> = {};

  if (typeof saved.s === "string") query.s = saved.s;
  if (typeof saved.location === "string") query.location = saved.location;
  if (typeof saved.favorites === "boolean") query.favorites = saved.favorites;
  if (typeof saved.page === "number") query.page = saved.page;
  for (const key of LIST_KEYS) {
    const ids = saved[key];
    if (isNumberArray(ids)) query[key] = ids;
  }

  return query;
}

/**
 * Synchronize the Store State to the Browser URL.
 * Converts the 'query' object into URL Search Parameters.
 *
 * Each call is one Back step (pushState) unless `replace` is set, which
 * rewrites the current entry instead: Load more, typing that continues a
 * search, and Back/Forward itself (see filters.restoreFromHistory).
 */
export function syncStateToUrl(
  query: ListingQuery,
  { replace = false }: { replace?: boolean } = {},
): void {
  const params = new URLSearchParams();

  // Category facets tree for slug serialization
  const { state } = listingStore();
  const { config } = settingsStore().state;
  const categoryTree = state.facets["category-oportunities"];

  for (const [key, value] of Object.entries(query)) {
    if (!value || (Array.isArray(value) && value.length === 0)) continue;

    //! avoid write default page=1 to URL — it's the implicit default
    if (key === "page" && value === 1) continue;

    // Smart category: parent selected → slug, children only → numeric IDs
    if (key === "category" && Array.isArray(value) && categoryTree.length > 0) {
      for (const node of categoryTree) {
        if (value.includes(node.id)) {
          // Parent fully selected → write slug, skip children
          params.append(key, node.slug);
        } else {
          for (const child of node.children) {
            if (value.includes(child.id)) {
              params.append(key, String(child.id));
            }
          }
        }
      }
    } else if (key === "country" && Array.isArray(value)) {
      // Country: emit alpha-2 code (?country=pl) for SEO. Look up each
      // selected id in facets to get its code; fall back to the numeric
      // id only if the facet entry is missing (orphaned id, or facets
      // not yet hydrated). The server-side 301 catches that fallback.
      for (const id of value) {
        const country = state.facets.country.find((c) => c.id === id);
        params.append(key, country?.code || String(id));
      }
    } else if (Array.isArray(value)) {
      // Clean URL: ?beneficiaries=75 (no brackets, repeated key)
      for (const id of value) params.append(key, String(id));
    } else {
      // Boolean filters (favorites): ?favorites=1, not "true"
      params.set(key, value === true ? "1" : String(value));
    }
  }

  // Base path: use archive root when pretty URLs are active to avoid
  // stale path segments (e.g., /opportunities/old-slug/ → /opportunities/)
  const basePath = config.prettyCategoryUrls
    ? (config.archivePath || "/opportunities/").replace(/\/?$/, "/")
    : window.location.pathname;

  // Pretty URL: single parent category slug with no other active filters
  if (config.prettyCategoryUrls) {
    const [slug, ...others] = params.getAll("category");
    if (slug !== undefined && others.length === 0 && isSlug(slug)) {
      const otherKeys = [...new Set(params.keys())].filter(
        (k) => k !== "category",
      );
      if (
        otherKeys.length === 0 ||
        (otherKeys.length === 1 && otherKeys[0] === "page")
      ) {
        params.delete("category");
        const remaining = params.toString();
        const prettyUrl = remaining
          ? `${basePath}${slug}/?${remaining}`
          : `${basePath}${slug}/`;
        writeHistory(query, prettyUrl, replace);
        return;
      }
    }
  }

  const newUrl = params.toString()
    ? `${basePath}?${params.toString()}`
    : basePath;

  writeHistory(query, newUrl, replace);
}

/** A category URL value that is a slug rather than a term id. */
function isSlug(value: string): boolean {
  return Number.isNaN(Number(value));
}

/**
 * Write `url` to history with a snapshot of the query, which Back/Forward
 * restores. A URL equal to the current one rewrites the entry rather than
 * adding a second, identical Back step.
 */
function writeHistory(
  query: ListingQuery,
  url: string,
  replace: boolean,
): void {
  const unchanged = url === window.location.pathname + window.location.search;
  const entry = historyEntry(query);
  if (replace || unchanged) {
    window.history.replaceState(entry, "", url);
  } else {
    window.history.pushState(entry, "", url);
  }
}

/**
 * Synchronize the Browser URL to the Store State.
 * Used on 'popstate' for entries the listing didn't write.
 *
 * @param queryState - The reactive state.query object to update.
 */
export function syncUrlToState(queryState: ListingQuery): void {
  const params = new URLSearchParams(window.location.search);

  // Category facets tree for slug resolution
  const { state } = listingStore();
  const { config } = settingsStore().state;
  const categoryTree = state.facets["category-oportunities"];

  // Pretty URL: extract category slug from pathname into params
  // so the existing resolution logic below handles it uniformly
  if (config.prettyCategoryUrls && !params.has("category")) {
    const archivePath = (config.archivePath || "/opportunities/").replace(
      /\/$/,
      "",
    );
    const pathname = window.location.pathname.replace(/\/$/, "");
    if (pathname.startsWith(archivePath + "/")) {
      const slug = pathname.slice(archivePath.length + 1).split("/")[0];
      if (slug && slug !== "page") {
        params.append("category", slug);
      }
    }
  }

  // Reset every filter before filling from the URL
  Object.assign(queryState, {
    s: "",
    category: [],
    country: [],
    location: "",
    beneficiaries: [],
    favorites: false,
    page: 1,
  } satisfies ListingQuery);

  // Resolve category first: slugs → parent + children IDs, numeric → as-is
  const categoryRaw = params.getAll("category");
  if (categoryRaw.length > 0) {
    const ids: number[] = [];
    for (const val of categoryRaw) {
      if (isSlug(val)) {
        // Slug → find parent in tree, expand to parent + all children
        const parent = categoryTree.find((n) => n.slug === val);
        if (parent) {
          ids.push(parent.id, ...parent.children.map((c) => c.id));
        }
      } else {
        ids.push(Number(val));
      }
    }
    queryState.category = [...new Set(ids)];
  }

  // Resolve country: alpha-2 codes → ids via facets, numerics → as-is.
  // The server-side 301 normalizes numeric URLs to slugs on full page
  // loads; this branch keeps back/forward (popstate) navigation robust
  // when an older state object is restored from history.
  const countryRaw = params.getAll("country");
  if (countryRaw.length > 0) {
    const ids: number[] = [];
    for (const val of countryRaw) {
      if (isSlug(val)) {
        const country = state.facets.country.find(
          (c) => c.code === val.toLowerCase(),
        );
        if (country) ids.push(country.id);
      } else {
        ids.push(Number(val));
      }
    }
    queryState.country = [...new Set(ids)];
  }

  // The remaining filters. Other params (utm_*, fbclid...) aren't filters:
  // they stay out of the query, so they never reach the API or the URL the
  // listing writes.
  for (const [key, value] of params.entries()) {
    const cleanKey = key.replace("[]", "");

    switch (cleanKey) {
      case "beneficiaries":
        queryState.beneficiaries = params
          .getAll(key)
          .map(Number)
          .filter((id) => !Number.isNaN(id));
        break;
      case "page":
        queryState.page = Number(value);
        break;
      case "favorites":
        // Boolean, and the user's own list: guests keep false, as the
        // server's ListingQuery does for them.
        queryState.favorites =
          state.isUserLoggedIn && (value === "1" || value === "true");
        break;
      case "s":
      case "location":
        queryState[cleanKey] = value;
        break;
    }
  }
}

// --- State --------------------------------------------------------------

/**
 * Utility: Safely merges source objects into a target object,
 * preserving Getters/Setters instead of evaluating them - so a getter's
 * `this` stays the reactive state. Returns the target, typed as all three.
 */
export function extendState<
  T extends object,
  A extends object,
  B extends object,
>(target: T, a: A, b: B): T & A & B;
export function extendState(target: object, ...sources: object[]): object {
  for (const source of sources) {
    Object.defineProperties(target, Object.getOwnPropertyDescriptors(source));
  }
  return target;
}

// --- Highlight ----------------------------------------------------------

function escapeHtml(text: string): string {
  return text
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function escapeRegex(text: string): string {
  return text.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

/**
 * `text` as HTML with each of `terms` wrapped in `<tag>`; everything else is
 * escaped, so the result is safe for innerHTML.
 */
export function highlightTerms(
  text: string,
  terms: readonly string[],
  tag = "mark",
): string {
  const sortedTerms = terms
    .filter((term) => term !== "")
    .sort((a, b) => b.length - a.length);
  if (!text || sortedTerms.length === 0) return escapeHtml(text);

  const regex = new RegExp(`(${sortedTerms.map(escapeRegex).join("|")})`, "gi");

  let result = "";
  let lastIndex = 0;
  let match: RegExpExecArray | null;
  while ((match = regex.exec(text)) !== null) {
    const i = match.index;
    result += escapeHtml(text.slice(lastIndex, i));
    result += `<${tag}>${escapeHtml(match[0])}</${tag}>`;
    lastIndex = regex.lastIndex;
    if (regex.lastIndex === i) regex.lastIndex++; // guard
  }
  result += escapeHtml(text.slice(lastIndex));
  return result;
}
