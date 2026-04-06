/**
 * Shared Store — Collapsible List
 *
 * Context-driven toggle for "Show N more / Show less" lists.
 * Each instance carries its own state via data-wp-context.
 * No global state — multiple instances on the same page are independent.
 *
 * File: inc/shared/Assets/collapsible-store.js
 */

import { store, getContext } from "@wordpress/interactivity";

store("shared", {
  state: {
    get isCollapsibleExpanded() {
      return getContext()?.isExpanded ?? false;
    },
    get collapsibleToggleLabel() {
      const ctx = getContext();
      return ctx.isExpanded ? ctx.labelLess : ctx.labelMore;
    },
  },
  actions: {
    toggleCollapsible() {
      const ctx = getContext();
      ctx.isExpanded = !ctx.isExpanded;
    },
  },
});
