/**
 * Social Share — Interactivity API Store
 *
 * Per-instance UI state lives in data-wp-context on the wrapper:
 *   { isOpen, showStatus, statusText, shareUrl, _timeoutId }
 *
 * Static config & i18n hydrated from PHP via wp_interactivity_state('social-share').
 *
 * File: inc/social-share/Assets/social-share-store.js
 */

import { store, getElement, getContext } from "@wordpress/interactivity";

function copyWithFallback(text) {
  const textArea = document.createElement("textarea");
  textArea.value = text;
  textArea.setAttribute("readonly", "readonly");
  textArea.style.position = "absolute";
  textArea.style.left = "-9999px";
  document.body.appendChild(textArea);
  textArea.select();
  const ok = document.execCommand("copy");
  document.body.removeChild(textArea);
  if (!ok) throw new Error("Fallback clipboard copy failed.");
}

function writeToClipboard(text) {
  if (navigator.clipboard && window.isSecureContext) {
    return navigator.clipboard.writeText(text).catch(() => copyWithFallback(text));
  }
  return new Promise((resolve, reject) => {
    try {
      copyWithFallback(text);
      resolve();
    } catch (e) {
      reject(e);
    }
  });
}

function clearStatusTimeout(ctx) {
  if (ctx._timeoutId) {
    window.clearTimeout(ctx._timeoutId);
    ctx._timeoutId = null;
  }
}

function closePanel(ctx) {
  ctx.isOpen = false;
  ctx.showStatus = false;
  ctx.statusText = "";
  clearStatusTimeout(ctx);
}

const { state } = store("social-share", {
  actions: {
    toggle() {
      const ctx = getContext();
      ctx.isOpen = !ctx.isOpen;
      if (!ctx.isOpen) {
        ctx.showStatus = false;
        ctx.statusText = "";
        clearStatusTimeout(ctx);
      }
    },

    async copyLink(event) {
      event?.preventDefault();
      const ctx = getContext();
      const url = ctx.shareUrl || "";
      if (!url) return;

      let label;
      try {
        await writeToClipboard(url);
        label = state.i18n?.copiedLabel || "Link copied!";
      } catch (_) {
        label = state.i18n?.copyFailed || "Copy failed";
      }

      clearStatusTimeout(ctx);
      ctx.statusText = label;
      ctx.showStatus = true;

      const ms = state.config?.statusTimeoutMs || 2500;
      ctx._timeoutId = window.setTimeout(() => {
        ctx.statusText = "";
        ctx.showStatus = false;
        ctx._timeoutId = null;
      }, ms);
    },

    closeOnLink() {
      closePanel(getContext());
    },

    handleKeydown(event) {
      if (event.key !== "Escape") return;
      const ctx = getContext();
      if (!ctx.isOpen) return;

      const { ref } = getElement();
      closePanel(ctx);

      const trigger = ref?.querySelector("[data-social-share-trigger]");
      if (trigger) trigger.focus();
    },

    handleOutsideClick(event) {
      const ctx = getContext();
      if (!ctx.isOpen) return;

      const { ref } = getElement();
      if (ref && !ref.contains(event.target)) {
        closePanel(ctx);
      }
    },
  },
});
