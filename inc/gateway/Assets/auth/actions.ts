/**
 * Gateway Store — Auth Actions (Login)
 * File: inc/gateway/Assets/auth/actions.ts
 */
import { getElement, withSyncEvent } from "@wordpress/interactivity";
import { fieldUpdater, reloadIfSessionChanged, showFailure } from "../forms.ts";
import { gatewayStore } from "../stores.ts";
import type { LoginForm } from "../types.ts";
import {
  gatewayFetch,
  isLoginResponse,
  sameOriginUrl,
  validators,
} from "../utils.ts";

/** The fields the login inputs write (data-field). */
const FIELDS = [
  "username",
  "password",
] as const satisfies readonly (keyof LoginForm)[];

const loginForm = () => gatewayStore().state.forms.login;

export const loginActions = {
  updateField: fieldUpdater(FIELDS, loginForm),

  toggleRemember(): void {
    const { ref } = getElement();
    if (ref instanceof HTMLInputElement) loginForm().rememberMe = ref.checked;
  },

  toggleVisibility(): void {
    const form = loginForm();
    form.isPasswordVisible = !form.isPasswordVisible;
  },

  /** withSyncEvent: it cancels the native submit. */
  submit: withSyncEvent(async (event: Event): Promise<void> => {
    event.preventDefault();

    const { state } = gatewayStore();
    const form = state.forms.login;
    if (form.isSubmitting) return;

    // Client-side validation (UX guard only).
    const strings = state.validationStrings;
    const errors: LoginForm["fieldErrors"] = {
      username: validators.required(form.username)
        ? null
        : strings.usernameRequired,
      password: validators.required(form.password)
        ? null
        : strings.passwordRequired,
    };
    if (errors.username !== null || errors.password !== null) {
      form.fieldErrors = errors;
      return;
    }

    form.isSubmitting = true;
    form.error = null;

    try {
      const data = await gatewayFetch(
        `${state.gatewaySettings.restUrl}login`,
        isLoginResponse,
        {
          method: "POST",
          body: {
            username: form.username,
            password: form.password,
            remember: form.rememberMe,
          },
        },
      );
      // Logged in: the cookie is set, so even an unexpected redirect target
      // only decides where to go - the gateway page forwards logged-in users.
      const target =
        sameOriginUrl(data.redirectTo) ?? state.gatewaySettings.baseUrl;
      window.location.href = target.toString();
    } catch (error) {
      // Another tab logged in meanwhile: fetch's retry was blocked by
      // checkLoggedOut() (already_authenticated), or couldn't be made.
      if (reloadIfSessionChanged(error)) return;
      showFailure(form, error, FIELDS);
    } finally {
      form.isSubmitting = false;
    }
  }),
};
