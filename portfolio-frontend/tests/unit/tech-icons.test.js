import { describe, expect, it } from 'vitest'
import {
  brandGlowStyle,
  getBrandColor,
  getDisplayBrandColor,
  getIcon,
  getLogoMatch,
  iconSvgUrl,
  searchIcons,
} from '../../lib/tech-icons.js'

/**
 * The Simple Icons lookup and the near-black lift that keeps dark brand marks
 * visible on the site's dark background.
 *
 * The lift is the part worth testing numerically: it has a threshold, a mixing
 * ratio and a rounding step, and getting any of the three wrong produces either
 * an invisible logo (too little lift) or a washed-out one (too much) — both of
 * which look plausible in a screenshot and neither of which a shape-only
 * assertion would catch.
 */

/** Brightest channel of a hex colour, 0-1 — the same measure the lift targets. */
const brightness = (hex) => {
  const int = parseInt(hex.replace('#', ''), 16)
  return Math.max((int >> 16) & 255, (int >> 8) & 255, int & 255) / 255
}

describe('getIcon / getBrandColor', () => {
  it('resolves a known slug to its metadata', () => {
    expect(getIcon('laravel')).toMatchObject({ slug: 'laravel', title: 'Laravel' })
  })

  it('returns null for an unknown slug', () => {
    expect(getIcon('definitely-not-a-brand')).toBeNull()
    expect(getBrandColor('definitely-not-a-brand')).toBeNull()
  })

  it('returns null rather than throwing on empty input', () => {
    expect(getIcon(null)).toBeNull()
    expect(getIcon('')).toBeNull()
    expect(getBrandColor(undefined)).toBeNull()
  })

  it('formats the brand colour as a css hex string', () => {
    expect(getBrandColor('laravel')).toMatch(/^#[0-9a-f]{6}$/i)
  })
})

describe('getDisplayBrandColor', () => {
  it('returns saturated brand colours completely untouched', () => {
    // These are the slugs actually in use on the site. Any drift here means a
    // brand is being rendered in a colour its owner did not choose.
    for (const slug of ['laravel', 'react', 'html5', 'css', 'vuedotjs', 'redis']) {
      expect(getDisplayBrandColor(slug), `${slug} was altered`).toBe(getBrandColor(slug))
    }
  })

  it('lifts a near-black brand until it clears the legibility threshold', () => {
    // Next.js, Express, Vercel and GitHub are #000000 or within a few points of
    // it, and would render as an invisible mark on the dark background.
    for (const slug of ['nextdotjs', 'express', 'vercel', 'github']) {
      const original = getBrandColor(slug)
      const display = getDisplayBrandColor(slug)

      expect(brightness(original), `${slug} is not near-black any more`).toBeLessThan(0.55)
      expect(display, `${slug} was not lifted`).not.toBe(original)
      // Rounding can land a hair under the target, so allow one 8-bit step.
      expect(brightness(display), `${slug} is still too dark`).toBeGreaterThanOrEqual(0.55 - 1 / 255)
    }
  })

  it('lifts pure black to a neutral grey rather than tinting it', () => {
    // The mix is toward white on every channel by the same ratio, so a neutral
    // source stays neutral — a hue shift here would be a visible bug.
    const display = getDisplayBrandColor('vercel')
    const int = parseInt(display.replace('#', ''), 16)
    const [r, g, b] = [(int >> 16) & 255, (int >> 8) & 255, int & 255]

    expect(r).toBe(g)
    expect(g).toBe(b)
  })

  it('lifts only as far as needed, so a dark grey travels less than pure black', () => {
    // Both land on the same threshold; what differs is the distance covered.
    // A flat "mix 55% toward white" would move them by the same amount and
    // overshoot the lighter source, so the per-colour ratio is load-bearing.
    const travelled = (slug) =>
      brightness(getDisplayBrandColor(slug)) - brightness(getBrandColor(slug))

    expect(travelled('vercel')).toBeGreaterThan(travelled('github'))

    const landed = (slug) => brightness(getDisplayBrandColor(slug))

    expect(Math.abs(landed('vercel') - landed('github'))).toBeLessThan(0.01)
  })

  it('always returns a parseable 6-digit hex', () => {
    for (const slug of ['vercel', 'github', 'laravel', 'react']) {
      expect(getDisplayBrandColor(slug)).toMatch(/^#[0-9a-f]{6}$/i)
    }
  })

  it('returns null for an unknown slug so callers fall through', () => {
    expect(getDisplayBrandColor('definitely-not-a-brand')).toBeNull()
    expect(getDisplayBrandColor(null)).toBeNull()
  })
})

describe('brandGlowStyle', () => {
  it('emits both glow custom properties plus the icon colour', () => {
    const style = brandGlowStyle('laravel')

    expect(style).toHaveProperty('--brand-glow')
    expect(style).toHaveProperty('--brand-glow-hover')
    expect(style).toHaveProperty('color')
  })

  it('sets the resting glow at 0.22 alpha and the hover glow at 0.38', () => {
    // globals.css swaps one for the other on hover; the two alphas are the whole
    // hover affordance now that the tiles have no border or background.
    const style = brandGlowStyle('laravel')

    expect(style['--brand-glow']).toMatch(/^rgba\(\d+, \d+, \d+, 0\.22\)$/)
    expect(style['--brand-glow-hover']).toMatch(/^rgba\(\d+, \d+, \d+, 0\.38\)$/)
  })

  it('builds the glow from the lifted colour, not the literal brand colour', () => {
    // A glow painted in #000000 is not a glow at all.
    const style = brandGlowStyle('vercel')

    expect(style['--brand-glow']).not.toMatch(/rgba\(0, 0, 0,/)
    expect(style.color).toBe(getDisplayBrandColor('vercel'))
  })

  it('returns null for an unknown slug so the caller renders its own fallback', () => {
    expect(brandGlowStyle('definitely-not-a-brand')).toBeNull()
    expect(brandGlowStyle(null)).toBeNull()
  })
})

describe('searchIcons', () => {
  it('ranks an exact slug match first', () => {
    // Typing "go" must surface Go, not Godot.
    expect(searchIcons('go')[0].slug).toBe('go')
  })

  describe('getLogoMatch', () => {
    it('classifies an exact title match', () => {
      const results = searchIcons('Laravel')

      expect(getLogoMatch('Laravel', results)).toMatchObject({
        type: 'exact',
        exact: [{ slug: 'laravel', title: 'Laravel' }],
      })
    })

    it('accepts normalized official names such as Vue.js', () => {
      expect(getLogoMatch('Vue.js', searchIcons('Vue.js')).type).toBe('exact')
    })

    it('keeps related results as suggestions instead of an exact match', () => {
      const results = [{ slug: 'laravel', title: 'Laravel', hex: 'FF2D20' }]

      expect(getLogoMatch('MVC Architecture', results)).toEqual({
        type: 'related',
        exact: [],
        related: results,
      })
    })

    it('reports no match for an empty query', () => {
      expect(getLogoMatch('', [])).toEqual({
        type: 'none',
        exact: [],
        related: [],
      })
    })
  })

  it('matches case-insensitively and ignores punctuation', () => {
    // "Vue.js" folds to "vuejs", which is how the catalogue is searched.
    expect(searchIcons('Vue.js').some((i) => i.slug === 'vuedotjs')).toBe(true)
  })

  it('honours the limit', () => {
    expect(searchIcons('a', 5)).toHaveLength(5)
  })

  it('returns nothing for an empty query', () => {
    expect(searchIcons('')).toEqual([])
    expect(searchIcons('   ')).toEqual([])
    expect(searchIcons(undefined)).toEqual([])
  })

  it('returns only the three keys the picker renders', () => {
    for (const icon of searchIcons('react', 3)) {
      expect(Object.keys(icon).sort()).toEqual(['hex', 'slug', 'title'])
    }
  })
})

describe('iconSvgUrl', () => {
  it('points at the static asset route Next serves', () => {
    expect(iconSvgUrl('laravel')).toBe('/tech-icons/laravel.svg')
  })
})
