/**
 * Gateway Interactivity API Store
 *
 * State is hydrated by PHP: the settings and policies (GatewayCore::
 * enqueueAssets) and the forms, their visibility flags and the active view
 * (StateAggregator, from templates/page-gateway.php). None of those keys has
 * a client default - store() merges client state over the server's, so a
 * default would replace it. The client adds only the getters.
 *
 * File: inc/gateway/Assets/gateway-store.ts
 */
import {
  getElement,
  getServerState,
  store,
  withSyncEvent,
} from "@wordpress/interactivity";
import { historyState, isRecord } from "../../shared/Assets/store-utils.ts";
import { loginActions } from "./auth/actions.ts";
import {
  lostPasswordActions,
  resetPasswordActions,
} from "./recovery/actions.ts";
import { registerActions } from "./register/actions.ts";
import { gatewayStore } from "./stores.ts";
import type {
  AnyForm,
  CamelCase,
  FormId,
  FormKey,
  GatewayActions,
  GatewayGetters,
  GatewayServerState,
  GatewayState,
  GatewayStore,
} from "./types.ts";

/** The view when ?view is missing or unknown - as page-gateway.php. */
const DEFAULT_VIEW = "login" satisfies FormId;

/** Each view's form key: AbstractForm::getJsId(), checked by the type. */
const FORM_KEYS = {
  login: "login",
  register: "register",
  "lost-password": "lostPassword",
  "reset-password": "resetPassword",
} as const satisfies { [Id in FormId]: CamelCase<Id> };

/** Is `view` a registered form (a key of the hydrated formMap)? */
function isFormId(state: GatewayState, view: string | null): view is FormId {
  return view !== null && Object.hasOwn(state.formMap, view);
}

/**
 * Put a form back as the server rendered it, so nothing typed or shown
 * there survives leaving the view. Copies: the server state is read-only.
 */
function resetForm<K extends FormKey>(key: K): void {
  const initial = getServerState<GatewayServerState>("gateway").forms[key];
  const form = gatewayStore().state.forms[key];
  Object.assign(form, initial);
  if ("fieldErrors" in initial && isRecord(initial.fieldErrors)) {
    Object.assign(form, { fieldErrors: { ...initial.fieldErrors } });
  }
}

const getters = {
  get currentForm(): AnyForm | undefined {
    return this.forms[FORM_KEYS[this.activeView]];
  },

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

store<GatewayStore>("gateway", {
  state: getters,
  actions: {
    ...gatewayActions,
    // Forms: plain objects; their methods reach the store through
    // gatewayStore(), so `this` doesn't matter.
    login: loginActions,
    register: registerActions,
    lostPassword: lostPasswordActions,
    resetPassword: resetPasswordActions,
  } satisfies GatewayActions,
});

// Plain calls, not through the store proxy: gatewayStore() needs no scope.
const sync = (): void => gatewayActions.syncStateFromUrl();
window.addEventListener("popstate", sync);
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", sync);
} else {
  sync();
}
