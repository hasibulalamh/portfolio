const { test, expect } = require('@playwright/test');
const { loginAdmin } = require('./helpers');

const adminUrl = process.env.ADMIN_URL || 'http://127.0.0.1:3001';

/*
 * Regression tests for the "dropdown is visible but unclickable" bug.
 *
 * The Header's user menu used to render visually on top of the page while
 * losing every real hit-test to the page content behind it: the header
 * (backdrop-filter → own stacking context) trapped the menu's z-index, so the
 * menu's z-50 only ranked inside the header's context while the dashboard
 * content — painted later at the same (auto) level — won the compositor's
 * hit-testing. DevTools hit-tested the dashboard paragraph; clicks fell
 * through. jsdom-based component tests cannot see any of this (no layout or
 * paint engine), which is why the previous fix attempt passed its suite
 * without fixing the bug.
 *
 * These tests run against real Chromium with real input: page.mouse.click
 * dispatches CDP input events that go through the browser's own hit-testing —
 * NOT locator.click()'s element.scrollIntoView + synthetic event path, and
 * certainly not jsdom's fireEvent.
 *
 * Login uses helpers.loginAdmin, which falls back to the seeder credentials
 * when ADMIN_EMAIL/ADMIN_PASSWORD are unset, so this guard runs wherever the
 * rest of the admin journeys run (a reachable backend is a suite-wide
 * precondition anyway).
 */

async function openDashboardWithMenu(page) {
  await loginAdmin(page, adminUrl, process.env.ADMIN_EMAIL, process.env.ADMIN_PASSWORD);

  const trigger = page.getByRole('button', { name: /open account menu/i });
  await trigger.click();
  await expect(page.getByRole('menu')).toBeVisible();
}

test.describe('admin header user dropdown — real hit-testing', () => {
  // loginAdmin's two gotos + auth round-trip through a remote DB can be slow.
  test.setTimeout(90_000);

  test('a real mouse click on the visible Logout item fires POST /logout', async ({ page }) => {
    await openDashboardWithMenu(page);

    const menu = page.getByRole('menu');
    const logoutItem = menu.getByRole('menuitem', { name: /logout/i });

    // Real screen coordinates of the visually-rendered Logout button.
    const box = await logoutItem.boundingBox();
    expect(box).not.toBeNull();
    const cx = box.x + box.width / 2;
    const cy = box.y + box.height / 2;

    // GUARD — the exact original bug, caught before any click: what does the
    // browser's hit-testing resolve at the Logout button's centre?
    // elementsFromPoint implements the same paint-order + stacking-context
    // comparison the compositor uses for real pointer input, so this mirrors
    // the element DevTools highlights under the cursor. Before the fix this
    // resolved to the dashboard paragraph BEHIND the dropdown.
    const hit = await page.evaluate(({ x, y }) => {
      const menuEl = document.querySelector('[role="menu"]');
      const chain = document
        .elementsFromPoint(x, y)
        .filter((el) => getComputedStyle(el).pointerEvents !== 'none');
      const describe = (el) =>
        el.tagName.toLowerCase() +
        (el.getAttribute('role') ? `[role=${el.getAttribute('role')}]` : '') +
        (typeof el.className === 'string' && el.className
          ? `.${el.className.trim().split(/\s+/).join('.')}`
          : '');
      return {
        top: chain.length ? describe(chain[0]) : 'none',
        topInsideMenu:
          chain.length > 0 && !!menuEl && menuEl.contains(chain[0]),
      };
    }, { x: cx, y: cy });

    expect(
      hit.topInsideMenu,
      `Topmost hit-test target at the Logout button's screen position was "${hit.top}" — the dropdown is visible but not receiving pointer events (stacking-context regression).`,
    ).toBe(true);

    // THE regression assertion: a REAL mouse click at those coordinates. If
    // the dropdown ever loses hit-testing again, this click lands on the page
    // content behind it, the menu item's handler never runs, and no request
    // is sent — waitForRequest fails, exactly reproducing the user's bug.
    const logoutPost = page.waitForRequest(
      (req) =>
        req.method() === 'POST' &&
        new URL(req.url()).pathname.endsWith('/logout'),
      { timeout: 15_000 },
    );
    await page.mouse.click(cx, cy);
    await logoutPost;
  });

  test('a real click on the page content behind the open menu closes it', async ({ page }) => {
    await openDashboardWithMenu(page);

    // The outside-click-to-close behaviour must also work with real input,
    // and a real click on the dashboard heading doubles as a check that the
    // page content around the menu is itself hit-testable.
    const menu = page.getByRole('menu');
    const heading = page.getByRole('heading', { name: 'Dashboard', exact: true });
    const hb = await heading.boundingBox();
    expect(hb).not.toBeNull();

    await page.mouse.click(hb.x + hb.width / 2, hb.y + hb.height / 2);
    await expect(menu).toBeHidden();
  });
});
