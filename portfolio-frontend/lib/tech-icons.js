/**
 * Shared Simple Icons lookup.
 *
 * Both the admin picker and the public sections need the same three things:
 * search the icon catalogue, resolve a slug to its brand colour, and resolve a
 * slug to SVG path data. Keeping that in one module means the admin can never
 * offer a slug the public site cannot render.
 *
 * The catalogue (`ICON_INDEX`) covers all 3453 icons but carries no path data,
 * because the full set is ~4.6 MB. Path data is preloaded for ~100 common
 * technologies (`ICON_PATHS`); anything else is fetched on demand from the
 * package's own SVG file, which Next serves as a static asset.
 *
 * Both files are generated — see portfolio-admin/scripts/generate-icon-index.mjs.
 */
import { ICON_INDEX } from './simple-icons-index'
import { ICON_PATHS } from './simple-icons-paths'

/** Fold a typed query onto the comparison form: "Vue.js" -> "vuejs". */
const normalise = (value = '') => value.toLowerCase().replace(/[^a-z0-9]/g, '')

// Precomputed once: the search runs on every keystroke, so normalising 3453
// titles per keypress would be wasteful.
const SEARCHABLE = ICON_INDEX.map(([slug, title, hex]) => ({
  slug,
  title,
  hex,
  nTitle: normalise(title),
  nSlug: normalise(slug),
}))

const BY_SLUG = new Map(SEARCHABLE.map((icon) => [icon.slug, icon]))

/** Metadata for one slug, or null when the slug is unknown. */
export function getIcon(slug) {
  if (!slug) return null
  return BY_SLUG.get(slug) ?? null
}

/** Official brand colour as a CSS hex string, or null when unknown. */
export function getBrandColor(slug) {
  const icon = getIcon(slug)
  return icon ? `#${icon.hex}` : null
}

/**
 * SVG path data for a slug, or null when it is not preloaded.
 *
 * A null here does not mean the icon does not exist — only that its path was
 * not bundled. Callers that can tolerate a network round trip should fall back
 * to `iconSvgUrl`.
 */
export function getIconPath(slug) {
  if (!slug) return null
  return ICON_PATHS[slug] ?? null
}

/**
 * URL of the icon's SVG, served straight out of the installed package.
 *
 * Next copies `public/` verbatim, so the generator does not need to duplicate
 * 15 MB of SVGs — see `scripts/copy-icon-svgs.mjs`, which links the subset the
 * picker can request.
 */
export function iconSvgUrl(slug) {
  return `/tech-icons/${slug}.svg`
}

/** "#1572B6" -> {r, g, b}, or null when the value is not a 6-digit hex. */
function toRgb(hex) {
  const match = /^#?([0-9a-f]{6})$/i.exec(hex || '')
  if (!match) return null

  const int = parseInt(match[1], 16)
  return { r: (int >> 16) & 255, g: (int >> 8) & 255, b: int & 255 }
}

/**
 * How light the brightest channel is, 0–1.
 *
 * Deliberately not perceived luminance. The question here is only "is this
 * colour so close to black that it vanishes on a dark background", and
 * luminance answers a different one: it scores any saturated dark-ish colour
 * low, so a threshold on it would wash out Laravel's red and CSS's purple even
 * though both read perfectly well already. The brightest channel isolates
 * near-blacks and leaves every saturated brand colour alone.
 */
function brightness({ r, g, b }) {
  return Math.max(r, g, b) / 255
}

/**
 * Brand colour adjusted to stay legible against the dark navy background.
 *
 * A lot of the catalogue is black or near-black by brand rule — Next.js,
 * Express, Vercel and GitHub are all #000000 or within a few points of it.
 * Painting those in their literal official colour on a dark section renders an
 * invisible icon, which is exactly the failure the old white tile existed to
 * avoid. Those are mixed toward white until they clear the threshold, which
 * keeps the hue neutral where the brand is neutral while making the mark
 * readable.
 *
 * Everything bright enough is returned untouched, so React's cyan, Laravel's
 * red and CSS's purple keep their exact official values.
 */
export function getDisplayBrandColor(slug) {
  const hex = getBrandColor(slug)
  const rgb = toRgb(hex)
  if (!rgb) return null

  const MIN = 0.55
  const current = brightness(rgb)
  if (current >= MIN) return hex

  // Mix toward white by exactly the amount that lands the brightest channel on
  // MIN. A pure black source needs the most; a dark grey needs very little.
  const ratio = (MIN - current) / (1 - current)
  const lift = (channel) => Math.round(channel + (255 - channel) * ratio)

  return `#${[lift(rgb.r), lift(rgb.g), lift(rgb.b)]
    .map((c) => c.toString(16).padStart(2, '0'))
    .join('')}`
}

/**
 * Custom properties for a brand-coloured ambient glow behind a logo.
 *
 * Deliberately not a tile: there is no background fill or border colour here,
 * because the logos float directly on the page background and any bounded
 * shape would defeat that. The consumer (.tech-glow in globals.css) paints
 * these into a blurred radial bloom.
 *
 * Inline rather than Tailwind arbitrary values because the colour is only
 * known at runtime — `bg-[${hex}]/20` cannot be generated, since Tailwind's
 * compiler only sees class strings that exist literally in the source.
 *
 * Returns null for an unknown slug so callers fall through to their own
 * fallback rendering.
 */
export function brandGlowStyle(slug) {
  const display = getDisplayBrandColor(slug)
  const rgb = toRgb(display)
  if (!rgb) return null

  const { r, g, b } = rgb

  return {
    '--brand-glow': `rgba(${r}, ${g}, ${b}, 0.22)`,
    '--brand-glow-hover': `rgba(${r}, ${g}, ${b}, 0.38)`,
    color: display,
  }
}

/**
 * Rank icons against a typed query.
 *
 * Ordering is deliberate: an exact slug match first (typing "go" must surface
 * Go, not Godot), then prefix matches, then substring. Without the tiering,
 * short queries bury the obvious answer under alphabetically-earlier noise.
 *
 * @param {string} query
 * @param {number} limit
 * @returns {Array<{slug: string, title: string, hex: string}>}
 */
export function searchIcons(query, limit = 12) {
  const q = normalise(query)
  if (!q) return []

  const exact = []
  const prefix = []
  const substring = []

  for (const icon of SEARCHABLE) {
    if (icon.nSlug === q || icon.nTitle === q) {
      exact.push(icon)
    } else if (icon.nTitle.startsWith(q) || icon.nSlug.startsWith(q)) {
      prefix.push(icon)
    } else if (icon.nTitle.includes(q) || icon.nSlug.includes(q)) {
      substring.push(icon)
    }

    // Cheap early exit: once every tier is saturated no later icon can
    // outrank what is already collected.
    if (exact.length >= limit) break
  }

  const byTitle = (a, b) => a.title.length - b.title.length || a.title.localeCompare(b.title)
  prefix.sort(byTitle)
  substring.sort(byTitle)

  return [...exact, ...prefix, ...substring]
    .slice(0, limit)
    .map(({ slug, title, hex }) => ({ slug, title, hex }))
}

/**
 * Classify search results without guessing at semantic relationships.
 *
 * Only an exact normalized title or slug is an exact logo match. Every other
 * search hit remains an intentional suggestion that the admin must click.
 */
export function getLogoMatch(query, results) {
  const normalizedQuery = normalise(query)
  const exact = (Array.isArray(results) ? results : []).filter(
    (icon) =>
      normalise(icon.title) === normalizedQuery ||
      normalise(icon.slug) === normalizedQuery,
  )

  return exact.length > 0
    ? { type: 'exact', exact, related: [] }
    : { type: normalizedQuery ? 'related' : 'none', exact: [], related: results || [] }
}
