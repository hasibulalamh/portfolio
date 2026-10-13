<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Public reads allowed per client IP per minute.
     *
     * Sized for the real consumer, which is one Next.js server rendering pages:
     * a homepage render fetches ten endpoints at once, and a revalidation after
     * the 60s ISR window does it again. 120/minute leaves room for a handful of
     * concurrent renders plus a person curling the API, while still stopping a
     * single client from spending this container's (free-tier, single-worker)
     * capacity on its own.
     */
    private const PUBLIC_READS_PER_MINUTE = 120;

    /**
     * Visitor submissions allowed per client IP per minute. Unchanged from the
     * `throttle:10,1` this replaced — moving it here does not loosen it.
     */
    private const PUBLIC_WRITES_PER_MINUTE = 10;

    /**
     * CTA clicks allowed per client IP per minute. Unchanged from the
     * `throttle:30,1` this replaced.
     */
    private const CLICK_TRACKING_PER_MINUTE = 30;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Only the login route has no limiter here: AuthService throttles it
        // per email+IP, with the tighter 5-per-minute budget that credential
        // guessing deserves. See BACKEND-SETUP.md § "Rate limiting and proxy
        // trust" for the full table and the reasoning behind the keys.
        //
        // All four are named limiters rather than inline throttle:10,1 specs
        // because an inline spec keys its bucket on sha1(domain|ip) alone — the
        // *same* key for every route that uses one. Under that scheme ten
        // contact-form posts spent the click-tracking budget too (and thirty
        // tracked clicks locked a visitor out of the contact form), which is
        // exactly the shared-bucket failure this project has already been bitten
        // by once. A named limiter keys on sha1(name|ip), so each limit protects
        // only what it names.
        RateLimiter::for('public-read', fn (Request $request) => Limit::perMinute(self::PUBLIC_READS_PER_MINUTE)
            ->by($request->ip()));

        RateLimiter::for('public-write', fn (Request $request) => Limit::perMinute(self::PUBLIC_WRITES_PER_MINUTE)
            ->by($request->ip()));

        RateLimiter::for('click-tracking', fn (Request $request) => Limit::perMinute(self::CLICK_TRACKING_PER_MINUTE)
            ->by($request->ip()));

        // Every bucket above is keyed on $request->ip(), which is the visitor's
        // address *only* because bootstrap/app.php trusts Render's load balancer
        // and Cloudflare (config/trustedproxy.php). With the proxy's address as
        // the key these are not per-visitor limits at all — they are one global
        // bucket, and the first few requests of the minute take it from everyone
        // else. tests/Feature/RateLimitProxyTrustTest.php pins both halves.
    }
}
