<?php

// TEMP-DIAGNOSTIC

// TEMP-DIAGNOSTIC — delete this whole file after reading the Render logs.
// TEMP-DIAGNOSTIC — it reports what Laravel actually sees behind Render's edge
// TEMP-DIAGNOSTIC — so the throttle:public-read buckets can be confirmed as
// TEMP-DIAGNOSTIC — per-visitor (or not) before touching trusted-proxy config.

namespace App\Http\Middleware; // TEMP-DIAGNOSTIC

// TEMP-DIAGNOSTIC
use Closure; // TEMP-DIAGNOSTIC
// TEMP-DIAGNOSTIC
use Illuminate\Http\Request; // TEMP-DIAGNOSTIC
// TEMP-DIAGNOSTIC
use Illuminate\Support\Facades\Log; // TEMP-DIAGNOSTIC
// TEMP-DIAGNOSTIC
use Symfony\Component\HttpFoundation\Response; // TEMP-DIAGNOSTIC

/**
 * TEMP-DIAGNOSTIC
 *
 * Logs Laravel's resolved client address and the raw forwarding headers, but
 * only for requests that explicitly opt in with `X-Probe: 1`. Without that
 * header it is a pass-through that touches nothing — in particular it never
 * calls Log::info, so normal production traffic produces no extra log lines.
 *
 * It judges nothing and changes nothing about trust: the limiter and
 * trusted-proxy configuration are untouched.
 */
class ProbeRequestDiagnostics // TEMP-DIAGNOSTIC
{
    // TEMP-DIAGNOSTIC
    // TEMP-DIAGNOSTIC
    public function handle(Request $request, Closure $next): Response // TEMP-DIAGNOSTIC
    { // TEMP-DIAGNOSTIC
        // TEMP-DIAGNOSTIC — the only trigger; anything else is a no-op.
        if ($request->header('X-Probe') === '1') { // TEMP-DIAGNOSTIC
            // TEMP-DIAGNOSTIC — one line, everything needed to read the chain.
            Log::info('TEMP-DIAGNOSTIC probe', [ // TEMP-DIAGNOSTIC
                'request_ip' => $request->ip(), // TEMP-DIAGNOSTIC
                'server_remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null, // TEMP-DIAGNOSTIC
                'x_forwarded_for' => $request->header('X-Forwarded-For'), // TEMP-DIAGNOSTIC
                'client_ips' => $request->getClientIps(), // TEMP-DIAGNOSTIC
                'cache_default' => config('cache.default'), // TEMP-DIAGNOSTIC
            ]); // TEMP-DIAGNOSTIC
        } // TEMP-DIAGNOSTIC
        // TEMP-DIAGNOSTIC

        return $next($request); // TEMP-DIAGNOSTIC
    } // TEMP-DIAGNOSTIC
} // TEMP-DIAGNOSTIC
// TEMP-DIAGNOSTIC
