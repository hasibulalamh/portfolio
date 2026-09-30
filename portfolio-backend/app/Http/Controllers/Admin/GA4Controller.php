<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\GA4Service;
use Illuminate\Http\JsonResponse;

class GA4Controller extends Controller
{
    /**
     * GET /api/admin/analytics/ga4
     *
     * Read-only Google Analytics 4 summary for the dashboard card. Both the
     * success payload and the { error: 'ga4_unavailable', message } payload
     * come fully formed from GA4Service — this controller only re-wraps them
     * in the shared envelope, and always answers 200 because "analytics is
     * down" is a dashboard display state, not an API failure.
     */
    public function index(GA4Service $ga4): JsonResponse
    {
        return ApiResponse::success(
            $ga4->getSummary(),
            'GA4 summary retrieved.',
        );
    }
}
