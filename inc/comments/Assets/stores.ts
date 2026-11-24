/**
 * Comments Store — Typed store accessor
 *
 * store("comments") with no store part returns the registered store; this
 * gives it its type, so no file calls an untyped store("comments").
 *
 * File: inc/comments/Assets/stores.ts
 */
import { store } from "@wordpress/interactivity";
import type { CommentsStore } from "./types.ts";

export const commentsStore = () => store<CommentsStore>("comments");
