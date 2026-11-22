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

export const gatewayActions = {
  /** The view from ?view: the active view and the visibility flags. */
  syncStateFromUrl(): void {
    const { state } = gatewayStore();
    const requested = new URL(window.location.href).searchParams.get("view");
    const view = isFormId(state, requested) ? requested : DEFAULT_VIEW;

    state.activeView = view;
    for (const [id, flag] of Object.entries(state.formMap)) {
      state[flag] = id === view;
    }
  },

  /** A link to another view. withSyncEvent: it cancels the navigation. */
  switchView: withSyncEvent((event: Event): void => {
    const { ref } = getElement();
    if (!(ref instanceof HTMLAnchorElement)) return;
    event.preventDefault();

    // Leave the current form as it started: no stale values or errors.
    const { state } = gatewayStore();
    resetForm(FORM_KEYS[state.activeView]);

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
