/**
 * Launchpad Store — Opportunities Callbacks
 *
 * Side effects run by data-wp-watch / data-wp-init in the opportunities panel,
 * under the `callbacks.opportunities` namespace.
 *
 * File: inc/launchpad/Assets/opportunities/callbacks.js
 */

import { getContext, getElement, store } from "@wordpress/interactivity";

// Category <details> element → the form it was last synced for ("view:id").
const syncedForm = new WeakMap();

export const opportunitiesCallbacks = {
  /**
   * data-wp-watch on each category group (<details>). Whenever a different
   * form is shown — Add, or Edit of another opportunity — open exactly the
   * groups holding a selected category, so an edited opportunity shows what
   * is ticked and a new one starts fully collapsed.
   *
   * In between, the user's own toggling wins: the watch returns before it
   * reads formData.category, so ticking a checkbox doesn't even re-run it
   * and a group never closes under the cursor.
   */
  syncCategoryGroupOpen() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    const p = state.panels.opportunities;

    const form = `${p.currentView}:${p.formData?.id ?? ""}`;
    if (syncedForm.get(ref) === form) return;
    syncedForm.set(ref, form);

    const selected = p.formData?.category;
    const children = getContext()?.item?.children || [];
    ref.open =
      Array.isArray(selected) &&
      children.some((c) => selected.some((s) => s == c.id));
  },
};
