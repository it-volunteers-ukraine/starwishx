/**
 * Projects Store — Tab Actions
 *
 * Handles tab switching via data-tab attribute.
 *
 * File: inc/projects/Assets/tabs/actions.js
 */

import { store, getElement, getContext } from "@wordpress/interactivity";

export const tabActions = {
  switchTab() {
    const { state } = store("starwishx/projects");
    const { ref } = getElement();
    const tab = ref.dataset.tab;

    if (tab) {
      state.activeTab = tab;
    }
  },

  async toggleFavorite(event) {
    if (event) event.preventDefault();

    const ctx = getContext();
    const item = ctx?.item;
    if (!item?.id) return;

    // Guest guard: show auth popup instead of toggling
    if (!store("starwishx/projects").state.isUserLoggedIn) {
      store("popup").actions.open();
      return;
    }

    // Optimistic local update
    item.isFavorite = !item.isFavorite;

    // Delegate to favorites domain store
    await store("favorites").actions.toggle(item.id);
  },
};
