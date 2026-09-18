'use client'

import Image from 'next/image'
import { useEffect, useMemo, useRef, useState } from 'react'
import { motion } from 'framer-motion'
import { ArrowRight, Download } from 'lucide-react'
import { SocialIcon } from './social-icons'
import { usableSocialLinks } from '@/lib/social-platforms'
import { TechIcon } from './tech-icon'

function useTypewriter(words) {
  const [text, setText] = useState('')
  const [index, setIndex] = useState(0)
  const [deleting, setDeleting] = useState(false)

  useEffect(() => {
    // Defensive: callers pass a non-empty array, but an empty one would make
    // `words[index % 0]` NaN-indexed and undefined, and .slice() would throw.
    if (!words || words.length === 0) return

    const current = words[index % words.length]
    const speed = deleting ? 45 : 90
    let pauseTimeout
    const timeout = setTimeout(() => {
      const next = deleting
        ? current.slice(0, text.length - 1)
        : current.slice(0, text.length + 1)
      setText(next)
      if (!deleting && next === current) {
        pauseTimeout = setTimeout(() => setDeleting(true), 1400)
      } else if (deleting && next === '') {
        setDeleting(false)
        setIndex((i) => i + 1)
      }
    }, speed)
    return () => {
      clearTimeout(timeout)
      clearTimeout(pauseTimeout)
    }
  }, [text, deleting, index, words])

  return text
}

function Particles() {
  const dots = useMemo(
    () =>
      Array.from({ length: 14 }).map((_, i) => ({
        id: i,
        left: `${(i * 37) % 100}%`,
        top: `${(i * 53) % 100}%`,
        size: (i % 3) + 2,
        duration: 6 + (i % 5),
        delay: (i % 7) * 0.4,
      })),
    [],
  )
  return (
    <div aria-hidden className="pointer-events-none absolute inset-0 overflow-hidden">
      {dots.map((d) => (
        <motion.span
          key={d.id}
          className="absolute rounded-full bg-accent/40"
          style={{ left: d.left, top: d.top, width: d.size, height: d.size }}
          animate={{ y: [0, -18, 0], opacity: [0.2, 0.7, 0.2] }}
          transition={{
            duration: d.duration,
            delay: d.delay,
            repeat: Infinity,
            ease: 'easeInOut',
          }}
        />
      ))}
    </div>
  )
}

/**
 * The stats strip that used to sit at the bottom of this section now renders
 * under the About bio instead, so Hero no longer takes a `stats` prop.
 */
export function Hero({ hero = {} }) {
  const photoRef = useRef(null)
  const [mousePosition, setMousePosition] = useState({ x: 0, y: 0 })

  const {
    heading,
    subheading,
    cta_primary_text: ctaPrimaryText,
    cta_primary_link: ctaPrimaryLink,
    cta_secondary_text: ctaSecondaryText,
    cta_secondary_link: ctaSecondaryLink,
    image_path: imagePath,
    image_alt: imageAlt,
    cv_path: cvPath,
    // New admin-managed fields:
    roles,
    tech_badges: techBadges,
    is_available: isAvailable,
    availability_label: availabilityLabel,
    social_links: rawSocialLinks,
  } = hero

  // Fallback: an empty roles list degrades to one default string so the
  // typewriter never has an empty words array (which would leave the cursor
  // blinking over nothing). A single-item array cycles forever, so the
  // animation still reads as a typewriter even with just the heading.
  const rolesWords = (Array.isArray(roles) && roles.length > 0) ? roles : [heading || 'Developer']

  const typed = useTypewriter(rolesWords)

  // Evenly distribute badges around the orbit. The old code hardcoded four at
  // 0/90/180/270 degrees; any-count support is a one-liner.
  const badges = Array.isArray(techBadges) ? techBadges : []
  const angleStep = badges.length > 0 ? 360 / badges.length : 0
  const orbitBadges = badges.map((badge, i) => ({
    ...badge,
    angle: i * angleStep,
  }))

  // Social links — the `usableSocialLinks` helper drops rows with no URL or an
  // unknown platform, so an empty circle cannot render.
  const socialLinks = usableSocialLinks(rawSocialLinks)

  // Mouse parallax on the portrait. The listener is on `window` so the effect
  // still responds when the pointer is beside the photo rather than over it —
  // but that means it also fires while Hero is scrolled off-screen, where
  // `rect.top` is a large negative number and the raw offset blows up to a few
  // hundred pixels. Storing that while away meant the spring animated the
  // image back from far off-position on every return to the section, which
  // read as the portrait "dropping in" (and slid it out from under the orbit
  // ring, whose radius is fixed to the wrapper). Skip updates while the photo
  // is outside the viewport and clamp what we do store.
  useEffect(() => {
    const MAX_OFFSET = 12

    const handleMouseMove = (e) => {
      if (!photoRef.current) return
      const rect = photoRef.current.getBoundingClientRect()

      const onScreen =
        rect.bottom > 0 &&
        rect.top < window.innerHeight &&
        rect.right > 0 &&
        rect.left < window.innerWidth
      if (!onScreen) return

      const clamp = (v) => Math.max(-MAX_OFFSET, Math.min(MAX_OFFSET, v))
      setMousePosition({
        x: clamp((e.clientX - rect.left - rect.width / 2) / 20),
        y: clamp((e.clientY - rect.top - rect.height / 2) / 20),
      })
    }

    window.addEventListener('mousemove', handleMouseMove)
    return () => window.removeEventListener('mousemove', handleMouseMove)
  }, [])

  return (
    <section
      id="home"
      className="relative flex min-h-screen items-center overflow-hidden pt-24 pb-12 md:pb-16"
    >
      {/* Background layers */}
      <div aria-hidden className="absolute inset-0 grid-pattern opacity-60" />
      <div
        aria-hidden
        className="absolute -left-24 top-10 h-80 w-80 rounded-full bg-primary/30 blur-2xl sm:blur-3xl animate-blob"
      />
      <div
        aria-hidden
        className="absolute -right-20 bottom-0 h-96 w-96 rounded-full bg-accent/25 blur-2xl sm:blur-3xl animate-blob"
        style={{ '--blob-delay': '4s' }}
      />
      <Particles />

      {/* Scroll indicator */}
      <motion.div
        className="absolute bottom-8 left-1/2 -translate-x-1/2"
        animate={{ y: [0, 8, 0] }}
        transition={{ duration: 2, repeat: Infinity }}
      >
        <div className="flex flex-col items-center gap-2">
          <span className="text-xs text-secondary-foreground/70 uppercase tracking-widest">Scroll to explore</span>
          <svg className="h-5 w-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V3" />
          </svg>
        </div>
      </motion.div>

      <div className="relative mx-auto grid w-full max-w-6xl grid-cols-1 items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:grid-flow-dense">
        {/* Right — photo (mobile first) */}
        <motion.div
          ref={photoRef}
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.7, delay: 0.2 }}
          className="relative mx-auto flex h-64 w-64 items-center justify-center [--orbit-r:112px] sm:h-80 sm:w-80 sm:[--orbit-r:140px] lg:h-96 lg:w-96 lg:[--orbit-r:170px] lg:col-start-2 lg:row-start-1"
        >
          <motion.div
            animate={{
              x: mousePosition.x,
              y: mousePosition.y,
            }}
            transition={{ type: 'spring', damping: 10, stiffness: 100 }}
            className="absolute inset-0"
          >
            <div className="absolute inset-6 rounded-full bg-gradient-to-tr from-primary to-accent opacity-30 blur-lg sm:blur-xl" />
            <div className="absolute inset-0 animate-float">
              <div className="relative h-full w-full rounded-full bg-gradient-to-tr from-primary via-accent to-primary p-[3px] glow-primary">
                <div className="h-full w-full overflow-hidden rounded-full bg-background">
                  <Image
                    src={imagePath || '/hasibul-portrait.png'}
                    alt={imageAlt || 'Portrait of Hasibul Alam'}
                    width={420}
                    height={420}
                    priority
                    className="h-full w-full object-cover"
                  />
                </div>
              </div>
            </div>
          </motion.div>

          {/* Orbiting tech badges — the radius comes from the responsive
              --orbit-r custom property set on the portrait wrapper above, so
              badges sit on the portrait border at every breakpoint instead of
              being clipped on mobile.

              DELIBERATE: the rotation runs continuously from mount via
              initial/animate and is NOT gated on viewport visibility. Wrapping
              it in whileInView would restart each badge from its `initial`
              angle on every re-entry, snapping the ring back to its starting
              position. If CPU while off-screen ever becomes a concern, pause
              and resume the existing rotation state rather than re-triggering
              it, otherwise the snap comes back. The radius is a static CSS
              value per breakpoint — nothing measures the container at runtime,
              so a layout shift cannot perturb it. */}
          {orbitBadges.map((tech, i) => (
            <motion.div
              key={`${tech.label}-${i}`}
              className="absolute left-1/2 top-1/2 h-0 w-0"
              data-testid="orbit-badge"
              data-label={tech.label}
              data-angle={tech.angle}
              style={{ transformOrigin: 'center' }}
              initial={{ rotate: tech.angle }}
              animate={{ rotate: tech.angle + 360 }}
              transition={{ duration: 20, repeat: Infinity, ease: 'linear' }}
            >
              {/* The counter-rotation keeps each badge upright while the ring
                  turns. Both halves share one duration, so they stay locked. */}
              <motion.div
                className="absolute -top-6 left-[var(--orbit-r)] flex h-12 w-12 items-center justify-center rounded-full border border-accent/40 bg-background/80 text-xs font-semibold text-accent backdrop-blur-sm"
                animate={{ rotate: -(tech.angle + 360) }}
                transition={{ duration: 20, repeat: Infinity, ease: 'linear' }}
                title={tech.label}
              >
                {tech.logo_type === 'custom' && tech.logo_url ? (
                  <img src={tech.logo_url} alt={tech.label} className="h-6 w-6 object-contain" />
                ) : tech.icon_slug ? (
                  <TechIcon
                    slug={tech.icon_slug}
                    title={tech.label}
                    tone="display"
                    className="h-6 w-6"
                  />
                ) : (
                  tech.label
                )}
              </motion.div>
            </motion.div>
          ))}
        </motion.div>

        {/* Left */}
        <div className="text-center lg:text-left lg:col-start-1 lg:row-start-1">
          {/* Hidden outright when the admin marks themselves unavailable — a
              stale "Available for Work" is worse than no badge at all. */}
          {isAvailable && (
            <motion.span
              initial={{ opacity: 0, y: 12 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5 }}
              className="inline-flex items-center gap-2 rounded-full border border-border bg-secondary px-4 py-1.5 text-sm font-medium text-accent"
              data-testid="availability-badge"
            >
              <span className="relative flex h-2 w-2">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-400" />
              </span>
              {availabilityLabel?.trim() || 'Available for Work'}
            </motion.span>
          )}

          <motion.h1
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.1 }}
            className="mt-6 font-heading text-4xl font-bold leading-tight tracking-tight text-foreground sm:text-5xl lg:text-6xl"
          >
            Hi, I&apos;m{' '}
            <span className="text-gradient">{heading}</span>
          </motion.h1>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.2 }}
            className="mt-4 flex h-9 items-center justify-center text-xl font-semibold text-muted-foreground lg:justify-start sm:text-2xl"
          >
            <span className="text-foreground" data-testid="typed-role">{typed}</span>
            <span className="ml-1 inline-block h-6 w-0.5 animate-pulse bg-accent" />
          </motion.div>

          <motion.p
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
            className="mx-auto mt-6 max-w-md text-pretty leading-relaxed text-muted-foreground lg:mx-0"
          >
            {subheading}
          </motion.p>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.4 }}
            className="mt-8 flex flex-col items-center gap-3 sm:flex-row lg:justify-start"
          >
            {ctaPrimaryText && (
              <a
                href={ctaPrimaryLink || '#contact'}
                className="group glow-primary inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 font-semibold text-primary-foreground transition-all duration-300 hover:brightness-110 sm:w-auto"
              >
                {ctaPrimaryText}
                <ArrowRight className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" />
              </a>
            )}
            {ctaSecondaryText && (
              <a
                href={ctaSecondaryLink || '#projects'}
                className="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-border bg-transparent px-6 py-3 font-semibold text-foreground transition-all duration-300 hover:border-accent hover:bg-secondary sm:w-auto"
              >
                {ctaSecondaryText}
              </a>
            )}
            {/* Only shown once a CV has been uploaded through the admin panel.
                This previously pointed at /resume.txt, a file that stood in for
                a PDF that was never added. */}
            {cvPath && (
              <a
                href={cvPath}
                download
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-accent/30 bg-accent/5 px-6 py-3 font-semibold text-accent transition-all duration-300 hover:border-accent hover:bg-accent/10 sm:w-auto"
                title="Download CV/Resume"
              >
                <Download className="h-4 w-4" />
                Download CV
              </a>
            )}
          </motion.div>

          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.6, delay: 0.5 }}
            className="mt-8 flex items-center justify-center gap-3 lg:justify-start"
          >
            {socialLinks.map(({ platform, label, href }) => (
              <a
                key={platform}
                href={href}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={label}
                data-testid="hero-social-link"
                data-platform={platform}
                className="inline-flex h-11 w-11 items-center justify-center rounded-full border border-border bg-secondary text-muted-foreground transition-all duration-300 hover:-translate-y-1 hover:border-accent hover:text-accent"
              >
                <SocialIcon platform={platform} className="h-5 w-5" />
              </a>
            ))}
          </motion.div>
        </div>
      </div>
    </section>
  )
}
