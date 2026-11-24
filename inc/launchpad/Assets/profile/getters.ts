/**
 * Launchpad Store — Profile Getters
 *
 * Computed state for the profile panel, mixed into state with extendState():
 * 'this' is the state proxy (ThisType below).
 *
 * File: inc/launchpad/Assets/profile/getters.ts
 */

import { getContext } from "@wordpress/interactivity";
import type {
  ItemContext,
  LaunchpadState,
  ProfileGetters,
  RevealContext,
} from "../types.ts";
import { loadedPanel } from "../utils.ts";

/** A password input's type for its visibility toggle. */
const inputType = (visible: boolean | undefined): "text" | "password" =>
  visible ? "text" : "password";

export const profileGetters = {
  /**
   * Is the profile card (read-only view) visible?
   * Hidden when editing profile or changing password.
   */
  get isProfileCardVisible(): boolean {
    const p = loadedPanel(this, "profile");
    return !p?.isEditing && !p?.isChangingPassword;
  },

  get currentPasswordInputType(): "text" | "password" {
    return inputType(loadedPanel(this, "profile")?.isCurrentPasswordVisible);
  },

  get newPasswordInputType(): "text" | "password" {
    return inputType(loadedPanel(this, "profile")?.isNewPasswordVisible);
  },

  get deletePasswordInputType(): "text" | "password" {
    return inputType(
      loadedPanel(this, "profile")?.deletePopup.isPasswordVisible,
    );
  },

  get emailPasswordInputType(): "text" | "password" {
    return inputType(
      loadedPanel(this, "profile")?.emailPopup.isPasswordVisible,
    );
  },

  /** Is the sensitive value in context (context.field) blurred? */
  get isFieldBlurred(): boolean {
    const { field } = getContext<Partial<RevealContext>>();
    const revealed = loadedPanel(this, "profile")?.revealedFields ?? [];
    return !field || !revealed.includes(field);
  },

  /** Is the display-name option in context the selected one? */
  get isDisplayNameItemSelected(): boolean {
    const { item } = getContext<Partial<ItemContext<string>>>();
    return item === loadedPanel(this, "profile")?.displayName;
  },
} satisfies ProfileGetters & ThisType<LaunchpadState>;
