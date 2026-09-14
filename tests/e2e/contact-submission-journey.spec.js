const { test, expect } = require('@playwright/test');
const { gotoPublic, settlePublicPage, loginAdmin, waitForAdminPage } = require('./helpers');

const adminUrl = process.env.ADMIN_URL || 'http://127.0.0.1:3001';
const adminEmail = process.env.ADMIN_EMAIL;
const adminPassword = process.env.ADMIN_PASSWORD;

test.describe('contact submission → admin reply journey', () => {
  test.skip(!adminEmail || !adminPassword, 'Set ADMIN_EMAIL and ADMIN_PASSWORD.');

  test('visitor submits contact form, admin sees it and replies', async ({
    page,
    browser,
  }) => {
    const timestamp = Date.now();
    const visitorEmail = `e2e-contact-${timestamp}@example.test`;
    const messageBody = `E2E contact journey ${timestamp}`;

    // ── Visitor submits the contact form ──
    await gotoPublic(page);
    // Settle lazy images + Reveal animations before scrolling and filling
    // (see helpers.settlePublicPage — same cold-page flake as the journeys).
    await settlePublicPage(page);

    await page.evaluate(() => {
      const contact =
        document.querySelector('#contact') ||
        document.querySelector('[id*="contact"]');
      if (contact) contact.scrollIntoView({ behavior: 'instant' });
    });
    await page.waitForTimeout(500);

    const nameInput = page
      .locator('input[placeholder*="name" i], input[name="name"]')
      .first();
    const emailInput = page
      .locator(
        'input[type="email"], input[placeholder*="email" i], input[name="email"]',
      )
      .first();
    const messageInput = page
      .locator('textarea, input[placeholder*="message" i], textarea[name="message"]')
      .first();

    await nameInput.fill('E2E Contact Visitor');
    await emailInput.fill(visitorEmail);
    await messageInput.fill(messageBody);

    const sendButton = page.locator('button[type="submit"]').first();
    await sendButton.click();

    // ── Visitor sees confirmation ──
    // Match the API's exact success copy — the bare /sent/ alternative used
    // to match "Present" in the timeline badges and pass vacuously.
    await expect(
      page.getByText(/your message has been sent/i).first(),
    ).toBeVisible({ timeout: 10000 });

    // ── Admin logs in and sees the message ──
    const admin = await browser.newPage({ baseURL: adminUrl });
    await loginAdmin(admin, adminUrl, adminEmail, adminPassword);

    await admin.goto('/admin/messages');
    await waitForAdminPage(admin);

    // The message should be visible in the inbox
    await expect(admin.getByText(messageBody)).toBeVisible({ timeout: 15000 });

    // ── Admin replies ──
    // Click on the message to open it
    await admin.getByText(messageBody).click();
    await admin.waitForTimeout(500);

    // Find and fill the reply textarea
    const replyInput = admin
      .locator('textarea[placeholder*="reply" i], textarea')
      .last();
    await replyInput.fill('E2E admin reply to your message.');

    // Click send reply button
    const replyButton = admin
      .getByRole('button', { name: /send|reply/i })
      .last();
    await replyButton.click();
    await admin.waitForTimeout(2000);

    await admin.close();
  });
});
