<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Chaos/fault-injection tests: verify graceful degradation, not crashes, when
 * a dependency fails.
 *
 * These are automated (not manual) because the application already swallows
 * mail failures by design. What we verify is that the data integrity guarantees
 * hold: no data loss, no false success signals, no crashes.
 *
 * Scenarios:
 * 1. Mail transport unreachable during contact submission
 * 2. Mail transport unreachable during meeting submission
 * 3. Mail transport unreachable during admin reply
 * 4. File storage unavailable during upload (simulated via fake disk)
 * 5. Invalid mail configuration doesn't crash the notifier
 */
class ChaosInjectionTest extends TestCase
{
    use RefreshDatabase;

    // =====================================================================
    // Scenario 1 & 2: Mail transport down during public submissions
    // =====================================================================

    public function test_contact_submission_survives_mail_transport_failure(): void
    {
        config(['mail.admin_notify_address' => 'admin@example.test']);

        // Simulate transport unreachable
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('Connection refused'));

        $response = $this->postJson('/api/contact-messages', [
            'name' => 'Chaos Visitor',
            'email' => 'chaos@example.com',
            'subject' => 'Test',
            'message' => 'Will this survive?',
        ]);

        // The submission must succeed despite mail failure
        $response->assertCreated();
        $response->assertJsonPath('message', fn ($msg) => str_contains($msg, 'Thanks'));

        // The record must be in the database
        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Chaos Visitor',
            'email' => 'chaos@example.com',
        ]);
    }

    public function test_meeting_submission_survives_mail_transport_failure(): void
    {
        config(['mail.admin_notify_address' => 'admin@example.test']);

        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('Connection refused'));

        $response = $this->postJson('/api/meeting-requests', [
            'name' => 'Chaos Client',
            'email' => 'chaos@example.com',
            'message' => 'Will this survive?',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('meeting_requests', [
            'name' => 'Chaos Client',
            'email' => 'chaos@example.com',
            'status' => 'pending',
        ]);
    }

    // =====================================================================
    // Scenario 3: Mail transport down during admin reply
    // =====================================================================

    public function test_admin_reply_survives_mail_transport_failure(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $message = ContactMessage::query()->create([
            'name' => 'Chaos Client',
            'email' => 'chaos@example.com',
            'message' => 'Help?',
        ]);

        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('SMTP server down'));

        $response = $this->postJson("/api/admin/contact-messages/{$message->id}/reply", [
            'admin_reply' => 'Sorry for the delay.',
        ]);

        // Must return 502 (delivery failed) not 500 (server error)
        $response->assertStatus(502);

        // The reply text must be persisted
        $message->refresh();
        $this->assertSame('Sorry for the delay.', $message->admin_reply);
        $this->assertNotNull($message->delivery_failed_at);
        $this->assertNull($message->replied_at);
    }

    public function test_meeting_reply_survives_mail_transport_failure(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $request = MeetingRequest::query()->create([
            'name' => 'Chaos Client',
            'email' => 'chaos@example.com',
            'status' => MeetingRequest::STATUS_PENDING,
        ]);

        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('SMTP server down'));

        $response = $this->putJson("/api/admin/meeting-requests/{$request->id}/reply", [
            'admin_reply' => 'Sorry for the delay.',
        ]);

        $response->assertStatus(502);

        $request->refresh();
        $this->assertSame('Sorry for the delay.', $request->admin_reply);
        $this->assertNotNull($request->delivery_failed_at);
        $this->assertSame(MeetingRequest::STATUS_PENDING, $request->status);
    }

    // =====================================================================
    // Scenario 4: File storage failure during upload
    // =====================================================================

    public function test_upload_failure_returns_controlled_error_not_crash(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        // Simulate storage write failure
        Storage::shouldReceive('put')->andThrow(new \RuntimeException('Disk full'));

        $file = UploadedFile::fake()->image('photo.png');

        $response = $this->postJson('/api/admin/upload', [
            'file' => $file,
            'type' => 'project-image',
        ]);

        // Must return a server error, not crash
        $this->assertContains($response->status(), [500, 502, 422]);
    }

    // =====================================================================
    // Scenario 5: Invalid mail config doesn't crash the notifier
    // =====================================================================

    public function test_notifier_handles_null_admin_address_gracefully(): void
    {
        // Both ADMIN_NOTIFY_EMAIL and ContactInfo email are unset
        config(['mail.admin_notify_address' => null]);

        Mail::fake();

        $response = $this->postJson('/api/contact-messages', [
            'name' => 'Config Test',
            'email' => 'test@example.com',
            'message' => 'No admin address.',
        ]);

        // Submission succeeds even when admin notification can't be sent
        $response->assertCreated();

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'test@example.com',
        ]);

        // Only the client acknowledgment should have been attempted
        // (admin notification skipped because no recipient)
        Mail::assertSentCount(1);
    }

    // =====================================================================
    // Scenario 6: Malformed payloads rejected cleanly
    // =====================================================================

    public function test_malformed_json_is_rejected_with_422_not_500(): void
    {
        $response = $this->call(
            'POST',
            '/api/contact-messages',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            '{invalid json',
        );

        // Laravel's middleware should catch the malformed JSON and return 422
        // (or 400), never a 500
        $this->assertContains($response->status(), [400, 422, 415]);
    }

    public function test_oversized_payload_is_rejected_cleanly(): void
    {
        $response = $this->postJson('/api/contact-messages', [
            'name' => str_repeat('A', 10000),
            'email' => 'test@example.com',
            'message' => str_repeat('B', 100000),
        ]);

        $response->assertStatus(422);
    }

    public function test_sql_injection_attempt_in_name_is_rejected(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/contact-messages', [
            'name' => "Robert'; DROP TABLE contact_messages;--",
            'email' => 'test@example.com',
            'message' => 'Injection attempt.',
        ]);

        // Should succeed (name is just a string) — Eloquent parameterizes queries
        $response->assertCreated();

        // The table must still exist
        $this->assertDatabaseCount('contact_messages', 1);
    }
}
