<?php

namespace Tests\Unit;

use App\Services\HealthCheckService;
use Tests\TestCase;

/**
 * The health-status rule table, driven with synthetic timings.
 *
 * The endpoint test (tests/Feature/HealthCheckEndpointTest.php) runs real
 * probes against the real test database and can therefore only assert that
 * the overall status is *consistent with the payload's own timings* — on a
 * remote test database with RTT spikes, a genuinely healthy system honestly
 * reports "degraded" under the 1000ms rule. Pinning the exact "healthy"
 * string there was an assertion about the machine's network speed, not about
 * the code, and it flaked exactly that way once (see report.md Part F).
 *
 * This test owns what the endpoint test cannot: the threshold rules, fed
 * hand-picked timings, no database and no storage involved — the method under
 * test is a pure function of its two arguments. The comment in the endpoint
 * test has claimed "the threshold logic is tested in unit tests" since it was
 * written; this file is what it was always supposed to point at.
 */
class HealthCheckStatusTest extends TestCase
{
    private HealthCheckService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new HealthCheckService;
    }

    public function test_database_down_is_critical_regardless_of_storage(): void
    {
        $this->assertSame('down', $this->service->computeOverallStatus(
            ['status' => 'down', 'response_time_ms' => 0],
            ['status' => 'healthy', 'response_time_ms' => 1],
        ));

        // Storage being down too cannot soften it: database down wins.
        $this->assertSame('down', $this->service->computeOverallStatus(
            ['status' => 'down', 'response_time_ms' => 9000],
            ['status' => 'down', 'response_time_ms' => 9000],
        ));
    }

    public function test_storage_down_alone_is_degraded_not_down(): void
    {
        $this->assertSame('degraded', $this->service->computeOverallStatus(
            ['status' => 'healthy', 'response_time_ms' => 5],
            ['status' => 'down', 'response_time_ms' => 3000],
        ));
    }

    public function test_slow_database_is_degraded(): void
    {
        $this->assertSame('degraded', $this->service->computeOverallStatus(
            ['status' => 'healthy', 'response_time_ms' => 1001],
            ['status' => 'healthy', 'response_time_ms' => 1],
        ));
    }

    public function test_slow_storage_is_degraded(): void
    {
        $this->assertSame('degraded', $this->service->computeOverallStatus(
            ['status' => 'healthy', 'response_time_ms' => 1],
            ['status' => 'healthy', 'response_time_ms' => 5000],
        ));
    }

    public function test_threshold_boundary_is_exclusive(): void
    {
        // Rule reads "> 1000ms": exactly 1000 is still healthy...
        $this->assertSame('healthy', $this->service->computeOverallStatus(
            ['status' => 'healthy', 'response_time_ms' => 1000],
            ['status' => 'healthy', 'response_time_ms' => 1000],
        ));

        // ...and 1001 is the first degraded millisecond. Boundary of one
        // millisecond — if the comparison operator ever flips, this pair
        // catches it in both directions.
        $this->assertSame('degraded', $this->service->computeOverallStatus(
            ['status' => 'healthy', 'response_time_ms' => 1001],
            ['status' => 'healthy', 'response_time_ms' => 0],
        ));
    }

    public function test_all_healthy_and_fast_is_healthy(): void
    {
        $this->assertSame('healthy', $this->service->computeOverallStatus(
            ['status' => 'healthy', 'response_time_ms' => 12],
            ['status' => 'healthy', 'response_time_ms' => 45],
        ));

        // Zeros are legitimate fast timings, not a special case.
        $this->assertSame('healthy', $this->service->computeOverallStatus(
            ['status' => 'healthy', 'response_time_ms' => 0],
            ['status' => 'healthy', 'response_time_ms' => 0],
        ));
    }
}
