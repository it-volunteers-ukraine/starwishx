/**
 * Logging in through the form: client checks, failures, unactivated
 * accounts, a real session, and the lockout.
 *
 * File: tests/e2e/gateway/login.spec.ts
 */
import type { BrowserContext } from "@playwright/test";
import { expect, test } from "../support/fixtures.ts";
import { wp } from "../support/wp.ts";

test.beforeAll(() => wp.clearLimits());
test.afterAll(() => wp.clearLimits());

const hasSession = async (context: BrowserContext): Promise<boolean> =>
  (await context.cookies()).some((c) =>
    c.name.startsWith("wordpress_logged_in"),
  );

test("empty or blank input: inline errors and no request", async ({
  gateway,
}) => {
  await gateway.goto();
  await gateway.type("login", "username", "   ");
  await gateway.submit("login");

  await expect(gateway.fieldError("login", "username")).toBeVisible();
  await expect(gateway.fieldError("login", "password")).toBeVisible();
  expect(gateway.requestsTo("login")).toBe(0);

  // Typing clears that field's error, and only that one.
  await gateway.type("login", "username", "someone");
  await expect(gateway.fieldError("login", "username")).toBeHidden();
  await expect(gateway.fieldError("login", "password")).toBeVisible();
});

test("a wrong password shows the banner and stays on the page", async ({
  gateway,
  wp,
}) => {
  const user = wp.user("wrong");
  await gateway.goto();
  await gateway.mark();

  await gateway.type("login", "username", user.login);
  await gateway.type("login", "password", "Wrong#Password1");
  await gateway.submit("login");

  await expect(gateway.banner("login")).toBeVisible();
  await expect(gateway.banner("login")).not.toBeEmpty();
  expect(await gateway.isMarked()).toBe(true);
  expect(await gateway.state((s) => s.forms.login.isSubmitting)).toBe(false);
});

test("data-field can't write the form's flags", async ({ gateway }) => {
  await gateway.goto();
  const input = gateway.input("login", "username");

  await input.evaluate((el) => {
    if (!(el instanceof HTMLInputElement)) return;
    el.dataset.field = "isSubmitting";
    el.value = "x";
    el.dispatchEvent(new Event("input", { bubbles: true }));
    el.dataset.field = "username";
  });

  expect(await gateway.state((s) => s.forms.login.isSubmitting)).toBe(false);
});

test("an unactivated account gets a message and no session", async ({
  gateway,
  wp,
  context,
}) => {
  const user = wp.user("inactive", { inactive: true });
  await gateway.goto();

  await gateway.type("login", "username", user.login);
  await gateway.type("login", "password", user.password);
  await gateway.submit("login");

  await expect(gateway.banner("login")).toBeVisible();
  expect(gateway.requestsTo("login")).toBe(1);
  expect(await hasSession(context)).toBe(false);
});

test("the right password logs in and lands on this site", async ({
  gateway,
  wp,
  page,
  context,
  baseURL,
}) => {
  const user = wp.user("active");
  await gateway.goto();

  await gateway.type("login", "username", user.login);
  await gateway.type("login", "password", user.password);
  await gateway.submit("login");

  await page.waitForURL((url) => !url.pathname.startsWith("/gateway/"));
  expect(new URL(page.url()).origin).toBe(new URL(baseURL ?? "").origin);
  expect(await hasSession(context)).toBe(true);

  // A logged-in visitor is sent away from the gateway.
  await page.goto("/gateway/");
  expect(new URL(page.url()).pathname.startsWith("/gateway/")).toBe(false);
});

test("five failures lock the account, even with the right password", async ({
  gateway,
  wp,
}) => {
  const user = wp.user("locked");
  await gateway.goto();
  await gateway.type("login", "username", user.login);

  for (let i = 0; i < 5; i++) {
    await gateway.type("login", "password", `Wrong#${i}xxxxxx`);
    await gateway.submit("login");
    await expect.poll(() => gateway.requestsTo("login")).toBe(i + 1);
    await expect(gateway.banner("login")).toBeVisible();
  }
  const fifth = await gateway.banner("login").textContent();

  await gateway.type("login", "password", user.password);
  await gateway.submit("login");

  await expect.poll(() => gateway.requestsTo("login")).toBe(6);
  await expect(gateway.banner("login")).toBeVisible();
  // The lockout has its own message, not the wrong-password one.
  await expect(gateway.banner("login")).not.toHaveText(fifth ?? "");
});
