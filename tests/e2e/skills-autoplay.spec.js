const { test, expect } = require('@playwright/test');
const { gotoPublic } = require('./helpers');

/*
 * Skills carousel autoplay behaviour.
 *
 * The carousel must WAIT ~2.8s → slide exactly ONE card left → WAIT → …,
 * wrap around seamlessly, pause on hover, reset its timer after manual
 * interaction / category switches, respect prefers-reduced-motion, and never
 * cause horizontal page overflow. Read-only spec: it never mutates CMS data.
 *
 * observeTrack() records every settled scroll position. The FIRST record is
 * just the starting snapshot (delta null, gapMs ~0.2s) — assertions therefore
 * only treat records WITH a delta as slides.
 */

const VIEWPORTS = [
  { label: '320px mobile', width: 320, height: 720 },
  { label: '390px mobile', width: 390, height: 820 },
  { label: '768px tablet', width: 768, height: 900 },
  { label: '1024px laptop', width: 1024, height: 900 },
  { label: '1440px desktop', width: 1440, height: 900 },
];

const trackLocator = (page) => page.locator('#skills .snap-x');

const slideEvents = (result) => result.events.filter((event) => event.delta !== null);

async function openSkills(page) {
  await gotoPublic(page);
  const section = page.locator('#skills');
  await section.scrollIntoViewIfNeeded();
  // Keep the pointer away from the carousel so hover-pause never kicks in.
  await page.mouse.move(2, 2);
  await trackLocator(page).waitFor({ state: 'visible' });
  // IntersectionObserver just started autoplay — give it a beat.
  await page.waitForTimeout(300);
}

/**
 * Sample the track's settled scroll positions for `durationMs` and return
 * every stable position change with its delta and the time since the previous
 * record. A "slide" moves ~one card width; the seamless loop snap is a large
 * instant jump back to ~0 (pixel-identical viewport, so invisible to users).
 */
async function observeTrack(page, durationMs) {
  return page.evaluate(
    (duration) =>
      new Promise((resolve) => {
        const track = document.querySelector('#skills [data-skill-card]')?.parentElement;
        if (!track) {
          resolve(null);
          return;
        }
        const card = track.querySelector('[data-skill-card]');
        const gap = Number.parseFloat(getComputedStyle(track).columnGap) || 0;
        const step = card.getBoundingClientRect().width + gap;

        const started = performance.now();
        let lastSeen = track.scrollLeft;
        let stableSince = performance.now();
        let lastRecorded = null;
        let lastRecordedAt = performance.now();
        const events = [];

        const tick = () => {
          const now = performance.now();
          const left = track.scrollLeft;
          if (Math.abs(left - lastSeen) > 1.5) {
            lastSeen = left;
            stableSince = now;
          } else if (now - stableSince > 150) {
            const position = Math.round(left);
            if (lastRecorded === null || Math.abs(position - lastRecorded) > 1) {
              events.push({
                position,
                delta: lastRecorded === null ? null : position - lastRecorded,
                gapMs: Math.round(now - lastRecordedAt),
                kind:
                  lastRecorded === null ||
                  Math.abs(position - lastRecorded) <= step * 1.5 + 4
                    ? 'slide'
                    : 'snap',
              });
              lastRecorded = position;
              lastRecordedAt = now;
            }
          }
          if (now - started >= duration) {
            resolve({ step: Math.round(step), events });
          } else {
            requestAnimationFrame(tick);
          }
        };
        requestAnimationFrame(tick);
      }),
    durationMs,
  );
}

async function trackState(page) {
  return page.evaluate(() => {
    const track = document.querySelector('#skills [data-skill-card]')?.parentElement;
    if (!track) return null;
    const card = track.querySelector('[data-skill-card]');
    const gap = Number.parseFloat(getComputedStyle(track).columnGap) || 0;
    const step = card.getBoundingClientRect().width + gap;
    const trackRect = track.getBoundingClientRect();
    const visible = [...track.children]
      .filter((child) => {
        const rect = child.getBoundingClientRect();
        return rect.right > trackRect.left + 2 && rect.left < trackRect.right - 2;
      })
      .map((child) => child.querySelector('h3')?.textContent);
    return {
      step,
      scrollLeft: Math.round(track.scrollLeft),
      realCount: track.querySelectorAll('[data-skill-card]').length,
      cloneCount: track.querySelectorAll('[data-skill-clone]').length,
      clonesHidden: [...track.querySelectorAll('[data-skill-clone]')].every(
        (clone) => clone.getAttribute('aria-hidden') === 'true',
      ),
      visible,
      pageOverflowX:
        document.documentElement.scrollWidth - document.documentElement.clientWidth,
    };
  });
}

/** Scroll the track to the given card index (instantly, no autoplay wait). */
async function scrollToIndex(page, index) {
  return page.evaluate((cardIndex) => {
    const track = document.querySelector('#skills [data-skill-card]').parentElement;
    const card = track.querySelector('[data-skill-card]');
    const gap = Number.parseFloat(getComputedStyle(track).columnGap) || 0;
    track.scrollTo({
      left: cardIndex * (card.getBoundingClientRect().width + gap),
      behavior: 'auto',
    });
  }, index);
}

test.describe('skills carousel autoplay', () => {
  for (const { label, width, height } of VIEWPORTS) {
    test(`advances exactly one card per step at ${label}`, async ({ page }) => {
      test.setTimeout(60_000);
      await page.setViewportSize({ width, height });
      await openSkills(page);

      const before = await trackState(page);
      expect(before.realCount).toBeGreaterThanOrEqual(4);
      // A scrollable track needs loop clones; they must be hidden from a11y.
      expect(before.cloneCount).toBeGreaterThan(0);
      expect(before.clonesHidden).toBe(true);
      expect(before.pageOverflowX).toBeLessThanOrEqual(1);

      // ~12s catches three autoplay ticks (first at ~2.8s, cadence ~3.45s).
      const { step, events } = await observeTrack(page, 12_000);
      const slides = slideEvents({ events });
      expect(step).toBeGreaterThan(100);
      expect(slides.length, JSON.stringify(events)).toBeGreaterThanOrEqual(3);

      for (const event of slides) {
        // Exactly ONE card (one step) per slide — never two, never a marquee.
        expect(
          Math.abs(event.delta - step),
          `delta ${event.delta} vs step ${step}: ${JSON.stringify(events)}`,
        ).toBeLessThanOrEqual(3);
        // The WAIT before each slide is roughly 2.5–3s+.
        expect(event.gapMs).toBeGreaterThanOrEqual(2400);
        expect(event.gapMs).toBeLessThanOrEqual(7000);
      }

      const after = await trackState(page);
      expect(after.pageOverflowX).toBeLessThanOrEqual(1);
    });
  }

  test('wraps around seamlessly and keeps autoplaying', async ({ page }) => {
    test.setTimeout(60_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await openSkills(page);

    const state = await trackState(page);
    const { realCount } = state;

    // The viewport parked on the aligned clones (index == realCount) must
    // look exactly like the start — that equivalence is what makes the snap
    // back to 0 invisible.
    await scrollToIndex(page, realCount);
    const namesAtAligned = (await trackState(page)).visible;
    await scrollToIndex(page, 0);
    const namesAtStart = (await trackState(page)).visible;
    expect(namesAtAligned).toEqual(namesAtStart);

    // Park just before the end so the wrap happens within seconds.
    await scrollToIndex(page, realCount - 1);
    await page.waitForTimeout(200);

    // Autoplay continues: one more slide, seamless snap to the start, then
    // the cycle resumes with a normal wait.
    const { step, events } = await observeTrack(page, 10_000);
    const firstSnap = events.findIndex((event) => event.kind === 'snap');
    expect(firstSnap, JSON.stringify(events)).toBeGreaterThan(0);
    const slideBeforeSnap = events[firstSnap - 1];
    expect(slideBeforeSnap.delta).not.toBeNull();
    expect(Math.abs(slideBeforeSnap.delta - step)).toBeLessThanOrEqual(3);
    expect(events[firstSnap].position).toBeLessThanOrEqual(3);
    const slideAfterSnap = events[firstSnap + 1];
    expect(slideAfterSnap.kind).toBe('slide');
    expect(Math.abs(slideAfterSnap.delta - step)).toBeLessThanOrEqual(3);
    expect(slideAfterSnap.gapMs).toBeGreaterThanOrEqual(2400);
  });

  test('category switch resets position and restarts autoplay', async ({ page }) => {
    test.setTimeout(60_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await openSkills(page);

    // Let at least one autoplay slide happen on "All" first.
    const initial = await observeTrack(page, 5_000);
    expect(slideEvents(initial).length).toBeGreaterThanOrEqual(1);

    await page.getByRole('button', { name: 'Backend', exact: true }).click();

    const track = trackLocator(page);
    await expect(track).toHaveAttribute('aria-label', 'Backend skills');
    const state = await trackState(page);
    expect(state.realCount).toBe(6);
    expect(state.scrollLeft).toBeLessThanOrEqual(2);

    // Autoplay restarts from the first skill of the category after a full
    // wait — not immediately.
    const { step, events } = await observeTrack(page, 8_000);
    const slides = slideEvents({ events });
    expect(slides.length, JSON.stringify(events)).toBeGreaterThanOrEqual(2);
    expect(slides[0].gapMs, JSON.stringify(events)).toBeGreaterThanOrEqual(2400);
    for (const event of slides) {
      expect(Math.abs(event.delta - step)).toBeLessThanOrEqual(3);
    }

    // After the observed slides the viewport leads with the matching
    // Backend skill (one card per slide, from the category's first skill).
    const backend = [
      'PHP',
      'Laravel',
      'PHP OOP',
      'Eloquent ORM',
      'MVC Architecture',
      'Authentication & RBAC',
    ];
    const visible = (await trackState(page)).visible;
    expect(visible.slice(0, backend.slice(slides.length).length)).toEqual(
      backend.slice(slides.length),
    );
  });

  test('hover pauses autoplay on desktop, resume resets the timer', async ({ page }) => {
    test.setTimeout(60_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await openSkills(page);

    // Confirm autoplay is live, then hover the carousel.
    const before = await observeTrack(page, 5_000);
    expect(slideEvents(before).length).toBeGreaterThanOrEqual(1);

    await trackLocator(page).hover();
    const hovered = await trackState(page);
    await page.waitForTimeout(5_000);
    const whileHovered = await trackState(page);
    expect(whileHovered.scrollLeft).toBe(hovered.scrollLeft);

    // Resume on pointer leave: a fresh full wait, then normal one-card steps.
    await page.mouse.move(2, 2);
    const resumed = await observeTrack(page, 7_000);
    const resumedSlides = slideEvents(resumed);
    expect(resumedSlides.length, JSON.stringify(resumed.events)).toBeGreaterThanOrEqual(1);
    expect(resumedSlides[0].gapMs).toBeGreaterThanOrEqual(2400);
    expect(resumedSlides[0].delta).toBeGreaterThan(0);
  });

  test('prev/next move one card and reset the autoplay timer', async ({ page }) => {
    test.setTimeout(60_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await openSkills(page);

    await observeTrack(page, 5_000); // autoplay confirmed running
    const start = await trackState(page);

    await page.getByRole('button', { name: 'Next skills' }).click();
    await page.waitForTimeout(600);
    const afterNext = await trackState(page);
    expect(
      Math.abs(afterNext.scrollLeft - (start.scrollLeft + start.step)),
    ).toBeLessThanOrEqual(3);

    // No immediate auto-slide after manual interaction: the wait is reset.
    await page.waitForTimeout(2_200);
    const settled = await trackState(page);
    expect(settled.scrollLeft).toBe(afterNext.scrollLeft);

    // The pointer still rests on the (hovered) Next button, so move it away
    // first — autoplay then resumes with a fresh full wait.
    await page.mouse.move(2, 2);
    const resumed = await observeTrack(page, 6_000);
    const resumedSlides = slideEvents(resumed);
    expect(resumedSlides.length, JSON.stringify(resumed.events)).toBeGreaterThanOrEqual(1);
    expect(resumedSlides[0].gapMs).toBeGreaterThanOrEqual(2400);

    const beforePrev = await trackState(page);
    await page.getByRole('button', { name: 'Previous skills' }).click();
    await page.waitForTimeout(600);
    const afterPrev = await trackState(page);
    expect(
      Math.abs(afterPrev.scrollLeft - (beforePrev.scrollLeft - beforePrev.step)),
    ).toBeLessThanOrEqual(3);
  });

  test('prefers-reduced-motion disables autoplay but keeps manual control', async ({
    page,
  }) => {
    test.setTimeout(45_000);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.setViewportSize({ width: 1440, height: 900 });
    await openSkills(page);

    const idle = await observeTrack(page, 6_000);
    expect(slideEvents(idle).length, JSON.stringify(idle.events)).toBe(0);
    expect((await trackState(page)).scrollLeft).toBeLessThanOrEqual(2);

    // Manual navigation still works, and moves instantly (no animation).
    await page.getByRole('button', { name: 'Next skills' }).click();
    await page.waitForTimeout(250);
    const state = await trackState(page);
    expect(Math.abs(state.scrollLeft - state.step)).toBeLessThanOrEqual(3);

    // Category filters still reset to the first card.
    await page.getByRole('button', { name: 'Backend', exact: true }).click();
    await expect(trackLocator(page)).toHaveAttribute('aria-label', 'Backend skills');
    expect((await trackState(page)).scrollLeft).toBeLessThanOrEqual(2);
  });

  test('horizontal wheel scroll still works and snaps to a card', async ({ page }) => {
    test.setTimeout(45_000);
    await page.setViewportSize({ width: 390, height: 820 });
    await openSkills(page);

    const start = await trackState(page);
    await trackLocator(page).hover();
    await page.mouse.wheel(360, 0);
    await page.waitForTimeout(1_200);

    const scrolled = await trackState(page);
    expect(scrolled.scrollLeft).toBeGreaterThan(start.scrollLeft);
    // Native scroll-snap must land on a card boundary.
    expect(Math.abs(scrolled.scrollLeft % scrolled.step)).toBeLessThanOrEqual(3);

    // Autoplay resumes after the manual interaction (timer was reset).
    await page.mouse.move(2, 2);
    const resumed = await observeTrack(page, 7_500);
    expect(
      slideEvents(resumed).some((event) => event.gapMs >= 2400),
      JSON.stringify(resumed.events),
    ).toBe(true);
    expect((await trackState(page)).pageOverflowX).toBeLessThanOrEqual(1);
  });
});
