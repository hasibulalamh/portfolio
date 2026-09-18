'use client'

import { useEffect, useId, useMemo, useRef, useState } from 'react'
import { Search, X } from 'lucide-react'
import { getIcon, searchIcons } from '@/lib/tech-icons'
import { TechIconTile } from './TechIcon'
import { FileUpload } from './FileUpload'
import { Input } from '@/components/ui/input'

/**
 * Type a technology name, pick its official logo.
 *
 * Search runs entirely client-side against the bundled Simple Icons catalogue
 * (all 3453 entries), so there is no backend endpoint and no request per
 * keystroke. The value handed back is the slug — never an image — because the
 * icon is rendered from the slug at display time.
 *
 * Used by both the Skills form and the API Showcase form.
 *
 * @param {string|null} value      currently selected slug
 * @param {(slug: string|null) => void} onChange
 * @param {string} query           optional seed for the search box, e.g. the
 *                                 skill name the admin already typed
 */
export function TechIconPicker({
  value = null,
  onChange,
  label = 'Technology Logo',
  query: seedQuery = '',
  disabled = false,
  logoType = 'library',
  logoUrl = null,
  onLogoChange,
}) {
  const [query, setQuery] = useState('')
  const [isOpen, setIsOpen] = useState(false)
  const [highlighted, setHighlighted] = useState(0)
  const containerRef = useRef(null)
  const inputId = useId()
  const listId = useId()

  const selected = getIcon(value)
  const results = useMemo(() => searchIcons(query), [query])

  // Close on outside click; mirrors the Header account menu's behaviour.
  useEffect(() => {
    if (!isOpen) return

    const handlePointerDown = (event) => {
      if (containerRef.current && !containerRef.current.contains(event.target)) {
        setIsOpen(false)
      }
    }

    document.addEventListener('mousedown', handlePointerDown)
    return () => document.removeEventListener('mousedown', handlePointerDown)
  }, [isOpen])

  // Reset the highlight whenever the result set changes, so Enter cannot
  // select a stale row left over from a previous query.
  useEffect(() => {
    setHighlighted(0)
  }, [query])

  const choose = (slug) => {
    onChange?.(slug)
    onLogoChange?.('library', null)
    setQuery('')
    setIsOpen(false)
  }

  const handleKeyDown = (event) => {
    if (event.key === 'Escape') {
      setIsOpen(false)
      return
    }

    if (!isOpen || results.length === 0) return

    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setHighlighted((i) => (i + 1) % results.length)
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setHighlighted((i) => (i - 1 + results.length) % results.length)
    } else if (event.key === 'Enter') {
      // The picker lives inside a form, so a bare Enter would submit it.
      event.preventDefault()
      choose(results[highlighted].slug)
    }
  }

  return (
    <div ref={containerRef} className="relative">
      <label htmlFor={inputId} className="block text-sm font-medium mb-1">
        {label}
      </label>

      <div className="mb-3 flex gap-2" role="tablist" aria-label={`${label} source`}>
        <button
          type="button"
          role="tab"
          aria-selected={logoType !== 'custom'}
          onClick={() => onLogoChange?.('library', null)}
          disabled={disabled}
          className={`rounded-md px-3 py-1.5 text-xs font-medium ${
            logoType !== 'custom' ? 'bg-primary text-primary-foreground' : 'border border-border'
          }`}
        >
          Search Library
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={logoType === 'custom'}
          onClick={() => onLogoChange?.('custom', logoUrl)}
          disabled={disabled}
          className={`rounded-md px-3 py-1.5 text-xs font-medium ${
            logoType === 'custom' ? 'bg-primary text-primary-foreground' : 'border border-border'
          }`}
        >
          Upload Custom Logo
        </button>
      </div>

      {logoType === 'custom' ? (
        <FileUpload
          label={null}
          accept="image/svg+xml,image/png,image/jpeg,image/webp"
          maxSize={5 * 1024 * 1024}
          uploadType="technology-logo"
          initialValue={logoUrl}
          showAltText={false}
          onUploadComplete={(url) => onLogoChange?.('custom', url)}
        />
      ) : (
      <div className="flex items-center gap-2">
        {/* Preview of the current selection, or an empty placeholder box so the
            row does not reflow once something is picked. Uses the same tinted
            tile as the dropdown rows and the public site, so what the admin
            sees here is what visitors get. */}
        <div
          className="flex h-10 w-10 shrink-0 items-center justify-center"
          data-testid="tech-icon-preview"
          data-slug={value || ''}
        >
          {selected ? (
            <TechIconTile
              slug={selected.slug}
              title={selected.title}
              size="h-10 w-10"
              iconSize="h-6 w-6"
            />
          ) : (
            <span className="flex h-10 w-10 items-center justify-center rounded-md border border-border bg-background">
              <Search className="h-4 w-4 text-muted-foreground" aria-hidden="true" />
            </span>
          )}
        </div>

        <div className="relative flex-1">
          <Input
            id={inputId}
            type="text"
            role="combobox"
            autoComplete="off"
            aria-expanded={isOpen}
            aria-controls={listId}
            aria-autocomplete="list"
            disabled={disabled}
            value={query}
            placeholder={
              selected ? `${selected.title} — type to change` : 'Type a technology, e.g. Laravel'
            }
            onChange={(event) => {
              setQuery(event.target.value)
              setIsOpen(true)
            }}
            onFocus={() => {
              // Seeding from the sibling name field means the admin usually
              // sees the right logo without typing anything twice.
              if (!query && seedQuery) setQuery(seedQuery)
              setIsOpen(true)
            }}
            onKeyDown={handleKeyDown}
          />
        </div>

        {selected && (
          <button
            type="button"
            onClick={() => choose(null)}
            aria-label="Clear selected logo"
            className="shrink-0 rounded-md p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
          >
            <X className="h-4 w-4" aria-hidden="true" />
          </button>
        )}
      </div>

      {selected && (
        <p className="mt-1 text-xs text-muted-foreground">
          Selected: {selected.title}{' '}
          <code className="rounded bg-muted px-1">{selected.slug}</code>
        </p>
      )}

      {isOpen && query.trim().length > 0 && (
        <ul
          id={listId}
          role="listbox"
          aria-label="Matching technology logos"
          className="absolute z-50 mt-1 max-h-80 w-full overflow-y-auto rounded-lg border border-border bg-popover p-1.5 shadow-xl"
        >
          {results.length === 0 ? (
            <li className="px-3 py-2.5 text-sm text-muted-foreground">
              No logo found for &ldquo;{query}&rdquo;. Leave it empty to use the default icon.
            </li>
          ) : (
            results.map((icon, index) => {
              const isHighlighted = index === highlighted
              // searchIcons already ranks exact matches first, so the head of
              // the list is the answer the admin most likely wants. Marking it
              // saves reading the whole dropdown to confirm the obvious.
              const isBest = index === 0

              return (
                <li key={icon.slug}>
                  <button
                    type="button"
                    role="option"
                    aria-selected={isHighlighted}
                    data-slug={icon.slug}
                    onClick={() => choose(icon.slug)}
                    onMouseEnter={() => setHighlighted(index)}
                    className={`flex w-full items-center gap-3 rounded-md px-2.5 py-2.5 text-left transition-colors ${
                      isHighlighted
                        ? 'bg-accent/15 ring-1 ring-inset ring-accent/40'
                        : 'hover:bg-accent/10'
                    }`}
                  >
                    <TechIconTile slug={icon.slug} title={icon.title} />

                    <span className="flex min-w-0 flex-1 flex-col">
                      <span
                        className={`truncate text-sm ${
                          isBest ? 'font-semibold text-foreground' : 'font-medium text-foreground/90'
                        }`}
                      >
                        {icon.title}
                      </span>
                      <code className="truncate text-[11px] leading-tight text-muted-foreground/70">
                        {icon.slug}
                      </code>
                    </span>

                    {isBest && (
                      <span className="shrink-0 rounded-full bg-accent/15 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-accent">
                        Best match
                      </span>
                    )}
                  </button>
                </li>
              )
            })
          )}
        </ul>
      )}
      )}
    </div>
  )
}
