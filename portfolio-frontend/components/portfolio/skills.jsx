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

const AUTOPLAY_INTERVAL_MS = 3000;
const TRANSITION_MS = 650;
const LOOP_TOLERANCE_PX = 4;

function getStep(track) {
  const card = track.querySelector('[data-skill-card]');
  if (!card) return 0;
  const gap = Number.parseFloat(getComputedStyle(track).columnGap) || 0;
  return card.getBoundingClientRect().width + gap;
}

export function Skills({ categories = [] }) {
  const [active, setActive] = useState('All');
  const [canPrevious, setCanPrevious] = useState(false);
  const [canNext, setCanNext] = useState(false);
  const trackRef = useRef(null);
  const sectionRef = useRef(null);

  const autoplayTimerRef = useRef(null);
  const scrollEndTimerRef = useRef(null);
  const hoverRef = useRef(false);
  const reducedMotionRef = useRef(false);
  const inViewRef = useRef(false);
  const transitioningRef = useRef(false);

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

  const clearAutoplay = useCallback(() => {
    if (autoplayTimerRef.current !== null) {
      clearTimeout(autoplayTimerRef.current);
      autoplayTimerRef.current = null;
    }
  }, []);

  const checkLoopSnap = useCallback(() => {
    const track = trackRef.current;
    if (!track) return;
    const step = getStep(track);
    if (step <= 0) return;
    const realCount = filtered.length;
    const boundary = realCount * step;
    if (track.scrollLeft >= boundary - LOOP_TOLERANCE_PX) {
      transitioningRef.current = true;
      track.scrollTo({
        left: Math.max(0, track.scrollLeft - boundary),
        behavior: 'auto',
      });
      requestAnimationFrame(() => {
        transitioningRef.current = false;
      });
    }
  }, [filtered]);

  const resetAutoplay = useCallback(() => {
    clearAutoplay();
    if (reducedMotionRef.current) return;
    if (hoverRef.current) return;
    if (!inViewRef.current) return;
    if (filtered.length < 2) return;
    autoplayTimerRef.current = setTimeout(() => {
      const track = trackRef.current;
      if (!track) {
        resetAutoplay();
        return;
      }
      const step = getStep(track);
      if (step <= 0) {
        resetAutoplay();
        return;
      }
      transitioningRef.current = true;
      track.scrollBy({
        left: step,
        behavior: reducedMotionRef.current ? 'auto' : 'smooth',
      });
      const settleMs = reducedMotionRef.current ? 120 : TRANSITION_MS + 80;
      setTimeout(() => {
        checkLoopSnap();
        transitioningRef.current = false;
        resetAutoplay();
      }, settleMs);
    }, AUTOPLAY_INTERVAL_MS);
  }, [clearAutoplay, checkLoopSnap, filtered]);

  const updateNavigation = useCallback(() => {
    const track = trackRef.current;
    if (!track) return;
    const step = getStep(track);
    const realCount = filtered.length;
    const maxScroll = realCount * step;
    const left = Math.min(track.scrollLeft, maxScroll - 1);
    setCanPrevious(left > 2);
    setCanNext(maxScroll - left > 2 + step * 0.25);
  }, [filtered]);

  const handleScroll = useCallback(() => {
    updateNavigation();
    if (transitioningRef.current) return;
    clearAutoplay();
    if (scrollEndTimerRef.current !== null) {
      clearTimeout(scrollEndTimerRef.current);
    }
    scrollEndTimerRef.current = setTimeout(() => {
      checkLoopSnap();
      resetAutoplay();
    }, 200);
  }, [updateNavigation, clearAutoplay, checkLoopSnap, resetAutoplay]);

  useEffect(() => {
    const track = trackRef.current;
    if (!track) return undefined;
    reducedMotionRef.current = window.matchMedia(
      '(prefers-reduced-motion: reduce)',
    ).matches;
    const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
    const onMotionChange = (e) => {
      reducedMotionRef.current = e.matches;
      if (e.matches) {
        clearAutoplay();
      } else {
        resetAutoplay();
      }
    };
    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', onMotionChange);
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(onMotionChange);
    }
    track.scrollTo({ left: 0, behavior: 'auto' });
    updateNavigation();
    track.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('resize', updateNavigation);
    return () => {
      track.removeEventListener('scroll', handleScroll);
      window.removeEventListener('resize', updateNavigation);
      if (typeof mq.removeEventListener === 'function') {
        mq.removeEventListener('change', onMotionChange);
      } else if (typeof mq.removeListener === 'function') {
        mq.removeListener(onMotionChange);
      }
    };
  }, [filtered, updateNavigation, handleScroll, clearAutoplay, resetAutoplay]);

  useEffect(() => {
    const section = sectionRef.current;
    if (!section) return undefined;
    const io = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          inViewRef.current = entry.isIntersecting;
          if (entry.isIntersecting) {
            resetAutoplay();
          } else {
            clearAutoplay();
          }
        }
      },
      { threshold: 0.1, rootMargin: '0px 0px -10% 0px' },
    );
    io.observe(section);
    return () => {
      io.disconnect();
    };
  }, [resetAutoplay, clearAutoplay]);

  useEffect(() => {
    if (tabs.includes(active)) return;
    setActive('All');
  }, [active, tabs]);

  useEffect(() => {
    resetAutoplay();
    return () => clearAutoplay();
  }, [resetAutoplay, clearAutoplay]);

  const move = (direction) => {
    const track = trackRef.current;
    if (!track) return;
    const step = getStep(track);
    const distance = step > 0 ? step : track.clientWidth;
    clearAutoplay();
    transitioningRef.current = true;
    track.scrollBy({
      left: direction * distance,
      behavior: reducedMotionRef.current ? 'auto' : 'smooth',
    });
    const settleMs = reducedMotionRef.current ? 180 : TRANSITION_MS + 120;
    setTimeout(() => {
      checkLoopSnap();
      transitioningRef.current = false;
      updateNavigation();
      resetAutoplay();
    }, settleMs);
  };

  const onMouseEnter = () => {
    hoverRef.current = true;
    clearAutoplay();
  };

  const onMouseLeave = () => {
    hoverRef.current = false;
    resetAutoplay();
  };

  const onTouchStart = () => {
    clearAutoplay();
  };

  const onTouchEnd = () => {
    setTimeout(() => {
      checkLoopSnap();
      resetAutoplay();
    }, 350);
  };

  const onWheel = () => {
    if (transitioningRef.current) return;
    clearAutoplay();
    if (scrollEndTimerRef.current !== null) {
      clearTimeout(scrollEndTimerRef.current);
    }
    scrollEndTimerRef.current = setTimeout(() => {
      checkLoopSnap();
      resetAutoplay();
    }, 260);
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
                    onClick={() => setActive(tab)}
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
              className="relative"
              onMouseEnter={onMouseEnter}
              onMouseLeave={onMouseLeave}
              onTouchStart={onTouchStart}
              onTouchEnd={onTouchEnd}
              onWheel={onWheel}
            >
              <div
                ref={trackRef}
                className="scrollbar-none flex snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain pb-3 [&::-webkit-scrollbar]:hidden"
                aria-label={`${active} skills`}
              >
                {filtered.map((skill) => (
                  <div
                    key={skill.id ?? `${skill.category}-${skill.name}`}
                    data-skill-card
                    className="w-[calc(100%-2.5rem)] shrink-0 snap-start sm:w-[calc(50%-0.5rem)] lg:w-[calc(25%-0.75rem)]"
                  >
                    <SkillCard skill={skill} />
                  </div>
                ))}
                {filtered.map((skill) => (
                  <div
                    key={`clone-${skill.id ?? `${skill.category}-${skill.name}`}`}
                    data-skill-clone
                    aria-hidden="true"
                    className="w-[calc(100%-2.5rem)] shrink-0 snap-start sm:w-[calc(50%-0.5rem)] lg:w-[calc(25%-0.75rem)]"
                  >
                    <SkillCard skill={skill} />
                  </div>
                ))}
              </div>

              <button
                type="button"
                aria-label="Previous skills"
                disabled={!canPrevious}
                onClick={() => move(-1)}
                className="absolute -left-3 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-background text-foreground shadow-lg transition hover:border-accent hover:text-accent disabled:pointer-events-none disabled:opacity-30 sm:-left-5"
              >
                <ChevronLeft className="h-5 w-5" aria-hidden="true" />
              </button>
              <button
                type="button"
                aria-label="Next skills"
                disabled={!canNext}
                onClick={() => move(1)}
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
