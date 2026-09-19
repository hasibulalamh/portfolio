<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthCheckService
{
    private const SLOW_THRESHOLD_MS = 1000;
    private const STORAGE_TIMEOUT_SECONDS = 3.0;
    private const STORAGE_HEALTH_PROBE = '__health_check_probe__';

    /**
     * Check database connectivity.
     *
     * @return array{status: string, response_time_ms: int}
     */
    public function checkDatabase(): array
    {
        $startTime = microtime(true);

        try {
            DB::select('SELECT 1');
            $elapsed = $this->elapsedMs($startTime);

            return [
                'status' => 'healthy',
                'response_time_ms' => $elapsed,
            ];
        } catch (Throwable $e) {
            $elapsed = $this->elapsedMs($startTime);

            return [
                'status' => 'down',
                'response_time_ms' => $elapsed,
            ];
        }
    }

    /**
     * Check R2 storage connectivity.
     *
     * Uses a read-only object lookup against R2. The disk is built only for
     * this check with bounded AWS SDK HTTP timeouts, so normal upload clients
     * retain their existing configuration.
     *
     * @return array{status: string, response_time_ms: int}
     */
    public function checkStorage(): array
    {
        $startTime = microtime(true);

        try {
            $r2Key = config('filesystems.disks.r2.key');
            $r2Secret = config('filesystems.disks.r2.secret');
            $r2Bucket = config('filesystems.disks.r2.bucket');
            $r2Endpoint = config('filesystems.disks.r2.endpoint');

            if (! $r2Key || ! $r2Secret || ! $r2Bucket || ! $r2Endpoint) {
                throw new \RuntimeException('R2 configuration is incomplete');
            }

            // A missing probe object is still a successful R2 response. The
            // S3 adapter implements this as a read-only HeadObject request.
            Storage::build(array_replace(config('filesystems.disks.r2', []), [
                'http' => [
                    'connect_timeout' => self::STORAGE_TIMEOUT_SECONDS,
                    'timeout' => self::STORAGE_TIMEOUT_SECONDS,
                ],
                'retries' => 0,
            ]))->exists(self::STORAGE_HEALTH_PROBE);

            $elapsed = $this->elapsedMs($startTime);

            return [
                'status' => 'healthy',
                'response_time_ms' => $elapsed,
            ];
        } catch (Throwable $e) {
            $elapsed = $this->elapsedMs($startTime);

            return [
                'status' => 'down',
                'response_time_ms' => $elapsed,
            ];
        }
    }


    /**
     * Get safe application information.
     *
     * @return array{environment: string, laravel_version: string, php_version: string}
     */
    public function getAppInfo(): array
    {
        return [
            'environment' => config('app.env'),
            'laravel_version' => app()->version(),
            'php_version' => phpversion(),
        ];
    }

    /**
     * Compute overall health status based on check results.
     *
     * Rules:
     * - database down → "down"
     * - any check slow (>1000ms) → "degraded"
     * - storage down → "degraded"
     * - all healthy and fast → "healthy"
     *
     * @param array $database Database check result
     * @param array $storage Storage check result
     * @return string
     */
    public function computeOverallStatus(array $database, array $storage): string
    {
        // Rule 1: Database down is critical
        if ($database['status'] === 'down') {
            return 'down';
        }

        // Rule 2: Storage down is degraded (not fully healthy)
        if ($storage['status'] === 'down') {
            return 'degraded';
        }

        // Rule 3: Any check exceeds slow threshold
        if (
            $database['response_time_ms'] > self::SLOW_THRESHOLD_MS ||
            $storage['response_time_ms'] > self::SLOW_THRESHOLD_MS
        ) {
            return 'degraded';
        }

        // Rule 4: All healthy and fast
        return 'healthy';
    }

    /**
     * Calculate elapsed time in milliseconds since start time.
     *
     * @param float $startTime Result of microtime(true)
     * @return int Elapsed milliseconds
     */
    private function elapsedMs(float $startTime): int
    {
        return (int) round((microtime(true) - $startTime) * 1000);
    }
}
