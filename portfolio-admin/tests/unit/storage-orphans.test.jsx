import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import '@testing-library/jest-dom/vitest'
import OrphanFilesPage from '@/app/admin/storage/orphans/page'

// The vitest config does not set globals: true, so @testing-library/react's
// automatic afterEach(cleanup) never registers and rendered DOM would leak
// between tests. Register it explicitly — same as the dashboard tests.

vi.mock('@/lib/api', () => ({
  apiCall: vi.fn(),
}))

vi.mock('@/components/ui/toast', () => ({
  useToast: vi.fn(() => ({
    showToast: vi.fn(),
  })),
}))

import { apiCall } from '@/lib/api'
import { useToast } from '@/components/ui/toast'

const mockApiCall = vi.mocked(apiCall)
const mockShowToast = vi.fn()

// A GET /admin/storage/orphans payload. orphan_candidates entries carry the
// path, byte size and ISO last_modified the page formats for display.
const scan = (candidates) => ({
  total_r2_files: candidates.length + 4,
  referenced_files: 4,
  orphan_candidates: candidates,
})

const orphan = (path, size, daysAgo) => ({
  path,
  size,
  last_modified: new Date(Date.now() - daysAgo * 86_400_000).toISOString(),
})

const SCAN_WITH_FILES = scan([
  orphan('logos/old-banner.png', 208_896, 3),
  orphan('cv/retired.pdf', 512, 60),
])

const SCAN_CLEAN = scan([])

/**
 * Mock apiCall with a queue of scan responses: the first GET gets
 * scans[0], the next GET scans[1], and so on (the last one repeats). This is
 * what makes the refetch-after-delete observable — the page's second load can
 * return a different list than its first.
 */
function mockBackend({ scans, deleteResult = { deleted: [], skipped: [] }, deleteSuccess = true }) {
  let scanIndex = 0
  mockApiCall.mockImplementation((method, endpoint) => {
    if (method === 'GET' && endpoint === '/admin/storage/orphans') {
      const index = Math.min(scanIndex, scans.length - 1)
      scanIndex += 1
      return Promise.resolve({ success: true, data: scans[index] })
    }
    if (method === 'DELETE' && endpoint === '/admin/storage/orphans') {
      return Promise.resolve(
        deleteSuccess
          ? { success: true, data: deleteResult }
          : { success: false, errorType: 'SERVER' },
      )
    }
    return Promise.resolve({ success: true, data: null })
  })
}

async function renderWithData(scans, options = {}) {
  mockBackend({ scans, ...options })
  render(<OrphanFilesPage />)
  await waitFor(() => {
    expect(screen.queryByText(/loading/i)).not.toBeInTheDocument()
  })
}
afterEach(() => {
  cleanup()
  mockShowToast.mockClear()
})

describe('Storage Orphans page', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockShowToast.mockReset()
    vi.mocked(useToast).mockReturnValue({ showToast: mockShowToast })
  })

  it('renders the summary counts and one row per orphan candidate', async () => {
    await renderWithData([SCAN_WITH_FILES])

    expect(screen.getByText('6')).toBeInTheDocument() // files on storage
    expect(screen.getByText('4')).toBeInTheDocument() // referenced
    expect(screen.getByText('2')).toBeInTheDocument() // orphan candidates

    const rows = screen.getAllByRole('row')
    // header row + 2 data rows
    expect(rows).toHaveLength(3)
    expect(screen.getByText('logos/old-banner.png')).toBeInTheDocument()
    expect(screen.getByText('cv/retired.pdf')).toBeInTheDocument()
  })

  it('formats sizes human-readably and last modified relatively', async () => {
    await renderWithData([SCAN_WITH_FILES])

    // 208896 bytes → "204 KB"; 512 bytes stays in "512 B".
    expect(screen.getByText('204 KB')).toBeInTheDocument()
    expect(screen.getByText('512 B')).toBeInTheDocument()

    // 3 days ago / 60 days → "2 months ago", via Intl.RelativeTimeFormat.
    expect(screen.getByText('3 days ago')).toBeInTheDocument()
    expect(screen.getByText('2 months ago')).toBeInTheDocument()
  })

  it('keeps the empty state with the delete button disabled when storage is clean', async () => {
    await renderWithData([SCAN_CLEAN])

    expect(
      screen.getByText('No orphaned files found — storage is clean'),
    ).toBeInTheDocument()

    const deleteButton = screen.getByRole('button', { name: /Delete Selected/ })
    expect(deleteButton).toBeDisabled()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('shows the error state with no table when the scan fails', async () => {
    await renderWithData([{ success: false, errorType: 'NETWORK' }])

    await waitFor(() => {
      expect(
        screen.getByText(/Unable to scan storage/i),
      ).toBeInTheDocument()
    })

    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('enables Delete Selected only once at least one row is checked', async () => {
    await renderWithData([SCAN_WITH_FILES])

    const deleteButton = screen.getByRole('button', { name: /Delete Selected/ })
    expect(deleteButton).toBeDisabled()

    const firstCheckbox = screen.getByLabelText('Select logos/old-banner.png')
    fireEvent.click(firstCheckbox)

    expect(screen.getByRole('button', { name: 'Delete Selected (1)' })).toBeEnabled()

    // Select all via the header checkbox, then clear it again.
    fireEvent.click(screen.getByLabelText('Select all orphaned files'))
    expect(screen.getByRole('button', { name: 'Delete Selected (2)' })).toBeEnabled()

    fireEvent.click(screen.getByLabelText('Select all orphaned files'))
    expect(screen.getByRole('button', { name: /Delete Selected/ })).toBeDisabled()
  })

  it('lists exactly the selected files in the confirm dialog with an undo warning', async () => {
    await renderWithData([SCAN_WITH_FILES])

    fireEvent.click(screen.getByLabelText('Select cv/retired.pdf'))
    fireEvent.click(screen.getByRole('button', { name: 'Delete Selected (1)' }))

    const dialog = screen.getByRole('alertdialog')
    expect(
      within(dialog).getByText(/This permanently deletes 1 file/),
    ).toBeInTheDocument()
    expect(within(dialog).getByText(/cv\/retired\.pdf/)).toBeInTheDocument()
    expect(within(dialog).getByText(/This cannot be undone/)).toBeInTheDocument()
  })

  it('deletes the selected paths, toasts the result, refetches and clears the selection', async () => {
    await renderWithData(
      [
        SCAN_WITH_FILES,
        // After the delete, only one file remains on the (refetched) list.
        scan([orphan('cv/retired.pdf', 512, 30)]),
      ],
      {
        deleteResult: {
          deleted: ['logos/old-banner.png'],
          skipped: [{ path: 'cv/retired.pdf', reason: 'now referenced' }],
        },
      },
    )

    fireEvent.click(screen.getByLabelText('Select logos/old-banner.png'))
    fireEvent.click(screen.getByRole('button', { name: 'Delete Selected (1)' }))

    const dialog = screen.getByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete' }))

    await waitFor(() => {
      expect(mockApiCall).toHaveBeenCalledWith('DELETE', '/admin/storage/orphans', {
        paths: ['logos/old-banner.png'],
      })
    })

    // Mixed result → warning toast with both counts.
    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(
        'Deleted 1 file, skipped 1',
        'warning',
      )
    })

    // The list was refetched and now shows only the surviving file; the
    // selection was cleared, so the delete button is disabled again.
    await waitFor(() => {
      expect(screen.getByText('cv/retired.pdf')).toBeInTheDocument()
    })
    expect(screen.queryByText('logos/old-banner.png')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Delete Selected/ })).toBeDisabled()
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })

  it('reports a clean success toast when nothing is skipped', async () => {
    await renderWithData(
      [
        scan([orphan('logos/lonely.png', 1024, 2)]),
        SCAN_CLEAN,
      ],
      { deleteResult: { deleted: ['logos/lonely.png'], skipped: [] } },
    )

    fireEvent.click(screen.getByLabelText('Select logos/lonely.png'))
    fireEvent.click(screen.getByRole('button', { name: 'Delete Selected (1)' }))

    fireEvent.click(
      within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Delete' }),
    )

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith('Deleted 1 file', 'success')
    })
  })

  it('keeps the list and reports an error toast when the delete call fails', async () => {
    await renderWithData([SCAN_WITH_FILES], { deleteSuccess: false })

    fireEvent.click(screen.getByLabelText('Select logos/old-banner.png'))
    fireEvent.click(screen.getByRole('button', { name: 'Delete Selected (1)' }))

    fireEvent.click(
      within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Delete' }),
    )

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(
        'Deletion failed — nothing was removed',
        'error',
      )
    })

    // Nothing was deleted server-side, so both files are still listed.
    expect(screen.getByText('logos/old-banner.png')).toBeInTheDocument()
    expect(screen.getByText('cv/retired.pdf')).toBeInTheDocument()
  })
})
