/**
 * Launchpad Store — Opportunities Actions
 *
 * All actions under the `actions.opportunities` namespace.
 * Uses 'this' to access { state, actions } scope.
 *
 * File: inc/launchpad/Assets/opportunities/actions.js
 */

import { getElement, getContext, store } from "@wordpress/interactivity"; // Import store
import { deepClone, ensurePanel, fetchJson } from "../utils.js";

/**
 * Opportunity actions.
 * ARCHITECTURE CHANGE: We use store("launchpad") to access state/actions
 * instead of 'this'. This prevents context loss in data-wp-init.
 */
export const opportunitiesActions = {
  resetForm() {
    // 1. Get the store instance explicitly
    const { state } = store("launchpad");

    const p = state.panels.opportunities;
    p.formData = p.emptyForm
      ? deepClone(p.emptyForm)
      : { id: null, title: "", seekers: [], subcategory: [] };
  },

  async loadSingle(id) {
    // 2. We can get actions too
    const { state, actions } = store("launchpad");

    const p = ensurePanel(state, "opportunities");
    p.isLoading = true;
    actions.opportunities.resetForm();

    try {
      const data = await fetchJson(
        state,
        `${state.launchpadSettings.restUrl}opportunities/${id}`,
        { panelId: "opportunities" }
      );
      if (data) {
        p.formData = data;
      }
    } catch (e) {
      p.error = e.message;
    } finally {
      p.isLoading = false;
    }
  },

  openAdd() {
    const { actions } = store("launchpad");
    actions.opportunities.resetForm();
    const url = new URL(window.location);
    url.searchParams.set("view", "add");
    url.searchParams.delete("id");
    window.history.pushState({}, "", url);
    actions.syncStateFromUrl();
  },

  async openEdit(event) {
    const { actions } = store("launchpad");
    const id = event.target.closest("button")?.dataset?.id;
    if (!id) return;

    const url = new URL(window.location);
    url.searchParams.set("view", "edit");
    url.searchParams.set("id", id);
    window.history.pushState({}, "", url);
    actions.syncStateFromUrl();
  },

  async save(event, statusOverride = null) {
    const { state, actions } = store("launchpad");
    if (event) event.preventDefault();
    const p = state.panels.opportunities;
    if (p.isSaving) return;

    if (statusOverride) {
      p.formData.status = statusOverride;
    }

    p.isSaving = true;
    try {
      const id = p.formData.id;
      await fetchJson(
        state,
        `${state.launchpadSettings.restUrl}opportunities${id ? "/" + id : ""}`,
        {
          method: id ? "PUT" : "POST",
          body: p.formData,
          panelId: "opportunities",
        }
      );
      actions.opportunities.cancel();
      await actions.loadPanelState("opportunities");
    } catch (error) {
      p.error = error.message;
    } finally {
      p.isSaving = false;
    }
  },

  async submitForReview() {
    const { actions } = store("launchpad");
    if (confirm("Submit changes and request review?")) {
      await actions.opportunities.save(null, "pending");
    }
  },

  async quickSubmit() {
    const { state, actions } = store("launchpad");
    const { item } = getContext();
    if (!item?.id) return;
    if (!confirm("Submit this opportunity for review?")) return;

    const p = state.panels.opportunities;
    p.isSaving = true;

    try {
      await fetchJson(
        state,
        `${state.launchpadSettings.restUrl}opportunities/${item.id}/status`,
        {
          method: "POST",
          body: { status: "pending" },
          panelId: "opportunities",
        }
      );
      await actions.loadPanelState("opportunities");
    } catch (error) {
      p.error = error.message;
    } finally {
      p.isSaving = false;
    }
  },

  cancel() {
    const { actions } = store("launchpad");
    const url = new URL(window.location);
    url.searchParams.delete("view");
    url.searchParams.delete("id");
    window.history.pushState({}, "", url);
    actions.syncStateFromUrl();
  },

  updateForm() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    if (state.panels.opportunities.formData) {
      state.panels.opportunities.formData[ref.dataset.field] = ref.value;
    }
  },

  toggleSeeker() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    const p = state.panels.opportunities;
    const val = Number(ref.value);
    let s = Array.isArray(p.formData.seekers) ? [...p.formData.seekers] : [];
    p.formData.seekers = ref.checked
      ? [...new Set([...s, val])]
      : s.filter((id) => id !== val);
  },

  async loadMore() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "opportunities");
    if (p.isLoading) return;

    p.isLoading = true;
    try {
      const nextPage = (p.page || 1) + 1;
      const params = new URLSearchParams();
      params.append("page", nextPage);
      params.append("per_page", state.launchpadSettings.perPage || 4);

      if (p.filters?.statuses?.length) {
        p.filters.statuses.forEach((s) => params.append("statuses[]", s));
      }

      const data = await fetchJson(
        state,
        `${state.launchpadSettings.restUrl}opportunities?${params.toString()}`,
        { panelId: "opportunities" }
      );

      if (data && data.items) {
        p.items = [...p.items, ...data.items];
        p.page = nextPage;
        p.hasMore = nextPage < data.total_pages;
      }
    } catch (error) {
      p.error = error.message;
    } finally {
      p.isLoading = false;
    }
  },

  initFilters() {
    // THIS WAS THE ERROR SOURCE. NOW FIXED.
    const { state } = store("launchpad");
    const p = ensurePanel(state, "opportunities");
    if (!p.filters) {
      p.filters = { statuses: ["draft", "pending", "publish"] };
    }
  },

  async toggleFilter() {
    const { state, actions } = store("launchpad");
    const { ref } = getElement();
    const p = state.panels.opportunities;
    const status = ref.value;

    let active = [...(p.filters?.statuses || [])];

    if (ref.checked) {
      active.push(status);
    } else {
      active = active.filter((s) => s !== status);
    }

    p.filters.statuses = active;
    p.page = 1;

    await actions.opportunities.fetchFilteredList();
  },

  async fetchFilteredList() {
    const { state } = store("launchpad");
    const p = state.panels.opportunities;
    p.isLoading = true;

    const params = new URLSearchParams();
    p.filters.statuses.forEach((s) => params.append("statuses[]", s));
    params.append("per_page", state.launchpadSettings.perPage || 4);

    try {
      const data = await fetchJson(
        state,
        `${state.launchpadSettings.restUrl}opportunities?${params.toString()}`,
        { panelId: "opportunities" }
      );
      if (data) {
        p.items = data.items;
        p.total = data.total;
        p.totalPages = data.total_pages;
        p.hasMore = p.page < data.total_pages;
      }
    } catch (error) {
      p.error = error.message;
    } finally {
      p.isLoading = false;
    }
  },

  setLayout() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    if (!ref?.value) return;

    const val = ref.value;
    const p = state.panels.opportunities;

    p.layout = val;
    p.isLayoutCompact = val === "compact";
    p.isLayoutCard = val === "card";
    p.isLayoutGrid = val === "grid";
  },
};
