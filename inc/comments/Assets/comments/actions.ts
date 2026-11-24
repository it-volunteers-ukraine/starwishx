/**
 * Comments Store — Actions
 *
 * The review form, the in-place editor and the reply form. One editor and
 * one reply form are open at a time: which one is state.editingId /
 * state.replyingId, read by the isEditing / isReplyOpen getters.
 *
 * Async actions read the context before their first await (it's gone
 * after), and re-check the ids when a response arrives: the user may have
 * opened another form meanwhile.
 *
 * File: inc/comments/Assets/comments/actions.ts
 */
import {
  getContext,
  getElement,
  withSyncEvent,
} from "@wordpress/interactivity";
import { commentsStore } from "../stores.ts";
import type { CommentNode, CommentsContext, CommentsState } from "../types.ts";
import {
  commentsFetch,
  errorMessage,
  isListResponse,
  isWriteResponse,
} from "../utils.ts";

/** The rating a new review starts with. */
const DEFAULT_RATING = 5;
/** How long a success message shows before its form closes, in ms. */
const CLOSE_DELAY = { form: 2000, edit: 1000 } as const;

// --- Delayed closing ----------------------------------------------------

const timers: Partial<
  Record<keyof typeof CLOSE_DELAY, ReturnType<typeof setTimeout>>
> = {};

/** Run `close` after the delay, replacing a close already pending. */
function closeLater(which: keyof typeof CLOSE_DELAY, close: () => void): void {
  cancelClose(which);
  timers[which] = setTimeout(() => {
    delete timers[which];
    close();
  }, CLOSE_DELAY[which]);
}

/** Drop a pending close: the user reopened or moved on. */
function cancelClose(which: keyof typeof CLOSE_DELAY): void {
  clearTimeout(timers[which]);
  delete timers[which];
}

// --- Helpers --------------------------------------------------------------

/** Replace the top-level comment `id` with what `update` makes of it. */
function updateComment(
  state: CommentsState,
  id: number,
  update: (node: CommentNode) => CommentNode,
): void {
  const index = state.list.findIndex((node) => node.id === id);
  const node = state.list[index];
  // A new object, so the loop re-renders the item.
  if (node) state.list[index] = update(node);
}

function openEditor(state: CommentsState, node: CommentNode, rating: number) {
  cancelClose("edit");
  state.editingId = node.id;
  state.editDraft = node.content;
  state.editRating = rating;
  state.error = null;
  state.successMessage = null;
}

function closeEditor(state: CommentsState): void {
  cancelClose("edit");
  state.editingId = null;
  state.editDraft = "";
  state.editRating = 0;
}

/** The typed text of the element the directive is on. */
function inputValue(): string | null {
  const { ref } = getElement();
  return ref instanceof HTMLTextAreaElement || ref instanceof HTMLInputElement
    ? ref.value
    : null;
}

/** The star clicked: its data-value, when it's a rating of 1-5. */
function clickedRating(): number | null {
  const { ref } = getElement();
  if (!(ref instanceof HTMLElement)) return null;
  const value = Number(ref.dataset.value);
  return Number.isInteger(value) && value >= 1 && value <= 5 ? value : null;
}

// --- Actions --------------------------------------------------------------

export const commentsActions = {
  // --- New review ---

  updateContent(): void {
    const value = inputValue();
    if (value !== null) commentsStore().state.newContent = value;
  },

  setRating(): void {
    const rating = clickedRating();
    if (rating !== null) commentsStore().state.newRating = rating;
  },

  toggleForm(): void {
    const { state } = commentsStore();
    if (!state.canComment) return;
    cancelClose("form");
    state.showForm = !state.showForm;
    state.successMessage = null;
    if (state.showForm) state.newRating = DEFAULT_RATING;
    else state.error = null;
  },

  /** withSyncEvent: it cancels the native submit. */
  submit: withSyncEvent(async (event: Event): Promise<void> => {
    event.preventDefault();
    const { state } = commentsStore();
    const { postId } = getContext<CommentsContext>();
    if (state.isSubmitting || !state.newContent.trim()) return;

    const { restUrl, messages } = state.config;
    state.isSubmitting = true;
    state.error = null;
    state.successMessage = null;

    try {
      const { comment, aggregates } = await commentsFetch(
        `${restUrl}comments`,
        isWriteResponse,
        {
          method: "POST",
          body: {
            post_id: postId,
            content: state.newContent,
            rating: state.newRating,
          },
        },
      );
      state.list.unshift(comment);
      state.aggregates = aggregates;
      state.newContent = "";
      state.newRating = DEFAULT_RATING;
      state.successMessage = messages.reviewPosted;
      closeLater("form", () => {
        state.successMessage = null;
        state.showForm = false;
      });
    } catch (error) {
      state.error = errorMessage(error, messages.submitError);
    } finally {
      state.isSubmitting = false;
    }
  }),

  /**
   * The next page. The pages are offsets, so a review posted since the
   * page loaded shifts them by one: what's already listed is skipped.
   */
  async loadMore(): Promise<void> {
    const { state } = commentsStore();
    const { postId } = getContext<CommentsContext>();
    if (state.isLoading) return;
    state.isLoading = true;

    try {
      const page = state.page + 1;
      // URLSearchParams: restUrl is ?rest_route=… without pretty permalinks.
      const url = new URL(`${state.config.restUrl}comments`);
      url.searchParams.set("post_id", String(postId));
      url.searchParams.set("page", String(page));

      const data = await commentsFetch(url.href, isListResponse);
      const listed = new Set(state.list.map((node) => node.id));
      state.list.push(...data.items.filter((node) => !listed.has(node.id)));
      state.page = page;
      state.hasMore = page < data.total_pages;
    } catch (error) {
      // The button stays for another try; nothing else to show it in.
      console.error(error);
    } finally {
      state.isLoading = false;
    }
  },

  // --- Editing (a comment or a reply) ---

  startEdit(): void {
    const { item } = getContext<CommentsContext>();
    if (item)
      openEditor(commentsStore().state, item, item.rating ?? DEFAULT_RATING);
  },

  /** Replies have no rating. */
  startReplyEdit(): void {
    const { reply } = getContext<CommentsContext>();
    if (reply) openEditor(commentsStore().state, reply, 0);
  },

  cancelEdit(): void {
    closeEditor(commentsStore().state);
  },

  cancelReplyEdit(): void {
    closeEditor(commentsStore().state);
  },

  updateEditDraft(): void {
    const value = inputValue();
    if (value !== null) commentsStore().state.editDraft = value;
  },

  setEditRating(): void {
    const rating = clickedRating();
    if (rating !== null) commentsStore().state.editRating = rating;
  },

  /** Save a top-level comment. withSyncEvent: it cancels the native submit. */
  saveEdit: withSyncEvent(async (event: Event): Promise<void> => {
    event.preventDefault();
    const { state } = commentsStore();
    const { item } = getContext<CommentsContext>();
    if (!item || state.isUpdating || !state.editDraft.trim()) return;

    const { id } = item;
    const { restUrl, messages } = state.config;
    state.isUpdating = true;
    state.error = null;
    state.successMessage = null;

    try {
      const { comment, aggregates } = await commentsFetch(
        `${restUrl}comments/${id}`,
        isWriteResponse,
        {
          method: "PUT",
          body: { content: state.editDraft, rating: state.editRating },
        },
      );
      // The server sends the node with replies: [] (no recursive query on
      // every edit): keep the replies already loaded.
      updateComment(state, id, (old) => ({
        ...comment,
        replies: old.replies,
        hasReplies: old.hasReplies,
      }));
      state.aggregates = aggregates;
      // Still this comment's editor? Show the message, then close it.
      // (Opening another editor cancels the close: openEditor().)
      if (state.editingId === id) {
        state.successMessage = messages.updateSaved;
        closeLater("edit", () => {
          state.successMessage = null;
          closeEditor(state);
        });
      }
    } catch (error) {
      state.error = errorMessage(error, messages.submitError);
    } finally {
      state.isUpdating = false;
    }
  }),

  /** Save a reply, inside its parent's replies. */
  saveReplyEdit: withSyncEvent(async (event: Event): Promise<void> => {
    event.preventDefault();
    const { state } = commentsStore();
    const { item, reply } = getContext<CommentsContext>();
    if (!item || !reply || state.isUpdating || !state.editDraft.trim()) return;

    const parentId = item.id;
    const replyId = reply.id;
    state.isUpdating = true;
    state.error = null;
    state.successMessage = null;

    try {
      const { comment } = await commentsFetch(
        `${state.config.restUrl}comments/${replyId}`,
        isWriteResponse,
        { method: "PUT", body: { content: state.editDraft, rating: 0 } },
      );
      updateComment(state, parentId, (parent) => ({
        ...parent,
        replies: parent.replies.map((node) =>
          node.id === replyId ? comment : node,
        ),
      }));
      if (state.editingId === replyId) closeEditor(state);
    } catch (error) {
      state.error = errorMessage(error, state.config.messages.submitError);
    } finally {
      state.isUpdating = false;
    }
  }),

  // --- Replying (to a top-level comment) ---

  startReply(): void {
    const { state } = commentsStore();
    const { item } = getContext<CommentsContext>();
    if (!item) return;
    state.replyingId = item.id;
    state.replyDraft = "";
    state.error = null;
  },

  cancelReply(): void {
    const { state } = commentsStore();
    state.replyingId = null;
    state.replyDraft = "";
  },

  updateReplyDraft(): void {
    const value = inputValue();
    if (value !== null) commentsStore().state.replyDraft = value;
  },

  /** withSyncEvent: it cancels the native submit. */
  submitReply: withSyncEvent(async (event: Event): Promise<void> => {
    event.preventDefault();
    const { state } = commentsStore();
    const { postId, item } = getContext<CommentsContext>();
    if (!item || state.isSendingReply || !state.replyDraft.trim()) return;

    const parentId = item.id;
    state.isSendingReply = true;
    state.error = null;

    try {
      const { comment } = await commentsFetch(
        `${state.config.restUrl}comments`,
        isWriteResponse,
        {
          method: "POST",
          body: {
            post_id: postId,
            parent_id: parentId,
            content: state.replyDraft,
            rating: 0,
          },
        },
      );
      updateComment(state, parentId, (parent) => ({
        ...parent,
        replies: [...parent.replies, comment],
        hasReplies: true,
      }));
      if (state.replyingId === parentId) {
        state.replyingId = null;
        state.replyDraft = "";
      }
    } catch (error) {
      state.error = errorMessage(error, state.config.messages.submitError);
    } finally {
      state.isSendingReply = false;
    }
  }),
};

/** Drop pending closes, so no timer outlives its test. */
export function cancelPendingCloses(): void {
  cancelClose("form");
  cancelClose("edit");
}
