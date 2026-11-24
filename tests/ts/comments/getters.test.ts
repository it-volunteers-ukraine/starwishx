/**
 * The comments getters: strict booleans and the translated badge label.
 *
 * File: tests/ts/comments/getters.test.ts
 */
import { beforeEach, describe, expect, it } from "vite-plus/test";
import type { CommentsState } from "../../../inc/comments/Assets/types.ts";
import { inContext, installComments } from "../support/comments.ts";

let state: CommentsState;

beforeEach(() => {
  state = installComments();
});

describe("getters", () => {
  it("follow the list and the totals", () => {
    expect(state.hasComments).toBe(true);
    expect(state.hasRatings).toBe(true);
    expect(state.roundedAvg).toBe(5);

    state.list = [];
    state.aggregates = { count: 0, avg: 0 };
    expect(state.hasComments).toBe(false);
    expect(state.hasRatings).toBe(false);
    expect(state.roundedAvg).toBe(0);
  });

  it("fill the badge label's placeholders", () => {
    expect(state.ratingBadgeLabel).toBe("Average 4.5 of 2");
  });

  it("hide 'Add review' from the post's author and while the form is open", () => {
    expect(state.isAddReviewVisible).toBe(true);
    state.showForm = true;
    expect(state.isAddReviewVisible).toBe(false);
    state.showForm = false;
    state.isPostAuthor = true;
    expect(state.isAddReviewVisible).toBe(false);
  });

  it("isEditing / isReplyOpen are false outside the list", () => {
    state.editingId = 1;
    state.replyingId = 1;
    inContext({});
    expect(state.isEditing).toBe(false);
    expect(state.isReplyOpen).toBe(false);
  });
});
