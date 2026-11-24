/**
 * Launchpad Store — Opportunities Callbacks
 *
 * Side effects run by data-wp-watch / data-wp-init in the opportunities panel,
 * under the `callbacks.opportunities` namespace.
 *
 * File: inc/launchpad/Assets/opportunities/callbacks.ts
 */

import { getContext, getElement } from "@wordpress/interactivity";
import { launchpadStore } from "../stores.ts";
import type { CategoryContext } from "../types.ts";
import { opportunities } from "../utils.ts";

// Category <details> element → the form it was last synced for ("view:id").
const syncedForm = new WeakMap<HTMLElement, string>();

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
  syncCategoryGroupOpen(): void {
    const { ref } = getElement();
    const p = opportunities(launchpadStore().state);
    if (!(ref instanceof HTMLDetailsElement) || !p) return;

    const form = `${p.currentView}:${p.formData.id ?? ""}`;
    if (syncedForm.get(ref) === form) return;
    syncedForm.set(ref, form);

    const selected = p.formData.category;
    const { item } = getContext<Partial<CategoryContext>>();
    // Loose: the ids from PHP may be strings.
    ref.open = (item?.children ?? []).some((c) =>
      selected.some((s) => s == c.id),
    );
  },
};
