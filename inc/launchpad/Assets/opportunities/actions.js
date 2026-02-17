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

let locationSearchTimeout = null;
let _pendingUploadFile = null;

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
      : { id: null, title: "", seekers: [], subcategory: [], category: [] };
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
        { panelId: "opportunities" },
      );
      if (data) {
        p.formData = data;
        if (!Array.isArray(p.formData.category)) {
          p.formData.category = p.formData.category
            ? [p.formData.category]
            : [];
        }
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

  triggerFileSelect() {
    const input = document.getElementById('opp-doc-upload');
    if(input) input.click();
  },

  handleFileSelect(event) {
    const { state } = store("launchpad");
    const file = event.target.files[0];
    if (!file) return;

    // Simple Client Validation
    if (file.size > 5 * 1024 * 1024) {
         alert("File too large"); return;
    }

    _pendingUploadFile = file;
    const p = state.panels.opportunities;

    // UI Preview
    p.formData.document = {
        name: file.name,
        size: (file.size / 1024 / 1024).toFixed(2) + ' MB',
        isPending: true
    };
    // We set ID to null/0 to indicate "unsaved change" or "replacement pending"
    // Actual ID comes after upload
    p.formData.document_id = 0;
  },

  removeDocument() {
    const { state } = store("launchpad");
    const p = state.panels.opportunities;

    _pendingUploadFile = null;
    p.formData.document = null;
    p.formData.document_id = 0; // 0 signals backend to delete existing

    const input = document.getElementById('opp-doc-upload');
    if(input) input.value = '';
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
    p.error = null;

    try {
        // STEP A: Upload (if pending)
        if (_pendingUploadFile) {
            p.isUploading = true;

            const formData = new FormData();
            formData.append('file', _pendingUploadFile);

            // Use enhanced fetchJson
            const mediaData = await fetchJson(
                state,
                `${state.launchpadSettings.restUrl}media`,
                {
                    method: 'POST',
                    body: formData,
                    panelId: "opportunities-upload"
                }
            );

            // Update State with new ID from backend
            p.formData.document_id = mediaData.id;

            // Update UI object to reflect "Saved" state
            p.formData.document = {
                ...p.formData.document,
                isPending: false
            };

            _pendingUploadFile = null;
            p.isUploading = false;
        }

        // STEP B: Save Opportunity
        const id = p.formData.id;

        // Clone and clean payload
        const payload = deepClone(p.formData);
        delete payload.document; // Don't send UI object to Opportunities endpoint

        await fetchJson(
            state,
            `${state.launchpadSettings.restUrl}opportunities${id ? "/" + id : ""}`,
            {
                method: id ? "PUT" : "POST",
                body: payload,
                panelId: "opportunities",
            },
        );

        actions.opportunities.cancel();
        await actions.loadPanelState("opportunities");
    } catch (error) {
        p.error = error.message;
    } finally {
        p.isSaving = false;
        p.isUploading = false;
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
        },
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

  toggleCategory() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    const p = state.panels.opportunities;
    const val = Number(ref.value);
    let c = Array.isArray(p.formData.category) ? [...p.formData.category] : [];
    p.formData.category = ref.checked
      ? [...new Set([...c, val])]
      : c.filter((id) => id !== val);
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
        { panelId: "opportunities" },
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
        { panelId: "opportunities" },
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

  /**
   * Generic internal helper to avoid code duplication
   */
  async _performLocSearch(query, levels, resultKey) {
    const { state } = store("launchpad");
    const p = state.panels.opportunities;

    if (locationSearchTimeout) clearTimeout(locationSearchTimeout);

    if (query.length < 1) {
      p.formData[resultKey] = [];
      return;
    }

    locationSearchTimeout = setTimeout(async () => {
      try {
        // Build URL with levels array: levels[]=1&levels[]=2
        const params = new URLSearchParams();
        params.append("search", query);
        levels.forEach((lvl) => params.append("levels[]", lvl));

        const results = await fetchJson(
          state,
          `${state.launchpadSettings.restUrl}opportunities/locations?${params.toString()}`,
        );
        p.formData[resultKey] = results || [];
      } catch (e) {
        console.error(e);
        p.formData[resultKey] = [];
      }
    }, 300);
  },

  /**
   * Wrapper 1: Search Oblasts (Level 1)
   */
  async searchKatottgOblast() {
    const { state, actions } = store("launchpad");
    const { ref } = getElement();
    const query = ref.value;
    state.panels.opportunities.formData.searchOblast = query;
    actions.opportunities._performLocSearch(query, [1], "resultsOblast");
  },

  /**
   * Wrapper 2: Search Raions (Level 2)
   */
  async searchKatottgRaion() {
    const { state, actions } = store("launchpad");
    const { ref } = getElement();
    const query = ref.value;
    state.panels.opportunities.formData.searchRaion = query;
    actions.opportunities._performLocSearch(query, [2, 3], "resultsRaion");
  },

  /**
   * Wrapper 3: Search Cities/Settlements (Level 3 & 4)
   * We include Level 3 (Hromada) and 4 (Settlements) to cover all "places"
   */
  async searchKatottgCity() {
    const { state, actions } = store("launchpad");
    const { ref } = getElement();
    const query = ref.value;
    state.panels.opportunities.formData.searchCity = query;
    actions.opportunities._performLocSearch(query, [4, 5], "resultsCity");
  },

  /**
   * Search Handler with Module-Scoped Debounce
   * DEPRECATED!
   */
  async searchLocations() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    const p = state.panels.opportunities;

    const query = ref.value;
    p.locationSearchQuery = query;

    // Clear existing timeout
    if (locationSearchTimeout) {
      clearTimeout(locationSearchTimeout);
      locationSearchTimeout = null;
    }

    if (query.length < 2) {
      p.locationResults = [];
      return;
    }

    // Set new timeout
    locationSearchTimeout = setTimeout(async () => {
      try {
        // UI: Optional loading indicator could go here (p.isSearchingLoc = true)
        const results = await fetchJson(
          state,
          `${state.launchpadSettings.restUrl}opportunities/locations?search=${encodeURIComponent(query)}`,
        );
        p.locationResults = results || [];
      } catch (e) {
        console.error("Location search failed", e);
        p.locationResults = [];
      }
    }, 300); // 300ms debounce
  },

  /**
   * Add Location: Works for ALL 3 boxes.
   * We need to identify WHICH box triggered it to clear that specific input.
   */
  addLocation(event) {
    const { state } = store("launchpad");
    const { item } = getContext(); // The result item clicked
    const p = state.panels.opportunities;

    // 1. Add to shared chips list
    if (!Array.isArray(p.formData.locations)) p.formData.locations = [];
    if (!p.formData.locations.some((l) => l.code === item.code)) {
      p.formData.locations.push(item);
    }

    // 2. Determine which list we clicked to clear IT specifically
    // We can infer this based on the item level
    const level = parseInt(item.level);

    if (level === 1) {
      p.formData.searchOblast = "";
      p.formData.resultsOblast = [];
    } else if (level === 2) {
      p.formData.searchRaion = "";
      p.formData.resultsRaion = [];
    } else {
      p.formData.searchCity = "";
      p.formData.resultsCity = [];
    }
  },

  removeLocation(event) {
    event.preventDefault(); // Prevent form submission
    const { state } = store("launchpad");
    const { item } = getContext();
    const p = state.panels.opportunities;

    p.formData.locations = p.formData.locations.filter(
      (l) => l.code !== item.code,
    );
  },

  /**
   * Simplified Open Date Picker
   * @param {Event} event - The native DOM event triggered by data-wp-on--click
   */
  openDatePicker(event) {
    // 1. Get the button and the associated input
    const button = event.currentTarget;
    const container = button.closest(".input-date-iconed");
    const dateInput = container?.querySelector('input[type="date"]');

    if (!dateInput) return;

    // 2. Modern: showPicker() (Chrome, Edge, Firefox, Opera)
    if ("showPicker" in HTMLInputElement.prototype) {
      try {
        dateInput.showPicker();
        return;
      } catch (err) {
        // Fallback if showPicker fails (e.g. prevented by browser security)
      }
    }

    // 3. Fallback: Focus (Required for iOS/Android to trigger native wheels)
    dateInput.focus();

    // 4. Desktop Safari Fallback (The Click Simulation)
    // We check purely for "not touch" to target Desktop Safari specifically.
    // Focusing usually works for mobile Safari, but Desktop needs the clicks.
    const isTouch = navigator.maxTouchPoints > 0 || "ontouchstart" in window;

    if (!isTouch) {
      // Dispatch sequence to force open in webkit desktop
      const events = ["mousedown", "click", "mouseup"];
      events.forEach((type) => {
        dateInput.dispatchEvent(
          new MouseEvent(type, {
            bubbles: true,
            cancelable: true,
            view: window,
          }),
        );
      });
    }
  },
};
