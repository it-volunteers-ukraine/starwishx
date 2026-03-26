/**
 * Tour Interactivity API Store
 * File: inc/tour/Assets/tour-store.js
 *
 * Independent store "tour" — manages reactive tour metadata.
 * Shepherd.js orchestration delegated to TourManager class.
 *
 * State hydrated by TourCore::hydrateState() in PHP.
 */

import { store, getElement } from "@wordpress/interactivity";
import { TourManager } from "./tour-manager.js";
import { fetchJson } from "./utils.js";

/** Singleton TourManager instance */
let manager = null;

const { state } = store("tour", {
  state: {
    /**
     * Whether a tour trigger button should be visible.
     * True if any scenario exists (completed tours can be retaken).
     */
    get showStartButton() {
      const { state } = store("tour");
      return Object.keys(state.scenarios || {}).length > 0;
    },

    /**
     * Whether any tour has been completed (enables "Retake" option).
     */
    get canRetake() {
      const { state } = store("tour");
      return Object.values(state.scenarios || {}).some((s) => s.completed);
    },

    /**
     * Trigger button label — "Take a Tour" or "Retake Tour".
     */
    get triggerLabel() {
      const { state } = store("tour");
      const messages = state.config?.messages || {};
      if (state.canRetake) return messages.retakeTour || "Retake Tour";
      return messages.startTour || "Take a Tour";
    },
  },

  actions: {
    /**
     * Start a tour by ID.
     * Called from template via data-wp-on--click with data-tour-id attribute.
     */
    startTour() {
      const { state } = store("tour");
      const { ref } = getElement();
      const tourId = ref?.dataset?.tourId;

      if (!tourId || !state.scenarios?.[tourId]) return;
      if (state.isRunning) return;

      // Reset if retaking a completed/dismissed tour
      if (
        state.scenarios[tourId].completed ||
        state.scenarios[tourId].dismissed
      ) {
        state.scenarios[tourId].completed = false;
        state.scenarios[tourId].dismissed = false;
        fetchJson(state, `${state.config.restUrl}reset`, {
          method: "POST",
          body: { tourId },
        }).catch(() => {});
      }

      state.activeTour = tourId;
      state.isRunning = true;
      state.activeStepIndex = 0;

      if (!manager) {
        manager = new TourManager(state);
      }
      manager.start(tourId, state.scenarios[tourId]);
    },

    /**
     * Start the first available (uncompleted, undismissed) tour.
     * Used by the sidebar trigger which serves both roles.
     */
    startFirstAvailable() {
      const { state } = store("tour");
      if (state.isRunning) return;

      const scenarios = Object.values(state.scenarios || {});
      // Prefer uncompleted tours, fall back to first available for retake
      const target =
        scenarios.find((s) => !s.completed && !s.dismissed) || scenarios[0];
      if (!target) return;

      // Reset if retaking
      if (target.completed || target.dismissed) {
        target.completed = false;
        target.dismissed = false;
        fetchJson(state, `${state.config.restUrl}reset`, {
          method: "POST",
          body: { tourId: target.id },
        }).catch(() => {});
      }

      state.activeTour = target.id;
      state.isRunning = true;
      state.activeStepIndex = 0;

      if (!manager) {
        manager = new TourManager(state);
      }
      manager.start(target.id, target);
    },

    /**
     * Mark the active tour as completed.
     * Called by TourManager on Shepherd 'complete' event.
     */
    async completeTour() {
      const { state } = store("tour");
      const tourId = state.activeTour;
      if (!tourId) return;

      state.scenarios[tourId].completed = true;
      state.isRunning = false;
      state.activeTour = null;

      try {
        await fetchJson(state, `${state.config.restUrl}complete`, {
          method: "POST",
          body: { tourId },
        });
      } catch (_) {
        // Silent — completion is already in client state
      }
    },

    /**
     * Dismiss/cancel the active tour.
     * Called by TourManager on Shepherd 'cancel' event.
     */
    async dismissTour() {
      const { state } = store("tour");
      const tourId = state.activeTour;
      if (!tourId) return;

      state.scenarios[tourId].dismissed = true;
      state.isRunning = false;
      state.activeTour = null;

      try {
        await fetchJson(state, `${state.config.restUrl}dismiss`, {
          method: "POST",
          body: { tourId },
        });
      } catch (_) {
        // Silent
      }
    },

    /**
     * Reset a completed/dismissed tour so it can be re-run.
     * Called from template via data-tour-id attribute.
     */
    async resetAndStart() {
      const { state, actions } = store("tour");
      const { ref } = getElement();
      const tourId = ref?.dataset?.tourId;

      if (!tourId || !state.scenarios?.[tourId]) return;

      state.scenarios[tourId].completed = false;
      state.scenarios[tourId].dismissed = false;

      try {
        await fetchJson(state, `${state.config.restUrl}reset`, {
          method: "POST",
          body: { tourId },
        });
      } catch (_) {}

      // Start immediately after reset
      // Set tourId on ref for startTour to read
      actions.startTour();
    },
  },
});

/**
 * Auto-start tour for first-time users.
 * Delayed to let launchpad fully hydrate before showing overlay.
 */
function autoStart() {
  const scenarios = state.scenarios || {};
  const introId = "launchpad-intro";

  if (
    scenarios[introId] &&
    !scenarios[introId].completed &&
    !scenarios[introId].dismissed
  ) {
    // Temporarily set tourId for startTour to read
    state.activeTour = introId;
    state.isRunning = true;
    state.activeStepIndex = 0;

    if (!manager) {
      manager = new TourManager(state);
    }
    manager.start(introId, scenarios[introId]);
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    setTimeout(autoStart, 1500),
  );
} else {
  setTimeout(autoStart, 1500);
}
