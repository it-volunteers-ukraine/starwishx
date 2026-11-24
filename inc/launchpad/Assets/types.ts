/**
 * Launchpad Store — Types
 *
 * The Launchpad's contracts with its PHP side. Each type names the PHP that
 * produces the data: change them together. Types only - the store accessors
 * are in ./stores.ts.
 *
 * File: inc/launchpad/Assets/types.ts
 */
import type { coreActions } from "./core/actions.ts";
import type { favoritesActions } from "./favorites/actions.ts";
import type { opportunitiesActions } from "./opportunities/actions.ts";
import type { opportunitiesCallbacks } from "./opportunities/callbacks.ts";
import type { profileActions } from "./profile/actions.ts";

// --- Settings (LaunchpadCore::enqueueAssets) ----------------------------

/** UsernamePolicy::getClientRules() / MessengerPolicy::getClientRules(). */
export interface PatternPolicy {
  /** A RegExp source, without delimiters. */
  pattern?: string;
  minLength?: number;
  maxLength?: number;
  messages?: { tooShort?: string; tooLong?: string; invalid?: string };
}

/** PasswordPolicy::getClientRules(). */
export interface PasswordPolicy {
  minLength?: number;
  messages?: { tooShort?: string; tooWeak?: string; hasUserData?: string };
}

/** state.launchpadSettings - LaunchpadCore::enqueueAssets(). */
export interface LaunchpadSettings {
  nonce: string;
  /** rest_url('launchpad/v1/'), with the trailing slash. */
  restUrl: string;
  userId: number;
  loginUrl: string;
  generatePasswordUrl: string;
  homeUrl: string;
  passwordPolicy?: PasswordPolicy;
  usernamePolicy?: PatternPolicy;
  messengerPolicy?: PatternPolicy;
  phoneConfig?: {
    initialCountry?: string;
    countryOrder?: string[];
    excludeCountries?: string[];
  };
  messages?: {
    invalidPhone?: string;
    requiredFields?: string;
    passwordRequired?: string;
  };
}

// --- Panels (StateAggregator, Panels/*::getInitialState) ------------------

/** What every panel holds, loaded or not - StateAggregator::aggregate(). */
export interface PanelBase {
  isLoading: boolean;
  isSaving: boolean;
  error: string | null;
}

/** An inactive panel before its first GET panel/{id}/state. */
export interface UnloadedPanel extends PanelBase {
  _loaded: false;
}

/** A panel once its state arrived (SSR for the active one, else REST). */
export type LoadedPanel<T> = PanelBase & { _loaded: true } & T;

/** A panel's state over its life: narrow on `_loaded` before using it. */
export type Lazy<T> = UnloadedPanel | LoadedPanel<T>;

/** Inline errors by form field - `data.field_errors` / client checks. */
export type FieldErrors = Record<string, string | null>;

// Opportunities ------------------------------------------------------------

/** Launchpad\Enums\OpportunityStatus. */
export type OpportunityStatus = "draft" | "pending" | "publish";

/** The panel's routing (?view): the list, or the form - Launchpad\Enums\OpportunityView. */
export type OpportunityView = "list" | "add" | "edit";

/** A list card - OpportunitiesService::getUserOpportunities(). */
export interface OpportunityListItem {
  id: number;
  title: string;
  status: OpportunityStatus;
  statusLabel: string;
  isExpired: boolean;
  excerpt: string;
  thumbnailUrl: string | null;
  viewUrl: string | false;
}

/** A KATOTTG place - OpportunitiesService::searchKatottg(). */
export interface KatottgPlace {
  code: string;
  name: string;
  /** 1 oblast, 2 raion, 3 hromada, 4-5 settlements; a string from MySQL. */
  level: number | string;
  category: string;
}

/** The form's document - getDocumentData(), or a file picked but unsent. */
export interface OpportunityDocument {
  id?: number;
  name: string;
  url?: string;
  size: string;
  isPending: boolean;
}

/** The form's values - $emptyForm / OpportunitiesService::getSingleOpportunity(). */
export interface OpportunityForm {
  id: number | null;
  status?: OpportunityStatus;
  title: string;
  company: string;
  date_starts: string;
  date_ends: string;
  category: number[];
  /** A country id, or "" for none. */
  country: number | "";
  locations: KatottgPlace[];
  city: string;
  sourcelink: string;
  application_form: string;
  beneficiaries: number[];
  subcategory: number[];
  description: string;
  requirements: string;
  details: string;
  document: OpportunityDocument | null;
  /** 0 asks the server to drop the saved document. */
  document_id: number | null;
}

/** The location searches, which live next to the form values (client only). */
export interface LocationSearch {
  searchOblast?: string;
  resultsOblast?: KatottgPlace[];
  searchRaion?: string;
  resultsRaion?: KatottgPlace[];
  searchCity?: string;
  resultsCity?: KatottgPlace[];
}

/** The location result lists, one per search box. */
export type LocationResultKey =
  | "resultsOblast"
  | "resultsRaion"
  | "resultsCity";

export interface Country {
  id: number;
  name: string;
}

export interface CategoryOption {
  id: number;
  name: string;
}

/** A parent category and the children the form offers. */
export interface CategoryGroup extends CategoryOption {
  children: CategoryOption[];
}

/** OpportunitiesService::getFormOptions() (`[]` while the panel is locked). */
export interface FormOptions {
  categories?: CategoryGroup[];
  countries?: Country[];
  beneficiaries?: CategoryOption[];
}

/** The fields the form counts or caps - selectionLimits keys. */
export type SelectionField = "category" | "beneficiaries";

/** OpportunitiesPanel::getInitialState() 'validationMessages'. */
export interface OpportunityValidationMessages {
  title?: string;
  titleMinLength?: string;
  company?: string;
  companyMinLength?: string;
  sourcelink?: string;
  description?: string;
  descriptionMinLength?: string;
  category?: string;
  beneficiaries?: string;
  categoryMax?: string;
  beneficiariesMax?: string;
  dateRange?: string;
  document?: string;
}

export interface Limits {
  min?: number;
  max?: number;
}

/** Where "Submit for Review" was asked from. */
export interface ConfirmPopup {
  isOpen: boolean;
  itemId: number | null;
  source: "form" | "list" | null;
}

export type Layout = "compact" | "card" | "grid";

/**
 * OpportunitiesPanel::getInitialState(). A locked panel (profile incomplete)
 * sends only isLocked, the view, items, options and the forms, hence the
 * optional rest: the form's actions are unreachable while it is locked.
 */
export interface OpportunitiesData {
  isLocked: boolean;
  currentView: OpportunityView;
  items: OpportunityListItem[];
  total: number;
  /** The list's page size (OpportunitiesService::ITEMS_PER_PAGE). */
  perPage: number;
  /** The document upload cap (MediaService::MAX_SIZE). */
  uploadMaxBytes: number;
  options: FormOptions;
  formData: OpportunityForm & LocationSearch;
  emptyForm?: OpportunityForm;
  fieldErrors: FieldErrors;
  page?: number;
  totalPages?: number;
  hasMore?: boolean;
  filters?: { statuses: OpportunityStatus[] };
  titleLimits?: Limits;
  companyLimits?: Limits;
  descriptionLimits?: Limits;
  selectionLimits?: Partial<Record<SelectionField, number>>;
  textareaLimits?: {
    description?: number;
    requirements?: number;
    details?: number;
  };
  validationMessages?: OpportunityValidationMessages;
  formHeaders?: { newOpportunity?: string; editOpportunity?: string };
  /** "%d selected", translated. */
  categoryCountLabel?: string;
  isCountryDropdownOpen?: boolean;
  countrySearch?: string;
  confirmPopup?: ConfirmPopup;
  isUploading?: boolean;
  layout?: Layout;
  isLayoutCompact?: boolean;
  isLayoutCard?: boolean;
  isLayoutGrid?: boolean;
}

// Profile --------------------------------------------------------------------

/** ProfileService::getProfileData(). */
export interface ProfileData {
  id: number;
  userLogin: string;
  firstName: string;
  lastName: string;
  nickname: string;
  displayName: string;
  email: string;
  userUrl: string;
  description: string;
  role: string;
  roleLabel: string;
  phone: string;
  telegram: string;
  organization: string;
  receiveMailNotifications: boolean;
  displayNameOptions: string[];
}

/** What validateName() returns - keys of the panel's validationMessages. */
export type NameError = "nameMinLength" | "nameMaxLength" | "nameInvalid";

/** The values "Cancel" puts back (captured on entering edit mode). */
export type ProfileSnapshot = Pick<
  ProfileData,
  "firstName" | "lastName" | "email" | "phone" | "telegram" | "organization"
>;

export interface PasswordPopup {
  isOpen: boolean;
  password: string;
  isPasswordVisible: boolean;
  error: string | null;
}

export interface EmailPopup extends PasswordPopup {
  newEmail: string;
  isChanging: boolean;
}

export interface DeletePopup extends PasswordPopup {
  isDeleting: boolean;
}

/** ProfilePanel::getInitialState(). */
export interface ProfilePanelData extends ProfileData {
  isEditing: boolean;
  isChangingPassword: boolean;
  isDisplayNameDropdownOpen: boolean;
  isFormExpanded: boolean;
  fieldErrors: FieldErrors;
  /** The saved nickname / telegram: a legacy value may stay as it is. */
  _originalNickname: string;
  _originalTelegram: string;
  _original?: ProfileSnapshot;
  nameLimits: Limits;
  validationMessages: Partial<Record<NameError, string>>;
  passwordData: { current: string; new: string };
  isCurrentPasswordVisible: boolean;
  isNewPasswordVisible: boolean;
  isGenerating: boolean;
  passwordSuccessPopup: { isOpen: boolean };
  deletePopup: DeletePopup;
  emailPopup: EmailPopup;
  deleteSuccessPopup: { isOpen: boolean };
  /** The sensitive fields shown unblurred for now. */
  revealedFields: string[];
}

// Favorites ------------------------------------------------------------------

/** A favorites card - FavoritesService::getUserFavorites(). */
export interface FavoriteItem {
  id: number;
}

/** FavoritesPanel::getInitialState(). */
export interface FavoritesPanelData {
  items: FavoriteItem[];
  total: number;
  page: number;
  /** FavoritesPanel::PER_PAGE. */
  perPage: number;
  totalPages: number;
  hasMore: boolean;
}

/** state.panels: the known panels; others (chat) register via a hook. */
export interface Panels {
  opportunities?: Lazy<OpportunitiesData>;
  profile?: Lazy<ProfilePanelData>;
  favorites?: Lazy<FavoritesPanelData>;
  [panelId: string]: Lazy<object> | undefined;
}

/** The panels the store knows the shape of. */
export type KnownPanelId = "opportunities" | "profile" | "favorites";

/** Each known panel's loaded state. */
export interface PanelData {
  opportunities: OpportunitiesData;
  profile: ProfilePanelData;
  favorites: FavoritesPanelData;
}

// --- REST responses -----------------------------------------------------

/** GET opportunities - OpportunitiesController::getOpportunities(). */
export interface OpportunityListResponse {
  items: OpportunityListItem[];
  total: number;
  total_pages: number;
}

/** POST media - MediaService::upload(). */
export interface MediaResponse {
  id: number;
}

/** POST profile - ProfileService::updateProfile(), with the role flags. */
export type ProfileUpdateResponse = Partial<ProfileData> & {
  _roleUpgraded?: boolean;
  _roleDegraded?: boolean;
};

/** GET gateway/v1/password/generate - PasswordController::generatePassword(). */
export interface GeneratedPassword {
  password: string;
}

// --- State --------------------------------------------------------------

/** One sidebar tab - StateAggregator 'navigation'. */
export interface NavigationItem {
  id: string;
  label: string;
  icon: string;
}

/** What PHP hydrates - LaunchpadCore::enqueueAssets() + StateAggregator. */
export interface ServerState {
  launchpadSettings: LaunchpadSettings;
  activePanel: string;
  /** Panel id -> its visibility flag's key ("isProfileActive"). */
  panelMap: Record<string, `is${string}Active`>;
  panels: Panels;
  navigation: NavigationItem[];
  user: { id: number; displayName: string; avatarUrl: string | false };
}

/** The visibility flags panelMap names, one per panel. */
export type PanelFlags = Record<`is${string}Active`, boolean>;

export interface OpportunitiesGetters {
  readonly isOppOnboardingVisible: boolean;
  readonly isOppListVisible: boolean;
  readonly isOppFormVisible: boolean;
  readonly hasOppItems: boolean;
  readonly showOppEmpty: boolean;
  readonly showOppFilteredEmpty: boolean;
  readonly showOppGrid: boolean;
  readonly opportunityFormHeaders: string | undefined;
  readonly isBeneficiaryChecked: boolean;
  readonly beneficiariesCounterText: string;
  readonly isBeneficiariesLimitReached: boolean;
  readonly isBeneficiaryOptionDisabled: boolean;
  readonly categoryCounterText: string;
  readonly isCategoryLimitReached: boolean;
  readonly isCategoryOptionDisabled: boolean;
  readonly isCategoryChecked: boolean;
  readonly categoryGroupTitleId: string;
  readonly categoryGroupSelectedCount: number;
  readonly categoryGroupCountLabel: string;
  readonly canEdit: boolean;
  readonly isPublish: boolean;
  readonly isCountryDropdownOpen: boolean;
  readonly selectedCountryLabel: string;
  readonly isCountryItemSelected: boolean;
  readonly countriesDisplayList: Country[];
  readonly hasNoCountryResults: boolean;
  readonly isUkraineSelected: boolean;
  readonly titleCounterText: string;
  readonly isTitleTooShort: boolean;
  readonly isCompanyTooShort: boolean;
  readonly isDescriptionTooShort: boolean;
  readonly descriptionCounterText: string;
  readonly isDescriptionNearLimit: boolean;
  readonly isDescriptionCounterWarn: boolean;
  readonly requirementsCounterText: string;
  readonly isRequirementsNearLimit: boolean;
  readonly detailsCounterText: string;
  readonly isDetailsNearLimit: boolean;
  readonly hasOblastResults: boolean;
  readonly hasRaionResults: boolean;
  readonly hasCityResults: boolean;
}

export interface ProfileGetters {
  readonly isProfileCardVisible: boolean;
  readonly currentPasswordInputType: "text" | "password";
  readonly newPasswordInputType: "text" | "password";
  readonly deletePasswordInputType: "text" | "password";
  readonly emailPasswordInputType: "text" | "password";
  readonly isFieldBlurred: boolean;
  readonly isDisplayNameItemSelected: boolean;
}

export interface FavoritesGetters {
  readonly isCurrentItemFavorite: boolean;
  readonly shouldHideFavoritesEmpty: boolean;
}

export type LaunchpadState = ServerState &
  PanelFlags &
  OpportunitiesGetters &
  ProfileGetters &
  FavoritesGetters;

// --- Contexts -----------------------------------------------------------

/** A data-wp-each item's context: `item` (or the each--{name} suffix). */
export interface ItemContext<T> {
  item: T;
}

/** A category group (`item`) and, inside it, one child checkbox (`child`). */
export interface CategoryContext {
  item: CategoryGroup;
  child: CategoryOption;
}

/** A sensitive profile value: data-wp-context='{"field":"email"}'. */
export interface RevealContext {
  field: string;
}

// --- Store --------------------------------------------------------------

/** The launchpad store's actions (launchpad-store.ts checks against it). */
export type LaunchpadActions = typeof coreActions & {
  opportunities: typeof opportunitiesActions;
  profile: typeof profileActions;
  favorites: typeof favoritesActions;
};

export interface LaunchpadStore {
  state: LaunchpadState;
  actions: LaunchpadActions;
  callbacks: { opportunities: typeof opportunitiesCallbacks };
}

/** The intlTelInput widget, as profile/actions.ts uses it. */
export interface PhoneWidget {
  setNumber(number: string): void;
  getNumber(): string;
  isValidNumber(): boolean | null;
  getSelectedCountryData(): { iso2?: string };
}

/** The intlTelInput factory (loaded by ProfilePanel as a classic script). */
export type PhoneWidgetFactory = (
  input: HTMLInputElement,
  options: Record<string, unknown>,
) => PhoneWidget;

declare global {
  interface Window {
    intlTelInput?: PhoneWidgetFactory;
  }
}
