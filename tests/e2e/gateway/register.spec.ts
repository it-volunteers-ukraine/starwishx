/**
 * Registering through the form: the client checks, and a taken username.
 * A successful registration sends real mail, so it's tested by PHPUnit
 * (tests/php/Gateway/RegisterTest.php), not here.
 *
 * File: tests/e2e/gateway/register.spec.ts
 */
import { expect, test } from "../support/fixtures.ts";
import { wp } from "../support/wp.ts";

test.beforeAll(() => wp.clearLimits());
test.afterAll(() => wp.clearLimits());

test("policy, pattern and email errors show inline, with no request", async ({
  gateway,
}) => {
  await gateway.goto("register");

  await gateway.type("register", "username", "ab");
  await gateway.type("register", "email", "not-an-email");
  await gateway.submit("register");
  await expect(gateway.fieldError("register", "username")).toBeVisible();
  await expect(gateway.fieldError("register", "email")).toBeVisible();

  await gateway.type("register", "username", "bad name!");
  await gateway.submit("register");
  await expect(gateway.fieldError("register", "username")).toBeVisible();

  expect(gateway.requestsTo("register")).toBe(0);
});

test("a taken username shows at the field, and no account is made", async ({
  gateway,
  wp,
}) => {
  const taken = wp.user("taken");
  await gateway.goto("register");

  // The address must pass EmailPolicy's DNS check, which runs first; the
  // username check refuses it before anything is created or sent.
  await gateway.type("register", "username", taken.login);
  await gateway.type("register", "email", `${taken.login}@gmail.com`);
  await gateway.submit("register");

  await expect.poll(() => gateway.requestsTo("register")).toBe(1);
  // The 409 carries field_errors: under the username, not in the banner.
  await expect(gateway.fieldError("register", "username")).toBeVisible();
  await expect(gateway.fieldError("register", "email")).toBeHidden();
  await expect(gateway.banner("register")).toBeHidden();
  expect(await gateway.state((s) => s.forms.register.success)).toBe(false);
});
