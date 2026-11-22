/**
 * After the run: delete every e2e- user and the rate-limit counters.
 *
 * File: tests/e2e/support/global-teardown.ts
 */
import { wp } from "./wp.ts";

export default function globalTeardown(): void {
  wp.cleanup();
}
