<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\IpUtils;
use Tests\TestCase;

/**
 * Proxy trust and rate-limit buckets, pinned from the outside.
 *
 * The production incident behind this file: every visitor's rate limit was
 * really the *proxy's* rate limit. Laravel did not trust Render's load
 * balancer, so `$request->ip()` returned the balancer's address for the whole
 * internet, all visitors shared one bucket, and the first few requests of the
 * minute refused everybody else.
 *
 * Two things therefore need pinning, and neither is visible from any single
 * route's test:
 *
 *   1. Which proxies are trusted, and that a *forged* X-Forwarded-For from an
 *      untrusted peer is still ignored — trusting proxies is what makes the
 *      limits per visitor, and trusting them blindly is what makes them
 *      bypassable.
 *   2. That each limit is keyed on that visitor: a second visitor is not
 *      refused because the first one was, and the strict limits on the login
 *      route and the visitor forms are unchanged.
 *
 * The exact per-minute numbers live in AppServiceProvider; where a test needs
 * to exhaust a bucket it shrinks the limiter rather than sending 120 requests,
 * so the wiring is tested without the runtime.
 */
class RateLimitProxyTrustTest extends TestCase
{
    use RefreshDatabase;

    /** The address a request arrives from, as Render's load balancer. */
    private const LOAD_BALANCER = '10.0.0.5';

    /** Two visitors, both forwarded by the proxies above. */
    private const VISITOR = '203.0.113.10';

    private const OTHER_VISITOR = '203.0.113.11';

    /**
     * Send subsequent requests as if they came through the proxy chain from
     * `$visitorIp`: the connection always terminates at Render's balancer, and
     * the visitor's address only exists in X-Forwarded-For.
     */
    private function asVisitor(string $peer, string $visitorIp): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $peer])
            ->withHeaders(['X-Forwarded-For' => $visitorIp]);
    }

    /** Exhaust a bucket cheaply: the limit is a number, the wiring is the test. */
    private function shrinkPublicReadLimit(int $perMinute): void
    {
        RateLimiter::for('public-read', fn (Request $request) => Limit::perMinute($perMinute)
            ->by($request->ip()));
    }

    // =====================================================================
    // Proxy trust
    // =====================================================================

    public function test_the_load_balancer_and_cloudflare_are_the_trusted_proxies(): void
    {
        $this->asVisitor(self::LOAD_BALANCER, self::VISITOR)->getJson('/api/settings')->assertOk();

        $trusted = Request::getTrustedProxies();

        // Every hop is trusted by range, not by a single address: the load
        // balancer has several nodes and the local hop's address is not
        // knowable at build time, so the private and loopback ranges cover
        // them. The balancer's address is verified as *covered* rather than
        // listed literally.
        $this->assertContains('10.0.0.0/8', $trusted);
        $this->assertContains('127.0.0.0/8', $trusted);
        $this->assertTrue(IpUtils::checkIp(self::LOAD_BALANCER, $trusted));

        // Cloudflare's egress ranges sit in front of them and are listed.
        $this->assertContains('104.16.0.0/13', $trusted);
        $this->assertContains('2606:4700::/32', $trusted);

        // Never everything: a client can send its own X-Forwarded-For, so
        // trusting every hop would return the leftmost — client-forged — value.
        $this->assertNotContains('*', $trusted);
        $this->assertNotContains('0.0.0.0/0', $trusted);
        $this->assertNotContains('::/0', $trusted);
    }

    public function test_each_visitor_gets_their_own_public_read_bucket(): void
    {
        $this->shrinkPublicReadLimit(2);

        $this->asVisitor(self::LOAD_BALANCER, self::VISITOR);
        $this->getJson('/api/settings')->assertOk();
        $this->getJson('/api/settings')->assertOk();
        $this->getJson('/api/settings')->assertStatus(429);

        // The regression this file exists for: keyed on the proxy address, this
        // request would be refused because the *previous* visitor spent "their"
        // bucket. Keyed on the visitor, it is untouched.
        $this->asVisitor(self::LOAD_BALANCER, self::OTHER_VISITOR);
        $this->getJson('/api/settings')->assertOk();
    }

    public function test_a_forged_x_forwarded_for_prefix_cannot_mint_a_new_bucket(): void
    {
        $this->shrinkPublicReadLimit(2);

        // A proxy appends the address it saw to whatever the client sent, so a
        // client-written X-Forwarded-For survives as a *prefix* on the left.
        // The resolved client IP is the rightmost entry that is not one of our
        // proxies, so the forged value never wins — three forged prefixes still
        // bill the same visitor, and the limit is not a per-request allowance.
        //
        // This is the property a trust-everything list would give up: Symfony
        // returns the leftmost address when every hop is trusted, and that is
        // the one the client wrote.
        foreach (['1.2.3.4', '9.9.9.9'] as $forged) {
            $this->asVisitor(self::LOAD_BALANCER, "{$forged}, ".self::VISITOR);
            $this->getJson('/api/settings')->assertOk();
        }

        $this->asVisitor(self::LOAD_BALANCER, '5.5.5.5, '.self::VISITOR);
        $this->getJson('/api/settings')->assertStatus(429);
    }

    // =====================================================================
    // The limits themselves
    // =====================================================================

    public function test_the_public_read_limit_is_120_per_minute(): void
    {
        // The shipped number, not a shrunken one: the frontend renders a page
        // with ten concurrent reads, so a limit below that is an outage.
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '120');
    }

    public function test_the_login_limit_stays_five_per_email_and_visitor(): void
    {
        $credentials = ['email' => 'admin@example.com', 'password' => 'definitely-wrong'];

        $this->asVisitor(self::LOAD_BALANCER, self::VISITOR);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/login', $credentials)->assertStatus(401);
        }

        $this->postJson('/api/login', $credentials)
            ->assertStatus(429)
            ->assertJsonPath('data', null);
        $this->assertStringContainsString(
            'Too many login attempts',
            (string) $this->postJson('/api/login', $credentials)->json('message'),
        );

        // The key is email|visitor-IP, so a different visitor is not locked out
        // by someone else's failed attempts against the same email.
        $this->asVisitor(self::LOAD_BALANCER, self::OTHER_VISITOR);
        $this->postJson('/api/login', $credentials)->assertStatus(401);
    }

    public function test_the_visitor_forms_stay_at_ten_per_minute_per_visitor(): void
    {
        $this->asVisitor(self::LOAD_BALANCER, self::VISITOR);

        // Deliberately invalid: a 422 still passes through the throttle, so the
        // limit is exercised without writing a row or sending an email.
        $invalid = ['name' => 'Rate Limit', 'email' => 'not-an-email', 'message' => 'body'];

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson('/api/contact-messages', $invalid)->assertStatus(422);
        }

        $this->postJson('/api/contact-messages', $invalid)->assertStatus(429);

        // ...and the bucket is that visitor's, not everyone's.
        $this->asVisitor(self::LOAD_BALANCER, self::OTHER_VISITOR);
        $this->postJson('/api/contact-messages', $invalid)->assertStatus(422);
    }

    public function test_the_meeting_form_is_protected_by_the_same_strict_budget(): void
    {
        $this->asVisitor(self::LOAD_BALANCER, self::VISITOR);

        $invalid = ['name' => 'Rate Limit', 'email' => 'not-an-email', 'message' => 'body'];

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson('/api/meeting-requests', $invalid)->assertStatus(422);
        }

        $this->postJson('/api/meeting-requests', $invalid)->assertStatus(429);
    }

    public function test_click_tracking_has_its_own_thirty_per_minute_budget(): void
    {
        $this->asVisitor(self::LOAD_BALANCER, self::VISITOR);

        $invalid = ['event_type' => '__not_in_the_allow_list__'];

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->postJson('/api/track', $invalid)->assertStatus(422);
        }

        $this->postJson('/api/track', $invalid)->assertStatus(429);

        // The collision this guards against: `throttle:30,1` and `throttle:10,1`
        // key on sha1(domain|ip) alone, so before the limiters were named, these
        // thirty requests would also have refused the contact form.
        $this->postJson('/api/contact-messages', [
            'name' => 'Rate Limit',
            'email' => 'not-an-email',
            'message' => 'body',
        ])->assertStatus(422);
    }

    // =====================================================================
    // Cache headers on public reads
    // =====================================================================

    public function test_public_reads_are_cacheable_downstream(): void
    {
        $cacheControl = (string) $this->getJson('/api/settings')->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('s-maxage=300', $cacheControl);
        $this->assertStringNotContainsString('no-cache', $cacheControl);
        $this->assertStringNotContainsString('private', $cacheControl);
    }

    public function test_a_rate_limited_response_is_never_cached(): void
    {
        $this->shrinkPublicReadLimit(1);

        $this->getJson('/api/settings')->assertOk();

        $limited = $this->getJson('/api/settings')->assertStatus(429);
        $cacheControl = (string) $limited->headers->get('Cache-Control');

        // Caching a 429 would turn a one-minute limit into a permanently
        // degraded site for whoever read that cache entry.
        $this->assertStringNotContainsString('s-maxage', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
    }

    public function test_write_responses_are_not_given_public_cache_headers(): void
    {
        $cacheControl = (string) $this->postJson('/api/track', ['event_type' => '__not_allowed__'])
            ->assertStatus(422)
            ->headers->get('Cache-Control');

        $this->assertStringNotContainsString('s-maxage', $cacheControl);
    }
}
