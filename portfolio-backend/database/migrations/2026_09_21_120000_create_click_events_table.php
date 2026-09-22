<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregate-only click tracking for the public site's conversion-intent CTAs.
 *
 * One row per click, deliberately carrying nothing but the event type and the
 * time it happened: no visitor identity, no IP, no session, no user agent, and
 * no per-row reference back to the request that produced it. The endpoint
 * accepts only a fixed allow-list of event types, so event_type is indexed
 * (every read is a group-by over it) but nothing else needs a column.
 *
 * created_at without updated_at: a click is an immutable fact, never edited —
 * the same treatment the events tables in Laravel's own first-party packages
 * get. down() drops the table; there are no soft deletes to consider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('click_events', function (Blueprint $table) {
            $table->id();
            // One of the fixed event types in ClickTrackingService::EVENT_TYPES,
            // e.g. 'hire_me_click'. Indexed because the only read this table
            // ever serves groups by it.
            $table->string('event_type', 50)->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('click_events');
    }
};
