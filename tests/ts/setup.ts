/**
 * Vitest setup (vite.config.ts `test.setupFiles`): the Interactivity API
 * stand-in, the minimal DOM, and a clean slate before every test.
 *
 * File: tests/ts/setup.ts
 */
import { beforeEach, vi } from "vite-plus/test";
import { installDom, resetDom } from "./support/dom.ts";
import { installGateway } from "./support/gateway.ts";

vi.mock(
  "@wordpress/interactivity",
  async () => (await import("./support/interactivity.ts")).interactivity,
);

installDom();

beforeEach(() => {
  resetDom();
  installGateway();
  vi.unstubAllGlobals();
  vi.useRealTimers();
});
