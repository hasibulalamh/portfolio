'use client'

import { useMemo, useState } from 'react'
import { AnimatePresence, motion } from 'framer-motion'
import { SectionHeading } from './reveal'
import { TechIconTile, ACCENT_GLOW } from './tech-icon'
import { cn } from '@/lib/utils'

/**
 * Two-letter badge for a skill, used when no icon is set in the admin panel.
 * "Vue.js" -> "Vu", "REST API" -> "RA".
 */
function abbreviate(name = '') {
  const words = name.trim().split(/[\s.]+/).filter(Boolean)

  if (words.length === 0) return '??'
  if (words.length === 1) {
    return words[0].slice(0, 2).replace(/^./, (c) => c.toUpperCase())
  }

  return (words[0][0] + words[1][0]).toUpperCase()
}

/**
 * Skills grouped by category. `categories` is the nested shape returned by
 * GET /api/skills — each category carries its own `skills` array.
 */
export function Skills({ categories = [] }) {
  const [active, setActive] = useState('All')

  const groups = Array.isArray(categories) ? categories : []

  // Flatten to a single list, tagging each skill with its category name so one
  // grid can render any filter selection.
  const skills = useMemo(
    () =>
      groups.flatMap((category) =>
        (Array.isArray(category.skills) ? category.skills : []).map((skill) => ({
          id: skill.id,
          name: skill.name,
          icon: skill.icon,
          icon_slug: skill.icon_slug,
          logo_type: skill.logo_type,
          logo_url: skill.logo_url,
          category: category.name,
        })),
      ),
    [groups],
  )

  const tabs = useMemo(
    () => ['All', ...groups.filter((c) => c.skills?.length > 0).map((c) => c.name)],
    [groups],
  )

  const filtered = skills.filter(
    (skill) => active === 'All' || skill.category === active,
  )

  return (
    <section id="skills" className="relative py-12 md:py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <SectionHeading eyebrow="What I Work With" title="My Tech Stack" />

        {skills.length === 0 ? (
          <p className="text-center text-muted-foreground">
            Skills are being updated. Check back soon.
          </p>
        ) : (
          <>
            {/* A lone "All" tab tells the visitor nothing, so only show the row
                once there is more than one category to switch between. */}
            {tabs.length > 2 && (
              <div className="mb-12 flex flex-wrap justify-center gap-2">
                {tabs.map((tab) => (
                  <button
                    key={tab}
                    type="button"
                    onClick={() => setActive(tab)}
                    aria-pressed={active === tab}
                    className={cn(
                      'rounded-full px-5 py-2 text-sm font-medium transition-all duration-300',
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

            <motion.div
              layout
              className="grid grid-cols-2 gap-6 sm:grid-cols-3 sm:gap-8 md:grid-cols-4 md:gap-10"
            >
              <AnimatePresence mode="popLayout">
                {filtered.map((skill) => (
                  <motion.div
                    key={skill.id ?? skill.name}
                    layout
                    initial={{ opacity: 0, scale: 0.85 }}
                    animate={{ opacity: 1, scale: 1 }}
                    exit={{ opacity: 0, scale: 0.85 }}
                    transition={{ duration: 0.3 }}
                    className="group flex flex-col items-center gap-2.5 text-center"
                  >
                    {/* A real brand logo when the admin picked one, otherwise
                        the initials badge. Both float on the page background
                        with only a glow — the fallback has no brand colour to
                        borrow, so it glows in the theme's own violet accent. */}
                    {skill.logo_type === 'custom' && skill.logo_url ? (
                      <TechIconTile logoType="custom" logoUrl={skill.logo_url} title={skill.name} />
                    ) : skill.icon_slug ? (
                      <TechIconTile slug={skill.icon_slug} title={skill.name} />
                    ) : (
                      <span
                        className="tech-glow flex h-12 w-12 items-center justify-center font-heading text-lg font-bold text-accent"
                        style={ACCENT_GLOW}
                      >
                        <span aria-hidden className="tech-glow__bloom" />
                        <span className="relative">
                          {skill.icon || abbreviate(skill.name)}
                        </span>
                      </span>
                    )}
                    <span className="font-medium text-foreground">{skill.name}</span>
                    <span className="text-xs uppercase tracking-wide text-muted-foreground">
                      {skill.category}
                    </span>
                  </motion.div>
                ))}
              </AnimatePresence>
            </motion.div>
          </>
        )}
      </div>
    </section>
  )
}
