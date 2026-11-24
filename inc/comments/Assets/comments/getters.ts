/**
 * Comments Store — Getters
 *
 * Mixed into the client state with extendState(), so `this` is the reactive
 * state.
 *
 * File: inc/comments/Assets/comments/getters.ts
 */
import { getContext } from "@wordpress/interactivity";
import type {
  CommentsContext,
  CommentsGetters,
  CommentsState,
} from "../types.ts";

export const commentsGetters = {
  get hasComments(): boolean {
    return this.list.length > 0;
  },

  get hasRatings(): boolean {
    return this.aggregates.count > 0;
  },

  get roundedAvg(): number {
    return Math.round(this.aggregates.avg);
  },

  get ratingBadgeLabel(): string {
    const { avg, count } = this.aggregates;
    return this.config.messages.ratingBadgeLabel
      .replace("%avg%", String(avg))
      .replace("%count%", String(count));
  },

  /**
   * "Add review" shows on published posts while the form is closed, and
   * never to the post's author: authors reply to reviews, not review.
   */
  get isAddReviewVisible(): boolean {
    return this.canComment && !this.showForm && !this.isPostAuthor;
  },

  /**
   * Is the comment in context the one being edited? In a reply's markup
   * that's the reply, elsewhere the item. Comment ids are unique, so one
   * editingId means one editor open at a time.
   */
  get isEditing(): boolean {
    const { item, reply } = getContext<Partial<CommentsContext>>();
    const target = reply ?? item;
    return target !== undefined && this.editingId === target.id;
  },

  /** Is the reply form under the item in context open? */
  get isReplyOpen(): boolean {
    const { item } = getContext<Partial<CommentsContext>>();
    return item !== undefined && this.replyingId === item.id;
  },
} satisfies CommentsGetters & ThisType<CommentsState>;
