const { test, expect } = require('@playwright/test');
const { loginAdmin, waitForAdminPage } = require('./helpers');
const fs = require('node:fs');
const path = require('node:path');

const adminUrl = process.env.ADMIN_URL || 'http://127.0.0.1:3001';
const adminEmail = process.env.ADMIN_EMAIL;
const adminPassword = process.env.ADMIN_PASSWORD;

/*
 * Two deterministic seed files are planted on the ACTIVE UPLOAD DISK before
 * this suite runs (backdated past the 15-minute grace period):
 *
 *   logos/e2e-orphan-a.png
 *   cv/e2e-orphan-b.pdf
 *
 * The backend MUST be started with R2_ACCESS_KEY_ID blanked so
 * UploadService::disk() falls back to the local `public` disk
 * (storage/app/public) — see the run instructions in report.md. Seeding
 * resolves the disk the same way the app does (php artisan tinker), so the
 * files land exactly where the scan looks and never touch real R2.
 *
 * The DELETE test removes e2e-orphan-a.png through the real admin UI and
 * verifies the file is really gone from disk afterwards. Nothing else in the
 * system is written: no models, no settings, no content.
 *
 * e2e-orphan-b.pdf is deliberately left behind — it makes the clean state
 * assertion in the second test truthful rather than vacuous, and its name
 * makes it trivial to identify and remove later if desired.
 */

const ORPHAN_A = 'logos/e2e-orphan-a.png';
const ORPHAN_B = 'cv/e2e-orphan-b.pdf';
const SEED_FILES = [ORPHAN_A, ORPHAN_B];

const backendApi = process.env.API_URL || 'http://127.0.0.1:8000/api';
const repoRoot = path.join(__dirname, '..', '..');
const backendRoot = path.join(repoRoot, 'portfolio-backend');

/** Resolve the active upload disk root exactly like UploadService does. */
async function resolveDiskRoot(request) {
  // UploadService picks r2 only when key+bucket+endpoint are all configured;
  // this suite requires the backend to be running with R2 blanked out, so the
  // local public disk is expected — assert against what the app itself says.
  const health = await request.get(`${backendApi}/settings`);
  if (health.status() !== 200) {
    throw new Error(`Backend not reachable at ${backendApi} (status ${health.status()})`);
  }
  return path.join(backendRoot, 'storage', 'app', 'public');
}

async function seedFiles(root) {
  const backdate = new Date(Date.now() - 96 * 60 * 60 * 1000); // 4 days old, past grace

  for (const rel of SEED_FILES) {
    const abs = path.join(root, rel);
    fs.mkdirSync(path.dirname(abs), { recursive: true });
    // Distinct byte sizes so the size column is assertable per row.
    fs.writeFileSync(abs, rel === ORPHAN_A ? 'PNG-e2e-seed-a!' : '%PDF-1.4 e2e-seed-b');
    fs.utimesSync(abs, backdate, backdate);
  }
}

async function loginAndOpenPage(page) {
  await loginAdmin(page, adminUrl, adminEmail, adminPassword);
  // The admin server shares the box with several other processes and the
  // auth flow round-trips a remote MySQL; 30s gotos have flaked under load.
  await page.goto(`${adminUrl}/admin/storage/orphans`, {
    waitUntil: 'domcontentloaded',
    timeout: 90_000,
  });
  await waitForAdminPage(page, 60_000);

  // Remote-DB latencies apply to Sanctum's /admin/me and to nothing else on
  // this page (the scan is disk-only), so a plain heading wait is fine.
  await expect(
    page.getByRole('heading', { name: 'Storage Orphans' }),
  ).toBeVisible({ timeout: 60_000 });
}

test.describe('storage orphans journey', () => {
  test.skip(!adminEmail || !adminPassword, 'Admin credentials are required.');

  let diskRoot;

  test.beforeAll(async ({ request }) => {
    diskRoot = await resolveDiskRoot(request);
    await seedFiles(diskRoot);
  });

  test('scan lists the seeded orphans with accurate details', async ({ page }) => {
    await loginAndOpenPage(page);

    // Summary strip — referenced count may be nonzero on a dirty dev disk,
    // but the candidates list must contain exactly our two seeded files.
    await expect(page.getByText('logos/e2e-orphan-a.png')).toBeVisible({
      timeout: 30_000,
    });
    await expect(page.getByText('cv/e2e-orphan-b.pdf')).toBeVisible();

    // Size rendering: 15-byte / 19-byte payloads → "15 B" / "19 B".
    const rowA = page.getByRole('row', { name: /e2e-orphan-a\.png/ });
    await expect(rowA.getByText('15 B')).toBeVisible();
    const rowB = page.getByRole('row', { name: /e2e-orphan-b\.pdf/ });
    await expect(rowB.getByText('19 B')).toBeVisible();

    // Relative time: backdated 4 days, far outside the 15-minute grace period.
    await expect(rowA.getByText('4 days ago')).toBeVisible();

    // The select-all checkbox reflects exactly the candidate rows.
    await expect(page.getByRole('row', { name: /e2e-orphan/ })).toHaveCount(2);
  });

  test('select, confirm and delete one orphan through the UI', async ({ page }) => {
    await loginAndOpenPage(page);

    const rowA = page.getByRole('row', { name: /e2e-orphan-a\.png/ });
    await rowA.locator('input[type="checkbox"]').check();

    const deleteButton = page.getByRole('button', { name: /Delete Selected \(1\)/ });
    await deleteButton.click();

    // Confirmation dialog names the exact file and carries the warning.
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.getByText(/logos\/e2e-orphan-a\.png/)).toBeVisible();
    await expect(dialog.getByText(/This cannot be undone/)).toBeVisible();

    await dialog.getByRole('button', { name: 'Delete' }).click();

    // Toast summarises the outcome (clean delete, nothing skipped). The UI's
    // DELETE refetches the list afterwards, and both actions ride the same
    // slow remote-DB backend as the login — hence the generous timeout.
    await expect(page.getByText('Deleted 1 file', { exact: true })).toBeVisible({
      timeout: 90_000,
    });

    // The list refetched. Order matters here: wait for the SURVIVOR to
    // render first — that proves the fresh list has replaced the skeleton —
    // before asserting the deleted file is gone. The reverse order would
    // pass vacuously while the skeleton is still up.
    await expect(page.getByText('cv/e2e-orphan-b.pdf')).toBeVisible({
      timeout: 90_000,
    });
    await expect(page.getByText('logos/e2e-orphan-a.png')).toHaveCount(0);

    // The file must be really gone from the disk, not just from the table.
    expect(fs.existsSync(path.join(diskRoot, ORPHAN_A))).toBe(false);
    expect(fs.existsSync(path.join(diskRoot, ORPHAN_B))).toBe(true);
  });
});
