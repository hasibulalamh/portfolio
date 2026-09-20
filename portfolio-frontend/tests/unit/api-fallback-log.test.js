import { describe, expect, it } from 'vitest'
import { formatApiFallbackLog } from '../../lib/api.js'

/**
 * formatApiFallbackLog() — the single greppable line emitted whenever a
 * backend fetch degrades to fallback content.
 *
 * This is the observability layer added after the production incident where
 * the ISR page kept serving 200s while every settings fetch silently used
 * FALLBACK_SETTINGS. The log line is the server-side half of that fix: if it
 * ever fires in production, Render's log drain must contain `api_fallback`
 * plus enough context (which endpoint, which URL was actually attempted, why)
 * to diagnose without redeploying first.
 *
 * Tested as a pure string builder: the fetch call sites in fetchFromApi only
 * pass arguments and console.error the result, so pinning the output shape
 * here pins the entire contract.
 */
describe('formatApiFallbackLog', () => {
  it('produces valid single-line JSON', () => {
    const line = formatApiFallbackLog({ path: '/settings', status: 404 })

    expect(() => JSON.parse(line)).not.toThrow()
    expect(line).not.toMatch(/\n/)
  })

  it('always tags the event so log drains can alert on it', () => {
    const parsed = JSON.parse(formatApiFallbackLog({ path: '/settings', status: 500 }))

    expect(parsed.level).toBe('error')
    expect(parsed.event).toBe('api_fallback')
    expect(parsed.path).toBe('/settings')
  })

  it('reports the attempted URL — the field that diagnosed the localhost incident', () => {
    const parsed = JSON.parse(formatApiFallbackLog({ path: '/settings', status: 404 }))

    expect(parsed.attempted_url).toMatch(/\/settings$/)
    // The incident shipped a build pointed at localhost:8000; if that ever
    // regresses, the log line itself must say so.
    expect(typeof parsed.attempted_url).toBe('string')
    expect(parsed.attempted_url.length).toBeGreaterThan('/settings'.length)
  })

  describe('reason classification', () => {
    it('classifies HTTP-level failures as http_<status>', () => {
      const parsed = JSON.parse(formatApiFallbackLog({ path: '/hero', status: 404 }))

      expect(parsed.reason).toBe('http_404')
      expect(parsed.status).toBe(404)
      expect(parsed.detail).toBe('HTTP 404')
    })

    it('classifies thrown fetch errors as network_error with the message', () => {
      const parsed = JSON.parse(
        formatApiFallbackLog({ path: '/settings', error: 'connect ECONNREFUSED 127.0.0.1:8000' }),
      )

      expect(parsed.reason).toBe('network_error')
      expect(parsed.status).toBeNull()
      expect(parsed.detail).toBe('connect ECONNREFUSED 127.0.0.1:8000')
    })

    it('classifies a 2xx response missing `data` as unexpected_shape', () => {
      const parsed = JSON.parse(formatApiFallbackLog({ path: '/settings', malformed: true }))

      expect(parsed.reason).toBe('unexpected_shape')
      expect(parsed.status).toBeNull()
      expect(parsed.detail).toContain('data')
    })
  })
})
