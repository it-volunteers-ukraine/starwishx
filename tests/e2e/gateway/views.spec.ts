/**
 * The Gateway's views: which form ?view shows, switching without a page
 * load, and the browser history.
 *
 * File: tests/e2e/gateway/views.spec.ts
 */
import { expect, test } from "../support/fixtures.ts";

test("the page itself shows login", async ({ gateway }) => {
  await gateway.goto();

  await expect(gateway.form("login")).toBeVisible();
  expect(await gateway.state((s) => s.activeView)).toBe("login");
});

test("an unknown ?view shows login, not a blank page", async ({ gateway }) => {
  await gateway.goto(undefined, { view: "foo" });

  await expect(gateway.form("login")).toBeVisible();
  expect(
    await gateway.state((s) => [
      s.activeView,
      s.isLoginActive,
      s.isRegisterActive,
    ]),
  ).toEqual(["login", true, false]);
});

test("?view=register keeps the hydrated view", async ({ gateway }) => {
  await gateway.goto("register");

  await expect(gateway.form("register")).toBeVisible();
  await expect(gateway.form("login")).toBeHidden();
});

test("switching views changes the URL and history, not the page", async ({
  gateway,
  page,
}) => {
  await gateway.goto();
  await gateway.mark();
  await gateway.type("login", "username", "someone");

  await gateway.link("register").click();

  await expect(gateway.form("register")).toBeVisible();
  await expect(gateway.form("login")).toBeHidden();
  expect(new URL(page.url()).searchParams.get("view")).toBe("register");
  // The router's id survives, so Back is handled by the store.
  expect(
    await page.evaluate(() =>
      Reflect.get(Object(history.state), "wpInteractivityId"),
    ),
  ).toBeTruthy();
  // The form left behind is back to what the server sent.
  expect(await gateway.state((s) => s.forms.login.username)).toBe("");

  await page.goBack();
  await expect(gateway.form("login")).toBeVisible();
  expect(await gateway.isMarked()).toBe(true);

  await page.goForward();
  await expect(gateway.form("register")).toBeVisible();
  expect(await gateway.isMarked()).toBe(true);
});

test("leaving a form clears its banner", async ({ gateway }) => {
  await gateway.goto();
  await gateway.type("login", "username", "nobody-e2e-zz");
  await gateway.type("login", "password", "Wrong#Password1");
  await gateway.submit("login");
  await expect(gateway.banner("login")).toBeVisible();

  await gateway.link("register").click();

  await expect(gateway.form("register")).toBeVisible();
  expect(await gateway.state((s) => s.forms.login.error)).toBeNull();
});
