import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  API_TIMEOUT_MS,
  fetchFromApi,
  fetchList,
  parseRetryAfter,
  probeApi,
} from '../../lib/api.js'

/**
 * fetchFromApi()'s failure budgets — the behaviour a page render depends on
 * when the API is not simply "down".
 *
 * Each case here is a shape the production incident actually took:
 *
 *   - the API accepted the connection and never answered (Render's free tier
 *     spinning the container up), so the render hung instead of falling back;
 *   - the API answered 429, and the site kept rendering fallback content with
 *     nothing in the logs saying the API had asked us to slow down.
 *
 * These are the only places in the codebase that know about timeouts and
 * Retry-After, so they are pinned here rather than through a rendered page.
 */
function jsonResponse(body, { status = 200, headers = {} } = {}) {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: {
      get: (name) => headers[String(name).toLowerCase()] ?? null,
    },
    json: async () => body,
  }
}

/** A fetch that never answers, but does honour the abort signal. */
function hangingFetch(url, init) {
  return new Promise((_resolve, reject) => {
    init.signal.addEventListener('abort', () => reject(init.signal.reason))
  })
}

let errorSpy

function captureFallbackLogs() {
  errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
  return () => errorSpy.mock.calls.map(([line]) => JSON.parse(line))
}

afterEach(() => {
  vi.unstubAllGlobals()
  errorSpy?.mockRestore()
})

describe('fetchFromApi timeout', () => {
  it('falls back instead of hanging when the API never answers', async () => {
    vi.stubGlobal('fetch', vi.fn(hangingFetch))
    const logs = captureFallbackLogs()

    const startedAt = Date.now()
    const result = await fetchFromApi('/settings', { fallback: true }, { timeoutMs: 25 })

    expect(result).toEqual({ fallback: true })
    // The whole point: bounded, not "eventually".
    expect(Date.now() - startedAt).toBeLessThan(2000)

    const [entry] = logs()
    expect(entry.event).toBe('api_fallback')
    expect(entry.reason).toBe('timeout')
    expect(entry.detail).toContain('25ms')
    expect(entry.status).toBeNull()
  })

  it('applies the timeout by default (the shipped ceiling, not a test artefact)', () => {
    expect(API_TIMEOUT_MS).toBe(8000)
  })
})

describe('fetchFromApi on 429', () => {
  it('logs http_429 and falls back when Retry-After is absent', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: null }, { status: 429 }))
    vi.stubGlobal('fetch', fetchMock)
    const logs = captureFallbackLogs()

    const result = await fetchFromApi('/settings', 'fallback')

    expect(result).toBe('fallback')
    // No Retry-After means no waiting: one request, no retry loop.
    expect(fetchMock).toHaveBeenCalledTimes(1)

    const [entry] = logs()
    expect(entry.reason).toBe('http_429')
    expect(entry.status).toBe(429)
    expect(entry.retry_after_ms).toBeUndefined()
  })

  it('waits out a short Retry-After once, then uses the live response', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({ data: null }, { status: 429, headers: { 'retry-after': '0' } }))
      .mockResolvedValueOnce(jsonResponse({ data: { site_title: 'Live' } }))
    vi.stubGlobal('fetch', fetchMock)
    const logs = captureFallbackLogs()

    const result = await fetchFromApi('/settings', 'fallback')

    expect(result).toEqual({ site_title: 'Live' })
    expect(fetchMock).toHaveBeenCalledTimes(2)
    // Recovering on the retry means no fallback line at all.
    expect(logs()).toHaveLength(0)
  })

  it('does not wait out a long Retry-After, and reports what was asked', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValue(jsonResponse({ data: null }, { status: 429, headers: { 'retry-after': '60' } }))
    vi.stubGlobal('fetch', fetchMock)
    const logs = captureFallbackLogs()

    const result = await fetchFromApi('/settings', 'fallback')

    expect(result).toBe('fallback')
    expect(fetchMock).toHaveBeenCalledTimes(1)

    const [entry] = logs()
    expect(entry.reason).toBe('http_429')
    expect(entry.retry_after_ms).toBe(60_000)
  })

  it('stops after one retry even if the API keeps rate limiting', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValue(jsonResponse({ data: null }, { status: 429, headers: { 'retry-after': '0' } }))
    vi.stubGlobal('fetch', fetchMock)
    const logs = captureFallbackLogs()

    const result = await fetchFromApi('/settings', 'fallback')

    expect(result).toBe('fallback')
    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(logs()[0].reason).toBe('http_429')
  })
})

describe('fetchFromApi request shape', () => {
  it('sends the ISR window and an abort signal on every request', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [] }))
    vi.stubGlobal('fetch', fetchMock)

    await fetchFromApi('/hero', null)

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toMatch(/\/hero$/)
    expect(init.next).toEqual({ revalidate: 60 })
    expect(init.signal).toBeInstanceOf(AbortSignal)
    expect(init.headers.Accept).toBe('application/json')
  })

  it('fetchList still guarantees an array on failure', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: 'not-a-list' })))
    captureFallbackLogs()

    expect(await fetchList('/skills')).toEqual([])
  })
})

describe('parseRetryAfter', () => {
  it('reads the seconds form', () => {
    expect(parseRetryAfter('30')).toBe(30_000)
    expect(parseRetryAfter('0')).toBe(0)
  })

  it('reads the HTTP-date form', () => {
    const now = Date.parse('2026-10-12T00:00:00Z')
    expect(parseRetryAfter('Mon, 12 Oct 2026 00:00:02 GMT', now)).toBe(2000)
  })

  it('ignores anything it cannot use', () => {
    expect(parseRetryAfter(null)).toBeNull()
    expect(parseRetryAfter('')).toBeNull()
    expect(parseRetryAfter('soon')).toBeNull()
    expect(parseRetryAfter('-5')).toBeNull()
  })
})

describe('probeApi', () => {
  it('reports the status and the latency of a successful read', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: { id: 1 } })))

    const probe = await probeApi('/settings')

    expect(probe.ok).toBe(true)
    expect(probe.status).toBe(200)
    expect(probe.data).toEqual({ id: 1 })
    expect(probe.latencyMs).toBeGreaterThanOrEqual(0)
    expect(probe.retryAfterMs).toBeNull()
  })

  it('surfaces a 429 (and its Retry-After) instead of a bare failure', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(jsonResponse(null, { status: 429, headers: { 'retry-after': '12' } })),
    )

    const probe = await probeApi('/settings')

    expect(probe.ok).toBe(false)
    expect(probe.status).toBe(429)
    expect(probe.retryAfterMs).toBe(12_000)
  })

  it('reports a timeout as a timeout, with no status', async () => {
    vi.stubGlobal('fetch', vi.fn(hangingFetch))

    const probe = await probeApi('/settings', { timeoutMs: 25 })

    expect(probe.ok).toBe(false)
    expect(probe.status).toBeNull()
    expect(probe.error).toContain('25ms')
  })

  it('treats a 2xx without `data` as not ok', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ message: 'hi' })))

    const probe = await probeApi('/settings')

    expect(probe.ok).toBe(false)
    expect(probe.status).toBe(200)
    expect(probe.error).toContain('data')
  })

  it('never throws, even when fetch does', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('connect ECONNREFUSED')))

    const probe = await probeApi('/settings')

    expect(probe.ok).toBe(false)
    expect(probe.error).toBe('connect ECONNREFUSED')
  })
})
