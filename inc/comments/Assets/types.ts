/**
 * Comments Store — Types
 *
 * The PHP side of each type is named on it. The hydrated state's shape is
 * pinned in tests/fixtures/comments-state.json (StateContractTest on the PHP
 * side, tests/ts/comments/contract.test.ts here): change a field in one
 * place and a test fails until the other follows.
 *
 * File: inc/comments/Assets/types.ts
 */
import type { commentsActions } from "./comments/actions.ts";

/** A comment or a reply: CommentsService::formatSingleComment(). */
export interface CommentNode {
  id: number;
  author: string;
  content: string;
  /** Formatted for display ('d.m.y H:i'). */
  date: string;
  /** ISO 8601, for <time datetime>. */
  dateIso: string;
  avatar: string;
  /** 1-5; null for replies and reviews without a rating. */
  rating: number | null;
  ratingLabel: string;
  isMine: boolean;
  /** Written by the commented post's author (the badge). */
  isPostAuthor: boolean;
  canReply: boolean;
  isEditable: boolean;
  /** Filled for top-level comments by getPostComments(); [] otherwise. */
  replies: CommentNode[];
  hasReplies: boolean;
}

/** CommentsService::getAggregates() */
export interface Aggregates {
  count: number;
  avg: number;
}

/** CommentsCore::getConfig() */
export interface CommentsConfig {
  nonce: string;
  restUrl: string;
  messages: {
    reviewPosted: string;
    updateSaved: string;
    submitError: string;
    /** With %avg% and %count% placeholders. */
    ratingBadgeLabel: string;
  };
}

/**
 * What PHP hydrates: CommentsService::getInitialState() plus `config`.
 * None of it has a client default - store() merges client state over the
 * server's, so a default would replace it.
 */
export interface CommentsServerState {
  config: CommentsConfig;
  canComment: boolean;
  /** The current user wrote the commented post: they reply, not review. */
  isPostAuthor: boolean;
  list: CommentNode[];
  aggregates: Aggregates;
  page: number;
  hasMore: boolean;
  newContent: string;
  newRating: number;
  error: string | null;
  successMessage: string | null;
  isSubmitting: boolean;
  isLoading: boolean;
  showForm: boolean;
}

/** State only the client has: the one open editor and reply form. */
export interface CommentsClientState {
  /** The comment or reply being edited; null when none is. */
  editingId: number | null;
  editDraft: string;
  editRating: number;
  isUpdating: boolean;
  /** The top-level comment whose reply form is open; null when none is. */
  replyingId: number | null;
  replyDraft: string;
  isSendingReply: boolean;
}

/**
 * Derived state. hasComments, hasRatings and roundedAvg are also hydrated,
 * for the server render; the getters replace them on the client.
 */
export interface CommentsGetters {
  readonly hasComments: boolean;
  readonly hasRatings: boolean;
  readonly roundedAvg: number;
  readonly ratingBadgeLabel: string;
  readonly isAddReviewVisible: boolean;
  /** Is the item (or reply) in context the one being edited? */
  readonly isEditing: boolean;
  /** Is the reply form of the item in context open? */
  readonly isReplyOpen: boolean;
}

export type CommentsState = CommentsServerState &
  CommentsClientState &
  CommentsGetters;

export type CommentsActions = typeof commentsActions;

export interface CommentsStore {
  state: CommentsState;
  actions: CommentsActions;
}

/**
 * The context: `postId` on the root, `item` inside the list loop and, in a
 * reply's markup, `reply` (with `item` its parent).
 */
export interface CommentsContext {
  postId: number;
  item?: CommentNode;
  reply?: CommentNode;
}

// --- REST responses (inc/comments/Api/CommentsController.php) -------------

/** GET comments */
export interface CommentListResponse {
  items: CommentNode[];
  total: number;
  page: number;
  total_pages: number;
}

/** POST comments, PUT comments/{id}: the node (replies: []) and the totals. */
export interface CommentWriteResponse {
  comment: CommentNode;
  aggregates: Aggregates;
}
