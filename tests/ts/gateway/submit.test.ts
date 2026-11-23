/**
 * The forms' submits, end to end inside the store: client validation stops
 * a request, a success goes where it should (and only same-origin), a reset
 * leaves no key behind, a changed session reloads.
 *
 * File: tests/ts/gateway/submit.test.ts
 */
import { beforeEach, describe, expect, it, vi } from "vite-plus/test";
import { loginActions } from "../../../inc/gateway/Assets/auth/actions.ts";
import {
  lostPasswordActions,
  resetPasswordActions,
} from "../../../inc/gateway/Assets/recovery/actions.ts";
import { registerActions } from "../../../inc/gateway/Assets/register/actions.ts";
import type { GatewayState } from "../../../inc/gateway/Assets/types.ts";
import type { SyncAwareAction } from "../../../inc/shared/Assets/store-utils.ts";
import { history, location, resetDom } from "../support/dom.ts";
import { BASE_URL, installGateway, REST_URL } from "../support/gateway.ts";

const fetchMock = vi.fn<typeof fetch>();
let state: GatewayState;

const json = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });

/**
 * Submit through the action, as data-wp-on--submit does. withSyncEvent()
 * types the action as `Function`, so the call is typed here.
 */
async function submit(action: SyncAwareAction): Promise<Event> {
  const event = new Event("submit", { cancelable: true });
  const done: unknown = action(event);
  await done;
  return event;
}

/** Start a submit without waiting for it: the request may still be open. */
function start(action: SyncAwareAction): Promise<unknown> {
  const done: unknown = action(new Event("submit", { cancelable: true }));
  return Promise.resolve(done);
}

/**
 * A fetch that stays open until the test answers it (ES2024
 * Promise.withResolvers), to look at a form while its request runs.
 */
function pendingFetch(): (response: Response) => void {
  const { promise, resolve } = Promise.withResolvers<Response>();
  fetchMock.mockReturnValueOnce(promise);
  return resolve;
}

/** The JSON bodies sent, by route. */
const sent = () =>
  fetchMock.mock.calls.map(([input, init]): [string, unknown] => {
    const url =
      typeof input === "string"
        ? input
        : input instanceof URL
          ? input.href
          : input.url;
    const body: unknown =
      typeof init?.body === "string" ? JSON.parse(init.body) : undefined;
    return [url.replace(REST_URL, ""), body];
  });

beforeEach(() => {
  state = installGateway();
  fetchMock.mockReset();
  vi.stubGlobal("fetch", fetchMock);
});

describe("login", () => {
  it("empty fields: inline errors, no request", async () => {
    state.forms.login.username = "   ";
    const event = await submit(loginActions.submit);
    expect(event.defaultPrevented).toBe(true);
    expect(state.forms.login.fieldErrors).toEqual({
      username: "username required",
      password: "password required",
    });
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("success: goes to the server's same-origin redirect, still busy", async () => {
    Object.assign(state.forms.login, {
      username: "anna",
      password: "pw",
      rememberMe: true,
    });
    fetchMock.mockResolvedValue(
      json({ success: true, redirectTo: "https://site.test/launchpad/" }),
    );
    await submit(loginActions.submit);
    expect(sent()).toEqual([
      ["login", { username: "anna", password: "pw", remember: true }],
    ]);
    expect(location.href).toBe("https://site.test/launchpad/");
    // The browser is leaving: no second click until it has.
    expect(state.forms.login.isSubmitting).toBe(true);
  });

  it("a foreign redirect goes to the gateway instead", async () => {
    Object.assign(state.forms.login, { username: "anna", password: "pw" });
    fetchMock.mockResolvedValue(
      json({ success: true, redirectTo: "https://evil.test/" }),
    );
    await submit(loginActions.submit);
    expect(location.href).toBe(BASE_URL);
  });

  it("wrong password: banner, form usable again", async () => {
    Object.assign(state.forms.login, { username: "anna", password: "pw" });
    fetchMock.mockResolvedValue(
      json(
        { code: "auth_failed", message: "Invalid username or password." },
        401,
      ),
    );
    await submit(loginActions.submit);
    expect(state.forms.login.error).toBe("Invalid username or password.");
    expect(state.forms.login.isSubmitting).toBe(false);
  });

  it("another tab logged in: reload", async () => {
    Object.assign(state.forms.login, { username: "anna", password: "pw" });
    fetchMock.mockResolvedValue(
      json({ code: "already_authenticated", message: "x" }, 403),
    );
    await submit(loginActions.submit);
    expect(location.reload).toHaveBeenCalledTimes(1);
    expect(state.forms.login.error).toBeNull();
  });

  it("while the request is open: busy, and a second submit sends nothing", async () => {
    Object.assign(state.forms.login, { username: "anna", password: "pw" });
    const answer = pendingFetch();

    const first = start(loginActions.submit);
    expect(state.forms.login.isSubmitting).toBe(true);
    await submit(loginActions.submit);
    expect(fetchMock).toHaveBeenCalledTimes(1);

    answer(json({ code: "auth_failed", message: "Wrong." }, 401));
    await first;
    expect(state.forms.login.isSubmitting).toBe(false);
  });
});

describe("register", () => {
  it("policy errors inline, no request", async () => {
    Object.assign(state.forms.register, { username: "a b", email: "nope" });
    await submit(registerActions.submit);
    expect(state.forms.register.fieldErrors).toEqual({
      username: "u invalid",
      email: "email invalid",
    });
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("server field errors go inline", async () => {
    Object.assign(state.forms.register, { username: "anna", email: "a@b.co" });
    fetchMock.mockResolvedValue(
      json(
        {
          code: "invalid_data",
          message: "Please correct the highlighted fields.",
          data: {
            field_errors: { email: "This email domain doesn't accept mail." },
          },
        },
        422,
      ),
    );
    await submit(registerActions.submit);
    expect(state.forms.register.fieldErrors.email).toBe(
      "This email domain doesn't accept mail.",
    );
    expect(state.forms.register.error).toBeNull();
  });

  it("a taken username (409) shows at the field, not in the banner", async () => {
    Object.assign(state.forms.register, { username: "anna", email: "a@b.co" });
    fetchMock.mockResolvedValue(
      json(
        {
          code: "username_exists",
          message: "This username is already taken.",
          data: {
            status: 409,
            field_errors: { username: "This username is already taken." },
          },
        },
        409,
      ),
    );
    await submit(registerActions.submit);
    expect(state.forms.register.fieldErrors).toEqual({
      username: "This username is already taken.",
      email: null,
    });
    expect(state.forms.register.error).toBeNull();
    expect(state.forms.register.isSubmitting).toBe(false);
  });

  it("success clears the form", async () => {
    Object.assign(state.forms.register, { username: "anna", email: "a@b.co" });
    fetchMock.mockResolvedValue(
      json({ success: true, message: "Check your email." }),
    );
    await submit(registerActions.submit);
    expect(state.forms.register).toMatchObject({
      success: true,
      username: "",
      email: "",
    });
  });
});

describe("lost password", () => {
  it("whitespace: inline error, no request", async () => {
    state.forms.lostPassword.userLogin = "  ";
    await submit(lostPasswordActions.submit);
    expect(state.forms.lostPassword.fieldErrors.userLogin).toBe(
      "login required",
    );
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("sends user_login, shows success", async () => {
    state.forms.lostPassword.userLogin = "anna";
    fetchMock.mockResolvedValue(
      json({ success: true, message: "If an account exists…" }),
    );
    await submit(lostPasswordActions.submit);
    expect(sent()).toEqual([["password/lost", { user_login: "anna" }]]);
    expect(state.forms.lostPassword.success).toBe(true);
  });

  it("a rate limit shows in the banner", async () => {
    state.forms.lostPassword.userLogin = "anna";
    fetchMock.mockResolvedValue(
      json({ code: "rate_limited", message: "Too many attempts." }, 429),
    );
    await submit(lostPasswordActions.submit);
    expect(state.forms.lostPassword).toMatchObject({
      error: "Too many attempts.",
      success: false,
      isSubmitting: false,
    });
  });

  it("a stale nonce reloads (a guest gets no fresh nonce to retry with)", async () => {
    state.forms.lostPassword.userLogin = "anna";
    fetchMock.mockResolvedValue(
      json({ code: "rest_cookie_invalid_nonce", message: "x" }, 403),
    );
    await submit(lostPasswordActions.submit);
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(location.reload).toHaveBeenCalledTimes(1);
  });
});

describe("reset password", () => {
  const resetUrl =
    "https://site.test/gateway/?view=reset-password&key=K3y&login=anna";

  it("a weak password: banner, no request", async () => {
    state.forms.resetPassword.newPassword = "weakpassword";
    await submit(resetPasswordActions.submit);
    expect(state.forms.resetPassword.error).toBe("too weak");
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("success: sends key + login, clears the secret, drops the key from history", async () => {
    vi.useFakeTimers();
    resetDom(resetUrl);
    state.forms.resetPassword.newPassword = "Str0ng!Passw0rd";
    fetchMock.mockResolvedValue(
      json({ success: true, message: "Password reset successfully!" }),
    );

    await submit(resetPasswordActions.submit);

    expect(sent()).toEqual([
      [
        "password/reset",
        { login: "anna", key: "K3y", password: "Str0ng!Passw0rd" },
      ],
    ]);
    expect(state.forms.resetPassword).toMatchObject({
      success: true,
      successMessage: "Password reset successfully!",
      newPassword: "",
    });
    expect(location.href).toBe(BASE_URL); // replaceState: no key in the URL
    expect(history.state).toEqual({ wpInteractivityId: "core-entry" });

    location.href = "https://site.test/elsewhere/";
    vi.advanceTimersByTime(1999);
    expect(location.href).toBe("https://site.test/elsewhere/");
    vi.advanceTimersByTime(1);
    expect(location.href).toBe(BASE_URL); // then on to login
  });

  it("a bad key: the server's banner, the key stays for another try", async () => {
    resetDom(resetUrl);
    state.forms.resetPassword.newPassword = "Str0ng!Passw0rd";
    fetchMock.mockResolvedValue(
      json(
        { code: "invalid_key", message: "This link is invalid or expired." },
        401,
      ),
    );
    await submit(resetPasswordActions.submit);
    expect(state.forms.resetPassword.error).toBe(
      "This link is invalid or expired.",
    );
    expect(location.href).toBe(resetUrl);
  });

  it("generate fills and shows a new password", async () => {
    state.forms.resetPassword.isPasswordVisible = false;
    fetchMock.mockResolvedValue(
      json({ success: true, password: "New#Generated9" }),
    );
    await resetPasswordActions.generate();
    expect(state.forms.resetPassword).toMatchObject({
      newPassword: "New#Generated9",
      isPasswordVisible: true,
      isGenerating: false,
    });
  });

  it("generate: one request at a time", async () => {
    const answer = pendingFetch();

    const first = resetPasswordActions.generate();
    expect(state.forms.resetPassword.isGenerating).toBe(true);
    await resetPasswordActions.generate();
    expect(fetchMock).toHaveBeenCalledTimes(1);

    answer(json({ success: true, password: "New#Generated9" }));
    await first;
    expect(state.forms.resetPassword.isGenerating).toBe(false);
  });

  it("generate: a failure shows in the banner, the password stays", async () => {
    fetchMock.mockResolvedValue(
      json({ code: "rest_no_route", message: "No route." }, 404),
    );
    await resetPasswordActions.generate();
    expect(state.forms.resetPassword).toMatchObject({
      error: "No route.",
      newPassword: "Generated#Password1",
      isGenerating: false,
    });
  });

  it("generate: a stale nonce reloads", async () => {
    fetchMock.mockResolvedValue(
      json({ code: "rest_cookie_invalid_nonce", message: "x" }, 403),
    );
    await resetPasswordActions.generate();
    expect(location.reload).toHaveBeenCalledTimes(1);
    expect(state.forms.resetPassword.error).toBeNull();
  });
});
