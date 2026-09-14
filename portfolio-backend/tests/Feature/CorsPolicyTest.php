<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The CORS policy is applied in two layers — Laravel's built-in HandleCors
 * middleware answers preflight OPTIONS requests, and App\Http\Middleware\Cors
 * enforces the config/cors.php allowlist on every actual response. These tests
 * lock the observable behaviour of the combined stack, since that is what the
 * two Next.js consumers (public site on :3000, admin on :3001) depend on.
 */
class CorsPolicyTest extends TestCase
{
    private const ALLOWED = 'http://localhost:3000';

    private const DISALLOWED = 'https://evil.example';

    // =====================================================================
    // Actual requests (what fetch()/axios sees)
    // =====================================================================

    public function test_an_allowed_origin_gets_its_origin_echoed_with_credentials(): void
    {
        $response = $this->getJson('/api/hero', ['Origin' => self::ALLOWED]);

        $response->assertOk();
        // Credentials are supported (admin axios sends them), so the response
        // must echo the concrete origin — never '*'.
        $response->assertHeader('Access-Control-Allow-Origin', self::ALLOWED);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        // The response varies by Origin; caches must key on it.
        $this->assertStringContainsString('Origin', $response->headers->get('Vary', ''));
    }

    public function test_an_env_configured_origin_is_allowed(): void
    {
        // config/cors.php builds the allowlist from FRONTEND_URL/ADMIN_URL, so
        // production origins arrive via env, not by editing the config file.
        config(['cors.allowed_origins' => ['https://portfolio.example']]);

        $this->getJson('/api/hero', ['Origin' => 'https://portfolio.example'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://portfolio.example');
    }

    public function test_a_disallowed_origin_receives_no_cors_headers(): void
    {
        $response = $this->getJson('/api/hero', ['Origin' => self::DISALLOWED]);

        $response->assertOk(); // the API still answers — CORS is a browser gate
        $response->assertHeaderMissing('Access-Control-Allow-Origin');
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_a_request_without_an_origin_receives_no_cors_headers(): void
    {
        // curl, server-to-server callers and same-origin pages send no Origin
        // and must not get ACAO either (nothing to allow).
        $response = $this->getJson('/api/hero');

        $response->assertOk();
        $response->assertHeaderMissing('Access-Control-Allow-Origin');
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    // =====================================================================
    // Preflight — answered by Laravel's built-in HandleCors middleware
    // =====================================================================

    public function test_preflight_from_an_allowed_origin_is_answered_by_the_builtin_middleware(): void
    {
        $response = $this->call('OPTIONS', '/api/hero', [], [], [], [
            'HTTP_ORIGIN' => self::ALLOWED,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type, authorization',
        ]);

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', self::ALLOWED);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        // The explicit method/header lists from config/cors.php, not echoes.
        // (fruitcake advertises header names lowercased, as browsers expect.)
        $this->assertStringContainsString('POST', $response->headers->get('Access-Control-Allow-Methods', ''));
        $allowedHeaders = strtolower((string) $response->headers->get('Access-Control-Allow-Headers', ''));
        $this->assertStringContainsString('content-type', $allowedHeaders);
        $this->assertStringContainsString('authorization', $allowedHeaders);
        $this->assertStringContainsString('86400', $response->headers->get('Access-Control-Max-Age', ''));
    }

    public function test_preflight_from_a_disallowed_origin_gets_no_allow_origin(): void
    {
        $response = $this->call('OPTIONS', '/api/hero', [], [], [], [
            'HTTP_ORIGIN' => self::DISALLOWED,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $response->assertStatus(204);
        $response->assertHeaderMissing('Access-Control-Allow-Origin');
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');
    }
}
