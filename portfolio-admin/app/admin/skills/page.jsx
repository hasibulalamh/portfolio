'use client'

import { useCallback, useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { skillCategorySchema, skillSchema } from '@/lib/validation'
import { apiCall } from '@/lib/api'
import { useToast } from '@/components/ui/toast'
import { AlertDialog } from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { EmptyState } from '@/components/admin/EmptyState'
import { ListSkeleton } from '@/components/admin/Skeleton'
import { TechIconPicker } from '@/components/admin/TechIconPicker'
import { TechIcon } from '@/components/admin/TechIcon'
import { Edit2, Trash2, Plus, ArrowUp, ArrowDown, Zap } from 'lucide-react'

export default function SkillsPage() {
  const { showToast } = useToast()
  const [categories, setCategories] = useState([])
  const [selectedCategory, setSelectedCategory] = useState(null)
  const [skills, setSkills] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState(false)
  const [isLoadingSkills, setIsLoadingSkills] = useState(false)
  const [showCategoryForm, setShowCategoryForm] = useState(false)
  const [showSkillForm, setShowSkillForm] = useState(false)
  const [editingCategoryId, setEditingCategoryId] = useState(null)
  const [editingSkillId, setEditingSkillId] = useState(null)
  const [deleteDialog, setDeleteDialog] = useState({ open: false, kind: null, id: null, name: '' })

  const {
    register: registerCategory,
    handleSubmit: handleCategorySubmit,
    reset: resetCategory,
    formState: { errors: categoryErrors, isSubmitting: isSavingCategory },
  } = useForm({
    resolver: zodResolver(skillCategorySchema),
    defaultValues: { name: '', order: 0 },
  })

  const {
    register: registerSkill,
    handleSubmit: handleSkillSubmit,
    reset: resetSkill,
    setValue: setSkillValue,
    watch: watchSkill,
    formState: { errors: skillErrors, isSubmitting: isSavingSkill },
  } = useForm({
    resolver: zodResolver(skillSchema),
    defaultValues: { name: '', icon_slug: null, logo_type: 'library', logo_url: null, order: 0 },
  })

  const skillIconSlug = watchSkill('icon_slug')
  const skillLogoType = watchSkill('logo_type')
  const skillLogoUrl = watchSkill('logo_url')
  const skillName = watchSkill('name')

  const loadSkills = useCallback(
    async (categoryId) => {
      setIsLoadingSkills(true)
      const result = await apiCall('GET', `/admin/skills?category_id=${categoryId}`)
      if (result.success) {
        setSkills(Array.isArray(result.data) ? result.data : [])
      } else {
        setSkills([])
        showToast(result.message || 'Failed to load skills', 'error')
      }
      setIsLoadingSkills(false)
    },
    [showToast]
  )

  const loadCategories = useCallback(async () => {
    setIsLoading(true)
    const result = await apiCall('GET', '/admin/skill-categories')
    if (result.success) {
      const cats = Array.isArray(result.data) ? result.data : []
      setCategories(cats)
      setLoadError(false)
      // Keep the current selection if it still exists, otherwise fall back to
      // the first category.
      setSelectedCategory((current) => {
        if (current) {
          const stillThere = cats.find((c) => c.id === current.id)
          if (stillThere) return stillThere
        }
        return cats[0] || null
      })
    } else {
      setLoadError(true)
      showToast(result.message || 'Failed to load skill categories', 'error')
    }
    setIsLoading(false)
  }, [showToast])

  useEffect(() => {
    loadCategories()
  }, [loadCategories])

  useEffect(() => {
    if (selectedCategory) {
      loadSkills(selectedCategory.id)
    } else {
      setSkills([])
    }
  }, [selectedCategory, loadSkills])

  const onCategorySubmit = async (data) => {
    let result
    if (editingCategoryId) {
      result = await apiCall('PUT', `/admin/skill-categories/${editingCategoryId}`, data)
    } else {
      result = await apiCall('POST', '/admin/skill-categories', {
        ...data,
        order: categories.length,
      })
    }

    if (result.success) {
      showToast(editingCategoryId ? 'Category updated' : 'Category created', 'success')
      setEditingCategoryId(null)
      setShowCategoryForm(false)
      resetCategory({ name: '', order: 0 })
      await loadCategories()
    } else {
      showToast(result.message || 'Failed to save', 'error')
    }
  }

  const onSkillSubmit = async (data) => {
    if (!selectedCategory) {
      showToast('Select a category first', 'error')
      return
    }

    const skillData = {
      ...data,
      skill_category_id: selectedCategory.id,
      order: editingSkillId ? data.order ?? 0 : skills.length,
    }

    let result
    if (editingSkillId) {
      result = await apiCall('PUT', `/admin/skills/${editingSkillId}`, skillData)
    } else {
      result = await apiCall('POST', '/admin/skills', skillData)
    }

    if (result.success) {
      showToast(editingSkillId ? 'Skill updated' : 'Skill created', 'success')
      setEditingSkillId(null)
      setShowSkillForm(false)
      resetSkill({ name: '', icon_slug: null, logo_type: 'library', logo_url: null, order: 0 })
      await loadSkills(selectedCategory.id)
    } else {
      showToast(result.message || 'Failed to save', 'error')
    }
  }

  const handleEditCategory = (category) => {
    setEditingCategoryId(category.id)
    resetCategory({ name: category.name || '', order: category.order ?? 0 })
    setShowCategoryForm(true)
  }

  const handleEditSkill = (skill) => {
    setEditingSkillId(skill.id)
    resetSkill({
      name: skill.name || '',
      icon_slug: skill.icon_slug || null,
      logo_type: skill.logo_type || 'library',
      logo_url: skill.logo_url || null,
      order: skill.order ?? 0,
    })
    setShowSkillForm(true)
  }

  const handleDeleteCategory = async (id) => {
    const result = await apiCall('DELETE', `/admin/skill-categories/${id}`)
    if (result.success) {
      showToast('Category deleted', 'success')
      if (selectedCategory?.id === id) {
        setSelectedCategory(null)
      }
      await loadCategories()
    } else {
      showToast(result.message || 'Failed to delete', 'error')
    }
    setDeleteDialog({ open: false, kind: null, id: null, name: '' })
  }

  const handleDeleteSkill = async (id) => {
    const result = await apiCall('DELETE', `/admin/skills/${id}`)
    if (result.success) {
      showToast('Skill deleted', 'success')
      if (editingSkillId === id) {
        setEditingSkillId(null)
        setShowSkillForm(false)
        resetSkill({ name: '', icon_slug: null, logo_type: 'library', logo_url: null, order: 0 })
      }
      if (selectedCategory) {
        await loadSkills(selectedCategory.id)
      }
    } else {
      showToast(result.message || 'Failed to delete', 'error')
    }
    setDeleteDialog({ open: false, kind: null, id: null, name: '' })
  }

  const handleCategoryReorder = async (id, direction) => {
    const idx = categories.findIndex((c) => c.id === id)
    if (idx === -1) return

    let newCats = [...categories]
    if (direction === 'up' && idx > 0) {
      [newCats[idx], newCats[idx - 1]] = [newCats[idx - 1], newCats[idx]]
    } else if (direction === 'down' && idx < newCats.length - 1) {
      [newCats[idx], newCats[idx + 1]] = [newCats[idx + 1], newCats[idx]]
    } else {
      return
    }

    const reorderData = newCats.map((cat, i) => ({ id: cat.id, order: i }))
    const result = await apiCall('PUT', '/admin/skill-categories/reorder', { items: reorderData })

    if (result.success) {
      setCategories(newCats)
      showToast('Order updated', 'success')
    } else {
      showToast(result.message || 'Failed to update order', 'error')
    }
  }

  const handleSkillReorder = async (id, direction) => {
    const idx = skills.findIndex((s) => s.id === id)
    if (idx === -1) return

    let newSkills = [...skills]
    if (direction === 'up' && idx > 0) {
      [newSkills[idx], newSkills[idx - 1]] = [newSkills[idx - 1], newSkills[idx]]
    } else if (direction === 'down' && idx < newSkills.length - 1) {
      [newSkills[idx], newSkills[idx + 1]] = [newSkills[idx + 1], newSkills[idx]]
    } else {
      return
    }

    const reorderData = newSkills.map((skill, i) => ({ id: skill.id, order: i }))
    const result = await apiCall('PUT', '/admin/skills/reorder', { items: reorderData })

    if (result.success) {
      setSkills(newSkills)
      showToast('Order updated', 'success')
    } else {
      showToast(result.message || 'Failed to update order', 'error')
    }
  }

  const confirmDelete = () => {
    if (deleteDialog.kind === 'category') return handleDeleteCategory(deleteDialog.id)
    if (deleteDialog.kind === 'skill') return handleDeleteSkill(deleteDialog.id)
    return Promise.resolve()
  }

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-3xl font-bold">Skills &amp; Categories</h1>
        <p className="text-muted-foreground mt-2">Manage your skills organized by categories</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Categories List */}
        <Card>
          <CardHeader className="flex items-center justify-between">
            <CardTitle className="text-lg">Categories</CardTitle>
            <Button
              size="sm"
              aria-label="Add category"
              onClick={() => {
                setEditingCategoryId(null)
                resetCategory({ name: '', order: 0 })
                setShowCategoryForm(!showCategoryForm)
              }}
            >
              <Plus className="w-4 h-4" aria-hidden="true" />
            </Button>
          </CardHeader>
          <CardContent>
            {showCategoryForm && (
              <form onSubmit={handleCategorySubmit(onCategorySubmit)} className="mb-4 p-3 border rounded bg-muted">
                <label htmlFor="category-name" className="block text-sm font-medium mb-1">
                  {editingCategoryId ? 'Rename category' : 'New category'}
                </label>
                <Input
                  id="category-name"
                  {...registerCategory('name')}
                  placeholder="Category name"
                  className="mb-2"
                />
                {categoryErrors.name && <p className="text-destructive text-sm mb-2">{categoryErrors.name.message}</p>}
                <div className="flex gap-2">
                  <Button type="submit" size="sm" className="flex-1" disabled={isSavingCategory}>
                    {editingCategoryId ? 'Save' : 'Create'}
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => {
                      setShowCategoryForm(false)
                      setEditingCategoryId(null)
                      resetCategory({ name: '', order: 0 })
                    }}
                  >
                    Cancel
                  </Button>
                </div>
              </form>
            )}

            {isLoading ? (
              <ListSkeleton rows={3} />
            ) : loadError ? (
              <EmptyState
                type="items"
                title="Couldn't load categories"
                description="Something went wrong. Check your connection and try again."
              />
            ) : categories.length === 0 ? (
              <EmptyState
                type="add"
                icon={Zap}
                title="No Categories Yet"
                description="Create a category to start grouping your skills"
              />
            ) : (
              <div className="space-y-2">
                {categories.map((cat, idx) => (
                  <div
                    key={cat.id}
                    onClick={() => setSelectedCategory(cat)}
                    className={`p-3 rounded-lg cursor-pointer border transition-colors ${
                      selectedCategory?.id === cat.id
                        ? 'bg-primary text-primary-foreground border-primary'
                        : 'border-border hover:bg-muted'
                    }`}
                  >
                    <div className="flex items-center justify-between mb-1">
                      <span className="font-medium">{cat.name}</span>
                      <div className="flex gap-1">
                        <button
                          type="button"
                          aria-label={`Move ${cat.name} up`}
                          onClick={(e) => {
                            e.stopPropagation()
                            handleCategoryReorder(cat.id, 'up')
                          }}
                          disabled={idx === 0}
                          className="p-1 rounded hover:bg-black/10 disabled:opacity-50"
                        >
                          <ArrowUp className="w-3 h-3" aria-hidden="true" />
                        </button>
                        <button
                          type="button"
                          aria-label={`Move ${cat.name} down`}
                          onClick={(e) => {
                            e.stopPropagation()
                            handleCategoryReorder(cat.id, 'down')
                          }}
                          disabled={idx === categories.length - 1}
                          className="p-1 rounded hover:bg-black/10 disabled:opacity-50"
                        >
                          <ArrowDown className="w-3 h-3" aria-hidden="true" />
                        </button>
                        <button
                          type="button"
                          aria-label={`Rename ${cat.name}`}
                          onClick={(e) => {
                            e.stopPropagation()
                            handleEditCategory(cat)
                          }}
                          className="p-1 rounded hover:bg-black/10"
                        >
                          <Edit2 className="w-3 h-3" aria-hidden="true" />
                        </button>
                        <button
                          type="button"
                          aria-label={`Delete ${cat.name}`}
                          onClick={(e) => {
                            e.stopPropagation()
                            setDeleteDialog({ open: true, kind: 'category', id: cat.id, name: cat.name })
                          }}
                          className="p-1 rounded hover:bg-red-600 hover:text-white"
                        >
                          <Trash2 className="w-3 h-3" aria-hidden="true" />
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        {/* Skills List */}
        {selectedCategory && (
          <Card className="lg:col-span-2">
            <CardHeader className="flex items-center justify-between">
              <CardTitle className="text-lg">{selectedCategory.name} - Skills</CardTitle>
              <Button
                size="sm"
                aria-label="Add skill"
                onClick={() => {
                  setEditingSkillId(null)
                  resetSkill({ name: '', icon_slug: null, logo_type: 'library', logo_url: null, order: 0 })
                  setShowSkillForm(!showSkillForm)
                }}
              >
                <Plus className="w-4 h-4" aria-hidden="true" />
              </Button>
            </CardHeader>
            <CardContent>
              {showSkillForm && (
                <form onSubmit={handleSkillSubmit(onSkillSubmit)} className="mb-4 p-3 border rounded bg-muted">
                  <label htmlFor="skill-name" className="block text-sm font-medium mb-1">
                    {editingSkillId ? 'Rename skill' : 'New skill'}
                  </label>
                  <Input
                    id="skill-name"
                    {...registerSkill('name')}
                    placeholder="Skill name"
                    className="mb-2"
                  />
                  {skillErrors.name && <p className="text-destructive text-sm mb-2">{skillErrors.name.message}</p>}

                  <div className="mb-2">
                    <TechIconPicker
                      value={skillIconSlug}
                      onChange={(slug) => setSkillValue('icon_slug', slug, { shouldDirty: true })}
                      logoType={skillLogoType}
                      logoUrl={skillLogoUrl}
                      onLogoChange={(type, url) => {
                        setSkillValue('logo_type', type, { shouldDirty: true })
                        setSkillValue('logo_url', url, { shouldDirty: true })
                        if (type === 'custom') setSkillValue('icon_slug', null, { shouldDirty: true })
                      }}
                      // Seed the search with the skill name, e.g. typing
                      // "Laravel" as the name pre-fills the logo search.
                      query={skillName || ''}
                      disabled={isSavingSkill}
                    />
                  </div>
                  <div className="flex gap-2">
                    <Button type="submit" size="sm" className="flex-1" disabled={isSavingSkill}>
                      {editingSkillId ? 'Save' : 'Add'}
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        setShowSkillForm(false)
                        setEditingSkillId(null)
                        resetSkill({ name: '', icon_slug: null, logo_type: 'library', logo_url: null, order: 0 })
                      }}
                    >
                      Cancel
                    </Button>
                  </div>
                </form>
              )}

              {isLoadingSkills ? (
                <ListSkeleton rows={3} />
              ) : skills.length === 0 ? (
                <EmptyState
                  type="add"
                  icon={Zap}
                  title="No Skills Yet"
                  description={`Add your first skill to the "${selectedCategory.name}" category`}
                />
              ) : (
                <div className="space-y-2">
                  {skills.map((skill, idx) => (
                    <div key={skill.id} className="p-3 border border-border rounded-lg flex items-center justify-between gap-2">
                      <span className="flex min-w-0 items-center gap-2">
                        {skill.icon_slug && (
                          <TechIcon slug={skill.icon_slug} className="h-4 w-4 shrink-0" />
                        )}
                        <span className="min-w-0 truncate">{skill.name}</span>
                      </span>
                      <div className="flex gap-1 shrink-0">
                        <Button
                          size="sm"
                          variant="ghost"
                          aria-label={`Move ${skill.name} up`}
                          onClick={() => handleSkillReorder(skill.id, 'up')}
                          disabled={idx === 0}
                        >
                          <ArrowUp className="w-4 h-4" aria-hidden="true" />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          aria-label={`Move ${skill.name} down`}
                          onClick={() => handleSkillReorder(skill.id, 'down')}
                          disabled={idx === skills.length - 1}
                        >
                          <ArrowDown className="w-4 h-4" aria-hidden="true" />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          aria-label={`Edit ${skill.name}`}
                          onClick={() => handleEditSkill(skill)}
                        >
                          <Edit2 className="w-4 h-4" aria-hidden="true" />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          aria-label={`Delete ${skill.name}`}
                          onClick={() => setDeleteDialog({ open: true, kind: 'skill', id: skill.id, name: skill.name })}
                        >
                          <Trash2 className="w-4 h-4 text-destructive" aria-hidden="true" />
                        </Button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        )}
      </div>

      <AlertDialog
        open={deleteDialog.open}
        onOpenChange={(open) => setDeleteDialog({ ...deleteDialog, open })}
        title={deleteDialog.kind === 'category' ? 'Delete Category' : 'Delete Skill'}
        description={
          deleteDialog.kind === 'category'
            ? `Deleting "${deleteDialog.name}" also removes every skill inside it. This cannot be undone.`
            : `"${deleteDialog.name}" will be permanently deleted. This cannot be undone.`
        }
        confirmText="Delete"
        pendingText="Deleting..."
        cancelText="Cancel"
        isDestructive={true}
        onConfirm={confirmDelete}
      />
    </div>
  )
}
