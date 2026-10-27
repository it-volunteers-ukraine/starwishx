/**
 * Popup Store — Interactivity API
 *
 * Minimal store for a reusable popup/modal.
 * Any other store can open it via: store("popup").actions.open()
 *
 * Keyboard (bound in template-parts/element-popup.php): Escape closes it,
 * Tab stays inside it while it is open, and focus moves into it on open and
 * back to where it was on close.
 *
 * File: inc/shared/Assets/popup-store.js
 */

import { store, getElement, withSyncEvent } from "@wordpress/interactivity";
import { keepTabInside } from "./shared-utils.js";

/** What had focus when the popup opened; it gets focus back on close. */
let opener = null;

/** The open state callbacks.syncFocus last acted on. */
let wasOpen = false;

store("popup", {
  state: { isOpen: false },
  actions: {
    open() {
      store("popup").state.isOpen = true;
    },
    close() {
      store("popup").state.isOpen = false;
    },

    /**
     * Escape closes the open popup, wherever focus is. Bound with
     * data-wp-on-document--keydown on the popup.
     */
    closeOnEscape(event) {
      const { state } = store("popup");
      if (event.key === "Escape" && state.isOpen) state.isOpen = false;
    },

    /**
     * Keeps Tab inside the dialog (aria-modal). Bound with
     * data-wp-on--keydown on the dialog; withSyncEvent because
     * keepTabInside() preventDefault()s the keystroke it redirects.
     */
    trapFocus: withSyncEvent((event) => {
      if (event.key !== "Tab" || !store("popup").state.isOpen) return;
      keepTabInside(event, getElement().ref);
    }),
  },
  callbacks: {
    /**
     * data-wp-watch on the popup. Focus follows it: to the close button when
     * it opens, and back to what had focus before when it closes, unless
     * focus has already moved elsewhere. Watch re-runs come after the next
     * frame, so the open popup is visible, and therefore focusable, by then.
     * The first run, at hydration, sees no change and leaves focus alone.
     */
    syncFocus() {
      const { state } = store("popup");
      const { ref } = getElement();
      const isOpen = state.isOpen;
      if (isOpen === wasOpen) return;
      wasOpen = isOpen;

      if (isOpen) {
        opener = document.activeElement;
        ref.querySelector(".popup__close")?.focus();
        return;
      }

      const current = document.activeElement;
      const lost = !current || current === document.body;
      if (opener?.isConnected && (lost || ref.contains(current))) {
        opener.focus();
      }
      opener = null;
    },
  },
});
