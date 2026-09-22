<?php

namespace App\Services;

use App\Models\ClickEvent;
use Illuminate\Support\Facades\DB;

class ClickTrackingService
{
    /**
     * The fixed set of event types the public endpoint accepts. Everything
     * else is silently ignored — no row, no error — so arbitrary strings can
     * never be inserted and the aggregate view cannot be polluted.
     *
     * portfolio-frontend/lib/track.js mirrors this list; keep the two in sync.
     */
    public const EVENT_TYPES = [
        'hire_me_click',
        'email_click',
        'whatsapp_click',
        'cv_download',
        'github_click',
        'linkedin_click',
        'contact_form_submit',
    ];

    /**
     * Record one click event.
     *
     * Unlisted event types are dropped silently rather than rejected with an
     * exception: the caller is an anonymous public endpoint, so a bad value is
     * noise to be discarded, not an error to surface.
     */
    public function record(string $eventType): void
    {
        if (! in_array($eventType, self::EVENT_TYPES, true)) {
            return;
        }

        ClickEvent::query()->create([
            'event_type' => $eventType,
            // Timestamps are off on the model, so created_at is set here rather
            // than by Laravel's automatic timestamp handling.
            'created_at' => now(),
        ]);
    }

    /**
     * Total clicks and per-type counts, over the whole table.
     *
     * One grouped query feeding both figures: a separate COUNT(*) would read
     * the table twice and could disagree with the by-type sum under a write
     * racing between the two queries.
     *
     * Every allow-listed type is present in the result with a zero baseline so
     * the admin card renders a stable list, and any type string that ever
     * existed in the table survives the same way.
     *
     * @return array{total: int, by_type: array<string, int>}
     */
    public function getAggregateCounts(): array
    {
        $counts = ClickEvent::query()
            ->select('event_type', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('event_type')
            ->pluck('aggregate', 'event_type');

        $byType = [];
        $total = 0;

        foreach (self::EVENT_TYPES as $eventType) {
            $count = (int) $counts->get($eventType, 0);
            $byType[$eventType] = $count;
            $total += $count;
        }

        return [
            'total' => $total,
            'by_type' => $byType,
        ];
    }
}
