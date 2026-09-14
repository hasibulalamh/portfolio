import { describe, expect, it } from 'vitest'
import {
  FALLBACK_SETTINGS,
  FALLBACK_SECTIONS,
  FALLBACK_HERO,
  FALLBACK_ABOUT,
  FALLBACK_CONTACT_INFO,
} from '../../lib/fallbacks.js'

/**
 * Fallback content used when the backend is unreachable.
 *
 * These are plain exported constants, not functions, so the test surface is
 * structural: every fallback carries the keys its consumers read, and the
 * values are in a usable state (no null where a string is expected, no missing
 * required fields). A consumer reading a key that does not exist would get
 * undefined and render nothing; a consumer getting null where it expects a
 * string would TypeError.
 */

describe('FALLBACK_SETTINGS', () => {
  it('carries every key the navbar and footer read', () => {
    const required = [
      'site_title', 'brand_name', 'accent_color',
      'logo_type', 'logo_text', 'logo_path', 'logo_alt', 'favicon_path',
    ]

    for (const key of required) {
      expect(FALLBACK_SETTINGS).toHaveProperty(key)
    }
  })

  it('has a valid hex accent colour', () => {
    expect(FALLBACK_SETTINGS.accent_color).toMatch(/^#[0-9a-fA-F]{6}$/)
  })

  it('has a non-empty brand_name for the logo fallback chain', () => {
    expect(FALLBACK_SETTINGS.brand_name.trim()).not.toBe('')
  })
})

describe('FALLBACK_SECTIONS', () => {
  it('is an array with at least one entry', () => {
    expect(Array.isArray(FALLBACK_SECTIONS)).toBe(true)
    expect(FALLBACK_SECTIONS.length).toBeGreaterThan(0)
  })

  it('every entry has a section_key, nav_href, is_visible, and order', () => {
    for (const section of FALLBACK_SECTIONS) {
      expect(section).toHaveProperty('section_key')
      expect(section).toHaveProperty('nav_href')
      expect(section).toHaveProperty('is_visible')
      expect(section).toHaveProperty('order')
    }
  })

  it('all entries default to visible so an API outage shows a full page', () => {
    for (const section of FALLBACK_SECTIONS) {
      expect(section.is_visible).toBe(true)
    }
  })

  it('nav_hrefs start with # for in-page anchoring', () => {
    for (const section of FALLBACK_SECTIONS) {
      expect(section.nav_href).toMatch(/^#/)
    }
  })

  it('orders are sequential from 0', () => {
    const orders = FALLBACK_SECTIONS.map((s) => s.order)
    expect(orders).toEqual(FALLBACK_SECTIONS.map((_, i) => i))
  })
})

describe('FALLBACK_HERO', () => {
  it('carries heading and subheading as non-empty strings', () => {
    expect(typeof FALLBACK_HERO.heading).toBe('string')
    expect(FALLBACK_HERO.heading.trim()).not.toBe('')
    expect(typeof FALLBACK_HERO.subheading).toBe('string')
    expect(FALLBACK_HERO.subheading.trim()).not.toBe('')
  })

  it('has a non-null image_path for the portrait', () => {
    expect(FALLBACK_HERO.image_path).toBeTruthy()
  })

  it('roles is a non-empty array of strings', () => {
    expect(Array.isArray(FALLBACK_HERO.roles)).toBe(true)
    expect(FALLBACK_HERO.roles.length).toBeGreaterThan(0)
    for (const role of FALLBACK_HERO.roles) {
      expect(typeof role).toBe('string')
    }
  })

  it('tech_badges have label and icon_slug keys', () => {
    expect(Array.isArray(FALLBACK_HERO.tech_badges)).toBe(true)
    for (const badge of FALLBACK_HERO.tech_badges) {
      expect(badge).toHaveProperty('label')
      expect(badge).toHaveProperty('icon_slug')
    }
  })

  it('social_links have platform and url keys', () => {
    expect(Array.isArray(FALLBACK_HERO.social_links)).toBe(true)
    for (const link of FALLBACK_HERO.social_links) {
      expect(link).toHaveProperty('platform')
      expect(link).toHaveProperty('url')
    }
  })
})

describe('FALLBACK_ABOUT', () => {
  it('has a non-empty bio_paragraph_1', () => {
    expect(FALLBACK_ABOUT.bio_paragraph_1.trim()).not.toBe('')
  })

  it('stats is an array of label-value pairs', () => {
    expect(Array.isArray(FALLBACK_ABOUT.stats)).toBe(true)
    expect(FALLBACK_ABOUT.stats.length).toBeGreaterThan(0)
    for (const stat of FALLBACK_ABOUT.stats) {
      expect(stat).toHaveProperty('label')
      expect(stat).toHaveProperty('value')
    }
  })
})

describe('FALLBACK_CONTACT_INFO', () => {
  it('has an email field', () => {
    expect(FALLBACK_CONTACT_INFO).toHaveProperty('email')
  })

  it('has a location field', () => {
    expect(FALLBACK_CONTACT_INFO).toHaveProperty('location')
  })
})
