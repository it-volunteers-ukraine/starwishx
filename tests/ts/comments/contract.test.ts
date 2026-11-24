/**
 * The PHP <-> TS contract for what the comments store receives.
 *
 * tests/fixtures/comments-state.json is the shape PHP sends, pinned by
 * tests/php/Comments/StateContractTest.php: the hydrated state for a post
 * with a rated review and its reply, and a write response. Here the TS side
 * is held to the same file: the samples below are typed as
 * CommentsServerState and CommentWriteResponse (so the compiler holds them
 * to types.ts), and their shapes must equal the fixture's.
 *
 * File: tests/ts/comments/contract.test.ts
 */
import { describe, expect, it } from "vite-plus/test";
// An ES2025 JSON module. Read as unknown: the test is about its shape.
import fixtureJson from "../../fixtures/comments-state.json" with { type: "json" };
import { commentsGetters } from "../../../inc/comments/Assets/comments/getters.ts";
import type {
  CommentsServerState,
  CommentWriteResponse,
} from "../../../inc/comments/Assets/types.ts";
import { isRecord } from "../../../inc/shared/Assets/store-utils.ts";
import { node, serverState } from "../support/comments.ts";

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

/** The fixture's object at `key`, or a failed expectation. */
function section(key: string): Record<string, unknown> {
  const value = isRecord(fixture) ? fixture[key] : undefined;
  if (!isRecord(value)) throw new Error(`fixture has no "${key}" object`);
  return value;
}

// The post StateContractTest builds: one rated review with one reply.
const state: CommentsServerState = {
  ...serverState(),
  list: [node(1, { rating: 4, replies: [node(2)], hasReplies: true })],
};

const write: CommentWriteResponse = {
  comment: node(3, { rating: 4 }),
  aggregates: { count: 1, avg: 4 },
};

/**
 * Hydrated for the server render only: the getters of the same names
 * replace them on the client, so CommentsServerState leaves them out.
 */
const SERVER_RENDER_MIRRORS = ["hasComments", "hasRatings", "roundedAvg"];

describe("comments contract", () => {
  it("the hydrated state has the fixture's shape", () => {
    const hydrated = { ...section("state") };
    for (const key of SERVER_RENDER_MIRRORS) delete hydrated[key];
    expect(shape(state)).toEqual(hydrated);
  });

  it("each server-render mirror is a getter on the client", () => {
    const hydratedKeys = Object.keys(section("state"));
    for (const key of SERVER_RENDER_MIRRORS) {
      expect(hydratedKeys).toContain(key);
      expect(
        Object.getOwnPropertyDescriptor(commentsGetters, key),
      ).toMatchObject({ get: expect.any(Function) });
    }
  });

  it("a write response has the fixture's shape", () => {
    expect(shape(write)).toEqual(section("write"));
  });
});
