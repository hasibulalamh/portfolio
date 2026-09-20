import { Analytics } from '@vercel/analytics/next'
import { Inter, Geist_Mono } from 'next/font/google'
import { getSettings } from '@/lib/api'
import './globals.css'

const inter = Inter({ variable: '--font-inter', subsets: ['latin'] })
const geistMono = Geist_Mono({
  variable: '--font-geist-mono',
  subsets: ['latin'],
})

const metadataBase = new URL(process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000')

const DEFAULT_TITLE = 'Hasibul Alam — Laravel & Vue.js Full-Stack Developer'

/**
 * Base metadata. generateMetadata() below spreads this and overrides the title
 * and favicon with whatever is configured in the admin panel, so a static
 * `metadata` export is deliberately not used — Next.js allows only one.
 */
const baseMetadata = {
  metadataBase,
  title: DEFAULT_TITLE,
  description:
    'Portfolio of Hasibul Alam, a Laravel + Vue.js full-stack developer from Dhaka, Bangladesh. Building scalable, high-performance web applications for the modern web.',
  keywords: [
    'Hasibul Alam',
    'Laravel Developer',
    'Vue.js Developer',
    'Full-Stack Engineer',
    'Web Development',
    'Dhaka',
    'Bangladesh',
  ],
  authors: [{ name: 'Hasibul Alam' }],
  creator: 'Hasibul Alam',
  // Icon files live in public/, so they have to be declared here — Next.js only
  // auto-detects icons placed in the app/ directory.
  icons: {
    icon: [
      { url: '/icon.svg', type: 'image/svg+xml' },
      {
        url: '/icon-light-32x32.png',
        type: 'image/png',
        sizes: '32x32',
        media: '(prefers-color-scheme: light)',
      },
      {
        url: '/icon-dark-32x32.png',
        type: 'image/png',
        sizes: '32x32',
        media: '(prefers-color-scheme: dark)',
      },
    ],
    shortcut: ['/icon-light-32x32.png'],
    apple: [{ url: '/apple-icon.png', sizes: '180x180', type: 'image/png' }],
  },
  openGraph: {
    type: 'website',
    locale: 'en_US',
    url: '/',
    siteName: 'Hasibul Alam - Developer',
    title: 'Hasibul Alam — Laravel & Vue.js Full-Stack Developer',
    description: 'Building scalable, high-performance web applications for the modern web.',
    images: [
      {
        url: '/og-image.png',
        width: 1200,
        height: 630,
        alt: 'Hasibul Alam - Full-Stack Developer Portfolio',
        type: 'image/png',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Hasibul Alam — Full-Stack Developer',
    description: 'Laravel + Vue.js developer building scalable web applications',
    images: ['/og-image.png'],
    creator: '@hasibulalamh',
  },
}

/**
 * Layer the admin-managed site title and favicon over the static defaults. If
 * the API is unreachable, getSettings() returns null and the defaults stand.
 *
 * `other['x-settings-source']` publishes whether the API actually answered for
 * this render. For a static/ISR page the verdict is baked into the HTML at
 * (re)build time, so `curl -s https://hasibulalam.com/ | grep x-settings-source`
 * reveals a dead-API build instantly — the production incident where the page
 * stayed 200 while silently serving fallback settings was invisible from the
 * outside. Same truth as /api-health, but visible on the document itself.
 */
export async function generateMetadata() {
  const settings = await getSettings()

  const title = settings?.site_title || DEFAULT_TITLE

  return {
    ...baseMetadata,
    title,
    openGraph: { ...baseMetadata.openGraph, title },
    // An uploaded favicon replaces the bundled icon set; otherwise keep the
    // light/dark PNG pair declared above.
    icons: settings?.favicon_path
      ? { icon: [{ url: settings.favicon_path }] }
      : baseMetadata.icons,
    other: { 'x-settings-source': settings ? 'live' : 'fallback' },
  }
}

export const viewport = {
  colorScheme: 'dark',
  themeColor: '#0f172a',
}

export default function RootLayout({ children }) {
  return (
    <html
      lang="en"
      className={`${inter.variable} ${geistMono.variable}`}
    >
      <body className="bg-background font-sans antialiased">
        {children}
        {process.env.NODE_ENV === 'production' && <Analytics />}
      </body>
    </html>
  )
}
