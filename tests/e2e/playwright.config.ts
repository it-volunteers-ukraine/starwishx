/**
 * Playwright: the E2E journeys in a real browser (`pnpm test:e2e`).
 * Locally the installed Edge against the site in .env.local.json; in CI
 * Chromium against a fresh WordPress (see .github/workflows/ci.yml).
 *
 * One worker: the specs share the site's database and the rate-limit
 * counters of the browser's IP. Each test still gets its own browser
 * context, so cookies never leak between tests or into your own browser.
 *
 * File: tests/e2e/playwright.config.ts
 */
import { defineConfig } from "@playwright/test";
import { config } from "./support/config.ts";

const CI = !!process.env.CI;

export default defineConfig({
  testDir: ".",
  testMatch: "**/*.spec.ts",
  outputDir: ".results",
  workers: 1,
  fullyParallel: false,
  forbidOnly: CI,
  retries: CI ? 1 : 0,
  timeout: 30_000,
  expect: { timeout: 7_000 },
  reporter: CI
    ? [["github"], ["html", { outputFolder: ".report", open: "never" }]]
    : [["list"], ["html", { outputFolder: ".report", open: "never" }]],
  globalSetup: "./support/global-setup.ts",
  globalTeardown: "./support/global-teardown.ts",
  use: {
    baseURL: config.baseURL,
    channel: config.channel,
    // Local's certificate is self-signed.
    ignoreHTTPSErrors: true,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
});
