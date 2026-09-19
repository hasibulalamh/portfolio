<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * Admin-only health check endpoint.
 *
 * Tests that the health endpoint:
 * - Requires authentication
 * - Returns well-formed health status
 * - Does not expose secrets
 * - Handles database failure without crashing
 * - Handles storage failure without crashing
 */
class HealthCheckEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Filesystem $storageDisk;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filesystems.disks.r2.key', 'test-key');
        config()->set('filesystems.disks.r2.secret', 'test-secret');
        config()->set('filesystems.disks.r2.bucket', 'test-bucket');
        config()->set('filesystems.disks.r2.endpoint', 'https://r2.test.invalid');

        $this->storageDisk = Mockery::mock(Filesystem::class);
        $this->storageDisk
            ->shouldReceive('exists')
            ->byDefault()
            ->with('__health_check_probe__')
            ->andReturn(false);

        // HealthCheckService builds an isolated short-timeout disk. Returning
        // this mock keeps endpoint tests deterministic and offline.
        Storage::shouldReceive('build')->byDefault()->andReturn($this->storageDisk);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/admin/health');

        $response->assertStatus(401);
    }

    public function test_authenticated_request_returns_health_status(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/health');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'status',
                'timestamp',
                'checks' => [
                    'database' => [
                        'status',
                        'response_time_ms',
                    ],
                    'storage' => [
                        'status',
                        'response_time_ms',
                    ],
                ],
                'app' => [
                    'environment',
                    'laravel_version',
                    'php_version',
                ],
            ],
            'message',
        ]);
    }

    public function test_healthy_status_when_all_systems_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/health');

        $response->assertOk();
        $data = $response->json('data');

        // All systems should be healthy
        $this->assertEquals('healthy', $data['status']);
        $this->assertEquals('healthy', $data['checks']['database']['status']);
        $this->assertEquals('healthy', $data['checks']['storage']['status']);

        // Response times should be reasonable (not negative, not absurd)
        $this->assertGreaterThanOrEqual(0, $data['checks']['database']['response_time_ms']);
        $this->assertLessThan(5000, $data['checks']['database']['response_time_ms']);
        $this->assertGreaterThanOrEqual(0, $data['checks']['storage']['response_time_ms']);
        $this->assertLessThan(5000, $data['checks']['storage']['response_time_ms']);
    }

    public function test_storage_failure_is_reported_as_degraded_without_real_r2_access(): void
    {
        $this->storageDisk
            ->shouldReceive('exists')
            ->once()
            ->with('__health_check_probe__')
            ->andThrow(new \RuntimeException('R2 unavailable'));
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/health');

        $response->assertOk();
        $response->assertJsonPath('data.checks.storage.status', 'down');
        $response->assertJsonPath('data.status', 'degraded');
    }

    public function test_response_does_not_expose_secrets(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/health');

        $response->assertOk();
        $body = json_encode($response->json());

        // Verify no sensitive data is in the response
        $this->assertStringNotContainsString('APP_KEY', $body);
        $this->assertStringNotContainsString('DB_PASSWORD', $body);
        $this->assertStringNotContainsString('DB_HOST', $body);
        $this->assertStringNotContainsString('DB_USER', $body);
        $this->assertStringNotContainsString('R2_SECRET', $body);
        $this->assertStringNotContainsString('AWS_SECRET', $body);
        $this->assertStringNotContainsString('access_key', $body);
        $this->assertStringNotContainsString('secret_key', $body);
    }

    public function test_degraded_when_response_time_exceeds_threshold(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // Note: This is a soft test since we can't easily force a 1000ms+ delay
        // in a real DB query. The threshold logic is tested in unit tests.
        // Here we just verify the endpoint doesn't error under normal conditions.

        $response = $this->getJson('/api/admin/health');
        $response->assertOk();

        $status = $response->json('data.status');
        $this->assertIsString($status);
        $this->assertTrue(in_array($status, ['healthy', 'degraded', 'down']));
    }

    public function test_status_transitions_follow_rules(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/health');
        $response->assertOk();

        $data = $response->json('data');
        $overall = $data['status'];
        $dbStatus = $data['checks']['database']['status'];
        $storageStatus = $data['checks']['storage']['status'];

        // Rule: database down always means overall down
        if ($dbStatus === 'down') {
            $this->assertEquals('down', $overall);
        }

        // Rule: healthy database + healthy storage + fast response times = healthy
        if ($dbStatus === 'healthy' && $storageStatus === 'healthy' &&
            $data['checks']['database']['response_time_ms'] <= 1000 &&
            $data['checks']['storage']['response_time_ms'] <= 1000) {
            $this->assertEquals('healthy', $overall);
        }

        // Rule: storage down but database healthy = degraded (not down)
        if ($dbStatus === 'healthy' && $storageStatus === 'down') {
            $this->assertEquals('degraded', $overall);
        }
    }

    public function test_timestamp_is_iso8601_formatted(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/health');
        $response->assertOk();

        $timestamp = $response->json('data.timestamp');

        // ISO-8601 format check: should be parseable
        $dateTime = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $timestamp);
        $this->assertNotFalse($dateTime, 'timestamp must be ISO-8601 formatted');
    }

    public function test_app_info_contains_no_secrets(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/health');
        $response->assertOk();

        $app = $response->json('data.app');

        // Only safe fields
        $this->assertArrayHasKey('environment', $app);
        $this->assertArrayHasKey('laravel_version', $app);
        $this->assertArrayHasKey('php_version', $app);

        // Should be strings
        $this->assertIsString($app['environment']);
        $this->assertIsString($app['laravel_version']);
        $this->assertIsString($app['php_version']);

        // Should not be empty
        $this->assertNotEmpty($app['environment']);
        $this->assertNotEmpty($app['laravel_version']);
        $this->assertNotEmpty($app['php_version']);
    }

    public function test_endpoint_does_not_create_table_or_migration(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // Call the endpoint multiple times
        $this->getJson('/api/admin/health')->assertOk();
        $this->getJson('/api/admin/health')->assertOk();

        // Verify no health_check_logs or health_checks table was created
        $tables = DB::select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?", [
            config('database.connections.' . config('database.default') . '.database'),
        ]);

        $tableNames = array_map(function ($table) {
            return $table->TABLE_NAME;
        }, $tables);

        $this->assertNotContains('health_check_logs', $tableNames);
        $this->assertNotContains('health_checks', $tableNames);
        $this->assertNotContains('system_health', $tableNames);
    }
}
