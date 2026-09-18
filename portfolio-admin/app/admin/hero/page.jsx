'use client'

import { useEffect, useState } from 'react'
import { useForm, useFieldArray } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { heroSchema } from '@/lib/validation'
import { SOCIAL_PLATFORMS } from '@/lib/social-platforms'
import { apiCall } from '@/lib/api'
import { useToast } from '@/components/ui/toast'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent } from '@/components/ui/card'
import { FileUpload } from '@/components/admin/FileUpload'
import { TechIconPicker } from '@/components/admin/TechIconPicker'
import { AlertDialog } from '@/components/ui/alert-dialog'
import { Loader2, Plus, RotateCcw, Trash2, ChevronUp, ChevronDown } from 'lucide-react'

// Only these keys belong to the form; the API record also carries id/timestamps.
const HERO_FIELDS = [
  'heading',
  'subheading',
  'roles',
  'tech_badges',
  'is_available',
  'availability_label',
  'cta_primary_text',
  'cta_primary_link',
  'cta_secondary_text',
  'cta_secondary_link',
  'image_path',
  'image_alt',
  'social_links',
  'email',
  'cv_path',
]

/** The orbit distributes badges evenly, but past six they touch on mobile. */
const MAX_TECH_BADGES = 6

/**
 * Push an API hero record into form state.
 *
 * The three list fields each need their own shape: `roles` is stored as plain
 * strings but edited as objects (useFieldArray needs a key to hang onto), while
 * the other two already match. `is_available` is the one field whose empty
 * value is `true` rather than '' — a reset should leave the site marked
 * available, not fall back to an empty string that React reads as unchecked.
 */
function applyHero(hero, setValue) {
  HERO_FIELDS.forEach((key) => {
    if (key === 'roles') {
      const roles = Array.isArray(hero.roles) ? hero.roles : []
      setValue('roles', roles.map((role) => ({ value: role })))
    } else if (key === 'tech_badges' || key === 'social_links') {
      setValue(key, Array.isArray(hero[key]) ? hero[key] : [])
    } else if (key === 'is_available') {
      setValue('is_available', hero.is_available ?? true)
    } else {
      setValue(key, hero[key] ?? '')
    }
  })
}

/**
 * Move-up / move-down buttons for one row of a repeatable list.
 *
 * Buttons rather than drag-and-drop: the lists here are short, and buttons are
 * keyboard-reachable and screen-reader-announceable for free, which a custom
 * drag implementation is not. The end button of each pair disables at the
 * boundary so the order cannot silently no-op.
 */
function ReorderControls({ index, count, move, disabled, label }) {
  return (
    <div className="flex flex-col shrink-0">
      <button
        type="button"
        onClick={() => move(index, index - 1)}
        disabled={disabled || index === 0}
        aria-label={`Move ${label} up`}
        className="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30 disabled:hover:bg-transparent"
      >
        <ChevronUp className="w-4 h-4" aria-hidden="true" />
      </button>
      <button
        type="button"
        onClick={() => move(index, index + 1)}
        disabled={disabled || index === count - 1}
        aria-label={`Move ${label} down`}
        className="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30 disabled:hover:bg-transparent"
      >
        <ChevronDown className="w-4 h-4" aria-hidden="true" />
      </button>
    </div>
  )
}

export default function HeroPage() {
  const { showToast } = useToast()
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [uploading, setUploading] = useState({ image: false, cv: false })
  const [resetDialogOpen, setResetDialogOpen] = useState(false)

  const isUploading = uploading.image || uploading.cv

  const {
    register,
    handleSubmit,
    formState: { errors },
    setValue,
    watch,
    control,
  } = useForm({
    resolver: zodResolver(heroSchema),
    defaultValues: {
      heading: '',
      subheading: '',
      roles: [],
      tech_badges: [],
      is_available: true,
      availability_label: '',
      cta_primary_text: '',
      cta_primary_link: '',
      cta_secondary_text: '',
      cta_secondary_link: '',
      image_path: '',
      image_alt: '',
      social_links: [],
      email: '',
      cv_path: '',
    },
  })

  const {
    fields: rolesFields,
    append: appendRole,
    remove: removeRole,
    move: moveRole,
  } = useFieldArray({ control, name: 'roles' })

  const {
    fields: badgesFields,
    append: appendBadge,
    remove: removeBadge,
    move: moveBadge,
  } = useFieldArray({ control, name: 'tech_badges' })

  const {
    fields: socialFields,
    append: appendSocial,
    remove: removeSocial,
    move: moveSocial,
  } = useFieldArray({ control, name: 'social_links' })

  const imagePath = watch('image_path')
  const imageAlt = watch('image_alt')
  const cvPath = watch('cv_path')
  const isAvailable = watch('is_available')

  useEffect(() => {
    const loadHero = async () => {
      const result = await apiCall('GET', '/admin/hero')
      // A brand-new site has no hero row yet, so data can legitimately be null.
      if (result.success && result.data && typeof result.data === 'object') {
        applyHero(result.data, setValue)
      } else if (!result.success) {
        showToast(result.message || 'Failed to load hero section', 'error')
      }
      setIsLoading(false)
    }

    loadHero()
  }, [setValue, showToast])

  const onSubmit = async (data) => {
    setIsSaving(true)
    // Map roles back to plain strings and strip blank rows.
    const payload = {
      ...data,
      roles: (data.roles || [])
        .map((r) => (r.value || '').trim())
        .filter(Boolean),
      tech_badges: (data.tech_badges || []).filter(
        (b) => (b.label || '').trim() !== '',
      ),
      social_links: (data.social_links || []).filter(
        (s) => (s.url || '').trim() !== '',
      ),
    }
    const result = await apiCall('PUT', '/admin/hero', payload)

    if (result.success) {
      showToast('Hero section updated', 'success')
    } else {
      showToast(result.message || 'Failed to save', 'error')
    }
    setIsSaving(false)
  }

  const handleReset = async () => {
    const result = await apiCall('POST', '/admin/hero/reset')

    if (result.success && result.data) {
      applyHero(result.data, setValue)
      showToast('Hero section reset successfully', 'success')
    } else {
      showToast(result.message || 'Failed to reset hero section', 'error')
    }
    setResetDialogOpen(false)
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center p-8">
        <Loader2 className="w-6 h-6 animate-spin" aria-hidden="true" />
        <span className="sr-only">Loading hero section…</span>
      </div>
    )
  }

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-3xl font-bold">Hero Section</h1>
        <p className="text-muted-foreground mt-2">Edit your homepage hero section</p>
      </div>

      <Card>
        <CardContent className="pt-6">
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
            <div>
              <label htmlFor="hero-heading" className="block text-sm font-medium mb-2">Heading</label>
              <Input
                id="hero-heading"
                {...register('heading')}
                placeholder="Your name"
                disabled={isSaving}
              />
              {errors.heading && <p className="text-destructive text-sm mt-1">{errors.heading.message}</p>}
            </div>

            <div>
              <label htmlFor="hero-subheading" className="block text-sm font-medium mb-2">Subheading</label>
              <Textarea
                id="hero-subheading"
                {...register('subheading')}
                placeholder="A short description..."
                disabled={isSaving}
              />
            </div>
            {/* Rotating role titles — the typewriter cycles these in order. */}
            <div className="border-t border-border pt-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="font-semibold">Rotating Role Titles</h2>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => appendRole({ value: '' })}
                  disabled={isSaving || rolesFields.length >= 12}
                >
                  <Plus className="w-4 h-4 mr-1" aria-hidden="true" />
                  Add Role
                </Button>
              </div>
              <p className="text-xs text-muted-foreground mb-3">
                Cycled by the typing animation under your name, in this order. Rows left blank are ignored when saving.
              </p>

              <div className="space-y-3" data-testid="roles-list">
                {rolesFields.length === 0 && (
                  <p className="text-sm text-muted-foreground italic">
                    No roles yet — the hero will show a single default title.
                  </p>
                )}
                {rolesFields.map((field, index) => (
                  <div key={field.id} className="flex gap-2 items-center" data-testid="role-row">
                    <ReorderControls
                      index={index}
                      count={rolesFields.length}
                      move={moveRole}
                      disabled={isSaving}
                      label={`role ${index + 1}`}
                    />
                    <div className="flex-1">
                      <label htmlFor={`role-${index}`} className="sr-only">
                        Role title {index + 1}
                      </label>
                      <Input
                        id={`role-${index}`}
                        {...register(`roles.${index}.value`)}
                        placeholder="e.g. Laravel Developer"
                        disabled={isSaving}
                      />
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      aria-label={`Remove role ${index + 1}`}
                      onClick={() => removeRole(index)}
                      disabled={isSaving}
                    >
                      <Trash2 className="w-4 h-4 text-destructive" aria-hidden="true" />
                    </Button>
                  </div>
                ))}
              </div>
            </div>

            {/* Availability badge. */}
            <div className="border-t border-border pt-6">
              <h2 className="font-semibold mb-4">Availability Badge</h2>
              <div className="flex items-center gap-3">
                <input
                  type="checkbox"
                  {...register('is_available')}
                  id="hero-is-available"
                  className="w-4 h-4"
                  disabled={isSaving}
                />
                <label htmlFor="hero-is-available" className="text-sm font-medium cursor-pointer">
                  Available for Work
                </label>
              </div>
              <p className="text-xs text-muted-foreground mt-2">
                When off, the badge is hidden from the homepage entirely.
              </p>

              <div className="mt-4">
                <label htmlFor="hero-availability-label" className="block text-sm font-medium mb-2">
                  Badge Label
                </label>
                <Input
                  id="hero-availability-label"
                  {...register('availability_label')}
                  placeholder="Available for Work"
                  disabled={isSaving || !isAvailable}
                />
                {errors.availability_label && (
                  <p className="text-destructive text-sm mt-1">{errors.availability_label.message}</p>
                )}
                <p className="text-xs text-muted-foreground mt-1">
                  Leave blank to use &ldquo;Available for Work&rdquo;.
                </p>
              </div>
            </div>
            {/* Orbiting tech badges. */}
            <div className="border-t border-border pt-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="font-semibold">Orbiting Tech Badges</h2>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() =>
                    appendBadge({
                      label: '',
                      icon_slug: null,
                      logo_type: 'library',
                      logo_url: null,
                    })
                  }
                  disabled={isSaving || badgesFields.length >= MAX_TECH_BADGES}
                >
                  <Plus className="w-4 h-4 mr-1" aria-hidden="true" />
                  Add Badge
                </Button>
              </div>
              <p className="text-xs text-muted-foreground mb-3">
                Logos that orbit your portrait, spaced evenly however many you add.
                Up to {MAX_TECH_BADGES} — beyond that they overlap on small screens.
              </p>

              <div className="space-y-4" data-testid="badges-list">
                {badgesFields.length === 0 && (
                  <p className="text-sm text-muted-foreground italic">
                    No badges yet — nothing will orbit the portrait.
                  </p>
                )}
                {badgesFields.map((field, index) => (
                  <div
                    key={field.id}
                    className="flex gap-2 items-start rounded-lg border border-border p-3"
                    data-testid="badge-row"
                  >
                    <div className="pt-2">
                      <ReorderControls
                        index={index}
                        count={badgesFields.length}
                        move={moveBadge}
                        disabled={isSaving}
                        label={`badge ${index + 1}`}
                      />
                    </div>

                    <div className="flex-1 space-y-3">
                      <div>
                        <label htmlFor={`badge-label-${index}`} className="block text-sm font-medium mb-1">
                          Label {index + 1}
                        </label>
                        <Input
                          id={`badge-label-${index}`}
                          {...register(`tech_badges.${index}.label`)}
                          placeholder="e.g. Laravel"
                          disabled={isSaving}
                        />
                        {errors.tech_badges?.[index]?.label && (
                          <p className="text-destructive text-sm mt-1">
                            {errors.tech_badges[index].label.message}
                          </p>
                        )}
                      </div>

                      {/* Same picker the Skills and API Showcase forms use, so
                          the slug it yields is one the public site can render. */}
                      <TechIconPicker
                        value={watch(`tech_badges.${index}.icon_slug`) || null}
                        onChange={(slug) =>
                          setValue(`tech_badges.${index}.icon_slug`, slug, { shouldDirty: true })
                        }
                        logoType={watch(`tech_badges.${index}.logo_type`) || 'library'}
                        logoUrl={watch(`tech_badges.${index}.logo_url`) || null}
                        onLogoChange={(type, url) => {
                          setValue(`tech_badges.${index}.logo_type`, type, { shouldDirty: true })
                          setValue(`tech_badges.${index}.logo_url`, url, { shouldDirty: true })
                          if (type === 'custom') {
                            setValue(`tech_badges.${index}.icon_slug`, null, { shouldDirty: true })
                          }
                        }}
                        query={watch(`tech_badges.${index}.label`) || ''}
                        disabled={isSaving}
                      />
                    </div>

                    <Button
                      type="button"
                      variant="ghost"
                      aria-label={`Remove badge ${index + 1}`}
                      onClick={() => removeBadge(index)}
                      disabled={isSaving}
                    >
                      <Trash2 className="w-4 h-4 text-destructive" aria-hidden="true" />
                    </Button>
                  </div>
                ))}
              </div>
            </div>
            <div className="border-t border-border pt-6">
              <h2 className="font-semibold mb-4">Call to Action Buttons</h2>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label htmlFor="hero-cta-primary-text" className="block text-sm font-medium mb-2">Primary Button Text</label>
                  <Input id="hero-cta-primary-text" {...register('cta_primary_text')} placeholder="Get Started" disabled={isSaving} />
                </div>
                <div>
                  <label htmlFor="hero-cta-primary-link" className="block text-sm font-medium mb-2">Primary Button Link</label>
                  <Input id="hero-cta-primary-link" {...register('cta_primary_link')} placeholder="/projects" disabled={isSaving} />
                </div>
                <div>
                  <label htmlFor="hero-cta-secondary-text" className="block text-sm font-medium mb-2">Secondary Button Text</label>
                  <Input id="hero-cta-secondary-text" {...register('cta_secondary_text')} placeholder="Learn More" disabled={isSaving} />
                </div>
                <div>
                  <label htmlFor="hero-cta-secondary-link" className="block text-sm font-medium mb-2">Secondary Button Link</label>
                  <Input id="hero-cta-secondary-link" {...register('cta_secondary_link')} placeholder="/about" disabled={isSaving} />
                </div>
              </div>
            </div>

            <div className="border-t border-border pt-6">
              <h2 className="font-semibold mb-4">Media</h2>
              <FileUpload
                label="Hero Image"
                accept="image/*"
                onUploadComplete={(url) => setValue('image_path', url || '')}
                initialValue={imagePath}
                onUploadingChange={(value) => setUploading((prev) => ({ ...prev, image: value }))}
                altText={imageAlt || ''}
                onAltTextChange={(value) => setValue('image_alt', value)}
              />
            </div>

            {/* Social links — any number, from the supported platform set. */}
            <div className="border-t border-border pt-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="font-semibold">Social Links</h2>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => appendSocial({ platform: 'github', url: '' })}
                  disabled={isSaving || socialFields.length >= 12}
                >
                  <Plus className="w-4 h-4 mr-1" aria-hidden="true" />
                  Add Link
                </Button>
              </div>
              <p className="text-xs text-muted-foreground mb-3">
                Shown as icons in the hero, footer and contact section. Rows with no URL are ignored when saving.
              </p>

              <div className="space-y-3" data-testid="social-list">
                {socialFields.length === 0 && (
                  <p className="text-sm text-muted-foreground italic">
                    No social links yet.
                  </p>
                )}
                {socialFields.map((field, index) => (
                  <div key={field.id} className="flex gap-2 items-center" data-testid="social-row">
                    <ReorderControls
                      index={index}
                      count={socialFields.length}
                      move={moveSocial}
                      disabled={isSaving}
                      label={`social link ${index + 1}`}
                    />
                    <div className="w-40 shrink-0">
                      <label htmlFor={`social-platform-${index}`} className="sr-only">
                        Platform for link {index + 1}
                      </label>
                      <select
                        id={`social-platform-${index}`}
                        {...register(`social_links.${index}.platform`)}
                        disabled={isSaving}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                      >
                        {SOCIAL_PLATFORMS.map((platform) => (
                          <option key={platform.value} value={platform.value}>
                            {platform.label}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div className="flex-1">
                      <label htmlFor={`social-url-${index}`} className="sr-only">
                        URL for link {index + 1}
                      </label>
                      <Input
                        id={`social-url-${index}`}
                        {...register(`social_links.${index}.url`)}
                        placeholder={
                          SOCIAL_PLATFORMS.find(
                            (p) => p.value === watch(`social_links.${index}.platform`),
                          )?.placeholder || 'https://...'
                        }
                        disabled={isSaving}
                      />
                      {errors.social_links?.[index]?.url && (
                        <p className="text-destructive text-sm mt-1">
                          {errors.social_links[index].url.message}
                        </p>
                      )}
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      aria-label={`Remove social link ${index + 1}`}
                      onClick={() => removeSocial(index)}
                      disabled={isSaving}
                    >
                      <Trash2 className="w-4 h-4 text-destructive" aria-hidden="true" />
                    </Button>
                  </div>
                ))}
              </div>
            </div>

            <div className="border-t border-border pt-6">
              <label htmlFor="hero-email" className="block text-sm font-medium mb-2">Contact Email</label>
              <Input id="hero-email" {...register('email')} type="email" placeholder="hello@example.com" disabled={isSaving} />
              {errors.email && <p className="text-destructive text-sm mt-1">{errors.email.message}</p>}
            </div>

            <div className="border-t border-border pt-6">
              <FileUpload
                label="CV/Resume (PDF)"
                accept=".pdf,application/pdf"
                onUploadComplete={(url) => setValue('cv_path', url || '')}
                initialValue={cvPath}
                onUploadingChange={(value) => setUploading((prev) => ({ ...prev, cv: value }))}
                showAltText={false}
              />
            </div>

            <div className="flex gap-3 justify-between pt-6 border-t border-border">
              <Button
                type="button"
                variant="destructive"
                onClick={() => setResetDialogOpen(true)}
                disabled={isSaving || isUploading}
              >
                <RotateCcw className="w-4 h-4 mr-2" aria-hidden="true" />
                Reset All Fields
              </Button>

              <Button type="submit" disabled={isSaving || isUploading}>
                {isSaving ? (
                  <>
                    <Loader2 className="w-4 h-4 mr-2 animate-spin" aria-hidden="true" />
                    Saving...
                  </>
                ) : isUploading ? (
                  'Waiting for upload...'
                ) : (
                  'Save Changes'
                )}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <AlertDialog
        open={resetDialogOpen}
        onOpenChange={setResetDialogOpen}
        title="Reset Hero Section"
        description="This will clear all fields in the Hero Section. The uploaded image and CV will be deleted. This cannot be undone. Continue?"
        onConfirm={handleReset}
        confirmText="Reset"
        cancelText="Cancel"
        isDestructive={true}
        pendingText="Resetting..."
      />
    </div>
  )
}
