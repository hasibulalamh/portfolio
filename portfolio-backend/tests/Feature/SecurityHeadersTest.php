<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The SecurityHeaders middleware is global, so every response — success or
 * error — must carry the same policy. These assertions lock that contract so a
 * future refactor (e.g. swapping the middleware for a web-server config) cannot
 * silently drop a header.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_public_responses_carry_all_security_headers(): void
    {
        $response = $this->getJson('/api/hero');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $response->assertHeader(
            'Content-Security-Policy',
            "default-src 'none'; frame-ancestors 'none'; form-action 'none'",
        );
        $response->assertHeaderMissing('X-Powered-By');
    }

    public function test_error_responses_carry_the_same_headers(): void
    {
        // A 404 renders through the exception pipeline, not a controller, so it
        // proves the middleware still wraps responses the handler built itself.
        $response = $this->getJson('/api/does-not-exist');

        $response->assertNotFound();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $response->assertHeader(
            'Content-Security-Policy',
            "default-src 'none'; frame-ancestors 'none'; form-action 'none'",
        );
        $response->assertHeaderMissing('X-Powered-By');
    }

    public function test_the_health_check_carries_the_same_headers(): void
    {
        // The /up route is wired outside the api group, so it confirms the
        // middleware is global rather than api-only.
        $response = $this->get('/up');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $response->assertHeader(
            'Content-Security-Policy',
            "default-src 'none'; frame-ancestors 'none'; form-action 'none'",
        );
        $response->assertHeaderMissing('X-Powered-By');
    }
}
