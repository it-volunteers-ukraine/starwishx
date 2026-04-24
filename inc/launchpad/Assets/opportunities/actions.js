/**
 * Launchpad Store — Opportunities Actions
 *
 * All actions under the `actions.opportunities` namespace.
 * Uses 'this' to access { state, actions } scope.
 *
 * File: inc/launchpad/Assets/opportunities/actions.js
 */

import { getElement, getContext, store } from "@wordpress/interactivity"; // Import store
import {
  deepClone,
  ensurePanel,
  fetchJson,
  normalizeUrl,
  scrollToFirstError,
  scrollToPageTop,
} from "../utils.js";

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
    p.error = null;
    p.fieldErrors = {};
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
      setTimeout(() => {
        p.error = null;
      }, 5000);
      scrollToFirstError();
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
    const input = document.getElementById("opp-doc-upload");
    if (input) input.click();
  },

  handleFileSelect(event) {
    const { state } = store("launchpad");
    const file = event.target.files[0];
    if (!file) return;

    const p = state.panels.opportunities;
    const vm = p.validationMessages || {};

    // Client-side size guard (backend enforces same limit in MediaService)
    if (file.size > 5 * 1024 * 1024) {
      p.fieldErrors.document = vm.document;
      event.target.value = "";
      return;
    }

    // Clear previous document error on valid selection
    if (p.fieldErrors?.document) p.fieldErrors.document = null;

    _pendingUploadFile = file;

    // UI Preview
    p.formData.document = {
      name: file.name,
      size: (file.size / 1024 / 1024).toFixed(2) + " MB",
      isPending: true,
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
    if (p.fieldErrors?.document) p.fieldErrors.document = null;

    const input = document.getElementById("opp-doc-upload");
    if (input) input.value = "";
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

    p.isSaving = true;
    p.error = null;
    p.fieldErrors = {};

    // Normalize URL fields before validation (mirrors backend sanitizeUrl)
    p.formData.sourcelink = normalizeUrl(p.formData.sourcelink);
    p.formData.application_form = normalizeUrl(p.formData.application_form);

    // Client-side required field check (messages from PHP state)
    const vm = p.validationMessages || {};
    const titleLen = p.formData.title?.trim().length || 0;
    if (titleLen === 0) {
      p.fieldErrors.title = vm.title;
    } else if (titleLen < (p.titleLimits?.min || 30)) {
      p.fieldErrors.title = vm.titleMinLength;
    }
    const companyLen = p.formData.company?.trim().length || 0;
    if (companyLen === 0) {
      p.fieldErrors.company = vm.company;
    } else if (companyLen < (p.companyLimits?.min || 2)) {
      p.fieldErrors.company = vm.companyMinLength;
    }
    if (!p.formData.sourcelink?.trim())
      p.fieldErrors.sourcelink = vm.sourcelink;
    const descLen = p.formData.description?.trim().length || 0;
    if (descLen === 0) {
      p.fieldErrors.description = vm.description;
    } else if (descLen < (p.descriptionLimits?.min || 50)) {
      p.fieldErrors.description = vm.descriptionMinLength;
    }
    if (!p.formData.category?.length) p.fieldErrors.category = vm.category;
    if (!p.formData.seekers?.length) p.fieldErrors.seekers = vm.seekers;
    if (
      p.formData.date_starts &&
      p.formData.date_ends &&
      p.formData.date_starts > p.formData.date_ends
    ) {
      p.fieldErrors.date_ends = vm.dateRange;
    }
    if (Object.keys(p.fieldErrors).length) {
      p.isSaving = false;
      scrollToFirstError();
      return;
    }

    try {
      // STEP A: Upload (if pending)
      if (_pendingUploadFile) {
        p.isUploading = true;

        const formData = new FormData();
        formData.append("file", _pendingUploadFile);

        // Use enhanced fetchJson
        const mediaData = await fetchJson(
          state,
          `${state.launchpadSettings.restUrl}media`,
          {
            method: "POST",
            body: formData,
            panelId: "opportunities-upload",
          },
        );

        // Update State with new ID from backend
        p.formData.document_id = mediaData.id;

        // Update UI object to reflect "Saved" state
        p.formData.document = {
          ...p.formData.document,
          isPending: false,
        };

        _pendingUploadFile = null;
        p.isUploading = false;
      }

      // STEP B: Save Opportunity
      const id = p.formData.id;

      // Clone and clean payload
      const payload = deepClone(p.formData);
      delete payload.document; // Don't send UI object to Opportunities endpoint

      // Apply status override to payload, not formData — avoids dirty state on validation failure
      if (statusOverride) {
        payload.status = statusOverride;
      }

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
      scrollToPageTop();
      await actions.loadPanelState("opportunities");
    } catch (error) {
      // Map backend field_errors first so we can decide whether the
      // banner is still needed — PR1's rest_invalid_param reshape lands
      // the per-field messages in `error.fieldErrors`.
      p.fieldErrors = error.fieldErrors || {};
      // Show banner only when there are no inline field errors — otherwise
      // the generic "Please correct the highlighted fields." duplicates
      // the inline guidance.
      if (!Object.keys(p.fieldErrors).length) {
        p.error = error.message;
        setTimeout(() => {
          p.error = null;
        }, 5000);
      }
      scrollToFirstError();
    } finally {
      p.isSaving = false;
      p.isUploading = false;
    }
  },

  submitForReview() {
    const { state } = store("launchpad");
    const p = state.panels.opportunities;
    p.confirmPopup = { isOpen: true, itemId: null, source: "form" };
  },

  quickSubmit() {
    const { state } = store("launchpad");
    const { item } = getContext();
    if (!item?.id) return;
    const p = state.panels.opportunities;
    p.confirmPopup = { isOpen: true, itemId: item.id, source: "list" };
  },

  closeConfirm() {
    const { state } = store("launchpad");
    state.panels.opportunities.confirmPopup = {
      isOpen: false,
      itemId: null,
      source: null,
    };
  },

  async confirmSubmit() {
    const { state, actions } = store("launchpad");
    const p = state.panels.opportunities;
    const { source, itemId } = p.confirmPopup;

    // Close popup immediately
    p.confirmPopup = { isOpen: false, itemId: null, source: null };

    if (source === "form") {
      // From the edit/add form — save with pending status
      await actions.opportunities.save(null, "pending");
    } else if (source === "list" && itemId) {
      // From the list card — quick status change
      p.isSaving = true;
      try {
        await fetchJson(
          state,
          `${state.launchpadSettings.restUrl}opportunities/${itemId}/status`,
          {
            method: "POST",
            body: { status: "pending" },
            panelId: "opportunities",
          },
        );
        await actions.loadPanelState("opportunities");
      } catch (error) {
        p.error = error.message;
        setTimeout(() => {
          p.error = null;
        }, 5000);
        scrollToFirstError();
      } finally {
        p.isSaving = false;
      }
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
    const p = state.panels.opportunities;
    if (p.formData) {
      const field = ref.dataset.field;
      p.formData[field] = ref.value;
      if (p.fieldErrors?.[field]) p.fieldErrors[field] = null;
      // Clear date range error when either date is adjusted and range becomes valid
      if (
        (field === "date_starts" || field === "date_ends") &&
        p.fieldErrors?.date_ends
      ) {
        if (
          !p.formData.date_starts ||
          !p.formData.date_ends ||
          p.formData.date_starts <= p.formData.date_ends
        ) {
          p.fieldErrors.date_ends = null;
        }
      }
    }
  },

  /**
   * Normalize URL fields on blur.
   * Auto-prepends https://, rejects non-http(s) schemes.
   * Mirrors backend InputSanitizer::sanitizeUrl().
   */
  normalizeUrlField() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    const p = state.panels.opportunities;
    const field = ref.dataset.field;
    if (field && p.formData?.[field] !== undefined) {
      p.formData[field] = normalizeUrl(p.formData[field]);
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
    if (p.fieldErrors?.seekers) p.fieldErrors.seekers = null;
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
    if (p.fieldErrors?.category) p.fieldErrors.category = null;
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
      setTimeout(() => {
        p.error = null;
      }, 5000);
      scrollToFirstError();
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
      setTimeout(() => {
        p.error = null;
      }, 5000);
      scrollToFirstError();
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

  // ── Country custom dropdown ────────────────────────────────────────

  toggleCountryDropdown() {
    const { state } = store("launchpad");
    const p = state.panels.opportunities;
    p.isCountryDropdownOpen = !p.isCountryDropdownOpen;
  },

  selectCountry() {
    const { state } = store("launchpad");
    const { item } = getContext();
    const p = state.panels.opportunities;
    // Toggle: clicking the already-selected country clears the selection
    p.formData.country =
      parseInt(p.formData.country) === item.id ? "" : item.id;
    p.isCountryDropdownOpen = false;
    if (p.fieldErrors?.country) p.fieldErrors.country = null;
  },

  countryFocusout(event) {
    const { state } = store("launchpad");
    const wrapper = event.currentTarget;
    if (wrapper.contains(event.relatedTarget)) return;
    state.panels.opportunities.isCountryDropdownOpen = false;
  },

  countryKeydown(event) {
    const { state, actions } = store("launchpad");
    const p = state.panels.opportunities;

    if (event.key === "Escape") {
      p.isCountryDropdownOpen = false;
      event.currentTarget.querySelector(".lp-dropdown__trigger")?.focus();
      return;
    }
    if (event.key === "ArrowDown" && p.isCountryDropdownOpen) {
      if (event.target.closest(".lp-dropdown__item")) return;
      event.preventDefault();
      event.currentTarget.querySelector(".lp-dropdown__item")?.focus();
    }
  },

  /**
   * Keyboard on individual dropdown items (shared pattern).
   * Enter/Space: select via click. ArrowDown/Up: navigate. Escape: close.
   */
  dropdownItemKeydown(event) {
    const li = event.target.closest(".lp-dropdown__item");
    if (!li) return;

    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      li.click();
      return;
    }
    if (event.key === "ArrowDown") {
      event.preventDefault();
      li.nextElementSibling?.focus();
      return;
    }
    if (event.key === "ArrowUp") {
      event.preventDefault();
      const prev = li.previousElementSibling;
      if (prev) {
        prev.focus();
      } else {
        li.closest(".lp-dropdown")
          ?.querySelector(".lp-dropdown__trigger")
          ?.focus();
      }
      return;
    }
    if (event.key === "Escape") {
      li.closest(".lp-dropdown")
        ?.querySelector(".lp-dropdown__trigger")
        ?.focus();
      // Bubbles up to wrapper keydown which closes the dropdown
    }
  },

  // ── Location search ───────────────────────────────────────────────

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
   * Close dropdown when focus leaves the wrapper entirely.
   * Uses focusout + relatedTarget to detect if focus moved outside.
   */
  locationFocusout(event) {
    const { state } = store("launchpad");
    const wrapper = event.currentTarget;
    // relatedTarget is where focus is going — if still inside wrapper, ignore
    if (wrapper.contains(event.relatedTarget)) return;
    // Small delay lets click handlers on <li> fire first
    setTimeout(() => {
      const p = state.panels.opportunities;
      const input = wrapper.querySelector("input");
      if (!input) return;
      // Determine which results to clear by checking which input is in this wrapper
      const val = input.getAttribute("data-wp-on--input") || "";
      if (val.includes("Oblast")) {
        p.formData.resultsOblast = [];
      } else if (val.includes("Raion")) {
        p.formData.resultsRaion = [];
      } else if (val.includes("City")) {
        p.formData.resultsCity = [];
      }
    }, 150);
  },

  /**
   * Keyboard handler on the location search wrapper.
   * Escape: close all dropdowns and return focus to input.
   * ArrowDown: focus first result item.
   */
  locationKeydown(event) {
    const { state } = store("launchpad");
    const p = state.panels.opportunities;
    const wrapper =
      event.currentTarget.closest?.(".location-search-wrapper") ||
      event.currentTarget;

    if (event.key === "Escape") {
      p.formData.resultsOblast = [];
      p.formData.resultsRaion = [];
      p.formData.resultsCity = [];
      wrapper.querySelector("input")?.focus();
      return;
    }

    if (event.key === "ArrowDown") {
      if (event.target.closest(".location-result-item")) return;
      event.preventDefault();
      const first = wrapper.querySelector(".location-result-item");
      if (first) first.focus();
    }
  },

  /**
   * Keyboard handler on individual location result items.
   * Enter/Space: select via click (preserves Interactivity API context).
   * ArrowDown/Up: navigate. Escape: close.
   */
  locationItemKeydown(event) {
    const { actions } = store("launchpad");
    const li = event.target.closest(".location-result-item");
    if (!li) return;

    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      // Delegate to click so getContext() resolves from the directive
      li.click();
      return;
    }

    if (event.key === "ArrowDown") {
      event.preventDefault();
      const next = li.nextElementSibling;
      if (next) next.focus();
      return;
    }

    if (event.key === "ArrowUp") {
      event.preventDefault();
      const prev = li.previousElementSibling;
      if (prev) {
        prev.focus();
      } else {
        li.closest(".location-search-wrapper")?.querySelector("input")?.focus();
      }
      return;
    }

    if (event.key === "Escape") {
      const wrapper = li.closest(".location-search-wrapper");
      if (wrapper) {
        // Trigger the wrapper-level Escape handler via focus + clearing
        wrapper.querySelector("input")?.focus();
        actions.opportunities.locationKeydown(event);
      }
    }
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

  /**
   * Show field error labels alongside native HTML5 validation.
   * Runs on click (before browser blocks submit for invalid fields).
   */
  validateRequired(event) {
    const { state } = store("launchpad");
    const p = state.panels.opportunities;
    const form = event.target.closest("form");

    p.fieldErrors = {};

    if (!form) return;

    form.querySelectorAll("[required]").forEach((el) => {
      if (!el.validity.valid) {
        const field = el.dataset.field;
        if (field && p.validationMessages?.[field]) {
          p.fieldErrors[field] = p.validationMessages[field];
        }
      }
    });

    // Min-length checks (only when field is non-empty — empty is caught by required above)
    const titleLen = p.formData.title?.trim().length || 0;
    if (titleLen > 0 && titleLen < (p.titleLimits?.min || 30)) {
      p.fieldErrors.title = p.validationMessages?.titleMinLength;
    }
    const companyLen = p.formData.company?.trim().length || 0;
    if (companyLen > 0 && companyLen < (p.companyLimits?.min || 2)) {
      p.fieldErrors.company = p.validationMessages?.companyMinLength;
    }
    const descLen = p.formData.description?.trim().length || 0;
    if (descLen > 0 && descLen < (p.descriptionLimits?.min || 50)) {
      p.fieldErrors.description = p.validationMessages?.descriptionMinLength;
    }

    // Checkbox groups (no HTML required attribute to query)
    if (!p.formData.category?.length) {
      p.fieldErrors.category = p.validationMessages?.category;
    }
    if (!p.formData.seekers?.length) {
      p.fieldErrors.seekers = p.validationMessages?.seekers;
    }
    if (
      p.formData.date_starts &&
      p.formData.date_ends &&
      p.formData.date_starts > p.formData.date_ends
    ) {
      p.fieldErrors.date_ends = p.validationMessages?.dateRange;
    }

    if (Object.keys(p.fieldErrors).length) {
      scrollToFirstError();
    }
  },

  /**
   * Navigate to profile panel for onboarding
   */
  goToProfile() {
    const { actions } = store("launchpad");
    actions.setActivePanel("profile", { pushHistory: true });
  },
};
