import { z } from 'zod'

// Optional URL that also tolerates an empty input.
const optionalUrl = (message = 'Invalid URL') =>
  z.string().url(message).optional().or(z.literal(''))

// Optional email that also tolerates an empty input. Without the empty-string
// branch these fields block submission whenever they're left blank.
const optionalEmail = (message = 'Invalid email address') =>
  z.string().email(message).optional().or(z.literal(''))

export const loginSchema = z.object({
  email: z.string().email('Invalid email address'),
  password: z.string().min(6, 'Password must be at least 6 characters'),
})

export const settingsSchema = z.object({
  site_title: z.string().min(1, 'Site title is required'),
  brand_name: z.string().min(1, 'Brand name is required'),
  footer_text: z.string().optional(),
  copyright_text: z.string().optional(),
  accent_color: z.string().regex(/^#[0-9A-F]{6}$/i, 'Invalid color format'),
  favicon_path: z.string().optional(),
  // Mirrors SettingRequest's `in:image,text` and `max:32` so the form catches
  // both before a round trip.
  logo_type: z.enum(['image', 'text']),
  logo_text: z.string().max(32, 'Logo text must be 32 characters or fewer').optional(),
  logo_path: z.string().optional(),
  logo_alt: z.string().optional(),
})

// The nav item schema lived here until section_visibility took over both
// section rendering and nav links. The Sections page posts only ids, booleans
// and integers, all validated server-side, so no client schema replaces it.

export const heroSchema = z.object({
  heading: z.string().min(1, 'Heading is required'),
  subheading: z.string().optional(),

  // Rotating roles for the typewriter. Wrapped in an object rather than a bare
  // string array because useFieldArray needs a stable per-row key, and a
  // primitive array gives it nothing to hold onto — the Hero form maps these
  // back down to plain strings before submitting.
  roles: z.array(z.object({ value: z.string() })).max(12).optional(),

  // Orbiting tech badges. label is required; icon_slug is nullable (a badge
  // can render as text when no brand logo matches).
  tech_badges: z
    .array(
      z.object({
        label: z.string().min(1, 'Badge label is required'),
        icon_slug: z.string().nullable().optional(),
        logo_type: z.enum(['library', 'custom']).optional(),
        logo_url: z.string().url().nullable().optional(),
      }),
    )
    .max(6)
    .optional(),

  is_available: z.boolean().optional(),
  availability_label: z.string().max(120).optional(),

  cta_primary_text: z.string().optional(),
  cta_primary_link: z.string().optional(),
  cta_secondary_text: z.string().optional(),
  cta_secondary_link: z.string().optional(),
  image_path: z.string().optional(),
  image_alt: z.string().optional(),

  // Flexible social links replacing the fixed github_url / linkedin_url.
  social_links: z
    .array(
      z.object({
        platform: z.string().min(1, 'Platform is required'),
        url: z.string().min(1, 'URL is required'),
      }),
    )
    .max(12)
    .optional(),

  email: optionalEmail(),
  cv_path: z.string().optional(),
})

export const aboutSchema = z.object({
  bio_paragraph_1: z.string().min(1, 'Bio is required'),
  bio_paragraph_2: z.string().optional(),
  image_path: z.string().optional(),
  image_alt: z.string().optional(),
  // Blank rows are allowed here and stripped before submit; requiring min(1)
  // meant the default empty rows blocked the whole form.
  stats: z
    .array(
      z.object({
        label: z.string(),
        value: z.string(),
      })
    )
    .optional(),
})

export const skillCategorySchema = z.object({
  name: z.string().min(1, 'Category name is required'),
  order: z.number().int().nonnegative(),
})

export const skillSchema = z.object({
  // The category comes from the selected category in the UI, not a form field,
  // so it's supplied at submit time rather than validated here.
  name: z.string().min(1, 'Skill name is required'),
  // Simple Icons slug chosen via TechIconPicker. Optional: a skill with no
  // matching brand logo falls back to its two-letter badge.
  icon_slug: z.string().nullable().optional(),
  logo_type: z.enum(['library', 'custom']).optional(),
  logo_url: z.string().url().nullable().optional(),
  order: z.number().int().nonnegative().optional(),
})

/**
 * One schema for both timeline variants. `type` selects the labels the form
 * shows; the two shared fields are stored in the same columns either way, so
 * there is nothing type-conditional to validate here.
 *
 * The legacy year/title/company columns are derived server-side in
 * TimelineItemRequest, so the form no longer sends them.
 */
export const timelineItemSchema = z.object({
  type: z.enum(['education', 'experience']),
  // "Institute" for education, "Company" for experience.
  institute_or_company: z.string().min(1, 'This field is required'),
  // "Subject" for education, "Role" for experience.
  subject_or_role: z.string().min(1, 'This field is required'),
  // Strings, not numbers: an end year of "Present" is valid.
  start_year: z.string().min(4, 'Start year is required'),
  end_year: z.string().optional(),
  description: z.string().optional(),
  order: z.number().int().nonnegative(),
})

export const projectSchema = z.object({
  title: z.string().min(1, 'Title is required'),
  // Still required, but it now renders on the project details page rather than
  // on the card.
  description: z.string().min(1, 'Description is required'),
  image_path: z.string().optional(),
  image_alt: z.string().optional(),
  // The tags input is comma-separated text; it's split into an array on submit.
  tags: z.union([z.string(), z.array(z.string())]).optional(),
  // These live on `projects`, not `project_details` — they were previously on
  // the case-study schema, which did not match the backend columns.
  github_url: optionalUrl(),
  live_url: optionalUrl(),
  is_featured: z.boolean().optional(),
  order: z.number().int().nonnegative(),
})

export const projectDetailSchema = z.object({
  client: z.string().optional(),
  date_range: z.string().optional(),
  challenge: z.string().optional(),
  solution: z.string().optional(),
  results: z.array(z.string()).optional(),
  // Uploaded via the shared FileUpload component; PDFs and docs allowed.
  document_path: z.string().optional(),
  gallery_images: z.array(z.string()).optional(),
})

export const apiShowcaseSchema = z.object({
  // No longer required: the icon now comes from the Simple Icons picker below,
  // and a showcase can legitimately have neither (it falls back to a plug icon).
  icon_name: z.string().optional(),
  // Simple Icons slug chosen via TechIconPicker.
  icon_slug: z.string().nullable().optional(),
  logo_type: z.enum(['library', 'custom']).optional(),
  logo_url: z.string().url().nullable().optional(),
  title: z.string().min(1, 'Title is required'),
  description: z.string().optional(),
  endpoints: z.array(z.string()).optional(),
  order: z.number().int().nonnegative(),
})

export const testimonialSchema = z.object({
  quote: z.string().min(1, 'Quote is required'),
  author_name: z.string().min(1, 'Author name is required'),
  author_role: z.string().optional(),
  avatar_path: z.string().optional(),
  avatar_alt: z.string().optional(),
  order: z.number().int().nonnegative(),
})

export const contactInfoSchema = z.object({
  email: optionalEmail(),
  phone: z.string().optional(),
  location: z.string().optional(),
  calendly_link: optionalUrl(),
})

export const meetingRequestSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  email: z.string().email('Invalid email address'),
  preferred_time: z.string().optional(),
  message: z.string().optional(),
  admin_reply: z.string().optional(),
  // Internal note, never sent to the requester.
  admin_note: z.string().optional(),
})
