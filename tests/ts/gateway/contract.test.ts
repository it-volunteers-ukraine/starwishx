/**
 * The PHP <-> TS contract for the Gateway's hydrated state.
 *
 * tests/fixtures/gateway-state.json is the shape PHP hydrates, pinned by
 * tests/php/Gateway/StateContractTest.php. Here the TS side is held to the
 * same file: the sample state in support/gateway.ts is typed as
 * GatewayServerState (so the compiler holds it to types.ts), and its shape
 * must equal the fixture's. Change a field on one side only, and one of
 * the two tests fails.
 *
 * File: tests/ts/gateway/contract.test.ts
 */
import { describe, expect, it } from "vite-plus/test";
// An ES2025 JSON module. Read as unknown: the test is about its shape.
import fixtureJson from "../../fixtures/gateway-state.json" with { type: "json" };
import { LOGIN_FIELDS } from "../../../inc/gateway/Assets/auth/actions.ts";
import {
  LOST_FIELDS,
  RESET_FIELDS,
} from "../../../inc/gateway/Assets/recovery/actions.ts";
import { REGISTER_FIELDS } from "../../../inc/gateway/Assets/register/actions.ts";
import { FORM_KEYS } from "../../../inc/gateway/Assets/views.ts";
import { isRecord } from "../../../inc/shared/Assets/store-utils.ts";
import { serverState } from "../support/gateway.ts";

const fixture: unknown = fixtureJson;

/** Keys and JSON value types, as StateContractTest::shape() writes them. */
function shape(value: unknown): unknown {
  if (value === null) return "null";
  if (Array.isArray(value)) return value.map(shape);
  if (isRecord(value)) {
    return Object.fromEntries(
      Object.entries(value).map(([key, item]) => [key, shape(item)]),
    );
  }
  return typeof value;
}

/** The fixture's object at `path`, or a failed expectation. */
function at(...path: string[]): Record<string, unknown> {
  let node = fixture;
  for (const key of path) node = isRecord(node) ? node[key] : undefined;
  if (!isRecord(node))
    throw new Error(`fixture has no object at ${path.join(".")}`);
  return node;
}

describe("gateway state contract (tests/fixtures/gateway-state.json)", () => {
  it("the TS-typed state has the shape PHP hydrates", () => {
    const { gatewaySettings: _static, ...hydrated } = serverState();
    expect(shape(hydrated)).toEqual(fixture);
  });

  // Key lists compare as Sets: the same members, in any order.
  it("every registered view has a form key the store knows", () => {
    expect(new Set(Object.keys(at("formMap")))).toEqual(
      new Set(Object.keys(FORM_KEYS)),
    );
    expect(new Set(Object.keys(at("forms")))).toEqual(
      new Set(Object.values(FORM_KEYS)),
    );
  });

  it.each([
    ["login", LOGIN_FIELDS],
    ["register", REGISTER_FIELDS],
    ["lostPassword", LOST_FIELDS],
    ["resetPassword", RESET_FIELDS],
  ] as const)(
    "%s: the fields its inputs write are strings PHP hydrates",
    (form, fields) => {
      const state = at("forms", form);
      for (const field of fields) expect(state[field]).toBe("string");
    },
  );

  it("forms with inline errors have a slot for every field", () => {
    for (const [form, fields] of [
      ["login", LOGIN_FIELDS],
      ["register", REGISTER_FIELDS],
      ["lostPassword", LOST_FIELDS],
    ] as const) {
      expect(new Set(Object.keys(at("forms", form, "fieldErrors")))).toEqual(
        new Set(fields),
      );
    }
  });
});
