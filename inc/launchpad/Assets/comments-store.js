/**
 * Launchpad Comments — Main Store
 * File: inc/launchpad/Assets/comments-store.js
 *
 * Registers the 'launchpadComments' namespace.
 * Designed to interact with the 'single-opportunity.php' template.
 */

import { store } from "@wordpress/interactivity";

// Import shared utilities
import { extendState } from "./utils.js";

// Import Domain Modules
import { commentsActions } from "./comments/actions.js";
import { commentsGetters } from "./comments/getters.js";

/**
 * Base State Definition
 *
 * CRITICAL ARCHITECTURE NOTE:
 * We do NOT define properties like 'list', 'newContent', 'error', or 'isSubmitting' here.
 * These properties are hydrated from the server via `wp_interactivity_state()` in PHP.
 * Defining them here would cause the Client Store to overwrite the Server Data
 * with default values (e.g., empty arrays), causing "flashing" or missing data.
 */
const commentsState = {
  // EDITING STATE
  editingId: null, // The ID of the comment currently being edited
  editDraft: "", // The text content being edited
  editRating: 0, // The rating being edited
  isUpdating: false, // Loading spinner for update

  // REPLYING STATE
  replyingId: null, // ID of the comment we are replying TO
  replyDraft: "", // Content of the reply
  isReplying: false, // Spinner
};

/**
 * Extend State
 * Mixes in the getters (computed properties) into the state object.
 */
extendState(commentsState, commentsGetters);

/**
 * Store Registration
 */
store("launchpadComments", {
  state: commentsState,
  actions: commentsActions,
});
