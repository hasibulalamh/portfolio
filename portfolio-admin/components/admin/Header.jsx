'use client'

import { useEffect, useRef, useState } from 'react'
import { Menu, LogOut, User, ChevronDown } from 'lucide-react'
import { apiCall } from '@/lib/api'
import { useToast } from '@/components/ui/toast'

export function Header({ user, onLogout, onToggleSidebar }) {
  const [showUserMenu, setShowUserMenu] = useState(false)
  const [isLoggingOut, setIsLoggingOut] = useState(false)
  const { showToast } = useToast()
  const menuRef = useRef(null)

  // Close the menu on outside click or Escape.
  useEffect(() => {
    if (!showUserMenu) return

    // Capture the wrapper element for this effect's lifetime. Reading
    // menuRef.current inside the handler races a re-render that swaps or
    // unmounts the node between attaching the listener and the press, which
    // would make contains() fail and close the menu under a menu-item press.
    const menuEl = menuRef.current
    if (!menuEl) return

    const handlePointerDown = (e) => {
      if (!menuEl.contains(e.target)) {
        setShowUserMenu(false)
      }
    }
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') setShowUserMenu(false)
    }

    document.addEventListener('mousedown', handlePointerDown)
    document.addEventListener('keydown', handleKeyDown)
    return () => {
      document.removeEventListener('mousedown', handlePointerDown)
      document.removeEventListener('keydown', handleKeyDown)
    }
  }, [showUserMenu])

  const handleLogout = async () => {
    setIsLoggingOut(true)
    const result = await apiCall('POST', '/logout')
    setIsLoggingOut(false)

    // Show error if the logout call itself failed, but still log out locally.
    if (!result.success && result.errorType === 'NETWORK') {
      showToast("Can't reach server, but you've been logged out locally", 'error')
    } else if (!result.success) {
      showToast("Error logging out, but you've been logged out locally", 'error')
    }

    // Close deterministically as part of the action rather than relying on
    // the outside-click listener: the menu must never outlive the logout.
    setShowUserMenu(false)
    onLogout()
  }

  return (
    <header className="glass-header px-6 py-4 flex items-center justify-between">
      <button
        type="button"
        onClick={onToggleSidebar}
        aria-label="Toggle navigation menu"
        className="md:hidden p-2 hover:bg-muted rounded-lg"
      >
        <Menu className="w-5 h-5" aria-hidden="true" />
      </button>

      <div className="flex-1" />

      <div className="relative" ref={menuRef}>
        <button
          type="button"
          onClick={() => setShowUserMenu(!showUserMenu)}
          aria-label="Open account menu"
          aria-expanded={showUserMenu}
          aria-haspopup="menu"
          className="flex items-center gap-2 px-4 py-2 rounded-lg hover:bg-muted transition-colors"
        >
          <div className="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
            <User className="w-4 h-4 text-primary-foreground" aria-hidden="true" />
          </div>
          <span className="text-sm font-medium">{user?.name || 'Admin'}</span>
          <ChevronDown className="w-4 h-4 text-muted-foreground" aria-hidden="true" />
        </button>

        {showUserMenu && (
          <div role="menu" className="absolute right-0 mt-2 w-48 glass-card rounded-lg shadow-lg p-2 z-50">
            <div className="px-3 py-2 text-sm text-muted-foreground break-all">
              {user?.email}
            </div>
            <hr className="my-2 border-border" />
            <button
              type="button"
              role="menuitem"
              // A press on a menu item is by definition "inside" the menu:
              // keep it out of the document-level outside-close logic so the
              // item's own click handler always gets to run, even if a future
              // refactor moves this menu outside the ref-checked wrapper.
              onMouseDown={(e) => e.stopPropagation()}
              onClick={handleLogout}
              disabled={isLoggingOut}
              className="w-full flex items-center gap-2 px-3 py-2 text-sm text-destructive hover:bg-destructive/10 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <LogOut className="w-4 h-4" aria-hidden="true" />
              {isLoggingOut ? 'Logging out...' : 'Logout'}
            </button>
          </div>
        )}
      </div>
    </header>
  )
}
