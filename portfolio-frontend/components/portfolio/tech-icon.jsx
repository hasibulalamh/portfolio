'use client'

import {
  getBrandColor,
  getDisplayBrandColor,
  getIconPath,
  iconSvgUrl,
  brandGlowStyle,
} from '@/lib/tech-icons'

/**
 * Glow for entries with no matched brand logo. Mirrors `brandGlowStyle`'s
 * contract, but sourced from the theme's violet accent (#818cf8) since there is
 * no brand colour to borrow — so a fallback still reads as a floating mark
 * rather than the one hard-edged box left in the grid.
 */
export const ACCENT_GLOW = {
  '--brand-glow': 'rgba(129, 140, 248, 0.22)',
  '--brand-glow-hover': 'rgba(129, 140, 248, 0.38)',
}

/**
 * Renders one Simple Icons brand mark from its slug.
 *
 * Two rendering paths, because path data for the full catalogue is far too
 * large to bundle:
 *   - preloaded slugs render as inline <svg>, so they can be recoloured with
 *     `currentColor` and need no network request;
 *   - everything else renders as <img> pointing at public/tech-icons/<slug>.svg.
 *     Those keep their own brand colours, which is what the file contains.
 *
 * Returns null for an unknown or empty slug so callers can use the standard
 * `<TechIcon .../> ?? fallback` shape without a wrapper check.
 *
 * `tone` picks which colour the mark is painted in:
 *   - 'brand'   the exact official colour (default; unchanged behaviour)
 *   - 'display' the official colour lightened only when it is too dark to read
 *               on the dark theme — what the tinted tiles use
 *   - 'current' inherit the surrounding text colour
 */
export function TechIcon({
  slug,
  title,
  className = 'h-5 w-5',
  colored = true,
  tone = colored ? 'brand' : 'current',
}) {
  if (!slug) return null

  const path = getIconPath(slug)
  const label = title || slug

  const fill =
    tone === 'current'
      ? 'currentColor'
      : (tone === 'display' ? getDisplayBrandColor(slug) : getBrandColor(slug)) ?? 'currentColor'

  if (!path) {
    // The packaged SVGs carry no fill attribute, so they paint black — which
    // disappears on a dark tile. Masking the file instead of drawing it lets
    // the same asset take any colour: the SVG supplies the shape, background
    // supplies the paint. Simple Icons marks are monochrome by definition, so
    // nothing is lost by flattening them to one colour.
    return (
      <span
        role="img"
        aria-label={label}
        className={className}
        style={{
          backgroundColor: fill === 'currentColor' ? 'currentColor' : fill,
          maskImage: `url(${iconSvgUrl(slug)})`,
          WebkitMaskImage: `url(${iconSvgUrl(slug)})`,
          maskRepeat: 'no-repeat',
          WebkitMaskRepeat: 'no-repeat',
          maskPosition: 'center',
          WebkitMaskPosition: 'center',
          maskSize: 'contain',
          WebkitMaskSize: 'contain',
          display: 'inline-block',
        }}
      />
    )
  }

  return (
    <svg
      role="img"
      aria-label={label}
      viewBox="0 0 24 24"
      className={className}
      fill={fill}
      xmlns="http://www.w3.org/2000/svg"
    >
      <title>{label}</title>
      <path d={path} />
    </svg>
  )
}

/**
 * A brand logo floating on the page background, lit by its own colour.
 *
 * There is deliberately no box here — no border, no fill, no rounded
 * rectangle. The only container is a blurred radial bloom sized larger than
 * the mark itself, which reads as ambient light rather than a card. The icon
 * stays crisp on top: only the bloom layer is blurred.
 *
 * Falls back to `null` for an unknown slug — callers already branch on
 * `icon_slug` and render their own neutral badge there.
 */
export function TechIconTile({ slug, title, logoType = 'library', logoUrl = null, className = '' }) {
  if (logoType === 'custom' && logoUrl) {
    return (
      <span
        className={`tech-glow group/tile relative flex h-12 w-12 items-center justify-center ${className}`}
        style={ACCENT_GLOW}
      >
        <span aria-hidden className="tech-glow__bloom" />
        <img
          src={logoUrl}
          alt={title || 'Custom technology logo'}
          className="relative h-8 w-8 object-contain"
        />
      </span>
    )
  }

  const glow = brandGlowStyle(slug)
  if (!glow) return null

  return (
    <span
      className={`tech-glow group/tile relative flex h-12 w-12 items-center justify-center ${className}`}
      style={glow}
    >
      {/*
        The bloom. Oversized relative to the icon (-inset-3) so its blurred
        edge falls off well outside the mark and never resolves into a shape
        with a discernible boundary. aria-hidden because it is pure decoration.
      */}
      <span aria-hidden className="tech-glow__bloom" />

      <TechIcon
        slug={slug}
        title={title}
        tone="display"
        className="relative h-full w-full"
      />
    </span>
  )
}
