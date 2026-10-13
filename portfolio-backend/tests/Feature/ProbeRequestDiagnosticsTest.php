<?php

// TEMP-DIAGNOSTIC

namespace Tests\Feature; // TEMP-DIAGNOSTIC

// TEMP-DIAGNOSTIC
use Illuminate\Foundation\Testing\RefreshDatabase; // TEMP-DIAGNOSTIC
// TEMP-DIAGNOSTIC
use Illuminate\Support\Facades\Log; // TEMP-DIAGNOSTIC
// TEMP-DIAGNOSTIC
use Tests\TestCase; // TEMP-DIAGNOSTIC

/**
 * TEMP-DIAGNOSTIC
 *
 * Pins both halves of the probe contract: `X-Probe: 1` produces exactly the
 * one diagnostic line, and every other request produces none. The second half
 * is the one that matters for production — the middleware must be invisible
 * on ordinary traffic. Delete alongside App\Http\Middleware\
 * ProbeRequestDiagnostics and the route hook.
 */
class ProbeRequestDiagnosticsTest extends TestCase // TEMP-DIAGNOSTIC
{
    // TEMP-DIAGNOSTIC
    use RefreshDatabase; // TEMP-DIAGNOSTIC — /api/settings reads Setting::singleton().

    /**
     * TEMP-DIAGNOSTIC
     *
     * The middleware logs the raw `$_SERVER['REMOTE_ADDR']`. Feature tests
     * build a Request object rather than going through a web SAPI, so
     * `withServerVariables()` populates the request's server bag but leaves
     * that superglobal alone — it has to be set explicitly to mimic the
     * production container, then restored so other tests are unaffected.
     *
     * @param  callable():void  $callback
     */
    private function withRawRemoteAddress(string $address, callable $callback): void // TEMP-DIAGNOSTIC
    { // TEMP-DIAGNOSTIC
        $previous = $_SERVER['REMOTE_ADDR'] ?? null; // TEMP-DIAGNOSTIC
        $_SERVER['REMOTE_ADDR'] = $address; // TEMP-DIAGNOSTIC

        // TEMP-DIAGNOSTIC
        try { // TEMP-DIAGNOSTIC
            $callback(); // TEMP-DIAGNOSTIC
        } finally { // TEMP-DIAGNOSTIC
            if ($previous === null) { // TEMP-DIAGNOSTIC
                unset($_SERVER['REMOTE_ADDR']); // TEMP-DIAGNOSTIC
            } else { // TEMP-DIAGNOSTIC
                $_SERVER['REMOTE_ADDR'] = $previous; // TEMP-DIAGNOSTIC
            } // TEMP-DIAGNOSTIC
        } // TEMP-DIAGNOSTIC
    } // TEMP-DIAGNOSTIC

    // TEMP-DIAGNOSTIC
    public function test_it_logs_the_proxy_chain_when_the_probe_header_is_present(): void // TEMP-DIAGNOSTIC
    { // TEMP-DIAGNOSTIC
        Log::spy(); // TEMP-DIAGNOSTIC

        // TEMP-DIAGNOSTIC
        $this->withRawRemoteAddress('10.0.0.5', function (): void { // TEMP-DIAGNOSTIC
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5']) // TEMP-DIAGNOSTIC
                ->withHeaders([ // TEMP-DIAGNOSTIC
                    'X-Probe' => '1', // TEMP-DIAGNOSTIC
                    'X-Forwarded-For' => '203.0.113.10', // TEMP-DIAGNOSTIC
                ]) // TEMP-DIAGNOSTIC
                ->getJson('/api/settings') // TEMP-DIAGNOSTIC
                ->assertOk(); // TEMP-DIAGNOSTIC
        }); // TEMP-DIAGNOSTIC

        Log::shouldHaveReceived('info') // TEMP-DIAGNOSTIC
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'TEMP-DIAGNOSTIC') // TEMP-DIAGNOSTIC
                && ($context['request_ip'] ?? null) === '203.0.113.10' // TEMP-DIAGNOSTIC
                && ($context['server_remote_addr'] ?? null) === '10.0.0.5' // TEMP-DIAGNOSTIC
                && ($context['x_forwarded_for'] ?? null) === '203.0.113.10' // TEMP-DIAGNOSTIC
                && ($context['client_ips'] ?? null) === ['203.0.113.10']) // TEMP-DIAGNOSTIC
            ->once(); // TEMP-DIAGNOSTIC
    } // TEMP-DIAGNOSTIC

    // TEMP-DIAGNOSTIC
    public function test_it_logs_nothing_without_the_probe_header(): void // TEMP-DIAGNOSTIC
    { // TEMP-DIAGNOSTIC
        Log::spy(); // TEMP-DIAGNOSTIC

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5']) // TEMP-DIAGNOSTIC
            ->withHeaders(['X-Forwarded-For' => '203.0.113.10']) // TEMP-DIAGNOSTIC — no X-Probe.
            ->getJson('/api/settings') // TEMP-DIAGNOSTIC
            ->assertOk(); // TEMP-DIAGNOSTIC

        Log::shouldNotHaveReceived('info'); // TEMP-DIAGNOSTIC
    } // TEMP-DIAGNOSTIC
} // TEMP-DIAGNOSTIC
