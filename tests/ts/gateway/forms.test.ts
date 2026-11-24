/**
 * Gateway form helpers: what an input may write, where a failure shows, and
 * when the session changed under the page.
 *
 * File: tests/ts/gateway/forms.test.ts
 */
import { describe, expect, it } from "vite-plus/test";
import { ApiError } from "../../../inc/shared/Assets/store-utils.ts";
import {
  fieldUpdater,
  reloadIfSessionChanged,
  showFailure,
} from "../../../inc/gateway/Assets/forms.ts";
import { LOGIN_FIELDS } from "../../../inc/gateway/Assets/auth/actions.ts";
import { RESET_FIELDS } from "../../../inc/gateway/Assets/recovery/actions.ts";
import { location } from "../support/dom.ts";
import { installGateway } from "../support/gateway.ts";
import { fake } from "../support/interactivity.ts";

/** An input as the store sees it: `data-field` and a value. */
function input(field: string, value: string): HTMLInputElement {
  const el = new HTMLInputElement();
  el.dataset.field = field;
  el.value = value;
  return el;
}

describe("fieldUpdater", () => {
  it("writes the field and clears its inline error", () => {
    const state = installGateway();
    const form = state.forms.login;
    form.fieldErrors.username = "required";
    fake.ref = input("username", "anna");

    fieldUpdater(LOGIN_FIELDS, () => form)();

    expect(form.username).toBe("anna");
    expect(form.fieldErrors.username).toBeNull();
  });

  it.each(["isSubmitting", "error", "rememberMe", "fieldErrors", undefined])(
    "data-field=%s can't write",
    (field) => {
      const state = installGateway();
      const before = structuredClone(state.forms.login);
      const el = new HTMLInputElement();
      if (field !== undefined) el.dataset.field = field;
      el.value = "x";
      fake.ref = el;

      fieldUpdater(LOGIN_FIELDS, () => state.forms.login)();

      expect(state.forms.login).toEqual(before);
    },
  );

  it("only inputs write", () => {
    const state = installGateway();
    fake.ref = new HTMLAnchorElement();
    fieldUpdater(LOGIN_FIELDS, () => state.forms.login)();
    expect(state.forms.login.username).toBe("");
  });

  it("clearBanner: typing clears the form's banner", () => {
    const state = installGateway();
    const form = state.forms.resetPassword;
    form.error = "too short";
    fake.ref = input("newPassword", "Longer#Pass1");

    fieldUpdater(RESET_FIELDS, () => form, { clearBanner: true })();

    expect(form.newPassword).toBe("Longer#Pass1");
    expect(form.error).toBeNull();
  });
});

describe("showFailure", () => {
  const invalid = (fieldErrors: Record<string, string>) =>
    new ApiError(422, "Unprocessable", {
      code: "invalid_data",
      message: "Please correct the highlighted fields.",
      data: { status: 422, field_errors: fieldErrors },
    });

  it("field errors of the form's own fields go inline", () => {
    const form = installGateway().forms.login;
    showFailure(form, invalid({ username: "Bad name" }), LOGIN_FIELDS);
    expect(form.fieldErrors.username).toBe("Bad name");
    expect(form.error).toBeNull();
  });

  it("field errors the form has no slot for go to the banner", () => {
    const form = installGateway().forms.login;
    showFailure(form, invalid({ user_login: "Nope" }), LOGIN_FIELDS);
    expect(form.error).toBe("Please correct the highlighted fields.");
    expect(form.fieldErrors).toEqual({ username: null, password: null });
  });

  it("other failures go to the banner", () => {
    const form = installGateway().forms.login;
    showFailure(
      form,
      new ApiError(401, "Unauthorized", {
        code: "auth_failed",
        message: "Wrong.",
      }),
      LOGIN_FIELDS,
    );
    expect(form.error).toBe("Wrong.");

    showFailure(form, new TypeError("Failed to fetch"), LOGIN_FIELDS);
    expect(form.error).toBe("Failed to fetch");
  });
});

describe("reloadIfSessionChanged", () => {
  it.each(["already_authenticated", "rest_cookie_invalid_nonce"])(
    "%s reloads",
    (code) => {
      expect(
        reloadIfSessionChanged(new ApiError(403, "Forbidden", { code })),
      ).toBe(true);
      expect(location.reload).toHaveBeenCalledTimes(1);
    },
  );

  it("anything else doesn't", () => {
    expect(
      reloadIfSessionChanged(
        new ApiError(401, "Unauthorized", { code: "auth_failed" }),
      ),
    ).toBe(false);
    expect(reloadIfSessionChanged(new Error("x"))).toBe(false);
    expect(location.reload).not.toHaveBeenCalled();
  });
});
