<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * GET /api/admin/health
     *
     * Returns comprehensive system health status. Admin-only.
     */
    public function index(HealthCheckService $healthCheck): JsonResponse
    {
        $database = $healthCheck->checkDatabase();
        $storage = $healthCheck->checkStorage();
        $app = $healthCheck->getAppInfo();
        $overallStatus = $healthCheck->computeOverallStatus($database, $storage);

        return ApiResponse::success(
            [
                'status' => $overallStatus,
                'timestamp' => now()->toIso8601String(),
                'checks' => [
                    'database' => $database,
                    'storage' => $storage,
                ],
                'app' => $app,
            ],
            'Health check completed.',
        );
    }
}
