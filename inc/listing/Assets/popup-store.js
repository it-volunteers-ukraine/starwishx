/**
 * Popup Store — Interactivity API
 *
 * Minimal store for a reusable popup/modal.
 * Any other store can open it via: store("popup").actions.open()
 *
 * File: inc/listing/Assets/popup-store.js
 */

import { store } from "@wordpress/interactivity";

store("popup", {
  state: { isOpen: false },
  actions: {
    open() {
      store("popup").state.isOpen = true;
    },
    close() {
      store("popup").state.isOpen = false;
    },
  },
  callbacks: {
    init() {
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") store("popup").state.isOpen = false;
      });
    },
  },
});
