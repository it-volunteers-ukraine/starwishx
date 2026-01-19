/**
 * Launchpad Comments — Actions
 * File: inc/launchpad/Assets/comments/actions.js
 *
 * Handles form updates and API submissions.
 */

import { getElement, getContext, store } from "@wordpress/interactivity";
import { fetchJson } from "../utils.js"; // Adjust path to shared utils

export const commentsActions = {
  /**
   * Update the comment textarea state on input.
   */
  updateContent() {
    const { state } = store("launchpadComments");
    const { ref } = getElement();
    state.newContent = ref.value;
  },

  /**
   * Toggles comment form visibility
   */
  toggleForm() {
    const { state } = store("launchpadComments");
    state.showForm = !state.showForm;
    if (!state.showForm) state.error = null; // Clear error on close
    if (state.showForm) {
      state.newRating = 5; // Reset on open
    }
  },

  /**
   * Set the star rating in the form
   */
  setRating() {
    const { state } = store("launchpadComments");
    const { ref } = getElement();
    // Assuming button has data-value="1" etc
    const val = parseInt(ref.dataset.value, 10);
    if (val) state.newRating = val;
  },

  /**
   * Pagination: Load next page of comments
   */
  async loadMore() {
    const { state } = store("launchpadComments");
    const context = getContext();
    const settings = state.settings || {};

    if (state.isLoading) return;

    state.isLoading = true;

    try {
      const nextPage = state.page + 1;

      const data = await fetchJson(
        { launchpadSettings: settings },
        `${settings.restUrl}comments?post_id=${context.postId}&page=${nextPage}`,
        { method: "GET" },
      );

      if (data && data.items) {
        // Append new items to the existing list
        state.list.push(...data.items);
        state.page = nextPage;
        state.hasMore = nextPage < data.total_pages;
      }
    } catch (e) {
      console.error(e); // Log silent error for pagination
    } finally {
      state.isLoading = false;
    }
  },

  /**
   * Submit a new comment to the server.
   */
  async submit(event) {
    event.preventDefault();

    // 1. Locate Store and Context
    const { state } = store("launchpadComments");
    const context = getContext();

    // 2. Validate
    if (!state.newContent || !state.newContent.trim()) return;

    // 3. Get Settings (Injected via wp_add_inline_script in LaunchpadCore)
    // const settings = window.launchpadCommentsSettings || {};
    const settings = state.settings || {};

    // 4. Set Loading State
    state.isSubmitting = true;
    state.error = null;
    state.successMessage = null; // Clear previous success

    try {
      // 5. API Call
      // API call returns { comment: {...}, aggregates: {...} }
      const response = await fetchJson(
        { launchpadSettings: settings },
        `${settings.restUrl}comments`,
        {
          method: "POST",
          body: {
            post_id: context.postId,
            content: state.newContent,
            rating: state.newRating,
          },
        },
      );

      // 1. Add comment to list
      if (!Array.isArray(state.list)) state.list = [];
      // state.list.push(response.comment);
      // unshift to show at top
      state.list.unshift(response.comment);

      state.successMessage = "Review posted successfully!";

      // 2. UX UPDATE: Update the header aggregates immediately
      if (response.aggregates) {
        state.aggregates = response.aggregates;
      }
      // 7. Cleanup & Close
      state.newContent = "";
      state.newRating = 5;
      // state.showForm = false;
      // Close form automatically after 2 seconds
      setTimeout(() => {
        state.successMessage = null;
        state.showForm = false;
      }, 2000);
    } catch (e) {
      state.error = e.message || "An error occurred while posting.";
    } finally {
      state.isSubmitting = false;
    }
  },

  /**
   * Start editing a comment.
   * Sets the local context.isEditing flag.
   */
  startEdit() {
    const { state } = store("launchpadComments");
    const context = getContext();

    // Set per-item editing flag
    context.isEditing = true;

    // Copy current values to draft state for the form
    state.editDraft = context.item.content;
    state.editRating = context.item.rating || 5;
    state.editingId = context.item.id; // Keep for reference during save
    state.error = null;
  },

  /**
   * Cancel editing.
   * Clears the local context.isEditing flag.
   */
  cancelEdit() {
    const { state } = store("launchpadComments");
    const context = getContext();

    // Clear per-item editing flag
    context.isEditing = false;

    // Clear global draft state
    state.editingId = null;
    state.editDraft = "";
    state.editRating = 0;
  },

  /**
   * Update draft text
   */
  updateEditDraft() {
    const { state } = store("launchpadComments");
    const { ref } = getElement();
    state.editDraft = ref.value;
  },

  /**
   * Update draft rating
   */
  setEditRating() {
    const { state } = store("launchpadComments");
    const { ref } = getElement();
    const val = parseInt(ref.dataset.value, 10);
    if (val) state.editRating = val;
  },

  /**
   * Save the edit.
   */
  async saveEdit(event) {
    event.preventDefault();
    const { state } = store("launchpadComments");
    const context = getContext();
    const settings = state.settings || {};

    if (!state.editDraft.trim()) return;

    state.isUpdating = true;
    state.error = null;
    state.successMessage = null;

    try {
      const response = await fetchJson(
        { launchpadSettings: settings },
        `${settings.restUrl}comments/${context.item.id}`,
        {
          method: "PUT",
          body: {
            content: state.editDraft,
            rating: state.editRating,
          },
        },
      );

      // Update the item in the list
      const index = state.list.findIndex((c) => c.id === context.item.id);
      if (index !== -1) {
        // ---------------------------------------------------------
        // ARCHITECTURAL FIX:
        // The backend returns the updated comment node, but with 'replies' set to []
        // to avoid expensive recursive database queries on every edit.
        // We must manually preserve the 'replies' array that is already loaded in the browser state.
        // ---------------------------------------------------------

        const existingItem = state.list[index];

        state.list[index] = {
          ...response.comment, // 1. Apply new data (Content, Rating, Date)
          replies: existingItem.replies, // 2. PRESERVE existing replies array
          hasReplies: existingItem.hasReplies, // 3. PRESERVE the flag
        };
      }

      // Update Aggregates
      if (response.aggregates) {
        state.aggregates = response.aggregates;
      }
      // Close Edit Mode via context
      // context.isEditing = false;
      // state.editingId = null;
      state.editDraft = "";
      state.successMessage = "Update saved.";
      // UX Choice: Close immediately, or show success then close?
      // Let's show success for 1 second, then close.
      setTimeout(() => {
        context.isEditing = false;
        state.editingId = null;
        state.successMessage = null;
      }, 1000);
    } catch (e) {
      state.error = e.message;
    } finally {
      state.isUpdating = false;
    }
  },

  // --- REPLY ACTIONS ---
  startReply() {
    const { state } = store("launchpadComments");
    const context = getContext();
    // Set per-item flag
    context.isReplying = true;
    // Store parent ID for API call
    state.replyingId = context.item.id;
    state.replyDraft = "";
  },

  cancelReply() {
    const { state } = store("launchpadComments");
    const context = getContext();

    context.isReplying = false;
    state.replyingId = null;
    state.replyDraft = "";
  },

  updateReplyDraft() {
    const { state } = store("launchpadComments");
    const { ref } = getElement();
    state.replyDraft = ref.value;
  },

  /**
   * REPLIES EDITING: Start Editing a Reply
   * Uses 'context.reply' instead of 'context.item'
   */
  startReplyEdit() {
    const { state } = store("launchpadComments");
    const context = getContext(); // Local context of the reply-item

    context.isEditing = true;

    // Load Reply data into the global draft state
    state.editDraft = context.reply.content;
    // Replies usually don't have ratings, but if they did, load it here.
    state.editRating = 0;
    state.editingId = context.reply.id;
    state.error = null;
  },

  /**
   * REPLIES EDITING: Cancel
   */
  cancelReplyEdit() {
    const { state } = store("launchpadComments");
    const context = getContext();
    context.isEditing = false;

    // Clear global draft
    state.editingId = null;
    state.editDraft = "";
  },

  /**
   * REPLIES EDITING: Save
   * Handles updating a Nested Item within the Parent's 'replies' array
   */
  async saveReplyEdit(event) {
    event.preventDefault();
    const { state } = store("launchpadComments");
    const context = getContext(); // Contains 'reply' (target) AND 'item' (parent)
    const settings = state.settings || {};

    if (!state.editDraft.trim()) return;

    state.isUpdating = true; // Reusing the global spinner flag

    try {
      const response = await fetchJson(
        { launchpadSettings: settings },
        `${settings.restUrl}comments/${context.reply.id}`,
        {
          method: "PUT",
          body: {
            content: state.editDraft,
            rating: 0, // Replies don't have ratings
          },
        },
      );

      // ARCHITECTURAL MAGIC:
      // We need to update a specific item inside a nested array.
      // 1. Find the Parent
      const parentIndex = state.list.findIndex((c) => c.id === context.item.id);

      if (parentIndex !== -1) {
        const parent = state.list[parentIndex];

        // 2. Find the Reply index within that parent
        const replyIndex = parent.replies.findIndex(
          (r) => r.id === context.reply.id,
        );

        if (replyIndex !== -1) {
          // 3. Create a clean copy of the replies array
          const updatedReplies = [...parent.replies];

          // 4. Update the specific reply with server response
          // The server returns a 'node', so it fits perfectly here.
          updatedReplies[replyIndex] = response.comment;

          // 5. Update the Parent Reference to trigger Reactivity
          state.list[parentIndex] = {
            ...parent,
            replies: updatedReplies,
          };
        }
      }

      context.isEditing = false;
      state.editingId = null;
      state.editDraft = "";
    } catch (e) {
      state.error = e.message;
    } finally {
      state.isUpdating = false;
    }
  },

  async submitReply(event) {
    event.preventDefault();
    const { state } = store("launchpadComments");
    const context = getContext(); // Cache full context
    const { item } = getContext(); // 'item' is the Parent Comment here
    const settings = state.settings || {};

    if (!state.replyDraft.trim()) return;

    state.isReplying = true;
    state.error = null; // Clear previous errors

    try {
      const response = await fetchJson(
        { launchpadSettings: settings },
        `${settings.restUrl}comments`,
        {
          method: "POST",
          body: {
            // post_id: getContext().postId, // or passed via other means, assuming context root has it
            post_id: context.postId, // Use cached context
            parent_id: item.id,
            content: state.replyDraft,
            rating: 0, // Replies usually don't have ratings
          },
        },
      );

      // ARCHITECTURAL MAGIC:
      // We need to find the parent comment in the list and append the reply
      // Since context.item is the parent in the loop, we find it in state.list
      const parentIndex = state.list.findIndex((c) => c.id === item.id);

      if (parentIndex !== -1) {
        const parent = state.list[parentIndex];
        const newReplies = [...(parent.replies || []), response.comment];

        // Replace the whole object to trigger reactivity
        state.list[parentIndex] = {
          ...parent,
          replies: newReplies,
          hasReplies: true, // Update the flag
        };
      }

      // Close reply form via context
      context.isReplying = false;

      state.replyingId = null;
      state.replyDraft = "";
    } catch (e) {
      // alert(e.message);
      state.error = e.message; // state.error instead of alert()
    } finally {
      state.isReplying = false;
    }
  },
};
