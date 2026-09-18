<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingRequest;
use App\Http\Resources\SettingResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use App\Services\SingletonResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SettingController extends Controller
{
    public function show(): JsonResponse
    {
        return ApiResponse::success(
            new SettingResource(Setting::singleton()),
            'Settings retrieved.',
        );
    }

    public function update(SettingRequest $request): JsonResponse
    {
        $settings = Setting::singleton();
        // Only fields present in the request are changed. In particular, an
        // unrelated partial update must not turn an existing media reference
        // into null because the client did not include it.
        $settings->fill($request->validated())->save();
        $settings->refresh();

        Log::info('Admin settings persisted.', [
            'id' => $settings->getKey(),
            'logo_type' => $settings->logo_type,
            'logo_path' => $settings->logo_path,
            'favicon_path' => $settings->favicon_path,
        ]);

        return ApiResponse::success(
            new SettingResource($settings),
            'Settings updated successfully.',
        );
    }

    /**
     * POST /admin/settings/reset
     *
     * Blanks every field and deletes the uploaded logo/favicon. Returns the
     * cleared record so the admin form can re-render from persisted state
     * rather than guessing at what the reset did.
     */
    public function reset(SingletonResetService $resets): JsonResponse
    {
        $result = $resets->reset(Setting::singleton());

        return ApiResponse::success(
            new SettingResource($result['model']),
            'Settings reset successfully.',
        );
    }
}
