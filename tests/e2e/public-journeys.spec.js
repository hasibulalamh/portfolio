const { test, expect } = require('@playwright/test');
const { gotoPublic, settlePublicPage, clickStable, loginAdmin, waitForAdminPage } = require('./helpers');

const adminUrl = process.env.ADMIN_URL || 'http://127.0.0.1:3001';
const adminEmail = process.env.ADMIN_EMAIL;
const adminPassword = process.env.ADMIN_PASSWORD;

test.describe('public submission journeys', () => {
  test.beforeEach(async ({ page }) => {
    test.skip(
      !adminEmail || !adminPassword,
      'Set ADMIN_EMAIL and ADMIN_PASSWORD for the full journey.',
    );
    await gotoPublic(page);
    // Cold browser contexts fetch lazy images + fire Reveal entrance animations
    // as the page scrolls, which shifts layout under Playwright's clicks (the
    // historical flake in this file). Settle the page before interacting.
    await settlePublicPage(page);
  });

  test('contact submission reaches the admin inbox and can be replied to', async ({
    page,
    browser,
  }) => {
    // Scroll to the contact section
    await page.evaluate(() => {
      const contact = document.querySelector('#contact') || document.querySelector('[id*="contact"]');
      if (contact) contact.scrollIntoView({ behavior: 'instant' });
    });
    await page.waitForTimeout(500);

    // Fill the contact form
    const nameInput = page.locator('input[name="name"]').first();
    const emailInput = page.locator('input[name="email"]').first();
    const messageInput = page.locator('textarea[name="message"]').first();

    // Track the unique values so the admin-side assertions can key on them
    // instead of generic copy (leftover rows with the same name used to
    // satisfy the check while the submission itself never arrived).
    const visitorEmail = `e2e-contact-${Date.now()}@example.test`;
    await nameInput.fill('E2E Contact Visitor');
    await emailInput.fill(visitorEmail);
    await messageInput.fill('E2E contact message');

    // Click send
    const sendButton = page.locator('button[type="submit"]').first();
    await sendButton.click();

    // Wait for the real API confirmation. Keep the match on the exact success
    // copy — a loose /sent/ regex matches "Present" in the timeline badges and
    // passes even when the submission never left the browser.
    await expect(
      page.getByText(/your message has been sent/i).first()
    ).toBeVisible({ timeout: 10000 });

    // Login to admin and check
    const admin = await browser.newPage({ baseURL: adminUrl });
    await loginAdmin(admin, adminUrl, adminEmail, adminPassword);
    await admin.goto('/admin/messages');
    await waitForAdminPage(admin);
    // The inbox card shows the sender email, which is unique to this run.
    await expect(admin.getByText(visitorEmail).first()).toBeVisible({ timeout: 15000 });
    await admin.close();
  });

  test('meeting submission reaches the admin inbox', async ({
    page,
    browser,
  }) => {
    // Scroll to the contact section so the meeting toggle is visible
    await page.evaluate(() => {
      const contact = document.querySelector('#contact') || document.querySelector('[id*="contact"]');
      if (contact) contact.scrollIntoView({ behavior: 'instant' });
    });
    await page.waitForTimeout(500);

    // Click the "Schedule a meeting" toggle to switch to meeting form
    const scheduleBtn = page.getByText(/schedule a meeting/i).first();
    await expect(scheduleBtn).toBeVisible({ timeout: 10000 });
    await clickStable(scheduleBtn);
    await page.waitForTimeout(500);

    // Fill name and email
    const nameInput = page.locator('input[name="name"]').first();
    const emailInput = page.locator('input[name="email"]').first();

    const visitorEmail = `e2e-meeting-${Date.now()}@example.test`;
    await nameInput.fill('E2E Meeting Visitor');
    await emailInput.fill(visitorEmail);

    // The meeting form has a required preferred_time select field
    const timeSelect = page.locator('select[name="preferred_time"]');
    await expect(timeSelect).toBeVisible({ timeout: 5000 });
    await timeSelect.selectOption('10:00');

    const messageInput = page.locator('textarea[name="message"]').first();
    await messageInput.fill('E2E meeting request');

    // Click submit
    const sendButton = page.locator('button[type="submit"]').first();
    await sendButton.click();

    // Wait for the real API confirmation. A loose /sent|submitted/ regex
    // matches "Present" in the timeline badges and passes vacuously when the
    // submission never left the browser — which is how this test used to
    // "pass" while the request silently failed.
    await expect(
      page.getByText(/meeting request has been submitted/i).first()
    ).toBeVisible({ timeout: 15000 });

    // Login to admin and check
    const admin = await browser.newPage({ baseURL: adminUrl });
    await loginAdmin(admin, adminUrl, adminEmail, adminPassword);
    await admin.goto('/admin/meeting-requests');
    await waitForAdminPage(admin);
    // The request card shows the requester email, unique to this run — not the
    // generic name, which leftover rows from earlier runs used to satisfy.
    await expect(admin.getByText(visitorEmail).first()).toBeVisible({ timeout: 15000 });
    await admin.close();
  });
});
