<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\Analytics\Data\V1beta\RunReportResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-only Google Analytics 4 summary for the admin dashboard.
 *
 * One method, one report: runReport over the trailing window for activeUsers,
 * sessions and screenPageViews. The result is cached for an hour (config('analytics.cache_ttl_seconds'))
 * because GA4 aggregates for a trailing window change at most daily, and every
 * admin page load would otherwise spend a quota'd API request on numbers a day
 * stale anyway.
 *
 * Failure policy: analytics is a nice-to-have on this dashboard. Any failure —
 * missing configuration, missing credential file, API error, network problem,
 * malformed property ID — is caught, logged with context and converted into a
 * structured "ga4_unavailable" payload. Nothing here throws out to the admin
 * request that asked for it, and no placeholder numbers are ever fabricated:
 * an absent metric stays absent.
 *
 * The credential file path and property ID are configuration, not response
 * data. They never appear in anything this service returns.
 */
class GA4Service
{
    private const CACHE_KEY = 'ga4_summary';

    public function __construct() {}

    /**
     * Cached GA4 summary for the dashboard card, or a structured unavailability
     * payload when analytics cannot be reached.
     *
     * @return array{
     *     period?: string,
     *     visitors?: int,
     *     sessions?: int,
     *     page_views?: int,
     *     cached_at?: string,
     *     cache_expires_at?: string,
     *     error?: string,
     *     message?: string
     * }
     */
    public function getSummary(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            (int) config('analytics.cache_ttl_seconds', 3600),
            fn () => $this->fetchSummary(),
        );
    }

    /**
     * The uncached fetch. Returns either the summary payload or the
     * "ga4_unavailable" error payload — never throws.
     *
     * @return array<string, mixed>
     */
    private function fetchSummary(): array
    {
        $propertyId = config('analytics.property_id');
        $credentialsPath = config('analytics.credentials_path');

        // Missing configuration is the expected state on a fresh checkout and
        // is not worth a log line — it is exactly what "unavailable" exists
        // for. Configured-but-failing is worth logging.
        if (! $propertyId || ! $credentialsPath) {
            return $this->unavailable('Analytics is not configured.');
        }

        if (! is_file($credentialsPath)) {
            Log::warning('GA4 credential file not found.', [
                'configured_path' => $credentialsPath,
                'property_id_set' => true,
            ]);

            return $this->unavailable('Analytics credential file is missing.');
        }

        // Defensive: GA4_PROPERTY_ID may be configured with or without the
        // "properties/" prefix the API expects. Strip it if present so a
        // misconfigured env var never produces a malformed, duplicated
        // "properties/properties/123" resource name.
        $cleanPropertyId = preg_replace('#^properties/#', '', (string) $propertyId);

        try {
            $response = $this->runReport($credentialsPath, $cleanPropertyId);

            // With no dimensions the report is a single aggregate row (or none
            // at all on a brand-new property with no traffic). Totals are
            // authoritative for the aggregate; row 0 is the fallback for API
            // versions that omit totals on single-row reports.
            $totals = $response->getTotals();
            $row = $totals->count() > 0
                ? $totals[0]
                : ($response->getRows()->count() > 0 ? $response->getRows()[0] : null);

            if ($row === null) {
                // No traffic in the window: real zeros, not a failure.
                $values = ['0', '0', '0'];
            } else {
                $values = iterator_to_array($row->getMetricValues());
                $values = array_map(
                    fn ($metricValue) => $metricValue->getValue(),
                    $values,
                );
            }

            $now = CarbonImmutable::now();

            return [
                'period' => 'last_'.(int) config('analytics.period_days', 30).'_days',
                'visitors' => (int) ($values[0] ?? 0),
                'sessions' => (int) ($values[1] ?? 0),
                'page_views' => (int) ($values[2] ?? 0),
                'cached_at' => $now->toIso8601String(),
                'cache_expires_at' => $now->addSeconds((int) config('analytics.cache_ttl_seconds', 3600))->toIso8601String(),
            ];
        } catch (Throwable $e) {
            // Anything the Google client can throw — invalid property, bad
            // credentials, quota, network — lands here. Log the reason with
            // context, return the safe payload, never a 500, never secrets.
            Log::warning('GA4 summary fetch failed.', [
                'exception' => $e::class,
                'reason' => $e->getMessage(),
                'property_id_set' => (bool) $propertyId,
            ]);

            return $this->unavailable('Analytics service could not be reached.');
        }
    }

    /**
     * The structured failure payload. Every failure funnels through here so
     * the message stays non-sensitive by construction: no paths, no property
     * IDs, no vendor exception text.
     */
    private function unavailable(string $message): array
    {
        return [
            'error' => 'ga4_unavailable',
            'message' => $message,
        ];
    }

    /**
     * Issue the single aggregate report through the official client.
     *
     * Kept in its own method so tests can replace the network call wholesale
     * (the test seam — see GA4EndpointTest): the generated client class is
     * `final`, so `client()` cannot be mocked, but this method can.
     *
     * @param  string  $propertyId  Already stripped of any "properties/" prefix.
     */
    protected function runReport(string $credentialsPath, string $propertyId): RunReportResponse
    {
        return $this->client($credentialsPath)->runReport(
            (new RunReportRequest)
                ->setProperty('properties/'.$propertyId)
                ->setDateRanges([
                    (new DateRange)
                        ->setStartDate(now()->subDays((int) config('analytics.period_days', 30))->toDateString())
                        ->setEndDate('today'),
                ])
                ->setMetrics([
                    new Metric(['name' => 'activeUsers']),
                    new Metric(['name' => 'sessions']),
                    new Metric(['name' => 'screenPageViews']),
                ])
        );
    }

    /**
     * Build the official client bound to the service-account credentials.
     *
     * The generated client class is `final`, so tests replace the call at the
     * `runReport()` seam rather than mocking this construction. The transport
     * gets bounded timeouts (HealthCheckService does the same for its R2 probe)
     * instead of the SDK's default unlimited wait.
     */
    protected function client(string $credentialsPath): BetaAnalyticsDataClient
    {
        return new BetaAnalyticsDataClient([
            'credentials' => $credentialsPath,
            'transportConfig' => [
                'rest' => [
                    'timeout' => (float) config('analytics.timeout_seconds', 10),
                ],
            ],
        ]);
    }
}
