'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Code2,
  ChevronLeft,
  ChevronRight,
  Database,
  Layers3,
  PlugZap,
  SearchCheck,
  ShieldCheck,
} from 'lucide-react';
import { SectionHeading } from './reveal';
import { TechIconTile, TechIcon, ACCENT_GLOW } from './tech-icon';
import { cn } from '@/lib/utils';

const CONCEPTUAL_ICONS = {
  'database schema design': Database,
  'query optimization': SearchCheck,
  'authentication & rbac': ShieldCheck,
  'mvc architecture': Layers3,
  'third-party api integration': PlugZap,
  'php oop': Code2,
};

function conceptualIcon(name) {
  return CONCEPTUAL_ICONS[name.trim().toLowerCase()] ?? null;
}

function abbreviate(name = '') {
  const words = name
    .trim()
    .split(/[\s.]+/)
    .filter(Boolean);

  if (words.length === 0) return '??';
  if (words.length === 1) {
    return words[0].slice(0, 2).replace(/^./, (c) => c.toUpperCase());
  }

  return (words[0][0] + words[1][0]).toUpperCase();
}

function SkillMark({ skill }) {
  const Icon = conceptualIcon(skill.name);

  if (Icon) {
    return (
      <span
        className="flex h-12 w-12 items-center justify-center rounded-xl border border-accent/30 bg-accent/10 text-accent"
        aria-hidden="true"
      >
        <Icon className="h-6 w-6" strokeWidth={1.75} />
      </span>
    );
  }

  if (skill.logo_type === 'custom' && skill.logo_url) {
    return (
      <span className="flex h-12 w-12 items-center justify-center rounded-xl border border-border bg-background/40">
        <img src={skill.logo_url} alt="" className="h-8 w-8 object-contain" />
      </span>
    );
  }

  if (skill.icon_slug) {
    return (
      <span className="flex h-12 w-12 items-center justify-center rounded-xl border border-border bg-background/40">
        <TechIcon
          slug={skill.icon_slug}
          title={skill.name}
          tone="display"
          className="h-7 w-7"
        />
      </span>
    );
  }

  return (
    <span
      className="tech-glow flex h-12 w-12 items-center justify-center rounded-xl border border-accent/30 bg-accent/10 font-heading text-lg font-bold text-accent"
      style={ACCENT_GLOW}
      aria-hidden="true"
    >
      <span className="relative">{skill.icon || abbreviate(skill.name)}</span>
    </span>
  );
}

function SkillCard({ skill }) {
  return (
    <article className="group flex h-44 min-w-0 flex-col justify-between rounded-2xl border border-border bg-card/70 p-5 shadow-lg shadow-black/5 transition-all duration-300 hover:-translate-y-1 hover:border-accent/50 hover:bg-secondary/80">
      <SkillMark skill={skill} />
      <div className="min-w-0">
        <h3 className="truncate font-heading text-base font-semibold text-foreground">
          {skill.name}
        </h3>
        <p className="mt-1 truncate text-xs uppercase tracking-[0.14em] text-muted-foreground">
          {skill.category}
        </p>
      </div>
    </article>
  );
}

/*
 * Skills marquee.
 *
 * The filtered card list is rendered several times inside a max-content
 * track (see copyCount) that a CSS @keyframes animation translates from 0 to
 * -50% — exactly one content period — on an infinite linear loop, so the
 * wrap point is pixel-identical to the start (seamless). The animation and
 * its pause states live in app/globals.css (.skills-marquee*): it pauses on
 * :hover, :focus-within, while a manual arrow nudge is settling, and while
 * the section is offscreen; @media (prefers-reduced-motion: reduce) turns it
 * into a static list.
 */

// One full -50% cycle lasts SECONDS_PER_CARD per skill, so perceived speed is
// identical for any category size (≈240px/s at desktop card width) — the
// current 20-skill "All" list loops in ~24s; small categories floor at
// MIN_CYCLE_SECONDS so a 2-skill set doesn't zip past frenetically.
const SECONDS_PER_CARD = 1.2;
const MIN_CYCLE_SECONDS = 12;

// After an arrow nudge or touch the animation holds briefly, so the manual
// nudge is not immediately fought by the auto-slide.
const MANUAL_HOLD_MS = 2500;

/*
 * Copies of the filtered list needed for a seamless loop: half the track must
 * always be at least one viewport wide. 2 copies cover 8+ skills on desktop;
 * smaller lists repeat more.
 */
function copyCount(count) {
  if (count >= 8) return 2;
  if (count >= 4) return 4;
  return 8;
}

export function Skills({ categories = [] }) {
  const [active, setActive] = useState('All');
  // Manual arrow nudge, in px, applied to .skills-marquee-offset. Normalized
  // into (-period, 0] — visually a no-op thanks to the repeated content, but
  // it keeps the offset bounded however often the arrows are used.
  const [offset, setOffset] = useState(0);
  // True right after an arrow nudge / touch: pauses the animation briefly.
  const [hold, setHold] = useState(false);

  const trackRef = useRef(null);
  const containerRef = useRef(null);
  const sectionRef = useRef(null);
  const holdTimerRef = useRef(null);

  const groups = Array.isArray(categories) ? categories : [];

  const skills = useMemo(
    () =>
      groups.flatMap((category) =>
        (Array.isArray(category.skills) ? category.skills : []).map(
          (skill) => ({
            id: skill.id,
            name:
              skill.name?.trim().toLowerCase() === 'oop'
                ? 'PHP OOP'
                : skill.name,
            icon: skill.icon,
            icon_slug: skill.icon_slug,
            logo_type: skill.logo_type,
            logo_url: skill.logo_url,
            category: category.name,
          }),
        ),
      ),
    [groups],
  );

  const tabs = useMemo(
    () => [
      'All',
      ...groups.filter((c) => c.skills?.length > 0).map((c) => c.name),
    ],
    [groups],
  );

  const filtered = useMemo(
    () =>
      skills.filter((skill) => active === 'All' || skill.category === active),
    [active, skills],
  );

  const copies = copyCount(filtered.length);
  const duration = Math.max(
    MIN_CYCLE_SECONDS,
    Math.round(filtered.length * SECONDS_PER_CARD * 10) / 10,
  );

  const holdMarquee = useCallback(() => {
    setHold(true);
    if (holdTimerRef.current !== null) {
      clearTimeout(holdTimerRef.current);
    }
    holdTimerRef.current = setTimeout(() => {
      holdTimerRef.current = null;
      setHold(false);
    }, MANUAL_HOLD_MS);
  }, []);

  useEffect(
    () => () => {
      if (holdTimerRef.current !== null) {
        clearTimeout(holdTimerRef.current);
      }
    },
    [],
  );

  // The CSS animation runs regardless of scroll position; only pause it when
  // the section is offscreen (battery/GPU friendliness, same as before).
  useEffect(() => {
    const section = sectionRef.current;
    const container = containerRef.current;
    if (
      !section ||
      !container ||
      typeof IntersectionObserver === 'undefined'
    ) {
      return undefined;
    }
    const io = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          container.dataset.inview = entry.isIntersecting ? 'true' : 'false';
        }
      },
      { threshold: 0.1, rootMargin: '0px 0px -10% 0px' },
    );
    io.observe(section);
    return () => io.disconnect();
  }, []);

  // Arrow buttons: nudge the visible cards by exactly one card width via the
  // offset wrapper (a 450ms eased transition in motion mode, an instant jump
  // under prefers-reduced-motion), and hold the auto-slide while doing so.
  const move = (direction, event) => {
    const track = trackRef.current;
    if (!track) return;
    const card = track.querySelector('[data-skill-card]');
    if (!card) return;
    const gap = Number.parseFloat(getComputedStyle(track).columnGap) || 0;
    const step = card.getBoundingClientRect().width + gap;
    if (step <= 0) return;
    // The track content repeats every period (half its width), so the nudge
    // offset can be normalized into (-period, 0] with no visible jump.
    const period = track.getBoundingClientRect().width / 2;
    setOffset((prev) => {
      let next = (prev - direction * step) % period;
      if (next > 0) next -= period;
      return next;
    });
    // A mouse click leaves focus on the button, which would latch the
    // :focus-within pause and stop the auto-slide from ever resuming — blur
    // so the hold below is what governs the resume. Keyboard activation
    // (event.detail === 0) keeps focus deliberately: a focused user gets the
    // stable, non-moving state for as long as they stay on the control.
    if (event && event.detail > 0 && typeof event.currentTarget.blur === 'function') {
      event.currentTarget.blur();
    }
    holdMarquee();
  };

  const selectTab = (tab) => {
    // A fresh filter starts from the first card with a freshly keyed track
    // (key={active} remounts it, restarting the CSS animation at phase 0).
    setOffset(0);
    setHold(false);
    if (holdTimerRef.current !== null) {
      clearTimeout(holdTimerRef.current);
      holdTimerRef.current = null;
    }
    setActive(tab);
  };

  const onTouchStart = () => {
    holdMarquee();
  };

  return (
    <section
      id="skills"
      ref={sectionRef}
      className="relative overflow-hidden py-12 md:py-16"
    >
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <SectionHeading eyebrow="What I Work With" title="My Tech Stack" />

        {skills.length === 0 ? (
          <p className="text-center text-muted-foreground">
            Skills are being updated. Check back soon.
          </p>
        ) : (
          <>
            {tabs.length > 2 && (
              <div
                className="mb-8 flex flex-wrap justify-center gap-2"
                role="group"
                aria-label="Filter skills by category"
              >
                {tabs.map((tab) => (
                  <button
                    key={tab}
                    type="button"
                    onClick={() => selectTab(tab)}
                    aria-pressed={active === tab}
                    className={cn(
                      'rounded-full px-4 py-2 text-sm font-medium transition-all duration-300',
                      active === tab
                        ? 'bg-primary text-primary-foreground glow-primary'
                        : 'border border-border bg-secondary text-muted-foreground hover:text-foreground',
                    )}
                  >
                    {tab}
                  </button>
                ))}
              </div>
            )}

            <div
              ref={containerRef}
              className="relative skills-marquee"
              data-hold={hold ? 'true' : undefined}
              data-inview="true"
              onTouchStart={onTouchStart}
            >
              <div
                className="skills-marquee-viewport overflow-hidden pb-3"
                aria-label={`${active} skills`}
              >
                <div
                  className="skills-marquee-offset"
                  style={{ '--marquee-offset': `${offset}px` }}
                >
                <div
                  key={active}
                  ref={trackRef}
                  data-skills-track
                  className="skills-marquee-track flex gap-4 pr-4"
                  style={{ '--marquee-duration': `${duration}s` }}
                >
                  {Array.from({ length: copies }, (_, copy) =>
                    filtered.map((skill) => {
                      const isReal = copy === 0;
                      const cardKey =
                        skill.id ?? `${skill.category}-${skill.name}`;
                      return (
                        <div
                          key={isReal ? cardKey : `clone-${copy}-${cardKey}`}
                          data-skill-card={isReal ? 'true' : undefined}
                          data-skill-clone={isReal ? undefined : 'true'}
                          aria-hidden={isReal ? undefined : 'true'}
                          className="w-[calc(100cqw_-_2.5rem)] shrink-0 sm:w-[calc(50cqw_-_0.5rem)] lg:w-[calc(25cqw_-_0.75rem)]"
                        >
                          <SkillCard skill={skill} />
                        </div>
                      );
                    }),
                  )}
                </div>
                </div>
              </div>

              <button
                type="button"
                aria-label="Previous skills"
                onClick={(event) => move(-1, event)}
                className="absolute -left-3 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-background text-foreground shadow-lg transition hover:border-accent hover:text-accent disabled:pointer-events-none disabled:opacity-30 sm:-left-5"
              >
                <ChevronLeft className="h-5 w-5" aria-hidden="true" />
              </button>
              <button
                type="button"
                aria-label="Next skills"
                onClick={(event) => move(1, event)}
                className="absolute -right-3 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-background text-foreground shadow-lg transition hover:border-accent hover:text-accent disabled:pointer-events-none disabled:opacity-30 sm:-right-5"
              >
                <ChevronRight className="h-5 w-5" aria-hidden="true" />
              </button>
            </div>
          </>
        )}
      </div>
    </section>
  );
}
