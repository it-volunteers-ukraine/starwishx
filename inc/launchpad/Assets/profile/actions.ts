/**
 * Launchpad Store — Profile Actions
 *
 * All actions under the `actions.profile` namespace.
 * Uses Store Locator pattern: launchpadStore() to access state/actions.
 *
 * File: inc/launchpad/Assets/profile/actions.ts
 */

import {
  getContext,
  getElement,
  withSyncEvent,
} from "@wordpress/interactivity";
import { ApiError, isKeyOf } from "../../../shared/Assets/store-utils.ts";
import { launchpadStore, tourStore } from "../stores.ts";
import type {
  ItemContext,
  LaunchpadState,
  LoadedPanel,
  PasswordPolicy,
  PhoneWidget,
  ProfileData,
  ProfilePanelData,
} from "../types.ts";
import {
  apiErrorMessage,
  boundElement,
  currentUrl,
  fetchJson,
  flashError,
  focus,
  isGeneratedPassword,
  isObject,
  isProfileUpdate,
  loadedPanel,
  unloadPanel,
  normalizeUrl,
  scrollToFirstError,
  scrollToPageTop,
  targetElement,
  validateMessenger,
  validateName,
  validateUsername,
  writeUrl,
} from "../utils.ts";

type Panel = LoadedPanel<ProfilePanelData>;

/** intlTelInput instance — survives across action calls */
let phoneWidget: PhoneWidget | null = null;

/** Auto re-blur timers keyed by field name */
const revealTimers = new Map<string, ReturnType<typeof setTimeout>>();

/** How long a revealed value stays unblurred. */
const REVEAL_MS = 10_000;

// --- Field allowlists (data-field -> state key) --------------------------

/** The text inputs updateField() writes (ProfilePanel's edit form). */
const TEXT_FIELDS = [
  "firstName",
  "lastName",
  "nickname",
  "organization",
  "telegram",
  "userUrl",
  "description",
] as const satisfies readonly (keyof ProfileData)[];

/** The fields the display-name options are made from. */
const NAME_FIELDS: readonly string[] = [
  "firstName",
  "lastName",
  "nickname",
  "organization",
];

const PASSWORD_FIELDS = ["current", "new"] as const;
const EMAIL_POPUP_FIELDS = ["newEmail", "password"] as const;

/** validateName()'s error keys, which the server's field_errors also use. */
const NAME_ERRORS = ["nameMinLength", "nameMaxLength", "nameInvalid"] as const;

// --- Helpers ------------------------------------------------------------

const profile = (state: LaunchpadState): Panel | undefined =>
  loadedPanel(state, "profile");

/** The input / textarea the action runs on. */
function fieldElement(): HTMLInputElement | HTMLTextAreaElement | null {
  const { ref } = getElement();
  return ref instanceof HTMLInputElement || ref instanceof HTMLTextAreaElement
    ? ref
    : null;
}

/**
 * Compute display-name dropdown options from current form state.
 * Mirrors WordPress wp-admin/user-edit.php logic.
 */
function computeDisplayNameOptions(p: Panel): string[] {
  const first = p.firstName.trim();
  const last = p.lastName.trim();
  const options = [
    p.nickname.trim(),
    p.userLogin.trim(),
    first,
    last,
    ...(first && last ? [`${first} ${last}`, `${last} ${first}`] : []),
    p.organization.trim(),
  ];
  return [...new Set(options)].filter(Boolean);
}

/** Leave the edit / password view: drop ?view as a new Back step (or not). */
function closeView({ replace = false } = {}): void {
  const url = currentUrl();
  url.searchParams.delete("view");
  writeUrl(url, { replace });
  launchpadStore().actions.syncStateFromUrl();
}

/** Open the edit / password view as a new Back step. */
function openView(view: "profile" | "password"): void {
  const url = currentUrl();
  url.searchParams.set("view", view);
  writeUrl(url);
  launchpadStore().actions.syncStateFromUrl();
}

/**
 * What is wrong with a new password, per the hydrated PasswordPolicy, or null
 * when it passes. A UX guard only: the server checks it again. The message
 * may be "" when the policy sends none, which still blocks the submit.
 */
function passwordProblem(
  password: string,
  policy: PasswordPolicy = {},
): string | null {
  const messages = policy.messages ?? {};
  if (password.length < (policy.minLength ?? 12)) {
    return messages.tooShort ?? "";
  }
  const mixed =
    /[A-Z]/.test(password) &&
    /[0-9]/.test(password) &&
    /[^A-Za-z0-9]/.test(password);
  return mixed ? null : (messages.tooWeak ?? "");
}

/** A dropdown item's Enter/Space/arrows/Escape (display name). */
function dropdownItemKeys(event: KeyboardEvent): void {
  const li = targetElement(event)?.closest(".lp-dropdown__item");
  if (!(li instanceof HTMLElement)) return;
  const trigger = () =>
    li.closest(".lp-dropdown")?.querySelector(".lp-dropdown__trigger");

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
    focus(li.previousElementSibling ?? trigger());
    return;
  }
  if (event.key === "Escape") focus(trigger());
}

// --- Actions ------------------------------------------------------------

export const profileActions = {
  /**
   * Initialise the intlTelInput widget on the phone field.
   * Called via data-wp-init on the profile form.
   */
  initPhoneWidget(): void {
    const factory = window.intlTelInput;
    if (!factory || phoneWidget) return;

    const { state } = launchpadStore();
    const input = document.getElementById("lp-phone");
    if (!(input instanceof HTMLInputElement)) return;

    const cfg = state.launchpadSettings.phoneConfig ?? {};
    phoneWidget = factory(input, {
      initialCountry: cfg.initialCountry || "ua",
      countryOrder: cfg.countryOrder ?? ["ua"],
      excludeCountries: cfg.excludeCountries ?? [],
      nationalMode: true,
      formatAsYouType: true,
      strictMode: true,
      separateDialCode: true,
      countrySearch: true,
    });

    const phone = profile(state)?.phone;
    if (phone) phoneWidget.setNumber(phone);
  },

  /**
   * Toggle the "Show More" section in the edit form.
   */
  toggleFormExpanded(): void {
    const p = profile(launchpadStore().state);
    if (p) p.isFormExpanded = !p.isFormExpanded;
  },

  /**
   * Normalize URL fields on blur (mirrors backend InputSanitizer::sanitizeUrl).
   */
  normalizeUrlField(): void {
    const p = profile(launchpadStore().state);
    if (p && fieldElement()?.dataset.field === "userUrl") {
      p.userUrl = normalizeUrl(p.userUrl);
    }
  },

  // ── Sensitive field reveal/blur ─────────────────────────────────────

  toggleReveal(): void {
    const { ref } = getElement();
    const field = ref?.dataset.field;
    const p = profile(launchpadStore().state);
    if (!field || !p) return;

    // Clear any pending timer for this field
    clearTimeout(revealTimers.get(field));
    revealTimers.delete(field);

    const hide = () => {
      p.revealedFields = p.revealedFields.filter((f) => f !== field);
    };

    if (p.revealedFields.includes(field)) {
      hide();
    } else {
      p.revealedFields = [...p.revealedFields, field];
      // Auto re-blur after 10 s
      revealTimers.set(
        field,
        setTimeout(() => {
          hide();
          revealTimers.delete(field);
        }, REVEAL_MS),
      );
    }
  },

  /** Enter / Space on a sensitive value. withSyncEvent: cancels the key. */
  revealKeydown: withSyncEvent((event: KeyboardEvent): void => {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      launchpadStore().actions.profile.toggleReveal();
    }
  }),

  // ── Display Name custom dropdown ───────────────────────────────────

  toggleDisplayNameDropdown(): void {
    const p = profile(launchpadStore().state);
    if (p) p.isDisplayNameDropdownOpen = !p.isDisplayNameDropdownOpen;
  },

  selectDisplayName(): void {
    const { item } = getContext<Partial<ItemContext<string>>>();
    const p = profile(launchpadStore().state);
    if (!p || item === undefined) return;
    p.displayName = item;
    p.isDisplayNameDropdownOpen = false;
  },

  /** withSyncEvent: reads currentTarget. */
  displayNameFocusout: withSyncEvent((event: FocusEvent): void => {
    const wrapper = boundElement(event);
    const p = profile(launchpadStore().state);
    if (!wrapper || !p) return;
    if (
      event.relatedTarget instanceof Node &&
      wrapper.contains(event.relatedTarget)
    ) {
      return;
    }
    p.isDisplayNameDropdownOpen = false;
  }),

  /** withSyncEvent: reads currentTarget, cancels ArrowDown. */
  displayNameKeydown: withSyncEvent((event: KeyboardEvent): void => {
    const wrapper = boundElement(event);
    const p = profile(launchpadStore().state);
    if (!wrapper || !p) return;

    if (event.key === "Escape") {
      p.isDisplayNameDropdownOpen = false;
      focus(wrapper.querySelector(".lp-dropdown__trigger"));
      return;
    }
    if (event.key === "ArrowDown" && p.isDisplayNameDropdownOpen) {
      if (targetElement(event)?.closest(".lp-dropdown__item")) return;
      event.preventDefault();
      focus(wrapper.querySelector(".lp-dropdown__item"));
    }
  }),

  /** withSyncEvent: cancels the keys it handles. */
  displayNameItemKeydown: withSyncEvent(dropdownItemKeys),

  // ────────────────────────────────────────────────────────────────────

  /**
   * Enter edit mode for profile
   */
  startEdit(): void {
    openView("profile");
    const phone = profile(launchpadStore().state)?.phone;
    if (phoneWidget && phone) phoneWidget.setNumber(phone);
  },

  /**
   * Cancel edit mode, restore original values
   */
  cancelEdit(): void {
    const p = profile(launchpadStore().state);
    if (p?._original) Object.assign(p, p._original);
    phoneWidget?.setNumber(p?._original?.phone ?? "");
    closeView();
  },

  /**
   * Update a field value from its input (data-field names it).
   */
  updateField(): void {
    const el = fieldElement();
    const field = el?.dataset.field;
    const p = profile(launchpadStore().state);
    if (!el || !field || !p) return;

    if (field === "receiveMailNotifications") {
      if (el instanceof HTMLInputElement)
        p.receiveMailNotifications = el.checked;
    } else if (isKeyOf(TEXT_FIELDS, field)) {
      p[field] = el.value;
    } else {
      return;
    }

    // Clear field error on input (same pattern as opportunities updateForm)
    if (p.fieldErrors[field]) p.fieldErrors[field] = null;

    // Recompute display name options when name-related fields change
    if (NAME_FIELDS.includes(field)) {
      p.displayNameOptions = computeDisplayNameOptions(p);
      // If current displayName is no longer among options, reset to first
      if (p.displayName && !p.displayNameOptions.includes(p.displayName)) {
        p.displayName = p.displayNameOptions[0] ?? "";
      }
    }
  },

  /**
   * Input handler for the phone field.
   * intlTelInput with separateDialCode:true keeps only the national part in
   * input.value — the dial code lives in a sibling widget element. Read the
   * canonical E.164 from the widget so state.phone (shown in the profile
   * card view) keeps its country code. Falls back to the input's value only
   * before the widget has initialized.
   */
  updatePhoneField(): void {
    const p = profile(launchpadStore().state);
    if (!p) return;
    p.phone = phoneWidget
      ? phoneWidget.getNumber() || ""
      : (fieldElement()?.value ?? p.phone);
    if (p.fieldErrors.phone) p.fieldErrors.phone = null;
  },

  /**
   * Save profile changes to server. withSyncEvent: cancels the submit.
   */
  save: withSyncEvent(async (event: Event): Promise<void> => {
    event.preventDefault();
    const { state } = launchpadStore();
    const p = profile(state);
    if (!p) return;
    const settings = state.launchpadSettings;
    p.fieldErrors = {};

    // Validate name fields (optional but must be well-formed when provided)
    for (const field of ["firstName", "lastName"] as const) {
      const err = validateName(p[field], p.nameLimits);
      if (err) p.fieldErrors[field] = p.validationMessages[err] ?? err;
    }

    // Nickname: validate-on-change (mirrors ProfileService).
    // Shares UsernamePolicy with register's login name. Legacy values that
    // predate this rule survive — we only reject when the user actively
    // changes to a non-conforming value. Empty nickname is allowed.
    const nickname = p.nickname.trim();
    if (nickname !== "" && nickname !== p._originalNickname) {
      const error = validateUsername(nickname, settings.usernamePolicy);
      if (error) p.fieldErrors.nickname = error;
    }

    // Telegram: validate-on-change via MessengerPolicy (Telegram-style rules).
    // Same legacy-tolerant semantics as nickname.
    const telegram = p.telegram.trim();
    if (telegram !== "" && telegram !== p._originalTelegram) {
      const error = validateMessenger(telegram, settings.messengerPolicy);
      if (error) p.fieldErrors.telegram = error;
    }

    // Read phone from intlTelInput widget (or fallback to state)
    const phone = phoneWidget ? phoneWidget.getNumber() : p.phone;
    const phoneCountry = phoneWidget?.getSelectedCountryData().iso2 ?? "";

    // Client-side phone validation via intlTelInput
    if (phoneWidget && phone && !phoneWidget.isValidNumber()) {
      p.fieldErrors.phone =
        settings.messages?.invalidPhone ?? "Invalid phone number";
    }

    if (Object.values(p.fieldErrors).some(Boolean)) {
      scrollToFirstError();
      return;
    }

    // Normalize URL field before sending (mirrors backend sanitizeUrl)
    p.userUrl = normalizeUrl(p.userUrl);

    p.isSaving = true;
    try {
      const data = await fetchJson(
        `${settings.restUrl}profile`,
        isProfileUpdate,
        {
          method: "POST",
          body: {
            firstName: p.firstName,
            lastName: p.lastName,
            phone,
            phoneCountry,
            nickname: p.nickname,
            displayName: p.displayName,
            userUrl: p.userUrl,
            description: p.description,
            telegram: p.telegram,
            organization: p.organization,
            receiveMailNotifications: p.receiveMailNotifications,
          },
        },
      );
      if (data) {
        Object.assign(p, data);
        // Snapshot saved values so future validate-on-change checks compare
        // against server-confirmed state, not a stale load-time one.
        p._originalNickname = data.nickname ?? "";
        p._originalTelegram = data.telegram ?? "";
        // Sync widget with server-returned value
        if (phoneWidget && data.phone) phoneWidget.setNumber(data.phone);
        // A role change locks or unlocks the opportunities panel: it
        // reloads on its next visit, showing (or clearing) the onboarding
        // screen.
        if (data._roleUpgraded || data._roleDegraded) {
          unloadPanel(state, "opportunities");
        }
        // Role change → refresh tour scenarios so button triggers correct tour
        if (data._roleUpgraded || data._roleDegraded) {
          void tourStore().actions.refreshScenarios();
        }
      }
      // Return to card view via URL
      closeView({ replace: true });
      scrollToPageTop();
    } catch (error) {
      // Map backend field_errors keys to translated messages
      if (error instanceof ApiError && error.fieldErrors) {
        for (const [field, key] of Object.entries(error.fieldErrors)) {
          p.fieldErrors[field] = isKeyOf(NAME_ERRORS, key)
            ? (p.validationMessages[key] ?? key)
            : key;
        }
      }
      // Show banner only when there are no inline field errors
      if (!Object.values(p.fieldErrors).some(Boolean)) {
        flashError(p, apiErrorMessage(error));
      }
      scrollToFirstError();
    } finally {
      p.isSaving = false;
    }
  }),

  /**
   * Enter password change mode
   */
  startChangePassword(): void {
    const p = profile(launchpadStore().state);
    if (p) p.error = null;
    openView("password");
  },

  /**
   * Cancel password change mode
   */
  cancelPasswordChange(): void {
    const p = profile(launchpadStore().state);
    if (p) {
      p.passwordData = { current: "", new: "" };
      p.isCurrentPasswordVisible = false;
      p.isNewPasswordVisible = false;
      p.error = null;
    }
    closeView();
  },

  /**
   * Update password form field
   */
  updatePasswordField(): void {
    const el = fieldElement();
    const field = el?.dataset.field;
    const p = profile(launchpadStore().state);
    if (el && p && isKeyOf(PASSWORD_FIELDS, field)) {
      p.passwordData[field] = el.value;
    }
  },

  toggleCurrentPasswordVisibility(): void {
    const p = profile(launchpadStore().state);
    if (p) p.isCurrentPasswordVisible = !p.isCurrentPasswordVisible;
  },

  toggleNewPasswordVisibility(): void {
    const p = profile(launchpadStore().state);
    if (p) p.isNewPasswordVisible = !p.isNewPasswordVisible;
  },

  async generatePassword(): Promise<void> {
    const { state } = launchpadStore();
    const p = profile(state);
    if (!p || p.isGenerating) return;

    p.isGenerating = true;
    p.error = null;
    try {
      const data = await fetchJson(
        state.launchpadSettings.generatePasswordUrl,
        isGeneratedPassword,
      );
      if (data?.password) {
        p.passwordData.new = data.password;
        p.isNewPasswordVisible = true;
      }
    } catch (error) {
      flashError(p, apiErrorMessage(error));
    } finally {
      p.isGenerating = false;
    }
  },

  confirmPasswordSuccess(): void {
    window.location.href = launchpadStore().state.launchpadSettings.loginUrl;
  },

  /**
   * Submit password change to server. withSyncEvent: cancels the submit.
   */
  submitPasswordChange: withSyncEvent(async (event: Event): Promise<void> => {
    event.preventDefault();
    const { state } = launchpadStore();
    const p = profile(state);
    if (!p) return;

    // ── Client-side validation (UX guard — server re-validates) ──────────
    const problem = passwordProblem(
      p.passwordData.new,
      state.launchpadSettings.passwordPolicy,
    );
    if (problem !== null) {
      flashError(p, problem);
      scrollToFirstError();
      return;
    }
    // ──────────────────────────────────────────────────────────────────────

    p.isSaving = true;
    try {
      await fetchJson(
        `${state.launchpadSettings.restUrl}security/password`,
        isObject,
        {
          method: "POST",
          body: {
            currentPassword: p.passwordData.current,
            newPassword: p.passwordData.new,
          },
        },
      );
      p.passwordSuccessPopup = { isOpen: true };
    } catch (error) {
      flashError(p, apiErrorMessage(error));
      scrollToFirstError();
    } finally {
      p.isSaving = false;
    }
  }),

  // ── Email change popup ──────────────────────────────────────────────

  openEmailChange(): void {
    const p = profile(launchpadStore().state);
    if (!p) return;
    p.emailPopup = {
      isOpen: true,
      newEmail: p.email,
      password: "",
      isPasswordVisible: false,
      isChanging: false,
      error: null,
    };
  },

  cancelEmailChange(): void {
    const popup = profile(launchpadStore().state)?.emailPopup;
    if (!popup) return;
    popup.isOpen = false;
    popup.password = "";
    popup.error = null;
  },

  updateEmailPopupField(): void {
    const el = fieldElement();
    const field = el?.dataset.field;
    const popup = profile(launchpadStore().state)?.emailPopup;
    if (el && popup && isKeyOf(EMAIL_POPUP_FIELDS, field)) {
      popup[field] = el.value;
    }
  },

  toggleEmailPasswordVisibility(): void {
    const popup = profile(launchpadStore().state)?.emailPopup;
    if (popup) popup.isPasswordVisible = !popup.isPasswordVisible;
  },

  async confirmEmailChange(): Promise<void> {
    const { state } = launchpadStore();
    const p = profile(state);
    if (!p) return;
    const popup = p.emailPopup;

    if (!popup.newEmail || !popup.password) {
      flashError(
        popup,
        state.launchpadSettings.messages?.requiredFields ??
          "Please fill in all fields.",
      );
      return;
    }

    popup.isChanging = true;
    popup.error = null;
    try {
      const data = await fetchJson(
        `${state.launchpadSettings.restUrl}profile/email`,
        isProfileUpdate,
        {
          method: "POST",
          body: { email: popup.newEmail, password: popup.password },
        },
      );
      if (data) Object.assign(p, data);
      popup.isOpen = false;
      popup.password = "";
    } catch (error) {
      flashError(popup, apiErrorMessage(error));
    } finally {
      popup.isChanging = false;
    }
  },

  // ── Delete account ────────────────────────────────────────────────

  /**
   * Open the delete account confirmation popup.
   */
  deleteProfile(): void {
    const p = profile(launchpadStore().state);
    if (!p) return;
    p.error = null;
    p.deletePopup = {
      isOpen: true,
      password: "",
      isPasswordVisible: false,
      isDeleting: false,
      error: null,
    };
  },

  /**
   * Close the delete popup and reset its state.
   */
  cancelDelete(): void {
    const popup = profile(launchpadStore().state)?.deletePopup;
    if (!popup) return;
    popup.isOpen = false;
    popup.password = "";
    popup.error = null;
  },

  /**
   * Update the delete popup password field from input.
   */
  updateDeletePassword(): void {
    const value = fieldElement()?.value;
    const popup = profile(launchpadStore().state)?.deletePopup;
    if (popup && value !== undefined) popup.password = value;
  },

  /**
   * Toggle password visibility in the delete popup.
   */
  toggleDeletePasswordVisibility(): void {
    const popup = profile(launchpadStore().state)?.deletePopup;
    if (popup) popup.isPasswordVisible = !popup.isPasswordVisible;
  },

  /**
   * Confirm account deletion: verify password server-side and delete.
   */
  async confirmDelete(): Promise<void> {
    const { state } = launchpadStore();
    const p = profile(state);
    if (!p) return;
    const popup = p.deletePopup;

    if (!popup.password) {
      flashError(
        popup,
        state.launchpadSettings.messages?.passwordRequired ??
          "Please enter your password.",
      );
      return;
    }

    popup.isDeleting = true;
    popup.error = null;
    try {
      await fetchJson(
        `${state.launchpadSettings.restUrl}profile/delete`,
        isObject,
        {
          method: "POST",
          body: { password: popup.password },
        },
      );
      // Close confirmation popup, show success popup
      popup.isOpen = false;
      p.deleteSuccessPopup = { isOpen: true };
    } catch (error) {
      popup.isDeleting = false;
      flashError(popup, apiErrorMessage(error));
    }
  },

  /**
   * After successful deletion, redirect to home.
   */
  confirmDeleteSuccess(): void {
    window.location.href =
      launchpadStore().state.launchpadSettings.homeUrl || "/";
  },
};
