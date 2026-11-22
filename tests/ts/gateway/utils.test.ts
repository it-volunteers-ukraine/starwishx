/**
 * Gateway utils: client validation, the redirect guard, the response guards
 * and gatewayFetch's nonce contract.
 *
 * File: tests/ts/gateway/utils.test.ts
 */
import { beforeEach, describe, expect, it, vi } from "vite-plus/test";
import { ApiError } from "../../../inc/shared/Assets/store-utils.ts";
import type { GatewayState } from "../../../inc/gateway/Assets/types.ts";
import {
  errorMessage,
  gatewayFetch,
  isGeneratedPassword,
  isLoginResponse,
  isMessageResponse,
  sameOriginUrl,
  validatePassword,
  validateUsername,
  validators,
} from "../../../inc/gateway/Assets/utils.ts";
import { installGateway, REST_URL } from "../support/gateway.ts";

describe("validators", () => {
  it("required: whitespace is empty", () => {
    expect(validators.required("a")).toBe(true);
    expect(validators.required("   ")).toBe(false);
    expect(validators.required("")).toBe(false);
  });

  it("email: the client's loose shape check", () => {
    expect(validators.email("a@b.co")).toBe(true);
    expect(validators.email("a@b")).toBe(false);
    expect(validators.email("a b@c.de")).toBe(false);
  });
});

describe("validateUsername", () => {
  const policy = installGateway().usernamePolicy;

  it.each([
    ["ab", "u short"],
    ["a".repeat(61), "u long"],
    ["bad name", "u invalid"],
    ["кирил", "u invalid"],
    ["good.name-1_x", null],
  ])("%s -> %s", (value, expected) => {
    expect(validateUsername(value, policy)).toBe(expected);
  });

  it("no policy: nothing to check", () => {
    expect(validateUsername("x", undefined)).toBeNull();
  });
});

describe("validatePassword (PasswordPolicy::validate()'s classes)", () => {
  const policy = installGateway().passwordPolicy;

  it.each([
    ["Short1!", "too short"],
    ["alllowercase1!", "too weak"],
    ["NoDigitsHere!!", "too weak"],
    ["NoSymbols12345", "too weak"],
    ["Str0ng!Passw0rd", null],
  ])("%s -> %s", (value, expected) => {
    expect(validatePassword(value, policy)).toBe(expected);
  });

  it("without a policy the length is 12", () => {
    expect(validatePassword("Ab1!Ab1!Ab1", undefined)).toBe("");
    expect(validatePassword("Ab1!Ab1!Ab1!", undefined)).toBeNull();
  });
});

describe("sameOriginUrl", () => {
  it.each([
    ["https://site.test/launchpad/", "https://site.test/launchpad/"],
    ["/wp-admin/", "https://site.test/wp-admin/"],
  ])("keeps %s", (url, expected) => {
    expect(sameOriginUrl(url)?.href).toBe(expected);
  });

  it.each([
    "https://evil.test/",
    "//evil.test/x",
    "javascript:alert(1)",
    "data:text/html,x",
    "http://site.test/", // another origin: the scheme differs
  ])("refuses %s", (url) => {
    expect(sameOriginUrl(url)).toBeNull();
  });
});

describe("response guards", () => {
  it("login needs success and a redirect", () => {
    expect(isLoginResponse({ success: true, redirectTo: "/x" })).toBe(true);
    expect(isLoginResponse({ success: true })).toBe(false);
    expect(isLoginResponse({ success: false, redirectTo: "/x" })).toBe(false);
    expect(isLoginResponse(null)).toBe(false);
  });

  it("message and generated password", () => {
    expect(isMessageResponse({ success: true, message: "ok" })).toBe(true);
    expect(isMessageResponse({ success: true, message: 1 })).toBe(false);
    expect(isGeneratedPassword({ success: true, password: "x" })).toBe(true);
    expect(isGeneratedPassword({ success: true, password: "" })).toBe(false);
  });
});

describe("errorMessage", () => {
  it("prefers the server's message", () => {
    const error = new ApiError(401, "Unauthorized", {
      code: "auth_failed",
      message: "Invalid username or password.",
    });
    expect(errorMessage(error)).toBe("Invalid username or password.");
  });

  it("falls back to the status, then to any error", () => {
    expect(errorMessage(new ApiError(500, "Server Error", "<html>"))).toBe(
      "500 Server Error",
    );
    expect(errorMessage(new Error("boom"))).toBe("boom");
    expect(errorMessage("odd")).toBe("odd");
  });
});

describe("gatewayFetch", () => {
  let state: GatewayState;
  const fetchMock = vi.fn<typeof fetch>();
  const isAny = (data: unknown): data is unknown => data !== undefined;

  const json = (
    body: unknown,
    status = 200,
    headers: Record<string, string> = {},
  ) =>
    new Response(JSON.stringify(body), {
      status,
      headers: { "Content-Type": "application/json", ...headers },
    });

  /** The X-WP-Nonce each call sent. */
  const sentNonces = () =>
    fetchMock.mock.calls.map(([, init]) => {
      const headers = init?.headers;
      return headers && !(headers instanceof Headers) && !Array.isArray(headers)
        ? headers["X-WP-Nonce"]
        : undefined;
    });

  const staleNonce = {
    code: "rest_cookie_invalid_nonce",
    message: "Cookie check failed.",
    data: { status: 403 },
  };

  beforeEach(() => {
    state = installGateway();
    fetchMock.mockReset();
    vi.stubGlobal("fetch", fetchMock);
  });

  it("sends the nonce and a JSON body, same-origin", async () => {
    fetchMock.mockResolvedValue(json({ success: true, message: "ok" }));
    await gatewayFetch(`${REST_URL}register`, isMessageResponse, {
      method: "POST",
      body: { username: "a" },
    });

    const [url, init] = fetchMock.mock.calls[0] ?? [];
    expect(url).toBe(`${REST_URL}register`);
    expect(init?.method).toBe("POST");
    expect(init?.credentials).toBe("same-origin");
    expect(init?.body).toBe('{"username":"a"}');
    expect(sentNonces()).toEqual(["nonce-1"]);
  });

  it("keeps a refreshed X-WP-Nonce for later calls", async () => {
    fetchMock.mockResolvedValue(
      json({ success: true, message: "ok" }, 200, { "X-WP-Nonce": "nonce-2" }),
    );
    await gatewayFetch(REST_URL, isMessageResponse);
    expect(state.gatewaySettings.nonce).toBe("nonce-2");
  });

  it("stale nonce with a fresh one: retries once, with the fresh one", async () => {
    fetchMock
      .mockResolvedValueOnce(json(staleNonce, 403, { "X-WP-Nonce": "fresh" }))
      .mockResolvedValueOnce(json({ success: true, message: "ok" }));

    await expect(gatewayFetch(REST_URL, isMessageResponse)).resolves.toEqual({
      success: true,
      message: "ok",
    });
    expect(sentNonces()).toEqual(["nonce-1", "fresh"]);
  });

  it("stale nonce without a fresh one (a guest): no retry", async () => {
    fetchMock.mockResolvedValue(json(staleNonce, 403));
    await expect(
      gatewayFetch(REST_URL, isMessageResponse),
    ).rejects.toMatchObject({
      status: 403,
      code: "rest_cookie_invalid_nonce",
    });
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("retries only once", async () => {
    fetchMock.mockResolvedValue(
      json(staleNonce, 403, { "X-WP-Nonce": "fresh" }),
    );
    await expect(
      gatewayFetch(REST_URL, isMessageResponse),
    ).rejects.toBeInstanceOf(ApiError);
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });

  it("a failure throws ApiError with the field errors", async () => {
    fetchMock.mockResolvedValue(
      json(
        {
          code: "invalid_data",
          message: "Please correct the highlighted fields.",
          data: { status: 422, field_errors: { username: "Taken", n: 1 } },
        },
        422,
      ),
    );
    const error: unknown = await gatewayFetch(REST_URL, isAny).catch(
      (e: unknown) => e,
    );
    expect(error).toBeInstanceOf(ApiError);
    if (!(error instanceof ApiError)) return;
    expect(error.code).toBe("invalid_data");
    expect(error.fieldErrors).toEqual({ username: "Taken" });
  });

  it("an unexpected shape is an error, not data", async () => {
    fetchMock.mockResolvedValue(json({ success: true }));
    await expect(gatewayFetch(REST_URL, isLoginResponse)).rejects.toThrow(
      /Unexpected response/,
    );
  });

  it("204 is an empty object", async () => {
    fetchMock.mockResolvedValue(new Response(null, { status: 204 }));
    await expect(gatewayFetch(REST_URL, isAny)).resolves.toEqual({});
  });
});
