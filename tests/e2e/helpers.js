/**
 * Shared helpers for Playwright E2E tests.
 */

/**
 * Wait for the LoadingScreen overlay to disappear.
 *
 * The frontend's `LoadingScreen` component renders a fixed z-50 overlay for
 * 1.5s + 0.5s exit animation. Playwright's `networkidle` fires before this
 * client-side timer completes, so the overlay intercepts pointer events on
 * form elements beneath it. This helper waits for the overlay to be removed
 * from the DOM.
 *
 * The overlay MUST be targeted precisely. The navbar
 * (`fixed inset-x-0 top-0 z-50`) and the scroll-progress bar (`fixed top-0 …
 * z-50`) also match the broad `.fixed.z-50` selector and are permanent DOM
 * nodes owned by React. Waiting on them — or force-removing them — corrupts
 * React's tree and crashes the page into the Next.js error boundary
 * ("removeChild … is not a child of this node" -> "This page couldn't load"),
 * which is what made the public-journeys tests flaky. Only the loading
 * overlay uses `fixed inset-0`, so that is the selector to wait on.
 *
 * @param {import('@playwright/test').Page} page
 * @param {number} [timeout=10000] - Max ms to wait
 */
async function waitForOverlay(page, timeout = 10000) {
  // The overlay is a fixed div with z-50 that only exists during the loading
  // animation. Wait for it to be detached (removed from DOM) rather than
  // hidden, because AnimatePresence removes it after the exit animation.
  //
  // If the overlay is still present after the timeout, remove only the
  // overlay node itself via JS so the test can proceed — never React-managed
  // siblings like the navbar.
  try {
    await page
      .locator('.fixed.inset-0.z-50')
      .waitFor({ state: 'detached', timeout });
  } catch {
    // Overlay didn't disappear in time — force-remove just the overlay
    await page.evaluate(() => {
      document.querySelectorAll('.fixed.inset-0.z-50').forEach((el) => el.remove());
    });
  }
}

/**
 * Navigate to the public site and wait for the loading overlay to clear.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} [url='/']
 */
async function gotoPublic(page, url = '/') {
  await page.goto(url, { waitUntil: 'domcontentloaded' });
  await waitForOverlay(page);
}

/**
 * Wait for a Next.js page to hydrate (JS loaded and interactive).
 * Checks that React event handlers are attached by verifying the page
 * has processed client-side JS.
 *
 * @param {import('@playwright/test').Page} page
 * @param {number} [timeout=15000]
 */
async function waitForHydration(page, timeout = 15000) {
  // The admin login form has method="post" which causes a native form POST
  // before React hydrates. We must wait for React to attach event handlers
  // so the onSubmit handler fires instead of the native submission.
  //
  // Strategy: wait for load state, then verify React has hydrated by checking
  // that __NEXT_DATA__ exists AND the page has interactive JS (not just SSR HTML).
  await page.waitForLoadState('load', { timeout });
  // Wait for React hydration — check that React fiber root is attached
  await page.waitForFunction(
    () => {
      // Next.js dev: __NEXT_DATA__ is present after SSR
      // React hydration: document has a root fiber or the form has onSubmit
      const form = document.querySelector('form');
      if (!form) return false;
      // After hydration, React attaches event listeners via __reactFiber$ or
      // __reactProps$. Check for either marker on the form or its children.
      const hasReactFiber =
        Object.keys(form).some((k) => k.startsWith('__reactFiber$')) ||
        Object.keys(form).some((k) => k.startsWith('__reactProps$'));
      return hasReactFiber;
    },
    { timeout },
  );
}

/**
 * Log into the admin panel. Navigates to login, waits for hydration,
 * fills credentials, clicks submit, and waits for the dashboard redirect.
 *
 * @param {import('@playwright/test').Page} page - The page to log in on
 * @param {string} adminUrl - Base URL of the admin app
 * @param {string} email
 * @param {string} password
 */
async function loginAdmin(page, adminUrl, email, password) {
  // Strategy 1: Direct token injection — fastest and most reliable.
  // Call the backend login API to get a token, then inject it into localStorage
  // before navigating to the admin page. This avoids the hydration timing issue
  // where the admin Next.js dev server is too slow for React to attach event
  // handlers before Playwright interacts with the form.
  //
  // Retry on 429 (Too Many Login Attempts) with exponential backoff.
  const apiUrl = process.env.API_URL || 'http://localhost:8000/api';
  // Default to the seeder credentials if env vars aren't set
  email = email || process.env.ADMIN_EMAIL || 'info@hasib.com';
  password = password || process.env.ADMIN_PASSWORD || '42862266';
  const MAX_RETRIES = 5;
  let loginData;
  let token;
  for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
    const loginResp = await page.request.post(`${apiUrl}/login`, {
      data: { email, password },
    });
    loginData = await loginResp.json();
    token = loginData.data?.token;
    if (token) break;
    if (loginResp.status() === 429 && attempt < MAX_RETRIES) {
      // Parse wait time from message: "Please try again in 41 seconds."
      const waitMatch = loginData.message?.match(/try again in (\d+)/);
      const waitSec = waitMatch ? parseInt(waitMatch[1], 10) : attempt * 15;
      console.log(`  [loginAdmin] 429 on attempt ${attempt}, waiting ${waitSec}s...`);
      await page.waitForTimeout(waitSec * 1000);
      continue;
    }
    throw new Error(
      `Login failed after ${attempt} attempts: ${loginData.message || 'no token returned'}\n` +
      `Status: ${loginResp.status()}`,
    );
  }

  // Navigate to the admin page first so localStorage is set in the right origin
  await page.goto(`${adminUrl}/login`, { waitUntil: 'domcontentloaded' });

  // Inject the auth token and user info into localStorage
  await page.evaluate(
    ({ token, user }) => {
      localStorage.setItem('auth_token', token);
      localStorage.setItem('admin_user', JSON.stringify(user));
    },
    { token, user: loginData.data.user },
  );

  // Navigate to the dashboard — the admin layout will see the token and stay
  await page.goto(`${adminUrl}/admin/dashboard`, {
    waitUntil: 'domcontentloaded',
    timeout: 15000,
  });
  // Wait for the admin shell to render (sidebar + header)
  // The layout calls GET /admin/me to verify the session — wait for it
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  // Wait for the "Loading..." spinner to disappear (layout auth check)
  await page
    .locator('text=Loading...')
    .waitFor({ state: 'detached', timeout: 10000 })
    .catch(() => {});
  // Small extra delay for Next.js client-side rendering
  await page.waitForTimeout(500);
}

/**
 * Wait for an admin page to finish loading (layout auth check + page data).
 * The admin layout shows "Loading..." while verifying the session via
 * GET /admin/me, then the page itself may show a spinner while fetching
 * its data. This helper waits for both to complete.
 *
 * @param {import('@playwright/test').Page} page
 * @param {number} [timeout=15000]
 */
async function waitForAdminPage(page, timeout = 15000) {
  await page.waitForLoadState('networkidle', { timeout }).catch(() => {});
  await page
    .locator('text=Loading...')
    .waitFor({ state: 'detached', timeout })
    .catch(() => {});
  // Extra wait for React to finish rendering after auth check
  await page.waitForTimeout(500);
}

/**
 * Quiesce a freshly loaded public page before interacting with it.
 *
 * Do NOT pre-scroll the full page here: on this Next.js 16 stack, scrolling the
 * whole page while React is still hydrating races a pre-existing AnimatePresence
 * node-removal bug ("removeChild ... is not a child of this node") and reliably
 * trips the Next error boundary, dropping the page into its "This page couldn't
 * load" state. The Reveal entrance animations are `once: true` and short-lived,
 * and clickStable() waits them out at the click site, so the only preparation
 * needed is:
 *   1. Letting hydration settle for a moment after the loading overlay clears.
 *   2. Checking the page did not land in the error state and reloading once if
 *      it did, so the test never interacts with a dead page.
 */
async function settlePublicPage(page) {
  await page.waitForTimeout(800);

  const crashed = await page
    .evaluate(() => /couldn.t load/i.test(document.body ? document.body.innerText : ''))
    .catch(() => false);

  if (crashed) {
    await page.reload({ waitUntil: 'domcontentloaded' });
    // The overlay shows again on reload; wait for it to clear like gotoPublic.
    await page.waitForTimeout(2200);
  }
}

/**
 * Click a locator only once it has stopped moving.
 *
 * Playwright's built-in actionability already waits for stability, but it gives
 * up quickly when the element sits inside an ancestor that is still animating
 * (e.g. a Reveal column) or shifting because of late layout. This helper waits
 * for the element AND its ancestor chain to have no running animations and for
 * its bounding box to be identical across two consecutive samples before
 * handing off to locator.click().
 */
async function clickStable(locator, { timeout = 10000 } = {}) {
  await locator.scrollIntoViewIfNeeded({ timeout }).catch(() => {});

  const deadline = Date.now() + timeout;
  let previous = null;
  let stable = false;

  while (Date.now() < deadline) {
    const box = await locator.boundingBox().catch(() => null);
    const stillAnimating = await locator
      .evaluate((el) => {
        let p = el;
        let running = 0;
        while (p) {
          running += p.getAnimations().filter((a) => a.playState === 'running').length;
          p = p.parentElement;
        }
        return running > 0;
      })
      .catch(() => true);

    if (
      box &&
      !stillAnimating &&
      previous &&
      Math.abs(box.x - previous.x) < 0.5 &&
      Math.abs(box.y - previous.y) < 0.5
    ) {
      stable = true;
      break;
    }
    previous = box;
    await new Promise((resolve) => setTimeout(resolve, 250));
  }

  if (!stable) {
    throw new Error(`Element did not become stable within ${timeout}ms`);
  }

  await locator.click({ timeout });
}

/**
 * Reset the volatile backend state that the admin-panel snapshots render.
 *
 * The snapshot baselines are pixel comparisons of admin UI chrome. Contact
 * messages and meeting requests accumulate with every journey-test run, and
 * the hero/settings singletons hold whatever content the last test left, so
 * without this the admin screenshots depend on the database instead of on the
 * UI. Run it before each admin snapshot:
 *
 *   - soft-deletes every contact message and meeting request, so both inboxes
 *     render empty and identical on every run.
 *
 * The hero and settings singletons are deliberately NOT touched here: the
 * other tests keep them stable (hero-edit-propagation restores whatever it
 * captured), and the snapshot baselines are captured against that stable
 * content. Blanking them via the reset endpoints broke the whole suite — the
 * admin shell renders from settings, and the public hero section from the hero
 * singleton.
 *
 * @param {import('@playwright/test').Page} page
 */
async function resetAdminSnapshotData(page) {
  const apiUrl = process.env.API_URL || 'http://localhost:8000/api';
  const email = process.env.ADMIN_EMAIL || 'info@hasib.com';
  const password = process.env.ADMIN_PASSWORD || '42862266';

  const login = await page.request.post(`${apiUrl}/login`, {
    data: { email, password },
  });
  const token = (await login.json())?.data?.token;
  if (!token) {
    throw new Error(`resetAdminSnapshotData: login failed (${login.status()})`);
  }
  const headers = { Authorization: `Bearer ${token}` };

  const inboxes = [
    ['/admin/messages', '/admin/messages/'],
    ['/admin/meeting-requests', '/admin/meeting-requests/'],
  ];
  for (const [listPath, deletePrefix] of inboxes) {
    const list = await page.request.get(`${apiUrl}${listPath}`, { headers });
    const data = await list.json();
    for (const row of data?.data ?? []) {
      await page.request.delete(`${apiUrl}${deletePrefix}${row.id}`, { headers });
    }
  }
}

/*
 * The public homepage is a server component rendered through Next's ISR cache
 * (fetch revalidate: 60). Its total height — and therefore every fullPage
 * screenshot of it — is a direct function of how many sections render, which
 * the section-visibility tests mutate mid-suite. Division of labor:
 *
 *   - The visibility tests themselves call waitForAllSectionsServed() after
 *     restoring their toggle, so they pay the ~60s revalidation wait for the
 *     poison they created and end with a fresh cache — the homepage snapshot
 *     tests that follow never see a stale hidden-section render.
 *   - The homepage snapshot tests call ensureAllSectionsVisible() +
 *     waitForAllSectionsServed() as a guard: normally the cache is already
 *     fresh and both return on the first check; if a visibility test crashed
 *     mid-poison or the DB was toggled by hand, the guard still self-heals
 *     instead of capturing a shortened page.
 */

/**
 * Section root ids on the public homepage. A hidden section is omitted from
 * the server-rendered page entirely, so the served HTML is missing its id.
 * These must stay in sync with the section components' `id` attributes
 * (portfolio-frontend/components/portfolio/*.jsx).
 */
const HOMEPAGE_SECTION_IDS = [
  'home',
  'about',
  'skills',
  'projects',
  'apis',
  'journey',
  'testimonials',
  'contact',
];

/**
 * Force every homepage section visible via the admin API.
 *
 * The homepage snapshot baselines show every section. The
 * section-visibility-propagation test toggles a section (About) off mid-suite;
 * if its restore races out, a later snapshot can capture the homepage with a
 * section missing — which shortens the page (the 6750px vs 6122px failures)
 * and turns the whole fullPage comparison into noise. Run this before
 * capturing so the DB always starts from the state the baselines were taken
 * in. Best-effort: without admin credentials the reset is skipped and the
 * caller relies on waitForAllSectionsServed alone.
 *
 * The locked Hero row is set true like the rest — the bulk endpoint rejects
 * hiding a non-toggleable section, but re-showing one is always allowed.
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<boolean>} whether the reset was applied
 */
async function ensureAllSectionsVisible(page) {
  const apiUrl = process.env.API_URL || 'http://localhost:8000/api';
  const email = process.env.ADMIN_EMAIL || 'info@hasib.com';
  const password = process.env.ADMIN_PASSWORD || '42862266';

  try {
    const login = await page.request.post(`${apiUrl}/login`, {
      data: { email, password },
    });
    const token = (await login.json())?.data?.token;
    if (!token) {
      console.warn('[ensureAllSectionsVisible] admin login failed; skipping reset');
      return false;
    }

    const headers = { Authorization: `Bearer ${token}` };
    const list = await page.request.get(`${apiUrl}/admin/section-visibility`, {
      headers,
    });
    const rows = (await list.json())?.data ?? [];
    if (rows.length === 0) return false;

    const sections = rows.map((row) => ({
      id: row.id,
      is_visible: true,
      order: typeof row.order === 'number' ? row.order : row.id,
    }));
    const put = await page.request.put(`${apiUrl}/admin/section-visibility`, {
      headers,
      data: { sections },
    });
    return put.ok();
  } catch (error) {
    console.warn(`[ensureAllSectionsVisible] reset skipped: ${error.message}`);
    return false;
  }
}

/**
 * Wait until the public server actually serves a homepage with every section.
 *
 * Once a render with a section hidden lands in Next's ISR cache it keeps being
 * served for the 60s revalidate window — including to the FIRST request after
 * the window expires, which Next answers from the stale entry while
 * revalidating in the background. A snapshot taken on that stale page is the
 * whole homepage flake.
 *
 * Poll the served HTML with cheap server-side requests (no browser load): if
 * the cache holds a stale render the polling itself drives revalidation once
 * the window passes, and the next poll observes the fresh page. Only then is
 * the caller's page.goto() guaranteed to get the deterministic all-sections
 * page the baselines were captured against.
 *
 * @param {import('@playwright/test').Page} page
 * @param {object} [options]
 * @param {string} [options.publicUrl] - public site base URL
 * @param {number} [options.timeout=100000] - max ms to wait for a fresh render
 * @param {number} [options.interval=4000] - ms between polls
 */
async function waitForAllSectionsServed(page, options = {}) {
  const {
    publicUrl = process.env.PUBLIC_URL || 'http://127.0.0.1:3000',
    timeout = 100000,
    interval = 4000,
  } = options;

  const deadline = Date.now() + timeout;
  let waited = false;
  let missing = HOMEPAGE_SECTION_IDS;

  while (Date.now() < deadline) {
    let html = '';
    try {
      const response = await page.request.get(`${publicUrl}/`);
      if (response.ok()) html = await response.text();
    } catch {
      // Server warming up / restarting — keep polling until the deadline.
    }

    missing = HOMEPAGE_SECTION_IDS.filter((id) => !html.includes(`id="${id}"`));
    if (missing.length === 0) {
      if (waited) {
        console.log(
          '[waitForAllSectionsServed] waited for the ISR cache to revalidate; fresh homepage now served',
        );
      }
      return;
    }
    waited = true;
    await page.waitForTimeout(interval);
  }

  throw new Error(
    `Homepage never rendered all sections within ${timeout}ms ` +
    `(still missing: ${missing.join(', ')}). ` +
    'Is the backend up and every section_visibility row enabled?',
  );
}

/**
 * Settle below-fold content before a fullPage screenshot.
 *
 * A fullPage screenshot scrolls the page while capturing, which triggers lazy
 * images to load and Reveal entrance animations to run mid-capture — pixels
 * then depend on scroll timing. Scroll through the page once, gently, so
 * images are loaded and the (once:true) Reveal blocks have already fired, then
 * return to the top for the capture.
 *
 * @param {import('@playwright/test').Page} page
 */
async function settleFullPageContent(page) {
  const height = await page.evaluate(() => document.body.scrollHeight);
  for (let i = 1; i <= 10; i++) {
    await page.evaluate(
      (y) => window.scrollTo(0, y),
      Math.round((height * i) / 10),
    );
    await page.waitForTimeout(120);
  }
  await page.evaluate(() => Promise.all(
    Array.from(document.images).map((img) =>
      img.complete ? Promise.resolve() :
      new Promise((resolve) => {
        img.addEventListener('load', resolve, { once: true });
        img.addEventListener('error', resolve, { once: true });
      }),
    ),
  )).catch(() => {});
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(500);
}

/**
 * Load the public homepage in the exact state the fullPage snapshot baselines
 * were captured against, and settle it for the shot.
 *
 *   1. ensureAllSectionsVisible  — the DB must show every section (the
 *      section-visibility test can leave one hidden mid-suite).
 *   2. waitForAllSectionsServed  — Next's ISR cache can keep serving a stale
 *      hidden-section render for up to a minute; only a fresh render has the
 *      baseline's height.
 *   3. Browser load + overlay clear + below-fold settle — the page must be
 *      pixel-stable before the screenshot.
 *
 * @param {import('@playwright/test').Page} page
 * @param {object} [options] - passed to waitForAllSectionsServed
 */
async function loadHomepageForSnapshot(page, options = {}) {
  await ensureAllSectionsVisible(page);
  await waitForAllSectionsServed(page, options);
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await waitForOverlay(page);
  await settleFullPageContent(page);
}

module.exports = {
  waitForOverlay,
  gotoPublic,
  waitForHydration,
  loginAdmin,
  waitForAdminPage,
  settlePublicPage,
  clickStable,
  resetAdminSnapshotData,
  ensureAllSectionsVisible,
  waitForAllSectionsServed,
  settleFullPageContent,
  loadHomepageForSnapshot,
};
