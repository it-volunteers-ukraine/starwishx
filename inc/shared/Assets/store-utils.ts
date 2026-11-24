/**
 * Shared Store Utilities (TypeScript)
 *
 * Helpers every Interactivity API store needs, written once: getter mixing,
 * history entries the core runtime accepts, and narrowing. Imported by the
 * TypeScript stores (inc/listing, inc/launchpad) and bundled into each.
 *
 * File: inc/shared/Assets/store-utils.ts
 */
import type { withSyncEvent } from "@wordpress/interactivity";

/**
 * What withSyncEvent() returns: an action that may use the event
 * synchronously (preventDefault, currentTarget). Its parameter type is
 * `Function`, so the wrapped callback's own signature is not kept.
 */
export type SyncAwareAction = ReturnType<typeof withSyncEvent>;

// --- Narrowing ----------------------------------------------------------

/** A non-null object whose properties can be read as unknown. */
export function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

/**
 * Is `key` one of `keys`? Narrows a string from the DOM (a data-field, an
 * input's value) to the keys an action may write.
 */
export function isKeyOf<const K extends string>(
  keys: readonly K[],
  key: string | undefined,
): key is K {
  return keys.some((known) => known === key);
}

/** A fetch cancelled through its AbortController. */
export function isAbortError(error: unknown): boolean {
  return error instanceof DOMException && error.name === "AbortError";
}

// --- Fetching ---------------------------------------------------------

/**
 * A failed response: its HTTP status, the WP error `code`, the server's own
 * (translated) `serverMessage` and its `fieldErrors` (`data.field_errors`,
 * see AbstractApiController). Thrown by the stores' fetch helpers.
 */
export class ApiError extends Error {
  override readonly name = "ApiError";
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

// --- State --------------------------------------------------------------

/** `A | B | C` -> `A & B & C`. */
type UnionToIntersection<U> = (
  U extends unknown ? (union: U) => void : never
) extends (intersection: infer I) => void
  ? I
  : never;

/**
 * Mixes the sources' properties into the target, copying getters and setters
 * as accessors instead of evaluating them - so a getter's `this` stays the
 * reactive state. Returns the target, typed as all of them.
 */
export function extendState<T extends object, S extends object[]>(
  target: T,
  ...sources: S
): T & UnionToIntersection<S[number]>;
export function extendState(target: object, ...sources: object[]): object {
  for (const source of sources) {
    Object.defineProperties(target, Object.getOwnPropertyDescriptors(source));
  }
  return target;
}

// --- History ------------------------------------------------------------

/**
 * history.state for a new or rewritten entry: `extra` next to what the
 * current entry already holds. Core's Interactivity API runtime stamps the
 * page's entry with `wpInteractivityId` and reloads the page on Back/Forward
 * to any non-null entry without it (it takes it for another session's), so
 * an entry written as just `{ panel }` or `{}` makes every Back a full page
 * load.
 */
export function historyState<T extends object>(
  extra: T,
): Record<string, unknown> & T {
  const current: unknown = window.history.state;
  return { ...(isRecord(current) ? current : {}), ...extra };
}
