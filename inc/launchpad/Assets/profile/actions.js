/**
 * Launchpad Store — Profile Actions
 *
 * All actions under the `actions.profile` namespace.
 * Uses Store Locator pattern: store("launchpad") to access state/actions.
 *
 * File: inc/launchpad/Assets/profile/actions.js
 */

import { getElement, store } from "@wordpress/interactivity";
import { ensurePanel, fetchJson } from "../utils.js";

/** intlTelInput instance — survives across action calls */
let itiInstance = null;

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
      p[ref.dataset.field] =
        ref.type === "checkbox" ? ref.checked : ref.value;

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
   * Save profile changes to server
   */
  async save(event) {
    const { state, actions } = store("launchpad");
    event.preventDefault();
    const p = ensurePanel(state, "profile");

    // Read phone from intlTelInput widget (or fallback to state)
    const phone = itiInstance ? itiInstance.getNumber() : p.phone;
    const phoneCountry = itiInstance
      ? itiInstance.getSelectedCountryData().iso2
      : "";

    // Client-side phone validation via intlTelInput
    if (itiInstance && phone && !itiInstance.isValidNumber()) {
      const msg =
        state.launchpadSettings.messages?.invalidPhone ??
        "Invalid phone number";
      p.error = msg;
      setTimeout(() => {
        p.error = null;
      }, 5000);
      return;
    }

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
            email: p.email,
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
      }
      // Return to card view via URL
      const url = new URL(window.location);
      url.searchParams.delete("view");
      window.history.replaceState({}, "", url);
      actions.syncStateFromUrl();
    } catch (error) {
      p.error = error.message;
      setTimeout(() => {
        p.error = null;
      }, 5000);
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
    }
  },

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
        state.launchpadSettings.validationStrings?.passwordRequired ??
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
