/**
 * Tour Manager — Shepherd.js Orchestration Layer
 * File: inc/tour/Assets/tour-manager.js
 *
 * Bridges the reactive iAPI world with imperative Shepherd.js.
 * Handles: panel switching, lazy-load waiting, conditional step skipping.
 *
 * Shepherd.js loaded as ESM via wp_enqueue_script_module() — imported as external.
 */

import { store } from "@wordpress/interactivity";
import Shepherd from "shepherd.js";

/**
 * Touch-input detection for the focusAfterRender workaround below.
 * Mirrors the coarse-pointer test in opportunities/actions.js::openDatePicker
 * — touch laptops with a mouse register a fine pointer and stay on the
 * desktop path, where Shepherd's a11y focus assertion is preserved.
 */
const isTouchDevice =
  typeof navigator !== "undefined" &&
  (navigator.maxTouchPoints > 0 ||
    (typeof matchMedia === "function" &&
      matchMedia("(pointer: coarse)").matches));

export class TourManager {
  constructor(tourState) {
    this.tourState = tourState;
    this.tour = null;
  }

  /**
   * Start a tour from scenario data (hydrated from PHP).
   *
   * @param {string} tourId
   * @param {Object} scenario - { id, label, steps[], completed, dismissed }
   */
  start(tourId, scenario) {
    this.cleanup();

    const messages = this.tourState.config.messages;

    this.tour = new Shepherd.Tour({
      useModalOverlay: true,
      defaultStepOptions: {
        classes: "sw-tour-step",
        scrollTo: { behavior: "smooth", block: "center" },
        cancelIcon: { enabled: true },
        modalOverlayOpeningPadding: 8,
        modalOverlayOpeningRadius: 8,
      },
    });

    // Build steps from server-hydrated scenario data
    const steps = scenario.steps || [];

    steps.forEach((stepDef, index) => {
      const isFirst = index === 0;
      const isLast = index === steps.length - 1;

      const buttons = [];

      // Skip button on first step
      if (isFirst) {
        buttons.push({
          text: messages.skip,
          action: () => this.tour.cancel(),
          classes: "sw-tour-btn sw-tour-btn--skip",
          secondary: true,
        });
      }

      // Back button (except first step)
      if (!isFirst) {
        buttons.push({
          text: messages.prev,
          action: () => this.tour.back(),
          classes: "sw-tour-btn sw-tour-btn--prev",
          secondary: true,
        });
      }

      // Next or Finish button
      buttons.push({
        text: isLast ? messages.finish : messages.next,
        action: isLast ? () => this.tour.complete() : () => this.tour.next(),
        classes: isLast
          ? "sw-tour-btn sw-tour-btn--finish"
          : "sw-tour-btn sw-tour-btn--next",
      });

      const stepOptions = {
        id: stepDef.id,
        title: stepDef.title,
        text: stepDef.text,
        buttons,
      };

      // Attach to element (some steps are centered modals with no target)
      if (stepDef.attachTo?.element) {
        stepOptions.attachTo = {
          element: stepDef.attachTo.element,
          on: stepDef.attachTo.on || "bottom",
        };
      }

      // Extra highlighted elements (cutouts in modal overlay without tooltip)
      if (stepDef.extraHighlights?.length) {
        stepOptions.extraHighlights = stepDef.extraHighlights;
      }

      // Advance the tour when the user performs the step's action directly
      // on the page (e.g. clicking the highlighted button) instead of the
      // tooltip's Next button. Shepherd attaches the listener on step show
      // and cleans it up on step exit. Both pathways converge on the same
      // next step: prepareStep is idempotent against a URL/view that
      // already matches stepDef.view.
      if (stepDef.advanceOn?.selector && stepDef.advanceOn?.event) {
        stepOptions.advanceOn = {
          selector: stepDef.advanceOn.selector,
          event: stepDef.advanceOn.event,
        };
      }

      // The key integration: beforeShowPromise handles panel/view switching + DOM readiness
      stepOptions.beforeShowPromise = () => this.prepareStep(stepDef);

      // Track active step index for reactive state. Regular function so
      // `this` resolves to the Shepherd Step instance (for getElement);
      // the manager is reached via the captured `manager` const.
      const manager = this;
      stepOptions.when = {
        show() {
          manager.tourState.activeStepIndex = index;
          // shepherd-issues #1143: on touch devices floating-ui's
          // focusAfterRender refires on every resize — including the one
          // triggered by the on-screen keyboard opening — and re-asserts
          // focus on the step element, dismissing the keyboard. Neutralise
          // the step element's .focus() so user input retains focus.
          // Desktop a11y (focus-trap, screen-reader step announcement) is
          // left intact because the gate stays false there.
          if (isTouchDevice) {
            const stepEl = this.getElement?.();
            if (stepEl) stepEl.focus = () => {};
          }
        },
      };

      this.tour.addStep(stepOptions);
    });

    // Lifecycle events → store actions
    this.tour.on("complete", () => {
      store("tour").actions.completeTour();
    });

    this.tour.on("cancel", () => {
      store("tour").actions.dismissTour();
    });

    this.tour.start();
  }

  /**
   * Prepare the DOM for a step before it shows.
   * Handles: conditional skipping, panel switching, view switching,
   * lazy-load waiting, and target element DOM readiness.
   */
  async prepareStep(stepDef) {
    const launchpad = store("launchpad");

    // 1. Conditional step: evaluate state path
    if (stepDef.condition) {
      const shouldShow = this.evaluateCondition(
        stepDef.condition,
        stepDef.conditionNegate,
      );
      if (!shouldShow) {
        // Can't truly skip in Shepherd — resolve immediately so step shows briefly,
        // then schedule an auto-advance on the next tick
        setTimeout(() => {
          if (this.tour?.getCurrentStep()?.id === stepDef.id) {
            this.tour.next();
          }
        }, 0);
        return;
      }
    }

    // 2. Panel switching (if step needs a different panel)
    if (stepDef.panel && launchpad.state.activePanel !== stepDef.panel) {
      await launchpad.actions.setActivePanel(stepDef.panel, {
        pushHistory: false,
      });

      // 3. Wait for panel to be loaded (lazy-load awareness)
      await this.waitForPanel(stepDef.panel);
    }

    // 4. View switching within panel
    if (stepDef.view) {
      if (stepDef.panel === "profile" && stepDef.view === "profile") {
        // Profile panel uses state-based edit mode (not URL-driven)
        if (!launchpad.state.panels?.profile?.isEditing) {
          launchpad.actions.profile?.startEdit?.();
          await this.waitForElement(stepDef.attachTo?.element, 2000);
        }
      } else {
        // Other panels use URL-driven view switching
        const url = new URL(window.location);
        const currentView = url.searchParams.get("view");
        if (currentView !== stepDef.view) {
          url.searchParams.set("view", stepDef.view);
          window.history.replaceState({}, "", url);
          launchpad.actions.syncStateFromUrl();
          await this.waitForElement(stepDef.attachTo?.element, 2000);
        }
      }
    }

    // 5. Wait for target element to exist in DOM (reactive rendering)
    if (stepDef.attachTo?.element) {
      await this.waitForElement(stepDef.attachTo.element, 3000);
    }
  }

  /**
   * Wait for a panel's data to finish loading.
   * Uses requestAnimationFrame polling (iAPI Proxies don't support observers).
   */
  waitForPanel(panelId, timeout = 5000) {
    return new Promise((resolve) => {
      const launchpad = store("launchpad");
      const start = Date.now();

      const check = () => {
        const panel = launchpad.state.panels?.[panelId];
        if (panel?._loaded && !panel?.isLoading) {
          resolve();
          return;
        }
        if (Date.now() - start > timeout) {
          // Resolve anyway — better to show step without target than hang
          resolve();
          return;
        }
        requestAnimationFrame(check);
      };
      check();
    });
  }

  /**
   * Wait for a DOM element to appear and be visible.
   * Uses MutationObserver to detect reactive DOM updates from iAPI.
   */
  waitForElement(selector, timeout = 3000) {
    return new Promise((resolve) => {
      if (!selector) {
        resolve();
        return;
      }

      const existing = document.querySelector(selector);
      if (existing && !existing.hidden && existing.offsetParent !== null) {
        resolve();
        return;
      }

      const start = Date.now();
      const observer = new MutationObserver(() => {
        const el = document.querySelector(selector);
        if (el && !el.hidden && el.offsetParent !== null) {
          observer.disconnect();
          resolve();
        } else if (Date.now() - start > timeout) {
          observer.disconnect();
          resolve();
        }
      });

      observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ["hidden"],
      });

      // Fallback timeout
      setTimeout(() => {
        observer.disconnect();
        resolve();
      }, timeout);
    });
  }

  /**
   * Evaluate a dot-path condition against launchpad state.
   * e.g., "panels.opportunities.isLocked" → state.panels.opportunities.isLocked
   */
  evaluateCondition(conditionPath, negate = false) {
    const launchpad = store("launchpad");
    const value = conditionPath
      .split(".")
      .reduce((obj, key) => obj?.[key], launchpad.state);
    return negate ? !value : !!value;
  }

  cleanup() {
    if (this.tour) {
      // Remove listeners before canceling to avoid triggering dismiss
      this.tour.off("complete");
      this.tour.off("cancel");
      this.tour.cancel();
      this.tour = null;
    }
  }
}
