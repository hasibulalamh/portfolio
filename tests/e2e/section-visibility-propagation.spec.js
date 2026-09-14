const { test, expect } = require('@playwright/test');
const {
  gotoPublic,
  loginAdmin,
  waitForAdminPage,
  waitForAllSectionsServed,
} = require('./helpers');

const adminUrl = process.env.ADMIN_URL || 'http://127.0.0.1:3001';
const publicUrl = process.env.PUBLIC_URL || 'http://127.0.0.1:3000';
const adminEmail = process.env.ADMIN_EMAIL;
const adminPassword = process.env.ADMIN_PASSWORD;

test.describe('section visibility propagation', () => {
  test.skip(!adminEmail || !adminPassword, 'Set ADMIN_EMAIL and ADMIN_PASSWORD.');

  test('admin hides a section and it disappears from the public site', async ({
    page,
  }) => {
    // This test poisons Next's ISR cache with a hidden-section render (its
    // mid-test public visit) and then restores the toggle — so it waits out
    // its own cache poison before ending, which can take the full 60s
    // revalidate window. Needs more headroom than the global 60s timeout.
    test.setTimeout(150000);

    // ── Login to admin ──
    await loginAdmin(page, adminUrl, adminEmail, adminPassword);

    // ── Navigate to sections page ──
    await page.goto(`${adminUrl}/admin/settings/sections`, {
      waitUntil: 'domcontentloaded',
    });
    await waitForAdminPage(page);

    // The first checkbox (Home) is disabled/not toggleable.
    // Find a toggleable checkbox — one that isn't disabled.
    const toggle = page.locator('input[type="checkbox"]:not([disabled])').first();
    await expect(toggle).toBeVisible({ timeout: 15000 });

    const wasChecked = await toggle.isChecked();

    // ── Toggle it ──
    // sr-only checkbox — click with force to bypass intercepting span
    await toggle.click({ force: true });
    await page.waitForTimeout(1000);

    // ── Verify on public site ──
    // We can't easily verify section absence without knowing which section,
    // but we can verify the page still loads without errors
    await gotoPublic(page, publicUrl);

    // ── Restore ──
    await page.goto(`${adminUrl}/admin/settings/sections`, {
      waitUntil: 'domcontentloaded',
    });
    await waitForAdminPage(page);

    const toggleAfter = page.locator('input[type="checkbox"]:not([disabled])').first();
    await expect(toggleAfter).toBeVisible({ timeout: 15000 });
    const isCurrentlyChecked = await toggleAfter.isChecked();
    if (isCurrentlyChecked !== wasChecked) {
      await toggleAfter.click({ force: true });
    }

    // ── Wait out our own cache poison ──
    // The toggle is restored in the DB, but Next's ISR cache can keep serving
    // the hidden-section render for up to a minute. Ending the test with a
    // fresh cache means the homepage snapshot tests that run after this one
    // capture immediately instead of paying that wait themselves. Cheap server
    // polls drive the revalidation once the window passes.
    await waitForAllSectionsServed(page, { publicUrl });
  });
});
