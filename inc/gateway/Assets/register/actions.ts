/**
 * Gateway Store — Register Actions
 * File: inc/gateway/Assets/register/actions.ts
 */
import { withSyncEvent } from "@wordpress/interactivity";
import { fieldUpdater, reloadIfSessionChanged, showFailure } from "../forms.ts";
import { gatewayStore } from "../stores.ts";
import type { RegisterForm } from "../types.ts";
import {
  gatewayFetch,
  isMessageResponse,
  validateUsername,
  validators,
} from "../utils.ts";

/** The fields the register inputs write (data-field). */
const FIELDS = [
  "username",
  "email",
] as const satisfies readonly (keyof RegisterForm)[];

const registerForm = () => gatewayStore().state.forms.register;

export const registerActions = {
  updateField: fieldUpdater(FIELDS, registerForm),

  /** withSyncEvent: it cancels the native submit. */
  submit: withSyncEvent(async (event: Event): Promise<void> => {
    event.preventDefault();

    const { state } = gatewayStore();
    const form = state.forms.register;
    if (form.isSubmitting) return;

    // Client-side validation (UX guard only).
    const strings = state.validationStrings;
    const errors: RegisterForm["fieldErrors"] = {
      username: validators.required(form.username)
        ? validateUsername(form.username, state.usernamePolicy)
        : strings.usernameRequired,
      email: !validators.required(form.email)
        ? strings.emailRequired
        : validators.email(form.email)
          ? null
          : strings.emailInvalid,
    };
    if (errors.username !== null || errors.email !== null) {
      form.fieldErrors = errors;
      return;
    }

    form.isSubmitting = true;
    form.error = null;

    try {
      await gatewayFetch(
        `${state.gatewaySettings.restUrl}register`,
        isMessageResponse,
        {
          method: "POST",
          body: { username: form.username, email: form.email },
        },
      );
      form.success = true;
      form.username = "";
      form.email = "";
      form.fieldErrors = { username: null, email: null };
    } catch (error) {
      // Same race as login: /register is guest-only too.
      if (reloadIfSessionChanged(error)) return;
      showFailure(form, error, FIELDS);
    } finally {
      form.isSubmitting = false;
    }
  }),
};
