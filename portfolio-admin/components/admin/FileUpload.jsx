'use client'

import { useEffect, useId, useRef, useState } from 'react'
import { Upload, Loader2, X } from 'lucide-react'
import { apiCall } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

// Matches a file against an `accept` string, supporting both MIME types
// ("image/png", "image/*") and file extensions (".pdf") — the browser accepts
// both, so validation has to as well.
const matchesAccept = (file, accept) => {
  const patterns = accept
    .split(',')
    .map((entry) => entry.trim().toLowerCase())
    .filter(Boolean)

  if (patterns.length === 0) return true

  const fileType = (file.type || '').toLowerCase()
  const fileName = (file.name || '').toLowerCase()

  return patterns.some((pattern) => {
    if (pattern.startsWith('.')) {
      return fileName.endsWith(pattern)
    }
    if (pattern.endsWith('/*')) {
      const category = pattern.slice(0, -2)
      return fileType.startsWith(category + '/')
    }
    return fileType === pattern
  })
}

export function FileUpload({
  label,
  accept = 'image/*',
  maxSize = 5 * 1024 * 1024,
  onUploadComplete,
  initialValue = null,
  uploadType = 'generic',
  // Lets the parent form disable its submit button while an upload is in
  // flight, so a save can't race the URL it depends on.
  onUploadingChange,
  altText = '',
  onAltTextChange,
  showAltText = true,
}) {
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState(null)
  const [preview, setPreview] = useState(initialValue)
  const [fileName, setFileName] = useState(null)
  const inputRef = useRef(null)
  const inputId = useId()
  const altId = useId()

  useEffect(() => {
    setPreview(initialValue)
  }, [initialValue])

  // Held in a ref so an inline arrow from the parent doesn't retrigger the
  // effect on every render.
  const uploadingChangeRef = useRef(onUploadingChange)
  uploadingChangeRef.current = onUploadingChange

  useEffect(() => {
    uploadingChangeRef.current?.(isLoading)
  }, [isLoading])

  const isImage = Boolean(preview) && !/\.(pdf|docx?|txt)$/i.test(preview)

  const handleFileSelect = async (file) => {
    if (!file) return

    // Validate against the accept list, including the "image/*" default —
    // previously the default was skipped entirely, so any file passed.
    if (accept && !matchesAccept(file, accept)) {
      setError(`File type must be one of: ${accept}`)
      return
    }

    if (file.size > maxSize) {
      setError(`File size must be less than ${maxSize / 1024 / 1024}MB`)
      return
    }

    setError(null)
    setIsLoading(true)
    setFileName(file.name)

    try {
      const formData = new FormData()
      formData.append('file', file)
      formData.append('type', uploadType)

      const result = await apiCall('POST', '/admin/upload', formData)

      if (result.success) {
        setPreview(result.data.url)
        onUploadComplete(result.data.url)
      } else {
        setError(result.message || 'Upload failed')
        setFileName(null)
      }
    } catch (err) {
      setError('Upload failed. Please try again.')
      setFileName(null)
      console.error('File upload failed:', err)
    } finally {
      setIsLoading(false)
    }
  }

  const handleDragOver = (e) => {
    e.preventDefault()
    e.currentTarget.classList.add('border-primary', 'bg-primary/5')
  }

  const handleDragLeave = (e) => {
    e.currentTarget.classList.remove('border-primary', 'bg-primary/5')
  }

  const handleDrop = (e) => {
    e.preventDefault()
    e.currentTarget.classList.remove('border-primary', 'bg-primary/5')
    if (isLoading) return
    const file = e.dataTransfer.files[0]
    handleFileSelect(file)
  }

  const handleInputChange = (e) => {
    const file = e.target.files?.[0]
    handleFileSelect(file)
  }

  const handleClear = () => {
    setPreview(null)
    setFileName(null)
    setError(null)
    if (inputRef.current) {
      inputRef.current.value = ''
    }
    onUploadComplete(null)
    onAltTextChange?.('')
  }

  return (
    <div className="space-y-2">
      {label && (
        <label htmlFor={inputId} className="block text-sm font-medium">
          {label}
        </label>
      )}

      <div
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onDrop={handleDrop}
        className="relative border-2 border-dashed border-border rounded-lg p-6 hover:border-primary transition-colors"
      >
        {/* The invisible input only covers the drop zone while there is nothing
            to interact with underneath it. Once a preview is shown it shrinks to
            the "Replace" control so the Remove button stays clickable. */}
        <input
          id={inputId}
          ref={inputRef}
          type="file"
          accept={accept}
          onChange={handleInputChange}
          disabled={isLoading}
          aria-describedby={error ? `${inputId}-error` : undefined}
          className={
            preview && !isLoading
              ? 'sr-only'
              : 'absolute inset-0 w-full h-full opacity-0 cursor-pointer disabled:cursor-not-allowed'
          }
        />

        <div className="flex flex-col items-center justify-center gap-2">
          {isLoading ? (
            <div className="flex flex-col items-center gap-2 pointer-events-none">
              <Loader2 className="w-8 h-8 text-primary animate-spin" />
              <p className="text-sm text-muted-foreground">Uploading...</p>
            </div>
          ) : preview ? (
            <>
              <div className="w-32 h-32 rounded-lg overflow-hidden border border-border bg-muted flex items-center justify-center">
                {isImage ? (
                  <img
                    src={preview}
                    alt={altText || 'Uploaded file preview'}
                    className="w-full h-full object-cover"
                  />
                ) : (
                  <span className="text-xs text-muted-foreground px-2 text-center break-all">
                    {fileName || 'Uploaded file'}
                  </span>
                )}
              </div>
              {fileName && (
                <p className="text-sm text-muted-foreground truncate max-w-xs">{fileName}</p>
              )}
              <div className="flex gap-2 mt-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => inputRef.current?.click()}
                >
                  <Upload className="w-4 h-4 mr-2" />
                  Replace
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={handleClear}
                >
                  <X className="w-4 h-4 mr-2" />
                  Remove
                </Button>
              </div>
            </>
          ) : (
            <div className="flex flex-col items-center gap-2 pointer-events-none">
              <Upload className="w-8 h-8 text-muted-foreground" />
              <p className="text-sm font-medium">Drag and drop or click to upload</p>
              <p className="text-xs text-muted-foreground">
                Accepts {accept} · Max size: {maxSize / 1024 / 1024}MB
              </p>
            </div>
          )}
        </div>
      </div>

      {showAltText && isImage && (
        <div>
          <label htmlFor={altId} className="block text-sm font-medium mb-1">
            Alt text <span className="text-muted-foreground font-normal">(optional)</span>
          </label>
          <Input
            id={altId}
            value={altText}
            onChange={(e) => onAltTextChange?.(e.target.value)}
            placeholder="Describe this image for screen readers"
          />
        </div>
      )}

      {error && (
        <p id={`${inputId}-error`} role="alert" className="text-sm text-destructive">
          {error}
        </p>
      )}
    </div>
  )
}
