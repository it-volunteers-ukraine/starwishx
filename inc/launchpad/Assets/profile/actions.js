/**
 * Launchpad Store — Profile Actions
 *
 * All actions under the `actions.profile` namespace.
 * Uses Store Locator pattern: store("launchpad") to access state/actions.
 *
 * File: inc/launchpad/Assets/profile/actions.js
 */

import { getElement, getContext, store } from "@wordpress/interactivity";
import {
  ensurePanel,
  fetchJson,
  normalizeUrl,
  scrollToFirstError,
  scrollToPageTop,
  validateName,
  validateUsername,
  validateMessenger,
} from "../utils.js";

/** intlTelInput instance — survives across action calls */
let itiInstance = null;

/** Auto re-blur timers keyed by field name */
const revealTimers = {};

/**
 * Compute display-name dropdown options from current form state.
 * Mirrors WordPress wp-admin/user-edit.php logic.
 */
function computeDisplayNameOptions(p) {
  const opts = [];
  if (p.nickname) opts.push(p.nickname.trim());
  if (p.userLogin) opts.push(p.userLogin.trim());
  if (p.firstName) opts.push(p.firstName.trim());
  if (p.lastName) opts.push(p.lastName.trim());
  if (p.firstName && p.lastName) {
    opts.push(`${p.firstName.trim()} ${p.lastName.trim()}`);
    opts.push(`${p.lastName.trim()} ${p.firstName.trim()}`);
  }
  if (p.organization) opts.push(p.organization.trim());
  return [...new Set(opts)].filter(Boolean);
}

/**
 * Profile actions - plain object using Store Locator pattern.
 */
export const profileActions = {
  /**
   * Initialise the intlTelInput widget on the phone field.
   * Called via data-wp-init on the profile form.
   */
  initPhoneWidget() {
    if (!window.intlTelInput || itiInstance) return;

    const { state } = store("launchpad");
    const input = document.getElementById("lp-phone");
    if (!input) return;

    const cfg = state.launchpadSettings.phoneConfig ?? {};

    itiInstance = window.intlTelInput(input, {
      initialCountry: cfg.initialCountry || "ua",
      countryOrder: cfg.countryOrder || ["ua"],
      excludeCountries: cfg.excludeCountries || [],
      nationalMode: true,
      formatAsYouType: true,
      strictMode: true,
      separateDialCode: true,
      countrySearch: true,
    });

    const p = ensurePanel(state, "profile");
    if (p.phone) {
      itiInstance.setNumber(p.phone);
    }
  },

  /**
   * Toggle the "Show More" section in the edit form.
   */
  toggleFormExpanded() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
    p.isFormExpanded = !p.isFormExpanded;
  },

  /**
   * Normalize URL fields on blur (mirrors backend InputSanitizer::sanitizeUrl).
   */
  normalizeUrlField() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    const field = ref.dataset.field;
    const p = ensurePanel(state, "profile");
    if (field && p[field] !== undefined) {
      p[field] = normalizeUrl(p[field]);
    }
  },

  // ── Sensitive field reveal/blur ─────────────────────────────────────

  toggleReveal() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    const field = ref.dataset.field;
    if (!field) return;
    const p = ensurePanel(state, "profile");
    if (!Array.isArray(p.revealedFields)) p.revealedFields = [];

    // Clear any pending timer for this field
    if (revealTimers[field]) {
      clearTimeout(revealTimers[field]);
      delete revealTimers[field];
    }

    const idx = p.revealedFields.indexOf(field);
    if (idx === -1) {
      p.revealedFields = [...p.revealedFields, field];
      // Auto re-blur after 10 s
      revealTimers[field] = setTimeout(() => {
        p.revealedFields = p.revealedFields.filter((f) => f !== field);
        delete revealTimers[field];
      }, 10_000);
    } else {
      p.revealedFields = p.revealedFields.filter((f) => f !== field);
    }
  },

  revealKeydown(event) {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      const { actions } = store("launchpad");
      actions.profile.toggleReveal();
    }
  },

  // ── Display Name custom dropdown ───────────────────────────────────

  toggleDisplayNameDropdown() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
    p.isDisplayNameDropdownOpen = !p.isDisplayNameDropdownOpen;
  },

  selectDisplayName() {
    const { state } = store("launchpad");
    const { item } = getContext();
    const p = ensurePanel(state, "profile");
    p.displayName = item;
    p.isDisplayNameDropdownOpen = false;
  },

  displayNameFocusout(event) {
    const { state } = store("launchpad");
    const wrapper = event.currentTarget;
    if (wrapper.contains(event.relatedTarget)) return;
    ensurePanel(state, "profile").isDisplayNameDropdownOpen = false;
  },

  displayNameKeydown(event) {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");

    if (event.key === "Escape") {
      p.isDisplayNameDropdownOpen = false;
      event.currentTarget.querySelector(".lp-dropdown__trigger")?.focus();
      return;
    }
    if (event.key === "ArrowDown" && p.isDisplayNameDropdownOpen) {
      if (event.target.closest(".lp-dropdown__item")) return;
      event.preventDefault();
      event.currentTarget.querySelector(".lp-dropdown__item")?.focus();
    }
  },

  displayNameItemKeydown(event) {
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
    }
  },

  triggerKeydown(event) {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      this.toggleDisplayNameDropdown();
    }
  },
  // ────────────────────────────────────────────────────────────────────

  /**
   * Enter edit mode for profile
   */
  startEdit() {
    const { state, actions } = store("launchpad");
    const url = new URL(window.location);
    url.searchParams.set("view", "profile");
    window.history.pushState({}, "", url);
    actions.syncStateFromUrl();

    const p = ensurePanel(state, "profile");
    if (itiInstance && p.phone) {
      itiInstance.setNumber(p.phone);
    }
  },

  /**
   * Cancel edit mode, restore original values
   */
  cancelEdit() {
    const { state, actions } = store("launchpad");
    const p = ensurePanel(state, "profile");
    if (p._original) Object.assign(p, p._original);

    if (itiInstance) {
      itiInstance.setNumber(p._original?.phone || "");
    }

    const url = new URL(window.location);
    url.searchParams.delete("view");
    window.history.pushState({}, "", url);
    actions.syncStateFromUrl();
  },

  /**
   * Update a field value from input element
   */
  updateField() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    if (ref.dataset.field) {
      const p = ensurePanel(state, "profile");
      p[ref.dataset.field] = ref.type === "checkbox" ? ref.checked : ref.value;

      // Clear field error on input (same pattern as opportunities updateForm)
      if (p.fieldErrors?.[ref.dataset.field])
        p.fieldErrors[ref.dataset.field] = null;

      // Recompute display name options when name-related fields change
      if (
        ["firstName", "lastName", "nickname", "organization"].includes(
          ref.dataset.field,
        )
      ) {
        p.displayNameOptions = computeDisplayNameOptions(p);
        // If current displayName is no longer among options, reset to first
        if (p.displayName && !p.displayNameOptions.includes(p.displayName)) {
          p.displayName = p.displayNameOptions[0] || "";
        }
      }
    }
  },

  /**
   * Input handler for the phone field.
   * intlTelInput with separateDialCode:true keeps only the national part in
   * input.value — the dial code lives in a sibling widget element. Read the
   * canonical E.164 from the widget so state.phone (shown in the profile
   * card view) keeps its country code. Falls back to ref.value only before
   * the widget has initialized.
   */
  updatePhoneField() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
    if (itiInstance) {
      p.phone = itiInstance.getNumber() || "";
    } else {
      const { ref } = getElement();
      p.phone = ref.value;
    }
    if (p.fieldErrors?.phone) p.fieldErrors.phone = null;
  },

  /**
   * Save profile changes to server
   */
  async save(event) {
    const { state, actions } = store("launchpad");
    event.preventDefault();
    const p = ensurePanel(state, "profile");
    p.fieldErrors = {};

    // Validate name fields (optional but must be well-formed when provided)
    const vm = p.validationMessages || {};
    const nameLimits = p.nameLimits || {};
    for (const field of ["firstName", "lastName"]) {
      const err = validateName(p[field], nameLimits);
      if (err) p.fieldErrors[field] = vm[err] || err;
    }

    // Nickname: validate-on-change (mirrors ProfileService).
    // Shares UsernamePolicy with register's login name. Legacy values that
    // predate this rule survive — we only reject when the user actively
    // changes to a non-conforming value. Empty nickname is allowed.
    const newNickname = (p.nickname ?? "").trim();
    const originalNickname = p._originalNickname ?? "";
    if (newNickname !== "" && newNickname !== originalNickname) {
      const usernameError = validateUsername(
        newNickname,
        state.launchpadSettings?.usernamePolicy,
      );
      if (usernameError) p.fieldErrors.nickname = usernameError;
    }

    // Telegram: validate-on-change via MessengerPolicy (Telegram-style rules).
    // Same legacy-tolerant semantics as nickname.
    const newTelegram = (p.telegram ?? "").trim();
    const originalTelegram = p._originalTelegram ?? "";
    if (newTelegram !== "" && newTelegram !== originalTelegram) {
      const telegramError = validateMessenger(
        newTelegram,
        state.launchpadSettings?.messengerPolicy,
      );
      if (telegramError) p.fieldErrors.telegram = telegramError;
    }

    // Read phone from intlTelInput widget (or fallback to state)
    const phone = itiInstance ? itiInstance.getNumber() : p.phone;
    const phoneCountry = itiInstance
      ? itiInstance.getSelectedCountryData().iso2
      : "";

    // Client-side phone validation via intlTelInput
    if (itiInstance && phone && !itiInstance.isValidNumber()) {
      p.fieldErrors.phone =
        state.launchpadSettings.messages?.invalidPhone ??
        "Invalid phone number";
    }

    if (Object.keys(p.fieldErrors).length) {
      scrollToFirstError();
      return;
    }

    // Normalize URL field before sending (mirrors backend sanitizeUrl)
    p.userUrl = normalizeUrl(p.userUrl);

    p.isSaving = true;

    try {
      const data = await fetchJson(
        state,
        `${state.launchpadSettings.restUrl}profile`,
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
        p._originalNickname = (data.nickname ?? "").toString();
        p._originalTelegram = (data.telegram ?? "").toString();
        // Sync widget with server-returned value
        if (itiInstance && data.phone) {
          itiInstance.setNumber(data.phone);
        }
        // If profile was completed (role upgraded), invalidate opportunities panel
        // so it re-fetches on next visit and clears the onboarding lock screen
        if (data._roleUpgraded) {
          const opp = ensurePanel(state, "opportunities");
          opp._loaded = false;
          opp.isLocked = false;
        }
        if (data._roleDegraded) {
          const opp = ensurePanel(state, "opportunities");
          opp._loaded = false;
          opp.isLocked = true;
        }
        // Role change → refresh tour scenarios so button triggers correct tour
        if (data._roleUpgraded || data._roleDegraded) {
          store("tour").actions.refreshScenarios();
        }
      }
      // Return to card view via URL
      const url = new URL(window.location);
      url.searchParams.delete("view");
      window.history.replaceState({}, "", url);
      actions.syncStateFromUrl();
      scrollToPageTop();
    } catch (error) {
      // Map backend field_errors keys to translated messages
      if (error.fieldErrors) {
        const vm = p.validationMessages || {};
        for (const [field, errKey] of Object.entries(error.fieldErrors)) {
          p.fieldErrors[field] = vm[errKey] || errKey;
        }
      }
      // Show banner only when there are no inline field errors
      if (!Object.keys(p.fieldErrors).length) {
        p.error = error.message;
        setTimeout(() => {
          p.error = null;
        }, 5000);
      }
      scrollToFirstError();
    } finally {
      p.isSaving = false;
    }
  },

  /**
   * Enter password change mode
   */
  startChangePassword() {
    const { state, actions } = store("launchpad");
    ensurePanel(state, "profile").error = null;
    const url = new URL(window.location);
    url.searchParams.set("view", "password");
    window.history.pushState({}, "", url);
    actions.syncStateFromUrl();
  },

  /**
   * Cancel password change mode
   */
  cancelPasswordChange() {
    const { state, actions } = store("launchpad");
    const p = ensurePanel(state, "profile");
    p.passwordData = { current: "", new: "" };
    p.isCurrentPasswordVisible = false;
    p.isNewPasswordVisible = false;
    p.error = null;
    const url = new URL(window.location);
    url.searchParams.delete("view");
    window.history.pushState({}, "", url);
    actions.syncStateFromUrl();
  },

  /**
   * Update password form field
   */
  updatePasswordField() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    const p = ensurePanel(state, "profile");
    p.passwordData[ref.dataset.field] = ref.value;
  },

  toggleCurrentPasswordVisibility() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
    p.isCurrentPasswordVisible = !p.isCurrentPasswordVisible;
  },

  toggleNewPasswordVisibility() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
    p.isNewPasswordVisible = !p.isNewPasswordVisible;
  },

  async generatePassword() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");

    if (p.isGenerating) return;

    p.isGenerating = true;
    p.error = null;

    try {
      const data = await fetchJson(
        state,
        state.launchpadSettings.generatePasswordUrl,
      );

      if (data.success && data.password) {
        p.passwordData.new = data.password;
        p.isNewPasswordVisible = true;
      }
    } catch (error) {
      p.error = error.message;
      setTimeout(() => {
        p.error = null;
      }, 5000);
    } finally {
      p.isGenerating = false;
    }
  },

  confirmPasswordSuccess() {
    const { state } = store("launchpad");
    window.location.href = state.launchpadSettings.loginUrl;
  },

  /**
   * Submit password change to server
   */
  async submitPasswordChange(event) {
    const { state } = store("launchpad");
    event.preventDefault();
    const p = ensurePanel(state, "profile");

    // ── Client-side validation (UX guard — server re-validates) ──────────
    const policy = state.launchpadSettings?.passwordPolicy ?? {};
    const messages = policy.messages ?? {};
    const minLen = policy.minLength ?? 12;
    const password = p.passwordData.new;

    if (!password || password.length < minLen) {
      p.error = messages.tooShort ?? "";
      setTimeout(() => {
        p.error = null;
      }, 5000);
      scrollToFirstError();
      return;
    }
    if (
      !/[A-Z]/.test(password) ||
      !/[0-9]/.test(password) ||
      !/[^A-Za-z0-9]/.test(password)
    ) {
      p.error = messages.tooWeak ?? "";
      setTimeout(() => {
        p.error = null;
      }, 5000);
      scrollToFirstError();
      return;
    }
    // ──────────────────────────────────────────────────────────────────────

    p.isSaving = true;
    try {
      await fetchJson(
        state,
        `${state.launchpadSettings.restUrl}security/password`,
        {
          method: "POST",
          body: {
            currentPassword: p.passwordData.current,
            newPassword: p.passwordData.new,
          },
        },
      );

      p.isSaving = false;
      p.passwordSuccessPopup = { isOpen: true };
    } catch (error) {
      p.error = error.message;
      p.isSaving = false;
      setTimeout(() => {
        p.error = null;
      }, 5000);
      scrollToFirstError();
    }
  },

  // ── Email change popup ──────────────────────────────────────────────

  openEmailChange() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
    p.emailPopup = {
      isOpen: true,
      newEmail: p.email,
      password: "",
      isPasswordVisible: false,
      isChanging: false,
      error: null,
    };
  },

  cancelEmailChange() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
    p.emailPopup.isOpen = false;
    p.emailPopup.password = "";
    p.emailPopup.error = null;
  },

  updateEmailPopupField() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    // const popup = ensurePanel(state, "profile").emailPopup;
    // if (ref.dataset.field) {
    //   popup[ref.dataset.field] = ref.value;
    // }
    /* hotfix actions.js:563 Uncaught TypeError: Cannot set properties of undefined (setting 'newEmail') at updateEmailPopupField */
    const p = ensurePanel(state, "profile");
    if (!p || !ref.dataset.field) return;
    const popup = p.emailPopup;
    if (!popup) return;
    popup[ref.dataset.field] = ref.value;
  },

  toggleEmailPasswordVisibility() {
    const { state } = store("launchpad");
    const popup = ensurePanel(state, "profile").emailPopup;
    popup.isPasswordVisible = !popup.isPasswordVisible;
  },

  async confirmEmailChange() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
    const popup = p.emailPopup;

    if (!popup.newEmail || !popup.password) {
      popup.error =
        state.launchpadSettings.messages?.requiredFields ??
        "Please fill in all fields.";
      setTimeout(() => {
        popup.error = null;
      }, 5000);
      return;
    }

    popup.isChanging = true;
    popup.error = null;

    try {
      const data = await fetchJson(
        state,
        `${state.launchpadSettings.restUrl}profile/email`,
        {
          method: "POST",
          body: {
            email: popup.newEmail,
            password: popup.password,
          },
        },
      );

      if (data) {
        Object.assign(p, data);
      }

      popup.isOpen = false;
      popup.password = "";
    } catch (error) {
      popup.error = error.message;
      setTimeout(() => {
        popup.error = null;
      }, 5000);
    } finally {
      popup.isChanging = false;
    }
  },

  // ── Delete account ────────────────────────────────────────────────

  /**
   * Open the delete account confirmation popup.
   */
  deleteProfile() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
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
  cancelDelete() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
    p.deletePopup.isOpen = false;
    p.deletePopup.password = "";
    p.deletePopup.error = null;
  },

  /**
   * Update the delete popup password field from input.
   */
  updateDeletePassword() {
    const { state } = store("launchpad");
    const { ref } = getElement();
    ensurePanel(state, "profile").deletePopup.password = ref.value;
  },

  /**
   * Toggle password visibility in the delete popup.
   */
  toggleDeletePasswordVisibility() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
    p.deletePopup.isPasswordVisible = !p.deletePopup.isPasswordVisible;
  },

  /**
   * Confirm account deletion: verify password server-side and delete.
   */
  async confirmDelete() {
    const { state } = store("launchpad");
    const p = ensurePanel(state, "profile");
    const popup = p.deletePopup;

    if (!popup.password) {
      popup.error =
        state.launchpadSettings.messages?.passwordRequired ??
        "Please enter your password.";
      setTimeout(() => {
        popup.error = null;
      }, 5000);
      return;
    }

    popup.isDeleting = true;
    popup.error = null;

    try {
      await fetchJson(
        state,
        `${state.launchpadSettings.restUrl}profile/delete`,
        {
          method: "POST",
          body: { password: popup.password },
        },
      );

      // Close confirmation popup, show success popup
      popup.isOpen = false;
      p.deleteSuccessPopup = { isOpen: true };
    } catch (error) {
      popup.error = error.message;
      popup.isDeleting = false;
      setTimeout(() => {
        popup.error = null;
      }, 5000);
    }
  },

  /**
   * After successful deletion, redirect to home.
   */
  confirmDeleteSuccess() {
    const { state } = store("launchpad");
    window.location.href = state.launchpadSettings.homeUrl || "/";
  },
};
