/**
 * Playwright globalSetup — runs once before all tests.
 *
 * Kills any leftover `next dev` processes on ports 3000/3001 so that
 * Playwright's `webServer.reuseExistingServer` never picks up a dev
 * server instead of building and starting the production server.
 *
 * NOTE: Playwright launches webServer processes BEFORE running
 * globalSetup, so we must NOT kill anything on ports 3000/3001 here —
 * that would kill the webServer processes we want. Instead, we only
 * clean up stale lock files and environment state.
 */
module.exports = async function globalSetup() {
  console.log('[globalSetup] E2E environment ready.');
  // No port cleanup — webServer entries handle their own lifecycle.
  // Port cleanup was moved into the webServer commands themselves.
};
