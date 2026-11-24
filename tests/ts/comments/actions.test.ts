/**
 * The comments store's actions inside the store: one editor and one reply
 * form at a time, pages that don't repeat what's listed, delayed closes
 * that don't close a form reopened meanwhile, and responses that are
 * checked before they reach the list.
 *
 * File: tests/ts/comments/actions.test.ts
 */
import { beforeEach, describe, expect, it, vi } from "vite-plus/test";
import {
  cancelPendingCloses,
  commentsActions,
} from "../../../inc/comments/Assets/comments/actions.ts";
import type { CommentsState } from "../../../inc/comments/Assets/types.ts";
import type { SyncAwareAction } from "../../../inc/shared/Assets/store-utils.ts";
import {
  inContext,
  installComments,
  node,
  POST_ID,
  REST_URL,
} from "../support/comments.ts";
import { fake } from "../support/interactivity.ts";

const fetchMock = vi.fn<typeof fetch>();
let state: CommentsState;

const json = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });

const written = (id: number, overrides = {}) =>
  json({ comment: node(id, overrides), aggregates: { count: 3, avg: 4 } });

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

/** Start a submit without waiting for it: the request stays open. */
function start(action: SyncAwareAction): Promise<unknown> {
  const done: unknown = action(new Event("submit", { cancelable: true }));
  return Promise.resolve(done);
}

/** A fetch the test answers later (ES2024 Promise.withResolvers). */
function pendingFetch(): (response: Response) => void {
  const { promise, resolve } = Promise.withResolvers<Response>();
  fetchMock.mockReturnValueOnce(promise);
  return resolve;
}

/** A fetch call's URL. */
const urlOf = (input: RequestInfo | URL): string =>
  typeof input === "string"
    ? input
    : input instanceof URL
      ? input.href
      : input.url;

/** The request sent: method, route and JSON body. */
function request(index = 0): [string, string, unknown] {
  const call = fetchMock.mock.calls[index];
  if (!call) throw new Error(`no request #${index}`);
  const [input, init] = call;
  const url = urlOf(input);
  const body: unknown =
    typeof init?.body === "string" ? JSON.parse(init.body) : undefined;
  return [init?.method ?? "GET", url.replace(REST_URL, ""), body];
}

/** The top-level comment `id` in the list. */
function listed(id: number) {
  const found = state.list.find((item) => item.id === id);
  if (!found) throw new Error(`comment ${id} is not listed`);
  return found;
}

/** Click an element whose dataset is `data`. */
function clicked(data: Record<string, string>): void {
  const element = new HTMLElement();
  Object.assign(element.dataset, data);
  fake.ref = element;
}

/** Type into a textarea. */
function typed(value: string): void {
  const textarea = new HTMLTextAreaElement();
  textarea.value = value;
  fake.ref = textarea;
}

beforeEach(() => {
  state = installComments();
  cancelPendingCloses();
  fetchMock.mockReset();
  vi.stubGlobal("fetch", fetchMock);
});

describe("new review", () => {
  it("posts the review, lists it first and updates the totals", async () => {
    state.showForm = true;
    typed("Great place");
    commentsActions.updateContent();
    clicked({ value: "4" });
    commentsActions.setRating();
    fetchMock.mockResolvedValueOnce(written(3, { rating: 4 }));

    const event = await submit(commentsActions.submit);

    expect(event.defaultPrevented).toBe(true);
    expect(request()).toEqual([
      "POST",
      "comments",
      { post_id: POST_ID, content: "Great place", rating: 4 },
    ]);
    expect(state.list.map((item) => item.id)).toEqual([3, 1, 2]);
    expect(state.aggregates).toEqual({ count: 3, avg: 4 });
    expect(state).toMatchObject({
      newContent: "",
      newRating: 5,
      successMessage: "posted",
      isSubmitting: false,
    });
  });

  it("blank content: no request", async () => {
    state.newContent = "  \n ";
    await submit(commentsActions.submit);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("ignores a star outside 1-5", () => {
    clicked({ value: "9" });
    commentsActions.setRating();
    clicked({ value: "" });
    commentsActions.setRating();
    expect(state.newRating).toBe(5);
  });

  it("is busy while its request is open: a second submit sends nothing", async () => {
    state.newContent = "Hello";
    const answer = pendingFetch();
    const first = start(commentsActions.submit);

    expect(state.isSubmitting).toBe(true);
    await submit(commentsActions.submit);
    expect(fetchMock).toHaveBeenCalledTimes(1);

    answer(written(3));
    await first;
    expect(state.isSubmitting).toBe(false);
  });

  it("a success without the expected body leaves the list intact", async () => {
    state.newContent = "Hello";
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 204 }));
    await submit(commentsActions.submit);
    expect(state.list.map((item) => item.id)).toEqual([1, 2]);
    expect(state.error).toBe("submit error");
  });

  it("shows the server's message on failure", async () => {
    state.newContent = "Hello";
    fetchMock.mockResolvedValueOnce(
      json({ code: "rate_limited", message: "Slow down" }, 429),
    );
    await submit(commentsActions.submit);
    expect(state.error).toBe("Slow down");
  });

  it("closes after the success message, unless reopened meanwhile", async () => {
    vi.useFakeTimers();
    state.showForm = true;
    state.newContent = "Hello";
    fetchMock.mockResolvedValueOnce(written(3));
    await submit(commentsActions.submit);

    commentsActions.toggleForm(); // closed by hand...
    commentsActions.toggleForm(); // ...and opened again
    vi.advanceTimersByTime(5000);
    expect(state.showForm).toBe(true);
  });

  it("closes by itself when left alone", async () => {
    vi.useFakeTimers();
    state.showForm = true;
    state.newContent = "Hello";
    fetchMock.mockResolvedValueOnce(written(3));
    await submit(commentsActions.submit);

    vi.advanceTimersByTime(2000);
    expect(state).toMatchObject({ showForm: false, successMessage: null });
  });
});

describe("show more", () => {
  it("skips what's already listed: a posted review shifts the pages", async () => {
    // Page 2 by offset, after one review was posted: comment 2 again.
    fetchMock.mockResolvedValueOnce(
      json({ items: [node(2), node(4)], total: 4, page: 2, total_pages: 2 }),
    );
    await commentsActions.loadMore();

    expect(state.list.map((item) => item.id)).toEqual([1, 2, 4]);
    expect(state).toMatchObject({ page: 2, hasMore: false, isLoading: false });
  });

  it("builds the query with URLSearchParams (plain permalinks too)", async () => {
    state.config.restUrl = "https://site.test/?rest_route=/comments/v1/";
    fetchMock.mockResolvedValueOnce(
      json({ items: [], total: 2, page: 2, total_pages: 1 }),
    );
    await commentsActions.loadMore();

    const input = fetchMock.mock.calls[0]?.[0];
    if (input === undefined) throw new Error("no request");
    const url = new URL(urlOf(input));
    expect(url.searchParams.get("rest_route")).toBe("/comments/v1/comments");
    expect(url.searchParams.get("post_id")).toBe(String(POST_ID));
    expect(url.searchParams.get("page")).toBe("2");
  });

  it("an unexpected body changes nothing", async () => {
    vi.spyOn(console, "error").mockImplementation(() => {});
    fetchMock.mockResolvedValueOnce(json({ items: [{ id: "x" }] }));
    await commentsActions.loadMore();
    expect(state.list).toHaveLength(2);
    expect(state.page).toBe(1);
    expect(state.isLoading).toBe(false);
  });
});

describe("editing", () => {
  it("one editor at a time, across comments and replies", () => {
    inContext({ item: listed(1) });
    commentsActions.startEdit();
    expect(state.isEditing).toBe(true);
    expect(state.editDraft).toBe("Comment 1");
    expect(state.editRating).toBe(4);

    inContext({ item: listed(2) });
    commentsActions.startEdit();
    expect(state.isEditing).toBe(true);
    inContext({ item: listed(1) });
    expect(state.isEditing).toBe(false);

    const reply = listed(1).replies[0];
    inContext({ item: listed(1), reply });
    commentsActions.startReplyEdit();
    expect(state.isEditing).toBe(true);
    expect(state.editRating).toBe(0);
    // The parent's own editor, outside the reply's markup, is closed.
    inContext({ item: listed(1) });
    expect(state.isEditing).toBe(false);
  });

  it("saves the text of the comment being edited, keeping its replies", async () => {
    inContext({ item: listed(1) });
    commentsActions.startEdit();
    typed("Edited");
    commentsActions.updateEditDraft();
    fetchMock.mockResolvedValueOnce(written(1, { content: "Edited" }));

    await submit(commentsActions.saveEdit);

    expect(request()).toEqual([
      "PUT",
      "comments/1",
      { content: "Edited", rating: 4 },
    ]);
    expect(listed(1).content).toBe("Edited");
    expect(listed(1).replies.map((reply) => reply.id)).toEqual([11, 12]);
    expect(listed(1).hasReplies).toBe(true);
    expect(state.successMessage).toBe("saved");
  });

  it("another editor opened while saving stays open", async () => {
    vi.useFakeTimers();
    inContext({ item: listed(1) });
    commentsActions.startEdit();
    const answer = pendingFetch();
    const saving = start(commentsActions.saveEdit);

    inContext({ item: listed(2) });
    commentsActions.startEdit();
    answer(written(1));
    await saving;
    vi.advanceTimersByTime(5000);

    expect(state.editingId).toBe(2);
    expect(state.editDraft).toBe("Comment 2");
    expect(state.successMessage).toBeNull();
  });

  it("a delayed close doesn't close an editor reopened meanwhile", async () => {
    vi.useFakeTimers();
    inContext({ item: listed(1) });
    commentsActions.startEdit();
    fetchMock.mockResolvedValueOnce(written(1));
    await submit(commentsActions.saveEdit);

    commentsActions.cancelEdit();
    commentsActions.startEdit();
    vi.advanceTimersByTime(5000);
    expect(state.editingId).toBe(1);
  });

  it("saves a reply inside its parent", async () => {
    const reply = listed(1).replies[1];
    inContext({ item: listed(1), reply });
    commentsActions.startReplyEdit();
    state.editDraft = "Reply edited";
    fetchMock.mockResolvedValueOnce(written(12, { content: "Reply edited" }));

    await submit(commentsActions.saveReplyEdit);

    expect(request()).toEqual([
      "PUT",
      "comments/12",
      { content: "Reply edited", rating: 0 },
    ]);
    expect(listed(1).replies.map((r) => r.content)).toEqual([
      "Comment 11",
      "Reply edited",
    ]);
    expect(state.editingId).toBeNull();
  });
});

describe("replying", () => {
  it("one reply form at a time", () => {
    inContext({ item: listed(1) });
    commentsActions.startReply();
    expect(state.isReplyOpen).toBe(true);

    inContext({ item: listed(2) });
    commentsActions.startReply();
    inContext({ item: listed(1) });
    expect(state.isReplyOpen).toBe(false);
  });

  it("appends the reply to its parent and closes the form", async () => {
    inContext({ item: listed(2) });
    commentsActions.startReply();
    typed("Thanks!");
    commentsActions.updateReplyDraft();
    fetchMock.mockResolvedValueOnce(written(21));

    await submit(commentsActions.submitReply);

    expect(request()).toEqual([
      "POST",
      "comments",
      { post_id: POST_ID, parent_id: 2, content: "Thanks!", rating: 0 },
    ]);
    expect(listed(2).replies.map((reply) => reply.id)).toEqual([21]);
    expect(listed(2).hasReplies).toBe(true);
    expect(state).toMatchObject({ replyingId: null, replyDraft: "" });
  });

  it("a reply form opened while sending stays open", async () => {
    inContext({ item: listed(2) });
    commentsActions.startReply();
    state.replyDraft = "First";
    const answer = pendingFetch();
    const sending = start(commentsActions.submitReply);

    inContext({ item: listed(1) });
    commentsActions.startReply();
    answer(written(21));
    await sending;

    expect(state.replyingId).toBe(1);
    expect(listed(2).replies.map((reply) => reply.id)).toEqual([21]);
  });
});
