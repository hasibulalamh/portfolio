import { describe, expect, it, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import '@testing-library/jest-dom/vitest'
import DashboardPage from '@/app/admin/dashboard/page'

// Mock the dependencies
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

describe('Dashboard Health Check', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders the system health card', async () => {
    mockApiCall.mockImplementation((method, endpoint) => {
      if (endpoint === '/admin/health') {
        return Promise.resolve({
          success: true,
          data: {
            status: 'healthy',
            checks: {
              database: { status: 'healthy', response_time_ms: 12 },
              storage: { status: 'healthy', response_time_ms: 45 },
            },
          },
        })
      }
      return Promise.resolve({ success: true, data: [] })
    })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText('System Health')).toBeInTheDocument()
    })
  })

  it('displays healthy status when all systems are up', async () => {
    mockApiCall.mockImplementation((method, endpoint) => {
      if (endpoint === '/admin/health') {
        return Promise.resolve({
          success: true,
          data: {
            status: 'healthy',
            checks: {
              database: { status: 'healthy', response_time_ms: 12 },
              storage: { status: 'healthy', response_time_ms: 45 },
            },
          },
        })
      }
      return Promise.resolve({ success: true, data: [] })
    })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText(/All systems healthy/i)).toBeInTheDocument()
    })
  })

  it('displays degraded status when storage is unavailable', async () => {
    mockApiCall.mockImplementation((method, endpoint) => {
      if (endpoint === '/admin/health') {
        return Promise.resolve({
          success: true,
          data: {
            status: 'degraded',
            checks: {
              database: { status: 'healthy', response_time_ms: 12 },
              storage: { status: 'down', response_time_ms: 5000 },
            },
          },
        })
      }
      return Promise.resolve({ success: true, data: [] })
    })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText(/Storage unavailable/i)).toBeInTheDocument()
    })
  })

  it('displays down status when database is unreachable', async () => {
    mockApiCall.mockImplementation((method, endpoint) => {
      if (endpoint === '/admin/health') {
        return Promise.resolve({
          success: true,
          data: {
            status: 'down',
            checks: {
              database: { status: 'down', response_time_ms: 5000 },
              storage: { status: 'healthy', response_time_ms: 45 },
            },
          },
        })
      }
      return Promise.resolve({ success: true, data: [] })
    })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText(/Database unreachable/i)).toBeInTheDocument()
    })
  })

  it('displays error message when API fails', async () => {
    mockApiCall.mockImplementation((method, endpoint) => {
      if (endpoint === '/admin/health') {
        return Promise.resolve({
          success: false,
          errorType: 'NETWORK',
        })
      }
      return Promise.resolve({ success: true, data: [] })
    })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText(/Unable to check system health/i)).toBeInTheDocument()
    })
  })

  it('shows loading state while fetching health data', () => {
    mockApiCall.mockImplementation((method, endpoint) => {
      if (endpoint === '/admin/health') {
        return new Promise(() => {}) // Never resolves
      }
      return Promise.resolve({ success: true, data: [] })
    })

    render(<DashboardPage />)

    // The Skeleton component should be shown initially
    const skeletons = document.querySelectorAll('.animate-pulse')
    expect(skeletons.length).toBeGreaterThan(0)
  })

  it('displays response times for database and storage', async () => {
    mockApiCall.mockImplementation((method, endpoint) => {
      if (endpoint === '/admin/health') {
        return Promise.resolve({
          success: true,
          data: {
            status: 'healthy',
            checks: {
              database: { status: 'healthy', response_time_ms: 42 },
              storage: { status: 'healthy', response_time_ms: 123 },
            },
          },
        })
      }
      return Promise.resolve({ success: true, data: [] })
    })

    render(<DashboardPage />)

    await waitFor(() => {
      expect(screen.getByText(/DB: 42ms/)).toBeInTheDocument()
      expect(screen.getByText(/Storage: 123ms/)).toBeInTheDocument()
    })
  })
})
