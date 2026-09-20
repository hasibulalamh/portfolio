/**
 * Server-side data access for the public site.
 *
 * Every helper here is safe to call from a server component and never throws:
 * if the backend is down, slow, or returns something unexpected, the caller
 * gets the supplied fallback instead of an exception. A portfolio that renders
 * stale-but-complete content beats one that 500s because the API restarted.
 */

// Exported so the /api-health probe route can report the exact base URL the
// fetches use — the field that diagnosed this project's production outage.
export const API_BASE_URL = (
  process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'
).replace(/\/$/, '')

// Mostly-static content, so cache for a minute rather than fetching per request.
// Callers that need a live answer (the /api-health probe) can override this.
const REVALIDATE_SECONDS = 60

/**
 * Build the structured log line emitted whenever a fetch degrades to a
 * fallback. A portfolio silently rendering default content is the failure
 * mode that is hardest to notice from the outside — the page still returns
 * 200 — so every fallback is a single greppable JSON line instead of prose.
 *
 * Render's log drains (and any tail -f | grep) can alert on `api_fallback`,
 * and the `attempted_url` field answers the question this project actually
 * hit in production: which base URL was the fetch really pointed at?
 *
 * Pure and string-only so it is unit-testable without mocking fetch.
 *
 * @param {string}   path     endpoint path, e.g. '/settings'
 * @param {?number}  status   HTTP status, or null when the request never got one
 * @param {?string}  error    error message, or null for HTTP-level failures
 * @param {boolean}  malformed true when the response was 2xx but missing `data`
 * @returns {string} single-line JSON
 */
export function formatApiFallbackLog({ path, status = null, error = null, malformed = false }) {
  const reason = malformed
    ? 'unexpected_shape'
    : status !== null
      ? `http_${status}`
      : 'network_error'

  const detail = malformed
    ? '2xx response was missing the `data` key'
    : error ?? `HTTP ${status}`

  return JSON.stringify({
    level: 'error',
    event: 'api_fallback',
    path,
    reason,
    status,
    detail,
    attempted_url: `${API_BASE_URL}${path}`,
    hint: 'the public site is rendering fallback content for this endpoint',
  })
}

/**
 * Fetch one endpoint and unwrap the API's { data, message, errors } envelope.
 *
 * @param {string}  path      endpoint path, e.g. '/hero'
 * @param {unknown} fallback  returned when the request or shape check fails
 * @param {object}  [options]
 * @param {number}  [options.revalidate] cache window in seconds; 0 bypasses
 *   the Data Cache entirely (used by the /api-health probe, which must not
 *   read the same cached entry the page render just used)
 */
export async function fetchFromApi(path, fallback = null, { revalidate = REVALIDATE_SECONDS } = {}) {
  const url = `${API_BASE_URL}${path}`

  try {
    const response = await fetch(url, {
      headers: { Accept: 'application/json' },
      next: { revalidate },
    })

    if (!response.ok) {
      console.error(formatApiFallbackLog({ path, status: response.status }))
      return fallback
    }

    const payload = await response.json()

    // A successful response always carries a `data` key. `data: null` is valid
    // (an unset singleton), so only a missing key counts as malformed.
    if (!payload || !('data' in payload)) {
      console.error(formatApiFallbackLog({ path, malformed: true }))
      return fallback
    }

    return payload.data ?? fallback
  } catch (error) {
    // Network error, DNS failure, timeout, invalid JSON — all non-fatal here.
    console.error(formatApiFallbackLog({ path, error: error.message }))
    return fallback
  }
}

/** Same as fetchFromApi but guarantees an array, so `.map` is always safe. */
export async function fetchList(path, fallback = []) {
  const data = await fetchFromApi(path, fallback)
  return Array.isArray(data) ? data : fallback
}

// Accepts the fetchFromApi options passthrough so the /api-health probe can
// request a cache-bypassing revalidate: 0 read.
export const getSettings = (options = {}) => fetchFromApi('/settings', null, options)
/**
 * Section visibility and ordering — drives both which sections the homepage
 * renders and which links the navbar shows, so one fetch answers both.
 */
export const getSectionVisibility = () => fetchList('/section-visibility')
export const getHero = () => fetchFromApi('/hero')
export const getAbout = () => fetchFromApi('/about')
export const getSkills = () => fetchList('/skills')
export const getTimeline = () => fetchList('/timeline')
export const getProjects = () => fetchList('/projects')
export const getApiShowcases = () => fetchList('/api-showcases')
export const getTestimonials = () => fetchList('/testimonials')
export const getContactInfo = () => fetchFromApi('/contact-info')

/** A single project with its case-study body, or null when the slug is unknown. */
export const getProject = (slug) =>
  fetchFromApi(`/projects/${encodeURIComponent(slug)}`)

/**
 * Load everything the homepage needs in one pass. Requests run concurrently,
 * and Promise.all is safe because no helper rejects.
 */
export async function getHomePageData() {
  const [
    settings,
    sections,
    hero,
    about,
    skills,
    timeline,
    projects,
    apiShowcases,
    testimonials,
    contactInfo,
  ] = await Promise.all([
    getSettings(),
    getSectionVisibility(),
    getHero(),
    getAbout(),
    getSkills(),
    getTimeline(),
    getProjects(),
    getApiShowcases(),
    getTestimonials(),
    getContactInfo(),
  ])

  return {
    settings,
    sections,
    hero,
    about,
    skills,
    timeline,
    projects,
    apiShowcases,
    testimonials,
    contactInfo,
  }
}
