/**
 * Gateway Store — Form helpers
 *
 * What every Gateway form does the same way, written once: writing a field
 * from its input, showing a failed request, and noticing that the session
 * changed under the page.
 *
 * File: inc/gateway/Assets/forms.ts
 */
import { getElement } from "@wordpress/interactivity";
import { ApiError, isKeyOf } from "../../shared/Assets/store-utils.ts";
import { errorMessage, WP_ERROR } from "./utils.ts";

/** The part of a form a field input writes. */
type FieldTarget<K extends string> = Record<K, string> & {
  error: string | null;
  fieldErrors?: Partial<Record<K, string | null>>;
};

/**
 * The `updateField` action for a form: the input's value into the field its
 * `data-field` names, which must be one of `fields` - a data-field can't
 * reach isSubmitting, success or error. Typing clears the field's inline
 * error, and with `clearBanner` the form's banner too (a form whose errors
 * have no inline slot).
 *
 * NoInfer: `fields` alone decides K, so a form without one of them is the
 * error - not a K widened to whatever the form happens to have.
 */
export function fieldUpdater<const K extends string>(
  fields: readonly K[],
  getForm: () => FieldTarget<NoInfer<K>> | undefined,
  { clearBanner = false } = {},
): () => void {
  return () => {
    const { ref } = getElement();
    if (!(ref instanceof HTMLInputElement)) return;
    const field = ref.dataset.field;
    const form = getForm();
    if (!form || !isKeyOf(fields, field)) return;

    const values: Record<K, string> = form;
    values[field] = ref.value;
    if (form.fieldErrors) form.fieldErrors[field] = null;
    if (clearBanner) form.error = null;
  };
}

/**
 * Did the request fail because the session changed under the page (another
 * tab logged in, or the nonce expired)? Then reload: page-gateway.php
 * redirects a logged-in user, and a guest gets a fresh nonce.
 *
 * gatewayFetch() has already retried once with a refreshed nonce, so a
 * stale nonce here means the retry was impossible or failed too.
 */
export function reloadIfSessionChanged(error: unknown): boolean {
  if (
    error instanceof ApiError &&
    (error.code === WP_ERROR.alreadyAuthenticated ||
      error.code === WP_ERROR.nonceInvalid)
  ) {
    window.location.reload();
    return true;
  }
  return false;
}

/**
 * Show a failed request: the server's field errors (`data.field_errors`, see
 * AbstractApiController) in the inline slots of the form's own `fields`,
 * else - including for errors on fields the form has no slot for - the
 * message in the banner. Never neither.
 *
 * The form's slots decide K (NoInfer on `fields`): passing another form's
 * field list is a type error.
 */
export function showFailure<K extends string>(
  form: { error: string | null; fieldErrors: Record<K, string | null> },
  error: unknown,
  fields: readonly NoInfer<K>[],
): void {
  const serverErrors = error instanceof ApiError ? error.fieldErrors : null;
  let routed = false;
  for (const field of fields) {
    const message = serverErrors?.[field];
    if (typeof message === "string") {
      form.fieldErrors[field] = message;
      routed = true;
    }
  }
  if (!routed) form.error = errorMessage(error);
}
