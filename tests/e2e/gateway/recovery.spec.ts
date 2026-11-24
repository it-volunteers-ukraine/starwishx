/**
 * Lost and reset password through the forms, ending with a real reset: a
 * key as the mail would carry it, a new password, the account activated.
 * No mail is ever sent: the lost-password form is only given unknown logins.
 *
 * File: tests/e2e/gateway/recovery.spec.ts
 */
import { expect, test } from "../support/fixtures.ts";
import { wp } from "../support/wp.ts";

test.beforeAll(() => wp.clearLimits());
test.afterAll(() => wp.clearLimits());

const STRONG = "Str0ng!Passw0rd#2026";

test.describe("lost password", () => {
  test("a blank login shows inline, an unknown one succeeds", async ({
    gateway,
  }) => {
    await gateway.goto("lost-password");

    await gateway.type("lostPassword", "userLogin", "   ");
    await gateway.submit("lostPassword");
    await expect(gateway.fieldError("lostPassword", "userLogin")).toBeVisible();
    expect(gateway.requestsTo("password/lost")).toBe(0);

    await gateway.type("lostPassword", "userLogin", "nobody-e2e-zz-123");
    await expect(gateway.fieldError("lostPassword", "userLogin")).toBeHidden();
    await gateway.submit("lostPassword");

    // The same answer as for a real account: nothing to enumerate.
    await expect
      .poll(() => gateway.state((s) => s.forms.lostPassword.success))
      .toBe(true);
    await expect(gateway.banner("lostPassword")).toBeHidden();
  });

  test("a stale nonce reloads the page after one request", async ({
    gateway,
    page,
  }) => {
    await gateway.goto("lost-password");
    await gateway.mark();
    await gateway.state((s) => {
      s.gatewaySettings.nonce = "0000000000";
      return true;
    });

    await gateway.type("lostPassword", "userLogin", "nobody-e2e-zz-123");
    const reloaded = page.waitForEvent("load");
    await gateway.submit("lostPassword");
    await reloaded;
    await gateway.hydrated();

    expect(await gateway.isMarked()).toBe(false);
    expect(await gateway.state((s) => s.gatewaySettings.nonce)).not.toBe(
      "0000000000",
    );
    // A guest's 403 has no fresh nonce to retry with: no second request.
    expect(gateway.requestsTo("password/lost")).toBe(1);
  });
});

test.describe("reset password", () => {
  test("the form's own checks, and a bogus key", async ({ gateway, page }) => {
    await gateway.goto("reset-password", {
      login: wp.login("nobody"),
      key: "bogusKey123",
    });

    await expect(gateway.form("resetPassword")).toBeVisible();
    expect(await gateway.state((s) => s.resetPasswordInputType)).toBe("text");

    const first = await gateway.state((s) => s.forms.resetPassword.newPassword);
    await page
      .locator('[data-wp-on--click="actions.resetPassword.generate"]')
      .click();
    await expect
      .poll(() => gateway.state((s) => s.forms.resetPassword.newPassword))
      .not.toBe(first);
    expect(
      (await gateway.state((s) => s.forms.resetPassword.newPassword)).length,
    ).toBeGreaterThanOrEqual(12);

    await gateway.type("resetPassword", "newPassword", "abc");
    await gateway.submit("resetPassword");
    await expect(gateway.banner("resetPassword")).toBeVisible();

    // No inline slot here: typing clears the banner.
    await gateway.type("resetPassword", "newPassword", "abcdefghijklmn");
    await expect(gateway.banner("resetPassword")).toBeHidden();
    await gateway.submit("resetPassword");
    await expect(gateway.banner("resetPassword")).toBeVisible();
    expect(gateway.requestsTo("password/reset")).toBe(0);

    await gateway.type("resetPassword", "newPassword", STRONG);
    await gateway.submit("resetPassword");
    await expect.poll(() => gateway.requestsTo("password/reset")).toBe(1);
    await expect(gateway.banner("resetPassword")).toBeVisible();
    expect(new URL(page.url()).searchParams.has("key")).toBe(true);
  });

  test("a real key sets the password and activates the account", async ({
    gateway,
    wp,
    page,
  }) => {
    const user = wp.user("reset", { inactive: true });
    const link = wp.resetKey(user.login);
    await gateway.open(link.url);

    await gateway.type("resetPassword", "newPassword", STRONG);
    await gateway.submit("resetPassword");

    await expect(
      page.locator('[data-wp-text="state.forms.resetPassword.successMessage"]'),
    ).toBeVisible();
    // The spent key (and the login) are taken out of the address bar.
    expect(new URL(page.url()).searchParams.has("key")).toBe(false);
    // Then it goes to login on its own.
    await page.waitForEvent("load");
    await gateway.hydrated();
    await expect(gateway.form("login")).toBeVisible();

    // The key works once.
    await gateway.open(link.url);
    await gateway.type("resetPassword", "newPassword", `${STRONG}x`);
    await gateway.submit("resetPassword");
    await expect(gateway.banner("resetPassword")).toBeVisible();

    // The new password logs in: the reset activated the account.
    await gateway.goto();
    await gateway.type("login", "username", user.login);
    await gateway.type("login", "password", STRONG);
    await gateway.submit("login");
    await page.waitForURL((url) => !url.pathname.startsWith("/gateway/"));
  });
});
