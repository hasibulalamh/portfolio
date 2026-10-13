import { API_BASE_URL, probeApi } from '@/lib/api'

/**
 * Live probe for the exact failure mode that took the real settings off this
 * site in production: the ISR page kept returning 200 while every settings
 * fetch degraded to fallback content, silently. The homepage cannot answer
 * "is this the API's data or the hardcoded fallback?" from the outside — both
 * render as plausible portfolio content.
 *
 * This route can. It reads /settings with revalidate: 0 (bypassing the Data
 * Cache entry the page render shares), so it always tells the truth about
 * reachability right now:
 *
 *   200 {"status":"ok","source":"live",...}       settings fetch succeeded
 *   503 {"status":"degraded","source":"fallback"} fetch failed or was empty
 *
 * It also reports *how* the API answered, not just whether it did:
 *
 *   api_status       the HTTP status, or null when the request never got one
 *   api_response_ms  how long that one request took
 *   retry_after_ms   what a 429 asked us to wait, when it asked
 *
 * Those three fields exist because "degraded" alone sent the previous
 * investigation looking in the wrong place: a rate-limited API (429), an API
 * that never answers (timeout), and a broken API (5xx) all print the same
 * verdict, and one curl should not have to guess between them.
 *
 * `attempted_url` is reported even when things look fine, so a mispointed
 * NEXT_PUBLIC_API_URL (this project shipped a build with localhost baked in)
 * is visible at a glance — no bundle grepping required. Uptime checkers can
 * watch for status 503; humans can curl it mid-incident.
 *
 * force-dynamic + no-store on purpose: a cached "degraded" verdict would
 * outlive the recovery it is reporting.
 */
export const dynamic = 'force-dynamic'

export async function GET() {
  const probe = await probeApi('/settings')
  const settings = probe.ok ? probe.data : null

  const payload = {
    // The URL the fetch was pointed at — the single most diagnostic field.
    settings_endpoint: `${API_BASE_URL}/settings`,
    api_status: probe.status,
    api_response_ms: probe.latencyMs,
    // Kept under its original name for existing uptime checks and dashboard
    // filters; it is the same measurement as api_response_ms.
    latency_ms: probe.latencyMs,
    // Present only when the API asked us to back off (429).
    ...(probe.retryAfterMs === null ? {} : { retry_after_ms: probe.retryAfterMs }),
    checked_at: new Date().toISOString(),
  }

  if (!settings) {
    return Response.json(
      {
        status: 'degraded',
        source: 'fallback',
        ...payload,
        // Lead with what the API actually did; the generic hint follows.
        ...(probe.error ? { api_error: probe.error } : {}),
        hint:
          probe.status === 429
            ? 'the API rate limited this request; the public site is rendering FALLBACK_SETTINGS until the limit resets. Check the upstream limiter and the api_fallback log lines.'
            : 'getSettings() returned no data; the public site is rendering FALLBACK_SETTINGS. Check the api_fallback log lines and that NEXT_PUBLIC_API_URL was present at build time.',
      },
      { status: 503, headers: { 'Cache-Control': 'no-store' } },
    )
  }

  return Response.json(
    {
      status: 'ok',
      source: 'live',
      ...payload,
      // Echo the brand fields the outside world checks by eye, so a favicon
      // or title mismatch can be attributed to data vs. rendering in one call.
      site_title: settings.site_title ?? null,
      favicon_path: settings.favicon_path ?? null,
      logo_path: settings.logo_path ?? null,
    },
    { status: 200, headers: { 'Cache-Control': 'no-store' } },
  )
}
