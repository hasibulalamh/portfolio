'use client'

import { motion } from 'framer-motion'
import * as LucideIcons from 'lucide-react'
import { Plug } from 'lucide-react'
import { SectionHeading } from './reveal'
import { TechIconTile, ACCENT_GLOW } from './tech-icon'

/**
 * Resolve the lucide icon named in the admin panel (`icon_name`, e.g. "Zap").
 * Falls back to a neutral icon when the name is empty or does not match, so a
 * typo cannot crash the section.
 */
function resolveIcon(name) {
  if (!name) return Plug

  const candidate = LucideIcons[name]

  // Guard against non-component exports on the namespace object.
  return typeof candidate === 'function' || typeof candidate === 'object'
    ? candidate ?? Plug
    : Plug
}

/**
 * APIs & integrations. Each showcase carries a list of endpoint strings, shown
 * as code lines under the description.
 */
export function APIShowcase({ showcases = [] }) {
  const items = Array.isArray(showcases) ? showcases : []

  if (items.length === 0) {
    return null
  }

  return (
    <section id="apis" className="relative py-12 md:py-16">
      <div
        aria-hidden
        className="absolute left-1/3 top-0 h-64 w-64 -translate-x-1/2 rounded-full bg-accent/10 blur-2xl sm:blur-3xl"
      />
      <div className="relative mx-auto max-w-6xl px-4 sm:px-6">
        <SectionHeading
          eyebrow="Technical Expertise"
          title="APIs & Integrations"
        />

        <div className="grid grid-cols-1 gap-8 md:grid-cols-2 md:gap-10">
          {items.map((api, i) => {
            const Icon = resolveIcon(api.icon_name)
            const endpoints = Array.isArray(api.endpoints) ? api.endpoints : []

            return (
              <motion.div
                key={api.id ?? `${api.title}-${i}`}
                initial={{ opacity: 0, y: 30 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: '-60px' }}
                transition={{ duration: 0.5, delay: i * 0.1 }}
                className="group flex flex-col gap-4"
              >
                {/* Prefer the real brand logo; fall back to the lucide icon
                    with a glow for entries that predate the picker or describe
                    a concept with no brand mark. */}
                {api.logo_type === 'custom' && api.logo_url ? (
                  <TechIconTile logoType="custom" logoUrl={api.logo_url} title={api.title} />
                ) : api.icon_slug ? (
                  <TechIconTile slug={api.icon_slug} title={api.title} />
                ) : (
                  <span
                    className="tech-glow flex h-12 w-12 items-center justify-center text-accent"
                    style={ACCENT_GLOW}
                  >
                    <span aria-hidden className="tech-glow__bloom" />
                    <Icon className="relative h-6 w-6" aria-hidden="true" />
                  </span>
                )}

                <div>
                  <h3 className="font-heading text-lg font-bold text-foreground mb-2">
                    {api.title}
                  </h3>
                  {api.description && (
                    <p className="text-sm leading-relaxed text-muted-foreground mb-4">
                      {api.description}
                    </p>
                  )}

                  {endpoints.length > 0 && (
                    <div className="space-y-1">
                      {endpoints.map((endpoint, index) => (
                        <code
                          key={`${endpoint}-${index}`}
                          className="block rounded-full border border-border/40 bg-background/40 px-2.5 py-1 text-xs font-medium text-accent/90"
                        >
                          {endpoint}
                        </code>
                      ))}
                    </div>
                  )}
                </div>
              </motion.div>
            )
          })}
        </div>
      </div>
    </section>
  )
}
