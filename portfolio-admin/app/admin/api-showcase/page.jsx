'use client'

import { useCallback, useEffect, useState } from 'react'
import { useForm, useFieldArray } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { apiShowcaseSchema } from '@/lib/validation'
import { apiCall } from '@/lib/api'
import { useToast } from '@/components/ui/toast'
import { AlertDialog } from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { EmptyState } from '@/components/admin/EmptyState'
import { ListSkeleton } from '@/components/admin/Skeleton'
import { TechIconPicker } from '@/components/admin/TechIconPicker'
import { TechIcon } from '@/components/admin/TechIcon'
import { Edit2, Trash2, Plus, Layout } from 'lucide-react'

export default function ApiShowcasePage() {
  const { showToast } = useToast()
  const [items, setItems] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState(false)
  const [editingId, setEditingId] = useState(null)
  const [showForm, setShowForm] = useState(false)
  const [deleteDialog, setDeleteDialog] = useState({ open: false, id: null })

  const {
    register,
    handleSubmit,
    reset,
    control,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(apiShowcaseSchema),
    defaultValues: { icon_name: '', icon_slug: null, logo_type: 'library', logo_url: null, title: '', description: '', endpoints: ['', ''], order: 0 },
  })

  const iconSlug = watch('icon_slug')
  const logoType = watch('logo_type')
  const logoUrl = watch('logo_url')
  const title = watch('title')

  const { fields, append, remove } = useFieldArray({
    control,
    name: 'endpoints',
  })

  const loadItems = useCallback(async () => {
    setIsLoading(true)
    const result = await apiCall('GET', '/admin/api-showcases')
    if (result.success) {
      setItems(Array.isArray(result.data) ? result.data : [])
      setLoadError(false)
    } else {
      setLoadError(true)
      showToast(result.message || 'Failed to load API showcases', 'error')
    }
    setIsLoading(false)
  }, [showToast])

  useEffect(() => {
    loadItems()
  }, [loadItems])

  const onSubmit = async (data) => {
    // Drop the blank endpoint rows the form starts with.
    const payload = {
      ...data,
      endpoints: (data.endpoints || []).map((e) => (e || '').trim()).filter(Boolean),
    }

    let result
    if (editingId) {
      result = await apiCall('PUT', `/admin/api-showcases/${editingId}`, payload)
    } else {
      result = await apiCall('POST', '/admin/api-showcases', { ...payload, order: items.length })
    }

    if (result.success) {
      showToast(editingId ? 'Showcase updated' : 'Showcase created', 'success')
      setEditingId(null)
      setShowForm(false)
      reset()
      await loadItems()
    } else {
      showToast(result.message || 'Failed to save', 'error')
    }
  }

  const handleDelete = async (id) => {
    const result = await apiCall('DELETE', `/admin/api-showcases/${id}`)
    if (result.success) {
      showToast('Showcase deleted', 'success')
      await loadItems()
    } else {
      showToast(result.message || 'Failed to delete', 'error')
    }
    setDeleteDialog({ open: false, id: null })
  }

  const handleEdit = (item) => {
    reset({
      icon_name: item.icon_name || '',
      icon_slug: item.icon_slug || null,
      logo_type: item.logo_type || 'library',
      logo_url: item.logo_url || null,
      title: item.title || '',
      description: item.description || '',
      endpoints: Array.isArray(item.endpoints) && item.endpoints.length > 0 ? item.endpoints : [''],
      order: item.order ?? 0,
    })
    setEditingId(item.id)
    setShowForm(true)
  }

  return (
    <div>
      <div className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">API Showcase</h1>
          <p className="text-muted-foreground mt-2">Highlight your API endpoints</p>
        </div>
        <Button onClick={() => {
          setEditingId(null)
          reset()
          setShowForm(!showForm)
        }}>
          <Plus className="w-4 h-4 mr-2" aria-hidden="true" />
          Add Showcase
        </Button>
      </div>

      {showForm && (
        <Card className="mb-6">
          <CardHeader>
            <CardTitle>{editingId ? 'Edit Showcase' : 'Add Showcase'}</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
              <TechIconPicker
                value={iconSlug}
                onChange={(slug) => setValue('icon_slug', slug, { shouldDirty: true })}
                logoType={logoType}
                logoUrl={logoUrl}
                onLogoChange={(type, url) => {
                  setValue('logo_type', type, { shouldDirty: true })
                  setValue('logo_url', url, { shouldDirty: true })
                  if (type === 'custom') setValue('icon_slug', null, { shouldDirty: true })
                }}
                label="Technology Logo"
                query={title || ''}
                disabled={isSubmitting}
              />

              <div>
                <label htmlFor="showcase-icon" className="block text-sm font-medium mb-1">
                  Fallback Icon Name{' '}
                  <span className="text-muted-foreground font-normal">
                    (lucide-react, optional)
                  </span>
                </label>
                <Input id="showcase-icon" {...register('icon_name')} placeholder="Users, Globe, Zap, etc." />
                <p className="text-xs text-muted-foreground mt-1">
                  Used only when no logo is selected above — handy for concepts
                  like &ldquo;Webhooks&rdquo; that have no brand mark.
                </p>
                {errors.icon_name && <p className="text-destructive text-sm mt-1">{errors.icon_name.message}</p>}
              </div>

              <div>
                <label htmlFor="showcase-title" className="block text-sm font-medium mb-1">Title</label>
                <Input id="showcase-title" {...register('title')} placeholder="API Feature Title" />
                {errors.title && <p className="text-destructive text-sm mt-1">{errors.title.message}</p>}
              </div>

              <div>
                <label htmlFor="showcase-description" className="block text-sm font-medium mb-1">Description</label>
                <Textarea id="showcase-description" {...register('description')} placeholder="Describe this API feature..." rows={3} />
              </div>

              <div>
                <div className="flex items-center justify-between mb-2">
                  <span className="block text-sm font-medium">Endpoints</span>
                  <Button type="button" size="sm" variant="outline" onClick={() => append('')}>
                    <Plus className="w-3 h-3 mr-1" aria-hidden="true" />
                    Add
                  </Button>
                </div>
                <div className="space-y-2">
                  {fields.map((field, idx) => (
                    <div key={field.id} className="flex gap-2">
                      <Input
                        {...register(`endpoints.${idx}`)}
                        aria-label={`Endpoint ${idx + 1}`}
                        placeholder="GET /api/endpoint"
                      />
                      <Button
                        type="button"
                        variant="ghost"
                        aria-label={`Remove endpoint ${idx + 1}`}
                        onClick={() => remove(idx)}
                      >
                        <Trash2 className="w-4 h-4" aria-hidden="true" />
                      </Button>
                    </div>
                  ))}
                </div>
              </div>

              <div className="flex gap-2">
                <Button type="submit" disabled={isSubmitting}>
                  {isSubmitting ? 'Saving...' : editingId ? 'Update' : 'Create'}
                </Button>
                <Button type="button" variant="outline" onClick={() => {
                  setShowForm(false)
                  setEditingId(null)
                  reset()
                }}>
                  Cancel
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardContent className="pt-6">
          {isLoading ? (
            <ListSkeleton rows={4} />
          ) : loadError ? (
            <EmptyState
              type="items"
              title="Couldn't load API showcases"
              description="Something went wrong fetching your showcases. Check your connection and try again."
            />
          ) : items.length === 0 ? (
            <EmptyState
              type="add"
              icon={Layout}
              title="No API Showcases Yet"
              description="Add your first showcase to highlight the APIs you've built"
            />
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {items.map((item) => (
                <div key={item.id} className="p-4 border border-border rounded-lg hover:bg-muted/50">
                  <div className="flex items-start justify-between mb-2 gap-2">
                    <div className="min-w-0">
                      <h3 className="font-medium flex items-center gap-2">
                        {item.icon_slug && (
                          <TechIcon slug={item.icon_slug} className="h-4 w-4 shrink-0" />
                        )}
                        <span className="min-w-0 truncate">{item.title}</span>
                      </h3>
                      <p className="text-sm text-muted-foreground mt-1">{item.description}</p>
                    </div>
                    <div className="flex gap-1 shrink-0">
                      <Button size="sm" variant="ghost" aria-label={`Edit ${item.title}`} onClick={() => handleEdit(item)}>
                        <Edit2 className="w-4 h-4" aria-hidden="true" />
                      </Button>
                      <Button size="sm" variant="ghost" aria-label={`Delete ${item.title}`} onClick={() => setDeleteDialog({ open: true, id: item.id })}>
                        <Trash2 className="w-4 h-4 text-destructive" aria-hidden="true" />
                      </Button>
                    </div>
                  </div>
                  {item.endpoints && item.endpoints.length > 0 && (
                    <div className="mt-3 pt-3 border-t border-border space-y-1">
                      {item.endpoints.map((ep, idx) => (
                        <code key={idx} className="text-xs bg-muted px-2 py-1 rounded block">{ep}</code>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      <AlertDialog
        open={deleteDialog.open}
        onOpenChange={(open) => setDeleteDialog({ ...deleteDialog, open })}
        title="Delete API Showcase"
        description="This action cannot be undone. The showcase will be permanently deleted."
        confirmText="Delete"
        pendingText="Deleting..."
        cancelText="Cancel"
        isDestructive={true}
        onConfirm={() => handleDelete(deleteDialog.id)}
      />
    </div>
  )
}
