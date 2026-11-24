/**
 * Listing Store — Types
 *
 * The listing's contracts with its PHP side, which used to live in comments.
 * Each type names the PHP that produces the data: change them together.
 * Types only - the store accessors are in ./stores.ts.
 *
 * File: inc/listing/Assets/types.ts
 */
import type { SyncAwareAction } from "../../shared/Assets/store-utils.ts";
import type { filterActions } from "./filters/actions.ts";
import type { gridActions } from "./grid/actions.ts";

// --- Query --------------------------------------------------------------

/** state.query - Listing\Core\ListingQuery::toArray(). */
export interface ListingQuery {
  s: string;
  /** Term ids of category-oportunities. */
  category: number[];
  /** ISO numeric country ids. */
  country: number[];
  location: string;
  /** Term ids of category-beneficiaries. */
  beneficiaries: number[];
  /** Only the user's favorites; always false for guests. */
  favorites: boolean;
  page: number;
}

/** The query's id-list filters (the checkbox lists). */
export type ListFilterKey = "category" | "country" | "beneficiaries";

/** A sidebar filter group's id (context.filterId): every key but page. */
export type FilterId = Exclude<keyof ListingQuery, "page">;

// --- Facets -------------------------------------------------------------

/** A flat facet option - ListingService::getFacets(). */
export interface FacetOption {
  id: number;
  label: string;
  count: number;
}

/** ListingService::buildCountryFacets(): `code` is what the URL carries. */
export interface CountryOption extends FacetOption {
  code: string;
}

/** TermCountingService::buildFacetedTree(), enriched by enrichCategoryNodes(). */
export interface CategoryNode extends FacetOption {
  slug: string;
  children: CategoryNode[];
  hasChildren: boolean;
  /** Top level only: distinct posts across the selected children. */
  selectedCount?: number;
  /** Top level only (archive header). */
  description?: string;
  imageUrl?: string;
}

/** state.facets - ListingService::getFacets(). */
export interface Facets {
  "category-oportunities": CategoryNode[];
  "category-beneficiaries": FacetOption[];
  country: CountryOption[];
  /** The "Favorites" count; logged-in users only. */
  favorites?: number;
}

// --- Results ------------------------------------------------------------

/** A card's top-level category - ListingService::resolveTopLevelCategories(). */
export interface CategoryTag {
  name: string;
  slug: string;
}

/** A card - ListingService::formatOpportunityCard(). */
export interface ResultItem {
  id: number;
  title: string;
  excerpt: string;
  categories: CategoryTag[];
  date_starts: string;
  date_ends: string;
  url: string;
  /** Overlaid from the favorites store on REST results (grid/actions.ts). */
  isFavorite: boolean;
  ratingAvg: number;
  ratingRounded: number;
  ratingCount: number;
  commentCount: number;
  commentsUrl: string;
}

/** GET listing/v1/search - Listing\Api\MainController::search(). */
export interface SearchResponse {
  items: ResultItem[];
  total: number;
  total_pages: number;
  page: number;
  /** Page 1 only: Load more can't change the facets. */
  facets?: Facets;
}

/** An active-filter chip - DerivedState::activeFilterChips(). */
export interface Chip {
  key: string;
  field: FilterId;
  value: number | string | boolean;
  label: string;
  /** Category chips: the parent's slug, for the chip's colour. */
  slug?: string;
  isChild?: boolean;
}

// --- Contexts (data-wp-context and data-wp-each items) -------------------

/** A sidebar filter group (AbstractFilter::render()). */
export interface FilterGroupContext {
  filterId: FilterId;
}

/** A checkbox list (AbstractTaxonomyFilter, CategoryFilter, CountryFilter). */
export interface FilterListContext {
  filterField: ListFilterKey;
}

/** data-wp-each--item. */
export interface ItemContext<T> {
  item: T;
}

/** A result card (ResultsGrid::renderCard()) and its category tags. */
export interface CardContext extends ItemContext<ResultItem> {
  cat?: CategoryTag;
}

// --- State --------------------------------------------------------------

/** Strings PHP translates - ListingCore::enqueueAssets(). */
export interface ListingI18n {
  loadError: string;
  found: string;
  removeFilter: string;
  favoritesChip: string;
  sessionExpired: string;
}

/**
 * Hydrated by PHP: StateAggregator::aggregate() and ListingCore::enqueueAssets().
 * Never declared in the client's state literal - store() merges client
 * state over server state, so a default there would wipe the server's data.
 */
export interface ServerState {
  query: ListingQuery;
  results: ResultItem[];
  facets: Facets;
  totalFound: number;
  totalPages: number;
  layout: string;
  isUserLoggedIn: boolean;
  i18n: ListingI18n;
}

/**
 * Client-only state, the only keys the store's state literal may declare
 * (StateAggregator also hydrates their defaults for the server render).
 */
export interface ClientState {
  isLoading: boolean;
  isSidebarOpen: boolean;
  /** The last failed request's message (grid/actions.ts). */
  error: string;
  /** "Found: N" for the results' role=status (grid/actions.ts). */
  announcement: string;
  /** Facet labels kept for chips whose option left the facets. */
  _labelCache?: Record<string, string | boolean>;
}

/**
 * grid/getters.ts. The ones the markup binds have PHP twins in
 * Listing\Core\DerivedState - keep them in step.
 */
export interface GridGetters {
  readonly hasResults: boolean;
  readonly hasMore: boolean;
  readonly layoutClass: string;
  readonly resultsFoundLabel: number | "";
  readonly activeFiltersCount: number;
  readonly hasActiveFilters: boolean;
  readonly activeFiltersCountLabel: string;
  readonly isFilterChecked: boolean;
  readonly categoryClass: string;
  readonly isFavorited: boolean;
  readonly selectedCategoryName: string;
  readonly hasSelectedCategory: boolean;
  readonly selectedCategoryImageUrl: string | null;
  readonly selectedCategoryDescription: string;
  readonly activeFilterChips: Chip[];
  readonly chipRemoveLabel: string;
}

/** filters/getters.ts, each with a PHP twin in Listing\Core\DerivedState. */
export interface FilterGetters {
  readonly filterCountLabel: string;
  readonly filterHasSelection: boolean;
  readonly isPartiallySelected: boolean;
  readonly isParentExpanded: boolean;
  readonly parentCount: number;
}

/** The listing store's state as its getters and actions see it. */
export type ListingState = ServerState &
  ClientState &
  GridGetters &
  FilterGetters;

// --- Stores -------------------------------------------------------------

/** The listing store's actions (listing-store.ts checks itself against it). */
export type ListingActions = typeof gridActions & {
  filters: typeof filterActions;
  toggleSidebar(): void;
  closeSidebar(): void;
  init(): void;
  trapSidebarFocus: SyncAwareAction;
  toggleFavorite: SyncAwareAction;
};

export interface ListingStore {
  state: ListingState;
  actions: ListingActions;
}

/** "listingSettings" - ListingCore::enqueueAssets(). */
export interface ListingSettingsStore {
  state: {
    config: {
      restUrl: string;
      prettyCategoryUrls?: boolean;
      archivePath?: string;
    };
  };
}

/**
 * What the listing writes to history.state (utils.ts historyEntry()), next
 * to core's `wpInteractivityId`, which it keeps.
 */
export interface HistoryEntry {
  query: ListingQuery;
  wpInteractivityId?: unknown;
}
