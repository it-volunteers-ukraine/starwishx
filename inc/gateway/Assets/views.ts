/**
 * Gateway Store — Views
 *
 * Which form is shown: the view from ?view, switching views, and putting a
 * left form back as the server rendered it. Plus the getters. No side
 * effects on import - gateway-store.ts registers the store and listens.
 *
 * File: inc/gateway/Assets/views.ts
 */
import {
  getElement,
  getServerState,
  withSyncEvent,
} from "@wordpress/interactivity";
import { historyState, isRecord } from "../../shared/Assets/store-utils.ts";
import { gatewayStore } from "./stores.ts";
import type {
  CamelCase,
  FormId,
  FormKey,
  GatewayGetters,
  GatewayServerState,
  GatewayState,
} from "./types.ts";

/** The view when ?view is missing or unknown - as page-gateway.php. */
export const DEFAULT_VIEW = "login" satisfies FormId;

/** Each view's form key: AbstractForm::getJsId(), checked by the type. */
export const FORM_KEYS = {
  login: "login",
  register: "register",
  "lost-password": "lostPassword",
  "reset-password": "resetPassword",
} as const satisfies { [Id in FormId]: CamelCase<Id> };

/**
 * ?view as page-gateway.php reads it - sanitize_key(): lower case, only
 * [a-z0-9_-] - so `?view=Register` shows the same form before and after
 * hydration. null when there's no ?view.
 */
export function viewFromUrl(href: string): string | null {
  const view = new URL(href).searchParams.get("view");
  return view === null ? null : view.toLowerCase().replace(/[^a-z0-9_-]/g, "");
}

/**
 * Is `view` a form this store drives (FORM_KEYS) that is registered (the
 * hydrated formMap)? A form added through `gateway_register_forms` has no
 * actions here, and one a plugin removed has no state: both fall back.
 */
export function isFormId(
  state: GatewayState,
  view: string | null,
): view is FormId {
  return (
    view !== null &&
    Object.hasOwn(FORM_KEYS, view) &&
    Object.hasOwn(state.formMap, view)
  );
}

/**
 * Put a form back as the server rendered it, so nothing typed or shown
 * there survives leaving the view. Copies: the server state is read-only.
 */
export function resetForm<K extends FormKey>(key: K): void {
  const initial = getServerState<GatewayServerState>("gateway").forms[key];
  const form = gatewayStore().state.forms[key];
  Object.assign(form, initial);
  if ("fieldErrors" in initial && isRecord(initial.fieldErrors)) {
    Object.assign(form, { fieldErrors: { ...initial.fieldErrors } });
  }
}

export const gatewayGetters = {
  get resetPasswordInputType(): "text" | "password" {
    return this.forms.resetPassword.isPasswordVisible ? "text" : "password";
  },

  get loginPasswordInputType(): "text" | "password" {
    return this.forms.login.isPasswordVisible ? "text" : "password";
  },
} satisfies GatewayGetters & ThisType<GatewayState>;

/**
 * pageshow: a page restored from the back-forward cache comes back as it was
 * left - after a login, with its form stuck on "Logging in..." and a nonce
 * for a session that has changed. Reload instead: page-gateway.php sends a
 * logged-in user on, and a guest gets a fresh page.
 */
export function reloadIfRestored(
  event: Pick<PageTransitionEvent, "persisted">,
): void {
  if (event.persisted) window.location.reload();
}

export const gatewayActions = {
  /**
   * The view from ?view: the active view and the visibility flags. Leaving
   * a view - by a link, Back or Forward - puts its form back as the server
   * rendered it: no stale values or errors when it's shown again.
   */
  syncStateFromUrl(): void {
    const { state } = gatewayStore();
    const requested = viewFromUrl(window.location.href);
    const view = isFormId(state, requested) ? requested : DEFAULT_VIEW;

    // The hydrated activeView can be a plugin's form, which has no key here.
    const left: string = state.activeView;
    if (left !== view && isFormId(state, left)) resetForm(FORM_KEYS[left]);

    state.activeView = view;
    for (const [id, flag] of Object.entries(state.formMap)) {
      state[flag] = id === view;
    }
  },

  /**
   * A link to another view. withSyncEvent: it cancels the navigation.
   * syncStateFromUrl() resets the form it leaves.
   */
  switchView: withSyncEvent((event: Event): void => {
    const { ref } = getElement();
    if (!(ref instanceof HTMLAnchorElement)) return;
    event.preventDefault();

    // historyState(): an entry without core's wpInteractivityId would make
    // Back reload the page.
    window.history.pushState(
      historyState({}),
      "",
      new URL(ref.href, window.location.href),
    );
    gatewayActions.syncStateFromUrl();
  }),
};
