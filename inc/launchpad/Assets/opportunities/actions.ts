/**
 * Launchpad Store — Opportunities Actions
 *
 * All actions under the `actions.opportunities` namespace.
 * Uses the Store Locator pattern (launchpadStore()) instead of `this`, which
 * data-wp-init and data-wp-on don't bind.
 *
 * File: inc/launchpad/Assets/opportunities/actions.ts
 */

import {
  getContext,
  getElement,
  withSyncEvent,
} from "@wordpress/interactivity";
import { ApiError, isKeyOf } from "../../../shared/Assets/store-utils.ts";
import { launchpadStore } from "../stores.ts";
import type {
  Country,
  ItemContext,
  KatottgPlace,
  Layout,
  LoadedPanel,
  LocationResultKey,
  OpportunitiesData,
  OpportunityForm,
  OpportunityValidationMessages,
} from "../types.ts";
import {
  apiErrorMessage,
  boundElement,
  cancelRequest,
  currentUrl,
  fetchJson,
  flashError,
  focus,
  isListResponse,
  isMediaResponse,
  isObject,
  isOpportunityForm,
  isPlaceList,
  normalizeUrl,
  opportunities,
  scrollToFirstError,
  scrollToPageTop,
  selectionLimit,
  selectionSize,
  STATUSES,
  targetElement,
  writeUrl,
} from "../utils.ts";

type Panel = LoadedPanel<OpportunitiesData>;

/** The file picked for upload, sent on the next save. */
let pendingUpload: File | null = null;

/** Debounce timer per location search box. */
const placeSearchTimers = new Map<
  LocationResultKey,
  ReturnType<typeof setTimeout>
>();

// --- Field allowlists (data-field / value -> state key) ------------------

/** The form fields updateForm() writes from an input's value. */
const TEXT_FIELDS = [
  "title",
  "company",
  "date_starts",
  "date_ends",
  "city",
  "sourcelink",
  "application_form",
  "description",
  "requirements",
  "details",
] as const satisfies readonly (keyof OpportunityForm)[];

/** The fields normalized as URLs on blur. */
const URL_FIELDS = ["sourcelink", "application_form"] as const;

/** The required inputs' messages (validationMessages keys). */
const MESSAGE_KEYS = [
  "title",
  "company",
  "sourcelink",
  "description",
] as const satisfies readonly (keyof OpportunityValidationMessages)[];

const LAYOUTS: readonly Layout[] = ["compact", "card", "grid"];

/**
 * The shortest location search the server takes (SEARCH_MIN_LENGTH in
 * OpportunitiesController); a shorter one is a 400.
 */
const PLACE_SEARCH_MIN = 2;

// --- Helpers ------------------------------------------------------------

/** The `<input>` the action runs on, when it is one. */
function inputElement(): HTMLInputElement | null {
  const { ref } = getElement();
  return ref instanceof HTMLInputElement ? ref : null;
}

/** The value of the input/textarea the action runs on. */
function fieldValue(): { field: string | undefined; value: string } | null {
  const { ref } = getElement();
  if (ref instanceof HTMLInputElement || ref instanceof HTMLTextAreaElement) {
    return { field: ref.dataset.field, value: ref.value };
  }
  return null;
}

/** A form with no values: for a panel that sent no emptyForm. */
const blankForm = (): OpportunityForm => ({
  id: null,
  title: "",
  company: "",
  date_starts: "",
  date_ends: "",
  category: [],
  country: "",
  locations: [],
  city: "",
  sourcelink: "",
  application_form: "",
  beneficiaries: [],
  subcategory: [],
  description: "",
  requirements: "",
  details: "",
  document: null,
  document_id: null,
});

/** A plain copy of a form: the state's arrays mustn't be shared. */
const cloneForm = (form: OpportunityForm): OpportunityForm => ({
  ...form,
  category: [...form.category],
  beneficiaries: [...form.beneficiaries],
  subcategory: [...form.subcategory],
  locations: form.locations.map((place) => ({ ...place })),
  document: form.document ? { ...form.document } : null,
});

/** Add or remove `id` in a list of ids. */
const toggleId = (ids: readonly number[], id: number, on: boolean): number[] =>
  on ? [...new Set([...ids, id])] : ids.filter((other) => other !== id);

/** Show a failed call's message above the panel. */
function showError(p: Panel, error: unknown): void {
  flashError(p, apiErrorMessage(error));
  scrollToFirstError();
}

/**
 * The checks shared by Save and the submit button's click: required text
 * fields, minimum lengths, the checkbox groups and the date range.
 */
function checkForm(p: Panel, { required }: { required: boolean }): void {
  const vm = p.validationMessages ?? {};
  const form = p.formData;
  const minLengths = [
    ["title", p.titleLimits?.min ?? 30, vm.title, vm.titleMinLength],
    ["company", p.companyLimits?.min ?? 2, vm.company, vm.companyMinLength],
    [
      "description",
      p.descriptionLimits?.min ?? 50,
      vm.description,
      vm.descriptionMinLength,
    ],
  ] as const;

  for (const [field, min, emptyMessage, shortMessage] of minLengths) {
    const length = form[field].trim().length;
    if (length === 0) {
      if (required) p.fieldErrors[field] = emptyMessage ?? null;
    } else if (length < min) {
      p.fieldErrors[field] = shortMessage ?? null;
    }
  }
  if (required && !form.sourcelink.trim()) {
    p.fieldErrors.sourcelink = vm.sourcelink ?? null;
  }

  // Checkbox groups (no HTML required attribute to query)
  if (!form.category.length) p.fieldErrors.category = vm.category ?? null;
  if (!form.beneficiaries.length) {
    p.fieldErrors.beneficiaries = vm.beneficiaries ?? null;
  }

  // Selections over a field's limit (data saved before the limits existed)
  // block Save with the REST validator's own message. An "at least one"
  // error already set for the field wins.
  const overLimit = [
    ["category", vm.categoryMax],
    ["beneficiaries", vm.beneficiariesMax],
  ] as const;
  for (const [field, message] of overLimit) {
    const max = selectionLimit(p, field);
    if (!p.fieldErrors[field] && max > 0 && selectionSize(p, field) > max) {
      p.fieldErrors[field] = message ?? null;
    }
  }

  if (form.date_starts && form.date_ends && form.date_starts > form.date_ends) {
    p.fieldErrors.date_ends = vm.dateRange ?? null;
  }
}

/** Does the panel hold any inline error? */
const hasFieldErrors = (p: Panel): boolean =>
  Object.values(p.fieldErrors).some(Boolean);

/**
 * Save the form: upload a picked document first, then create (POST) or
 * update (PUT) the opportunity. `status` overrides the form's status in the
 * request only, so a failed check leaves the form as it was.
 */
async function saveForm(status?: "pending"): Promise<void> {
  const { state, actions } = launchpadStore();
  const p = opportunities(state);
  if (!p || p.isSaving) return;

  p.isSaving = true;
  p.error = null;
  p.fieldErrors = {};

  // Normalize URL fields before validation (mirrors backend sanitizeUrl)
  p.formData.sourcelink = normalizeUrl(p.formData.sourcelink);
  p.formData.application_form = normalizeUrl(p.formData.application_form);

  checkForm(p, { required: true });
  if (hasFieldErrors(p)) {
    p.isSaving = false;
    scrollToFirstError();
    return;
  }

  const { restUrl } = state.launchpadSettings;
  try {
    // STEP A: Upload (if pending)
    if (pendingUpload) {
      p.isUploading = true;
      const body = new FormData();
      body.append("file", pendingUpload);
      const media = await fetchJson(`${restUrl}media`, isMediaResponse, {
        method: "POST",
        body,
      });
      if (!media) return;

      p.formData.document_id = media.id;
      if (p.formData.document) {
        p.formData.document = { ...p.formData.document, isPending: false };
      }
      pendingUpload = null;
      p.isUploading = false;
    }

    // STEP B: Save Opportunity - the fields the endpoint reads
    // (OpportunitiesController::saveOpportunity); not the document's UI
    // object nor the location searches.
    const form = p.formData;
    const payload = {
      title: form.title,
      status: status ?? form.status,
      company: form.company,
      date_starts: form.date_starts,
      date_ends: form.date_ends,
      category: form.category,
      country: form.country,
      locations: form.locations,
      city: form.city,
      sourcelink: form.sourcelink,
      application_form: form.application_form,
      subcategory: form.subcategory,
      beneficiaries: form.beneficiaries,
      description: form.description,
      requirements: form.requirements,
      details: form.details,
      document_id: form.document_id,
    };
    const saved = await fetchJson(
      `${restUrl}opportunities${form.id ? `/${form.id}` : ""}`,
      isObject,
      { method: form.id ? "PUT" : "POST", body: payload },
    );
    if (!saved) return;

    actions.opportunities.cancel();
    scrollToPageTop();
    await actions.loadPanelState("opportunities");
  } catch (error) {
    // The server's per-field messages first; the banner only when there
    // are none, or "Please correct the highlighted fields." would repeat
    // the inline guidance.
    p.fieldErrors =
      error instanceof ApiError && error.fieldErrors
        ? { ...error.fieldErrors }
        : {};
    if (!hasFieldErrors(p)) flashError(p, apiErrorMessage(error));
    scrollToFirstError();
  } finally {
    p.isSaving = false;
    p.isUploading = false;
  }
}

/**
 * Reload the list's first page for the status filters. No status ticked is
 * an empty list, not a request: the boxes describe the list (the server
 * reads a missing `statuses` as "all").
 */
async function fetchFilteredList(p: Panel): Promise<void> {
  const statuses = p.filters?.statuses ?? STATUSES;
  if (!statuses.length) {
    // A request still in flight mustn't land after the list was emptied.
    cancelRequest("opportunities:list");
    p.items = [];
    p.total = 0;
    p.totalPages = 0;
    p.hasMore = false;
    p.isLoading = false;
    return;
  }

  const { restUrl } = launchpadStore().state.launchpadSettings;
  p.isLoading = true;

  const params = new URLSearchParams();
  for (const status of statuses) params.append("statuses[]", status);
  params.append("per_page", String(p.perPage));

  try {
    const data = await fetchJson(
      `${restUrl}opportunities?${params.toString()}`,
      isListResponse,
      { abortKey: "opportunities:list" },
    );
    if (data) {
      p.items = data.items;
      p.total = data.total;
      p.totalPages = data.total_pages;
      p.hasMore = (p.page ?? 1) < data.total_pages;
    }
  } catch (error) {
    showError(p, error);
  } finally {
    p.isLoading = false;
  }
}

/**
 * Debounced KATOTTG search for one box. Each box has its own timer and
 * request, so typing in a second box doesn't cancel the first one's search.
 */
function searchPlaces(
  query: string,
  levels: readonly number[],
  resultKey: LocationResultKey,
): void {
  const { state } = launchpadStore();
  const p = opportunities(state);
  if (!p) return;

  // Each keystroke restarts the box's debounce; a query too short to search
  // also drops the box's request in flight and its results.
  clearTimeout(placeSearchTimers.get(resultKey));
  if (query.trim().length < PLACE_SEARCH_MIN) {
    cancelRequest(`places:${resultKey}`);
    p.formData[resultKey] = [];
    return;
  }

  placeSearchTimers.set(
    resultKey,
    setTimeout(async () => {
      const params = new URLSearchParams({ search: query });
      for (const level of levels) params.append("levels[]", String(level));
      try {
        const results = await fetchJson(
          `${state.launchpadSettings.restUrl}opportunities/locations?${params.toString()}`,
          isPlaceList,
          { abortKey: `places:${resultKey}` },
        );
        if (results) p.formData[resultKey] = results;
      } catch (e) {
        console.error(e);
        p.formData[resultKey] = [];
      }
    }, 300),
  );
}

/** Clear every location result list. */
function closePlaceResults(p: Panel): void {
  p.formData.resultsOblast = [];
  p.formData.resultsRaion = [];
  p.formData.resultsCity = [];
}

/** Close the country dropdown and reset its search. */
function closeCountryDropdown(p: Panel): void {
  p.isCountryDropdownOpen = false;
  p.countrySearch = "";
}

/** The trigger button of the dropdown `el` belongs to. */
const dropdownTrigger = (el: Element): Element | null =>
  el.closest(".lp-dropdown")?.querySelector(".lp-dropdown__trigger") ?? null;

/** Set ?view (and ?id) as a new Back step, then route. */
function openView(view: "add" | "edit", id?: string): void {
  const url = currentUrl();
  url.searchParams.set("view", view);
  if (id) url.searchParams.set("id", id);
  else url.searchParams.delete("id");
  writeUrl(url);
  launchpadStore().actions.syncStateFromUrl();
}

// --- Actions ------------------------------------------------------------

export const opportunitiesActions = {
  resetForm(): void {
    const p = opportunities(launchpadStore().state);
    if (!p) return;
    p.formData = p.emptyForm ? cloneForm(p.emptyForm) : blankForm();
    p.error = null;
    p.fieldErrors = {};
  },

  async loadSingle(id: number): Promise<void> {
    const { state, actions } = launchpadStore();
    const p = opportunities(state);
    if (!p) return;

    p.isLoading = true;
    actions.opportunities.resetForm();

    try {
      const data = await fetchJson(
        `${state.launchpadSettings.restUrl}opportunities/${id}`,
        isOpportunityForm,
        { abortKey: "opportunities:single" },
      );
      if (data) p.formData = data;
    } catch (error) {
      showError(p, error);
    } finally {
      p.isLoading = false;
    }
  },

  openAdd(): void {
    launchpadStore().actions.opportunities.resetForm();
    openView("add");
  },

  triggerFileSelect(): void {
    document.getElementById("opp-doc-upload")?.click();
  },

  handleFileSelect(event: Event): void {
    const input = event.target;
    const p = opportunities(launchpadStore().state);
    if (!(input instanceof HTMLInputElement) || !p) return;
    const file = input.files?.[0];
    if (!file) return;

    // Client-side size guard: MediaService's own cap, hydrated
    if (file.size > p.uploadMaxBytes) {
      p.fieldErrors.document = p.validationMessages?.document ?? null;
      input.value = "";
      return;
    }

    // Clear previous document error on valid selection
    if (p.fieldErrors.document) p.fieldErrors.document = null;

    pendingUpload = file;

    // UI Preview. The id stays 0 until the upload on save returns one.
    p.formData.document = {
      name: file.name,
      size: `${(file.size / 1024 / 1024).toFixed(2)} MB`,
      isPending: true,
    };
    p.formData.document_id = 0;
  },

  removeDocument(): void {
    const p = opportunities(launchpadStore().state);
    if (!p) return;

    pendingUpload = null;
    p.formData.document = null;
    p.formData.document_id = 0; // 0 signals backend to delete existing
    if (p.fieldErrors.document) p.fieldErrors.document = null;

    const input = document.getElementById("opp-doc-upload");
    if (input instanceof HTMLInputElement) input.value = "";
  },

  openEdit(event: Event): void {
    const button = targetElement(event)?.closest("button");
    const id = button?.dataset.id;
    if (id) openView("edit", id);
  },

  /** The form's submit. withSyncEvent: it cancels the native submit. */
  save: withSyncEvent((event: Event): void => {
    event.preventDefault();
    void saveForm();
  }),

  submitForReview(): void {
    const p = opportunities(launchpadStore().state);
    if (p) p.confirmPopup = { isOpen: true, itemId: null, source: "form" };
  },

  quickSubmit(): void {
    const { item } = getContext<Partial<ItemContext<{ id?: number }>>>();
    const p = opportunities(launchpadStore().state);
    if (!item?.id || !p) return;
    p.confirmPopup = { isOpen: true, itemId: item.id, source: "list" };
  },

  closeConfirm(): void {
    const p = opportunities(launchpadStore().state);
    if (p) p.confirmPopup = { isOpen: false, itemId: null, source: null };
  },

  async confirmSubmit(): Promise<void> {
    const { state, actions } = launchpadStore();
    const p = opportunities(state);
    if (!p?.confirmPopup) return;
    const { source, itemId } = p.confirmPopup;

    // Close popup immediately
    p.confirmPopup = { isOpen: false, itemId: null, source: null };

    if (source === "form") {
      // From the edit/add form — save with pending status
      await saveForm("pending");
    } else if (source === "list" && itemId) {
      // From the list card — quick status change
      p.isSaving = true;
      try {
        await fetchJson(
          `${state.launchpadSettings.restUrl}opportunities/${itemId}/status`,
          isObject,
          { method: "POST", body: { status: "pending" } },
        );
        await actions.loadPanelState("opportunities");
      } catch (error) {
        showError(p, error);
      } finally {
        p.isSaving = false;
      }
    }
  },

  cancel(): void {
    const url = currentUrl();
    url.searchParams.delete("view");
    url.searchParams.delete("id");
    writeUrl(url);
    launchpadStore().actions.syncStateFromUrl();
    scrollToPageTop();
  },

  updateForm(): void {
    const input = fieldValue();
    const p = opportunities(launchpadStore().state);
    if (!input || !p || !isKeyOf(TEXT_FIELDS, input.field)) return;
    const { field, value } = input;

    p.formData[field] = value;
    if (p.fieldErrors[field]) p.fieldErrors[field] = null;

    // Clear the date range error once the range is valid again
    if (
      (field === "date_starts" || field === "date_ends") &&
      p.fieldErrors.date_ends
    ) {
      const { date_starts, date_ends } = p.formData;
      if (!date_starts || !date_ends || date_starts <= date_ends) {
        p.fieldErrors.date_ends = null;
      }
    }
  },

  /**
   * Normalize URL fields on blur.
   * Auto-prepends https://, rejects non-http(s) schemes.
   * Mirrors backend InputSanitizer::sanitizeUrl().
   */
  normalizeUrlField(): void {
    const field = inputElement()?.dataset.field;
    const p = opportunities(launchpadStore().state);
    if (!p || !isKeyOf(URL_FIELDS, field)) return;
    p.formData[field] = normalizeUrl(p.formData[field]);
  },

  toggleBeneficiary(): void {
    const { state } = launchpadStore();
    const ref = inputElement();
    const p = opportunities(state);
    if (!ref || !p) return;
    if (ref.checked && state.isBeneficiariesLimitReached) {
      ref.checked = false; // guard behind state.isBeneficiaryOptionDisabled
      return;
    }
    p.formData.beneficiaries = toggleId(
      p.formData.beneficiaries,
      Number(ref.value),
      ref.checked,
    );
    if (p.fieldErrors.beneficiaries) p.fieldErrors.beneficiaries = null;
  },

  toggleCategory(): void {
    const { state } = launchpadStore();
    const ref = inputElement();
    const p = opportunities(state);
    if (!ref || !p) return;
    if (ref.checked && state.isCategoryLimitReached) {
      ref.checked = false; // guard behind state.isCategoryOptionDisabled
      return;
    }
    p.formData.category = toggleId(
      p.formData.category,
      Number(ref.value),
      ref.checked,
    );
    if (p.fieldErrors.category) p.fieldErrors.category = null;
  },

  async loadMore(): Promise<void> {
    const { state } = launchpadStore();
    const p = opportunities(state);
    if (!p || p.isLoading) return;

    p.isLoading = true;
    try {
      const nextPage = (p.page ?? 1) + 1;
      const params = new URLSearchParams({
        page: String(nextPage),
        per_page: String(p.perPage),
      });
      for (const status of p.filters?.statuses ?? []) {
        params.append("statuses[]", status);
      }

      const data = await fetchJson(
        `${state.launchpadSettings.restUrl}opportunities?${params.toString()}`,
        isListResponse,
        { abortKey: "opportunities:list" },
      );
      if (data) {
        p.items = [...p.items, ...data.items];
        p.page = nextPage;
        p.hasMore = nextPage < data.total_pages;
      }
    } catch (error) {
      showError(p, error);
    } finally {
      p.isLoading = false;
    }
  },

  /** data-wp-init on the status filters: all statuses at first. */
  initFilters(): void {
    const p = opportunities(launchpadStore().state);
    if (p && !p.filters) p.filters = { statuses: [...STATUSES] };
  },

  async toggleFilter(): Promise<void> {
    const ref = inputElement();
    const p = opportunities(launchpadStore().state);
    const status = ref?.value;
    if (!ref || !p || !isKeyOf(STATUSES, status)) return;

    const active = p.filters?.statuses ?? [];
    p.filters = {
      statuses: ref.checked
        ? [...new Set([...active, status])]
        : active.filter((s) => s !== status),
    };
    p.page = 1;

    await fetchFilteredList(p);
  },

  setLayout(): void {
    const layout = inputElement()?.value;
    const p = opportunities(launchpadStore().state);
    if (!p || !isKeyOf(LAYOUTS, layout)) return;

    p.layout = layout;
    p.isLayoutCompact = layout === "compact";
    p.isLayoutCard = layout === "card";
    p.isLayoutGrid = layout === "grid";
  },

  // ── Country custom dropdown ────────────────────────────────────────

  toggleCountryDropdown(): void {
    const p = opportunities(launchpadStore().state);
    if (!p) return;
    p.isCountryDropdownOpen = !p.isCountryDropdownOpen;
    // Reset search on close so the next open starts from the full list
    if (!p.isCountryDropdownOpen) p.countrySearch = "";
  },

  selectCountry(): void {
    const { item } = getContext<Partial<ItemContext<Country>>>();
    const p = opportunities(launchpadStore().state);
    if (!item || !p) return;
    // Toggle: clicking the already-selected country clears the selection
    p.formData.country = Number(p.formData.country) === item.id ? "" : item.id;
    closeCountryDropdown(p);
    if (p.fieldErrors.country) p.fieldErrors.country = null;
  },

  searchCountries(): void {
    const value = inputElement()?.value;
    const p = opportunities(launchpadStore().state);
    if (p && value !== undefined) p.countrySearch = value;
  },

  /** withSyncEvent: reads currentTarget. */
  countryFocusout: withSyncEvent((event: FocusEvent): void => {
    const wrapper = boundElement(event);
    const p = opportunities(launchpadStore().state);
    if (!wrapper || !p) return;
    if (
      event.relatedTarget instanceof Node &&
      wrapper.contains(event.relatedTarget)
    ) {
      return;
    }
    closeCountryDropdown(p);
  }),

  /** withSyncEvent: reads currentTarget, cancels Enter / ArrowDown. */
  countryKeydown: withSyncEvent((event: KeyboardEvent): void => {
    const wrapper = boundElement(event);
    const target = targetElement(event);
    const p = opportunities(launchpadStore().state);
    if (!wrapper || !p) return;

    if (event.key === "Escape") {
      closeCountryDropdown(p);
      focus(wrapper.querySelector(".lp-dropdown__trigger"));
      return;
    }
    // Enter inside the search row would submit the surrounding <form>;
    // intercept it and move focus to the first result instead.
    if (event.key === "Enter" && target?.closest(".lp-dropdown__search")) {
      event.preventDefault();
      focus(wrapper.querySelector(".lp-dropdown__item"));
      return;
    }
    if (event.key === "ArrowDown" && p.isCountryDropdownOpen) {
      if (target?.closest(".lp-dropdown__item")) return;
      event.preventDefault();
      focus(wrapper.querySelector(".lp-dropdown__item"));
    }
  }),

  /**
   * Keyboard on individual dropdown items (shared pattern).
   * Enter/Space: select via click. ArrowDown/Up: navigate. Escape: close.
   * withSyncEvent: cancels the keys it handles.
   */
  dropdownItemKeydown: withSyncEvent((event: KeyboardEvent): void => {
    const li = targetElement(event)?.closest(".lp-dropdown__item");
    if (!(li instanceof HTMLElement)) return;

    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      li.click();
      return;
    }
    if (event.key === "ArrowDown") {
      event.preventDefault();
      focus(li.nextElementSibling);
      return;
    }
    if (event.key === "ArrowUp") {
      event.preventDefault();
      // Walk backward over non-focusable header rows (no-results, search
      // wrapper) until we hit something focusable. The first focusable
      // thing going up is the search input inside .lp-dropdown__search.
      let prev = li.previousElementSibling;
      while (prev && !prev.matches(".lp-dropdown__item")) {
        const input = prev.querySelector("input");
        if (input) {
          input.focus();
          return;
        }
        prev = prev.previousElementSibling;
      }
      focus(prev ?? dropdownTrigger(li));
      return;
    }
    if (event.key === "Escape") {
      focus(dropdownTrigger(li));
      // Bubbles up to wrapper keydown which closes the dropdown
    }
  }),

  // ── Location search ───────────────────────────────────────────────

  /** Search Oblasts (Level 1). */
  searchKatottgOblast(): void {
    const value = inputElement()?.value ?? "";
    const p = opportunities(launchpadStore().state);
    if (!p) return;
    p.formData.searchOblast = value;
    searchPlaces(value, [1], "resultsOblast");
  },

  /** Search Raions (Level 2) and hromadas (3). */
  searchKatottgRaion(): void {
    const value = inputElement()?.value ?? "";
    const p = opportunities(launchpadStore().state);
    if (!p) return;
    p.formData.searchRaion = value;
    searchPlaces(value, [2, 3], "resultsRaion");
  },

  /** Search Cities/Settlements (Level 4 & 5). */
  searchKatottgCity(): void {
    const value = inputElement()?.value ?? "";
    const p = opportunities(launchpadStore().state);
    if (!p) return;
    p.formData.searchCity = value;
    searchPlaces(value, [4, 5], "resultsCity");
  },

  /**
   * Add Location: works for all 3 boxes. The place's level tells which box
   * it came from, whose search is then cleared.
   */
  addLocation(): void {
    const { item } = getContext<Partial<ItemContext<KatottgPlace>>>();
    const p = opportunities(launchpadStore().state);
    if (!item || !p) return;

    // 1. Add to shared chips list
    if (!p.formData.locations.some((l) => l.code === item.code)) {
      p.formData.locations = [...p.formData.locations, { ...item }];
    }

    // 2. Clear the box it came from
    const level = Number(item.level);
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

  /** withSyncEvent: the chip's button would submit the form. */
  removeLocation: withSyncEvent((event: Event): void => {
    event.preventDefault();
    const { item } = getContext<Partial<ItemContext<KatottgPlace>>>();
    const p = opportunities(launchpadStore().state);
    if (!item || !p) return;
    p.formData.locations = p.formData.locations.filter(
      (l) => l.code !== item.code,
    );
  }),

  /**
   * Close a box's results when focus leaves its wrapper entirely.
   * withSyncEvent: reads currentTarget.
   */
  locationFocusout: withSyncEvent((event: FocusEvent): void => {
    const wrapper = boundElement(event);
    if (!wrapper) return;
    // relatedTarget is where focus is going — if still inside wrapper, ignore
    if (
      event.relatedTarget instanceof Node &&
      wrapper.contains(event.relatedTarget)
    ) {
      return;
    }
    // Small delay lets click handlers on <li> fire first
    setTimeout(() => {
      const p = opportunities(launchpadStore().state);
      const input = wrapper.querySelector("input");
      if (!p || !input) return;
      // Which box this is: its input's search action names it.
      const action = input.getAttribute("data-wp-on--input") ?? "";
      if (action.includes("Oblast")) p.formData.resultsOblast = [];
      else if (action.includes("Raion")) p.formData.resultsRaion = [];
      else if (action.includes("City")) p.formData.resultsCity = [];
    }, 150);
  }),

  /**
   * Keyboard on a location search wrapper.
   * Escape: close all result lists and return focus to the input.
   * ArrowDown: focus the first result.
   * withSyncEvent: reads currentTarget, cancels ArrowDown.
   */
  locationKeydown: withSyncEvent((event: KeyboardEvent): void => {
    const bound = boundElement(event);
    const p = opportunities(launchpadStore().state);
    if (!bound || !p) return;
    const wrapper = bound.closest(".location-search-wrapper") ?? bound;

    if (event.key === "Escape") {
      closePlaceResults(p);
      focus(wrapper.querySelector("input"));
      return;
    }

    if (event.key === "ArrowDown") {
      if (targetElement(event)?.closest(".location-result-item")) return;
      event.preventDefault();
      focus(wrapper.querySelector(".location-result-item"));
    }
  }),

  /**
   * Keyboard on a location result.
   * Enter/Space: select via click (keeps the Interactivity API context).
   * ArrowDown/Up: navigate. Escape bubbles to locationKeydown, which closes.
   * withSyncEvent: cancels the keys it handles.
   */
  locationItemKeydown: withSyncEvent((event: KeyboardEvent): void => {
    const li = targetElement(event)?.closest(".location-result-item");
    if (!(li instanceof HTMLElement)) return;

    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      // Delegate to click so getContext() resolves from the directive
      li.click();
      return;
    }

    if (event.key === "ArrowDown") {
      event.preventDefault();
      focus(li.nextElementSibling);
      return;
    }

    if (event.key === "ArrowUp") {
      event.preventDefault();
      focus(
        li.previousElementSibling ??
          li.closest(".location-search-wrapper")?.querySelector("input"),
      );
    }
  }),

  /**
   * Open the native date picker of the input next to the icon button.
   * withSyncEvent: reads currentTarget.
   */
  openDatePicker: withSyncEvent((event: Event): void => {
    const dateInput = boundElement(event)
      ?.closest(".input-date-iconed")
      ?.querySelector('input[type="date"]');
    if (!(dateInput instanceof HTMLInputElement)) return;

    // 1. Modern: showPicker() (Chrome, Edge, Firefox, Opera)
    if ("showPicker" in HTMLInputElement.prototype) {
      try {
        dateInput.showPicker();
        return;
      } catch {
        // Fallback if showPicker fails (e.g. prevented by browser security)
      }
    }

    // 2. Fallback: Focus (Required for iOS/Android to trigger native wheels)
    dateInput.focus();

    // 3. Desktop Safari fallback: clicks open it where focus doesn't.
    const isTouch = navigator.maxTouchPoints > 0 || "ontouchstart" in window;
    if (!isTouch) {
      for (const type of ["mousedown", "click", "mouseup"]) {
        dateInput.dispatchEvent(
          new MouseEvent(type, {
            bubbles: true,
            cancelable: true,
            view: window,
          }),
        );
      }
    }
  }),

  /**
   * Show field error labels alongside native HTML5 validation.
   * Runs on click (before browser blocks submit for invalid fields).
   */
  validateRequired(event: Event): void {
    const p = opportunities(launchpadStore().state);
    const form = targetElement(event)?.closest("form");
    if (!p) return;

    p.fieldErrors = {};
    if (!form) return;

    const vm = p.validationMessages ?? {};
    for (const el of form.querySelectorAll("[required]")) {
      if (
        !(
          el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement
        ) ||
        el.validity.valid
      ) {
        continue;
      }
      const field = el.dataset.field;
      if (isKeyOf(MESSAGE_KEYS, field) && vm[field]) {
        p.fieldErrors[field] = vm[field];
      }
    }

    // Min lengths (empty is caught by required above), checkbox groups,
    // selection limits, date range
    checkForm(p, { required: false });

    if (hasFieldErrors(p)) scrollToFirstError();
  },

  /**
   * Navigate to profile panel for onboarding
   */
  goToProfile(): void {
    launchpadStore().actions.setActivePanel("profile", { pushHistory: true });
  },
};
