/**
 * Contact Form — Interactivity API Store
 *
 * State hydrated from PHP via wp_interactivity_state('contact', ...).
 * intlTelInput initialized imperatively via data-wp-init callback.
 *
 * Feedback architecture:
 *   - Field errors  → inline per-field spans (state.errors.{name})
 *   - Server errors → inline auto-dismiss alert (state.serverError)
 *   - Success       → popup overlay, auto-dismiss 3s (state.showSuccess)
 *   - Loading       → in-button text swap (state.isSubmitting)
 *
 * File: inc/contact/Assets/contact-store.js
 */

import { store, getElement } from "@wordpress/interactivity";
import { fetchJson } from "../../shared/Assets/shared-utils.js";

/** @type {import('intl-tel-input').Iti|null} */
let iti = null;

const EMAIL_RE = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

const EMPTY_FIELDS = { name: "", phone: "", email: "", message: "" };
const EMPTY_ERRORS = { name: null, phone: null, email: null, message: null };

const { state } = store("contact", {
  state: {
    get charCount() {
      return (state.fields?.message || "").length;
    },

    get counterText() {
      const limit = state.config?.charLimit || 500;
      return `${state.charCount}/${limit}`;
    },
  },

  actions: {
    /**
     * Shared handler for all form inputs.
     * Reads name & value from the DOM element that fired the event.
     */
    updateField() {
      const { ref } = getElement();
      const { name, value } = ref;
      if (!name || !state.fields) return;

      state.fields[name] = value;

      // Clear field error on input
      if (value.trim() && state.errors) {
        state.errors[name] = null;
      }
    },

    /**
     * Form submission: validate client-side, then POST to REST.
     */
    async submit(e) {
      e.preventDefault();

      const msgs = state.config?.messages || {};
      const form = getElement().ref;

      // Reset
      state.errors = { ...EMPTY_ERRORS };
      state.serverError = null;

      let valid = true;
      let firstInvalid = null;

      const markInvalid = (fieldName, msg, el) => {
        state.errors[fieldName] = msg;
        valid = false;
        if (!firstInvalid) firstInvalid = el;
      };

      /* ---- Required fields ---- */
      form.querySelectorAll("[required]").forEach((input) => {
        if (!input.value.trim()) {
          markInvalid(input.name, msgs.required || "Required", input);
        }
      });

      /* ---- Email format ---- */
      const email = (state.fields.email || "").trim();
      if (email && !state.errors.email && !EMAIL_RE.test(email)) {
        markInvalid(
          "email",
          msgs.invalidEmail || "Invalid email",
          form.querySelector('[name="email"]'),
        );
      }

      /* ---- Phone (intlTelInput) ---- */
      if (iti) {
        const phoneVal = (state.fields.phone || "").trim();
        if (phoneVal && !iti.isValidNumber()) {
          markInvalid(
            "phone",
            msgs.invalidPhone || "Invalid phone",
            form.querySelector('[name="phone"]'),
          );
        }
      }

      if (!valid) {
        firstInvalid?.focus();
        return;
      }

      /* ---- Collect final values ---- */
      if (iti && iti.isValidNumber()) {
        state.fields.phone = iti.getNumber();
      }

      state.isSubmitting = true;
      state.showSuccess = false;

      try {
        const data = await fetchJson(
          state.config,
          `${state.config.restUrl}send`,
          {
            method: "POST",
            body: {
              name: state.fields.name,
              phone: state.fields.phone,
              email: state.fields.email,
              message: state.fields.message,
              honeypot: form.querySelector('[name="honeypot"]')?.value || "",
            },
          },
        );

        // Success — show popup, reset form
        state.showSuccess = true;
        state.fields = { ...EMPTY_FIELDS };
        state.errors = { ...EMPTY_ERRORS };
        form.reset();
        if (iti) iti.setNumber("");

        setTimeout(() => {
          state.showSuccess = false;
        }, 3000);
      } catch (err) {
        // Server/network error — inline auto-dismiss
        state.serverError = err.message || msgs.errorText;

        setTimeout(() => {
          state.serverError = null;
        }, 5000);
      } finally {
        state.isSubmitting = false;
      }
    },

    /**
     * Dismiss success popup (click overlay).
     */
    dismissPopup() {
      state.showSuccess = false;
    },
  },

  callbacks: {
    /**
     * Initialize intlTelInput on the phone <input>.
     * Bound via data-wp-init="callbacks.initPhone" on the input element.
     */
    initPhone() {
      const { ref } = getElement();
      if (!ref || !window.intlTelInput) return;

      iti = window.intlTelInput(ref, {
        initialCountry: "auto",
        geoIpLookup(success, failure) {
          fetch("https://ipapi.co/json/")
            .then((r) => r.json())
            .then((d) => success(d.country_code))
            .catch(() => failure());
        },
        countryOrder: ["ua", "pl", "de", "us", "gb"],
        excludeCountries: ["ru"],
        nationalMode: true,
        formatAsYouType: true,
        strictMode: true,
        countrySearch: true,
      });

      ref.addEventListener("blur", () => {
        try {
          const full = iti.getNumber();
          if (full) {
            ref.value = full;
            state.fields.phone = full;
          }
        } catch (_) {
          /* intlTelInput may throw if destroyed */
        }

        if (iti.isValidNumber() && state.errors) {
          state.errors.phone = null;
        }
      });
    },
  },
});
