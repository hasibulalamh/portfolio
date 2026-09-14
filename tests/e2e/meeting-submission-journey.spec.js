const { test, expect } = require('@playwright/test');
const { gotoPublic, settlePublicPage, clickStable, loginAdmin, waitForAdminPage } = require('./helpers');

const adminUrl = process.env.ADMIN_URL || 'http://127.0.0.1:3001';
const adminEmail = process.env.ADMIN_EMAIL;
const adminPassword = process.env.ADMIN_PASSWORD;

test.describe('meeting submission → admin inbox journey', () => {
  test.skip(!adminEmail || !adminPassword, 'Set ADMIN_EMAIL and ADMIN_PASSWORD.');

  test('visitor submits meeting request, admin sees it', async ({
    page,
    browser,
  }) => {
    const timestamp = Date.now();
    const visitorEmail = `e2e-meeting-${timestamp}@example.test`;
    const messageBody = `E2E meeting journey ${timestamp}`;

    // ── Visitor submits the meeting form ──
    await gotoPublic(page);
    // Settle lazy images + Reveal animations before scrolling and clicking
    // (see helpers.settlePublicPage — this flow has been flaky on cold pages).
    await settlePublicPage(page);

    // Scroll to the contact section so the meeting toggle is visible
    await page.evaluate(() => {
      const contact = document.querySelector('#contact') || document.querySelector('[id*="contact"]');
      if (contact) contact.scrollIntoView({ behavior: 'instant' });
    });
    await page.waitForTimeout(500);

    // Click the "Schedule a meeting" toggle to switch to meeting form
    const scheduleBtn = page
      .getByText(/schedule a meeting/i)
      .first();
    await expect(scheduleBtn).toBeVisible({ timeout: 10000 });
    await clickStable(scheduleBtn);
    await page.waitForTimeout(500);

    // Fill the meeting form fields
    const nameInput = page.locator('input[name="name"]').first();
    const emailInput = page.locator('input[name="email"]').first();

    await nameInput.fill('E2E Meeting Visitor');
    await emailInput.fill(visitorEmail);

    // The meeting form has a required preferred_time select field
    const timeSelect = page.locator('select[name="preferred_time"]');
    await expect(timeSelect).toBeVisible({ timeout: 5000 });
    await timeSelect.selectOption('10:00');

    const messageInput = page.locator('textarea[name="message"]').first();
    await messageInput.fill(messageBody);

    const sendButton = page.locator('button[type="submit"]').first();
    await sendButton.click();

    // ── Visitor sees confirmation ──
    // Match the API's exact success copy. The old loose /sent|submitted/
    // regex matched "Present" in the timeline badges, so it passed even when
    // the form submission never left the browser.
    await expect(
      page.getByText(/meeting request has been submitted/i).first(),
    ).toBeVisible({ timeout: 15000 });

    // ── Admin sees it in the meeting requests inbox ──
    const admin = await browser.newPage({ baseURL: adminUrl });
    await loginAdmin(admin, adminUrl, adminEmail, adminPassword);

    await admin.goto('/admin/meeting-requests');
    await waitForAdminPage(admin);

    // The meeting requests card shows name and email, not the message body.
    // Key on the unique email, not the generic name — leftover rows from
    // earlier runs named "E2E Meeting Visitor" used to satisfy the old check
    // while this run's request never arrived.
    await expect(admin.getByText(visitorEmail).first()).toBeVisible({
      timeout: 15000,
    });

    await admin.close();
  });
});
