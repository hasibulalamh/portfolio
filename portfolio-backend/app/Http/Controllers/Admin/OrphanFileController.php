<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\OrphanFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin-only storage hygiene: find uploaded files nothing references any
 * more, and delete them — but only ones the admin explicitly picked.
 *
 * Read-only by default; the DELETE side re-verifies every path server-side
 * before touching it, so a stale scan result can never cause a delete.
 */
class OrphanFileController extends Controller
{
    /**
     * GET /api/admin/storage/orphans
     */
    public function index(OrphanFileService $orphans): JsonResponse
    {
        return ApiResponse::success(
            $orphans->findOrphans(),
            'Orphan file scan completed.',
        );
    }

    /**
     * DELETE /api/admin/storage/orphans
     *
     * Body: { "paths": ["logos/….png", …] }
     */
    public function destroy(Request $request, OrphanFileService $orphans): JsonResponse
    {
        $validated = $request->validate([
            'paths' => ['required', 'array', 'min:1'],
            'paths.*' => ['string', Rule::notIn([''])],
        ]);

        return ApiResponse::success(
            $orphans->deleteFiles($validated['paths']),
            'Orphan file deletion completed.',
        );
    }
}
