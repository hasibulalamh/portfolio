const { test, expect } = require('@playwright/test');
const { gotoPublic, loginAdmin, waitForAdminPage, waitForOverlay } = require('./helpers');

const adminUrl = process.env.ADMIN_URL || 'http://127.0.0.1:3001';
const publicUrl = process.env.PUBLIC_URL || 'http://127.0.0.1:3000';
const adminEmail = process.env.ADMIN_EMAIL;
const adminPassword = process.env.ADMIN_PASSWORD;

test.describe('hero edit propagation', () => {
  test.skip(!adminEmail || !adminPassword, 'Set ADMIN_EMAIL and ADMIN_PASSWORD.');

  test('admin edits hero heading and it renders on the public site', async ({
    page,
  }) => {
    // This test needs up to 90s because it waits for the 60s stale-while-revalidate
    test.setTimeout(120_000);


    // ── Login to admin ──
    await loginAdmin(page, adminUrl, adminEmail, adminPassword);

    // ── Navigate to Hero page and save the original value ──
    await page.goto(`${adminUrl}/admin/hero`, {
      waitUntil: 'domcontentloaded',
    });
    await waitForAdminPage(page);

    const headingInput = page
      .locator(
        'input[name="heading"], input[id*="heading"], input[placeholder*="heading" i]',
      )
      .first();
    await expect(headingInput).toBeVisible({ timeout: 15000 });

    const originalValue = await headingInput.inputValue();

    // ── Set test value ──
    const testValue = `E2E Hero ${Date.now()}`;
    await headingInput.fill(testValue);

    const saveButton = page.getByRole('button', {
      name: /save|update/i,
    });
    await saveButton.click();
    await page.waitForTimeout(2000);

    // ── Verify it renders on the public site ──
    // The public frontend uses stale-while-revalidate (REVALIDATE_SECONDS=60).
    // Strategy: wait 65s, then reload TWICE. The first reload triggers background
    // revalidation (still serves stale), the second reload gets fresh data.
    await page.waitForTimeout(65000);
    // First reload: triggers revalidation in background, may still serve stale
    await page.goto(publicUrl, { waitUntil: 'domcontentloaded' });
    await waitForOverlay(page);
    await page.waitForTimeout(2000);
    // Second reload: revalidation should have completed by now
    await page.goto(publicUrl, { waitUntil: 'domcontentloaded' });
    await waitForOverlay(page);
    await expect(page.getByText(testValue).first()).toBeVisible({
      timeout: 10000,
    });

    // ── Restore original value ──
    await page.goto(`${adminUrl}/admin/hero`, {
      waitUntil: 'domcontentloaded',
    });
    await page.waitForLoadState('networkidle').catch(() => {});
    // Wait for the layout auth check to complete
    await page
      .locator('text=Loading...')
      .waitFor({ state: 'detached', timeout: 15000 })
      .catch(() => {});

    const headingInputRestore = page
      .locator(
        'input[name="heading"], input[id*="heading"], input[placeholder*="heading" i]',
      )
      .first();
    await expect(headingInputRestore).toBeVisible({ timeout: 15000 });
    await headingInputRestore.fill(originalValue);

    const saveButtonRestore = page.getByRole('button', {
      name: /save|update/i,
    });
    await saveButtonRestore.click();
  });
});
