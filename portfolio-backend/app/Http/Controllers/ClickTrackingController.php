<?php

namespace App\Http\Controllers;

use App\Services\ClickTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public click-tracking endpoint — called by anonymous visitors on
 * hasibulalam.com, so there is deliberately no auth here.
 */
class ClickTrackingController extends Controller
{
    /**
     * POST /api/track
     *
     * Body: { "event_type": "hire_me_click" }
     *
     * Returns 204 No Content: there is nothing for the fire-and-forget caller
     * to read back, and an empty body keeps the response trivially cheap. The
     * service silently ignores anything outside its allow-list, which the
     * validation below turns into a 422 before it is ever reached.
     */
    public function store(Request $request, ClickTrackingService $tracking): Response
    {
        $validated = $request->validate([
            'event_type' => ['required', 'string', 'in:'.implode(',', ClickTrackingService::EVENT_TYPES)],
        ]);

        $tracking->record($validated['event_type']);

        return response()->noContent();
    }
}
