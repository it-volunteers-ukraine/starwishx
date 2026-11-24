/**
 * Gateway state as PHP hydrates it (GatewayCore::enqueueAssets +
 * StateAggregator), for the store code under test. A fresh copy for every
 * test: the store mutates it.
 *
 * File: tests/ts/support/gateway.ts
 */
import type {
  GatewayServerState,
  GatewayState,
} from "../../../inc/gateway/Assets/types.ts";
import { fake } from "./interactivity.ts";

export const REST_URL = "https://site.test/wp-json/gateway/v1/";
export const BASE_URL = "https://site.test/gateway/";

export function serverState(): GatewayServerState {
  return {
    gatewaySettings: { nonce: "nonce-1", restUrl: REST_URL, baseUrl: BASE_URL },
    passwordPolicy: {
      minLength: 12,
      messages: {
        tooShort: "too short",
        tooWeak: "too weak",
        hasUserData: "has user data",
      },
    },
    usernamePolicy: {
      pattern: "^[a-zA-Z0-9._-]+$",
      minLength: 3,
      maxLength: 60,
      messages: {
        tooShort: "u short",
        tooLong: "u long",
        invalid: "u invalid",
      },
    },
    validationStrings: {
      usernameRequired: "username required",
      passwordRequired: "password required",
      emailRequired: "email required",
      emailInvalid: "email invalid",
      userLoginRequired: "login required",
    },
    activeView: "login",
    formMap: {
      login: "isLoginActive",
      register: "isRegisterActive",
      "lost-password": "isLostPasswordActive",
      "reset-password": "isResetPasswordActive",
    },
    forms: {
      login: {
        username: "",
        password: "",
        rememberMe: false,
        isPasswordVisible: false,
        isSubmitting: false,
        error: null,
        fieldErrors: { username: null, password: null },
      },
      register: {
        username: "",
        email: "",
        isSubmitting: false,
        success: false,
        error: null,
        fieldErrors: { username: null, email: null },
      },
      lostPassword: {
        userLogin: "",
        isSubmitting: false,
        error: null,
        success: false,
        fieldErrors: { userLogin: null },
      },
      resetPassword: {
        newPassword: "Generated#Password1",
        isPasswordVisible: true,
        isGenerating: false,
        isSubmitting: false,
        error: null,
        success: false,
        successMessage: null,
      },
    },
    isLoginActive: true,
    isRegisterActive: false,
    isLostPasswordActive: false,
    isResetPasswordActive: false,
  };
}

/**
 * Install a fresh gateway store (and the server's copy) and return its
 * state. The getters are plain values here: views.test.ts tests the real
 * ones.
 */
export function installGateway(): GatewayState {
  const state: GatewayState = {
    ...serverState(),
    resetPasswordInputType: "text",
    loginPasswordInputType: "password",
  };
  fake.stores.set("gateway", state);
  fake.server.set("gateway", serverState());
  fake.ref = null;
  return state;
}
