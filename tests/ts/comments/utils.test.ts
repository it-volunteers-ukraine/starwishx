/**
 * The comments fetch helper (nonce contract, errors) and response guards.
 *
 * File: tests/ts/comments/utils.test.ts
 */
import { beforeEach, describe, expect, it, vi } from "vite-plus/test";
import type { CommentsState } from "../../../inc/comments/Assets/types.ts";
import {
  commentsFetch,
  errorMessage,
  isCommentNode,
  isListResponse,
  isWriteResponse,
} from "../../../inc/comments/Assets/utils.ts";
import { ApiError } from "../../../inc/shared/Assets/store-utils.ts";
import { installComments, node, REST_URL } from "../support/comments.ts";

const fetchMock = vi.fn<typeof fetch>();
let state: CommentsState;

const json = (body: unknown, status = 200, headers = {}) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json", ...headers },
  });

const sentNonce = (index: number) =>
  new Headers(fetchMock.mock.calls[index]?.[1]?.headers).get("X-WP-Nonce");

const anything = (data: unknown): data is unknown => data !== undefined;

beforeEach(() => {
  state = installComments();
  fetchMock.mockReset();
  vi.stubGlobal("fetch", fetchMock);
});

describe("commentsFetch", () => {
  it("sends the nonce and keeps the refreshed one", async () => {
    fetchMock.mockResolvedValueOnce(json({}, 200, { "X-WP-Nonce": "nonce-2" }));
    await commentsFetch(`${REST_URL}comments`, anything);
    expect(sentNonce(0)).toBe("nonce-1");
    expect(state.config.nonce).toBe("nonce-2");
  });

  it("retries a stale nonce once, with the refreshed one", async () => {
    fetchMock
      .mockResolvedValueOnce(
        json({ code: "rest_cookie_invalid_nonce" }, 403, {
          "X-WP-Nonce": "nonce-2",
        }),
      )
      .mockResolvedValueOnce(json({ ok: true }));
    await expect(
      commentsFetch(`${REST_URL}comments`, anything, { method: "POST" }),
    ).resolves.toEqual({ ok: true });
    expect(sentNonce(1)).toBe("nonce-2");
  });

  it("doesn't retry without a refreshed nonce", async () => {
    fetchMock.mockResolvedValueOnce(
      json({ code: "rest_cookie_invalid_nonce" }, 403),
    );
    await expect(
      commentsFetch(`${REST_URL}comments`, anything),
    ).rejects.toBeInstanceOf(ApiError);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("rejects a success that isn't the expected JSON", async () => {
    fetchMock.mockResolvedValueOnce(new Response("<html>", { status: 200 }));
    await expect(
      commentsFetch(`${REST_URL}comments`, isWriteResponse),
    ).rejects.toThrow("Unexpected response");
  });
});

describe("errorMessage", () => {
  it("is the server's message, else the fallback", () => {
    const withMessage = new ApiError(403, "Forbidden", { message: "No" });
    expect(errorMessage(withMessage, "fallback")).toBe("No");
    expect(errorMessage(new ApiError(500, "Error", null), "fallback")).toBe(
      "fallback",
    );
    expect(errorMessage(new TypeError("Failed to fetch"), "fallback")).toBe(
      "fallback",
    );
  });

  it("puts a field error first: the forms have no field slots", () => {
    const tooLong = new ApiError(422, "Unprocessable Entity", {
      code: "invalid_data",
      message: "Please correct the highlighted fields.",
      data: {
        field_errors: { content: "Comment: maximum 2000 characters allowed." },
      },
    });
    expect(errorMessage(tooLong, "fallback")).toBe(
      "Comment: maximum 2000 characters allowed.",
    );
  });
});

describe("guards", () => {
  it("accept what the server sends", () => {
    expect(isCommentNode(node(1, { rating: 3 }))).toBe(true);
    expect(
      isWriteResponse({ comment: node(1), aggregates: { count: 1, avg: 5 } }),
    ).toBe(true);
    expect(
      isListResponse({ items: [node(1)], total: 1, page: 1, total_pages: 1 }),
    ).toBe(true);
  });

  it.each([
    ["{}", {}],
    ["null", null],
    [
      "an id as a string",
      { comment: { ...node(1), id: "1" }, aggregates: { count: 1, avg: 5 } },
    ],
    ["no aggregates", { comment: node(1) }],
  ])("reject a write response: %s", (_label, data) => {
    expect(isWriteResponse(data)).toBe(false);
  });

  it("reject a list with a bad item", () => {
    expect(
      isListResponse({ items: [node(1), { id: 2 }], total_pages: 1 }),
    ).toBe(false);
  });
});
