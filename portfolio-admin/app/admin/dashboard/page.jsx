'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/admin/Skeleton'
import { apiCall } from '@/lib/api'
import { useToast } from '@/components/ui/toast'
import { BarChart3, FileText, MessageSquare, Calendar, AlertCircle, CheckCircle2, AlertTriangle } from 'lucide-react'

const QUICK_ACTIONS = [
  { href: '/admin/settings', label: 'Update Site Settings' },
  { href: '/admin/hero', label: 'Edit Hero Section' },
  { href: '/admin/projects', label: 'Manage Projects' },
  { href: '/admin/testimonials', label: 'Add Testimonials' },
]

export default function DashboardPage() {
  const { showToast } = useToast()
  const [stats, setStats] = useState({
    projects: 0,
    testimonials: 0,
    messages: 0,
    meetings: 0,
  })
  const [health, setHealth] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isHealthLoading, setIsHealthLoading] = useState(true)

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
    </div>
  )
}
