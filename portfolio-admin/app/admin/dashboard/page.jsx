'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/admin/Skeleton'
import { apiCall } from '@/lib/api'
import { useToast } from '@/components/ui/toast'
import { BarChart3, FileText, MessageSquare, Calendar, AlertCircle, CheckCircle2, AlertTriangle, Target } from 'lucide-react'

const QUICK_ACTIONS = [
  { href: '/admin/settings', label: 'Update Site Settings' },
  { href: '/admin/hero', label: 'Edit Hero Section' },
  { href: '/admin/projects', label: 'Manage Projects' },
  { href: '/admin/testimonials', label: 'Add Testimonials' },
]

// Labels for the tracked event types, keyed by the backend's allow-list values.
// Any key not listed here falls back to its raw value, so a type added on the
// backend later still renders instead of vanishing.
const CONVERSION_LABELS = {
  hire_me_click: 'Hire Me clicks',
  email_click: 'Email clicks',
  whatsapp_click: 'WhatsApp clicks',
  cv_download: 'CV downloads',
  github_click: 'GitHub clicks',
  linkedin_click: 'LinkedIn clicks',
  contact_form_submit: 'Contact form submissions',
}

export default function DashboardPage() {
  const { showToast } = useToast()
  const [stats, setStats] = useState({
    projects: 0,
    testimonials: 0,
    messages: 0,
    meetings: 0,
  })
  const [health, setHealth] = useState(null)
  const [conversions, setConversions] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isHealthLoading, setIsHealthLoading] = useState(true)
  const [isConversionsLoading, setIsConversionsLoading] = useState(true)

  useEffect(() => {
    const loadStats = async () => {
      try {
        const results = await Promise.allSettled([
          apiCall('GET', '/admin/projects'),
          apiCall('GET', '/admin/testimonials'),
          apiCall('GET', '/admin/messages'),
          apiCall('GET', '/admin/meeting-requests'),
        ])

        const countOf = (result) =>
          result.status === 'fulfilled' && Array.isArray(result.value?.data)
            ? result.value.data.length
            : 0

        setStats({
          projects: countOf(results[0]),
          testimonials: countOf(results[1]),
          messages: countOf(results[2]),
          meetings: countOf(results[3]),
        })

        const failed = results.filter(
          (result) => result.status === 'rejected' || result.value?.success === false
        )
        if (failed.length === results.length) {
          showToast("Couldn't load dashboard stats — showing zeros", 'error')
        } else if (failed.length > 0) {
          showToast(`Couldn't load ${failed.length} of ${results.length} stats`, 'error')
        }
      } catch (error) {
        showToast("Couldn't load dashboard stats", 'error')
        console.error('Failed to load dashboard stats:', error)
      } finally {
        setIsLoading(false)
      }
    }

    loadStats()
    // Runs once on mount; showToast is stable across renders.
  }, [showToast])

  useEffect(() => {
    const loadHealth = async () => {
      try {
        const result = await apiCall('GET', '/admin/health')
        if (result.success) {
          setHealth(result.data)
        } else {
          setHealth({ error: result.errorType || 'unknown' })
        }
      } catch (error) {
        setHealth({ error: 'unknown' })
        console.error('Failed to load health check:', error)
      } finally {
        setIsHealthLoading(false)
      }
    }

    loadHealth()
  }, [])

  useEffect(() => {
    const loadConversions = async () => {
      try {
        const result = await apiCall('GET', '/admin/conversions')
        if (result.success) {
          setConversions(result.data)
        } else {
          setConversions({ error: result.errorType || 'unknown' })
        }
      } catch (error) {
        setConversions({ error: 'unknown' })
        console.error('Failed to load conversions:', error)
      } finally {
        setIsConversionsLoading(false)
      }
    }

    loadConversions()
  }, [])

  // Rows for the Conversions card. Bar length is scaled against the largest
  // count — not the total — so one dominant type doesn't flatten every other
  // bar into invisibility; the exact share of the total travels alongside as
  // text instead. Bars are sorted strongest-first; the sort is stable, so
  // equal counts keep the backend's allow-list order.
  const conversionEntries = conversions && !conversions.error && conversions.by_type
    ? Object.entries(conversions.by_type)
    : []
  const conversionTotal = conversions && !conversions.error
    ? conversions.total ?? 0
    : 0
  const maxConversionCount = Math.max(0, ...conversionEntries.map(([, count]) => count))

  const conversionRows = conversionEntries
    .map(([type, count]) => ({
      type,
      count,
      label: CONVERSION_LABELS[type] ?? type,
      // Share of total, but only for rows that have clicks: a 0/0 division on
      // a fresh install would render NaN, and "0% of total" next to a zero
      // count is noise — the row already says 0.
      share:
        conversionTotal > 0 && count > 0
          ? Math.round((count / conversionTotal) * 100)
          : null,
      // 2% floor for any nonzero count keeps a single click visible next to
      // hundreds; zero-count bars collapse to nothing, which is the honest
      // rendering of zero.
      width:
        maxConversionCount > 0 && count > 0
          ? Math.max(2, Math.round((count / maxConversionCount) * 1000) / 10)
          : 0,
    }))
    .sort((a, b) => b.count - a.count)

  const getHealthStatusDisplay = () => {
    if (isHealthLoading) {
      return { message: 'Checking...', icon: null, color: 'text-gray-500' }
    }

    if (!health) {
      return { message: 'Unable to check system health', icon: AlertCircle, color: 'text-red-600 dark:text-red-400' }
    }

    if (health.error) {
      return { message: 'Unable to check system health', icon: AlertCircle, color: 'text-red-600 dark:text-red-400' }
    }

    const { status, checks } = health
    let icon = CheckCircle2
    let color = 'text-green-600 dark:text-green-400'
    let message = 'All systems healthy'

    if (status === 'down') {
      icon = AlertCircle
      color = 'text-red-600 dark:text-red-400'
      if (checks.database.status === 'down') {
        message = 'Database unreachable'
      }
    } else if (status === 'degraded') {
      icon = AlertTriangle
      color = 'text-yellow-600 dark:text-yellow-400'
      if (checks.database.status === 'down') {
        message = 'Database unreachable'
      } else if (checks.storage.status === 'down') {
        message = 'Storage unavailable'
      } else if (checks.database.response_time_ms > 1000) {
        message = 'Database slow'
      } else if (checks.storage.response_time_ms > 1000) {
        message = 'Storage slow'
      }
    }

    return { message, icon, color }
  }

  const statCards = [
    {
      label: 'Projects',
      value: stats.projects,
      icon: BarChart3,
      color: 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400',
    },
    {
      label: 'Testimonials',
      value: stats.testimonials,
      icon: MessageSquare,
      color: 'bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400',
    },
    {
      label: 'Messages',
      value: stats.messages,
      icon: FileText,
      color: 'bg-purple-50 text-purple-600 dark:bg-purple-900/20 dark:text-purple-400',
    },
    {
      label: 'Meeting Requests',
      value: stats.meetings,
      icon: Calendar,
      color: 'bg-orange-50 text-orange-600 dark:bg-orange-900/20 dark:text-orange-400',
    },
  ]

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-foreground">Dashboard</h1>
        <p className="text-muted-foreground mt-2">Welcome to your portfolio CMS admin panel</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {statCards.map((stat) => {
          const Icon = stat.icon
          return (
            <Card
              key={stat.label}
              className="p-6 hover:shadow-lg transition-shadow"
            >
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-sm text-muted-foreground">{stat.label}</p>
                  {isLoading ? (
                    <Skeleton className="h-8 w-12 mt-2" />
                  ) : (
                    <p className="text-2xl font-bold mt-2">{stat.value}</p>
                  )}
                </div>
                <div className={`p-3 rounded-lg ${stat.color}`}>
                  <Icon className="w-6 h-6" aria-hidden="true" />
                </div>
              </div>
            </Card>
          )
        })}
      </div>

      <div className="mt-12 grid grid-cols-1 lg:grid-cols-3 gap-6">
        <Card className="p-6">
          <h2 className="text-lg font-bold mb-4">Quick Actions</h2>
          <div className="space-y-3">
            {/* Link, not <a>: a plain anchor forces a full page reload and
                re-runs the whole auth check. */}
            {QUICK_ACTIONS.map((action) => (
              <Link
                key={action.href}
                href={action.href}
                className="block p-3 border border-border rounded-lg hover:bg-muted transition-colors text-sm"
              >
                → {action.label}
              </Link>
            ))}
          </div>
        </Card>

        <Card className="p-6">
          <h2 className="text-lg font-bold mb-4">Content Sections</h2>
          <div className="space-y-2 text-sm text-muted-foreground">
            <p>✓ Site Settings &amp; Navigation</p>
            <p>✓ Hero Section &amp; About</p>
            <p>✓ Skills &amp; Timeline</p>
            <p>✓ Projects &amp; Case Studies</p>
            <p>✓ Testimonials &amp; Contact Info</p>
            <p>✓ Message &amp; Meeting Inboxes</p>
          </div>
        </Card>

        <Card className="p-6">
          <h2 className="text-lg font-bold mb-4">System Health</h2>
          <div className="space-y-4">
            {isHealthLoading ? (
              <Skeleton className="h-6 w-full" />
            ) : (
              <>
                {(() => {
                  const { message, icon: StatusIcon, color } = getHealthStatusDisplay()
                  return (
                    <div className="flex items-center gap-2">
                      {StatusIcon && <StatusIcon className={`w-5 h-5 ${color}`} aria-hidden="true" />}
                      <span className={`text-sm font-medium ${color}`}>● {message}</span>
                    </div>
                  )
                })()}
                {health && !health.error && (
                  <div className="text-xs text-muted-foreground space-y-1">
                    <p>DB: {health.checks.database.response_time_ms}ms</p>
                    <p>Storage: {health.checks.storage.response_time_ms}ms</p>
                  </div>
                )}
              </>
            )}
          </div>
        </Card>
      </div>

      {/* Conversions — aggregate CTA click counts. All-time only: the tracking
          feature records no period dimension, so there is no range to pick. */}
      <div className="mt-6">
        <Card className="p-6" data-testid="conversions-card">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-bold">Conversions</h2>
            <Target className="w-5 h-5 text-muted-foreground" aria-hidden="true" />
          </div>
          {isConversionsLoading ? (
            <Skeleton className="h-8 w-24" />
          ) : !conversions || conversions.error ? (
            <div className="flex items-center gap-2">
              <AlertCircle className="w-5 h-5 text-red-600 dark:text-red-400" aria-hidden="true" />
              <span className="text-sm font-medium text-red-600 dark:text-red-400">
                Unable to load conversion counts
              </span>
            </div>
          ) : (
            <>
              <div className="flex items-baseline gap-2">
                <p className="text-3xl font-bold">{conversions.total ?? 0}</p>
                <span className="text-sm text-muted-foreground">
                  total clicks ({conversions.period === 'all_time' ? 'all time' : conversions.period})
                </span>
              </div>
              {conversionRows.length > 0 && (
                <ul className="mt-4 space-y-3 text-sm">
                  {conversionRows.map((row) => (
                    <li key={row.type} data-testid="conversion-row" data-event-type={row.type}>
                      <div className="flex items-baseline justify-between gap-2">
                        <span className="text-muted-foreground">{row.label}</span>
                        <span className="font-medium tabular-nums">
                          {row.count}
                          {row.share !== null && (
                            <span className="ml-1.5 text-xs font-normal text-muted-foreground">
                              {row.share}% of total
                            </span>
                          )}
                        </span>
                      </div>
                      {/* Decorative: the numbers above carry the data, so the
                          bar is hidden from the accessibility tree. */}
                      <div
                        className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-muted"
                        aria-hidden="true"
                      >
                        <div
                          data-testid="conversion-bar"
                          className="h-full rounded-full bg-primary transition-[width] duration-500"
                          style={{ width: `${row.width}%` }}
                        />
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </>
          )}
        </Card>
      </div>
    </div>
  )
}
