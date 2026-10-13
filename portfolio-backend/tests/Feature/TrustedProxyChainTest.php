<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The production proxy chain, replayed address for address.
 *
 * A TEMP-DIAGNOSTIC probe on `GET /api/settings` returned:
 *
 *   request_ip          = 10.199.220.25
 *   server_remote_addr  = 127.0.0.1
 *   x_forwarded_for     = 160.25.9.210, 172.71.247.163, 10.199.220.25
 *
 * so the real chain is:
 *
 *   visitor -> Render edge (Cloudflare range, 172.71.x)
 *           -> Render load balancer (private 10.x)
 *           -> local hop inside the container (127.0.0.1)
 *           -> Laravel
 *
 * The balancer's hop was not trusted, so Symfony stopped there and
 * `$request->ip()` was the balancer's private address for every visitor. The
 * buckets were therefore keyed on whichever 10.199.x.x node answered — which
 * also explains X-RateLimit-Remaining sometimes rising again.
 *
 * `RateLimitProxyTrustTest` pins the limits and the trust list broadly; this
 * file replays the exact production values above, so a regression in either
 * range (the missing `10.0.0.0/8`, or the loopback hop) fails here first.
 */
class TrustedProxyChainTest extends TestCase
{
    use RefreshDatabase;

    /** The local hop inside the container — REMOTE_ADDR is always this. */
    private const LOCAL_HOP = '127.0.0.1';

    /** Render's load balancer: private, and one of several 10.199.x.x nodes. */
    private const LOAD_BALANCER = '10.199.220.25';

    /** Render's edge, inside Cloudflare's published egress ranges. */
    private const EDGE = '172.71.247.163';

    /** The visitor — the first address from the right that is not ours. */
    private const VISITOR = '203.0.113.9';

    /** A second visitor arriving through the same balancer. */
    private const OTHER_VISITOR = '198.51.100.7';

    /**
     * Send one request down the chain and hand back both the request and the
     * response. Built by hand (rather than with `getJson`) so the test can
     * assert `$request->ip()` — the exact value every limiter keys on — and not
     * merely the response body.
     *
     * @return array{0: Request, 1: \Symfony\Component\HttpFoundation\Response}
     */
    private function replayChain(string $remoteAddr, string $forwardedFor): array
    {
        $request = Request::create('/api/settings', 'GET', [], [], [], [
            'REMOTE_ADDR' => $remoteAddr,
            'HTTP_X_FORWARDED_FOR' => $forwardedFor,
        ]);

        $response = $this->app->make(Kernel::class)->handle($request);

        return [$request, $response];
    }

    /** Reach `GET /api/settings` as a visitor forwarded through the chain. */
    private function asChainVisitor(string $visitor): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::LOCAL_HOP])
            ->withHeaders([
                'X-Forwarded-For' => $visitor.', '.self::EDGE.', '.self::LOAD_BALANCER,
            ]);
    }

    // =====================================================================
    // (a) The exact production chain resolves the visitor
    // =====================================================================

    public function test_the_visitor_is_resolved_past_the_load_balancer_and_local_hop(): void
    {
        [$request, $response] = $this->replayChain(
            self::LOCAL_HOP,
            self::VISITOR.', '.self::EDGE.', '.self::LOAD_BALANCER,
        );

        $this->assertSame(200, $response->getStatusCode());

        // Before the fix this returned 10.199.220.25 (the balancer); now the
        // walk skips the local hop, the balancer and the edge and lands on the
        // visitor.
        $this->assertSame(self::VISITOR, $request->ip());

        // Our own hops are stripped, so the visitor is the whole client chain.
        $this->assertSame([self::VISITOR], $request->getClientIps());
    }

    // =====================================================================
    // (b) A forged prefix cannot win
    // =====================================================================

    public function test_a_client_forged_x_forwarded_for_prefix_is_not_believed(): void
    {
        [$request, $response] = $this->replayChain(
            self::LOCAL_HOP,
            '1.1.1.1, '.self::VISITOR.', '.self::EDGE.', '.self::LOAD_BALANCER,
        );

        $this->assertSame(200, $response->getStatusCode());

        // A proxy appends to X-Forwarded-For rather than replacing it, so the
        // client's forged value survives as a *prefix* on the left. Because the
        // chain is walked from the right, that prefix can never become the
        // client IP — the visitor does.
        $this->assertSame(self::VISITOR, $request->ip());
        $this->assertSame([self::VISITOR, '1.1.1.1'], $request->getClientIps());
    }

    // =====================================================================
    // (c) Two visitors behind one load balancer get independent buckets
    // =====================================================================

    public function test_two_visitors_behind_one_load_balancer_get_independent_read_buckets(): void
    {
        // One visitor spends two of the 120 public reads available per minute.
        $this->asChainVisitor(self::VISITOR)->getJson('/api/settings')
            ->assertOk()
            ->assertHeader('X-RateLimit-Remaining', '119');

        $this->asChainVisitor(self::VISITOR)->getJson('/api/settings')
            ->assertOk()
            ->assertHeader('X-RateLimit-Remaining', '118');

        // A second visitor reaching the app through the *same* balancer address
        // still has the full budget: the bucket is keyed on the visitor, not on
        // the balancer node that happened to answer.
        $this->asChainVisitor(self::OTHER_VISITOR)->getJson('/api/settings')
            ->assertOk()
            ->assertHeader('X-RateLimit-Remaining', '119');
    }

    // =====================================================================
    // (d) An untrusted peer cannot forge a client IP
    // =====================================================================

    public function test_a_public_peer_that_forges_x_forwarded_for_is_not_believed(): void
    {
        [$request, $response] = $this->replayChain('198.51.100.5', self::VISITOR);

        $this->assertSame(200, $response->getStatusCode());

        // 198.51.100.5 is in none of the trusted ranges, so it is not a proxy
        // and its X-Forwarded-For carries no authority: the connection's own
        // address is the client. This is the property the loopback *range*
        // preserves — the literal 'REMOTE_ADDR' token would have trusted
        // whoever connected and believed the forged 203.0.113.9.
        $this->assertFalse($request->isFromTrustedProxy());
        $this->assertSame('198.51.100.5', $request->ip());
        $this->assertSame(['198.51.100.5'], $request->getClientIps());
    }
}
