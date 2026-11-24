/**
 * Comments state as PHP hydrates it (CommentsCore::getConfig() +
 * CommentsService::getInitialState()), for the store code under test. A
 * fresh copy for every test: the store mutates it.
 *
 * File: tests/ts/support/comments.ts
 */
import { commentsGetters } from "../../../inc/comments/Assets/comments/getters.ts";
import type {
  CommentNode,
  CommentsClientState,
  CommentsContext,
  CommentsServerState,
  CommentsState,
} from "../../../inc/comments/Assets/types.ts";
import { extendState } from "../../../inc/shared/Assets/store-utils.ts";
import { fake } from "./interactivity.ts";

export const REST_URL = "https://site.test/wp-json/comments/v1/";
export const POST_ID = 42;

/** A comment or reply as CommentsService::formatSingleComment() sends it. */
export function node(id: number, overrides: Partial<CommentNode> = {}) {
  return {
    id,
    author: `Author ${id}`,
    content: `Comment ${id}`,
    date: "24.11.26 10:00",
    dateIso: "2026-11-24T10:00:00+00:00",
    avatar: "https://site.test/avatar.png",
    rating: null,
    ratingLabel: "",
    isMine: true,
    isPostAuthor: false,
    canReply: true,
    isEditable: true,
    replies: [],
    hasReplies: false,
    ...overrides,
  } satisfies CommentNode;
}

export function serverState(): CommentsServerState {
  return {
    config: {
      nonce: "nonce-1",
      restUrl: REST_URL,
      messages: {
        reviewPosted: "posted",
        updateSaved: "saved",
        submitError: "submit error",
        ratingBadgeLabel: "Average %avg% of %count%",
      },
    },
    canComment: true,
    isPostAuthor: false,
    list: [
      node(1, { rating: 4, replies: [node(11), node(12)], hasReplies: true }),
      node(2, { rating: 5 }),
    ],
    aggregates: { count: 2, avg: 4.5 },
    page: 1,
    hasMore: true,
    newContent: "",
    newRating: 5,
    error: null,
    successMessage: null,
    isSubmitting: false,
    isLoading: false,
    showForm: false,
  };
}

/**
 * Install a fresh comments store and return its state: the server's state,
 * the client defaults (as comments-store.ts declares them) and the real
 * getters. The root context is set: `postId`.
 */
export function installComments(): CommentsState {
  const client: CommentsClientState = {
    editingId: null,
    editDraft: "",
    editRating: 0,
    isUpdating: false,
    replyingId: null,
    replyDraft: "",
    isSendingReply: false,
  };
  const state = extendState({ ...serverState(), ...client }, commentsGetters);
  fake.stores.set("comments", state);
  inContext({});
  return state;
}

/** Act as if inside `context` (in the list loop: item, reply). */
export function inContext(context: Omit<CommentsContext, "postId">): void {
  fake.context = { postId: POST_ID, ...context };
}
