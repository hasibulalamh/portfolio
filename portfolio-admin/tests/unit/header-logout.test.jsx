import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import '@testing-library/jest-dom/vitest'
import { Header } from '@/components/admin/Header'

// The vitest config does not set globals: true, so @testing-library/react's
// automatic afterEach(cleanup) never registers — register it explicitly,
// same as the dashboard/storage tests.

vi.mock('@/lib/api', () => ({
  apiCall: vi.fn(),
}))

vi.mock('@/components/ui/toast', () => ({
  useToast: vi.fn(() => ({
    showToast: vi.fn(),
  })),
}))

import { apiCall } from '@/lib/api'

const mockApiCall = vi.mocked(apiCall)

const USER = { name: 'Admin', email: 'admin@example.com' }

function openUserMenu() {
  render(<Header user={USER} onLogout={vi.fn()} onToggleSidebar={vi.fn()} />)
  fireEvent.click(screen.getByRole('button', { name: /open account menu/i }))
  expect(screen.getByRole('menu')).toBeInTheDocument()
}

afterEach(() => {
  cleanup()
})

describe('Header user menu — Logout', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockApiCall.mockResolvedValue({ success: true, data: null, errorType: null })
  })

  it('fires the logout API call when the Logout item receives a real click sequence (mousedown → mouseup → click)', async () => {
    openUserMenu()

    const logout = screen.getByRole('menuitem', { name: /logout/i })

    // A real browser press fires mousedown first; only then mouseup + click.
    // jsdom's fireEvent.click alone skips the press phase, which is exactly
    // where the outside-click close logic races the item's own handler.
    fireEvent.mouseDown(logout)
    fireEvent.mouseUp(logout)
    fireEvent.click(logout)

    await waitFor(() => {
      expect(mockApiCall).toHaveBeenCalledWith('POST', '/logout')
    })
    expect(mockApiCall).toHaveBeenCalledTimes(1)
  })

  it('logs out when the press starts on the item but the release/click lands after the menu re-renders', async () => {
    openUserMenu()

    const logout = screen.getByRole('menuitem', { name: /logout/i })

    // Press starts on the item (the menu's document mousedown listener runs
    // here) and the click arrives afterwards — the handler must still fire
    // even if anything re-rendered the menu in between.
    fireEvent.mouseDown(logout)
    fireEvent.click(logout)

    await waitFor(() => {
      expect(mockApiCall).toHaveBeenCalledWith('POST', '/logout')
    })
  })

  it('closes the menu on an outside mousedown WITHOUT firing the logout call', async () => {
    openUserMenu()

    // Press outside the menu entirely.
    fireEvent.mouseDown(document.body)

    await waitFor(() => {
      expect(screen.queryByRole('menu')).not.toBeInTheDocument()
    })
    expect(mockApiCall).not.toHaveBeenCalled()
  })

  it('never treats a press on the Logout item as an outside click (stopPropagation hardening)', async () => {
    openUserMenu()

    const logout = screen.getByRole('menuitem', { name: /logout/i })

    // The document-level mousedown listener must not act on a press that
    // originates on a menu item, even before the item's click handler runs.
    fireEvent.mouseDown(logout)

    expect(screen.getByRole('menu')).toBeInTheDocument()

    fireEvent.click(logout)
    await waitFor(() => {
      expect(mockApiCall).toHaveBeenCalledWith('POST', '/logout')
    })
  })

  it('keeps the menu open while the press is inside the menu (no premature close before click)', () => {
    openUserMenu()

    const logout = screen.getByRole('menuitem', { name: /logout/i })
    fireEvent.mouseDown(logout)

    // The press phase alone must not unmount the item the user is pressing.
    expect(screen.getByRole('menu')).toBeInTheDocument()
    expect(screen.getByRole('menuitem', { name: /logout/i })).toBeInTheDocument()
  })
})
