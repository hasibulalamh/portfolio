<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One recorded click on a public conversion-intent CTA.
 *
 * Written exclusively through ClickTrackingService, which validates the event
 * type against its allow-list before anything reaches this model — arbitrary
 * strings never become rows. There is no updated_at (see the table migration):
 * a click is written once and never changed, so the model disables Laravel's
 * timestamp maintenance and the service sets created_at explicitly on insert.
 *
 * Deliberately no relationships, scopes or accessors: the table exists to be
 * counted, and getAggregateCounts() is the only reader.
 */
class ClickEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
