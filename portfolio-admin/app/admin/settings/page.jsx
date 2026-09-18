'use client'

import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { settingsSchema } from '@/lib/validation'
import { apiCall } from '@/lib/api'
import { useToast } from '@/components/ui/toast'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { FileUpload } from '@/components/admin/FileUpload'
import { AlertDialog } from '@/components/ui/alert-dialog'
import { TextLogo } from '@/components/ui/text-logo'
import { DEFAULT_LOGO_TEXT } from '@/lib/logo'
import { Loader2, RotateCcw } from 'lucide-react'

const SETTINGS_FIELDS = [
  'site_title',
  'brand_name',
  'footer_text',
  'copyright_text',
  'accent_color',
  'favicon_path',
  'logo_type',
  'logo_text',
  'logo_path',
  'logo_alt',
]

/**
 * Push an API settings record into form state.
 *
 * Shared by the initial load and the post-reset refill so the per-field coercion
 * cannot drift between them — `logo_type` in particular must never become '',
 * which would leave both radios unchecked.
 */
function applySettings(data, setValue) {
  SETTINGS_FIELDS.forEach((key) => {
    const value = data[key]
    if (key === 'accent_color') {
      setValue(key, value || '#000000')
    } else if (key === 'logo_type') {
      // A record written before this column existed comes back null; anything
      // that is not an explicit 'image' means the text logo.
      setValue(key, value === 'image' ? 'image' : 'text')
    } else {
      setValue(key, value ?? '')
    }
  })
}

export default function SettingsPage() {
  const { showToast } = useToast()
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [uploading, setUploading] = useState({ logo: false, favicon: false })
  const [resetDialogOpen, setResetDialogOpen] = useState(false)

  const isUploading = uploading.logo || uploading.favicon

  const {
    register,
    handleSubmit,
    formState: { errors },
    setValue,
    watch,
  } = useForm({
    resolver: zodResolver(settingsSchema),
    defaultValues: {
      site_title: '',
      brand_name: '',
      footer_text: '',
      copyright_text: '',
      accent_color: '#000000',
      favicon_path: '',
      logo_type: 'text',
      logo_text: '',
      logo_path: '',
      logo_alt: '',
    },
  })

  const faviconPath = watch('favicon_path')
  const logoPath = watch('logo_path')
  const logoAlt = watch('logo_alt')
  const logoType = watch('logo_type')
  const logoText = watch('logo_text')
  // Watched rather than read once: the logo-text hint and preview fall back to
  // the brand name, so editing brand_name above should update both live.
  const brandName = watch('brand_name')
  const accentColor = watch('accent_color')

  useEffect(() => {
    const loadSettings = async () => {
      const result = await apiCall('GET', '/admin/settings')
      // A brand-new site has no settings row yet, so data can legitimately be null.
      if (result.success && result.data && typeof result.data === 'object') {
        applySettings(result.data, setValue)
      } else if (!result.success) {
        showToast(result.message || 'Failed to load settings', 'error')
      }
      setIsLoading(false)
    }

    loadSettings()
  }, [setValue, showToast])

  const onSubmit = async (data) => {
    setIsSaving(true)
    const result = await apiCall('PUT', '/admin/settings', data)

    if (result.success) {
      showToast('Settings updated successfully', 'success')
    } else {
      showToast(result.message || 'Failed to save settings', 'error')
    }
    setIsSaving(false)
  }

  const handleReset = async () => {
    const result = await apiCall('POST', '/admin/settings/reset')

    if (result.success && result.data) {
      applySettings(result.data, setValue)
      showToast('Settings reset successfully', 'success')
    } else {
      showToast(result.message || 'Failed to reset settings', 'error')
    }
    setResetDialogOpen(false)
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center p-8">
        <Loader2 className="w-6 h-6 animate-spin text-muted-foreground" aria-hidden="true" />
        <span className="sr-only">Loading settings…</span>
      </div>
    )
  }

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-3xl font-bold">Site Settings</h1>
        <p className="text-muted-foreground mt-2">Manage your site configuration</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>General Settings</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label htmlFor="settings-site-title" className="block text-sm font-medium mb-2">Site Title</label>
                <Input
                  id="settings-site-title"
                  {...register('site_title')}
                  placeholder="My Portfolio"
                  disabled={isSaving}
                />
                {errors.site_title && (
                  <p className="text-sm text-destructive mt-1">{errors.site_title.message}</p>
                )}
              </div>

              <div>
                <label htmlFor="settings-brand-name" className="block text-sm font-medium mb-2">Brand Name</label>
                <Input
                  id="settings-brand-name"
                  {...register('brand_name')}
                  placeholder="Your Name"
                  disabled={isSaving}
                />
                {errors.brand_name && (
                  <p className="text-sm text-destructive mt-1">{errors.brand_name.message}</p>
                )}
              </div>
            </div>

            <div>
              <label htmlFor="settings-footer-text" className="block text-sm font-medium mb-2">Footer Text</label>
              <Textarea
                id="settings-footer-text"
                {...register('footer_text')}
                placeholder="Footer content"
                disabled={isSaving}
                rows={3}
              />
            </div>

            <div>
              <label htmlFor="settings-copyright" className="block text-sm font-medium mb-2">Copyright Text</label>
              <Input
                id="settings-copyright"
                {...register('copyright_text')}
                placeholder="© 2024 Your Name. All rights reserved."
                disabled={isSaving}
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label htmlFor="settings-accent-color" className="block text-sm font-medium mb-2">Accent Color</label>
                <div className="flex gap-2">
                  {/* Controlled rather than a second register() on the same name,
                      so the swatch and the hex field stay in sync. */}
                  <input
                    type="color"
                    aria-label="Pick accent color"
                    value={/^#[0-9A-F]{6}$/i.test(accentColor || '') ? accentColor : '#000000'}
                    onChange={(e) =>
                      setValue('accent_color', e.target.value, { shouldValidate: true })
                    }
                    disabled={isSaving}
                    className="w-16 h-10 rounded border border-border cursor-pointer disabled:cursor-not-allowed"
                  />
                  <Input
                    id="settings-accent-color"
                    {...register('accent_color')}
                    placeholder="#000000"
                    className="flex-1"
                    disabled={isSaving}
                  />
                </div>
                {errors.accent_color && (
                  <p className="text-sm text-destructive mt-1">{errors.accent_color.message}</p>
                )}
              </div>
            </div>

            <div className="border-t border-border pt-6 space-y-6">
              {/* Logo type picker.
                  Native radios rather than a custom control: two mutually
                  exclusive options is exactly what a radio group is for, and it
                  arrives keyboard- and screen-reader-accessible without extra
                  work. `register` on both inputs gives react-hook-form the
                  value, so switching is just a form state change — no save
                  needed to preview the other option. */}
              <fieldset>
                <legend className="block text-sm font-medium mb-2">Logo Type</legend>
                <p className="text-xs text-muted-foreground mb-3">
                  Upload an image, or render a word as a styled text logo using the
                  site&apos;s own gradient and glow — no image file needed.
                </p>
                <div className="flex flex-wrap gap-3">
                  {[
                    { value: 'image', label: 'Image Logo', hint: 'Use an uploaded file' },
                    { value: 'text', label: 'Text Logo', hint: 'Style a word or initials' },
                  ].map((option) => (
                    <label
                      key={option.value}
                      className={`flex flex-1 min-w-[200px] cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors ${
                        logoType === option.value
                          ? 'border-primary bg-primary/10'
                          : 'border-border hover:bg-muted'
                      } ${isSaving ? 'cursor-not-allowed opacity-60' : ''}`}
                    >
                      <input
                        type="radio"
                        value={option.value}
                        {...register('logo_type')}
                        disabled={isSaving}
                        className="mt-0.5 h-4 w-4 shrink-0"
                      />
                      <span>
                        <span className="block text-sm font-medium">{option.label}</span>
                        <span className="block text-xs text-muted-foreground">
                          {option.hint}
                        </span>
                      </span>
                    </label>
                  ))}
                </div>
                {errors.logo_type && (
                  <p className="text-sm text-destructive mt-2">{errors.logo_type.message}</p>
                )}
              </fieldset>

              {/* Only the selected option's editor is shown, but BOTH fields stay
                  registered and are submitted either way — that is what lets an
                  admin flip back to the other option without having re-uploaded
                  or re-typed. Hiding is done by not rendering the inactive
                  editor; the form value behind it is untouched. */}
              {logoType === 'image' ? (
                <div>
                  <FileUpload
                    label="Logo"
                    accept="image/*"
                    uploadType="logo"
                    onUploadComplete={(url) => setValue('logo_path', url || '')}
                    initialValue={logoPath}
                    onUploadingChange={(value) => setUploading((prev) => ({ ...prev, logo: value }))}
                    altText={logoAlt || ''}
                    onAltTextChange={(value) => setValue('logo_alt', value)}
                  />
                </div>
              ) : (
                <div>
                  <label htmlFor="settings-logo-text" className="block text-sm font-medium mb-2">
                    Logo Text
                  </label>
                  <div className="grid gap-4 md:grid-cols-2 md:items-stretch">
                    <div>
                      <Input
                        id="settings-logo-text"
                        {...register('logo_text')}
                        placeholder={DEFAULT_LOGO_TEXT}
                        maxLength={32}
                        disabled={isSaving}
                        aria-describedby="settings-logo-text-hint"
                      />
                      {errors.logo_text && (
                        <p className="text-sm text-destructive mt-1">
                          {errors.logo_text.message}
                        </p>
                      )}
                      <p id="settings-logo-text-hint" className="text-xs text-muted-foreground mt-2">
                        A word or initials — e.g. &ldquo;Hasibul.&rdquo;, &ldquo;H.&rdquo;,
                        &ldquo;HA&rdquo;. Leave blank to use your brand name
                        {brandName?.trim() ? ` (“${brandName.trim()}”)` : ''}.
                      </p>
                    </div>

                    {/* Live preview. Renders through the very same TextLogo the
                        public site uses, so this is the real thing at a larger
                        size rather than an approximation of it. */}
                    <div
                      className="flex flex-col items-center justify-center gap-2 rounded-lg border border-border bg-background/40 p-4"
                      data-testid="logo-text-preview"
                    >
                      <span className="text-xs uppercase tracking-widest text-muted-foreground">
                        Live preview
                      </span>
                      {/* Mirrors resolveLogo's fallback chain so the preview shows
                          what would actually render, including when the field is
                          empty and the brand name takes over. */}
                      <TextLogo
                        text={logoText?.trim() || brandName?.trim() || DEFAULT_LOGO_TEXT}
                        size="xl"
                        data-testid="logo-text-preview-mark"
                      />
                    </div>
                  </div>
                </div>
              )}

              <div>
                <FileUpload
                  label="Favicon"
                  accept="image/*"
                  maxSize={1 * 1024 * 1024}
                  uploadType="favicon"
                  onUploadComplete={(url) => setValue('favicon_path', url || '')}
                  initialValue={faviconPath}
                  onUploadingChange={(value) => setUploading((prev) => ({ ...prev, favicon: value }))}
                  showAltText={false}
                />
              </div>
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

              <Button
                type="submit"
                disabled={isSaving || isUploading}
              >
                {isSaving ? (
                  <>
                    <Loader2 className="w-4 h-4 mr-2 animate-spin" aria-hidden="true" />
                    Saving...
                  </>
                ) : isUploading ? (
                  'Waiting for upload...'
                ) : (
                  'Save Settings'
                )}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <AlertDialog
        open={resetDialogOpen}
        onOpenChange={setResetDialogOpen}
        title="Reset Site Settings"
        description="This will clear all fields in Site Settings. Uploaded files will be deleted. This cannot be undone. Continue?"
        onConfirm={handleReset}
        confirmText="Reset"
        cancelText="Cancel"
        isDestructive={true}
        pendingText="Resetting..."
      />
    </div>
  )
}
