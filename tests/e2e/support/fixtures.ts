/**
 * The E2E fixtures:
 *   gateway  a page object for /gateway/: forms, fields, error slots, the
 *            store's state and the REST requests the page made
 *   wp       the test-data helper (support/wp.ts)
 *   (auto)   fails a test on a page error, a console error of ours or an
 *            Interactivity API warning
 *
 * Assertions read structure (which form shows, whether an error slot is
 * filled, URLs, requests), never translated text: the same specs run on the
 * local site (Ukrainian, plugins) and on plain WordPress in CI.
 *
 * File: tests/e2e/support/fixtures.ts
 */
import {
  test as base,
  expect,
  type Locator,
  type Page,
} from "@playwright/test";
import type {
  FormId,
  FormKey,
  Forms,
  GatewayState,
} from "../../../inc/gateway/Assets/types.ts";
import { wp } from "./wp.ts";

declare global {
  interface Window {
    /** Set by a test; gone after a page load. */
    __e2eMarker?: number;
  }
}

/** A form's inputs, by their data-field. */
type FieldOf<K extends FormKey> = Extract<keyof Forms[K], string>;
/** A form's inline error slots. */
type ErrorSlotOf<K extends FormKey> = Forms[K] extends { fieldErrors: infer E }
  ? Extract<keyof E, string>
  : never;

/** The Interactivity API's own warnings, and the store's. */
const IAPI_WARNING = /withSyncEvent|deprecat|interactivity/i;
/**
 * Not ours: failed fetches are logged by the browser for every 4xx the specs
 * provoke, and the local site's Google Translate plugin is noisy.
 */
const NOT_OURS =
  /Failed to load resource|translate\.googleapis|Error: pa\b|__closure_events_fn|Maximum call stack/i;

export class Gateway {
  /** "METHOD route" of every gateway/v1 request, in order. */
  readonly requests: string[] = [];
  readonly page: Page;

  constructor(page: Page) {
    this.page = page;
    page.on("request", (request) => {
      const [, route] = request.url().split("/gateway/v1/");
      if (route !== undefined) {
        this.requests.push(`${request.method()} ${route.split("?")[0]}`);
      }
    });
  }

  /** Opens /gateway/ (optionally a view and query) and waits for hydration. */
  async goto(view?: FormId, query: Record<string, string> = {}): Promise<void> {
    const params = new URLSearchParams(view ? { view, ...query } : query);
    const search = params.size ? `?${params}` : "";
    await this.page.goto(`/gateway/${search}`);
    await this.hydrated();
  }

  /** Opens a full URL (e.g. a reset link) and waits for hydration. */
  async open(url: string): Promise<void> {
    await this.page.goto(url);
    await this.hydrated();
  }

  /**
   * Hydration has bound the forms' submit handlers. Preact keeps an
   * element's listeners in its `l` property; the server-rendered markup has
   * none, so typing before this point would go unseen by the store.
   */
  async hydrated(): Promise<void> {
    await this.page.waitForFunction(() => {
      const forms = document.querySelectorAll("form[data-wp-on--submit]");
      return (
        forms.length > 0 &&
        [...forms].every((f) => typeof Reflect.get(f, "l") === "object")
      );
    });
  }

  form(key: FormKey): Locator {
    return this.page.locator(
      `form[data-wp-on--submit="actions.${key}.submit"]`,
    );
  }

  input<K extends FormKey>(key: K, field: FieldOf<K>): Locator {
    return this.form(key).locator(`[data-field="${field}"]`);
  }

  /** Types into a field the way a person does (input events). */
  async type<K extends FormKey>(
    key: K,
    field: FieldOf<K>,
    value: string,
  ): Promise<void> {
    await this.input(key, field).fill(value);
  }

  /**
   * Submits the form the way Enter or its submit button does
   * (requestSubmit), and the store cancels the navigation. The forms are
   * `novalidate`, so the browser's own constraint checks (required,
   * type=email) don't get in first and the store's checks run - without
   * it, a required field left empty would stop the submit right here.
   */
  async submit(key: FormKey): Promise<void> {
    await this.form(key).evaluate((form) => {
      if (form instanceof HTMLFormElement) form.requestSubmit();
    });
  }

  /** A field's inline error slot. */
  fieldError<K extends FormKey>(key: K, field: ErrorSlotOf<K>): Locator {
    return this.form(key).locator(
      `[data-wp-text="state.forms.${key}.fieldErrors.${String(field)}"]`,
    );
  }

  /** The form's error banner. */
  banner(key: FormKey): Locator {
    return this.form(key).locator(`[data-wp-text="state.forms.${key}.error"]`);
  }

  /** A switchView link in the visible form. */
  link(view: FormId): Locator {
    return this.page
      .locator(
        `a[href="?view=${view}"][data-wp-on--click="actions.switchView"]`,
      )
      .filter({ visible: true })
      .first();
  }

  /**
   * Reads the store's state in the page. `select` is sent as source, so it
   * must be self-contained: no outer variables.
   */
  async state<T>(select: (s: GatewayState) => T): Promise<T> {
    return this.page.evaluate<T>(`(async () => {
      const { store } = await import("@wordpress/interactivity");
      return (${select.toString()})(store("gateway").state);
    })()`);
  }

  /** How many requests went to a gateway/v1 route ("login", "password/lost"). */
  requestsTo(route: string): number {
    return this.requests.filter((r) => r.endsWith(` ${route}`)).length;
  }

  /** Marks the window; a page load clears the mark. */
  async mark(): Promise<void> {
    await this.page.evaluate(() => {
      window.__e2eMarker = 1;
    });
  }

  async isMarked(): Promise<boolean> {
    return this.page.evaluate(() => window.__e2eMarker === 1);
  }
}

interface Fixtures {
  gateway: Gateway;
  wp: typeof wp;
  pageProblems: void;
}

export const test = base.extend<Fixtures>({
  gateway: async ({ page }, use) => {
    await use(new Gateway(page));
  },

  // eslint-disable-next-line no-empty-pattern -- Playwright reads the pattern
  wp: async ({}, use) => {
    await use(wp);
  },

  pageProblems: [
    async ({ page }, use) => {
      const problems: string[] = [];
      page.on("pageerror", (error) => {
        const text = `${error.message}\n${error.stack ?? ""}`;
        if (!NOT_OURS.test(text)) problems.push(`pageerror: ${error.message}`);
      });
      page.on("console", (message) => {
        const text = message.text();
        if (message.type() === "error" && !NOT_OURS.test(text)) {
          problems.push(`console.error: ${text}`);
        } else if (message.type() === "warning" && IAPI_WARNING.test(text)) {
          problems.push(`console.warn: ${text}`);
        }
      });
      await use();
      expect(problems, "page errors and Interactivity API warnings").toEqual(
        [],
      );
    },
    { auto: true },
  ],
});

export { expect };
