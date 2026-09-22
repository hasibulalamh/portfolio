<?php

namespace Tests\Feature;

use App\Models\ClickEvent;
use App\Models\User;
use App\Services\ClickTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The click-tracking pair end to end: the public POST /api/track and the
 * admin-only GET /api/admin/conversions.
 *
 * The endpoint is deliberately unauthenticated — anonymous visitors fire it —
 * so the tests pin the two properties that make that safe: only allow-listed
 * event types ever become rows, and the aggregates stay admin-only. Rate
 * limiting is asserted structurally (middleware attached to the route) rather
 * than by exhausting the limit, which would slow the suite and fight the
 * limiter's per-minute window.
 */
class ClickTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_event_type_returns_no_content_and_creates_a_row(): void
    {
        $this->postJson('/api/track', ['event_type' => 'hire_me_click'])
            ->assertStatus(204);

        $this->assertDatabaseCount('click_events', 1);
        $this->assertDatabaseHas('click_events', [
            'event_type' => 'hire_me_click',
        ]);
    }

    public function test_the_recorded_row_has_a_created_at_and_no_updated_at(): void
    {
        $this->postJson('/api/track', ['event_type' => 'cv_download'])->assertStatus(204);

        $event = ClickEvent::query()->sole();

        $this->assertNotNull($event->created_at);
        // The table has no updated_at column by design; assert it stays that way
        // so nobody can reintroduce timestamps without this test noticing.
        $this->assertSame([], $event->getDirty());
        $this->assertTrue(
            app('db')->getSchemaBuilder()->hasColumn('click_events', 'created_at'),
        );
        $this->assertFalse(
            app('db')->getSchemaBuilder()->hasColumn('click_events', 'updated_at'),
        );
    }

    public function test_every_allow_listed_event_type_is_accepted(): void
    {
        foreach (ClickTrackingService::EVENT_TYPES as $eventType) {
            $this->postJson('/api/track', ['event_type' => $eventType])->assertStatus(204);
        }

        $this->assertDatabaseCount('click_events', count(ClickTrackingService::EVENT_TYPES));
    }

    public function test_an_unlisted_event_type_is_rejected_and_creates_no_row(): void
    {
        $this->postJson('/api/track', ['event_type' => 'arbitrary_string'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('event_type');

        $this->assertDatabaseCount('click_events', 0);
    }

    public function test_a_missing_event_type_is_rejected(): void
    {
        $this->postJson('/api/track', [])->assertStatus(422);

        $this->assertDatabaseCount('click_events', 0);
    }

    public function test_a_non_string_event_type_is_rejected(): void
    {
        $this->postJson('/api/track', ['event_type' => ['hire_me_click']])->assertStatus(422);

        $this->assertDatabaseCount('click_events', 0);
    }

    public function test_the_endpoint_requires_no_authentication(): void
    {
        // Explicitly no Sanctum::actingAs and no token: the whole point is that
        // an anonymous visitor can post this. If a future change moves the route
        // behind auth, this fails loudly.
        $this->postJson('/api/track', ['event_type' => 'email_click'])
            ->assertStatus(204);

        $this->assertDatabaseCount('click_events', 1);
    }

    public function test_the_track_route_carries_the_throttle_middleware(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route) => in_array('POST', $route->methods(), true)
                && $route->uri() === 'api/track');

        $this->assertNotNull($route, 'POST /api/track must exist');

        $middleware = $route->gatherMiddleware();

        $this->assertContains('throttle:30,1', $middleware);
        $this->assertNotContains('auth:sanctum', $middleware);
    }

    public function test_conversions_requires_authentication(): void
    {
        $this->getJson('/api/admin/conversions')->assertStatus(401);
    }

    public function test_conversions_returns_aggregated_counts_for_an_admin(): void
    {
        $tracking = app(ClickTrackingService::class);
        $tracking->record('hire_me_click');
        $tracking->record('hire_me_click');
        $tracking->record('cv_download');
        $tracking->record('contact_form_submit');

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/conversions');

        $response->assertOk();
        $response->assertJsonPath('data.total', 4);
        $response->assertJsonPath('data.by_type.hire_me_click', 2);
        $response->assertJsonPath('data.by_type.cv_download', 1);
        $response->assertJsonPath('data.by_type.contact_form_submit', 1);
        $response->assertJsonPath('data.by_type.email_click', 0);
        $response->assertJsonPath('data.period', 'all_time');
    }

    public function test_conversions_reports_all_types_with_zero_baseline_when_the_table_is_empty(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/conversions');

        $response->assertOk();
        $response->assertJsonPath('data.total', 0);

        foreach (ClickTrackingService::EVENT_TYPES as $eventType) {
            $response->assertJsonPath("data.by_type.{$eventType}", 0);
        }
    }
}
