/**
 * The little DOM the stores touch, for Vitest's node environment: elements
 * the stores narrow with `instanceof`, and window.location / history.
 * No jsdom - nothing here renders.
 *
 * File: tests/ts/support/dom.ts
 */
import { vi } from "vite-plus/test";

class FakeElement {
  readonly dataset: Record<string, string | undefined> = {};
}

class FakeInputElement extends FakeElement {
  value = "";
  checked = false;
}

class FakeTextAreaElement extends FakeElement {
  value = "";
}

class FakeAnchorElement extends FakeElement {
  href = "";
}

export const location = {
  href: "https://site.test/gateway/",
  get origin(): string {
    return new URL(this.href).origin;
  },
  reload: vi.fn<() => void>(),
};

export const history = {
  state: null as unknown,
  pushState(state: unknown, _title: string, url?: string | URL | null): void {
    history.state = state;
    if (url != null) location.href = new URL(url, location.href).href;
  },
  replaceState(
    state: unknown,
    _title: string,
    url?: string | URL | null,
  ): void {
    history.pushState(state, _title, url);
  },
};

export function installDom(): void {
  for (const [name, value] of Object.entries({
    HTMLElement: FakeElement,
    HTMLInputElement: FakeInputElement,
    HTMLTextAreaElement: FakeTextAreaElement,
    HTMLAnchorElement: FakeAnchorElement,
    window: { location, history },
  })) {
    Object.defineProperty(globalThis, name, { value, configurable: true });
  }
}

/** Start each test at `href`, with a fresh history entry from core. */
export function resetDom(href = "https://site.test/gateway/"): void {
  location.href = href;
  location.reload.mockClear();
  history.state = { wpInteractivityId: "core-entry" };
}
