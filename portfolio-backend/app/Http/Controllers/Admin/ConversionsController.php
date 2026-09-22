<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\ClickTrackingService;
use Illuminate\Http\JsonResponse;

class ConversionsController extends Controller
{
    /**
     * GET /api/admin/conversions
     *
     * Aggregate CTA click counts for the admin dashboard card. All-time only:
     * the feature tracks nothing else, so there is no period parameter to
     * accept and nothing to filter.
     */
    public function index(ClickTrackingService $tracking): JsonResponse
    {
        return ApiResponse::success(
            [
                ...$tracking->getAggregateCounts(),
                'period' => 'all_time',
            ],
            'Conversion counts retrieved.',
        );
    }
}
