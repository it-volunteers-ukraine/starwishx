/**
 * Gateway Store — Typed store accessor
 *
 * store("gateway") with no store part returns the registered store; this
 * gives it its type, so no file calls an untyped store("gateway").
 *
 * File: inc/gateway/Assets/stores.ts
 */
import { store } from "@wordpress/interactivity";
import type { GatewayStore } from "./types.ts";

export const gatewayStore = () => store<GatewayStore>("gateway");
