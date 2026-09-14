const { test, expect } = require('@playwright/test');
const { loadHomepageForSnapshot } = require('./helpers');

test.describe('CMS visual snapshots', () => {
  test('public homepage', async ({ page }) => {
    // Same contract as visual-snapshots.spec.js: force every section visible,
    // wait for the ISR cache to serve that all-sections page, and settle
    // below-fold lazy images / entrance animations — so the fullPage height
    // never depends on which suite tests ran before this one.
    // Waiting out a stale ISR render can take the full 60s revalidate window,
    // so this test needs more headroom than the global 60s test timeout.
    test.setTimeout(150000);
    await loadHomepageForSnapshot(page);

    await expect(page).toHaveScreenshot('homepage.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.01,
    });
  });

  test('admin hero form', async ({ page }) => {
    test.skip(
      !process.env.ADMIN_EMAIL || !process.env.ADMIN_PASSWORD,
      'Admin credentials are required.',
    );
    const { loginAdmin } = require('./helpers');
    const adminUrl = process.env.ADMIN_URL || 'http://127.0.0.1:3001';
    await loginAdmin(page, adminUrl, process.env.ADMIN_EMAIL, process.env.ADMIN_PASSWORD);
    await page.goto(`${adminUrl}/admin/hero`, { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('domcontentloaded');
    await expect(page).toHaveScreenshot('admin-hero.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.05,
    });
  });
});
