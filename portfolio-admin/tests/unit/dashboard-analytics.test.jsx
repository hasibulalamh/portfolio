import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { cleanup, render, screen, waitFor } from '@testing-library/react'
import '@testing-library/jest-dom/vitest'
import DashboardPage from '@/app/admin/dashboard/page'

// Mirrors dashboard-health.test.jsx / dashboard-conversions.test.jsx: mock
// everything the dashboard page pulls in, then drive the GA4 endpoint per
// test. No test talks to the network.

// The vitest config does not set globals: true, so @testing-library/react's
// automatic afterEach(cleanup) never registers and rendered DOM would leak
// between tests. Register it explicitly.
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

// A valid GA4 summary payload, matching the backend's documented shape.
const GA4_DATA = {
  period: 'last_30_days',
  visitors: 1234,
  sessions: 1500,
  page_views: 4321,
  cached_at: '2026-09-23T12:00:00+00:00',
  cache_expires_at: '2026-09-23T13:00:00+00:00',
}

function mockBackend({ ga4 }) {
  mockApiCall.mockImplementation((method, endpoint) => {
    if (endpoint === '/admin/analytics/ga4') {
      return Promise.resolve(ga4)
    }
    if (endpoint === '/admin/health') {
      return Promise.resolve({ success: true, data: HEALTH_DATA })
    }
    if (endpoint === '/admin/conversions') {
      return Promise.resolve({
        success: true,
        data: { total: 0, by_type: {}, period: 'all_time' },
      })
    }
    // Stat cards — the dashboard only reads .length off these.
    return Promise.resolve({ success: true, data: [] })
  })
}

afterEach(cleanup)

describe('Dashboard GA4 Analytics card', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders visitors, sessions and page views with the period label', async () => {
    mockBackend({ ga4: { success: true, data: GA4_DATA } })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByTestId('analytics-visitors')).toHaveTextContent('1234')
      expect(screen.getByTestId('analytics-sessions')).toHaveTextContent('1500')
      expect(screen.getByTestId('analytics-page-views')).toHaveTextContent('4321')
    })
    expect(screen.getByTestId('analytics-period')).toHaveTextContent('Last 30 days')
  })

  it('shows the "as of" timestamp from cached_at, never "Invalid Date"', async () => {
    mockBackend({ ga4: { success: true, data: GA4_DATA } })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText(/as of /)).toBeInTheDocument()
    })

    const footer = screen.getByText(/as of /).textContent
    expect(footer).toContain('Last 30 days')
    expect(footer).not.toContain('Invalid Date')
  })

  it('shows "Analytics unavailable" when the endpoint fails, with no metrics', async () => {
    mockBackend({ ga4: { success: false, errorType: 'NETWORK' } })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText(/Analytics unavailable/i)).toBeInTheDocument()
    })

    expect(screen.queryByTestId('analytics-visitors')).not.toBeInTheDocument()
    expect(screen.queryByTestId('analytics-sessions')).not.toBeInTheDocument()
    expect(screen.queryByTestId('analytics-page-views')).not.toBeInTheDocument()
  })

  it('treats the backend\'s ga4_unavailable payload as the error state, not as metrics', async () => {
    // The backend answers 200 with a structured error payload when the GA4
    // API is down; the card must render the unavailable message rather than
    // trying to show numbers that don't exist. No fabricated zeros either.
    mockBackend({
      ga4: {
        success: true,
        data: { error: 'ga4_unavailable', message: 'Analytics service could not be reached.' },
      },
    })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText(/Analytics unavailable/i)).toBeInTheDocument()
    })

    expect(screen.queryByTestId('analytics-visitors')).not.toBeInTheDocument()
  })

  it('renders the raw period string for an unrecognized period value', async () => {
    mockBackend({
      ga4: {
        success: true,
        data: { ...GA4_DATA, period: 'this_week' },
      },
    })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText(/as of /)).toBeInTheDocument()
    })

    // Unknown period labels pass through untouched rather than being
    // silently relabeled into a window the data isn't for.
    expect(screen.getByTestId('analytics-period')).toHaveTextContent('this_week')
    expect(screen.queryByTestId('analytics-period')).not.toHaveTextContent('Last 30 days')
  })

  it('shows a loading skeleton while the GA4 request is in flight', () => {
    mockApiCall.mockImplementation((method, endpoint) => {
      if (endpoint === '/admin/analytics/ga4') {
        return new Promise(() => {}) // Never resolves
      }
      if (endpoint === '/admin/health') {
        return Promise.resolve({ success: true, data: HEALTH_DATA })
      }
      return Promise.resolve({ success: true, data: [] })
    })

    render(<DashboardPage />)

    const card = screen.getByTestId('analytics-card')
    expect(card.querySelector('.animate-pulse')).not.toBeNull()
    expect(screen.queryByTestId('analytics-visitors')).not.toBeInTheDocument()
  })
})
