/**
 * Before the run: remove what a crashed earlier run left (e2e- users, the
 * rate-limit counters).
 *
 * File: tests/e2e/support/global-setup.ts
 */
import { wp } from "./wp.ts";

export default function globalSetup(): void {
  wp.cleanup();
}
