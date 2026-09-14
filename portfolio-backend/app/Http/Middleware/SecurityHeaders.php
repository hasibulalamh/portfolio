<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hardens every response with a minimal set of security headers.
 *
 * Registered globally (web and api) so that success envelopes, the framework's
 * 4xx/5xx JSON, and the /up health check all carry the same policy — none of
 * them should ever leak that this is PHP, or invite framing or MIME sniffing.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // PHP's SAPI (built-in server and FPM alike) appends X-Powered-By after
        // the script runs whenever expose_php is on. It never lives in Symfony's
        // header bag, so removing it from the response object alone is not
        // enough — drop the pending SAPI header too, before it is flushed.
        $response->headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        // Stop browsers from MIME-sniffing a response into a different type.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // This backend only ever serves same-origin resources; forbid cross-site
        // embedding of its responses.
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // API-only backend: no HTML is rendered, so deny every resource type
        // and refuse to be framed by any site. form-action is pinned to 'none'
        // too because — unlike most directives — it does not fall back to
        // default-src, so omitting it would leave form submissions ungoverned
        // (flagged by ZAP rule 10055).
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; frame-ancestors 'none'; form-action 'none'",
        );

        return $response;
    }
}
