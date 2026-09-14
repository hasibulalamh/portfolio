const { test, expect } = require('@playwright/test');
const {
  waitForOverlay,
  loginAdmin,
  resetAdminSnapshotData,
  loadHomepageForSnapshot,
} = require('./helpers');

const adminUrl = process.env.ADMIN_URL || 'http://127.0.0.1:3001';
const adminEmail = process.env.ADMIN_EMAIL;
const adminPassword = process.env.ADMIN_PASSWORD;

/**
 * Visual snapshot tests for key pages.
 *
 * These use Playwright's toHaveScreenshot for pixel-level comparison against
 * committed baseline PNGs. The snapshots directory is `tests/e2e/` + the spec
 * file name + `-snapshots/`.
 *
 * Update baselines with: npx playwright test --update-snapshots
 * Run comparison with: npx playwright test tests/e2e/visual-snapshots.spec.js
 *
 * Scope: key pages only (not every component) to keep the suite fast and
 * maintainable. Threshold is 1% pixel ratio for full-page screenshots.
 */

test.describe('visual regression snapshots', () => {
  test('public homepage', async ({ page }) => {
    // The homepage's fullPage height is a function of how many sections
    // render, which the section-visibility test mutates mid-suite. Make the
    // capture independent of leftover state: force every section visible,
    // wait until Next's ISR cache actually serves that all-sections page, and
    // settle below-fold lazy images / entrance animations before the shot.
    //
    // Waiting out a stale ISR render can take the full 60s revalidate window,
    // so this test needs more headroom than the global 60s test timeout.
    test.setTimeout(150000);
    await loadHomepageForSnapshot(page);

    await expect(page).toHaveScreenshot('homepage.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.01,
    });
  });

  test('public homepage - hero section viewport', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await waitForOverlay(page);
    await page.waitForTimeout(1000);

    // Screenshot just the viewport (hero is the first thing visible)
    await expect(page).toHaveScreenshot('hero-viewport.png', {
      fullPage: false,
      maxDiffPixelRatio: 0.01,
    });
  });

  test('admin login page', async ({ page }) => {
    await page.goto(`${adminUrl}/login`, {
      waitUntil: 'domcontentloaded',
    });
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveScreenshot('admin-login.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.02,
    });
  });
});

test.describe('admin panel snapshots', () => {
  test.skip(
    !adminEmail || !adminPassword,
    'Admin credentials are required.',
  );

  // The screenshots below must compare UI chrome, not accumulated test data:
  // journey runs add contact messages / meeting requests, and other tests
  // leave content in the hero/settings singletons. Reset that state first so
  // every run captures the same (empty inbox, blanked singletons) pixels.
  test.beforeEach(async ({ page }) => {
    await resetAdminSnapshotData(page);
  });

  test('admin hero form', async ({ page }) => {
    await loginAdmin(page, adminUrl, adminEmail, adminPassword);

    await page.goto(`${adminUrl}/admin/hero`, {
      waitUntil: 'domcontentloaded',
    });
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    await expect(page).toHaveScreenshot('admin-hero-form.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.05,
    });
  });

  test('admin messages inbox', async ({ page }) => {
    await loginAdmin(page, adminUrl, adminEmail, adminPassword);

    await page.goto(`${adminUrl}/admin/messages`, {
      waitUntil: 'domcontentloaded',
    });
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    await expect(page).toHaveScreenshot('admin-messages-inbox.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.05,
    });
  });

  test('admin settings page', async ({ page }) => {
    await loginAdmin(page, adminUrl, adminEmail, adminPassword);

    await page.goto(`${adminUrl}/admin/settings`, {
      waitUntil: 'domcontentloaded',
    });
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    await expect(page).toHaveScreenshot('admin-settings.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.05,
    });
  });
});
