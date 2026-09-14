const {
  defineConfig,
  devices,
} = require('@playwright/test');

const CI = !!process.env.CI;

module.exports = defineConfig({
  testDir: './tests/e2e',
  /*
   * Run the suite on a single worker. The tests share one backend database and
   * mutate it (hero edits, section-visibility toggles, submissions), so running
   * files in parallel lets one test's mid-flight data changes land inside
   * another test's page render — the visual snapshots repeatedly captured the
   * hero-edit test's temporary heading mid-suite. Serial execution makes the
   * suite deterministic; the longest test is the hero-edit propagation (waits
   * out the 60s ISR revalidation), so wall time stays reasonable.
   */
  workers: 1,
  globalSetup: require.resolve('./tests/e2e/global-setup.js'),
  timeout: 60_000,
  expect: { timeout: 5_000 },
  use: {
    baseURL: process.env.PUBLIC_URL || 'http://127.0.0.1:3000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],

  /*
   * Build and start the frontend + admin apps in production mode before
   * running E2E tests. Production builds pre-compile all pages so they
   * load instantly — avoiding the dev-server compilation delays that
   * caused admin page timeouts in previous runs.
   *
   * Each webServer entry:
   *   1. Builds the Next.js app (next build)
   *   2. Starts the production server (next start)
   *   3. Playwright waits for the port to be available before running tests
   *
   * `reuseExistingServer` lets you run tests against already-running
   * servers during local development (set CI=1 to force fresh builds).
   */
  webServer: [
    {
      // Kill any leftover process on port 3000, then build & start
      command:
        'fuser -k 3000/tcp 2>/dev/null || true; cd portfolio-frontend && npm run build && npm start -- -p 3000',
      port: 3000,
      reuseExistingServer: !CI,
      timeout: 180_000,
    },
    {
      // Kill any leftover process on port 3001, then build & start
      command:
        'fuser -k 3001/tcp 2>/dev/null || true; cd portfolio-admin && npm run build && npm start -- -p 3001',
      port: 3001,
      reuseExistingServer: !CI,
      timeout: 180_000,
    },
  ],
});
