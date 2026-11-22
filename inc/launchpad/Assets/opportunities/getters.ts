/**
 * Launchpad Store — Opportunities Getters
 *
 * Computed state for the opportunities panel, mixed into state with
 * extendState(): 'this' is the state proxy (ThisType below). Each reads the
 * panel through opportunities(), which is undefined until it has loaded.
 *
 * File: inc/launchpad/Assets/opportunities/getters.ts
 */

import { getContext } from "@wordpress/interactivity";
import type {
  CategoryContext,
  Country,
  ItemContext,
  LaunchpadState,
  OpportunitiesGetters,
  OpportunityStatus,
} from "../types.ts";
import {
  isStatusFilterActive,
  opportunities,
  selectionLimit,
  selectionSize,
} from "../utils.ts";

/** "n / max" for a text field's character counter. */
const counter = (length: number, max: number): string => `${length} / ${max}`;

/** Is `id` among `selected`? Loose: the ids from PHP may be strings. */
const includesId = (selected: readonly number[], id: unknown): boolean =>
  selected.some((s) => s == id);

/**
 * The status of the list card in context, else of the form being edited
 * (undefined for a new one).
 */
function currentStatus(state: LaunchpadState): OpportunityStatus | undefined {
  const { item } =
    getContext<
      Partial<ItemContext<{ id?: number; status?: OpportunityStatus }>>
    >();
  if (item?.id) return item.status;
  const p = opportunities(state);
  return p?.currentView === "edit" ? p.formData.status : undefined;
}

export const opportunitiesGetters = {
  /** The onboarding screen, while the profile is incomplete. */
  get isOppOnboardingVisible(): boolean {
    return opportunities(this)?.isLocked === true;
  },

  /** The list - unless locked. */
  get isOppListVisible(): boolean {
    const p = opportunities(this);
    return !!p && !p.isLocked && p.currentView === "list";
  },

  /** The form (add or edit) - unless locked. */
  get isOppFormVisible(): boolean {
    const p = opportunities(this);
    return !!p && !p.isLocked && p.currentView !== "list";
  },

  get hasOppItems(): boolean {
    return !!opportunities(this)?.items.length;
  },

  /** "No opportunities yet": none at all, not just none the filter keeps. */
  get showOppEmpty(): boolean {
    const p = opportunities(this);
    if (p && isStatusFilterActive(p)) return false;
    return !p?.isLoading && !this.hasOppItems;
  },

  /** The status filter leaves nothing (the user has opportunities). */
  get showOppFilteredEmpty(): boolean {
    const p = opportunities(this);
    return !!p && isStatusFilterActive(p) && !p.isLoading && !this.hasOppItems;
  },

  get showOppGrid(): boolean {
    return this.hasOppItems;
  },

  /** The form's heading for the current view. */
  get opportunityFormHeaders(): string | undefined {
    const p = opportunities(this);
    return p?.currentView === "add"
      ? p.formHeaders?.newOpportunity
      : p?.formHeaders?.editOpportunity;
  },

  // ──────────────────────────────────────────────────────────────
  // CONTEXT-AWARE GETTERS
  // ──────────────────────────────────────────────────────────────

  /** data-wp-bind--checked="state.isBeneficiaryChecked" */
  get isBeneficiaryChecked(): boolean {
    const { item } = getContext<Partial<ItemContext<{ id: number }>>>();
    const selected = opportunities(this)?.formData.beneficiaries ?? [];
    return includesId(selected, item?.id);
  },

  // --- Selection limits (selectionLimits from PHP; same as the REST validators) ---
  // Counter "n / max" like the char counters; at the limit the unticked
  // options are disabled (ticked ones stay enabled, so over-limit data saved
  // before the limit existed can be unticked back down).

  get beneficiariesCounterText(): string {
    const p = opportunities(this);
    return counter(
      selectionSize(p, "beneficiaries"),
      selectionLimit(p, "beneficiaries"),
    );
  },

  get isBeneficiariesLimitReached(): boolean {
    const p = opportunities(this);
    const max = selectionLimit(p, "beneficiaries");
    return max > 0 && selectionSize(p, "beneficiaries") >= max;
  },

  /** data-wp-bind--disabled="state.isBeneficiaryOptionDisabled" */
  get isBeneficiaryOptionDisabled(): boolean {
    return this.isBeneficiariesLimitReached && !this.isBeneficiaryChecked;
  },

  get categoryCounterText(): string {
    const p = opportunities(this);
    return counter(selectionSize(p, "category"), selectionLimit(p, "category"));
  },

  get isCategoryLimitReached(): boolean {
    const p = opportunities(this);
    const max = selectionLimit(p, "category");
    return max > 0 && selectionSize(p, "category") >= max;
  },

  /** data-wp-bind--disabled="state.isCategoryOptionDisabled" */
  get isCategoryOptionDisabled(): boolean {
    return this.isCategoryLimitReached && !this.isCategoryChecked;
  },

  /** data-wp-bind--checked="state.isCategoryChecked" (context.child). */
  get isCategoryChecked(): boolean {
    const { child } = getContext<Partial<CategoryContext>>();
    const selected = opportunities(this)?.formData.category ?? [];
    return includesId(selected, child?.id);
  },

  // Category groups: one <details> per parent category, context.item = parent.

  /** Id of the group's title — labels the group's checkboxes (aria-labelledby). */
  get categoryGroupTitleId(): string {
    const { item } = getContext<Partial<CategoryContext>>();
    return `opp-category-group-${item?.id}`;
  },

  /** How many of the current group's categories are selected. */
  get categoryGroupSelectedCount(): number {
    const { item } = getContext<Partial<CategoryContext>>();
    const selected = opportunities(this)?.formData.category ?? [];
    return (item?.children ?? []).filter((c) => includesId(selected, c.id))
      .length;
  },

  /** "N selected" for the group's summary; the pattern is translated in PHP. */
  get categoryGroupCountLabel(): string {
    const pattern = opportunities(this)?.categoryCountLabel ?? "%d";
    return pattern.replace("%d", String(this.categoryGroupSelectedCount));
  },

  /** Can the current card / form be edited? A new form always can. */
  get canEdit(): boolean {
    const { item } = getContext<Partial<ItemContext<{ id?: number }>>>();
    if (!item?.id && opportunities(this)?.currentView === "add") return true;
    return currentStatus(this) === "draft";
  },

  /** Is the current card / form published? */
  get isPublish(): boolean {
    return currentStatus(this) === "publish";
  },

  get isCountryDropdownOpen(): boolean {
    return !!opportunities(this)?.isCountryDropdownOpen;
  },

  /** The selected country's name. */
  get selectedCountryLabel(): string {
    const p = opportunities(this);
    const id = Number(p?.formData.country);
    if (!id) return "";
    return p?.options.countries?.find((c) => c.id === id)?.name ?? "";
  },

  /** Is the country in context the selected one? */
  get isCountryItemSelected(): boolean {
    const { item } = getContext<Partial<ItemContext<Country>>>();
    const selected = Number(opportunities(this)?.formData.country);
    return !!selected && selected === item?.id;
  },

  /**
   * Country list driving the dropdown UI. Pure composition over the
   * cached options.countries:
   *   - When a search query is active, filter case-insensitively and
   *     keep the natural alphabetical order — the previously-selected
   *     country is NOT pinned, so filter results behave intuitively.
   *   - When the query is empty, pin the selected country to the top
   *     so returning users see their previous choice without scrolling.
   */
  get countriesDisplayList(): Country[] {
    const p = opportunities(this);
    const all = p?.options.countries ?? [];
    const q = (p?.countrySearch ?? "").toLowerCase().trim();

    if (q) return all.filter((c) => c.name.toLowerCase().includes(q));

    const selectedId = Number(p?.formData.country);
    const idx = selectedId ? all.findIndex((c) => c.id === selectedId) : -1;
    const selected = all[idx];
    if (idx <= 0 || !selected) return all;
    return [selected, ...all.slice(0, idx), ...all.slice(idx + 1)];
  },

  /**
   * True when the user typed a query and nothing matches — drives the
   * "No countries match" row inside the dropdown.
   */
  get hasNoCountryResults(): boolean {
    const q = (opportunities(this)?.countrySearch ?? "").trim();
    return !!q && this.countriesDisplayList.length === 0;
  },

  /** Ukraine: the KATOTTG location search applies. */
  get isUkraineSelected(): boolean {
    const name = this.selectedCountryLabel;
    return name === "Ukraine" || name === "Україна";
  },

  /** Title character counter text: "N / max" */
  get titleCounterText(): string {
    const p = opportunities(this);
    return counter(p?.formData.title.length ?? 0, p?.titleLimits?.max ?? 108);
  },

  /** Is the title shorter than the minimum required length? */
  get isTitleTooShort(): boolean {
    const p = opportunities(this);
    const len = p?.formData.title.length ?? 0;
    return len > 0 && len < (p?.titleLimits?.min ?? 30);
  },

  get isCompanyTooShort(): boolean {
    const p = opportunities(this);
    const len = p?.formData.company.length ?? 0;
    return len > 0 && len < (p?.companyLimits?.min ?? 2);
  },

  get isDescriptionTooShort(): boolean {
    const p = opportunities(this);
    const len = p?.formData.description.length ?? 0;
    return len > 0 && len < (p?.descriptionLimits?.min ?? 50);
  },

  // --- Textarea character counters ---

  get descriptionCounterText(): string {
    const p = opportunities(this);
    return counter(
      p?.formData.description.length ?? 0,
      p?.textareaLimits?.description ?? 4000,
    );
  },

  get isDescriptionNearLimit(): boolean {
    const p = opportunities(this);
    const max = p?.textareaLimits?.description ?? 4000;
    return (p?.formData.description.length ?? 0) >= max * 0.9;
  },

  get isDescriptionCounterWarn(): boolean {
    return this.isDescriptionTooShort || this.isDescriptionNearLimit;
  },

  get requirementsCounterText(): string {
    const p = opportunities(this);
    return counter(
      p?.formData.requirements.length ?? 0,
      p?.textareaLimits?.requirements ?? 3000,
    );
  },

  get isRequirementsNearLimit(): boolean {
    const p = opportunities(this);
    const max = p?.textareaLimits?.requirements ?? 3000;
    return (p?.formData.requirements.length ?? 0) >= max * 0.9;
  },

  get detailsCounterText(): string {
    const p = opportunities(this);
    return counter(
      p?.formData.details.length ?? 0,
      p?.textareaLimits?.details ?? 2000,
    );
  },

  get isDetailsNearLimit(): boolean {
    const p = opportunities(this);
    const max = p?.textareaLimits?.details ?? 2000;
    return (p?.formData.details.length ?? 0) >= max * 0.9;
  },

  // --- Location dropdown state (for aria-expanded + hidden binding) ---

  get hasOblastResults(): boolean {
    return !!opportunities(this)?.formData.resultsOblast?.length;
  },

  get hasRaionResults(): boolean {
    return !!opportunities(this)?.formData.resultsRaion?.length;
  },

  get hasCityResults(): boolean {
    return !!opportunities(this)?.formData.resultsCity?.length;
  },
} satisfies OpportunitiesGetters & ThisType<LaunchpadState>;
