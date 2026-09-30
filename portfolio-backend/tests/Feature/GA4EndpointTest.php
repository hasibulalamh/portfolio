<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GA4Service;
use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\MetricValue;
use Google\Analytics\Data\V1beta\Row;
use Google\Analytics\Data\V1beta\RunReportResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * Admin-only GA4 summary endpoint (GET /api/admin/analytics/ga4).
 *
 * Everything here is offline: the Google client is mocked at the service's
 * `client()` seam — no test ever constructs real credentials or touches the
 * network. The suite pins four things the dashboard depends on:
 *
 *  1. auth:sanctum guards the route (401 without a token);
 *  2. the success payload shape (visitors / sessions / page_views / period /
 *     cached_at / cache_expires_at) survives the ApiResponse envelope;
 *  3. any service-level failure degrades to a 200 with { error:
 *     'ga4_unavailable' } — never a 500, never fabricated numbers;
 *  4. neither the credential path nor the property ID — the only secrets this
 *     feature touches — can appear anywhere in a response body, success or
 *     failure (the HealthCheckEndpointTest secrets test's pattern).
 *
 * A fifth, service-level test pins the cache contract: within the cache TTL a
 * second getSummary() must not re-invoke the underlying GA4 client.
 */
class GA4EndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A path that is set but fake — asserted absent from every response body
     * in this suite, exactly as the real secret-file path must be.
     */
    private const FAKE_CREDENTIAL_PATH = '/tmp/should-never-leak/ga4-service-account.json';

    /**
     * A fake-but-plausible property ID — asserted absent from every response
     * body in this suite, exactly as the real GA4_PROPERTY_ID must be.
     */
    private const FAKE_PROPERTY_ID = '9876543210';

    private array $validSummary;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('analytics.property_id', self::FAKE_PROPERTY_ID);
        config()->set('analytics.credentials_path', self::FAKE_CREDENTIAL_PATH);
        config()->set('analytics.cache_ttl_seconds', 3600);

        $this->validSummary = [
            'period' => 'last_30_days',
            'visitors' => 1234,
            'sessions' => 1500,
            'page_views' => 4321,
            'cached_at' => '2026-09-23T12:00:00+00:00',
            'cache_expires_at' => '2026-09-23T13:00:00+00:00',
        ];
    }

    /**
     * Bind a fully-stubbed GA4Service into the container so the controller
     * resolves the mock instead of the real service.
     */
    private function mockService(callable $defineExpectations): void
    {
        $mock = Mockery::mock(GA4Service::class);
        $defineExpectations($mock);
        $this->app->instance(GA4Service::class, $mock);
    }

    /**
     * A partial mock whose real getSummary()/fetchSummary() logic runs while
     * the `client()` seam is swapped for a test double. This exercises the
     * service's real error handling and cache flow offline.
     */
    private function partialService(callable $defineClient): GA4Service
    {
        $mock = Mockery::mock(GA4Service::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $defineClient($mock);
        $this->app->instance(GA4Service::class, $mock);

        return $mock;
    }

    /**
     * The one aggregate-row report the service parses. Metric order matters:
     * activeUsers, sessions, screenPageViews — the order the service requests.
     */
    private function fakeReportResponse(string $visitors = '123', string $sessions = '456', string $pageViews = '789'): RunReportResponse
    {
        return new RunReportResponse([
            'rows' => [
                new Row([
                    'metric_values' => [
                        new MetricValue(['value' => $visitors]),
                        new MetricValue(['value' => $sessions]),
                        new MetricValue(['value' => $pageViews]),
                    ],
                ]),
            ],
        ]);
    }

    /**
     * The secrets assertion, applied to every response shape this suite can
     * produce. If any of these strings surfaces in a body, configuration has
     * leaked into API output.
     */
    private function assertNoAnalyticsSecretsInBody($response): void
    {
        $body = json_encode($response->json());

        $this->assertStringNotContainsString(self::FAKE_CREDENTIAL_PATH, $body);
        $this->assertStringNotContainsString('ga4-service-account', $body);
        $this->assertStringNotContainsString('should-never-leak', $body);
        $this->assertStringNotContainsString(self::FAKE_PROPERTY_ID, $body);
        $this->assertStringNotContainsString('properties/', $body);
        $this->assertStringNotContainsString('GOOGLE_APPLICATION_CREDENTIALS', $body);
        $this->assertStringNotContainsString('GA4_PROPERTY_ID', $body);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/admin/analytics/ga4');

        $response->assertStatus(401);
    }

    public function test_authenticated_request_returns_valid_summary_shape(): void
    {
        $this->mockService(function ($mock) {
            $mock->shouldReceive('getSummary')->once()->andReturn($this->validSummary);
        });
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/analytics/ga4');

        $response->assertOk();
        $response->assertJsonPath('errors', null);
        $response->assertJsonStructure([
            'data' => [
                'period',
                'visitors',
                'sessions',
                'page_views',
                'cached_at',
                'cache_expires_at',
            ],
            'message',
        ]);
        $response->assertJsonPath('data.visitors', 1234);
        $response->assertJsonPath('data.sessions', 1500);
        $response->assertJsonPath('data.page_views', 4321);
        $response->assertJsonPath('data.period', 'last_30_days');

        $this->assertNoAnalyticsSecretsInBody($response);
    }

    public function test_service_failure_is_wrapped_as_ga4_unavailable_not_500(): void
    {
        // Even a stubbed service answering its structured error payload must
        // come out as a 200 envelope — the controller never escalates an
        // analytics outage into an HTTP failure.
        $this->mockService(function ($mock) {
            $mock->shouldReceive('getSummary')->once()->andReturn([
                'error' => 'ga4_unavailable',
                'message' => 'Analytics service could not be reached.',
            ]);
        });
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/analytics/ga4');

        $response->assertOk();
        $response->assertJsonPath('data.error', 'ga4_unavailable');
        $response->assertJsonPath('errors', null);

        // The error payload carries no metric fields to mistake for data.
        $this->assertArrayNotHasKey('visitors', $response->json('data'));
        $this->assertArrayNotHasKey('page_views', $response->json('data'));

        $this->assertNoAnalyticsSecretsInBody($response);
    }

    public function test_real_service_failure_path_returns_ga4_unavailable_without_leaking_the_exception(): void
    {
        // The full real service runs here; only the client seam is swapped.
        // The credential path points at an existing file (content is never
        // read) so the service proceeds past its is_file() guard to the client
        // call, which then throws. The exception text below is designed to
        // look like a leak magnet — if any of it reached the response, this
        // test fails.
        Cache::flush();
        config()->set('analytics.credentials_path', base_path('composer.json'));
        $this->partialService(function ($mock) {
            $mock->shouldReceive('client')->once()->andThrow(
                new \RuntimeException('permission denied reading '.self::FAKE_CREDENTIAL_PATH.' for properties/'.self::FAKE_PROPERTY_ID),
            );
        });
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/analytics/ga4');

        $response->assertOk();
        $response->assertJsonPath('data.error', 'ga4_unavailable');
        $this->assertStringNotContainsString('permission denied', json_encode($response->json()));

        $this->assertNoAnalyticsSecretsInBody($response);
    }

    public function test_second_service_call_within_cache_window_does_not_hit_the_ga4_client_again(): void
    {
        Cache::flush();
        $client = Mockery::mock(BetaAnalyticsDataClient::class);
        $client
            ->shouldReceive('runReport')
            ->once()
            ->with(Mockery::on(
                fn ($request) => $request->getProperty() === 'properties/'.self::FAKE_PROPERTY_ID,
            ))
            ->andReturn($this->fakeReportResponse());

        $service = $this->partialService(function ($mock) use ($client) {
            // Point at an existing file so the real service proceeds to the
            // client call; the file's content is never read.
            config()->set('analytics.credentials_path', base_path('composer.json'));
            $mock->shouldReceive('client')->once()->andReturn($client);
        });

        $first = $service->getSummary();
        $second = $service->getSummary();

        $this->assertSame(123, $first['visitors']);
        $this->assertSame(456, $first['sessions']);
        $this->assertSame(789, $first['page_views']);
        $this->assertSame('last_30_days', $first['period']);

        // The second call is served from cache: same payload, same timestamps.
        $this->assertSame($first, $second);

        // runReport's ->once() expectation above is the real assertion: a
        // second underlying API call would fail the Mockery expectation.
    }

    public function test_failure_payload_is_cached_so_failures_do_not_hammer_the_api(): void
    {
        Cache::flush();
        config()->set('analytics.credentials_path', base_path('composer.json'));
        $service = $this->partialService(function ($mock) {
            $mock->shouldReceive('client')->once()->andThrow(
                new \RuntimeException('network unreachable'),
            );
        });

        $first = $service->getSummary();
        $second = $service->getSummary();

        $this->assertSame('ga4_unavailable', $first['error']);
        $this->assertSame($first, $second);
        // client()'s ->once() above fails if the second call re-attempted
        // the API — a quota-burning retry loop under sustained outage.
    }
}
