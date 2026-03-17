/**
 * Menu Store — Interactivity API
 *
 * Auth gate: intercepts clicks on [data-menu-auth] links
 * and shows a login/register popup for guests.
 *
 * File: inc/menu/Assets/menu-store.js
 */

import { store } from "@wordpress/interactivity";

const { state } = store("menu", {
  state: {
    // Hydrated from PHP (always false for guests, not enqueued for logged-in)
    isLoggedIn: false,
    showAuthPopup: false,
  },

  actions: {
    /**
     * Auth gate handler for menu items with [data-menu-auth].
     * Prevents navigation and shows auth popup for guests.
     * Logged-in users never reach this (attribute not rendered).
     */
    handleAuthGate(event) {
      event.preventDefault();
      state.showAuthPopup = true;
    },

    closeAuthPopup() {
      state.showAuthPopup = false;
    },
  },

  callbacks: {
    init() {
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && state.showAuthPopup) {
          state.showAuthPopup = false;
        }
      });
    },
  },
});
