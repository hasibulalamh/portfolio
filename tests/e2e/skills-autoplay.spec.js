const { test, expect } = require('@playwright/test');
const { gotoPublic } = require('./helpers');

/*
 * Skills marquee behaviour.
 *
 * The card track auto-slides continuously right-to-left (CSS keyframes,
 * translateX 0 -> -50% of a repeated track), wraps seamlessly at the content
 * period, pauses while hovered / keyboard-focused / right after a manual
 * arrow nudge, restarts cleanly when a category filter changes, and renders
 * as a static list under prefers-reduced-motion — never causing horizontal
 * page overflow. Read-only spec: it never mutates CMS data.
 *
 * sampleMotion() samples the track's animated translateX and "unwraps" each
 * seamless period wrap, so a broken (visible-jump) loop shows up as a large
 * negative delta while a seamless one stays smooth.
 */

const VIEWPORTS = [
  { label: '320px mobile', width: 320, height: 720 },
  { label: '390px mobile', width: 390, height: 820 },
  { label: '768px tablet', width: 768, height: 900 },
  { label: '1024px laptop', width: 1024, height: 900 },
  { label: '1440px desktop', width: 1440, height: 900 },
];

const trackLocator = (page) => page.locator('#skills [data-skills-track]');
const viewportLocator = (page) =>
  page.locator('#skills .skills-marquee-viewport');

async function openSkills(page) {
  await gotoPublic(page);
  const section = page.locator('#skills');
  await section.scrollIntoViewIfNeeded();
  // Keep the pointer away from the carousel so hover-pause never kicks in.
  await page.mouse.move(2, 2);
  await trackLocator(page).waitFor({ state: 'visible' });
  // Give the IntersectionObserver a beat to mark the section in view.
  await page.waitForTimeout(300);
}

async function trackState(page) {
  return page.evaluate(() => {
    const track = document.querySelector('#skills [data-skills-track]');
    if (!track) return null;
    const transform = getComputedStyle(track).transform;
    const x = transform === 'none' ? 0 : new DOMMatrixReadOnly(transform).m41;

    const realCards = [...track.querySelectorAll('[data-skill-card]')];
    const step =
      realCards.length > 1
        ? realCards[1].getBoundingClientRect().left -
          realCards[0].getBoundingClientRect().left
        : 0;

    const viewport = track.closest('.skills-marquee-viewport');
    const viewportRect = viewport.getBoundingClientRect();
    const visible = [...track.children]
      .filter((child) => {
        const rect = child.getBoundingClientRect();
        return (
          rect.right > viewportRect.left + 2 && rect.left < viewportRect.right - 2
        );
      })
      .map((child) => child.querySelector('h3')?.textContent);

    return {
      // One content period = half of the track's width (the track renders
      // the filtered list an even number of times).
      period: track.getBoundingClientRect().width / 2,
      x: Math.round(x * 10) / 10,
      step: Math.round(step * 10) / 10,
      realCount: realCards.length,
      cloneCount: track.querySelectorAll('[data-skill-clone]').length,
      clonesHidden: [...track.querySelectorAll('[data-skill-clone]')].every(
        (clone) => clone.getAttribute('aria-hidden') === 'true',
      ),
      visible,
      animationName: getComputedStyle(track).animationName,
      playState: track.getAnimations()[0]?.playState ?? 'none',
      pageOverflowX:
        document.documentElement.scrollWidth - document.documentElement.clientWidth,
    };
  });
}

/** Current translateX of the arrow-nudge wrapper (the offset layer). */
async function offsetState(page) {
  return page.evaluate(() => {
    const wrapper = document.querySelector('.skills-marquee-offset');
    if (!wrapper) return null;
    const transform = getComputedStyle(wrapper).transform;
    return transform === 'none' ? 0 : new DOMMatrixReadOnly(transform).m41;
  });
}

/**
 * Sample the track's animated translateX for `durationMs`. The track moves
 * leftward, so its phase (x mod period) DECREASES from period toward 0 and a
 * seamless wrap shows up as a jump back UP to ~period. Each wrap is detected
 * and folded back, so `deltas[].d` approximates the continuous leftward
 * speed (small negative values): a visible jump at the loop point would show
 * up as a large negative d, a stall as d ~ 0, rightward drift as d > 0.
 */
async function sampleMotion(page, durationMs, intervalMs = 100) {
  return page.evaluate(
    ({ duration, interval }) =>
      new Promise((resolve) => {
        const track = document.querySelector('#skills [data-skills-track]');
        if (!track) {
          resolve(null);
          return;
        }
        const period = track.getBoundingClientRect().width / 2;
        const readX = () => {
          const transform = getComputedStyle(track).transform;
          return transform === 'none'
            ? 0
            : new DOMMatrixReadOnly(transform).m41;
        };

        const started = performance.now();
        let lastPhase = null;
        let lastT = performance.now();
        let cumulative = 0;
        const deltas = [];

        const tick = () => {
          const now = performance.now();
          const x = readX();
          const phase = ((x % period) + period) % period;
          if (lastPhase !== null) {
            let d = phase - lastPhase;
            // Seamless wrap: phase jumped from ~0 back up to ~period.
            const wrapped = d > period / 2;
            if (wrapped) d -= period;
            deltas.push({ dt: Math.round(now - lastT), d: Math.round(d * 10) / 10, wrapped });
            cumulative += d;
          }
          lastPhase = phase;
          lastT = now;
          if (now - started >= duration) {
            resolve({
              period: Math.round(period),
              cumulative: Math.round(cumulative),
              deltas,
            });
          } else {
            setTimeout(tick, interval);
          }
        };
        tick();
      }),
    { duration: durationMs, interval: intervalMs },
  );
}

test.describe('skills marquee', () => {
  for (const { label, width, height } of VIEWPORTS) {
    test(`slides continuously right-to-left at ${label}`, async ({ page }) => {
      test.setTimeout(60_000);
      await page.setViewportSize({ width, height });
      await openSkills(page);

      const state = await trackState(page);
      expect(state.realCount).toBeGreaterThanOrEqual(4);
      // The repeated track needs hidden-from-a11y clone copies.
      expect(state.cloneCount).toBeGreaterThan(0);
      expect(state.clonesHidden).toBe(true);
      expect(state.animationName).toBe('skills-marquee');
      expect(state.playState).toBe('running');
      expect(state.pageOverflowX).toBeLessThanOrEqual(1);

      const motion = await sampleMotion(page, 4_000);
      // Continuous leftward motion — roughly card-speed (~200-260px/s),
      // recorded as negative displacement.
      expect(motion.cumulative, JSON.stringify(motion.deltas)).toBeLessThan(-150);
      // Never any rightward drift, stall, or visible loop jump: unwrapped
      // deltas stay small and negative.
      for (const delta of motion.deltas) {
        expect(
          delta.d,
          `delta ${delta.d} over ${delta.dt}ms: ${JSON.stringify(motion.deltas)}`,
        ).toBeLessThanOrEqual(2);
        expect(delta.d).toBeGreaterThan(-motion.period / 2);
      }

      expect((await trackState(page)).pageOverflowX).toBeLessThanOrEqual(1);
    });
  }

  test('wraps around seamlessly at the content period and keeps looping', async ({
    page,
  }) => {
    test.setTimeout(60_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await openSkills(page);

    // Force a quick 3s loop so several wraps fit inside the sample window.
    await page.evaluate(() => {
      const track = document.querySelector('#skills [data-skills-track]');
      track.style.animationDuration = '3s';
    });
    await page.waitForTimeout(200);

    const motion = await sampleMotion(page, 7_600, 80);

    await page.evaluate(() => {
      const track = document.querySelector('#skills [data-skills-track]');
      track.style.removeProperty('animation-duration');
    });

    const wraps = motion.deltas.filter((delta) => delta.wrapped).length;
    expect(
      wraps,
      `period ${motion.period}px: ${JSON.stringify(motion.deltas)}`,
    ).toBeGreaterThanOrEqual(1);
    // Seamless: even across wraps, no sample ever moves right or jumps.
    for (const delta of motion.deltas) {
      expect(delta.d).toBeLessThanOrEqual(2);
      expect(delta.d).toBeGreaterThan(-motion.period / 2);
    }
    expect(motion.cumulative).toBeLessThan(-100);
  });

  test('hover pauses the marquee, leaving resumes it', async ({ page }) => {
    test.setTimeout(60_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await openSkills(page);

    // Confirm the marquee is live, then hover the carousel.
    const before = await sampleMotion(page, 2_000);
    expect(before.cumulative).toBeLessThan(-100);

    // Hover the clipped viewport (the track itself is wider than the screen).
    await viewportLocator(page).hover();
    await page.waitForFunction(
      () =>
        document.querySelector('#skills [data-skills-track]')?.getAnimations()[0]
          ?.playState === 'paused',
      undefined,
      { timeout: 3_000 },
    );
    const whileHovered = await sampleMotion(page, 2_000);
    expect(Math.abs(whileHovered.cumulative), JSON.stringify(whileHovered)).toBeLessThanOrEqual(2);

    // Resume on pointer leave.
    await page.mouse.move(2, 2);
    await page.waitForFunction(
      () =>
        document.querySelector('#skills [data-skills-track]')?.getAnimations()[0]
          ?.playState === 'running',
      undefined,
      { timeout: 3_000 },
    );
    const resumed = await sampleMotion(page, 2_500);
    expect(resumed.cumulative).toBeLessThan(-100);
  });

  test('keyboard focus inside the carousel pauses it too', async ({ page }) => {
    test.setTimeout(60_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await openSkills(page);

    await page.getByRole('button', { name: 'Next skills' }).focus();
    await page.waitForFunction(
      () =>
        document.querySelector('#skills [data-skills-track]')?.getAnimations()[0]
          ?.playState === 'paused',
      undefined,
      { timeout: 3_000 },
    );
    const whileFocused = await sampleMotion(page, 1_500);
    expect(Math.abs(whileFocused.cumulative)).toBeLessThanOrEqual(2);

    await page.getByRole('button', { name: 'Next skills' }).blur();
    await page.waitForFunction(
      () =>
        document.querySelector('#skills [data-skills-track]')?.getAnimations()[0]
          ?.playState === 'running',
      undefined,
      { timeout: 3_000 },
    );
  });

  test('category switch restarts the track cleanly from the first card', async ({
    page,
  }) => {
    test.setTimeout(60_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await openSkills(page);

    // Let the track run away from phase 0 on "All" first.
    const initial = await sampleMotion(page, 3_000);
    expect(initial.cumulative).toBeLessThan(-100);

    await page.getByRole('button', { name: 'Backend', exact: true }).click();

    await expect(viewportLocator(page)).toHaveAttribute(
      'aria-label',
      'Backend skills',
    );
    const state = await trackState(page);
    expect(state.realCount).toBe(6);
    // Re-keyed track: the animation restarted at phase 0 (no stale state).
    expect(Math.abs(state.x)).toBeLessThan(100);
    expect(state.visible[0]).toBe('PHP');

    // The marquee is already moving again on the new (shorter) track.
    const after = await sampleMotion(page, 3_000);
    expect(after.cumulative).toBeLessThan(-100);
    for (const delta of after.deltas) {
      expect(delta.d).toBeLessThanOrEqual(2);
      expect(delta.d).toBeGreaterThan(-after.period / 2);
    }
  });

  test('prev/next nudge one card, briefly hold the marquee, then resume', async ({
    page,
  }) => {
    test.setTimeout(60_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await openSkills(page);

    const start = await trackState(page);
    expect(start.step).toBeGreaterThan(100);

    await page.getByRole('button', { name: 'Next skills' }).click();
    // The nudge (450ms transition) runs while the animation is held.
    await page.waitForTimeout(120);
    expect((await trackState(page)).playState).toBe('paused');
    await page.waitForTimeout(700);
    const afterNext = await offsetState(page);
    expect(Math.abs(afterNext - (0 - start.step))).toBeLessThanOrEqual(3);

    // The hold expires and the marquee resumes (pointer parked away first).
    await page.mouse.move(2, 2);
    await page.waitForFunction(
      () =>
        document.querySelector('#skills [data-skills-track]')?.getAnimations()[0]
          ?.playState === 'running',
      undefined,
      { timeout: 6_000 },
    );
    const resumed = await sampleMotion(page, 2_500);
    expect(resumed.cumulative).toBeLessThan(-100);

    const beforePrev = await offsetState(page);
    await page.getByRole('button', { name: 'Previous skills' }).click();
    await page.waitForTimeout(700);
    const afterPrev = await offsetState(page);
    expect(Math.abs(afterPrev - (beforePrev + start.step))).toBeLessThanOrEqual(3);
  });

  test('rapid arrow nudges stay normalized within one content period', async ({
    page,
  }) => {
    test.setTimeout(60_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await openSkills(page);

    const start = await trackState(page);
    for (let i = 0; i < 5; i += 1) {
      await page.getByRole('button', { name: 'Next skills' }).click();
      await page.waitForTimeout(150);
    }
    await page.waitForTimeout(700);

    const offset = await offsetState(page);
    // Exactly five card-widths leftward, always inside (-period, 0].
    expect(Math.abs(offset - -5 * start.step)).toBeLessThanOrEqual(6);
    expect(offset).toBeLessThanOrEqual(0);
    expect(offset).toBeGreaterThan(-start.period);
  });

  test('prefers-reduced-motion renders a static list but keeps arrow control', async ({
    page,
  }) => {
    test.setTimeout(45_000);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.setViewportSize({ width: 1440, height: 900 });
    await openSkills(page);

    const state = await trackState(page);
    expect(state.animationName).toBe('none');
    expect(state.playState).toBe('none');

    const idle = await sampleMotion(page, 2_000);
    expect(idle.cumulative).toBe(0);
    expect(state.pageOverflowX).toBeLessThanOrEqual(1);

    // Arrows still work — instantly, with no eased transition. "Next"
    // nudges the content one card to the left (negative offset).
    await page.getByRole('button', { name: 'Next skills' }).click();
    await page.waitForTimeout(80);
    expect(await offsetState(page)).toBeCloseTo(-state.step, 0);

    // Category filters still reset to the first card.
    await page.getByRole('button', { name: 'Backend', exact: true }).click();
    await expect(viewportLocator(page)).toHaveAttribute(
      'aria-label',
      'Backend skills',
    );
    const after = await trackState(page);
    expect(after.realCount).toBe(6);
    expect(Math.abs(after.x)).toBeLessThanOrEqual(3);
  });
});
