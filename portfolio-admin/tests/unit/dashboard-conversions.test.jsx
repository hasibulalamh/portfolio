import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { cleanup, render, screen, waitFor } from '@testing-library/react'
import '@testing-library/jest-dom/vitest'
import DashboardPage from '@/app/admin/dashboard/page'

// The vitest config does not set globals: true, so @testing-library/react's
// automatic afterEach(cleanup) never registers and rendered DOM would leak
// between tests. Register it explicitly.

// Mirrors dashboard-health.test.jsx: mock everything the dashboard page pulls
// in, then drive the conversions endpoint per test.
vi.mock('@/lib/api', () => ({
  apiCall: vi.fn(),
}))

vi.mock('@/components/ui/toast', () => ({
  useToast: vi.fn(() => ({
    showToast: vi.fn(),
  })),
}))

vi.mock('next/link', () => {
  return {
    default: ({ children, ...props }) => <a {...props}>{children}</a>,
  }
})

import { apiCall } from '@/lib/api'

const mockApiCall = vi.mocked(apiCall)

// A valid /admin/health payload — the dashboard fetches it unconditionally and
// crashes rendering the response-time lines if the shape is wrong, so every
// test needs a real one behind that endpoint.
const HEALTH_DATA = {
  status: 'healthy',
  checks: {
    database: { status: 'healthy', response_time_ms: 12 },
    storage: { status: 'healthy', response_time_ms: 45 },
  },
}

function mockBackend({ conversions }) {
  mockApiCall.mockImplementation((method, endpoint) => {
    if (endpoint === '/admin/conversions') {
      return Promise.resolve(conversions)
    }
    if (endpoint === '/admin/health') {
      return Promise.resolve({ success: true, data: HEALTH_DATA })
    }
    // Stat cards — the dashboard only reads .length off these.
    return Promise.resolve({ success: true, data: [] })
  })
}

afterEach(cleanup)

describe('Dashboard Conversions card', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders one bar row per event type, strongest first', async () => {
    mockBackend({
      conversions: {
        success: true,
        data: {
          total: 7,
          by_type: {
            hire_me_click: 2,
            email_click: 0,
            whatsapp_click: 0,
            cv_download: 5,
            github_click: 0,
            linkedin_click: 0,
            contact_form_submit: 0,
          },
          period: 'all_time',
        },
      },
    })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText('Conversions')).toBeInTheDocument()
    })

    const rows = screen.getAllByTestId('conversion-row')
    expect(rows).toHaveLength(7)
    // Sorted by count descending; zero-count rows keep the backend's order.
    expect(rows.map((row) => row.dataset.eventType)).toEqual([
      'cv_download',
      'hire_me_click',
      'email_click',
      'whatsapp_click',
      'github_click',
      'linkedin_click',
      'contact_form_submit',
    ])
  })

  it('scales bar widths against the largest count, with a floor for nonzero rows', async () => {
    mockBackend({
      conversions: {
        success: true,
        data: {
          total: 7,
          by_type: {
            hire_me_click: 2,
            email_click: 0,
            whatsapp_click: 0,
            cv_download: 5,
            github_click: 0,
            linkedin_click: 0,
            contact_form_submit: 0,
          },
          period: 'all_time',
        },
      },
    })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText('CV downloads')).toBeInTheDocument()
    })

    const widthFor = (eventType) => {
      const row = screen
        .getAllByTestId('conversion-row')
        .find((row) => row.dataset.eventType === eventType)
      return row.querySelector('[data-testid="conversion-bar"]').style.width
    }

    // The largest count fills the track; 2/5ths of it renders at 40%; zeros
    // collapse rather than showing a sliver.
    expect(widthFor('cv_download')).toBe('100%')
    expect(widthFor('hire_me_click')).toBe('40%')
    expect(widthFor('email_click')).toBe('0%')
  })

  it('shows each row’s share of the total', async () => {
    mockBackend({
      conversions: {
        success: true,
        data: {
          total: 7,
          by_type: {
            hire_me_click: 2,
            email_click: 0,
            whatsapp_click: 0,
            cv_download: 5,
            github_click: 0,
            linkedin_click: 0,
            contact_form_submit: 0,
          },
          period: 'all_time',
        },
      },
    })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText('71% of total')).toBeInTheDocument()
    })

    // 5/7 and 2/7, rounded. Zero-count rows have no share to state.
    expect(screen.getByText('29% of total')).toBeInTheDocument()
    expect(screen.queryByText('0% of total')).not.toBeInTheDocument()
  })

  it('omits shares and renders empty bars when the table is empty', async () => {
    mockBackend({
      conversions: {
        success: true,
        data: {
          total: 0,
          by_type: {
            hire_me_click: 0,
            email_click: 0,
            whatsapp_click: 0,
            cv_download: 0,
            github_click: 0,
            linkedin_click: 0,
            contact_form_submit: 0,
          },
          period: 'all_time',
        },
      },
    })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText('Hire Me clicks')).toBeInTheDocument()
    })

    expect(screen.queryByText(/of total/)).not.toBeInTheDocument()

    for (const bar of screen.getAllByTestId('conversion-bar')) {
      expect(bar.style.width).toBe('0%')
    }
  })

  it('keeps the error state with no bars when the endpoint fails', async () => {
    mockBackend({
      conversions: { success: false, errorType: 'NETWORK' },
    })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText(/Unable to load conversion counts/i)).toBeInTheDocument()
    })

    expect(screen.queryByTestId('conversion-row')).not.toBeInTheDocument()
  })

  it('falls back to the raw type string for an unknown event type', async () => {
    mockBackend({
      conversions: {
        success: true,
        data: {
          total: 3,
          by_type: {
            hire_me_click: 0,
            email_click: 0,
            whatsapp_click: 0,
            cv_download: 0,
            github_click: 0,
            linkedin_click: 0,
            contact_form_submit: 0,
            new_backend_type: 3,
          },
          period: 'all_time',
        },
      },
    })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText('new_backend_type')).toBeInTheDocument()
    })
  })
})
