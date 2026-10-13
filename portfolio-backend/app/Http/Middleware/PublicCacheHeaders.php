<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks successful public reads as shared-cacheable.
 *
 * Everything in the `public-read` group is anonymous, visitor-independent CMS
 * content, but Laravel's JSON responses default to `Cache-Control: no-cache,
 * private` — which forbids every intermediary (Cloudflare, Render's proxy)
 * from storing them. The frontend therefore paid a full origin round trip for
 * content that had not changed in weeks.
 *
 * `s-maxage` is the important part: it lets the Cloudflare edge answer repeat
 * reads without touching the container, which is what turns a burst of page
 * renders into cache hits instead of requests that can be rate limited.
 * `max-age` covers a browser reading the API directly, and
 * `stale-while-revalidate` lets a stale copy be served while a fresh one is
 * fetched in the background.
 *
 * Only GETs are touched, and only successful ones: an error response (or a
 * 429) must never be cached, and the write endpoints — the visitor forms and
 * click tracking — are POSTs and stay uncacheable by construction.
 *
 * Route-level throttling runs *after* this middleware (throttle is applied by
 * the route, this one is in the group), so its headers survive onto the
 * response.
 */
class PublicCacheHeaders
{
    /** Seconds an edge cache may reuse a public read response. */
    private const EDGE_TTL = 300;

    /** Seconds a browser — or the Next.js Data Cache — may reuse it. */
    private const CLIENT_TTL = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            $response->headers->set('Cache-Control', sprintf(
                'public, max-age=%d, s-maxage=%d, stale-while-revalidate=%d',
                self::CLIENT_TTL,
                self::EDGE_TTL,
                self::EDGE_TTL,
            ));
        }

        return $response;
    }
}
