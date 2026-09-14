<?php

namespace Tests\Regression;

use App\Models\ContactMessage;
use App\Models\User;
use App\Services\ContactMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression guard for the contact reply delivery-status contract.
 *
 * The contact reply was built to the same standard as the meeting reply (which
 * had the 2026-08-25 false-success toast and the 2026-08-26 status-flip bugs).
 * This test pins the contact reply's equivalent guarantees so it cannot silently
 * regress to the same broken state.
 */
class ContactReplyRegressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression guard: a refused contact reply must NOT stamp replied_at.
     *
     * This is the contact equivalent of the meeting reply's delivery-status
     * bug (2026-08-26). If replied_at is set on failure, the inbox shows the
     * message as answered when the client was never reached.
     */
    public function test_refused_contact_reply_preserves_pending_state_and_text(): void
    {
        Mail::shouldReceive('to->send')->once()->andThrow(new \RuntimeException('refused'));

        $message = ContactMessage::query()->create([
            'name' => 'Regression Visitor',
            'email' => 'regression@example.com',
            'subject' => 'Regression test',
            'message' => 'Testing delivery failure.',
        ]);

        $result = app(ContactMessageService::class)->reply($message, 'Saved but not delivered.');

        $this->assertFalse($result['emailed']);
        $this->assertDatabaseHas('contact_messages', [
            'id' => $message->id,
            'admin_reply' => 'Saved but not delivered.',
            'replied_at' => null,
        ]);
        $this->assertNotNull($message->refresh()->delivery_failed_at);
    }

    /**
     * Regression guard: the API must expose contact reply failure as a non-2xx
     * response, not a 200 that the admin panel reads as success.
     *
     * This is the contact equivalent of the 2026-08-25 false-success toast.
     */
    public function test_refused_contact_reply_api_answers_502_not_200(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('refused'));
        Sanctum::actingAs(User::factory()->create());

        $message = ContactMessage::query()->create([
            'name' => 'Toast Visitor',
            'email' => 'toast@example.com',
            'message' => 'Testing false success.',
        ]);

        $response = $this->postJson("/api/admin/contact-messages/{$message->id}/reply", [
            'admin_reply' => 'This must show an error.',
        ]);

        $response->assertStatus(502);
        $this->assertStringContainsString('could not be delivered', $response->json('message'));
    }

    /**
     * Regression guard: a successful retry clears delivery_failed_at.
     *
     * If the stale failure marker persists after a successful retry, the
     * inbox keeps showing the red failure indicator on an already-answered
     * message.
     */
    public function test_successful_retry_clears_contact_delivery_failure(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $message = ContactMessage::query()->create([
            'name' => 'Retry Visitor',
            'email' => 'retry@example.com',
            'message' => 'Testing retry.',
        ]);

        // First attempt fails
        Mail::shouldReceive('to->send')->once()->andThrow(new \RuntimeException('refused'));

        $this->postJson("/api/admin/contact-messages/{$message->id}/reply", [
            'admin_reply' => 'First try.',
        ])->assertStatus(502);

        $this->assertNotNull($message->refresh()->delivery_failed_at);

        // Second attempt succeeds
        Mail::shouldReceive('to->send')->once()->andReturnNull();

        $retry = $this->postJson("/api/admin/contact-messages/{$message->id}/reply", [
            'admin_reply' => 'Second try.',
        ]);

        $retry->assertOk();
        $retry->assertJsonPath('data.delivery_failed_at', null);

        $message->refresh();
        $this->assertNull($message->delivery_failed_at);
        $this->assertSame('Second try.', $message->admin_reply);
        $this->assertNotNull($message->replied_at);
    }
}
