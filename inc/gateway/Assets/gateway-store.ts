/**
 * Gateway Interactivity API Store
 *
 * State is hydrated by PHP: the settings and policies (GatewayCore::
 * enqueueAssets) and the forms, their visibility flags and the active view
 * (StateAggregator, from templates/page-gateway.php). None of those keys has
 * a client default - store() merges client state over the server's, so a
 * default would replace it. The client adds only the getters.
 *
 * The entry point: registers the store and follows the URL. The logic is in
 * views.ts and the forms' action modules (tests/ts imports those).
 *
 * File: inc/gateway/Assets/gateway-store.ts
 */
import { store } from "@wordpress/interactivity";
import { loginActions } from "./auth/actions.ts";
import {
  lostPasswordActions,
  resetPasswordActions,
} from "./recovery/actions.ts";
import { registerActions } from "./register/actions.ts";
import type { GatewayActions, GatewayStore } from "./types.ts";
import { gatewayActions, gatewayGetters } from "./views.ts";

store<GatewayStore>("gateway", {
  state: gatewayGetters,
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
