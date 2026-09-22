const { test, expect } = require('@playwright/test');
const { loginAdmin, waitForAdminPage } = require('./helpers');

const adminUrl = process.env.ADMIN_URL || 'http://127.0.0.1:3001';
const publicUrl = process.env.PUBLIC_URL || 'http://127.0.0.1:3000';
const adminEmail = process.env.ADMIN_EMAIL;
const adminPassword = process.env.ADMIN_PASSWORD;

/*
 * The backend here runs against a remote managed MySQL, so every API call
 * takes seconds and `php artisan serve` (single-threaded PHP) serves the
 * dashboard's six concurrent fetches serially — the conversions response can
 * land 20s+ after page load. Every wait in this spec is sized for that.
 */

// The Conversions card. Card-level scoping instead of heading.locator('..'):
// the heading sits in a header row whose parent div does not contain the
// card's body.
const conversionsCardFor = (page) =>
  page.locator('[data-testid="conversions-card"]');

const waitForConversionsData = async (card) => {
  // Data rows (or the error state) replace the loading skeleton once the
  // fetch resolves.
  await card
    .locator('[data-testid="conversion-row"]')
    .first()
    .waitFor({ state: 'visible', timeout: 60_000 });
  await card.getByText('total clicks').waitFor({
    state: 'visible',
    timeout: 10_000,
  });
};

const readTotal = async (card) =>
  Number(
    (await card.locator('p.text-3xl.font-bold').textContent())?.trim() || '0',
  );

test.describe('click tracking → conversions card journey', () => {
  test.skip(
    !adminEmail || !adminPassword,
    'Admin credentials are required.',
  );

  test('the Conversions card renders aggregate bars for an admin', async ({
    page,
  }) => {
    await loginAdmin(page, adminUrl, adminEmail, adminPassword);

    const card = conversionsCardFor(page);

    await expect(
      page.getByRole('heading', { name: 'Conversions' }),
    ).toBeVisible({ timeout: 30_000 });

    await waitForConversionsData(card);

    // Seven rows: one per event type in the backend's allow-list, zero-baseline
    // included — never a bare subset.
    const rows = page.locator('[data-testid="conversion-row"]');
    await expect(rows).toHaveCount(7);

    // Bar widths must be non-increasing down the list (strongest first).
    const widths = [];
    for (const row of await rows.all()) {
      const width = await row
        .locator('[data-testid="conversion-bar"]')
        .evaluate((el) => parseFloat(el.style.width));
      widths.push(width);
    }
    for (let i = 1; i < widths.length; i++) {
      expect(widths[i]).toBeLessThanOrEqual(widths[i - 1]);
    }

    // Rows with clicks show their share of the total; zero rows show none.
    // The count lives in the first text node of the value span — textContent
    // alone would also swallow the nested "% of total" span.
    for (const row of await rows.all()) {
      const count = Number(
        await row
          .locator('span.font-medium')
          .first()
          .evaluate((el) => el.firstChild?.textContent?.trim() ?? '0'),
      );
      if (count > 0) {
        await expect(row.getByText(/% of total/)).toBeVisible();
      } else {
        await expect(row.getByText(/% of total/)).toHaveCount(0);
      }
    }
  });

  test('a real CTA click lands in the dashboard Conversions card', async ({
    page,
  }) => {
    await loginAdmin(page, adminUrl, adminEmail, adminPassword);

    const card = conversionsCardFor(page);
    await expect(
      page.getByRole('heading', { name: 'Conversions' }),
    ).toBeVisible({ timeout: 30_000 });
    await waitForConversionsData(card);

    const totalBefore = await readTotal(card);

    // ── Visitor clicks a tracked CTA on the public site ──
    await page.goto(publicUrl, { waitUntil: 'domcontentloaded' });

    // The navbar "Hire Me" button is the most stable tracked CTA: it exists
    // whenever the contact section is enabled and does not navigate away.
    const hireMe = page.locator('a:has-text("Hire Me")').first();
    await hireMe.waitFor({ state: 'visible', timeout: 30_000 });
    await hireMe.click();

    // The public track POST is fire-and-forget (keepalive); give it a beat to
    // leave the browser before moving on.
    await page.waitForTimeout(3000);

    // ── Admin reloads and sees the count rise by exactly one ──
    await page.goto(`${adminUrl}/admin/dashboard`, {
      waitUntil: 'domcontentloaded',
      timeout: 30_000,
    });
    await waitForAdminPage(page);
    await expect(
      page.getByRole('heading', { name: 'Conversions' }),
    ).toBeVisible({ timeout: 30_000 });
    await waitForConversionsData(card);

    const totalAfter = await readTotal(card);
    expect(totalAfter).toBe(totalBefore + 1);
  });
});
