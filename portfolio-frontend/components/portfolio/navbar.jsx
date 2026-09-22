'use client'

import Image from 'next/image'
import { useEffect, useMemo, useState } from 'react'
import { motion } from 'framer-motion'
import { Menu, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { resolveLogo } from '@/lib/logo'
import { trackEvent } from '@/lib/track'
import { TextLogo } from './text-logo'

export function Navbar({ navItems = [], settings = {} }) {
  const [scrolled, setScrolled] = useState(false)
  const [active, setActive] = useState('home')
  const [menuOpen, setMenuOpen] = useState(false)

  const links = Array.isArray(navItems) ? navItems : []

  // "Hire Me" is a shortcut to the contact section, so it only makes sense
  // while that section is on. navItems contains exactly the visible sections,
  // so its absence here means the anchor would point at nothing.
  const contactLink = links.find((link) => link.href === '#contact')

  // Which logo to render — uploaded image or styled wordmark — is an
  // admin-chosen setting. resolveLogo owns that decision and the fallback chain
  // so this component, the Footer, the admin Sidebar and the login screen cannot
  // drift apart. See lib/logo.js.
  const logo = resolveLogo(settings)

  // Scroll-spy only applies to same-page anchors, so the observed ids are
  // derived from the nav links rather than a fixed list — an admin-added
  // anchor gets highlighting for free.
  const sectionIds = useMemo(
    () =>
      links
        .map((link) => link.href)
        .filter((href) => typeof href === 'string' && href.startsWith('#'))
        .map((href) => href.slice(1))
        .filter(Boolean),
    [links],
  )

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 24)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) setActive(entry.target.id)
        })
      },
      { rootMargin: '-45% 0px -50% 0px' },
    )
    sectionIds.forEach((id) => {
      const el = document.getElementById(id)
      if (el) observer.observe(el)
    })
    return () => observer.disconnect()
  }, [sectionIds])

  return (
    <motion.header
      initial={{ y: -80, opacity: 0 }}
      animate={{ y: 0, opacity: 1 }}
      transition={{ duration: 0.5, ease: 'easeOut' }}
      className={cn(
        'fixed inset-x-0 top-0 z-50 transition-all duration-300',
        scrolled
          ? 'border-b border-border bg-background/70 backdrop-blur-xl'
          : 'border-b border-transparent bg-transparent',
      )}
    >
      <nav className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
        {/* The wordmark IS the brand element here — there is no logo-plus-name
            pairing to preserve, so an uploaded logo replaces it in this slot
            rather than sitting beside it. Width is intrinsic: h-10 with w-auto
            keeps the aspect ratio whatever the uploaded dimensions are, and the
            width/height props only give next/image its ratio hint. */}
        <a
          href="#home"
          className="font-heading text-xl font-bold tracking-tight text-foreground"
        >
          {logo.kind === 'image' ? (
            <Image
              src={logo.src}
              alt={logo.alt}
              width={160}
              height={40}
              priority
              // Constraining only one axis makes next/image warn that the
              // aspect ratio may be distorted; width:auto states that the
              // other axis is intentionally free.
              style={{ width: 'auto', height: 'auto' }}
              className="max-h-10 w-auto object-contain"
            />
          ) : (
            // `inherit` on purpose: the anchor above already sets the size and
            // weight for this slot, so the logo matches the header's scale.
            <TextLogo text={logo.text} />
          )}
        </a>

        <ul className="hidden items-center gap-1 md:flex">
          {links.map((link) => {
            const id = String(link.href ?? '').replace('#', '')
            const isActive = active === id
            return (
              <li key={link.id ?? link.href}>
                <a
                  href={link.href}
                  className={cn(
                    'relative rounded-full px-4 py-2 text-sm font-medium transition-colors duration-200',
                    isActive
                      ? 'text-foreground'
                      : 'text-muted-foreground hover:text-foreground',
                  )}
                >
                  {isActive && (
                    <motion.span
                      layoutId="nav-active"
                      className="absolute inset-0 -z-10 rounded-full bg-primary/15 ring-1 ring-primary/30"
                      transition={{ type: 'spring', stiffness: 380, damping: 30 }}
                    />
                  )}
                  {link.label}
                </a>
              </li>
            )
          })}
        </ul>

        <div className="flex items-center gap-2">
          {contactLink && (
            <a
              href={contactLink.href}
              onClick={() => trackEvent('hire_me_click')}
              className="hidden rounded-lg bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground transition-all duration-300 hover:glow-primary hover:brightness-110 md:inline-flex"
            >
              Hire Me
            </a>
          )}
          <button
            type="button"
            aria-label={menuOpen ? 'Close menu' : 'Open menu'}
            aria-expanded={menuOpen}
            onClick={() => setMenuOpen((v) => !v)}
            className="inline-flex h-10 w-10 items-center justify-center rounded-lg text-foreground transition-colors hover:bg-secondary md:hidden"
          >
            {menuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </button>
        </div>
      </nav>

      {menuOpen && (
        <motion.div
          initial={{ opacity: 0, height: 0 }}
          animate={{ opacity: 1, height: 'auto' }}
          exit={{ opacity: 0, height: 0 }}
          className="border-t border-border bg-background/95 backdrop-blur-xl md:hidden"
        >
          <ul className="flex flex-col gap-1 px-4 py-4">
            {links.map((link) => (
              <li key={link.id ?? link.href}>
                <a
                  href={link.href}
                  onClick={() => setMenuOpen(false)}
                  className="block rounded-lg px-4 py-3 text-sm font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                >
                  {link.label}
                </a>
              </li>
            ))}
            {contactLink && (
              <li>
                <a
                  href={contactLink.href}
                  onClick={() => {
                    setMenuOpen(false)
                    trackEvent('hire_me_click')
                  }}
                  className="mt-2 block rounded-lg bg-primary px-4 py-3 text-center text-sm font-semibold text-primary-foreground"
                >
                  Hire Me
                </a>
              </li>
            )}          </ul>
        </motion.div>
      )}
    </motion.header>
  )
}
