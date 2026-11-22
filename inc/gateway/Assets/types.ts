/**
 * Gateway Store — Types
 *
 * The state PHP hydrates (GatewayCore::enqueueAssets for the settings and
 * policies, StateAggregator for the forms) and the REST responses the
 * store reads. Keep in step with inc/gateway/Forms/*::getInitialState().
 *
 * File: inc/gateway/Assets/types.ts
 */
import type { loginActions } from "./auth/actions.ts";
import type { gatewayActions } from "./gateway-store.ts";
import type {
  lostPasswordActions,
  resetPasswordActions,
} from "./recovery/actions.ts";
import type { registerActions } from "./register/actions.ts";

// --- Forms ----------------------------------------------------------------

/** The FormRegistry ids (GatewayCore::registerForms), also `?view=`. */
export type FormId = "login" | "register" | "lost-password" | "reset-password";

/** "lost-password" -> "lostPassword": AbstractForm::getJsId(). */
export type CamelCase<S extends string> =
  S extends `${infer Head}-${infer Tail}`
    ? `${Head}${Capitalize<CamelCase<Tail>>}`
    : S;

/** A form's key under `state.forms` and `actions`. */
export type FormKey = CamelCase<FormId>;

/** A form's visibility flag: AbstractForm::getStateKey(). */
export type ActiveFlag = `is${Capitalize<FormKey>}Active`;

/** The inline error slot of each field; null when there is none. */
export type FieldErrors<F extends string> = Record<F, string | null>;

/** Forms/LoginForm.php */
export interface LoginForm {
  username: string;
  password: string;
  rememberMe: boolean;
  isPasswordVisible: boolean;
  isSubmitting: boolean;
  error: string | null;
  fieldErrors: FieldErrors<"username" | "password">;
}

/** Forms/RegisterForm.php */
export interface RegisterForm {
  username: string;
  email: string;
  isSubmitting: boolean;
  success: boolean;
  error: string | null;
  fieldErrors: FieldErrors<"username" | "email">;
}

/** Forms/LostPasswordForm.php */
export interface LostPasswordForm {
  userLogin: string;
  isSubmitting: boolean;
  error: string | null;
  success: boolean;
  fieldErrors: FieldErrors<"userLogin">;
}

/** Forms/ResetPasswordForm.php - errors go to the banner only. */
export interface ResetPasswordForm {
  newPassword: string;
  isPasswordVisible: boolean;
  isGenerating: boolean;
  isSubmitting: boolean;
  error: string | null;
  success: boolean;
  successMessage: string | null;
}

export interface Forms {
  login: LoginForm;
  register: RegisterForm;
  lostPassword: LostPasswordForm;
  resetPassword: ResetPasswordForm;
}

/** Any one form. */
export type AnyForm = Forms[FormKey];

/** A form with inline field errors. */
export type FormWithFieldErrors = Extract<AnyForm, { fieldErrors: object }>;

// --- Hydrated settings and policies ---------------------------------------

export interface GatewaySettings {
  nonce: string;
  restUrl: string;
  baseUrl: string;
}

/** PasswordPolicy::getClientRules() */
export interface PasswordPolicy {
  minLength: number;
  messages: { tooShort: string; tooWeak: string; hasUserData: string };
}

/** UsernamePolicy::getClientRules() */
export interface UsernamePolicy {
  /** A RegExp source, no delimiters. */
  pattern: string;
  minLength: number;
  maxLength: number;
  messages: { tooShort: string; tooLong: string; invalid: string };
}

/** PasswordPolicy::getClientValidationStrings() */
export interface ValidationStrings {
  usernameRequired: string;
  passwordRequired: string;
  emailRequired: string;
  emailInvalid: string;
  userLoginRequired: string;
}

// --- State and store -----------------------------------------------------

/** What PHP hydrates. The client declares none of it (store merge order). */
export type GatewayServerState = {
  gatewaySettings: GatewaySettings;
  passwordPolicy: PasswordPolicy;
  usernamePolicy: UsernamePolicy;
  validationStrings: ValidationStrings;
  activeView: FormId;
  formMap: Record<FormId, ActiveFlag>;
  forms: Forms;
} & Record<ActiveFlag, boolean>;

export interface GatewayGetters {
  readonly currentForm: AnyForm | undefined;
  readonly resetPasswordInputType: "text" | "password";
  readonly loginPasswordInputType: "text" | "password";
}

export type GatewayState = GatewayServerState & GatewayGetters;

export type GatewayActions = typeof gatewayActions & {
  login: typeof loginActions;
  register: typeof registerActions;
  lostPassword: typeof lostPasswordActions;
  resetPassword: typeof resetPasswordActions;
};

export interface GatewayStore {
  state: GatewayState;
  actions: GatewayActions;
}

// --- REST responses (inc/gateway/Api) --------------------------------------

/** POST login */
export interface LoginResponse {
  success: true;
  redirectTo: string;
}

/** POST register, password/lost, password/reset */
export interface MessageResponse {
  success: true;
  message: string;
}

/** GET password/generate */
export interface GeneratedPasswordResponse {
  success: true;
  password: string;
}
