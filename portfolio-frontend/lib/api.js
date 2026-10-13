/**
 * Server-side data access for the public site.
 *
 * Every helper here is safe to call from a server component and never throws:
 * if the backend is down, slow, or returns something unexpected, the caller
 * gets the supplied fallback instead of an exception. A portfolio that renders
 * stale-but-complete content beats one that 500s because the API restarted.
 *
 * "Down" has three shapes in this project's history, and each one has an
 * explicit budget here:
 *
 *   1. Unreachable or erroring   → fall back, log `api_fallback`.
 *   2. Rate limited (429)        → wait out a *short* Retry-After once, then
 *                                  fall back; never retry in a loop.
 *   3. Accepting but not answering (cold start, stuck worker, a proxy with
 *      nowhere to route)        → the hard timeout below. Without it, one hung
 *      request holds the whole page render open, which is exactly how the site
 *      came back as a blank 52-minute response instead of a fast fallback.
 */

// Exported so the /api-health probe route can report the exact base URL the
// fetches use — the field that diagnosed this project's production outage.
export const API_BASE_URL = (
  process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'
).replace(/\/$/, '')

/**
 * Hard ceiling on a single server-side API call.
 *
 * Render's free tier spins the API container down, so a cold request can spend
 * longer connecting than the whole page render should take. Eight seconds is
 * long enough for a sleeping container to wake and answer a cached query, and
 * short enough that a visitor sees fallback content rather than a stalled page.
 * Overridable per call so tests do not have to wait it out.
 */
export const API_TIMEOUT_MS = 8000

/**
 * Longest Retry-After we are willing to sit out mid-render.
 *
 * A 429 whose own advice is "come back in a second" is worth obeying: the
 * content is live and one short wait turns a fallback page into a real one.
 * Anything longer belongs to the visitor's next visit, not to this render.
 */
const MAX_RETRY_AFTER_MS = 1000

// Mostly-static content, so cache for a minute rather than fetching per request.
// Callers that need a live answer (the /api-health probe) can override this.
const REVALIDATE_SECONDS = 60

/**
 * Read a Retry-After header, which HTTP allows to be either a number of
 * seconds or an HTTP date. Returns milliseconds to wait, or null when the
 * header is absent or unparseable (in which case the caller just falls back).
 *
 * Pure, and takes `now` so the date form is testable without mocking the clock.
 *
 * @param {?string} header
 * @param {number}  now  epoch ms used to interpret the date form
 * @returns {?number}
 */
export function parseRetryAfter(header, now = Date.now()) {
  if (!header) {
    return null
  }

  const seconds = Number(header)

  if (Number.isFinite(seconds)) {
    return seconds < 0 ? null : Math.round(seconds * 1000)
  }

  const timestamp = Date.parse(header)

  if (Number.isNaN(timestamp)) {
    return null
  }

  return Math.max(0, timestamp - now)
}

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
 * `reason` separates the three shapes above, so an alert can tell "the API is
 * rate limiting us" (http_429) from "the API never answered" (timeout) from
 * "the API is broken" (http_5xx / network_error).
 *
 * Pure and string-only so it is unit-testable without mocking fetch.
 *
 * @param {string}   path       endpoint path, e.g. '/settings'
 * @param {?number}  status     HTTP status, or null when the request never got one
 * @param {?string}  error      error message, or null for HTTP-level failures
 * @param {boolean}  malformed  true when the response was 2xx but missing `data`
 * @param {boolean}  timeout    true when the request was aborted by the timeout
 * @param {?number}  timeoutMs  the ceiling that was exceeded
 * @param {?number}  retryAfterMs  Retry-After the API asked for, in ms
 * @returns {string} single-line JSON
 */
export function formatApiFallbackLog({
  path,
  status = null,
  error = null,
  malformed = false,
  timeout = false,
  timeoutMs = API_TIMEOUT_MS,
  retryAfterMs = null,
}) {
  const reason = malformed
    ? 'unexpected_shape'
    : timeout
      ? 'timeout'
      : status !== null
        ? `http_${status}`
        : 'network_error'

  const detail = malformed
    ? '2xx response was missing the `data` key'
    : timeout
      ? `no response within ${timeoutMs}ms`
      : error ?? `HTTP ${status}`

  return JSON.stringify({
    level: 'error',
    event: 'api_fallback',
    path,
    reason,
    status,
    detail,
    // Only present when the API sent one, so its absence is meaningful.
    ...(retryAfterMs === null ? {} : { retry_after_ms: retryAfterMs }),
    attempted_url: `${API_BASE_URL}${path}`,
    hint: 'the public site is rendering fallback content for this endpoint',
  })
}

/** One fetch, bounded by the timeout. Shared by the fetch and probe paths. */
function requestOnce(url, { revalidate, timeoutMs }) {
  return fetch(url, {
    headers: { Accept: 'application/json' },
    next: { revalidate },
    // Next.js's patched fetch honours `signal` (it is passed through to the
    // runtime and skipped only for background revalidation of a stale entry),
    // so an unanswered request cannot outlive this render.
    signal: AbortSignal.timeout(timeoutMs),
  })
}

/** Wait, but not forever — the abort in requestOnce bounds the retry anyway. */
function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
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
 * @param {number}  [options.timeoutMs] override the request timeout
 */
export async function fetchFromApi(
  path,
  fallback = null,
  { revalidate = REVALIDATE_SECONDS, timeoutMs = API_TIMEOUT_MS } = {},
) {
  const url = `${API_BASE_URL}${path}`

  try {
    let response = await requestOnce(url, { revalidate, timeoutMs })
    let retryAfterMs = response.status === 429 ? parseRetryAfter(response.headers.get('retry-after')) : null

    // A 429 is the API asking us to come back shortly. Honour it exactly once,
    // and only when "shortly" fits inside a page render; otherwise take the
    // fallback now and let the log line carry the wait the API asked for.
    if (retryAfterMs !== null && retryAfterMs <= MAX_RETRY_AFTER_MS) {
      await delay(retryAfterMs)
      response = await requestOnce(url, { revalidate, timeoutMs })
      retryAfterMs = response.status === 429 ? parseRetryAfter(response.headers.get('retry-after')) : null
    }

    if (!response.ok) {
      console.error(formatApiFallbackLog({ path, status: response.status, retryAfterMs }))
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
    // AbortSignal.timeout() rejects with a TimeoutError DOMException, which is
    // worth its own reason: "the API is too slow" and "the API refused the
    // connection" send an operator to different places.
    const timedOut = error?.name === 'TimeoutError' || error?.name === 'AbortError'

    console.error(formatApiFallbackLog({
      path,
      error: timedOut ? null : error.message,
      timeout: timedOut,
      timeoutMs,
    }))

    return fallback
  }
}

/** Same as fetchFromApi but guarantees an array, so `.map` is always safe. */
export async function fetchList(path, fallback = []) {
  const data = await fetchFromApi(path, fallback)
  return Array.isArray(data) ? data : fallback
}

/**
 * One live, uncached request that reports *what came back* as well as the
 * payload — the difference between "the site is up" and "the site is up and
 * the API answered us, in this many milliseconds, with this status".
 *
 * fetchFromApi deliberately hides both facts (it returns data or a fallback,
 * and logs), which is right for rendering and useless for a health probe: a
 * 503 from this route currently cannot say whether the API was rate limiting
 * us, silent, or broken. This returns them.
 *
 * Never throws.
 *
 * @returns {Promise<{ok: boolean, status: ?number, latencyMs: number,
 *   data: unknown, retryAfterMs: ?number, error: ?string}>}
 */
export async function probeApi(path, { timeoutMs = API_TIMEOUT_MS } = {}) {
  const url = `${API_BASE_URL}${path}`
  const startedAt = Date.now()

  try {
    // revalidate: 0 bypasses the Data Cache, so this reports this request's
    // answer rather than the entry a page render may have cached.
    const response = await requestOnce(url, { revalidate: 0, timeoutMs })
    const latencyMs = Date.now() - startedAt

    if (!response.ok) {
      return {
        ok: false,
        status: response.status,
        latencyMs,
        data: null,
        retryAfterMs: response.status === 429 ? parseRetryAfter(response.headers.get('retry-after')) : null,
        error: null,
      }
    }

    const payload = await response.json()
    const wellFormed = Boolean(payload) && 'data' in payload

    return {
      ok: wellFormed,
      status: response.status,
      latencyMs,
      data: wellFormed ? payload.data ?? null : null,
      retryAfterMs: null,
      error: wellFormed ? null : 'response was missing the `data` key',
    }
  } catch (error) {
    const timedOut = error?.name === 'TimeoutError' || error?.name === 'AbortError'

    return {
      ok: false,
      status: null,
      latencyMs: Date.now() - startedAt,
      data: null,
      retryAfterMs: null,
      error: timedOut ? `no response within ${timeoutMs}ms` : error.message,
    }
  }
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
 *
 * Ten requests at once is why the API's public-read limiter is sized per
 * minute rather than per second, and why build-time prerendering (this
 * function, plus one call per project page) stays far inside it.
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
