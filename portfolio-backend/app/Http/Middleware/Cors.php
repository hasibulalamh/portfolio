<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * First-party CORS policy layer.
 *
 * Registered outermost in bootstrap/app.php so it has the final word on every
 * response that carries an Origin header. Laravel's built-in HandleCors
 * middleware — which is part of the framework's default global stack and runs
 * *inside* this layer — still owns the mechanics:
 *
 *   - Preflight OPTIONS requests (those carrying Access-Control-Request-Method)
 *     are short-circuited by HandleCors with a 204 before the app runs. This
 *     middleware passes them straight through, so nothing here can double-answer
 *     a preflight.
 *   - HandleCors reads config/cors.php and emits the CORS headers itself.
 *
 * What this layer adds is enforcement in code. config/cors.php is the single
 * source of truth for the allowlist, but no response may carry CORS headers to
 * an origin that is not on it — so after HandleCors has done its work, this
 * middleware re-asserts the policy on the way out and strips any ACAO /
 * credentials header a disallowed origin would otherwise see. It also gives
 * future policy logic (logging, per-host rules) a first-party home.
 */
class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        // Not a browser CORS request (curl, server-to-server, same-origin page,
        // or a no-cors subresource like <img>): the API needs no CORS headers,
        // and HandleCors already decided that for preflight.
        if ($request->getMethod() === 'OPTIONS'
            || ! $request->headers->has('Origin')) {
            return $next($request);
        }

        $response = $next($request);

        $origin = (string) $request->headers->get('Origin');
        $allowedOrigins = (array) config('cors.allowed_origins', []);

        if (in_array($origin, $allowedOrigins, true)) {
            // Must echo the exact origin — never '*' — because the API supports
            // credentials, and browsers reject a wildcard on credentialed calls.
            $response->headers->set('Access-Control-Allow-Origin', $origin);

            if (config('cors.supports_credentials', false)) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
        } else {
            // Defense in depth: even if a lower layer (or a future config
            // change) emitted headers for this origin, the browser must not
            // receive them. Same-origin fallbacks like Origin: "null" (sandboxed
            // iframes, file:// pages) are deliberately not in the allowlist.
            $response->headers->remove('Access-Control-Allow-Origin');
            $response->headers->remove('Access-Control-Allow-Credentials');
        }

        return $response;
    }
}
