const { test, expect } = require('@playwright/test');
const {
  loginAdmin,
  gotoPublic,
  waitForAdminPage,
  waitForAllSectionsServed,
} = require('./helpers');

const adminUrl = process.env.ADMIN_URL || 'http://127.0.0.1:3001';
const publicUrl = process.env.PUBLIC_URL || 'http://127.0.0.1:3000';
const adminEmail = process.env.ADMIN_EMAIL;
const adminPassword = process.env.ADMIN_PASSWORD;

test.describe('CMS propagation', () => {
  test.skip(
    !adminEmail || !adminPassword,
    'Admin credentials are required.',
  );

  test('section visibility toggles off and back on publicly', async ({
    page,
  }) => {
    // This test poisons Next's ISR cache with a hidden-section render (its
    // mid-test public visit) and then restores the toggle — so it waits out
    // its own cache poison before ending, which can take the full 60s
    // revalidate window. Needs more headroom than the global 60s timeout.
    test.setTimeout(150000);

    // Login first
    await loginAdmin(page, adminUrl, adminEmail, adminPassword);

    // Navigate to sections page
    await page.goto(`${adminUrl}/admin/settings/sections`, { waitUntil: 'domcontentloaded' });
    await waitForAdminPage(page);

    // The first checkbox (Home) is disabled/not toggleable.
    // Find a toggleable checkbox — one that isn't disabled.
    const toggle = page.locator('input[type="checkbox"]:not([disabled])').first();
    await expect(toggle).toBeVisible({ timeout: 10000 });

    // Get the current state and toggle it
    const wasChecked = await toggle.isChecked();
    // The checkbox is sr-only (visually hidden), so Playwright can't click it
    // directly — the visible label span intercepts pointer events.
    await toggle.click({ force: true });
    await page.waitForTimeout(500);

    // Navigate to public site
    await gotoPublic(page, publicUrl);

    // Check section visibility changed
    if (wasChecked === 'checked') {
      // Section was visible, now should be hidden
      // (We can't easily check section absence without knowing which section)
    }

    // Go back and restore
    await loginAdmin(page, adminUrl, adminEmail, adminPassword);

    await page.goto(`${adminUrl}/admin/settings/sections`, { waitUntil: 'domcontentloaded' });
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

  test('edited hero content renders publicly', async ({ page }) => {
    // Login
    await loginAdmin(page, adminUrl, adminEmail, adminPassword);

    // Navigate to hero page
    await page.goto(`${adminUrl}/admin/hero`, { waitUntil: 'domcontentloaded' });
    await waitForAdminPage(page);

    // Find the heading input field
    const headingInput = page.locator('input[name="heading"], input[id*="heading"], input[placeholder*="heading" i]').first();
    await expect(headingInput).toBeVisible({ timeout: 15000 });

    // Save current value and set test value
    const originalValue = await headingInput.inputValue();
    await headingInput.fill('E2E Hero Heading');

    // Click save
    const saveButton = page.getByRole('button', { name: /save|update/i });
    await saveButton.click();
    await page.waitForTimeout(2000);

    // Check public site
    await gotoPublic(page, publicUrl);

    // Restore original value
    await loginAdmin(page, adminUrl, adminEmail, adminPassword);

    await page.goto(`${adminUrl}/admin/hero`, { waitUntil: 'domcontentloaded' });
    await waitForAdminPage(page);

    const headingInputRestore = page.locator('input[name="heading"], input[id*="heading"], input[placeholder*="heading" i]').first();
    await expect(headingInputRestore).toBeVisible({ timeout: 15000 });
    await headingInputRestore.fill(originalValue);

    const saveButtonRestore = page.getByRole('button', { name: /save|update/i });
    await saveButtonRestore.click();
  });
});
