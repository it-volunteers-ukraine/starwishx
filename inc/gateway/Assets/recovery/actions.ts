/**
 * Gateway Store — Recovery Actions
 * File: inc/gateway/Assets/recovery/actions.ts
 */
import { withSyncEvent } from "@wordpress/interactivity";
import { historyState } from "../../../shared/Assets/store-utils.ts";
import { fieldUpdater, reloadIfSessionChanged, showFailure } from "../forms.ts";
import { gatewayStore } from "../stores.ts";
import type { LostPasswordForm, ResetPasswordForm } from "../types.ts";
import {
  errorMessage,
  gatewayFetch,
  isGeneratedPassword,
  isMessageResponse,
  validatePassword,
  validators,
} from "../utils.ts";

/** How long the reset's success message shows before going to login. */
const RESET_REDIRECT_DELAY_MS = 2000;

// ─────────────────────────────────────────────────────────────────────────────
// Phase 1: Lost Password — request a reset link
// ─────────────────────────────────────────────────────────────────────────────

export const LOST_FIELDS = [
  "userLogin",
] as const satisfies readonly (keyof LostPasswordForm)[];

const lostPasswordForm = () => gatewayStore().state.forms.lostPassword;

export const lostPasswordActions = {
  updateField: fieldUpdater(LOST_FIELDS, lostPasswordForm),

  /** withSyncEvent: it cancels the native submit. */
  submit: withSyncEvent(async (event: Event): Promise<void> => {
    event.preventDefault();

    const { state } = gatewayStore();
    const form = state.forms.lostPassword;
    if (form.isSubmitting) return;

    if (!validators.required(form.userLogin)) {
      form.fieldErrors = {
        userLogin: state.validationStrings.userLoginRequired,
      };
      return;
    }

    form.isSubmitting = true;
    form.error = null;

    try {
      await gatewayFetch(
        `${state.gatewaySettings.restUrl}password/lost`,
        isMessageResponse,
        {
          method: "POST",
          body: { user_login: form.userLogin },
        },
      );
      // Success whether or not the account exists: the server answers the
      // same either way, so the form can't be used to enumerate users.
      form.success = true;
    } catch (error) {
      if (reloadIfSessionChanged(error)) return;
      showFailure(form, error, LOST_FIELDS);
    } finally {
      form.isSubmitting = false;
    }
  }),
};

// ─────────────────────────────────────────────────────────────────────────────
// Phase 2: Reset Password — the new password, with key + login from the URL
// ─────────────────────────────────────────────────────────────────────────────

export const RESET_FIELDS = [
  "newPassword",
] as const satisfies readonly (keyof ResetPasswordForm)[];

const resetPasswordForm = () => gatewayStore().state.forms.resetPassword;

export const resetPasswordActions = {
  // No inline slot: typing clears the banner instead.
  updateField: fieldUpdater(RESET_FIELDS, resetPasswordForm, {
    clearBanner: true,
  }),

  toggleVisibility(): void {
    const form = resetPasswordForm();
    form.isPasswordVisible = !form.isPasswordVisible;
  },

  /** A strong password suggested by the server. */
  async generate(): Promise<void> {
    const { state } = gatewayStore();
    const form = state.forms.resetPassword;
    if (form.isGenerating) return;

    form.isGenerating = true;
    form.error = null;

    try {
      const data = await gatewayFetch(
        `${state.gatewaySettings.restUrl}password/generate`,
        isGeneratedPassword,
      );
      form.newPassword = data.password;
      form.isPasswordVisible = true; // show what was generated
    } catch (error) {
      if (reloadIfSessionChanged(error)) return;
      form.error = errorMessage(error);
    } finally {
      form.isGenerating = false;
    }
  },

  /** withSyncEvent: it cancels the native submit. */
  submit: withSyncEvent(async (event: Event): Promise<void> => {
    event.preventDefault();

    const { state } = gatewayStore();
    const form = state.forms.resetPassword;
    if (form.isSubmitting) return;

    // Client-side validation (UX guard only - the server re-validates).
    const invalid = validatePassword(form.newPassword, state.passwordPolicy);
    if (invalid !== null) {
      form.error = invalid;
      return;
    }

    form.isSubmitting = true;
    form.error = null;

    try {
      const params = new URL(window.location.href).searchParams;
      const data = await gatewayFetch(
        `${state.gatewaySettings.restUrl}password/reset`,
        isMessageResponse,
        {
          method: "POST",
          body: {
            login: params.get("login") ?? "",
            key: params.get("key") ?? "",
            password: form.newPassword,
          },
        },
      );

      form.success = true;
      form.successMessage = data.message;
      form.newPassword = ""; // clear the secret at once

      // The key is spent; don't leave it (and the login) in the history.
      const { baseUrl } = state.gatewaySettings;
      window.history.replaceState(historyState({}), "", baseUrl);
      setTimeout(() => {
        window.location.href = baseUrl;
      }, RESET_REDIRECT_DELAY_MS);
    } catch (error) {
      if (reloadIfSessionChanged(error)) return;
      form.error = errorMessage(error);
    } finally {
      form.isSubmitting = false;
    }
  }),
};
