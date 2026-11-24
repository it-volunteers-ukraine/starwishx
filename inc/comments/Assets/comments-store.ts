/**
 * Comments Interactivity API Store
 *
 * Registers the 'comments' namespace for template-parts/comments-interactive.php
 * (single opportunities, projects and news).
 *
 * State is hydrated by PHP: `config` by CommentsCore::enqueueAssets(), the
 * post's list, totals and review form by CommentsService::getInitialState().
 * None of those keys has a client default - store() merges client state over
 * the server's, so a default would replace it. The client adds the editor
 * and reply-form state, and the getters.
 *
 * The logic is in comments/ (tests/ts imports those, not this file).
 *
 * File: inc/comments/Assets/comments-store.ts
 */
import { store } from "@wordpress/interactivity";
import { extendState } from "../../shared/Assets/store-utils.ts";
import { commentsActions } from "./comments/actions.ts";
import { commentsGetters } from "./comments/getters.ts";
import type { CommentsClientState, CommentsStore } from "./types.ts";

const clientState: CommentsClientState = {
  editingId: null,
  editDraft: "",
  editRating: 0,
  isUpdating: false,
  replyingId: null,
  replyDraft: "",
  isSendingReply: false,
};

store<CommentsStore>("comments", {
  state: extendState(clientState, commentsGetters),
  actions: commentsActions,
});
