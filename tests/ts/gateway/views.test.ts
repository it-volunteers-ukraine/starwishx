/**
 * Gateway views: ?view to the visible form, switching views, the getters.
 *
 * File: tests/ts/gateway/views.test.ts
 */
import { describe, expect, it } from "vite-plus/test";
import {
  gatewayActions,
  gatewayGetters,
  isFormId,
  reloadIfRestored,
} from "../../../inc/gateway/Assets/views.ts";
import type { GatewayState } from "../../../inc/gateway/Assets/types.ts";
import { history, location, resetDom } from "../support/dom.ts";
import { installGateway, serverState } from "../support/gateway.ts";
import { fake } from "../support/interactivity.ts";

const flags = (state: GatewayState) =>
  [
    state.isLoginActive,
    state.isRegisterActive,
    state.isLostPasswordActive,
    state.isResetPasswordActive,
  ]
    .map(Number)
    .join("");

describe("syncStateFromUrl", () => {
  it.each([
    ["", "login", "1000"],
    ["?view=register", "register", "0100"],
    ["?view=lost-password", "lost-password", "0010"],
    ["?view=reset-password&key=k&login=l", "reset-password", "0001"],
    ["?view=foo", "login", "1000"], // unknown: login, as the server renders
    ["?view=__proto__", "login", "1000"],
    ["?view=", "login", "1000"],
    // Read as sanitize_key() reads it on the server: lower case, [a-z0-9_-].
    ["?view=Register", "register", "0100"],
    ["?view=lost-password!", "lost-password", "0010"],
  ])("%s -> %s", (query, view, expected) => {
    resetDom(`https://site.test/gateway/${query}`);
    const state = installGateway();
    gatewayActions.syncStateFromUrl();
    expect(state.activeView).toBe(view);
    expect(flags(state)).toBe(expected);
  });

  it("a registered form the store has no actions for falls back", () => {
    const state = installGateway();
    const formMap: Record<string, string> = state.formMap;
    formMap["magic-link"] = "isMagicLinkActive";
    expect(isFormId(state, "magic-link")).toBe(false);
  });

  it("a built-in form a plugin removed falls back", () => {
    resetDom("https://site.test/gateway/?view=register");
    const state = installGateway();
    const { register: _removed, ...rest } = state.formMap;
    Object.defineProperty(state, "formMap", { value: rest });
    gatewayActions.syncStateFromUrl();
    expect(state.activeView).toBe("login");
  });

  it("Back or Forward (popstate) puts the form it leaves back as it started", () => {
    resetDom("https://site.test/gateway/?view=register");
    const state = installGateway();
    gatewayActions.syncStateFromUrl();
    Object.assign(state.forms.register, { username: "anna", error: "Taken." });
    state.forms.register.fieldErrors.email = "Bad.";

    // What Back does before popstate fires: the URL changes under the page.
    location.href = "https://site.test/gateway/";
    gatewayActions.syncStateFromUrl();

    expect(state.activeView).toBe("login");
    expect(state.forms.register).toEqual(serverState().forms.register);
  });

  it("the first sync keeps what's typed in the view it shows", () => {
    const state = installGateway();
    state.forms.login.username = "anna";
    gatewayActions.syncStateFromUrl();
    expect(state.forms.login.username).toBe("anna");
  });

  it("a plugin's view hydrated as active is left without a reset", () => {
    const state = installGateway();
    const formMap: Record<string, string> = state.formMap;
    formMap["magic-link"] = "isMagicLinkActive";
    // What page-gateway.php hydrates for ?view=magic-link.
    Object.assign(state, { activeView: "magic-link" });

    expect(() => gatewayActions.syncStateFromUrl()).not.toThrow();
    expect(state.activeView).toBe("login");
  });
});

describe("reloadIfRestored (pageshow)", () => {
  it("a page back from the back-forward cache reloads", () => {
    reloadIfRestored({ persisted: true });
    expect(location.reload).toHaveBeenCalledTimes(1);
  });

  it("a fresh load doesn't", () => {
    reloadIfRestored({ persisted: false });
    expect(location.reload).not.toHaveBeenCalled();
  });
});

describe("switchView", () => {
  /** A click on a view link: switchView reads getElement().ref. */
  function clickLink(href: string): Event {
    const link = new HTMLAnchorElement();
    link.href = href;
    fake.ref = link;
    const event = new Event("click", { cancelable: true });
    gatewayActions.switchView(event);
    return event;
  }

  it("shows the linked view, keeps core's history id, cancels the navigation", () => {
    const state = installGateway();
    const event = clickLink("https://site.test/gateway/?view=register");

    expect(event.defaultPrevented).toBe(true);
    expect(location.href).toBe("https://site.test/gateway/?view=register");
    expect(history.state).toEqual({ wpInteractivityId: "core-entry" });
    expect(state.activeView).toBe("register");
    expect(flags(state)).toBe("0100");
  });

  it("puts the form it leaves back as the server rendered it", () => {
    const state = installGateway();
    const form = state.forms.login;
    form.username = "anna";
    form.rememberMe = true;
    form.error = "Wrong.";
    form.fieldErrors.password = "required";

    clickLink("https://site.test/gateway/?view=register");

    expect(form).toEqual({
      username: "",
      password: "",
      rememberMe: false,
      isPasswordVisible: false,
      isSubmitting: false,
      error: null,
      fieldErrors: { username: null, password: null },
    });
    // A copy, not the read-only server object.
    form.fieldErrors.username = "x";
    expect(fake.server.get("gateway")).toMatchObject({
      forms: { login: { fieldErrors: { username: null } } },
    });
  });

  it("ignores anything but a link", () => {
    const state = installGateway();
    fake.ref = new HTMLInputElement();
    const event = new Event("click", { cancelable: true });
    gatewayActions.switchView(event);
    expect(event.defaultPrevented).toBe(false);
    expect(state.activeView).toBe("login");
  });
});

describe("getters", () => {
  it("password input types follow visibility", () => {
    const state = installGateway();
    const read = (name: "loginPasswordInputType" | "resetPasswordInputType") =>
      Object.getOwnPropertyDescriptor(gatewayGetters, name)?.get?.call(state);

    expect(read("loginPasswordInputType")).toBe("password");
    state.forms.login.isPasswordVisible = true;
    expect(read("loginPasswordInputType")).toBe("text");
    expect(read("resetPasswordInputType")).toBe("text");
    state.forms.resetPassword.isPasswordVisible = false;
    expect(read("resetPasswordInputType")).toBe("password");
  });
});
