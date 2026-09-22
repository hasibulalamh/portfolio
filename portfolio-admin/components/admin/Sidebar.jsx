'use client'

import Link from 'next/link'
// Aliased: lucide-react also exports an `Image` icon, which this file already
// uses for the Hero Section menu item.
import NextImage from 'next/image'
import { usePathname } from 'next/navigation'
import { Menu, X, Settings, Layout, Image, Users, Clock, Zap, MessageSquare, Mail, Palette, ChevronDown, HardDrive } from 'lucide-react'
import { useState } from 'react'
import { useSettings } from '@/lib/settings'
import { resolveLogo } from '@/lib/logo'
import { TextLogo } from '@/components/ui/text-logo'

const menuItems = [
  { label: 'Dashboard', href: '/admin/dashboard', icon: Settings },
  {
    label: 'Site Settings',
    icon: Settings,
    submenu: [
      { label: 'General', href: '/admin/settings' },
      { label: 'Sections', href: '/admin/settings/sections' },
    ],
  },
  { label: 'Hero Section', href: '/admin/hero', icon: Image },
  { label: 'About', href: '/admin/about', icon: Users },
  { label: 'Skills', href: '/admin/skills', icon: Zap },
  { label: 'Timeline', href: '/admin/timeline', icon: Clock },
  { label: 'Projects', href: '/admin/projects', icon: Palette },
  { label: 'API Showcase', href: '/admin/api-showcase', icon: Layout },
  { label: 'Testimonials', href: '/admin/testimonials', icon: MessageSquare },
  {
    label: 'Contact',
    icon: Mail,
    submenu: [
      { label: 'Info', href: '/admin/contact-info' },
      { label: 'Messages', href: '/admin/messages' },
      { label: 'Meeting Requests', href: '/admin/meeting-requests' },
    ],
  },
  { label: 'Storage Orphans', href: '/admin/storage/orphans', icon: HardDrive },
]

export function Sidebar({ isOpen, onToggle }) {
  const pathname = usePathname()
  const [expandedMenu, setExpandedMenu] = useState(null)
  const settings = useSettings()

  // Same helper and same fallback chain the public site uses, so the panel's
  // wordmark matches what visitors see. See lib/logo.js.
  const logo = resolveLogo(settings)

  const isActive = (href) => {
    return pathname === href || pathname.startsWith(href + '/')
  }

  const toggleSubmenu = (label) => {
    setExpandedMenu(expandedMenu === label ? null : label)
  }

  return (
    <>
      {/* Mobile toggle button */}
      <button
        type="button"
        onClick={onToggle}
        aria-label={isOpen ? 'Close navigation menu' : 'Open navigation menu'}
        aria-expanded={isOpen}
        aria-controls="admin-sidebar-nav"
        className="md:hidden fixed top-4 left-4 z-50 p-2 rounded-lg bg-card border border-border"
      >
        {isOpen ? <X className="w-5 h-5" aria-hidden="true" /> : <Menu className="w-5 h-5" aria-hidden="true" />}
      </button>

      {/* Sidebar */}
      <aside
        className={`${
          isOpen ? 'w-64' : 'w-0'
        } glass-sidebar transition-all duration-300 overflow-y-auto hidden md:block`}
        aria-hidden={!isOpen}
      >
        <div className="p-6">
          {/* Same pattern as the public site's navbar and footer: an uploaded
              logo replaces the text wordmark, and the styled wordmark is what
              renders whenever the logo type is text. */}
          <h1 className="text-xl font-bold">
            {logo.kind === 'image' ? (
              <NextImage
                src={logo.src}
                alt={logo.alt}
                width={160}
                height={40}
                // See the matching note on the login page: constraining only one
                // axis makes next/image warn about aspect ratio unless the other
                // is explicitly auto.
                style={{ width: 'auto', height: 'auto' }}
                className="max-h-9 w-auto object-contain"
              />
            ) : (
              <TextLogo text={logo.text} />
            )}
          </h1>
        </div>

        <nav id="admin-sidebar-nav" aria-label="Admin sections" className="px-3 py-4 space-y-2">
          {menuItems.map((item) => {
            const submenuId = `submenu-${item.label.replace(/\s+/g, '-').toLowerCase()}`

            return (
              <div key={item.label}>
                {item.submenu ? (
                  <div>
                    <button
                      type="button"
                      onClick={() => toggleSubmenu(item.label)}
                      aria-expanded={expandedMenu === item.label}
                      aria-controls={submenuId}
                      className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg transition-colors ${
                        expandedMenu === item.label
                          ? 'bg-accent text-accent-foreground'
                          : 'text-foreground hover:bg-muted'
                      }`}
                    >
                      <item.icon className="w-4 h-4" aria-hidden="true" />
                      <span className="flex-1 text-left text-sm font-medium">{item.label}</span>
                      <ChevronDown
                        aria-hidden="true"
                        className={`w-4 h-4 transition-transform ${
                          expandedMenu === item.label ? 'rotate-180' : ''
                        }`}
                      />
                    </button>
                    {expandedMenu === item.label && (
                      <div id={submenuId} className="ml-3 mt-1 space-y-1 border-l border-border pl-3">
                        {item.submenu.map((subitem) => (
                          <Link
                            key={subitem.href}
                            href={subitem.href}
                            aria-current={isActive(subitem.href) ? 'page' : undefined}
                            className={`block px-3 py-2 rounded text-sm transition-colors ${
                              isActive(subitem.href)
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground hover:bg-muted'
                            }`}
                          >
                            {subitem.label}
                          </Link>
                        ))}
                      </div>
                    )}
                  </div>
                ) : (
                  <Link
                    href={item.href}
                    aria-current={isActive(item.href) ? 'page' : undefined}
                    className={`flex items-center gap-3 px-3 py-2 rounded-lg transition-colors relative ${
                      isActive(item.href)
                        ? 'text-primary'
                        : 'text-foreground hover:text-primary'
                    }`}
                  >
                    {isActive(item.href) && (
                      <div aria-hidden="true" className="absolute left-0 top-0 bottom-0 w-1 bg-primary rounded-r-md" />
                    )}
                    <item.icon className="w-4 h-4" aria-hidden="true" />
                    <span className="text-sm font-medium">{item.label}</span>
                  </Link>
                )}
              </div>
            )
          })}
        </nav>
      </aside>

      {/* Mobile sidebar overlay */}
      {isOpen && (
        <div
          className="fixed inset-0 bg-black/50 md:hidden z-40"
          onClick={onToggle}
          aria-hidden="true"
        />
      )}
    </>
  )
}
