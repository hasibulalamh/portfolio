'use client'

import { useEffect, useMemo, useState } from 'react'
import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/admin/Skeleton'
import { AlertDialog } from '@/components/ui/alert-dialog'
import { apiCall } from '@/lib/api'
import { useToast } from '@/components/ui/toast'
import { AlertCircle, CheckCircle2, FileWarning, HardDrive } from 'lucide-react'

/**
 * Human-readable file size, e.g. 204 KB / 1.5 MB.
 */
function formatSize(bytes) {
  if (!Number.isFinite(bytes) || bytes < 0) return '—'
  if (bytes < 1024) return `${bytes} B`
  const units = ['KB', 'MB', 'GB']
  let value = bytes
  let unit = 'B'
  for (const next of units) {
    if (value < 1024) break
    value /= 1024
    unit = next
  }
  return `${value >= 100 ? Math.round(value) : value.toFixed(1)} ${unit}`
}

/**
 * Relative last-modified time, e.g. "3 days ago". One threshold table: the
 * first limit the elapsed seconds fit under picks the unit, and each unit
 * carries its own divisor.
 */
const RELATIVE_UNITS = [
  { limit: 60, divisor: 1, unit: 'second' },
  { limit: 3600, divisor: 60, unit: 'minute' },
  { limit: 86400, divisor: 3600, unit: 'hour' },
  { limit: 604800, divisor: 86400, unit: 'day' },
  { limit: 2629800, divisor: 604800, unit: 'week' },
]

const MONTH_SECONDS = 2629800
const YEAR_SECONDS = 31557600

function formatRelative(iso) {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '—'

  const secondsAgo = Math.max(0, Math.round((Date.now() - date.getTime()) / 1000))
  const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' })

  for (const { limit, divisor, unit } of RELATIVE_UNITS) {
    if (secondsAgo < limit) {
      return rtf.format(-Math.max(1, Math.round(secondsAgo / divisor)), unit)
    }
  }

  if (secondsAgo < YEAR_SECONDS) {
    return rtf.format(-Math.max(1, Math.round(secondsAgo / MONTH_SECONDS)), 'month')
  }

  return rtf.format(-Math.round(secondsAgo / YEAR_SECONDS), 'year')
}

export default function OrphanFilesPage() {
  const { showToast } = useToast()
  const [scan, setScan] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [selected, setSelected] = useState(new Set())
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)

  const loadOrphans = async () => {
    setIsLoading(true)
    try {
      const result = await apiCall('GET', '/admin/storage/orphans')
      if (result.success && result.data?.total_r2_files !== undefined) {
        setScan(result.data)
      } else {
        setScan({ error: result.errorType || 'unknown' })
        showToast('Unable to scan storage for orphaned files', 'error')
      }
    } catch {
      setScan({ error: 'unknown' })
      showToast('Unable to scan storage for orphaned files', 'error')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadOrphans()
    // Runs once on mount; showToast is stable across renders.
  }, [showToast])

  const orphans = scan && !scan.error ? scan.orphan_candidates ?? [] : []

  // Clear selections that no longer exist after a refetch.
  useEffect(() => {
    setSelected((prev) => {
      const valid = new Set(orphans.map((orphan) => orphan.path))
      const next = new Set([...prev].filter((path) => valid.has(path)))
      return next.size === prev.size ? prev : next
    })
  }, [orphans])

  const toggleAll = () => {
    setSelected((prev) =>
      prev.size === orphans.length ? new Set() : new Set(orphans.map((orphan) => orphan.path)),
    )
  }

  const toggleOne = (path) => {
    setSelected((prev) => {
      const next = new Set(prev)
      if (next.has(path)) {
        next.delete(path)
      } else {
        next.add(path)
      }
      return next
    })
  }

  const handleDelete = async () => {
    setIsDeleting(true)
    try {
      const result = await apiCall('DELETE', '/admin/storage/orphans', {
        paths: [...selected],
      })

      if (result.success) {
        const { deleted = [], skipped = [] } = result.data ?? {}
        if (skipped.length > 0) {
          showToast(`Deleted ${deleted.length} file${deleted.length === 1 ? '' : 's'}, skipped ${skipped.length}`, 'warning')
        } else {
          showToast(`Deleted ${deleted.length} file${deleted.length === 1 ? '' : 's'}`, 'success')
        }
        setDeleteDialogOpen(false)
        setSelected(new Set())
        await loadOrphans()
      } else {
        showToast('Deletion failed — nothing was removed', 'error')
      }
    } catch {
      showToast('Deletion failed — nothing was removed', 'error')
    } finally {
      setIsDeleting(false)
    }
  }

  const allChecked = orphans.length > 0 && selected.size === orphans.length

  const selectedPaths = useMemo(() => [...selected], [selected])

  if (isLoading) {
    return (
      <div>
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-foreground">Storage Orphans</h1>
          <p className="text-muted-foreground mt-2">
            Uploaded files that no content references any more
          </p>
        </div>
        <Card className="p-6">
          <Skeleton className="h-6 w-full mb-4" />
          <Skeleton className="h-6 w-5/6 mb-4" />
          <Skeleton className="h-6 w-4/6" />
        </Card>
      </div>
    )
  }

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-foreground">Storage Orphans</h1>
        <p className="text-muted-foreground mt-2">
          Uploaded files that no content references any more
        </p>
      </div>

      {scan?.error ? (
        <Card className="p-6">
          <div className="flex items-center gap-2">
            <AlertCircle className="w-5 h-5 text-red-600 dark:text-red-400" aria-hidden="true" />
            <span className="text-sm font-medium text-red-600 dark:text-red-400">
              Unable to scan storage. Please try again shortly.
            </span>
          </div>
        </Card>
      ) : (
        <>
          {/* Summary strip, mirroring the dashboard stat cards. */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <Card className="p-6">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-sm text-muted-foreground">Files on storage</p>
                  <p className="text-2xl font-bold mt-2">{scan.total_r2_files}</p>
                </div>
                <div className="p-3 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400">
                  <HardDrive className="w-6 h-6" aria-hidden="true" />
                </div>
              </div>
            </Card>
            <Card className="p-6">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-sm text-muted-foreground">Referenced by content</p>
                  <p className="text-2xl font-bold mt-2">{scan.referenced_files}</p>
                </div>
                <div className="p-3 rounded-lg bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400">
                  <CheckCircle2 className="w-6 h-6" aria-hidden="true" />
                </div>
              </div>
            </Card>
            <Card className="p-6">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-sm text-muted-foreground">Orphan candidates</p>
                  <p className="text-2xl font-bold mt-2">{orphans.length}</p>
                </div>
                <div className="p-3 rounded-lg bg-orange-50 text-orange-600 dark:bg-orange-900/20 dark:text-orange-400">
                  <FileWarning className="w-6 h-6" aria-hidden="true" />
                </div>
              </div>
            </Card>
          </div>

          <Card className="p-6 mt-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-bold">Orphaned files</h2>
              <button
                type="button"
                disabled={selected.size === 0 || isDeleting}
                onClick={() => setDeleteDialogOpen(true)}
                className="px-4 py-2 text-sm rounded-lg text-white bg-destructive hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                Delete Selected{selected.size > 0 ? ` (${selected.size})` : ''}
              </button>
            </div>

            {orphans.length === 0 ? (
              <p className="text-sm text-muted-foreground py-8 text-center">
                No orphaned files found — storage is clean
              </p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-muted-foreground border-b border-border">
                      <th scope="col" className="py-2 pr-3 w-10">
                        <input
                          type="checkbox"
                          aria-label="Select all orphaned files"
                          checked={allChecked}
                          onChange={toggleAll}
                        />
                      </th>
                      <th scope="col" className="py-2 pr-3 font-medium">File</th>
                      <th scope="col" className="py-2 pr-3 font-medium">Size</th>
                      <th scope="col" className="py-2 font-medium">Last modified</th>
                    </tr>
                  </thead>
                  <tbody>
                    {orphans.map((orphan) => (
                      <tr key={orphan.path} className="border-b border-border/50">
                        <td className="py-2.5 pr-3">
                          <input
                            type="checkbox"
                            aria-label={`Select ${orphan.path}`}
                            checked={selected.has(orphan.path)}
                            onChange={() => toggleOne(orphan.path)}
                          />
                        </td>
                        <td className="py-2.5 pr-3 font-mono text-xs break-all">
                          {orphan.path}
                        </td>
                        <td className="py-2.5 pr-3 whitespace-nowrap tabular-nums">
                          {formatSize(orphan.size)}
                        </td>
                        <td className="py-2.5 whitespace-nowrap text-muted-foreground">
                          {formatRelative(orphan.last_modified)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Card>
        </>
      )}

      <AlertDialog
        open={deleteDialogOpen}
        onOpenChange={setDeleteDialogOpen}
        title="Delete orphaned files"
        description={
          `This permanently deletes ${selectedPaths.length} file${selectedPaths.length === 1 ? '' : 's'} ` +
          `from storage: ${selectedPaths.join(', ')}. This cannot be undone. Continue?`
        }
        onConfirm={handleDelete}
        confirmText="Delete"
        isDestructive={true}
      />
    </div>
  )
}
