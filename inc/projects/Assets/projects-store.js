/**
 * Projects Store — Main Entry
 *
 * Orchestrates Single Project Page tabs using the Interactivity API.
 * File: inc/projects/Assets/projects-store.js
 */

import { store } from "@wordpress/interactivity";
import { tabGetters } from "./tabs/getters.js";
import { tabActions } from "./tabs/actions.js";
import "../../shared/Assets/popup-store.js";

/**
 * Base State Definition
 *
 * We are not define 'opportunities', 'ngos', or 'counts' here.
 * Properties are hydrated from the server via wp_interactivity_state()
 * in single-project.php. Defining them here would cause the Client Store
 * to overwrite the Server Data with empty defaults.
 *
 * Here defined client-only properties (like activeTab) that need a default.
 */
const projectState = {
  activeTab: "about",
};

/**
 * Mixin Getters
 * Preserves getter descriptors so 'this' remains reactive.
 */
Object.defineProperties(
  projectState,
  Object.getOwnPropertyDescriptors(tabGetters),
);

/**
 * STORE DEFINITION
 */
store("starwishx/projects", {
  state: projectState,
  actions: {
    ...tabActions,
  },
});
