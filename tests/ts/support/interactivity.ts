/**
 * A stand-in for @wordpress/interactivity (mocked in tests/ts/setup.ts).
 *
 * The stores read their state through store(namespace) and the element
 * through getElement(); tests put what those return in `fake`. The real
 * package needs a hydrated page; the store code under test only needs these
 * few functions.
 *
 * File: tests/ts/support/interactivity.ts
 */

export const fake: {
  /** store(ns).state, by namespace. */
  stores: Map<string, object>;
  /** getServerState(ns), by namespace. */
  server: Map<string, object>;
  /** getElement().ref */
  ref: HTMLElement | null;
} = {
  stores: new Map(),
  server: new Map(),
  ref: null,
};

export const interactivity = {
  store: (namespace: string) => ({
    state: fake.stores.get(namespace) ?? {},
    actions: {},
  }),
  getServerState: (namespace: string) => fake.server.get(namespace) ?? {},
  getElement: () => ({ ref: fake.ref, attributes: {} }),
  getContext: () => ({}),
  withSyncEvent: <F>(callback: F): F => callback,
};
