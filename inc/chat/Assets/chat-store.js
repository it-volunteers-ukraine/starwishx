/**
 * Chat Store — Notification Center
 *
 * Independent Interactivity API store for the Chat panel.
 * Reads panel data from store("launchpad").state.panels.chat (cross-store).
 * Actions mutate that data directly — same pattern as favorites toggle.
 *
 * File: inc/chat/Assets/chat-store.js
 */
import { store, getContext } from "@wordpress/interactivity";
import { fetchJson } from "./utils.js";

const { state } = store("chat", {
  state: {
    isRefreshing: false,
    isLoadingMore: false,

    /**
     * Whether the user has unread notifications.
     * Reads from own state — decoupled from launchpad so the header badge works on all pages.
     */
    get hasUnread() {
      return (state.unreadCount || 0) > 0;
    },

    /**
     * Whether there are any activity items to display.
     */
    get hasItems() {
      const panel = store("launchpad").state.panels?.chat;
      return Array.isArray(panel?.items) && panel.items.length > 0;
    },

    /**
     * Whether there are more pages to load.
     */
    get hasMoreItems() {
      const panel = store("launchpad").state.panels?.chat;
      return panel?.hasMore === true;
    },

    /**
     * The activity items array (proxy to launchpad panel state).
     */
    get panelItems() {
      const panel = store("launchpad").state.panels?.chat;
      return panel?.items || [];
    },

    /**
     * Badge text for the sidebar tab and header button. "3", "99+", or "".
     */
    get badgeText() {
      const count = state.unreadCount || 0;
      if (count > 99) return "99+";
      return count > 0 ? String(count) : "";
    },

    /**
     * i18n action text for the current item in the data-wp-each loop.
     * "left a review on" or "replied to your review on".
     */
    get currentItemAction() {
      const { state } = store("chat");
      const ctx = getContext();
      const type = ctx?.item?.type;
      if (type === "comment_reply")
        return state.config?.messages?.commentReply || "";
      return state.config?.messages?.newComment || "";
    },

    /**
     * Relative time string for the current item.
     */
    get currentItemTimeAgo() {
      const ctx = getContext();
      const { state } = store("chat");
      if (!ctx?.item?.createdAt) return "";
      return formatTimeAgo(
        ctx.item.createdAt,
        state.config?.messages?.timeAgo || {},
      );
    },
  },

  actions: {
    /**
     * Manual refresh — re-fetch the full activity feed from the server.
     */
    async refresh() {
      const { state } = store("chat");
      const panel = store("launchpad").state.panels?.chat;
      if (!panel || state.isRefreshing) return;

      state.isRefreshing = true;
      try {
        const data = await fetchJson(state, `${state.config.restUrl}activity`);
        if (data) {
          panel.items = data.items;
          panel.total = data.total;
          panel.page = data.page;
          panel.totalPages = data.totalPages;
          panel.hasMore = data.hasMore;
          state.unreadCount = data.unreadCount;
        }
      } catch (err) {
        panel.error = err.message;
        setTimeout(() => {
          panel.error = null;
        }, 5000);
      } finally {
        state.isRefreshing = false;
      }
    },

    /**
     * Append the next page of activity items.
     */
    async loadMore() {
      const { state } = store("chat");
      const panel = store("launchpad").state.panels?.chat;
      if (!panel || state.isLoadingMore) return;

      state.isLoadingMore = true;
      try {
        const nextPage = (panel.page || 1) + 1;
        const data = await fetchJson(
          state,
          `${state.config.restUrl}activity?page=${nextPage}`,
        );
        if (data?.items) {
          panel.items = [...panel.items, ...data.items];
          panel.page = nextPage;
          panel.hasMore = data.hasMore;
          state.unreadCount = data.unreadCount;
        }
      } catch (err) {
        panel.error = err.message;
        setTimeout(() => {
          panel.error = null;
        }, 5000);
      } finally {
        state.isLoadingMore = false;
      }
    },

    /**
     * Mark a single notification as read (click handler on activity item).
     */
    async markItemRead() {
      const { state } = store("chat");
      const panel = store("launchpad").state.panels?.chat;
      const ctx = getContext();
      const item = ctx?.item;

      if (!item || item.isRead) return;

      // Optimistic update
      item.isRead = true;
      state.unreadCount = Math.max(0, (state.unreadCount || 0) - 1);

      try {
        const data = await fetchJson(
          state,
          `${state.config.restUrl}activity/${item.id}/read`,
          { method: "POST" },
        );
        if (data?.unreadCount !== undefined) {
          state.unreadCount = data.unreadCount;
        }
      } catch (err) {
        // Revert on failure
        item.isRead = false;
        state.unreadCount = (state.unreadCount || 0) + 1;
      }
    },

    /**
     * Mark all notifications as read.
     */
    async markAllRead() {
      const { state } = store("chat");
      const panel = store("launchpad").state.panels?.chat;
      if (!panel) return;

      // Optimistic update
      const prevStates = (panel.items || []).map((item) => item.isRead);
      (panel.items || []).forEach((item) => {
        item.isRead = true;
      });
      const prevCount = state.unreadCount;
      state.unreadCount = 0;

      try {
        await fetchJson(state, `${state.config.restUrl}activity/read-all`, {
          method: "POST",
        });
      } catch (err) {
        // Revert on failure
        (panel.items || []).forEach((item, i) => {
          item.isRead = prevStates[i];
        });
        state.unreadCount = prevCount;
        panel.error = err.message;
        setTimeout(() => {
          panel.error = null;
        }, 5000);
      }
    },
  },
});

/**
 * Format a datetime string into a relative "time ago" label.
 *
 * @param {string} dateStr MySQL datetime string (server timezone)
 * @param {Object} msgs   i18n messages { justNow, minutes, hours, days }
 */
function formatTimeAgo(dateStr, msgs) {
  const now = Date.now();
  // MySQL datetime from WP is in server timezone — parse as local
  const then = new Date(dateStr.replace(" ", "T")).getTime();
  const diffMs = now - then;
  const minutes = Math.floor(diffMs / 60000);

  if (minutes < 1) return msgs.justNow || "just now";
  if (minutes < 60)
    return (msgs.minutes || "%d min ago").replace("%d", minutes);
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return (msgs.hours || "%d hr ago").replace("%d", hours);
  const days = Math.floor(hours / 24);
  return (msgs.days || "%d days ago").replace("%d", days);
}
