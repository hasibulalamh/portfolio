import { API_BASE_URL, getSettings } from '@/lib/api'

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
  const startedAt = Date.now()
  const settings = await getSettings({ revalidate: 0 })
  const latencyMs = Date.now() - startedAt

  const payload = {
    // The URL the fetch was pointed at — the single most diagnostic field.
    settings_endpoint: `${API_BASE_URL}/settings`,
    latency_ms: latencyMs,
    checked_at: new Date().toISOString(),
  }

  if (!settings) {
    return Response.json(
      {
        status: 'degraded',
        source: 'fallback',
        ...payload,
        hint: 'getSettings() returned no data; the public site is rendering FALLBACK_SETTINGS. Check the api_fallback log lines and that NEXT_PUBLIC_API_URL was present at build time.',
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
